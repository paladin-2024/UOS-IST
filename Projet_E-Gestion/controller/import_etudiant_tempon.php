<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['importPrepStudentBtn'])) {
    // Vérifier si un fichier a été téléchargé
    if (!isset($_FILES['importPrepFile']) || $_FILES['importPrepFile']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Erreur lors du téléchargement du fichier.";
        header('Location: ../etudiants/etudiant.inscrit');
        exit;
    }

    // Récupérer les paramètres du formulaire
    $startRow = isset($_POST['startRow']) ? intval($_POST['startRow']) : 2;
    $matriculeColumn = isset($_POST['matriculeColumn']) ? intval($_POST['matriculeColumn']) : 1;
    $nomsColumn = isset($_POST['nomsColumn']) ? intval($_POST['nomsColumn']) : 2;
    $anneeId = isset($_POST['importPrepIdAnnee']) ? intval($_POST['importPrepIdAnnee']) : 0;
    $promotion_designation = isset($_POST['promotion_designation']) ? $_POST['promotion_designation'] : 'PRÉPARATOIRE';

    if ($anneeId <= 0) {
        $_SESSION['error'] = "Veuillez sélectionner une année académique valide.";
        header('Location: ../etudiants/etudiant.inscrit');
        exit;
    }

    try {
        // Charger le fichier Excel
        $spreadsheet = IOFactory::load($_FILES['importPrepFile']['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();

        // Initialiser la connexion à la base de données
        $universite = new Universite();
        $db = Connexion::getInstance()->getPDO();
        
        // Préparer les requêtes de vérification et d'insertion
        $checkQuery = "SELECT COUNT(*) FROM etudiant WHERE matricule = :matricule UNION SELECT COUNT(*) FROM etudiant_tempon WHERE matricule = :matricule";
        $checkStmt = $db->prepare($checkQuery);

        $query = "INSERT INTO etudiant_tempon (matricule, noms, sexe, nationalite, dateEnregistrement, annee_acad_idannee_acad, promotion_designation, idUser)
                  VALUES (:matricule, :noms, 'Non défini', 'Non défini', NOW(), :anneeId, :promotion_designation, :idUser)";
        $stmt = $db->prepare($query);

        $idUser = isset($_SESSION['id']) ? $_SESSION['id'] : 0;
        $importCount = 0;
        $errorCount = 0;

        // Parcourir les lignes du fichier Excel
        for ($row = $startRow; $row <= $highestRow; $row++) {
            $matricule = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($matriculeColumn) . $row)->getValue();
            $noms = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($nomsColumn) . $row)->getValue();

            // Vérifier si les données essentielles sont présentes
            if (empty($noms)) {
                $errorCount++;
                continue;
            }

            // Générer un matricule si non fourni ou vérifier l'unicité
            if (empty($matricule)) {
                $matricule = "PREP-" . str_pad($importCount + 1, 8, '0', STR_PAD_LEFT);
            }

            // Vérifier l'unicité du matricule
            $checkStmt->bindParam(':matricule', $matricule);
            $checkStmt->execute();
            $results = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
            $totalOccurrences = array_sum($results);

            if ($totalOccurrences > 0) {
                $errorCount++;
                continue; // Skip this entry due to duplicate matricule
            }

            // Insérer l'étudiant dans la table temporaire
            $stmt->bindParam(':matricule', $matricule);
            $stmt->bindParam(':noms', $noms);
            $stmt->bindParam(':anneeId', $anneeId);
            $stmt->bindParam(':promotion_designation', $promotion_designation);
            $stmt->bindParam(':idUser', $idUser);

            if ($stmt->execute()) {
                $importCount++;
            } else {
                $errorCount++;
            }
        }

        if ($importCount > 0) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: '$importCount étudiants préparatoires importés avec succès. $errorCount erreurs rencontrées.'
                }).then(() => {
                    window.location.href = '../etudiants/etudiant.inscrit';
                });
              </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Aucun étudiant importé. Veuillez vérifier votre fichier.'
                }).then(() => {
                    window.location.href = '../etudiants/etudiant.inscrit';
                });
              </script>";
        }
    } catch (Exception $e) {
        echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: '" . addslashes($e->getMessage()) . "'
                }).then(() => {
                    window.location.href = '../etudiants/etudiant.inscrit';
                });
              </script>";
    }

    header('Location: ../etudiants/etudiant.inscrit');
    exit;
}
// Redirection par défaut
header('Location: ../etudiants/etudiant.inscrit');
exit;
