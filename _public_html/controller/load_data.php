<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérification et nettoyage du type
$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';

// Paramètres de pagination et recherche
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 15;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($type === 'permissions') {
    // Vérification des paramètres requis
    $m = isset($_GET['m']) ? intval($_GET['m']) : null;

    if ($m === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Le paramètre m (ID du module) est requis']);
        exit;
    }

    // Construction du chemin du fichier modèle
    $modelFile = dirname(__DIR__) . '/models/Module.php';

    // Vérification de l'existence du fichier
    if (!file_exists($modelFile)) {
        http_response_code(500);
        echo json_encode(['error' => 'Fichier modèle non trouvé']);
        exit;
    }

    // Inclusion du modèle et traitement
    require_once $modelFile;
    $model = new Module();

    try {
        // Vérification de l'existence des méthodes requises
        if (!method_exists($model, 'getModulePermissionByModule') || !method_exists($model, 'countPermUserModule')) {
            throw new Exception('Les méthodes requises ne sont pas disponibles dans le modèle');
        }

        // Récupération des données
        $items = $model->getModulePermissionByModule($m, $offset, $limit, $search);

        // Vérification que des données ont été trouvées
        if ($items === false) {
            echo json_encode([
                'permission' => [],
                'total' => 0,
                'hasMore' => false
            ]);
            exit;
        }

        // Récupération du total
        $total = $model->countPermUserModule($m, $search);

        // Construction de la réponse
        echo json_encode([
            'permission' => $items,  // Correction de 'persmission' en 'permissions'
            'total' => $total,
            'hasMore' => ($offset + $limit) < $total
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Erreur lors de la récupération des données',
            'message' => $e->getMessage()
        ]);
    }
} elseif ($type === 'users') {
    // Construction du chemin du fichier modèle
    $modelFile = dirname(__DIR__) . '/models/User.php';

    // Vérification de l'existence du fichier
    if (!file_exists($modelFile)) {
        http_response_code(500);
        echo json_encode(['error' => 'Fichier modèle non trouvé']);
        exit;
    }

    // Inclusion du modèle et traitement
    require_once $modelFile;
    $model = new User();

    try {
        // Vérification de l'existence des méthodes requises
        if (!method_exists($model, 'getUserListe') || !method_exists($model, 'countUser')) {
            throw new Exception('Les méthodes requises ne sont pas disponibles dans le modèle');
        }

        // Récupération des données
        $items = $model->getUserListe($offset, $limit, $search);

        // Vérification que des données ont été trouvées
        if ($items === false) {
            echo json_encode([
                'user' => [],
                'total' => 0,
                'hasMore' => false
            ]);
            exit;
        }

        // Récupération du total
        $total = $model->countUser($search);

        // Construction de la réponse
        echo json_encode([
            'user' => $items,
            'total' => $total,
            'hasMore' => ($offset + $limit) < $total
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Erreur lors de la récupération des données',
            'message' => $e->getMessage()
        ]);
    }
} elseif ($type === 'roles') {
    // Construction du chemin du fichier modèle
    $modelFile = dirname(__DIR__) . '/models/Role.php';

    // Vérification de l'existence du fichier
    if (!file_exists($modelFile)) {
        http_response_code(500);
        echo json_encode(['error' => 'Fichier modèle non trouvé']);
        exit;
    }

    // Inclusion du modèle et traitement
    require_once $modelFile;
    $model = new Role();

    try {
        // Vérification de l'existence des méthodes requises
        if (!method_exists($model, 'getRoleListe') || !method_exists($model, 'countRole')) {
            throw new Exception('Les méthodes requises ne sont pas disponibles dans le modèle');
        }

        // Récupération des données
        $items = $model->getRoleListe($offset, $limit, $search);

        // Vérification que des données ont été trouvées
        if ($items === false) {
            echo json_encode([
                'role' => [],
                'total' => 0,
                'hasMore' => false
            ]);
            exit;
        }

        // Récupération du total
        $total = $model->countRole($search);

        // Construction de la réponse
        echo json_encode([
            'role' => $items,
            'total' => $total,
            'hasMore' => ($offset + $limit) < $total
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Erreur lors de la récupération des données',
            'message' => $e->getMessage()
        ]);
    }
} elseif ($type === 'modules') {
    // Construction du chemin du fichier modèle
    $modelFile = dirname(__DIR__) . '/models/Module.php';

    // Vérification de l'existence du fichier
    if (!file_exists($modelFile)) {
        http_response_code(500);
        echo json_encode(['error' => 'Fichier modèle non trouvé']);
        exit;
    }

    // Inclusion du modèle et traitement
    require_once $modelFile;
    $model = new Module();

    try {
        // Vérification de l'existence des méthodes requises
        if (!method_exists($model, 'getModuleListe') || !method_exists($model, 'countModule')) {
            throw new Exception('Les méthodes requises ne sont pas disponibles dans le modèle');
        }

        // Récupération des données
        $items = $model->getModuleListe($offset, $limit, $search);

        // Vérification que des données ont été trouvées
        if ($items === false) {
            echo json_encode([
                'module' => [],
                'total' => 0,
                'hasMore' => false
            ]);
            exit;
        }

        // Récupération du total
        $total = $model->countModule($search);

        // Construction de la réponse
        echo json_encode([
            'module' => $items,
            'total' => $total,
            'hasMore' => ($offset + $limit) < $total
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Erreur lors de la récupération des données',
            'message' => $e->getMessage()
        ]);
    }
} elseif ($type === 'userpermissions') {
    // Vérification des paramètres requis
    $r = isset($_GET['r']) ? intval($_GET['r']) : null;

    if ($r === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Le paramètre r (ID du module) est requis']);
        exit;
    }

    // Construction du chemin du fichier modèle
    $modelFile = dirname(__DIR__) . '/models/Module.php';

    // Vérification de l'existence du fichier
    if (!file_exists($modelFile)) {
        http_response_code(500);
        echo json_encode(['error' => 'Fichier modèle non trouvé']);
        exit;
    }

    // Inclusion du modèle et traitement
    require_once $modelFile;
    $model = new Module();

    try {
        // Vérification de l'existence des méthodes requises
        if (!method_exists($model, 'getUserAllPermissionsByRoleListe') || !method_exists($model, 'countUserAllPermissionsByRole')) {
            throw new Exception('Les méthodes requises ne sont pas disponibles dans le modèle');
        }

        // Récupération des données
        $items = $model->getUserAllPermissionsByRoleListe($r, $offset, $limit, $search);

        // Vérification que des données ont été trouvées
        if ($items === false) {
            echo json_encode([
                'userpermission' => [],
                'total' => 0,
                'hasMore' => false
            ]);
            exit;
        }

        // Récupération du total
        $total = $model->countUserAllPermissionsByRole($r, $search);

        // Construction de la réponse
        echo json_encode([
            'userpermission' => $items,
            'total' => $total,
            'hasMore' => ($offset + $limit) < $total
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Erreur lors de la récupération des données',
            'message' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Type de requête invalide']);
}
