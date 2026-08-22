<?php
error_reporting(E_ALL); ini_set("display_errors", 1);
require_once 'config/Connexion.php';
require_once 'models/Agent.php';

$agent = new Agent();

echo "<h1>Diagnostic des Enseignants</h1>";

// 1. Vérifier tous les agents avec type_agent = 'Enseignant'
echo "<h2>1. Agents avec type_agent = 'Enseignant'</h2>";
$enseignants = $agent->getAgentsByType('Enseignant');
echo "<p>Nombre d'enseignants trouvés : " . count($enseignants) . "</p>";

if (count($enseignants) > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Noms</th><th>Type Agent</th><th>Grade</th><th>Structure</th><th>Service</th></tr>";
    foreach ($enseignants as $ens) {
        echo "<tr>";
        echo "<td>" . $ens['idAgent'] . "</td>";
        echo "<td>" . $ens['noms'] . "</td>";
        echo "<td>" . $ens['type_agent'] . "</td>";
        echo "<td>" . ($ens['gradeDesignation'] ?? 'N/A') . "</td>";
        echo "<td>" . ($ens['designationStructure'] ?? 'N/A') . "</td>";
        echo "<td>" . ($ens['serviceDesignation'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Aucun enseignant trouvé avec type_agent = 'Enseignant'</p>";
}

// 2. Vérifier tous les agents sans filtre de type
echo "<h2>2. Tous les agents (sans filtre de type)</h2>";
$db = Connexion::getInstance()->getPDO();
$query = "SELECT a.*, g.designation as gradeDesignation, str.designation as designationStructure, s.designation as serviceDesignation 
          FROM agent a 
          LEFT JOIN grade g ON a.grade_id = g.idgrade 
          LEFT JOIN structure str ON a.idStructure = str.idStructure 
          LEFT JOIN service s ON a.idService = s.idService 
          ORDER BY a.noms";
$stmt = $db->prepare($query);
$stmt->execute();
$tous_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Nombre total d'agents : " . count($tous_agents) . "</p>";

// 3. Analyser les types d'agents
echo "<h2>3. Répartition par type d'agent</h2>";
$types = [];
foreach ($tous_agents as $agent_item) {
    $type = $agent_item['type_agent'] ?? 'NULL';
    if (!isset($types[$type])) {
        $types[$type] = 0;
    }
    $types[$type]++;
}

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Type Agent</th><th>Nombre</th></tr>";
foreach ($types as $type => $count) {
    echo "<tr><td>" . $type . "</td><td>" . $count . "</td></tr>";
}
echo "</table>";

// 4. Vérifier les agents qui pourraient être des enseignants mais n'ont pas le bon type
echo "<h2>4. Agents potentiellement enseignants (par grade ou nom)</h2>";
$potentiels_enseignants = [];
foreach ($tous_agents as $agent_item) {
    $grade = strtolower($agent_item['gradeDesignation'] ?? '');
    $nom = strtolower($agent_item['noms'] ?? '');
    
    // Rechercher des mots-clés qui indiquent un enseignant
    $mots_cles_enseignant = ['professeur', 'prof', 'docteur', 'dr', 'assistant', 'chef de travaux', 'ct', 'maitre', 'enseignant'];
    $est_potentiel_enseignant = false;
    
    foreach ($mots_cles_enseignant as $mot) {
        if (strpos($grade, $mot) !== false || strpos($nom, $mot) !== false) {
            $est_potentiel_enseignant = true;
            break;
        }
    }
    
    if ($est_potentiel_enseignant && $agent_item['type_agent'] !== 'Enseignant') {
        $potentiels_enseignants[] = $agent_item;
    }
}

if (count($potentiels_enseignants) > 0) {
    echo "<p>Agents qui pourraient être des enseignants mais n'ont pas type_agent = 'Enseignant' : " . count($potentiels_enseignants) . "</p>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Noms</th><th>Type Agent Actuel</th><th>Grade</th><th>Action</th></tr>";
    foreach ($potentiels_enseignants as $ens) {
        echo "<tr>";
        echo "<td>" . $ens['idAgent'] . "</td>";
        echo "<td>" . $ens['noms'] . "</td>";
        echo "<td>" . ($ens['type_agent'] ?? 'NULL') . "</td>";
        echo "<td>" . ($ens['gradeDesignation'] ?? 'N/A') . "</td>";
        echo "<td><button onclick='corrigerTypeAgent(" . $ens['idAgent'] . ")'>Corriger en Enseignant</button></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucun agent potentiellement enseignant trouvé avec un mauvais type.</p>";
}

// 5. Vérifier les problèmes de jointure
echo "<h2>5. Vérification des problèmes de jointure</h2>";
$query_problemes = "SELECT a.idAgent, a.noms, a.type_agent, a.grade_id, a.idStructure, a.idService,
                    CASE WHEN g.idgrade IS NULL THEN 'Grade manquant' ELSE 'Grade OK' END as statut_grade,
                    CASE WHEN str.idStructure IS NULL THEN 'Structure manquante' ELSE 'Structure OK' END as statut_structure,
                    CASE WHEN s.idService IS NULL THEN 'Service manquant' ELSE 'Service OK' END as statut_service
                    FROM agent a 
                    LEFT JOIN grade g ON a.grade_id = g.idgrade 
                    LEFT JOIN structure str ON a.idStructure = str.idStructure 
                    LEFT JOIN service s ON a.idService = s.idService 
                    WHERE a.type_agent = 'Enseignant'
                    AND (g.idgrade IS NULL OR str.idStructure IS NULL)";

$stmt_problemes = $db->prepare($query_problemes);
$stmt_problemes->execute();
$problemes = $stmt_problemes->fetchAll(PDO::FETCH_ASSOC);

if (count($problemes) > 0) {
    echo "<p style='color: orange;'>Enseignants avec des problèmes de jointure : " . count($problemes) . "</p>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Noms</th><th>Statut Grade</th><th>Statut Structure</th><th>Statut Service</th></tr>";
    foreach ($problemes as $pb) {
        echo "<tr>";
        echo "<td>" . $pb['idAgent'] . "</td>";
        echo "<td>" . $pb['noms'] . "</td>";
        echo "<td>" . $pb['statut_grade'] . "</td>";
        echo "<td>" . $pb['statut_structure'] . "</td>";
        echo "<td>" . $pb['statut_service'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: green;'>Aucun problème de jointure détecté pour les enseignants.</p>";
}

// 6. Proposer des solutions
echo "<h2>6. Solutions proposées</h2>";
echo "<div style='background-color: #f0f0f0; padding: 10px; margin: 10px 0;'>";
echo "<h3>Solutions possibles :</h3>";
echo "<ol>";
echo "<li><strong>Corriger le type d'agent :</strong> Si des agents sont des enseignants mais n'ont pas le bon type_agent, utilisez les boutons 'Corriger en Enseignant' ci-dessus.</li>";
echo "<li><strong>Vérifier les grades manquants :</strong> Assurez-vous que tous les enseignants ont un grade valide.</li>";
echo "<li><strong>Vérifier les structures manquantes :</strong> Assurez-vous que tous les enseignants sont affectés à une structure valide.</li>";
echo "<li><strong>Requête SQL de correction :</strong> Vous pouvez exécuter cette requête pour corriger automatiquement certains cas :</li>";
echo "</ol>";

echo "<textarea style='width: 100%; height: 100px;'>";
echo "-- Corriger les agents qui ont un grade d'enseignant mais pas le bon type_agent\n";
echo "UPDATE agent SET type_agent = 'Enseignant' \n";
echo "WHERE grade_id IN (\n";
echo "    SELECT idgrade FROM grade \n";
echo "    WHERE LOWER(designation) LIKE '%professeur%' \n";
echo "    OR LOWER(designation) LIKE '%assistant%' \n";
echo "    OR LOWER(designation) LIKE '%chef de travaux%'\n";
echo "    OR LOWER(designation) LIKE '%docteur%'\n";
echo ") AND type_agent != 'Enseignant';";
echo "</textarea>";
echo "</div>";
?>

<script>
function corrigerTypeAgent(idAgent) {
    if (confirm('Êtes-vous sûr de vouloir changer le type de cet agent en "Enseignant" ?')) {
        // Créer une requête AJAX pour corriger le type d'agent
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'corriger_type_agent.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                alert('Type d\'agent corrigé avec succès !');
                location.reload();
            }
        };
        xhr.send('idAgent=' + idAgent + '&type_agent=Enseignant');
    }
}
</script>

<style>
table {
    border-collapse: collapse;
    width: 100%;
    margin: 10px 0;
}
th, td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
}
th {
    background-color: #f2f2f2;
}
button {
    background-color: #4CAF50;
    color: white;
    padding: 5px 10px;
    border: none;
    cursor: pointer;
    border-radius: 3px;
}
button:hover {
    background-color: #45a049;
}
</style>