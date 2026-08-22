<?php
session_start();

if (!isset($_SESSION['idRole']) || $_SESSION['idRole'] != 1) {
    http_response_code(403);
    die('Accès refusé');
}

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die('PhpSpreadsheet non installé.');
}
require_once $autoloadPath;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setTitle('Modèle Grille de Notes')
    ->setDescription('Modèle standard pour import visuel dans e-Gestion');

// ── Feuille 1 : Grille de Notes ─────────────────────────────────
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Grille de Notes');

// Structure UE/ECUE d'exemple
$ues = [
    [
        'nom'   => 'UE 1 - Mathématiques',
        'ecues' => [
            ['nom' => 'Analyse',          'credits' => 3],
            ['nom' => 'Algèbre Linéaire', 'credits' => 2],
        ],
    ],
    [
        'nom'   => 'UE 2 - Sciences Physiques',
        'ecues' => [
            ['nom' => 'Mécanique',    'credits' => 3],
            ['nom' => 'Électricité',  'credits' => 2],
        ],
    ],
    [
        'nom'   => 'UE 3 - Informatique',
        'ecues' => [
            ['nom' => 'Algorithmique',    'credits' => 4],
            ['nom' => 'Programmation',    'credits' => 3],
            ['nom' => 'Bases de données', 'credits' => 2],
        ],
    ],
];

// Positions de colonnes (C = index 3)
$colStart   = 3;
$currentCol = $colStart;
$ueRanges   = [];
$ecueList   = [];

foreach ($ues as $ue) {
    $ueColStart = $currentCol;
    foreach ($ue['ecues'] as $ecue) {
        $ecueList[] = ['col' => $currentCol, 'nom' => $ecue['nom'], 'credits' => $ecue['credits']];
        $currentCol++;
    }
    $ueRanges[] = ['col_start' => $ueColStart, 'col_end' => $currentCol - 1, 'nom' => $ue['nom']];
}
$lastCol       = $currentCol - 1;
$lastColLetter = Coordinate::stringFromColumnIndex($lastCol);

// Palette de couleurs
$cHeader   = '1F3864';
$cUE       = '2E75B6';
$cECUE     = 'BDD7EE';
$cCredit   = 'FFD966';
$cMeta     = 'FFF2CC'; // fond métadonnées
$cMetaKey  = 'ED7D31'; // clé métadonnées
$cData     = 'FFFFFF';
$cAlt      = 'F2F2F2';
$cSample   = 'E2EFDA';

// ═══════════════════════════════════════════════════════════════
// SECTION MÉTADONNÉES (lignes 1-4) — à remplir par l'utilisateur
// ═══════════════════════════════════════════════════════════════
$metaRows = [
    1 => ['ANNEE_ACADEMIQUE', '2024-2025'],
    2 => ['SESSION',          'principale'],
    3 => ['PROMOTION',        'L1 Informatique'],
    4 => ['SEMESTRE',         'annuel'],
];

foreach ($metaRows as $r => [$key, $defaultVal]) {
    $sheet->setCellValue('A' . $r, $key);
    $sheet->setCellValue('B' . $r, $defaultVal);

    // Style clé
    $sheet->getStyle('A' . $r)->applyFromArray([
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $cMetaKey]],
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
    ]);
    // Style valeur
    $sheet->getStyle('B' . $r)->applyFromArray([
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $cMeta]],
        'font'      => ['color' => ['rgb' => $cHeader]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
    ]);
    // Lignes vides C…lastCol dans la section méta : grisées
    $sheet->getStyle('C' . $r . ':' . $lastColLetter . $r)->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F5F5']],
    ]);
}

// Ligne 5 : séparateur vide
$sheet->getStyle('A5:' . $lastColLetter . '5')->applyFromArray([
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E7E6E6']],
]);
$sheet->getRowDimension(5)->setRowHeight(6);

// Décalage : données UE/ECUE commencent à la ligne 6
$rowUE      = 6;   // Noms UE
$rowECUE    = 7;   // Noms ECUE
$rowCredits = 8;   // Crédits (avec mot-clé "CREDITS")
$rowData    = 9;   // Première ligne étudiants

// ═══════════════════════════════════════════════════════════════
// LIGNE 6 : en-têtes UE
// ═══════════════════════════════════════════════════════════════
$sheet->setCellValue('A' . $rowUE, 'MATRICULE');
$sheet->setCellValue('B' . $rowUE, 'NOM');

foreach ($ueRanges as $ue) {
    $startL = Coordinate::stringFromColumnIndex($ue['col_start']);
    $endL   = Coordinate::stringFromColumnIndex($ue['col_end']);
    $sheet->setCellValue($startL . $rowUE, $ue['nom']);
    if ($ue['col_start'] < $ue['col_end']) {
        $sheet->mergeCells($startL . $rowUE . ':' . $endL . $rowUE);
    }
    $sheet->getStyle($startL . $rowUE . ':' . $endL . $rowUE)->applyFromArray([
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $cUE]],
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
    ]);
}
$sheet->getStyle('A' . $rowUE . ':B' . $rowUE)->applyFromArray([
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $cHeader]],
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '4472C4']]],
]);

// ═══════════════════════════════════════════════════════════════
// LIGNE 7 : noms ECUE
// ═══════════════════════════════════════════════════════════════
foreach ($ecueList as $ecue) {
    $letter = Coordinate::stringFromColumnIndex($ecue['col']);
    $sheet->setCellValue($letter . $rowECUE, $ecue['nom']);
}
$sheet->getStyle('A' . $rowECUE . ':B' . $rowECUE)->applyFromArray([
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $cHeader]],
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
]);
$sheet->getStyle('C' . $rowECUE . ':' . $lastColLetter . $rowECUE)->applyFromArray([
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $cECUE]],
    'font'      => ['bold' => true, 'color' => ['rgb' => $cHeader], 'size' => 10],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
]);

// ═══════════════════════════════════════════════════════════════
// LIGNE 8 : crédits
// ═══════════════════════════════════════════════════════════════
$sheet->setCellValue('A' . $rowCredits, 'CREDITS');
foreach ($ecueList as $ecue) {
    $letter = Coordinate::stringFromColumnIndex($ecue['col']);
    $sheet->setCellValue($letter . $rowCredits, $ecue['credits']);
}
$sheet->getStyle('A' . $rowCredits . ':' . $lastColLetter . $rowCredits)->applyFromArray([
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $cCredit]],
    'font'      => ['bold' => true, 'color' => ['rgb' => $cHeader]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
]);

// ═══════════════════════════════════════════════════════════════
// LIGNES 9-10 : données d'exemple (à remplacer)
// ═══════════════════════════════════════════════════════════════
$samples = [
    ['20240001', 'DUPONT Jean',   14, 12, 15, 11, 16, 13, 10],
    ['20240002', 'MARTIN Sophie', 10,  8, 13,  9, 17, 14, 12],
];
foreach ($samples as $i => $student) {
    $row = $rowData + $i;
    $sheet->setCellValue('A' . $row, $student[0]);
    $sheet->setCellValue('B' . $row, $student[1]);
    foreach ($ecueList as $j => $ecue) {
        $letter = Coordinate::stringFromColumnIndex($ecue['col']);
        $sheet->setCellValue($letter . $row, $student[2 + $j] ?? '');
    }
    $sheet->getStyle('A' . $row . ':' . $lastColLetter . $row)->applyFromArray([
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $cSample]],
        'font'      => ['color' => ['rgb' => '666666'], 'italic' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
    ]);
}

// ═══════════════════════════════════════════════════════════════
// LIGNES 11-55 : lignes vides pour les données réelles
// ═══════════════════════════════════════════════════════════════
for ($row = $rowData + 2; $row <= 55; $row++) {
    $bgColor = ($row % 2 === 0) ? $cData : $cAlt;
    $sheet->getStyle('A' . $row . ':' . $lastColLetter . $row)->applyFromArray([
        'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
    ]);
}

// ── Largeurs de colonnes ──
$sheet->getColumnDimension('A')->setWidth(20);
$sheet->getColumnDimension('B')->setWidth(28);
for ($col = $colStart; $col <= $lastCol; $col++) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth(16);
}

// ── Hauteurs de lignes ──
for ($r = 1; $r <= 4; $r++) {
    $sheet->getRowDimension($r)->setRowHeight(22);
}
$sheet->getRowDimension($rowUE)->setRowHeight(30);
$sheet->getRowDimension($rowECUE)->setRowHeight(38);
$sheet->getRowDimension($rowCredits)->setRowHeight(22);

// Figer après les en-têtes
$sheet->freezePane('C' . $rowData);

// ── Feuille 2 : Instructions ─────────────────────────────────────
$instrSheet = $spreadsheet->createSheet();
$instrSheet->setTitle('Instructions');

$lines = [
    ['INSTRUCTIONS D\'UTILISATION DU MODÈLE', ''],
    ['', ''],
    ['SECTION MÉTADONNÉES (lignes 1 à 4) — À REMPLIR OBLIGATOIREMENT', ''],
    ['ANNEE_ACADEMIQUE', 'Saisir l\'année académique en colonne B, ex : 2023-2024'],
    ['SESSION',          'Saisir "principale" ou "rattrapage" en colonne B'],
    ['PROMOTION',        'Saisir le nom de la promotion en colonne B, ex : L1 Informatique'],
    ['SEMESTRE',         'Saisir le semestre en colonne B : annuel, S1, S2, S3, S4, S5, ou S6'],
    ['', ''],
    ['STRUCTURE DES EN-TÊTES (lignes 6-8) — NE PAS MODIFIER', ''],
    ['Ligne 6', 'Noms des UE (cellules fusionnées sur les colonnes de chaque UE). Colonnes A et B = MATRICULE et NOM.'],
    ['Ligne 7', 'Noms des ECUE — une colonne par ECUE.'],
    ['Ligne 8', 'Crédits — valeur numérique par ECUE. Colonne A contient le mot "CREDITS".'],
    ['', ''],
    ['DONNÉES ÉTUDIANTS (à partir de la ligne 9)', ''],
    ['Colonne A', 'Matricule de l\'étudiant'],
    ['Colonne B', 'Nom et prénom'],
    ['Colonne C et +', 'Notes par ECUE (0 à 20). Laisser vide si note absente.'],
    ['', 'Effacer les 2 lignes d\'exemple (lignes 9 et 10 en vert) avant d\'importer.'],
    ['', ''],
    ['PERSONNALISATION', ''],
    ['Ajouter une UE', 'Insérer des colonnes, saisir le nom UE ligne 6 (fusionner), noms ECUE ligne 7, crédits ligne 8.'],
    ['Supprimer une UE', 'Supprimer les colonnes correspondantes.'],
    ['', ''],
    ['IMPORT AUTOMATIQUE', ''],
    ['', 'Lorsque ce fichier est importé dans e-Gestion, TOUTE la configuration est détectée'],
    ['', 'automatiquement : métadonnées, mapping UE/ECUE, crédits.'],
    ['', 'Il suffit de vérifier et de cliquer "Lancer l\'import".'],
];

foreach ($lines as $i => $line) {
    $r = $i + 1;
    $instrSheet->setCellValue('A' . $r, $line[0]);
    $instrSheet->setCellValue('B' . $r, $line[1]);
}

$instrSheet->getColumnDimension('A')->setWidth(32);
$instrSheet->getColumnDimension('B')->setWidth(90);

$instrSheet->getStyle('A1:B1')->applyFromArray([
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $cHeader]],
    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
]);
foreach ([3, 9, 14, 20, 24] as $secRow) {
    $instrSheet->getStyle('A' . $secRow . ':B' . $secRow)->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $cUE]],
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    ]);
}

$spreadsheet->setActiveSheetIndex(0);

// ── Envoi du fichier ──
$filename = 'modele_grille_notes_' . date('Ymd') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
