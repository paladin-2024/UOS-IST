<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Deliberation.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

// Vérification d'authentification
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Récupérer les paramètres
$ecueId = isset($_GET['ecue']) ? intval($_GET['ecue']) : 0;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;

if ($ecueId <= 0 || $sessionId <= 0 || $anneeId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètres invalides']);
    exit;
}

// Initialiser les objets
$universite = new Universite();
$ecue = new Ecue();
$deliberation = new Deliberation();

// Récupérer la configuration de l'université
$configUniversite = $universite->getConfigurationUniversite();

// Récupérer les informations sur l'ECUE et la session
$ecueDetails = $ecue->getEcueById($ecueId);
$sessionInfo = $universite->getSessionById($sessionId);
$anneeInfo = $universite->getAcademicYearById($anneeId);

if (!$ecueDetails || !$sessionInfo || !$anneeInfo) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Informations introuvables']);
    exit;
}

// Récupérer la promotion associée à l'ECUE
$promotionId = $ecueDetails['idpromotion'] ?? 0;
$promotionInfo = $universite->getPromotionById($promotionId);

// Récupérer les étudiants inscrits à cet ECUE pour cette année académique
$etudiants = $ecue->getStudentsByEcue($ecueId, $anneeId);

// Récupérer la configuration de pondération CC/EX pour cet ECUE
$config = $ecue->getConfigurationMoyenne($ecueId, $anneeId, $sessionId);
// Récupérer les pondérations depuis la configuration par défaut si pas de config spécifique
require_once '../models/Universite.php';
$universite = new Universite();
$ponderationsDefaut = $universite->getPonderationsDefaut();
$ponderationCC = $config ? $config['ponderation_cc'] : $ponderationsDefaut['ponderation_cc'];
$ponderationEX = $config ? $config['ponderation_ex'] : $ponderationsDefaut['ponderation_ex'];

// Créer un nouvel objet Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Cotes ' . substr($ecueDetails['designationECUE'], 0, 20));

// Configurez les propriétés personnalisées pour l'authentification
$spreadsheet->getProperties()
    ->setCompany(!empty($configUniversite['nom']) ? $configUniversite['nom'] : 'E-GESTION UNIVERSITY')
    ->setTitle('Grille de Cotes ECUE')
    ->setSubject('Encodage des cotes')
    ->setDescription('Formulaire d\'encodage des cotes pour ' . $ecueDetails['designationECUE'])
    ->setCustomProperty('EcueId', $ecueId)
    ->setCustomProperty('SessionId', $sessionId)
    ->setCustomProperty('AnneeId', $anneeId)
    ->setCustomProperty('FileToken', md5($ecueId . $sessionId . $anneeId . time())); // Jeton unique

// Configuration des marges étroites
$sheet->getPageMargins()->setTop(0.25);
$sheet->getPageMargins()->setRight(0.25);
$sheet->getPageMargins()->setBottom(0.25);
$sheet->getPageMargins()->setLeft(0.25);
$sheet->getPageMargins()->setHeader(0.125);
$sheet->getPageMargins()->setFooter(0.125);

// Configurer la page en mode portrait et ajuster à 1 page de largeur
$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0); // 0 = autant de pages que nécessaire en hauteur

// Définir les styles
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4472C4'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
];

$subHeaderStyle = [
    'font' => [
        'bold' => true,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'D9E1F2'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
];

$instructionStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => '000000'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'FFFF00'], // Jaune
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_LEFT,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
];

$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
];

$editableCellStyle = [
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E2EFDA'], // Vert clair
    ],
];

// En-tête avec les informations de l'université
$row = 1;
// Ajouter le logo si disponible
if (!empty($configUniversite['logo'])) {
    $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
    if (file_exists($logoPath)) {
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo de l\'université');
        $drawing->setPath($logoPath);
        $drawing->setCoordinates('A1');
        $drawing->setHeight(60);
        $drawing->setWorksheet($sheet);
        $row = 4;
    }
}

// En-tête avec les informations de l'université
$sheet->setCellValue('A' . $row, !empty($configUniversite['nom']) ? $configUniversite['nom'] : 'E-GESTION UNIVERSITY');
$sheet->mergeCells('A' . $row . ':F' . $row);
$sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray([
    'font' => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);
$row++;

// Titre du document
$sheet->setCellValue('A' . $row, 'FORMULAIRE D\'ENCODAGE DES COTES');
$sheet->mergeCells('A' . $row . ':F' . $row);
$sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray([
    'font' => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);
$row++;

// Informations sur l'ECUE
$row++;
$sheet->setCellValue('A' . $row, 'ECUE:');
$sheet->setCellValue('C' . $row, $ecueDetails['designationECUE']);
$sheet->mergeCells('C' . $row . ':F' . $row);
$row++;

$sheet->setCellValue('A' . $row, 'UE:');
$sheet->setCellValue('C' . $row, $ecueDetails['designationUE']);
$sheet->mergeCells('C' . $row . ':F' . $row);
$row++;

$sheet->setCellValue('A' . $row, 'Promotion:');
$sheet->setCellValue('C' . $row, $promotionInfo['designationPromotion']);
$sheet->mergeCells('C' . $row . ':F' . $row);
$row++;

$sheet->setCellValue('A' . $row, 'Session:');
$sheet->setCellValue('C' . $row, $sessionInfo['designSession']);
$sheet->mergeCells('C' . $row . ':F' . $row);
$row++;

$sheet->setCellValue('A' . $row, 'Année Académique:');
$sheet->setCellValue('C' . $row, $anneeInfo['designation']);
$sheet->mergeCells('C' . $row . ':F' . $row);
$row++;

// Instructions pour l'encodage
$row += 2;
$sheet->setCellValue('A' . $row, 'INSTRUCTIONS:');
$sheet->mergeCells('A' . $row . ':F' . $row);
$sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray($instructionStyle);
$row++;

$instructions = [
    '1. Ne modifiez que les cellules surlignées en vert clair (CC et EX).',
    '2. Les notes doivent être comprises entre 0 et 20.',
    '3. La moyenne finale (MF) est calculée automatiquement selon la formule: CC*' . $ponderationCC . ' + EX*' . $ponderationEX . '.',
    '4. Ne modifiez pas la structure du fichier, sinon l\'importation échouera.',
    '5. Après avoir terminé l\'encodage, enregistrez le fichier et importez-le dans le système.'
];

foreach ($instructions as $instruction) {
    $sheet->setCellValue('A' . $row, $instruction);
    $sheet->mergeCells('A' . $row . ':F' . $row);
    $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'wrapText' => true]
    ]);
    $row++;
}

// En-têtes du tableau
$headerRow = $row + 1;
$sheet->setCellValue('A' . $headerRow, 'N°');
$sheet->setCellValue('B' . $headerRow, 'Matricule');
$sheet->setCellValue('C' . $headerRow, 'Nom de l\'étudiant');
$sheet->setCellValue('D' . $headerRow, 'CC (sur 20)');
$sheet->setCellValue('E' . $headerRow, 'EX (sur 20)');
$sheet->setCellValue('F' . $headerRow, 'MF (sur 20)');

// Appliquer le style aux en-têtes
$sheet->getStyle('A' . $headerRow . ':F' . $headerRow)->applyFromArray($headerStyle);

// Figer la première ligne du tableau pour faciliter la navigation
$sheet->freezePane('A' . ($headerRow + 1));

// Données des étudiants
$rowStart = $headerRow + 1;
$rowIndex = 0;

foreach ($etudiants as $index => $etudiant) {
    $currentRow = $rowStart + $rowIndex;
    $matricule = $etudiant['matricule'];
    
    // Récupérer les notes existantes
    $notes = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId);
    
    $cc = ($notes && $notes['CC'] !== null) ? $notes['CC'] : '';
    $ex = ($notes && $notes['EX'] !== null) ? $notes['EX'] : '';
    
       // Numéro d'ordre, matricule et nom
       $sheet->setCellValue('A' . $currentRow, $index + 1);
       $sheet->setCellValue('B' . $currentRow, $matricule);
       $sheet->setCellValue('C' . $currentRow, $etudiant['noms']);
       
       // Cellules pour CC et EX (modifiables)
       $sheet->setCellValue('D' . $currentRow, $cc);
       $sheet->setCellValue('E' . $currentRow, $ex);
       
       // Formule pour calculer la moyenne finale
       // La formule ne s'applique que si les deux valeurs sont présentes
       $ccCell = 'D' . $currentRow;
       $exCell = 'E' . $currentRow;
       $sheet->setCellValue('F' . $currentRow, '=IF(AND(ISNUMBER(' . $ccCell . '),ISNUMBER(' . $exCell . ')), ' . $ccCell . '*' . $ponderationCC . '+' . $exCell . '*' . $ponderationEX . ', IF(ISNUMBER(' . $ccCell . '),' . $ccCell . ',IF(ISNUMBER(' . $exCell . '),' . $exCell . ',"")))');
       
       // Appliquer le style aux cellules modifiables
       $sheet->getStyle('D' . $currentRow . ':E' . $currentRow)->applyFromArray($editableCellStyle);
       
       // Validation des données pour les notes (entre 0 et 20)
       $validation = $sheet->getCell('D' . $currentRow)->getDataValidation();
       $validation->setType(DataValidation::TYPE_DECIMAL);
       $validation->setErrorStyle(DataValidation::STYLE_STOP);
       $validation->setAllowBlank(true);
       $validation->setFormula1(0);
       $validation->setFormula2(20);
       $validation->setErrorTitle('Erreur de saisie');
       $validation->setError('La note doit être comprise entre 0 et 20.');
       $validation->setPromptTitle('Note CC');
       $validation->setPrompt('Entrez une note entre 0 et 20 pour le contrôle continu.');
       
       $validation = $sheet->getCell('E' . $currentRow)->getDataValidation();
       $validation->setType(DataValidation::TYPE_DECIMAL);
       $validation->setErrorStyle(DataValidation::STYLE_STOP);
       $validation->setAllowBlank(true);
       $validation->setFormula1(0);
       $validation->setFormula2(20);
       $validation->setErrorTitle('Erreur de saisie');
       $validation->setError('La note doit être comprise entre 0 et 20.');
       $validation->setPromptTitle('Note Examen');
       $validation->setPrompt('Entrez une note entre 0 et 20 pour l\'examen.');
       
       $rowIndex++;
   }
   
   // Appliquer le style à toutes les cellules de données
   $dataRange = 'A' . $rowStart . ':F' . ($rowStart + count($etudiants) - 1);
   $sheet->getStyle($dataRange)->applyFromArray($dataStyle);
   
   // Ajuster la largeur des colonnes
   $sheet->getColumnDimension('A')->setWidth(5);
   $sheet->getColumnDimension('B')->setWidth(15);
   $sheet->getColumnDimension('C')->setWidth(40);
   $sheet->getColumnDimension('D')->setWidth(12);
   $sheet->getColumnDimension('E')->setWidth(12);
   $sheet->getColumnDimension('F')->setWidth(12);
   
   // Aligner les noms d'étudiants à gauche
   $sheet->getStyle('C' . $rowStart . ':C' . ($rowStart + count($etudiants) - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
   
   // Information sur la configuration de pondération
   $infoRow = $rowStart + count($etudiants) + 2;
   $sheet->setCellValue('A' . $infoRow, 'Configuration de pondération:');
   $sheet->mergeCells('A' . $infoRow . ':C' . $infoRow);
   $sheet->getStyle('A' . $infoRow)->getFont()->setBold(true);
   $infoRow++;
   
   $sheet->setCellValue('A' . $infoRow, 'CC: ' . ($ponderationCC * 100) . '%');
   $sheet->setCellValue('C' . $infoRow, 'EX: ' . ($ponderationEX * 100) . '%');
   $infoRow += 2;
   
   $sheet->setCellValue('A' . $infoRow, 'Date d\'exportation: ' . date('d/m/Y H:i'));
   $sheet->mergeCells('A' . $infoRow . ':C' . $infoRow);
   
   // Ajouter des métadonnées cachées pour faciliter l'importation
   $sheet->setCellValue('X1', 'ecue_id');
   $sheet->setCellValue('Y1', $ecueId);
   $sheet->setCellValue('X2', 'session_id');
   $sheet->setCellValue('Y2', $sessionId);
   $sheet->setCellValue('X3', 'annee_id');
   $sheet->setCellValue('Y3', $anneeId);
   $sheet->setCellValue('X4', 'export_date');
   $sheet->setCellValue('Y4', date('Y-m-d H:i:s'));
   $sheet->setCellValue('X5', 'token');
   $sheet->setCellValue('Y5', md5($ecueId . $sessionId . $anneeId . time()));
   
   // Masquer ces cellules de métadonnées
   $sheet->getColumnDimension('X')->setVisible(false);
   $sheet->getColumnDimension('Y')->setVisible(false);
   
   // Protéger la feuille en autorisant uniquement la modification des cellules déverrouillées
   $sheet->getProtection()->setSheet(true);
   $sheet->getProtection()->setPassword('universitelock'); // Définir un mot de passe
   
   // Déverrouiller les cellules modifiables
   $editableCells = 'D' . $rowStart . ':E' . ($rowStart + count($etudiants) - 1);
   $sheet->getStyle($editableCells)->getProtection()->setLocked(false);
   
   // Créer le nom du fichier
   $fileName = 'Cotes_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $ecueDetails['designationECUE']) . '_' . date('Y-m-d_H-i-s') . '.xlsx';
   
   // Définir les en-têtes HTTP pour le téléchargement
   header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
   header('Content-Disposition: attachment;filename="' . $fileName . '"');
   header('Cache-Control: max-age=0');
   
   // Créer le writer et envoyer le fichier au navigateur
   $writer = new Xlsx($spreadsheet);
   $writer->save('php://output');
   exit;
   