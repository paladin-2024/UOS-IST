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

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Connexion::getInstance()->getPDO();
        
        // Récupérer les données du formulaire
        $idSeance = isset($_POST['idSeance']) ? intval($_POST['idSeance']) : 0;
        $idEtudiant = isset($_POST['idetudiant']) ? intval($_POST['idetudiant']) : 0;
        $heureArrivee = isset($_POST['heureArrivee']) ? $_POST['heureArrivee'] : '';
        $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
        
        // Validation des données
        if (!$idSeance || !$idEtudiant || empty($heureArrivee)) {
            throw new Exception('Tous les champs obligatoires doivent être remplis.');
        }
        
        // Vérifier si la séance existe
        $querySeance = "SELECT * FROM seance_labo WHERE idseance_labo = :idSeance";
        $stmtSeance = $db->prepare($querySeance);
        $stmtSeance->bindParam(':idSeance', $idSeance);
        $stmtSeance->execute();
        
        if (!$stmtSeance->fetch()) {
            throw new Exception('La séance spécifiée n\'existe pas.');
        }
        
        // Vérifier si l'étudiant existe
        $queryEtudiant = "SELECT * FROM etudiant_tempon WHERE idetudiant = :idEtudiant";
        $stmtEtudiant = $db->prepare($queryEtudiant);
        $stmtEtudiant->bindParam(':idEtudiant', $idEtudiant);
        $stmtEtudiant->execute();
        
        if (!$stmtEtudiant->fetch()) {
            throw new Exception('L\'étudiant spécifié n\'existe pas.');
        }
        
        // Vérifier si l'étudiant est déjà marqué présent pour cette séance
        $queryExistingPresence = "SELECT * FROM presence_labo WHERE idseance_labo = :idSeance AND idetudiant = :idEtudiant";
        $stmtExistingPresence = $db->prepare($queryExistingPresence);
        $stmtExistingPresence->bindParam(':idSeance', $idSeance);
        $stmtExistingPresence->bindParam(':idEtudiant', $idEtudiant);
        $stmtExistingPresence->execute();
        
        if ($stmtExistingPresence->fetch()) {
            throw new Exception('Cet étudiant est déjà marqué présent pour cette séance.');
        }
        
        // Construire la date et l'heure d'arrivée complète
        $dateSeance = $db->query("SELECT date_seance FROM seance_labo WHERE idseance_labo = $idSeance")->fetchColumn();
        $heureArriveeComplete = $dateSeance . ' ' . $heureArrivee . ':00';
        
        // Insérer la présence
        $queryInsert = "INSERT INTO presence_labo (idseance_labo, idetudiant, heure_arrivee, statut, commentaire, 
                        methode_enregistrement, \"idUser\", date_enregistrement, ip_address) 
                        VALUES (:idSeance, :idEtudiant, :heureArrivee, 'Présent', :commentaire, 
                        'Manuel', :idUser, NOW(), :ipAddress)";
        
        $stmtInsert = $db->prepare($queryInsert);
        $stmtInsert->bindParam(':idSeance', $idSeance);
        $stmtInsert->bindParam(':idEtudiant', $idEtudiant);
        $stmtInsert->bindParam(':heureArrivee', $heureArriveeComplete);
        $stmtInsert->bindParam(':commentaire', $commentaire);
        $stmtInsert->bindParam(':idUser', $_SESSION['id']);
        $stmtInsert->bindParam(':ipAddress', $_SERVER['REMOTE_ADDR']);
        
        if ($stmtInsert->execute()) {
            // Enregistrer dans le journal d'activités
            $queryJournal = "INSERT INTO journal_activites (user_type, user_id, type_activite, id_element, description, date_activite, ip_address) 
                            VALUES ('admin', :userId, 'presence_labo', :idSeance, :description, NOW(), :ipAddress)";
            
            $stmtJournal = $db->prepare($queryJournal);
            $stmtJournal->bindParam(':userId', $_SESSION['id']);
            $stmtJournal->bindParam(':idSeance', $idSeance);
            
            // Récupérer le nom de l'étudiant pour le journal
            $nomEtudiant = $db->query("SELECT noms FROM etudiant_tempon WHERE idetudiant = $idEtudiant")->fetchColumn();
            $description = "Ajout manuel d'une présence pour l'étudiant $nomEtudiant (ID: $idEtudiant)";
            
            $stmtJournal->bindParam(':description', $description);
            $stmtJournal->bindParam(':ipAddress', $_SERVER['REMOTE_ADDR']);
            $stmtJournal->execute();
            
            // Rediriger avec un message de succès
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'La présence a été enregistrée avec succès.'
                }).then(() => {
                    window.location.href = '../laboratoire/presence.list&id=$idSeance';
                });
            </script>";
        } else {
            throw new Exception('Erreur lors de l\'enregistrement de la présence.');
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
    // Redirection si accès direct au fichier
    header("Location: ../index.php");
    exit();
}
?>
