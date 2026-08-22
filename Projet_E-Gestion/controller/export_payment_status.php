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
$frais_rapport = isset($_GET['frais']) ? intval($_GET['frais']) : 0;
$promotion_rapport = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$selectedType = isset($_GET['type']) ? $_GET['type'] : 'academique';

// Initialisation des modèles
$universite = new Universite();
$fraisModel = new Frais();

// Récupération de l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();
$anneeAcadId = $currentYear['idannee_acad'];

// Récupération de la configuration de l'université
$configUniversite = $universite->getConfigurationUniversite();

// Création du document Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('État des paiements');

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
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
];

$styleGroupHeader = [
    'font' => ['bold' => true, 'size' => 12],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN]],
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

$styleComplete = [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C6EFCE']],
    'font' => ['color' => ['rgb' => '006100']]
];

$stylePartial = [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFEB9C']],
    'font' => ['color' => ['rgb' => '9C5700']]
];

$styleTotal = [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']],
    'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM]],
];

// Configuration des largeurs de colonnes
$sheet->getColumnDimension('A')->setWidth(6);    // N°
$sheet->getColumnDimension('B')->setWidth(15);   // Matricule
$sheet->getColumnDimension('C')->setWidth(35);   // Nom de l'étudiant
$sheet->getColumnDimension('D')->setWidth(15);   // Montant total
$sheet->getColumnDimension('E')->setWidth(15);   // Montant payé
$sheet->getColumnDimension('F')->setWidth(15);   // Montant restant
$sheet->getColumnDimension('G')->setWidth(12);   // Statut

// Ajout du logo si disponible
$row = 1;
if (!empty($configUniversite['logo'])) {
    $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
    if (file_exists($logoPath)) {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo');
        $drawing->setPath($logoPath);
        $drawing->setCoordinates('D1');
        $drawing->setOffsetX(0);
        $drawing->setHeight(100);
        $drawing->setWorksheet($sheet);
        
        $sheet->getRowDimension(1)->setRowHeight(60);
        $row = 4;
    }
}

// En-tête avec les informations de l'université
$sheet->setCellValue('A' . $row, !empty($configUniversite['nom']) ? $configUniversite['nom'] : '');
$sheet->mergeCells('A' . $row . ':G' . $row);
$sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($styleTitle);
$sheet->getRowDimension($row)->setRowHeight(30);
$row++;

$sheet->setCellValue('A' . $row, 'ÉTAT DES PAIEMENTS');
$sheet->mergeCells('A' . $row . ':G' . $row);
$sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($styleTitle);
$sheet->getRowDimension($row)->setRowHeight(25);
$row++;

$sheet->setCellValue('A' . $row, 'Année Académique: ' . $currentYear['designation']);
$sheet->mergeCells('A' . $row . ':G' . $row);
$sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($styleSubtitle);
$sheet->getRowDimension($row)->setRowHeight(25);
$row++;

// Informations sur les filtres
if ($promotion_rapport > 0) {
    $promotion = $universite->getPromotionById($promotion_rapport);
    $sheet->setCellValue('A' . $row, 'Promotion: ' . $promotion['designationPromotion']);
    $sheet->mergeCells('A' . $row . ':G' . $row);
    $row++;
}

if ($frais_rapport > 0) {
    $frais = $selectedType == 'academique' 
        ? $fraisModel->getFraisById($frais_rapport)
        : $fraisModel->getFraisSoutenanceById($frais_rapport);
    $sheet->setCellValue('A' . $row, 'Frais: ' . $frais['designation']);
    $sheet->mergeCells('A' . $row . ':G' . $row);
    $row++;
}

$row++; // Ligne vide pour séparation

// Récupération des données de paiement
$data = [];
$globalTotals = [
    'nombre_etudiants' => 0,
    'paiements_complets' => 0,
    'paiements_partiels' => 0,
    'par_devise' => []
];

// Récupération des étudiants et leurs paiements
// Selon les filtres, obtenir les promotions concernées
$promotions = [];
if ($promotion_rapport > 0) {
    $promotions[] = $universite->getPromotionById($promotion_rapport);
} else {
    // Toutes les promotions de l'année en cours
    $promotions = $universite->getPromotionsByAnneeAcad($anneeAcadId);
}

foreach ($promotions as $promotion) {
    $promotionId = $promotion['idpromotion'];
    $data[$promotionId] = [
        'promotion' => $promotion,
        'frais' => [],
        'totals' => [
            'nombre_etudiants' => 0,
            'paiements_complets' => 0,
            'paiements_partiels' => 0,
            'par_devise' => []
        ]
    ];
    
    // Récupération des frais concernés
    $fraisList = [];
    if ($frais_rapport > 0) {
        if ($selectedType == 'academique') {
            $fraisList[] = $fraisModel->getFraisById($frais_rapport);
        } else {
            $fraisList[] = $fraisModel->getFraisSoutenanceById($frais_rapport);
        }
    } else {
        // Tous les frais pour cette promotion
        if ($selectedType == 'academique') {
            $fraisList = $fraisModel->getFraisByPromotion($promotionId, $anneeAcadId);
        } else {
            $fraisList = $fraisModel->getFraisSoutenanceByPromotion($promotionId, $anneeAcadId);
        }
    }
    
    foreach ($fraisList as $frais) {
        $fraisId = $frais['id' . ($selectedType == 'academique' ? 'frais' : 'frais_soutenance')];
        $data[$promotionId]['frais'][$fraisId] = [
            'details' => $frais,
            'etudiants' => [],
            'totals' => [
                'nombre_etudiants' => 0,
                'paiements_complets' => 0,
                'paiements_partiels' => 0,
                'par_devise' => []
            ]
        ];
        
        // Récupération des étudiants pour cette promotion
        $etudiants = $universite->getEtudiantsByPromotion($promotionId, $anneeAcadId);
        
        foreach ($etudiants as $etudiant) {
            // Récupération du statut de paiement pour cet étudiant et ce frais
            $paiementInfo = $fraisModel->getEtatPaiementEtudiant(
                $etudiant['idetudiant'], 
                $fraisId, 
                $selectedType == 'academique' ? 'frais' : 'soutenance'
            );
            
            if ($paiementInfo) {
                $montantTotal = $paiementInfo['montant_total'];
                $montantPaye = $paiementInfo['montant_paye'];
                $montantRestant = $montantTotal - $montantPaye;
                $devise = $frais['devise'];
                $statut = $montantRestant <= 0 ? 'Complet' : 'Partiel';
                
                // Ajouter l'étudiant aux données
                $data[$promotionId]['frais'][$fraisId]['etudiants'][] = [
                    'etudiant' => $etudiant,
                    'montant_total' => $montantTotal,
                    'montant_paye' => $montantPaye,
                    'montant_restant' => $montantRestant,
                    'devise' => $devise,
                    'statut' => $statut
                ];
                
                // Ajouter aux totaux du frais
                $data[$promotionId]['frais'][$fraisId]['totals']['nombre_etudiants']++;
                if ($statut == 'Complet') {
                    $data[$promotionId]['frais'][$fraisId]['totals']['paiements_complets']++;
                } else {
                    $data[$promotionId]['frais'][$fraisId]['totals']['paiements_partiels']++;
                }
                
                // Ajouter aux totaux du frais par devise
                if (!isset($data[$promotionId]['frais'][$fraisId]['totals']['par_devise'][$devise])) {
                    $data[$promotionId]['frais'][$fraisId]['totals']['par_devise'][$devise] = [
                        'total_montant' => 0,
                        'total_paye' => 0,
                        'total_restant' => 0
                    ];
                }
                
                $data[$promotionId]['frais'][$fraisId]['totals']['par_devise'][$devise]['total_montant'] += $montantTotal;
                $data[$promotionId]['frais'][$fraisId]['totals']['par_devise'][$devise]['total_paye'] += $montantPaye;
                $data[$promotionId]['frais'][$fraisId]['totals']['par_devise'][$devise]['total_restant'] += $montantRestant;
                
                // Ajouter aux totaux de promotion
                $data[$promotionId]['totals']['nombre_etudiants']++;
                if ($statut == 'Complet') {
                    $data[$promotionId]['totals']['paiements_complets']++;
                } else {
                    $data[$promotionId]['totals']['paiements_partiels']++;
                }
                
                // Ajouter aux totaux de promotion par devise
                if (!isset($data[$promotionId]['totals']['par_devise'][$devise])) {
                    $data[$promotionId]['totals']['par_devise'][$devise] = [
                        'total_montant' => 0,
                        'total_paye' => 0,
                        'total_restant' => 0
                    ];
                }
                
                $data[$promotionId]['totals']['par_devise'][$devise]['total_montant'] += $montantTotal;
                $data[$promotionId]['totals']['par_devise'][$devise]['total_paye'] += $montantPaye;
                $data[$promotionId]['totals']['par_devise'][$devise]['total_restant'] += $montantRestant;
                
                // Ajouter aux totaux globaux
                $globalTotals['nombre_etudiants']++;
                if ($statut == 'Complet') {
                    $globalTotals['paiements_complets']++;
                } else {
                    $globalTotals['paiements_partiels']++;
                }
                
                // Ajouter aux totaux globaux par devise
                if (!isset($globalTotals['par_devise'][$devise])) {
                    $globalTotals['par_devise'][$devise] = [
                        'total_montant' => 0,
                        'total_paye' => 0,
                        'total_restant' => 0
                    ];
                }
                
                $globalTotals['par_devise'][$devise]['total_montant'] += $montantTotal;
                $globalTotals['par_devise'][$devise]['total_paye'] += $montantPaye;
                $globalTotals['par_devise'][$devise]['total_restant'] += $montantRestant;
            }
        }
    }
}

// Génération du rapport Excel
foreach ($data as $promotionId => $promotionData) {
    // En-tête de la promotion
    $sheet->setCellValue('A' . $row, 'Promotion: ' . $promotionData['promotion']['designationPromotion']);
    $sheet->mergeCells('A' . $row . ':G' . $row);
    $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($styleGroupHeader);
    $sheet->getRowDimension($row)->setRowHeight(20);
    $row++;
    
    foreach ($promotionData['frais'] as $fraisId => $fraisData) {
        // En-tête du frais
        $sheet->setCellValue('A' . $row, 'Frais: ' . $fraisData['details']['designation']);
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']]
        ]);
        $row++;
        
        // En-têtes des colonnes
        $sheet->setCellValue('A' . $row, 'N°');
        $sheet->setCellValue('B' . $row, 'Matricule');
        $sheet->setCellValue('C' . $row, 'Nom de l\'étudiant');
        $sheet->setCellValue('D' . $row, 'Montant total');
        $sheet->setCellValue('E' . $row, 'Montant payé');
        $sheet->setCellValue('F' . $row, 'Montant restant');
        $sheet->setCellValue('G' . $row, 'Statut');
        $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($styleHeader);
        $row++;
        
        // Données des étudiants
        $startDataRow = $row;
        $index = 1;
        foreach ($fraisData['etudiants'] as $etudiantData) {
            $sheet->setCellValue('A' . $row, $index++);
            $sheet->setCellValue('B' . $row, $etudiantData['etudiant']['matricule']);
            $sheet->setCellValue('C' . $row, $etudiantData['etudiant']['noms']);
            $sheet->setCellValue('D' . $row, $etudiantData['montant_total'] . ' ' . $etudiantData['devise']);
            $sheet->setCellValue('E' . $row, $etudiantData['montant_paye'] . ' ' . $etudiantData['devise']);
            $sheet->setCellValue('F' . $row, $etudiantData['montant_restant'] . ' ' . $etudiantData['devise']);
            $sheet->setCellValue('G' . $row, $etudiantData['statut']);
            
            // Style selon le statut
            if ($etudiantData['statut'] == 'Complet') {
                $sheet->getStyle('G' . $row)->applyFromArray($styleComplete);
            } else {
                $sheet->getStyle('G' . $row)->applyFromArray($stylePartial);
            }
            
            $row++;
        }
        
        // Style des données
        if ($row > $startDataRow) {
            $sheet->getStyle('A' . $startDataRow . ':G' . ($row - 1))->applyFromArray($styleData);
        }
        
        // Total pour ce frais - par devise
        foreach ($fraisData['totals']['par_devise'] as $devise => $montants) {
            $sheet->setCellValue('A' . $row, 'Total pour ' . $fraisData['details']['designation'] . ' (' . $devise . ')');
            $sheet->mergeCells('A' . $row . ':C' . $row);
            $sheet->setCellValue('D' . $row, $montants['total_montant'] . ' ' . $devise);
            $sheet->setCellValue('E' . $row, $montants['total_paye'] . ' ' . $devise);
            $sheet->setCellValue('F' . $row, $montants['total_restant'] . ' ' . $devise);
            $sheet->setCellValue('G' . $row, $fraisData['totals']['paiements_complets'] . ' / ' . count($fraisData['etudiants']));
            $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($styleTotal);
            $row++;
        }
        
        $row++; // Espace après chaque frais
    }

    // Total pour cette promotion - par devise
    $sheet->setCellValue('A' . $row, 'TOTAL POUR LA PROMOTION ' . $promotionData['promotion']['designationPromotion']);
    $sheet->mergeCells('A' . $row . ':G' . $row);
    $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 12],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'BDD7EE']],
        'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM]]
    ]);
    $row++;
    
    foreach ($promotionData['totals']['par_devise'] as $devise => $montants) {
        $sheet->setCellValue('A' . $row, 'Devise: ' . $devise);
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->setCellValue('D' . $row, $montants['total_montant'] . ' ' . $devise);
        $sheet->setCellValue('E' . $row, $montants['total_paye'] . ' ' . $devise);
        $sheet->setCellValue('F' . $row, $montants['total_restant'] . ' ' . $devise);
        $sheet->setCellValue('G' . $row, $promotionData['totals']['paiements_complets'] . ' / ' . 
            ($promotionData['totals']['paiements_complets'] + $promotionData['totals']['paiements_partiels']));
        $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $row++;
    }
    
    $row += 2; // Espace après chaque promotion
}

// Résumé global
$row++;
$sheet->setCellValue('A' . $row, 'RÉSUMÉ GLOBAL');
$sheet->mergeCells('A' . $row . ':G' . $row);
$sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($styleSubtitle);
$row++;

$sheet->setCellValue('A' . $row, 'Nombre total d\'étudiants:');
$sheet->mergeCells('A' . $row . ':C' . $row);
$sheet->setCellValue('D' . $row, $globalTotals['nombre_etudiants']);
$sheet->mergeCells('D' . $row . ':G' . $row);
$row++;

$sheet->setCellValue('A' . $row, 'Paiements complets:');
$sheet->mergeCells('A' . $row . ':C' . $row);
$sheet->setCellValue('D' . $row, $globalTotals['paiements_complets']);
$sheet->mergeCells('D' . $row . ':G' . $row);
$row++;

$sheet->setCellValue('A' . $row, 'Paiements partiels:');
$sheet->mergeCells('A' . $row . ':C' . $row);
$sheet->setCellValue('D' . $row, $globalTotals['paiements_partiels']);
$sheet->mergeCells('D' . $row . ':G' . $row);
$row++;

// Afficher les totaux par devise
foreach ($globalTotals['par_devise'] as $devise => $montants) {
    $sheet->setCellValue('A' . $row, 'TOTAL ' . $devise . ':');
    $sheet->mergeCells('A' . $row . ':C' . $row);
    $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Montant total à percevoir:');
    $sheet->mergeCells('A' . $row . ':C' . $row);
    $sheet->setCellValue('D' . $row, $montants['total_montant'] . ' ' . $devise);
    $sheet->mergeCells('D' . $row . ':G' . $row);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Montant total perçu:');
    $sheet->mergeCells('A' . $row . ':C' . $row);
    $sheet->setCellValue('D' . $row, $montants['total_paye'] . ' ' . $devise);
    $sheet->mergeCells('D' . $row . ':G' . $row);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Montant total restant à percevoir:');
    $sheet->mergeCells('A' . $row . ':C' . $row);
    $sheet->setCellValue('D' . $row, $montants['total_restant'] . ' ' . $devise);
    $sheet->mergeCells('D' . $row . ':G' . $row);
    $row++;
    
    // Pourcentage de perception pour cette devise
    $pourcentagePerception = $montants['total_montant'] > 0 
        ? round(($montants['total_paye'] / $montants['total_montant']) * 100, 2)
        : 0;
    
    $sheet->setCellValue('A' . $row, 'Taux de perception:');
    $sheet->mergeCells('A' . $row . ':C' . $row);
    $sheet->setCellValue('D' . $row, $pourcentagePerception . '%');
    $sheet->mergeCells('D' . $row . ':G' . $row);
    $row++;
    
    // Ligne vide entre les devises
    $row++;
}

// Appliquer un style au résumé global
$resumeStartRow = $row - count($globalTotals['par_devise']) * 6;
$sheet->getStyle('A' . $resumeStartRow . ':G' . ($row - 2))->applyFromArray([
    'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM], 'inside' => ['borderStyle' => Border::BORDER_THIN]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']]
]);

// Configurer les options d'impression
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);
$sheet->getPageMargins()->setTop(0.5);
$sheet->getPageMargins()->setRight(0.5);
$sheet->getPageMargins()->setLeft(0.5);
$sheet->getPageMargins()->setBottom(0.5);

// Ajouter l'en-tête et le pied de page
$sheet->getHeaderFooter()->setOddHeader('&C&B' . (!empty($configUniversite['nom']) ? $configUniversite['nom'] : 'Établissement') . ' - État des Paiements');
$sheet->getHeaderFooter()->setOddFooter('&L&B' . date('d/m/Y') . '&C&P / &N&R&BExporté le ' . date('d/m/Y H:i:s'));

// Créer le nom du fichier
$fileName = 'Etat_Paiements_' . date('Y-m-d_H-i-s') . '.xlsx';

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

