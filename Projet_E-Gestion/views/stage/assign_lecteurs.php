<?php
include "./views/include/header.php";

if (!isset($_SESSION['id'])) {
    echo "<script>window.location.href='?view=login';</script>";
    exit;
}

// Vérification que l'utilisateur est administrateur
if ($_SESSION['idRole'] != 1) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Seuls les administrateurs peuvent accéder à cette page.'
        }).then(() => {
            window.location.href = '?view=stage';
        });
    </script>";
    exit;
}

$stage = new Stage();
$agentModel = new Agent();

// Get active academic year
$activeYear = $stage->getActiveAcademicYear();
$activeYearId = $activeYear ? $activeYear['idannee_acad'] : null;

// Selected year
$selectedYearId = isset($_GET['annee_acad']) ? $_GET['annee_acad'] : $activeYearId;

// Selected promotion
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : null;

// Get all years
$allYears = $stage->getAcademicYears();

// Get promotions
$universite = new Universite();
$promotions = $universite->getPromotionsByAnneeAcad($selectedYearId);

// Get enseignants (lecteurs potentiels)
$enseignants = $agentModel->getAgentsByType('Enseignant');

// Get stages with reports but without lecteur
$stagesWithReports = [];
if ($promotionId) {
    try {
        $db = Connexion::getInstance()->getPDO();
        
        $sql = 'SELECT
                    s.idstage,
                    s.idetudiant,
                    s.lieu_stage,
                    s.rapport_path,
                    s.idlecteur,
                    e.noms as nom_etudiant,
                    e.matricule,
                    lect.noms as lecteur_nom
                FROM stage_assignments s
                JOIN etudiant e ON s.idetudiant = e.idetudiant
                LEFT JOIN agent lect ON s.idlecteur = lect."idAgent"
                WHERE e.promotion_idpromotion = :promotion_id
                AND e.annee_acad_idannee_acad = :annee_acad
                AND s.rapport_path IS NOT NULL
                AND s.rapport_path != \'\'
                ORDER BY e.noms';
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'promotion_id' => $promotionId,
            'annee_acad' => $selectedYearId
        ]);
        $stagesWithReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Erreur récupération stages avec rapports: " . $e->getMessage());
    }
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>AFFECTATION DES LECTEURS DE STAGE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item"><a href="stage">Stages</a></li>
                <li class="breadcrumb-item active">Affecter Lecteurs</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <!-- Filtres -->
            <div class="col-lg-12">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-funnel me-2"></i>Filtres de sélection
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <!-- Année académique -->
                            <div class="col-md-6">
                                <label for="yearSelect" class="form-label">
                                    <i class="bi bi-calendar me-1"></i>Année Académique
                                </label>
                                <select class="form-select" id="yearSelect" onchange="updateYearFilter(this.value)">
                                    <?php foreach ($allYears as $year): ?>
                                        <option value="<?= $year['idannee_acad'] ?>" 
                                                <?= $year['idannee_acad'] == $selectedYearId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($year['designation']) ?>
                                            <?= $year['est_active'] == 1 ? ' (Active)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Promotion -->
                            <div class="col-md-6">
                                <label for="promotionSelect" class="form-label required">
                                    <i class="bi bi-mortarboard me-1"></i>Promotion
                                </label>
                                <select class="form-select" id="promotionSelect" onchange="updatePromotionFilter(this.value)" required>
                                    <option value="">Sélectionner une promotion...</option>
                                    <?php foreach ($promotions as $promo): ?>
                                        <option value="<?= $promo['idpromotion'] ?>" 
                                                <?= $promo['idpromotion'] == $promotionId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($promo['designationPromotion']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($promotionId && !empty($stagesWithReports)): ?>
            <!-- Liste des étudiants -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-file-earmark-check me-2"></i>
                            Étudiants avec Rapport Déposé (<?= count($stagesWithReports) ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="assignLecteursForm" method="POST" action="controller/assign_lecteurs_multiple.php">
                            <input type="hidden" name="promotion_id" value="<?= $promotionId ?>">
                            <input type="hidden" name="annee_acad" value="<?= $selectedYearId ?>">
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                                            <i class="bi bi-check-square me-1"></i>Tout sélectionner
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">
                                            <i class="bi bi-square me-1"></i>Tout désélectionner
                                        </button>
                                    </div>
                                    <span class="text-muted" id="selectedCount">0 étudiant(s) sélectionné(s)</span>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th width="50">
                                                <input type="checkbox" class="form-check-input" id="selectAllCheckbox" onclick="toggleAll(this)">
                                            </th>
                                            <th>Matricule</th>
                                            <th>Étudiant</th>
                                            <th>Lieu de Stage</th>
                                            <th>Lecteur Actuel</th>
                                            <th>Rapport</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stagesWithReports as $stage): ?>
                                        <tr class="student-row" data-has-lecteur="<?= !empty($stage['idlecteur']) ? '1' : '0' ?>">
                                            <td>
                                                <input type="checkbox" 
                                                       class="form-check-input student-checkbox" 
                                                       name="stage_ids[]" 
                                                       value="<?= $stage['idstage'] ?>"
                                                       onchange="updateSelectedCount()">
                                            </td>
                                            <td><?= htmlspecialchars($stage['matricule']) ?></td>
                                            <td><?= htmlspecialchars($stage['nom_etudiant']) ?></td>
                                            <td><?= htmlspecialchars($stage['lieu_stage']) ?></td>
                                            <td>
                                                <?php if (!empty($stage['lecteur_nom'])): ?>
                                                    <span class="badge bg-info"><?= htmlspecialchars($stage['lecteur_nom']) ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Non attribué</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?= htmlspecialchars($stage['rapport_path']) ?>" 
                                                   target="_blank" 
                                                   class="btn btn-sm btn-success">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <label for="lecteur_id" class="form-label required">
                                        <i class="bi bi-person-badge me-1"></i>Lecteur à attribuer
                                    </label>
                                    <select class="form-select" id="lecteur_id" name="lecteur_id" required>
                                        <option value="">Sélectionner un enseignant...</option>
                                        <?php foreach ($enseignants as $ens): ?>
                                            <option value="<?= $ens['idAgent'] ?>">
                                                <?= htmlspecialchars(($ens['gradeDesignation'] ? $ens['gradeDesignation'] . ' ' : '') . $ens['noms']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="replaceExisting" name="replace_existing" value="1">
                                        <label class="form-check-label" for="replaceExisting">
                                            Remplacer les lecteurs déjà attribués
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Information :</strong> Le lecteur aura accès aux rapports des stages sélectionnés et pourra attribuer une cote sur 20.
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="stage" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-1"></i>Retour
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle me-1"></i>Affecter le Lecteur
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php elseif ($promotionId): ?>
            <!-- Aucun stage avec rapport -->
            <div class="col-lg-12">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Aucun rapport déposé</strong><br>
                    Il n'y a pas de rapports de stage déposés pour cette promotion.
                </div>
            </div>
            
            <?php else: ?>
            <!-- Aucune promotion sélectionnée -->
            <div class="col-lg-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Veuillez sélectionner une promotion pour voir les stages avec rapports déposés.
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<script>
function updateYearFilter(value) {
    let url = new URL(window.location);
    url.searchParams.set('annee_acad', value);
    url.searchParams.delete('promotion');
    window.location.href = url.toString();
}

function updatePromotionFilter(value) {
    let url = new URL(window.location);
    if (value) {
        url.searchParams.set('promotion', value);
    } else {
        url.searchParams.delete('promotion');
    }
    window.location.href = url.toString();
}

function toggleAll(checkbox) {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateSelectedCount();
}

function selectAll() {
    document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = true);
    document.getElementById('selectAllCheckbox').checked = true;
    updateSelectedCount();
}

function deselectAll() {
    document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAllCheckbox').checked = false;
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = document.querySelectorAll('.student-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count + ' étudiant(s) sélectionné(s)';
}

// Soumission du formulaire
document.getElementById('assignLecteursForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const selectedStages = document.querySelectorAll('.student-checkbox:checked');
    const lecteurId = document.getElementById('lecteur_id').value;
    
    if (selectedStages.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Aucun étudiant sélectionné',
            text: 'Veuillez sélectionner au moins un étudiant.',
            confirmButtonColor: '#ffc107'
        });
        return;
    }
    
    if (!lecteurId) {
        Swal.fire({
            icon: 'warning',
            title: 'Lecteur non sélectionné',
            text: 'Veuillez sélectionner un enseignant lecteur.',
            confirmButtonColor: '#ffc107'
        });
        return;
    }
    
    const formData = new FormData(this);
    
    Swal.fire({
        title: 'Confirmation',
        html: `Vous allez affecter le lecteur à <strong>${selectedStages.length}</strong> stage(s).<br>Continuer ?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4CAF50',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, affecter',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Traitement...',
                text: 'Affectation des lecteurs en cours',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('controller/assign_lecteurs_multiple.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Affectation réussie!',
                        html: data.message || 'Les lecteurs ont été affectés avec succès.',
                        confirmButtonColor: '#4CAF50'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message || 'Une erreur est survenue lors de l\'affectation.',
                        confirmButtonColor: '#d33'
                    });
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur de connexion',
                    text: 'Impossible de communiquer avec le serveur.',
                    confirmButtonColor: '#d33'
                });
            });
        }
    });
});
</script>

<?php include "./views/include/footer_file.php"; ?>
