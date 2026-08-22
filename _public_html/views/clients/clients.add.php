<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

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
        <h1>AJOUTER UN CLIENT</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Ventes</li>
                <li class="breadcrumb-item"><a href="clients/clients.list">Clients</a></li>
                <li class="breadcrumb-item active">Ajouter</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations du client</h5>

                        <form action="controller/create_client.php" method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label for="code_client" class="form-label">Code client <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="code_client" name="code_client" required>
                            </div>

                            <div class="col-md-6">
                                <label for="type_client" class="form-label">Type de client <span class="text-danger">*</span></label>
                                <select class="form-select" id="type_client" name="type_client" required>
                                    <option value="">Sélectionner un type</option>
                                    <option value="Particulier">Particulier</option>
                                    <option value="Entreprise">Entreprise</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="nom_client" class="form-label">Nom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nom_client" name="nom_client" required>
                            </div>

                            <div class="col-md-6">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="telephone" name="telephone">
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>

                            <div class="col-md-6 enterprise-fields">
                                <label for="nif" class="form-label">NIF</label>
                                <input type="text" class="form-control" id="nif" name="nif">
                            </div>

                            <div class="col-md-6 enterprise-fields">
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

                            <div class="col-md-12">
                                <label for="adresse" class="form-label">Adresse</label>
                                <textarea class="form-control" id="adresse" name="adresse" rows="3"></textarea>
                            </div>

                            <div class="col-md-6">
                                <label for="plafond_credit" class="form-label">Plafond de crédit</label>
                                <input type="number" class="form-control" id="plafond_credit" name="plafond_credit" step="0.01" value="0.00">
                            </div>

                            <div class="col-md-6">
                                <label for="delai_paiement" class="form-label">Délai de paiement (jours)</label>
                                <input type="number" class="form-control" id="delai_paiement" name="delai_paiement" value="0">
                            </div>

                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="actif" name="actif" value="1" checked>
                                    <label class="form-check-label" for="actif">
                                        Client actif
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Enregistrer
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
                $('#nif, #rccm').val('');
            }
        }
    });
</script>

<?php include "./views/include/footer.php"; ?>
