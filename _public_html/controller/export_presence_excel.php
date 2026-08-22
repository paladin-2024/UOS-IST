<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

$start = isset($_GET['start']) ? $_GET['start'] : '';
$end = isset($_GET['end']) ? $_GET['end'] : '';
$structureId = isset($_GET['structureId']) ? intval($_GET['structureId']) : 0;
$serviceId = isset($_GET['serviceId']) ? intval($_GET['serviceId']) : 0;

if ($start === '' || $end === '') {
    die('Paramètres de période manquants.');
}

$agent = new Agent();
$rows = $agent->getPresenceSummary($start, $end, $structureId ?: null, $serviceId ?: null);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Presences');

// Headers
$headers = ['#','Noms','Matricule','Téléphone','Structure','Service','Jours présence'];
foreach ($headers as $i => $h) {
    $col = Coordinate::stringFromColumnIndex($i+1);
    $sheet->setCellValue($col.'1', $h);
}

$r = 2; $i = 1;
foreach ($rows as $row) {
    $sheet->setCellValue('A'.$r, $i++);
    $sheet->setCellValue('B'.$r, $row['noms']);
    $sheet->setCellValue('C'.$r, $row['matricule']);
    $sheet->setCellValue('D'.$r, $row['telephone']);
    $sheet->setCellValue('E'.$r, $row['structure']);
    $sheet->setCellValue('F'.$r, $row['service']);
    $sheet->setCellValue('G'.$r, $row['jours_presence']);
    $r++;
}

// Autosize
foreach (range('A','G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Output direct fiable
$filename = 'presence_' . $start . '_' . $end . '.xlsx';
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
