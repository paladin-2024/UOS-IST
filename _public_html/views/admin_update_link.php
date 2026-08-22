<?php
/**
 * Widget pour afficher les notifications de mise à jour dans l'interface admin
 * À inclure dans le tableau de bord administrateur
 */

// Vérifier si l'utilisateur est admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    return;
}

require_once '../install/InstallManager.php';

try {
    $installManager = new InstallManager();
    $status = $installManager->checkInstallationStatus();
    
    // Afficher uniquement s'il y a des mises à jour disponibles
    if ($status['needs_update'] || count($status['missing_tables']) > 0 || count($status['missing_columns']) > 0) {
        $totalChanges = count($status['missing_tables']) + count($status['missing_columns']);
        ?>
        
        <div class="alert alert-info d-flex align-items-center" role="alert">
            <i class="bi bi-info-circle-fill me-3 fs-4"></i>
            <div class="flex-grow-1">
                <h6 class="alert-heading mb-1">
                    <i class="bi bi-arrow-clockwise"></i> Mise à jour disponible
                </h6>
                <p class="mb-2">
                    <?php echo $totalChanges; ?> changement(s) détecté(s) pour votre système E-GESTION.
                    <small class="text-muted d-block">
                        Version actuelle: <?php echo $status['version']; ?> → Version cible: <?php echo $status['target_version']; ?>
                    </small>
                </p>
                <div class="d-flex gap-2">
                    <a href="install/" class="btn btn-primary btn-sm" target="_blank">
                        <i class="bi bi-arrow-clockwise"></i> Mettre à jour maintenant
                    </a>
                    <button class="btn btn-outline-secondary btn-sm" onclick="this.parentElement.parentElement.parentElement.style.display='none'">
                        <i class="bi bi-x"></i> Ignorer
                    </button>
                </div>
            </div>
        </div>
        
        <style>
        .alert-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-left: 4px solid #2196f3;
            border-radius: 8px;
        }
        </style>
        
        <?php
    }
} catch (Exception $e) {
    // Ignorer les erreurs silencieusement
}
?>
