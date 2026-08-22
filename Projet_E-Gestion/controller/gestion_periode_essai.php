<?php
session_start();
require_once dirname(__DIR__).'/config/Connexion.php';

if (!isset($_SESSION['id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$connexion = Connexion::getInstance()->getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'creer_periode':
                $clientNom = trim($_POST['client_nom']);
                $clientEmail = trim($_POST['client_email'] ?? '');
                $dureeJours = intval($_POST['duree_jours']);
                
                $stmt = $connexion->prepare("
                    INSERT INTO periodes_essai (client_nom, client_email, date_debut, date_fin, statut)
                    VALUES (:client_nom, :client_email, NOW(), DATE_ADD(NOW(), INTERVAL :duree DAY), 'Actif')
                ");
                $stmt->bindParam(':client_nom', $clientNom);
                $stmt->bindParam(':client_email', $clientEmail);
                $stmt->bindParam(':duree', $dureeJours);
                $stmt->execute();
                
                $_SESSION['message'] = 'Période d\'essai créée avec succès!';
                $_SESSION['messageType'] = 'success';
                break;
                
            case 'prolonger_periode':
                $periodeId = intval($_POST['periode_id']);
                $joursSupplementaires = intval($_POST['jours_supplementaires']);
                
                $stmt = $connexion->prepare("
                    UPDATE periodes_essai 
                    SET date_fin = DATE_ADD(date_fin, INTERVAL :jours DAY),
                        statut = 'Actif',
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->bindParam(':jours', $joursSupplementaires);
                $stmt->bindParam(':id', $periodeId);
                $stmt->execute();
                
                $_SESSION['message'] = 'Période d\'essai prolongée avec succès!';
                $_SESSION['messageType'] = 'success';
                break;
                
            case 'suspendre_periode':
                $periodeId = intval($_POST['periode_id']);
                
                $stmt = $connexion->prepare("
                    UPDATE periodes_essai 
                    SET statut = 'Suspendu',
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->bindParam(':id', $periodeId);
                $stmt->execute();
                
                $_SESSION['message'] = 'Période d\'essai suspendue!';
                $_SESSION['messageType'] = 'warning';
                break;
                
            case 'reactiver_periode':
                $periodeId = intval($_POST['periode_id']);
                
                $stmt = $connexion->prepare("
                    UPDATE periodes_essai 
                    SET statut = 'Actif',
                        updated_at = NOW()
                    WHERE id = :id AND date_fin > NOW()
                ");
                $stmt->bindParam(':id', $periodeId);
                $stmt->execute();
                
                $_SESSION['message'] = 'Période d\'essai réactivée!';
                $_SESSION['messageType'] = 'success';
                break;
                
            default:
                throw new Exception('Action non reconnue');
        }
        
    } catch (Exception $e) {
        $_SESSION['message'] = 'Erreur: ' . $e->getMessage();
        $_SESSION['messageType'] = 'danger';
    }
    
    header('Location: ../index.php?view=admin/gestion_periodes_essai');
    exit;
}

// Récupération des périodes d'essai pour affichage
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_periodes':
            $stmt = $connexion->prepare("
                SELECT id, client_nom, client_email, date_debut, date_fin, statut, 
                       nombre_connexions, derniere_connexion,
                       CASE 
                           WHEN date_fin > NOW() AND statut = 'Actif' THEN DATEDIFF(date_fin, NOW())
                           ELSE 0 
                       END as jours_restants
                FROM periodes_essai 
                ORDER BY date_debut DESC
            ");
            $stmt->execute();
            $periodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($periodes);
            break;
            
        case 'get_periode':
            $id = intval($_GET['id']);
            $stmt = $connexion->prepare("
                SELECT * FROM periodes_essai WHERE id = :id
            ");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $periode = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode($periode);
            break;
            
        default:
            echo json_encode(['error' => 'Action non reconnue']);
    }
    exit;
}
?>