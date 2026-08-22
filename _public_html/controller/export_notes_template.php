<?php
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Enseignant.php';
require_once dirname(__DIR__) . '/views/405.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

// Vérifier la session
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index');
    exit();
}

// Vérifier les données POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['idevaluation']) || !isset($_POST['idECUE'])) {
    die("Requête invalide");
}

$evaluationId = intval($_POST['idevaluation']);
$ecueId = intval($_POST['idECUE']);
$userId = $_SESSION['id'];

// Récupérer l'instance de connexion
$connexion = Connexion::getInstance();
$pdo = $connexion->getPDO();

// Initialiser les modèles
$ecue = new Ecue();
$universite = new Universite();
$enseignant = new Enseignant();

// Vérifier que l'utilisateur est un enseignant
if (!$enseignant->isUserEnseignant($userId)) {
    die("Accès non autorisé");
}

// Récupérer l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();
if (!$currentYear) {
    die("Impossible de déterminer l'année académique actuelle");
}

// Récupérer l'ID de l'agent (enseignant)
$idEnseignant = $enseignant->getAgentIdByUserId($userId);
if (!$idEnseignant) {
    die("Impossible de récupérer les informations de l'enseignant");
}

// Vérifier si l'enseignant est autorisé à accéder à cet ECUE
$isAuthorized = $enseignant->isEnseignantAssignedToEcue($idEnseignant, $ecueId, $currentYear['idannee_acad']);
if (!$isAuthorized) {
    die("Vous n'êtes pas autorisé à accéder à ce cours");
}

// Récupérer les détails de l'évaluation
try {
    $sql = "SELECT e.*, t.\"designationT\", t.categorie, s.\"designSession\", s.description AS session_description
            FROM evaluations e
            INNER JOIN typeevaluation t ON e.\"idType\" = t.\"idType\"
            INNER JOIN session s ON e.session_idsession = s.idsession
            WHERE e.idevaluation = ? AND e.\"idECUE\" = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$evaluationId, $ecueId]);
    $evaluation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$evaluation) {
        die("Évaluation non trouvée ou non associée à cet ECUE");
    }
} catch (PDOException $e) {
    die("Erreur lors de la récupération de l'évaluation: " . $e->getMessage());
}

// Vérifier si c'est une évaluation de deuxième session
$isDeuxiemeSession = mb_strpos(mb_strtolower($evaluation['designSession']), 'deuxième') !== false ||
                     mb_strpos(mb_strtolower($evaluation['designSession']), 'deuxieme') !== false ||
                     mb_strpos(mb_strtolower($evaluation['session_description']), 'deuxième') !== false ||
                     mb_strpos(mb_strtolower($evaluation['session_description']), 'deuxieme') !== false;

// Récupérer les détails de l'ECUE
try {
    $sql = "SELECT e.*, u.\"designationUE\", u.\"codeUE\", s.\"numeroSemestre\", p.\"designationPromotion\", o.\"designationOrientation\"
            FROM ecue e
            INNER JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
            INNER JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
            INNER JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
            INNER JOIN orientation o ON p.orientation_idorientation = o.idorientation
            WHERE e.\"idECUE\" = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ecueId]);
    $ecueDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ecueDetails) {
        die("ECUE non trouvé");
    }
} catch (PDOException $e) {
    die("Erreur lors de la récupération des détails de l'ECUE: " . $e->getMessage());
}

// Récupérer la liste des étudiants inscrits à ce cours
// Récupérer la liste des étudiants inscrits à ce cours
try {
    if ($isDeuxiemeSession) {
        // 1. Récupérer l'UE associée à cet ECUE
        $sqlUE = "SELECT e.\"UE_idUE\" FROM ecue e WHERE e.\"idECUE\" = ?";
        $stmtUE = $pdo->prepare($sqlUE);
        $stmtUE->execute([$ecueId]);
        $idUE = $stmtUE->fetchColumn();
        
        if (!$idUE) {
            die("Impossible de récupérer l'UE associée à cet ECUE");
        }
        
        // 2. Récupérer l'ID de la première session
        $sqlSession = "SELECT idsession FROM session
                     WHERE LOWER(\"designSession\") LIKE 'premi%re session'
                     OR LOWER(\"designSession\") = 'premiere session' LIMIT 1";
        $stmtSession = $pdo->prepare($sqlSession);
        $stmtSession->execute();
        $session1Id = $stmtSession->fetchColumn();
        
        if (!$session1Id) {
            die("Impossible de déterminer la première session");
        }
        
        // 3. Récupérer la promotion associée à cet ECUE
        $sqlPromotion = "SELECT p.idpromotion
                       FROM ecue e
                       JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                       JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                       JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                       WHERE e.\"idECUE\" = ?";
        $stmtPromotion = $pdo->prepare($sqlPromotion);
        $stmtPromotion->execute([$ecueId]);
        $promotionId = $stmtPromotion->fetchColumn();
        
        if (!$promotionId) {
            die("Impossible de déterminer la promotion associée à cet ECUE");
        }
        
        // 4. Vérifier si des notes existent pour cet ECUE en première session
        $sqlNoteCount = "SELECT COUNT(*) FROM cotes_grille
                       WHERE \"ECUE_idECUE\" = ?
                       AND session_idsession = ?
                       AND annee_acad_id = ?";
        $stmtNoteCount = $pdo->prepare($sqlNoteCount);
        $stmtNoteCount->execute([$ecueId, $session1Id, $currentYear['idannee_acad']]);
        $notesExist = ($stmtNoteCount->fetchColumn() > 0);
        
        // 5. Récupérer les étudiants qui ont échoué à cet ECUE
        $sqlFailed = "SELECT e.idetudiant, e.matricule, e.noms 
                    FROM etudiant e 
                    LEFT JOIN cotes_grille cg ON e.matricule = cg.matricule
                                              AND cg.\"ECUE_idECUE\" = ?
                                              AND cg.session_idsession = ?
                                              AND cg.annee_acad_id = ?
                    WHERE e.promotion_idpromotion = ?
                    AND e.annee_acad_idannee_acad = ?
                    AND (cg.\"MF\" IS NULL OR cg.\"MF\" < 10 OR cg.\"CC\" IS NULL OR cg.\"EX\" IS NULL)
                    ORDER BY e.noms";
        $stmtFailed = $pdo->prepare($sqlFailed);
        $stmtFailed->execute([$ecueId, $session1Id, $currentYear['idannee_acad'], $promotionId, $currentYear['idannee_acad']]);
        $failedStudents = $stmtFailed->fetchAll(PDO::FETCH_ASSOC);
        
        // Si aucun étudiant n'a échoué et qu'aucune note n'existe
        if (empty($failedStudents) && !$notesExist) {
            // Aucune note n'existe, récupérer tous les étudiants de la promotion
            $sqlAll = "SELECT e.idetudiant, e.matricule, e.noms 
                      FROM etudiant e 
                      WHERE e.promotion_idpromotion = ? 
                      AND e.annee_acad_idannee_acad = ? 
                      ORDER BY e.noms";
            $stmtAll = $pdo->prepare($sqlAll);
            $stmtAll->execute([$promotionId, $currentYear['idannee_acad']]);
            $etudiants = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
        } else if (empty($failedStudents) && $notesExist) {
            // Des notes existent mais aucun étudiant n'a échoué
            $etudiants = [];
        } else {
            // 6. Filtrer les étudiants qui ont validé l'UE malgré l'échec à cet ECUE
            $etudiants = [];
            
            foreach ($failedStudents as $student) {
                $matricule = $student['matricule'];
                
                // Vérifier si l'UE a été validée en première session
                $sqlUEValidation = "SELECT
                                  SUM(cg.\"MF\" * ROUND((ec.\"CMI\" + ec.\"TD\" + ec.\"TP\")/15, 2)) /
                                  SUM(ROUND((ec.\"CMI\" + ec.\"TD\" + ec.\"TP\")/15, 2)) AS moyenne_ponderee,
                                  COUNT(cg.\"MF\") AS notes_count,
                                  (SELECT COUNT(*) FROM ecue WHERE \"UE_idUE\" = ?) AS total_ecues
                                FROM cotes_grille cg
                                JOIN ecue ec ON cg.\"ECUE_idECUE\" = ec.\"idECUE\"
                                WHERE ec.\"UE_idUE\" = ?
                                AND cg.matricule = ?
                                AND cg.session_idsession = ?
                                AND cg.annee_acad_id = ? 
                                AND cg.MF IS NOT NULL";
                $stmtUEValidation = $pdo->prepare($sqlUEValidation);
                $stmtUEValidation->execute([$idUE, $idUE, $matricule, $session1Id, $currentYear['idannee_acad']]);
                $ueResult = $stmtUEValidation->fetch(PDO::FETCH_ASSOC);
                
                // L'UE est validée si la moyenne pondérée est >= 10 ET toutes les ECUEs ont des notes
                $ueValidated = false;
                if ($ueResult &&
                    $ueResult['moyenne_ponderee'] !== null &&
                    $ueResult['moyenne_ponderee'] >= 10 &&
                    $ueResult['notes_count'] == $ueResult['total_ecues']) {
                    $ueValidated = true;
                }
                
                // Si l'UE n'est pas validée, l'étudiant est éligible pour la deuxième session
                if (!$ueValidated) {
                    $etudiants[] = $student;
                }
            }
        }
    } else {
        // Pour la première session, récupérer tous les étudiants inscrits
        // Récupérer la promotion associée à cet ECUE
        $sqlPromotion = "SELECT p.idpromotion
                       FROM ecue e
                       JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                       JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                       JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                       WHERE e.\"idECUE\" = ?";
        $stmtPromotion = $pdo->prepare($sqlPromotion);
        $stmtPromotion->execute([$ecueId]);
        $promotionId = $stmtPromotion->fetchColumn();
        
        if (!$promotionId) {
            die("Impossible de déterminer la promotion associée à cet ECUE");
        }
        
        // Récupérer tous les étudiants de cette promotion
        $sql = "SELECT e.idetudiant, e.matricule, e.noms 
                FROM etudiant e 
                WHERE e.promotion_idpromotion = ? 
                AND e.annee_acad_idannee_acad = ? 
                ORDER BY e.noms";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$promotionId, $currentYear['idannee_acad']]);
        $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Erreur lors de la récupération des étudiants: " . $e->getMessage());
}


// Si c'est une deuxième session et aucun étudiant n'est éligible
if ($isDeuxiemeSession && empty($etudiants)) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        Swal.fire({
            icon: 'warning',
            title: 'Aucun étudiant éligible',
            text: 'Aucun étudiant n\'est éligible pour cette évaluation de deuxième session. Tous les étudiants ont validé l\'UE en première session.'
        }).then(() => {
            window.history.back();
        });
    </script>";
    exit();
}

if (empty($etudiants)) {
    die("Aucun étudiant inscrit à ce cours ou éligible pour cette session");
}

// Récupérer les notes existantes pour cette évaluation
try {
    $sql = "SELECT p.matricule, p.\"coteObtenu\"
            FROM points p
            WHERE p.typeEvaluation = ?
            AND p.\"ECUE_idECUE\" = ?
            AND p.session_idsession = ?
            AND p.annee_acad_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$evaluation['idType'], $ecueId, $evaluation['session_idsession'], $currentYear['idannee_acad']]);
    $existingNotes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des notes existantes: " . $e->getMessage());
}

// Créer un nouveau classeur
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Notes');

// Générer un identifiant unique pour le fichier (pour éviter la falsification)
$fileToken = md5($evaluationId . '_' . $ecueId . '_' . time());

// Ajouter des métadonnées pour la validation ultérieure
$spreadsheet->getProperties()
    ->setCreator('Système de Gestion Universitaire')
    ->setLastModifiedBy('Système de Gestion Universitaire')
    ->setTitle('Modèle de notes - ' . $evaluation['titre'])
    ->setSubject('Notes pour ' . $ecueDetails['designationECUE'])
    ->setDescription('Modèle pour l\'importation des notes')
    ->setKeywords('notes, évaluation, importation')
    ->setCategory('Notes')
    ->setCustomProperty('FileToken', $fileToken)
    ->setCustomProperty('EvaluationId', $evaluationId)
    ->setCustomProperty('EcueId', $ecueId)
    ->setCustomProperty('DateGeneration', date('Y-m-d H:i:s'));

// En-tête du fichier Excel
$sheet->setCellValue('A1', 'MODÈLE POUR L\'IMPORTATION DES NOTES');
$sheet->mergeCells('A1:E1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Informations sur le cours et l'évaluation
$sheet->setCellValue('A3', 'Cours:');
$sheet->setCellValue('B3', $ecueDetails['designationECUE']);
$sheet->setCellValue('A4', 'Évaluation:');
$sheet->setCellValue('B4', $evaluation['titre']);
$sheet->setCellValue('A5', 'Type:');
$sheet->setCellValue('B5', $evaluation['designationT']);
$sheet->setCellValue('A6', 'Date:');
$sheet->setCellValue('B6', date('d/m/Y', strtotime($evaluation['date_evaluation'])));
$sheet->setCellValue('A7', 'Session:');
$sheet->setCellValue('B7', $evaluation['designSession']);
$sheet->setCellValue('A8', 'Note maximale:');
$sheet->setCellValue('B8', $evaluation['note_max']);
$sheet->getStyle('A3:A8')->getFont()->setBold(true);

// Instruction
$sheet->setCellValue('A10', 'INSTRUCTIONS:');
$sheet->getStyle('A10')->getFont()->setBold(true);
$sheet->setCellValue('A11', '1. Veuillez saisir les notes des étudiants dans la colonne "Note /' . $evaluation['note_max'] . '".');
$sheet->setCellValue('A12', '2. Les notes doivent être comprises entre 0 et ' . $evaluation['note_max'] . '.');
$sheet->setCellValue('A13', '3. Ne modifiez pas la structure du fichier, notamment les colonnes ID et Matricule.');
$sheet->setCellValue('A14', '4. Enregistrez le fichier et importez-le dans le système.');
$sheet->mergeCells('A11:E11');
$sheet->mergeCells('A12:E12');
$sheet->mergeCells('A13:E13');
$sheet->mergeCells('A14:E14');

// En-tête du tableau
$sheet->setCellValue('A16', 'N°');
$sheet->setCellValue('B16', 'ID Étudiant');
$sheet->setCellValue('C16', 'Matricule');
$sheet->setCellValue('D16', 'Nom de l\'étudiant');
$sheet->setCellValue('E16', 'Note (/' . $evaluation['note_max'] . ')');
$sheet->getStyle('A16:E16')->getFont()->setBold(true);
$sheet->getStyle('A16:E16')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
$sheet->getStyle('A16:E16')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Données des étudiants
$row = 17;
foreach ($etudiants as $index => $etudiant) {
    $sheet->setCellValue('A' . $row, $index + 1);
    $sheet->setCellValue('B' . $row, $etudiant['idetudiant']);
    $sheet->setCellValue('C' . $row, $etudiant['matricule']);
    $sheet->setCellValue('D' . $row, $etudiant['noms']);
    
    // Ajouter la note existante si elle existe
    if (isset($existingNotes[$etudiant['matricule']])) {
        $sheet->setCellValue('E' . $row, $existingNotes[$etudiant['matricule']]);
    }
    
    $row++;
}

// Ajouter une validation des données pour les notes
$lastRow = $row - 1;
$validation = $sheet->getCell('E17')->getDataValidation();
$validation->setType(DataValidation::TYPE_DECIMAL);
$validation->setErrorStyle(DataValidation::STYLE_STOP);
$validation->setAllowBlank(true);
$validation->setShowErrorMessage(true);
$validation->setErrorTitle('Erreur de saisie');
$validation->setError('La note doit être un nombre entre 0 et ' . $evaluation['note_max']);
$validation->setFormula1('0');
$validation->setFormula2($evaluation['note_max']);
$sheet->setDataValidation('E17:E' . $lastRow, $validation);

// Ajouter une bordure au tableau
$styleArray = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
];
$sheet->getStyle('A16:E' . $lastRow)->applyFromArray($styleArray);

// Ajuster la largeur des colonnes
$sheet->getColumnDimension('A')->setWidth(5);
$sheet->getColumnDimension('B')->setWidth(12);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->getColumnDimension('D')->setWidth(40);
$sheet->getColumnDimension('E')->setWidth(15);

// Centrer les notes et les numéros
$sheet->getStyle('A17:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('E17:E' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Ajouter une ligne invisible avec le token de sécurité et les métadonnées
$sheet->setCellValue('G1', 'METADATA');
$sheet->setCellValue('G2', 'FileToken');
$sheet->setCellValue('H2', $fileToken);
$sheet->setCellValue('G3', 'EvaluationId');
$sheet->setCellValue('H3', $evaluationId);
$sheet->setCellValue('G4', 'EcueId');
$sheet->setCellValue('H4', $ecueId);
$sheet->setCellValue('G5', 'SessionId');
$sheet->setCellValue('H5', $evaluation['session_idsession']);
$sheet->setCellValue('G6', 'AnneeId');
$sheet->setCellValue('H6', $currentYear['idannee_acad']);
$sheet->setCellValue('G7', 'NoteMax');
$sheet->setCellValue('H7', $evaluation['note_max']);
// Cacher les métadonnées
$sheet->getColumnDimension('G')->setVisible(false);
$sheet->getColumnDimension('H')->setVisible(false);

// Activer la protection de la feuille mais autoriser la modification des notes
$sheet->getProtection()->setSheet(true);
$sheet->getProtection()->setPassword('sgu_secured');
$sheet->getStyle('E17:E' . $lastRow)->getProtection()->setLocked(false);

// Créer le nom du fichier
$fileName = 'Modele_Notes_' . preg_replace('/[^a-zA-Z0-9]/', '_', $evaluation['titre']) . '_' . date('Ymd_His') . '.xlsx';

// Définir les en-têtes de réponse pour le téléchargement
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

// Créer le writer pour la sortie
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
