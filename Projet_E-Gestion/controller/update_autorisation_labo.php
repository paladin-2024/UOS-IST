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
        $idautorisation = isset($_POST['idautorisation']) ? intval($_POST['idautorisation']) : 0;
        $niveau_autorisation = isset($_POST['niveau_autorisation']) ? trim($_POST['niveau_autorisation']) : '';
        $date_debut = isset($_POST['date_debut']) ? trim($_POST['date_debut']) : '';
        $date_fin = isset($_POST['date_fin']) && !empty($_POST['date_fin']) ? trim($_POST['date_fin']) : null;
        $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : null;
        $est_active = isset($_POST['est_active']) ? 1 : 0;
        $idUser = $_SESSION['id'];
        
        // Validation des données
        if ($idautorisation <= 0 || empty($niveau_autorisation) || empty($date_debut)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }
        
        // Récupérer l'idlabo associé à cette autorisation pour redirection ultérieure
        $stmtLabo = $db->prepare("SELECT idlabo FROM autorisation_labo WHERE idautorisation = :idautorisation");
        $stmtLabo->bindParam(':idautorisation', $idautorisation, PDO::PARAM_INT);
        $stmtLabo->execute();
        $labo = $stmtLabo->fetch(PDO::FETCH_ASSOC);
        
        if (!$labo) {
            throw new Exception("Autorisation non trouvée.");
        }
        
        $idlabo = $labo['idlabo'];
        
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Vérifier si la colonne 'est_active' existe dans la table
        $checkColumn = $db->prepare("SHOW COLUMNS FROM autorisation_labo LIKE 'est_active'");
        $checkColumn->execute();
        $columnExists = $checkColumn->rowCount() > 0;
        
        // Vérifier si la colonne 'commentaire' existe dans la table
        $checkCommentColumn = $db->prepare("SHOW COLUMNS FROM autorisation_labo LIKE 'commentaire'");
        $checkCommentColumn->execute();
        $commentColumnExists = $checkCommentColumn->rowCount() > 0;
        
        // Construire la requête SQL en fonction des colonnes existantes
        $sql = "UPDATE autorisation_labo SET 
                niveau_autorisation = :niveau_autorisation,
                date_debut = :date_debut,
                date_fin = :date_fin";
        
        // Ajouter la colonne est_active si elle existe
        if ($columnExists) {
            $sql .= ", est_active = :est_active";
        }
        
        // Ajouter la colonne commentaire si elle existe
        if ($commentColumnExists) {
            $sql .= ", commentaire = :commentaire";
        }
        
        $sql .= " WHERE idautorisation = :idautorisation";
        
        // Préparation et exécution de la requête
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':niveau_autorisation', $niveau_autorisation, PDO::PARAM_STR);
        $stmt->bindParam(':date_debut', $date_debut, PDO::PARAM_STR);
        $stmt->bindParam(':date_fin', $date_fin, PDO::PARAM_STR);
        $stmt->bindParam(':idautorisation', $idautorisation, PDO::PARAM_INT);
        
        // Lier les paramètres supplémentaires si les colonnes existent
        if ($columnExists) {
            $stmt->bindParam(':est_active', $est_active, PDO::PARAM_INT);
        }
        
        if ($commentColumnExists) {
            $stmt->bindParam(':commentaire', $commentaire, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        
        // Récupérer les informations de l'agent pour la journalisation
        $stmtAgent = $db->prepare("
            SELECT a.idAgent, a.noms 
            FROM autorisation_labo al
            JOIN agent a ON al.idAgent = a.idAgent
            WHERE al.idautorisation = :idautorisation
        ");
        $stmtAgent->bindParam(':idautorisation', $idautorisation, PDO::PARAM_INT);
        $stmtAgent->execute();
        $agent = $stmtAgent->fetch(PDO::FETCH_ASSOC);
        
        // Journaliser l'action
        $logStmt = $db->prepare("INSERT INTO journal_activites 
            (user_type, user_id, type_activite, id_element, description, date_activite) 
            VALUES 
            ('admin', :user_id, 'autorisation_labo', :id_element, :description, NOW())");
        
        $descriptionLog = "Mise à jour de l'autorisation pour " . ($agent['noms'] ?? 'agent inconnu') . 
                          " (niveau: $niveau_autorisation, actif: " . ($est_active ? 'Oui' : 'Non') . ")";
        
        $logStmt->bindParam(':user_id', $idUser, PDO::PARAM_INT);
        $logStmt->bindParam(':id_element', $idautorisation, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $descriptionLog, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Valider la transaction
        $db->commit();
        
        // Afficher SweetAlert directement et rediriger
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'L\'autorisation a été mise à jour avec succès.',
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
                window.location.href = '../laboratoire/autorisation.add&id=" . ($idlabo ?? 0) . "';
            });
        </script>";
        exit;
    }
} else {
    // Accès direct au fichier sans requête POST
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Méthode d\'accès non autorisée',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            window.location.href = '../laboratoire/laboratoire.list';
        });
    </script>";
    exit;
}
