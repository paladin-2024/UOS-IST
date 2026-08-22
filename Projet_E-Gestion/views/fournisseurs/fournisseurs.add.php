<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupérer les comptes comptables pour les fournisseurs
$queryComptes = "SELECT * FROM compte_comptable 
                WHERE classe_compte = 4 
                AND (type_compte = 'Actif' OR type_compte = 'Passif') 
                ORDER BY numero_compte";
$stmtComptes = $db->prepare($queryComptes);
$stmtComptes->execute();
$comptes = $stmtComptes->fetchAll(PDO::FETCH_ASSOC);

// Générer un code fournisseur unique
$queryLastCode = "SELECT code_fournisseur FROM fournisseur ORDER BY id_fournisseur DESC LIMIT 1";
$stmtLastCode = $db->prepare($queryLastCode);
$stmtLastCode->execute();
$lastCode = $stmtLastCode->fetchColumn();

if ($lastCode) {
    $numericPart = intval(substr($lastCode, 3));
    $newCode = 'FRN' . str_pad($numericPart + 1, 4, '0', STR_PAD_LEFT);
} else {
    $newCode = 'FRN0001';
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>AJOUTER UN FOURNISSEUR</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Achats</li>
                <li class="breadcrumb-item"><a href="fournisseurs/fournisseurs.list">Fournisseurs</a></li>
                <li class="breadcrumb-item active">Ajouter</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Informations du fournisseur
                            <div class="float-end">
                                <a href="fournisseurs/fournisseurs.list" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Retour
                                </a>
                            </div>
                        </h5>

                        <form action="controller/create_fournisseur.php" method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label for="code_fournisseur" class="form-label">Code fournisseur <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="code_fournisseur" name="code_fournisseur" value="<?= $newCode ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="nom_fournisseur" class="form-label">Nom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nom_fournisseur" name="nom_fournisseur" required>
                            </div>

                            <div class="col-md-6">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="telephone" name="telephone">
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>

                            <div class="col-md-6">
                                <label for="nif" class="form-label">NIF</label>
                                <input type="text" class="form-control" id="nif" name="nif">
                            </div>

                            <div class="col-md-6">
                                <label for="rccm" class="form-label">RCCM</label>
                                <input type="text" class="form-control" id="rccm" name="rccm">
                            </div>

                            <div class="col-md-6">
                                <label for="id_compte_comptable" class="form-label">Compte comptable <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_compte_comptable" name="id_compte_comptable" required>
                                    <option value="">Sélectionner un compte</option>
                                    <?php foreach ($comptes as $compte): ?>
                                        <option value="<?= $compte['id_compte'] ?>">
                                            <?= $compte['numero_compte'] ?> - <?= htmlspecialchars($compte['intitule_compte']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                            <label for="delai_paiement" class="form-label">Délai de paiement (jours)</label>
                                <input type="number" class="form-control" id="delai_paiement" name="delai_paiement" value="0" min="0">
                            </div>

                            <div class="col-md-12">
                                <label for="adresse" class="form-label">Adresse</label>
                                <textarea class="form-control" id="adresse" name="adresse" rows="3"></textarea>
                            </div>

                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="actif" name="actif" checked>
                                    <label class="form-check-label" for="actif">
                                        Actif
                                    </label>
                                </div>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Enregistrer
                                </button>
                                <button type="reset" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Annuler
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include "./views/include/footer.php"; ?>
