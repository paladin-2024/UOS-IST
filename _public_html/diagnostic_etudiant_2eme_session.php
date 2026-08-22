<?php
/**
 * Script de diagnostic pour vérifier l'éligibilité d'un étudiant à la 2ème session
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/Connexion.php';

$matricule = isset($_GET['matricule']) ? $_GET['matricule'] : 'ET-A00000418';
$selectedId = isset($_GET['id']) ? intval($_GET['id']) : 0;

$db = Connexion::getInstance()->getPDO();

echo "<html><head><title>Diagnostic 2ème Session</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; margin: 10px 0; width: 100%; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    th { background: #f0f0f0; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    h2 { color: #333; border-bottom: 2px solid #333; padding-bottom: 5px; }
</style></head><body>";

echo "<h1>Diagnostic Éligibilité 2ème Session</h1>";
echo "<p>Matricule: <strong>$matricule</strong></p>";

// 1. Rechercher l'étudiant
echo "<h2>1. Recherche de l'étudiant</h2>";

$query = "SELECT e.*, p.designationpromotion 
          FROM etudiant e
          LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
          WHERE e.matricule = ? OR e.noms LIKE '%TUMBU%'
          ORDER BY e.annee_acad_idannee_acad DESC, e.idetudiant DESC";
$stmt = $db->prepare($query);
$stmt->execute([$matricule]);
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($etudiants)) {
    echo "<p class='error'>Aucun étudiant trouvé!</p></body></html>";
    exit;
}

echo "<table><tr><th>Sélection</th><th>ID</th><th>Matricule</th><th>Noms</th><th>Promotion</th><th>Année ID</th></tr>";
foreach ($etudiants as $e) {
    $selected = ($selectedId == $e['idetudiant'] || ($selectedId == 0 && $e == $etudiants[0])) ? " style='background:#fffacd'" : "";
    echo "<tr$selected>";
    echo "<td><a href='?matricule={$e['matricule']}&id={$e['idetudiant']}'>Analyser</a></td>";
    echo "<td>{$e['idetudiant']}</td>";
    echo "<td>{$e['matricule']}</td>";
    echo "<td>{$e['noms']}</td>";
    echo "<td>{$e['designationpromotion']} (ID:{$e['promotion_idpromotion']})</td>";
    echo "<td>{$e['annee_acad_idannee_acad']}</td>";
    echo "</tr>";
}
echo "</table>";

// Sélectionner l'étudiant à analyser
$etudiant = $selectedId > 0 ? 
    array_filter($etudiants, fn($e) => $e['idetudiant'] == $selectedId)[0] ?? $etudiants[0] : 
    $etudiants[0];

$promotionId = $etudiant['promotion_idpromotion'];
$anneeId = $etudiant['annee_acad_idannee_acad'];
$matricule = $etudiant['matricule'];

echo "<p class='success'>Étudiant analysé: <strong>{$etudiant['noms']}</strong> (ID: {$etudiant['idetudiant']}, Promotion: $promotionId, Année: $anneeId)</p>";

// Structure des tables
echo "<h2>1b. Structure des tables</h2>";
$tables = ['ecue', 'ue', 'cotes_grille'];
foreach ($tables as $t) {
    $cols = $db->query("DESCRIBE $t")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p><strong>$t:</strong> " . implode(", ", $cols) . "</p>";
}

// 2. Sessions
echo "<h2>2. Sessions</h2>";
$query = "SELECT * FROM session";
$sessions = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
$session1Id = null;
$session2Id = null;
foreach ($sessions as $s) {
    if (stripos($s['designSession'], 'premi') !== false) $session1Id = $s['idsession'];
    if (stripos($s['designSession'], 'deuxi') !== false) $session2Id = $s['idsession'];
}
echo "<p>Session 1: <strong>$session1Id</strong>, Session 2: <strong>$session2Id</strong></p>";

// 3. Toutes les notes de l'étudiant
echo "<h2>3. Toutes les notes dans cotes_grille</h2>";

$query = "SELECT cg.*, e.*, s.designSession
          FROM cotes_grille cg
          LEFT JOIN ecue e ON cg.ECUE_idECUE = e.idECUE
          LEFT JOIN session s ON cg.session_idsession = s.idsession
          WHERE cg.matricule = ? AND cg.annee_acad_id = ?
          ORDER BY cg.session_idsession, cg.ECUE_idECUE";
$stmt = $db->prepare($query);
$stmt->execute([$matricule, $anneeId]);
$allNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Total enregistrements: <strong>" . count($allNotes) . "</strong></p>";

// Détecter le nom de la colonne pour ECUE (codeECUE ou designationECUE ou autre)
$ecueNameCol = null;
if (!empty($allNotes)) {
    $first = $allNotes[0];
    if (isset($first['codeECUE'])) $ecueNameCol = 'codeECUE';
    elseif (isset($first['designationECUE'])) $ecueNameCol = 'designationECUE';
    elseif (isset($first['designation'])) $ecueNameCol = 'designation';
    elseif (isset($first['nom'])) $ecueNameCol = 'nom';
}

// Détecter colonne UE
$ueIdCol = null;
if (!empty($allNotes)) {
    $first = $allNotes[0];
    if (isset($first['UE_idUE'])) $ueIdCol = 'UE_idUE';
    elseif (isset($first['idUE'])) $ueIdCol = 'idUE';
    elseif (isset($first['ue_id'])) $ueIdCol = 'ue_id';
}

echo "<p>Colonne ECUE: $ecueNameCol, Colonne UE: $ueIdCol</p>";

echo "<table>";
echo "<tr><th>Session</th><th>ECUE ID</th><th>ECUE</th><th>UE ID</th><th>CC</th><th>EX</th><th>MF</th><th>Statut</th></tr>";
foreach ($allNotes as $n) {
    $mfStatus = '';
    if ($n['MF'] === null) {
        $mfStatus = "<span class='error'>NULL (VIDE!)</span>";
    } elseif ($n['MF'] < 10) {
        $mfStatus = "<span class='warning'>< 10</span>";
    } else {
        $mfStatus = "<span class='success'>≥ 10</span>";
    }
    $ecueName = $ecueNameCol ? ($n[$ecueNameCol] ?? 'N/A') : $n['ECUE_idECUE'];
    $ueIdVal = $ueIdCol ? ($n[$ueIdCol] ?? 'N/A') : 'N/A';
    echo "<tr>";
    echo "<td>{$n['designSession']}</td>";
    echo "<td>{$n['ECUE_idECUE']}</td>";
    echo "<td>$ecueName</td>";
    echo "<td>$ueIdVal</td>";
    echo "<td>" . ($n['CC'] !== null ? $n['CC'] : 'NULL') . "</td>";
    echo "<td>" . ($n['EX'] !== null ? $n['EX'] : 'NULL') . "</td>";
    echo "<td>" . ($n['MF'] !== null ? $n['MF'] : '<span class="error">NULL</span>') . "</td>";
    echo "<td>$mfStatus</td>";
    echo "</tr>";
}
echo "</table>";

// 4. Analyser par UE - Notes S1 vs ECUE totaux
echo "<h2>4. Analyse par UE - Première Session</h2>";

// Récupérer les UE concernées via les ECUE
$ueIds = [];
if ($ueIdCol && !empty($allNotes)) {
    $ueIds = array_unique(array_filter(array_column($allNotes, $ueIdCol)));
}
echo "<p>UE concernées: " . implode(", ", $ueIds) . "</p>";

$problemesDetectes = [];

foreach ($ueIds as $ueId) {
    if (!$ueId) continue;
    
    // Info UE
    $query = "SELECT * FROM ue WHERE idUE = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$ueId]);
    $ue = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ue) {
        echo "<p class='warning'>UE $ueId non trouvée</p>";
        continue;
    }
    
    // Détecter les colonnes UE
    $ueCodeCol = isset($ue['codeUE']) ? 'codeUE' : (isset($ue['code']) ? 'code' : 'idUE');
    $ueDesigCol = isset($ue['designationUE']) ? 'designationUE' : (isset($ue['designation']) ? 'designation' : 'idUE');
    
    // Tous les ECUE de l'UE
    $query = "SELECT * FROM ecue WHERE UE_idUE = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$ueId]);
    $ecuesUE = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $nbEcuesTotal = count($ecuesUE);
    
    // Détecter colonnes ECUE
    $ecueCodeCol = 'idECUE';
    $ecueDesigCol = 'idECUE';
    if (!empty($ecuesUE)) {
        $first = $ecuesUE[0];
        if (isset($first['codeECUE'])) $ecueCodeCol = 'codeECUE';
        elseif (isset($first['code'])) $ecueCodeCol = 'code';
        if (isset($first['designationECUE'])) $ecueDesigCol = 'designationECUE';
        elseif (isset($first['designation'])) $ecueDesigCol = 'designation';
    }
    
    // Notes S1 pour cette UE
    $query = "SELECT cg.* FROM cotes_grille cg
              INNER JOIN ecue e ON cg.ECUE_idECUE = e.idECUE
              WHERE cg.matricule = ? AND e.UE_idUE = ? AND cg.session_idsession = ? AND cg.annee_acad_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$matricule, $ueId, $session1Id, $anneeId]);
    $notesS1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Compter les notes valides (MF non NULL)
    $nbNotesValides = 0;
    $nbNotesNull = 0;
    foreach ($notesS1 as $n) {
        if ($n['MF'] !== null) {
            $nbNotesValides++;
        } else {
            $nbNotesNull++;
        }
    }
    
    echo "<h4>UE {$ue[$ueCodeCol]}: {$ue[$ueDesigCol]}</h4>";
    echo "<table>";
    echo "<tr><th>ECUE dans l'UE</th><th>$nbEcuesTotal</th></tr>";
    echo "<tr><th>Enregistrements S1</th><th>" . count($notesS1) . "</th></tr>";
    echo "<tr><th>Notes MF valides (non NULL)</th><th>$nbNotesValides</th></tr>";
    echo "<tr><th>Notes MF NULL</th><th class='" . ($nbNotesNull > 0 ? 'error' : '') . "'>$nbNotesNull</th></tr>";
    echo "</table>";
    
    // Détail des ECUE
    echo "<table><tr><th>ECUE ID</th><th>ECUE</th><th>A note S1?</th><th>MF</th><th>Problème?</th></tr>";
    foreach ($ecuesUE as $ecue) {
        $noteFound = null;
        foreach ($notesS1 as $n) {
            if ($n['ECUE_idECUE'] == $ecue['idECUE']) {
                $noteFound = $n;
                break;
            }
        }
        
        $probleme = '';
        $ecueLabel = $ecue[$ecueCodeCol] ?? $ecue['idECUE'];
        if (!$noteFound) {
            $probleme = "<span class='error'>PAS D'ENREGISTREMENT S1</span>";
            $problemesDetectes[] = "UE {$ue[$ueCodeCol]}, ECUE $ecueLabel: Pas d'enregistrement";
        } elseif ($noteFound['MF'] === null) {
            $probleme = "<span class='error'>MF EST NULL</span>";
            $problemesDetectes[] = "UE {$ue[$ueCodeCol]}, ECUE $ecueLabel: MF est NULL";
        }
        
        echo "<tr>";
        echo "<td>{$ecue['idECUE']}</td>";
        echo "<td>{$ecue[$ecueCodeCol]} - {$ecue[$ecueDesigCol]}</td>";
        echo "<td>" . ($noteFound ? 'OUI' : '<span class="error">NON</span>') . "</td>";
        echo "<td>" . ($noteFound ? ($noteFound['MF'] !== null ? $noteFound['MF'] : '<span class="error">NULL</span>') : '-') . "</td>";
        echo "<td>$probleme</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verdict pour cette UE
    if ($nbNotesValides < $nbEcuesTotal) {
        echo "<p class='error'>⚠️ Cette UE a des notes incomplètes ($nbNotesValides/$nbEcuesTotal) → Étudiant DEVRAIT être éligible S2</p>";
    } else {
        echo "<p class='success'>✓ Cette UE a toutes ses notes S1</p>";
    }
}

// 5. Résumé
echo "<h2>5. Résumé et Diagnostic</h2>";

if (!empty($problemesDetectes)) {
    echo "<p class='error'>PROBLÈMES DÉTECTÉS (" . count($problemesDetectes) . "):</p>";
    echo "<ul>";
    foreach ($problemesDetectes as $p) {
        echo "<li class='error'>$p</li>";
    }
    echo "</ul>";
    echo "<p class='success'>L'étudiant DEVRAIT apparaître dans la grille de 2ème session.</p>";
    echo "<p>Si ce n'est pas le cas, la correction du modèle Deliberation.php n'est peut-être pas appliquée sur le serveur.</p>";
} else {
    echo "<p class='success'>Aucun problème détecté - Toutes les notes S1 sont complètes.</p>";
    echo "<p>L'étudiant ne devrait pas être en 2ème session sauf si moyenne < 10.</p>";
}

echo "</body></html>";
?>
