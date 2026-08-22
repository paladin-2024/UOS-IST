<?php
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Universite.php';

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
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ecueId']) || !isset($_POST['sessionId']) || !isset($_POST['anneeId']) || !isset($_POST['promotionId'])) {
    die("Requête invalide");
}

$ecueId = intval($_POST['ecueId']);
$sessionId = intval($_POST['sessionId']);
$anneeId = intval($_POST['anneeId']);
$promotionId = intval($_POST['promotionId']);
$userId = $_SESSION['id'];

// Initialiser les modèles
$ecue = new Ecue();
$universite = new Universite();

$currentYear=$anneeId;

// Vérifier si l'utilisateur est autorisé (administrateur ou membre du jury)
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
if (!$isAdmin) {
    // Vérifier si l'utilisateur est membre du jury pour cette promotion
    $agent = new Agent();
    $agentId = $agent->getAgentIdByUserId($userId);
    $isJuryMember = false;
    
    if ($agentId) {
        $juryBureaux = $universite->getJuryBureauxByAgent($agentId);
        foreach ($juryBureaux as $jury) {
            $promotions = $universite->getPromotionsByJury($jury['idbureau']);
            foreach ($promotions as $promotion) {
                if ($promotion['idpromotion'] == $promotionId) {
                    $isJuryMember = true;
                    break 2;
                }
            }
        }
    }
    
    if (!$isJuryMember) {
        die("Accès non autorisé");
    }
}

// Récupérer les détails de l'ECUE
$ecueDetails = $ecue->getEcueById($ecueId);
if (!$ecueDetails) {
    die("ECUE non trouvé");
}

// Récupérer la session
$session = $ecue->getSessionById($sessionId);
if (!$session) {
    die("Session non trouvée");
}

// Récupérer la liste des étudiants
$etudiants = $universite->getEtudiantsByPromotion($promotionId,$currentYear);
if (empty($etudiants)) {
    die("Aucun étudiant trouvé pour cette promotion");
}

// Si c'est une deuxième session, filtrer les étudiants éligibles
$isDeuxiemeSession = $ecue->isDeuxiemeSession($sessionId);
if ($isDeuxiemeSession) {
    $etudiantsEligibles = $ecue->getStudentsEligibleForSecondSessionCours($ecueId, $anneeId);
    if (empty($etudiantsEligibles)) {
        die("Aucun étudiant éligible pour cette session");
    }
    
    // Filtrer la liste des étudiants
    $matriculesEligibles = array_column($etudiantsEligibles, 'matricule');
    $etudiants = array_filter($etudiants, function($etudiant) use ($matriculesEligibles) {
        return in_array($etudiant['matricule'], $matriculesEligibles);
    });
}

// Récupérer les cotes existantes
$cotes = $universite->getCotesGrilleByEcue($ecueId, $sessionId, $anneeId);
$cotesByMatricule = [];
foreach ($cotes as $cote) {
    $cotesByMatricule[$cote['matricule']] = $cote;
}

// Créer un nouveau classeur
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Notes');

// Générer un identifiant unique pour le fichier
$fileToken = md5($ecueId . '_' . $sessionId . '_' . $anneeId . '_' . time());

// Ajouter des métadonnées
$spreadsheet->getProperties()
    ->setCreator('Système de Gestion Universitaire')
    ->setLastModifiedBy('Système de Gestion Universitaire')
    ->setTitle('Modèle de notes - ' . $ecueDetails['designationECUE'])
    ->setSubject('Notes pour ' . $ecueDetails['designationECUE'])
    ->setDescription('Modèle pour l\'importation des notes')
    ->setKeywords('notes, encodage, importation')
    ->setCategory('Notes')
    ->setCustomProperty('FileToken', $fileToken)
    ->setCustomProperty('EcueId', $ecueId)
    ->setCustomProperty('SessionId', $sessionId)
    ->setCustomProperty('AnneeId', $anneeId)
    ->setCustomProperty('DateGeneration', date('Y-m-d H:i:s'));

// En-tête du fichier Excel
$sheet->setCellValue('A1', 'MODÈLE POUR L\'IMPORTATION DES NOTES');
$sheet->mergeCells('A1:E1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Informations sur le cours
$sheet->setCellValue('A3', 'Cours:');
$sheet->setCellValue('B3', $ecueDetails['designationECUE']);
$sheet->setCellValue('A4', 'UE:');
$sheet->setCellValue('B4', $ecueDetails['designationUE']);
$sheet->setCellValue('A5', 'Semestre:');
$sheet->setCellValue('B5', $ecueDetails['numeroSemestre']);
$sheet->setCellValue('A6', 'Session:');
$sheet->setCellValue('B6', $session['designSession']);
$sheet->setCellValue('A7', 'Année Académique:');
$sheet->setCellValue('B7', $universite->getAcademicYearById($anneeId)['designation']);
$sheet->getStyle('A3:A7')->getFont()->setBold(true);

// Instruction
$sheet->setCellValue('A9', 'INSTRUCTIONS:');
$sheet->getStyle('A9')->getFont()->setBold(true);
$sheet->setCellValue('A10', '1. Veuillez saisir les notes des étudiants dans les colonnes "CC (/20)" et "EX (/20)".');
$sheet->setCellValue('A11', '2. Les notes doivent être comprises entre 0 et 20.');
$sheet->setCellValue('A12', '3. Ne modifiez pas la structure du fichier, notamment les colonnes Matricule.');
$sheet->setCellValue('A13', '4. Enregistrez le fichier et importez-le dans le système.');
$sheet->mergeCells('A10:E10');
$sheet->mergeCells('A11:E11');
$sheet->mergeCells('A12:E12');
$sheet->mergeCells('A13:E13');

// En-tête du tableau
$sheet->setCellValue('A15', 'N°');
$sheet->setCellValue('B15', 'Matricule');
$sheet->setCellValue('C15', 'Nom de l\'étudiant');
$sheet->setCellValue('D15', 'CC (/20)');
$sheet->setCellValue('E15', 'EX (/20)');
$sheet->setCellValue('F15', 'MF (/20)');
$sheet->getStyle('A15:F15')->getFont()->setBold(true);
$sheet->getStyle('A15:F15')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDEBF7');
$sheet->getStyle('A15:F15')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Définir la largeur des colonnes
$sheet->getColumnDimension('A')->setWidth(5);
$sheet->getColumnDimension('B')->setWidth(15);
$sheet->getColumnDimension('C')->setWidth(30);
$sheet->getColumnDimension('D')->setWidth(10);
$sheet->getColumnDimension('E')->setWidth(10);
$sheet->getColumnDimension('F')->setWidth(10);

// Ajouter les données des étudiants
$row = 16;
$count = 1;
foreach ($etudiants as $etudiant) {
    $sheet->setCellValue('A' . $row, $count);
    $sheet->setCellValue('B' . $row, $etudiant['matricule']);
    $sheet->setCellValue('C' . $row, $etudiant['noms']);
    
    // Ajouter les notes existantes s'il y en a
    if (isset($cotesByMatricule[$etudiant['matricule']])) {
        $cote = $cotesByMatricule[$etudiant['matricule']];
        $sheet->setCellValue('D' . $row, $cote['CC'] ?? '');
        $sheet->setCellValue('E' . $row, $cote['EX'] ?? '');
        $sheet->setCellValue('F' . $row, $cote['MF'] ?? '');
    }
    
    // Ajouter une validation pour les notes (entre 0 et 20)
    for ($col = 'D'; $col <= 'E'; $col++) {
        $validation = $sheet->getCell($col . $row)->getDataValidation();
        $validation->setType(DataValidation::TYPE_DECIMAL);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setFormula1(0);
        $validation->setFormula2(20);
        $validation->setErrorTitle('Erreur de saisie');
        $validation->setError('La note doit être entre 0 et 20');
        $validation->setPromptTitle('Note');
        $validation->setPrompt('Entrez une note entre 0 et 20');
    }
    
    // Rendre la colonne MF en lecture seule (formule qui sera calculée à l'importation)
    $sheet->getStyle('F' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EDEDED');
    
    $row++;
    $count++;
}

// Dessiner les bordures autour du tableau
$styleArray = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000'],
        ],
    ],
];
$sheet->getStyle('A15:F' . ($row - 1))->applyFromArray($styleArray);

// Protéger certaines cellules contre les modifications
$sheet->getProtection()->setSheet(true);
$sheet->getStyle('B16:C' . ($row - 1))->getProtection()->setLocked(true);
$sheet->getStyle('F16:F' . ($row - 1))->getProtection()->setLocked(true);
$sheet->getStyle('D16:E' . ($row - 1))->getProtection()->setLocked(false);


// Configurer l'en-tête pour le téléchargement
$courseShortName = preg_replace('/[^a-zA-Z0-9]/', '_', substr($ecueDetails['designationECUE'], 0, 15));
$sessionType = str_replace(' ', '_', strtolower($session['designSession']));
$filename = "modele_notes_{$courseShortName}_{$sessionType}.xlsx";

// Créer le fichier Excel
$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit();
