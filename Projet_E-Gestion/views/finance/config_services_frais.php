<?php
include "./views/include/header.php";

$connexion = Connexion::getInstance()->getPDO();
$idUser = $_SESSION['id'];

// Initialiser le modèle
$dependanceModel = new DependanceServiceFrais();

// Récupérer l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();
$annee_acad_id = isset($_GET['annee_acad_id']) ? intval($_GET['annee_acad_id']) : ($currentYear ? $currentYear['idannee_acad'] : 0);

// Récupérer les années académiques
$stmt = $connexion->prepare("SELECT idannee_acad, designation FROM annee_acad ORDER BY designation DESC");
$stmt->execute();
$annees_academiques = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer tous les services/documents
$services = $dependanceModel->getAllServices();

// Récupérer les promotions
$stmt = $connexion->prepare("
    SELECT p.idpromotion, p.\"designationPromotion\", o.\"designationOrientation\", s.\"designationSection\"
    FROM promotion p
    JOIN orientation o ON p.orientation_idorientation = o.idorientation
    JOIN section s ON o.section_idsection = s.idsection
    ORDER BY s.\"designationSection\", o.\"designationOrientation\", p.\"designationPromotion\"
");
$stmt->execute();
$promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Messages
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';
unset($_SESSION['message'], $_SESSION['messageType']);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Configuration Services/Documents - Frais Requis</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Configuration Services/Frais</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Création d'un nouveau service -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Nouveau Service/Document</h5>
                        <form action="controller/create_service_frais.php" method="POST">
                            <div class="mb-3">
                                <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="designation" name="designation" required placeholder="Ex: Certificat d'études">
                            </div>

                            <div class="mb-3">
                                <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="">Sélectionnez un type</option>
                                    <option value="service">Service</option>
                                    <option value="document">Document</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Décrivez ce service ou document..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="scope" class="form-label">Portée <span class="text-danger">*</span></label>
                                <select class="form-select" id="scope" name="scope" required>
                                    <option value="">Sélectionnez une portée</option>
                                    <option value="promotion">Par promotion</option>
                                    <option value="cycle">Par cycle</option>
                                    <option value="annee_complete">Année complète</option>
                                </select>
                                <small class="text-muted">Portée par laquelle ce service sera configuré</small>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Créer le service</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Configuration des dépendances frais -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Configuration des Frais Requis</h5>

                        <?php if (empty($services)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Créez d'abord un service ou un document pour configurer les frais requis.
                            </div>
                        <?php else: ?>
                            <!-- Filtre année académique -->
                            <div class="mb-3">
                                <label for="filter_annee" class="form-label">Filtrer par année académique</label>
                                <select class="form-select" id="filter_annee" onchange="window.location.href='?view=finance/config_services_frais&annee_acad_id=' + this.value">
                                    <option value="">Toutes les années</option>
                                    <?php foreach ($annees_academiques as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" <?= $annee_acad_id == $annee['idannee_acad'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                            <?php if ($currentYear && $annee['idannee_acad'] == $currentYear['idannee_acad']): ?>
                                                (En cours)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Onglets pour chaque service -->
                            <ul class="nav nav-tabs" role="tablist">
                                <?php foreach ($services as $index => $service): ?>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link <?= $index === 0 ? 'active' : '' ?>"
                                            id="service-tab-<?= $service['id'] ?>"
                                            data-bs-toggle="tab"
                                            data-bs-target="#service-content-<?= $service['id'] ?>"
                                            type="button" role="tab">
                                            <span class="badge bg-secondary me-2"><?= ucfirst($service['type']) ?></span>
                                            <?= htmlspecialchars(substr($service['designation'], 0, 25)) ?>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <!-- Contenu des onglets -->
                            <div class="tab-content mt-3">
                                <?php foreach ($services as $index => $service): ?>
                                    <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>"
                                        id="service-content-<?= $service['id'] ?>" role="tabpanel">

                                        <?php
                                        $dependances = $dependanceModel->getDependancesByService($service['id']);
                                        ?>

                                        <div class="mb-3">
                                            <h6><?= htmlspecialchars($service['designation']) ?></h6>
                                            <?php if ($service['description']): ?>
                                                <p class="text-muted small"><?= htmlspecialchars($service['description']) ?></p>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Formulaire d'ajout de dépendance -->
                                        <form action="controller/add_dependance_service_frais.php" method="POST" class="mb-3">
                                            <input type="hidden" name="service_id" value="<?= $service['id'] ?>">

                                            <div class="row">
                                                <?php if ($service['scope'] === 'promotion'): ?>
                                                    <div class="col-md-6 mb-3">
                                                        <label for="promotion-<?= $service['id'] ?>" class="form-label">Promotion(s) <span class="text-danger">*</span></label>
                                                        <select class="form-select select2" id="promotion-<?= $service['id'] ?>" name="promotion_id[]" multiple="multiple" required>
                                                            <?php
                                                            // Récupérer les promotions déjà configurées pour éviter les doublons
                                                            $configuredPromos = [];
                                                            foreach ($dependances as $dep) {
                                                                if ($dep['promotion_id']) {
                                                                    $configuredPromos[] = $dep['promotion_id'];
                                                                }
                                                            }

                                                            foreach ($promotions as $promo):
                                                                // Éviter les doublons : ne pas afficher les promotions déjà configurées
                                                                if (!in_array($promo['idpromotion'], $configuredPromos)):
                                                            ?>
                                                                    <option value="<?= $promo['idpromotion'] ?>">
                                                                        <?= $promo['designationSection'] ?> - <?= $promo['designationOrientation'] ?> - <?= $promo['designationPromotion'] ?>
                                                                    </option>
                                                            <?php
                                                                endif;
                                                            endforeach;
                                                            ?>
                                                        </select>
                                                        <small class="text-muted">Sélectionnez une ou plusieurs promotions</small>
                                                    </div>
                                                <?php elseif ($service['scope'] === 'annee_complete'): ?>
                                                    <div class="col-md-6 mb-3">
                                                        <label for="annee-<?= $service['id'] ?>" class="form-label">Année académique <span class="text-danger">*</span></label>
                                                        <select class="form-select" id="annee-<?= $service['id'] ?>" name="annee_acad_id" required>
                                                            <option value="">Sélectionnez une année</option>
                                                            <?php foreach ($annees_academiques as $annee): ?>
                                                                <option value="<?= $annee['idannee_acad'] ?>" <?= $annee_acad_id == $annee['idannee_acad'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($annee['designation']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="col-md-6 mb-3">
                                                        <label for="cycle-<?= $service['id'] ?>" class="form-label">Cycle <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control" id="cycle-<?= $service['id'] ?>" name="cycle" placeholder="Ex: L1, L2, M1, M2" required>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="col-md-6 mb-3">
                                                    <label for="frais-<?= $service['id'] ?>" class="form-label">Frais à exiger <span class="text-danger">*</span></label>
                                                    <select class="form-select select2" id="frais-<?= $service['id'] ?>" name="frais_id[]" multiple="multiple" required>
                                                        <?php
                                                        if ($service['scope'] === 'promotion') {
                                                            $availableFrais = [];
                                                        } else if ($service['scope'] === 'annee_complete') {
                                                            $availableFrais = $dependanceModel->getFraisAffectesByAnneeAcad($annee_acad_id ?: 0);
                                                        } else {
                                                            $availableFrais = [];
                                                        }
                                                        ?>
                                                        <?php foreach ($availableFrais as $frais): ?>
                                                            <option value="<?= $frais['id'] ?>">
                                                                <?= htmlspecialchars($frais['designation']) ?> - <?= number_format($frais['montant'], 2) ?> <?= $frais['devise'] ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <small class="text-muted">Sélectionnez un ou plusieurs frais</small>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label for="ordre-<?= $service['id'] ?>" class="form-label">Ordre de paiement</label>
                                                <input type="number" class="form-control" id="ordre-<?= $service['id'] ?>" name="ordre" value="0" min="0">
                                                <small class="text-muted">0 = pas d'ordre spécifique, 1 = frais requis avant les autres, etc.</small>
                                            </div>

                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="bi bi-plus-circle"></i> Ajouter ce frais
                                            </button>
                                        </form>

                                        <!-- Liste des dépendances existantes -->
                                        <?php if (!empty($dependances)): ?>
                                            <div class="mt-3">
                                                <h6>Frais requis pour ce service</h6>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Frais</th>
                                                                <th>Montant</th>
                                                                <th>Scope</th>
                                                                <th>Détails</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($dependances as $dep): ?>
                                                                <tr>
                                                                    <td>
                                                                        <strong><?= htmlspecialchars($dep['frais_designation']) ?></strong>
                                                                    </td>
                                                                    <td>
                                                                        <?= number_format($dep['montant'], 2) ?> <?= $dep['devise'] ?>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge bg-info"><?= ucfirst($dep['scope']) ?></span>
                                                                    </td>
                                                                    <td>
                                                                        <small>
                                                                            <?php
                                                                            if ($dep['promotion_id']) {
                                                                                echo htmlspecialchars($dep['designationPromotion']);
                                                                            } elseif ($dep['annee_acad_id']) {
                                                                                echo htmlspecialchars($dep['annee_academique']);
                                                                            } elseif ($dep['cycle']) {
                                                                                echo "Cycle: " . htmlspecialchars($dep['cycle']);
                                                                            }
                                                                            ?>
                                                                        </small>
                                                                    </td>
                                                                    <td>
                                                                        <form action="controller/delete_dependance_service_frais.php" method="POST" style="display: inline;">
                                                                            <input type="hidden" name="dependance_id" value="<?= $dep['id'] ?>">
                                                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr ?');">
                                                                                <i class="bi bi-trash"></i>
                                                                            </button>
                                                                        </form>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-warning mt-3">
                                                <i class="bi bi-exclamation-triangle"></i> Aucun frais configuré pour ce service.
                                            </div>
                                        <?php endif; ?>

                                        <!-- Actions sur le service -->
                                        <div class="mt-3 pt-3 border-top">
                                            <form action="controller/update_service_frais.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                                                <input type="hidden" name="active" value="<?= $service['active'] ? 0 : 1 ?>">
                                                <button type="submit" class="btn btn-sm btn-warning">
                                                    <?= $service['active'] ? '<i class="bi bi-eye-slash"></i> Désactiver' : '<i class="bi bi-eye"></i> Activer' ?>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editServiceModal" onclick="loadEditService(<?= $service['id'] ?>)">
                                                <i class="bi bi-pencil"></i> Modifier
                                            </button>
                                            <form action="controller/delete_service_frais.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr ? Cette action supprimera aussi ses dépendances.');">
                                                    <i class="bi bi-trash"></i> Supprimer
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal d'édition du service -->
<div class="modal fade" id="editServiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/update_service_frais.php" method="POST">
                <input type="hidden" name="service_id" id="edit_service_id">

                <div class="modal-header">
                    <h5 class="modal-title">Modifier le service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_designation" class="form-label">Désignation</label>
                        <input type="text" class="form-control" id="edit_designation" name="designation" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof $.fn.select2 !== 'undefined') {
            $('.select2').select2({
                width: '100%'
            });
        }

        // Gérer le changement de promotion pour charger les frais disponibles
        const promotionSelects = document.querySelectorAll('[id^="promotion-"]');
        promotionSelects.forEach(select => {
            select.addEventListener('change', function() {
                const selectedPromos = Array.from(this.selectedOptions).map(opt => opt.value);
                const serviceId = this.id.replace('promotion-', '');
                const fraisSelect = document.getElementById('frais-' + serviceId);

                if (selectedPromos.length > 0 && fraisSelect) {
                    // Récupérer les frais de toutes les promotions sélectionnées
                    const allFrais = [];
                    const fraisIds = new Set();

                    Promise.all(selectedPromos.map(promoId =>
                            fetch(`controller/ajax/get_frais_by_promotion.php?promotion_id=${promoId}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    data.data.forEach(frais => {
                                        if (!fraisIds.has(frais.id)) {
                                            fraisIds.add(frais.id);
                                            allFrais.push(frais);
                                        }
                                    });
                                }
                            })
                        ))
                        .then(() => {
                            // Remplir le select avec les frais uniques
                            fraisSelect.innerHTML = '';
                            allFrais.sort((a, b) => a.designation.localeCompare(b.designation));
                            allFrais.forEach(frais => {
                                const option = document.createElement('option');
                                option.value = frais.id;
                                option.textContent = `${frais.designation} - ${parseFloat(frais.montant).toLocaleString('fr-FR', {minimumFractionDigits: 2})} ${frais.devise}`;
                                fraisSelect.appendChild(option);
                            });

                            // Réinitialiser Select2
                            if (typeof $(fraisSelect).data('select2') !== 'undefined') {
                                $(fraisSelect).select2('destroy').select2({
                                    width: '100%'
                                });
                            }
                        })
                        .catch(error => console.error('Erreur:', error));
                } else if (selectedPromos.length === 0 && fraisSelect) {
                    fraisSelect.innerHTML = '';
                    if (typeof $(fraisSelect).data('select2') !== 'undefined') {
                        $(fraisSelect).select2('destroy').select2({
                            width: '100%'
                        });
                    }
                }
            });
        });

        // Gérer le changement d'année académique pour charger les frais disponibles
        const anneeSelects = document.querySelectorAll('[id^="annee-"]');
        anneeSelects.forEach(select => {
            select.addEventListener('change', function() {
                const anneeAcadId = this.value;
                const serviceId = this.id.replace('annee-', '');
                const fraisSelect = document.getElementById('frais-' + serviceId);

                if (anneeAcadId && fraisSelect) {
                    fetch(`controller/ajax/get_frais_by_annee.php?annee_acad_id=${anneeAcadId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                fraisSelect.innerHTML = '<option value="">Sélectionnez un frais</option>';
                                data.data.forEach(frais => {
                                    const option = document.createElement('option');
                                    option.value = frais.id;
                                    option.textContent = `${frais.designation} - ${parseFloat(frais.montant).toLocaleString('fr-FR', {minimumFractionDigits: 2})} ${frais.devise}`;
                                    fraisSelect.appendChild(option);
                                });

                                if (typeof $(fraisSelect).data('select2') !== 'undefined') {
                                    $(fraisSelect).select2('destroy').select2({
                                        width: '100%'
                                    });
                                }
                            }
                        })
                        .catch(error => console.error('Erreur:', error));
                }
            });
        });
    });

    function loadEditService(serviceId) {
        // À implémenter pour charger les détails du service via AJAX
        document.getElementById('edit_service_id').value = serviceId;
    }
</script>

<?php include "./views/include/footer.php"; ?>