<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération de l'ID du fournisseur
$fournisseurId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($fournisseurId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Fournisseur non trouvé'
        }).then(() => {
            window.location.href = '../fournisseurs/fournisseurs.list';
        });
    </script>";
    exit;
}

// Récupération des détails du fournisseur
$query = "SELECT * FROM fournisseur WHERE id_fournisseur = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $fournisseurId, PDO::PARAM_INT);
$stmt->execute();
$fournisseur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fournisseur) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Fournisseur non trouvé'
        }).then(() => {
            window.location.href = '../fournisseurs/fournisseurs.list';
        });
    </script>";
    exit;
}

// Récupération des comptes comptables pour le formulaire
$queryComptes = "SELECT * FROM compte_comptable ORDER BY numero_compte";
$stmtComptes = $db->prepare($queryComptes);
$stmtComptes->execute();
$comptes = $stmtComptes->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>MODIFIER UN FOURNISSEUR</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Achats</li>
                <li class="breadcrumb-item"><a href="fournisseurs/fournisseurs.list">Fournisseurs</a></li>
                <li class="breadcrumb-item active">Modifier</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations du fournisseur</h5>

                        <form class="row g-3" action="controller/update_fournisseur.php" method="POST">
                            <input type="hidden" name="id_fournisseur" value="<?= $fournisseur['id_fournisseur'] ?>">
                            
                            <div class="col-md-4">
                                <label for="code_fournisseur" class="form-label">Code fournisseur *</label>
                                <input type="text" class="form-control" id="code_fournisseur" name="code_fournisseur" value="<?= htmlspecialchars($fournisseur['code_fournisseur']) ?>" required readonly>
                                <small class="text-muted">Le code ne peut pas être modifié</small>
                            </div>
                            
                            <div class="col-md-8">
                                <label for="nom_fournisseur" class="form-label">Nom du fournisseur *</label>
                                <input type="text" class="form-control" id="nom_fournisseur" name="nom_fournisseur" value="<?= htmlspecialchars($fournisseur['nom_fournisseur']) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input type="text" class="form-control" id="telephone" name="telephone" value="<?= htmlspecialchars($fournisseur['telephone'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($fournisseur['email'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="nif" class="form-label">NIF</label>
                                <input type="text" class="form-control" id="nif" name="nif" value="<?= htmlspecialchars($fournisseur['nif'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="rccm" class="form-label">RCCM</label>
                                <input type="text" class="form-control" id="rccm" name="rccm" value="<?= htmlspecialchars($fournisseur['rccm'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="id_compte_comptable" class="form-label">Compte comptable *</label>
                                <select class="form-select" id="id_compte_comptable" name="id_compte_comptable" required>
                                    <option value="">Sélectionner un compte</option>
                                    <?php foreach ($comptes as $compte): ?>
                                        <option value="<?= $compte['id_compte'] ?>" <?= $fournisseur['id_compte_comptable'] == $compte['id_compte'] ? 'selected' : '' ?>>
                                            <?= $compte['numero_compte'] ?> - <?= htmlspecialchars($compte['intitule_compte']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="delai_paiement" class="form-label">Délai de paiement (jours)</label>
                                <input type="number" class="form-control" id="delai_paiement" name="delai_paiement" value="<?= $fournisseur['delai_paiement'] ?>" min="0">
                            </div>

                            <div class="col-md-12">
                                <label for="adresse" class="form-label">Adresse</label>
                                <textarea class="form-control" id="adresse" name="adresse" rows="3"><?= htmlspecialchars($fournisseur['adresse'] ?? '') ?></textarea>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="actif" name="actif" <?= $fournisseur['actif'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="actif">
                                        Fournisseur actif
                                    </label>
                                </div>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">Enregistrer</button>
                                <a href="fournisseurs/fournisseurs.view&id=<?= $fournisseurId ?>" class="btn btn-secondary">Annuler</a>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include "./views/include/footer.php"; ?>
