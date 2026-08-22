<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Comptabilite.php';
require_once dirname(__DIR__) . '/models/Structure.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

session_start();
if (!isset($_SESSION['id'])) {
    die("Accès refusé.");
}

$userId = $_SESSION['id'];
$compteId = $_GET['compte'];
$startDate = $_GET['startDate'];
$endDate = $_GET['endDate'];

// Instantiate models
$compteModel = new Comptabilite();
$structureModel = new Structure();
$structureInfo = $structureModel->getStructureByCompte($compteId);

// Calculate report period balances
$reportBalances = $compteModel->getReportPeriodBalancesByCompte($compteId, $startDate);
$reportDebit = $reportBalances['report_debit'];
$reportCredit = $reportBalances['report_credit'];
$runningBalance = $reportDebit - $reportCredit;

// Fetch transactions for the selected period
$transactions = $compteModel->getTransactionsByCompteAndPeriod($compteId, $startDate, $endDate);

// Determine account class
$classeCompte = $structureInfo['classeCompte'];
$is_debit_account = in_array($classeCompte, ['2', '3', '4', '5', '6']);
$is_credit_account = in_array($classeCompte, ['1', '7']);

// Create a new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Integrate logo and structure information
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
    $sheet->mergeCells('B1:E1');
    $sheet->mergeCells('B2:E2');
    $sheet->mergeCells('B3:E3');

    $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('B1:B3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    $row = 5;
}

$numeCompte = $structureInfo['numeroCompte'];
$intituleCompte = $structureInfo['intituleCompte'];

$dd = date('d/m/Y', strtotime($startDate));
$df = date('d/m/Y', strtotime($endDate));

// Add document title
$title = sprintf('Historique du Compte [%s] - %s Du %s Au %s', $numeCompte, $intituleCompte, $dd, $df);
$sheet->setCellValue('A' . $row, $title);
$sheet->mergeCells("A$row:E$row");
$sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(14);
$sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$row += 2;

// Add header
$sheet->setCellValue('A' . $row, 'Date')
      ->setCellValue('B' . $row, 'Libellé')
      ->setCellValue('C' . $row, 'Débit')
      ->setCellValue('D' . $row, 'Crédit')
      ->setCellValue('E' . $row, 'Solde');
$sheet->getStyle("A$row:E$row")->getFont()->setBold(true);
$sheet->getStyle("A$row:E$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
$sheet->getStyle("A$row:E$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Add report balance
$row++;
$sheet->setCellValue('A' . $row, 'Solde avant ' . date('d/m/Y', strtotime($startDate)))
      ->setCellValue('C' . $row, $reportDebit)
      ->setCellValue('D' . $row, $reportCredit)
      ->setCellValue('E' . $row, $runningBalance);

// Add transactions
$row++;
foreach ($transactions as $transaction) {
    if ($is_debit_account) {
        $runningBalance += $transaction['debit'] - $transaction['credit'];
    } elseif ($is_credit_account) {
        $runningBalance += $transaction['credit'] - $transaction['debit'];
    }
    $sheet->setCellValue('A' . $row, date('d/m/Y', strtotime($transaction['date'])))
          ->setCellValue('B' . $row, $transaction['libelle'])
          ->setCellValue('C' . $row, $transaction['debit'])
          ->setCellValue('D' . $row, $transaction['credit'])
          ->setCellValue('E' . $row, $runningBalance);
    $row++;
}

// Add borders to the table only
$sheet->getStyle("A5:E" . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Auto-size columns
foreach (range('A', 'E') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Set page setup
$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);

// Generate dynamic filename
$filename = sprintf('historique_compte_%s_%s.xlsx', $compteId, date('Ymd_His'));

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Write the file
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;