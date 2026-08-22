<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Créer un nouveau document Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Modèle Palmarès');

// Définir les en-têtes
$sheet->setCellValue('A1', 'Matricule');
$sheet->setCellValue('B1', 'Nom complet');
$sheet->setCellValue('C1', 'Pourcentage');
$sheet->setCellValue('D1', 'Décision');

// Ajouter des données d'exemple
$exampleData = [
    ['MAT001', 'MUTOMBO Jean', 85.75, 'Grande Distinction'],
    ['MAT002', 'KABONGO Marie', 92.30, 'Très grande distinction'],
    ['MAT003', 'LUKUSA Pierre', 75.50, 'Distinction'],
    ['MAT004', 'MBUYI Anne', 65.25, 'Satisfaction'],
    ['MAT005', 'KALALA Paul', 45.75, 'Ajournée']
];

$row = 2;
foreach ($exampleData as $data) {
    $sheet->setCellValue('A' . $row, $data[0]);
    $sheet->setCellValue('B' . $row, $data[1]);
    $sheet->setCellValue('C' . $row, $data[2]);
    $sheet->setCellValue('D' . $row, $data[3]);
    $row++;
}

// Ajouter une ligne vide pour montrer où commencer
$sheet->setCellValue('A7', '');
$sheet->setCellValue('B7', '');
$sheet->setCellValue('C7', '');
$sheet->setCellValue('D7', '');

// Styliser les en-têtes
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '198754'], // Vert Bootstrap
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
$sheet->getStyle('A1:D1')->applyFromArray($headerStyle);

// Styliser les données d'exemple
$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F8F9FA'], // Gris très clair
    ],
];
$sheet->getStyle('A2:D6')->applyFromArray($dataStyle);

// Ajouter une note explicative
$sheet->setCellValue('A9', 'Note:');
$sheet->setCellValue('A10', '1. Les données ci-dessus sont des exemples. Veuillez les remplacer par vos propres données.');
$sheet->setCellValue('A11', '2. La colonne "Décision" est optionnelle. Si elle n\'est pas remplie, elle sera calculée automatiquement en fonction du pourcentage.');
$sheet->setCellValue('A12', '3. Les valeurs possibles pour la colonne "Décision" sont: Très grande distinction, Grande Distinction, Distinction, Satisfaction, Ajournée, Assimilé aux ajournées, Abandon.');

$sheet->mergeCells('A10:D10');
$sheet->mergeCells('A11:D11');
$sheet->mergeCells('A12:D12');

$noteStyle = [
    'font' => [
        'italic' => true,
        'color' => ['rgb' => '6C757D'], // Gris Bootstrap
    ],
];
$sheet->getStyle('A9:D12')->applyFromArray($noteStyle);

// Ajuster la largeur des colonnes
$sheet->getColumnDimension('A')->setWidth(15);
$sheet->getColumnDimension('B')->setWidth(30);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->getColumnDimension('D')->setWidth(25);

// Définir les formats de cellules
$sheet->getStyle('C2:C6')->getNumberFormat()->setFormatCode('0.00');

// Créer le fichier
$writer = new Xlsx($spreadsheet);
$filename = 'template_palmares_archive.xlsx';

// En-têtes pour le téléchargement
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;