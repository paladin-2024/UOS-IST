<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
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
    header('Location: ../configuration/orientation');
    exit;
}

$idSection = isset($_POST['idSection']) ? $_POST['idSection'] : 'all';
$idOrientation = isset($_POST['idOrientation']) ? $_POST['idOrientation'] : 'all';
$includeManagers = isset($_POST['includeManagers']) ? true : false;

// Instancier le modèle et récupérer les données
$universite = new Universite();
$data = $universite->getOrientationsForExport($idSection, $idOrientation);

// Créer un nouveau document Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Orientations');

// Styles pour les différents niveaux de regroupement
$styleSection = [
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
];

$styleOrientation = [
    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '5B9BD5']],
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

// Organiser les données par hiérarchie
$organizedData = [];
foreach ($data as $orientation) {
    $sectionId = $orientation['section_idsection'] ?? 'unknown';
    $orientationId = $orientation['idorientation'] ?? 'unknown';
    
    if (!isset($organizedData[$sectionId])) {
        $organizedData[$sectionId] = [
            'name' => $orientation['sectionDesignation'],
            'orientations' => []
        ];
    }
    
    if (!isset($organizedData[$sectionId]['orientations'][$orientationId])) {
        $organizedData[$sectionId]['orientations'][$orientationId] = [
            'name' => $orientation['designationOrientation'],
            'dateCreation' => $orientation['dateCreation'],
            'managers' => []
        ];
    }
    
    // Si on inclut les responsables et qu'il y a des données de responsables
    if ($includeManagers && isset($orientation['idresponsable_orientation'])) {
        $organizedData[$sectionId]['orientations'][$orientationId]['managers'][] = [
            'noms' => $orientation['noms'] ?? '',
            'fonction' => $orientation['fonction'] ?? '',
            'anneeDesignation' => $orientation['anneeDesignation'] ?? '',
            'dateEnregistrement' => $orientation['dateEnregistrement'] ?? ''
        ];
    }
}

// Écrire les données dans le fichier Excel
$row = 1;

// En-tête du document
$sheet->setCellValue('A1', 'LISTE DES ORIENTATIONS');
$sheet->mergeCells('A1:E1');
$sheet->getStyle('A1:E1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 16],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);
$row += 2;

foreach ($organizedData as $sectionId => $section) {
    // En-tête de la section
    $sheet->setCellValue('A' . $row, 'Section: ' . $section['name']);
    $sheet->mergeCells('A' . $row . ':E' . $row);
    $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($styleSection);
    $sheet->getRowDimension($row)->setRowHeight(25);
    $row++;
    
    foreach ($section['orientations'] as $orientationId => $orientation) {
        // En-tête de l'orientation
        $sheet->setCellValue('A' . $row, 'Orientation: ' . $orientation['name']);
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($styleOrientation);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;
        
        // Informations de base sur l'orientation
        $sheet->setCellValue('A' . $row, 'Date de création:');
        $sheet->setCellValue('B' . $row, date('d/m/Y H:i:s', strtotime($orientation['dateCreation'])));
        $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
        ]);
        $row++;
        
        // Si on inclut les responsables et qu'il y en a
        if ($includeManagers && !empty($orientation['managers'])) {
            $row++;
            $sheet->setCellValue('A' . $row, 'Responsables:');
            $sheet->mergeCells('A' . $row . ':E' . $row);
            $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray([
                'font' => ['bold' => true, 'italic' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDEBF7']]
            ]);
            $row++;
            
            // En-têtes des colonnes pour les responsables
            $sheet->setCellValue('A' . $row, 'N°');
            $sheet->setCellValue('B' . $row, 'Nom');
            $sheet->setCellValue('C' . $row, 'Fonction');
            $sheet->setCellValue('D' . $row, 'Année Académique');
            $sheet->setCellValue('E' . $row, 'Date d\'enregistrement');
            $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($styleHeader);
            $row++;
            
            // Données des responsables
            $count = 1;
            $startDataRow = $row;
            foreach ($orientation['managers'] as $manager) {
                $sheet->setCellValue('A' . $row, $count);
                $sheet->setCellValue('B' . $row, $manager['noms']);
                $sheet->setCellValue('C' . $row, $manager['fonction']);
                $sheet->setCellValue('D' . $row, $manager['anneeDesignation']);
                $sheet->setCellValue('E' . $row, date('d/m/Y', strtotime($manager['dateEnregistrement'])));
                $count++;
                $row++;
            }
            
            // Appliquer le style aux données
            if ($row > $startDataRow) {
                $sheet->getStyle('A' . $startDataRow . ':E' . ($row - 1))->applyFromArray($styleData);
            }
        }
        
        // Ajouter un espace après chaque orientation
        $row += 2;
    }
    
    // Ajouter un espace après chaque section
    $row++;
}

// Ajuster la largeur des colonnes
foreach (range('A', 'E') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

// Créer le nom du fichier
$fileName = 'Orientations_' . date('Y-m-d_H-i-s') . '.xlsx';

// Définir les en-têtes HTTP pour le téléchargement
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

// Créer le writer et envoyer le fichier au navigateur
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>