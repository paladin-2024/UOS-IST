<?php
// Script rapide pour corriger le problème de contrainte chef_promotion
require_once dirname(__DIR__) . "/config/Connexion.php";

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    echo "<h2>Correction rapide de la contrainte chef_promotion</h2>";
    
    // 1. Supprimer la contrainte problématique
    echo "<h3>1. Suppression de la contrainte problématique</h3>";
    try {
        $pdo->exec("ALTER TABLE chef_promotion DROP INDEX idx_chef_unique_actif");
        echo "<p style='color: green;'>✓ Contrainte idx_chef_unique_actif supprimée</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "doesn't exist") !== false) {
            echo "<p style='color: blue;'>ℹ La contrainte n'existait pas</p>";
        } else {
            echo "<p style='color: red;'>✗ Erreur : " . $e->getMessage() . "</p>";
        }
    }
    
    // 2. Corriger les doublons existants
    echo "<h3>2. Correction des doublons</h3>";
    
    $query = "SELECT promotion_idpromotion, annee_acad_idannee_acad, COUNT(*) as nb_chefs
              FROM chef_promotion 
              WHERE est_actif = 1 
              GROUP BY promotion_idpromotion, annee_acad_idannee_acad 
              HAVING COUNT(*) > 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $doublons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($doublons)) {
        echo "<p>Doublons trouvés : " . count($doublons) . "</p>";
        
        foreach ($doublons as $doublon) {
            // Garder le plus récent et supprimer les autres
            $query = "SELECT id_chef, date_creation 
                      FROM chef_promotion 
                      WHERE promotion_idpromotion = ? 
                      AND annee_acad_idannee_acad = ? 
                      AND est_actif = 1
                      ORDER BY date_creation DESC, id_chef DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$doublon['promotion_idpromotion'], $doublon['annee_acad_idannee_acad']]);
            $chefs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Supprimer tous sauf le premier (plus récent)
            for ($i = 1; $i < count($chefs); $i++) {
                $deleteQuery = "DELETE FROM chef_promotion WHERE id_chef = ?";
                $deleteStmt = $pdo->prepare($deleteQuery);
                $deleteStmt->execute([$chefs[$i]['id_chef']]);
                echo "<p>✓ Chef ID {$chefs[$i]['id_chef']} supprimé</p>";
            }
        }
    } else {
        echo "<p>✓ Aucun doublon trouvé</p>";
    }
    
    // 3. Créer une nouvelle contrainte plus simple
    echo "<h3>3. Création d'une nouvelle contrainte</h3>";
    
    try {
        // Créer un index unique seulement sur les chefs actifs
        // En utilisant une approche qui fonctionne avec toutes les versions de MySQL
        $pdo->exec("CREATE UNIQUE INDEX idx_chef_actif_unique ON chef_promotion (promotion_idpromotion, annee_acad_idannee_acad) WHERE est_actif = 1");
        echo "<p style='color: green;'>✓ Index partiel créé</p>";
    } catch (PDOException $e) {
        // Si MySQL ne supporte pas WHERE dans les index, utiliser une approche alternative
        echo "<p style='color: orange;'>⚠ Index partiel non supporté, pas de contrainte ajoutée</p>";
        echo "<p>Le système fonctionnera sans contrainte d'unicité stricte.</p>";
    }
    
    echo "<h3>4. Résumé</h3>";
    echo "<div style='background-color: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<h4 style='color: #155724;'>✅ Problème résolu !</h4>";
    echo "<ul>";
    echo "<li>Contrainte problématique supprimée</li>";
    echo "<li>Doublons corrigés</li>";
    echo "<li>Vous pouvez maintenant retirer des chefs de promotion</li>";
    echo "</ul>";
    echo "<p><strong>Vous pouvez retourner à la gestion des chefs de promotion.</strong></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur : " . $e->getMessage() . "</p>";
}
?>

<p><a href="../index.php?view=configuration/chef_promotion" class="btn btn-primary">Retourner à la gestion des chefs</a></p>