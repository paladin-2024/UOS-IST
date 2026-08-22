<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

header('Content-Type: application/json');

// Récupérer les données JSON envoyées
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

// Vérifier si les données nécessaires sont présentes
if (!isset($data['student_id']) || !isset($data['matricule']) || 
    !isset($data['seance_id']) || !isset($data['type']) || 
    !isset($data['code'])) {
    echo json_encode(['success' => false, 'message' => 'Données incomplètes']);
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Récupérer l'adresse IP du client
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP)) {
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    
    // Vérifier si cette adresse IP a déjà enregistré une présence pour cette séance
    if ($data['type'] === 'presence_labo') {
        $stmt = $db->prepare("
            SELECT * FROM presence_labo 
            WHERE idseance_labo = :seance_id AND ip_address = :ip_address
        ");
    } else if ($data['type'] === 'presence_cours') {
        $stmt = $db->prepare("
            SELECT * FROM presence_cours 
            WHERE idseance = :seance_id AND ip_address = :ip_address
        ");
    } else {
        echo json_encode(['success' => false, 'message' => 'Type de séance non valide']);
        exit;
    }
    
    $stmt->bindParam(':seance_id', $data['seance_id'], PDO::PARAM_INT);
    $stmt->bindParam(':ip_address', $ipAddress, PDO::PARAM_STR);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Enregistrer la tentative de fraude
        $fraudStmt = $db->prepare("
            INSERT INTO tentatives_fraude_presence (
                ip_address, idseance, type_seance, matricule_tente, date_tentative, details
            ) VALUES (
                :ip_address, :seance_id, :type_seance, :matricule, NOW(), :details
            )
        ");
        
        $typeSeance = ($data['type'] === 'presence_labo') ? 'labo' : 'cours';
        $details = "Tentative d'utiliser un même appareil pour enregistrer plusieurs présences à la même séance.";
        
        $fraudStmt->bindParam(':ip_address', $ipAddress, PDO::PARAM_STR);
        $fraudStmt->bindParam(':seance_id', $data['seance_id'], PDO::PARAM_INT);
        $fraudStmt->bindParam(':type_seance', $typeSeance, PDO::PARAM_STR);
        $fraudStmt->bindParam(':matricule', $data['matricule'], PDO::PARAM_STR);
        $fraudStmt->bindParam(':details', $details, PDO::PARAM_STR);
        $fraudStmt->execute();
        
        echo json_encode([
            'success' => false, 
            'message' => 'Tentative de fraude détectée. Veuillez utiliser votre propre numéro matricule ou contacter l\'administrateur.'
        ]);
        exit;
    }
    
    // Vérifier si le code QR est valide
    if ($data['type'] === 'presence_labo') {
        $stmt = $db->prepare("
            SELECT *, DATE(date_seance) as date_jour, TIME(heure_debut) as heure_debut_time, 
            TIME(heure_fin) as heure_fin_time
            FROM seance_labo 
            WHERE idseance_labo = :seance_id AND qrcode = :code
        ");
    } else if ($data['type'] === 'presence_cours') {
        $stmt = $db->prepare("
            SELECT *, DATE(date_seance) as date_jour, TIME(heure_debut) as heure_debut_time, 
            TIME(heure_fin) as heure_fin_time
            FROM seance_cours 
            WHERE idseance = :seance_id AND qrcode = :code
        ");
    }
    
    $stmt->bindParam(':seance_id', $data['seance_id'], PDO::PARAM_INT);
    $stmt->bindParam(':code', $data['code'], PDO::PARAM_STR);
    $stmt->execute();
    
    $seance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$seance) {
        echo json_encode(['success' => false, 'message' => 'QR Code non valide ou expiré']);
        exit;
    }
    
    // Vérifier si la séance n'est pas déjà passée
    $dateSeance = $seance['date_jour'];
    $heureDebut = $seance['heure_debut_time'];
    $heureFin = $seance['heure_fin_time'];
    
    $dateActuelle = date('Y-m-d');
    $heureActuelle = date('H:i:s');
    
    // Vérifier si la date de la séance est antérieure à aujourd'hui
    if ($dateSeance < $dateActuelle) {
        echo json_encode(['success' => false, 'message' => 'Cette séance est déjà passée. Présence non autorisée.']);
        exit;
    }
    
    // Si c'est le même jour, vérifier si l'heure actuelle est dans la plage horaire de la séance
    // avec une tolérance de 15 minutes avant le début et 30 minutes après la fin
    if ($dateSeance == $dateActuelle) {
        // Calculer l'heure de début avec tolérance (15 minutes avant)
        $heureDebutAvecTolerance = date('H:i:s', strtotime($heureDebut) - 15 * 60);
        
        // Calculer l'heure de fin avec tolérance (30 minutes après)
        $heureFinAvecTolerance = date('H:i:s', strtotime($heureFin) + 30 * 60);
        
        // Vérifier si l'heure actuelle est trop tôt
        if ($heureActuelle < $heureDebutAvecTolerance) {
            echo json_encode([
                'success' => false, 
                'message' => 'La séance n\'a pas encore commencé. Veuillez revenir à l\'heure prévue.'
            ]);
            exit;
        }
        
        // Vérifier si l'heure actuelle est trop tard
        if ($heureActuelle > $heureFinAvecTolerance) {
            echo json_encode([
                'success' => false, 
                'message' => 'La séance est terminée. Présence tardive non autorisée.'
            ]);
            exit;
        }
    }
    
    // Vérifier si l'étudiant n'a pas déjà enregistré sa présence
    if ($data['type'] === 'presence_labo') {
        $stmt = $db->prepare("
            SELECT * FROM presence_labo 
            WHERE idseance_labo = :seance_id AND idetudiant = :student_id
        ");
    } else {
        $stmt = $db->prepare("
            SELECT * FROM presence_cours 
            WHERE idseance = :seance_id AND idetudiant = :student_id
        ");
    }
    
    $stmt->bindParam(':seance_id', $data['seance_id'], PDO::PARAM_INT);
    $stmt->bindParam(':student_id', $data['student_id'], PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Vous avez déjà enregistré votre présence pour cette séance']);
        exit;
    }
    
    // Déterminer le statut en fonction de l'heure d'arrivée
    $statut = 'Présent';
    $commentaire = 'Présence enregistrée via QR Code';
    
        // Si l'étudiant arrive après le début du cours mais dans la tolérance
        if ($dateSeance == $dateActuelle && $heureActuelle > $heureDebut) {
            // Calculer le retard en minutes
            $retardMinutes = (strtotime($heureActuelle) - strtotime($heureDebut)) / 60;
            
            // Si le retard est entre 1 et 15 minutes
            if ($retardMinutes > 0 && $retardMinutes <= 15) {
                $statut = 'Retard';
                $commentaire = 'Retard de ' . round($retardMinutes) . ' minutes. Présence enregistrée via QR Code';
            } 
            // Si le retard est supérieur à 15 minutes
            else if ($retardMinutes > 15) {
                $statut = 'Retard';
                $commentaire = 'Retard de ' . round($retardMinutes) . ' minutes. Présence enregistrée via QR Code';
            }
        }
        
        // Enregistrer la présence dans la base de données avec l'adresse IP
        if ($data['type'] === 'presence_labo') {
            $stmt = $db->prepare("
                INSERT INTO presence_labo (
                    idseance_labo, idetudiant, heure_arrivee, statut, 
                    commentaire, methode_enregistrement, ip_address, latitude, longitude, idUser, date_enregistrement
                ) VALUES (
                    :seance_id, :student_id, NOW(), :statut, 
                    :commentaire, 'QR Code', :ip_address, :latitude, :longitude, NULL, NOW()
                )
            ");
        } else {
            $stmt = $db->prepare("
                INSERT INTO presence_cours (
                    idseance, idetudiant, heure_arrivee, statut, 
                    commentaire, methode_enregistrement, ip_address, latitude, longitude, idUser, date_enregistrement
                ) VALUES (
                    :seance_id, :student_id, NOW(), :statut, 
                    :commentaire, 'QR Code', :ip_address, :latitude, :longitude, NULL, NOW()
                )
            ");
        }
        
        // Utiliser des valeurs par défaut pour latitude et longitude
        $latitude = isset($data['latitude']) ? $data['latitude'] : 0;
        $longitude = isset($data['longitude']) ? $data['longitude'] : 0;
        
        $stmt->bindParam(':seance_id', $data['seance_id'], PDO::PARAM_INT);
        $stmt->bindParam(':student_id', $data['student_id'], PDO::PARAM_INT);
        $stmt->bindParam(':statut', $statut, PDO::PARAM_STR);
        $stmt->bindParam(':commentaire', $commentaire, PDO::PARAM_STR);
        $stmt->bindParam(':ip_address', $ipAddress, PDO::PARAM_STR);
        $stmt->bindParam(':latitude', $latitude, PDO::PARAM_STR);
        $stmt->bindParam(':longitude', $longitude, PDO::PARAM_STR);
        $success = $stmt->execute();
        
        if ($success) {
            // Enregistrer l'activité dans le journal
            $stmt = $db->prepare("
                INSERT INTO journal_activites (
                    user_type, user_id, type_activite, id_element, 
                    description, date_activite, ip_address
                ) VALUES (
                    'etudiant', :student_id, :type_activite, :seance_id, 
                    :description, NOW(), :ip_address
                )
            ");
            
            $typeActivite = ($data['type'] === 'presence_labo') ? 'presence_laboratoire' : 'presence_cours';
            $description = "Présence ($statut) enregistrée via QR Code pour la séance #" . $data['seance_id'];
            
            $stmt->bindParam(':student_id', $data['student_id'], PDO::PARAM_INT);
            $stmt->bindParam(':type_activite', $typeActivite, PDO::PARAM_STR);
            $stmt->bindParam(':seance_id', $data['seance_id'], PDO::PARAM_INT);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':ip_address', $ipAddress, PDO::PARAM_STR);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Présence enregistrée avec succès' . ($statut != 'Présent' ? ' (Statut: ' . $statut . ')' : '')
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement de la présence'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()
        ]);
    }
    
    // Supprimer la fonction calculateDistance qui n'est plus nécessaire
    ?>
    