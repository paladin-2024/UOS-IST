<?php
session_start();
require_once '../config/Connexion.php';
require_once '../models/Agent.php';
require_once '../models/Structure.php';
require_once '../models/Service.php';
require_once '../models/Grade.php';
require_once '../vendor/autoload.php'; // Assurez-vous que PhpSpreadsheet est installé via Composer

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Vérification de la connexion et des droits
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

// Récupérer les paramètres de filtrage
$search = isset($_GET['search']) ? $_GET['search'] : '';
$typeAgent = isset($_GET['typeAgent']) ? $_GET['typeAgent'] : '';
$gradeId = isset($_GET['gradeId']) ? (int)$_GET['gradeId'] : 0;
$structureId = isset($_GET['structureId']) ? (int)$_GET['structureId'] : 0;
$serviceId = isset($_GET['serviceId']) ? (int)$_GET['serviceId'] : 0;

// Initialiser les modèles
$agent = new Agent();
$structure = new Structure();
$service = new Service();
$grade = new Grade();

// Récupérer les agents filtrés
$agents = $agent->getFilteredAgents($search, $typeAgent, $gradeId, $structureId, $serviceId);

// Créer un nouveau classeur
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Liste des agents');

// Définir les en-têtes
$sheet->setCellValue('A1', 'ID');
$sheet->setCellValue('B1', 'Noms');
$sheet->setCellValue('C1', 'Matricule');
$sheet->setCellValue('D1', 'Type');
$sheet->setCellValue('E1', 'Grade');
$sheet->setCellValue('F1', 'Téléphone');
$sheet->setCellValue('G1', 'Email');
$sheet->setCellValue('H1', 'Structure');
$sheet->setCellValue('I1', 'Service');

// Style des en-têtes
$sheet->getStyle('A1:I1')->getFont()->setBold(true);
$sheet->getStyle('A1:I1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');

// Remplir les données
$row = 2;
foreach ($agents as $a) {
    // Récupérer les informations supplémentaires
    $gradeInfo = !empty($a['grade_id']) ? $grade->getGradeById($a['grade_id']) : null;
    $gradeName = $gradeInfo ? $gradeInfo['designation'] : '-';
    
    $serviceInfo = !empty($a['idService']) ? $service->getServiceById($a['idService']) : null;
    $serviceName = $serviceInfo ? $serviceInfo['designation'] : '-';
    
    // Ajouter la ligne
    $sheet->setCellValue('A' . $row, $a['idAgent']);
    $sheet->setCellValue('B' . $row, $a['noms']);
    $sheet->setCellValue('C' . $row, empty($a['matricule']) ? '-' : $a['matricule']);
    $sheet->setCellValue('D' . $row, empty($a['type_agent']) ? '-' : $a['type_agent']);
    $sheet->setCellValue('E' . $row, $gradeName);
    $sheet->setCellValue('F' . $row, $a['telephone']);
    $sheet->setCellValue('G' . $row, $a['email']);
    $sheet->setCellValue('H' . $row, $a['designationStructure']);
    $sheet->setCellValue('I' . $row, $serviceName);
    
    $row++;
}

// Ajuster automatiquement les colonnes
foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Créer le fichier Excel
$writer = new Xlsx($spreadsheet);
$filename = 'liste_agents_' . date('Y-m-d_H-i-s') . '.xlsx';

// En-têtes HTTP pour le téléchargement
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Envoyer le fichier au navigateur
$writer->save('php://output');
exit;
