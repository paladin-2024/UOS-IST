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

// Start session and check user authentication
session_start();
if (!isset($_SESSION['id'])) {
    die("Accès refusé.");
}

$userId = $_SESSION['id'];
$compteModel = new Comptabilite();
$structureModel = new Structure();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $journalId = $_GET['journal'];
    $startDate = $_GET['startDate'];
    $endDate = $_GET['endDate'];

    // Fetch entries for the selected period
    $entries = $compteModel->getEcrituresByJournalAndPeriod($journalId, $startDate, $endDate);

    // Fetch opening balance
    $openingBalanceData = $compteModel->getReportPeriodBalances($journalId, $startDate);
    $openingBalance = $openingBalanceData['report_debit'] - $openingBalanceData['report_credit'];

    // Fetch structure information
    $structureInfo = $structureModel->getStructureByJournal($journalId);

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

    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator("Your Company")
        ->setTitle("Journal Entry Details")
        ->setSubject("Journal Entry Details")
        ->setDescription("Generated journal entry details.");

    // Add table title
    $journalName = $structureInfo['nom_journal']; // Assuming this field exists
    $title = sprintf('Journal: %s | Période: %s - %s', $journalName, date('d/m/Y', strtotime($startDate)), date('d/m/Y', strtotime($endDate)));
    $sheet->setCellValue('A' . $row, $title);
    $sheet->mergeCells("A$row:F$row");
    $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row += 2;

    // Add table headers
    $sheet->setCellValue('A' . $row, 'Date')
          ->setCellValue('B' . $row, 'Numéro de Pièce')
          ->setCellValue('C' . $row, 'Libellé')
          ->setCellValue('D' . $row, 'Compte')
          ->setCellValue('E' . $row, 'Débit')
          ->setCellValue('F' . $row, 'Crédit');
    $sheet->getStyle('A' . $row . ':F' . $row)->getFont()->setBold(true);
    $sheet->getStyle('A' . $row . ':F' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
    $sheet->getStyle('A' . $row . ':F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Initialize totals
    $totalDebit = $openingBalanceData['report_debit'];
    $totalCredit = $openingBalanceData['report_credit'];
    $row++;

    // Add opening balance row
    $sheet->setCellValue('A' . $row, 'Solde de Report')
          ->setCellValue('E' . $row, number_format($openingBalanceData['report_debit'], 2))
          ->setCellValue('F' . $row, number_format($openingBalanceData['report_credit'], 2));
    $row++;

    // Add entries and their details
    foreach ($entries as $entry) {
        $details = $compteModel->getDetailsByEcritureId($entry['idEcriture']);
        foreach ($details as $detail) {
            $totalDebit += $detail['debit'];
            $totalCredit += $detail['credit'];
            $sheet->setCellValue('A' . $row, $entry['dateEcriture'])
                  ->setCellValue('B' . $row, $entry['numeroPiece'])
                  ->setCellValue('C' . $row, $entry['libelle'])
                  ->setCellValue('D' . $row, $detail['compte'])
                  ->setCellValue('E' . $row, number_format($detail['debit'], 2))
                  ->setCellValue('F' . $row, number_format($detail['credit'], 2));
            $row++;
        }
    }

    // Add total row
    $sheet->setCellValue('D' . $row, 'Total')
          ->setCellValue('E' . $row, number_format($totalDebit, 2))
          ->setCellValue('F' . $row, number_format($totalCredit, 2));
    $sheet->getStyle("D$row:F$row")->getFont()->setBold(true);

    // Add borders to the table
    $sheet->getStyle('A5:F' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Auto-size columns
    foreach (range('A', 'F') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // Set page setup
    $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
    $sheet->getPageSetup()->setFitToWidth(1);
    $sheet->getPageSetup()->setFitToHeight(0);

    // Generate dynamic filename
    $filename = sprintf('journal_entry_details_%s_%s.xlsx', $journalId, date('Ymd_His'));

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    // Write the file
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>