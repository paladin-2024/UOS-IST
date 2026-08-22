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

// Récupération des créances fournisseurs
$debts = $structureModel->getSupplierDebts($structureId, $startDate, $endDate);

// Création d'un fichier Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Titre du rapport
$title = sprintf('Rapport des Dettes Fournisseurs du %s au %s', date('d/m/Y', strtotime($startDate)), date('d/m/Y', strtotime($endDate)));
$sheet->setCellValue('A1', $title);
$sheet->mergeCells('A1:H1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// En-têtes du fichier Excel
$headers = ['Fournisseur', 'Numéro de Facture', 'Montant Total', 'Montant Restant', 'Date d\'Échéance', 'Date de Recouvrement', 'Jours de Retard', 'Statut'];
$sheet->fromArray($headers, NULL, 'A2');
$sheet->getStyle('A2:H2')->getFont()->setBold(true);
$sheet->getStyle('A2:H2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');

// Remplir les données
$row = 3;
$totalDebt = 0;
$totalOutstandingDebt = 0;

$previousSupplier = ''; // Pour regrouper les lignes par fournisseur
$supplierTotalDebt = 0;
$supplierTotalOutstandingDebt = 0;

foreach ($debts as $debt) {
    // Calculer les jours de retard
    $recoveryDate = date('Y-m-d', strtotime($debt['due_date'] . ' +30 days'));
    $daysOverdue = max(0, (strtotime(date('Y-m-d')) - strtotime($recoveryDate)) / (60 * 60 * 24));

    // Données à insérer dans la feuille Excel
    $data = [
        $debt['supplier_name'],
        $debt['invoice_number'],
        (float) $debt['total_amount'], // Assurer que le montant soit un nombre réel
        (float) $debt['outstanding_amount'], // Assurer que le montant soit un nombre réel
        date('d/m/Y', strtotime($debt['due_date'])),
        date('d/m/Y', strtotime($recoveryDate)),
        $daysOverdue,
        $debt['status']
    ];

    // Si le fournisseur change, ajouter le sous-total pour le fournisseur précédent
    if ($previousSupplier != '' && $previousSupplier != $debt['supplier_name']) {
        // Ajouter le sous-total pour le fournisseur précédent
        $sheet->setCellValue('A' . $row, 'Sous-total pour ' . $previousSupplier);
        $sheet->mergeCells('A' . $row . ':B' . $row);
        $sheet->setCellValue('C' . $row, $supplierTotalDebt);
        $sheet->setCellValue('D' . $row, $supplierTotalOutstandingDebt);
        $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true)->getColor()->setARGB(Color::COLOR_DARKBLUE);
        $sheet->getStyle('A' . $row . ':D' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');
        $row++; // Avancer d'une ligne après le sous-total
        
        // Réinitialiser les sous-totaux pour le nouveau fournisseur
        $supplierTotalDebt = 0;
        $supplierTotalOutstandingDebt = 0;
    }

    // Ajouter les données de la ligne
    $sheet->fromArray($data, NULL, 'A' . $row);
    
    // Si le nombre de jours de retard est supérieur à 0, on applique une couleur rouge au texte
    if ($daysOverdue > 0) {
        $sheet->getStyle('A' . $row . ':H' . $row)->getFont()->getColor()->setRGB(Color::COLOR_RED);
    }

    // Mise à jour des totaux pour le fournisseur courant
    $supplierTotalDebt += $debt['total_amount'];
    $supplierTotalOutstandingDebt += $debt['outstanding_amount'];
    
    // Mise à jour des totaux globaux
    $totalDebt += $debt['total_amount'];
    $totalOutstandingDebt += $debt['outstanding_amount'];

    $previousSupplier = $debt['supplier_name']; // Mettre à jour le fournisseur courant
    $row++; // Passer à la ligne suivante
}

// Ajouter le sous-total du dernier fournisseur après la dernière ligne
if ($previousSupplier != '') {
    $sheet->setCellValue('A' . $row, 'Sous-total pour ' . $previousSupplier);
    $sheet->mergeCells('A' . $row . ':B' . $row);
    $sheet->setCellValue('C' . $row, $supplierTotalDebt);
    $sheet->setCellValue('D' . $row, $supplierTotalOutstandingDebt);
    $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true)->getColor()->setARGB(Color::COLOR_DARKBLUE);
    $sheet->getStyle('A' . $row . ':D' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');
    $row++; // Avancer d'une ligne après le sous-total
}

// Ajouter la ligne des totaux
$sheet->setCellValue('A' . $row, 'Totaux');
$sheet->mergeCells('A' . $row . ':B' . $row);
$sheet->setCellValue('C' . $row, $totalDebt);
$sheet->setCellValue('D' . $row, $totalOutstandingDebt);
$sheet->getStyle('A' . $row . ':H' . $row)->getFont()->setBold(true);
$sheet->getStyle('A' . $row . ':H' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');

// Ajouter les bordures pour toutes les cellules
$sheet->getStyle('A2:H' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Ajustement automatique de la taille des colonnes
foreach (range('A', 'H') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Générer le nom de fichier avec la date, heure, minute et seconde
$dateNow = date('Y-m-d_H-i-s');
$fileName = "rapport_dettes_fournisseurs_{$dateNow}.xlsx";

// Définir les en-têtes pour le téléchargement
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$fileName\"");
header('Cache-Control: max-age=0');

// Écrire le fichier et l'envoyer au navigateur
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
