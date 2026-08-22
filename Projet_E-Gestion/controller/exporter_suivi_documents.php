<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require dirname(__DIR__) . '/vendor/autoload.php'; // Assurez-vous d'avoir installé PhpSpreadsheet via Composer

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['promotion_id']) || empty($_GET['promotion_id'])) {
    echo "<script>
        alert('Promotion non spécifiée');
        window.location.href = '../?view=enseignement/suivi_documents_etudiants';
    </script>";
    exit();
}

$promotionId = intval($_GET['promotion_id']);

try {
    $conn = Connexion::getInstance()->getPDO();
    
    // Récupérer les informations sur la promotion
    $stmt = $conn->prepare("
        SELECT p.\"designationPromotion\", p.cycle, o.\"designationOrientation\", s.\"designationSection\",
               aa.designation as annee_academique
        FROM promotion p
        JOIN orientation o ON p.orientation_idorientation = o.idorientation
        JOIN section s ON o.section_idsection = s.idsection
        JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
        WHERE p.idpromotion = ?
    ");
    $stmt->execute([$promotionId]);
    $promotion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$promotion) {
        echo "<script>
            alert('Promotion non trouvée');
            window.location.href = '../?view=enseignement/suivi_documents_etudiants';
        </script>";
        exit();
    }
    
    // Récupérer les documents obligatoires pour ce cycle
    $stmt = $conn->prepare("
        SELECT * FROM documents_obligatoires 
        WHERE cycle = ? OR cycle = 'Tous'
        ORDER BY designation
    ");
    $stmt->execute([$promotion['cycle']]);
    $documentsObligatoires = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les étudiants de cette promotion
    $stmt = $conn->prepare("
        SELECT e.idetudiant, e.matricule, e.noms, e.sexe, e.telephone, e.adressemail
        FROM etudiant e
        WHERE e.promotion_idpromotion = ?
        ORDER BY e.noms
    ");
    $stmt->execute([$promotionId]);
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les documents fournis par ces étudiants
    $documentsParEtudiant = [];
    if (!empty($etudiants)) {
        $matricules = array_column($etudiants, 'matricule');
        $placeholders = str_repeat('?,', count($matricules) - 1) . '?';
        
        $stmt = $conn->prepare("
            SELECT ed.*, do.designation as nom_doc_obligatoire
            FROM etudiant_documents ed
            LEFT JOIN documents_obligatoires do ON ed.document_obligatoire_id = do.id
            WHERE ed.matricule IN ($placeholders)
        ");
        $stmt->execute($matricules);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($documents as $doc) {
            if (!isset($documentsParEtudiant[$doc['matricule']])) {
                $documentsParEtudiant[$doc['matricule']] = [];
            }
            
            if ($doc['document_obligatoire_id']) {
                $documentsParEtudiant[$doc['matricule']][$doc['document_obligatoire_id']] = [
                    'id' => $doc['id'],
                    'statut' => $doc['statut'],
                    'date_ajout' => $doc['date_ajout']
                ];
            }
        }
    }
    
    // Créer un nouveau document Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Suivi Documents');
    
    // Configurer les en-têtes
    $sheet->setCellValue('A1', 'SUIVI DES DOCUMENTS OBLIGATOIRES');
    $sheet->mergeCells('A1:' . chr(65 + count($documentsObligatoires) + 1) . '1');
    
    $sheet->setCellValue('A2', 'Promotion: ' . $promotion['designationSection'] . ' - ' . 
                              $promotion['designationOrientation'] . ' - ' . 
                              $promotion['designationPromotion']);
    $sheet->mergeCells('A2:' . chr(65 + count($documentsObligatoires) + 1) . '2');
    
    $sheet->setCellValue('A3', 'Année académique: ' . $promotion['annee_academique']);
    $sheet->mergeCells('A3:' . chr(65 + count($documentsObligatoires) + 1) . '3');
    
    $sheet->setCellValue('A4', 'Date d\'extraction: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A4:' . chr(65 + count($documentsObligatoires) + 1) . '4');
    
    // Style des en-têtes
    $headerStyle = [
        'font' => [
            'bold' => true,
            'size' => 14
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => [
                'rgb' => 'CCCCFF',
            ],
        ],
    ];
    
    $sheet->getStyle('A1:' . chr(65 + count($documentsObligatoires) + 1) . '4')->applyFromArray($headerStyle);
    $sheet->getRowDimension(1)->setRowHeight(30);
    
    // En-têtes de tableau
    $sheet->setCellValue('A6', 'Matricule');
    $sheet->setCellValue('B6', 'Nom de l\'étudiant');
    
    $col = 2;
    foreach ($documentsObligatoires as $doc) {
        $sheet->setCellValue(chr(65 + $col) . '6', $doc['designation']);
        $col++;
    }
    
    // Style des en-têtes de colonnes
    $columnHeaderStyle = [
        'font' => [
            'bold' => true,
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => [
                'rgb' => 'E0E0E0',
            ],
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ];
    
    $sheet->getStyle('A6:' . chr(65 + count($documentsObligatoires) + 1) . '6')->applyFromArray($columnHeaderStyle);
    
    // Remplir les données
    $row = 7;
    foreach ($etudiants as $etudiant) {
        $sheet->setCellValue('A' . $row, $etudiant['matricule']);
        $sheet->setCellValue('B' . $row, $etudiant['noms']);
        
        $col = 2;
        foreach ($documentsObligatoires as $doc) {
            $status = 'Non fourni';
            $bgcolor = 'FFCCCC'; // Rouge clair pour non fourni
            
            if (isset($documentsParEtudiant[$etudiant['matricule']][$doc['id']])) {
                $docEtudiant = $documentsParEtudiant[$etudiant['matricule']][$doc['id']];
                
                switch ($docEtudiant['statut']) {
                    case 'Valide':
                        $status = 'Validé';
                        $bgcolor = 'CCFFCC'; // Vert clair
                        break;
                    case 'En attente de validation':
                        $status = 'En attente';
                        $bgcolor = 'FFFFCC'; // Jaune clair
                        break;
                    case 'Rejeté':
                        $status = 'Rejeté';
                        $bgcolor = 'FFB0B0'; // Rouge plus foncé
                        break;
                }
            }
            
            $sheet->setCellValue(chr(65 + $col) . $row, $status);
            $sheet->getStyle(chr(65 + $col) . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bgcolor);
            
            $col++;
        }
        
        $row++;
    }
    
    // Style des cellules de données
    $dataCellStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ];
    
    $sheet->getStyle('A7:' . chr(65 + count($documentsObligatoires) + 1) . ($row - 1))->applyFromArray($dataCellStyle);
    
    // Ajuster automatiquement la largeur des colonnes
    foreach (range('A', chr(65 + count($documentsObligatoires) + 1)) as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    
    // Légende
    $row += 2;
    $sheet->setCellValue('A' . $row, 'Légende:');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    
    $row++;
    $sheet->setCellValue('A' . $row, 'Validé');
    $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('CCFFCC');
    
    $row++;
    $sheet->setCellValue('A' . $row, 'En attente');
    $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFCC');
    
    $row++;
    $sheet->setCellValue('A' . $row, 'Rejeté');
    $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFB0B0');
    
    $row++;
    $sheet->setCellValue('A' . $row, 'Non fourni');
    $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFCCCC');
    
    // Générer le fichier Excel
    $writer = new Xlsx($spreadsheet);
    $fileName = 'Suivi_Documents_' . str_replace(' ', '_', $promotion['designationPromotion']) . '_' . date('Ymd_His') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit();
    
} catch (PDOException $e) {
    echo "<script>
    alert('Erreur lors de l\'exportation: " . $e->getMessage() . "');
    window.location.href = '../?view=enseignement/suivi_documents_etudiants';
</script>";
exit();
}
