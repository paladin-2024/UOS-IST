<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Comptabilite.php';
require_once dirname(__DIR__) . '/models/Structure.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

// Start session and check user authentication
session_start();
if (!isset($_SESSION['id'])) {
    die("Access denied.");
}

$compteModel = new Comptabilite();
$structureModel = new Structure();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $structureId = $_POST['structure'];
    $startDate = $_POST['startDate'];
    $endDate = $_POST['endDate'];

    $comptes = $compteModel->getComptesByStructure($structureId);
    $structureInfo = $structureModel->getStructureById($structureId);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Add logo and structure information
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
        $sheet->setCellValue('B2', 'Address: ' . $structureInfo['adresse']);
        $sheet->setCellValue('B3', 'Phone: ' . $structureInfo['phone1']);
        $sheet->mergeCells('B1:G1');
        $sheet->mergeCells('B2:G2');
        $sheet->mergeCells('B3:G3');

        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('B1:B3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator($structureInfo['designation'])
        ->setTitle("Balance Générale")
        ->setSubject("Balance Générale")
        ->setDescription("Balance Générale for the selected structure and period.");

    // Add document title with date range
    $title = sprintf('Balance Générale du %s au %s', date('d/m/Y', strtotime($startDate)), date('d/m/Y', strtotime($endDate)));
    $sheet->setCellValue('A5', $title);
    $sheet->mergeCells('A5:F5');
    $sheet->getStyle('A5')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Add header
    $sheet->setCellValue('A6', 'Numéro Compte')
          ->setCellValue('B6', 'Intitulé Compte')
          ->setCellValue('C6', 'Débit')
          ->setCellValue('D6', 'Crédit')
          ->setCellValue('E6', 'Solde');
    $sheet->getStyle('A6:E6')->getFont()->setBold(true);
    $sheet->getStyle('A6:E6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A6:E6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');

    $row = 7;
    $totalDebit = 0;
    $totalCredit = 0;
    $totalBalance = 0;

    foreach ($comptes as $compte) {
        $compteId = $compte['idCompte'];
        $classeCompte = $compte['classeCompte'];
        $is_debit_account = in_array($classeCompte, ['2', '3', '4', '5', '6']);
        $is_credit_account = in_array($classeCompte, ['1', '7']);

        $reportBalances = $compteModel->getReportPeriodBalancesByCompte($compteId, $startDate);
        $reportDebit = $reportBalances['report_debit'];
        $reportCredit = $reportBalances['report_credit'];

        $transactions = $compteModel->getTransactionsByCompteAndPeriod($compteId, $startDate, $endDate);

        $debit = $reportDebit;
        $credit = $reportCredit;

        foreach ($transactions as $transaction) {
            $debit += $transaction['debit'];
            $credit += $transaction['credit'];
        }

        if ($is_debit_account) {
            $balance = $debit - $credit;
        } elseif ($is_credit_account) {
            $balance = $credit - $debit;
        }

        if ($balance != 0) {
            $sheet->setCellValue('A' . $row, $compte['numeroCompte'])
                  ->setCellValue('B' . $row, $compte['intituleCompte'])
                  ->setCellValue('C' . $row, $debit)
                  ->setCellValue('D' . $row, $credit)
                  ->setCellValue('E' . $row, $balance);
            $row++;

            $totalDebit += $debit;
            $totalCredit += $credit;
            $totalBalance += $balance;
        }
    }

    // Add total row
    $sheet->setCellValue('A' . $row, 'Total Général')
          ->setCellValue('C' . $row, $totalDebit)
          ->setCellValue('D' . $row, $totalCredit)
          ->setCellValue('E' . $row, '');
    $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);

    // Add borders
    $sheet->getStyle("A6:E" . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Auto-size columns
    foreach (range('A', 'E') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // Page setup
    $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
    $sheet->getPageSetup()->setFitToWidth(1);
    $sheet->getPageSetup()->setFitToHeight(0);

    $filename = 'balance_generale_' . date('Ymd_His') . '.xlsx';
    $writer = new Xlsx($spreadsheet);

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="'.$filename.'"');
    header('Cache-Control: max-age=0');

    // Write the file
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>