<?php
session_start();
error_reporting(E_ALL); ini_set("display_errors", 1);
require_once dirname(__DIR__) . '/config/Connexion.php';
require dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

try {
    // Récupération des fournisseurs
    $query = "SELECT f.*, cc.numero_compte, cc.intitule_compte 
              FROM fournisseur f
              LEFT JOIN compte_comptable cc ON f.id_compte_comptable = cc.id_compte
              ORDER BY f.nom_fournisseur";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Création du fichier Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Liste des fournisseurs');
    
    // Style pour les en-têtes
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4F81BD'],
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
    
    // En-têtes
    $headers = [
        'Code', 'Nom', 'Adresse', 'Téléphone', 'Email', 'NIF', 'RCCM', 
        'Compte Comptable', 'Délai Paiement (jours)', 'Statut'
    ];
    
    foreach (range('A', 'J') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }
    
    // Application des en-têtes
    $sheet->fromArray([$headers], NULL, 'A1');
    $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);
    
    // Remplissage des données
    $row = 2;
    foreach ($fournisseurs as $fournisseur) {
        $compteComptable = $fournisseur['numero_compte'] . ' - ' . $fournisseur['intitule_compte'];
        $statut = $fournisseur['actif'] ? 'Actif' : 'Inactif';
        
        $sheet->setCellValue('A' . $row, $fournisseur['code_fournisseur']);
        $sheet->setCellValue('B' . $row, $fournisseur['nom_fournisseur']);
        $sheet->setCellValue('C' . $row, $fournisseur['adresse']);
        $sheet->setCellValue('D' . $row, $fournisseur['telephone']);
        $sheet->setCellValue('E' . $row, $fournisseur['email']);
        $sheet->setCellValue('F' . $row, $fournisseur['nif']);
        $sheet->setCellValue('G' . $row, $fournisseur['rccm']);
        $sheet->setCellValue('H' . $row, $compteComptable);
        $sheet->setCellValue('I' . $row, $fournisseur['delai_paiement']);
        $sheet->setCellValue('J' . $row, $statut);
        
        $row++;
    }
    
    // Style pour toutes les cellules de données
    $sheet->getStyle('A2:J' . ($row - 1))->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ]);
    
    // Création du fichier
    $filename = 'liste_fournisseurs_' . date('YmdHis') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
} catch (Exception $e) {
    echo "Une erreur est survenue lors de l'exportation : " . $e->getMessage();
    exit;
}
