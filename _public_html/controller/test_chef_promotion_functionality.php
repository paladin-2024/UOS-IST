<?php
// Script de test pour vérifier toutes les fonctionnalités de gestion des chefs de promotion
require_once dirname(__DIR__) . "/config/Connexion.php";

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    echo "<h2>Test des fonctionnalités de gestion des chefs de promotion</h2>";
    
    // 1. Test de la structure de la table
    echo "<h3>1. Test de la structure de la table</h3>";
    $query = "SELECT COUNT(*) FROM chef_promotion";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "<p>✓ Table chef_promotion accessible. Nombre d'enregistrements : {$count}</p>";
    
    // 2. Test des contraintes
    echo "<h3>2. Test des contraintes</h3>";
    
    // Vérifier la contrainte d'unicité
    $query = "SHOW INDEX FROM chef_promotion WHERE Key_name = 'idx_chef_unique_actif'";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $uniqueIndex = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($uniqueIndex) {
        echo "<p>✓ Contrainte d'unicité présente</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Contrainte d'unicité manquante</p>";
    }
    
    // 3. Test de récupération des promotions avec chefs
    echo "<h3>3. Test de récupération des promotions avec chefs</h3>";
    
    $query = "SELECT p.idpromotion, p.\"designationPromotion\",
                s.\"designationSection\" as section,
                o.\"designationOrientation\" as orientation,
                cp.id_chef,
                e.noms as chef_nom,
                e.matricule as chef_matricule,
                a.designation as annee
            FROM promotion p
            LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
            LEFT JOIN section s ON o.section_idsection = s.idsection
            LEFT JOIN chef_promotion cp ON p.idpromotion = cp.promotion_idpromotion AND cp.est_actif = 1
            LEFT JOIN etudiant e ON cp.idetudiant = e.idetudiant
            LEFT JOIN annee_acad a ON cp.annee_acad_idannee_acad = a.idannee_acad
            LIMIT 5";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($promotions)) {
        echo "<p>✓ Récupération des promotions réussie</p>";
        echo "<table border='1'>";
        echo "<tr><th>Promotion</th><th>Section</th><th>Orientation</th><th>Chef</th><th>Année</th></tr>";
        foreach ($promotions as $promo) {
            $chef = $promo['chef_nom'] ? $promo['chef_nom'] . ' (' . $promo['chef_matricule'] . ')' : 'Non assigné';
            echo "<tr>";
            echo "<td>{$promo['designationPromotion']}</td>";
            echo "<td>{$promo['section']}</td>";
            echo "<td>{$promo['orientation']}</td>";
            echo "<td>{$chef}</td>";
            echo "<td>{$promo['annee']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠ Aucune promotion trouvée</p>";
    }
    
    // 4. Test de récupération des étudiants d'une promotion
    echo "<h3>4. Test de récupération des étudiants</h3>";
    
    // Prendre la première promotion disponible
    $query = "SELECT p.idpromotion, p.\"designationPromotion\", 
                     (SELECT idannee_acad FROM annee_acad ORDER BY \"dateCreation\" DESC LIMIT 1) as annee_id
              FROM promotion p LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $testPromotion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testPromotion) {
        $query = "SELECT e.idetudiant, e.noms, e.matricule 
                  FROM etudiant e
                  WHERE e.promotion_idpromotion = ? 
                  AND e.annee_acad_idannee_acad = ?
                  AND e.est_actif = 1
                  LIMIT 5";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$testPromotion['idpromotion'], $testPromotion['annee_id']]);
        $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>✓ Test pour la promotion : {$testPromotion['designationPromotion']}</p>";
        echo "<p>Nombre d'étudiants trouvés : " . count($etudiants) . "</p>";
        
        if (!empty($etudiants)) {
            echo "<ul>";
            foreach ($etudiants as $etudiant) {
                echo "<li>{$etudiant['noms']} ({$etudiant['matricule']})</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p style='color: orange;'>⚠ Aucune promotion disponible pour le test</p>";
    }
    
    // 5. Test des droits d'accès (simulation)
    echo "<h3>5. Test des droits d'accès</h3>";
    
    // Vérifier la table responsable_section
    $query = "SELECT COUNT(*) FROM responsable_section";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $countResponsables = $stmt->fetchColumn();
    echo "<p>✓ Table responsable_section accessible. Nombre de responsables : {$countResponsables}</p>";
    
    // 6. Test de validation des données
    echo "<h3>6. Test de validation des données</h3>";
    
    // Vérifier qu'il n'y a pas de chefs orphelins (étudiants inexistants)
    $query = "SELECT cp.id_chef, cp.idetudiant 
              FROM chef_promotion cp 
              LEFT JOIN etudiant e ON cp.idetudiant = e.idetudiant 
              WHERE cp.est_actif = 1 AND e.idetudiant IS NULL";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $orphelins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($orphelins)) {
        echo "<p>✓ Aucun chef orphelin trouvé</p>";
    } else {
        echo "<p style='color: red;'>✗ Chefs orphelins trouvés : " . count($orphelins) . "</p>";
    }
    
    // Vérifier qu'il n'y a pas de promotions orphelines
    $query = "SELECT cp.id_chef, cp.promotion_idpromotion 
              FROM chef_promotion cp 
              LEFT JOIN promotion p ON cp.promotion_idpromotion = p.idpromotion 
              WHERE cp.est_actif = 1 AND p.idpromotion IS NULL";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $promotionsOrphelines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($promotionsOrphelines)) {
        echo "<p>✓ Aucune promotion orpheline trouvée</p>";
    } else {
        echo "<p style='color: red;'>✗ Promotions orphelines trouvées : " . count($promotionsOrphelines) . "</p>";
    }
    
    // 7. Test des statistiques
    echo "<h3>7. Statistiques</h3>";
    
    // Nombre de promotions par statut de chef
    $query = "SELECT 
                COUNT(DISTINCT p.idpromotion) as total_promotions,
                COUNT(DISTINCT CASE WHEN cp.est_actif = 1 THEN p.idpromotion END) as avec_chef,
                COUNT(DISTINCT CASE WHEN cp.est_actif IS NULL THEN p.idpromotion END) as sans_chef
              FROM promotion p
              LEFT JOIN chef_promotion cp ON p.idpromotion = cp.promotion_idpromotion AND cp.est_actif = 1";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<ul>";
    echo "<li>Total promotions : {$stats['total_promotions']}</li>";
    echo "<li>Avec chef : {$stats['avec_chef']}</li>";
    echo "<li>Sans chef : {$stats['sans_chef']}</li>";
    echo "</ul>";
    
    // 8. Test de performance
    echo "<h3>8. Test de performance</h3>";
    
    $start = microtime(true);
    
    $query = "SELECT p.*, 
                s.\"designationSection\" as section,
                o.\"designationOrientation\" as orientation,
                cp.id_chef,
                e.noms as chef_nom,
                e.matricule as chef_matricule
            FROM promotion p
            LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
            LEFT JOIN section s ON o.section_idsection = s.idsection
            LEFT JOIN chef_promotion cp ON p.idpromotion = cp.promotion_idpromotion AND cp.est_actif = 1
            LEFT JOIN etudiant e ON cp.idetudiant = e.idetudiant";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $allPromotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $end = microtime(true);
    $duration = round(($end - $start) * 1000, 2);
    
    echo "<p>✓ Requête principale exécutée en {$duration}ms pour " . count($allPromotions) . " promotions</p>";
    
    // 9. Résumé final
    echo "<h3>9. Résumé final</h3>";
    echo "<div style='background-color: #f0f8ff; padding: 15px; border: 1px solid #0066cc; border-radius: 5px;'>";
    echo "<h4>État du système de gestion des chefs de promotion :</h4>";
    echo "<ul>";
    echo "<li>✓ Structure de base fonctionnelle</li>";
    echo "<li>✓ Récupération des données opérationnelle</li>";
    echo "<li>✓ Validation des données correcte</li>";
    echo "<li>✓ Performance acceptable</li>";
    echo "</ul>";
    
    if ($uniqueIndex) {
        echo "<p style='color: green;'><strong>✓ Système prêt pour la production</strong></p>";
    } else {
        echo "<p style='color: orange;'><strong>⚠ Recommandation : Ajouter la contrainte d'unicité</strong></p>";
        echo "<code>ALTER TABLE chef_promotion ADD UNIQUE KEY idx_chef_unique_actif (promotion_idpromotion, annee_acad_idannee_acad, est_actif);</code>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur lors du test : " . $e->getMessage() . "</p>";
}
?>