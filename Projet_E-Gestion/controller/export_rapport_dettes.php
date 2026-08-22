<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/assets/html2pdf/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;

// Vérifier l'authentification
if (!isset($_SESSION['id']) || ($_SESSION['idRole'] != 1 && $_SESSION['idRole'] != 2)) {
    die('Accès non autorisé');
}

// Récupérer les paramètres
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$typeRapport = isset($_GET['type']) ? $_GET['type'] : 'general';

if (!$promotionId || !$anneeId) {
    die('Paramètres manquants');
}

// Connexion à la base de données
$db = Connexion::getInstance()->getPDO();

// Récupérer les informations de la promotion et de l'année
$promotion = [];
$annee = [];

try {
    $sql = "SELECT idpromotion, designationPromotion FROM promotion WHERE idpromotion = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $promotionId]);
    $promotion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $sql = "SELECT idannee_acad, designation FROM annee_acad WHERE idannee_acad = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $anneeId]);
    $annee = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur lors de la récupération des informations');
}

// Récupérer les statistiques
$stats = [];
try {
    $sql = "SELECT 
                COUNT(DISTINCT d.matricule) as total_etudiants,
                SUM(d.credits_ecue) as total_credits,
                SUM(CASE WHEN d.statut = 'Validée' THEN 1 ELSE 0 END) as dettes_validees,
                SUM(CASE WHEN d.statut = 'En cours' THEN 1 ELSE 0 END) as dettes_en_cours,
                ROUND(AVG(d.credits_ecue), 1) as moyenne_credits,
                ROUND((SUM(CASE WHEN d.statut = 'Validée' THEN 1 ELSE 0 END) * 100.0) / COUNT(*), 1) as taux_validation
            FROM dette_etudiant d
            WHERE d.promotion_idpromotion = :promotion
            AND d.annee_acad_idannee_acad = :annee";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':promotion' => $promotionId,
        ':annee' => $anneeId
    ]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur calcul statistiques: " . $e->getMessage());
}

// Récupérer les données du rapport selon le type
$rapportData = [];
try {
    switch ($typeRapport) {
        case 'general':
            $sql = "SELECT 
                        'Total étudiants avec dettes' as indicateur,
                        COUNT(DISTINCT d.matricule) as valeur,
                        100 as pourcentage
                    FROM dette_etudiant d
                    WHERE d.promotion_idpromotion = :promotion
                    AND d.annee_acad_idannee_acad = :annee
                    
                    UNION ALL
                    
                    SELECT 
                        'Total crédits en dette' as indicateur,
                        SUM(d.credits_ecue) as valeur,
                        100 as pourcentage
                    FROM dette_etudiant d
                    WHERE d.promotion_idpromotion = :promotion
                    AND d.annee_acad_idannee_acad = :annee
                    
                    UNION ALL
                    
                    SELECT 
                        'Dettes validées' as indicateur,
                        COUNT(*) as valeur,
                        ROUND((COUNT(*) * 100.0) / (SELECT COUNT(*) FROM dette_etudiant WHERE promotion_idpromotion = :promotion2 AND annee_acad_idannee_acad = :annee2), 1) as pourcentage
                    FROM dette_etudiant d
                    WHERE d.promotion_idpromotion = :promotion3
                    AND d.annee_acad_idannee_acad = :annee3
                    AND d.statut = 'Validée'
                    
                    UNION ALL
                    
                    SELECT 
                        'Dettes en cours' as indicateur,
                        COUNT(*) as valeur,
                        ROUND((COUNT(*) * 100.0) / (SELECT COUNT(*) FROM dette_etudiant WHERE promotion_idpromotion = :promotion4 AND annee_acad_idannee_acad = :annee4), 1) as pourcentage
                    FROM dette_etudiant d
                    WHERE d.promotion_idpromotion = :promotion5
                    AND d.annee_acad_idannee_acad = :annee5
                    AND d.statut = 'En cours'";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':promotion' => $promotionId,
                ':annee' => $anneeId,
                ':promotion2' => $promotionId,
                ':annee2' => $anneeId,
                ':promotion3' => $promotionId,
                ':annee3' => $anneeId,
                ':promotion4' => $promotionId,
                ':annee4' => $anneeId,
                ':promotion5' => $promotionId,
                ':annee5' => $anneeId
            ]);
            $rapportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'par_ue':
            $sql = "SELECT 
                        ec.codeECUE as code_ue,
                        ec.designationECUE as designation,
                        s.numeroSemestre as semestre,
                        COUNT(DISTINCT d.matricule) as nombre_etudiants,
                        SUM(d.credits_ecue) as total_credits,
                        SUM(CASE WHEN d.statut = 'Validée' THEN 1 ELSE 0 END) as nombre_validees,
                        ROUND((SUM(CASE WHEN d.statut = 'Validée' THEN 1 ELSE 0 END) * 100.0) / COUNT(*), 1) as taux_validation
                    FROM dette_etudiant d
                    INNER JOIN ecue ec ON d.ECUE_idECUE = ec.idECUE
                    INNER JOIN semestre s ON d.semestre_idsemestre = s.idsemestre
                    WHERE d.promotion_idpromotion = :promotion
                    AND d.annee_acad_idannee_acad = :annee
                    GROUP BY ec.idECUE, s.numeroSemestre
                    ORDER BY s.numeroSemestre, ec.codeECUE";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':promotion' => $promotionId,
                ':annee' => $anneeId
            ]);
            $rapportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'par_semestre':
            $sql = "SELECT 
                        CONCAT('Semestre ', s.numeroSemestre) as designation,
                        COUNT(DISTINCT d.matricule) as nombre_etudiants,
                        SUM(d.credits_ecue) as total_credits,
                        SUM(CASE WHEN d.statut = 'Validée' THEN 1 ELSE 0 END) as dettes_validees,
                        ROUND((SUM(CASE WHEN d.statut = 'Validée' THEN 1 ELSE 0 END) * 100.0) / COUNT(*), 1) as taux_validation
                    FROM dette_etudiant d
                    INNER JOIN semestre s ON d.semestre_idsemestre = s.idsemestre
                    WHERE d.promotion_idpromotion = :promotion
                    AND d.annee_acad_idannee_acad = :annee
                    GROUP BY s.idsemestre, s.numeroSemestre
                    ORDER BY s.numeroSemestre";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':promotion' => $promotionId,
                ':annee' => $anneeId
            ]);
            $rapportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
} catch (PDOException $e) {
    error_log("Erreur récupération données rapport: " . $e->getMessage());
}

// Initialiser l'objet Universite et récupérer la configuration
$universite = new Universite();
$configUniversite = $universite->getConfigurationUniversite();

// Récupérer le logo en base64
$logoBase64 = '';
if (!empty($configUniversite['logo'])) {
    $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
    if (file_exists($logoPath)) {
        $logoType = pathinfo($logoPath, PATHINFO_EXTENSION);
        $logoData = file_get_contents($logoPath);
        $logoBase64 = 'data:image/' . $logoType . ';base64,' . base64_encode($logoData);
    }
}

if ($format == 'excel') {
    // Créer un nouveau spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // En-tête du document
    $row = 1;
    $sheet->setCellValue('A' . $row, $configUniversite['nom'] ?? 'UNIVERSITÉ');
    $sheet->mergeCells('A' . $row . ':G' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $row++;
    $sheet->setCellValue('A' . $row, 'RAPPORT DES DETTES ACADÉMIQUES');
    $sheet->mergeCells('A' . $row . ':G' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $row += 2;
    $sheet->setCellValue('A' . $row, 'Promotion: ' . $promotion['designationPromotion']);
    $sheet->setCellValue('D' . $row, 'Année académique: ' . $annee['designation']);
    
    $row += 2;
    
    // Statistiques générales
    $sheet->setCellValue('A' . $row, 'STATISTIQUES GÉNÉRALES');
    $sheet->mergeCells('A' . $row . ':D' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $sheet->getStyle('A' . $row . ':D' . $row)->getFill()
          ->setFillType(Fill::FILL_SOLID)
          ->getStartColor()->setARGB('FFE0E0E0');
    
    $row++;
    $sheet->setCellValue('A' . $row, 'Total étudiants avec dettes');
    $sheet->setCellValue('B' . $row, $stats['total_etudiants'] ?? 0);
    $sheet->setCellValue('C' . $row, 'Total crédits en dette');
    $sheet->setCellValue('D' . $row, $stats['total_credits'] ?? 0);
    
    $row++;
    $sheet->setCellValue('A' . $row, 'Dettes en cours');
    $sheet->setCellValue('B' . $row, $stats['dettes_en_cours'] ?? 0);
    $sheet->setCellValue('C' . $row, 'Dettes validées');
    $sheet->setCellValue('D' . $row, $stats['dettes_validees'] ?? 0);
    
    $row += 2;
    
    // Données du rapport selon le type
    if ($typeRapport == 'general') {
        $sheet->setCellValue('A' . $row, 'RAPPORT GÉNÉRAL');
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':C' . $row)->getFill()
              ->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setARGB('FFE0E0E0');
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Indicateur');
        $sheet->setCellValue('B' . $row, 'Valeur');
        $sheet->setCellValue('C' . $row, 'Pourcentage');
        $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
        
        foreach ($rapportData as $ligne) {
            $row++;
            $sheet->setCellValue('A' . $row, $ligne['indicateur']);
            $sheet->setCellValue('B' . $row, $ligne['valeur']);
            $sheet->setCellValue('C' . $row, number_format($ligne['pourcentage'], 1) . '%');
        }
        
    } elseif ($typeRapport == 'par_ue') {
        $sheet->setCellValue('A' . $row, 'RAPPORT PAR UNITÉ D\'ENSEIGNEMENT');
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':G' . $row)->getFill()
              ->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setARGB('FFE0E0E0');
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Code UE');
        $sheet->setCellValue('B' . $row, 'Désignation');
        $sheet->setCellValue('C' . $row, 'Semestre');
        $sheet->setCellValue('D' . $row, 'Étudiants');
        $sheet->setCellValue('E' . $row, 'Crédits');
        $sheet->setCellValue('F' . $row, 'Validées');
        $sheet->setCellValue('G' . $row, 'Taux');
        $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
        
        foreach ($rapportData as $ue) {
            $row++;
            $sheet->setCellValue('A' . $row, $ue['code_ue']);
            $sheet->setCellValue('B' . $row, $ue['designation']);
            $sheet->setCellValue('C' . $row, $ue['semestre']);
            $sheet->setCellValue('D' . $row, $ue['nombre_etudiants']);
            $sheet->setCellValue('E' . $row, $ue['total_credits']);
            $sheet->setCellValue('F' . $row, $ue['nombre_validees']);
            $sheet->setCellValue('G' . $row, number_format($ue['taux_validation'], 1) . '%');
        }
        
    } elseif ($typeRapport == 'par_semestre') {
        $sheet->setCellValue('A' . $row, 'RAPPORT PAR SEMESTRE');
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':E' . $row)->getFill()
              ->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setARGB('FFE0E0E0');
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Semestre');
        $sheet->setCellValue('B' . $row, 'Étudiants');
        $sheet->setCellValue('C' . $row, 'Total crédits');
        $sheet->setCellValue('D' . $row, 'Dettes validées');
        $sheet->setCellValue('E' . $row, 'Taux validation');
        $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
        
        foreach ($rapportData as $semestre) {
            $row++;
            $sheet->setCellValue('A' . $row, $semestre['designation']);
            $sheet->setCellValue('B' . $row, $semestre['nombre_etudiants']);
            $sheet->setCellValue('C' . $row, $semestre['total_credits']);
            $sheet->setCellValue('D' . $row, $semestre['dettes_validees']);
            $sheet->setCellValue('E' . $row, number_format($semestre['taux_validation'], 1) . '%');
        }
    }
    
    // Ajuster la largeur des colonnes
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Ajouter les bordures
    $lastRow = $row;
    $sheet->getStyle('A1:G' . $lastRow)->getBorders()->getAllBorders()
          ->setBorderStyle(Border::BORDER_THIN);
    
    // Créer le writer et envoyer le fichier
    $writer = new Xlsx($spreadsheet);
    $filename = 'Rapport_Dettes_' . date('Ymd_His') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit;
    
} else {
    // Format PDF
    $html = '
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Rapport des dettes - ' . htmlspecialchars($promotion['designationPromotion']) . '</title>
        <style>
            @page {
                margin: 15mm 10mm 15mm 10mm;
            }
            body {
                font-family: Arial, sans-serif;
                font-size: 8pt;
                line-height: 1.3;
                color: #333;
                margin: 0;
                padding: 0;
            }
            .header {
                text-align: center;
                margin-bottom: 15px;
            }
            .institution-header {
                margin-bottom: 10px;
                border-bottom: 2px solid #337ab7;
                padding-bottom: 8px;
            }
            .logo {
                float: left;
                max-width: 60px;
                max-height: 60px;
                margin-right: 10px;
            }
            .institution-info {
                margin-left: 70px;
            }
            h1 {
                font-size: 14pt;
                margin: 8px 0;
                color: #337ab7;
                text-transform: uppercase;
            }
            h2 {
                font-size: 12pt;
                margin: 6px 0;
                color: #555;
            }
            h3 {
                font-size: 10pt;
                margin: 10px 0 8px 0;
                color: #337ab7;
                border-bottom: 1px solid #337ab7;
                padding-bottom: 3px;
            }
            h4 {
                font-size: 9pt;
                margin: 5px 0;
            }
            .info-box {
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 3px;
                padding: 8px;
                margin: 10px 0;
                font-size: 8pt;
            }
            .info-box p {
                margin: 3px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 10px 0;
                font-size: 7pt;
                table-layout: fixed;
            }
            th, td {
                border: 1px solid #dee2e6;
                padding: 4px 6px;
                text-align: left;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            th {
                background-color: #337ab7;
                color: white;
                font-weight: bold;
                text-align: center;
                font-size: 7pt;
            }
            tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            .text-center {
                text-align: center;
            }
            .text-right {
                text-align: right;
            }
            .badge {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 7pt;
                font-weight: bold;
            }
            .bg-success {
                background-color: #28a745;
                color: white;
            }
            .bg-warning {
                background-color: #ffc107;
                color: #212529;
            }
            .bg-danger {
                background-color: #dc3545;
                color: white;
            }
            .bg-info {
                background-color: #17a2b8;
                color: white;
            }
            .stats-box {
                background-color: #e7f3ff;
                border: 1px solid #337ab7;
                border-radius: 3px;
                padding: 10px;
                margin: 15px 0;
                font-size: 8pt;
            }
            .stats-grid {
                display: table;
                width: 100%;
                margin: 5px 0;
            }
            .stats-item {
                display: table-cell;
                width: 50%;
                padding: 3px;
                font-size: 8pt;
            }
            .footer {
                margin-top: 30px;
                padding-top: 15px;
                border-top: 1px solid #dee2e6;
                text-align: center;
                font-size: 7pt;
                color: #666;
            }
            .signature-section {
                margin-top: 40px;
                text-align: right;
                font-size: 8pt;
            }
            .page-break {
                page-break-after: always;
            }
            /* Styles spécifiques pour éviter les débordements */
            .compact-table {
                font-size: 7pt;
            }
            .compact-table th,
            .compact-table td {
                padding: 3px 4px;
            }
            .designation-cell {
                font-size: 7pt;
                line-height: 1.2;
            }
        </style>
    </head>
    <body>
        <div class="institution-header">
            ' . (!empty($logoBase64) ? '<img src="' . $logoBase64 . '" class="logo" alt="Logo">' : '') . '
            <div class="institution-info">
                <div style="font-size: 9pt; color: #666;">' . htmlspecialchars($configUniversite['ministere_tutelle'] ?? 'MINISTÈRE DE TUTELLE') . '</div>
                <div style="font-size: 12pt; font-weight: bold; color: #337ab7;">' . htmlspecialchars($configUniversite['nom'] ?? 'UNIVERSITÉ') . '</div>
                <div style="font-size: 7pt; color: #666;">
                    ' . (!empty($configUniversite['telephone']) ? 'Tél: ' . htmlspecialchars($configUniversite['telephone']) : '') . 
                    (!empty($configUniversite['telephone']) && !empty($configUniversite['email']) ? ' | ' : '') .
                    (!empty($configUniversite['email']) ? 'Email: ' . htmlspecialchars($configUniversite['email']) : '') . '
                </div>
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <div class="header">
            <h1>RAPPORT DES DETTES ACADÉMIQUES</h1>
            <h2>' . strtoupper(str_replace('_', ' ', $typeRapport)) . '</h2>
        </div>
        
        <div class="info-box">
            <p><strong>Promotion:</strong> ' . htmlspecialchars($promotion['designationPromotion']) . '</p>
            <p><strong>Année académique:</strong> ' . htmlspecialchars($annee['designation']) . '</p>
            <p><strong>Date du rapport:</strong> ' . date('d/m/Y à H:i') . '</p>
            <p><strong>Type de rapport:</strong> ' . ucfirst(str_replace('_', ' ', $typeRapport)) . '</p>
        </div>
        
        <div class="stats-box">
            <h3>STATISTIQUES GÉNÉRALES</h3>
            <div class="stats-grid">
                <div class="stats-item">
                    <strong>Total étudiants avec dettes:</strong> 
                    <span class="badge bg-info">' . ($stats['total_etudiants'] ?? 0) . '</span>
                </div>
                <div class="stats-item">
                    <strong>Total crédits en dette:</strong> 
                    <span class="badge bg-info">' . ($stats['total_credits'] ?? 0) . '</span>
                </div>
            </div>
            <div class="stats-grid">
                <div class="stats-item">
                    <strong>Dettes en cours:</strong> 
                    <span class="badge bg-warning">' . ($stats['dettes_en_cours'] ?? 0) . '</span>
                </div>
                <div class="stats-item">
                    <strong>Dettes validées:</strong> 
                    <span class="badge bg-success">' . ($stats['dettes_validees'] ?? 0) . '</span>
                </div>
            </div>
            <div class="stats-grid">
                <div class="stats-item">
                    <strong>Moyenne crédits par étudiant:</strong> 
                    <span class="badge bg-info">' . number_format($stats['moyenne_credits'] ?? 0, 1) . '</span>
                </div>
                <div class="stats-item">
                    <strong>Taux de validation:</strong> 
                    <span class="badge ' . (($stats['taux_validation'] ?? 0) >= 50 ? 'bg-success' : 'bg-danger') . '">' . 
                    number_format($stats['taux_validation'] ?? 0, 1) . '%</span>
                </div>
            </div>
        </div>';
    
    // Ajouter les données du rapport selon le type
    if ($typeRapport == 'general') {
        $html .= '<h3>DÉTAIL DU RAPPORT GÉNÉRAL</h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 50%;">Indicateur</th>
                    <th style="width: 25%;">Valeur</th>
                    <th style="width: 25%;">Pourcentage</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($rapportData as $ligne) {
            $pourcentage = $ligne['pourcentage'] ?? 0;
            $badgeClass = $pourcentage >= 75 ? 'bg-success' : ($pourcentage >= 50 ? 'bg-warning' : 'bg-danger');
            
            $html .= '<tr>
                <td><strong>' . htmlspecialchars($ligne['indicateur']) . '</strong></td>
                <td class="text-center">' . htmlspecialchars($ligne['valeur']) . '</td>
                <td class="text-center">
                    <span class="badge ' . $badgeClass . '">' . number_format($pourcentage, 1) . '%</span>
                </td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        
    } elseif ($typeRapport == 'par_ue') {
        $html .= '<h3>RAPPORT PAR UNITÉ D\'ENSEIGNEMENT</h3>
        <table class="compact-table">
            <thead>
                <tr>
                    <th style="width: 12%;">Code</th>
                    <th style="width: 30%;">Désignation</th>
                    <th style="width: 8%;">Sem.</th>
                    <th style="width: 12%;">Étud.</th>
                    <th style="width: 10%;">Créd.</th>
                    <th style="width: 10%;">Valid.</th>
                    <th style="width: 18%;">Taux</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($rapportData as $ue) {
            $taux = $ue['taux_validation'] ?? 0;
            $badgeClass = $taux >= 50 ? 'bg-success' : 'bg-danger';
            
            // Tronquer la désignation si elle est trop longue
            $designation = $ue['designation'];
            if (strlen($designation) > 40) {
                $designation = substr($designation, 0, 37) . '...';
            }
            
            $html .= '<tr>
                <td style="font-size: 7pt;"><strong>' . htmlspecialchars($ue['code_ue']) . '</strong></td>
                <td class="designation-cell">' . htmlspecialchars($designation) . '</td>
                <td class="text-center">S' . htmlspecialchars($ue['semestre']) . '</td>
                <td class="text-center">' . $ue['nombre_etudiants'] . '</td>
                <td class="text-center">' . $ue['total_credits'] . '</td>
                <td class="text-center">' . $ue['nombre_validees'] . '</td>
                <td class="text-center">
                    <span class="badge ' . $badgeClass . '">' . number_format($taux, 1) . '%</span>
                </td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        
    } elseif ($typeRapport == 'par_semestre') {
        $html .= '<h3>RAPPORT PAR SEMESTRE</h3>
        <table class="compact-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Semestre</th>
                    <th style="width: 20%;">Étudiants</th>
                    <th style="width: 20%;">Crédits</th>
                    <th style="width: 20%;">Validées</th>
                    <th style="width: 15%;">Taux</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($rapportData as $semestre) {
            $taux = $semestre['taux_validation'] ?? 0;
            $tauxClass = $taux >= 50 ? 'bg-success' : 'bg-danger';
            
            $html .= '<tr>
                <td><strong>' . htmlspecialchars($semestre['designation']) . '</strong></td>
                <td class="text-center">' . $semestre['nombre_etudiants'] . '</td>
                <td class="text-center">' . $semestre['total_credits'] . '</td>
                <td class="text-center">' . $semestre['dettes_validees'] . '</td>
                <td class="text-center">
                    <span class="badge ' . $tauxClass . '">' . number_format($taux, 1) . '%</span>
                </td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
    }
    
    // Section signature
    $html .= '
        <div class="signature-section">
            <p>Le Chef de la Cellule LMD</p>
            <p style="margin-top: 40px;">_______________________</p>
        </div>';
    
    // Footer
    $html .= '
        <div class="footer">
            <p>Document généré le ' . date('d/m/Y à H:i:s') . ' par ' . htmlspecialchars($_SESSION['nom'] ?? 'Utilisateur') . '</p>
            <p>Ce document est certifié conforme aux données du système de gestion académique.</p>
        </div>
    </body>
    </html>';
    
    try {
        // Configuration optimisée pour éviter les débordements
        $html2pdf = new Html2Pdf('P', 'A4', 'fr', true, 'UTF-8', array(15, 10, 15, 10));
        $html2pdf->pdf->SetDisplayMode('fullpage');
        $html2pdf->setDefaultFont('Arial');
        $html2pdf->writeHTML($html);
        
        $filename = 'Rapport_Dettes_' . date('Ymd_His') . '.pdf';
        $html2pdf->output($filename);
        
    } catch (Html2PdfException $e) {
        die('Erreur lors de la génération du PDF : ' . $e->getMessage());
    }
}
?>