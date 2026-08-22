<?php
// Configuration des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Frais.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

// Vérification d'authentification
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

// Récupération des paramètres
$etudiantId = isset($_GET['etudiantId']) ? intval($_GET['etudiantId']) : 0;
$fraisId = isset($_GET['fraisId']) ? intval($_GET['fraisId']) : 0;
$anneeAcadId = isset($_GET['anneeAcad']) ? intval($_GET['anneeAcad']) : 0;
$selectedType = isset($_GET['type']) ? $_GET['type'] : 'academique';

// Vérification de l'ID étudiant
if ($etudiantId <= 0) {
    header('Location: ../?view=frais/suivi_paiement&error=etudiant_invalide');
    exit;
}

// Initialisation des modèles
$universite = new Universite();
$fraisModel = new Frais();

// Si l'année académique n'est pas spécifiée, utiliser l'année en cours
if ($anneeAcadId <= 0) {
    $currentYear = $universite->getCurrentAcademicYear();
    $anneeAcadId = $currentYear['idannee_acad'];
}

// Récupération des informations de l'étudiant
$etudiant = $universite->getEtudiantById($etudiantId);
if (!$etudiant) {
    header('Location: ../?view=frais/suivi_paiement&error=etudiant_introuvable');
    exit;
}

// Récupération de la configuration de l'université
$configUniversite = $universite->getConfigurationUniversite();

// Création du document Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Relevé des paiements');

// Configuration de la page
$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
$sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);

// Définition des styles
$styleTitle = [
    'font' => ['bold' => true, 'size' => 16],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];

$styleSubtitle = [
    'font' => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];

$styleHeader = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];

$styleData = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
];

$styleSectionHeader = [
    'font' => ['bold' => true, 'size' => 12],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN]],
];

$styleTotal = [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']],
    'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM], 
                 'inside' => ['borderStyle' => Border::BORDER_THIN]],
];

$styleComplete = [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C6EFCE']],
    'font' => ['color' => ['rgb' => '006100']]
];

$stylePartial = [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFEB9C']],
    'font' => ['color' => ['rgb' => '9C5700']]
];

// Configuration des largeurs de colonnes
$sheet->getColumnDimension('A')->setWidth(30);   // Désignation
$sheet->getColumnDimension('B')->setWidth(15);   // Montant total
$sheet->getColumnDimension('C')->setWidth(15);   // Montant payé
$sheet->getColumnDimension('D')->setWidth(15);   // Montant restant
$sheet->getColumnDimension('E')->setWidth(12);   // Statut

// Ajout du logo si disponible
$row = 1;
if (!empty($configUniversite['logo'])) {
    $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
    if (file_exists($logoPath)) {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo');
        $drawing->setPath($logoPath);
        $drawing->setCoordinates('C1');
        $drawing->setOffsetX(-15);
        $drawing->setHeight(100);
        $drawing->setWorksheet($sheet);
        
        $sheet->getRowDimension(1)->setRowHeight(60);
        $row = 4;
    }
}

// En-tête avec les informations de l'université
$sheet->setCellValue('A' . $row, !empty($configUniversite['nom']) ? $configUniversite['nom'] : '');
$sheet->mergeCells('A' . $row . ':E' . $row);
$sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($styleTitle);
$sheet->getRowDimension($row)->setRowHeight(30);
$row++;

$sheet->setCellValue('A' . $row, 'RELEVÉ DES PAIEMENTS');
$sheet->mergeCells('A' . $row . ':E' . $row);
$sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($styleSubtitle);
$sheet->getRowDimension($row)->setRowHeight(25);
$row++;

// Année académique
$anneeAcad = $universite->getAcademicYearById($anneeAcadId);
$sheet->setCellValue('A' . $row, 'Année Académique: ' . $anneeAcad['designation']);
$sheet->mergeCells('A' . $row . ':E' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(true);
$row++;

// Informations de l'étudiant
$row++;
$sheet->setCellValue('A' . $row, 'Matricule:');
$sheet->setCellValue('B' . $row, $etudiant['matricule']);
$sheet->mergeCells('B' . $row . ':E' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(true);
$row++;

$sheet->setCellValue('A' . $row, 'Nom:');
$sheet->setCellValue('B' . $row, $etudiant['noms']);
$sheet->mergeCells('B' . $row . ':E' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(true);
$row++;

$sheet->setCellValue('A' . $row, 'Promotion:');
$sheet->setCellValue('B' . $row, $etudiant['designationPromotion']);
$sheet->mergeCells('B' . $row . ':E' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(true);
$row++;

$row++;

// Récupération des frais de la promotion de l'étudiant
$promotionId = $etudiant['promotion_idpromotion'];

// SECTION 1: RÉCAPITULATIF DES FRAIS
$sheet->setCellValue('A' . $row, '1. RÉCAPITULATIF DES FRAIS À PAYER');
$sheet->mergeCells('A' . $row . ':E' . $row);
$sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($styleSectionHeader);
$row++;

// En-têtes des colonnes pour le récapitulatif
$sheet->setCellValue('A' . $row, 'Désignation du frais');
$sheet->setCellValue('B' . $row, 'Montant total');
$sheet->setCellValue('C' . $row, 'Montant payé');
$sheet->setCellValue('D' . $row, 'Montant restant');
$sheet->setCellValue('E' . $row, 'Statut');
$sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($styleHeader);
$sheet->getRowDimension($row)->setRowHeight(20);
$row++;

// Préparation des données pour les totaux par devise
$totauxParDevise = [];
$allPaymentDetails = [];

// Récupérer tous les frais de la promotion
if ($selectedType == 'academique') {
    $fraisList = $fraisModel->getFraisByPromotion($promotionId, $anneeAcadId);
    $paiements = $fraisModel->getPaiementsByEtudiant($etudiantId, $anneeAcadId);
} else {
    $fraisList = $fraisModel->getFraisSoutenanceByPromotion($promotionId, $anneeAcadId);
    $paiements = $fraisModel->getPaiementsSoutenanceByEtudiant($etudiantId, $anneeAcadId);
}

// Début de la ligne pour les données des frais
$startDataRow = $row;

// Traitement de tous les frais
foreach ($fraisList as $frais) {
    $fraisId = $selectedType == 'academique' ? $frais['idfrais'] : $frais['idfrais_soutenance'];
    $montantTotal = $frais['montant'];
    $devise = $frais['devise'];
    
    // Calculer le montant payé pour ce frais
    $montantPaye = 0;
    $paiementsFrais = [];
    foreach ($paiements as $paiement) {
        $paiementFraisId = $selectedType == 'academique' ? $paiement['frais_idfrais'] : $paiement['idfrais_soutenance'];
        if ($paiementFraisId == $fraisId) {
            $montantPaye += $selectedType == 'academique' ? $paiement['montantPaye'] : $paiement['montant_paye'];
            $paiementsFrais[] = $paiement;
        }
    }
    
    $montantRestant = max(0, $montantTotal - $montantPaye);
    $statut = $montantRestant <= 0 ? 'Complet' : 'Partiel';
    
    // Enregistrer les détails des paiements pour ce frais
    $allPaymentDetails[$fraisId] = [
        'frais' => $frais,
        'paiements' => $paiementsFrais
    ];
    
    // Ajouter aux totaux par devise
    if (!isset($totauxParDevise[$devise])) {
        $totauxParDevise[$devise] = [
            'total' => 0,
            'paye' => 0,
            'restant' => 0
        ];
    }
    $totauxParDevise[$devise]['total'] += $montantTotal;
    $totauxParDevise[$devise]['paye'] += $montantPaye;
    $totauxParDevise[$devise]['restant'] += $montantRestant;
    
    // Ajouter à la feuille Excel
    $sheet->setCellValue('A' . $row, $frais['designation']);
    $sheet->setCellValue('B' . $row, $montantTotal . ' ' . $devise);
    $sheet->setCellValue('C' . $row, $montantPaye . ' ' . $devise);
    $sheet->setCellValue('D' . $row, $montantRestant . ' ' . $devise);
    $sheet->setCellValue('E' . $row, $statut);
    
    // Appliquer le style selon le statut
    if ($statut == 'Complet') {
        $sheet->getStyle('E' . $row)->applyFromArray($styleComplete);
    } else {
        $sheet->getStyle('E' . $row)->applyFromArray($stylePartial);
    }
    
    $row++;
}

// Style des données du récapitulatif
if ($row > $startDataRow) {
    $sheet->getStyle('A' . $startDataRow . ':E' . ($row - 1))->applyFromArray($styleData);
}

// Totaux par devise pour le récapitulatif
foreach ($totauxParDevise as $devise => $montants) {
    $sheet->setCellValue('A' . $row, 'Total ' . $devise);
    $sheet->setCellValue('B' . $row, $montants['total'] . ' ' . $devise);
    $sheet->setCellValue('C' . $row, $montants['paye'] . ' ' . $devise);
    $sheet->setCellValue('D' . $row, $montants['restant'] . ' ' . $devise);
    $sheet->setCellValue('E' . $row, round(($montants['paye'] / $montants['total']) * 100, 2) . '%');
    $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($styleTotal);
    $row++;
}

// SECTION 2: DÉTAIL DES PAIEMENTS EFFECTUÉS
$row += 2;
$sheet->setCellValue('A' . $row, '2. DÉTAIL DES PAIEMENTS EFFECTUÉS');
$sheet->mergeCells('A' . $row . ':E' . $row);
$sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($styleSectionHeader);
$row++;

// Parcourir tous les frais pour lesquels il y a eu des paiements
foreach ($allPaymentDetails as $fraisId => $details) {
    $frais = $details['frais'];
    $paiementsFrais = $details['paiements'];
    
    // N'afficher les détails que si des paiements ont été effectués
    if (count($paiementsFrais) > 0) {
        $sheet->setCellValue('A' . $row, $frais['designation']);
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        
        // En-têtes pour les détails de paiement
        $sheet->setCellValue('A' . $row, 'Date');
        $sheet->setCellValue('B' . $row, 'Référence');
        $sheet->setCellValue('C' . $row, 'Mode');
        $sheet->setCellValue('D' . $row, 'Montant');
        $sheet->setCellValue('E' . $row, 'Utilisateur');
        $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($styleHeader);
        $row++;
        
        $startDetailRow = $row;
        
        // Détails des paiements pour ce frais
        foreach ($paiementsFrais as $paiement) {
            $sheet->setCellValue('A' . $row, $selectedType == 'academique' ? date('d/m/Y', strtotime($paiement['datePaiement'])) : date('d/m/Y', strtotime($paiement['date_paiement'])));
            $sheet->setCellValue('B' . $row, $selectedType == 'academique' ? $paiement['referencePaiement'] : $paiement['reference_paiement']);
            $sheet->setCellValue('C' . $row, $selectedType == 'academique' ? $paiement['modePaiement'] : $paiement['mode_paiement']);
            $sheet->setCellValue('D' . $row, ($selectedType == 'academique' ? $paiement['montantPaye'] : $paiement['montant_paye']) . ' ' . $frais['devise']);
            $sheet->setCellValue('E' . $row, $paiement['nom_utilisateur'] ?? '');
            $row++;
        }
        
        // Style des détails
        $sheet->getStyle('A' . $startDetailRow . ':E' . ($row - 1))->applyFromArray($styleData);
        
        // Total pour ce frais
        $totalPaye = 0;
        foreach ($paiementsFrais as $paiement) {
            $totalPaye += $selectedType == 'academique' ? $paiement['montantPaye'] : $paiement['montant_paye'];
        }
        
        $sheet->setCellValue('A' . $row, 'Total payé:');
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->setCellValue('D' . $row, $totalPaye . ' ' . $frais['devise']);
        $sheet->mergeCells('D' . $row . ':E' . $row);
        $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($styleTotal);
        $row++;
        
        $row++; // Espace après chaque frais
    }
}

// SECTION 3: RÉCAPITULATIF GLOBAL
$row += 1;
$sheet->setCellValue('A' . $row, '3. RÉCAPITULATIF GLOBAL PAR DEVISE');
$sheet->mergeCells('A' . $row . ':E' . $row);
$sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($styleSectionHeader);
$row++;

foreach ($totauxParDevise as $devise => $montants) {
    $sheet->setCellValue('A' . $row, 'Devise: ' . $devise);
    $sheet->mergeCells('A' . $row . ':E' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Total à payer:');
    $sheet->setCellValue('B' . $row, $montants['total'] . ' ' . $devise);
    $sheet->mergeCells('B' . $row . ':E' . $row);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Total payé:');
    $sheet->setCellValue('B' . $row, $montants['paye'] . ' ' . $devise);
    $sheet->mergeCells('B' . $row . ':E' . $row);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Total restant:');
    $sheet->setCellValue('B' . $row, $montants['restant'] . ' ' . $devise);
    $sheet->mergeCells('B' . $row . ':E' . $row);
    $row++;
    
    // Pourcentage de paiement pour cette devise
    $pourcentage = $montants['total'] > 0 ? round(($montants['paye'] / $montants['total']) * 100, 2) : 0;
    $sheet->setCellValue('A' . $row, 'Taux de paiement:');
    $sheet->setCellValue('B' . $row, $pourcentage . '%');
    $sheet->mergeCells('B' . $row . ':E' . $row);
    $row++;
    
    $row++; // Espace entre les devises
}

// Appliquer un style au récapitulatif
$recapStartRow = $row - count($totauxParDevise) * 6;
if ($recapStartRow < $row) {
    $sheet->getStyle('A' . $recapStartRow . ':E' . ($row - 2))->applyFromArray($styleTotal);
}

// Ajouter la date et l'utilisateur qui a généré le rapport
$row += 1;
$sheet->setCellValue('A' . $row, 'Rapport généré le ' . date('d/m/Y à H:i:s') . ' par ' . ($_SESSION['nomUser'] ?? 'Système'));
$sheet->mergeCells('A' . $row . ':E' . $row);
$sheet->getStyle('A' . $row)->getFont()->setItalic(true);

// Configurer les options d'impression
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);
$sheet->getPageMargins()->setTop(0.5);
$sheet->getPageMargins()->setRight(0.5);
$sheet->getPageMargins()->setLeft(0.5);
$sheet->getPageMargins()->setBottom(0.5);

// Ajouter l'en-tête et le pied de page
$sheet->getHeaderFooter()->setOddHeader('&C&B' . (!empty($configUniversite['nom']) ? $configUniversite['nom'] : 'Établissement') . ' - Relevé des Paiements');
$sheet->getHeaderFooter()->setOddFooter('&L&B' . date('d/m/Y') . '&C&P / &N&R&BExporté le ' . date('d/m/Y H:i:s'));

// Créer le nom du fichier
$nomEtudiant = preg_replace('/[^a-zA-Z0-9]/', '_', $etudiant['noms']);
$fileName = 'Releve_Paiements_' . $nomEtudiant . '_' . date('Y-m-d_H-i-s') . '.xlsx';

// Nettoyer le buffer de sortie avant d'envoyer le fichier
ob_end_clean();

// Définir les en-têtes HTTP pour le téléchargement
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');
header('Expires: 0');
header('Pragma: public');

// Créer le writer et envoyer le fichier au navigateur
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

