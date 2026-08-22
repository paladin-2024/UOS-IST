<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

$currentUserId = $_SESSION['id'];
$pdo = Connexion::getInstance()->getPDO();

// Vérifier les droits d'accès (même logique que dans le tableau de bord)
$userSections = [];
$isResponsableSection = false;

// Récupérer l'année académique en cours
$checkColumn = "SHOW COLUMNS FROM annee_acad LIKE 'est_active'";
$stmtCheck = $pdo->prepare($checkColumn);
$stmtCheck->execute();
$columnExists = $stmtCheck->fetch();

if ($columnExists) {
    $queryAnnee = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
} else {
    $queryAnnee = "SELECT * FROM annee_acad ORDER BY \"dateCreation\" DESC LIMIT 1";
}

$stmtAnnee = $pdo->prepare($queryAnnee);
$stmtAnnee->execute();
$currentYear = $stmtAnnee->fetch(PDO::FETCH_ASSOC);

if (!$currentYear) {
    $queryAnnee = "SELECT * FROM annee_acad ORDER BY \"dateCreation\" DESC LIMIT 1";
    $stmtAnnee = $pdo->prepare($queryAnnee);
    $stmtAnnee->execute();
    $currentYear = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
}

// Récupérer les sections dont l'utilisateur est responsable
$query = "SELECT section_idsection 
          FROM responsable_section 
          WHERE \"idUser\" = :userId 
          AND annee_acad_idannee_acad = :anneeId";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':userId', $currentUserId);
$stmt->bindParam(':anneeId', $currentYear['idannee_acad']);
$stmt->execute();
$userSections = $stmt->fetchAll(PDO::FETCH_COLUMN);

$isResponsableSection = !empty($userSections);

// Récupérer les paramètres de filtrage
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : $currentYear['idannee_acad'];
$sectionFilter = isset($_GET['section']) ? intval($_GET['section']) : 0;
$cycleFilter = isset($_GET['cycle']) ? $_GET['cycle'] : '';

// Fonction pour récupérer les données d'export
function getDataForExport($pdo, $sections = [], $anneeId = null, $cycleFilter = '') {
    $params = [];
    
    $query = "SELECT 
                p.\"designationPromotion\" as 'Promotion',
                p.cycle as 'Cycle',
                o.\"designationOrientation\" as 'Orientation',
                s.\"designationSection\" as 'Section',
                aa.designation as 'Année académique',
                COUNT(e.idetudiant) as 'Total inscrits',
                COUNT(CASE WHEN e.est_actif = 1 THEN 1 END) as 'Inscrits actifs',
                COUNT(CASE WHEN e.est_actif = 0 THEN 1 END) as 'Inscrits inactifs',
                COUNT(CASE WHEN e.dossier_complete = 1 THEN 1 END) as 'Dossiers complets',
                COUNT(CASE WHEN e.dossier_complete = 0 THEN 1 END) as 'Dossiers incomplets',
                COUNT(CASE WHEN e.sexe = 'M' THEN 1 END) as 'Hommes',
                COUNT(CASE WHEN e.sexe = 'F' THEN 1 END) as 'Femmes',
                ROUND(COUNT(CASE WHEN e.est_actif = 1 THEN 1 END) * 100.0 / COUNT(e.idetudiant), 2) as 'Pourcentage actifs',
                ROUND(COUNT(CASE WHEN e.dossier_complete = 1 THEN 1 END) * 100.0 / COUNT(e.idetudiant), 2) as 'Pourcentage dossiers complets',
                ROUND(COUNT(CASE WHEN e.sexe = 'M' THEN 1 END) * 100.0 / COUNT(e.idetudiant), 2) as 'Pourcentage hommes',
                ROUND(COUNT(CASE WHEN e.sexe = 'F' THEN 1 END) * 100.0 / COUNT(e.idetudiant), 2) as 'Pourcentage femmes'
              FROM promotion p
              LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
              LEFT JOIN section s ON o.section_idsection = s.idsection
              LEFT JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
              LEFT JOIN etudiant e ON p.idpromotion = e.promotion_idpromotion 
                                   AND e.annee_acad_idannee_acad = p.annee_acad_idannee_acad
              WHERE 1=1";
    
    // Filtrer par sections si spécifié
    if (!empty($sections) && is_array($sections)) {
        $sectionParams = [];
        foreach ($sections as $i => $section) {
            if (!empty($section)) {
                $paramName = ":section{$i}";
                $sectionParams[] = $paramName;
                $params[$paramName] = $section;
            }
        }
        
        if (!empty($sectionParams)) {
            $placeholders = implode(',', $sectionParams);
            $query .= " AND o.section_idsection IN ($placeholders)";
        }
    }
    
    // Filtrer par année académique
    if (!empty($anneeId)) {
        $query .= " AND p.annee_acad_idannee_acad = :anneeId";
        $params[':anneeId'] = $anneeId;
    }
    
    // Filtrer par cycle
    if (!empty($cycleFilter)) {
        $query .= " AND p.cycle = :cycle";
        $params[':cycle'] = $cycleFilter;
    }
    
    $query .= " GROUP BY p.idpromotion, p.\"designationPromotion\", p.cycle, o.\"designationOrientation\", s.\"designationSection\", aa.designation
                HAVING COUNT(e.idetudiant) > 0
                ORDER BY s.\"designationSection\", p.cycle, p.\"designationPromotion\"";
    
    try {
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur dans getDataForExport: " . $e->getMessage());
        return [];
    }
}

// Vérifier les droits et récupérer les données
if ($isResponsableSection) {
    if ($sectionFilter > 0) {
        if (in_array($sectionFilter, $userSections)) {
            $sectionsForExport = [$sectionFilter];
        } else {
            die("Accès refusé à cette section.");
        }
    } else {
        $sectionsForExport = $userSections;
    }
} else {
    $hasFullAccess = $_SESSION['idRole'] == 1;
    if (!$hasFullAccess) {
        die("Accès refusé.");
    }
    
    if ($sectionFilter > 0) {
        $sectionsForExport = [$sectionFilter];
    } else {
        $sectionsForExport = [];
    }
}

$data = getDataForExport($pdo, $sectionsForExport, $anneeId, $cycleFilter);

if (empty($data)) {
    die("Aucune donnée à exporter.");
}

// Récupérer l'année académique pour le nom du fichier
$queryAnneeNom = "SELECT designation FROM annee_acad WHERE idannee_acad = :anneeId";
$stmtAnneeNom = $pdo->prepare($queryAnneeNom);
$stmtAnneeNom->bindParam(':anneeId', $anneeId);
$stmtAnneeNom->execute();
$anneeNom = $stmtAnneeNom->fetchColumn();

// Générer le nom du fichier
$filename = "statistiques_inscriptions_" . str_replace(['/', ' '], ['_', '_'], $anneeNom) . "_" . date('Y-m-d_H-i-s') . ".csv";

// Headers pour le téléchargement
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Ouvrir le flux de sortie
$output = fopen('php://output', 'w');

// Ajouter le BOM UTF-8 pour Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Écrire les en-têtes
if (!empty($data)) {
    fputcsv($output, array_keys($data[0]), ';');
    
    // Écrire les données
    foreach ($data as $row) {
        // Formater les cycles
        if (isset($row['Cycle'])) {
            switch ($row['Cycle']) {
                case 'Premier':
                    $row['Cycle'] = 'Licence';
                    break;
                case 'Deuxieme':
                    $row['Cycle'] = 'Master';
                    break;
                case 'Troisieme':
                    $row['Cycle'] = 'Doctorat';
                    break;
            }
        }
        
        fputcsv($output, $row, ';');
    }
}

fclose($output);
exit;
?>