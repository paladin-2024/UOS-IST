<?php
/**
 * Export Maquette UE - Exporte l'offre de formation d'une promotion en Excel
 * Format similaire à une maquette pédagogique officielle
 */
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

// Vérifier les paramètres
if (!isset($_GET['promotion']) || !isset($_GET['annee_acad'])) {
    die('Paramètres manquants');
}

$promotionId = intval($_GET['promotion']);
$anneeAcadId = intval($_GET['annee_acad']);

try {
    $universite = new Universite();
    $pdo = Connexion::getInstance()->getPDO();
    
    // Récupérer le crédit horaire depuis la configuration
    $configQuery = $pdo->query("SELECT credit_heure FROM configuration_universite LIMIT 1");
    $config = $configQuery->fetch(PDO::FETCH_ASSOC);
    $creditHeure = $config && isset($config['credit_heure']) ? intval($config['credit_heure']) : 25;
    
    // Récupérer les informations de la promotion
    $queryPromo = "SELECT p.*, o.\"designationOrientation\", s.\"designationSection\",
                          aa.designation as annee_designation
                   FROM promotion p
                   JOIN orientation o ON p.orientation_idorientation = o.idorientation
                   JOIN section s ON o.section_idsection = s.idsection
                   JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
                   WHERE p.idpromotion = :promotionId";
    $stmtPromo = $pdo->prepare($queryPromo);
    $stmtPromo->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
    $stmtPromo->execute();
    $promotion = $stmtPromo->fetch(PDO::FETCH_ASSOC);
    
    if (!$promotion) {
        die('Promotion non trouvée');
    }
    
    // Récupérer les semestres de la promotion
    $semestres = $universite->getSemestresByPromotion($promotionId);
    
    if (empty($semestres)) {
        die('Aucun semestre trouvé pour cette promotion');
    }
    
    // Créer le document Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Maquette');
    
    // Styles
    $titleStyle = [
        'font' => ['bold' => true, 'size' => 14],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ];
    
    $headerStyle = [
        'font' => ['bold' => true, 'size' => 10],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'D9E2F3']
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ]
    ];
    
    $semestreHeaderStyle = [
        'font' => ['bold' => true, 'size' => 11],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E2EFDA']
        ]
    ];
    
    $ueStyle = [
        'font' => ['bold' => true],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ]
    ];
    
    $ecueStyle = [
        'font' => ['italic' => true],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ]
    ];
    
    $totalStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FFF2CC']
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ]
    ];
    
    // En-tête du document
    $row = 1;
    $sheet->setCellValue('A' . $row, 'OFFRE DE FORMATION');
    $sheet->mergeCells('A' . $row . ':H' . $row);
    $sheet->getStyle('A' . $row)->applyFromArray($titleStyle);
    $row++;
    
    // Informations sur la promotion
    $sheet->setCellValue('A' . $row, $promotion['designationPromotion'] . ' - ' . ($promotion['cycle'] ?? ''));
    $sheet->mergeCells('A' . $row . ':H' . $row);
    $sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true, 'size' => 12], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
    $row++;
    
    $sheet->setCellValue('A' . $row, $promotion['designationSection']);
    $sheet->mergeCells('A' . $row . ':H' . $row);
    $sheet->getStyle('A' . $row)->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Année Académique: ' . $promotion['annee_designation']);
    $sheet->mergeCells('A' . $row . ':H' . $row);
    $sheet->getStyle('A' . $row)->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
    $row += 2;
    
    // Pour chaque semestre
    $totalCreditsAnnuel = 0;
    foreach ($semestres as $semestre) {
        // En-tête du semestre
        $sheet->setCellValue('A' . $row, strtolower(chr(96 + $semestre['numeroSemestre'])) . ') SEMESTRE ' . $semestre['numeroSemestre']);
        $sheet->mergeCells('A' . $row . ':H' . $row);
        $sheet->getStyle('A' . $row)->applyFromArray($semestreHeaderStyle);
        $row++;
        
        // En-têtes des colonnes
        $sheet->setCellValue('A' . $row, 'Code UE');
        $sheet->setCellValue('B' . $row, 'Intitulés des UE');
        $sheet->setCellValue('C' . $row, 'CMI');
        $sheet->setCellValue('D' . $row, 'TD');
        $sheet->setCellValue('E' . $row, 'TP');
        $sheet->setCellValue('F' . $row, 'Cr');
        $sheet->mergeCells('F' . $row . ':G' . $row);
        $row++;
        
        // Sous-en-têtes pour les crédits
        $sheet->setCellValue('F' . $row, 'EC');
        $sheet->setCellValue('G' . $row, 'UE');
        
        // Appliquer les styles aux en-têtes
        $sheet->getStyle('A' . ($row - 1) . ':G' . $row)->applyFromArray($headerStyle);
        $row++;
        
        // Récupérer les UE du semestre
        $queryUEs = "SELECT u.\"idUE\", u.\"codeUE\", u.\"designationUE\" 
                     FROM ue u 
                     WHERE u.semestre_idsemestre = :semestreId 
                     ORDER BY u.\"codeUE\"";
        $stmtUEs = $pdo->prepare($queryUEs);
        $stmtUEs->bindParam(':semestreId', $semestre['idsemestre'], PDO::PARAM_INT);
        $stmtUEs->execute();
        $ues = $stmtUEs->fetchAll(PDO::FETCH_ASSOC);
        
        $totalCreditsSemestre = 0;
        
        foreach ($ues as $ue) {
            // Récupérer les ECUEs de cette UE
            $queryECUEs = "SELECT e.\"idECUE\", e.\"designationECUE\", e.\"CMI\", e.\"TD\", e.\"TP\"
                           FROM ecue e 
                           WHERE e.\"UE_idUE\" = :ueId 
                           ORDER BY e.\"designationECUE\"";
            $stmtECUEs = $pdo->prepare($queryECUEs);
            $stmtECUEs->bindParam(':ueId', $ue['idUE'], PDO::PARAM_INT);
            $stmtECUEs->execute();
            $ecues = $stmtECUEs->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculer les totaux pour l'UE
            $totalCMI = 0;
            $totalTD = 0;
            $totalTP = 0;
            $totalCreditsUE = 0;
            
            foreach ($ecues as $ecue) {
                $totalCMI += intval($ecue['CMI'] ?? 0);
                $totalTD += intval($ecue['TD'] ?? 0);
                $totalTP += intval($ecue['TP'] ?? 0);
                // Calculer les crédits à partir des heures
                $ecueHours = intval($ecue['CMI'] ?? 0) + intval($ecue['TD'] ?? 0) + intval($ecue['TP'] ?? 0);
                $totalCreditsUE += ($ecueHours > 0) ? round($ecueHours / $creditHeure) : 0;
            }
            
            // Si toujours pas de crédits mais des heures totales, recalculer
            if ($totalCreditsUE == 0 && ($totalCMI + $totalTD + $totalTP) > 0) {
                $totalCreditsUE = ceil(($totalCMI + $totalTD + $totalTP) / $creditHeure);
            }
            
            // Ligne de l'UE principale
            $sheet->setCellValue('A' . $row, $ue['codeUE']);
            $sheet->setCellValue('B' . $row, $ue['designationUE']);
            $sheet->setCellValue('C' . $row, $totalCMI > 0 ? $totalCMI . 'h' : '');
            $sheet->setCellValue('D' . $row, $totalTD > 0 ? $totalTD . 'h' : '');
            $sheet->setCellValue('E' . $row, $totalTP > 0 ? $totalTP . 'h' : '');
            $sheet->setCellValue('G' . $row, $totalCreditsUE);
            $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($ueStyle);
            $row++;
            
            // Lignes des ECUEs (en italique, sans code UE)
            foreach ($ecues as $ecue) {
                // Calculer le crédit de l'ECUE
                $ecueHours = intval($ecue['CMI'] ?? 0) + intval($ecue['TD'] ?? 0) + intval($ecue['TP'] ?? 0);
                $ecueCredit = ($ecueHours > 0) ? round($ecueHours / $creditHeure) : 0;
                
                $sheet->setCellValue('A' . $row, ''); // Pas de code pour les ECUEs
                $sheet->setCellValue('B' . $row, $ecue['designationECUE']);
                $sheet->setCellValue('C' . $row, intval($ecue['CMI'] ?? 0) > 0 ? $ecue['CMI'] . 'h' : '');
                $sheet->setCellValue('D' . $row, intval($ecue['TD'] ?? 0) > 0 ? $ecue['TD'] . 'h' : '');
                $sheet->setCellValue('E' . $row, intval($ecue['TP'] ?? 0) > 0 ? $ecue['TP'] . 'h' : '');
                $sheet->setCellValue('F' . $row, $ecueCredit > 0 ? $ecueCredit : '');
                $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($ecueStyle);
                $row++;
            }
            
            $totalCreditsSemestre += $totalCreditsUE;
        }
        
        // Ligne de total du semestre
        $sheet->setCellValue('A' . $row, 'Total S' . $semestre['numeroSemestre']);
        $sheet->mergeCells('A' . $row . ':F' . $row);
        $sheet->setCellValue('G' . $row, $totalCreditsSemestre);
        $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($totalStyle);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $row += 2;
        
        $totalCreditsAnnuel += $totalCreditsSemestre;
    }
    
    // Total annuel
    $sheet->setCellValue('A' . $row, 'TOTAL ANNUEL');
    $sheet->mergeCells('A' . $row . ':F' . $row);
    $sheet->setCellValue('G' . $row, $totalCreditsAnnuel);
    $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 12],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'C6EFCE']
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]
        ]
    ]);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    
    // Ajuster la largeur des colonnes
    $sheet->getColumnDimension('A')->setWidth(12);
    $sheet->getColumnDimension('B')->setWidth(45);
    $sheet->getColumnDimension('C')->setWidth(8);
    $sheet->getColumnDimension('D')->setWidth(8);
    $sheet->getColumnDimension('E')->setWidth(8);
    $sheet->getColumnDimension('F')->setWidth(6);
    $sheet->getColumnDimension('G')->setWidth(6);
    
    // Générer le fichier
    $filename = 'Maquette_' . preg_replace('/[^a-zA-Z0-9]/', '_', $promotion['designationPromotion']) . '_' . date('Y-m-d') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    
} catch (Exception $e) {
    error_log("Erreur export maquette: " . $e->getMessage());
    die('Erreur lors de la génération du fichier: ' . $e->getMessage());
}
