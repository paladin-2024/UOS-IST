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

// Vérifier si l'utilisateur est connecté
session_start();
if (!isset($_SESSION['id'])) {
    die("Accès refusé.");
}

$userId = $_SESSION['id'];
$structureId = $_POST['structureId'];
$accountId = $_POST['accountId'];

// Instancier les modèles
$structureModel = new Structure();
$structureInfo = $structureModel->getStructureById($structureId);

$accountModel = new Structure();
$results = $accountModel->getAccountHistory($structureId, $accountId);

if (empty($results)) {
    die("Aucune donnée trouvée pour ce compte.");
}

// Récupération des infos du compte
$numeroCompte = $results[0]['numeroCompte'] ?? 'Inconnu';
$intituleCompte = $results[0]['intituleCompte'] ?? 'Compte sans nom';
$classeCompte = $results[0]['classeCompte'] ?? '0';

// Création du fichier Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// ✅ Intégration du logo et informations de la structure
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

// ✅ Titre du document avec numéro et libellé du compte
$title = sprintf('Historique du Compte [%s] - %s', $numeroCompte, $intituleCompte);
$sheet->setCellValue('A' . $row, $title);
$sheet->mergeCells("A$row:E$row");
$sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(14);
$sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$row += 2;

// ✅ En-têtes des colonnes
$headers = ['Date', 'Description', 'Débit', 'Crédit', 'Solde'];
$sheet->fromArray($headers, NULL, 'A' . $row);
$sheet->getStyle("A$row:E$row")->getFont()->setBold(true);
$sheet->getStyle("A$row:E$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
$sheet->getStyle("A$row:E$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// ✅ Initialisation du solde
$balance = 0;
$is_debit_account = in_array($classeCompte, ['1', '2', '3', '4', '6']);
$is_credit_account = in_array($classeCompte, ['1', '4', '5', '7']);

$row++;
foreach ($results as $data) {
    $dateOperation = date('d/m/Y', strtotime($data['dateOperation']));
    $description = $data['libele']." ".$data['numPiece'];
    $debit = floatval($data['montant_debit']);
    $credit = floatval($data['montant_credit']);

    if ($is_debit_account) {
        $balance += $debit - $credit;
    } elseif ($is_credit_account) {
        $balance += $credit - $debit;
    }

    $sheet->setCellValue('A' . $row, $dateOperation);
    $sheet->setCellValue('B' . $row, $description);
    $sheet->setCellValue('C' . $row, $debit > 0 ? number_format($debit, 2) . ' $' : '-');
    $sheet->setCellValue('D' . $row, $credit > 0 ? number_format($credit, 2) . ' $' : '-');
    $sheet->setCellValue('E' . $row, number_format($balance, 2) . ' $');

    $row++;
}

// ✅ Ajout des bordures
$sheet->getStyle("A5:E" . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// ✅ Auto-ajustement des colonnes
foreach (range('A', 'E') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// ✅ Mise en échelle pour tenir en largeur sur une seule page
$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0); // Nombre de pages en hauteur non fixé

// ✅ Vérification et création du dossier "exports"
$exportDir = '../exports/';
if (!is_dir($exportDir)) {
    mkdir($exportDir, 0777, true);
}

// ✅ Génération du fichier Excel
$filename = 'historique_compte_' . $numeroCompte . '_' . date('Ymd_His') . '.xlsx';
$filepath = $exportDir . $filename;
$writer = new Xlsx($spreadsheet);
$writer->save($filepath);

// ✅ Téléchargement du fichier
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
readfile($filepath);

// ✅ Suppression du fichier après téléchargement
unlink($filepath);
exit;
?>
