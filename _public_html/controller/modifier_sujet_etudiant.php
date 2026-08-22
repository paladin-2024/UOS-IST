<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $connexion = Connexion::getInstance()->getPDO();
        $connexion->beginTransaction();

        // Récupération des données du formulaire
        $sujetId = intval($_POST['sujet_id'] ?? 0);
        $etudiantId = intval($_POST['etudiant_id'] ?? 0);
        $intitule = trim($_POST['intitule'] ?? '');
        $idSpecialisation = intval($_POST['idSpecialisation'] ?? 0);
        $directeurId = intval($_POST['directeur_id'] ?? 0);
        $encadreurId = !empty($_POST['encadreur_id']) ? intval($_POST['encadreur_id']) : null;
        $anneeAcadId = intval($_POST['annee_acad'] ?? 0);

        // Validation des données requises
        if (empty($sujetId) || empty($etudiantId) || empty($intitule) || empty($idSpecialisation) || empty($directeurId) || empty($anneeAcadId)) {
            throw new Exception("Tous les champs obligatoires doivent être renseignés.");
        }

        // Validation : directeur et encadreur différents
        if ($encadreurId && $directeurId === $encadreurId) {
            throw new Exception("Le directeur et l'encadreur doivent être différents.");
        }

        // Debug : Log des valeurs reçues
        error_log("=== DEBUG MODIFICATION SUJET ===");
        error_log("sujet_id: " . $sujetId);
        error_log("etudiant_id: " . $etudiantId);
        error_log("intitule: " . $intitule);
        error_log("idSpecialisation: " . $idSpecialisation);
        error_log("directeur_id: " . $directeurId);
        error_log("encadreur_id: " . ($encadreurId ?? 'NULL'));
        error_log("annee_acad: " . $anneeAcadId);

        // 1. Vérifier que le sujet existe et appartient à l'étudiant
        $checkQuery = "SELECT * FROM sujets WHERE idsujets = :sujet_id AND etudiant_idetudiant = :etudiant_id";
        $checkStmt = $connexion->prepare($checkQuery);
        $checkStmt->bindValue(':sujet_id', $sujetId, PDO::PARAM_INT);
        $checkStmt->bindValue(':etudiant_id', $etudiantId, PDO::PARAM_INT);
        $checkStmt->execute();
        $existingSujet = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingSujet) {
            error_log("ERREUR: Sujet non trouvé - ID: $sujetId, Etudiant: $etudiantId");
            throw new Exception("Le sujet n'existe pas ou n'appartient pas à cet étudiant.");
        }

        error_log("Sujet existant trouvé: " . print_r($existingSujet, true));

        // 2. Récupérer le cycle de l'étudiant
        $cycleQuery = "SELECT p.cycle FROM etudiant e 
                       JOIN promotion p ON e.promotion_idpromotion = p.idpromotion 
                       WHERE e.idetudiant = :etudiant_id";
        $cycleStmt = $connexion->prepare($cycleQuery);
        $cycleStmt->bindValue(':etudiant_id', $etudiantId, PDO::PARAM_INT);
        $cycleStmt->execute();
        $cycleResult = $cycleStmt->fetch(PDO::FETCH_ASSOC);
        $cycleActuel = $cycleResult['cycle'] ?? '';

        error_log("Cycle étudiant: " . $cycleActuel);

        // 3. Effectuer la mise à jour
        $updateQuery = "UPDATE sujets SET 
                        intitule = :intitule,
                        idSpecialisation = :specialisation,
                        idDirecteur = :directeur,
                        idEncadreur = :encadreur,
                        annee_acad_idannee_acad = :annee_acad,
                        cycle = :cycle,
                        statut_validation = 'En attente',
                        commentaire_commission = NULL,
                        date_validation = NULL,
                        idValidateur = NULL
                        WHERE idsujets = :sujet_id AND etudiant_idetudiant = :etudiant_id";

        $updateStmt = $connexion->prepare($updateQuery);
        $updateStmt->bindValue(':intitule', $intitule, PDO::PARAM_STR);
        $updateStmt->bindValue(':specialisation', $idSpecialisation, PDO::PARAM_INT);
        $updateStmt->bindValue(':directeur', $directeurId, PDO::PARAM_INT);
        $updateStmt->bindValue(':encadreur', $encadreurId, PDO::PARAM_INT);
        $updateStmt->bindValue(':annee_acad', $anneeAcadId, PDO::PARAM_INT);
        $updateStmt->bindValue(':cycle', $cycleActuel, PDO::PARAM_STR);
        $updateStmt->bindValue(':sujet_id', $sujetId, PDO::PARAM_INT);
        $updateStmt->bindValue(':etudiant_id', $etudiantId, PDO::PARAM_INT);

        error_log("Requête UPDATE: " . $updateQuery);
        error_log("Paramètres: intitule=$intitule, specialisation=$idSpecialisation, directeur=$directeurId, encadreur=$encadreurId, annee_acad=$anneeAcadId, cycle=$cycleActuel");

        $result = $updateStmt->execute();
        
        if (!$result) {
            $errorInfo = $updateStmt->errorInfo();
            error_log("Erreur SQL: " . print_r($errorInfo, true));
            throw new Exception("Erreur lors de l'exécution de la requête: " . $errorInfo[2]);
        }

        $rowsAffected = $updateStmt->rowCount();
        error_log("Lignes affectées: " . $rowsAffected);

        if ($rowsAffected === 0) {
            // Vérifier si les valeurs sont identiques
            $sameValues = (
                $existingSujet['intitule'] === $intitule &&
                $existingSujet['idSpecialisation'] == $idSpecialisation &&
                $existingSujet['idDirecteur'] == $directeurId &&
                $existingSujet['idEncadreur'] == $encadreurId &&
                $existingSujet['annee_acad_idannee_acad'] == $anneeAcadId
            );

            if ($sameValues) {
                // Forcer la mise à jour du statut même si les autres valeurs sont identiques
                $forceUpdateQuery = "UPDATE sujets SET 
                                   statut_validation = 'En attente',
                                   commentaire_commission = NULL,
                                   date_validation = NULL,
                                   idValidateur = NULL
                                   WHERE idsujets = :sujet_id AND etudiant_idetudiant = :etudiant_id";
                
                $forceUpdateStmt = $connexion->prepare($forceUpdateQuery);
                $forceUpdateStmt->bindValue(':sujet_id', $sujetId, PDO::PARAM_INT);
                $forceUpdateStmt->bindValue(':etudiant_id', $etudiantId, PDO::PARAM_INT);
                $forceUpdateStmt->execute();
                
                error_log("Mise à jour forcée du statut effectuée");
            } else {
                error_log("Aucune ligne affectée - valeurs: même=" . ($sameValues ? 'oui' : 'non'));
                throw new Exception("Aucune modification effectuée. Vérifiez les données.");
            }
        }

        $connexion->commit();

        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le sujet a été modifié avec succès.'
            }).then(() => {
                window.location.href = '../portail/student';
            });
        </script>";

    } catch (Exception $e) {
        if (isset($connexion)) {
            $connexion->rollBack();
        }
        
        error_log("Erreur modification sujet: " . $e->getMessage());
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la modification du sujet: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../portail/student';
            });
        </script>";
    }
} else {
    header("Location: ../portail/student");
    exit();
}
?>
