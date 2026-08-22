<?php
require 'vendor/autoload.php'; // Adjust the path as necessary

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$compteModel = new Comptabilite();
$userId = $_SESSION['id'];
$compteId = $_GET['compte'];
$startDate = $_GET['startDate'];
$endDate = $_GET['endDate'];

// Calculate report period balances
$reportBalances = $compteModel->getReportPeriodBalancesByCompte($compteId, $startDate);
$reportDebit = $reportBalances['report_debit'];
$reportCredit = $reportBalances['report_credit'];
$runningBalance = $reportDebit - $reportCredit;

// Fetch transactions for the selected period
$transactions = $compteModel->getTransactionsByCompteAndPeriod($compteId, $startDate, $endDate);

// Create a new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set header
$sheet->setCellValue('A1', 'Date')
      ->setCellValue('B1', 'Libellé')
      ->setCellValue('C1', 'Débit')
      ->setCellValue('D1', 'Crédit')
      ->setCellValue('E1', 'Solde');

// Add report balance
$sheet->setCellValue('A2', 'Solde avant ' . $startDate)
      ->setCellValue('C2', $reportDebit)
      ->setCellValue('D2', $reportCredit)
      ->setCellValue('E2', $runningBalance);

// Add transactions
$row = 3;
foreach ($transactions as $transaction) {
    $runningBalance += $transaction['debit'] - $transaction['credit'];
    $sheet->setCellValue('A' . $row, $transaction['date'])
          ->setCellValue('B' . $row, $transaction['libelle'])
          ->setCellValue('C' . $row, $transaction['debit'])
          ->setCellValue('D' . $row, $transaction['credit'])
          ->setCellValue('E' . $row, $runningBalance);
    $row++;
}

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="historique_compte.xlsx"');
header('Cache-Control: max-age=0');

// Write the file
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;