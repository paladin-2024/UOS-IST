<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/GrilleAncienne.php';

header('Content-Type: application/json');

// Vérification d'authentification
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

try {
    $importId = isset($_POST['import_id']) ? intval($_POST['import_id']) : 0;
    $promotion = isset($_POST['promotion']) ? trim($_POST['promotion']) : null;
    $semestre = isset($_POST['semestre']) ? trim($_POST['semestre']) : null;
    $uesData = isset($_POST['ues']) ? json_decode($_POST['ues'], true) : [];
    $ecuésData = isset($_POST['ecues']) ? json_decode($_POST['ecues'], true) : [];
    
    if ($importId <= 0) {
        throw new Exception('ID d\'import invalide');
    }
    
    $grilleAncienne = new GrilleAncienne();
    $grilleAncienne->createTablesIfNotExists();
    $db = Connexion::getInstance()->getPDO();
    
    // Commencer une transaction
    $db->beginTransaction();
    
    // Mettre à jour l'import (promotion et semestre)
    if (!empty($promotion) || !empty($semestre)) {
        $updateClauses = [];
        $params = [];
        
        if (!empty($promotion)) {
            $updateClauses[] = 'promotion = ?';
            $params[] = $promotion;
        }
        if (!empty($semestre)) {
            $updateClauses[] = 'semestre = ?';
            $params[] = $semestre;
        }
        
        if (!empty($updateClauses)) {
            $params[] = $importId;
            $query = "UPDATE grilles_anciennes_imports SET " . implode(', ', $updateClauses) . " WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
        }
    }
    
    // Mettre à jour les UEs
    if (!empty($uesData)) {
        foreach ($uesData as $ueId => $ueModifications) {
            $setClauses = [];
            $params = [];
            
            if (isset($ueModifications['code_ue'])) {
                $setClauses[] = 'code_ue = ?';
                $params[] = $ueModifications['code_ue'];
            }
            if (isset($ueModifications['designation_ue'])) {
                $setClauses[] = 'designation_ue = ?';
                $params[] = $ueModifications['designation_ue'];
            }
            if (isset($ueModifications['semestre'])) {
                $setClauses[] = 'semestre = ?';
                $params[] = $ueModifications['semestre'];
            }
            if (isset($ueModifications['credits'])) {
                $setClauses[] = 'credits = ?';
                $params[] = floatval($ueModifications['credits']);
            }
            
            if (!empty($setClauses)) {
                $params[] = intval($ueId);
                $query = "UPDATE grilles_anciennes_ue SET " . implode(', ', $setClauses) . " WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute($params);
            }
        }
    }
    
    // Mettre à jour les ECUEs
    if (!empty($ecuésData)) {
        foreach ($ecuésData as $ecuéId => $ecuéModifications) {
            $setClauses = [];
            $params = [];
            
            if (isset($ecuéModifications['code_ecue'])) {
                $setClauses[] = 'code_ecue = ?';
                $params[] = $ecuéModifications['code_ecue'];
            }
            if (isset($ecuéModifications['designation_ecue'])) {
                $setClauses[] = 'designation_ecue = ?';
                $params[] = $ecuéModifications['designation_ecue'];
            }
            if (isset($ecuéModifications['coefficient'])) {
                $setClauses[] = 'coefficient = ?';
                $params[] = floatval($ecuéModifications['coefficient']);
            }
            if (isset($ecuéModifications['ue_id'])) {
                $setClauses[] = 'ue_id = ?';
                $params[] = intval($ecuéModifications['ue_id']);
            }
            
            if (!empty($setClauses)) {
                $params[] = intval($ecuéId);
                $query = "UPDATE grilles_anciennes_ecue SET " . implode(', ', $setClauses) . " WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute($params);
            }
        }
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'La grille a été mise à jour avec succès'
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    error_log('Erreur update_grille_ancienne: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
