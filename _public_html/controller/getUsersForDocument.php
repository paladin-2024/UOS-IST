<?php
/**
 * Contrôleur AJAX pour récupérer les utilisateurs associés à un document
 * 
 * Point d'entrée API pour obtenir la liste des utilisateurs ayant accès à un document spécifique.
 * Retourne une réponse JSON avec les données des utilisateurs.
 * 
 * @method GET
 * @param int id - ID du document (requis dans $_GET)
 * @return JSON - Liste des utilisateurs ou message d'erreur
 * 
 * Codes de réponse HTTP:
 * - 200: Succès
 * - 400: Requête invalide (méthode incorrecte ou ID manquant)
 * - 500: Erreur serveur
 */

require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

// Configuration de l'en-tête pour réponse JSON avec support UTF-8
header('Content-Type: application/json; charset=utf-8');

try {
    // Validation de la requête GET avec paramètre ID obligatoire
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
        $docId = intval($_GET['id']); // Conversion sécurisée en entier
        $structure = new Structure();

        // Récupération des utilisateurs via le modèle Structure
        $users = $structure->getUsersByDocumentId($docId);
        http_response_code(200);
        echo json_encode($users, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Requête invalide: méthode incorrecte ou paramètre manquant
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
} catch (Throwable $e) {
    // Gestion des erreurs avec détails pour débogage
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
?>
