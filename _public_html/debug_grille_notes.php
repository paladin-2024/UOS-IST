<?php
/**
 * Script de diagnostic pour les notes manquantes dans grille_notes.php
 * Usage: http://localhost/e_gestion/debug_grille_notes.php?matricule=ET-A00000151&annee=X
 */

require_once 'config/Connexion.php';

$matricule = $_GET['matricule'] ?? 'ET-A00000151';
$anneeId = $_GET['annee'] ?? null;

$db = Connexion::getInstance()->getPDO();

echo "<h1>Diagnostic des notes pour: $matricule</h1>";

// Récupérer l'année académique si non fournie
if (!$anneeId) {
    $stmt = $db->query("SELECT idannee_acad, designation FROM annee_acad ORDER BY idannee_acad DESC LIMIT 1");
    $annee = $stmt->fetch(PDO::FETCH_ASSOC);
    $anneeId = $annee['idannee_acad'];
    echo "<p>Année académique: {$annee['designation']}</p>";
}

// Récupérer les sessions
$stmt = $db->query("SELECT idsession, designSession FROM session ORDER BY idsession");
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<h2>Sessions disponibles:</h2><ul>";
foreach ($sessions as $s) {
    echo "<li>ID: {$s['idsession']} - {$s['designSession']}</li>";
}
echo "</ul>";

// Chercher l'ECUE "Projet tutoré"
echo "<h2>Recherche de l'ECUE 'Projet tutoré':</h2>";
$stmt = $db->prepare("SELECT e.*, u.designationUE, u.idUE 
                       FROM ecue e 
                       JOIN ue u ON e.UE_idUE = u.idUE 
                       WHERE e.designationECUE LIKE '%Projet%' OR e.designationECUE LIKE '%tut%'");
$stmt->execute();
$ecues = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>idECUE</th><th>Désignation</th><th>UE</th><th>CMI</th><th>TD</th><th>TP</th></tr>";
foreach ($ecues as $e) {
    echo "<tr>";
    echo "<td>{$e['idECUE']}</td>";
    echo "<td>{$e['designationECUE']}</td>";
    echo "<td>{$e['designationUE']}</td>";
    echo "<td>{$e['CMI']}</td>";
    echo "<td>{$e['TD']}</td>";
    echo "<td>{$e['TP']}</td>";
    echo "</tr>";
}
echo "</table>";

// Chercher toutes les notes de cet étudiant
echo "<h2>Toutes les notes de l'étudiant dans cotes_grille:</h2>";
$stmt = $db->prepare("SELECT cg.*, e.designationECUE, u.designationUE, s.designSession
                       FROM cotes_grille cg
                       JOIN ecue e ON cg.ECUE_idECUE = e.idECUE
                       JOIN ue u ON e.UE_idUE = u.idUE
                       JOIN session s ON cg.session_idsession = s.idsession
                       WHERE cg.matricule = :matricule AND cg.annee_acad_id = :anneeId
                       ORDER BY u.designationUE, e.designationECUE, s.idsession");
$stmt->execute(['matricule' => $matricule, 'anneeId' => $anneeId]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>UE</th><th>ECUE</th><th>idECUE</th><th>Session</th><th>CC</th><th>EX</th><th>MF</th></tr>";
$lastUE = '';
foreach ($notes as $n) {
    $bgColor = ($n['designationUE'] != $lastUE) ? '#f0f0f0' : 'white';
    $lastUE = $n['designationUE'];
    echo "<tr style='background-color: $bgColor'>";
    echo "<td>{$n['designationUE']}</td>";
    echo "<td>{$n['designationECUE']}</td>";
    echo "<td>{$n['ECUE_idECUE']}</td>";
    echo "<td>{$n['designSession']}</td>";
    echo "<td>{$n['CC']}</td>";
    echo "<td>{$n['EX']}</td>";
    echo "<td>{$n['MF']}</td>";
    echo "</tr>";
}
echo "</table>";

// Vérifier spécifiquement SAF1122
echo "<h2>ECUEs de SAF1122 - Activités d'intégration II:</h2>";
$stmt = $db->prepare("SELECT e.*, u.codeUE, u.designationUE 
                       FROM ecue e 
                       JOIN ue u ON e.UE_idUE = u.idUE 
                       WHERE u.codeUE LIKE '%SAF1122%' OR u.designationUE LIKE '%intégration II%'
                       ORDER BY e.designationECUE");
$stmt->execute();
$ecuesSAF = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>idECUE</th><th>Désignation ECUE</th><th>Crédit (CMI+TD+TP)/25</th></tr>";
foreach ($ecuesSAF as $e) {
    $credit = ($e['CMI'] + $e['TD'] + $e['TP']) / 25;
    echo "<tr>";
    echo "<td>{$e['idECUE']}</td>";
    echo "<td>{$e['designationECUE']}</td>";
    echo "<td>" . round($credit, 1) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Comparer avec les notes encodées pour cet UE
echo "<h2>Notes encodées pour les ECUEs de SAF1122:</h2>";
$ecueIds = array_column($ecuesSAF, 'idECUE');
if (!empty($ecueIds)) {
    $placeholders = implode(',', array_fill(0, count($ecueIds), '?'));
    $stmt = $db->prepare("SELECT cg.*, e.designationECUE, s.designSession
                           FROM cotes_grille cg
                           JOIN ecue e ON cg.ECUE_idECUE = e.idECUE
                           JOIN session s ON cg.session_idsession = s.idsession
                           WHERE cg.matricule = ? AND cg.annee_acad_id = ? AND cg.ECUE_idECUE IN ($placeholders)
                           ORDER BY e.designationECUE, s.idsession");
    $params = array_merge([$matricule, $anneeId], $ecueIds);
    $stmt->execute($params);
    $notesSAF = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ECUE</th><th>idECUE</th><th>Session</th><th>CC</th><th>EX</th><th>MF</th></tr>";
    foreach ($notesSAF as $n) {
        $color = (strpos($n['designationECUE'], 'Projet') !== false) ? 'yellow' : 'white';
        echo "<tr style='background-color: $color'>";
        echo "<td>{$n['designationECUE']}</td>";
        echo "<td>{$n['ECUE_idECUE']}</td>";
        echo "<td>{$n['designSession']}</td>";
        echo "<td>{$n['CC']}</td>";
        echo "<td>{$n['EX']}</td>";
        echo "<td>{$n['MF']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Vérifier si "Projet tutoré" est dans la liste
    $projetTutoreFound = false;
    foreach ($notesSAF as $n) {
        if (stripos($n['designationECUE'], 'Projet') !== false) {
            $projetTutoreFound = true;
            break;
        }
    }
    
    if (!$projetTutoreFound) {
        echo "<p style='color: red; font-weight: bold;'>⚠️ PROBLÈME: Aucune note pour 'Projet tutoré' n'a été trouvée dans cotes_grille!</p>";
        
        // Vérifier s'il existe un ECUE "Projet tutoré" mais avec un ID différent
        echo "<h3>Vérification d'autres ECUEs 'Projet':</h3>";
        $stmt = $db->prepare("SELECT cg.*, e.designationECUE, e.idECUE as ecueId, u.designationUE
                               FROM cotes_grille cg
                               JOIN ecue e ON cg.ECUE_idECUE = e.idECUE
                               JOIN ue u ON e.UE_idUE = u.idUE
                               WHERE cg.matricule = ? AND cg.annee_acad_id = ? 
                               AND e.designationECUE LIKE '%Projet%'");
        $stmt->execute([$matricule, $anneeId]);
        $autresProjets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($autresProjets)) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>UE</th><th>ECUE</th><th>idECUE</th><th>MF</th></tr>";
            foreach ($autresProjets as $p) {
                echo "<tr>";
                echo "<td>{$p['designationUE']}</td>";
                echo "<td>{$p['designationECUE']}</td>";
                echo "<td>{$p['ecueId']}</td>";
                echo "<td>{$p['MF']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>Aucun ECUE 'Projet' trouvé dans les notes de l'étudiant.</p>";
        }
    }
}

echo "<hr><p><small>Script de diagnostic - " . date('Y-m-d H:i:s') . "</small></p>";
?>
