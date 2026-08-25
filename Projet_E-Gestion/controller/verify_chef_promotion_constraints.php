<?php
// Script pour vérifier et corriger les contraintes de la table chef_promotion
require_once dirname(__DIR__) . "/config/Connexion.php";

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    echo "<h2>Vérification des contraintes de la table chef_promotion</h2>";
    
    // 1. Vérifier la structure de la table
    echo "<h3>1. Structure de la table chef_promotion</h3>";
    $query = "DESCRIBE chef_promotion";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($structure as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 2. Vérifier les index existants
    echo "<h3>2. Index existants</h3>";
    $query = "SHOW INDEX FROM chef_promotion";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Key_name</th><th>Column_name</th><th>Non_unique</th></tr>";
    foreach ($indexes as $index) {
        echo "<tr>";
        echo "<td>{$index['Key_name']}</td>";
        echo "<td>{$index['Column_name']}</td>";
        echo "<td>{$index['Non_unique']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 3. Vérifier s'il y a des doublons (plusieurs chefs actifs pour une même promotion/année)
    echo "<h3>3. Vérification des doublons</h3>";
    $query = "SELECT promotion_idpromotion, annee_acad_idannee_acad, COUNT(*) as nb_chefs
              FROM chef_promotion 
              WHERE est_actif = 1 
              GROUP BY promotion_idpromotion, annee_acad_idannee_acad 
              HAVING COUNT(*) > 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $doublons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($doublons)) {
        echo "<p style='color: green;'>✓ Aucun doublon trouvé. Chaque promotion a au maximum un chef actif.</p>";
    } else {
        echo "<p style='color: red;'>✗ Doublons trouvés :</p>";
        echo "<table border='1'>";
        echo "<tr><th>Promotion ID</th><th>Année académique ID</th><th>Nombre de chefs</th></tr>";
        foreach ($doublons as $doublon) {
            echo "<tr>";
            echo "<td>{$doublon['promotion_idpromotion']}</td>";
            echo "<td>{$doublon['annee_acad_idannee_acad']}</td>";
            echo "<td>{$doublon['nb_chefs']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Proposer de corriger les doublons
        echo "<h4>Correction des doublons</h4>";
        foreach ($doublons as $doublon) {
            $query = "SELECT cp.*, e.noms, e.matricule, cp.date_creation
                      FROM chef_promotion cp
                      INNER JOIN etudiant e ON cp.idetudiant = e.idetudiant
                      WHERE cp.promotion_idpromotion = ? 
                      AND cp.annee_acad_idannee_acad = ? 
                      AND cp.est_actif = 1
                      ORDER BY cp.date_creation DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$doublon['promotion_idpromotion'], $doublon['annee_acad_idannee_acad']]);
            $chefs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<p>Promotion {$doublon['promotion_idpromotion']}, Année {$doublon['annee_acad_idannee_acad']} :</p>";
            echo "<ul>";
            foreach ($chefs as $i => $chef) {
                $status = $i === 0 ? "(GARDER - plus récent)" : "(DÉSACTIVER)";
                echo "<li>{$chef['noms']} ({$chef['matricule']}) - Créé le {$chef['date_creation']} {$status}</li>";
            }
            echo "</ul>";
        }
    }
    
    // 4. Vérifier la contrainte d'unicité
    echo "<h3>4. Contrainte d'unicité</h3>";
    $hasUniqueConstraint = false;
    foreach ($indexes as $index) {
        if ($index['Key_name'] === 'idx_chef_unique_actif' && $index['Non_unique'] == 0) {
            $hasUniqueConstraint = true;
            break;
        }
    }
    
    if ($hasUniqueConstraint) {
        echo "<p style='color: green;'>✓ La contrainte d'unicité existe.</p>";
    } else {
        echo "<p style='color: orange;'>⚠ La contrainte d'unicité n'existe pas ou n'est pas correcte.</p>";
        echo "<p>Pour l'ajouter, exécutez :</p>";
        echo "<code>ALTER TABLE chef_promotion ADD UNIQUE KEY idx_chef_unique_actif (promotion_idpromotion, annee_acad_idannee_acad, est_actif);</code>";
    }
    
    // 5. Statistiques générales
    echo "<h3>5. Statistiques</h3>";
    
    // Nombre total de chefs actifs
    $query = "SELECT COUNT(*) as total_chefs_actifs FROM chef_promotion WHERE est_actif = 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $totalChefs = $stmt->fetchColumn();
    
    // Nombre de promotions avec chef
    $query = "SELECT COUNT(DISTINCT promotion_idpromotion) as promotions_avec_chef 
              FROM chef_promotion WHERE est_actif = 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $promotionsAvecChef = $stmt->fetchColumn();
    
    // Nombre total de promotions
    $query = "SELECT COUNT(*) as total_promotions FROM promotion";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $totalPromotions = $stmt->fetchColumn();
    
    echo "<ul>";
    echo "<li>Total des chefs actifs : {$totalChefs}</li>";
    echo "<li>Promotions avec chef : {$promotionsAvecChef}</li>";
    echo "<li>Total des promotions : {$totalPromotions}</li>";
    echo "<li>Promotions sans chef : " . ($totalPromotions - $promotionsAvecChef) . "</li>";
    echo "</ul>";
    
    echo "<h3>6. Test de la contrainte</h3>";
    echo "<p>Pour tester la contrainte, essayez d'insérer un doublon :</p>";
    echo "<code>
    INSERT INTO chef_promotion (promotion_idpromotion, idetudiant, annee_acad_idannee_acad, date_nomination, est_actif, \"idUser\") 
    VALUES (1, 1, 1, CURRENT_DATE, 1, 1);
    </code>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur : " . $e->getMessage() . "</p>";
}
?>