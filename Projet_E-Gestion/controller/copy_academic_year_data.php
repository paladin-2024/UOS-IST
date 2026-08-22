<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $connexion = Connexion::getInstance()->getPDO();

    $targetYearId = isset($_POST['target_year_id']) ? (int)$_POST['target_year_id'] : 0;
    $anneeSource = isset($_POST['annee_source']) ? (int)$_POST['annee_source'] : 0;

    if (empty($targetYearId) || empty($anneeSource)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Paramètres manquants.'
            }).then(() => {
                window.location.href = '../configuration/annee';
            });
        </script>";
        exit();
    }

    // Vérifier que les années existent
    $checkTarget = $connexion->prepare("SELECT designation FROM annee_acad WHERE idannee_acad = ?");
    $checkTarget->execute([$targetYearId]);
    $targetYear = $checkTarget->fetch(PDO::FETCH_ASSOC);

    $checkSource = $connexion->prepare("SELECT designation FROM annee_acad WHERE idannee_acad = ?");
    $checkSource->execute([$anneeSource]);
    $sourceYear = $checkSource->fetch(PDO::FETCH_ASSOC);

    if (!$targetYear || !$sourceYear) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Année académique introuvable.'
            }).then(() => {
                window.location.href = '../configuration/annee';
            });
        </script>";
        exit();
    }

    // Début de la transaction
    $connexion->beginTransaction();

    try {
        $copiedItems = [];

        // 1. Copier les sections si demandé (éviter les doublons)
        if (isset($_POST['copier_sections']) && $_POST['copier_sections'] == 1) {
            $stmt = $connexion->prepare("
                INSERT INTO section (designationSection, dateCreation, idAnnee)
                SELECT s.designationSection, NOW(), ?
                FROM section s
                WHERE s.idAnnee = ?
                AND NOT EXISTS (
                    SELECT 1 FROM section s2
                    WHERE s2.designationSection = s.designationSection
                    AND s2.idAnnee = ?
                )
            ");
            $stmt->execute([$targetYearId, $anneeSource, $targetYearId]);
            $sectionCount = $stmt->rowCount();
            if ($sectionCount > 0) {
                $copiedItems[] = "$sectionCount section(s)";
            }

            // Créer une table temporaire pour mapper les ID de sections
            if ($sectionCount > 0) {
                $connexion->exec("
                    CREATE TEMPORARY TABLE section_mapping_copy (
                        old_id INT,
                        new_id INT
                    )
                ");

                $connexion->exec("
                    INSERT INTO section_mapping_copy (old_id, new_id)
                    SELECT s_old.idsection, s_new.idsection
                    FROM section s_old
                    JOIN section s_new ON s_new.designationSection = s_old.designationSection
                                       AND s_new.idAnnee = {$targetYearId}
                    WHERE s_old.idAnnee = {$anneeSource}
                ");
            }
        }

        // 2. Copier les orientations si demandé et si les sections existent
        if (isset($_POST['copier_orientations']) && $_POST['copier_orientations'] == 1 &&
            isset($_POST['copier_sections']) && $_POST['copier_sections'] == 1) {

            $stmt = $connexion->prepare("
                INSERT INTO orientation (designationOrientation, dateCreation, section_idsection)
                SELECT o.designationOrientation, NOW(), sm.new_id
                FROM orientation o
                JOIN section_mapping_copy sm ON o.section_idsection = sm.old_id
                WHERE NOT EXISTS (
                    SELECT 1 FROM orientation o2
                    WHERE o2.designationOrientation = o.designationOrientation
                    AND o2.section_idsection = sm.new_id
                )
            ");
            $stmt->execute();
            $orientationCount = $stmt->rowCount();
            if ($orientationCount > 0) {
                $copiedItems[] = "$orientationCount orientation(s)";
            }

            // Créer une table temporaire pour mapper les ID d'orientations
            if ($orientationCount > 0) {
                $connexion->exec("
                    CREATE TEMPORARY TABLE orientation_mapping_copy (
                        old_id INT,
                        new_id INT
                    )
                ");

                $connexion->exec("
                    INSERT INTO orientation_mapping_copy (old_id, new_id)
                    SELECT o_old.idorientation, o_new.idorientation
                    FROM orientation o_old
                    JOIN section_mapping_copy sm ON o_old.section_idsection = sm.old_id
                    JOIN orientation o_new ON o_new.designationOrientation = o_old.designationOrientation
                                           AND o_new.section_idsection = sm.new_id
                ");
            }
        }

        // 3. Copier les promotions si demandé et si les orientations existent
        if (isset($_POST['copier_promotions']) && $_POST['copier_promotions'] == 1 &&
            isset($_POST['copier_orientations']) && $_POST['copier_orientations'] == 1) {

            $stmt = $connexion->prepare("
                INSERT INTO promotion (designationPromotion, dateCreation, cycle, orientation_idorientation, annee_acad_idannee_acad, est_terminale)
                SELECT p.designationPromotion, NOW(), p.cycle, om.new_id, ?, p.est_terminale
                FROM promotion p
                JOIN orientation_mapping_copy om ON p.orientation_idorientation = om.old_id
                WHERE p.annee_acad_idannee_acad = ?
                AND NOT EXISTS (
                    SELECT 1 FROM promotion p2
                    WHERE p2.designationPromotion = p.designationPromotion
                    AND p2.orientation_idorientation = om.new_id
                    AND p2.annee_acad_idannee_acad = ?
                )
            ");
            $stmt->execute([$targetYearId, $anneeSource, $targetYearId]);
            $promotionCount = $stmt->rowCount();
            if ($promotionCount > 0) {
                $copiedItems[] = "$promotionCount promotion(s)";
            }

            // Créer une table temporaire pour mapper les ID de promotions
            if ($promotionCount > 0) {
                $connexion->exec("
                    CREATE TEMPORARY TABLE promotion_mapping_copy (
                        old_id INT,
                        new_id INT
                    )
                ");

                $connexion->exec("
                    INSERT INTO promotion_mapping_copy (old_id, new_id)
                    SELECT p_old.idpromotion, p_new.idpromotion
                    FROM promotion p_old
                    JOIN orientation_mapping_copy om ON p_old.orientation_idorientation = om.old_id
                    JOIN promotion p_new ON p_new.designationPromotion = p_old.designationPromotion
                                         AND p_new.annee_acad_idannee_acad = {$targetYearId}
                                         AND p_new.orientation_idorientation = om.new_id
                    WHERE p_old.annee_acad_idannee_acad = {$anneeSource}
                ");
            }
        }

        // 4. Copier les semestres si demandé et si les promotions existent
        if (isset($_POST['copier_semestres']) && $_POST['copier_semestres'] == 1 &&
            isset($_POST['copier_promotions']) && $_POST['copier_promotions'] == 1) {

            $stmt = $connexion->prepare("
                INSERT INTO semestre (numeroSemestre, dateEnregistrement, promotion_idpromotion)
                SELECT s.numeroSemestre, NOW(), pm.new_id
                FROM semestre s
                JOIN promotion_mapping_copy pm ON s.promotion_idpromotion = pm.old_id
                WHERE NOT EXISTS (
                    SELECT 1 FROM semestre s2
                    WHERE s2.numeroSemestre = s.numeroSemestre
                    AND s2.promotion_idpromotion = pm.new_id
                )
            ");
            $stmt->execute();
            $semestreCount = $stmt->rowCount();
            if ($semestreCount > 0) {
                $copiedItems[] = "$semestreCount semestre(s)";
            }

            // Créer une table temporaire pour mapper les ID de semestres
            if ($semestreCount > 0) {
                $connexion->exec("
                    CREATE TEMPORARY TABLE semestre_mapping_copy (
                        old_id INT,
                        new_id INT
                    )
                ");

                $connexion->exec("
                    INSERT INTO semestre_mapping_copy (old_id, new_id)
                    SELECT s_old.idsemestre, s_new.idsemestre
                    FROM semestre s_old
                    JOIN promotion_mapping_copy pm ON s_old.promotion_idpromotion = pm.old_id
                    JOIN semestre s_new ON s_new.numeroSemestre = s_old.numeroSemestre
                                        AND s_new.promotion_idpromotion = pm.new_id
                ");
            }
        }

        // 5. Copier les UE si demandé et si les semestres existent
        if (isset($_POST['copier_ue']) && $_POST['copier_ue'] == 1 &&
            isset($_POST['copier_semestres']) && $_POST['copier_semestres'] == 1) {

            $stmt = $connexion->prepare("
                INSERT INTO ue (codeUE, designationUE, description, semestre_idsemestre)
                SELECT ue.codeUE, ue.designationUE, ue.description, sm.new_id
                FROM ue
                JOIN semestre_mapping_copy sm ON ue.semestre_idsemestre = sm.old_id
                WHERE NOT EXISTS (
                    SELECT 1 FROM ue ue2
                    WHERE ue2.codeUE = ue.codeUE
                    AND ue2.semestre_idsemestre = sm.new_id
                )
            ");
            $stmt->execute();
            $ueCount = $stmt->rowCount();
            if ($ueCount > 0) {
                $copiedItems[] = "$ueCount UE(s)";
            }

            // Créer une table temporaire pour mapper les ID d'UE
            if ($ueCount > 0) {
                $connexion->exec("
                    CREATE TEMPORARY TABLE ue_mapping_copy (
                        old_id INT,
                        new_id INT
                    )
                ");

                $connexion->exec("
                    INSERT INTO ue_mapping_copy (old_id, new_id)
                    SELECT ue_old.idUE, ue_new.idUE
                    FROM ue ue_old
                    JOIN semestre_mapping_copy sm ON ue_old.semestre_idsemestre = sm.old_id
                    JOIN ue ue_new ON ue_new.codeUE = ue_old.codeUE
                                   AND ue_new.semestre_idsemestre = sm.new_id
                ");
            }
        }

        // 6. Copier les ECUE si demandé et si les UE existent
        if (isset($_POST['copier_ecue']) && $_POST['copier_ecue'] == 1 &&
            isset($_POST['copier_ue']) && $_POST['copier_ue'] == 1) {

            $stmt = $connexion->prepare("
                INSERT INTO ecue (designationECUE, CMI, TD, TP, UE_idUE, idCreateur, estVisible)
                SELECT e.designationECUE, e.CMI, e.TD, e.TP, um.new_id, e.idCreateur, e.estVisible
                FROM ecue e
                JOIN ue_mapping_copy um ON e.UE_idUE = um.old_id
                WHERE NOT EXISTS (
                    SELECT 1 FROM ecue e2
                    WHERE e2.designationECUE = e.designationECUE
                    AND e2.UE_idUE = um.new_id
                )
            ");
            $stmt->execute();
            $ecueCount = $stmt->rowCount();
            if ($ecueCount > 0) {
                $copiedItems[] = "$ecueCount ECUE(s)";
            }
        }

        // Supprimer les tables temporaires
        $connexion->exec("DROP TEMPORARY TABLE IF EXISTS section_mapping_copy");
        $connexion->exec("DROP TEMPORARY TABLE IF EXISTS orientation_mapping_copy");
        $connexion->exec("DROP TEMPORARY TABLE IF EXISTS promotion_mapping_copy");
        $connexion->exec("DROP TEMPORARY TABLE IF EXISTS semestre_mapping_copy");
        $connexion->exec("DROP TEMPORARY TABLE IF EXISTS ue_mapping_copy");

        // 7. Copier les Unités de Recherche si demandé
        if (isset($_POST['copier_ur']) && $_POST['copier_ur'] == 1) {
            // Créer la table de mapping des sections pour les associations UR-Sections
            $connexion->exec("DROP TEMPORARY TABLE IF EXISTS section_mapping_ur");
            $connexion->exec("
                CREATE TEMPORARY TABLE section_mapping_ur (
                    old_id INT,
                    new_id INT
                )
            ");
            
            //Mapper les sections seulement si l'année source a des sections
            $connexion->exec("
                INSERT INTO section_mapping_ur (old_id, new_id)
                SELECT s_old.idsection, s_new.idsection
                FROM section s_old
                JOIN section s_new ON s_new.designationSection = s_old.designationSection
                                   AND s_new.idAnnee = {$targetYearId}
                WHERE s_old.idAnnee = {$anneeSource}
            ");

            // Copier les UR (indépendamment des sections)
            $stmt = $connexion->prepare("
                INSERT INTO unite_recherche (designation_UR, description)
                SELECT ur.designation_UR, ur.description
                FROM unite_recherche ur
                WHERE NOT EXISTS (
                    SELECT 1 FROM unite_recherche ur2
                    WHERE ur2.designation_UR = ur.designation_UR
                )
            ");
            $stmt->execute();
            $urCount = $stmt->rowCount();
            if ($urCount > 0) {
                $copiedItems[] = "$urCount unité(s) de recherche";
            }

            // Créer la table de mapping UR
            $connexion->exec("DROP TEMPORARY TABLE IF EXISTS ur_mapping_copy");
            $connexion->exec("
                CREATE TEMPORARY TABLE ur_mapping_copy (
                    old_id INT,
                    new_id INT
                )
            ");
            $connexion->exec("
                INSERT INTO ur_mapping_copy (old_id, new_id)
                SELECT ur_old.idunite_recherche, ur_new.idunite_recherche
                FROM unite_recherche ur_old
                JOIN unite_recherche ur_new ON ur_new.designation_UR = ur_old.designation_UR
            ");

            // Copier les associations UR-Sections (si sections copiées ou déjà existantes dans année cible)
            $stmt = $connexion->prepare("
                INSERT INTO unite_recherche_section (idunite_recherche, idsection)
                SELECT um.new_id, sm.new_id
                FROM unite_recherche_section urs
                JOIN ur_mapping_copy um ON urs.idunite_recherche = um.old_id
                JOIN section_mapping_ur sm ON urs.idsection = sm.old_id
                WHERE NOT EXISTS (
                    SELECT 1 FROM unite_recherche_section urs2
                    WHERE urs2.idunite_recherche = um.new_id
                    AND urs2.idsection = sm.new_id
                )
            ");
            $stmt->execute();
        }

        // 8. Copier les Spécialisations si demandé
        if (isset($_POST['copier_specialisations']) && $_POST['copier_specialisations'] == 1) {
            // Créer les tables de mapping nécessaires
            $connexion->exec("DROP TEMPORARY TABLE IF EXISTS section_mapping_spec");
            $connexion->exec("
                CREATE TEMPORARY TABLE section_mapping_spec (
                    old_id INT,
                    new_id INT
                )
            ");
            
            // Mapper les sections - insertion silencieuse si déjà existantes
            $connexion->exec("
                INSERT INTO section_mapping_spec (old_id, new_id)
                SELECT s_old.idsection, s_new.idsection
                FROM section s_old
                JOIN section s_new ON s_new.designationSection = s_old.designationSection
                                   AND s_new.idAnnee = {$targetYearId}
                WHERE s_old.idAnnee = {$anneeSource}
            ");

            $connexion->exec("DROP TEMPORARY TABLE IF EXISTS orientation_mapping_spec");
            $connexion->exec("
                CREATE TEMPORARY TABLE orientation_mapping_spec (
                    old_id INT,
                    new_id INT
                )
            ");
            
            // Mapper les orientations
            $connexion->exec("
                INSERT INTO orientation_mapping_spec (old_id, new_id)
                SELECT o_old.idorientation, o_new.idorientation
                FROM orientation o_old
                JOIN section_mapping_spec sm ON o_old.section_idsection = sm.old_id
                JOIN orientation o_new ON o_new.designationOrientation = o_old.designationOrientation
                                       AND o_new.section_idsection = sm.new_id
            ");

            $connexion->exec("DROP TEMPORARY TABLE IF EXISTS ur_mapping_spec");
            $connexion->exec("
                CREATE TEMPORARY TABLE ur_mapping_spec (
                    old_id INT,
                    new_id INT
                )
            ");
            
            // Mapper les UR - depuis UR copiées ou déjà existantes
            $connexion->exec("
                INSERT INTO ur_mapping_spec (old_id, new_id)
                SELECT ur_old.idunite_recherche, ur_new.idunite_recherche
                FROM unite_recherche ur_old
                JOIN unite_recherche ur_new ON ur_new.designation_UR = ur_old.designation_UR
            ");

            // Copier les spécialisations (si UR et orientations sont copiées ou existantes)
            $stmt = $connexion->prepare("
                INSERT INTO specialisation (designation, idUnite_recherche, idorientation)
                SELECT s.designation, um.new_id, om.new_id
                FROM specialisation s
                JOIN ur_mapping_spec um ON s.idUnite_recherche = um.old_id
                JOIN orientation_mapping_spec om ON s.idorientation = om.old_id
                WHERE NOT EXISTS (
                    SELECT 1 FROM specialisation s2
                    WHERE s2.designation = s.designation
                    AND s2.idUnite_recherche = um.new_id
                    AND s2.idorientation = om.new_id
                )
            ");
            $stmt->execute();
            $specCount = $stmt->rowCount();
            if ($specCount > 0) {
                $copiedItems[] = "$specCount spécialisation(s)";
            }
        }

        // Nettoyer les tables temporaires
        $connexion->exec("DROP TEMPORARY TABLE IF EXISTS section_mapping_ur");
        $connexion->exec("DROP TEMPORARY TABLE IF EXISTS ur_mapping_copy");
        $connexion->exec("DROP TEMPORARY TABLE IF EXISTS section_mapping_spec");
        $connexion->exec("DROP TEMPORARY TABLE IF EXISTS orientation_mapping_spec");
        $connexion->exec("DROP TEMPORARY TABLE IF EXISTS ur_mapping_spec");

        // Valider la transaction
        $connexion->commit();

        $message = "Données copiées avec succès.";
        if (!empty($copiedItems)) {
            $message .= " Éléments copiés : " . implode(", ", $copiedItems);
        } else {
            $message = "Aucune nouvelle donnée à copier (toutes existent déjà).";
        }

        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: '$message'
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
                text: 'Erreur lors de la copie des données: " . addslashes($e->getMessage()) . "'
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
