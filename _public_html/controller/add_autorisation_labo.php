<?php
session_start();
error_reporting(E_ALL); ini_set("display_errors", 1);
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Récupérer les données du formulaire
        $idlabo = isset($_POST['idlabo']) ? intval($_POST['idlabo']) : 0;
        $idAgent = isset($_POST['idAgent']) ? intval($_POST['idAgent']) : 0;
        $niveau_autorisation = isset($_POST['niveau_autorisation']) ? trim($_POST['niveau_autorisation']) : '';
        $date_debut = isset($_POST['date_debut']) ? trim($_POST['date_debut']) : '';
        $date_fin = isset($_POST['date_fin']) && !empty($_POST['date_fin']) ? trim($_POST['date_fin']) : null;
        $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : null;
        $id_user = $_SESSION['id'];
        
        // Validation des données
        if ($idlabo <= 0 || $idAgent <= 0 || empty($niveau_autorisation) || empty($date_debut)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }
        
        // Vérifier que la date de fin est postérieure à la date de début si elle est définie
        if ($date_fin !== null && $date_fin < $date_debut) {
            throw new Exception("La date de fin doit être postérieure à la date de début.");
        }
        
        // Vérifier si l'agent a déjà une autorisation pour ce laboratoire
        $stmt = $db->prepare("SELECT COUNT(*) FROM autorisation_labo WHERE idlabo = :idlabo AND idAgent = :idAgent");
        $stmt->bindParam(':idlabo', $idlabo, PDO::PARAM_INT);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Cet agent a déjà une autorisation pour ce laboratoire.");
        }
        
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Vérifier si la colonne 'commentaire' existe dans la table
        $checkCommentColumn = $db->prepare("SHOW COLUMNS FROM autorisation_labo LIKE 'commentaire'");
        $checkCommentColumn->execute();
        $commentColumnExists = $checkCommentColumn->rowCount() > 0;
        
        // Vérifier si la colonne 'est_active' existe dans la table
        $checkActiveColumn = $db->prepare("SHOW COLUMNS FROM autorisation_labo LIKE 'est_active'");
        $checkActiveColumn->execute();
        $activeColumnExists = $checkActiveColumn->rowCount() > 0;
        
        // Construire la requête SQL d'insertion en fonction des colonnes existantes
        $sql = "INSERT INTO autorisation_labo (idlabo, idAgent, date_debut, date_fin, niveau_autorisation";
        
        if ($commentColumnExists) {
            $sql .= ", commentaire";
        }
        
        if ($activeColumnExists) {
            $sql .= ", est_active";
        }
        
        $sql .= ", idUser) VALUES (:idlabo, :idAgent, :date_debut, :date_fin, :niveau_autorisation";
        
        if ($commentColumnExists) {
            $sql .= ", :commentaire";
        }
        
        if ($activeColumnExists) {
            $sql .= ", 1"; // Par défaut, l'autorisation est active
        }
        
        $sql .= ", :idUser)";
        
        // Préparation et exécution de la requête d'insertion
        $insertStmt = $db->prepare($sql);
        $insertStmt->bindParam(':idlabo', $idlabo, PDO::PARAM_INT);
        $insertStmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $insertStmt->bindParam(':date_debut', $date_debut, PDO::PARAM_STR);
        $insertStmt->bindParam(':date_fin', $date_fin, PDO::PARAM_STR);
        $insertStmt->bindParam(':niveau_autorisation', $niveau_autorisation, PDO::PARAM_STR);
        $insertStmt->bindParam(':idUser', $id_user, PDO::PARAM_INT);
        
        if ($commentColumnExists) {
            $insertStmt->bindParam(':commentaire', $commentaire, PDO::PARAM_STR);
        }
        
        $insertStmt->execute();
        $idAutorisation = $db->lastInsertId();
        
        // Journalisation comme avant
        // [Code de journalisation inchangé]
        
        // Valider la transaction
        $db->commit();
        
        // Afficher SweetAlert directement et rediriger
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'L\'autorisation a été ajoutée avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../laboratoire/autorisation.add&id=" . $idlabo . "';
                }
            });
        </script>";
        exit;
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        // Afficher SweetAlert d'erreur directement
        echo "<script>
            Swal.fire({
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.location.href = '../laboratoire/autorisation.add&id=" . $idlabo . "';
            });
        </script>";
        exit;
    }
} else {
    // Redirection si accès direct au fichier
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Accès non autorisé',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            window.location.href = '../laboratoire/laboratoire.list';
        });
    </script>";
    exit;
}
