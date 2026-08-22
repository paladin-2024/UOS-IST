<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // Pour PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Accès refusé',
                text: 'Vous devez être connecté pour effectuer cette action.'
            }).then(() => {
                window.location.href = '../login';
            });
        });
    </script>";
    exit;
}

// Récupérer les paramètres
$idEvaluation = isset($_POST['idevaluation']) ? intval($_POST['idevaluation']) : 0;
$idECUE = isset($_POST['idECUE']) ? intval($_POST['idECUE']) : 0;

if ($idEvaluation <= 0 || $idECUE <= 0) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Paramètres invalides.'
            }).then(() => {
                window.history.back();
            });
        });
    </script>";
    exit;
}

try {
    // Initialisation des modèles
    $ecueModel = new Ecue();
    $etudiantModel = new Etudiant();
    
    // Récupération de l'instance PDO
    $connexion = Connexion::getInstance();
    $pdo = $connexion->getPDO();
    
    // Récupérer les informations sur l'évaluation
    $query = "SELECT e.*, t.\"idType\", t.\"designationT\", s.idsession, s.\"designSession\", s.description as session_description 
             FROM evaluations e 
             JOIN typeevaluation t ON e.\"idType\" = t.\"idType\" 
             JOIN session s ON e.session_idsession = s.idsession 
             WHERE e.idevaluation = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$idEvaluation]);
    $evaluation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$evaluation) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Évaluation introuvable.'
                }).then(() => {
                    window.history.back();
                });
            });
        </script>";
        exit;
    }
    
    // Vérifier si un fichier a été téléchargé
    if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors du téléchargement du fichier.'
                }).then(() => {
                    window.history.back();
                });
            });
        </script>";
        exit;
    }
    
    // Charger le fichier Excel
    $inputFileName = $_FILES['excelFile']['tmp_name'];
    $spreadsheet = IOFactory::load($inputFileName);
    $worksheet = $spreadsheet->getActiveSheet();
    
    // Vérifier les métadonnées du fichier pour s'assurer qu'il s'agit du bon modèle
    $properties = $spreadsheet->getProperties();
    $fileToken = $properties->getCustomPropertyValue('FileToken') ?? '';
    $fileEvaluationId = $properties->getCustomPropertyValue('EvaluationId') ?? 0;
    $fileEcueId = $properties->getCustomPropertyValue('EcueId') ?? 0;
    
    // Vérification optionnelle des métadonnées
    if ($fileEvaluationId != $idEvaluation || $fileEcueId != $idECUE) {
        // Log cette situation mais continuer quand même (pour la flexibilité)
        error_log("Avertissement: Métadonnées du fichier Excel ne correspondent pas aux paramètres d'importation.");
    }
    
    // Récupérer la note maximale définie pour cette évaluation
    $noteMax = $evaluation['note_max'] ?? 20;
    
    // Récupérer l'année académique actuelle
    $query = "SELECT idannee_acad FROM annee_acad WHERE \"dateCreation\" = (SELECT MAX(\"dateCreation\") FROM annee_acad)";
$stmt = $pdo->prepare($query);
$stmt->execute();
$anneeAcad = $stmt->fetch(PDO::FETCH_ASSOC);
$anneeAcadId = $anneeAcad ? $anneeAcad['idannee_acad'] : 0;
    if ($anneeAcadId === 0) {
        throw new Exception("Impossible de déterminer l'année académique actuelle.");
    }
    
    // Récupérer les détails de l'ECUE pour obtenir l'UE et la promotion associées
    $query = "SELECT e.\"UE_idUE\", p.idpromotion 
             FROM ecue e 
             JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\" 
             JOIN semestre s ON u.semestre_idsemestre = s.idsemestre 
             JOIN promotion p ON s.promotion_idpromotion = p.idpromotion 
             WHERE e.\"idECUE\" = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$idECUE]);
    $ecueDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ecueDetails) {
        throw new Exception("Impossible de récupérer les détails de l'ECUE.");
    }
    
    // Récupérer les étudiants associés à cette promotion
    $query = "SELECT idetudiant, matricule, noms FROM etudiant 
             WHERE promotion_idpromotion = ? AND annee_acad_idannee_acad = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$ecueDetails['idpromotion'], $anneeAcadId]);
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Créer un mapping des matricules aux IDs étudiants
    $etudiantsByMatricule = [];
    foreach ($etudiants as $etudiant) {
        $etudiantsByMatricule[$etudiant['matricule']] = $etudiant['idetudiant'];
    }
    
    // Parcourir les lignes du fichier Excel pour récupérer les notes
    $highestRow = $worksheet->getHighestRow();
    $notes = [];
    $successCount = 0;
    $errorCount = 0;
    
    // Log pour débogage
    $debug_info = [];
    for ($debug_row = 17; $debug_row < min(22, $highestRow); $debug_row++) {
        $matricule = $worksheet->getCell('C' . $debug_row)->getValue();
        $noteValue = $worksheet->getCell('E' . $debug_row)->getValue();
        $debug_info[] = "Ligne $debug_row: Matricule='$matricule', Note='$noteValue'";
    }
    error_log("DEBUG IMPORT EXCEL: " . implode(" | ", $debug_info));
    error_log("MATRICULES ATTENDUS: " . implode(", ", array_keys($etudiantsByMatricule)));
    
    // Commencer à la ligne 17 (après les en-têtes et instructions)
    for ($row = 17; $row <= $highestRow; $row++) {
        $matricule = $worksheet->getCell('C' . $row)->getValue();
        $noteValue = $worksheet->getCell('E' . $row)->getValue();
        
        // Ne traiter que les lignes avec des matricules et des notes
        if (empty($matricule)) {
            continue; // Ignorer les lignes vides
        }
        
        // Vérifier si le matricule existe et si la note est valide
        if (isset($etudiantsByMatricule[$matricule]) && (is_numeric($noteValue) || $noteValue === null || $noteValue === '')) {
            $idEtudiant = $etudiantsByMatricule[$matricule];
            
            // Si la note est vide, la définir à NULL
            if ($noteValue === '' || $noteValue === null) {
                $notes[] = [
                    'idetudiant' => $idEtudiant,
                    'matricule' => $matricule,
                    'coteObtenu' => null
                ];
                $successCount++;
            } else {
                // Convertir et vérifier la plage de la note
                $note = floatval($noteValue);
                
                // Valider la note par rapport à la note maximale
                if ($note >= 0 && $note <= $noteMax) {
                    $notes[] = [
                        'idetudiant' => $idEtudiant,
                        'matricule' => $matricule,
                        'coteObtenu' => $note
                    ];
                    $successCount++;
                } else {
                    error_log("Note invalide pour $matricule: $note (doit être entre 0 et $noteMax)");
                    $errorCount++;
                }
            }
        } else if (!empty($matricule)) {
            error_log("Matricule invalide ou introuvable: $matricule");
            $errorCount++;
        }
    }
    
    // Enregistrer les notes dans la base de données
    if (!empty($notes)) {
        // Démarrer une transaction
        $pdo->beginTransaction();
        
        // Préparation des requêtes
        $sqlCheckExist = "SELECT COUNT(*) FROM points 
                        WHERE matricule = ? AND \"ECUE_idECUE\" = ? 
                        AND typeEvaluation = ? AND session_idsession = ? 
                        AND annee_acad_id = ?";
        $stmtCheckExist = $pdo->prepare($sqlCheckExist);
        
        $sqlInsert = "INSERT INTO points (\"coteObtenu\", typeEvaluation, \"ECUE_idECUE\", 
                     session_idsession, matricule, annee_acad_id) 
                     VALUES (?, ?, ?, ?, ?, ?)";
        $stmtInsert = $pdo->prepare($sqlInsert);
        
        $sqlUpdate = "UPDATE points SET \"coteObtenu\" = ? 
                     WHERE matricule = ? AND \"ECUE_idECUE\" = ? 
                     AND typeEvaluation = ? AND session_idsession = ? 
                     AND annee_acad_id = ?";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        
        // Pour chaque note, vérifier si elle existe déjà puis insérer/mettre à jour
        $savedCount = 0;
        foreach ($notes as $noteData) {
            $matricule = $noteData['matricule'];
            $coteObtenu = $noteData['coteObtenu'];
            
            // Vérifier si la note existe déjà
            $stmtCheckExist->execute([
                $matricule, 
                $idECUE, 
                $evaluation['idType'], 
                $evaluation['idsession'], 
                $anneeAcadId
            ]);
            $exists = $stmtCheckExist->fetchColumn() > 0;
            
            if ($exists) {
                // Mettre à jour la note existante
                $result = $stmtUpdate->execute([
                    $coteObtenu,
                    $matricule, 
                    $idECUE, 
                    $evaluation['idType'], 
                    $evaluation['idsession'], 
                    $anneeAcadId
                ]);
            } else {
                // Insérer une nouvelle note
                $result = $stmtInsert->execute([
                    $coteObtenu,
                    $evaluation['idType'], 
                    $idECUE, 
                    $evaluation['idsession'], 
                    $matricule, 
                    $anneeAcadId
                ]);
            }
            
            if ($result) {
                $savedCount++;
            }
        }
        
        // Valider la transaction
        $pdo->commit();
    } else {
        $savedCount = 0;
    }
    
    // Message de succès
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                html: 'Importation réussie. " . $savedCount . " notes ont été importées.<br><br>" .
                     "<strong>Détails:</strong><br>" .
                     "- Total d\'étudiants: " . count($etudiants) . "<br>" .
                     "- Notes valides: " . $successCount . "<br>" .
                     "- Notes enregistrées: " . $savedCount . "<br>" .
                     "- Erreurs: " . $errorCount . "'
            }).then(() => {
                window.location.href = '../?view=enseignement/evaluations&ecue=" . $idECUE . "';
            });
        });
    </script>";
    
} catch (Exception $e) {
    // En cas d'erreur, annuler la transaction si elle est en cours
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log l'erreur pour le débogage
    error_log("Erreur lors de l'importation des notes: " . $e->getMessage());
    
    // Afficher un message d'erreur à l'utilisateur
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'importation: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.history.back();
            });
        });
    </script>";
}

