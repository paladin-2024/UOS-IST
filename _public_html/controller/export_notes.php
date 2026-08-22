<?php
session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Ecue.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

// Vérifications d'authentification
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['idDevoir'])) {
    header('Location: ../?view=enseignement/cours');
    exit;
}

$universite = new Universite();
$ecue = new Ecue();
$idDevoir = intval($_POST['idDevoir']);

// Récupérer les détails du devoir
$devoir = $ecue->getAssignmentById($idDevoir);
if (!$devoir) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Devoir non trouvé.'
        }).then(() => {
            window.history.back();
        });
    </script>";
    exit;
}

// Récupérer l'ECUE associé
$ecueDetails = $ecue->getEcueById($devoir['idECUE']);

// Récupérer l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();

// Récupérer les réponses des étudiants
$reponses = $ecue->getAssignmentResponses($idDevoir);

// Récupérer la configuration de l'université
$configUniversite = $universite->getConfigurationUniversite();

// Créer un nouveau document Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Notes ' . substr($devoir['titre'], 0, 20));

// Configurer la page en mode portrait et ajuster à 1 page de largeur
$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0); // 0 = autant de pages que nécessaire en hauteur

// Styles pour les différents niveaux
$styleTitle = [
    'font' => ['bold' => true, 'size' => 16],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];

$styleUniversite = [
    'font' => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];

$styleSubtitle = [
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
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
];

// Ajouter le logo si disponible
$row = 1;
if (!empty($configUniversite['logo'])) {
    $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
    if (file_exists($logoPath)) {
        // Insérer le logo dans le document Excel
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo de l\'université');
        $drawing->setPath($logoPath);
        
        // Centrer le logo
        $drawing->setCoordinates('D1'); // Colonne centrale (ajuster selon le nombre de colonnes)
        $drawing->setOffsetX(30); // Ajuster pour centrer parfaitement
        $drawing->setHeight(100); // Ajustez la hauteur selon vos besoins
        $drawing->setWorksheet($sheet);
        
        // Ajuster la hauteur de la ligne pour le logo
        $sheet->getRowDimension(1)->setRowHeight(60);
        
        // Décaler les autres informations
        $row = 4;
    }
}

// En-tête avec les informations de l'université
$sheet->setCellValue('A' . $row, !empty($configUniversite['ministere_tutelle']) ? $configUniversite['ministere_tutelle'] : '');
$sheet->mergeCells('A' . $row . ':F' . $row);
$sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray([
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);
$row++;

$sheet->setCellValue('A' . $row, !empty($configUniversite['nom']) ? $configUniversite['nom'] : '');
$sheet->mergeCells('A' . $row . ':F' . $row);
$sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray($styleUniversite);
$sheet->getRowDimension($row)->setRowHeight(25);
$row++;

// Adresse et coordonnées
$adresse = '';
if (!empty($configUniversite['adresse'])) $adresse .= $configUniversite['adresse'];
if (!empty($configUniversite['ville'])) $adresse .= (!empty($adresse) ? ', ' : '') . $configUniversite['ville'];

$sheet->setCellValue('A' . $row, $adresse);
$sheet->mergeCells('A' . $row . ':F' . $row);
$sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray([
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);
$row++;

$contacts = '';
if (!empty($configUniversite['telephone'])) $contacts .= 'Tél: ' . $configUniversite['telephone'];
if (!empty($configUniversite['email'])) $contacts .= (!empty($contacts) ? ' | ' : '') . 'Email: ' . $configUniversite['email'];

$sheet->setCellValue('A' . $row, $contacts);
$sheet->mergeCells('A' . $row . ':F' . $row);
$sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray([
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);
$row++;

// Ligne de séparation
$sheet->setCellValue('A' . $row, '');
$sheet->mergeCells('A' . $row . ':F' . $row);
$sheet->getStyle('A' . $row . ':F' . $row)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
$row += 2;

// Titre du document
$sheet->setCellValue('A' . $row, 'FICHE DES POINTS EVALUATION EN LIGNE');
$sheet->mergeCells('A' . $row . ':F' . $row);
$sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray($styleTitle);
$sheet->getRowDimension($row)->setRowHeight(30);
$row += 2;

// Informations sur l'année académique et le cours
$sheet->setCellValue('A' . $row, 'Année Académique: ' . $currentYear['designation']);
$sheet->mergeCells('A' . $row . ':F' . $row);
$sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray($styleSubtitle);
$sheet->getRowDimension($row)->setRowHeight(25);
$row++;

// Informations du devoir
$row++;
$sheet->setCellValue('A' . $row, 'ECUE:');
$sheet->setCellValue('C' . $row, $ecueDetails['designationECUE']);
$sheet->mergeCells('C' . $row . ':F' . $row);
$row++;

$sheet->setCellValue('A' . $row, 'UE:');
$sheet->setCellValue('C' . $row, $ecueDetails['designationUE']);
$sheet->mergeCells('C' . $row . ':F' . $row);
$row++;

$sheet->setCellValue('A' . $row, 'Devoir:');
$sheet->setCellValue('C' . $row, $devoir['titre']);
$sheet->mergeCells('C' . $row . ':F' . $row);
$row++;

$sheet->setCellValue('A' . $row, 'Date limite:');
$sheet->setCellValue('C' . $row, date('d/m/Y H:i', strtotime($devoir['date_limite'])));
$sheet->mergeCells('C' . $row . ':F' . $row);
$row += 2;

// Statistiques
$totalReponses = count($reponses);
$notesCount = 0;
$totalNotes = 0;

foreach ($reponses as $reponse) {
    if ($reponse['note'] !== null) {
        $notesCount++;
        $totalNotes += $reponse['note'];
    }
}

$averageNote = $notesCount > 0 ? round($totalNotes / $notesCount, 2) : 0;

$sheet->setCellValue('A' . $row, 'Statistiques:');
$sheet->getStyle('A' . $row)->getFont()->setBold(true);
$row++;

$sheet->setCellValue('A' . $row, 'Nombre total de réponses:');
$sheet->setCellValue('C' . $row, $totalReponses);
$row++;

$sheet->setCellValue('A' . $row, 'Nombre de réponses notées:');
$sheet->setCellValue('C' . $row, $notesCount);
$row++;

$sheet->setCellValue('A' . $row, 'Note moyenne:');
$sheet->setCellValue('C' . $row, $averageNote . ' / 20');
$row += 2;

// En-têtes du tableau
$sheet->setCellValue('A' . $row, 'N°');
$sheet->setCellValue('B' . $row, 'Matricule');
$sheet->setCellValue('C' . $row, 'Nom de l\'étudiant');
$sheet->setCellValue('D' . $row, 'Date de soumission');
$sheet->setCellValue('E' . $row, 'Note');
$sheet->setCellValue('F' . $row, 'Observations');
$sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray($styleHeader);
$row++;

// Données des réponses
$count = 1;
$startDataRow = $row;

foreach ($reponses as $reponse) {
    $sheet->setCellValue('A' . $row, $count);
    $sheet->setCellValue('B' . $row, $reponse['matricule']);
    $sheet->setCellValue('C' . $row, $reponse['noms']);
    $sheet->setCellValue('D' . $row, date('d/m/Y H:i', strtotime($reponse['date_soumission'])));
    
        // Note
        if ($reponse['note'] !== null) {
            $sheet->setCellValue('E' . $row, $reponse['note'] . ' / 20');
            
            // Appliquer une couleur en fonction de la note
            if ($reponse['note'] >= 10) {
                $sheet->getStyle('E' . $row)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C6EFCE']],
                    'font' => ['color' => ['rgb' => '006100']]
                ]);
            } else {
                $sheet->getStyle('E' . $row)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC7CE']],
                    'font' => ['color' => ['rgb' => '9C0006']]
                ]);
            }
        } else {
            $sheet->setCellValue('E' . $row, 'Non noté');
        }
        
        // Observations (feedback)
        $sheet->setCellValue('F' . $row, $reponse['feedback_enseignant'] ?? '');
        
        // Autofit pour le feedback qui peut être long
        $sheet->getStyle('F' . $row)->getAlignment()->setWrapText(true);
        
        $count++;
        $row++;
    }
    
    // Appliquer le style aux données
    if ($row > $startDataRow) {
        $sheet->getStyle('A' . $startDataRow . ':F' . ($row - 1))->applyFromArray($styleData);
    }
    
    // Ajuster la largeur des colonnes
    $sheet->getColumnDimension('A')->setWidth(5);     // N°
    $sheet->getColumnDimension('B')->setWidth(25);    // Matricule
    $sheet->getColumnDimension('C')->setWidth(30);    // Nom de l'étudiant
    $sheet->getColumnDimension('D')->setWidth(20);    // Date de soumission
    $sheet->getColumnDimension('E')->setWidth(10);    // Note
    $sheet->getColumnDimension('F')->setWidth(40);    // Observations
    
    // Ajouter une section pour les signatures
    $row += 2;
    $sheet->setCellValue('A' . $row, 'Fait à ' . (!empty($configUniversite['ville']) ? $configUniversite['ville'] : '_______________') . ', le ' . date('d/m/Y'));
    $sheet->mergeCells('A' . $row . ':D' . $row);
    $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
    ]);
    $row += 2;
    
    // Signature de l'enseignant
    $sheet->setCellValue('A' . $row, 'Signature de l\'enseignant:');
    $sheet->mergeCells('A' . $row . ':D' . $row);
    $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray([
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
    ]);
    $row += 6;
    
    // Signature du responsable pédagogique
    $sheet->setCellValue('A' . $row, 'Signature du responsable pédagogique:');
    $sheet->mergeCells('A' . $row . ':D' . $row);
    $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray([
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
    ]);
    
    // Configurer les options d'impression
    $sheet->getPageSetup()->setFitToWidth(1);
    $sheet->getPageSetup()->setFitToHeight(0);
    $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
    $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
    $sheet->getPageMargins()->setTop(0.5);
    $sheet->getPageMargins()->setRight(0.5);
    $sheet->getPageMargins()->setLeft(0.5);
    $sheet->getPageMargins()->setBottom(0.5);
    
    // Ajouter l'en-tête et le pied de page
    $sheet->getHeaderFooter()->setOddHeader('&C&B' . $ecueDetails['designationECUE'] . ' - Relevé de Notes');
    $sheet->getHeaderFooter()->setOddFooter('&L&B' . $configUniversite['nom'] . '&C&P / &N&R&B' . date('d/m/Y'));
    
    // Créer le nom du fichier
    $fileName = 'Notes_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $devoir['titre']) . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    // Définir les en-têtes HTTP pour le téléchargement
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    
    // Créer le writer et envoyer le fichier au navigateur
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    ?>
    
