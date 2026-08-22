<?php
// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Vérifier si l'utilisateur a les droits d'approbation (admin ou RH)
$isAuthorized = isset($_SESSION['idRole']) && in_array($_SESSION['idRole'], [1, 3]); // Supposons que 1=Admin, 3=RH
if (!$isAuthorized) {
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Vous n\'avez pas les droits pour approuver cette demande.',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            window.location.href = '../grh/conges.list';
        });
    </script>";
    exit();
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Récupérer les données du formulaire
        $idDemande = isset($_POST['idDemande']) ? intval($_POST['idDemande']) : 0;
        $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
        $idDecideur = $_SESSION['id'];
        
        // Validation des données
        if ($idDemande <= 0) {
            throw new Exception("Identifiant de demande invalide.");
        }
        
        // Vérifier si la demande existe et est en attente
        $stmt = $db->prepare("SELECT dc.*, tc.est_cumulable 
                              FROM demande_conge dc
                              JOIN type_conge tc ON dc.idtype_conge = tc.idtype_conge
                              WHERE dc.iddemande_conge = :idDemande");
        $stmt->bindParam(':idDemande', $idDemande, PDO::PARAM_INT);
        $stmt->execute();
        $demande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$demande) {
            throw new Exception("Demande de congé non trouvée.");
        }
        
        if ($demande['statut'] !== 'En attente') {
            throw new Exception("Cette demande a déjà été traitée.");
        }
        
        // Calculer le nombre de jours ouvrables
        $debut = new DateTime($demande['date_debut']);
        $fin = new DateTime($demande['date_fin']);
        $joursOuvrables = 0;
        
        for ($date = clone $debut; $date <= $fin; $date->modify('+1 day')) {
            $jour = $date->format('N'); // 1 (lundi) à 7 (dimanche)
            if ($jour < 6) { // Si ce n'est pas samedi (6) ou dimanche (7)
                $joursOuvrables++;
            }
        }
        
        // Commencer une transaction
        $db->beginTransaction();
        
        // Mettre à jour le statut de la demande
        $stmt = $db->prepare("UPDATE demande_conge 
                              SET statut = 'Approuvé', 
                                  commentaire_decision = :commentaire, 
                                  date_decision = NOW(), 
                                  \"idDecideur\" = :idDecideur 
                              WHERE iddemande_conge = :idDemande");
        $stmt->bindParam(':commentaire', $commentaire);
        $stmt->bindParam(':idDecideur', $idDecideur, PDO::PARAM_INT);
        $stmt->bindParam(':idDemande', $idDemande, PDO::PARAM_INT);
        $stmt->execute();
        
        // Si le congé est cumulable, mettre à jour le solde de congé
        if ($demande['est_cumulable']) {
            $annee = date('Y');
            
            // Vérifier si un solde existe pour cet agent et ce type de congé
            $stmt = $db->prepare("SELECT * FROM solde_conge 
                                  WHERE \"idAgent\" = :idAgent 
                                  AND idtype_conge = :idTypeConge 
                                  AND annee = :annee");
            $stmt->bindParam(':idAgent', $demande['idAgent'], PDO::PARAM_INT);
            $stmt->bindParam(':idTypeConge', $demande['idtype_conge'], PDO::PARAM_INT);
            $stmt->bindParam(':annee', $annee, PDO::PARAM_INT);
            $stmt->execute();
            $solde = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($solde) {
                // Mettre à jour le solde existant
                $stmt = $db->prepare("UPDATE solde_conge 
                                     SET jours_pris = jours_pris + :joursOuvrables,
                                         date_mise_a_jour = NOW()
                                     WHERE idsolde_conge = :idSolde");
                $stmt->bindParam(':joursOuvrables', $joursOuvrables, PDO::PARAM_INT);
                $stmt->bindParam(':idSolde', $solde['idsolde_conge'], PDO::PARAM_INT);
                $stmt->execute();
            } else {
                // Récupérer d'abord le type de congé avec sa durée standard
                $stmt = $db->prepare("SELECT duree_standard FROM type_conge WHERE idtype_conge = :idTypeConge");
                $stmt->bindParam(':idTypeConge', $demande['idtype_conge'], PDO::PARAM_INT);
                $stmt->execute();
                $typeConge = $stmt->fetch(PDO::FETCH_ASSOC);

                // Utiliser la durée standard du type de congé ou une valeur par défaut si non définie
                $joursAcquis = $typeConge['duree_standard'] ?? 0;

                // Si aucune durée standard n'est définie, on pourrait appliquer une logique métier
                if ($joursAcquis <= 0) {
                    // Utiliser une logique métier, par exemple en fonction du type d'agent
                    // ou simplement une valeur par défaut raisonnable
                    $joursAcquis = 26; // Par exemple, congé annuel standard dans certains pays
                }

                $stmt = $db->prepare("INSERT INTO solde_conge 
                                     (\"idAgent\", idtype_conge, annee, jours_acquis, jours_pris, jours_reportes, date_mise_a_jour, \"idUser\") 
                                     VALUES 
                                     (:idAgent, :idTypeConge, :annee, :joursAcquis, :joursPris, 0, NOW(), :idUser)");
                $stmt->bindParam(':idAgent', $demande['idAgent'], PDO::PARAM_INT);
                $stmt->bindParam(':idTypeConge', $demande['idtype_conge'], PDO::PARAM_INT);
                $stmt->bindParam(':annee', $annee, PDO::PARAM_INT);
                $stmt->bindParam(':joursAcquis', $joursAcquis, PDO::PARAM_INT);
                $stmt->bindParam(':joursPris', $joursOuvrables, PDO::PARAM_INT);
                $stmt->bindParam(':idUser', $idDecideur, PDO::PARAM_INT);
                $stmt->execute();
            }
        }
        
        // Valider la transaction
        $db->commit();
        
        // Rediriger avec un message de succès en utilisant SweetAlert directement
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'La demande de congé a été approuvée avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.location.href = '../grh/conges.view&id=" . $idDemande . "';
            });
        </script>";
        exit();
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        // Rediriger avec un message d'erreur en utilisant SweetAlert directement
        echo "<script>
            Swal.fire({
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.location.href = '../grh/conges.list';
            });
        </script>";
        exit();
    }
} else {
    // Si ce n'est pas une requête POST, vérifier s'il y a un ID de demande dans l'URL
    $idDemande = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($idDemande > 0) {
        // Rediriger vers la page de détails de la demande avec un formulaire d'approbation
        header('Location: ../grh/conges.view&id=' . $idDemande . '&action=approve');
        exit();
    } else {
        // Redirection si accès direct au fichier sans ID en utilisant SweetAlert directement
        echo "<script>
            Swal.fire({
                title: 'Erreur',
                text: 'Identifiant de demande manquant.',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.location.href = '../grh/conges.list';
            });
        </script>";
        exit();
    }
}
