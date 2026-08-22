<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $connexion = Connexion::getInstance()->getPDO();
    
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';

    if (empty($designation)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La désignation est obligatoire.'
            }).then(() => {
                window.location.href = '../configuration/annee';
            });
        </script>";
        exit();
    }

    // Vérification de l'unicité de la désignation
    $checkQuery = "SELECT COUNT(*) FROM annee_acad WHERE designation = :designation";
    $checkStmt = $connexion->prepare($checkQuery);
    $checkStmt->bindParam(':designation', $designation);
    $checkStmt->execute();
    
    if ($checkStmt->fetchColumn() > 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Cette année académique existe déjà.'
            }).then(() => {
                window.location.href = '../configuration/annee';
            });
        </script>";
        exit();
    }

    // Vérifier si l'utilisateur souhaite définir cette année comme active
    $setAsActive = isset($_POST['set_as_active']) && $_POST['set_as_active'] == 1;

    // Début de la transaction
    $connexion->beginTransaction();
    
    try {
        // Si on définit cette année comme active, désactiver toutes les autres d'abord
        if ($setAsActive) {
            $deactivateQuery = "UPDATE annee_acad SET est_active = 0";
            $connexion->exec($deactivateQuery);
        }
        
        // Insertion de la nouvelle année académique
        $estActive = $setAsActive ? 1 : 0;
        $insertQuery = "INSERT INTO annee_acad (designation, \"dateCreation\", est_active) VALUES (:designation, NOW(), :est_active)";
        $insertStmt = $connexion->prepare($insertQuery);
        $insertStmt->bindParam(':designation', $designation);
        $insertStmt->bindParam(':est_active', $estActive, PDO::PARAM_INT);
        $insertStmt->execute();
        
        // Récupération de l'ID de la nouvelle année
        $newYearId = $connexion->lastInsertId();
        
        // Vérifier si l'utilisateur souhaite copier les données
        if (isset($_POST['copier_donnees']) && $_POST['copier_donnees'] == 1 && !empty($_POST['annee_source'])) {
            $anneeSource = $_POST['annee_source'];
            
            // 1. Copier les sections si demandé
            if (isset($_POST['copier_sections']) && $_POST['copier_sections'] == 1) {
                $connexion->exec("
                    INSERT INTO section (designationSection, dateCreation, idAnnee)
                    SELECT designationSection, NOW(), {$newYearId}
                    FROM section
                    WHERE idAnnee = {$anneeSource}
                ");
                
                // Créer une table temporaire pour mapper les ID de sections
                $connexion->exec("
                    CREATE TEMPORARY TABLE section_mapping (
                        old_id INT,
                        new_id INT
                    )
                ");
                
                $connexion->exec("
                    INSERT INTO section_mapping (old_id, new_id)
                    SELECT s_old.idsection, s_new.idsection
                    FROM section s_old
                    JOIN section s_new ON s_new.designationSection = s_old.designationSection 
                                       AND s_new.idAnnee = {$newYearId}
                    WHERE s_old.idAnnee = {$anneeSource}
                ");
            }
            
            // 2. Copier les orientations si demandé et si les sections ont été copiées
            if (isset($_POST['copier_orientations']) && $_POST['copier_orientations'] == 1 && 
                isset($_POST['copier_sections']) && $_POST['copier_sections'] == 1) {
                
                $connexion->exec("
                    INSERT INTO orientation (\"designationOrientation\", \"dateCreation\", section_idsection)
                    SELECT o.\"designationOrientation\", NOW(), sm.new_id
                    FROM orientation o
                    JOIN section_mapping sm ON o.section_idsection = sm.old_id
                ");
                
                // Créer une table temporaire pour mapper les ID d'orientations
                $connexion->exec("
                    CREATE TEMPORARY TABLE orientation_mapping (
                        old_id INT,
                        new_id INT
                    )
                ");
                
                $connexion->exec("
                    INSERT INTO orientation_mapping (old_id, new_id)
                    SELECT o_old.idorientation, o_new.idorientation
                    FROM orientation o_old
                    JOIN section_mapping sm ON o_old.section_idsection = sm.old_id
                    JOIN orientation o_new ON o_new.\"designationOrientation\" = o_old.\"designationOrientation\" 
                                           AND o_new.section_idsection = sm.new_id
                ");
            }
            
            // 3. Copier les promotions si demandé et si les orientations ont été copiées
            if (isset($_POST['copier_promotions']) && $_POST['copier_promotions'] == 1 && 
                isset($_POST['copier_orientations']) && $_POST['copier_orientations'] == 1) {
                
                $connexion->exec("
                    INSERT INTO promotion (designationPromotion, dateCreation, cycle, orientation_idorientation, annee_acad_idannee_acad, est_terminale)
                    SELECT p.designationPromotion, NOW(), p.cycle, om.new_id, {$newYearId}, p.est_terminale
                    FROM promotion p
                    JOIN orientation_mapping om ON p.orientation_idorientation = om.old_id
                    WHERE p.annee_acad_idannee_acad = {$anneeSource}
                ");
                
                // Créer une table temporaire pour mapper les ID de promotions
                $connexion->exec("
                    CREATE TEMPORARY TABLE promotion_mapping (
                        old_id INT,
                        new_id INT
                    )
                ");
                
                $connexion->exec("
                    INSERT INTO promotion_mapping (old_id, new_id)
                    SELECT p_old.idpromotion, p_new.idpromotion
                    FROM promotion p_old
                    JOIN orientation_mapping om ON p_old.orientation_idorientation = om.old_id
                    JOIN promotion p_new ON p_new.designationPromotion = p_old.designationPromotion 
                                         AND p_new.annee_acad_idannee_acad = {$newYearId}
                                         AND p_new.orientation_idorientation = om.new_id
                    WHERE p_old.annee_acad_idannee_acad = {$anneeSource}
                ");
            }
            
            // 4. Copier les semestres si demandé et si les promotions ont été copiées
            if (isset($_POST['copier_semestres']) && $_POST['copier_semestres'] == 1 && 
                isset($_POST['copier_promotions']) && $_POST['copier_promotions'] == 1) {
                    $connexion->exec("
                    INSERT INTO semestre (\"numeroSemestre\", \"dateEnregistrement\", promotion_idpromotion)
                    SELECT s.\"numeroSemestre\", NOW(), pm.new_id
                    FROM semestre s
                    JOIN promotion_mapping pm ON s.promotion_idpromotion = pm.old_id
                ");
                
                // Créer une table temporaire pour mapper les ID de semestres
                $connexion->exec("
                    CREATE TEMPORARY TABLE semestre_mapping (
                        old_id INT,
                        new_id INT
                    )
                ");
                
                $connexion->exec("
                    INSERT INTO semestre_mapping (old_id, new_id)
                    SELECT s_old.idsemestre, s_new.idsemestre
                    FROM semestre s_old
                    JOIN promotion_mapping pm ON s_old.promotion_idpromotion = pm.old_id
                    JOIN semestre s_new ON s_new.\"numeroSemestre\" = s_old.\"numeroSemestre\" 
                                        AND s_new.promotion_idpromotion = pm.new_id
                ");
            }
            
            // 5. Copier les UE si demandé et si les semestres ont été copiés
            if (isset($_POST['copier_ue']) && $_POST['copier_ue'] == 1 && 
                isset($_POST['copier_semestres']) && $_POST['copier_semestres'] == 1) {
                
                $connexion->exec("
                    INSERT INTO ue (\"codeUE\", \"designationUE\", description, semestre_idsemestre)
                    SELECT ue.\"codeUE\", ue.\"designationUE\", ue.description, sm.new_id
                    FROM ue
                    JOIN semestre_mapping sm ON ue.semestre_idsemestre = sm.old_id
                ");
                
                // Créer une table temporaire pour mapper les ID d'UE
                $connexion->exec("
                    CREATE TEMPORARY TABLE ue_mapping (
                        old_id INT,
                        new_id INT
                    )
                ");
                
                $connexion->exec("
                    INSERT INTO ue_mapping (old_id, new_id)
                    SELECT ue_old.\"idUE\", ue_new.\"idUE\"
                    FROM ue ue_old
                    JOIN semestre_mapping sm ON ue_old.semestre_idsemestre = sm.old_id
                    JOIN ue ue_new ON ue_new.\"codeUE\" = ue_old.\"codeUE\" 
                                   AND ue_new.semestre_idsemestre = sm.new_id
                ");
            }
            
            // 6. Copier les ECUE si demandé et si les UE ont été copiées
            if (isset($_POST['copier_ecue']) && $_POST['copier_ecue'] == 1 && 
                isset($_POST['copier_ue']) && $_POST['copier_ue'] == 1) {
                
                $connexion->exec("
                    INSERT INTO ecue (\"designationECUE\", CMI, TD, TP, \"UE_idUE\", \"idCreateur\", \"estVisible\")
                    SELECT e.\"designationECUE\", e.CMI, e.TD, e.TP, um.new_id, e.\"idCreateur\", e.\"estVisible\"
                    FROM ecue e
                    JOIN ue_mapping um ON e.\"UE_idUE\" = um.old_id
                ");
            }
            
            // Supprimer les tables temporaires
            $connexion->exec("DROP TEMPORARY TABLE IF EXISTS section_mapping");
            $connexion->exec("DROP TEMPORARY TABLE IF EXISTS orientation_mapping");
            $connexion->exec("DROP TEMPORARY TABLE IF EXISTS promotion_mapping");
            $connexion->exec("DROP TEMPORARY TABLE IF EXISTS semestre_mapping");
            $connexion->exec("DROP TEMPORARY TABLE IF EXISTS ue_mapping");
        }
        
        // Valider la transaction
        $connexion->commit();
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Année académique ajoutée avec succès.'
            }).then(() => {
                window.location.href = '../configuration/annee';
            });
        </script>";
        
    } catch (PDOException $e) {
        // Annuler la transaction en cas d'erreur
        $connexion->rollBack();
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de l\'année académique: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../configuration/annee';
            });
        </script>";
    }
} else {
    header("Location: ../configuration/annee.php");
    exit();
}
?>

