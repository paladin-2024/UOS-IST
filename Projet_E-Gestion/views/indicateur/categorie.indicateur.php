<?php
include "./views/include/header.php";

$connexion = Connexion::getInstance()->getPDO();

$stmt = $connexion->prepare("SELECT * FROM categorie_indicateur ORDER BY \"nomCategorie\" ASC");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';
unset($_SESSION['message'], $_SESSION['messageType']);

$categorie_edit = null;
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = $connexion->prepare("SELECT * FROM categorie_indicateur WHERE \"idCategorie\" = :id");
    $stmt->bindParam(':id', $edit_id);
    $stmt->execute();
    $categorie_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Catégories d'indicateurs</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Indicateurs de Gestion</li>
                <li class="breadcrumb-item active">Catégories</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Liste des catégories d'indicateurs</h5>

                        <?php if (empty($categories)): ?>
                            <div class="alert alert-info">
                                Aucune catégorie d'indicateur n'a été définie.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover datatable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $categorie): ?>
                                            <tr>
                                                <td><?= $categorie['idCategorie'] ?></td>
                                                <td><?= htmlspecialchars($categorie['nomCategorie']) ?></td>
                                                <td>
                                                    <a href="?view=indicateur/categorie.indicateur&edit_id=<?= $categorie['idCategorie'] ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger delete-categorie"
                                                            data-id="<?= $categorie['idCategorie'] ?>"
                                                            data-nom="<?= htmlspecialchars($categorie['nomCategorie']) ?>">
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

            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><?= $categorie_edit ? 'Modifier une catégorie' : 'Ajouter une catégorie' ?></h5>

                        <form action="controller/save_categorie_indicateur.php" method="POST">
                            <input type="hidden" name="action" value="<?= $categorie_edit ? 'modifier' : 'ajouter' ?>">
                            <?php if ($categorie_edit): ?>
                                <input type="hidden" name="id" value="<?= $categorie_edit['idCategorie'] ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="nomCategorie" class="form-label">Nom de la catégorie <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nomCategorie" name="nomCategorie"
                                       value="<?= $categorie_edit ? htmlspecialchars($categorie_edit['nomCategorie']) : '' ?>" required>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> <?= $categorie_edit ? 'Mettre à jour' : 'Enregistrer' ?>
                                </button>
                                <?php if ($categorie_edit): ?>
                                    <a href="?view=indicateur/categorie.indicateur" class="btn btn-secondary">
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

<div class="modal fade" id="deleteCategorieModal" tabindex="-1" aria-labelledby="deleteCategorieModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/save_categorie_indicateur.php" method="POST">
                <input type="hidden" name="action" value="supprimer">
                <input type="hidden" name="id" id="delete_categorie_id">

                <div class="modal-header">
                    <h5 class="modal-title" id="deleteCategorieModalLabel">Supprimer la catégorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer la catégorie <span id="delete_categorie_nom" class="fw-bold"></span> ?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i> Les indicateurs associés à cette catégorie ne seront plus catégorisés.
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
    new DataTable('.datatable', { responsive: true, pageLength: 10 });

    document.querySelectorAll('.delete-categorie').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('delete_categorie_id').value = this.getAttribute('data-id');
            document.getElementById('delete_categorie_nom').textContent = this.getAttribute('data-nom');
            new bootstrap.Modal(document.getElementById('deleteCategorieModal')).show();
        });
    });
});
</script>

<?php include "./views/include/footer.php"; ?>
