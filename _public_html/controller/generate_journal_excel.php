<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Start session and check user authentication
session_start();
if (!isset($_SESSION['id'])) {
    die("Accès refusé.");
}

$userId = $_SESSION['id'];
$startDate = $_POST['startDate'];
$endDate = $_POST['endDate'];
$structureId = $_POST['structureId'];

// Instantiate the journal model
$journalModel = new Structure();
$results = $journalModel->getJournalEntries($userId, $structureId, $startDate, $endDate);

// Retrieve initial balances
$initialBalances = $journalModel->getInitialBalances($userId, $structureId, $startDate);

// Retrieve structure information
$structureInfo = $journalModel->getStructureById($structureId);

// Create the Excel file
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Add logo and structure information
$row = 1;
if ($structureInfo) {
    $logoPath = '../uploads/' . $structureInfo['logo'];

    if (file_exists($logoPath)) {
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setName('Logo');
        $drawing->setPath($logoPath);
        $drawing->setCoordinates('A1');
        $drawing->setHeight(50);
        $drawing->setWorksheet($sheet);
    }

    $sheet->setCellValue('B1', strtoupper($structureInfo['designation']));
    $sheet->setCellValue('B2', 'Adresse : ' . $structureInfo['adresse']);
    $sheet->setCellValue('B3', 'Téléphone : ' . $structureInfo['phone1']);
    $sheet->mergeCells('B1:G1');
    $sheet->mergeCells('B2:G2');
    $sheet->mergeCells('B3:G3');

    $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('B1:B3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    $row = 5;
}

// Document title
$title = sprintf('Historique des Opérations Comptables Automatiques du %s au %s', date('d/m/Y', strtotime($startDate)), date('d/m/Y', strtotime($endDate)));
$sheet->setCellValue('A' . $row, $title);
$sheet->mergeCells("A$row:G$row");
$sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(14);
$sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$row += 2;

// Column headers
$headers = ['Date', 'Compte', 'Libellé Compte', 'Montant Débit', 'Montant Crédit', 'Libellé Opération', 'Numéro Pièce'];
$sheet->fromArray($headers, NULL, 'A' . $row);
$sheet->getStyle("A$row:G$row")->getFont()->setBold(true);
$sheet->getStyle("A$row:G$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
$sheet->getStyle("A$row:G$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$row++;

// Insert initial balances
$sheet->setCellValue('C' . $row, 'Solde du Report');
$sheet->setCellValue('D' . $row, $initialBalances['initial_debit']);
$sheet->setCellValue('E' . $row, $initialBalances['initial_credit']);
$sheet->getStyle("C$row:E$row")->getFont()->setBold(true);

$row++;

// Initialize totals
$totalDebit = $initialBalances['initial_debit'];
$totalCredit = $initialBalances['initial_credit'];

// Insert data without formatting amounts
foreach ($results as $data) {
    $sheet->setCellValue('A' . $row, date('d/m/Y', strtotime($data['dateOperation'])));
    $sheet->setCellValue('B' . $row, $data['compte']);
    $sheet->setCellValue('C' . $row, $data['libelle_compte']);
    $sheet->setCellValue('D' . $row, $data['montant_debit']);
    $sheet->setCellValue('E' . $row, $data['montant_credit']);
    $sheet->setCellValue('F' . $row, $data['libele']);
    $sheet->setCellValue('G' . $row, $data['numPiece']);
    
    // Accumulate totals
    $totalDebit += $data['montant_debit'];
    $totalCredit += $data['montant_credit'];
    
    $row++;
}

// Add totals row
$sheet->setCellValue('C' . $row, 'Total');
$sheet->setCellValue('D' . $row, $totalDebit);
$sheet->setCellValue('E' . $row, $totalCredit);
$sheet->getStyle("C$row:E$row")->getFont()->setBold(true);

// Add borders
$sheet->getStyle("A5:G" . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Auto-size columns
foreach (range('A', 'G') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Page setup
$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);

// Check and create export directory
$exportDir = '../exports/';
if (!is_dir($exportDir)) {
    mkdir($exportDir, 0777, true);
}

// Generate Excel file
$filename = 'journal_comptable_' . date('Ymd_His') . '.xlsx';
$filepath = $exportDir . $filename;
$writer = new Xlsx($spreadsheet);
$writer->save($filepath);

// Download file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
readfile($filepath);

// Delete file after download
unlink($filepath);
header("Location:../comptabilite/journal.operations");
?>