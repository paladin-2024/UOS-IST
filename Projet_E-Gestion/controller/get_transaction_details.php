<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Récupérer l'ID de la transaction
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    echo json_encode(['error' => 'ID de transaction manquant']);
    exit;
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Requête pour récupérer les détails de la transaction
    $sql = "
        SELECT t.*, 
               c.designation AS categorie_nom,
               CONCAT(a.noms) AS agent_nom,
               CASE 
                   WHEN t.source = 'Caisse' THEN (SELECT designation FROM caisses WHERE id = t.source_id)
                                      WHEN t.source = 'Banque' THEN (SELECT intitule_compte FROM comptes_bancaires WHERE id = t.source_id)
                   ELSE ''
               END AS source_nom,
               CASE 
                   WHEN t.type = 'Transfert' THEN (
                       CASE 
                           WHEN EXISTS(SELECT 1 FROM caisses WHERE id = t.destination_id) 
                               THEN (SELECT designation FROM caisses WHERE id = t.destination_id)
                           WHEN EXISTS(SELECT 1 FROM comptes_bancaires WHERE id = t.destination_id) 
                               THEN (SELECT CONCAT(intitule_compte, ' - ', nom_banque) FROM comptes_bancaires WHERE id = t.destination_id)
                           ELSE ''
                       END
                   )
                   ELSE ''
               END AS destination_nom
        FROM transactions t
        LEFT JOIN categories_budget c ON t.categorie_id = c.id
        LEFT JOIN agent a ON t.\"idAgent\" = a.\"idAgent\"
        WHERE t.id = :id
    ";
    
    $stmt = $connexion->prepare($sql);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode(['error' => 'Transaction non trouvée']);
        exit;
    }
    
    // Renvoyer les données en JSON
    echo json_encode($transaction);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}
