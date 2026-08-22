<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Role.php';
require_once dirname(__DIR__) . '/models/Module.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Session expirée. Veuillez vous reconnecter.']);
    exit;
}

// Récupération des données envoyées via POST JSON
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['roleIds']) || !is_array($data['roleIds']) || empty($data['roleIds'])) {
    echo json_encode(['error' => 'Aucun rôle sélectionné.']);
    exit;
}

try {
    $moduleModel = new Module();
    $roleModel = new Role();
    
    // Récupérer toutes les permissions des rôles sélectionnés
    $allPermissions = [];
    $uniquePermIds = [];
    
    foreach ($data['roleIds'] as $roleId) {
        if (empty($roleId)) continue;
        
        $rolePermissions = $moduleModel->getUserAllPermissionsByRole($roleId);
        
        foreach ($rolePermissions as $perm) {
            // Si la permission est active (is_checked=1) et pas déjà dans notre liste
            if ($perm['is_checked'] && !in_array($perm['idPerm'], $uniquePermIds)) {
                $uniquePermIds[] = $perm['idPerm'];
                $allPermissions[] = $perm;
            }
        }
    }
    
    // Vérifier s'il existe des rôles ayant exactement les mêmes permissions
    $matchingRoles = [];
    if (!empty($uniquePermIds)) {
        $roles = $roleModel->getAllRoles();
        
        foreach ($roles as $role) {
            $rolePerms = $moduleModel->getUserAllPermissionsByRole($role['idRole']);
            $rolePermIds = [];
            
            foreach ($rolePerms as $perm) {
                if ($perm['is_checked']) {
                    $rolePermIds[] = $perm['idPerm'];
                }
            }
            
            // Vérifier si les ensembles de permissions sont identiques
            if (count($rolePermIds) == count($uniquePermIds) && 
                empty(array_diff($rolePermIds, $uniquePermIds)) && 
                empty(array_diff($uniquePermIds, $rolePermIds))) {
                    
                $matchingRoles[] = [
                    'idRole' => $role['idRole'],
                    'nomRole' => $role['nomRole']
                ];
            }
        }
    }
    
    // Retourner les permissions et les rôles correspondants
    echo json_encode([
        'success' => true,
        'permissions' => $allPermissions,
        'permissionCount' => count($uniquePermIds),
        'matchingRoles' => $matchingRoles
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
}