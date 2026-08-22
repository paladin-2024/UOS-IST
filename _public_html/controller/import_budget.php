<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // Pour PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login.php');
    exit;
}

$exercice_id = isset($_POST['exercice_id']) ? intval($_POST['exercice_id']) : 0;
$ecraser = isset($_POST['ecraser']) && $_POST['ecraser'] == 1;
$idUser = $_SESSION['id'];

if (!$exercice_id) {
    $_SESSION['message'] = "ID de l'exercice manquant.";
    $_SESSION['messageType'] = "danger";
    header('Location: ../?view=finance/config_budget');
    exit;
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Vérifier si l'exercice est clôturé
    $stmt = $connexion->prepare("SELECT est_cloture FROM exercices_budgetaires WHERE id = :id");
    $stmt->bindParam(':id', $exercice_id);
    $stmt->execute();
    $exercice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($exercice && $exercice['est_cloture']) {
        throw new Exception("Impossible de modifier un budget dans un exercice clôturé");
    }
    
    // Vérifier si un fichier a été uploadé
    if (!isset($_FILES['fichier_import']) || $_FILES['fichier_import']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Erreur lors du téléchargement du fichier");
    }
    
    $file = $_FILES['fichier_import']['tmp_name'];
    $extension = pathinfo($_FILES['fichier_import']['name'], PATHINFO_EXTENSION);
    
    // Charger le fichier avec PhpSpreadsheet
    if ($extension == 'csv') {
        $reader = IOFactory::createReader('Csv');
    } elseif ($extension == 'xlsx') {
        $reader = IOFactory::createReader('Xlsx');
    } elseif ($extension == 'xls') {
        $reader = IOFactory::createReader('Xls');
    } else {
        throw new Exception("Format de fichier non supporté. Veuillez utiliser CSV, XLS ou XLSX.");
    }
    
    $spreadsheet = $reader->load($file);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();
    
    // Vérifier que le fichier a des données
    if (count($rows) <= 1) {
        throw new Exception("Le fichier est vide ou ne contient que les en-têtes");
    }
    
    // Récupérer les en-têtes
    $headers = array_shift($rows);
    
    // Vérifier les en-têtes requis
    $requiredHeaders = ['Code', 'Montant Prévu'];
    foreach ($requiredHeaders as $requiredHeader) {
        if (!in_array($requiredHeader, $headers)) {
            throw new Exception("En-tête manquant: $requiredHeader");
        }
    }
    
    // Récupérer les index des colonnes
    $codeIndex = array_search('Code', $headers);
    $montantPrevuIndex = array_search('Montant Prévu', $headers);
    $montantReviseIndex = array_search('Montant Révisé', $headers);
    $commentaireIndex = array_search('Commentaire', $headers);
    
    // Récupérer toutes les catégories pour faire la correspondance par code
    $stmt = $connexion->query("
        SELECT id, code 
        FROM categories_budget 
        WHERE est_actif = 1
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Commencer une transaction
    $connexion->beginTransaction();
    
    // Si on doit écraser les données existantes, supprimer d'abord
    if ($ecraser) {
        $stmt = $connexion->prepare("DELETE FROM budget WHERE exercice_id = :exercice_id");
        $stmt->bindParam(':exercice_id', $exercice_id);
        $stmt->execute();
    }
    
    // Préparer les requêtes
    $insertSql = "INSERT INTO budget (
                    exercice_id, categorie_id, montant_prevu, montant_revise,
                    disponible, commentaire, \"idUser\"
                ) VALUES (
                    :exercice_id, :categorie_id, :montant_prevu, :montant_revise,
                    :disponible, :commentaire, :idUser
                )";
    
    $updateSql = "UPDATE budget SET 
                    montant_prevu = :montant_prevu,
                    montant_revise = :montant_revise,
                    disponible = :disponible,
                    commentaire = :commentaire
                WHERE exercice_id = :exercice_id AND categorie_id = :categorie_id";
    
    $insertStmt = $connexion->prepare($insertSql);
    $updateStmt = $connexion->prepare($updateSql);
    
    $count = 0;
    $errors = [];
    
    // Traiter chaque ligne
    foreach ($rows as $row) {
        $code = trim($row[$codeIndex]);
        $montantPrevu = floatval(str_replace([' ', ','], ['', '.'], $row[$montantPrevuIndex]));
        $montantRevise = $montantReviseIndex !== false ? 
            (empty($row[$montantReviseIndex]) ? null : floatval(str_replace([' ', ','], ['', '.'], $row[$montantReviseIndex]))) : null;
        $commentaire = $commentaireIndex !== false ? trim($row[$commentaireIndex]) : null;
        
        // Vérifier si le code existe
        if (!isset($categories[$code])) {
            $errors[] = "Code non trouvé: $code";
            continue;
        }
        
        $categorieId = $categories[$code];
        $disponible = $montantRevise !== null ? $montantRevise : $montantPrevu;
        
        // Vérifier si un budget existe déjà pour cette catégorie
        $stmt = $connexion->prepare("
            SELECT id FROM budget 
            WHERE exercice_id = :exercice_id AND categorie_id = :categorie_id
        ");
        $stmt->bindParam(':exercice_id', $exercice_id);
        $stmt->bindParam(':categorie_id', $categorieId);
        $stmt->execute();
        $budgetExists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($budgetExists && !$ecraser) {
            // Mise à jour
            $updateStmt->bindParam(':exercice_id', $exercice_id);
            $updateStmt->bindParam(':categorie_id', $categorieId);
            $updateStmt->bindParam(':montant_prevu', $montantPrevu);
            $updateStmt->bindParam(':montant_revise', $montantRevise);
            $updateStmt->bindParam(':disponible', $disponible);
            $updateStmt->bindParam(':commentaire', $commentaire);
            $updateStmt->execute();
        } else {
            // Insertion
            $insertStmt->bindParam(':exercice_id', $exercice_id);
            $insertStmt->bindParam(':categorie_id', $categorieId);
            $insertStmt->bindParam(':montant_prevu', $montantPrevu);
            $insertStmt->bindParam(':montant_revise', $montantRevise);
            $insertStmt->bindParam(':disponible', $disponible);
            $insertStmt->bindParam(':commentaire', $commentaire);
            $insertStmt->bindParam(':idUser', $idUser);
            $insertStmt->execute();
        }
        
        $count++;
    }
    
    // Valider ou annuler la transaction
    if (empty($errors)) {
        $connexion->commit();
        $_SESSION['message'] = "Import réussi : $count lignes traitées.";
        $_SESSION['messageType'] = "success";
    } else {
        $connexion->rollBack();
        $_SESSION['message'] = "Erreurs lors de l'import : " . implode(", ", $errors);
        $_SESSION['messageType'] = "warning";
    }
    
} catch (Exception $e) {
    if (isset($connexion) && $connexion->inTransaction()) {
        $connexion->rollBack();
    }
    
    $_SESSION['message'] = "Erreur lors de l'importation du budget: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

// Redirection vers la page de configuration du budget
header('Location: ../?view=finance/config_budget&exercice_id=' . $exercice_id);
exit;
