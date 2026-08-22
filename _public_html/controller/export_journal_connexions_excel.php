<?php
require_once '../config/Connexion.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Vérifier l'authentification
session_start();
if (!isset($_SESSION['id'])) {
    http_response_code(403);
    exit('Accès refusé');
}

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Construire la requête avec les mêmes filtres que la vue
    $sql = "SELECT u.\"idUser\", u.\"nomUser\", ual.* FROM user_activity_log ual 
            LEFT JOIN t_users u ON ual.user_id = u.\"idUser\" 
            WHERE 1=1";
    $parametres = [];

    if (!empty($_GET['recherche'])) {
        $sql .= " AND (ual.description LIKE ? OR u.nomUser LIKE ? OR ual.ip_address LIKE ?)";
        $recherche = '%' . $_GET['recherche'] . '%';
        $parametres[] = $recherche;
        $parametres[] = $recherche;
        $parametres[] = $recherche;
    }

    if (!empty($_GET['date_debut'])) {
        $sql .= " AND DATE(ual.created_at) >= ?";
        $parametres[] = $_GET['date_debut'];
    }

    if (!empty($_GET['date_fin'])) {
        $sql .= " AND DATE(ual.created_at) <= ?";
        $parametres[] = $_GET['date_fin'];
    }

    $sql .= " ORDER BY ual.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($parametres);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Créer un nouveau spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Journal Connexions');

    // Définir les en-têtes
    $headers = ['#', 'Date/Heure', 'Utilisateur', 'Type d\'Action', 'Description', 'Adresse IP', 'User-Agent'];
    $sheet->fromArray($headers, NULL, 'A1');

    // Style des en-têtes
    $sheet->getStyle('A1:G1')->getFont()->setBold(true);
    $sheet->getStyle('A1:G1')->getFont()->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle('A1:G1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF4472C4');
    $sheet->getStyle('A1:G1')->getAlignment()->setHorizontal('center')->setVertical('center');

    // Remplir les données
    $row = 2;
    $numero = 1;
    
    if (!empty($logs)) {
        foreach ($logs as $log) {
            $sheet->setCellValue('A' . $row, $numero);
            $sheet->setCellValue('B' . $row, date('d/m/Y H:i:s', strtotime($log['created_at'])));
            $sheet->setCellValue('C' . $row, $log['nomUser'] ?? '-');
            $sheet->setCellValue('D' . $row, ucfirst($log['action_type']));
            $sheet->setCellValue('E' . $row, substr($log['description'] ?? '-', 0, 200));
            $sheet->setCellValue('F' . $row, $log['ip_address']);
            $sheet->setCellValue('G' . $row, substr($log['user_agent'] ?? '-', 0, 100));
            
            $row++;
            $numero++;
        }
    }

    // Ajuster les largeurs des colonnes
    $sheet->getColumnDimension('A')->setWidth(5);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(15);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(40);
    $sheet->getColumnDimension('F')->setWidth(18);
    $sheet->getColumnDimension('G')->setWidth(50);

    // Générer le fichier
    $writer = new Xlsx($spreadsheet);
    $filename = 'Journal_Connexions_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo "Erreur lors de l'export: " . htmlspecialchars($e->getMessage());
    exit;
}
?>
