<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $visiteId = $_POST['idVisite'] ?? '';
    $nomVisiteur = $_POST['nom_visiteur'] ?? '';
    $prenomVisiteur = $_POST['prenom_visiteur'] ?? null;
    $entrepriseVisiteur = $_POST['entreprise_visiteur'] ?? null;
    $telephoneVisiteur = $_POST['telephone_visiteur'] ?? '';
    $emailVisiteur = $_POST['email_visiteur'] ?? null;
    $carteIdentite = $_POST['carte_identite'] ?? null;
    $agentId = $_POST['Agent_idAgent'] ?? '';
    $serviceId = $_POST['Service_idService'] ?? '';
    $dateVisite = $_POST['date_visite'] ?? '';
    $heureDebut = $_POST['heure_debut'] ?? '';
    $heureFin = $_POST['heure_fin'] ?? '';
    $objetVisite = $_POST['objet_visite'] ?? '';
    $description = $_POST['description'] ?? null;
    $lieuRencontre = $_POST['lieu_rencontre'] ?? null;
    $statutVisite = $_POST['statut_visite'] ?? 'programmee';
    $typeVisite = $_POST['type_visite'] ?? 'professionnelle';
    $nombreAccompagnants = $_POST['nombre_accompagnants'] ?? 0;
    $observations = $_POST['observations'] ?? null;
    $validationSecurite = isset($_POST['validation_securite']) ? 1 : 0;
    $heureArriveeReelle = $_POST['heure_arrivee_reelle'] ?? null;
    $heureDepartReelle = $_POST['heure_depart_reelle'] ?? null;
    $userId = $_SESSION['id'];

    // Validation des champs obligatoires
    if (empty($visiteId) || empty($nomVisiteur) || empty($telephoneVisiteur) || 
        empty($agentId) || empty($serviceId) || empty($dateVisite) || 
        empty($heureDebut) || empty($heureFin) || empty($objetVisite)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../reception/visites.edit?id={$visiteId}';
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
                text: 'L\'heure de fin doit être postérieure à l\'heure de début.'
            }).then(() => {
                window.location.href = '../reception/visites.edit?id={$visiteId}';
            });
        </script>";
        exit();
    }

    try {
        $db = Connexion::getInstance()->getPDO();

        // Vérifier que la visite appartient à l'utilisateur connecté
        $stmtCheck = $db->prepare("
            SELECT statut_visite 
            FROM visites 
            WHERE idVisite = ? AND cree_par = ?
        ");
        $stmtCheck->execute([$visiteId, $userId]);
        $visiteExistante = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$visiteExistante) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Visite non trouvée ou accès non autorisé.'
                }).then(() => {
                    window.location.href = '../reception/visites.add';
                });
            </script>";
            exit();
        }

        // Vérifier les conflits d'horaire (exclure la visite actuelle)
        $stmtConflict = $db->prepare("
            SELECT COUNT(*) as conflicts 
            FROM visites 
            WHERE \"Agent_idAgent\" = ? 
            AND date_visite = ? 
            AND idVisite != ?
            AND statut_visite NOT IN ('annulee', 'terminee')
            AND (
                (heure_debut <= ? AND heure_fin > ?) OR
                (heure_debut < ? AND heure_fin >= ?) OR
                (heure_debut >= ? AND heure_fin <= ?)
            )
        ");
        $stmtConflict->execute([
            $agentId, $dateVisite, $visiteId,
            $heureDebut, $heureDebut,
            $heureFin, $heureFin,
            $heureDebut, $heureFin
        ]);
        
        $conflicts = $stmtConflict->fetch(PDO::FETCH_ASSOC);
        
        if ($conflicts['conflicts'] > 0) {
            echo "<script>
                Swal.fire({
                    icon: 'warning',
                    title: 'Conflit détecté',
                    text: 'L\'agent a déjà une visite programmée à cette période.'
                }).then(() => {
                                        window.location.href = '../reception/visites.edit?id={$visiteId}';
                });
            </script>";
            exit();
        }

        // Mise à jour de la visite
        $sql = "
            UPDATE visites SET 
                nom_visiteur = ?, prenom_visiteur = ?, entreprise_visiteur = ?,
                telephone_visiteur = ?, email_visiteur = ?, carte_identite = ?,
                \"Agent_idAgent\" = ?, \"Service_idService\" = ?, date_visite = ?,
                heure_debut = ?, heure_fin = ?, objet_visite = ?, description = ?,
                lieu_rencontre = ?, statut_visite = ?, type_visite = ?,
                nombre_accompagnants = ?, observations = ?, validation_securite = ?,
                date_modification = NOW(), modifie_par = ?
        ";
        
        $params = [
            $nomVisiteur, $prenomVisiteur, $entrepriseVisiteur,
            $telephoneVisiteur, $emailVisiteur, $carteIdentite,
            $agentId, $serviceId, $dateVisite,
            $heureDebut, $heureFin, $objetVisite, $description,
            $lieuRencontre, $statutVisite, $typeVisite,
            $nombreAccompagnants, $observations, $validationSecurite,
            $userId
        ];
        
        // Ajouter les heures réelles si la visite est terminée
        if ($statutVisite == 'terminee') {
            $sql .= ", heure_arrivee_reelle = ?, heure_depart_reelle = ?";
            $params[] = $heureArriveeReelle;
            $params[] = $heureDepartReelle;
        }
        
        $sql .= " WHERE idVisite = ?";
        $params[] = $visiteId;

        $stmt = $db->prepare($sql);
        $result = $stmt->execute($params);

        if ($result) {
            // Enregistrer dans l'historique si le statut a changé
            if ($visiteExistante['statut_visite'] != $statutVisite) {
                $stmtHistorique = $db->prepare("
                    INSERT INTO historique_visites (idVisite, action, ancien_statut, nouveau_statut, commentaire, \"idUser\")
                    VALUES (?, 'Modification statut', ?, ?, 'Statut modifié', ?)
                ");
                $stmtHistorique->execute([$visiteId, $visiteExistante['statut_visite'], $statutVisite, $userId]);
            }

            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Visite mise à jour avec succès.'
                }).then(() => {
                    window.location.href = '../reception/visites.add';
                });
            </script>";
        } else {
            throw new Exception("Erreur lors de la mise à jour de la visite");
        }
        
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../reception/visites.edit?id={$visiteId}';
            });
        </script>";
    }
    
} else {
    header("Location: ../reception/visites.add");
    exit();
}
?>
