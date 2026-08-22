<?php
/**
 * API pour récupérer les logs de journalisation
 * Utilisé par le script de test et l'interface d'administration
 */

session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/JournalServeur.php';

header('Content-Type: application/json');

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

$journalServeur = new JournalServeur();
$action = isset($_GET['action']) ? $_GET['action'] : 'get';

try {
    switch ($action) {
        case 'get':
            // Récupérer les logs pour un module spécifique
            $module = isset($_GET['module']) ? $_GET['module'] : null;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
            
            if (!$module) {
                echo json_encode(['success' => false, 'message' => 'Module requis']);
                exit();
            }
            
            $filtres = ['module' => $module];
            $result = $journalServeur->obtenirLogs($filtres, 1, $limit);
            
            echo json_encode([
                'success' => true,
                'logs' => $result['logs'],
                'total' => $result['total'],
                'pages' => $result['pages']
            ]);
            break;
        
        case 'statistics':
            // Récupérer les statistiques globales
            $stats = $journalServeur->obtenirStatistiques();
            $typeStats = $journalServeur->obtenirLogsParType();
            
            // Compter le nombre de modules distincts avec logs
            $pdo = Connexion::getInstance()->getPDO();
            $stmt = $pdo->query("SELECT COUNT(DISTINCT module) as modules FROM journal_serveur");
            $moduleCount = $stmt->fetch(PDO::FETCH_ASSOC)['modules'];
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'total' => $stats['total_logs'] ?? 0,
                    'succes' => $stats['succes'] ?? 0,
                    'erreurs' => $stats['erreurs'] ?? 0,
                    'modules' => $moduleCount ?? 0
                ],
                'par_type' => $typeStats
            ]);
            break;
        
        case 'filter':
            // Récupérer les logs avec filtres avancés
            $filtres = [];
            
            if (isset($_GET['module']) && !empty($_GET['module'])) {
                $filtres['module'] = $_GET['module'];
            }
            if (isset($_GET['type_action']) && !empty($_GET['type_action'])) {
                $filtres['type_action'] = $_GET['type_action'];
            }
            if (isset($_GET['statut']) && !empty($_GET['statut'])) {
                $filtres['statut'] = $_GET['statut'];
            }
            if (isset($_GET['date_debut']) && !empty($_GET['date_debut'])) {
                $filtres['date_debut'] = $_GET['date_debut'];
            }
            if (isset($_GET['date_fin']) && !empty($_GET['date_fin'])) {
                $filtres['date_fin'] = $_GET['date_fin'];
            }
            if (isset($_GET['recherche']) && !empty($_GET['recherche'])) {
                $filtres['recherche'] = $_GET['recherche'];
            }
            
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            
            $result = $journalServeur->obtenirLogs($filtres, $page, $limit);
            
            echo json_encode([
                'success' => true,
                'logs' => $result['logs'],
                'total' => $result['total'],
                'pages' => $result['pages'],
                'page_actuelle' => $result['page_actuelle'],
                'par_page' => $result['par_page']
            ]);
            break;
        
        case 'module':
            // Récupérer tous les logs d'un module
            $module = isset($_GET['module']) ? $_GET['module'] : null;
            
            if (!$module) {
                echo json_encode(['success' => false, 'message' => 'Module requis']);
                exit();
            }
            
            $filtres = ['module' => $module];
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
            
            $result = $journalServeur->obtenirLogs($filtres, $page, $limit);
            
            echo json_encode([
                'success' => true,
                'module' => $module,
                'logs' => $result['logs'],
                'total' => $result['total'],
                'pages' => $result['pages'],
                'page_actuelle' => $result['page_actuelle']
            ]);
            break;
        
        case 'utilisateur':
            // Récupérer les logs d'un utilisateur
            $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
            
            if (!$userId) {
                echo json_encode(['success' => false, 'message' => 'User ID requis']);
                exit();
            }
            
            $filtres = ['id_utilisateur' => $userId];
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
            
            $result = $journalServeur->obtenirLogs($filtres, $page, $limit);
            
            echo json_encode([
                'success' => true,
                'user_id' => $userId,
                'logs' => $result['logs'],
                'total' => $result['total'],
                'pages' => $result['pages']
            ]);
            break;
        
        case 'horaire':
            // Récupérer les logs spécifiquement pour les horaires
            $filtres = ['module' => 'HORAIRE'];
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
            
            $result = $journalServeur->obtenirLogs($filtres, $page, $limit);
            
            echo json_encode([
                'success' => true,
                'logs' => $result['logs'],
                'total' => $result['total'],
                'pages' => $result['pages']
            ]);
            break;
        
        case 'cotes':
            // Récupérer les logs spécifiquement pour les cotes
            $filtres = ['module' => 'COTES_GRILLE'];
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
            
            $result = $journalServeur->obtenirLogs($filtres, $page, $limit);
            
            echo json_encode([
                'success' => true,
                'logs' => $result['logs'],
                'total' => $result['total'],
                'pages' => $result['pages']
            ]);
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'Action inconnue']);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
