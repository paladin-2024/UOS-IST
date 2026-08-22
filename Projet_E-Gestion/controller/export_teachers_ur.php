<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si la bibliothèque PhpSpreadsheet est disponible
if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'La bibliothèque PhpSpreadsheet n\'est pas disponible. Veuillez l\'installer avec Composer.'
        }).then(() => {
            window.location.href = '../index.php?view=ur/affecation_ur';
        });
    </script>";
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['exportBtn'])) {
    $idsection = isset($_POST['idsection']) ? intval($_POST['idsection']) : 0;
    $idUniteRecherche = isset($_POST['idUniteRecherche']) ? $_POST['idUniteRecherche'] : '';
    
    if ($idsection <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Section invalide.'
            }).then(() => {
                window.location.href = '../index.php?view=ur/affecation_ur';
            });
        </script>";
        exit;
    }
    
    $db = Connexion::getInstance()->getPDO();
    
    try {
        // Récupérer les informations de la section
        $stmtSection = $db->prepare("
            SELECT s.*, a.designation as anneeDesignation
            FROM section s
            LEFT JOIN annee_acad a ON s.idAnnee = a.idannee_acad
            WHERE s.idsection = ?
        ");
        $stmtSection->execute([$idsection]);
        $section = $stmtSection->fetch(PDO::FETCH_ASSOC);
        
        if (!$section) {
            throw new Exception("Section non trouvée");
        }
        
        // Construire le titre du rapport
        $titre = "Enseignants par unité de recherche - {$section['designationSection']}";
        
        // Créer un nouveau document
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Enseignants par UR');
        
        // En-tête
        $sheet->setCellValue('A1', $titre);
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Sous-titre
        $sheet->setCellValue('A2', 'Date d\'extraction: ' . date('d/m/Y H:i'));
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // En-têtes des colonnes
        $sheet->setCellValue('A4', 'N°');
        $sheet->setCellValue('B4', 'Nom');
        $sheet->setCellValue('C4', 'Grade');
        $sheet->setCellValue('D4', 'Unité de recherche');
        $sheet->setCellValue('E4', 'Spécialisation');
        $sheet->setCellValue('F4', 'Date d\'affectation');
        
                // Mettre en forme les en-têtes
        $sheet->getStyle('A4:F4')->getFont()->setBold(true);
        $sheet->getStyle('A4:F4')->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new \PhpOffice\PhpSpreadsheet\Style\Color('DDDDDD'));
        $sheet->getStyle('A4:F4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Largeur des colonnes
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(30);
        $sheet->getColumnDimension('E')->setWidth(30);
        $sheet->getColumnDimension('F')->setWidth(20);
        
        // Récupérer les données
        $query = "
            SELECT a.idAgent, a.noms, g.designation as gradeDesignation, 
                   ur.designation_UR, s.designation as specialisationName, 
                   es.dateAffectation
            FROM enseignant_specialisation es
            JOIN agent a ON es.idAgent = a.idAgent
            LEFT JOIN grade g ON a.grade_id = g.idgrade
            JOIN specialisation s ON es.idSpecialisation = s.idSpecialisation
            JOIN unite_recherche ur ON s.idUnite_recherche = ur.idunite_recherche
            WHERE s.idsection = ?
        ";
        
        $params = [$idsection];
        
        // Filtrer par unité de recherche si spécifiée
        if ($idUniteRecherche !== 'all' && !empty($idUniteRecherche)) {
            $query .= " AND ur.idunite_recherche = ?";
            $params[] = $idUniteRecherche;
        }
        
        $query .= " ORDER BY ur.designation_UR, s.designation, a.noms";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Remplir les données
        $row = 5;
        $i = 1;
        
        foreach ($teachers as $teacher) {
            $sheet->setCellValue('A' . $row, $i);
            $sheet->setCellValue('B' . $row, $teacher['noms']);
            $sheet->setCellValue('C' . $row, $teacher['gradeDesignation'] ?? '');
            $sheet->setCellValue('D' . $row, $teacher['designation_UR']);
            $sheet->setCellValue('E' . $row, $teacher['specialisationName']);
            $sheet->setCellValue('F' . $row, date('d/m/Y', strtotime($teacher['dateAffectation'])));
            
            $row++;
            $i++;
        }
        
        // Bordures pour toutes les cellules de données
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle('A4:F' . ($row - 1))->applyFromArray($styleArray);
        
        // Créer le fichier Excel
        $writer = new Xlsx($spreadsheet);
        $filename = 'enseignants_ur_' . date('YmdHis') . '.xlsx';
        $filepath = dirname(__DIR__) . '/tmp/' . $filename;
        
        // S'assurer que le dossier tmp existe
        if (!is_dir(dirname(__DIR__) . '/tmp')) {
            mkdir(dirname(__DIR__) . '/tmp', 0777, true);
        }
        
        $writer->save($filepath);
        
        // Télécharger le fichier
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
        
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur s\'est produite: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../index.php?view=ur/affecation_ur';
            });
        </script>";
        exit;
    }
} else {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Méthode non autorisée.'
        }).then(() => {
            window.location.href = '../index.php?view=ur/affecation_ur';
        });
    </script>";
}
