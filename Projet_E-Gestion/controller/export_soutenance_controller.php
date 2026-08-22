<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Frais.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // Pour PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Initialiser les modèles
$fraisModel = new Frais();
$universite = new Universite();

// Récupérer les paramètres
$type = isset($_GET['type']) ? $_GET['type'] : 'eligible';
$sectionId = isset($_GET['section']) ? intval($_GET['section']) : 0;
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$anneeAcadId = isset($_GET['annee']) ? intval($_GET['annee']) : $universite->getCurrentAcademicYear()['idannee_acad'];

// Vérifier les autorisations (si l'utilisateur a accès à cette section)
if ($_SESSION['idRole'] != 1) { // Si pas admin
    $userSections = $universite->getUserSections($_SESSION['id']);
    if (!in_array($sectionId, $userSections)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Vous n\'avez pas accès à cette section']);
        exit();
    }
}

// Récupérer les données selon le type d'exportation
$data = [];
$filename = '';

if ($type === 'eligible') {
    // Étudiants éligibles à la soutenance (en ordre de paiement)
    $data = $fraisModel->getEtudiantsEligiblesSoutenance($sectionId, $promotionId, $anneeAcadId);
    $filename = 'Etudiants_eligibles_soutenance';
} else {
    // Étudiants avec litiges de frais de soutenance
    $data = $fraisModel->getEtudiantsLitigesSoutenance($sectionId, $promotionId, $anneeAcadId);
    $filename = 'Etudiants_litiges_soutenance';
}

// Ajouter la section et/ou promotion au nom du fichier
if ($sectionId > 0) {
    $section = $universite->getSectionById($sectionId);
    $filename .= '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $section['designationSection']);
}
if ($promotionId > 0) {
    $promotion = $universite->getPromotionById($promotionId);
    $filename .= '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $promotion['designationPromotion']);
}
$filename .= '_' . date('Y-m-d') . '.xlsx';

// Créer le fichier Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Définir les en-têtes du tableau
if ($type === 'eligible') {
    $headers = ['N°', 'Matricule', 'Nom', 'Promotion', 'Section', 'Frais payés', 'Montant total payé', 'Date dernier paiement'];
} else {
    $headers = ['N°', 'Matricule', 'Nom', 'Promotion', 'Section', 'Frais manquants', 'Montant restant à payer', 'Statut'];
}

// Appliquer les en-têtes
foreach ($headers as $colIndex => $header) {
    $sheet->setCellValue(chr(65 + $colIndex) . '1', $header);
}
// Styliser les en-têtes
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '4472C4']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A1:' . chr(64 + count($headers)) . '1')->applyFromArray($headerStyle);

// Remplir les données
$rowIndex = 2;
foreach ($data as $index => $row) {
    if ($type === 'eligible') {
        $sheet->setCellValue('A' . $rowIndex, $index + 1);
        $sheet->setCellValue('B' . $rowIndex, $row['matricule']);
        $sheet->setCellValue('C' . $rowIndex, $row['noms']);
        $sheet->setCellValue('D' . $rowIndex, $row['designationPromotion']);
        $sheet->setCellValue('E' . $rowIndex, $row['designationSection']);
        $sheet->setCellValue('F' . $rowIndex, $row['designation_frais']);
        $sheet->setCellValue('G' . $rowIndex, $row['montant_total_paye'] . ' ' . $row['devise']);
        $sheet->setCellValue('H' . $rowIndex, date('d/m/Y', strtotime($row['date_dernier_paiement'])));
    } else {
        $sheet->setCellValue('A' . $rowIndex, $index + 1);
        $sheet->setCellValue('B' . $rowIndex, $row['matricule']);
        $sheet->setCellValue('C' . $rowIndex, $row['noms']);
        $sheet->setCellValue('D' . $rowIndex, $row['designationPromotion']);
        $sheet->setCellValue('E' . $rowIndex, $row['designationSection']);
        $sheet->setCellValue('F' . $rowIndex, $row['designation_frais']);
        $sheet->setCellValue('G' . $rowIndex, $row['montant_restant'] . ' ' . $row['devise']);
        $sheet->setCellValue('H' . $rowIndex, $row['statut_paiement']);
    }
    $rowIndex++;
}
// Ajuster la largeur des colonnes automatiquement
foreach (range('A', chr(64 + count($headers))) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Appliquer un style aux données
$dataStyle = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
];
$sheet->getStyle('A2:' . chr(64 + count($headers)) . ($rowIndex - 1))->applyFromArray($dataStyle);

// Créer le writer et envoyer le fichier
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
