<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération de l'ID du client
$clientId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($clientId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Client non trouvé'
        }).then(() => {
            window.location.href = '../clients/clients.list';
        });
    </script>";
    exit;
}

// Récupération des détails du client
$query = "SELECT * FROM client WHERE id_client = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $clientId, PDO::PARAM_INT);
$stmt->execute();
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Client non trouvé'
        }).then(() => {
            window.location.href = '../clients/clients.list';
        });
    </script>";
    exit;
}

// Récupérer les comptes comptables pour les clients
$queryComptes = "SELECT * FROM compte_comptable 
                WHERE classe_compte = 4 
                AND (type_compte = 'Actif' OR type_compte = 'Passif') 
                ORDER BY numero_compte";
$stmtComptes = $db->prepare($queryComptes);
$stmtComptes->execute();
$comptes = $stmtComptes->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>MODIFIER UN CLIENT</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Ventes</li>
                <li class="breadcrumb-item"><a href="clients/clients.list">Clients</a></li>
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
                            Modifier le client: <?= htmlspecialchars($client['nom_client']) ?>
                            <div class="float-end">
                                <a href="clients/clients.list" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Retour
                                </a>
                            </div>
                        </h5>

                        <form action="controller/update_client.php" method="POST" class="row g-3">
                            <input type="hidden" name="id_client" value="<?= $client['id_client'] ?>">

                            <div class="col-md-6">
                                <label for="code_client" class="form-label">Code client <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="code_client" name="code_client" value="<?= htmlspecialchars($client['code_client']) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="type_client" class="form-label">Type de client <span class="text-danger">*</span></label>
                                <select class="form-select" id="type_client" name="type_client" required>
                                    <option value="Particulier" <?= $client['type_client'] == 'Particulier' ? 'selected' : '' ?>>Particulier</option>
                                    <option value="Entreprise" <?= $client['type_client'] == 'Entreprise' ? 'selected' : '' ?>>Entreprise</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="nom_client" class="form-label">Nom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nom_client" name="nom_client" value="<?= htmlspecialchars($client['nom_client']) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="telephone" name="telephone" value="<?= htmlspecialchars($client['telephone'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($client['email'] ?? '') ?>">
                            </div>

                            <div class="col-md-6 enterprise-fields">
                                <label for="nif" class="form-label">NIF</label>
                                <input type="text" class="form-control" id="nif" name="nif" value="<?= htmlspecialchars($client['nif'] ?? '') ?>">
                            </div>

                            <div class="col-md-6 enterprise-fields">
                                <label for="rccm" class="form-label">RCCM</label>
                                <input type="text" class="form-control" id="rccm" name="rccm" value="<?= htmlspecialchars($client['rccm'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="id_compte_comptable" class="form-label">Compte comptable <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_compte_comptable" name="id_compte_comptable" required>
                                    <?php foreach ($comptes as $compte): ?>
                                        <option value="<?= $compte['id_compte'] ?>" <?= $client['id_compte_comptable'] == $compte['id_compte'] ? 'selected' : '' ?>>
                                            <?= $compte['numero_compte'] ?> - <?= htmlspecialchars($compte['intitule_compte']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label for="adresse" class="form-label">Adresse</label>
                                <textarea class="form-control" id="adresse" name="adresse" rows="3"><?= htmlspecialchars($client['adresse'] ?? '') ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <label for="plafond_credit" class="form-label">Plafond de crédit</label>
                                <input type="number" class="form-control" id="plafond_credit" name="plafond_credit" step="0.01" value="<?= number_format($client['plafond_credit'], 2, '.', '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="delai_paiement" class="form-label">Délai de paiement (jours)</label>
                                <input type="number" class="form-control" id="delai_paiement" name="delai_paiement" value="<?= $client['delai_paiement'] ?>">
                            </div>

                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="actif" name="actif" value="1" <?= $client['actif'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="actif">
                                        Client actif
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Enregistrer les modifications
                                </button>
                                <a href="clients/clients.list" class="btn btn-secondary">
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

<script>
    // Gestion des champs spécifiques aux entreprises
    $(document).ready(function() {
        toggleEnterpriseFields();
        
        $('#type_client').on('change', function() {
            toggleEnterpriseFields();
        });
        
        function toggleEnterpriseFields() {
            if ($('#type_client').val() === 'Entreprise') {
                $('.enterprise-fields').show();
            } else {
                $('.enterprise-fields').hide();
            }
        }
    });
</script>

<?php include "./views/include/footer.php"; ?>
