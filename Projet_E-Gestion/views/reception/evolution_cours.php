<?php
include "./views/include/header.php";

$db = Connexion::getInstance()->getPDO();
$userId = $_SESSION['id'];

// Récupérer l'année académique active
$anneeAcadActive = "SELECT idannee_acad FROM annee_acad WHERE est_active = 1 LIMIT 1";
$stmtAnneeAcadActive = $db->prepare($anneeAcadActive);
$stmtAnneeAcadActive->execute();
$anneeAcademique = $stmtAnneeAcadActive->fetch(PDO::FETCH_ASSOC);

// Récupérer les promotions actives
$queryPromotions = "SELECT p.idpromotion, p.\"designationPromotion\", o.\"designationOrientation\"
                    FROM promotion p
                    JOIN orientation o ON p.orientation_idorientation = o.idorientation
                    WHERE p.annee_acad_idannee_acad = :idannee_acad
                    ORDER BY o.\"designationOrientation\", p.\"designationPromotion\"";
$stmtPromotions = $db->prepare($queryPromotions);
$stmtPromotions->bindParam(':idannee_acad', $anneeAcademique['idannee_acad'], PDO::PARAM_INT);
$stmtPromotions->execute();
$promotions = $stmtPromotions->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les évolutions en attente de validation
$queryEvolutions = "SELECT se.*, e.\"designationECUE\", ue.\"designationUE\", 
                           p.\"designationPromotion\", o.\"designationOrientation\",
                           et.noms as chef_promotion_nom, et.matricule as chef_promotion_matricule
                    FROM suivi_enseignement_ecue se
                    JOIN ecue e ON se.\"idECUE\" = e.\"idECUE\"
                    JOIN ue ON e.\"UE_idUE\" = ue.\"idUE\"
                    JOIN promotion p ON se.promotion_idpromotion = p.idpromotion
                    JOIN orientation o ON p.orientation_idorientation = o.idorientation
                    JOIN etudiant et ON se.chef_promotion_id = et.idetudiant
                    WHERE se.statut_validation = 'En attente'
                    ORDER BY se.date_encodage DESC";
$stmtEvolutions = $db->prepare($queryEvolutions);
$stmtEvolutions->execute();
$evolutionsEnAttente = $stmtEvolutions->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les évolutions (historique)
$queryHistorique = "SELECT se.*, e.\"designationECUE\", ue.\"designationUE\", 
                           p.\"designationPromotion\", o.\"designationOrientation\",
                           et.noms as chef_promotion_nom, et.matricule as chef_promotion_matricule,
                           ag.noms as appariteur_nom
                    FROM suivi_enseignement_ecue se
                    JOIN ecue e ON se.\"idECUE\" = e.\"idECUE\"
                    JOIN ue ON e.\"UE_idUE\" = ue.\"idUE\"
                    JOIN promotion p ON se.promotion_idpromotion = p.idpromotion
                    JOIN orientation o ON p.orientation_idorientation = o.idorientation
                    JOIN etudiant et ON se.chef_promotion_id = et.idetudiant
                    LEFT JOIN agent ag ON se.appariteur_id = ag.\"idAgent\"
                    ORDER BY se.date_encodage DESC
                    LIMIT 50";
$stmtHistorique = $db->prepare($queryHistorique);
$stmtHistorique->execute();
$historique = $stmtHistorique->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Évolutions de Cours</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Évolutions de Cours</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Évolutions de Cours (ECUE)</h5>

                        <!-- Navigation Tabs -->
                        <ul class="nav nav-tabs" id="evolutionTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="validation-tab" data-bs-toggle="tab" 
                                        data-bs-target="#validation" type="button" role="tab">
                                    <i class="bi bi-check-circle"></i> Validations en Attente 
                                    <span class="badge bg-danger"><?= count($evolutionsEnAttente) ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="encodage-tab" data-bs-toggle="tab" 
                                        data-bs-target="#encodage" type="button" role="tab">
                                    <i class="bi bi-plus-circle"></i> Nouvel Encodage
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="historique-tab" data-bs-toggle="tab" 
                                        data-bs-target="#historique" type="button" role="tab">
                                    <i class="bi bi-clock-history"></i> Historique
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="evolutionTabsContent">
                            <!-- Tab Validation -->
                            <div class="tab-pane fade show active" id="validation" role="tabpanel">
                                <div class="mt-3">
                                    <h6>Évolutions en Attente de Validation</h6>
                                    <?php if (empty($evolutionsEnAttente)): ?>
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i> Aucune évolution en attente de validation.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Date Séance</th>
                                                        <th>ECUE</th>
                                                        <th>Promotion</th>
                                                        <th>Heures</th>
                                                        <th>Matière Vue</th>
                                                        <th>Chef Promotion</th>
                                                        <th>Date Encodage</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($evolutionsEnAttente as $evolution): ?>
                                                        <tr>
                                                            <td><?= date('d/m/Y', strtotime($evolution['date_seance'])) ?><br>
                                                                <small><?= date('H:i', strtotime($evolution['heure_debut'])) ?> - 
                                                                       <?= date('H:i', strtotime($evolution['heure_fin'])) ?></small>
                                                            </td>
                                                            <td>
                                                                <strong><?= htmlspecialchars($evolution['designationECUE']) ?></strong><br>
                                                                <small class="text-muted"><?= htmlspecialchars($evolution['designationUE']) ?></small>
                                                            </td>
                                                            <td>
                                                                <?= htmlspecialchars($evolution['designationPromotion']) ?><br>
                                                                <small class="text-muted"><?= htmlspecialchars($evolution['designationOrientation']) ?></small>
                                                            </td>
                                                            <td><?= $evolution['nombre_heures_reelles'] ?> h</td>
                                                            <td><?= htmlspecialchars(substr($evolution['matiere_vue'], 0, 50)) . (strlen($evolution['matiere_vue']) > 50 ? '...' : '') ?></td>
                                                            <td>
                                                                <?= htmlspecialchars($evolution['chef_promotion_nom']) ?><br>
                                                                <small class="text-muted"><?= $evolution['chef_promotion_matricule'] ?></small>
                                                            </td>
                                                            <td><?= date('d/m/Y H:i', strtotime($evolution['date_encodage'])) ?></td>
                                                            <td>
                                                                <button type="button" class="btn btn-primary btn-sm" 
                                                                        onclick="voirDetails(<?= $evolution['id_suivi'] ?>)">
                                                                    <i class="bi bi-eye"></i> Voir
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Tab Encodage -->
                            <div class="tab-pane fade" id="encodage" role="tabpanel">
                                <div class="mt-3">
                                    <h6>Encoder une Nouvelle Évolution de Cours</h6>
                                    <form id="encodageForm" method="POST" action="controller/addEvolutionCours.php">
                                        <!-- Étape 1: Sélection de la promotion -->
                                        <div class="row mb-4">
                                            <div class="col-md-12">
                                                <div class="card border-primary">
                                                    <div class="card-header bg-primary text-white">
                                                        <h6 class="mb-0"><i class="bi bi-people"></i> Étape 1: Sélection de la Promotion</h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="row">
                                                            <div class="col-md-8">
                                                                <label for="promotion_idpromotion" class="form-label">
                                                                    Promotion <span class="text-danger">*</span>
                                                                </label>
                                                                <select class="form-select" id="promotion_idpromotion" name="promotion_idpromotion" required>
                                                                    <option value="">Sélectionner une promotion</option>
                                                                    <?php foreach ($promotions as $promotion): ?>
                                                                        <option value="<?= $promotion['idpromotion'] ?>">
                                                                            <?= htmlspecialchars($promotion['designationOrientation']) ?> - 
                                                                            <?= htmlspecialchars($promotion['designationPromotion']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">&nbsp;</label>
                                                                <div class="d-grid">
                                                                    <button type="button" class="btn btn-outline-primary" id="loadDataBtn" disabled>
                                                                        <i class="bi bi-arrow-clockwise"></i> Charger les données
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Étape 2: Formulaire principal (initialement masqué) -->
                                        <div id="mainFormSection" style="display: none;">
                                            <div class="card border-success">
                                                <div class="card-header bg-success text-white">
                                                    <h6 class="mb-0"><i class="bi bi-journal-text"></i> Étape 2: Informations de la séance</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label for=idECUE class="form-label">ECUE <span class="text-danger">*</span></label>
                                                            <select class="form-select" id=idECUE name=idECUE required disabled>
                                                                <option value="">Chargement...</option>
                                                            </select>
                                                            <small class="form-text text-muted">
                                                                Seuls les ECUE avec des heures restantes sont affichés
                                                            </small>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="chef_promotion_id" class="form-label">Chef de Promotion <span class="text-danger">*</span></label>
                                                            <select class="form-select" id="chef_promotion_id" name="chef_promotion_id" required disabled>
                                                                <option value="">Chargement...</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-4">
                                                                                                                        <label for="date_seance" class="form-label">Date de la Séance <span class="text-danger">*</span></label>
                                                            <input type="date" class="form-control" id="date_seance" name="date_seance" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label for="heure_debut" class="form-label">Heure Début <span class="text-danger">*</span></label>
                                                            <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label for="heure_fin" class="form-label">Heure Fin <span class="text-danger">*</span></label>
                                                            <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label for="nombre_heures_reelles" class="form-label">Nombre d'Heures Réelles <span class="text-danger">*</span></label>
                                                            <input type="number" step="0.25" min="0.25" max="8" class="form-control" 
                                                                   id="nombre_heures_reelles" name="nombre_heures_reelles" required readonly>
                                                            <small class="form-text text-muted">Calculé automatiquement à partir des heures</small>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Heures disponibles pour cet ECUE</label>
                                                            <div class="alert alert-info mb-0" id="heuresDisponibles">
                                                                <small>Sélectionnez d'abord un ECUE</small>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="matiere_vue" class="form-label">Matière Vue <span class="text-danger">*</span></label>
                                                        <textarea class="form-control" id="matiere_vue" name="matiere_vue" rows="4" 
                                                                  placeholder="Décrivez la matière enseignée lors de cette séance..." required></textarea>
                                                    </div>

                                                    <input type="hidden" name="annee_acad_idannee_acad" value="<?= $anneeAcademique['idannee_acad'] ?>">

                                                    <div class="d-flex justify-content-end">
                                                        <button type="button" class="btn btn-secondary me-2" onclick="resetForm()">
                                                            <i class="bi bi-arrow-clockwise"></i> Réinitialiser
                                                        </button>
                                                        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                                            <i class="bi bi-save"></i> Enregistrer l'Évolution
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Tab Historique -->
                            <div class="tab-pane fade" id="historique" role="tabpanel">
                                <div class="mt-3">
                                    <h6>Historique des Évolutions</h6>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Date Séance</th>
                                                    <th>ECUE</th>
                                                    <th>Promotion</th>
                                                    <th>Heures</th>
                                                    <th>Chef Promotion</th>
                                                    <th>Statut</th>
                                                    <th>Appariteur</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($historique as $evolution): ?>
                                                    <tr>
                                                        <td><?= date('d/m/Y', strtotime($evolution['date_seance'])) ?><br>
                                                            <small><?= date('H:i', strtotime($evolution['heure_debut'])) ?> - 
                                                                   <?= date('H:i', strtotime($evolution['heure_fin'])) ?></small>
                                                        </td>
                                                        <td>
                                                            <strong><?= htmlspecialchars($evolution['designationECUE']) ?></strong><br>
                                                            <small class="text-muted"><?= htmlspecialchars($evolution['designationUE']) ?></small>
                                                        </td>
                                                        <td>
                                                            <?= htmlspecialchars($evolution['designationPromotion']) ?><br>
                                                            <small class="text-muted"><?= htmlspecialchars($evolution['designationOrientation']) ?></small>
                                                        </td>
                                                        <td><?= $evolution['nombre_heures_reelles'] ?> h</td>
                                                        <td>
                                                            <?= htmlspecialchars($evolution['chef_promotion_nom']) ?><br>
                                                            <small class="text-muted"><?= $evolution['chef_promotion_matricule'] ?></small>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $statusClass = '';
                                                            $statusIcon = '';
                                                            switch ($evolution['statut_validation']) {
                                                                case 'Validé':
                                                                    $statusClass = 'badge bg-success';
                                                                    $statusIcon = 'bi-check-circle';
                                                                    break;
                                                                case 'Rejeté':
                                                                    $statusClass = 'badge bg-danger';
                                                                    $statusIcon = 'bi-x-circle';
                                                                    break;
                                                                default:
                                                                    $statusClass = 'badge bg-warning';
                                                                    $statusIcon = 'bi-clock';
                                                            }
                                                            ?>
                                                            <span class="<?= $statusClass ?>">
                                                                <i class="bi <?= $statusIcon ?>"></i> 
                                                                <?= $evolution['statut_validation'] ?>
                                                            </span>
                                                        </td>
                                                        <td><?= $evolution['appariteur_nom'] ?: 'N/A' ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-info btn-sm" 
                                                                    onclick="voirDetails(<?= $evolution['id_suivi'] ?>)">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Modal Détails Évolution -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">Détails de l'Évolution de Cours</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detailsContent">
                    <!-- Contenu chargé dynamiquement -->
                </div>
                <div class="modal-footer" id="detailsFooter">
                    <!-- Boutons chargés dynamiquement -->
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentPromotionId = null;
        let availableEcues = [];

        // Événement sur la sélection de promotion
        document.getElementById('promotion_idpromotion').addEventListener('change', function() {
            const promotionId = this.value;
            const loadBtn = document.getElementById('loadDataBtn');
            
            if (promotionId) {
                currentPromotionId = promotionId;
                loadBtn.disabled = false;
                loadBtn.classList.remove('btn-outline-primary');
                loadBtn.classList.add('btn-primary');
            } else {
                currentPromotionId = null;
                loadBtn.disabled = true;
                loadBtn.classList.remove('btn-primary');
                loadBtn.classList.add('btn-outline-primary');
                resetMainForm();
            }
        });

        // Événement sur le bouton de chargement des données
        document.getElementById('loadDataBtn').addEventListener('click', function() {
            if (currentPromotionId) {
                loadPromotionData(currentPromotionId);
            }
        });

        // Fonction pour charger les données de la promotion
        async function loadPromotionData(promotionId) {
            const loadBtn = document.getElementById('loadDataBtn');
            const originalText = loadBtn.innerHTML;
            
            try {
                // Afficher le loading
                loadBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Chargement...';
                loadBtn.disabled = true;

                // Charger les ECUE disponibles et les chefs de promotion
                const [ecuesResponse, chefsResponse] = await Promise.all([
                    fetch(`controller/getEcuesDisponibles.php?promotion_id=${promotionId}`),
                    fetch(`controller/getChefPromotion.php?promotion_id=${promotionId}`)
                ]);

                const ecuesData = await ecuesResponse.json();
                const chefsData = await chefsResponse.json();

                if (ecuesData.success && chefsData.success) {
                    // Mettre à jour les selects
                    updateEcueSelect(ecuesData.ecues);
                    updateChefSelect(chefsData.chefs);
                    
                    // Afficher le formulaire principal
                    document.getElementById('mainFormSection').style.display = 'block';
                    document.getElementById('submitBtn').disabled = false;
                    
                    // Scroll vers le formulaire
                    document.getElementById('mainFormSection').scrollIntoView({ 
                        behavior: 'smooth' 
                    });

                    Swal.fire({
                        icon: 'success',
                        title: 'Données chargées',
                        text: `${ecuesData.ecues.length} ECUE(s) disponible(s) trouvé(s)`,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    throw new Error(ecuesData.message || chefsData.message || 'Erreur lors du chargement');
                }

            } catch (error) {
                console.error('Erreur:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors du chargement des données'
                });
            } finally {
                loadBtn.innerHTML = originalText;
                loadBtn.disabled = false;
            }
        }

        // Fonction pour mettre à jour le select des ECUE
        function updateEcueSelect(ecues) {
            const ecueSelect = document.getElementById('idECUE');
            availableEcues = ecues;
            
            ecueSelect.innerHTML = '<option value="">Sélectionner un ECUE</option>';
            
            if (ecues && ecues.length > 0) {
                ecues.forEach(ecue => {
                    const heuresTotal = parseFloat(ecue.heures_total);
                    const heuresRealisees = parseFloat(ecue.heures_realisees);
                    const heuresRestantes = heuresTotal - heuresRealisees;
                    
                    ecueSelect.innerHTML += `
                        <option value="${ecue.idECUE}" 
                                data-heures-total="${heuresTotal}" 
                                data-heures-realisees="${heuresRealisees}" 
                                data-heures-restantes="${heuresRestantes}">
                            ${ecue.designationECUE} (${heuresRestantes}h restantes sur ${heuresTotal}h)
                        </option>`;
                });
                ecueSelect.disabled = false;
            } else {
                ecueSelect.innerHTML = '<option value="">Aucun ECUE disponible</option>';
                ecueSelect.disabled = true;
            }
        }

        // Fonction pour mettre à jour le select des chefs de promotion
        function updateChefSelect(chefs) {
            const chefSelect = document.getElementById('chef_promotion_id');
            
            chefSelect.innerHTML = '<option value="">Sélectionner le chef de promotion</option>';
            
            if (chefs && chefs.length > 0) {
                chefs.forEach(chef => {
                    chefSelect.innerHTML += `<option value="${chef.idetudiant}">${chef.noms} (${chef.matricule})</option>`;
                });
                chefSelect.disabled = false;
            } else {
                chefSelect.innerHTML = '<option value="">Aucun chef de promotion trouvé</option>';
                chefSelect.disabled = true;
            }
        }

        // Événement sur la sélection d'ECUE
        document.getElementById('idECUE').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (selectedOption.value) {
                const heuresTotal = selectedOption.dataset.heuresTotal;
                const heuresRealisees = selectedOption.dataset.heuresRealisees;
                const heuresRestantes = selectedOption.dataset.heuresRestantes;
                
                document.getElementById('heuresDisponibles').innerHTML = `
                    <strong>Total:</strong> ${heuresTotal}h | 
                    <strong>Réalisées:</strong> ${heuresRealisees}h | 
                    <strong>Restantes:</strong> <span class="text-primary">${heuresRestantes}h</span>
                `;
            } else {
                document.getElementById('heuresDisponibles').innerHTML = '<small>Sélectionnez d\'abord un ECUE</small>';
            }
        });

                // Calculer automatiquement le nombre d'heures
        function calculerHeures() {
            const heureDebut = document.getElementById('heure_debut').value;
            const heureFin = document.getElementById('heure_fin').value;
            
            if (heureDebut && heureFin) {
                const debut = new Date(`2000-01-01 ${heureDebut}`);
                const fin = new Date(`2000-01-01 ${heureFin}`);
                
                if (fin > debut) {
                    const diffMs = fin - debut;
                    const diffHours = diffMs / (1000 * 60 * 60);
                    document.getElementById('nombre_heures_reelles').value = diffHours.toFixed(2);
                    
                    // Vérifier si les heures ne dépassent pas les heures restantes
                    validateHeures(diffHours);
                } else {
                    document.getElementById('nombre_heures_reelles').value = '';
                    Swal.fire({
                        icon: 'warning',
                        title: 'Attention',
                        text: 'L\'heure de fin doit être supérieure à l\'heure de début'
                    });
                }
            }
        }

        // Valider que les heures ne dépassent pas les heures restantes
        function validateHeures(heuresSaisies) {
            const ecueSelect = document.getElementById('idECUE');
            const selectedOption = ecueSelect.options[ecueSelect.selectedIndex];
            
            if (selectedOption.value) {
                const heuresRestantes = parseFloat(selectedOption.dataset.heuresRestantes);
                
                if (heuresSaisies > heuresRestantes) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Heures insuffisantes',
                        text: `Vous ne pouvez pas saisir ${heuresSaisies}h. Il ne reste que ${heuresRestantes}h pour cet ECUE.`
                    });
                    
                    document.getElementById('nombre_heures_reelles').value = heuresRestantes;
                    return false;
                }
            }
            return true;
        }

        // Événements pour le calcul automatique des heures
        document.getElementById('heure_debut').addEventListener('change', calculerHeures);
        document.getElementById('heure_fin').addEventListener('change', calculerHeures);

        // Fonction pour réinitialiser le formulaire principal
        function resetMainForm() {
            document.getElementById('mainFormSection').style.display = 'none';
            document.getElementById('idECUE').innerHTML = '<option value="">Chargement...</option>';
            document.getElementById('chef_promotion_id').innerHTML = '<option value="">Chargement...</option>';
            document.getElementById('idECUE').disabled = true;
            document.getElementById('chef_promotion_id').disabled = true;
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('heuresDisponibles').innerHTML = '<small>Sélectionnez d\'abord un ECUE</small>';
        }

        // Fonction pour réinitialiser tout le formulaire
        function resetForm() {
            Swal.fire({
                title: 'Réinitialiser le formulaire ?',
                text: "Toutes les données saisies seront perdues.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Oui, réinitialiser!',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('encodageForm').reset();
                    document.getElementById('promotion_idpromotion').value = '';
                    currentPromotionId = null;
                    resetMainForm();
                    
                    const loadBtn = document.getElementById('loadDataBtn');
                    loadBtn.disabled = true;
                    loadBtn.classList.remove('btn-primary');
                    loadBtn.classList.add('btn-outline-primary');
                }
            });
        }

        // Validation du formulaire avant soumission
        document.getElementById('encodageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Vérifications supplémentaires
            const ecueSelect = document.getElementById('idECUE');
            const heuresReelles = parseFloat(document.getElementById('nombre_heures_reelles').value);
            
            if (ecueSelect.value) {
                const selectedOption = ecueSelect.options[ecueSelect.selectedIndex];
                const heuresRestantes = parseFloat(selectedOption.dataset.heuresRestantes);
                
                if (heuresReelles > heuresRestantes) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur de validation',
                        text: `Heures insuffisantes. Il ne reste que ${heuresRestantes}h pour cet ECUE.`
                    });
                    return;
                }
            }

            // Confirmer la soumission
            Swal.fire({
                title: 'Confirmer l\'encodage ?',
                html: `
                    <div class="text-start">
                        <p><strong>ECUE:</strong> ${ecueSelect.options[ecueSelect.selectedIndex].text}</p>
                        <p><strong>Date:</strong> ${document.getElementById('date_seance').value}</p>
                        <p><strong>Heures:</strong> ${document.getElementById('heure_debut').value} - ${document.getElementById('heure_fin').value}</p>
                        <p><strong>Durée:</strong> ${heuresReelles}h</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Oui, enregistrer!',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Soumission du formulaire
                    this.submit();
                }
            });
        });

        // Fonction pour voir les détails d'une évolution
        function voirDetails(idSuivi) {
            fetch(`controller/getDetailsEvolution.php?id=${idSuivi}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('detailsContent').innerHTML = data.html;
                        document.getElementById('detailsFooter').innerHTML = data.buttons;
                        new bootstrap.Modal(document.getElementById('detailsModal')).show();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: data.message || 'Erreur lors du chargement des détails'
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Erreur lors du chargement des détails'
                    });
                });
        }

        // Fonction pour valider une évolution
        function validerEvolution(idSuivi) {
            Swal.fire({
                title: 'Valider cette évolution ?',
                text: "Cette action confirmera l'évolution du cours.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Oui, valider!',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `controller/validerEvolution.php?id=${idSuivi}&action=valider`;
                }
            });
        }

        // Fonction pour rejeter une évolution
        function rejeterEvolution(idSuivi) {
            Swal.fire({
                title: 'Rejeter cette évolution ?',
                input: 'textarea',
                inputLabel: 'Motif du rejet',
                inputPlaceholder: 'Veuillez indiquer le motif du rejet...',
                inputAttributes: {
                    'aria-label': 'Motif du rejet'
                },
                showCancelButton: true,
                confirmButtonText: 'Rejeter',
                cancelButtonText: 'Annuler',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Vous devez indiquer un motif de rejet!'
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const commentaire = result.value;
                    window.location.href = `controller/validerEvolution.php?id=${idSuivi}&action=rejeter&commentaire=${encodeURIComponent(commentaire)}`;
                }
            });
        }

        // Ajouter CSS pour l'animation de rotation
        const style = document.createElement('style');
        style.textContent = `
            .spin {
                animation: spin 1s linear infinite;
            }
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);

        // Initialisation au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            // Désactiver les champs initialement
            resetMainForm();
        });
    </script>
</main>

<?php include "./views/include/footer.php"; ?>

