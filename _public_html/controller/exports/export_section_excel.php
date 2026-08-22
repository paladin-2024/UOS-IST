<?php
session_start();
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';
require_once dirname(dirname(__DIR__)) . '/config/Connexion.php';
require_once dirname(dirname(__DIR__)) . '/models/DepotSoutenance.php';
require_once dirname(dirname(__DIR__)) . '/models/Universite.php';
require_once dirname(dirname(__DIR__)) . '/models/Agent.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing as WorksheetDrawing;

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    header('Location: ../../index.php');
    exit;
}

$idSection = isset($_GET['id_section']) ? intval($_GET['id_section']) : 0;
$anneeAcadId = isset($_GET['annee_acad']) ? intval($_GET['annee_acad']) : 0;

if ($idSection <= 0 || $anneeAcadId <= 0) {
    echo "Paramètres invalides";
    exit;
}

$depotSoutenanceModel = new DepotSoutenance();
$universite = new Universite();

// Récupérer les données
$soutenances = $depotSoutenanceModel->getSoutenancesProgrammeesParSection($idSection, $anneeAcadId);
$configUni = $universite->getConfigurationUniversite();
$sectionInfo = $universite->getSectionById($idSection);
$anneeAcad = $universite->getAcademicYearById($anneeAcadId);

// Regrouper les soutenances par jury
$soutenancesParJury = [];
foreach ($soutenances as $soutenance) {
    $juryKey = !empty($soutenance['jury_designation']) ? $soutenance['jury_designation'] : 'Sans jury assigné';
    if (!isset($soutenancesParJury[$juryKey])) {
        $soutenancesParJury[$juryKey] = [];
    }
    $soutenancesParJury[$juryKey][] = $soutenance;
}

// Créer un nouveau document Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Soutenances par Jury');

// Configurer la page en mode paysage et ajuster à 1 page de largeur
$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);

// Styles pour les différents niveaux
$styleTitle = [
    'font' => ['bold' => true, 'size' => 16],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];

$styleUniversite = [
    'font' => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];

$styleSection = [
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
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
    'alignment' => [
        'vertical' => Alignment::VERTICAL_TOP,
        'wrapText' => true // Active le retour à la ligne automatique
    ],
];

$styleJuryTitle = [
    'font' => ['bold' => true, 'size' => 12],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN]],
];

$styleJuryInfo = [
    'font' => ['bold' => true, 'size' => 10],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]],
];

// Ajouter le logo si disponible
$row = 1;
if (!empty($configUni['logo'])) {
    $logoPath = dirname(dirname(__DIR__)) . '/' . $configUni['logo'];
    if (file_exists($logoPath)) {
        $drawing = new WorksheetDrawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo de l\'université');
        $drawing->setPath($logoPath);
        
        $drawing->setCoordinates('E1');
        $drawing->setOffsetX(80);
        $drawing->setHeight(80);
        $drawing->setWorksheet($sheet);
        
        $sheet->getRowDimension(1)->setRowHeight(60);
        
        $row = 4;
    }
}

// En-tête avec les informations de l'université
$sheet->setCellValue('A' . $row, !empty($configUni['ministere_tutelle']) ? $configUni['ministere_tutelle'] : '');
$sheet->mergeCells('A' . $row . ':H' . $row);
$sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);
$row++;

$sheet->setCellValue('A' . $row, !empty($configUni['nom']) ? $configUni['nom'] : '');
$sheet->mergeCells('A' . $row . ':H' . $row);
$sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($styleUniversite);
$sheet->getRowDimension($row)->setRowHeight(25);
$row++;

// Adresse et coordonnées
$adresse = '';
if (!empty($configUni['adresse'])) $adresse .= $configUni['adresse'];
if (!empty($configUni['ville'])) $adresse .= (!empty($adresse) ? ', ' : '') . $configUni['ville'];

$sheet->setCellValue('A' . $row, $adresse);
$sheet->mergeCells('A' . $row . ':H' . $row);
$sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);
$row++;

$contacts = '';
if (!empty($configUni['telephone'])) $contacts .= 'Tél: ' . $configUni['telephone'];
if (!empty($configUni['email'])) $contacts .= (!empty($contacts) ? ' | ' : '') . 'Email: ' . $configUni['email'];

$sheet->setCellValue('A' . $row, $contacts);
$sheet->mergeCells('A' . $row . ':H' . $row);
$sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);
$row++;

// Ligne de séparation
$sheet->setCellValue('A' . $row, '');
$sheet->mergeCells('A' . $row . ':H' . $row);
$sheet->getStyle('A' . $row . ':H' . $row)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
$row += 2;

// Titre du document
$sheet->setCellValue('A' . $row, 'PROGRAMME DES SOUTENANCES PAR JURY');
$sheet->mergeCells('A' . $row . ':H' . $row);
$sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($styleTitle);
$sheet->getRowDimension($row)->setRowHeight(30);
$row += 2;

// Informations sur la section
$sheet->setCellValue('A' . $row, 'Section: ' . $sectionInfo['designationSection']);
$sheet->mergeCells('A' . $row . ':H' . $row);
$sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($styleSection);
$sheet->getRowDimension($row)->setRowHeight(25);
$row++;

// Informations supplémentaires
$sheet->setCellValue('A' . $row, 'Année Académique:');
$sheet->setCellValue('B' . $row, $anneeAcad['designation']);
$sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray([
    'font' => ['bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
]);
$row += 2;

// Parcourir les jurys et ajouter chacun avec ses soutenances
foreach ($soutenancesParJury as $juryName => $juryGroup) {
    // En-tête du jury
    $sheet->setCellValue('A' . $row, 'Jury: ' . $juryName);
    $sheet->mergeCells('A' . $row . ':H' . $row);
    $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($styleJuryTitle);
    $sheet->getRowDimension($row)->setRowHeight(25);
    $row++;
    
    // Détails du jury si disponibles
    if (!empty($juryGroup[0]['president_nom']) || !empty($juryGroup[0]['secretaire_nom'])) {
        $juryDetails = '';
        if (!empty($juryGroup[0]['president_nom'])) {
            $juryDetails .= 'Président: ' . $juryGroup[0]['president_nom'];
        }
        if (!empty($juryGroup[0]['secretaire_nom'])) {
            $juryDetails .= (!empty($juryDetails) ? ' | ' : '') . 'Secrétaire: ' . $juryGroup[0]['secretaire_nom'];
        }
        
        $sheet->setCellValue('A' . $row, $juryDetails);
        $sheet->mergeCells('A' . $row . ':H' . $row);
        $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($styleJuryInfo);
        $row++;
    }
    
    // En-têtes du tableau de soutenances pour ce jury
    $sheet->setCellValue('A' . $row, 'N°');
    $sheet->setCellValue('B' . $row, 'Date et Heure');
    $sheet->setCellValue('C' . $row, 'Lieu');
    $sheet->setCellValue('D' . $row, 'Étudiant');
    $sheet->setCellValue('E' . $row, 'Matricule');
    $sheet->setCellValue('F' . $row, 'Sujet');
    $sheet->setCellValue('G' . $row, '1er Lecteur');
    $sheet->setCellValue('H' . $row, '2ème Lecteur');
    $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($styleHeader);
    $row++;
    
    // Données des soutenances pour ce jury
    $count = 1;
    $startDataRow = $row;
    
    foreach ($juryGroup as $soutenance) {
        $sheet->setCellValue('A' . $row, $count);
        $sheet->setCellValue('B' . $row, date('d/m/Y H:i', strtotime($soutenance['date_soutenance'])));
        $sheet->setCellValue('C' . $row, $soutenance['lieu']);
        $sheet->setCellValue('D' . $row, $soutenance['nom_etudiant']);
        $sheet->setCellValue('E' . $row, $soutenance['matricule']);
        $sheet->setCellValue('F' . $row, $soutenance['intitule']); // Le sujet complet
        
        // Ajouter les informations des lecteurs s'ils sont disponibles
        $lecteurs = !empty($soutenance['lecteurs']) ? explode('|', $soutenance['lecteurs']) : [];
        $sheet->setCellValue('G' . $row, $lecteurs[0] ?? 'Non assigné');
        $sheet->setCellValue('H' . $row, $lecteurs[1] ?? 'Non assigné');
        
        // Définir une hauteur de ligne suffisante pour les sujets longs
        $sheet->getRowDimension($row)->setRowHeight(-1); // Ajustement automatique
        
        $count++;
        $row++;
    }
    
        // Si aucune soutenance pour ce jury
        if (count($juryGroup) === 0) {
            $sheet->setCellValue('A' . $row, 'Aucune soutenance programmée pour ce jury');
            $sheet->mergeCells('A' . $row . ':H' . $row);
            $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'font' => ['italic' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
            ]);
            $row++;
        }
        
        // Appliquer le style aux données
        if ($row > $startDataRow) {
            $sheet->getStyle('A' . $startDataRow . ':H' . ($row - 1))->applyFromArray($styleData);
        }
        
        // Ajouter un espace après chaque jury
        $row += 2;
    }
    
    // Si aucun jury n'est défini
    if (empty($soutenancesParJury)) {
        $sheet->setCellValue('A' . $row, 'Aucune soutenance programmée pour cette section');
        $sheet->mergeCells('A' . $row . ':H' . $row);
        $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'font' => ['bold' => true, 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']]
        ]);
        $row += 2;
    }
    
    // Ajuster la largeur des colonnes
    $sheet->getColumnDimension('A')->setWidth(5);   // N°
    $sheet->getColumnDimension('B')->setWidth(18);  // Date et Heure
    $sheet->getColumnDimension('C')->setWidth(15);  // Lieu
    $sheet->getColumnDimension('D')->setWidth(20);  // Étudiant
    $sheet->getColumnDimension('E')->setWidth(12);  // Matricule
    $sheet->getColumnDimension('F')->setWidth(45);  // Sujet - plus large pour texte long
    $sheet->getColumnDimension('G')->setWidth(20);  // 1er Lecteur
    $sheet->getColumnDimension('H')->setWidth(20);  // 2ème Lecteur
    
    // Créer le nom du fichier
    $sectionName = preg_replace('/[^A-Za-z0-9\-]/', '_', $sectionInfo['designationSection']);
    $fileName = 'Programme_Soutenances_' . $sectionName . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    // Définir les en-têtes HTTP pour le téléchargement
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    
    // Créer le writer et envoyer le fichier au navigateur
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    
