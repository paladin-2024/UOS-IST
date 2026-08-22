<?php include "./views/include/header.php"; 

// Initialiser les modèles nécessaires
$congeModel = new Conge();
$agentModel = new Agent();

// Récupérer tous les types de congés
$typesConges = $congeModel->getAllTypeConges();

// Récupérer l'agent si spécifié (pour les administrateurs)
$idAgent = isset($_GET['agent']) ? intval($_GET['agent']) : (isset($_SESSION['agent_id']) ? $_SESSION['agent_id'] : $_SESSION['id']);
$agent = null;

if ($idAgent) {
    $agent = $agentModel->getAgentById($idAgent);
}

// Si l'utilisateur est un administrateur, récupérer la liste des agents
$isAdmin = isset($_SESSION['idRole']) && ($_SESSION['idRole'] == 1 || $_SESSION['idRole'] == 3);
$agents = [];

if ($isAdmin) {
    $agents = $agentModel->getAgents();
}




?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Nouvelle demande de congé</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">GRH</li>
                <li class="breadcrumb-item"><a href="grh/conges.list">Congés</a></li>
                <li class="breadcrumb-item active">Nouvelle demande</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Formulaire de demande de congé</h5>
                        
                        <form action="controller/create_conge.php" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <?php if ($isAdmin): ?>
                                <div class="row mb-3">
                                    <label for="idAgent" class="col-sm-2 col-form-label">Agent <span class="text-danger">*</span></label>
                                    <div class="col-sm-10">
                                        <select class="form-select" id="idAgent" name="idAgent" required>
                                            <option value="">Sélectionner un agent</option>
                                            <?php foreach ($agents as $a): ?>
                                                <option value="<?= $a['idAgent'] ?>" <?= $idAgent == $a['idAgent'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($a['noms']) ?> (<?= htmlspecialchars($a['matricule'] ?? 'N/A') ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Veuillez sélectionner un agent.</div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="idAgent" value="<?= $idAgent ?>">
                            <?php endif; ?>
                            
                            <div class="row mb-3">
                                <label for="idTypeConge" class="col-sm-2 col-form-label">Type de congé <span class="text-danger">*</span></label>
                                <div class="col-sm-10">
                                    <select class="form-select" id="idTypeConge" name="idTypeConge" required>
                                        <option value="">Sélectionner un type de congé</option>
                                        <?php foreach ($typesConges as $type): ?>
                                            <option value="<?= $type['idtype_conge'] ?>" data-cumulable="<?= $type['est_cumulable'] ?>">
                                                <?= htmlspecialchars($type['designation']) ?>
                                                <?php if ($type['duree_standard']): ?>
                                                    (<?= $type['duree_standard'] ?> jours)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Veuillez sélectionner un type de congé.</div>
                                </div>
                            </div>
                            
                            <?php if (!empty($soldesConges)): ?>
                                <div class="row mb-3" id="soldeCongeContainer">
                                    <label class="col-sm-2 col-form-label">Solde disponible</label>
                                    <div class="col-sm-10">
                                        <?php foreach ($soldesConges as $typeId => $solde): ?>
                                            <div class="solde-item" data-type="<?= $typeId ?>" style="display: none;">
                                                <div class="alert alert-info">
                                                    <strong><?= htmlspecialchars($solde['type']['designation']) ?>:</strong> 
                                                    <?= $solde['disponible'] ?> jours disponibles
                                                    <?php if ($solde['type']['est_cumulable']): ?>
                                                        (dont <?= $solde['reportes'] ?> jours reportés)
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <div id="soldeNonDisponible" class="alert alert-warning" style="display: none;">
                                            Ce type de congé n'a pas de solde prédéfini.
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="row mb-3">
                                <label for="dateDebut" class="col-sm-2 col-form-label">Date de début <span class="text-danger">*</span></label>
                                <div class="col-sm-4">
                                    <input type="date" class="form-control" id="dateDebut" name="dateDebut" required min="<?= date('Y-m-d') ?>">
                                    <div class="invalid-feedback">Veuillez sélectionner une date de début.</div>
                                </div>
                                
                                <label for="dateFin" class="col-sm-2 col-form-label">Date de fin <span class="text-danger">*</span></label>
                                <div class="col-sm-4">
                                    <input type="date" class="form-control" id="dateFin" name="dateFin" required min="<?= date('Y-m-d') ?>">
                                    <div class="invalid-feedback">Veuillez sélectionner une date de fin.</div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label">Durée calculée</label>
                                <div class="col-sm-10">
                                <div class="input-group">
                                        <input type="text" class="form-control" id="dureeCalculee" readonly>
                                        <span class="input-group-text">jours</span>
                                    </div>
                                    <small class="text-muted">Nombre de jours ouvrables (hors week-ends)</small>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label for="motif" class="col-sm-2 col-form-label">Motif</label>
                                <div class="col-sm-10">
                                    <textarea class="form-control" id="motif" name="motif" rows="3"></textarea>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label for="documentJustificatif" class="col-sm-2 col-form-label">Document justificatif</label>
                                <div class="col-sm-10">
                                    <input type="file" class="form-control" id="documentJustificatif" name="documentJustificatif">
                                    <small class="text-muted">Formats acceptés: PDF, JPG, PNG (max 5 Mo)</small>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-10 offset-sm-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="confirmationCheck" required>
                                        <label class="form-check-label" for="confirmationCheck">
                                            Je confirme que les informations fournies sont exactes
                                        </label>
                                        <div class="invalid-feedback">
                                            Vous devez confirmer avant de soumettre.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-10 offset-sm-2">
                                    <button type="submit" class="btn btn-primary">Soumettre la demande</button>
                                    <a href="grh/conges.list" class="btn btn-secondary">Annuler</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validation du formulaire
    const form = document.querySelector('form.needs-validation');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
    
    // Gestion du changement de type de congé
    const typeCongeSelect = document.getElementById('idTypeConge');
    const soldeItems = document.querySelectorAll('.solde-item');
    const soldeNonDisponible = document.getElementById('soldeNonDisponible');
    
    if (typeCongeSelect) {
        typeCongeSelect.addEventListener('change', function() {
            const typeId = this.value;
            
            // Masquer tous les soldes
            soldeItems.forEach(item => {
                item.style.display = 'none';
            });
            
            if (soldeNonDisponible) {
                soldeNonDisponible.style.display = 'none';
            }
            
            // Afficher le solde correspondant au type sélectionné
            if (typeId) {
                const soldeItem = document.querySelector(`.solde-item[data-type="${typeId}"]`);
                if (soldeItem) {
                    soldeItem.style.display = 'block';
                } else if (soldeNonDisponible) {
                    soldeNonDisponible.style.display = 'block';
                }
            }
        });
    }
    
    // Calcul de la durée en jours ouvrables
    const dateDebutInput = document.getElementById('dateDebut');
    const dateFinInput = document.getElementById('dateFin');
    const dureeCalculeeInput = document.getElementById('dureeCalculee');
    
    function calculerJoursOuvrables() {
        const dateDebut = dateDebutInput.value;
        const dateFin = dateFinInput.value;
        
        if (dateDebut && dateFin) {
            // Vérifier que la date de fin est après la date de début
            if (new Date(dateFin) < new Date(dateDebut)) {
                dateFinInput.setCustomValidity('La date de fin doit être postérieure à la date de début');
                dureeCalculeeInput.value = '';
                return;
            }
            
            dateFinInput.setCustomValidity('');
            
            // Calculer le nombre de jours ouvrables
            const debut = new Date(dateDebut);
            const fin = new Date(dateFin);
            let joursOuvrables = 0;
            
            for (let d = new Date(debut); d <= fin; d.setDate(d.getDate() + 1)) {
                // Si ce n'est pas un samedi (6) ou un dimanche (0)
                if (d.getDay() !== 0 && d.getDay() !== 6) {
                    joursOuvrables++;
                }
            }
            
            dureeCalculeeInput.value = joursOuvrables;
        } else {
            dureeCalculeeInput.value = '';
        }
    }
    
    if (dateDebutInput && dateFinInput) {
        dateDebutInput.addEventListener('change', calculerJoursOuvrables);
        dateFinInput.addEventListener('change', calculerJoursOuvrables);
    }
    
    // Si l'administrateur change l'agent sélectionné
    const agentSelect = document.getElementById('idAgent');
    if (agentSelect) {
        agentSelect.addEventListener('change', function() {
            const idAgent = this.value;
            if (idAgent) {
                window.location.href = `grh/conges.add&agent=${idAgent}`;
            }
        });
    }
});
</script>

<?php include "./views/include/footer.php"; ?>
