<?php
/**
 * Contrôleur d'export Excel - Module Dossiers
 * Génère un fichier Excel formaté avec en-tête universitaire, feuille de données + feuille de statistiques
 */

require_once APP_ROOT . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

$model = new DossierModel();

$anneeAcadId = intval($_GET['annee'] ?? 0);
$sectionId = intval($_GET['section'] ?? 0) ?: null;
$orientationId = intval($_GET['orientation'] ?? 0) ?: null;
$statut = $_GET['statut'] ?? null;
if ($statut === '') $statut = null;

// Récupérer les données
$exportData = $model->getExportDetailedData($anneeAcadId, $sectionId, $orientationId, $statut);
$etudiants = $exportData['etudiants'];
$typesDocuments = $exportData['types_documents'];
$statsData = $model->getExportStats($anneeAcadId, $sectionId);
$configUniv = $model->getConfigurationUniversite();

// Récupérer le nom de l'année académique
$annees = $model->getAnneesAcademiques();
$anneeDesignation = '';
foreach ($annees as $a) {
    if ($a['idannee_acad'] == $anneeAcadId) {
        $anneeDesignation = $a['designation'];
        break;
    }
}

// Récupérer le nom de la section si filtrée
$sectionDesignation = 'Toutes les sections';
if ($sectionId) {
    $sections = $model->getSections($anneeAcadId);
    foreach ($sections as $sec) {
        if ($sec['idsection'] == $sectionId) {
            $sectionDesignation = $sec['designationSection'];
            break;
        }
    }
}

// ═══════════════════════════════════════════════════════
// PALETTE DE COULEURS & STYLES
// ═══════════════════════════════════════════════════════

$colors = [
    'primary'      => '0D1B2A',  // Bleu très foncé
    'secondary'    => '1B3A5C',  // Bleu foncé
    'accent'       => '2E86AB',  // Bleu accent
    'highlight'    => '4ECDC4',  // Turquoise
    'gold'         => 'D4A843',  // Or/doré
    'white'        => 'FFFFFF',
    'light_gray'   => 'F8F9FA',
    'medium_gray'  => 'E9ECEF',
    'dark_gray'    => '6C757D',
    'text_dark'    => '212529',
    'text_muted'   => '6C757D',
    'success'      => '198754',
    'warning'      => 'FFC107',
    'danger'       => 'DC3545',
    'info'         => '0DCAF0',
];

// Style en-tête de tableau principal
$tableHeaderStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => $colors['white']], 'size' => 10, 'name' => 'Calibri'],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['secondary']]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['primary']]]]
];

// Style sous-en-tête (colonnes documents)
$docHeaderStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => $colors['white']], 'size' => 9, 'name' => 'Calibri'],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['accent']]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['secondary']]]]
];

$dataBorderStyle = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['medium_gray']]]]
];

$statutColors = [
    'valide' => $colors['success'],
    'soumis' => $colors['accent'],
    'rejete' => $colors['danger'],
    'en_cours' => 'E67E22',
    'incomplet' => 'E67E22',
    'en_attente' => $colors['dark_gray']
];

$statutLabels = [
    'valide' => 'Validé',
    'soumis' => 'Soumis',
    'rejete' => 'Rejeté',
    'en_cours' => 'En cours',
    'incomplet' => 'Incomplet',
    'en_attente' => 'En attente',
    'non_commence' => 'Non commencé'
];

// ═══════════════════════════════════════════════════════
// FONCTION : En-tête institutionnel réutilisable
// ═══════════════════════════════════════════════════════

/**
 * Insère l'en-tête institutionnel (logo + infos université) sur une feuille
 * @return int La ligne suivante disponible après l'en-tête
 */
function insertInstitutionalHeader($sheet, $configUniv, $lastCol, $colors) {
    $row = 1;

    // ── Bandeau supérieur coloré ──
    $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
    $sheet->getRowDimension($row)->setRowHeight(6);
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['primary']]]
    ]);
    $row++;

    // ── Logo de l'université ──
    $logoInserted = false;
    if (!empty($configUniv['logo'])) {
        $logoPath = APP_ROOT . '/' . $configUniv['logo'];
        if (!file_exists($logoPath)) {
            $logoPath = APP_ROOT . '/uploads/' . $configUniv['logo'];
        }
        if (file_exists($logoPath)) {
            $drawing = new Drawing();
            $drawing->setName('Logo');
            $drawing->setPath($logoPath);
            $drawing->setHeight(55);
            $drawing->setCoordinates('A' . $row);
            $drawing->setOffsetX(10);
            $drawing->setOffsetY(5);
            $drawing->setWorksheet($sheet);
            $logoInserted = true;
        }
    }

    // ── Ministère de tutelle ──
    $startCol = $logoInserted ? 'C' : 'A';
    if (!empty($configUniv['ministere_tutelle'])) {
        $sheet->mergeCells($startCol . $row . ':' . $lastCol . $row);
        $sheet->setCellValue($startCol . $row, strtoupper($configUniv['ministere_tutelle']));
        $sheet->getStyle($startCol . $row)->applyFromArray([
            'font' => ['bold' => false, 'size' => 9, 'color' => ['rgb' => $colors['dark_gray']], 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);
        $sheet->getRowDimension($row)->setRowHeight(16);
        $row++;
    }

    // ── Nom de l'université ──
    $univNom = !empty($configUniv['nom']) ? strtoupper($configUniv['nom']) : 'UNIVERSITÉ';
    $sheet->mergeCells($startCol . $row . ':' . $lastCol . $row);
    $sheet->setCellValue($startCol . $row, $univNom);
    $sheet->getStyle($startCol . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => $colors['primary']], 'name' => 'Calibri'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
    ]);
    $sheet->getRowDimension($row)->setRowHeight(24);
    $row++;

    // ── Sigle ──
    if (!empty($configUniv['sigle'])) {
        $sheet->mergeCells($startCol . $row . ':' . $lastCol . $row);
        $sheet->setCellValue($startCol . $row, '« ' . $configUniv['sigle'] . ' »');
        $sheet->getStyle($startCol . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => $colors['gold']], 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;
    }

    // ── Contact (ville, téléphone, email, site web) ──
    $contactParts = [];
    if (!empty($configUniv['ville'])) $contactParts[] = $configUniv['ville'];
    if (!empty($configUniv['pays'])) $contactParts[] = $configUniv['pays'];
    if (!empty($configUniv['telephone'])) $contactParts[] = 'Tél: ' . $configUniv['telephone'];
    if (!empty($configUniv['email'])) $contactParts[] = $configUniv['email'];
    if (!empty($configUniv['site_web'])) $contactParts[] = $configUniv['site_web'];

    if (!empty($contactParts)) {
        $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        $sheet->setCellValue('A' . $row, implode('  •  ', $contactParts));
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['size' => 8, 'color' => ['rgb' => $colors['text_muted']], 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);
        $sheet->getRowDimension($row)->setRowHeight(14);
        $row++;
    }

    // ── Ligne de séparation dorée ──
    $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
    $sheet->getRowDimension($row)->setRowHeight(3);
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['gold']]]
    ]);
    $row++;

    // ── Ligne fine secondaire ──
    $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
    $sheet->getRowDimension($row)->setRowHeight(2);
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['primary']]]
    ]);
    $row++;

    // Fond blanc pour toute la zone d'en-tête
    $sheet->getStyle('A1:' . $lastCol . ($row - 1))->applyFromArray([
        'borders' => ['outline' => ['borderStyle' => Border::BORDER_NONE]]
    ]);

    return $row;
}

// ═══════════════════════════════════════════════════════
// FEUILLE 1 : LISTE DES ÉTUDIANTS
// ═══════════════════════════════════════════════════════

$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Liste des dossiers');

// Colonnes de base
$baseColumns = ['N°', 'Photo', 'Matricule', 'Nom de l\'étudiant', 'Promotion', 'Orientation', 'Section', 'Cycle', 'Statut dossier', 'Complétion', 'Date soumission'];

// Colonnes des types de documents
$docColumns = [];
foreach ($typesDocuments as $td) {
    $docColumns[] = $td['designation'] . ($td['est_obligatoire'] ? ' *' : '');
}

$allColumns = array_merge($baseColumns, $docColumns);
$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($allColumns));

// ── En-tête institutionnel ──
$row = insertInstitutionalHeader($sheet, $configUniv, $lastCol, $colors);

// ── Espace ──
$sheet->getRowDimension($row)->setRowHeight(8);
$row++;

// ── Titre du rapport ──
$sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
$sheet->setCellValue('A' . $row, 'RAPPORT DES DOSSIERS ÉTUDIANTS');
$sheet->getStyle('A' . $row)->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => $colors['primary']], 'name' => 'Calibri'],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['light_gray']]],
    'borders' => [
        'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => $colors['accent']]],
        'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => $colors['accent']]]
    ]
]);
$sheet->getRowDimension($row)->setRowHeight(28);
$row++;

// ── Informations du rapport (cartouche) ──
$sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
$infoLine = 'Année académique : ' . $anneeDesignation . '    |    Section : ' . $sectionDesignation . '    |    Généré le : ' . date('d/m/Y à H:i');
$sheet->setCellValue('A' . $row, $infoLine);
$sheet->getStyle('A' . $row)->applyFromArray([
    'font' => ['size' => 9, 'color' => ['rgb' => $colors['text_muted']], 'italic' => true, 'name' => 'Calibri'],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['medium_gray']]]
]);
$sheet->getRowDimension($row)->setRowHeight(20);
$row++;

// ── Filtre statut si présent ──
if ($statut) {
    $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
    $sheet->setCellValue('A' . $row, 'Filtre appliqué : ' . ($statutLabels[$statut] ?? $statut));
    $sheet->getStyle('A' . $row)->applyFromArray([
        'font' => ['size' => 9, 'color' => ['rgb' => $colors['accent']], 'bold' => true, 'name' => 'Calibri'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F4FD']]
    ]);
    $sheet->getRowDimension($row)->setRowHeight(18);
    $row++;
}

// ── Espace avant le tableau ──
$sheet->getRowDimension($row)->setRowHeight(5);
$row++;

// ── En-têtes de colonnes ──
$headerRow = $row;
for ($i = 0; $i < count($allColumns); $i++) {
    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
    $sheet->setCellValue($col . $headerRow, $allColumns[$i]);
    
    if ($i >= count($baseColumns)) {
        $sheet->getStyle($col . $headerRow)->applyFromArray($docHeaderStyle);
    } else {
        $sheet->getStyle($col . $headerRow)->applyFromArray($tableHeaderStyle);
    }
}
$sheet->getRowDimension($headerRow)->setRowHeight(36);
$row = $headerRow + 1;

// ── Données des étudiants ──
$num = 1;
foreach ($etudiants as $etu) {
    $pct = floatval($etu['pourcentage_completion'] ?? 0);
    $dStatut = $etu['dossier_statut'] ?? 'non_commence';
    $dateSoum = !empty($etu['date_soumission']) ? date('d/m/Y', strtotime($etu['date_soumission'])) : '—';

    $sheet->setCellValue('A' . $row, $num);

    // Photo étudiant
    $photoInserted = false;
    if (!empty($etu['photo'])) {
        $photoPath = APP_ROOT . '/uploads/' . $etu['photo'];
        if (file_exists($photoPath)) {
            $drawing = new Drawing();
            $drawing->setName('Photo ' . $etu['matricule']);
            $drawing->setPath($photoPath);
            $drawing->setHeight(38);
            $drawing->setCoordinates('B' . $row);
            $drawing->setOffsetX(4);
            $drawing->setOffsetY(2);
            $drawing->setWorksheet($sheet);
            $photoInserted = true;
        }
    }
    if (!$photoInserted) {
        $sheet->setCellValue('B' . $row, '—');
        $sheet->getStyle('B' . $row)->applyFromArray([
            'font' => ['color' => ['rgb' => $colors['medium_gray']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);
    }

    $sheet->setCellValue('C' . $row, $etu['matricule']);
    $sheet->setCellValue('D' . $row, $etu['noms']);
    $sheet->setCellValue('E' . $row, $etu['designationPromotion']);
    $sheet->setCellValue('F' . $row, $etu['designationOrientation']);
    $sheet->setCellValue('G' . $row, $etu['designationSection']);
    $sheet->setCellValue('H' . $row, $etu['cycle']);
    $sheet->setCellValue('I' . $row, $statutLabels[$dStatut] ?? $dStatut);
    $sheet->setCellValue('J' . $row, $pct);
    $sheet->setCellValue('K' . $row, $dateSoum);

    // Hauteur de ligne pour la photo
    $sheet->getRowDimension($row)->setRowHeight(34);

    // Couleur alternée
    $bgColor = ($num % 2 === 0) ? $colors['light_gray'] : $colors['white'];
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
        'borders' => [
            'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['medium_gray']]],
            'left' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['medium_gray']]],
            'right' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['medium_gray']]]
        ],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
    ]);

    // Centrer certaines colonnes
    foreach (['A', 'B', 'C', 'H', 'I', 'J', 'K'] as $centerCol) {
        $sheet->getStyle($centerCol . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // Nom en gras
    $sheet->getStyle('D' . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => $colors['text_dark']]]
    ]);

    // Colorer le statut du dossier avec fond léger
    $statutColor = $statutColors[$dStatut] ?? $colors['dark_gray'];
    $sheet->getStyle('I' . $row)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => $statutColor], 'size' => 9]
    ]);

    // Complétion avec barre de progression textuelle
    $pctColor = $pct >= 100 ? $colors['success'] : ($pct >= 50 ? 'E67E22' : $colors['danger']);
    $sheet->getStyle('J' . $row)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => $pctColor]],
        'numberFormat' => ['formatCode' => '0"%"']
    ]);

    // Colonnes des documents
    $docColStart = count($baseColumns) + 1;
    foreach ($typesDocuments as $td) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($docColStart);
        if (isset($etu['documents_detail'][$td['id']])) {
            $docInfo = $etu['documents_detail'][$td['id']];
            $docStatutLabel = $statutLabels[$docInfo['statut']] ?? $docInfo['statut'];
            $sheet->setCellValue($col . $row, $docStatutLabel);
            $docStatutColor = $statutColors[$docInfo['statut']] ?? $colors['dark_gray'];
            $sheet->getStyle($col . $row)->applyFromArray([
                'font' => ['color' => ['rgb' => $docStatutColor], 'size' => 9],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
        } else {
            $sheet->setCellValue($col . $row, '—');
            $sheet->getStyle($col . $row)->applyFromArray([
                'font' => ['color' => ['rgb' => $colors['medium_gray']]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
        }
        $docColStart++;
    }

    $num++;
    $row++;
}

// ── Ligne de total ──
$row++;
$sheet->mergeCells('A' . $row . ':D' . $row);
$sheet->setCellValue('A' . $row, 'TOTAL : ' . count($etudiants) . ' étudiants');
$sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray([
    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => $colors['white']], 'name' => 'Calibri'],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['secondary']]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['primary']]]]
]);
$sheet->getRowDimension($row)->setRowHeight(26);

// ── Bandeau inférieur ──
$row += 2;
$sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
$sheet->getRowDimension($row)->setRowHeight(3);
$sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray([
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['gold']]]
]);
$row++;
$sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
$sheet->getRowDimension($row)->setRowHeight(2);
$sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray([
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['primary']]]
]);

// ── Pied de page ──
$row++;
$footerText = 'Document généré automatiquement par E-Gestion';
if (!empty($configUniv['sigle'])) $footerText .= ' — ' . $configUniv['sigle'];
$footerText .= ' — ' . date('d/m/Y H:i');
$sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
$sheet->setCellValue('A' . $row, $footerText);
$sheet->getStyle('A' . $row)->applyFromArray([
    'font' => ['size' => 8, 'color' => ['rgb' => $colors['text_muted']], 'italic' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);

// ── Largeurs de colonnes ──
$sheet->getColumnDimension('A')->setWidth(6);   // N°
$sheet->getColumnDimension('B')->setWidth(8);   // Photo
$sheet->getColumnDimension('C')->setWidth(14);  // Matricule
$sheet->getColumnDimension('D')->setWidth(30);  // Nom
$sheet->getColumnDimension('E')->setWidth(20);  // Promotion
$sheet->getColumnDimension('F')->setWidth(20);  // Orientation
$sheet->getColumnDimension('G')->setWidth(18);  // Section
$sheet->getColumnDimension('H')->setWidth(8);   // Cycle
$sheet->getColumnDimension('I')->setWidth(16);  // Statut
$sheet->getColumnDimension('J')->setWidth(12);  // Complétion
$sheet->getColumnDimension('K')->setWidth(16);  // Date soumission

$docColStart = count($baseColumns) + 1;
foreach ($typesDocuments as $td) {
    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($docColStart);
    $sheet->getColumnDimension($col)->setWidth(16);
    $docColStart++;
}

// Figer les volets sous l'en-tête de colonnes
$sheet->freezePane('A' . ($headerRow + 1));

// Filtre automatique sur la zone de données
$filterRange = 'A' . $headerRow . ':' . $lastCol . ($headerRow + count($etudiants));
$sheet->setAutoFilter($filterRange);

// Mise en page impression
$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);
$sheet->getHeaderFooter()->setOddFooter('&L&8E-Gestion&C&8Page &P / &N&R&8' . date('d/m/Y'));

// ═══════════════════════════════════════════════════════
// FEUILLE 2 : STATISTIQUES
// ═══════════════════════════════════════════════════════

$statsSheet = $spreadsheet->createSheet();
$statsSheet->setTitle('Statistiques');
$statsLastCol = 'H';

// ── En-tête institutionnel ──
$row = insertInstitutionalHeader($statsSheet, $configUniv, $statsLastCol, $colors);

// ── Espace ──
$statsSheet->getRowDimension($row)->setRowHeight(8);
$row++;

// ── Titre ──
$statsSheet->mergeCells('A' . $row . ':' . $statsLastCol . $row);
$statsSheet->setCellValue('A' . $row, 'STATISTIQUES DES DOSSIERS');
$statsSheet->getStyle('A' . $row)->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => $colors['primary']], 'name' => 'Calibri'],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['light_gray']]],
    'borders' => [
        'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => $colors['accent']]],
        'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => $colors['accent']]]
    ]
]);
$statsSheet->getRowDimension($row)->setRowHeight(28);
$row++;

// Sous-titre
$statsSheet->mergeCells('A' . $row . ':' . $statsLastCol . $row);
$statsSheet->setCellValue('A' . $row, 'Année : ' . $anneeDesignation . '    |    Section : ' . $sectionDesignation . '    |    Date : ' . date('d/m/Y'));
$statsSheet->getStyle('A' . $row)->applyFromArray([
    'font' => ['size' => 9, 'color' => ['rgb' => $colors['text_muted']], 'italic' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['medium_gray']]]
]);
$statsSheet->getRowDimension($row)->setRowHeight(20);
$row += 2;

// ── Section : Résumé global ──
$statsSheet->mergeCells('A' . $row . ':D' . $row);
$statsSheet->setCellValue('A' . $row, '  RÉSUMÉ GLOBAL');
$statsSheet->getStyle('A' . $row . ':D' . $row)->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => $colors['white']]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['secondary']]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['primary']]]]
]);
$statsSheet->getRowDimension($row)->setRowHeight(28);
$row++;

$globalItems = [
    ['Nombre total de finalistes', $statsData['total_finalistes'], $colors['accent'], 'E8F4FD'],
    ['Dossiers créés', $statsData['dossiers_crees'], $colors['dark_gray'], $colors['white']],
    ['Dossiers soumis', $statsData['dossiers_soumis'], $colors['accent'], 'E8F4FD'],
    ['Dossiers validés', $statsData['dossiers_valides'], $colors['success'], 'E8F5E9'],
    ['Dossiers rejetés', $statsData['dossiers_rejetes'], $colors['danger'], 'FFEBEE'],
    ['Dossiers en cours', $statsData['dossiers_en_cours'], 'E67E22', 'FFF3E0'],
    ['Non démarrés', $statsData['total_finalistes'] - $statsData['dossiers_crees'], '9CA3AF', $colors['light_gray']],
    ['Complétion moyenne', $statsData['moyenne_completion'] . '%', $colors['gold'], 'FFF8E1'],
];

foreach ($globalItems as $item) {
    $statsSheet->setCellValue('A' . $row, '    ' . $item[0]);
    $statsSheet->mergeCells('B' . $row . ':C' . $row);
    $statsSheet->setCellValue('B' . $row, $item[1]);
    $statsSheet->getStyle('A' . $row . ':D' . $row)->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $item[3]]],
        'borders' => [
            'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['medium_gray']]],
            'left' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['medium_gray']]],
            'right' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['medium_gray']]]
        ],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
    ]);
    $statsSheet->getStyle('A' . $row)->applyFromArray([
        'font' => ['size' => 10, 'color' => ['rgb' => $colors['text_dark']]]
    ]);
    $statsSheet->getStyle('B' . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => $item[2]]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $statsSheet->getRowDimension($row)->setRowHeight(24);
    $row++;
}

// Taux
$row++;
$tauxSoumission = $statsData['total_finalistes'] > 0 ? round(($statsData['dossiers_soumis'] + $statsData['dossiers_valides'] + $statsData['dossiers_rejetes']) / $statsData['total_finalistes'] * 100, 1) : 0;
$tauxValidation = $statsData['dossiers_crees'] > 0 ? round($statsData['dossiers_valides'] / $statsData['dossiers_crees'] * 100, 1) : 0;

foreach ([
    ['Taux de soumission', $tauxSoumission . '%', $colors['accent']],
    ['Taux de validation', $tauxValidation . '%', $colors['success']]
] as $taux) {
    $statsSheet->setCellValue('A' . $row, '    ' . $taux[0]);
    $statsSheet->mergeCells('B' . $row . ':C' . $row);
    $statsSheet->setCellValue('B' . $row, $taux[1]);
    $statsSheet->getStyle('A' . $row . ':D' . $row)->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['medium_gray']]]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
    ]);
    $statsSheet->getStyle('B' . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => $taux[2]]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $statsSheet->getRowDimension($row)->setRowHeight(24);
    $row++;
}

$row += 2;

// ── Section : Répartition par section ──
$statsSheet->mergeCells('A' . $row . ':G' . $row);
$statsSheet->setCellValue('A' . $row, '  RÉPARTITION PAR SECTION');
$statsSheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => $colors['white']]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['secondary']]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['primary']]]]
]);
$statsSheet->getRowDimension($row)->setRowHeight(28);
$row++;

$sectionHeaders = ['Section', 'Étudiants', 'Dossiers créés', 'Soumis', 'Validés', 'Rejetés', 'Complétion moy.'];
for ($i = 0; $i < count($sectionHeaders); $i++) {
    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
    $statsSheet->setCellValue($col . $row, $sectionHeaders[$i]);
}
$statsSheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($tableHeaderStyle);
$statsSheet->getRowDimension($row)->setRowHeight(30);
$row++;

if (!empty($statsData['par_section'])) {
    foreach ($statsData['par_section'] as $idx => $sec) {
        $bgColor = ($idx % 2 === 0) ? $colors['light_gray'] : $colors['white'];
        $statsSheet->setCellValue('A' . $row, $sec['designationSection']);
        $statsSheet->setCellValue('B' . $row, $sec['total_etudiants']);
        $statsSheet->setCellValue('C' . $row, $sec['dossiers_crees']);
        $statsSheet->setCellValue('D' . $row, $sec['soumis']);
        $statsSheet->setCellValue('E' . $row, $sec['valides']);
        $statsSheet->setCellValue('F' . $row, $sec['rejetes']);
        $statsSheet->setCellValue('G' . $row, ($sec['moy_completion'] ?? 0) . '%');

        $statsSheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['medium_gray']]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
        ]);
        $statsSheet->getStyle('B' . $row . ':G' . $row)->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
        $statsSheet->getStyle('E' . $row)->applyFromArray(['font' => ['color' => ['rgb' => $colors['success']], 'bold' => true]]);
        $statsSheet->getStyle('F' . $row)->applyFromArray(['font' => ['color' => ['rgb' => $colors['danger']], 'bold' => true]]);
        $statsSheet->getRowDimension($row)->setRowHeight(22);
        $row++;
    }
}

$row += 2;

// ── Section : Répartition par promotion ──
$statsSheet->mergeCells('A' . $row . ':H' . $row);
$statsSheet->setCellValue('A' . $row, '  RÉPARTITION PAR PROMOTION');
$statsSheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => $colors['white']]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['secondary']]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['primary']]]]
]);
$statsSheet->getRowDimension($row)->setRowHeight(28);
$row++;

$promoHeaders = ['Promotion', 'Section', 'Étudiants', 'Dossiers créés', 'Soumis', 'Validés', 'Rejetés', 'Complétion moy.'];
for ($i = 0; $i < count($promoHeaders); $i++) {
    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
    $statsSheet->setCellValue($col . $row, $promoHeaders[$i]);
}
$statsSheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($tableHeaderStyle);
$statsSheet->getRowDimension($row)->setRowHeight(30);
$row++;

if (!empty($statsData['par_promotion'])) {
    foreach ($statsData['par_promotion'] as $idx => $promo) {
        $bgColor = ($idx % 2 === 0) ? $colors['light_gray'] : $colors['white'];
        $statsSheet->setCellValue('A' . $row, $promo['designationPromotion']);
        $statsSheet->setCellValue('B' . $row, $promo['designationSection']);
        $statsSheet->setCellValue('C' . $row, $promo['total_etudiants']);
        $statsSheet->setCellValue('D' . $row, $promo['dossiers_crees']);
        $statsSheet->setCellValue('E' . $row, $promo['soumis']);
        $statsSheet->setCellValue('F' . $row, $promo['valides']);
        $statsSheet->setCellValue('G' . $row, $promo['rejetes']);
        $statsSheet->setCellValue('H' . $row, ($promo['moy_completion'] ?? 0) . '%');

        $statsSheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colors['medium_gray']]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
        ]);
        $statsSheet->getStyle('C' . $row . ':H' . $row)->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
        $statsSheet->getStyle('F' . $row)->applyFromArray(['font' => ['color' => ['rgb' => $colors['success']], 'bold' => true]]);
        $statsSheet->getStyle('G' . $row)->applyFromArray(['font' => ['color' => ['rgb' => $colors['danger']], 'bold' => true]]);
        $statsSheet->getRowDimension($row)->setRowHeight(22);
        $row++;
    }
}

// ── Bandeau inférieur + pied de page ──
$row += 2;
$statsSheet->mergeCells('A' . $row . ':H' . $row);
$statsSheet->getRowDimension($row)->setRowHeight(3);
$statsSheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['gold']]]
]);
$row++;
$statsSheet->mergeCells('A' . $row . ':H' . $row);
$statsSheet->getRowDimension($row)->setRowHeight(2);
$statsSheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['primary']]]
]);
$row++;
$footerText = 'Document généré automatiquement par E-Gestion';
if (!empty($configUniv['sigle'])) $footerText .= ' — ' . $configUniv['sigle'];
$footerText .= ' — ' . date('d/m/Y H:i');
$statsSheet->mergeCells('A' . $row . ':H' . $row);
$statsSheet->setCellValue('A' . $row, $footerText);
$statsSheet->getStyle('A' . $row)->applyFromArray([
    'font' => ['size' => 8, 'color' => ['rgb' => $colors['text_muted']], 'italic' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);

// ── Largeurs des colonnes feuille stats ──
$statsSheet->getColumnDimension('A')->setWidth(30);
$statsSheet->getColumnDimension('B')->setWidth(18);
$statsSheet->getColumnDimension('C')->setWidth(16);
$statsSheet->getColumnDimension('D')->setWidth(16);
$statsSheet->getColumnDimension('E')->setWidth(14);
$statsSheet->getColumnDimension('F')->setWidth(14);
$statsSheet->getColumnDimension('G')->setWidth(18);
$statsSheet->getColumnDimension('H')->setWidth(18);

// Mise en page stats
$statsSheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
$statsSheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
$statsSheet->getHeaderFooter()->setOddFooter('&L&8E-Gestion&C&8Page &P / &N&R&8' . date('d/m/Y'));

// ═══════════════════════════════════════════════════════
// GÉNÉRATION DU FICHIER
// ═══════════════════════════════════════════════════════

$spreadsheet->setActiveSheetIndex(0);

// Métadonnées du document
$spreadsheet->getProperties()
    ->setCreator(!empty($configUniv['nom']) ? $configUniv['nom'] : 'E-Gestion')
    ->setTitle('Rapport des dossiers étudiants - ' . $anneeDesignation)
    ->setSubject('Dossiers numériques étudiants')
    ->setDescription('Rapport généré par le module Dossiers de E-Gestion')
    ->setLastModifiedBy('E-Gestion');

$filename = 'Rapport_Dossiers_' . str_replace(['/', ' '], '_', $anneeDesignation) . '_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
