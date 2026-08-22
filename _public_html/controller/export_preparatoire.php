<?php
session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Drawing\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

// Vérifications d'authentification
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../etudiants/etudiant.inscrit');
    exit;
}

// Vérification des paramètres requis
if (!isset($_POST['preparatoireClass']) || empty($_POST['preparatoireClass'])) {
    die("Classe préparatoire non spécifiée");
}

try {
    $universite = new Universite();
    $preparatoireClass = $_POST['preparatoireClass'];
    
    // Récupérer les étudiants de la classe préparatoire
    $students = $universite->getStudentsByPreparatoireClass($preparatoireClass);
    
    if (empty($students)) {
        die("Aucun étudiant trouvé pour cette classe préparatoire.");
    }
    
    // Récupérer la configuration de l'université
    $configUniversite = $universite->getConfigurationUniversite();
    
    // Nom du fichier d'exportation
    $fileName = "etudiants_preparatoire_" . str_replace(' ', '_', $preparatoireClass) . "_" . date('Y-m-d_H-i-s') . ".xlsx";
    
    // Créer un nouveau document Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Étudiants Préparatoires');
    
    // Configurer la page en mode paysage et ajuster à 1 page de largeur
    $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
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
    
    $stylePromotion = [
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
            $drawing->setCoordinates('F1'); // Colonne centrale (ajuster selon le nombre de colonnes)
            $drawing->setOffsetX(80); // Ajuster pour centrer parfaitement
            $drawing->setHeight(120); // Ajustez la hauteur selon vos besoins
            $drawing->setWorksheet($sheet);
            
            // Ajuster la hauteur de la ligne pour le logo
            $sheet->getRowDimension(1)->setRowHeight(60);
            
            // Décaler les autres informations
            $row = 4;
        }
    }
    
    // En-tête avec les informations de l'université
    $sheet->setCellValue('A' . $row, !empty($configUniversite['ministere_tutelle']) ? $configUniversite['ministere_tutelle'] : '');
    $sheet->mergeCells('A' . $row . ':L' . $row);
    $sheet->getStyle('A' . $row . ':L' . $row)->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $row++;
    
    $sheet->setCellValue('A' . $row, !empty($configUniversite['nom']) ? $configUniversite['nom'] : '');
    $sheet->mergeCells('A' . $row . ':L' . $row);
    $sheet->getStyle('A' . $row . ':L' . $row)->applyFromArray($styleUniversite);
    $sheet->getRowDimension($row)->setRowHeight(25);
    $row++;
    
    // Adresse et coordonnées
    $adresse = '';
    if (!empty($configUniversite['adresse'])) $adresse .= $configUniversite['adresse'];
    if (!empty($configUniversite['ville'])) $adresse .= (!empty($adresse) ? ', ' : '') . $configUniversite['ville'];
    
    $sheet->setCellValue('A' . $row, $adresse);
    $sheet->mergeCells('A' . $row . ':L' . $row);
    $sheet->getStyle('A' . $row . ':L' . $row)->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $row++;
    
    $contacts = '';
    if (!empty($configUniversite['telephone'])) $contacts .= 'Tél: ' . $configUniversite['telephone'];
    if (!empty($configUniversite['email'])) $contacts .= (!empty($contacts) ? ' | ' : '') . 'Email: ' . $configUniversite['email'];
    
    $sheet->setCellValue('A' . $row, $contacts);
    $sheet->mergeCells('A' . $row . ':L' . $row);
    $sheet->getStyle('A' . $row . ':L' . $row)->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $row++;
    
    // Ligne de séparation
    $sheet->setCellValue('A' . $row, '');
    $sheet->mergeCells('A' . $row . ':L' . $row);
    $sheet->getStyle('A' . $row . ':L' . $row)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
    $row += 2;
    
    // Titre du document
    $sheet->setCellValue('A' . $row, 'LISTE DES ÉTUDIANTS PRÉPARATOIRES');
    $sheet->mergeCells('A' . $row . ':L' . $row);
    $sheet->getStyle('A' . $row . ':L' . $row)->applyFromArray($styleTitle);
    $sheet->getRowDimension($row)->setRowHeight(30);
    $row += 2;
    
    // Informations sur la classe préparatoire
    $sheet->setCellValue('A' . $row, 'Classe: ' . $preparatoireClass);
    $sheet->mergeCells('A' . $row . ':L' . $row);
    $sheet->getStyle('A' . $row . ':L' . $row)->applyFromArray($stylePromotion);
    $sheet->getRowDimension($row)->setRowHeight(25);
    $row += 2;
    
    // En-têtes des colonnes pour les étudiants - Toutes les colonnes demandées
    $sheet->setCellValue('A' . $row, 'N°');
    $sheet->setCellValue('B' . $row, 'Matricule');
    $sheet->setCellValue('C' . $row, 'Noms');
    $sheet->setCellValue('D' . $row, 'Sexe');
    $sheet->setCellValue('E' . $row, 'Lieu de Naissance');
    $sheet->setCellValue('F' . $row, 'Date de Naissance');
    $sheet->setCellValue('G' . $row, 'Nationalité');
    $sheet->setCellValue('H' . $row, 'Email');
    $sheet->setCellValue('I' . $row, 'Téléphone');
    $sheet->setCellValue('J' . $row, 'Adresse');
    $sheet->setCellValue('K' . $row, 'Personne à contacter');
    $sheet->setCellValue('L' . $row, 'Téléphone contact');
    $sheet->getStyle('A' . $row . ':L' . $row)->applyFromArray($styleHeader);
    $row++;
    
    // Données des étudiants avec toutes les colonnes
    $count = 1;
    $startDataRow = $row;
    foreach ($students as $student) {
        $sheet->setCellValue('A' . $row, $count);
        $sheet->setCellValue('B' . $row, $student['matricule'] ?? 'N/A');
        $sheet->setCellValue('C' . $row, $student['noms'] ?? 'N/A');
        $sheet->setCellValue('D' . $row, $student['sexe'] ?? '');
        $sheet->setCellValue('E' . $row, $student['lieuNaissance'] ?? '');
        $sheet->setCellValue('F' . $row, $student['dateNaissance'] ?? '');
        $sheet->setCellValue('G' . $row, $student['nationalite'] ?? '');
        $sheet->setCellValue('H' . $row, $student['adressemail'] ?? '');
        $sheet->setCellValue('I' . $row, $student['telephone'] ?? '');
        $sheet->setCellValue('J' . $row, $student['adresse'] ?? '');
        $sheet->setCellValue('K' . $row, $student['personne_contact'] ?? '');
        $sheet->setCellValue('L' . $row, $student['telephone_contact'] ?? '');
        $count++;
        $row++;
    }
    
    // Appliquer le style aux données
    if ($row > $startDataRow) {
        $sheet->getStyle('A' . $startDataRow . ':L' . ($row - 1))->applyFromArray($styleData);
    }
    
    // Ajuster la largeur des colonnes
    foreach (range('A', 'L') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    
    // Nettoyer le buffer de sortie pour éviter les problèmes d'en-têtes
    ob_clean();
    
    // Définir les en-têtes HTTP pour le téléchargement
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    
    // Créer le writer et envoyer le fichier au navigateur
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    
} catch (Exception $e) {
    // Journaliser l'erreur
    error_log('Erreur lors de l\'exportation des étudiants préparatoires: ' . $e->getMessage());
    
    // Afficher un message d'erreur à l'utilisateur
    echo "<script>
        alert('Erreur lors de l\'exportation: " . addslashes($e->getMessage()) . "');
        window.location.href = '../etudiants/etudiant.inscrit';
    </script>";
    exit;
}
?>
