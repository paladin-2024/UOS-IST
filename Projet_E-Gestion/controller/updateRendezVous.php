<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rendezVousId = $_POST['idRendez_vous'] ?? '';
    $agentId = $_POST['Agent_idAgent'] ?? '';
    $serviceId = $_POST['Service_idService'] ?? '';
    $contactExterne = $_POST['contact_externe'] ?? null;
    $emailExterne = $_POST['email_externe'] ?? null;
    $telephoneExterne = $_POST['telephone_externe'] ?? null;
    $dateRendezVous = $_POST['date_rendez_vous'] ?? '';
    $heureDebut = $_POST['heure_debut'] ?? '';
    $heureFin = $_POST['heure_fin'] ?? '';
    $objet = $_POST['objet'] ?? '';
    $description = $_POST['description'] ?? null;
    $lieu = $_POST['lieu'] ?? null;
    $statutRendezVous = $_POST['statut_rendez_vous'] ?? 'planifie';
    $typeRendezVous = $_POST['type_rendez_vous'] ?? null;
    $priorite = $_POST['priorite'] ?? 'normale';
    $rappelActive = isset($_POST['rappel_active']) ? 1 : 0;
    $delaiRappel = $_POST['delai_rappel'] ?? 30;
    $commentaires = $_POST['commentaires'] ?? null;
    $userId = $_SESSION['id'];

    // Validation des champs obligatoires
    if (empty($rendezVousId) || empty($agentId) || empty($serviceId) || 
        empty($dateRendezVous) || empty($heureDebut) || empty($heureFin) || empty($objet)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../reception/rendez_vous.edit?id={$rendezVousId}';
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
                window.location.href = '../reception/rendez_vous.edit?id={$rendezVousId}';
            });
        </script>";
        exit();
    }

    try {
        $db = Connexion::getInstance()->getPDO();

        // Vérifier que le rendez-vous appartient à l'utilisateur connecté
        $stmtCheck = $db->prepare("
            SELECT idRendez_vous 
            FROM rendez_vous 
            WHERE idRendez_vous = ? AND cree_par = ?
        ");
        $stmtCheck->execute([$rendezVousId, $userId]);
        
        if (!$stmtCheck->fetch()) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Rendez-vous non trouvé ou accès non autorisé.'
                }).then(() => {
                    window.location.href = '../reception/rendez_vous.add';
                });
            </script>";
            exit();
        }

        // Vérifier la disponibilité de l'agent (exclure le rendez-vous actuel)
        $stmtConflict = $db->prepare("
            SELECT COUNT(*) as conflicts 
            FROM rendez_vous 
            WHERE \"Agent_idAgent\" = ? 
            AND date_rendez_vous = ? 
            AND idRendez_vous != ?
            AND statut_rendez_vous NOT IN ('annule', 'termine')
            AND (
                (heure_debut <= ? AND heure_fin > ?) OR
                (heure_debut < ? AND heure_fin >= ?) OR
                (heure_debut >= ? AND heure_fin <= ?)
            )
        ");
        $stmtConflict->execute([
            $agentId, $dateRendezVous, $rendezVousId,
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
                    text: 'L\'agent a déjà un rendez-vous à cette période.'
                }).then(() => {
                    window.location.href = '../reception/rendez_vous.edit?id={$rendezVousId}';
                });
            </script>";
            exit();
        }

        // Mise à jour du rendez-vous
        $stmt = $db->prepare("
            UPDATE rendez_vous SET 
                \"Agent_idAgent\" = ?, \"Service_idService\" = ?, contact_externe = ?, 
                email_externe = ?, telephone_externe = ?, date_rendez_vous = ?, 
                heure_debut = ?, heure_fin = ?, objet = ?, description = ?, 
                lieu = ?, statut_rendez_vous = ?, type_rendez_vous = ?, 
                priorite = ?, rappel_active = ?, delai_rappel = ?, 
                commentaires = ?, date_modification = NOW(), modifie_par = ?
            WHERE idRendez_vous = ? AND cree_par = ?
        ");
        
        $result = $stmt->execute([
            $agentId, $serviceId, $contactExterne,
            $emailExterne, $telephoneExterne, $dateRendezVous,
            $heureDebut, $heureFin, $objet, $description, $lieu,
            $statutRendezVous, $typeRendezVous, $priorite,
            $rappelActive, $delaiRappel, $commentaires,
            $userId, $rendezVousId, $userId
        ]);

        if ($result) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Rendez-vous mis à jour avec succès.'
                }).then(() => {
                    window.location.href = '../reception/rendez_vous.add';
                });
            </script>";
        } else {
            throw new Exception("Erreur lors de la mise à jour du rendez-vous");
        }
        
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../reception/rendez_vous.edit?id={$rendezVousId}';
            });
        </script>";
    }
    
} else {
    header("Location: ../reception/rendez_vous.add");
    exit();
}
?>