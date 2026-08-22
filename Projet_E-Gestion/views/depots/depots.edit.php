<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération de l'ID du dépôt
$depotId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($depotId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Dépôt non trouvé'
        }).then(() => {
            window.location.href = '../depots/depots.list';
        });
    </script>";
    exit;
}

// Récupération des détails du dépôt
$query = "SELECT * FROM depot WHERE id_depot = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $depotId, PDO::PARAM_INT);
$stmt->execute();
$depot = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$depot) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Dépôt non trouvé'
        }).then(() => {
            window.location.href = '../depots/depots.list';
        });
    </script>";
    exit;
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>MODIFIER UN DÉPÔT</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item"><a href="depots/depots.list">Dépôts</a></li>
                <li class="breadcrumb-item active">Modifier</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Modifier le dépôt: <?= htmlspecialchars($depot['libelle_depot']) ?>
                            <div class="float-end">
                                <a href="depots/depots.list" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Retour
                                </a>
                            </div>
                        </h5>

                        <form action="controller/update_depot.php" method="POST" class="row g-3">
                        <input type="hidden" name="id_depot" value="<?= $depot['id_depot'] ?>">

<div class="col-md-6">
    <label for="code_depot" class="form-label">Code <span class="text-danger">*</span></label>
    <input type="text" class="form-control" id="code_depot" name="code_depot" value="<?= htmlspecialchars($depot['code_depot']) ?>" required>
</div>

<div class="col-md-6">
    <label for="libelle_depot" class="form-label">Libellé <span class="text-danger">*</span></label>
    <input type="text" class="form-control" id="libelle_depot" name="libelle_depot" value="<?= htmlspecialchars($depot['libelle_depot']) ?>" required>
</div>

<div class="col-md-12">
    <label for="adresse" class="form-label">Adresse</label>
    <textarea class="form-control" id="adresse" name="adresse" rows="3"><?= htmlspecialchars($depot['adresse'] ?? '') ?></textarea>
</div>

<div class="col-md-6">
    <label for="responsable" class="form-label">Responsable</label>
    <input type="text" class="form-control" id="responsable" name="responsable" value="<?= htmlspecialchars($depot['responsable'] ?? '') ?>">
</div>

<div class="col-md-6">
    <label class="form-label">&nbsp;</label>
    <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" id="actif" name="actif" value="1" <?= $depot['actif'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="actif">
            Dépôt actif
        </label>
    </div>
</div>

<div class="col-12 mt-3">
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-save"></i> Enregistrer les modifications
    </button>
    <a href="depots/depots.list" class="btn btn-secondary">
        <i class="bi bi-x-circle"></i> Annuler
    </a>
</div>
</form>
</div>
</div>
</div>
</div>
</section>
</main>

<?php include "./views/include/footer.php"; ?>
