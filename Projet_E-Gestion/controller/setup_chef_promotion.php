<?php
require_once '../config/Connexion.php';

$connexion = Connexion::getInstance()->getPDO();

echo "<h2>Configuration de la table chef_promotion</h2>";

try {
    // 1. Vérifier si la table chef_promotion existe
    $queryTableExists = "SHOW TABLES LIKE 'chef_promotion'";
    $stmtTableExists = $connexion->prepare($queryTableExists);
    $stmtTableExists->execute();
    $tableExists = $stmtTableExists->fetch();

    if (!$tableExists) {
        echo "<h3>❌ La table chef_promotion n'existe pas. Création en cours...</h3>";
        
        // Créer la table chef_promotion
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `chef_promotion` (
          `id_chef` int(11) NOT NULL AUTO_INCREMENT,
          `idetudiant` int(11) NOT NULL COMMENT 'ID de l\'étudiant chef de promotion',
          `promotion_idpromotion` int(11) NOT NULL COMMENT 'ID de la promotion',
          `annee_acad_idannee_acad` int(11) NOT NULL COMMENT 'Année académique',
          `date_nomination` date NOT NULL COMMENT 'Date de nomination',
          `est_actif` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Statut actif/inactif',
          `idUser` int(11) NOT NULL COMMENT 'Utilisateur ayant créé l\'enregistrement',
          `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id_chef`),
          UNIQUE KEY `unique_chef_promotion_annee` (`idetudiant`, `promotion_idpromotion`, `annee_acad_idannee_acad`),
          KEY `fk_chef_etudiant` (`idetudiant`),
          KEY `fk_chef_promotion` (`promotion_idpromotion`),
          KEY `fk_chef_annee_acad` (`annee_acad_idannee_acad`),
          KEY `idx_actif` (`est_actif`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Table des chefs de promotion';
        ";
        
        $connexion->exec($createTableSQL);
        echo "<p style='color: green;'>✅ Table chef_promotion créée avec succès!</p>";
        
        // Ajouter les contraintes de clés étrangères
        try {
            $constraintsSQL = "
            ALTER TABLE `chef_promotion`
              ADD CONSTRAINT `fk_chef_etudiant` FOREIGN KEY (`idetudiant`) REFERENCES `etudiant` (`idetudiant`) ON DELETE CASCADE,
              ADD CONSTRAINT `fk_chef_promotion` FOREIGN KEY (`promotion_idpromotion`) REFERENCES `promotion` (`idpromotion`) ON DELETE CASCADE,
              ADD CONSTRAINT `fk_chef_annee_acad` FOREIGN KEY (`annee_acad_idannee_acad`) REFERENCES `annee_acad` (`idannee_acad`) ON DELETE CASCADE;
            ";
            $connexion->exec($constraintsSQL);
            echo "<p style='color: green;'>✅ Contraintes de clés étrangères ajoutées!</p>";
        } catch (Exception $e) {
            echo "<p style='color: orange;'>⚠️ Contraintes non ajoutées (probablement déjà existantes): " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<h3>✅ La table chef_promotion existe déjà</h3>";
    }

    // 2. Vérifier si la table suivi_enseignements existe
    $queryTableSuiviExists = "SHOW TABLES LIKE 'suivi_enseignements'";
    $stmtTableSuiviExists = $connexion->prepare($queryTableSuiviExists);
    $stmtTableSuiviExists->execute();
    $tableSuiviExists = $stmtTableSuiviExists->fetch();

    if (!$tableSuiviExists) {
        echo "<h3>❌ La table suivi_enseignements n'existe pas. Création en cours...</h3>";
        
        // Lire et exécuter le fichier SQL
        $sqlFile = '../models/suivi_enseignements.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $connexion->exec($sql);
            echo "<p style='color: green;'>✅ Table suivi_enseignements créée avec succès!</p>";
        } else {
            echo "<p style='color: red;'>❌ Fichier SQL non trouvé: $sqlFile</p>";
        }
    } else {
        echo "<h3>✅ La table suivi_enseignements existe déjà</h3>";
    }

    // 3. Lister les étudiants disponibles pour être nommés chefs de promotion
    echo "<h3>3. Étudiants disponibles pour être nommés chefs de promotion</h3>";
    
    $queryEtudiants = "SELECT e.idetudiant, e.matricule, e.noms, p.\"designationPromotion\", aa.designation as annee_acad,
                              CASE WHEN cp.id_chef IS NOT NULL THEN 'OUI' ELSE 'NON' END as est_chef
                       FROM etudiant e
                       INNER JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                       INNER JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
                       LEFT JOIN chef_promotion cp ON e.idetudiant = cp.idetudiant 
                                                   AND cp.annee_acad_idannee_acad = e.annee_acad_idannee_acad 
                                                   AND cp.est_actif = 1
                       WHERE e.est_actif = 1
                       ORDER BY p.\"designationPromotion\", e.noms";

    $stmtEtudiants = $connexion->prepare($queryEtudiants);
    $stmtEtudiants->execute();
    $etudiants = $stmtEtudiants->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($etudiants)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>ID</th><th>Matricule</th><th>Nom</th><th>Promotion</th><th>Année Acad</th><th>Est Chef</th><th>Action</th>";
        echo "</tr>";
        
        foreach ($etudiants as $etudiant) {
            echo "<tr>";
            echo "<td>" . $etudiant['idetudiant'] . "</td>";
            echo "<td>" . $etudiant['matricule'] . "</td>";
            echo "<td>" . $etudiant['noms'] . "</td>";
            echo "<td>" . $etudiant['designationPromotion'] . "</td>";
            echo "<td>" . $etudiant['annee_acad'] . "</td>";
            echo "<td style='color: " . ($etudiant['est_chef'] === 'OUI' ? 'green' : 'red') . ";'>" . $etudiant['est_chef'] . "</td>";
            echo "<td>";
            
            if ($etudiant['est_chef'] === 'NON') {
                echo "<form method='POST' style='display: inline;'>";
                echo "<input type='hidden' name='action' value='nommer_chef'>";
                echo "<input type='hidden' name='etudiant_id' value='" . $etudiant['idetudiant'] . "'>";
                echo "<input type='hidden' name='promotion_id' value='" . $etudiant['idpromotion'] . "'>";
                echo "<input type='hidden' name='annee_acad' value='" . $etudiant['annee_acad_idannee_acad'] . "'>";
                echo "<button type='submit' style='background: green; color: white; padding: 5px 10px; border: none; cursor: pointer;'>Nommer Chef</button>";
                echo "</form>";
            } else {
                echo "<span style='color: green;'>Déjà chef</span>";
            }
            
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>Aucun étudiant trouvé!</p>";
    }

    // Traitement du formulaire pour nommer un chef de promotion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'nommer_chef') {
        try {
            $etudiantId = $_POST['etudiant_id'];
            $promotionId = $_POST['promotion_id'];
            $anneeAcadId = $_POST['annee_acad'];
            
            // Récupérer les informations de l'étudiant
            $queryEtudiantInfo = "SELECT e.*, p.idpromotion 
                                 FROM etudiant e 
                                 INNER JOIN promotion p ON e.promotion_idpromotion = p.idpromotion 
                                 WHERE e.idetudiant = :etudiant_id";
            $stmtEtudiantInfo = $connexion->prepare($queryEtudiantInfo);
            $stmtEtudiantInfo->bindParam(':etudiant_id', $etudiantId);
            $stmtEtudiantInfo->execute();
            $etudiantInfo = $stmtEtudiantInfo->fetch(PDO::FETCH_ASSOC);
            
            if (!$etudiantInfo) {
                throw new Exception("Étudiant non trouvé!");
            }
            
            // Vérifier si l'étudiant n'est pas déjà chef de promotion
            $queryVerif = "SELECT id_chef FROM chef_promotion 
                           WHERE idetudiant = :etudiant_id 
                           AND annee_acad_idannee_acad = :annee_acad 
                           AND est_actif = 1";
            $stmtVerif = $connexion->prepare($queryVerif);
            $stmtVerif->bindParam(':etudiant_id', $etudiantId);
            $stmtVerif->bindParam(':annee_acad', $anneeAcadId);
            $stmtVerif->execute();
            
            if ($stmtVerif->fetch()) {
                echo "<p style='color: red;'>❌ Cet étudiant est déjà chef de promotion!</p>";
            } else {
                // Insérer le nouveau chef de promotion
                $queryInsert = "INSERT INTO chef_promotion (idetudiant, promotion_idpromotion, annee_acad_idannee_acad, date_nomination, est_actif, \"idUser\")
                               VALUES (:etudiant_id, :promotion_id, :annee_acad, CURDATE(), 1, :user_id)";
                $stmtInsert = $connexion->prepare($queryInsert);
                $stmtInsert->bindParam(':etudiant_id', $etudiantId);
                $stmtInsert->bindParam(':promotion_id', $etudiantInfo['idpromotion']);
                $stmtInsert->bindParam(':annee_acad', $anneeAcadId);
                $stmtInsert->bindParam(':user_id', $etudiantId);
                
                if ($stmtInsert->execute()) {
                    echo "<p style='color: green;'>✅ Étudiant nommé chef de promotion avec succès!</p>";
                    echo "<script>setTimeout(function(){ window.location.reload(); }, 2000);</script>";
                } else {
                    echo "<p style='color: red;'>❌ Erreur lors de la nomination!</p>";
                }
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
        }
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur générale: " . $e->getMessage() . "</p>";
}

echo "<br><br>";
echo "<p><a href='../views/portail/student.php'>Retour à la page étudiant</a></p>";
echo "<p><a href='debug_chef_promotion.php'>Page de debug chef promotion</a></p>";
?>