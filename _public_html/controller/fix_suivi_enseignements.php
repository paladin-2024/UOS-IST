<?php
session_start();
require_once '../config/Connexion.php';

// Vérifier si l'utilisateur est connecté et est un étudiant
if (!isset($_SESSION['student_id'])) {
    echo "Session non initialisée. Veuillez vous connecter.";
    exit();
}

$connexion = Connexion::getInstance()->getPDO();

echo "<h2>Diagnostic et correction du problème de suivi des enseignements</h2>";

// 1. Vérifier les données de session
echo "<h3>1. Données de session</h3>";
echo "Student ID: " . ($_SESSION['student_id'] ?? 'Non défini') . "<br>";
echo "Student Matricule: " . ($_SESSION['student_matricule'] ?? 'Non défini') . "<br>";
echo "Année académique: " . ($_SESSION['annee_acad'] ?? 'Non défini') . "<br>";
echo "Promotion ID: " . ($_SESSION['promotion_id'] ?? 'Non défini') . "<br>";

// 2. Vérifier l'année académique active
echo "<h3>2. Année académique active</h3>";
$queryAnneeActive = "SELECT * FROM annee_acad WHERE est_active = 1";
$stmtAnneeActive = $connexion->prepare($queryAnneeActive);
$stmtAnneeActive->execute();
$anneeActive = $stmtAnneeActive->fetch(PDO::FETCH_ASSOC);

if ($anneeActive) {
    echo "Année académique active trouvée: " . $anneeActive['designation'] . " (ID: " . $anneeActive['idannee_acad'] . ")<br>";
    
    // Mettre à jour la session si nécessaire
    if (!isset($_SESSION['annee_acad']) || $_SESSION['annee_acad'] != $anneeActive['idannee_acad']) {
        $_SESSION['annee_acad'] = $anneeActive['idannee_acad'];
        echo "<span style='color: green;'>Session mise à jour avec l'année académique active.</span><br>";
    }
} else {
    echo "<span style='color: red;'>Aucune année académique active trouvée!</span><br>";
    
    // Essayer de trouver la dernière année académique
    $queryDerniereAnnee = "SELECT * FROM annee_acad ORDER BY idannee_acad DESC LIMIT 1";
    $stmtDerniereAnnee = $connexion->prepare($queryDerniereAnnee);
    $stmtDerniereAnnee->execute();
    $derniereAnnee = $stmtDerniereAnnee->fetch(PDO::FETCH_ASSOC);
    
    if ($derniereAnnee) {
        echo "Dernière année académique trouvée: " . $derniereAnnee['designation'] . " (ID: " . $derniereAnnee['idannee_acad'] . ")<br>";
        $_SESSION['annee_acad'] = $derniereAnnee['idannee_acad'];
        echo "<span style='color: orange;'>Session mise à jour avec la dernière année académique.</span><br>";
    }
}

// 3. Vérifier les données de l'étudiant
echo "<h3>3. Données de l'étudiant</h3>";
$queryEtudiant = "SELECT e.*, p.designationPromotion 
                  FROM etudiant e 
                  LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion 
                  WHERE e.idetudiant = :student_id";
$stmtEtudiant = $connexion->prepare($queryEtudiant);
$stmtEtudiant->bindParam(':student_id', $_SESSION['student_id']);
$stmtEtudiant->execute();
$etudiant = $stmtEtudiant->fetch(PDO::FETCH_ASSOC);

if ($etudiant) {
    echo "Étudiant trouvé: " . $etudiant['noms'] . "<br>";
    echo "Promotion: " . ($etudiant['designationPromotion'] ?? 'Non définie') . "<br>";
    echo "Promotion ID: " . ($etudiant['promotion_idpromotion'] ?? 'Non défini') . "<br>";
    
    // Mettre à jour la session si nécessaire
    if (!isset($_SESSION['promotion_id']) || $_SESSION['promotion_id'] != $etudiant['promotion_idpromotion']) {
        $_SESSION['promotion_id'] = $etudiant['promotion_idpromotion'];
        echo "<span style='color: green;'>Session mise à jour avec l'ID de promotion.</span><br>";
    }
} else {
    echo "<span style='color: red;'>Étudiant non trouvé!</span><br>";
    exit();
}

// 4. Vérifier si l'étudiant est chef de promotion
echo "<h3>4. Vérification chef de promotion</h3>";
$queryChef = "SELECT cp.*, e.noms as nom_etudiant, p.designationPromotion, aa.designation as annee_acad
              FROM chef_promotion cp 
              INNER JOIN etudiant e ON cp.idetudiant = e.idetudiant 
              INNER JOIN promotion p ON cp.promotion_idpromotion = p.idpromotion
              INNER JOIN annee_acad aa ON cp.annee_acad_idannee_acad = aa.idannee_acad
              WHERE e.idetudiant = :student_id 
              AND cp.est_actif = 1";

$stmtChef = $connexion->prepare($queryChef);
$stmtChef->bindParam(':student_id', $_SESSION['student_id']);
$stmtChef->execute();
$chefPromotion = $stmtChef->fetch(PDO::FETCH_ASSOC);

if ($chefPromotion) {
    echo "<span style='color: green;'>L'étudiant EST chef de promotion!</span><br>";
    echo "ID Chef: " . $chefPromotion['id_chef'] . "<br>";
    echo "Promotion: " . $chefPromotion['designationPromotion'] . "<br>";
    echo "Année académique: " . $chefPromotion['annee_acad'] . "<br>";
    echo "Date nomination: " . $chefPromotion['date_nomination'] . "<br>";
} else {
    echo "<span style='color: red;'>L'étudiant N'EST PAS chef de promotion!</span><br>";
    
    // Proposer de créer un chef de promotion pour test
    echo "<h4>Voulez-vous créer un chef de promotion pour cet étudiant ?</h4>";
    echo "<form method='post' style='margin: 10px 0;'>";
    echo "<input type='hidden' name='action' value='create_chef'>";
    echo "<button type='submit' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px;'>Créer chef de promotion</button>";
    echo "</form>";
}

// 5. Traitement des actions
if ($_POST['action'] ?? '' === 'create_chef') {
    echo "<h3>5. Création du chef de promotion</h3>";
    
    try {
        $insertChef = "INSERT INTO chef_promotion (idetudiant, promotion_idpromotion, annee_acad_idannee_acad, date_nomination, est_actif, date_creation, idUser) 
                       VALUES (:idetudiant, :promotion_id, :annee_acad, CURDATE(), 1, NOW(), 1)";
        
        $stmtInsert = $connexion->prepare($insertChef);
        $stmtInsert->bindParam(':idetudiant', $_SESSION['student_id']);
        $stmtInsert->bindParam(':promotion_id', $etudiant['promotion_idpromotion']);
        $stmtInsert->bindParam(':annee_acad', $_SESSION['annee_acad']);
        
        if ($stmtInsert->execute()) {
            echo "<span style='color: green;'>Chef de promotion créé avec succès!</span><br>";
            echo "<a href='../views/portail/student.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Retourner au portail étudiant</a>";
        } else {
            echo "<span style='color: red;'>Erreur lors de la création du chef de promotion.</span><br>";
        }
    } catch (Exception $e) {
        echo "<span style='color: red;'>Erreur: " . $e->getMessage() . "</span><br>";
    }
}

// 6. Vérifier la structure des tables
echo "<h3>6. Vérification des structures de tables</h3>";

// Vérifier la table chef_promotion
echo "<h4>Structure de la table chef_promotion:</h4>";
try {
    $queryStructureChef = "DESCRIBE chef_promotion";
    $stmtStructureChef = $connexion->prepare($queryStructureChef);
    $stmtStructureChef->execute();
    $structureChef = $stmtStructureChef->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th></tr>";
    foreach ($structureChef as $field) {
        echo "<tr>";
        echo "<td>" . $field['Field'] . "</td>";
        echo "<td>" . $field['Type'] . "</td>";
        echo "<td>" . $field['Null'] . "</td>";
        echo "<td>" . $field['Key'] . "</td>";
        echo "<td>" . ($field['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<span style='color: red;'>Erreur lors de la vérification de la structure: " . $e->getMessage() . "</span><br>";
}

// Vérifier la table suivi_enseignements
echo "<h4>Structure de la table suivi_enseignements:</h4>";
try {
    $queryStructureSuivi = "DESCRIBE suivi_enseignements";
    $stmtStructureSuivi = $connexion->prepare($queryStructureSuivi);
    $stmtStructureSuivi->execute();
    $structureSuivi = $stmtStructureSuivi->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th></tr>";
    foreach ($structureSuivi as $field) {
        echo "<tr>";
        echo "<td>" . $field['Field'] . "</td>";
        echo "<td>" . $field['Type'] . "</td>";
        echo "<td>" . $field['Null'] . "</td>";
        echo "<td>" . $field['Key'] . "</td>";
        echo "<td>" . ($field['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<span style='color: red;'>Table suivi_enseignements n'existe pas ou erreur: " . $e->getMessage() . "</span><br>";
    
    // Proposer de créer la table
    echo "<h4>Créer la table suivi_enseignements ?</h4>";
    echo "<form method='post' style='margin: 10px 0;'>";
    echo "<input type='hidden' name='action' value='create_table'>";
    echo "<button type='submit' style='background: #ffc107; color: black; padding: 10px 20px; border: none; border-radius: 5px;'>Créer la table</button>";
    echo "</form>";
}

// 7. Traitement de création de table
if ($_POST['action'] ?? '' === 'create_table') {
    echo "<h3>7. Création de la table suivi_enseignements</h3>";
    
    try {
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `suivi_enseignements` (
          `id_suivi` int(11) NOT NULL AUTO_INCREMENT,
          `chef_promotion_id` int(11) NOT NULL COMMENT 'ID de l''étudiant chef de promotion',
          `idECUE` int(11) NOT NULL COMMENT 'ID de la matière/ECUE',
          `date_cours` date NOT NULL COMMENT 'Date de la séance de cours',
          `heure_debut` time NOT NULL COMMENT 'Heure de début du cours',
          `heure_fin` time NOT NULL COMMENT 'Heure de fin du cours',
          `type_cours` enum('CM','TD','TP','Evaluation') NOT NULL DEFAULT 'CM' COMMENT 'Type de cours',
          `enseignant_id` int(11) DEFAULT NULL COMMENT 'ID de l''enseignant (optionnel)',
          `salle` varchar(100) DEFAULT NULL COMMENT 'Salle de cours',
          `commentaire` text DEFAULT NULL COMMENT 'Commentaires ou observations',
          `annee_acad_idannee_acad` int(11) NOT NULL COMMENT 'Année académique',
          `date_encodage` datetime NOT NULL DEFAULT current_timestamp(),
          `idUser` int(11) NOT NULL COMMENT 'Utilisateur ayant créé l''enregistrement',
          PRIMARY KEY (`id_suivi`),
          KEY `idx_chef_promotion` (`chef_promotion_id`),
          KEY `idx_ecue` (`idECUE`),
          KEY `idx_enseignant` (`enseignant_id`),
          KEY `idx_annee_acad` (`annee_acad_idannee_acad`),
          KEY `idx_date_cours` (`date_cours`),
          KEY `idx_chef_date` (`chef_promotion_id`,`date_cours`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Suivi des enseignements par les chefs de promotion';
        ";
        
        $connexion->exec($createTableSQL);
        echo "<span style='color: green;'>Table suivi_enseignements créée avec succès!</span><br>";
        
    } catch (Exception $e) {
        echo "<span style='color: red;'>Erreur lors de la création de la table: " . $e->getMessage() . "</span><br>";
    }
}

echo "<hr>";
echo "<h3>Actions disponibles</h3>";
echo "<a href='debug_chef_promotion.php' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Page de debug détaillée</a>";
echo "<a href='../views/portail/student.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Retourner au portail étudiant</a>";
?>