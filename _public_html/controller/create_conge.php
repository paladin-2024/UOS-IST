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
        $idAgent = isset($_POST['idAgent']) ? intval($_POST['idAgent']) : 0;
        $idTypeConge = isset($_POST['idTypeConge']) ? intval($_POST['idTypeConge']) : 0;
        $dateDebut = isset($_POST['dateDebut']) ? $_POST['dateDebut'] : '';
        $dateFin = isset($_POST['dateFin']) ? $_POST['dateFin'] : '';
        $motif = isset($_POST['motif']) ? trim($_POST['motif']) : '';
        $idUser = $_SESSION['id'];
        
        // Validation des données
        if ($idAgent <= 0 || $idTypeConge <= 0 || empty($dateDebut) || empty($dateFin)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }
        
        // Vérifier que la date de fin est après la date de début
        if (strtotime($dateFin) < strtotime($dateDebut)) {
            throw new Exception("La date de fin doit être postérieure à la date de début.");
        }
        
        // Vérifier si l'agent existe
        $stmt = $db->prepare("SELECT * FROM agent WHERE \"idAgent\" = :idAgent");
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->execute();
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$agent) {
            throw new Exception("Agent non trouvé.");
        }
        
        // Vérifier si le type de congé existe
        $stmt = $db->prepare("SELECT * FROM type_conge WHERE idtype_conge = :idTypeConge");
        $stmt->bindParam(':idTypeConge', $idTypeConge, PDO::PARAM_INT);
        $stmt->execute();
        $typeConge = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$typeConge) {
            throw new Exception("Type de congé non trouvé.");
        }
        
        // Vérifier si l'agent a déjà une demande en cours pour cette période
        $stmt = $db->prepare("SELECT * FROM demande_conge 
                              WHERE \"idAgent\" = :idAgent 
                              AND statut = 'En attente'
                              AND ((date_debut BETWEEN :dateDebut AND :dateFin) 
                                  OR (date_fin BETWEEN :dateDebut AND :dateFin)
                                  OR (:dateDebut BETWEEN date_debut AND date_fin))");
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->bindParam(':dateDebut', $dateDebut);
        $stmt->bindParam(':dateFin', $dateFin);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            throw new Exception("Une demande de congé est déjà en cours pour cette période.");
        }
        
        // Calculer le nombre de jours ouvrables demandés
        $debut = new DateTime($dateDebut);
        $fin = new DateTime($dateFin);
        $joursOuvrables = 0;
        
        for ($date = clone $debut; $date <= $fin; $date->modify('+1 day')) {
            $jour = $date->format('N'); // 1 (lundi) à 7 (dimanche)
            if ($jour < 6) { // Si ce n'est pas samedi (6) ou dimanche (7)
                $joursOuvrables++;
            }
        }
        
        // NOUVELLE VÉRIFICATION: Vérifier si la durée demandée dépasse la durée standard du type de congé
        if (!empty($typeConge['duree_standard']) && $typeConge['duree_standard'] > 0 && $joursOuvrables > $typeConge['duree_standard']) {
            throw new Exception("La durée demandée ({$joursOuvrables} jours) dépasse la durée standard pour ce type de congé ({$typeConge['duree_standard']} jours).");
        }
        
        // Vérifier le solde de congé disponible si le type est cumulable
        if ($typeConge['est_cumulable']) {
            // Récupérer l'année actuelle
            $annee = date('Y');
            
            // Vérifier le solde
            $stmt = $db->prepare("SELECT * FROM solde_conge 
                                  WHERE \"idAgent\" = :idAgent 
                                  AND idtype_conge = :idTypeConge 
                                  AND annee = :annee");
            $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
            $stmt->bindParam(':idTypeConge', $idTypeConge, PDO::PARAM_INT);
            $stmt->bindParam(':annee', $annee, PDO::PARAM_INT);
            $stmt->execute();
            
            $solde = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculer le solde disponible
            $soldeDisponible = 0;
            if ($solde) {
                $soldeDisponible = $solde['jours_acquis'] + $solde['jours_reportes'] - $solde['jours_pris'];
            }
            
            if ($soldeDisponible < $joursOuvrables) {
                throw new Exception("Solde de congé insuffisant. Solde disponible: " . $soldeDisponible . " jours.");
            }
        }
        
        // Traiter le document justificatif si présent
        $documentJustificatif = null;
        if (isset($_FILES['documentJustificatif']) && $_FILES['documentJustificatif']['error'] == 0) {
            $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
            $filename = $_FILES['documentJustificatif']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                throw new Exception("Format de fichier non autorisé. Formats acceptés: PDF, JPG, PNG.");
            }
            
            if ($_FILES['documentJustificatif']['size'] > 5 * 1024 * 1024) { // 5 Mo
                throw new Exception("Le fichier est trop volumineux. Taille maximale: 5 Mo.");
            }
            
            $newFilename = 'conge_' . $idAgent . '_' . time() . '.' . $ext;
            $uploadDir = dirname(__DIR__) . '/uploads/conges/';
            
            // Créer le répertoire s'il n'existe pas
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $uploadFile = $uploadDir . $newFilename;
            
            if (!move_uploaded_file($_FILES['documentJustificatif']['tmp_name'], $uploadFile)) {
                throw new Exception("Erreur lors du téléchargement du fichier.");
            }
            
            $documentJustificatif = $newFilename;
        }
        
        // Créer la demande de congé
        $stmt = $db->prepare("INSERT INTO demande_conge 
                             (\"idAgent\", idtype_conge, date_debut, date_fin, motif, 
                              document_justificatif, statut, date_demande, \"idUser\") 
                             VALUES 
                             (:idAgent, :idTypeConge, :dateDebut, :dateFin, :motif, 
                              :documentJustificatif, 'En attente', NOW(), :idUser)");
        
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->bindParam(':idTypeConge', $idTypeConge, PDO::PARAM_INT);
        $stmt->bindParam(':dateDebut', $dateDebut);
        $stmt->bindParam(':dateFin', $dateFin);
        $stmt->bindParam(':motif', $motif);
        $stmt->bindParam(':documentJustificatif', $documentJustificatif);
        $stmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
        
        $stmt->execute();
        $idDemande = $db->lastInsertId();
        
        if ($idDemande) {
            // Si le congé est cumulable, mettre à jour les jours pris
            if ($typeConge['est_cumulable']) {
                $stmt = $db->prepare("UPDATE solde_conge 
                                     SET jours_pris = jours_pris + :joursOuvrables,
                                         date_mise_a_jour = NOW()
                                     WHERE \"idAgent\" = :idAgent 
                                     AND idtype_conge = :idTypeConge 
                                     AND annee = :annee");
                $stmt->bindParam(':joursOuvrables', $joursOuvrables, PDO::PARAM_INT);
                $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
                $stmt->bindParam(':idTypeConge', $idTypeConge, PDO::PARAM_INT);
                $stmt->bindParam(':annee', $annee, PDO::PARAM_INT);
                $stmt->execute();
            }
            
            // Rediriger avec un message de succès
            echo "<script>
                Swal.fire({
                    title: 'Succès',
                    text: 'Votre demande de congé a été soumise avec succès.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../grh/conges.view&id=" . $idDemande . "';
                    }
                });
            </script>";
            exit;
        } else {
            throw new Exception("Erreur lors de la création de la demande de congé.");
        }
        
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.location.href = '../grh/conges.add';
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
            window.location.href = '../grh/conges.list';
        });
    </script>";
    exit;
}