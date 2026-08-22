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
        $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
        $date_seance = isset($_POST['date_seance']) ? trim($_POST['date_seance']) : '';
        $heure_debut = isset($_POST['heure_debut']) ? trim($_POST['heure_debut']) : '';
        $heure_fin = isset($_POST['heure_fin']) ? trim($_POST['heure_fin']) : '';
        $idresponsable = isset($_POST['idresponsable']) ? intval($_POST['idresponsable']) : 0;
        $description = isset($_POST['description']) ? trim($_POST['description']) : null;
        $activer_qrcode = isset($_POST['activer_qrcode']) ? 1 : 0;
        $id_user = $_SESSION['id'];
        
        // Validation des données
        if (empty($titre) || empty($date_seance) || empty($heure_debut) || empty($heure_fin) || $idlabo <= 0 || $idresponsable <= 0) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }
        
        // Vérification que l'heure de fin est après l'heure de début
        if ($heure_debut >= $heure_fin) {
            throw new Exception("L'heure de fin doit être postérieure à l'heure de début.");
        }
        
        // Vérifier si le laboratoire existe et récupérer l'année académique
        $stmt = $db->prepare("SELECT annee_acad_id FROM laboratoire WHERE idlabo = :idlabo");
        $stmt->bindParam(':idlabo', $idlabo, PDO::PARAM_INT);
        $stmt->execute();
        $labo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$labo) {
            throw new Exception("Le laboratoire spécifié n'existe pas.");
        }
        
        $annee_acad_id = $labo['annee_acad_id'];
        
        // Vérifier si l'agent est autorisé pour ce laboratoire
        $stmt = $db->prepare("SELECT COUNT(*) FROM autorisation_labo 
                              WHERE idlabo = :idlabo 
                              AND idAgent = :idAgent 
                              AND date_debut <= CURRENT_DATE()
                              AND (date_fin IS NULL OR date_fin >= CURRENT_DATE())");
        $stmt->bindParam(':idlabo', $idlabo, PDO::PARAM_INT);
        $stmt->bindParam(':idAgent', $idresponsable, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            throw new Exception("L'agent sélectionné n'est pas autorisé pour ce laboratoire.");
        }
        
        // Générer un QR code si nécessaire
        $qrcode = null;
        if ($activer_qrcode) {
            $qrcode = 'LABO_' . uniqid() . '_' . date('Ymd');
        }
        
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Insertion de la séance de laboratoire
        $stmt = $db->prepare("INSERT INTO seance_labo 
            (titre, date_seance, heure_debut, heure_fin, description, qrcode, idlabo, idUser, annee_acad_id) 
            VALUES 
            (:titre, :date_seance, :heure_debut, :heure_fin, :description, :qrcode, :idlabo, :idUser, :annee_acad_id)");
        
        $stmt->bindParam(':titre', $titre, PDO::PARAM_STR);
        $stmt->bindParam(':date_seance', $date_seance, PDO::PARAM_STR);
        $stmt->bindParam(':heure_debut', $heure_debut, PDO::PARAM_STR);
        $stmt->bindParam(':heure_fin', $heure_fin, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':qrcode', $qrcode, PDO::PARAM_STR);
        $stmt->bindParam(':idlabo', $idlabo, PDO::PARAM_INT);
        $stmt->bindParam(':idUser', $id_user, PDO::PARAM_INT);
        $stmt->bindParam(':annee_acad_id', $annee_acad_id, PDO::PARAM_INT);
        
        $stmt->execute();
        $idSeance = $db->lastInsertId();
        
        // Récupérer le nom du laboratoire pour le journal
        $stmtLabo = $db->prepare("SELECT nom FROM laboratoire WHERE idlabo = :idlabo");
        $stmtLabo->bindParam(':idlabo', $idlabo, PDO::PARAM_INT);
        $stmtLabo->execute();
        $laboratoire = $stmtLabo->fetch(PDO::FETCH_ASSOC);
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO journal_activites 
            (user_type, user_id, type_activite, id_element, description, date_activite) 
            VALUES 
            ('admin', :user_id, 'seance_labo', :id_element, :description, NOW())");
        
        $date_formatted = date('d/m/Y', strtotime($date_seance));
        $description_log = "Création d'une séance de laboratoire: '$titre' pour le laboratoire '" . ($laboratoire['nom'] ?? 'Inconnu') . "' du $date_formatted";
        
        $logStmt->bindParam(':user_id', $id_user, PDO::PARAM_INT);
        $logStmt->bindParam(':id_element', $idSeance, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description_log, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Valider la transaction
        $db->commit();
        
        // Rediriger avec un message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'La séance de laboratoire a été créée avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../laboratoire/seance.list&id=$idlabo';
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
                if (result.isConfirmed) {
                    window.history.back();
                }
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
