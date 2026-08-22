<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idECUE = $_POST['idECUE'] ?? '';
    $dateSeance = $_POST['date_seance'] ?? '';
    $heureDebut = $_POST['heure_debut'] ?? '';
    $heureFin = $_POST['heure_fin'] ?? '';
    $matiereVue = $_POST['matiere_vue'] ?? '';
    $nombreHeuresReelles = $_POST['nombre_heures_reelles'] ?? '';
    $promotionId = $_POST['promotion_idpromotion'] ?? '';
    $chefPromotionId = $_POST['chef_promotion_id'] ?? '';
    $anneeAcadId = $_POST['annee_acad_idannee_acad'] ?? '';
    $userId = $_SESSION['id'];

    // Validation des données
    if (empty($idECUE) || empty($dateSeance) || empty($heureDebut) || empty($heureFin) || 
        empty($matiereVue) || empty($nombreHeuresReelles) || empty($promotionId) || 
        empty($chefPromotionId) || empty($anneeAcadId)) {
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../reception/evolution_cours';
            });
                    </script>";
        exit();
    }

    // Validation des heures
    if ($heureDebut >= $heureFin) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'L\'heure de fin doit être supérieure à l\'heure de début.'
            }).then(() => {
                window.location.href = '../reception/evolution_cours';
            });
        </script>";
        exit();
    }

    // Validation de la date (ne peut pas être dans le futur)
    if (strtotime($dateSeance) > time()) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La date de la séance ne peut pas être dans le futur.'
            });
        </script>";
        exit();
    }

    try {
        $db = Connexion::getInstance()->getPDO();

        // Vérifier si l'ECUE et la promotion correspondent
        $queryVerif = "SELECT COUNT(*) FROM ecue e
                       JOIN ue ON e.\"UE_idUE\" = ue.\"idUE\"
                       JOIN semestre s ON ue.semestre_idsemestre = s.idsemestre
                       WHERE e.\"idECUE\" = :idECUE AND s.promotion_idpromotion = :promotionId";
        $stmtVerif = $db->prepare($queryVerif);
        $stmtVerif->bindParam(':idECUE', $idECUE, PDO::PARAM_INT);
        $stmtVerif->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmtVerif->execute();
        
        if ($stmtVerif->fetchColumn() == 0) {
            throw new Exception("L'ECUE sélectionné ne correspond pas à la promotion choisie.");
        }

        // Vérifier si le chef de promotion appartient bien à cette promotion
        $queryChef = "SELECT COUNT(*) FROM chef_promotion cp
                      JOIN etudiant e ON cp.idetudiant = e.idetudiant
                      WHERE cp.idetudiant = :chefId AND cp.promotion_idpromotion = :promotionId 
                      AND cp.annee_acad_idannee_acad = :anneeAcadId AND cp.est_actif = 1";
        $stmtChef = $db->prepare($queryChef);
        $stmtChef->bindParam(':chefId', $chefPromotionId, PDO::PARAM_INT);
        $stmtChef->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmtChef->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        $stmtChef->execute();
        
        if ($stmtChef->fetchColumn() == 0) {
            throw new Exception("Le chef de promotion sélectionné n'est pas valide pour cette promotion.");
        }

        // Vérifier les doublons (même ECUE, même date, même promotion)
        $queryDoublon = "SELECT COUNT(*) FROM suivi_enseignement_ecue 
                         WHERE \"idECUE\" = :idECUE AND date_seance = :dateSeance 
                         AND promotion_idpromotion = :promotionId 
                         AND heure_debut = :heureDebut AND heure_fin = :heureFin";
        $stmtDoublon = $db->prepare($queryDoublon);
        $stmtDoublon->bindParam(':idECUE', $idECUE, PDO::PARAM_INT);
        $stmtDoublon->bindParam(':dateSeance', $dateSeance);
        $stmtDoublon->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmtDoublon->bindParam(':heureDebut', $heureDebut);
        $stmtDoublon->bindParam(':heureFin', $heureFin);
        $stmtDoublon->execute();
        
        if ($stmtDoublon->fetchColumn() > 0) {
            throw new Exception("Cette séance a déjà été enregistrée.");
        }

        // Insérer l'évolution de cours
        $queryInsert = "INSERT INTO suivi_enseignement_ecue 
                        (\"idECUE\", date_seance, heure_debut, heure_fin, matiere_vue, 
                         nombre_heures_reelles, promotion_idpromotion, annee_acad_idannee_acad, 
                         chef_promotion_id, statut_validation, idUser_creation) 
                        VALUES (:idECUE, :dateSeance, :heureDebut, :heureFin, :matiereVue, 
                                :nombreHeures, :promotionId, :anneeAcadId, :chefId, 'Validé', :userId)";
        
        $stmtInsert = $db->prepare($queryInsert);
        $stmtInsert->bindParam(':idECUE', $idECUE, PDO::PARAM_INT);
        $stmtInsert->bindParam(':dateSeance', $dateSeance);
        $stmtInsert->bindParam(':heureDebut', $heureDebut);
        $stmtInsert->bindParam(':heureFin', $heureFin);
        $stmtInsert->bindParam(':matiereVue', $matiereVue);
        $stmtInsert->bindParam(':nombreHeures', $nombreHeuresReelles);
        $stmtInsert->bindParam(':promotionId', $promotionId, PDO::PARAM_INT);
        $stmtInsert->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
        $stmtInsert->bindParam(':chefId', $chefPromotionId, PDO::PARAM_INT);
        $stmtInsert->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        $stmtInsert->execute();

        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Évolution de cours enregistrée avec succès.'
            }).then(() => {
                window.location.href = '../reception/evolution_cours';
            });
        </script>";

    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../reception/evolution_cours';
            });
        </script>";
    }
    
} else {
    header("Location: ../reception/evolution_cours");
    exit();
}
?>
