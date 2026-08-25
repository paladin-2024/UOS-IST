<?php
session_start();
require_once dirname(dirname(__FILE__)) . '/config/Connexion.php';

// Définir le header pour JSON
header('Content-Type: application/json');

try {
    $db = Connexion::getInstance()->getPDO();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $results = [];
    
    // Vérifier la table dette_etudiant
    $sql = "SELECT column_name AS \"Field\", data_type AS \"Type\" FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'dette_etudiant'";
    $stmt = $db->query($sql);
    $results['dette_etudiant_columns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Vérifier la table dette_evaluation
    $sql = "SELECT column_name AS \"Field\", data_type AS \"Type\" FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'dette_evaluation'";
    $stmt = $db->query($sql);
    $results['dette_evaluation_columns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Vérifier la table dette_historique
    $sql = "SELECT column_name AS \"Field\", data_type AS \"Type\" FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'dette_historique'";
    $stmt = $db->query($sql);
    $results['dette_historique_columns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Vérifier la table cotes_grille
    $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'cotes_grille'";
    $stmt = $db->query($sql);
    $results['cotes_grille_exists'] = $stmt->rowCount() > 0;

    if ($results['cotes_grille_exists']) {
        $sql = "SELECT column_name AS \"Field\", data_type AS \"Type\" FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'cotes_grille'";
        $stmt = $db->query($sql);
        $results['cotes_grille_columns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Vérifier la table session
    $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'session'";
    $stmt = $db->query($sql);
    $results['session_exists'] = $stmt->rowCount() > 0;

    if ($results['session_exists']) {
        $sql = "SELECT * FROM session";
        $stmt = $db->query($sql);
        $results['sessions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Vérifier les années académiques actives
    $sql = "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'annee_acad' AND column_name = 'est_active'";
    $stmt = $db->query($sql);
    $results['annee_acad_has_est_active'] = $stmt->rowCount() > 0;
    
    if ($results['annee_acad_has_est_active']) {
        $sql = "SELECT * FROM annee_acad WHERE est_active = 1";
        $stmt = $db->query($sql);
        $results['annee_active'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Vérifier un exemple de dette
    $sql = "SELECT COUNT(*) as total FROM dette_etudiant";
    $stmt = $db->query($sql);
    $results['total_dettes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'message' => 'Test de base de données réussi',
        'results' => $results
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur PDO: ' . $e->getMessage(),
        'code' => $e->getCode()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}