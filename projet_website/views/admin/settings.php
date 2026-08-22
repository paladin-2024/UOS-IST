<?php
// Définir la page actuelle pour le menu actif
$currentPage = 'settings';

// Récupérer les paramètres du site
$db = Connexion::getInstance()->getPDO();

// Traitement de la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    try {
        // Début de la transaction
        $db->beginTransaction();
        
        // Parcourir tous les paramètres soumis
        foreach ($_POST as $key => $value) {
            // Ignorer le champ submit
            if ($key === 'update_settings') continue;
            
            // Mettre à jour chaque paramètre
            $stmt = $db->prepare("UPDATE site_settings SET setting_value = :value WHERE setting_key = :key");
            $stmt->bindParam(':value', $value);
            $stmt->bindParam(':key', $key);
            $stmt->execute();
        }
        
        // Valider la transaction
        $db->commit();
        
        // Message de succès
        $_SESSION['success_message'] = "Les paramètres du site ont été mis à jour avec succès.";
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollBack();
        $_SESSION['error_message'] = "Erreur lors de la mise à jour des paramètres : " . $e->getMessage();
    }
    
    // Redirection pour éviter la resoumission du formulaire
    header('Location: settings');
    exit;
}

// Récupérer tous les paramètres
$stmt = $db->query("SELECT * FROM site_settings ORDER BY setting_key");
$settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organiser les paramètres par catégories
$generalSettings = [];
$contactSettings = [];
$socialSettings = [];
$appearanceSettings = [];
$otherSettings = [];

foreach ($settings as $setting) {
    $key = $setting['setting_key'];
    
    if (strpos($key, 'site_') === 0) {
        $generalSettings[] = $setting;
    } else if (strpos($key, 'contact_') === 0) {
        $contactSettings[] = $setting;
    } else if (strpos($key, 'social_') === 0) {
        $socialSettings[] = $setting;
    } else if (in_array($key, ['primary_color', 'secondary_color', 'footer_text'])) {
        $appearanceSettings[] = $setting;
    } else {
        $otherSettings[] = $setting;
    }
}

// Inclure le header
include_once './views/admin/include/header.php';
?>

<!-- Contenu de la page de paramètres -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-cogs me-2"></i>Paramètres du site</h1>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <p class="text-muted">
            Configurez les paramètres généraux de votre site. Ces paramètres affectent le fonctionnement et l'apparence du site.
        </p>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="list-group" id="settings-tabs" role="tablist">
            <a class="list-group-item list-group-item-action active" id="general-tab" data-bs-toggle="list" href="#general" role="tab" aria-controls="general">
                <i class="fas fa-globe me-2"></i>Général
            </a>
            <a class="list-group-item list-group-item-action" id="contact-tab" data-bs-toggle="list" href="#contact" role="tab" aria-controls="contact">
                <i class="fas fa-address-card me-2"></i>Contact
            </a>
            <a class="list-group-item list-group-item-action" id="social-tab" data-bs-toggle="list" href="#social" role="tab" aria-controls="social">
                <i class="fas fa-share-alt me-2"></i>Réseaux sociaux
            </a>
            <a class="list-group-item list-group-item-action" id="appearance-tab" data-bs-toggle="list" href="#appearance" role="tab" aria-controls="appearance">
                <i class="fas fa-paint-brush me-2"></i>Apparence
            </a>
            <a class="list-group-item list-group-item-action" id="other-tab" data-bs-toggle="list" href="#other" role="tab" aria-controls="other">
                <i class="fas fa-wrench me-2"></i>Autres
            </a>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="tab-content" id="settings-tabContent">
                        <!-- Paramètres généraux -->
                        <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                            <h4 class="card-title mb-4">Paramètres généraux</h4>
                            
                            <?php foreach ($generalSettings as $setting): ?>
                                <div class="mb-3">
                                    <label for="<?php echo $setting['setting_key']; ?>" class="form-label">
                                        <?php echo htmlspecialchars($setting['setting_description']); ?>
                                    </label>
                                    <?php if ($setting['setting_key'] === 'site_description'): ?>
                                        <textarea class="form-control" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>" rows="3"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                    <?php else: ?>
                                        <input type="text" class="form-control" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Paramètres de contact -->
                        <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">
                            <h4 class="card-title mb-4">Informations de contact</h4>
                            
                            <?php foreach ($contactSettings as $setting): ?>
                                <div class="mb-3">
                                    <label for="<?php echo $setting['setting_key']; ?>" class="form-label">
                                        <?php echo htmlspecialchars($setting['setting_description']); ?>
                                    </label>
                                    <?php if ($setting['setting_key'] === 'contact_address'): ?>
                                        <textarea class="form-control" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>" rows="3"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                    <?php else: ?>
                                        <input type="text" class="form-control" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Réseaux sociaux -->
                        <div class="tab-pane fade" id="social" role="tabpanel" aria-labelledby="social-tab">
                            <h4 class="card-title mb-4">Réseaux sociaux</h4>
                            
                            <?php foreach ($socialSettings as $setting): ?>
                                <div class="mb-3">
                                    <label for="<?php echo $setting['setting_key']; ?>" class="form-label">
                                        <?php echo htmlspecialchars($setting['setting_description']); ?>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <?php 
                                            $icon = '';
                                            if (strpos($setting['setting_key'], 'facebook') !== false) $icon = 'fab fa-facebook';
                                            else if (strpos($setting['setting_key'], 'twitter') !== false) $icon = 'fab fa-twitter';
                                            else if (strpos($setting['setting_key'], 'instagram') !== false) $icon = 'fab fa-instagram';
                                            else if (strpos($setting['setting_key'], 'linkedin') !== false) $icon = 'fab fa-linkedin';
                                            else $icon = 'fas fa-link';
                                            ?>
                                            <i class="<?php echo $icon; ?>"></i>
                                        </span>
                                        <input type="text" class="form-control" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Apparence -->
                        <div class="tab-pane fade" id="appearance" role="tabpanel" aria-labelledby="appearance-tab">
                            <h4 class="card-title mb-4">Apparence</h4>
                            
                            <?php foreach ($appearanceSettings as $setting): ?>
                                <div class="mb-3">
                                    <label for="<?php echo $setting['setting_key']; ?>" class="form-label">
                                        <?php echo htmlspecialchars($setting['setting_description']); ?>
                                    </label>
                                    
                                    <?php if (in_array($setting['setting_key'], ['primary_color', 'secondary_color'])): ?>
                                        <div class="input-group">
                                            <input type="color" class="form-control form-control-color" id="<?php echo $setting['setting_key']; ?>_picker" value="<?php echo htmlspecialchars($setting['setting_value']); ?>" onchange="document.getElementById('<?php echo $setting['setting_key']; ?>').value = this.value">
                                            <input type="text" class="form-control" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                        </div>
                                    <?php elseif ($setting['setting_key'] === 'footer_text'): ?>
                                        <textarea class="form-control" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>" rows="3"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                    <?php else: ?>
                                        <input type="text" class="form-control" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Autres paramètres -->
                        <div class="tab-pane fade" id="other" role="tabpanel" aria-labelledby="other-tab">
                            <h4 class="card-title mb-4">Autres paramètres</h4>
                            
                            <?php foreach ($otherSettings as $setting): ?>
                                <div class="mb-3">
                                    <label for="<?php echo $setting['setting_key']; ?>" class="form-label">
                                        <?php echo htmlspecialchars($setting['setting_description']); ?>
                                    </label>
                                    
                                    <?php if ($setting['setting_key'] === 'google_analytics'): ?>
                                        <textarea class="form-control" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>" rows="3" placeholder="Code Google Analytics"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                        <div class="form-text">Collez ici votre code de suivi Google Analytics.</div>
                                    <?php else: ?>
                                        <input type="text" class="form-control" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mt-4 text-end">
                        <button type="submit" name="update_settings" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Script pour prévisualiser les changements de couleur
document.addEventListener('DOMContentLoaded', function() {
    // Activer les tooltips Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Gestionnaire pour les champs de couleur
    const colorPickers = document.querySelectorAll('input[type="color"]');
    colorPickers.forEach(picker => {
        picker.addEventListener('input', function() {
            const targetId = this.id.replace('_picker', '');
            document.getElementById(targetId).value = this.value;
        });
    });

    // Synchroniser les valeurs de texte et les color pickers
    const colorInputs = document.querySelectorAll('input[id="primary_color"], input[id="secondary_color"]');
    colorInputs.forEach(input => {
        input.addEventListener('input', function() {
            const pickerId = this.id + '_picker';
            document.getElementById(pickerId).value = this.value;
        });
    });
});
</script>

<!-- Prévisualisation des paramètres -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-eye me-2"></i>Prévisualisation</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>Cette section montre une prévisualisation de certains paramètres. Enregistrez vos modifications pour voir les changements appliqués sur le site.
                </div>
                
                <h6 class="mt-3">Informations du site</h6>
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars(getSetting('site_name', $settings)); ?></h5>
                        <p class="card-text"><?php echo htmlspecialchars(getSetting('site_description', $settings)); ?></p>
                    </div>
                </div>
                
                <h6 class="mt-3">Contact</h6>
                <div class="row">
                    <div class="col-md-6">
                        <ul class="list-group mb-3">
                            <li class="list-group-item"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars(getSetting('contact_email', $settings)); ?></li>
                            <li class="list-group-item"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars(getSetting('contact_phone', $settings)); ?></li>
                            <li class="list-group-item"><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars(getSetting('contact_address', $settings)); ?></li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Réseaux sociaux</h5>
                                <div class="d-flex gap-2 mt-2">
                                    <?php if(getSetting('social_facebook', $settings)): ?>
                                        <a href="<?php echo htmlspecialchars(getSetting('social_facebook', $settings)); ?>" class="btn btn-outline-primary" target="_blank"><i class="fab fa-facebook"></i></a>
                                    <?php endif; ?>
                                    
                                    <?php if(getSetting('social_twitter', $settings)): ?>
                                        <a href="<?php echo htmlspecialchars(getSetting('social_twitter', $settings)); ?>" class="btn btn-outline-info" target="_blank"><i class="fab fa-twitter"></i></a>
                                    <?php endif; ?>
                                    
                                    <?php if(getSetting('social_instagram', $settings)): ?>
                                        <a href="<?php echo htmlspecialchars(getSetting('social_instagram', $settings)); ?>" class="btn btn-outline-danger" target="_blank"><i class="fab fa-instagram"></i></a>
                                    <?php endif; ?>
                                    
                                    <?php if(getSetting('social_linkedin', $settings)): ?>
                                        <a href="<?php echo htmlspecialchars(getSetting('social_linkedin', $settings)); ?>" class="btn btn-outline-primary" target="_blank"><i class="fab fa-linkedin"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h6 class="mt-3">Couleurs du thème</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header" style="background-color: <?php echo htmlspecialchars(getSetting('primary_color', $settings)); ?>; color: white;">
                                Couleur primaire
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="color-sample me-3" style="width: 40px; height: 40px; background-color: <?php echo htmlspecialchars(getSetting('primary_color', $settings)); ?>; border-radius: 4px;"></div>
                                    <div><?php echo htmlspecialchars(getSetting('primary_color', $settings)); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header" style="background-color: <?php echo htmlspecialchars(getSetting('secondary_color', $settings)); ?>; color: white;">
                                Couleur secondaire
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="color-sample me-3" style="width: 40px; height: 40px; background-color: <?php echo htmlspecialchars(getSetting('secondary_color', $settings)); ?>; border-radius: 4px;"></div>
                                    <div><?php echo htmlspecialchars(getSetting('secondary_color', $settings)); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h6 class="mt-3">Pied de page</h6>
                <div class="card">
                    <div class="card-body bg-light">
                        <?php echo htmlspecialchars(getSetting('footer_text', $settings)); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Fonction utilitaire pour récupérer un paramètre spécifique
function getSetting($key, $settingsArray) {
    foreach ($settingsArray as $setting) {
        if ($setting['setting_key'] === $key) {
            return $setting['setting_value'];
        }
    }
    return '';
}

// Inclure le footer
include_once 'views/admin/include/footer.php';
?>
