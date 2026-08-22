<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des unités de mesure
$query = "SELECT * FROM unite_mesure ORDER BY libelle_unite";
$stmt = $db->prepare($query);
$stmt->execute();
$unites = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        <h1>UNITÉS DE MESURE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Paramétrage</li>
                <li class="breadcrumb-item active">Unités de mesure</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Liste des unités de mesure
                            <button type="button" class="btn btn-primary btn-sm float-end" data-bs-toggle="modal" data-bs-target="#addUniteModal">
                                <i class="bi bi-plus-circle"></i> Nouvelle unité
                            </button>
                        </h5>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered datatable">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Libellé</th>
                                        <th>Symbole</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unites as $unite): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($unite['code_unite']) ?></td>
                                        <td><?= htmlspecialchars($unite['libelle_unite']) ?></td>
                                        <td><?= htmlspecialchars($unite['symbole_unite']) ?></td>
                                        <td>
                                            <button type="button" class="btn btn-warning btn-sm" 
                                                onclick="editUnite(<?= $unite['id_unite'] ?>, '<?= addslashes($unite['code_unite']) ?>', '<?= addslashes($unite['libelle_unite']) ?>', '<?= addslashes($unite['symbole_unite']) ?>')">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $unite['id_unite'] ?>)">
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

<!-- Modal Ajout Unité -->
<div class="modal fade" id="addUniteModal" tabindex="-1" aria-labelledby="addUniteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUniteModalLabel">Nouvelle Unité de Mesure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addUniteForm" action="controller/create_unite.php" method="POST" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="code_unite" class="form-label">Code</label>
                        <input type="text" class="form-control" id="code_unite" name="code_unite" maxlength="10" required>
                        <div class="invalid-feedback">Veuillez saisir un code.</div>
                    </div>
                    <div class="mb-3">
                        <label for="libelle_unite" class="form-label">Libellé</label>
                        <input type="text" class="form-control" id="libelle_unite" name="libelle_unite" required>
                        <div class="invalid-feedback">Veuillez saisir un libellé.</div>
                    </div>
                    <div class="mb-3">
                        <label for="symbole_unite" class="form-label">Symbole</label>
                        <input type="text" class="form-control" id="symbole_unite" name="symbole_unite" maxlength="10">
                        <div class="form-text">Exemple: kg, m, l, etc.</div>
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

<!-- Modal Modification Unité -->
<div class="modal fade" id="editUniteModal" tabindex="-1" aria-labelledby="editUniteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUniteModalLabel">Modifier Unité de Mesure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editUniteForm" action="controller/update_unite.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" id="edit_id_unite" name="id_unite">
                    <div class="mb-3">
                        <label for="edit_code_unite" class="form-label">Code</label>
                        <input type="text" class="form-control" id="edit_code_unite" name="code_unite" maxlength="10" required>
                        <div class="invalid-feedback">Veuillez saisir un code.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_libelle_unite" class="form-label">Libellé</label>
                        <input type="text" class="form-control" id="edit_libelle_unite" name="libelle_unite" required>
                        <div class="invalid-feedback">Veuillez saisir un libellé.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_symbole_unite" class="form-label">Symbole</label>
                        <input type="text" class="form-control" id="edit_symbole_unite" name="symbole_unite" maxlength="10">
                        <div class="form-text">Exemple: kg, m, l, etc.</div>
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
    // Fonction pour éditer une unité
    function editUnite(id, code, libelle, symbole) {
        document.getElementById('edit_id_unite').value = id;
        document.getElementById('edit_code_unite').value = code;
        document.getElementById('edit_libelle_unite').value = libelle;
        document.getElementById('edit_symbole_unite').value = symbole;
        
        var editModal = new bootstrap.Modal(document.getElementById('editUniteModal'));
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
                window.location.href = 'controller/delete_unite.php?id=' + id;
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
