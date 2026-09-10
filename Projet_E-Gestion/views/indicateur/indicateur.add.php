<?php
include "./views/include/header.php";

$connexion = Connexion::getInstance()->getPDO();

$stmt = $connexion->prepare("SELECT * FROM categorie_indicateur ORDER BY \"nomCategorie\" ASC");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $connexion->prepare("
    SELECT i.*, c.\"nomCategorie\"
    FROM indicateur i
    LEFT JOIN categorie_indicateur c ON c.\"idCategorie\" = i.\"Idcategorie\"
    ORDER BY i.nom ASC
");
$stmt->execute();
$indicateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';
unset($_SESSION['message'], $_SESSION['messageType']);

$indicateur_edit = null;
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    foreach ($indicateurs as $ind) {
        if ((int) $ind['idIndicateur'] === $edit_id) {
            $indicateur_edit = $ind;
            break;
        }
    }
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des indicateurs</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Indicateurs de Gestion</li>
                <li class="breadcrumb-item active">Indicateurs</li>
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

        <?php if (empty($categories)): ?>
            <div class="alert alert-warning">
                Aucune catégorie d'indicateur n'existe encore. <a href="?view=indicateur/categorie.indicateur">Créez-en une</a> avant d'ajouter un indicateur.
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Liste des indicateurs</h5>

                        <?php if (empty($indicateurs)): ?>
                            <div class="alert alert-info">Aucun indicateur n'a été défini.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover datatable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom</th>
                                            <th>Catégorie</th>
                                            <th>Description</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($indicateurs as $indicateur): ?>
                                            <tr>
                                                <td><?= $indicateur['idIndicateur'] ?></td>
                                                <td><?= htmlspecialchars($indicateur['nom']) ?></td>
                                                <td><?= htmlspecialchars($indicateur['nomCategorie'] ?? 'Non catégorisé') ?></td>
                                                <td><?= htmlspecialchars($indicateur['description'] ?? '') ?></td>
                                                <td>
                                                    <a href="?view=indicateur/indicateur.add&edit_id=<?= $indicateur['idIndicateur'] ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger delete-indicateur"
                                                            data-id="<?= $indicateur['idIndicateur'] ?>"
                                                            data-nom="<?= htmlspecialchars($indicateur['nom']) ?>">
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
                        <h5 class="card-title"><?= $indicateur_edit ? 'Modifier un indicateur' : 'Ajouter un indicateur' ?></h5>

                        <form action="controller/save_indicateur.php" method="POST">
                            <input type="hidden" name="action" value="<?= $indicateur_edit ? 'modifier' : 'ajouter' ?>">
                            <?php if ($indicateur_edit): ?>
                                <input type="hidden" name="id" value="<?= $indicateur_edit['idIndicateur'] ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="nom" class="form-label">Nom de l'indicateur <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nom" name="nom"
                                       value="<?= $indicateur_edit ? htmlspecialchars($indicateur_edit['nom']) : '' ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="Idcategorie" class="form-label">Catégorie</label>
                                <select class="form-select" id="Idcategorie" name="Idcategorie">
                                    <option value="">-- Aucune --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['idCategorie'] ?>"
                                            <?= ($indicateur_edit && $indicateur_edit['Idcategorie'] == $cat['idCategorie']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['nomCategorie']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= $indicateur_edit ? htmlspecialchars($indicateur_edit['description'] ?? '') : '' ?></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> <?= $indicateur_edit ? 'Mettre à jour' : 'Enregistrer' ?>
                                </button>
                                <?php if ($indicateur_edit): ?>
                                    <a href="?view=indicateur/indicateur.add" class="btn btn-secondary">
                                        <i class="bi bi-plus-circle"></i> Nouvel indicateur
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

<div class="modal fade" id="deleteIndicateurModal" tabindex="-1" aria-labelledby="deleteIndicateurModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/save_indicateur.php" method="POST">
                <input type="hidden" name="action" value="supprimer">
                <input type="hidden" name="id" id="delete_indicateur_id">

                <div class="modal-header">
                    <h5 class="modal-title" id="deleteIndicateurModalLabel">Supprimer l'indicateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer l'indicateur <span id="delete_indicateur_nom" class="fw-bold"></span> ?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i> Toutes les valeurs enregistrées pour cet indicateur seront également supprimées.
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

    document.querySelectorAll('.delete-indicateur').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('delete_indicateur_id').value = this.getAttribute('data-id');
            document.getElementById('delete_indicateur_nom').textContent = this.getAttribute('data-nom');
            new bootstrap.Modal(document.getElementById('deleteIndicateurModal')).show();
        });
    });
});
</script>

<?php include "./views/include/footer.php"; ?>
