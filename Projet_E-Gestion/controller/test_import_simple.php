<?php
// Test simple de l'import sans interface
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once dirname(__DIR__) . '/models/GrilleAncienne.php';
    
    echo "<h3>Test Import Grille Ancienne</h3>";
    
    $grilleAncienne = new GrilleAncienne();
    
    // Test 1: Créer les tables
    echo "<h4>1. Création des tables</h4>";
    $grilleAncienne->createTablesIfNotExists();
    echo "✅ Tables créées<br>";
    
    // Test 2: Créer un import test
    echo "<h4>2. Création d'un import test</h4>";
    $importData = [
        'annee_academique' => '2019-2020',
        'session' => 'principale',
        'semestre' => 'S1',
        'promotion' => 'L1 TEST',
        'fichier_origine' => 'test.xlsx',
        'mapping_config' => []
    ];
    
    $importId = $grilleAncienne->createImport($importData);
    echo "✅ Import créé avec ID: $importId<br>";
    
    // Test 3: Créer une UE test
    echo "<h4>3. Création d'une UE test</h4>";
    $ueData = [
        'code_ue' => 'TEST01',
        'designation_ue' => 'UE Test',
        'credits' => 3,
        'ordre_affichage' => 0
    ];
    
    $ueId = $grilleAncienne->insertUE($importId, $ueData);
    echo "✅ UE créée avec ID: $ueId<br>";
    
    // Test 4: Créer une ECUE test
    echo "<h4>4. Création d'une ECUE test</h4>";
    $ecueData = [
        'code_ecue' => 'TEST01_01',
        'designation_ecue' => 'ECUE Test',
        'coefficient' => 1,
        'ordre_affichage' => 0
    ];
    
    $ecueId = $grilleAncienne->insertECUE($ueId, $ecueData);
    echo "✅ ECUE créée avec ID: $ecueId<br>";
    
    // Test 5: Créer un étudiant test
    echo "<h4>5. Création d'un étudiant test</h4>";
    $etudiantData = [
        'matricule' => 'TEST001',
        'noms' => 'Etudiant Test',
        'ordre_affichage' => 0
    ];
    
    $etudiantId = $grilleAncienne->insertEtudiant($importId, $etudiantData);
    echo "✅ Etudiant créé avec ID: $etudiantId<br>";
    
    // Test 6: Créer une note test
    echo "<h4>6. Création d'une note test</h4>";
    $noteData = [
        'note_cc' => null,
        'note_examen' => null,
        'note_finale' => 15.5
    ];
    
    $grilleAncienne->insertNote($etudiantId, $ecueId, $noteData);
    echo "✅ Note créée<br>";
    
    // Test 7: Calculer les moyennes (c'est ici que l'erreur SQL peut survenir)
    echo "<h4>7. Calcul des moyennes</h4>";
    $grilleAncienne->calculerMoyennes($importId);
    echo "✅ Moyennes calculées<br>";
    
    // Test 8: Récupérer les statistiques
    echo "<h4>8. Récupération des statistiques</h4>";
    $import = $grilleAncienne->getImportById($importId);
    echo "✅ Statistiques récupérées:<br>";
    echo "- Étudiants: " . $import['nombre_etudiants'] . "<br>";
    echo "- UE: " . $import['nombre_ues'] . "<br>";
    echo "- ECUE: " . $import['nombre_ecues'] . "<br>";
    
    // Test 9: Nettoyer (supprimer l'import test)
    echo "<h4>9. Nettoyage</h4>";
    $grilleAncienne->deleteImport($importId);
    echo "✅ Import test supprimé<br>";
    
    echo "<h4>✅ Tous les tests sont passés avec succès !</h4>";
    
} catch (Exception $e) {
    echo "<h4>❌ Erreur durant les tests:</h4>";
    echo "<div style='background: #ffeeee; padding: 10px; border: 1px solid #ff0000;'>";
    echo "<strong>Message:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>Fichier:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Ligne:</strong> " . $e->getLine() . "<br>";
    echo "<strong>Trace:</strong><br><pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}
?>
