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
        $idECUE = isset($_POST['idECUE']) ? intval($_POST['idECUE']) : 0;
        $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
        $date_seance = isset($_POST['date_seance']) ? trim($_POST['date_seance']) : '';
        $heure_debut = isset($_POST['heure_debut']) ? trim($_POST['heure_debut']) : '';
        $heure_fin = isset($_POST['heure_fin']) ? trim($_POST['heure_fin']) : '';
        $salle = isset($_POST['salle']) ? trim($_POST['salle']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : null;
        $annee_acad_id = isset($_POST['annee_acad_id']) ? intval($_POST['annee_acad_id']) : 0;
        $id_user = $_SESSION['id'];
        
        // Validation des données
        if (empty($titre) || empty($date_seance) || empty($heure_debut) || 
            empty($heure_fin) || empty($salle) || $idECUE <= 0 || $annee_acad_id <= 0) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }
        
        // Vérifier que l'heure de fin est après l'heure de début
        if ($heure_fin <= $heure_debut) {
            throw new Exception("L'heure de fin doit être postérieure à l'heure de début.");
        }
        
        /*
        // Vérifier si la salle est disponible pour cette période
        $stmtCheckSalle = $db->prepare("SELECT COUNT(*) FROM seance_cours 
                                     WHERE date_seance = :date_seance 
                                     AND salle = :salle 
                                     AND ((heure_debut <= :heure_debut AND heure_fin > :heure_debut) 
                                          OR (heure_debut < :heure_fin AND heure_fin >= :heure_fin) 
                                          OR (heure_debut >= :heure_debut AND heure_fin <= :heure_fin))");
        
        $stmtCheckSalle->bindParam(':date_seance', $date_seance, PDO::PARAM_STR);
        $stmtCheckSalle->bindParam(':salle', $salle, PDO::PARAM_STR);
        $stmtCheckSalle->bindParam(':heure_debut', $heure_debut, PDO::PARAM_STR);
        $stmtCheckSalle->bindParam(':heure_fin', $heure_fin, PDO::PARAM_STR);
        $stmtCheckSalle->execute();
        
        if ($stmtCheckSalle->fetchColumn() > 0) {
            throw new Exception("La salle est déjà réservée pour ce créneau horaire.");
        }
            */
        
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Générer un QR code unique pour cette séance
        $qrcode = 'SEA_' . time() . '_' . rand(1000, 9999);
        
        // Jour de la semaine à partir de la date
        $jourSemaine = date('l', strtotime($date_seance));
        $joursFrancais = [
            'Monday' => 'Lundi',
            'Tuesday' => 'Mardi',
            'Wednesday' => 'Mercredi',
            'Thursday' => 'Jeudi',
            'Friday' => 'Vendredi',
            'Saturday' => 'Samedi',
            'Sunday' => 'Dimanche'
        ];
        $jour = $joursFrancais[$jourSemaine];
        
        // Insertion de la séance
        $stmt = $db->prepare("INSERT INTO seance_cours 
            (titre, date_seance, heure_debut, heure_fin, salle, qrcode, description, 
             \"idECUE\", annee_acad_id, \"idUser\") 
            VALUES 
            (:titre, :date_seance, :heure_debut, :heure_fin, :salle, :qrcode, 
             :description, :idECUE, :annee_acad_id, :idUser)");
        
        $stmt->bindParam(':titre', $titre, PDO::PARAM_STR);
        $stmt->bindParam(':date_seance', $date_seance, PDO::PARAM_STR);
        $stmt->bindParam(':heure_debut', $heure_debut, PDO::PARAM_STR);
        $stmt->bindParam(':heure_fin', $heure_fin, PDO::PARAM_STR);
        $stmt->bindParam(':salle', $salle, PDO::PARAM_STR);
        $stmt->bindParam(':qrcode', $qrcode, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':idECUE', $idECUE, PDO::PARAM_INT);
        $stmt->bindParam(':annee_acad_id', $annee_acad_id, PDO::PARAM_INT);
        $stmt->bindParam(':idUser', $id_user, PDO::PARAM_INT);
        
        $stmt->execute();
        $idSeance = $db->lastInsertId();
        
        // Récupérer les informations sur l'ECUE pour le journal
        $stmtEcue = $db->prepare("SELECT \"designationECUE\" FROM ecue WHERE \"idECUE\" = :idECUE");
        $stmtEcue->bindParam(':idECUE', $idECUE, PDO::PARAM_INT);
        $stmtEcue->execute();
        $ecue = $stmtEcue->fetch(PDO::FETCH_ASSOC);
        $designationECUE = $ecue['designationECUE'];
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO journal_activites 
            (user_type, user_id, type_activite, id_element, description, date_activite) 
            VALUES 
            ('enseignant', :user_id, 'seance', :id_element, :description, NOW())");
        
        $description = "Création d'une séance de cours: $titre pour le cours $designationECUE le $date_seance de $heure_debut à $heure_fin";
        
        $logStmt->bindParam(':user_id', $id_user, PDO::PARAM_INT);
        $logStmt->bindParam(':id_element', $idSeance, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Valider la transaction
        $db->commit();
        
        // Rediriger avec un message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'La séance de cours a été créée avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../cours/seances.list';
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
                window.location.href = '../cours/seance.add';
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
            window.location.href = '../cours/seances.list';
        });
    </script>";
    exit;
}
