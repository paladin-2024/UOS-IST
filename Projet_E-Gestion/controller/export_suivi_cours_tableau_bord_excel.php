<?php
session_start();
ob_start();

require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/SuiviCours.php';
require dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;

if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

function trouverElementParId(array $elements, string $cleId, int $id): ?array
{
    foreach ($elements as $element) {
        if (isset($element[$cleId]) && (int)$element[$cleId] === $id) {
            return $element;
        }
    }

    return null;
}

function nettoyerNomFichier(string $texte): string
{
    $texte = preg_replace('/[^A-Za-z0-9]+/', '_', $texte);
    $texte = trim((string) $texte, '_');

    return $texte !== '' ? $texte : 'export';
}

try {
    $suiviCours = new SuiviCours();
    $annees = $suiviCours->getAnneesAcad();
    $anneeActive = $suiviCours->getAnneeActive();

    $idannee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) ($anneeActive['idannee_acad'] ?? 0);
    if ($idannee <= 0) {
        throw new RuntimeException('Aucune année académique disponible pour l’export.');
    }

    $idsectionFilter = isset($_GET['section']) ? (int) $_GET['section'] : 0;
    $anneeChoisie = trouverElementParId($annees, 'idannee_acad', $idannee) ?: $anneeActive;

    $allSections = $suiviCours->getAllSections($idannee);
    $sectionChoisie = $idsectionFilter > 0 ? trouverElementParId($allSections, 'idsection', $idsectionFilter) : null;

    $statsGlobales = $suiviCours->getStatistiquesGlobales($idannee) ?: [
        'total' => 0,
        'termines' => 0,
        'en_cours' => 0,
        'non_commences' => 0,
    ];
    $statsSections = $suiviCours->getStatistiquesBySection($idannee);
    $statsParGraph = $idsectionFilter > 0
        ? $suiviCours->getStatistiquesByPromotion($idsectionFilter, $idannee)
        : $statsSections;
    $detailSection = $idsectionFilter > 0 ? $suiviCours->getDetailBySection($idsectionFilter, $idannee) : [];

    $total = (int) ($statsGlobales['total'] ?? 0);
    $termines = (int) ($statsGlobales['termines'] ?? 0);
    $enCours = (int) ($statsGlobales['en_cours'] ?? 0);
    $nonCommences = (int) ($statsGlobales['non_commences'] ?? 0);

    $ratioTermines = $total > 0 ? $termines / $total : 0;
    $ratioEnCours = $total > 0 ? $enCours / $total : 0;
    $ratioNonCommences = $total > 0 ? $nonCommences / $total : 0;

    $contexte = $idsectionFilter > 0
        ? ($sectionChoisie['designationSection'] ?? ('Section ' . $idsectionFilter))
        : 'Toutes les sections';
    $nomAnnee = $anneeChoisie['designation'] ?? ('Année ' . $idannee);
    $nomFichier = 'tableau_bord_suivi_cours_' . nettoyerNomFichier($nomAnnee . '_' . $contexte) . '_' . date('Ymd_His') . '.xlsx';

    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator($_SESSION['nomUser'] ?? 'E-GESTION')
        ->setTitle('Tableau de bord suivi des cours')
        ->setSubject('Export des statistiques de suivi des cours')
        ->setDescription('Export Excel du tableau de bord de suivi des cours');
    $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

    $styleTitre = [
        'font' => [
            'bold' => true,
            'size' => 14,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '1E3A5F'],
        ],
    ];

    $styleSousTitre = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => '1E3A5F'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'EAF1FF'],
        ],
    ];

    $styleEntete = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '2D5BE3'],
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'D9E1F2'],
            ],
        ],
    ];

    $styleTableau = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'D9E1F2'],
            ],
        ],
    ];

    $styleNote = [
        'font' => [
            'italic' => true,
            'color' => ['rgb' => '666666'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
    ];

    $statusFills = [
        'non_commence' => ['rgb' => 'E9EAEC', 'font' => '44505F'],
        'en_cours' => ['rgb' => 'FFF1CC', 'font' => '8A5C00'],
        'termine' => ['rgb' => 'D4F5E4', 'font' => '156B3E'],
    ];

    // Synthèse
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Synthese');
    $sheet->mergeCells('A1:K1');
    $sheet->setCellValue('A1', 'TABLEAU DE BORD - SUIVI DES COURS');
    $sheet->setCellValue('A2', 'Année académique');
    $sheet->setCellValue('B2', $nomAnnee);
    $sheet->setCellValue('A3', 'Contexte');
    $sheet->setCellValue('B3', $contexte);
    $sheet->setCellValue('A5', 'Indicateur');
    $sheet->setCellValue('B5', 'Valeur');

    $resumes = [
        ['Total cours', $total, 'number'],
        ['Terminés', $termines, 'number'],
        ['En cours', $enCours, 'number'],
        ['Non commencés', $nonCommences, 'number'],
        ['Taux de terminés', $ratioTermines, 'percent'],
        ['Taux en cours', $ratioEnCours, 'percent'],
        ['Taux non commencés', $ratioNonCommences, 'percent'],
    ];

    $ligne = 6;
    foreach ($resumes as [$libelle, $valeur, $type]) {
        $sheet->setCellValue('A' . $ligne, $libelle);
        $sheet->setCellValue('B' . $ligne, $valeur);
        if ($type === 'percent') {
            $sheet->getStyle('B' . $ligne)->getNumberFormat()->setFormatCode('0.0%');
        }
        $ligne++;
    }

    $sheet->setCellValue('D5', 'Statut');
    $sheet->setCellValue('E5', 'Valeur');
    $sheet->setCellValue('D6', 'Terminés');
    $sheet->setCellValue('E6', $termines);
    $sheet->setCellValue('D7', 'En cours');
    $sheet->setCellValue('E7', $enCours);
    $sheet->setCellValue('D8', 'Non commencés');
    $sheet->setCellValue('E8', $nonCommences);

    $sheet->getStyle('A1:K1')->applyFromArray($styleTitre);
    $sheet->getStyle('A2:B3')->applyFromArray($styleSousTitre);
    $sheet->getStyle('A5:B11')->applyFromArray($styleTableau);
    $sheet->getStyle('D5:E8')->applyFromArray($styleTableau);
    $sheet->getStyle('A5:B5')->applyFromArray($styleEntete);
    $sheet->getStyle('D5:E5')->applyFromArray($styleEntete);
    $sheet->getStyle('A5:A11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle('B6:B8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('B5:B11')->getFont()->setBold(true);
    $sheet->getRowDimension(1)->setRowHeight(28);
    $sheet->getRowDimension(5)->setRowHeight(24);
    $sheet->getColumnDimension('A')->setWidth(26);
    $sheet->getColumnDimension('B')->setWidth(16);
    $sheet->getColumnDimension('C')->setWidth(4);
    $sheet->getColumnDimension('D')->setWidth(18);
    $sheet->getColumnDimension('E')->setWidth(12);
    $sheet->freezePane('A6');
    $sheet->setAutoFilter('A5:B11');

    $chartDessine = false;
    if ($total > 0) {
        $dataSeriesLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Synthese!$E$5', null, 1),
        ];
        $xAxisTickValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Synthese!$D$6:$D$8', null, 3),
        ];
        $dataSeriesValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Synthese!$E$6:$E$8', null, 3),
        ];

        $series = new DataSeries(
            DataSeries::TYPE_PIECHART,
            DataSeries::GROUPING_STANDARD,
            range(0, count($dataSeriesValues) - 1),
            $dataSeriesLabels,
            $xAxisTickValues,
            $dataSeriesValues
        );
        $plotArea = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        $title = new Title('Répartition globale des statuts');
        $chart = new Chart('chart_statuts', $title, $legend, $plotArea);
        $chart->setTopLeftPosition('D11');
        $chart->setBottomRightPosition('K27');
        $sheet->addChart($chart);
        $chartDessine = true;
    } else {
        $sheet->setCellValue('D11', 'Aucune donnée disponible pour générer le graphique.');
        $sheet->getStyle('D11:K11')->applyFromArray($styleNote);
    }

    // Répartition
    $sheetRepartition = $spreadsheet->createSheet();
    $sheetRepartition->setTitle('Repartition');
    $sheetRepartition->mergeCells('A1:F1');
    $sheetRepartition->setCellValue('A1', $idsectionFilter > 0 ? 'RÉPARTITION PAR PROMOTION' : 'RÉPARTITION PAR SECTION');
    $sheetRepartition->setCellValue('A2', 'Année académique');
    $sheetRepartition->setCellValue('B2', $nomAnnee);
    $sheetRepartition->setCellValue('A3', 'Contexte');
    $sheetRepartition->setCellValue('B3', $contexte);

    $colonneLibelle = $idsectionFilter > 0 ? 'Promotion' : 'Section';
    $sheetRepartition->setCellValue('A5', $colonneLibelle);
    $sheetRepartition->setCellValue('B5', 'Total');
    $sheetRepartition->setCellValue('C5', 'Terminés');
    $sheetRepartition->setCellValue('D5', 'En cours');
    $sheetRepartition->setCellValue('E5', 'Non commencés');
    $sheetRepartition->setCellValue('F5', 'Taux de terminés');

    $ligne = 6;
    $totalRepartition = 0;
    $terminesRepartition = 0;
    $enCoursRepartition = 0;
    $nonCommencesRepartition = 0;

    foreach ($statsParGraph as $stat) {
        $totalLigne = (int) ($stat['total'] ?? 0);
        $terminesLigne = (int) ($stat['termines'] ?? 0);
        $enCoursLigne = (int) ($stat['en_cours'] ?? 0);
        $nonCommencesLigne = (int) ($stat['non_commences'] ?? 0);
        $tauxTerminaison = $totalLigne > 0 ? $terminesLigne / $totalLigne : 0;

        $libelle = $idsectionFilter > 0
            ? ($stat['designationPromotion'] ?? '')
            : ($stat['designationSection'] ?? '');

        $sheetRepartition->setCellValue('A' . $ligne, $libelle);
        $sheetRepartition->setCellValue('B' . $ligne, $totalLigne);
        $sheetRepartition->setCellValue('C' . $ligne, $terminesLigne);
        $sheetRepartition->setCellValue('D' . $ligne, $enCoursLigne);
        $sheetRepartition->setCellValue('E' . $ligne, $nonCommencesLigne);
        $sheetRepartition->setCellValue('F' . $ligne, $tauxTerminaison);
        $sheetRepartition->getStyle('F' . $ligne)->getNumberFormat()->setFormatCode('0.0%');

        $totalRepartition += $totalLigne;
        $terminesRepartition += $terminesLigne;
        $enCoursRepartition += $enCoursLigne;
        $nonCommencesRepartition += $nonCommencesLigne;
        $ligne++;
    }

    if (!empty($statsParGraph)) {
        $sheetRepartition->setCellValue('A' . $ligne, 'TOTAL');
        $sheetRepartition->setCellValue('B' . $ligne, $totalRepartition);
        $sheetRepartition->setCellValue('C' . $ligne, $terminesRepartition);
        $sheetRepartition->setCellValue('D' . $ligne, $enCoursRepartition);
        $sheetRepartition->setCellValue('E' . $ligne, $nonCommencesRepartition);
        $sheetRepartition->setCellValue('F' . $ligne, $totalRepartition > 0 ? $terminesRepartition / $totalRepartition : 0);
        $sheetRepartition->getStyle('F' . $ligne)->getNumberFormat()->setFormatCode('0.0%');
        $sheetRepartition->getStyle('A' . $ligne . ':F' . $ligne)->getFont()->setBold(true);
    } else {
        $sheetRepartition->mergeCells('A6:F6');
        $sheetRepartition->setCellValue('A6', 'Aucune donnée disponible pour les critères sélectionnés.');
        $sheetRepartition->getStyle('A6:F6')->applyFromArray($styleNote);
    }

    $dernierLigneRepartition = max(6, $ligne);
    $sheetRepartition->getStyle('A1:F1')->applyFromArray($styleTitre);
    $sheetRepartition->getStyle('A2:B3')->applyFromArray($styleSousTitre);
    $sheetRepartition->getStyle('A5:F' . $dernierLigneRepartition)->applyFromArray($styleTableau);
    $sheetRepartition->getStyle('A5:F5')->applyFromArray($styleEntete);
    $sheetRepartition->getRowDimension(1)->setRowHeight(28);
    $sheetRepartition->getRowDimension(5)->setRowHeight(24);
    $sheetRepartition->freezePane('A6');
    if (!empty($statsParGraph)) {
        $sheetRepartition->setAutoFilter('A5:F' . $dernierLigneRepartition);
    }
    $sheetRepartition->getColumnDimension('A')->setWidth(30);
    $sheetRepartition->getColumnDimension('B')->setWidth(12);
    $sheetRepartition->getColumnDimension('C')->setWidth(12);
    $sheetRepartition->getColumnDimension('D')->setWidth(12);
    $sheetRepartition->getColumnDimension('E')->setWidth(16);
    $sheetRepartition->getColumnDimension('F')->setWidth(16);

    // Détail
    if ($idsectionFilter > 0) {
        $sheetDetail = $spreadsheet->createSheet();
        $sheetDetail->setTitle('Detail');
        $sheetDetail->mergeCells('A1:I1');
        $sheetDetail->setCellValue('A1', 'DÉTAIL DU SUIVI DES COURS');
        $sheetDetail->setCellValue('A2', 'Année académique');
        $sheetDetail->setCellValue('B2', $nomAnnee);
        $sheetDetail->setCellValue('A3', 'Section');
        $sheetDetail->setCellValue('B3', $contexte);

        if (!empty($detailSection)) {
            $enTetesDetail = ['Promotion', 'Semestre', 'UE', 'Cours (ECUE)', 'CM', 'TD', 'TP', 'Statut', 'MAJ'];
            $col = 'A';
            foreach ($enTetesDetail as $entete) {
                $sheetDetail->setCellValue($col . '5', $entete);
                $col++;
            }

            $ligne = 6;
            foreach ($detailSection as $detail) {
                $sheetDetail->setCellValue('A' . $ligne, $detail['designationPromotion'] ?? '');
                $sheetDetail->setCellValue('B' . $ligne, $detail['numeroSemestre'] ?? '');
                $sheetDetail->setCellValue('C' . $ligne, trim(($detail['codeUE'] ?? '') . ' ' . ($detail['designationUE'] ?? '')));
                $sheetDetail->setCellValue('D' . $ligne, $detail['designationECUE'] ?? '');
                $sheetDetail->setCellValue('E' . $ligne, (float) ($detail['CMI'] ?? 0));
                $sheetDetail->setCellValue('F' . $ligne, (float) ($detail['TD'] ?? 0));
                $sheetDetail->setCellValue('G' . $ligne, (float) ($detail['TP'] ?? 0));
                $sheetDetail->setCellValue('H' . $ligne, ucfirst(str_replace('_', ' ', $detail['statut'] ?? 'non_commence')));
                $sheetDetail->setCellValue('I' . $ligne, !empty($detail['date_mise_a_jour']) ? date('d/m/Y', strtotime($detail['date_mise_a_jour'])) : '—');

                $statut = $detail['statut'] ?? 'non_commence';
                $couleur = $statusFills[$statut] ?? $statusFills['non_commence'];
                $sheetDetail->getStyle('H' . $ligne)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($couleur['rgb']);
                $sheetDetail->getStyle('H' . $ligne)->getFont()->getColor()->setRGB($couleur['font']);
                $sheetDetail->getStyle('E' . $ligne . ':G' . $ligne)->getNumberFormat()->setFormatCode('0 "h"');
                $ligne++;
            }

            $dernierLigneDetail = $ligne - 1;
            $sheetDetail->getStyle('A1:I1')->applyFromArray($styleTitre);
            $sheetDetail->getStyle('A2:B3')->applyFromArray($styleSousTitre);
            $sheetDetail->getStyle('A5:I' . $dernierLigneDetail)->applyFromArray($styleTableau);
            $sheetDetail->getStyle('A5:I5')->applyFromArray($styleEntete);
            $sheetDetail->freezePane('A6');
            $sheetDetail->setAutoFilter('A5:I' . $dernierLigneDetail);
        } else {
            $sheetDetail->mergeCells('A5:I5');
            $sheetDetail->setCellValue('A5', 'Aucun cours enregistré pour cette section dans l’année sélectionnée.');
            $sheetDetail->getStyle('A5:I5')->applyFromArray($styleNote);
        }

        $sheetDetail->getRowDimension(1)->setRowHeight(28);
        $sheetDetail->getRowDimension(5)->setRowHeight(24);
        $sheetDetail->getColumnDimension('A')->setWidth(30);
        $sheetDetail->getColumnDimension('B')->setWidth(11);
        $sheetDetail->getColumnDimension('C')->setWidth(18);
        $sheetDetail->getColumnDimension('D')->setWidth(32);
        $sheetDetail->getColumnDimension('E')->setWidth(10);
        $sheetDetail->getColumnDimension('F')->setWidth(10);
        $sheetDetail->getColumnDimension('G')->setWidth(10);
        $sheetDetail->getColumnDimension('H')->setWidth(16);
        $sheetDetail->getColumnDimension('I')->setWidth(15);
    }

    $spreadsheet->setActiveSheetIndex(0);

    if (ob_get_length()) {
        ob_end_clean();
    }

    $writer = new Xlsx($spreadsheet);
    $writer->setIncludeCharts($chartDessine);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer->save('php://output');
    exit;
} catch (Throwable $e) {
    error_log('Erreur export tableau de bord suivi cours: ' . $e->getMessage());

    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo 'Erreur lors de la génération du fichier Excel.';
    exit;
}
