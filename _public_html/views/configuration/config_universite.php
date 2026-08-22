<?php
include "./views/include/header.php";
$universite = new Universite();

// Récupérer la configuration actuelle
$config = $universite->getConfigurationUniversite();
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Configuration de l'Établissement</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Configuration</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body pt-3">

                        <!-- Onglets de navigation -->
                        <ul class="nav nav-tabs nav-tabs-bordered" id="configTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#tab-general" type="button" role="tab">
                                    <i class="bi bi-building me-1"></i> Général
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#tab-contact" type="button" role="tab">
                                    <i class="bi bi-envelope me-1"></i> Contact
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="responsables-tab" data-bs-toggle="tab" data-bs-target="#tab-responsables" type="button" role="tab">
                                    <i class="bi bi-people me-1"></i> Responsables
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="academique-tab" data-bs-toggle="tab" data-bs-target="#tab-academique" type="button" role="tab">
                                    <i class="bi bi-calculator me-1"></i> Académique
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="flexpay-tab" data-bs-toggle="tab" data-bs-target="#tab-flexpay" type="button" role="tab">
                                    <i class="bi bi-credit-card me-1"></i> FlexPay
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="fichiers-tab" data-bs-toggle="tab" data-bs-target="#tab-fichiers" type="button" role="tab">
                                    <i class="bi bi-image me-1"></i> Logo & Signatures
                                </button>
                            </li>
                        </ul>

                        <form id="configForm" method="POST" action="controller/save_config_universite.php" enctype="multipart/form-data">
                            <div class="tab-content pt-4" id="configTabsContent">

                                <!-- Onglet Général -->
                                <div class="tab-pane fade show active" id="tab-general" role="tabpanel">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Type d'Établissement</label>
                                            <select name="type_etablissement" class="form-select" required>
                                                <option value="Institut Supérieur" <?= ($config['type_etablissement'] ?? '') == 'Institut Supérieur' ? 'selected' : '' ?>>Institut Supérieur</option>
                                                <option value="Université" <?= ($config['type_etablissement'] ?? '') == 'Université' ? 'selected' : '' ?>>Université</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Sigle</label>
                                            <input type="text" class="form-control" name="sigle" value="<?= htmlspecialchars($config['sigle'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label">Nom Complet</label>
                                            <input type="text" class="form-control" name="nom" value="<?= htmlspecialchars($config['nom'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label">Nom de l'Application</label>
                                            <input type="text" class="form-control" name="nom_application" value="<?= htmlspecialchars($config['nom_application'] ?? 'E-GESTION') ?>" required>
                                            <small class="text-muted">Ce nom sera utilisé dans le titre de l'application et autres endroits</small>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label">Ministère de Tutelle</label>
                                            <input type="text" class="form-control" name="ministere_tutelle" value="<?= htmlspecialchars($config['ministere_tutelle'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <!-- Onglet Contact -->
                                <div class="tab-pane fade" id="tab-contact" role="tabpanel">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Adresse</label>
                                            <textarea class="form-control" name="adresse" rows="2"><?= htmlspecialchars($config['adresse'] ?? '') ?></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Ville</label>
                                            <input type="text" class="form-control" name="ville" value="<?= htmlspecialchars($config['ville'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Téléphone</label>
                                            <input type="tel" class="form-control" name="telephone" value="<?= htmlspecialchars($config['telephone'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($config['email'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Site Web</label>
                                            <input type="url" class="form-control" name="site_web" value="<?= htmlspecialchars($config['site_web'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Onglet Responsables -->
                                <div class="tab-pane fade" id="tab-responsables" role="tabpanel">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Nom du Responsable</label>
                                            <input type="text" class="form-control" name="nom_responsable" value="<?= htmlspecialchars($config['nom_responsable'] ?? '') ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Titre du Responsable</label>
                                            <input type="text" class="form-control" name="titre_responsable" value="<?= htmlspecialchars($config['titre_responsable'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Responsable Académique</label>
                                            <input type="text" class="form-control" name="nom_responsable_academique" value="<?= htmlspecialchars($config['nom_responsable_academique'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Titre Responsable Académique</label>
                                            <input type="text" class="form-control" name="titre_responsable_academique" value="<?= htmlspecialchars($config['titre_responsable_academique'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Secrétaire Général Académique</label>
                                            <input type="text" class="form-control" name="nom_secretaire_general" value="<?= htmlspecialchars($config['nom_secretaire_general'] ?? '') ?>" placeholder="Nom du Secrétaire Général Académique">
                                            <small class="text-muted">Ce nom apparaîtra sur les relevés de notes</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Titre du Secrétaire Général</label>
                                            <input type="text" class="form-control" name="titre_secretaire_general" value="<?= htmlspecialchars($config['titre_secretaire_general'] ?? 'Secrétaire Général Académique') ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Onglet Académique -->
                                <div class="tab-pane fade" id="tab-academique" role="tabpanel">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Heures par Crédit</label>
                                            <input type="number" class="form-control" name="credit_heure" value="<?= htmlspecialchars($config['credit_heure'] ?? '25') ?>" min="1" required>
                                            <small class="text-muted">Nombre d'heures équivalant à 1 crédit (par défaut: 25)</small>
                                        </div>
                                    </div>
                                    <h6 class="text-muted mt-4 mb-3">
                                        <i class="bi bi-sliders me-2"></i>Pondérations par Défaut
                                    </h6>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Pondération Contrôle Continu (CC)</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" name="ponderation_cc_defaut" 
                                                       value="<?= htmlspecialchars($config['ponderation_cc_defaut'] ?? '0.4') ?>" 
                                                       min="0" max="1" step="0.01" required>
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <small class="text-muted">Valeur décimale entre 0 et 1</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Pondération Examen (EX)</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" name="ponderation_ex_defaut" 
                                                       value="<?= htmlspecialchars($config['ponderation_ex_defaut'] ?? '0.6') ?>" 
                                                       min="0" max="1" step="0.01" required>
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <small class="text-muted">Valeur décimale entre 0 et 1</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Onglet FlexPay -->
                                <div class="tab-pane fade" id="tab-flexpay" role="tabpanel">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Activer FlexPay</label>
                                            <select name="flexpay_actif" class="form-select">
                                                <option value="0" <?= ($config['flexpay_actif'] ?? 0) == 0 ? 'selected' : '' ?>>Désactivé</option>
                                                <option value="1" <?= ($config['flexpay_actif'] ?? 0) == 1 ? 'selected' : '' ?>>Activé</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Merchant ID</label>
                                            <input type="text" class="form-control" name="flexpay_merchant" value="<?= htmlspecialchars($config['flexpay_merchant'] ?? '') ?>" placeholder="Identifiant du marchand FlexPay">
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label">Token d'authentification (Bearer)</label>
                                            <input type="password" class="form-control" name="flexpay_token" value="<?= htmlspecialchars($config['flexpay_token'] ?? '') ?>" placeholder="Token Bearer FlexPay">
                                            <small class="text-muted">Ce token est stocké de manière sécurisée et ne sera pas visible après enregistrement</small>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-8">
                                            <label class="form-label">URL de Callback</label>
                                            <input type="url" class="form-control" name="flexpay_callback_url" value="<?= htmlspecialchars($config['flexpay_callback_url'] ?? '') ?>" placeholder="https://votre-domaine.com/e_gestion/controller/flexpay_callback.php">
                                            <small class="text-muted">URL publiquement accessible par FlexPay pour les notifications de paiement</small>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Timeout (secondes)</label>
                                            <input type="number" class="form-control" name="flexpay_timeout" value="<?= htmlspecialchars($config['flexpay_timeout'] ?? '30') ?>" min="5" max="120">
                                        </div>
                                    </div>
                                    <div class="row mb-3" id="flexpay-endpoints" style="<?= ($config['flexpay_actif'] ?? 0) == 0 ? 'display:none;' : '' ?>">
                                        <div class="col-md-12 mb-2">
                                            <h6 class="text-muted"><i class="bi bi-link-45deg me-2"></i>Endpoints API</h6>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Mobile Money</label>
                                            <input type="url" class="form-control form-control-sm" name="flexpay_endpoint_mobile_money" value="<?= htmlspecialchars($config['flexpay_endpoint_mobile_money'] ?? 'https://backend.flexpay.cd/api/rest/v1/paymentService') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Carte Bancaire</label>
                                            <input type="url" class="form-control form-control-sm" name="flexpay_endpoint_card_payment" value="<?= htmlspecialchars($config['flexpay_endpoint_card_payment'] ?? 'https://backend.flexpay.cd/api/rest/v1.1/pay') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Vérification Transaction</label>
                                            <input type="url" class="form-control form-control-sm" name="flexpay_endpoint_check_transaction" value="<?= htmlspecialchars($config['flexpay_endpoint_check_transaction'] ?? 'https://backend.flexpay.cd/api/rest/v1/check/') ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Onglet Logo & Signatures -->
                                <div class="tab-pane fade" id="tab-fichiers" role="tabpanel">
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Logo</label>
                                            <input type="file" class="form-control" name="logo" accept="image/*">
                                            <?php if (!empty($config['logo'])): ?>
                                                <img src="<?= $config['logo'] ?>" alt="Logo actuel" class="mt-2" style="max-height: 100px">
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Signature du Responsable</label>
                                            <input type="file" class="form-control" name="signature_responsable" accept="image/*">
                                            <?php if (!empty($config['signature_responsable'])): ?>
                                                <img src="<?= $config['signature_responsable'] ?>" alt="Signature actuelle" class="mt-2" style="max-height: 100px">
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Cachet</label>
                                            <input type="file" class="form-control" name="cachet" accept="image/*">
                                            <?php if (!empty($config['cachet'])): ?>
                                                <img src="<?= $config['cachet'] ?>" alt="Cachet actuel" class="mt-2" style="max-height: 100px">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
// Toggle FlexPay endpoints visibility
document.querySelector('[name="flexpay_actif"]').addEventListener('change', function() {
    document.getElementById('flexpay-endpoints').style.display = this.value == '1' ? '' : 'none';
});

document.getElementById('configForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    Swal.fire({
        title: 'Enregistrement en cours...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('controller/save_config_universite.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Erreur réseau');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: data.message || 'Configuration enregistrée avec succès'
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: data.message || 'Une erreur est survenue lors de l\'enregistrement'
            });
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Une erreur est survenue lors de la communication avec le serveur'
        });
    });
});
</script>

<?php include "./views/include/footer_file.php"; ?>
