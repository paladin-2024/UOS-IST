<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/JournalServeur.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

// Récupérer les filtres depuis GET
$filtres = [];
if (!empty($_GET['type_action'])) $filtres['type_action'] = $_GET['type_action'];
if (!empty($_GET['module'])) $filtres['module'] = $_GET['module'];
if (!empty($_GET['statut'])) $filtres['statut'] = $_GET['statut'];
if (!empty($_GET['date_debut'])) $filtres['date_debut'] = $_GET['date_debut'];
if (!empty($_GET['date_fin'])) $filtres['date_fin'] = $_GET['date_fin'];
if (!empty($_GET['recherche'])) $filtres['recherche'] = $_GET['recherche'];

$journal = new JournalServeur();

// Récupérer tous les logs sans pagination pour l'export
$resultats = $journal->obtenirLogs($filtres, 1, 10000);
$logs = $resultats['logs'] ?? [];

// Créer le spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Journal Serveur');

// Headers
$headers = ['#', 'Date/Heure', 'Utilisateur', 'Type', 'Module', 'Description', 'Statut', 'IP', 'Table', 'ID Enregistrement'];
foreach ($headers as $i => $h) {
    $col = Coordinate::stringFromColumnIndex($i + 1);
    $sheet->setCellValue($col . '1', $h);
    // Style header
    $sheet->getStyle($col . '1')->getFont()->setBold(true);
    $sheet->getStyle($col . '1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFD3D3D3');
}

// Données
$r = 2;
$i = 1;
foreach ($logs as $log) {
    $sheet->setCellValue('A' . $r, $i++);
    $sheet->setCellValue('B' . $r, date('d/m/Y H:i:s', strtotime($log['date_creation'])));
    $sheet->setCellValue('C' . $r, $log['nom_utilisateur'] ?? 'Système');
    $sheet->setCellValue('D' . $r, $log['type_action']);
    $sheet->setCellValue('E' . $r, $log['module'] ?? '-');
    $sheet->setCellValue('F' . $r, $log['description']);
    $sheet->setCellValue('G' . $r, ucfirst($log['statut']));
    $sheet->setCellValue('H' . $r, $log['adresse_ip']);
    $sheet->setCellValue('I' . $r, $log['table_affectee'] ?? '-');
    $sheet->setCellValue('J' . $r, $log['id_enregistrement'] ?? '-');
    $r++;
}

// Autosize colonnes
foreach (range('A', 'J') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Largeur minimum pour la description
$sheet->getColumnDimension('F')->setWidth(40);

// Output
$filename = 'journal_serveur_' . date('Y-m-d_H-i-s') . '.xlsx';
while (ob_get_level() > 0) { ob_end_clean(); }
if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
@ini_set('zlib.output_compression', 'Off');

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
exit;
?>
