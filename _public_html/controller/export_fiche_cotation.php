<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Vérification de l'authentification
if (!isset($_SESSION['id'])) {
    header("Location: ../login");
    exit;
}

// Récupérer les paramètres
$annee_acad = $_GET['annee_acad'] ?? null;
$specialisation = $_GET['specialisation'] ?? null;
$cycle = $_GET['cycle'] ?? null;
$jury = $_GET['jury'] ?? null;
$promotion = $_GET['promotion'] ?? null;

if (empty($annee_acad)) {
    die("Année académique requise");
}

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    // Récupérer la désignation de l'année académique
    $stmtAnnee = $pdo->prepare("SELECT designation FROM annee_acad WHERE idannee_acad = ?");
    $stmtAnnee->execute([$annee_acad]);
    $anneeInfo = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
    $anneeDesignation = $anneeInfo['designation'] ?? $annee_acad;
    
    // Construire la requête SQL
    $sql = "
        SELECT 
            e.matricule,
            e.noms AS nom_etudiant,
            sj.intitule AS titre_memoire,
            ad.noms AS directeur,
            sp.designation AS specialisation,
            sj.cycle,
            so.date_soutenance,
            so.note_finale,
            j.designation AS jury_designation,
            p.designationPromotion AS promotion_designation
        FROM soutenance so
        INNER JOIN sujets sj ON so.sujets_idsujets = sj.idsujets
        INNER JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
        LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
        LEFT JOIN specialisation sp ON sj.idSpecialisation = sp.idSpecialisation
        LEFT JOIN agent ad ON sj.idDirecteur = ad.idAgent
        LEFT JOIN jury j ON so.jury_id = j.idjury
        WHERE so.annee_acad_idannee_acad = :annee_acad
    ";
    
    $params = ['annee_acad' => $annee_acad];
    
    // Filtre par spécialisation
    if (!empty($specialisation)) {
        $sql .= " AND sj.idSpecialisation = :specialisation";
        $params['specialisation'] = $specialisation;
    }
    
    // Filtre par cycle
    if (!empty($cycle)) {
        $sql .= " AND sj.cycle = :cycle";
        $params['cycle'] = $cycle;
    }
    
    // Filtre par jury
    if (!empty($jury)) {
        $sql .= " AND so.jury_id = :jury";
        $params['jury'] = $jury;
    }
    
    // Filtre par promotion
    if (!empty($promotion)) {
        $sql .= " AND e.promotion_idpromotion = :promotion";
        $params['promotion'] = $promotion;
    }
    
    $sql .= " ORDER BY sp.designation, e.noms";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $soutenances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Créer le fichier Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Fiche de cotation');
    
    // En-têtes
    $headers = [
        'A' => 'N°',
        'B' => 'Matricule',
        'C' => 'Nom Étudiant',
        'D' => 'Titre Mémoire',
        'E' => 'Directeur',
        'F' => 'Spécialisation',
        'G' => 'Promotion',
        'H' => 'Cycle',
        'I' => 'Jury',
        'J' => 'Date Soutenance',
        'K' => 'Note (/20)'
    ];
    
    foreach ($headers as $col => $header) {
        $sheet->setCellValue($col . '1', $header);
    }
    
    // Style des en-têtes
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4472C4']
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];
    
    $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);
    
    // Données
    $row = 2;
    foreach ($soutenances as $index => $soutenance) {
        $sheet->setCellValue('A' . $row, $index + 1);
        $sheet->setCellValue('B' . $row, $soutenance['matricule']);
        $sheet->setCellValue('C' . $row, $soutenance['nom_etudiant']);
        $sheet->setCellValue('D' . $row, $soutenance['titre_memoire']);
        $sheet->setCellValue('E' . $row, $soutenance['directeur']);
        $sheet->setCellValue('F' . $row, $soutenance['specialisation']);
        $sheet->setCellValue('G' . $row, $soutenance['promotion_designation']);
        $sheet->setCellValue('H' . $row, $soutenance['cycle']);
        $sheet->setCellValue('I' . $row, $soutenance['jury_designation']);
        
        // Date de soutenance formatée
        $dateSoutenance = $soutenance['date_soutenance'] 
            ? date('d/m/Y', strtotime($soutenance['date_soutenance'])) 
            : '';
        $sheet->setCellValue('J' . $row, $dateSoutenance);
        
        // Note - laisser vide si non encodée (pour remplissage manuel)
        $note = $soutenance['note_finale'] !== null ? $soutenance['note_finale'] : '';
        $sheet->setCellValue('K' . $row, $note);
        
        $row++;
    }
    
    // Style des données
    $dataStyle = [
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ],
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];
    
    if ($row > 2) {
        $sheet->getStyle('A2:K' . ($row - 1))->applyFromArray($dataStyle);
    }
    
    // Centrer certaines colonnes
    $sheet->getStyle('A2:B' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('H2:K' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Auto-dimensionner les colonnes
    foreach (range('A', 'K') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Limiter la largeur de la colonne titre
    $sheet->getColumnDimension('D')->setAutoSize(false);
    $sheet->getColumnDimension('D')->setWidth(40);
    $sheet->getStyle('D2:D' . ($row - 1))->getAlignment()->setWrapText(true);
    
    // Nom du fichier
    $anneeClean = preg_replace('/[^a-zA-Z0-9\-]/', '_', $anneeDesignation);
    $fileName = 'fiche_cotation_' . $anneeClean . '_' . date('Y-m-d') . '.xlsx';
    
    // Envoyer le fichier
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    
} catch (PDOException $e) {
    error_log("Erreur export_fiche_cotation (PDO): " . $e->getMessage());
    die("Erreur lors de l'export: " . $e->getMessage());
} catch (Exception $e) {
    error_log("Erreur export_fiche_cotation: " . $e->getMessage());
    die("Erreur lors de la génération du fichier Excel: " . $e->getMessage());
}
