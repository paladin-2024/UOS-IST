<?php
include "./views/include/header.php";

// Initialisation des classes
$soutenanceModel = new Soutenance();
$universite = new Universite();
$agentModel = new Agent();

// Récupération de l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();
$yearId = $currentYear['idannee_acad'];

// Récupérer les jurys existants
$jurys = $soutenanceModel->getAllJurys($yearId);

// Récupérer tous les enseignants pour les sélecteurs
$enseignants = $agentModel->getAgentsByType('Enseignant');
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES JURYS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Recherche</li>
                <li class="breadcrumb-item active">Gestion des Jurys</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title d-flex justify-content-between align-items-center">
                            Liste des jurys
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createJuryModal">
                                <i class="bi bi-plus-circle"></i> Nouveau jury
                            </button>
                        </h5>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Désignation</th>
                                        <th>Président</th>
                                        <th>Secrétaire</th>
                                        <th>Section</th>
                                        <th>Date de création</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($jurys)): ?>
                                        <?php foreach ($jurys as $jury): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($jury['designation']) ?></td>
                                                <td><?= htmlspecialchars($jury['president_nom']) ?></td>
                                                <td><?= htmlspecialchars($jury['secretaire_nom']) ?></td>
                                                <td><?= htmlspecialchars($jury['section_nom'] ?? 'Toutes sections') ?></td>
                                                <td><?= date('d/m/Y', strtotime($jury['date_creation'])) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewJurySoutenances(<?= $jury['idjury'] ?>)">
                                                        <i class="bi bi-eye"></i> Voir soutenances
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">Aucun jury enregistré</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour créer un jury -->
<div class="modal fade" id="createJuryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Créer un nouveau jury</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="controller/depot_soutenance_controller.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="create_jury">
                    <input type="hidden" name="id_annee_acad" value="<?= $yearId ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="designation" class="form-label">Désignation du jury</label>
                            <input type="text" name="designation" id="designation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation pour ce jury.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="id_president" class="form-label">Président du jury</label>
                            <select name="id_president" id="id_president" class="form-select" required>
                                <option value="">Sélectionner un président</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?= $enseignant['idAgent'] ?>">
                                        <?= htmlspecialchars($enseignant['gradeDesignation'] ?? '') ?> 
                                        <?= htmlspecialchars($enseignant['noms']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un président.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="id_secretaire" class="form-label">Secrétaire du jury</label>
                            <select name="id_secretaire" id="id_secretaire" class="form-select" required>
                                <option value="">Sélectionner un secrétaire</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?= $enseignant['idAgent'] ?>">
                                        <?= htmlspecialchars($enseignant['gradeDesignation'] ?? '') ?> 
                                        <?= htmlspecialchars($enseignant['noms']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un secrétaire.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="id_section" class="form-label">Section (optionnel)</label>
                            <select name="id_section" id="id_section" class="form-select">
                                <option value="">Toutes les sections</option>
                                <?php 
                                $sections = $universite->getSections();
                                foreach ($sections as $section): 
                                ?>
                                    <option value="<?= $section['idsection'] ?>">
                                        <?= htmlspecialchars($section['designationSection']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Créer le jury</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function viewJurySoutenances(juryId) {
        window.location.href = `?view=recherche/jury_soutenances&jury_id=${juryId}`;
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Validation des formulaires Bootstrap
        var forms = document.querySelectorAll('.needs-validation');
        Array.prototype.slice.call(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
        
        // Vérifier que le président et le secrétaire sont différents
        const presidentSelect = document.getElementById('id_president');
        const secretaireSelect = document.getElementById('id_secretaire');
        
        function checkDifferentMembers() {
            if (presidentSelect.value && secretaireSelect.value && 
                presidentSelect.value === secretaireSelect.value) {
                secretaireSelect.setCustomValidity('Le président et le secrétaire doivent être différents');
            } else {
                secretaireSelect.setCustomValidity('');
            }
        }
        
        presidentSelect.addEventListener('change', checkDifferentMembers);
        secretaireSelect.addEventListener('change', checkDifferentMembers);
    });
</script>

<?php include "./views/include/footer.php"; ?>
