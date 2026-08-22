<?php
// Configuration des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Frais.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Vérification d'authentification
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

// Vérification de la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../?view=frais/paiement&error=methode_non_autorisee');
    exit;
}

// Récupération des paramètres
$action = isset($_POST['action']) ? $_POST['action'] : '';
$idAnneeAcad = isset($_POST['idAnneeAcad']) ? intval($_POST['idAnneeAcad']) : 0;
$selectedType = isset($_POST['type_paiement']) ? $_POST['type_paiement'] : 'academique';

// Initialisation des modèles
$universite = new Universite();
$fraisModel = new Frais();

// Si l'année académique n'est pas spécifiée, utiliser l'année en cours
if ($idAnneeAcad <= 0) {
    $currentYear = $universite->getCurrentAcademicYear();
    $idAnneeAcad = $currentYear['idannee_acad'];
}

// Traitement de l'action
if ($action === 'import') {
    // Vérification des paramètres requis
    $fraisId = isset($_POST['frais']) ? intval($_POST['frais']) : 0;
    $modePaiement = isset($_POST['mode_paiement']) ? $_POST['mode_paiement'] : '';
    $importType = isset($_POST['import_type']) ? $_POST['import_type'] : 'complet';
    $datePaiement = isset($_POST['date_paiement']) ? $_POST['date_paiement'] : date('Y-m-d');
    $prefixReference = isset($_POST['prefix_reference']) ? $_POST['prefix_reference'] : 'IMP-' . date('Ymd') . '-';
    
    // Pour les frais de soutenance, on récupère l'ID de l'étudiant
    $etudiantId = 0;
    if ($selectedType === 'soutenance') {
        // Vérifier si c'est une importation en masse
        $isBulkImport = isset($_POST['bulk_import']) && $_POST['bulk_import'] == 1;
        
        if ($isBulkImport) {
            // Récupérer la promotion pour l'importation en masse
            $promotionId = isset($_POST['promotion']) ? intval($_POST['promotion']) : 0;
            if ($promotionId <= 0) {
                header('Location: ../?view=frais/paiement_soutenance&tab=bulk&error=promotion_non_valide');
                exit;
            }
            $redirectPage = '?view=frais/paiement_soutenance&tab=bulk';
        } else {
            // Importation pour un étudiant spécifique
            $etudiantId = isset($_POST['etudiant_id']) ? intval($_POST['etudiant_id']) : 0;
            if ($etudiantId <= 0) {
                header('Location: ../?view=frais/paiement_soutenance&error=etudiant_non_valide');
                exit;
            }
            $redirectPage = '?view=frais/paiement_soutenance&etudiant=' . $etudiantId;
        }
    } else {
        $promotionId = isset($_POST['promotion']) ? intval($_POST['promotion']) : 0;
        $redirectPage = '?view=frais/paiement&type=' . $selectedType;
        
        // Vérification de la promotion pour les frais académiques
        if ($promotionId <= 0) {
            header('Location: ../' . $redirectPage . '&error=promotion_non_valide');
            exit;
        }
    }
    
    // Vérification du fichier importé
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        header('Location: ../' . $redirectPage . '&error=fichier_non_valide');
        exit;
    }

    // Récupération des informations du frais
    if ($selectedType == 'academique') {
        $frais = $fraisModel->getFraisById($fraisId);
    } else {
        $frais = $fraisModel->getFraisSoutenanceById($fraisId);
    }
    
    if (!$frais) {
        header('Location: ../' . $redirectPage . '&error=frais_non_trouve');
        exit;
    }
    
    try {
        // Chargement du fichier
        $inputFileType = IOFactory::identify($_FILES['import_file']['tmp_name']);
        $reader = IOFactory::createReader($inputFileType);
        $spreadsheet = $reader->load($_FILES['import_file']['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Récupération des données du fichier
        $importData = [];
        $highestRow = $worksheet->getHighestDataRow();
        
        
        // Déterminer les colonnes selon le type d'importation
        if ($importType === 'complet') {
            // Format: Matricule
            for ($row = 2; $row <= $highestRow; $row++) { // Commencer à 2 pour ignorer l'en-tête
                $matricule = trim($worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(1) . $row)->getValue());
                if (!empty($matricule)) {
                    $importData[] = [
                        'matricule' => $matricule,
                        'montant' => $frais['montant'], // Montant total du frais
                        'date' => $datePaiement
                    ];
                }
            }
        } else {
            // Format: Matricule, Montant, Date (optionnelle)
            for ($row = 2; $row <= $highestRow; $row++) { // Commencer à 2 pour ignorer l'en-tête
                $matricule = trim($worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(1) . $row)->getValue());
                $montant = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2) . $row)->getValue();
                $date = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3) . $row)->getValue();
                
                if (!empty($matricule) && is_numeric($montant)) {
                    $importData[] = [
                        'matricule' => $matricule,
                        'montant' => (float)$montant,
                        'date' => !empty($date) ? $date : $datePaiement
                    ];
                }
            }
        }

        
        // Validation: vérifier s'il y a des données à importer
        if (empty($importData)) {
            header('Location: ../' . $redirectPage . '&error=donnees_vides');
            exit;
        }
        
        // Traitement des paiements
        $compteurSucces = 0;
        $compteurEchec = 0;
        $erreurs = [];
        
        foreach ($importData as $index => $data) {
            // Pour les frais de soutenance avec étudiant spécifique
            if ($selectedType === 'soutenance') {
                // Rechercher l'étudiant par matricule
                $etudiant = $universite->getEtudiantByMatricule($data['matricule'], $idAnneeAcad);
                
                if (!$etudiant) {
                    $erreurs[] = "Ligne " . ($index + 2) . ": Étudiant avec matricule '{$data['matricule']}' non trouvé.";
                    $compteurEchec++;
                    continue;
                }

                // Vérifier que l'étudiant appartient à la promotion sélectionnée
                if ($etudiant['promotion_idpromotion'] != $promotionId) {
                    $erreurs[] = "Ligne " . ($index + 2) . ": L'étudiant '{$data['matricule']}' n'appartient pas à la promotion sélectionnée.";
                    $compteurEchec++;
                    continue;
                }
                
                // Enregistrer le paiement
                $reference = $prefixReference . ($index + 1);
                $commentaire = "Importé en masse le " . date('Y-m-d H:i:s');
                
                $succes = $fraisModel->enregistrerPaiementSoutenance(
                    $fraisId,
                    $etudiant['idetudiant'],
                    $data['montant'],
                    $reference,
                    $modePaiement,
                    $commentaire,
                    $idAnneeAcad,
                    $_SESSION['id']
                );
                
                if ($succes) {
                    $compteurSucces++;
                } else {
                    $erreurs[] = "Ligne " . ($index + 2) . ": Erreur lors de l'enregistrement du paiement pour '{$data['matricule']}'.";
                    $compteurEchec++;
                }
            } else {
                // Code existant pour les frais académiques
                // Rechercher l'étudiant par matricule
                $etudiant = $universite->getEtudiantByMatricule($data['matricule'], $idAnneeAcad);
                
                if (!$etudiant) {
                    $erreurs[] = "Ligne " . ($index + 2) . ": Étudiant avec matricule '{$data['matricule']}' non trouvé.";
                    $compteurEchec++;
                    continue;
                }

                // Vérifier que l'étudiant appartient à la promotion sélectionnée
                if ($selectedType === 'academique' && $etudiant['promotion_idpromotion'] != $promotionId) {
                    $erreurs[] = "Ligne " . ($index + 2) . ": L'étudiant '{$data['matricule']}' n'appartient pas à la promotion sélectionnée.";
                    $compteurEchec++;
                    continue;
                }
                
                // Créer une référence unique pour ce paiement
                $reference = $prefixReference . ($index + 1);
                
                // Enregistrer le paiement
                $commentaire = "Importé le " . date('Y-m-d H:i:s');
                
                $succes = false;
                if ($selectedType == 'academique') {
                    $succes = $fraisModel->enregistrerPaiement(
                        $etudiant['idetudiant'],
                        $fraisId,
                        $data['montant'],
                        $reference,
                        $modePaiement,
                        $commentaire,
                        $idAnneeAcad,
                        $_SESSION['id']
                    );
                } else {
                    $succes = $fraisModel->enregistrerPaiementSoutenance(
                        $fraisId,
                        $etudiant['idetudiant'],
                        $data['montant'],
                        $reference,
                        $modePaiement,
                        $commentaire,
                        $idAnneeAcad,
                        $_SESSION['id']
                    );
                }
                
                if ($succes) {
                    $compteurSucces++;
                } else {
                    $erreurs[] = "Ligne " . ($index + 2) . ": Erreur lors de l'enregistrement du paiement pour '{$data['matricule']}'.";
                    $compteurEchec++;
                }
            }
        }
        
        // Redirection avec résultat
        if ($compteurEchec > 0) {
            $_SESSION['import_errors'] = $erreurs;
            header('Location: ../' . $redirectPage . '&result=partiel&success=' . $compteurSucces . '&failed=' . $compteurEchec);
        } else {
            header('Location: ../' . $redirectPage . '&result=success&count=' . $compteurSucces);
        }
        exit;
        
    } catch (Exception $e) {
        // Log de l'erreur et redirection
        error_log("Erreur d'importation de paiements: " . $e->getMessage());
        header('Location: ../' . $redirectPage . '&error=erreur_importation&message=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    // Action non reconnue
    header('Location: ../?view=frais/paiement&error=action_non_reconnue');
    exit;
}
