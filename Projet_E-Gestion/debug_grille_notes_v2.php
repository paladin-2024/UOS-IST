<?php
/**
 * Diagnostic V2 - Vérifier toutes les années académiques
 */

require_once 'config/Connexion.php';

$matricule = $_GET['matricule'] ?? 'ET-A00000151';

$db = Connexion::getInstance()->getPDO();

echo "<h1>Diagnostic V2 - Notes pour: $matricule</h1>";

// 1. Vérifier TOUTES les notes de cet étudiant (sans filtre d'année)
echo "<h2>1. TOUTES les notes dans cotes_grille (sans filtre année):</h2>";
$stmt = $db->prepare("SELECT cg.*, e.designationECUE, u.designationUE, s.designSession, a.designation as annee
                       FROM cotes_grille cg
                       JOIN ecue e ON cg.ECUE_idECUE = e.idECUE
                       JOIN ue u ON e.UE_idUE = u.idUE
                       JOIN session s ON cg.session_idsession = s.idsession
                       LEFT JOIN annee_acad a ON cg.annee_acad_id = a.idannee_acad
                       WHERE cg.matricule = :matricule
                       ORDER BY cg.annee_acad_id DESC, u.designationUE, e.designationECUE");
$stmt->execute(['matricule' => $matricule]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p><strong>Nombre total de notes trouvées: " . count($notes) . "</strong></p>";

if (count($notes) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Année</th><th>UE</th><th>ECUE</th><th>idECUE</th><th>Session</th><th>CC</th><th>EX</th><th>MF</th></tr>";
    foreach ($notes as $n) {
        $highlight = (stripos($n['designationECUE'], 'Projet') !== false) ? 'background-color: yellow;' : '';
        echo "<tr style='$highlight'>";
        echo "<td>{$n['annee']}</td>";
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
} else {
    echo "<p style='color:red;'>❌ AUCUNE NOTE TROUVÉE pour ce matricule!</p>";
}

// 2. Vérifier l'étudiant dans la table etudiant
echo "<h2>2. Informations étudiant:</h2>";
$stmt = $db->prepare("SELECT e.*, p.designationPromotion, a.designation as annee
                       FROM etudiant e
                       LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                       LEFT JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
                       WHERE e.matricule = :matricule");
$stmt->execute(['matricule' => $matricule]);
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Matricule</th><th>Noms</th><th>Promotion</th><th>Année</th><th>ID Année</th><th>Est Actif</th></tr>";
foreach ($etudiants as $et) {
    echo "<tr>";
    echo "<td>{$et['matricule']}</td>";
    echo "<td>{$et['noms']}</td>";
    echo "<td>{$et['designationPromotion']}</td>";
    echo "<td>{$et['annee']}</td>";
    echo "<td>{$et['annee_acad_idannee_acad']}</td>";
    echo "<td>{$et['est_actif']}</td>";
    echo "</tr>";
}
echo "</table>";

// 3. Années académiques disponibles
echo "<h2>3. Années académiques:</h2>";
$stmt = $db->query("SELECT * FROM annee_acad ORDER BY idannee_acad DESC");
$annees = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Désignation</th><th>Est Active</th></tr>";
foreach ($annees as $a) {
    echo "<tr>";
    echo "<td>{$a['idannee_acad']}</td>";
    echo "<td>{$a['designation']}</td>";
    echo "<td>" . ($a['estActive'] ?? 'N/A') . "</td>";
    echo "</tr>";
}
echo "</table>";

// 4. Vérifier les notes pour SAF1122 spécifiquement (tous les ECUEs possibles)
echo "<h2>4. Notes pour ECUEs 'Activités d'intégration II' (tous IDs):</h2>";
$stmt = $db->prepare("SELECT e.idECUE, e.designationECUE 
                       FROM ecue e 
                       JOIN ue u ON e.UE_idUE = u.idUE 
                       WHERE u.designationUE LIKE '%Activités d\\'intégration II%'");
$stmt->execute();
$ecuesAI = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ecueIds = array_column($ecuesAI, 'idECUE');
echo "<p>ECUEs trouvés: " . implode(', ', $ecueIds) . "</p>";

if (!empty($ecueIds)) {
    $placeholders = implode(',', $ecueIds);
    $stmt = $db->prepare("SELECT cg.*, e.designationECUE, s.designSession, a.designation as annee
                           FROM cotes_grille cg
                           JOIN ecue e ON cg.ECUE_idECUE = e.idECUE
                           JOIN session s ON cg.session_idsession = s.idsession
                           LEFT JOIN annee_acad a ON cg.annee_acad_id = a.idannee_acad
                           WHERE cg.matricule = :matricule 
                           AND cg.ECUE_idECUE IN ($placeholders)
                           ORDER BY e.designationECUE");
    $stmt->execute(['matricule' => $matricule]);
    $notesAI = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($notesAI) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Année</th><th>ECUE</th><th>idECUE</th><th>Session</th><th>CC</th><th>EX</th><th>MF</th></tr>";
        foreach ($notesAI as $n) {
            echo "<tr>";
            echo "<td>{$n['annee']}</td>";
            echo "<td>{$n['designationECUE']}</td>";
            echo "<td>{$n['ECUE_idECUE']}</td>";
            echo "<td>{$n['designSession']}</td>";
            echo "<td>{$n['CC']}</td>";
            echo "<td>{$n['EX']}</td>";
            echo "<td>{$n['MF']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>Aucune note trouvée pour ces ECUEs!</p>";
    }
}

// 5. Vérifier comment encodage_points récupère les notes
echo "<h2>5. Promotion de l'étudiant et ses ECUEs:</h2>";
$stmt = $db->prepare("SELECT e.promotion_idpromotion, p.designationPromotion, e.annee_acad_idannee_acad
                       FROM etudiant e
                       JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                       WHERE e.matricule = :matricule AND e.est_actif = 1");
$stmt->execute(['matricule' => $matricule]);
$promoInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if ($promoInfo) {
    echo "<p>Promotion active: {$promoInfo['designationPromotion']} (ID: {$promoInfo['promotion_idpromotion']})</p>";
    echo "<p>Année académique: {$promoInfo['annee_acad_idannee_acad']}</p>";
    
    // Récupérer les semestres de cette promotion
    $stmt = $db->prepare("SELECT * FROM semestre WHERE promotion_idpromotion = :promoId ORDER BY numeroSemestre");
    $stmt->execute(['promoId' => $promoInfo['promotion_idpromotion']]);
    $semestres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($semestres as $sem) {
        echo "<h3>Semestre {$sem['numeroSemestre']} (ID: {$sem['idsemestre']})</h3>";
        
        // UEs du semestre
        $stmt = $db->prepare("SELECT * FROM ue WHERE semestre_idsemestre = :semId ORDER BY codeUE");
        $stmt->execute(['semId' => $sem['idsemestre']]);
        $ues = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($ues as $ue) {
            echo "<h4>{$ue['codeUE']} - {$ue['designationUE']}</h4>";
            
            // ECUEs de l'UE
            $stmt = $db->prepare("SELECT e.*, 
                                  (SELECT MF FROM cotes_grille WHERE ECUE_idECUE = e.idECUE AND matricule = :matricule AND session_idsession = 1) as note_s1,
                                  (SELECT MF FROM cotes_grille WHERE ECUE_idECUE = e.idECUE AND matricule = :matricule AND session_idsession = 2) as note_s2
                                  FROM ecue e WHERE e.UE_idUE = :ueId ORDER BY e.designationECUE");
            $stmt->execute(['ueId' => $ue['idUE'], 'matricule' => $matricule]);
            $ecues = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table border='1' cellpadding='3'>";
            echo "<tr><th>idECUE</th><th>ECUE</th><th>Note S1</th><th>Note S2</th></tr>";
            foreach ($ecues as $ec) {
                $highlight = (stripos($ec['designationECUE'], 'Projet') !== false) ? 'background-color: yellow;' : '';
                echo "<tr style='$highlight'>";
                echo "<td>{$ec['idECUE']}</td>";
                echo "<td>{$ec['designationECUE']}</td>";
                echo "<td>" . ($ec['note_s1'] ?? '-') . "</td>";
                echo "<td>" . ($ec['note_s2'] ?? '-') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
}

echo "<hr><p><small>Diagnostic V2 - " . date('Y-m-d H:i:s') . "</small></p>";
?>
