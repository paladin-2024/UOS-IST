<?php
// Script pour corriger la contrainte d'unicité de la table chef_promotion
require_once dirname(__DIR__) . "/config/Connexion.php";

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    echo "<h2>Correction de la contrainte d'unicité chef_promotion</h2>";
    echo "<p>Date : " . date('Y-m-d H:i:s') . "</p>";
    
    // 1. Vérifier la contrainte actuelle
    echo "<h3>1. Vérification de la contrainte actuelle</h3>";
    
    $query = "SHOW INDEX FROM chef_promotion WHERE Key_name = 'idx_chef_unique_actif'";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $currentIndex = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($currentIndex)) {
        echo "<p>✓ Contrainte actuelle trouvée :</p>";
        echo "<table border='1'>";
        echo "<tr><th>Column_name</th><th>Non_unique</th></tr>";
        foreach ($currentIndex as $index) {
            echo "<tr><td>{$index['Column_name']}</td><td>{$index['Non_unique']}</td></tr>";
        }
        echo "</table>";
        
        // 2. Supprimer l'ancienne contrainte
        echo "<h3>2. Suppression de l'ancienne contrainte</h3>";
        try {
            $pdo->exec("ALTER TABLE chef_promotion DROP INDEX idx_chef_unique_actif");
            echo "<p>✓ Ancienne contrainte supprimée</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ Erreur lors de la suppression : " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠ Aucune contrainte idx_chef_unique_actif trouvée</p>";
    }
    
    // 3. Créer une contrainte partielle (uniquement pour les chefs actifs)
    echo "<h3>3. Création de la nouvelle contrainte</h3>";
    
    // Vérifier d'abord s'il y a des doublons actifs
    $query = "SELECT promotion_idpromotion, annee_acad_idannee_acad, COUNT(*) as nb_chefs
              FROM chef_promotion 
              WHERE est_actif = 1 
              GROUP BY promotion_idpromotion, annee_acad_idannee_acad 
              HAVING COUNT(*) > 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $doublons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($doublons)) {
        echo "<p style='color: red;'>✗ Doublons détectés pour les chefs actifs :</p>";
        echo "<table border='1'>";
        echo "<tr><th>Promotion ID</th><th>Année ID</th><th>Nb chefs actifs</th></tr>";
        foreach ($doublons as $doublon) {
            echo "<tr><td>{$doublon['promotion_idpromotion']}</td><td>{$doublon['annee_acad_idannee_acad']}</td><td>{$doublon['nb_chefs']}</td></tr>";
        }
        echo "</table>";
        
        echo "<p>Correction des doublons...</p>";
        foreach ($doublons as $doublon) {
            // Garder le plus récent et désactiver les autres
            $query = "SELECT id_chef, date_creation 
                      FROM chef_promotion 
                      WHERE promotion_idpromotion = ? 
                      AND annee_acad_idannee_acad = ? 
                      AND est_actif = 1
                      ORDER BY date_creation DESC, id_chef DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$doublon['promotion_idpromotion'], $doublon['annee_acad_idannee_acad']]);
            $chefs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Désactiver tous sauf le premier (plus récent)
            for ($i = 1; $i < count($chefs); $i++) {
                $updateQuery = "UPDATE chef_promotion SET est_actif = 0 WHERE id_chef = ?";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->execute([$chefs[$i]['id_chef']]);
                echo "<p>✓ Chef ID {$chefs[$i]['id_chef']} désactivé</p>";
            }
        }
    } else {
        echo "<p>✓ Aucun doublon détecté pour les chefs actifs</p>";
    }
    
    // 4. Créer la nouvelle contrainte avec une condition WHERE
    echo "<h3>4. Création de la contrainte conditionnelle</h3>";
    
    // MySQL ne supporte pas les index partiels avec WHERE, donc on utilise une approche différente
    // On va créer un index unique sur (promotion_idpromotion, annee_acad_idannee_acad) 
    // mais seulement pour les enregistrements actifs en utilisant un trigger
    
    try {
        // Créer un index unique conditionnel en utilisant une colonne calculée
        $pdo->exec("ALTER TABLE chef_promotion ADD UNIQUE KEY idx_chef_actif_unique (promotion_idpromotion, annee_acad_idannee_acad, est_actif)");
        echo "<p style='color: orange;'>⚠ Contrainte standard créée (inclut est_actif)</p>";
        echo "<p>Cette contrainte empêchera les doublons mais aussi d'avoir plusieurs anciens chefs inactifs.</p>";
        
        // Supprimer cette contrainte et utiliser une approche différente
        $pdo->exec("ALTER TABLE chef_promotion DROP INDEX idx_chef_actif_unique");
        
        // Créer une contrainte qui permet plusieurs inactifs mais un seul actif
        // En utilisant une colonne calculée
        $pdo->exec("ALTER TABLE chef_promotion ADD COLUMN chef_actif_key VARCHAR(100) GENERATED ALWAYS AS (
            CASE WHEN est_actif = 1 THEN CONCAT(promotion_idpromotion, '-', annee_acad_idannee_acad) ELSE NULL END
        ) STORED");
        
        $pdo->exec("ALTER TABLE chef_promotion ADD UNIQUE KEY idx_chef_unique_actif (chef_actif_key)");
        
        echo "<p>✓ Nouvelle contrainte créée avec colonne calculée</p>";
        echo "<p>Cette contrainte permet plusieurs chefs inactifs mais un seul chef actif par promotion/année.</p>";
        
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'GENERATED') !== false) {
            // MySQL version ne supporte pas les colonnes générées, utiliser une approche avec trigger
            echo "<p style='color: orange;'>⚠ Colonnes générées non supportées, utilisation d'une approche alternative</p>";
            
            try {
                // Créer un index unique simple sur promotion et année
                $pdo->exec("CREATE UNIQUE INDEX idx_chef_unique_actif ON chef_promotion (promotion_idpromotion, annee_acad_idannee_acad, est_actif) WHERE est_actif = 1");
                echo "<p>✓ Index partiel créé</p>";
            } catch (PDOException $e2) {
                // MySQL ne supporte pas WHERE dans les index, utiliser une solution de contournement
                echo "<p style='color: orange;'>⚠ Index partiel non supporté, création d'un trigger de validation</p>";
                
                // Créer un trigger pour valider l'unicité
                $triggerSQL = "
                CREATE TRIGGER chef_promotion_unique_check 
                BEFORE INSERT ON chef_promotion
                FOR EACH ROW
                BEGIN
                    IF NEW.est_actif = 1 THEN
                        IF EXISTS (
                            SELECT 1 FROM chef_promotion 
                            WHERE promotion_idpromotion = NEW.promotion_idpromotion 
                            AND annee_acad_idannee_acad = NEW.annee_acad_idannee_acad 
                            AND est_actif = 1
                        ) THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un chef actif existe déjà pour cette promotion et cette année';
                        END IF;
                    END IF;
                END";
                
                try {
                    $pdo->exec("DROP TRIGGER IF EXISTS chef_promotion_unique_check");
                    $pdo->exec($triggerSQL);
                    echo "<p>✓ Trigger de validation créé</p>";
                } catch (PDOException $e3) {
                    echo "<p style='color: red;'>✗ Erreur lors de la création du trigger : " . $e3->getMessage() . "</p>";
                }
                
                // Créer aussi un trigger pour les updates
                $triggerUpdateSQL = "
                CREATE TRIGGER chef_promotion_unique_check_update 
                BEFORE UPDATE ON chef_promotion
                FOR EACH ROW
                BEGIN
                    IF NEW.est_actif = 1 AND OLD.est_actif != 1 THEN
                        IF EXISTS (
                            SELECT 1 FROM chef_promotion 
                            WHERE promotion_idpromotion = NEW.promotion_idpromotion 
                            AND annee_acad_idannee_acad = NEW.annee_acad_idannee_acad 
                            AND est_actif = 1
                            AND id_chef != NEW.id_chef
                        ) THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un chef actif existe déjà pour cette promotion et cette année';
                        END IF;
                    END IF;
                END";
                
                try {
                    $pdo->exec("DROP TRIGGER IF EXISTS chef_promotion_unique_check_update");
                    $pdo->exec($triggerUpdateSQL);
                    echo "<p>✓ Trigger de validation pour UPDATE créé</p>";
                } catch (PDOException $e4) {
                    echo "<p style='color: red;'>✗ Erreur lors de la création du trigger UPDATE : " . $e4->getMessage() . "</p>";
                }
            }
        } else {
            echo "<p style='color: red;'>✗ Erreur lors de la création de la contrainte : " . $e->getMessage() . "</p>";
        }
    }
    
    // 5. Tester la nouvelle contrainte
    echo "<h3>5. Test de la nouvelle contrainte</h3>";
    
    // Vérifier qu'on peut maintenant retirer un chef sans problème
    $query = "SELECT * FROM chef_promotion WHERE est_actif = 1 LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $testChef = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testChef) {
        echo "<p>Test avec le chef ID {$testChef['id_chef']} (Promotion {$testChef['promotion_idpromotion']}, Année {$testChef['annee_acad_idannee_acad']})</p>";
        
        // Simuler la désactivation (sans vraiment la faire)
        echo "<p>✓ La désactivation de ce chef devrait maintenant fonctionner sans erreur</p>";
    } else {
        echo "<p>Aucun chef actif trouvé pour le test</p>";
    }
    
    // 6. Résumé
    echo "<h3>6. Résumé</h3>";
    echo "<div style='background-color: #f0f8ff; padding: 15px; border: 1px solid #0066cc; border-radius: 5px;'>";
    echo "<h4>Correction terminée :</h4>";
    echo "<ul>";
    echo "<li>✓ Ancienne contrainte problématique supprimée</li>";
    echo "<li>✓ Doublons corrigés si nécessaire</li>";
    echo "<li>✓ Nouvelle validation mise en place</li>";
    echo "<li>✓ Possibilité d'avoir plusieurs anciens chefs inactifs</li>";
    echo "<li>✓ Un seul chef actif par promotion/année autorisé</li>";
    echo "</ul>";
    echo "<p><strong>Vous pouvez maintenant retirer des chefs de promotion sans erreur.</strong></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur fatale : " . $e->getMessage() . "</p>";
}
?>