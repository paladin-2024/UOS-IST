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

// Récupération des données POST
$startDate = $_POST['startDate'];
$endDate = $_POST['endDate'];
$structureId = $_POST['structureId'];

// Récupération des créances clients
$receivables = $structureModel->getClientReceivables($structureId, $startDate, $endDate);

// Trier les résultats par noms des clients
usort($receivables, function ($a, $b) {
    return strcmp($a['noms'], $b['noms']);
});

// Création du fichier Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Titre du rapport
$sheet->setCellValue('A1', 'Rapport des créances clients du ' . date('d/m/Y', strtotime($startDate)) . ' au ' . date('d/m/Y', strtotime($endDate)));
$sheet->mergeCells('A1:H1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);


// En-têtes du fichier Excel
$headers = ['Client', 'Numéro de Facture', 'Montant Total', 'Montant Restant', 'Date d\'Échéance', 'Date de Recouvrement', 'Jours de Retard', 'Statut'];
$sheet->fromArray($headers, NULL, 'A2');
$sheet->getStyle('A2:H2')->getFont()->setBold(true);
$sheet->getStyle('A2:H2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');

// Variables de suivi
$row = 3;
$totalAmountGlobal = 0;
$totalOutstandingGlobal = 0;
$currentClient = null;
$subtotalAmount = 0;
$subtotalOutstanding = 0;

foreach ($receivables as $receivable) {
    $recoveryDate = date('Y-m-d', strtotime($receivable['due_date'] . ' +30 days'));
    $daysOverdue = max(0, (strtotime(date('Y-m-d')) - strtotime($recoveryDate)) / (60 * 60 * 24));

    // Si le client change, insérer une ligne de sous-total avant de passer au suivant
    if ($currentClient !== null && $currentClient !== $receivable['noms']) {
        // Ajouter une ligne de sous-total
        $row++;
        $sheet->setCellValue('A' . $row, 'Sous-total pour ' . $currentClient);
        $sheet->mergeCells('A' . $row . ':B' . $row);
        $sheet->setCellValue('C' . $row, $subtotalAmount);
        $sheet->setCellValue('D' . $row, $subtotalOutstanding);
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true)->getColor()->setARGB(Color::COLOR_DARKBLUE);
        $sheet->getStyle("A{$row}:H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDDDDDD');

        // Réinitialiser les sous-totaux
        $subtotalAmount = 0;
        $subtotalOutstanding = 0;
        $row++;
    }

    $currentClient = $receivable['noms'];
    
    // Données de la ligne
    $data = [
        $receivable['noms'],
        $receivable['numeroFacture'],
        $receivable['total_amount'],  
        $receivable['outstanding_amount'],
        date('d/m/Y', strtotime($receivable['due_date'])),
        date('d/m/Y', strtotime($recoveryDate)),
        $daysOverdue,
        $receivable['status']
    ];

    $sheet->fromArray($data, NULL, 'A' . $row);

    // Mise en rouge des factures en retard
    if ($daysOverdue > 0) {
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->getColor()->setARGB(Color::COLOR_RED);
    }

    // Ajout des montants aux sous-totaux et totaux
    $subtotalAmount += $receivable['total_amount'];
    $subtotalOutstanding += $receivable['outstanding_amount'];
    $totalAmountGlobal += $receivable['total_amount'];
    $totalOutstandingGlobal += $receivable['outstanding_amount'];

    $row++;
}

// Ajouter le dernier sous-total si nécessaire
if ($currentClient !== null) {
    $sheet->setCellValue('A' . $row, 'Sous-total pour ' . $currentClient);
    $sheet->mergeCells('A' . $row . ':B' . $row);
    $sheet->setCellValue('C' . $row, $subtotalAmount);
    $sheet->setCellValue('D' . $row, $subtotalOutstanding);
    $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true)->getColor()->setARGB(Color::COLOR_DARKBLUE);
    $sheet->getStyle("A{$row}:H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDDDDDD');

    $row++;
}

// Ajouter la ligne de total général
$sheet->setCellValue('A' . $row, 'Total Général');
$sheet->mergeCells('A' . $row . ':B' . $row);
$sheet->setCellValue('C' . $row, $totalAmountGlobal);
$sheet->setCellValue('D' . $row, $totalOutstandingGlobal);
$sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
$sheet->getStyle("A{$row}:H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');

// Ajout de bordures
$sheet->getStyle('A2:H' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Ajustement automatique de la taille des colonnes
foreach (range('A', 'H') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Génération du nom de fichier avec date et heure
$dateNow = date('Y-m-d_H-i-s');
$fileName = "creances_clients_{$startDate}_au_{$endDate}_{$dateNow}.xlsx";


// Définition des en-têtes pour le téléchargement
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$fileName\"");
header('Cache-Control: max-age=0');

// Écriture du fichier et sortie
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
