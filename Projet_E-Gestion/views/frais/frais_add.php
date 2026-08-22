
<?php
include "./views/include/header.php";

$universite = new Universite();

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Récupérer les données nécessaires
$academicYears = $universite->getAcademicYears();
$promotions = $universite->getPromotions();
$frais = $universite->getFrais($search);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>CONFIGURATION DES FRAIS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                <li class="breadcrumb-item active">Gestion des Frais</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Liste des Frais
                            <button type="button" class="btn btn-primary float-end" data-bs-toggle="modal" data-bs-target="#createFraisModal">
                                <i class="bi bi-plus-circle"></i> Nouveau Frais
                            </button>
                        </h5>

                        <!-- Filtres de recherche -->
                        <form method="GET" action="" class="mb-4">
                            <div class="row g-3">
                                <div class="col-md-10">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher par désignation, promotion ou année académique...">
                                </div>
                                
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">Rechercher</button>
                                </div>
                            </div>
                        </form>

                        <!-- Table des frais -->
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Désignation</th>
                                        <th>Montant</th>
                                        <th>Devise</th>
                                        <th>Promotion</th>
                                        <th>Année Académique</th>
                                        <th>Obligatoire</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($frais as $index => $f): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($f['designation']) ?></td>
                                        <td><?= number_format($f['montant'], 2) ?></td>
                                        <td><?= htmlspecialchars($f['devise']) ?></td>
                                        <td><?= htmlspecialchars($f['designationPromotion']) ?></td>
                                        <td><?= htmlspecialchars($f['anneeDesignation']) ?></td>
                                        <td>
                                            <?php if ($f['estObligatoire']): ?>
                                                <span class="badge bg-success">Oui</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Non</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewFrais(<?= $f['idfrais'] ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-primary" onclick="editFrais(<?= $f['idfrais'] ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteFrais(<?= $f['idfrais'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($frais)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Aucun frais trouvé</td>
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

<!-- Modal pour ajouter un frais -->
<div class="modal fade" id="createFraisModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Nouveau Frais</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="fraisForm" action="controller/create_frais.php" method="POST">
                    <div class="row g-3">
                        <!-- Désignation -->
                        <div class="col-md-12">
                            <label class="form-label">Désignation</label>
                            <input type="text" name="designation" class="form-control" required>
                        </div>

                        <!-- Montant et Devise -->
                        <div class="col-md-6">
                            <label class="form-label">Montant</label>
                            <input type="number" name="montant" class="form-control" step="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Devise</label>
                            <select name="devise" class="form-select" required>
                                <option value="USD">USD</option>
                                <option value="FC">FC</option>
                                <option value="EUR">EUR</option>
                            </select>
                        </div>

                        <!-- Promotion -->
                        <div class="col-md-6">
                            <label class="form-label">Promotion</label>
                            <select name="promotionId" class="form-select" required>
                                <option value="">Sélectionner...</option>
                                <?php foreach ($promotions as $promotion): ?>
                                    <option value="<?= $promotion['idpromotion'] ?>"><?= htmlspecialchars($promotion['designationPromotion']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Année académique -->
                        <div class="col-md-6">
                            <label class="form-label">Année académique</label>
                            <select name="anneeAcadId" class="form-select" required>
                                <option value="">Sélectionner...</option>
                                <?php foreach ($academicYears as $year): ?>
                                    <option value="<?= $year['idannee_acad'] ?>"><?= htmlspecialchars($year['designation']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Description -->
                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>

                        <!-- Est obligatoire -->
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="estObligatoire" id="estObligatoire" value="1" checked>
                                <label class="form-check-label" for="estObligatoire">
                                    Frais obligatoire
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un frais -->
<div class="modal fade" id="editFraisModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le Frais</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editFraisForm" action="controller/update_frais.php" method="POST">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row g-3">
                        <!-- Désignation -->
                        <div class="col-md-12">
                            <label class="form-label">Désignation</label>
                            <input type="text" name="designation" id="edit_designation" class="form-control" required>
                        </div>

                        <!-- Montant et Devise -->
                        <div class="col-md-6">
                            <label class="form-label">Montant</label>
                            <input type="number" name="montant" id="edit_montant" class="form-control" step="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Devise</label>
                            <select name="devise" id="edit_devise" class="form-select" required>
                                <option value="USD">USD</option>
                                <option value="FC">FC</option>
                                <option value="EUR">EUR</option>
                            </select>
                        </div>

                        <!-- Promotion -->
                        <div class="col-md-6">
                            <label class="form-label">Promotion</label>
                            <select name="promotionId" id="edit_promotionId" class="form-select" required>
                                <option value="">Sélectionner...</option>
                                <?php foreach ($promotions as $promotion): ?>
                                    <option value="<?= $promotion['idpromotion'] ?>"><?= htmlspecialchars($promotion['designationPromotion']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Année académique -->
                        <div class="col-md-6">
                            <label class="form-label">Année académique</label>
                            <select name="anneeAcadId" id="edit_anneeAcadId" class="form-select" required>
                                <option value="">Sélectionner...</option>
                                <?php foreach ($academicYears as $year): ?>
                                    <option value="<?= $year['idannee_acad'] ?>"><?= htmlspecialchars($year['designation']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Description -->
                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>

                        <!-- Est obligatoire -->
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="estObligatoire" id="edit_estObligatoire" value="1">
                                <label class="form-check-label" for="edit_estObligatoire">
                                    Frais obligatoire
                                </label>
                            </div>
                        </div>
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

<!-- Modal pour voir les détails d'un frais -->
<div class="modal fade" id="viewFraisModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails du Frais</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Désignation:</strong> <span id="view_designation"></span></p>
                        <p><strong>Montant:</strong> <span id="view_montant"></span> <span id="view_devise"></span></p>
                        <p><strong>Promotion:</strong> <span id="view_promotion"></span></p>
                        <p><strong>Année Académique:</strong> <span id="view_annee"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Obligatoire:</strong> <span id="view_obligatoire"></span></p>
                        <p><strong>Date de création:</strong> <span id="view_dateCreation"></span></p>
                        <p><strong>Description:</strong></p>
                        <p id="view_description" class="border p-2 rounded bg-light"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
function editFrais(id) {
    // Charger les détails du frais via une requête AJAX
    fetch(`controller/get_frais_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            // Remplir le formulaire avec les données
            document.getElementById('edit_id').value = data.idfrais;
            document.getElementById('edit_designation').value = data.designation;
            document.getElementById('edit_montant').value = data.montant;
            document.getElementById('edit_devise').value = data.devise;
            document.getElementById('edit_promotionId').value = data.promotion_idpromotion;
            document.getElementById('edit_anneeAcadId').value = data.annee_acad_idannee_acad;
            document.getElementById('edit_description').value = data.description;
            document.getElementById('edit_estObligatoire').checked = data.estObligatoire == 1;

            // Afficher le modal
            new bootstrap.Modal(document.getElementById('editFraisModal')).show();
        })
        .catch(error => {
            console.error('Erreur:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Impossible de charger les détails du frais'
            });
        });
}

function viewFrais(id) {
    // Charger les détails du frais via une requête AJAX
    fetch(`controller/get_frais_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            // Remplir les champs de la modal avec les données
            document.getElementById('view_designation').textContent = data.designation;
            document.getElementById('view_montant').textContent = data.montant;
            document.getElementById('view_devise').textContent = data.devise;
            document.getElementById('view_promotion').textContent = data.designationPromotion;
            document.getElementById('view_annee').textContent = data.anneeDesignation;
            document.getElementById('view_obligatoire').textContent = data.estObligatoire == 1 ? 'Oui' : 'Non';
            document.getElementById('view_dateCreation').textContent = new Date(data.dateCreation).toLocaleString();
            document.getElementById('view_description').textContent = data.description || 'Aucune description';

            // Afficher la modal
            new bootstrap.Modal(document.getElementById('viewFraisModal')).show();
        })
        .catch(error => {
            console.error('Erreur:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Impossible de charger les détails du frais'
            });
        });
}

function deleteFrais(id) {
    Swal.fire({
        title: 'Êtes-vous sûr ?',
        text: "Cette action est irréversible ! Les paiements associés à ce frais ne seront pas supprimés.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'controller/delete_frais.php?id=' + id;
        }
    });
}
</script>

<?php include "./views/include/footer.php"; ?>
