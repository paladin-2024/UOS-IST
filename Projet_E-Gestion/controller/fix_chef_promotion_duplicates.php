<?php
// Script pour corriger les doublons dans la table chef_promotion
require_once dirname(__DIR__) . "/config/Connexion.php";

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    echo "<h2>Correction des doublons dans chef_promotion</h2>";
    
    // Commencer une transaction
    $pdo->beginTransaction();
    
    // 1. Identifier les doublons
    $query = "SELECT promotion_idpromotion, annee_acad_idannee_acad, COUNT(*) as nb_chefs
              FROM chef_promotion 
              WHERE est_actif = 1 
              GROUP BY promotion_idpromotion, annee_acad_idannee_acad 
              HAVING COUNT(*) > 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $doublons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($doublons)) {
        echo "<p style='color: green;'>✓ Aucun doublon trouvé.</p>";
        $pdo->rollback();
        exit;
    }
    
    echo "<p>Doublons trouvés : " . count($doublons) . "</p>";
    
    $corrections = 0;
    
    foreach ($doublons as $doublon) {
        echo "<h3>Correction pour Promotion {$doublon['promotion_idpromotion']}, Année {$doublon['annee_acad_idannee_acad']}</h3>";
        
        // Récupérer tous les chefs actifs pour cette promotion/année, triés par date de création (le plus récent en premier)
        $query = "SELECT cp.*, e.noms, e.matricule
                  FROM chef_promotion cp
                  INNER JOIN etudiant e ON cp.idetudiant = e.idetudiant
                  WHERE cp.promotion_idpromotion = ? 
                  AND cp.annee_acad_idannee_acad = ? 
                  AND cp.est_actif = 1
                  ORDER BY cp.date_creation DESC, cp.id_chef DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$doublon['promotion_idpromotion'], $doublon['annee_acad_idannee_acad']]);
        $chefs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Garder le premier (plus récent) et désactiver les autres
        foreach ($chefs as $i => $chef) {
            if ($i === 0) {
                echo "<p style='color: green;'>✓ Gardé : {$chef['noms']} ({$chef['matricule']}) - ID: {$chef['id_chef']}</p>";
            } else {
                // Désactiver ce chef
                $updateQuery = "UPDATE chef_promotion SET est_actif = 0 WHERE id_chef = ?";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->execute([$chef['id_chef']]);
                
                echo "<p style='color: orange;'>⚠ Désactivé : {$chef['noms']} ({$chef['matricule']}) - ID: {$chef['id_chef']}</p>";
                $corrections++;
            }
        }
    }
    
    // Vérifier qu'il n'y a plus de doublons
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $doublonsRestants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($doublonsRestants)) {
        echo "<p style='color: green;'>✓ Tous les doublons ont été corrigés. {$corrections} enregistrements désactivés.</p>";
        
        // Valider la transaction
        $pdo->commit();
        echo "<p style='color: green;'>✓ Transaction validée.</p>";
        
        // Essayer d'ajouter la contrainte d'unicité si elle n'existe pas
        try {
            $pdo->exec("ALTER TABLE chef_promotion ADD UNIQUE KEY idx_chef_unique_actif (promotion_idpromotion, annee_acad_idannee_acad, est_actif)");
            echo "<p style='color: green;'>✓ Contrainte d'unicité ajoutée.</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "<p style='color: blue;'>ℹ La contrainte d'unicité existe déjà.</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Impossible d'ajouter la contrainte d'unicité : " . $e->getMessage() . "</p>";
            }
        }
        
    } else {
        echo "<p style='color: red;'>✗ Il reste encore des doublons après correction.</p>";
        $pdo->rollback();
        echo "<p style='color: red;'>✗ Transaction annulée.</p>";
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "<p style='color: red;'>Erreur : " . $e->getMessage() . "</p>";
}
?>