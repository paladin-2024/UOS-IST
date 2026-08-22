<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();

// Récupérer la configuration financière actuelle
$stmt = $connexion->query("SELECT * FROM config_finance WHERE est_actif = 1 LIMIT 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

// Si aucune configuration n'existe, on prépare un tableau vide pour éviter les erreurs
if (!$config) {
    $config = [
        'id' => null,
        'devise_principale' => 'USD',
        'devise_secondaire' => 'CDF',
        'taux_change' => 2000.000000,
        'date_mise_a_jour_taux' => date('Y-m-d H:i:s'),
        'annee_fiscale_debut' => null,
        'annee_fiscale_fin' => null,
        'format_facture' => 'INV-{YEAR}-{NUM}',
        'numero_facture_suivant' => 1,
        'logo_facture' => null,
        'signature_comptable' => null,
        'signature_finance' => null,
        'termes_paiement' => null,
        'est_actif' => 1
    ];
}

// Récupérer les messages d'alerte s'ils existent
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';

// Effacer les messages après les avoir récupérés
unset($_SESSION['message']);
unset($_SESSION['messageType']);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Configuration Financière</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Configuration</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Paramètres Financiers Globaux</h5>
                        
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                                <?= $message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form action="controller/update_config_finance.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="id" value="<?= $config['id'] ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Paramètres de Devise</h6>
                                    
                                    <div class="mb-3">
                                        <label for="devise_principale" class="form-label">Devise principale</label>
                                        <input type="text" class="form-control" id="devise_principale" name="devise_principale" value="<?= htmlspecialchars($config['devise_principale']) ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="devise_secondaire" class="form-label">Devise secondaire</label>
                                        <input type="text" class="form-control" id="devise_secondaire" name="devise_secondaire" value="<?= htmlspecialchars($config['devise_secondaire']) ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="taux_change" class="form-label">Taux de change</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="taux_change" name="taux_change" step="0.000001" value="<?= htmlspecialchars($config['taux_change']) ?>" required>
                                            <span class="input-group-text">1 <?= htmlspecialchars($config['devise_principale']) ?> = ? <?= htmlspecialchars($config['devise_secondaire']) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="date_mise_a_jour_taux" class="form-label">Date de mise à jour du taux</label>
                                        <input type="datetime-local" class="form-control" id="date_mise_a_jour_taux" name="date_mise_a_jour_taux" value="<?= date('Y-m-d\TH:i', strtotime($config['date_mise_a_jour_taux'])) ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Année Fiscale</h6>
                                    
                                    <div class="mb-3">
                                        <label for="annee_fiscale_debut" class="form-label">Début de l'année fiscale</label>
                                        <input type="date" class="form-control" id="annee_fiscale_debut" name="annee_fiscale_debut" value="<?= $config['annee_fiscale_debut'] ? date('Y-m-d', strtotime($config['annee_fiscale_debut'])) : '' ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="annee_fiscale_fin" class="form-label">Fin de l'année fiscale</label>
                                        <input type="date" class="form-control" id="annee_fiscale_fin" name="annee_fiscale_fin" value="<?= $config['annee_fiscale_fin'] ? date('Y-m-d', strtotime($config['annee_fiscale_fin'])) : '' ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Facturation</h6>
                                    
                                    <div class="mb-3">
                                        <label for="format_facture" class="form-label">Format des numéros de facture</label>
                                        <input type="text" class="form-control" id="format_facture" name="format_facture" value="<?= htmlspecialchars($config['format_facture']) ?>">
                                        <small class="form-text text-muted">Utiliser {YEAR} pour l'année et {NUM} pour le numéro séquentiel.</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="numero_facture_suivant" class="form-label">Prochain numéro de facture</label>
                                        <input type="number" class="form-control" id="numero_facture_suivant" name="numero_facture_suivant" value="<?= intval($config['numero_facture_suivant']) ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Pièces Jointes</h6>
                                    
                                    <div class="mb-3">
                                        <label for="logo_facture" class="form-label">Logo pour factures</label>
                                        <?php if (!empty($config['logo_facture'])): ?>
                                            <div class="mb-2">
                                                <img src="uploads/finance/<?= htmlspecialchars($config['logo_facture']) ?>" alt="Logo facture" class="img-thumbnail" style="max-height: 100px;">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="logo_facture" name="logo_facture" accept="image/*">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="signature_comptable" class="form-label">Signature du comptable</label>
                                        <?php if (!empty($config['signature_comptable'])): ?>
                                            <div class="mb-2">
                                                <img src="uploads/finance/<?= htmlspecialchars($config['signature_comptable']) ?>" alt="Signature comptable" class="img-thumbnail" style="max-height: 100px;">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="signature_comptable" name="signature_comptable" accept="image/*">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="signature_finance" class="form-label">Signature du responsable financier</label>
                                        <?php if (!empty($config['signature_finance'])): ?>
                                            <div class="mb-2">
                                                <img src="uploads/finance/<?= htmlspecialchars($config['signature_finance']) ?>" alt="Signature finance" class="img-thumbnail" style="max-height: 100px;">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="signature_finance" name="signature_finance" accept="image/*">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-12">
                                    <h6 class="text-primary mb-3">Termes & Conditions</h6>
                                    
                                    <div class="mb-3">
                                        <label for="termes_paiement" class="form-label">Termes de paiement</label>
                                        <textarea class="form-control" id="termes_paiement" name="termes_paiement" rows="4"><?= htmlspecialchars($config['termes_paiement']) ?></textarea>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="est_actif" name="est_actif" value="1" <?= $config['est_actif'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="est_actif">
                                            Configuration active
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Enregistrer la configuration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Format preview for invoice number
    const formatInput = document.getElementById('format_facture');
    const numeroInput = document.getElementById('numero_facture_suivant');
    
    if (formatInput && numeroInput) {
        const updatePreview = function() {
            const format = formatInput.value;
            const numero = numeroInput.value;
            const year = new Date().getFullYear();
            
            let preview = format
                .replace('{YEAR}', year)
                .replace('{NUM}', numero.padStart(3, '0'));
                
            // You could display this preview somewhere if needed
            console.log('Format de facture: ' + preview);
        };
        
        formatInput.addEventListener('input', updatePreview);
        numeroInput.addEventListener('input', updatePreview);
    }
});
</script>

<?php include "./views/include/footer.php"; ?>