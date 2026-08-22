<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

// Initialisation du modèle
$structureModel = new Structure();

// Récupérer les dates de début et de fin depuis POST
$structureId = $_POST['structureId'] ?? 1; // Exemple d'ID de structure, défaut à 1 si non fourni
$startDate = $_POST['startDate'] ?? '2023-01-01'; // Date de début par défaut si non fournie
$endDate = $_POST['endDate'] ?? '2023-12-31'; // Date de fin par défaut si non fournie

// Récupérer les dépenses périodiques
$expenses = $structureModel->getPeriodicExpenses($structureId, $startDate, $endDate);

// Trier par motif
usort($expenses, function ($a, $b) {
    return strcmp($a['motifD'], $b['motifD']);
});

// Création de la feuille Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Titre du rapport
$sheet->setCellValue('A1', 'Rapport des dépenses du ' . date('d/m/Y'));
$sheet->mergeCells('A1:E1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB(Color::COLOR_WHITE);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('4F81BD'); // Couleur d'arrière-plan

// En-têtes du tableau
$headers = ['Motif', 'Montant', 'Bénéficiaire', 'Date d\'Opération', 'Date d\'Enregistrement'];
$sheet->fromArray($headers, NULL, 'A2');
$sheet->getStyle('A2:E2')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A2:E2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC'); // Couleur de fond pour les en-têtes
$sheet->getStyle('A2:E2')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN); // Bordures fines

// Variables pour le suivi des totaux
$row = 3;
$totalAmount = 0;

// Remplissage des données
foreach ($expenses as $expense) {
    $data = [
        $expense['motifD'],
        $expense['montantD'],
        $expense['beneficiaire'],
        date('d/m/Y', strtotime($expense['dateoperation'])),
        date('d/m/Y H:i:s', strtotime($expense['dateEnregistrement']))
    ];

    // Insérer les données dans le tableau
    $sheet->fromArray($data, NULL, 'A' . $row);

    // Formatage des montants en nombre réel
    $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0.00'); // Format pour les montants avec décimales

    $totalAmount += $expense['montantD']; // Accumuler les montants
    $row++;
}

// Lignes de Totaux
$sheet->setCellValue('A' . $row, 'Total');
$sheet->mergeCells('A' . $row . ':B' . $row);
$sheet->setCellValue('C' . $row, $totalAmount);
$sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true)->getColor()->setARGB(Color::COLOR_WHITE);
$sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('4F81BD'); // Fond bleu pour la ligne Total
$sheet->getStyle("A{$row}:E{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN); // Bordures fines

// Application des bordures à tout le tableau
$sheet->getStyle('A2:E' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Ajustement automatique de la largeur des colonnes
foreach (range('A', 'E') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Nom du fichier
$fileName = 'expenses_report_' . date('Y-m-d_H-i-s') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$fileName\"");
header('Cache-Control: max-age=0');

// Génération du fichier Excel
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>