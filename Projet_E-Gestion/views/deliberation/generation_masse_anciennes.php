<?php
include "./views/include/header.php";

// Récupérer l'ID d'import depuis l'URL
$importId = isset($_GET['import_id']) ? intval($_GET['import_id']) : 0;
if ($importId <= 0) {
    echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'ID d\'import invalide.'
                }).then(() => {
                    window.location.href = 'index.php?view=grilles_anciennes';
                });
            </script>";
    exit();
}

// Récupérer le type (releves ou fiches)
$type = isset($_GET['type']) ? $_GET['type'] : 'releves';
$isReleves = ($type === 'releves');

// Vérifier les droits d'accès
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
if (!$isAdmin) {
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
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Génération en Masse</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item"><a href="deliberation/grilles_anciennes">Grilles Anciennes</a></li>
                <li class="breadcrumb-item active">Génération en Masse</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            
            <!-- CARTE PRINCIPALE -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-files me-2"></i>
                            Génération en masse - <?= $isReleves ? 'Relevés de notes' : 'Fiches de validation' ?>
                        </h5>
                        
                        <!-- Zone d'information -->
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Information :</strong> Cette fonction va générer tous les <?= $isReleves ? 'relevés' : 'fiches' ?> 
                            pour les étudiants de cet import et les compresser dans un fichier ZIP.
                        </div>

                        <!-- Bouton de démarrage -->
                        <div id="start-section" class="text-center mb-4">
                            <button id="btn-start" class="btn btn-success btn-lg">
                                <i class="bi bi-play-fill me-2"></i>
                                Démarrer la génération
                            </button>
                        </div>

                        <!-- Zone de progression -->
                        <div id="progress-section" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Progression :</h6>
                                    <div class="progress" style="height: 25px;">
                                        <div id="progress-bar" 
                                             class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                                             role="progressbar" 
                                             style="width: 0%"
                                             aria-valuenow="0" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            0%
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Statistiques :</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="card bg-light">
                                                <div class="card-body p-2 text-center">
                                                    <h5 id="current-count" class="mb-1 text-primary">0</h5>
                                                    <small><?= $isReleves ? 'Relevés' : 'Fiches' ?> générés</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="card bg-light">
                                                <div class="card-body p-2 text-center">
                                                    <h5 id="total-count" class="mb-1 text-info">-</h5>
                                                    <small>Total étudiants</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Statut et messages -->
                            <div class="mt-3">
                                <div id="status-message" class="alert alert-info">
                                    <i class="bi bi-arrow-clockwise spin me-2"></i>
                                    Initialisation...
                                </div>
                            </div>

                            <!-- Liste des erreurs (si il y en a) -->
                            <div id="errors-section" style="display: none;">
                                <h6 class="text-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    Erreurs rencontrées :
                                </h6>
                                <div id="errors-list" class="alert alert-warning">
                                    <!-- Les erreurs seront listées ici -->
                                </div>
                            </div>
                        </div>

                        <!-- Zone de finalisation -->
                        <div id="complete-section" style="display: none;">
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                <strong>Terminé !</strong> Tous les <?= $isReleves ? 'relevés' : 'fiches' ?> ont été générés avec succès.
                            </div>
                            
                            <div class="text-center">
                                <button id="btn-download" class="btn btn-primary btn-lg me-3">
                                    <i class="bi bi-download me-2"></i>
                                    Télécharger le fichier ZIP
                                </button>
                                <a href="deliberation/grilles_anciennes" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>
                                    Retour à la liste
                                </a>
                            </div>
                        </div>

                        <!-- Boutons d'action -->
                        <div class="text-center mt-4">
                            <a href="deliberation/grilles_anciennes" 
                               id="btn-cancel" 
                               class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg me-2"></i>
                                Annuler
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </section>

</main><!-- End #main -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const importId = <?= $importId ?>;
    const type = '<?= $type ?>';
    const isReleves = <?= $isReleves ? 'true' : 'false' ?>;
    
    const btnStart = document.getElementById('btn-start');
    const startSection = document.getElementById('start-section');
    const progressSection = document.getElementById('progress-section');
    const completeSection = document.getElementById('complete-section');
    const progressBar = document.getElementById('progress-bar');
    const currentCount = document.getElementById('current-count');
    const totalCount = document.getElementById('total-count');
    const statusMessage = document.getElementById('status-message');
    const errorsSection = document.getElementById('errors-section');
    const errorsList = document.getElementById('errors-list');
    const btnDownload = document.getElementById('btn-download');
    const btnCancel = document.getElementById('btn-cancel');
    
    let progressInterval;
    let allErrors = [];

    // Démarrer la génération
    btnStart.addEventListener('click', function() {
        startGeneration();
    });

    // Télécharger le ZIP
    btnDownload.addEventListener('click', function() {
        downloadZip();
    });

    function startGeneration() {
        // Masquer le bouton de démarrage et afficher la progression
        startSection.style.display = 'none';
        progressSection.style.display = 'block';
        btnCancel.style.display = 'none';

        // Initialiser la génération
        const url = `controller/export_${isReleves ? 'tous_releves_anciens' : 'toutes_fiches_anciennes'}.php`;
        
        fetch(`${url}?import_id=${importId}&action=start`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                
                totalCount.textContent = data.total;
                statusMessage.innerHTML = '<i class="fas fa-cogs"></i> ' + data.message;
                
                // Commencer la génération par batch
                generateBatch();
                
                // Démarrer le suivi du progrès
                progressInterval = setInterval(checkProgress, 2000);
            })
            .catch(error => {
                showError('Erreur lors de l\'initialisation: ' + error.message);
            });
    }

    function generateBatch() {
        const url = `controller/export_${isReleves ? 'tous_releves_anciens' : 'toutes_fiches_anciennes'}.php`;
        
        fetch(`${url}?import_id=${importId}&action=generate`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                
                // Mettre à jour l'affichage
                updateProgress(data.progress, data.current, data.total);
                
                // Ajouter les erreurs s'il y en a
                if (data.errors && data.errors.length > 0) {
                    allErrors = allErrors.concat(data.errors);
                    showErrors();
                }
                
                if (data.complete) {
                    // Génération terminée
                    clearInterval(progressInterval);
                    showComplete();
                } else {
                    // Continuer avec le prochain batch
                    setTimeout(generateBatch, 1000);
                }
            })
            .catch(error => {
                clearInterval(progressInterval);
                showError('Erreur lors de la génération: ' + error.message);
            });
    }

    function checkProgress() {
        const url = `controller/export_${isReleves ? 'tous_releves_anciens' : 'toutes_fiches_anciennes'}.php`;
        
        fetch(`${url}?import_id=${importId}&action=progress`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    return; // Ignorer les erreurs de progression
                }
                
                updateProgress(data.progress, data.current, data.total);
            })
            .catch(error => {
                // Ignorer les erreurs de progression
            });
    }

    function updateProgress(progress, current, total) {
        progressBar.style.width = progress + '%';
        progressBar.setAttribute('aria-valuenow', progress);
        progressBar.textContent = Math.round(progress) + '%';
        
        currentCount.textContent = current;
        totalCount.textContent = total;
        
        statusMessage.innerHTML = `<i class="bi bi-gear-fill spin me-2"></i> 
            Génération en cours... ${current}/${total} ${isReleves ? 'relevés' : 'fiches'} traités`;
    }

    function showComplete() {
        progressSection.style.display = 'none';
        completeSection.style.display = 'block';
        
        // Animation de succès
        progressBar.classList.remove('progress-bar-animated');
        progressBar.classList.add('bg-success');
    }

    function showErrors() {
        errorsSection.style.display = 'block';
        errorsList.innerHTML = allErrors.map(error => `<div>• ${error}</div>`).join('');
    }

    function showError(message) {
        statusMessage.innerHTML = `<i class="bi bi-exclamation-triangle me-2"></i> ${message}`;
        statusMessage.className = 'alert alert-danger';
        btnCancel.style.display = 'inline-block';
    }

    function downloadZip() {
        const url = `controller/export_${isReleves ? 'tous_releves_anciens' : 'toutes_fiches_anciennes'}.php`;
        window.location.href = `${url}?import_id=${importId}&action=download`;
        
        // Retourner à la liste après un délai
        setTimeout(() => {
            window.location.href = 'deliberation/grilles_anciennes';
        }, 3000);
    }
});
</script>

<style>
.progress {
    border-radius: 10px;
}

.progress-bar {
    font-weight: bold;
    font-size: 14px;
}

.alert {
    border-radius: 10px;
}

#errors-list {
    max-height: 200px;
    overflow-y: auto;
}

#errors-list div {
    margin-bottom: 5px;
}

.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<?php include "./views/include/footer_file.php"; ?>
