<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header("Location: ../index");
    exit();
}

// Vérifier si l'ID de présence est fourni
if (isset($_GET['id']) && isset($_GET['idSeance'])) {
    try {
        $db = Connexion::getInstance()->getPDO();
        
        $idPresence = intval($_GET['id']);
        $idSeance = intval($_GET['idSeance']);
        
        if (!$idPresence || !$idSeance) {
            throw new Exception('Paramètres invalides.');
        }
        
        // Récupérer les informations de la présence avant suppression (pour le journal)
        $queryInfo = "SELECT pl.*, e.noms, e.matricule 
                     FROM presence_labo pl
                     JOIN etudiant e ON pl.idetudiant = e.idetudiant
                     WHERE pl.idpresence_labo = :idPresence";
        $stmtInfo = $db->prepare($queryInfo);
        $stmtInfo->bindParam(':idPresence', $idPresence);
        $stmtInfo->execute();
        $presenceInfo = $stmtInfo->fetch(PDO::FETCH_ASSOC);
        
        if (!$presenceInfo) {
            throw new Exception('La présence spécifiée n\'existe pas.');
        }
        
        // Vérifier que la présence appartient bien à la séance spécifiée
        if ($presenceInfo['idseance_labo'] != $idSeance) {
            throw new Exception('La présence ne correspond pas à la séance spécifiée.');
        }
        
        // Supprimer la présence
        $queryDelete = "DELETE FROM presence_labo WHERE idpresence_labo = :idPresence";
        $stmtDelete = $db->prepare($queryDelete);
        $stmtDelete->bindParam(':idPresence', $idPresence);
        
        if ($stmtDelete->execute()) {
            // Enregistrer dans le journal d'activités
            $queryJournal = "INSERT INTO journal_activites (user_type, user_id, type_activite, id_element, description, date_activite, ip_address) 
                            VALUES ('admin', :userId, 'presence_labo', :idSeance, :description, NOW(), :ipAddress)";
            
            $stmtJournal = $db->prepare($queryJournal);
            $stmtJournal->bindParam(':userId', $_SESSION['id']);
            $stmtJournal->bindParam(':idSeance', $idSeance);
            
            $description = "Suppression de la présence de l'étudiant " . $presenceInfo['noms'] . 
                          " (Matricule: " . $presenceInfo['matricule'] . ") pour la séance #" . $idSeance;
            
            $stmtJournal->bindParam(':description', $description);
            $stmtJournal->bindParam(':ipAddress', $_SERVER['REMOTE_ADDR']);
            $stmtJournal->execute();
            
            // Rediriger avec un message de succès
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'La présence a été supprimée avec succès.'
                }).then(() => {
                    window.location.href = '../laboratoire/presence.list&id=$idSeance';
                });
            </script>";
        } else {
            throw new Exception('Erreur lors de la suppression de la présence.');
        }
        
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../laboratoire/presence.list&id=$idSeance';
            });
        </script>";
    }
} else {
    // Redirection si paramètres manquants
    header("Location: ../index");
    exit();
}
?>
