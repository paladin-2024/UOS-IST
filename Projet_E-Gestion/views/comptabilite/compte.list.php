<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des comptes comptables
$query = "SELECT c.*, parent.intitule_compte as parent_nom 
          FROM compte_comptable c
          LEFT JOIN compte_comptable parent ON c.compte_parent = parent.id_compte
          ORDER BY c.numero_compte";
$stmt = $db->prepare($query);
$stmt->execute();
$comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!-- Espace de travail -->
<main id="main" class="main">
    <!-- Toast Notifications -->
    <div class="toast-container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="toast toast-animated align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-check-circle me-2"></i> <?= htmlspecialchars($_SESSION['success_message']) ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="toast toast-animated align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-exclamation-triangle me-2"></i> <?= htmlspecialchars($_SESSION['error_message']) ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
    </div>

    <div class="pagetitle">
        <h1>PLAN COMPTABLE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Comptabilité</li>
                <li class="breadcrumb-item active">Plan Comptable</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Liste des comptes
                            <button type="button" class="btn btn-primary btn-sm float-end" data-bs-toggle="modal" data-bs-target="#addCompteModal">
                                <i class="bi bi-plus-circle"></i> Nouveau compte
                            </button>
                        </h5>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered datatable">
                                <thead>
                                    <tr>
                                        <th>Numéro</th>
                                        <th>Intitulé</th>
                                        <th>Classe</th>
                                        <th>Compte parent</th>
                                        <th>Type</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($comptes as $compte): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($compte['numero_compte']) ?></td>
                                        <td><?= htmlspecialchars($compte['intitule_compte']) ?></td>
                                        <td><?= htmlspecialchars($compte['classe_compte']) ?></td>
                                        <td><?= htmlspecialchars($compte['parent_nom'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($compte['type_compte']) ?></td>
                                        <td>
                                            <button type="button" class="btn btn-warning btn-sm" 
                                                onclick="editCompte(<?= $compte['id_compte'] ?>, '<?= addslashes($compte['numero_compte']) ?>', '<?= addslashes($compte['intitule_compte']) ?>', <?= $compte['classe_compte'] ?>, <?= $compte['compte_parent'] ? $compte['compte_parent'] : 'null' ?>, '<?= $compte['type_compte'] ?>')">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $compte['id_compte'] ?>)">
                                                <i class="bi bi-trash"></i>
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
    </section>
</main><!-- End #main -->

<!-- Modal Ajout Compte -->
<div class="modal fade" id="addCompteModal" tabindex="-1" aria-labelledby="addCompteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCompteModalLabel">Nouveau Compte Comptable</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addCompteForm" action="controller/create_compte.php" method="POST" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="numero_compte" class="form-label">Numéro de compte</label>
                        <input type="text" class="form-control" id="numero_compte" name="numero_compte" required>
                        <div class="invalid-feedback">Veuillez saisir un numéro de compte.</div>
                    </div>
                    <div class="mb-3">
                        <label for="intitule_compte" class="form-label">Intitulé</label>
                        <input type="text" class="form-control" id="intitule_compte" name="intitule_compte" required>
                        <div class="invalid-feedback">Veuillez saisir un intitulé.</div>
                    </div>
                    <div class="mb-3">
                        <label for="classe_compte" class="form-label">Classe</label>
                        <select class="form-select" id="classe_compte" name="classe_compte" required>
                            <option value="" selected disabled>Sélectionner une classe</option>
                            <option value="1">1 - Comptes de capitaux</option>
                            <option value="2">2 - Comptes d'immobilisations</option>
                            <option value="3">3 - Comptes de stocks</option>
                            <option value="4">4 - Comptes de tiers</option>
                            <option value="5">5 - Comptes financiers</option>
                            <option value="6">6 - Comptes de charges</option>
                            <option value="7">7 - Comptes de produits</option>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner une classe.</div>
                    </div>
                    <div class="mb-3">
                        <label for="compte_parent" class="form-label">Compte parent</label>
                        <select class="form-select" id="compte_parent" name="compte_parent">
                            <option value="">Aucun (compte principal)</option>
                            <?php foreach ($comptes as $compte): ?>
                                <option value="<?= $compte['id_compte'] ?>"><?= htmlspecialchars($compte['numero_compte'] . ' - ' . $compte['intitule_compte']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="type_compte" class="form-label">Type de compte</label>
                        <select class="form-select" id="type_compte" name="type_compte" required>
                            <option value="" selected disabled>Sélectionner un type</option>
                            <option value="Actif">Actif</option>
                            <option value="Passif">Passif</option>
                            <option value="Charge">Charge</option>
                            <option value="Produit">Produit</option>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner un type.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Modification Compte -->
<div class="modal fade" id="editCompteModal" tabindex="-1" aria-labelledby="editCompteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCompteModalLabel">Modifier Compte Comptable</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editCompteForm" action="controller/update_compte.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" id="edit_id_compte" name="id_compte">
                    <div class="mb-3">
                        <label for="edit_numero_compte" class="form-label">Numéro de compte</label>
                        <input type="text" class="form-control" id="edit_numero_compte" name="numero_compte" required>
                        <div class="invalid-feedback">Veuillez saisir un numéro de compte.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_intitule_compte" class="form-label">Intitulé</label>
                        <input type="text" class="form-control" id="edit_intitule_compte" name="intitule_compte" required>
                        <div class="invalid-feedback">Veuillez saisir un intitulé.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_classe_compte" class="form-label">Classe</label>
                        <select class="form-select" id="edit_classe_compte" name="classe_compte" required>
                            <option value="" selected disabled>Sélectionner une classe</option>
                            <option value="1">1 - Comptes de capitaux</option>
                            <option value="2">2 - Comptes d'immobilisations</option>
                            <option value="3">3 - Comptes de stocks</option>
                            <option value="4">4 - Comptes de tiers</option>
                            <option value="5">5 - Comptes financiers</option>
                            <option value="6">6 - Comptes de charges</option>
                            <option value="7">7 - Comptes de produits</option>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner une classe.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_compte_parent" class="form-label">Compte parent</label>
                        <select class="form-select" id="edit_compte_parent" name="compte_parent">
                            <option value="">Aucun (compte principal)</option>
                            <?php foreach ($comptes as $compte): ?>
                                <option value="<?= $compte['id_compte'] ?>"><?= htmlspecialchars($compte['numero_compte'] . ' - ' . $compte['intitule_compte']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_type_compte" class="form-label">Type de compte</label>
                        <select class="form-select" id="edit_type_compte" name="type_compte" required>
                            <option value="" selected disabled>Sélectionner un type</option>
                            <option value="Actif">Actif</option>
                            <option value="Passif">Passif</option>
                            <option value="Charge">Charge</option>
                            <option value="Produit">Produit</option>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner un type.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>

    // Fonction pour éditer un compte
    function editCompte(id, numero, intitule, classe, parent, type) {
        document.getElementById('edit_id_compte').value = id;
        document.getElementById('edit_numero_compte').value = numero;
        document.getElementById('edit_intitule_compte').value = intitule;
        document.getElementById('edit_classe_compte').value = classe;
        document.getElementById('edit_compte_parent').value = parent === null ? '' : parent;
        document.getElementById('edit_type_compte').value = type;
        
        var editModal = new bootstrap.Modal(document.getElementById('editCompteModal'));
        editModal.show();
    }
    
    // Fonction pour confirmer la suppression
    function confirmDelete(id) {
        Swal.fire({
            title: 'Êtes-vous sûr?',
            text: "Cette action est irréversible!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer!',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/delete_compte.php?id=' + id;
            }
        });
    }
    
    // Validation des formulaires
    (function() {
        'use strict';
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
    })();
    
    // Initialisation de la table des données
    $(document).ready(function() {
        $('.datatable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/fr-FR.json'
            },
            responsive: true
        });
    });
</script>


<?php include "./views/include/footer.php"; ?>

