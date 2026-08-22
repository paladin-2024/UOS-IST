<?php
session_start();
require_once '../config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

// Vérifier les droits d'accès
$currentUserId = $_SESSION['id'];
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

$isResponsableSection = !empty($userSections);
$hasFullAccess = $_SESSION['idRole'] == 1;

if (!$isResponsableSection && !$hasFullAccess) {
    header('HTTP/1.0 403 Forbidden');
    exit('Accès refusé');
}

// Fonction pour récupérer les données complètes
function getDataForExport($pdo, $userSections, $anneeId) {
    $params = [':anneeId' => $anneeId];
    
    $query = "SELECT 
                s.designationSection,
                p.idpromotion,
                p.designationPromotion,
                p.cycle,
                p.est_terminale,
                o.designationOrientation,
                COUNT(DISTINCT e.idetudiant) as nb_etudiants_inscrits,
                COUNT(DISTINCT CASE WHEN e.est_actif = 1 THEN e.idetudiant END) as nb_etudiants_actifs,
                COUNT(DISTINCT suj.idsujets) as nb_sujets_recherche,
                COUNT(DISTINCT CASE WHEN suj.etatSujet = 'Validé' THEN suj.idsujets END) as nb_sujets_valides,
                COUNT(DISTINCT ag.idAgent) as nb_enseignants,
                COUNT(DISTINCT eo.idetudiant) as nb_etudiants_en_ordre,
                -- Calcul des heures de cours
                SUM(CASE WHEN ecue.CMI > 0 THEN ecue.CMI ELSE 0 END) as total_heures_cm_prevues,
                SUM(CASE WHEN ecue.TD > 0 THEN ecue.TD ELSE 0 END) as total_heures_td_prevues,
                SUM(CASE WHEN ecue.TP > 0 THEN ecue.TP ELSE 0 END) as total_heures_tp_prevues,
                COALESCE(SUM(CASE WHEN se.type_cours = 'CM' THEN TIMESTAMPDIFF(HOUR, se.heure_debut, se.heure_fin) ELSE 0 END), 0) as heures_cm_realisees,
                COALESCE(SUM(CASE WHEN se.type_cours = 'TD' THEN TIMESTAMPDIFF(HOUR, se.heure_debut, se.heure_fin) ELSE 0 END), 0) as heures_td_realisees,
                COALESCE(SUM(CASE WHEN se.type_cours = 'TP' THEN TIMESTAMPDIFF(HOUR, se.heure_debut, se.heure_fin) ELSE 0 END), 0) as heures_tp_realisees
              FROM promotion p
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN section s ON o.section_idsection = s.idsection
              LEFT JOIN etudiant e ON p.idpromotion = e.promotion_idpromotion 
                  AND e.annee_acad_idannee_acad = :anneeId
              LEFT JOIN sujets suj ON e.idetudiant = suj.etudiant_idetudiant 
                  AND suj.annee_acad_idannee_acad = :anneeId
              LEFT JOIN enseignant_section es ON s.idsection = es.idsection
              LEFT JOIN agent ag ON es.idenseignant = ag.idAgent
              LEFT JOIN etudiant_en_ordre eo ON e.idetudiant = eo.idetudiant 
                  AND eo.annee_acad_idannee_acad = :anneeId
              LEFT JOIN semestre sem ON p.idpromotion = sem.promotion_idpromotion
              LEFT JOIN ue ON sem.idsemestre = ue.semestre_idsemestre
              LEFT JOIN ecue ON ue.idUE = ecue.UE_idUE AND ecue.estVisible = 1
              LEFT JOIN suivi_enseignements se ON ecue.idECUE = se.idECUE 
                  AND se.annee_acad_idannee_acad = :anneeId
              WHERE p.annee_acad_idannee_acad = :anneeId";
    
    if (!empty($userSections)) {
        $placeholders = [];
        foreach ($userSections as $i => $section) {
            $paramName = ":section{$i}";
            $placeholders[] = $paramName;
            $params[$paramName] = $section;
        }
        $query .= " AND o.section_idsection IN (" . implode(',', $placeholders) . ")";
    }
    
    $query .= " GROUP BY s.idsection, p.idpromotion
                ORDER BY s.designationSection, p.cycle, p.designationPromotion";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les données
if ($isResponsableSection) {
    $data = getDataForExport($pdo, $userSections, $currentYear['idannee_acad']);
} else {
    $data = getDataForExport($pdo, [], $currentYear['idannee_acad']);
}

// Générer le fichier Excel
$filename = 'tableau_bord_section_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Ouvrir le flux de sortie
$output = fopen('php://output', 'w');

// Ajouter le BOM UTF-8 pour Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// En-têtes du CSV
$headers = [
    'Section',
    'Promotion',
    'Orientation',
    'Cycle',
    'Est Terminale',
    'Étudiants Inscrits',
    'Étudiants Actifs',
    '% Étudiants Actifs',
    'Étudiants En Ordre',
    '% En Ordre',
    'Sujets Recherche',
    'Sujets Validés',
    '% Sujets Validés',
    'Enseignants',
    'Heures CM Prévues',
    'Heures CM Réalisées',
    '% Avancement CM',
    'Heures TD Prévues',
    'Heures TD Réalisées',
    '% Avancement TD',
    'Heures TP Prévues',
    'Heures TP Réalisées',
    '% Avancement TP',
    'Total Heures Prévues',
    'Total Heures Réalisées',
    '% Avancement Global'
];

fputcsv($output, $headers, ';');

// Données
foreach ($data as $row) {
    // Calculs des pourcentages
    $pourcentageActifs = $row['nb_etudiants_inscrits'] > 0 ? 
        round(($row['nb_etudiants_actifs'] / $row['nb_etudiants_inscrits']) * 100, 1) : 0;
    
    $pourcentageEnOrdre = $row['nb_etudiants_inscrits'] > 0 ? 
        round(($row['nb_etudiants_en_ordre'] / $row['nb_etudiants_inscrits']) * 100, 1) : 0;
    
    $pourcentageSujets = $row['nb_sujets_recherche'] > 0 ? 
        round(($row['nb_sujets_valides'] / $row['nb_sujets_recherche']) * 100, 1) : 0;
    
    $pourcentageCM = $row['total_heures_cm_prevues'] > 0 ? 
        round(($row['heures_cm_realisees'] / $row['total_heures_cm_prevues']) * 100, 1) : 0;
    
    $pourcentageTD = $row['total_heures_td_prevues'] > 0 ? 
        round(($row['heures_td_realisees'] / $row['total_heures_td_prevues']) * 100, 1) : 0;
    
    $pourcentageTP = $row['total_heures_tp_prevues'] > 0 ? 
        round(($row['heures_tp_realisees'] / $row['total_heures_tp_prevues']) * 100, 1) : 0;
    
    $totalHeuresPrevu = $row['total_heures_cm_prevues'] + $row['total_heures_td_prevues'] + $row['total_heures_tp_prevues'];
    $totalHeuresRealise = $row['heures_cm_realisees'] + $row['heures_td_realisees'] + $row['heures_tp_realisees'];
    $pourcentageGlobal = $totalHeuresPrevu > 0 ? 
        round(($totalHeuresRealise / $totalHeuresPrevu) * 100, 1) : 0;
    
    $csvRow = [
        $row['designationSection'],
        $row['designationPromotion'],
        $row['designationOrientation'],
        $row['cycle'],
        $row['est_terminale'] ? 'Oui' : 'Non',
        $row['nb_etudiants_inscrits'],
        $row['nb_etudiants_actifs'],
        $pourcentageActifs . '%',
        $row['nb_etudiants_en_ordre'],
        $pourcentageEnOrdre . '%',
        $row['nb_sujets_recherche'],
        $row['nb_sujets_valides'],
        $pourcentageSujets . '%',
        $row['nb_enseignants'],
        $row['total_heures_cm_prevues'],
        $row['heures_cm_realisees'],
        $pourcentageCM . '%',
        $row['total_heures_td_prevues'],
        $row['heures_td_realisees'],
        $pourcentageTD . '%',
        $row['total_heures_tp_prevues'],
        $row['heures_tp_realisees'],
        $pourcentageTP . '%',
        $totalHeuresPrevu,
        $totalHeuresRealise,
        $pourcentageGlobal . '%'
    ];
    
    fputcsv($output, $csvRow, ';');
}

// Ajouter une ligne de résumé
fputcsv($output, [], ';'); // Ligne vide
fputcsv($output, ['RÉSUMÉ GLOBAL'], ';');

// Calculer les totaux
$totalEtudiants = array_sum(array_column($data, 'nb_etudiants_inscrits'));
$totalEtudiantsActifs = array_sum(array_column($data, 'nb_etudiants_actifs'));
$totalEtudiantsEnOrdre = array_sum(array_column($data, 'nb_etudiants_en_ordre'));
$totalSujets = array_sum(array_column($data, 'nb_sujets_recherche'));
$totalSujetsValides = array_sum(array_column($data, 'nb_sujets_valides'));
$totalEnseignants = array_sum(array_column($data, 'nb_enseignants'));

$totalCMPrevu = array_sum(array_column($data, 'total_heures_cm_prevues'));
$totalCMRealise = array_sum(array_column($data, 'heures_cm_realisees'));
$totalTDPrevu = array_sum(array_column($data, 'total_heures_td_prevues'));
$totalTDRealise = array_sum(array_column($data, 'heures_td_realisees'));
$totalTPPrevu = array_sum(array_column($data, 'total_heures_tp_prevues'));
$totalTPRealise = array_sum(array_column($data, 'heures_tp_realisees'));

$totalHeuresPrevu = $totalCMPrevu + $totalTDPrevu + $totalTPPrevu;
$totalHeuresRealise = $totalCMRealise + $totalTDRealise + $totalTPRealise;

$resumeRow = [
    'TOTAL',
    count($data) . ' promotions',
    '',
    '',
    '',
    $totalEtudiants,
    $totalEtudiantsActifs,
    $totalEtudiants > 0 ? round(($totalEtudiantsActifs / $totalEtudiants) * 100, 1) . '%' : '0%',
    $totalEtudiantsEnOrdre,
    $totalEtudiants > 0 ? round(($totalEtudiantsEnOrdre / $totalEtudiants) * 100, 1) . '%' : '0%',
    $totalSujets,
    $totalSujetsValides,
    $totalSujets > 0 ? round(($totalSujetsValides / $totalSujets) * 100, 1) . '%' : '0%',
    $totalEnseignants,
    $totalCMPrevu,
    $totalCMRealise,
    $totalCMPrevu > 0 ? round(($totalCMRealise / $totalCMPrevu) * 100, 1) . '%' : '0%',
    $totalTDPrevu,
    $totalTDRealise,
    $totalTDPrevu > 0 ? round(($totalTDRealise / $totalTDPrevu) * 100, 1) . '%' : '0%',
    $totalTPPrevu,
    $totalTPRealise,
    $totalTPPrevu > 0 ? round(($totalTPRealise / $totalTPPrevu) * 100, 1) . '%' : '0%',
    $totalHeuresPrevu,
    $totalHeuresRealise,
    $totalHeuresPrevu > 0 ? round(($totalHeuresRealise / $totalHeuresPrevu) * 100, 1) . '%' : '0%'
];

fputcsv($output, $resumeRow, ';');

// Ajouter les métadonnées
fputcsv($output, [], ';'); // Ligne vide
fputcsv($output, ['MÉTADONNÉES'], ';');
fputcsv($output, ['Année académique', $currentYear['designation']], ';');
fputcsv($output, ['Date d\'export', date('d/m/Y H:i:s')], ';');
fputcsv($output, ['Exporté par', $_SESSION['nomUser']], ';');

if ($isResponsableSection) {
    fputcsv($output, ['Sections sous responsabilité', count($userSections)], ';');
} else {
    fputcsv($output, ['Vue', 'Globale (toutes sections)'], ';');
}

fclose($output);
exit;
?>