<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();
$idUser = $_SESSION['id'];

// Récupérer toutes les catégories de frais
$stmt = $connexion->prepare("
    SELECT * FROM categories_frais
    ORDER BY designation ASC
");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les messages d'alerte
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';
unset($_SESSION['message'], $_SESSION['messageType']);

// Récupérer les informations d'une catégorie pour l'édition si ID est fourni
$categorie_edit = null;
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = $connexion->prepare("SELECT * FROM categories_frais WHERE id = :id");
    $stmt->bindParam(':id', $edit_id);
    $stmt->execute();
    $categorie_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Catégories de Frais</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Catégories de Frais</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            
            
            <!-- Liste des catégories -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Liste des catégories de frais</h5>
                        
                        <?php if (empty($categories)): ?>
                            <div class="alert alert-info">
                                Aucune catégorie de frais n'a été définie.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover datatable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Désignation</th>
                                            <th>Compte</th>
                                            <th>Options</th>
                                            <th>Date création</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $categorie): ?>
                                            <tr>
                                                <td><?= $categorie['id'] ?></td>
                                                <td><?= htmlspecialchars($categorie['designation']) ?></td>
                                                <td><?= htmlspecialchars($categorie['compte_comptable'] ?? 'Non défini') ?></td>
                                                <td>
                                                    <span class="badge <?= $categorie['est_obligatoire'] ? 'bg-success' : 'bg-secondary' ?> me-1" title="Obligatoire">
                                                        <i class="bi bi-check-circle-fill"></i>
                                                    </span>
                                                    <span class="badge <?= $categorie['est_echelonnable'] ? 'bg-info' : 'bg-secondary' ?> me-1" title="Échelonnable">
                                                        <i class="bi bi-calendar3"></i>
                                                    </span>
                                                    <span class="badge <?= $categorie['est_remboursable'] ? 'bg-warning' : 'bg-secondary' ?>" title="Remboursable">
                                                        <i class="bi bi-arrow-return-left"></i>
                                                    </span>
                                                </td>
                                                <td><?= date('d/m/Y H:i', strtotime($categorie['date_creation'])) ?></td>
                                                <td>
                                                    <a href="?view=finance/categories_frais&edit_id=<?= $categorie['id'] ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger delete-categorie" 
                                                            data-id="<?= $categorie['id'] ?>" 
                                                            data-designation="<?= htmlspecialchars($categorie['designation']) ?>">
                                                        <i class="bi bi-trash"></i>
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
            </div>
            <!-- Formulaire d'ajout/modification -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><?= $categorie_edit ? 'Modifier une catégorie' : 'Ajouter une catégorie' ?></h5>
                        
                        <form action="controller/categories_frais_operations.php" method="POST">
                            <input type="hidden" name="action" value="<?= $categorie_edit ? 'modifier' : 'ajouter' ?>">
                            <?php if ($categorie_edit): ?>
                                <input type="hidden" name="id" value="<?= $categorie_edit['id'] ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="designation" name="designation" 
                                       value="<?= $categorie_edit ? htmlspecialchars($categorie_edit['designation']) : '' ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= $categorie_edit ? htmlspecialchars($categorie_edit['description']) : '' ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="compte_comptable" class="form-label">Compte comptable</label>
                                <input type="text" class="form-control" id="compte_comptable" name="compte_comptable" 
                                       value="<?= $categorie_edit ? htmlspecialchars($categorie_edit['compte_comptable']) : '' ?>">
                                <small class="text-muted">Numéro du compte dans le plan comptable</small>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="est_obligatoire" name="est_obligatoire" value="1" 
                                           <?= (!$categorie_edit || $categorie_edit['est_obligatoire']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="est_obligatoire">
                                        Frais obligatoire
                                    </label>
                                    <div class="form-text">Les frais obligatoires doivent être payés par tous les étudiants</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="est_echelonnable" name="est_echelonnable" value="1" 
                                           <?= ($categorie_edit && $categorie_edit['est_echelonnable']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="est_echelonnable">
                                        Peut être échelonné
                                    </label>
                                    <div class="form-text">Permet le paiement en plusieurs tranches</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="est_remboursable" name="est_remboursable" value="1" 
                                           <?= ($categorie_edit && $categorie_edit['est_remboursable']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="est_remboursable">
                                        Remboursable
                                    </label>
                                    <div class="form-text">Peut être remboursé dans certaines conditions</div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> <?= $categorie_edit ? 'Mettre à jour' : 'Enregistrer' ?>
                                </button>
                                <?php if ($categorie_edit): ?>
                                    <a href="?view=finance/categories_frais" class="btn btn-secondary">
                                        <i class="bi bi-plus-circle"></i> Nouvelle catégorie
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal de confirmation de suppression -->
<div class="modal fade" id="deleteCategorieModal" tabindex="-1" aria-labelledby="deleteCategorieModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/categories_frais_operations.php" method="POST">
                <input type="hidden" name="action" value="supprimer">
                <input type="hidden" name="id" id="delete_categorie_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteCategorieModalLabel">Supprimer la catégorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer la catégorie <span id="delete_categorie_name" class="fw-bold"></span>?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i> Attention: Cette action supprimera également tous les frais associés à cette catégorie et pourrait affecter les paiements déjà effectués.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Confirmer la suppression</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialisation de DataTable
    new DataTable('.datatable', {
    language: {
    "sProcessing": "Traitement en cours...",
        "sSearch": "Rechercher&nbsp;:",
        "sLengthMenu": "Afficher _MENU_ &eacute;l&eacute;ments",
        "sInfo": "Affichage de l'&eacute;l&eacute;ment _START_ &agrave; _END_ sur _TOTAL_ &eacute;l&eacute;ments",
            "sInfoEmpty": "Affichage de l'&eacute;l&eacute;ment 0 &agrave; 0 sur 0 &eacute;l&eacute;ment",
            "sInfoFiltered": "(filtr&eacute; de _MAX_ &eacute;l&eacute;ments au total)",
            "sInfoPostFix": "",
            "sLoadingRecords": "Chargement en cours...",
            "sZeroRecords": "Aucun &eacute;l&eacute;ment &agrave; afficher",
            "sEmptyTable": "Aucune donn&eacute;e disponible dans le tableau",
            "oPaginate": {
                "sFirst": "Premier",
                "sPrevious": "Pr&eacute;c&eacute;dent",
                "sNext": "Suivant",
                "sLast": "Dernier"
            },
            "oAria": {
                "sSortAscending": ": activer pour trier la colonne par ordre croissant",
                "sSortDescending": ": activer pour trier la colonne par ordre d&eacute;croissant"
            }
        },
        responsive: true,
        pageLength: 10
    });
    
    // Gestion de la suppression
    const deleteButtons = document.querySelectorAll('.delete-categorie');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const categorieId = this.getAttribute('data-id');
            const categorieDesignation = this.getAttribute('data-designation');
            
            document.getElementById('delete_categorie_id').value = categorieId;
            document.getElementById('delete_categorie_name').textContent = categorieDesignation;
            
            // Afficher la modal de confirmation
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteCategorieModal'));
            deleteModal.show();
        });
    });
});
</script>

<?php include "./views/include/footer.php"; ?>