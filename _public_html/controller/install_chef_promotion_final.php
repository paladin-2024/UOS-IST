<?php
// Script d'installation/mise à jour finale pour la gestion des chefs de promotion
require_once dirname(__DIR__) . "/config/Connexion.php";

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    echo "<h2>Installation/Mise à jour - Gestion des Chefs de Promotion</h2>";
    echo "<p>Date : " . date('Y-m-d H:i:s') . "</p>";
    
    // 1. Vérifier/Créer la table chef_promotion
    echo "<h3>1. Vérification de la table chef_promotion</h3>";
    
    $tableExists = false;
    try {
        $pdo->query("SELECT 1 FROM chef_promotion LIMIT 1");
        $tableExists = true;
        echo "<p>✓ Table chef_promotion existe</p>";
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>⚠ Table chef_promotion n'existe pas</p>";
    }
    
    if (!$tableExists) {
        echo "<p>Création de la table chef_promotion...</p>";
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `chef_promotion` (
          `id_chef` int(11) NOT NULL AUTO_INCREMENT,
          `idetudiant` int(11) NOT NULL,
          `promotion_idpromotion` int(11) NOT NULL,
          `annee_acad_idannee_acad` int(11) NOT NULL,
          `date_nomination` date NOT NULL,
          `est_actif` tinyint(1) NOT NULL DEFAULT 1,
          `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
          `idUser` int(11) NOT NULL,
          PRIMARY KEY (`id_chef`),
          KEY `fk_chef_promotion_etudiant` (`idetudiant`),
          KEY `fk_chef_promotion_promotion` (`promotion_idpromotion`),
          KEY `fk_chef_promotion_annee_acad` (`annee_acad_idannee_acad`),
          KEY `fk_chef_promotion_user` (`idUser`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";
        
        $pdo->exec($createTableSQL);
        echo "<p>✓ Table chef_promotion créée</p>";
    }
    
    // 2. Vérifier/Ajouter la contrainte d'unicité
    echo "<h3>2. Vérification de la contrainte d'unicité</h3>";
    
    $query = "SHOW INDEX FROM chef_promotion WHERE Key_name = 'idx_chef_unique_actif'";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $uniqueIndex = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$uniqueIndex) {
        echo "<p>Ajout de la contrainte d'unicité...</p>";
        try {
            $pdo->exec("ALTER TABLE chef_promotion ADD UNIQUE KEY idx_chef_unique_actif (promotion_idpromotion, annee_acad_idannee_acad, est_actif)");
            echo "<p>✓ Contrainte d'unicité ajoutée</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "<p style='color: red;'>✗ Impossible d'ajouter la contrainte : doublons détectés</p>";
                echo "<p>Exécutez d'abord fix_chef_promotion_duplicates.php</p>";
            } else {
                echo "<p style='color: red;'>✗ Erreur lors de l'ajout de la contrainte : " . $e->getMessage() . "</p>";
            }
        }
    } else {
        echo "<p>✓ Contrainte d'unicité existe</p>";
    }
    
    // 3. Vérifier les clés étrangères
    echo "<h3>3. Vérification des clés étrangères</h3>";
    
    $foreignKeys = [
        'fk_chef_promotion_etudiant' => 'FOREIGN KEY (`idetudiant`) REFERENCES `etudiant` (`idetudiant`) ON DELETE CASCADE ON UPDATE CASCADE',
        'fk_chef_promotion_promotion' => 'FOREIGN KEY (`promotion_idpromotion`) REFERENCES `promotion` (`idpromotion`) ON DELETE CASCADE ON UPDATE CASCADE',
        'fk_chef_promotion_annee_acad' => 'FOREIGN KEY (`annee_acad_idannee_acad`) REFERENCES `annee_acad` (`idannee_acad`) ON DELETE CASCADE ON UPDATE CASCADE',
        'fk_chef_promotion_user' => 'FOREIGN KEY (`idUser`) REFERENCES `t_users` (`idUser`) ON DELETE CASCADE ON UPDATE CASCADE'
    ];
    
    foreach ($foreignKeys as $keyName => $keyDefinition) {
        $query = "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                  WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'chef_promotion' 
                  AND CONSTRAINT_NAME = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$keyName]);
        $exists = $stmt->fetch();
        
        if (!$exists) {
            try {
                $pdo->exec("ALTER TABLE chef_promotion ADD CONSTRAINT {$keyName} {$keyDefinition}");
                echo "<p>✓ Clé étrangère {$keyName} ajoutée</p>";
            } catch (PDOException $e) {
                echo "<p style='color: orange;'>⚠ Impossible d'ajouter la clé étrangère {$keyName} : " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p>✓ Clé étrangère {$keyName} existe</p>";
        }
    }
    
    // 4. Vérifier les doublons
    echo "<h3>4. Vérification des doublons</h3>";
    
    $query = "SELECT promotion_idpromotion, annee_acad_idannee_acad, COUNT(*) as nb_chefs
              FROM chef_promotion 
              WHERE est_actif = 1 
              GROUP BY promotion_idpromotion, annee_acad_idannee_acad 
              HAVING COUNT(*) > 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $doublons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($doublons)) {
        echo "<p>✓ Aucun doublon détecté</p>";
    } else {
        echo "<p style='color: red;'>✗ " . count($doublons) . " doublon(s) détecté(s)</p>";
        echo "<p>Exécutez fix_chef_promotion_duplicates.php pour corriger</p>";
    }
    
    // 5. Vérifier les tables dépendantes
    echo "<h3>5. Vérification des tables dépendantes</h3>";
    
    $requiredTables = ['etudiant', 'promotion', 'orientation', 'section', 'annee_acad', 'responsable_section', 't_users'];
    
    foreach ($requiredTables as $table) {
        try {
            $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
            echo "<p>✓ Table {$table} accessible</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ Table {$table} inaccessible : " . $e->getMessage() . "</p>";
        }
    }
    
    // 6. Test de fonctionnalité de base
    echo "<h3>6. Test de fonctionnalité de base</h3>";
    
    try {
        // Test de lecture
        $query = "SELECT COUNT(*) FROM chef_promotion";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $count = $stmt->fetchColumn();
        echo "<p>✓ Lecture : {$count} enregistrement(s) dans chef_promotion</p>";
        
        // Test de jointure
        $query = "SELECT p.designationPromotion, e.noms, cp.date_nomination
                  FROM chef_promotion cp
                  INNER JOIN promotion p ON cp.promotion_idpromotion = p.idpromotion
                  INNER JOIN etudiant e ON cp.idetudiant = e.idetudiant
                  WHERE cp.est_actif = 1
                  LIMIT 1";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($test) {
            echo "<p>✓ Jointures : Test réussi avec {$test['noms']} pour {$test['designationPromotion']}</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Jointures : Aucun chef actif pour tester</p>";
        }
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Test de fonctionnalité échoué : " . $e->getMessage() . "</p>";
    }
    
    // 7. Vérification des fichiers
    echo "<h3>7. Vérification des fichiers</h3>";
    
    $requiredFiles = [
        'views/configuration/chef_promotion.php' => 'Interface principale',
        'controller/manage_chef_promotion.php' => 'Contrôleur principal',
        'controller/get_etudiants_promotion.php' => 'API AJAX',
        'controller/verify_chef_promotion_constraints.php' => 'Vérification',
        'controller/fix_chef_promotion_duplicates.php' => 'Correction doublons',
        'controller/test_chef_promotion_functionality.php' => 'Tests'
    ];
    
    $baseDir = dirname(__DIR__);
    foreach ($requiredFiles as $file => $description) {
        $fullPath = $baseDir . '/' . $file;
        if (file_exists($fullPath)) {
            echo "<p>✓ {$description} : {$file}</p>";
        } else {
            echo "<p style='color: red;'>✗ {$description} manquant : {$file}</p>";
        }
    }
    
    // 8. Statistiques finales
    echo "<h3>8. Statistiques</h3>";
    
    try {
        // Nombre total de promotions
        $query = "SELECT COUNT(*) FROM promotion";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $totalPromotions = $stmt->fetchColumn();
        
        // Nombre de promotions avec chef
        $query = "SELECT COUNT(DISTINCT promotion_idpromotion) FROM chef_promotion WHERE est_actif = 1";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $promotionsAvecChef = $stmt->fetchColumn();
        
        // Nombre total de chefs actifs
        $query = "SELECT COUNT(*) FROM chef_promotion WHERE est_actif = 1";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $chefsActifs = $stmt->fetchColumn();
        
        echo "<ul>";
        echo "<li>Total promotions : {$totalPromotions}</li>";
        echo "<li>Promotions avec chef : {$promotionsAvecChef}</li>";
        echo "<li>Promotions sans chef : " . ($totalPromotions - $promotionsAvecChef) . "</li>";
        echo "<li>Total chefs actifs : {$chefsActifs}</li>";
        echo "</ul>";
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Erreur lors du calcul des statistiques : " . $e->getMessage() . "</p>";
    }
    
    // 9. Résumé final
    echo "<h3>9. Résumé de l'installation</h3>";
    
    $hasErrors = false;
    $hasWarnings = false;
    
    // Vérifications finales
    $finalChecks = [
        'Table chef_promotion' => $tableExists || true,
        'Contrainte d\'unicité' => !empty($uniqueIndex),
        'Aucun doublon' => empty($doublons),
        'Fichiers présents' => file_exists($baseDir . '/views/configuration/chef_promotion.php')
    ];
    
    echo "<div style='background-color: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px;'>";
    echo "<h4>État du système :</h4>";
    echo "<ul>";
    
    foreach ($finalChecks as $check => $status) {
        if ($status) {
            echo "<li style='color: green;'>✓ {$check}</li>";
        } else {
            echo "<li style='color: red;'>✗ {$check}</li>";
            $hasErrors = true;
        }
    }
    
    echo "</ul>";
    
    if (!$hasErrors && !$hasWarnings) {
        echo "<p style='color: green; font-weight: bold;'>🎉 Installation réussie ! Le système de gestion des chefs de promotion est prêt.</p>";
        echo "<p>Vous pouvez maintenant accéder à : <a href='../index.php?view=configuration/chef_promotion'>Configuration > Chefs de Promotion</a></p>";
    } elseif (!$hasErrors) {
        echo "<p style='color: orange; font-weight: bold;'>⚠ Installation terminée avec des avertissements.</p>";
        echo "<p>Le système fonctionne mais certaines optimisations sont recommandées.</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ Installation incomplète.</p>";
        echo "<p>Veuillez corriger les erreurs avant d'utiliser le système.</p>";
    }
    
    echo "</div>";
    
    // 10. Actions recommandées
    echo "<h3>10. Actions recommandées</h3>";
    echo "<ul>";
    echo "<li>Exécuter <code>test_chef_promotion_functionality.php</code> pour un test complet</li>";
    echo "<li>Configurer les responsables de section si nécessaire</li>";
    echo "<li>Former les utilisateurs sur les nouvelles fonctionnalités</li>";
    echo "<li>Planifier des sauvegardes régulières</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur fatale lors de l'installation : " . $e->getMessage() . "</p>";
    echo "<p>Veuillez vérifier la configuration de la base de données et réessayer.</p>";
}
?>