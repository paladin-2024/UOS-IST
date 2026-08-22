<?php
// Désactiver la mise en cache et les erreurs potentielles qui pourraient corrompre le fichier
error_reporting(0);
ob_start();

session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Drawing\Drawing;

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    header('Location: ../index');
    exit;
}

// Récupérer les paramètres
$idEvaluation = isset($_POST['idevaluation']) ? intval($_POST['idevaluation']) : 0;
$idECUE = isset($_POST['idECUE']) ? intval($_POST['idECUE']) : 0;

if ($idEvaluation <= 0 || $idECUE <= 0) {
    exit('Paramètres invalides');
}

try {
    $ecue = new Ecue();
    $universite = new Universite();
    
    // Récupérer la configuration de l'université
    $configUniversite = $universite->getConfigurationUniversite();
    
    // Récupérer les informations sur l'évaluation
    $evaluation = $ecue->getEvaluationById($idEvaluation);
    if (!$evaluation) {
        exit('Évaluation introuvable');
    }
    
    // Récupérer les informations sur l'ECUE
    $ecueInfo = $ecue->getEcueById($idECUE);
    
    // Récupérer les étudiants et leurs notes
    $etudiants = $ecue->getStudentsByEcue($idECUE, $evaluation['annee_acad_id']);
    $notes = $ecue->getNotesByEvaluation($idEvaluation);
    
    // Créer un nouveau classeur
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Notes');
    
    // Configuration de la mise en page pour l'impression sur une seule page
    $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
    $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
    $sheet->getPageSetup()->setFitToPage(true);
    $sheet->getPageSetup()->setFitToWidth(1);
    $sheet->getPageSetup()->setFitToHeight(0); // 0 = autocalculé
    
    // Configurer les marges (en pouces)
    $sheet->getPageMargins()->setTop(0.5);
    $sheet->getPageMargins()->setRight(0.5);
    $sheet->getPageMargins()->setLeft(0.5);
    $sheet->getPageMargins()->setBottom(0.5);
    
    // Configurer l'en-tête et le pied de page
    $universityName = !empty($configUniversite['nom']) ? $configUniversite['nom'] : 'Université';
    $sheet->getHeaderFooter()->setOddHeader('&C&B' . $universityName);
    $sheet->getHeaderFooter()->setOddFooter('&L&B' . date('d/m/Y') . '&C&BPage &P sur &N&R&BExporté par: ' . ($_SESSION['noms'] ?? 'Utilisateur'));
    
    // Styles
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4472C4'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ];
    
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ];
    
    $warningStyle = [
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FFC7CE'],
        ],
        'font' => [
            'color' => ['rgb' => '9C0006'],
        ],
    ];
    
    $successStyle = [
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'C6EFCE'],
        ],
        'font' => [
            'color' => ['rgb' => '006100'],
        ],
    ];
    
    // Position de départ pour le contenu
    $row = 1;
    
    // Ajouter le logo si disponible
    
    if (!empty($configUniversite['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
        if (file_exists($logoPath)) {
            // Insérer le logo dans le document Excel
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName('Logo');
            $drawing->setDescription('Logo de l\'université');
            $drawing->setPath($logoPath);
            
            // Centrer le logo
            $drawing->setCoordinates('E1'); // Colonne centrale (ajuster selon le nombre de colonnes)
            $drawing->setOffsetX(0); // Ajuster pour centrer parfaitement
            $drawing->setHeight(60); // Ajustez la hauteur selon vos besoins
            $drawing->setWorksheet($sheet);
            
            // Ajuster la hauteur de la ligne pour le logo
            $sheet->getRowDimension(1)->setRowHeight(60);
            
            // Décaler les autres informations
            $row = 4;
        }
    }        // En-tête avec les informations de l'université
    $sheet->setCellValue('A' . $row, !empty($configUniversite['ministere_tutelle']) ? $configUniversite['ministere_tutelle'] : '');
    $sheet->mergeCells('A' . $row . ':E' . $row);
    $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $row++;
    
    $sheet->setCellValue('A' . $row, !empty($configUniversite['nom']) ? $configUniversite['nom'] : '');
    $sheet->mergeCells('A' . $row . ':E' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension($row)->setRowHeight(25);
    $row++;
    
    // Adresse et coordonnées
    $adresse = '';
    if (!empty($configUniversite['adresse'])) $adresse .= $configUniversite['adresse'];
    if (!empty($configUniversite['ville'])) $adresse .= (!empty($adresse) ? ', ' : '') . $configUniversite['ville'];
    
    $sheet->setCellValue('A' . $row, $adresse);
    $sheet->mergeCells('A' . $row . ':E' . $row);
    $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $row++;
    
    $contacts = '';
    if (!empty($configUniversite['telephone'])) $contacts .= 'Tél: ' . $configUniversite['telephone'];
    if (!empty($configUniversite['email'])) $contacts .= (!empty($contacts) ? ' | ' : '') . 'Email: ' . $configUniversite['email'];
    
    $sheet->setCellValue('A' . $row, $contacts);
    $sheet->mergeCells('A' . $row . ':E' . $row);
    $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $row++;
    
    // Ligne de séparation
    $sheet->setCellValue('A' . $row, '');
    $sheet->mergeCells('A' . $row . ':E' . $row);
    $sheet->getStyle('A' . $row . ':E' . $row)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
    $row += 2;
    
    // Informations d'en-tête du document
    $sheet->setCellValue('A' . $row, 'GRILLE DE NOTES - ' . strtoupper($ecueInfo['designationECUE']));
    $sheet->mergeCells('A' . $row . ':E' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row += 2;
    
    // Informations sur l'évaluation
    $sheet->setCellValue('A' . $row, 'Évaluation:');
    $sheet->setCellValue('B' . $row, $evaluation['titre']);
    $sheet->setCellValue('D' . $row, 'Type:');
    $sheet->setCellValue('E' . $row, $evaluation['designationT']);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Date:');
    $sheet->setCellValue('B' . $row, date('d/m/Y', strtotime($evaluation['date_evaluation'])));
    $sheet->setCellValue('D' . $row, 'Session:');
    $sheet->setCellValue('E' . $row, $evaluation['designSession']);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Cours:');
    $sheet->setCellValue('B' . $row, $ecueInfo['designationECUE']);
    $sheet->setCellValue('D' . $row, 'Année acad.:');
    $sheet->setCellValue('E' . $row, $evaluation['annee_acad_id']);
    $row += 2;
    
    // En-têtes du tableau
    $sheet->setCellValue('A' . $row, 'N°');
    $sheet->setCellValue('B' . $row, 'Matricule');
    $sheet->setCellValue('C' . $row, 'Nom de l\'étudiant');
    $sheet->setCellValue('D' . $row, 'Note (/20)');
    $sheet->setCellValue('E' . $row, 'Observation');
    
    $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($headerStyle);
    $tableHeaderRow = $row;
    $row++;
    
    // Ajuster la largeur des colonnes pour optimiser l'espace sur une page
    $sheet->getColumnDimension('A')->setWidth(4);
    $sheet->getColumnDimension('B')->setWidth(12);
    $sheet->getColumnDimension('C')->setWidth(35);
    $sheet->getColumnDimension('D')->setWidth(10);
    $sheet->getColumnDimension('E')->setWidth(15);
    
    // Remplir les données
    $i = 1;
    $totalNotes = 0;
    $notesCount = 0;
    
    foreach ($etudiants as $etudiant) {
        $note = null;
        foreach ($notes as $n) {
            if ($n['idetudiant'] == $etudiant['idetudiant']) {
                $note = $n['coteObtenu'];
                if ($note !== null) {
                    $totalNotes += $note;
                    $notesCount++;
                }
                break;
            }
        }
        
        $sheet->setCellValue('A' . $row, $i++);
        $sheet->setCellValue('B' . $row, $etudiant['matricule']);
        $sheet->setCellValue('C' . $row, $etudiant['noms']);
        $sheet->setCellValue('D' . $row, $note !== null ? $note : '');
        
        // Observation automatique basée sur la note
        $observation = '';
        if ($note !== null) {
            if ($note < 10) {
                $observation = 'Insuffisant';
                $sheet->getStyle('D' . $row)->applyFromArray($warningStyle);
            } elseif ($note >= 16) {
                $observation = 'Excellent';
                $sheet->getStyle('D' . $row)->applyFromArray($successStyle);
            } elseif ($note >= 14) {
                $observation = 'Très Bien';
            } elseif ($note >= 12) {
                $observation = 'Bien';
            } elseif ($note >= 10) {
                $observation = 'Passable';
            }
        }
        $sheet->setCellValue('E' . $row, $observation);
        
        $row++;
    }
    
    // Appliquer le style aux données
    $sheet->getStyle('A' . $tableHeaderRow + 1 . ':E' . ($row - 1))->applyFromArray($dataStyle);
    $sheet->getStyle('D' . $tableHeaderRow + 1 . ':D' . ($row - 1))->getNumberFormat()->setFormatCode('0.00');
    
    // Ajouter des statistiques
    $row++;
    $sheet->setCellValue('C' . $row, 'Statistiques:');
    $sheet->getStyle('C' . $row)->getFont()->setBold(true);
    
    $row++;
    $sheet->setCellValue('C' . $row, 'Nombre d\'étudiants:');
    $sheet->setCellValue('D' . $row, count($etudiants));
    
    $row++;
    $sheet->setCellValue('C' . $row, 'Notes enregistrées:');
    $sheet->setCellValue('D' . $row, $notesCount);
    
    $row++;
    $sheet->setCellValue('C' . $row, 'Moyenne de classe:');
    $sheet->setCellValue('D' . $row, $notesCount > 0 ? number_format($totalNotes / $notesCount, 2) : 'N/A');
    $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('0.00');
    
    // Signature
    $row += 2;
    $sheet->setCellValue('A' . $row, 'Exporté le:');
    $sheet->setCellValue('B' . $row, date('d/m/Y H:i:s'));
    
    $row++;
    $sheet->setCellValue('A' . $row, 'Par:');
    $sheet->setCellValue('B' . $row, $_SESSION['noms'] ?? 'Utilisateur du système');
    
    $row += 2;
    $sheet->setCellValue('A' . $row, 'Signature:');
    $sheet->setCellValue('C' . $row, 'Cachet de l\'institution:');
    
    // Répéter les lignes d'en-tête sur chaque page
    $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, $tableHeaderRow);
    
    // Vider toute sortie précédente
    if (ob_get_length()) ob_clean();
    
    // Préparation du nom de fichier (éviter les caractères problématiques)
    $safeTitle = preg_replace('/[^a-zA-Z0-9_]/', '_', $evaluation['titre']);
    $fileName = 'Notes_' . $safeTitle . '_' . date('Ymd_His') . '.xlsx';
    
    // Définir les en-têtes HTTP pour le téléchargement
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Créer et enregistrer le fichier
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    
} catch (Exception $e) {
    exit('Erreur: ' . $e->getMessage());
}
