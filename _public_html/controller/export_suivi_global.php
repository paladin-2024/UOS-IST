<?php
session_start();
require_once dirname(__DIR__).'/config/Connexion.php';
require_once dirname(__DIR__).'/vendor/autoload.php'; // Pour PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

// Récupérer les paramètres
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$semestreId = isset($_GET['semestre']) ? intval($_GET['semestre']) : 0;

if ($promotionId <= 0) {
    die('Promotion non spécifiée');
}

$pdo = Connexion::getInstance()->getPDO();

// Récupérer l'année académique en cours
$checkColumn = "SHOW COLUMNS FROM annee_acad LIKE 'est_active'";
$stmtCheck = $pdo->prepare($checkColumn);
$stmtCheck->execute();
$columnExists = $stmtCheck->fetch();

if ($columnExists) {
    $queryAnnee = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
} else {
    $queryAnnee = "SELECT * FROM annee_acad ORDER BY dateCreation DESC LIMIT 1";
}

$stmtAnnee = $pdo->prepare($queryAnnee);
$stmtAnnee->execute();
$currentYear = $stmtAnnee->fetch(PDO::FETCH_ASSOC);

// Récupérer les informations de la promotion
$queryPromo = "SELECT p.*, o.designationOrientation, s.designationSection 
               FROM promotion p
               JOIN orientation o ON p.orientation_idorientation = o.idorientation
               JOIN section s ON o.section_idsection = s.idsection
               WHERE p.idpromotion = :promotionId";
$stmtPromo = $pdo->prepare($queryPromo);
$stmtPromo->bindParam(':promotionId', $promotionId);
$stmtPromo->execute();
$promotion = $stmtPromo->fetch(PDO::FETCH_ASSOC);

// Vérifier les permissions de l'utilisateur
$currentUserId = $_SESSION['id'];
$hasFullAccess = $_SESSION['idRole'] == 1;
$userSections = [];

if (!$hasFullAccess) {
    // Récupérer les sections dont l'utilisateur est responsable
    $query = "SELECT section_idsection 
              FROM responsable_section 
              WHERE idUser = :userId 
              AND annee_acad_idannee_acad = :anneeId";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':userId', $currentUserId);
    $stmt->bindParam(':anneeId', $currentYear['idannee_acad']);
    $stmt->execute();
    $userSections = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Fonction pour calculer les statistiques (même logique que dans la page principale)
function getStatistiquesAvancement($pdo, $promotionId, $semestreId, $anneeAcadId, $userSections = []) {
    // Vérifier d'abord que l'utilisateur a accès à cette promotion
    if (!empty($userSections)) {
        $checkParams = [':promotionId' => $promotionId];
        $checkQuery = "SELECT COUNT(*) 
                      FROM promotion p
                      JOIN orientation o ON p.orientation_idorientation = o.idorientation
                      WHERE p.idpromotion = :promotionId";
        
        $placeholders = [];
        foreach ($userSections as $i => $section) {
            $paramName = ":section{$i}";
            $placeholders[] = $paramName;
            $checkParams[$paramName] = $section;
        }
        $checkQuery .= " AND o.section_idsection IN (" . implode(',', $placeholders) . ")";
        
        $stmt = $pdo->prepare($checkQuery);
        foreach ($checkParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            // L'utilisateur n'a pas accès à cette promotion
            die('Accès refusé à cette promotion');
        }
    }
    
    // Paramètres pour la requête principale
    $params = [
        ':promotionId' => $promotionId
    ];
    
    $query = "SELECT DISTINCT 
              e.idECUE,
              e.designationECUE,
              e.CMI as volumeHoraireCM,
              e.TD as volumeHoraireTD,
              e.TP as volumeHoraireTP,
              u.designationUE,
              s.numeroSemestre,
              s.idsemestre
              FROM ecue e
              JOIN ue u ON e.UE_idUE = u.idUE
              JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
              WHERE s.promotion_idpromotion = :promotionId
              AND e.estVisible = 1";
    
    if ($semestreId) {
        $query .= " AND s.idsemestre = :semestreId";
        $params[':semestreId'] = $semestreId;
    }
    
    $query .= " ORDER BY s.numeroSemestre, u.designationUE, e.designationECUE";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $ecues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $statistiques = [];
    $totauxGlobaux = [
        'CM' => ['prevu' => 0, 'realise' => 0],
        'TD' => ['prevu' => 0, 'realise' => 0],
        'TP' => ['prevu' => 0, 'realise' => 0],
        'total' => ['prevu' => 0, 'realise' => 0]
    ];
    
    foreach ($ecues as $ecue) {
        $queryRealise = "SELECT 
                        type_cours,
                        SUM(TIMESTAMPDIFF(HOUR, heure_debut, heure_fin)) as heures_realisees
                        FROM suivi_enseignements
                        WHERE idECUE = :ecueId
                        AND annee_acad_idannee_acad = :anneeAcadId
                        GROUP BY type_cours";
        
        $stmtRealise = $pdo->prepare($queryRealise);
        $stmtRealise->bindParam(':ecueId', $ecue['idECUE']);
        $stmtRealise->bindParam(':anneeAcadId', $anneeAcadId);
        $stmtRealise->execute();
        $heuresRealisees = $stmtRealise->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $ecueStats = [
            'idECUE' => $ecue['idECUE'],
            'designationECUE' => $ecue['designationECUE'],
            'designationUE' => $ecue['designationUE'],
            'semestre' => $ecue['numeroSemestre'],
            'CM' => [
                'prevu' => $ecue['volumeHoraireCM'] ?: 0,
                'realise' => $heuresRealisees['CM'] ?? 0,
                'pourcentage' => 0
            ],
            'TD' => [
                'prevu' => $ecue['volumeHoraireTD'] ?: 0,
                'realise' => $heuresRealisees['TD'] ?? 0,
                'pourcentage' => 0
            ],
            'TP' => [
                'prevu' => $ecue['volumeHoraireTP'] ?: 0,
                'realise' => $heuresRealisees['TP'] ?? 0,
                'pourcentage' => 0
            ],
            'total' => [
                'prevu' => 0,
                'realise' => 0,
                'pourcentage' => 0
            ]
        ];
        
        $ecueStats['total']['prevu'] = $ecueStats['CM']['prevu'] + $ecueStats['TD']['prevu'] + $ecueStats['TP']['prevu'];
        $ecueStats['total']['realise'] = $ecueStats['CM']['realise'] + $ecueStats['TD']['realise'] + $ecueStats['TP']['realise'];
        
        foreach (['CM', 'TD', 'TP', 'total'] as $type) {
            if ($ecueStats[$type]['prevu'] > 0) {
                $ecueStats[$type]['pourcentage'] = round(($ecueStats[$type]['realise'] / $ecueStats[$type]['prevu']) * 100, 1);
            }
        }
        
        foreach (['CM', 'TD', 'TP'] as $type) {
            $totauxGlobaux[$type]['prevu'] += $ecueStats[$type]['prevu'];
            $totauxGlobaux[$type]['realise'] += $ecueStats[$type]['realise'];
        }
        
        $statistiques[] = $ecueStats;
    }
    
    $totauxGlobaux['total']['prevu'] = $totauxGlobaux['CM']['prevu'] + $totauxGlobaux['TD']['prevu'] + $totauxGlobaux['TP']['prevu'];
    $totauxGlobaux['total']['realise'] = $totauxGlobaux['CM']['realise'] + $totauxGlobaux['TD']['realise'] + $totauxGlobaux['TP']['realise'];
    
    foreach ($totauxGlobaux as $type => &$data) {
        if ($data['prevu'] > 0) {
            $data['pourcentage'] = round(($data['realise'] / $data['prevu']) * 100, 1);
        } else {
            $data['pourcentage'] = 0;
        }
    }
    
    return [
        'details' => $statistiques,
        'totaux' => $totauxGlobaux
    ];
}

// Récupérer les statistiques
if (!$hasFullAccess && !empty($userSections)) {
    $statistiques = getStatistiquesAvancement($pdo, $promotionId, $semestreId, $currentYear['idannee_acad'], $userSections);
} else {
    $statistiques = getStatistiquesAvancement($pdo, $promotionId, $semestreId, $currentYear['idannee_acad']);
}

// Créer le fichier Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Titre
$sheet->setCellValue('A1', 'SUIVI GLOBAL DES ENSEIGNEMENTS');
$sheet->mergeCells('A1:P1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Informations générales
$sheet->setCellValue('A3', 'Année académique:');
$sheet->setCellValue('B3', $currentYear['designation']);
$sheet->setCellValue('A4', 'Section:');
$sheet->setCellValue('B4', $promotion['designationSection']);
$sheet->setCellValue('A5', 'Orientation:');
$sheet->setCellValue('B5', $promotion['designationOrientation']);
$sheet->setCellValue('A6', 'Promotion:');
$sheet->setCellValue('B6', $promotion['designationPromotion']);
$sheet->setCellValue('A7', 'Date d\'export:');
$sheet->setCellValue('B7', date('d/m/Y H:i'));

// Statistiques globales
$sheet->setCellValue('A9', 'STATISTIQUES GLOBALES');
$sheet->mergeCells('A9:D9');
$sheet->getStyle('A9')->getFont()->setBold(true);

$sheet->setCellValue('A10', 'Type');
$sheet->setCellValue('B10', 'Heures prévues');
$sheet->setCellValue('C10', 'Heures réalisées');
$sheet->setCellValue('D10', 'Pourcentage');

$row = 11;
foreach (['CM', 'TD', 'TP', 'total'] as $type) {
    $sheet->setCellValue('A' . $row, $type == 'total' ? 'TOTAL' : $type);
    $sheet->setCellValue('B' . $row, $statistiques['totaux'][$type]['prevu']);
    $sheet->setCellValue('C' . $row, $statistiques['totaux'][$type]['realise']);
    $sheet->setCellValue('D' . $row, $statistiques['totaux'][$type]['pourcentage'] . '%');
    
    if ($type == 'total') {
        $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
    }
    $row++;
}

// Détail par ECUE
$row += 2;
$sheet->setCellValue('A' . $row, 'DETAIL PAR COURS (ECUE)');
$sheet->mergeCells('A' . $row . ':P' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(true);

$row++;
// En-têtes du tableau
$headers = [
    'A' => '#',
    'B' => 'Semestre',
    'C' => 'UE',
    'D' => 'ECUE',
    'E' => 'CM Prévu',
    'F' => 'CM Réalisé',
    'G' => 'CM %',
    'H' => 'TD Prévu',
    'I' => 'TD Réalisé',
    'J' => 'TD %',
    'K' => 'TP Prévu',
    'L' => 'TP Réalisé',
    'M' => 'TP %',
    'N' => 'Total Prévu',
    'O' => 'Total Réalisé',
    'P' => 'Total %'
];

foreach ($headers as $col => $header) {
    $sheet->setCellValue($col . $row, $header);
}
$sheet->getStyle('A' . $row . ':P' . $row)->getFont()->setBold(true);
$sheet->getStyle('A' . $row . ':P' . $row)->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setARGB('FFE0E0E0');

// Données
$row++;
$index = 1;
foreach ($statistiques['details'] as $ecue) {
    $sheet->setCellValue('A' . $row, $index++);
    $sheet->setCellValue('B' . $row, $ecue['semestre']);
    $sheet->setCellValue('C' . $row, $ecue['designationUE']);
    $sheet->setCellValue('D' . $row, $ecue['designationECUE']);
    
    $sheet->setCellValue('E' . $row, $ecue['CM']['prevu']);
    $sheet->setCellValue('F' . $row, $ecue['CM']['realise']);
    $sheet->setCellValue('G' . $row, $ecue['CM']['pourcentage'] . '%');
    
    $sheet->setCellValue('H' . $row, $ecue['TD']['prevu']);
    $sheet->setCellValue('I' . $row, $ecue['TD']['realise']);
    $sheet->setCellValue('J' . $row, $ecue['TD']['pourcentage'] . '%');
    
    $sheet->setCellValue('K' . $row, $ecue['TP']['prevu']);
    $sheet->setCellValue('L' . $row, $ecue['TP']['realise']);
    $sheet->setCellValue('M' . $row, $ecue['TP']['pourcentage'] . '%');
    
    $sheet->setCellValue('N' . $row, $ecue['total']['prevu']);
    $sheet->setCellValue('O' . $row, $ecue['total']['realise']);
    $sheet->setCellValue('P' . $row, $ecue['total']['pourcentage'] . '%');
    
    $row++;
}

// Ligne de totaux
$sheet->setCellValue('A' . $row, 'TOTAUX');
$sheet->mergeCells('A' . $row . ':D' . $row);
$sheet->setCellValue('E' . $row, $statistiques['totaux']['CM']['prevu']);
$sheet->setCellValue('F' . $row, $statistiques['totaux']['CM']['realise']);
$sheet->setCellValue('G' . $row, $statistiques['totaux']['CM']['pourcentage'] . '%');
$sheet->setCellValue('H' . $row, $statistiques['totaux']['TD']['prevu']);
$sheet->setCellValue('I' . $row, $statistiques['totaux']['TD']['realise']);
$sheet->setCellValue('J' . $row, $statistiques['totaux']['TD']['pourcentage'] . '%');
$sheet->setCellValue('K' . $row, $statistiques['totaux']['TP']['prevu']);
$sheet->setCellValue('L' . $row, $statistiques['totaux']['TP']['realise']);
$sheet->setCellValue('M' . $row, $statistiques['totaux']['TP']['pourcentage'] . '%');
$sheet->setCellValue('N' . $row, $statistiques['totaux']['total']['prevu']);
$sheet->setCellValue('O' . $row, $statistiques['totaux']['total']['realise']);
$sheet->setCellValue('P' . $row, $statistiques['totaux']['total']['pourcentage'] . '%');

$sheet->getStyle('A' . $row . ':P' . $row)->getFont()->setBold(true);
$sheet->getStyle('A' . $row . ':P' . $row)->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setARGB('FF4472C4');
$sheet->getStyle('A' . $row . ':P' . $row)->getFont()->getColor()->setARGB('FFFFFFFF');

// Ajuster la largeur des colonnes
foreach (range('A', 'P') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Bordures
$lastRow = $row;
$sheet->getStyle('A10:D14')->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
]);

$sheet->getStyle('A' . ($row - count($statistiques['details']) - 1) . ':P' . $lastRow)->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
]);

// Télécharger le fichier
$filename = 'suivi_global_' . $promotion['designationPromotion'] . '_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>