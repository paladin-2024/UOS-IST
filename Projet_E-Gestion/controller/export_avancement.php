<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annee_acad'])) {
    $universite = new Universite();
    $etudiantModel = new Etudiant();
    $anneeAcadId = intval($_POST['annee_acad']);
    
    // Récupérer les informations de l'année académique
    $anneeAcad = $universite->getAcademicYearById($anneeAcadId);
    
    // Récupérer tous les étudiants pour cette année
    $etudiants = $universite->getAllEtudiantsWithSujets('', $anneeAcadId);

    // Créer un nouveau document Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Styles
    $headerStyle = [
        'font' => ['bold' => true, 'size' => 12],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E2EFDA']
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ]
    ];

    $subHeaderStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F2F2F2']
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ]
    ];

    // En-tête du document
    $sheet->setCellValue('A1', 'FICHES D\'AVANCEMENT DES ÉTUDIANTS');
    $sheet->setCellValue('A2', 'Année Académique: ' . $anneeAcad['designation']);
    $sheet->mergeCells('A1:H1');
    $sheet->mergeCells('A2:H2');
    
    // En-têtes des colonnes
    $sheet->setCellValue('A4', 'N°');
    $sheet->setCellValue('B4', 'Nom de l\'étudiant');
    $sheet->setCellValue('C4', 'Matricule');
    $sheet->setCellValue('D4', 'Promotion');
    $sheet->setCellValue('E4', 'Sujet');
    $sheet->setCellValue('F4', 'Directeur');
    $sheet->setCellValue('G4', 'Encadreur');
    $sheet->setCellValue('H4', 'Progression');

    // Appliquer les styles
    $sheet->getStyle('A1:H2')->applyFromArray($headerStyle);
    $sheet->getStyle('A4:H4')->applyFromArray($subHeaderStyle);

    // Remplir les données
    $row = 5;
    $count = 1;
    foreach ($etudiants as $etudiant) {
        $sujets = $universite->getSujetsByEtudiant($etudiant['idetudiant']);
        $progression = calculerProgression($sujets, $etudiantModel);

        foreach ($sujets as $sujet) {
            $sheet->setCellValue('A' . $row, $count);
            $sheet->setCellValue('B' . $row, $etudiant['noms']);
            $sheet->setCellValue('C' . $row, $etudiant['matricule']);
            $sheet->setCellValue('D' . $row, $etudiant['promotion']);
            $sheet->setCellValue('E' . $row, $sujet['intitule']);
            $sheet->setCellValue('F' . $row, $sujet['directeur']);
            $sheet->setCellValue('G' . $row, $sujet['encadreur']);
            $sheet->setCellValue('H' . $row, $progression . '%');

            // Style pour les cellules de données
            $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                ]
            ]);

            $row++;
        }
        $count++;
    }

    // Ajuster la largeur des colonnes automatiquement
    foreach (range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }



    // Après la première feuille avec le résumé, ajouter une feuille pour les détails des tâches
$detailSheet = $spreadsheet->createSheet();
$detailSheet->setTitle('Détails des tâches');

// Style pour les en-têtes de la feuille de détails
$detailHeaderStyle = [
    'font' => ['bold' => true],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E2EFDA']
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
    ]
];

// En-têtes pour les détails
$detailSheet->setCellValue('A1', 'N°');
$detailSheet->setCellValue('B1', 'Étudiant');
$detailSheet->setCellValue('C1', 'Promotion');
$detailSheet->setCellValue('D1', 'Sujet');
$detailSheet->setCellValue('E1', 'N° Tâche');
$detailSheet->setCellValue('F1', 'Description de la tâche');
$detailSheet->setCellValue('G1', 'Date');
$detailSheet->setCellValue('H1', 'État');
$detailSheet->setCellValue('I1', 'Dernier échange');

// Appliquer le style aux en-têtes
$detailSheet->getStyle('A1:I1')->applyFromArray($detailHeaderStyle);

// Remplir les détails des tâches
// Remplir les détails des tâches
$detailRow = 2;
$studentCount = 1;

foreach ($etudiants as $etudiant) {
    $sujets = $universite->getSujetsByEtudiant($etudiant['idetudiant']);
    $studentStartRow = $detailRow; // Mémoriser la ligne de début pour l'étudiant
    
    foreach ($sujets as $sujet) {
        $taches = $etudiantModel->getTaches($sujet['idsujets']);
        $sujetStartRow = $detailRow; // Mémoriser la ligne de début pour le sujet
        $taskCount = 1;
        
        foreach ($taches as $tache) {
            $echanges = $etudiantModel->getEchangesTache($tache['idtaches']);
            $dernierEchange = !empty($echanges) ? end($echanges) : null;
            
            $statusColor = match($tache['validation']) {
                'Validé' => 'C6EFCE',
                'En cours' => 'FFE699',
                'Rejeté' => 'FFC7CE',
                default => 'F2F2F2'
            };
            
            // Remplir les données de la tâche
            $detailSheet->setCellValue('A' . $detailRow, $studentCount);
            $detailSheet->setCellValue('B' . $detailRow, $etudiant['noms']);
            $detailSheet->setCellValue('C' . $detailRow, $etudiant['promotion']);
            $detailSheet->setCellValue('D' . $detailRow, $sujet['intitule']);
            $detailSheet->setCellValue('E' . $detailRow, $taskCount);
            $detailSheet->setCellValue('F' . $detailRow, $tache['description']);
            $detailSheet->setCellValue('G' . $detailRow, date('d/m/Y', strtotime($tache['dateTache'])));
            $detailSheet->setCellValue('H' . $detailRow, $tache['validation']);
            
            if ($dernierEchange) {
                $echangeText = sprintf("%s\nPar: %s\nLe: %s",
                    $dernierEchange['commentaire'],
                    $dernierEchange['type_auteur'],
                    date('d/m/Y H:i', strtotime($dernierEchange['dateEchange']))
                );
                $detailSheet->setCellValue('I' . $detailRow, $echangeText);
                $detailSheet->getStyle('I' . $detailRow)
                    ->getAlignment()
                    ->setWrapText(true);
            }
            
            // Appliquer la couleur de fond pour l'état
            $detailSheet->getStyle('H' . $detailRow)
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->setStartColor(new \PhpOffice\PhpSpreadsheet\Style\Color($statusColor));
            
            $detailRow++;
            $taskCount++;
        }
        
        // Fusionner les cellules pour le sujet si plusieurs tâches
        if ($sujetStartRow < ($detailRow - 1)) {
            $detailSheet->mergeCells('D' . $sujetStartRow . ':D' . ($detailRow - 1));
        }
    }
    
    // Fusionner les cellules pour l'étudiant si plusieurs tâches
    if ($studentStartRow < ($detailRow - 1)) {
        $detailSheet->mergeCells('A' . $studentStartRow . ':A' . ($detailRow - 1));
        $detailSheet->mergeCells('B' . $studentStartRow . ':B' . ($detailRow - 1));
        $detailSheet->mergeCells('C' . $studentStartRow . ':C' . ($detailRow - 1));
    }
    
    // Centrer verticalement le contenu des cellules fusionnées
    $detailSheet->getStyle('A' . $studentStartRow . ':D' . ($detailRow - 1))->getAlignment()
        ->setVertical(Alignment::VERTICAL_CENTER);
    
    $studentCount++;
}

// Appliquer les bordures à toutes les cellules
$detailSheet->getStyle('A1:I' . ($detailRow - 1))->applyFromArray([
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
    ]
]);

// Ajuster la largeur des colonnes et la hauteur des lignes
foreach (range('A', 'I') as $col) {
    $detailSheet->getColumnDimension($col)->setAutoSize(true);
}
$detailSheet->getDefaultRowDimension()->setRowHeight(40);

// Style pour améliorer la lisibilité
$detailSheet->getStyle('A2:I' . ($detailRow - 1))->applyFromArray([
    'alignment' => [
        'vertical' => Alignment::VERTICAL_CENTER
    ]
]);

// Définir un style alterné pour les lignes (pour une meilleure lisibilité)
for ($i = 2; $i < $detailRow; $i++) {
    if ($i % 2 == 0) {
        $detailSheet->getStyle('A' . $i . ':I' . $i)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->setStartColor(new \PhpOffice\PhpSpreadsheet\Style\Color('F9F9F9'));
    }
}

// Revenir à la première feuille avant de sauvegarder
$spreadsheet->setActiveSheetIndex(0);

    // Créer le fichier Excel
    $writer = new Xlsx($spreadsheet);
    
    // En-têtes pour le téléchargement
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Fiches_Avancement_' . $anneeAcad['designation'] . '.xlsx"');
    header('Cache-Control: max-age=0');

    // Sauvegarder le fichier
    $writer->save('php://output');
    exit;
}

function calculerProgression($sujets, $etudiantModel) {
    $totalTaches = 0;
    $tachesValidees = 0;

    foreach ($sujets as $sujet) {
        $taches = $etudiantModel->getTaches($sujet['idsujets']);
        $totalTaches += count($taches);
        foreach ($taches as $tache) {
            if ($tache['validation'] === 'Validé') {
                $tachesValidees++;
            }
        }
    }

    return $totalTaches > 0 ? round(($tachesValidees / $totalTaches) * 100) : 0;
}