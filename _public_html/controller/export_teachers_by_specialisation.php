<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/UniteRecherche.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

// Vérifications d'authentification et récupération des paramètres
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../ur/affecation_ur');
    exit;
}

$idUniteRecherche = isset($_POST['idUniteRecherche']) ? $_POST['idUniteRecherche'] : 'all';
$idSection = isset($_POST['idSection']) ? $_POST['idSection'] : 'all';
$includeDetails = isset($_POST['includeDetails']) ? true : false;

// Instancier le modèle et récupérer les données
$uniteRecherche = new UniteRecherche();
$data = $uniteRecherche->getTeachersForExport($idUniteRecherche, $idSection);

// Créer un nouveau document Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Enseignants par spécialisation');

// Styles pour les différents niveaux de regroupement
$styleUR = [
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
];

$styleSection = [
    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '5B9BD5']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
];

$styleSpecialisation = [
    'font' => ['bold' => true, 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDEBF7']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
];

$styleHeader = [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];

$styleData = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
];

// Définir les en-têtes des colonnes pour les enseignants
$teacherHeaders = ['N°', 'Nom de l\'enseignant'];
if ($includeDetails) {
    $teacherHeaders = array_merge($teacherHeaders, ['Grade', 'Service']);
}
$teacherHeaders[] = 'Date d\'affectation';

// Organiser les données par hiérarchie
$organizedData = [];
foreach ($data as $teacher) {
    $urId = $teacher['idunite_recherche'] ?? 'unknown';
    $sectionId = $teacher['idsection'] ?? 'unknown';
    $specialisationId = $teacher['idSpecialisation'] ?? 'unknown';
    
    if (!isset($organizedData[$urId])) {
        $organizedData[$urId] = [
            'name' => $teacher['designation_UR'],
            'sections' => []
        ];
    }
    
    if (!isset($organizedData[$urId]['sections'][$sectionId])) {
        $organizedData[$urId]['sections'][$sectionId] = [
            'name' => $teacher['designationSection'],
            'specialisations' => []
        ];
    }
    
    if (!isset($organizedData[$urId]['sections'][$sectionId]['specialisations'][$specialisationId])) {
        $organizedData[$urId]['sections'][$sectionId]['specialisations'][$specialisationId] = [
            'name' => $teacher['designation'],
            'teachers' => []
        ];
    }
    
    $organizedData[$urId]['sections'][$sectionId]['specialisations'][$specialisationId]['teachers'][] = $teacher;
}

// Écrire les données dans le fichier Excel
$row = 1;

foreach ($organizedData as $urId => $ur) {
    // En-tête de l'unité de recherche
    $sheet->setCellValue('A' . $row, 'Unité de Recherche: ' . $ur['name']);
    $lastCol = $includeDetails ? 'E' : 'C';
    $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray($styleUR);
    $sheet->getRowDimension($row)->setRowHeight(25);
    $row++;
    
    foreach ($ur['sections'] as $sectionId => $section) {
        // En-tête de la section
        $sheet->setCellValue('A' . $row, 'Section: ' . $section['name']);
        $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray($styleSection);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;
        
        foreach ($section['specialisations'] as $specialisationId => $specialisation) {
            // En-tête de la spécialisation
            $sheet->setCellValue('A' . $row, 'Spécialisation: ' . $specialisation['name']);
            $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray($styleSpecialisation);
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
            
            // En-têtes des colonnes pour les enseignants
            $col = 'A';
            foreach ($teacherHeaders as $header) {
                $sheet->setCellValue($col . $row, $header);
                $col++;
            }
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray($styleHeader);
            $row++;
            
            // Données des enseignants
$count = 1;
$startDataRow = $row;
foreach ($specialisation['teachers'] as $teacher) {
    // Utiliser un tableau de colonnes pour éviter les problèmes d'incrémentation
    $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
    $colIndex = 0;
    
    // Numéro
    $sheet->setCellValue($columns[$colIndex] . $row, $count);
    $colIndex++;
    
    // Nom de l'enseignant
    $sheet->setCellValue($columns[$colIndex] . $row, $teacher['noms']);
    $colIndex++;
    
    // Détails optionnels
    if ($includeDetails) {
        // Grade
        $sheet->setCellValue($columns[$colIndex] . $row, $teacher['gradeDesignation'] ?? '');
        $colIndex++;
        
        // Service
        $sheet->setCellValue($columns[$colIndex] . $row, $teacher['serviceDesignation'] ?? '');
        $colIndex++;
    }
    
    // Date d'affectation
    $dateAffectation = new DateTime($teacher['dateAffectation']);
    $sheet->setCellValue($columns[$colIndex] . $row, $dateAffectation->format('d/m/Y'));
    
    $count++;
    $row++;
}

            
            // Appliquer le style aux données
            if ($row > $startDataRow) {
                $sheet->getStyle('A' . $startDataRow . ':' . $lastCol . ($row - 1))->applyFromArray($styleData);
            }
            
            // Ajouter un espace après chaque groupe de spécialisation
            $row++;
        }
    }
}

// Ajuster la largeur des colonnes
foreach (range('A', $lastCol) as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

// Créer le nom du fichier
$fileName = 'Enseignants_par_specialisation_' . date('Y-m-d_H-i-s') . '.xlsx';

// Définir les en-têtes HTTP pour le téléchargement
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

// Créer le writer et envoyer le fichier au navigateur
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
