<?php
session_start();
require_once '../models/Connexion.php';

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

// Récupérer les paramètres de filtrage
$search = isset($_GET['search']) ? $_GET['search'] : '';
$promotionFilter = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$ecueFilter = isset($_GET['ecue']) ? intval($_GET['ecue']) : 0;
$dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$dateFin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';
$typeCoursFilter = isset($_GET['type_cours']) ? $_GET['type_cours'] : '';

// Récupérer l'année académique en cours
$pdo = Connexion::getInstance()->getPDO();

// Vérifier si la colonne est_active existe
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

// Vérifier les sections de l'utilisateur
$currentUserId = $_SESSION['id'];
$userSections = [];
$isResponsableSection = false;

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
$hasFullAccess = $_SESSION['idRole'] == 1;

// Construire la requête
$params = [];
$query = "SELECT se.*, 
                 e.\"designationECUE\",
                 tu.\"nomUser\" as user_nom,
                 a.noms as enseignant_nom,
                 gr.designation as grade_enseignant,
                 p.\"designationPromotion\",
                 sec.\"designationSection\" as section
          FROM suivi_enseignements se
          JOIN ecue e ON se.\"idECUE\" = e.\"idECUE\"
          LEFT JOIN t_users tu ON se.\"idUser\" = tu.\"idUser\"
          LEFT JOIN agent a ON se.enseignant_id = a.\"idAgent\"
          LEFT JOIN grade gr ON a.grade_id = gr.idgrade
          LEFT JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
          LEFT JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
          LEFT JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
          LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
          LEFT JOIN section sec ON o.section_idsection = sec.idsection
          WHERE 1=1";

// Filtrer par sections si l'utilisateur est responsable
if ($isResponsableSection && !$hasFullAccess) {
    $placeholders = [];
    foreach ($userSections as $i => $section) {
        $paramName = ":section{$i}";
        $placeholders[] = $paramName;
        $params[$paramName] = $section;
    }
    $query .= " AND o.section_idsection IN (" . implode(',', $placeholders) . ")";
}

// Appliquer les autres filtres
if ($promotionFilter > 0) {
    $query .= " AND p.idpromotion = :promotion";
    $params[':promotion'] = $promotionFilter;
}

if ($ecueFilter > 0) {
    $query .= " AND se.\"idECUE\" = :ecue";
    $params[':ecue'] = $ecueFilter;
}

if (!empty($typeCoursFilter)) {
    $query .= " AND se.type_cours = :type_cours";
    $params[':type_cours'] = $typeCoursFilter;
}

if (!empty($dateDebut)) {
    $query .= " AND se.date_cours >= :date_debut";
    $params[':date_debut'] = $dateDebut;
}

if (!empty($dateFin)) {
    $query .= " AND se.date_cours <= :date_fin";
    $params[':date_fin'] = $dateFin;
}

if (!empty($search)) {
    $query .= " AND (e.\"designationECUE\" LIKE :search 
                    OR p.\"designationPromotion\" LIKE :search 
                    OR a.noms LIKE :search 
                    OR se.commentaire LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$query .= " AND se.annee_acad_idannee_acad = :annee_acad";
$params[':annee_acad'] = $currentYear['idannee_acad'];

$query .= " ORDER BY se.date_cours DESC, se.heure_debut DESC";

// Exécuter la requête
$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$suivis = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Créer le fichier Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="suivi_enseignements_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

// Début du fichier HTML pour Excel
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head>';
echo '<body>';

// Titre
echo '<h2>Suivi des Enseignements - ' . $currentYear['designation'] . '</h2>';

// Informations de filtrage
echo '<p>';
if ($promotionFilter > 0) {
    $stmtPromo = $pdo->prepare("SELECT \"designationPromotion\" FROM promotion WHERE idpromotion = ?");
    $stmtPromo->execute([$promotionFilter]);
    $promo = $stmtPromo->fetchColumn();
    echo '<strong>Promotion :</strong> ' . htmlspecialchars($promo) . '<br>';
}
if (!empty($typeCoursFilter)) {
    echo '<strong>Type de cours :</strong> ' . htmlspecialchars($typeCoursFilter) . '<br>';
}
if (!empty($dateDebut) || !empty($dateFin)) {
    echo '<strong>Période :</strong> ';
    if (!empty($dateDebut)) echo 'du ' . date('d/m/Y', strtotime($dateDebut));
    if (!empty($dateFin)) echo ' au ' . date('d/m/Y', strtotime($dateFin));
    echo '<br>';
}
echo '</p>';

// Tableau des données
echo '<table border="1">';
echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
echo '<th>N°</th>';
echo '<th>Date</th>';
echo '<th>Heure début</th>';
echo '<th>Heure fin</th>';
echo '<th>Durée (h)</th>';
echo '<th>Cours (ECUE)</th>';
echo '<th>Type</th>';
echo '<th>Promotion</th>';
echo '<th>Section</th>';
echo '<th>Enseignant</th>';
echo '<th>Grade</th>';
echo '<th>Salle</th>';
echo '<th>Matières enseignées</th>';
echo '<th>Enregistré par</th>';
echo '</tr>';

$index = 1;
$totalHeures = 0;

foreach ($suivis as $suivi) {
    $duree = (strtotime($suivi['heure_fin']) - strtotime($suivi['heure_debut'])) / 3600;
    $totalHeures += $duree;
    
    echo '<tr>';
    echo '<td>' . $index++ . '</td>';
    echo '<td>' . date('d/m/Y', strtotime($suivi['date_cours'])) . '</td>';
    echo '<td>' . substr($suivi['heure_debut'], 0, 5) . '</td>';
    echo '<td>' . substr($suivi['heure_fin'], 0, 5) . '</td>';
    echo '<td>' . number_format($duree, 1) . '</td>';
    echo '<td>' . htmlspecialchars($suivi['designationECUE']) . '</td>';
    echo '<td>' . $suivi['type_cours'] . '</td>';
    echo '<td>' . htmlspecialchars($suivi['designationPromotion']) . '</td>';
    echo '<td>' . htmlspecialchars($suivi['section']) . '</td>';
    echo '<td>' . ($suivi['enseignant_nom'] ? htmlspecialchars($suivi['enseignant_nom']) : 'Non spécifié') . '</td>';
    echo '<td>' . ($suivi['grade_enseignant'] ? htmlspecialchars($suivi['grade_enseignant']) : '-') . '</td>';
    echo '<td>' . ($suivi['salle'] ? htmlspecialchars($suivi['salle']) : '-') . '</td>';
    echo '<td>' . ($suivi['commentaire'] ? htmlspecialchars($suivi['commentaire']) : 'Non spécifié') . '</td>';
    echo '<td>' . htmlspecialchars($suivi['user_nom']) . '</td>';
    echo '</tr>';
}

// Total
echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
echo '<td colspan="4">TOTAL</td>';
echo '<td>' . number_format($totalHeures, 1) . '</td>';
echo '<td colspan="9">' . count($suivis) . ' séances enregistrées</td>';
echo '</tr>';

echo '</table>';

// Statistiques par type de cours
$heuresParType = ['CM' => 0, 'TD' => 0, 'TP' => 0, 'Evaluation' => 0];
foreach ($suivis as $suivi) {
    $duree = (strtotime($suivi['heure_fin']) - strtotime($suivi['heure_debut'])) / 3600;
    if (isset($heuresParType[$suivi['type_cours']])) {
        $heuresParType[$suivi['type_cours']] += $duree;
    }
}

echo '<br><br>';
echo '<h3>Répartition par type de cours</h3>';
echo '<table border="1">';
echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
echo '<th>Type de cours</th>';
echo '<th>Nombre d\'heures</th>';
echo '<th>Pourcentage</th>';
echo '</tr>';

foreach ($heuresParType as $type => $heures) {
    if ($heures > 0) {
        echo '<tr>';
        echo '<td>' . $type . '</td>';
        echo '<td>' . number_format($heures, 1) . '</td>';
        echo '<td>' . ($totalHeures > 0 ? round(($heures / $totalHeures) * 100, 1) : 0) . '%</td>';
        echo '</tr>';
    }
}

echo '</table>';

echo '</body></html>';
?>