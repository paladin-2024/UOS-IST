<?php
require_once __DIR__ . '/../config/Connexion.php';
require_once __DIR__ . '/../models/Universite.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$universite = new Universite();

$selectedYear = isset($_GET['annee']) ? $_GET['annee'] : null;
$selectedSection = isset($_GET['section']) ? $_GET['section'] : 'all';

if (!$selectedYear) {
    die('Année académique non spécifiée');
}

// Récupérer les statistiques
if ($selectedSection === 'all') {
    $stats = $universite->getInscriptionsStatsByYear($selectedYear);
} else {
    $stats = $universite->getInscriptionsStatsBySectionAndYear($selectedSection, $selectedYear);
}

// Récupérer la désignation de l'année académique
$academicYears = $universite->getAcademicYears();
$yearDesignation = '';
foreach ($academicYears as $year) {
    if ($year['idannee_acad'] == $selectedYear) {
        $yearDesignation = $year['designation'];
        break;
    }
}

// Créer le fichier Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Statistiques Inscriptions');

// En-tête du document
$sheet->setCellValue('A1', 'STATISTIQUES DES INSCRIPTIONS');
$sheet->mergeCells('A1:F1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', 'Année Académique: ' . $yearDesignation);
$sheet->mergeCells('A2:F2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Résumé global
$sheet->setCellValue('A4', 'RÉSUMÉ GLOBAL');
$sheet->mergeCells('A4:B4');
$sheet->getStyle('A4')->getFont()->setBold(true);

$sheet->setCellValue('A5', 'Total étudiants:');
$sheet->setCellValue('B5', isset($stats['total']) ? $stats['total'] : 0);

$sheet->setCellValue('A6', 'Masculin:');
$sheet->setCellValue('B6', isset($stats['masculin']) ? $stats['masculin'] : 0);

$sheet->setCellValue('A7', 'Féminin:');
$sheet->setCellValue('B7', isset($stats['feminin']) ? $stats['feminin'] : 0);

// Tableau détaillé par promotion
$sheet->setCellValue('A9', 'DÉTAIL PAR PROMOTION');
$sheet->mergeCells('A9:F9');
$sheet->getStyle('A9')->getFont()->setBold(true);

// En-têtes du tableau
$headers = ['Promotion', 'Total', 'Masculin', 'Féminin', '% Masculin', '% Féminin'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '10', $header);
    $col++;
}

// Style des en-têtes
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4154f1']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A10:F10')->applyFromArray($headerStyle);

// Données
$row = 11;
if (isset($stats['promotions']) && is_array($stats['promotions'])) {
    foreach ($stats['promotions'] as $promotion) {
        $percentMasc = $promotion['total'] > 0 ? round(($promotion['masculin'] / $promotion['total']) * 100, 1) : 0;
        $percentFem = $promotion['total'] > 0 ? round(($promotion['feminin'] / $promotion['total']) * 100, 1) : 0;
        
        $sheet->setCellValue('A' . $row, $promotion['designationPromotion']);
        $sheet->setCellValue('B' . $row, $promotion['total']);
        $sheet->setCellValue('C' . $row, $promotion['masculin']);
        $sheet->setCellValue('D' . $row, $promotion['feminin']);
        $sheet->setCellValue('E' . $row, $percentMasc . '%');
        $sheet->setCellValue('F' . $row, $percentFem . '%');
        $row++;
    }
}

// Style des données
$dataStyle = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
];
if ($row > 11) {
    $sheet->getStyle('A11:F' . ($row - 1))->applyFromArray($dataStyle);
}

// Ajuster la largeur des colonnes
foreach (range('A', 'F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Télécharger le fichier
$filename = 'Statistiques_Inscriptions_' . str_replace('/', '-', $yearDesignation) . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
