<?php
include "./views/include/header.php";

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo "<script>window.location.href = 'login';</script>";
    exit();
}

// Vérifier les droits d'accès
$universite = new Universite();
$agent = new Agent();
$userId = $_SESSION['id'];
$agentId = $agent->getAgentIdByUserId($userId);
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$isJuryPresident = $universite->isJuryPresident($agentId);

if (!$isAdmin && !$isJuryPresident) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas les droits pour accéder à cette page.'
        }).then(() => {
            window.location.href = 'index';
        });
    </script>";
    exit();
}

// Récupérer les paramètres
$processId = isset($_GET['process_id']) ? intval($_GET['process_id']) : 0;
$deliberationId = isset($_GET['deliberation_id']) ? intval($_GET['deliberation_id']) : 0;

if (!$processId || !$deliberationId) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Paramètres manquants pour suivre le processus de délibération.'
        }).then(() => {
            window.location.href = 'index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

// Récupérer les informations du processus
$deliberation = new Deliberation();
$processInfo = $deliberation->getProcessInfo($processId);

if (!$processInfo) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Processus de délibération introuvable.'
        }).then(() => {
            window.location.href = 'index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

// Récupérer les informations de la délibération
$deliberationInfo = $deliberation->getDeliberationInfo($deliberationId);

if (!$deliberationInfo) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Délibération introuvable.'
        }).then(() => {
            window.location.href = 'index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

// Récupérer l'historique du processus
$processHistory = $deliberation->getProcessHistory($deliberationId);
?>

<main id="main" class="main">
    <div class="pagetitle">
    <h1>Exécution de la délibération</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item"><a href="index.php?view=deliberation/seances">Délibération</a></li>
                <li class="breadcrumb-item active">Exécution</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-gear-fill me-1"></i>
                            Processus de délibération en cours
                        </h5>
                        
                        <div class="alert alert-info">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-info-circle-fill me-2 fs-4"></i>
                                <h5 class="mb-0">Informations sur la délibération</h5>
                            </div>
                            <p><strong>Promotion:</strong> <?= htmlspecialchars($deliberationInfo['designationPromotion']) ?></p>
                            <p><strong>Session:</strong> <?= htmlspecialchars($deliberationInfo['designSession']) ?></p>
                            <p><strong>Année académique:</strong> <?= htmlspecialchars($deliberationInfo['annee_acad']) ?></p>
                            <p><strong>Date prévue:</strong> <?= date('d/m/Y H:i', strtotime($deliberationInfo['date_deliberation'])) ?></p>
                        </div>
                        
                        <!-- Affichage de l'étape actuelle -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Étape actuelle</h5>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h6 class="mb-0"><?= htmlspecialchars($processInfo['etape']) ?></h6>
                                        <small class="text-muted">
                                            <?= $processInfo['date_debut'] ? 'Démarrée le ' . date('d/m/Y à H:i', strtotime($processInfo['date_debut'])) : 'En attente' ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-<?= $this->getStatusBadgeClass($processInfo['statut']) ?> fs-6">
                                        <?= htmlspecialchars($processInfo['statut']) ?>
                                    </span>
                                </div>
                                
                                <div class="progress mb-3" style="height: 25px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                                         role="progressbar" 
                                         style="width: <?= $processInfo['progression'] ?>%;" 
                                         aria-valuenow="<?= $processInfo['progression'] ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        <?= $processInfo['progression'] ?>%
                                    </div>
                                </div>
                                
                                <div class="alert alert-<?= $this->getStatusAlertClass($processInfo['statut']) ?>">
                                    <i class="bi bi-<?= $this->getStatusIcon($processInfo['statut']) ?> me-1"></i>
                                    <?= htmlspecialchars($processInfo['message']) ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Historique des étapes -->
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Historique du processus</h5>
                                
                                <div class="timeline">
                                    <?php foreach ($processHistory as $step): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-<?= $this->getStatusBadgeClass($step['statut']) ?>">
                                            <i class="bi bi-<?= $this->getStatusIcon($step['statut']) ?>"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <h6 class="mb-0"><?= htmlspecialchars($step['etape']) ?></h6>
                                            <small class="text-muted">
                                                <?= date('d/m/Y H:i', strtotime($step['date_debut'])) ?>
                                                <?= $step['date_fin'] ? ' - ' . date('d/m/Y H:i', strtotime($step['date_fin'])) : '' ?>
                                            </small>
                                            <p class="mt-2"><?= htmlspecialchars($step['message']) ?></p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="d-flex justify-content-between mt-4">
                            <a href="index.php?view=deliberation/seances" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Retour aux séances
                            </a>
                            
                            <?php if ($processInfo['statut'] === 'Terminé'): ?>
                            <a href="index.php?view=deliberation/resultats&deliberation_id=<?= $deliberationId ?>" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i> Voir les résultats
                            </a>
                            <?php elseif ($processInfo['statut'] === 'Erreur'): ?>
                            <button type="button" class="btn btn-warning" onclick="relancerProcessus(<?= $processId ?>, <?= $deliberationId ?>)">
                                <i class="bi bi-arrow-repeat me-1"></i> Relancer le processus
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
// Fonction pour actualiser automatiquement la page
function autoRefresh() {
    const status = "<?= $processInfo['statut'] ?>";
    
    // Si le processus est en cours, actualiser la page toutes les 5 secondes
    if (status === 'En cours') {
        setTimeout(function() {
            window.location.reload();
        }, 5000);
    }
}

// Fonction pour relancer un processus en erreur
function relancerProcessus(processId, deliberationId) {
    Swal.fire({
        title: 'Confirmation',
        text: 'Êtes-vous sûr de vouloir relancer le processus de délibération?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Oui, relancer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `controller/restart_deliberation.php?process_id=${processId}&deliberation_id=${deliberationId}`;
        }
    });
}

// Démarrer l'actualisation automatique au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    autoRefresh();
});
</script>

<?php
// Fonctions utilitaires pour l'affichage
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'En cours': return 'primary';
        case 'Terminé': return 'success';
        case 'Erreur': return 'danger';
        case 'En attente': return 'secondary';
        default: return 'info';
    }
}

function getStatusAlertClass($status) {
    switch ($status) {
        case 'En cours': return 'info';
        case 'Terminé': return 'success';
        case 'Erreur': return 'danger';
        case 'En attente': return 'secondary';
        default: return 'info';
    }
}

function getStatusIcon($status) {
    switch ($status) {
        case 'En cours': return 'arrow-repeat';
        case 'Terminé': return 'check-circle';
        case 'Erreur': return 'exclamation-triangle';
        case 'En attente': return 'hourglass-split';
        default: return 'info-circle';
    }
}
?>

<?php include "./views/include/footer.php"; ?>
