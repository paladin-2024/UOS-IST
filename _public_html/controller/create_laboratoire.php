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
        $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : null;
        $localisation = isset($_POST['localisation']) ? trim($_POST['localisation']) : null;
        $responsable_id = isset($_POST['responsable_id']) ? intval($_POST['responsable_id']) : 0;
        $annee_acad_id = isset($_POST['annee_acad_id']) ? intval($_POST['annee_acad_id']) : 0;
        $id_user = $_SESSION['id'];
        
        // Validation des données
        if (empty($nom) || $responsable_id <= 0 || $annee_acad_id <= 0) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }
        
        // Vérifier si le laboratoire existe déjà pour cette année académique
        $stmt = $db->prepare("SELECT COUNT(*) FROM laboratoire WHERE nom = :nom AND annee_acad_id = :annee_acad_id");
        $stmt->bindParam(':nom', $nom, PDO::PARAM_STR);
        $stmt->bindParam(':annee_acad_id', $annee_acad_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Un laboratoire avec ce nom existe déjà pour cette année académique.");
        }
        
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Insertion du laboratoire
        $stmt = $db->prepare("INSERT INTO laboratoire 
            (nom, description, localisation, responsable_id, annee_acad_id, idUser) 
            VALUES 
            (:nom, :description, :localisation, :responsable_id, :annee_acad_id, :idUser)");
        
        $stmt->bindParam(':nom', $nom, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':localisation', $localisation, PDO::PARAM_STR);
        $stmt->bindParam(':responsable_id', $responsable_id, PDO::PARAM_INT);
        $stmt->bindParam(':annee_acad_id', $annee_acad_id, PDO::PARAM_INT);
        $stmt->bindParam(':idUser', $id_user, PDO::PARAM_INT);
        
        $stmt->execute();
        $idLabo = $db->lastInsertId();
        
        // Ajouter une autorisation pour le responsable
        $stmt = $db->prepare("INSERT INTO autorisation_labo 
            (idlabo, idAgent, date_debut, niveau_autorisation, idUser) 
            VALUES 
            (:idlabo, :idAgent, CURRENT_DATE(), 'Admin', :idUser)");
        
        $stmt->bindParam(':idlabo', $idLabo, PDO::PARAM_INT);
        $stmt->bindParam(':idAgent', $responsable_id, PDO::PARAM_INT);
        $stmt->bindParam(':idUser', $id_user, PDO::PARAM_INT);
        
        $stmt->execute();
        
        // Récupérer le nom du responsable pour le journal
        $stmtResp = $db->prepare("SELECT noms FROM agent WHERE idAgent = :idAgent");
        $stmtResp->bindParam(':idAgent', $responsable_id, PDO::PARAM_INT);
        $stmtResp->execute();
        $responsable = $stmtResp->fetch(PDO::FETCH_ASSOC);
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO journal_activites 
            (user_type, user_id, type_activite, id_element, description, date_activite) 
            VALUES 
            ('admin', :user_id, 'laboratoire', :id_element, :description, NOW())");
        
        $description_log = "Création du laboratoire: $nom (Responsable: " . ($responsable['noms'] ?? 'Inconnu') . ")";
        
        $logStmt->bindParam(':user_id', $id_user, PDO::PARAM_INT);
        $logStmt->bindParam(':id_element', $idLabo, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description_log, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Valider la transaction
        $db->commit();
        
        // Rediriger avec un message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'Le laboratoire a été créé avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../laboratoire/laboratoire.list';
                }
            });
        </script>";
        exit;
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollBack();
        
        echo "<script>
            Swal.fire({
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.location.href = '../laboratoire/laboratoire.add';
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
