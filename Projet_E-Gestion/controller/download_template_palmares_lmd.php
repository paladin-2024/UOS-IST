<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Modèle de palmarès pour système LMD
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Modèle Palmarès LMD');

// En-têtes
$sheet->setCellValue('A1', 'Matricule');
$sheet->setCellValue('B1', 'Nom complet');
$sheet->setCellValue('C1', 'Moyenne (sur 20)');
$sheet->setCellValue('D1', 'Crédits validés');
$sheet->setCellValue('E1', 'Décision');

// Données d'exemple (LMD)
$exampleData = [
    ['MAT001', 'MUTOMBO Jean', 16.2, 60, 'Très Bien'],
    ['MAT002', 'KABONGO Marie', 14.5, 54, 'Bien'],
    ['MAT003', 'LUKUSA Pierre', 12.8, 48, 'Assez Bien'],
    ['MAT004', 'MBUYI Anne', 10.7, 42, 'Satisfaction'],
    ['MAT005', 'KALALA Paul', 8.5, 30, ''], // décision laissée vide (auto)
];

$row = 2;
foreach ($exampleData as $data) {
    $sheet->setCellValue('A' . $row, $data[0]);
    $sheet->setCellValue('B' . $row, $data[1]);
    $sheet->setCellValue('C' . $row, $data[2]);
    $sheet->setCellValue('D' . $row, $data[3]);
    $sheet->setCellValue('E' . $row, $data[4]);
    $row++;
}

// Ligne vide indicative
$sheet->setCellValue('A7', '');
$sheet->setCellValue('B7', '');
$sheet->setCellValue('C7', '');
$sheet->setCellValue('D7', '');
$sheet->setCellValue('E7', '');

// Styles
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '0D6EFD'], // Bleu Bootstrap
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
$sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F8F9FA'],
    ],
];
$sheet->getStyle('A2:E6')->applyFromArray($dataStyle);

// Notes
$sheet->setCellValue('A9', 'Notes:');
$sheet->setCellValue('A10', '1. La colonne "Décision" est optionnelle. Si elle est vide, elle sera déterminée automatiquement en fonction de la moyenne.');
$sheet->setCellValue('A11', '2. "Crédits validés" est recommandé mais optionnel.');
$sheet->setCellValue('A12', '3. Valeurs usuelles pour "Décision" (LMD): Satisfaction, Assez Bien, Bien, Très Bien.');

$sheet->mergeCells('A10:E10');
$sheet->mergeCells('A11:E11');
$sheet->mergeCells('A12:E12');
$sheet->getStyle('A9:E12')->getFont()->setItalic(true)->getColor()->setRGB('6C757D');

// Largeur colonnes
$sheet->getColumnDimension('A')->setWidth(15);
$sheet->getColumnDimension('B')->setWidth(30);
$sheet->getColumnDimension('C')->setWidth(18);
$sheet->getColumnDimension('D')->setWidth(18);
$sheet->getColumnDimension('E')->setWidth(25);

// Formats
$sheet->getStyle('C2:C6')->getNumberFormat()->setFormatCode('0.00');
$sheet->getStyle('D2:D6')->getNumberFormat()->setFormatCode('0');

// Sortie
$writer = new Xlsx($spreadsheet);
$filename = 'template_palmares_lmd.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;

