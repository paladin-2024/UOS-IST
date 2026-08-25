<?php
session_start();
require_once "../models/Connexion.php";
require_once "../utils/SecurityUtils.php";

// Vérifier que l'utilisateur est administrateur
if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Récupérer les scripts de création de tables
    $scripts = SecurityUtils::getTableCreationScripts();
    
    // Exécuter chaque script
    foreach ($scripts as $script) {
        $db->exec($script);
    }
    
    // Ajouter les colonnes de couleurs à la table promotion si elles n'existent pas
    $checkColumnsQuery = "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'promotion' AND column_name = 'color_primary'";
    $stmt = $db->query($checkColumnsQuery);
    
    if ($stmt->rowCount() === 0) {
        $addColumnsQuery = "ALTER TABLE promotion 
                           ADD COLUMN color_primary VARCHAR(20) NULL,
                           ADD COLUMN color_secondary VARCHAR(20) NULL";
        $db->exec($addColumnsQuery);
    }
    
    echo json_encode(['success' => true, 'message' => 'Tables de sécurité installées avec succès']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur d\'installation: ' . $e->getMessage()]);
}
