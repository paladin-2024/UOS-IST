<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php?view=login');
    exit;
}

// Vérifier si l'ID du palmarès est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID du palmarès non spécifié.";
    header('Location: ../index.php?view=academique/palmares_archives');
    exit;
}

$idPalmares = intval($_GET['id']);
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'excel';

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    // Récupérer les informations du palmarès
    $query = "SELECT p.*, u.nomUser 
              FROM palmares_archives p 
              LEFT JOIN t_users u ON p.idUser = u.idUser 
              WHERE p.idpalmares = :id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $idPalmares, PDO::PARAM_INT);
    $stmt->execute();
    $palmares = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$palmares) {
        $_SESSION['error'] = "Palmarès introuvable.";
        header('Location: ../index.php?view=academique/palmares_archives');
        exit;
    }
    
    // Récupérer les étudiants associés à ce palmarès
    $queryEtudiants = "SELECT * FROM etudiants_palmares_archives 
                      WHERE idpalmares = :id 
                      ORDER BY pourcentage DESC, nom_complet ASC";
    $stmtEtudiants = $pdo->prepare($queryEtudiants);
    $stmtEtudiants->bindParam(':id', $idPalmares, PDO::PARAM_INT);
    $stmtEtudiants->execute();
    $etudiants = $stmtEtudiants->fetchAll(PDO::FETCH_ASSOC);
    
    // Créer un nouveau document Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Palmarès');
    
    // Définir les styles
    $titleStyle = [
        'font' => [
            'bold' => true,
            'size' => 16,
            'color' => ['rgb' => '000000']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];
    
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4472C4']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ];
    
    $infoStyle = [
        'font' => [
            'bold' => true
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];
    
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ],
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];
    
    $percentageStyle = [
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER
        ],
        'numberFormat' => [
            'formatCode' => '0.00%'
        ]
    ];
    
    // Titre du document
    $sheet->mergeCells('A1:E1');
    $sheet->setCellValue('A1', mb_strtoupper($palmares['designation']));
    $sheet->getStyle('A1')->applyFromArray($titleStyle);
    $sheet->getRowDimension(1)->setRowHeight(30);
    
    // Informations générales
    $sheet->setCellValue('A3', 'Année académique:');
    $sheet->setCellValue('B3', $palmares['annee_academique']);
    $sheet->setCellValue('A4', 'Section:');
    $sheet->setCellValue('B4', $palmares['section']);
    $sheet->setCellValue('A5', 'Promotion:');
    $sheet->setCellValue('B5', $palmares['promotion']);
    $sheet->setCellValue('A6', 'Session:');
    $sheet->setCellValue('B6', $palmares['session']);
    $sheet->setCellValue('D3', 'Date de création:');
    $sheet->setCellValue('E3', date('d/m/Y', strtotime($palmares['date_creation'])));
    $sheet->setCellValue('D4', 'Créé par:');
    $sheet->setCellValue('E4', $palmares['nomUser']);
    $sheet->setCellValue('D5', 'Nombre d\'étudiants:');
    $sheet->setCellValue('E5', count($etudiants));
    
    $sheet->getStyle('A3:A6')->applyFromArray($infoStyle);
    $sheet->getStyle('D3:D6')->applyFromArray($infoStyle);
    
    // En-têtes du tableau
    $sheet->setCellValue('A8', 'Rang');
    $sheet->setCellValue('B8', 'Matricule');
    $sheet->setCellValue('C8', 'Nom complet');
    $sheet->setCellValue('D8', 'Pourcentage');
    $sheet->setCellValue('E8', 'Décision');
    $sheet->getStyle('A8:E8')->applyFromArray($headerStyle);
    
    // Données des étudiants
    $row = 9;
    $rang = 1;
    
    foreach ($etudiants as $etudiant) {
        $sheet->setCellValue('A' . $row, $rang++);
        $sheet->setCellValue('B' . $row, $etudiant['matricule']);
        $sheet->setCellValue('C' . $row, $etudiant['nom_complet']);
        $sheet->setCellValue('D' . $row, $etudiant['pourcentage'] / 100); // Convertir en pourcentage pour Excel
        $sheet->setCellValue('E' . $row, $etudiant['decision']);
        
        // Appliquer des styles conditionnels selon la décision
        $decisionLower = strtolower($etudiant['decision']);
        $fillColor = 'FFFFFF'; // Blanc par défaut
        
        if (strpos($decisionLower, 'grande distinction') !== false) {
            $fillColor = 'C6EFCE'; // Vert clair
        } elseif (strpos($decisionLower, 'distinction') !== false) {
            $fillColor = 'B4C6E7'; // Bleu clair
        } elseif (strpos($decisionLower, 'satisfaction') !== false) {
            $fillColor = 'BDD7EE'; // Bleu très clair
        } elseif (strpos($decisionLower, 'ajourn') !== false) {
            $fillColor = 'FFC7CE'; // Rouge clair
        } elseif (strpos($decisionLower, 'abandon') !== false) {
            $fillColor = 'FFEB9C'; // Jaune clair
        }
        
        $sheet->getStyle('E' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($fillColor);
        
        $row++;
    }    
    // Appliquer les styles aux données
    $sheet->getStyle('A9:E' . ($row - 1))->applyFromArray($dataStyle);
    $sheet->getStyle('D9:D' . ($row - 1))->applyFromArray($percentageStyle);
    
    // Ajuster la largeur des colonnes
    $sheet->getColumnDimension('A')->setWidth(10);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(40);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(25);
    
    // Ajuster la hauteur des lignes
    $sheet->getDefaultRowDimension()->setRowHeight(20);
    
    // Centrer les colonnes A, B et D
    $sheet->getStyle('A9:A' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('B9:B' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D9:D' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Générer le nom du fichier
    $fileName = 'Palmares_' . preg_replace('/[^a-zA-Z0-9]/', '_', $palmares['promotion']) . '_' . 
                preg_replace('/[^a-zA-Z0-9]/', '_', $palmares['annee_academique']) . '_' . 
                date('Y-m-d') . '.xlsx';
    
    // Configurer les en-têtes pour le téléchargement
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    
    // Créer l'objet Writer pour sauvegarder le document
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    
} catch (Exception $e) {
    $_SESSION['error'] = "Erreur lors de l'exportation: " . $e->getMessage();
    header('Location: ../index.php?view=academique/voir_palmares_archive&id=' . $idPalmares);
    exit;
}