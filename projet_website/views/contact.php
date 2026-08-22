<?php 
include "include/head.php";

// Initialisation des variables
$errors = [];
$success = false;
$formData = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'subject' => '',
    'message' => ''
];

// Traitement du formulaire lors de la soumission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Connexion::getInstance()->getPDO();
    
    // Récupération des données du formulaire
    $formData = [
        'name' => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'subject' => trim($_POST['subject'] ?? ''),
        'message' => trim($_POST['message'] ?? '')
    ];
    
    // Validation des champs
    if (empty($formData['name'])) {
        $errors[] = "Le nom est obligatoire.";
    }
    
    if (empty($formData['email'])) {
        $errors[] = "L'adresse email est obligatoire.";
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email n'est pas valide.";
    }
    
    if (empty($formData['message'])) {
        $errors[] = "Le message est obligatoire.";
    }
    
    // Récupération de l'adresse IP
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    
    // Si pas d'erreurs, enregistrer le message dans la base de données
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("INSERT INTO contact_submissions (name, email, subject, message, phone, ip_address, is_read, created_at) 
                                VALUES (:name, :email, :subject, :message, :phone, :ip_address, 0, NOW())");
            
            $stmt->bindParam(':name', $formData['name']);
            $stmt->bindParam(':email', $formData['email']);
            $stmt->bindParam(':subject', $formData['subject']);
            $stmt->bindParam(':message', $formData['message']);
            $stmt->bindParam(':phone', $formData['phone']);
            $stmt->bindParam(':ip_address', $ipAddress);
            $stmt->execute();
            
            // Message envoyé avec succès
            $success = true;
            
            // Réinitialisation du formulaire
            $formData = [
                'name' => '',
                'email' => '',
                'phone' => '',
                'subject' => '',
                'message' => ''
            ];
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de l'envoi du message. Veuillez réessayer plus tard.";
        }
    }
}

// Récupération des paramètres du site pour les coordonnées
$stmt = $db->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('contact_email', 'contact_phone', 'contact_address')");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!-- En-tête de la page avec titre -->
<section class="page-header bg-primary text-white py-5">
    <div class="container">
        <div class="row">
            <div class="col-md-8 mx-auto text-center">
                <h1 class="fw-bold">Contactez-nous</h1>
                <p class="lead">Nous sommes à votre écoute. N'hésitez pas à nous contacter pour toute question ou information.</p>
            </div>
        </div>
    </div>
</section>

<!-- Section principale avec le formulaire et les informations de contact -->
<section class="contact-section py-5">
    <div class="container">
        <?php if ($success): ?>
        <div class="row mb-5">
            <div class="col-md-8 mx-auto">
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <h4 class="alert-heading"><i class="fas fa-check-circle me-2"></i>Message envoyé avec succès!</h4>
                    <p>Nous avons bien reçu votre message et nous vous répondrons dans les plus brefs délais.</p>
                    <p class="mb-0">Merci de nous avoir contactés.</p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
        <div class="row mb-4">
            <div class="col-md-8 mx-auto">
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Erreur</h4>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Colonne du formulaire -->
            <div class="col-lg-8 mb-5 mb-lg-0">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h3 class="card-title">Envoyez-nous un message</h3>
                    </div>
                    <div class="card-body p-4">
                        <form action="" method="POST" id="contactForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Nom complet *</label>
                                    <input type="text" class="form-control" id="name" name="name" required 
                                           value="<?php echo htmlspecialchars($formData['name']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required
                                           value="<?php echo htmlspecialchars($formData['email']); ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                           value="<?php echo htmlspecialchars($formData['phone']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="subject" class="form-label">Sujet</label>
                                    <input type="text" class="form-control" id="subject" name="subject"
                                           value="<?php echo htmlspecialchars($formData['subject']); ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Message *</label>
                                <textarea class="form-control" id="message" name="message" rows="6" required><?php echo htmlspecialchars($formData['message']); ?></textarea>
                            </div>
                            <div class="form-text mb-3">
                                Les champs marqués d'un astérisque (*) sont obligatoires.
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Envoyer le message
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Colonne des informations de contact -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h3 class="card-title">Nos coordonnées</h3>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled contact-info">
                            <li class="d-flex mb-4">
                                <div class="icon-box bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">Adresse</h5>
                                    <p class="mb-0"><?php echo htmlspecialchars($settings['contact_address'] ?? 'Avenue de la Santé, Quartier Malepe, Beni, Nord-Kivu, RDC'); ?></p>
                                </div>
                            </li>
                            <li class="d-flex mb-4">
                                <div class="icon-box bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">Téléphone</h5>
                                    <p class="mb-0"><?php echo htmlspecialchars($settings['contact_phone'] ?? '+243 123 456 789'); ?></p>
                                </div>
                            </li>
                            <li class="d-flex mb-4">
                                <div class="icon-box bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">Email</h5>
                                    <p class="mb-0"><?php echo htmlspecialchars($settings['contact_email'] ?? 'contact@istmbeni.ac.cd'); ?></p>
                                </div>
                            </li>
                        </ul>
                        
                        <hr class="my-4">
                        
                        <h4 class="mb-3">Heures d'ouverture</h4>
                        <ul class="list-unstyled opening-hours">
                            <li class="d-flex justify-content-between">
                                <span>Lundi - Vendredi:</span>
                                <span>8h00 - 16h00</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span>Samedi:</span>
                                <span>8h00 - 12h00</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span>Dimanche:</span>
                                <span>Fermé</span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h3 class="card-title">Suivez-nous</h3>
                    </div>
                    <div class="card-body">
                        <div class="social-links d-flex justify-content-center gap-3">
                            <a href="#" class="social-link bg-primary text-white">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="social-link bg-info text-white">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="social-link bg-danger text-white">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="social-link bg-secondary text-white">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <a href="#" class="social-link bg-success text-white">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section carte -->
<section class="map-section py-5 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h3 class="text-center mb-4">Nous localiser</h3>
                <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                        <!-- Carte Google Maps (remplacer src par l'URL réelle) -->
                        <div class="map-container ratio ratio-21x9">
                            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3985.0251214946374!2d29.4613!3d0.4960!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMMKwMjknNDUuNiJOIDI5wrAyNyczNC43IkU!5e0!3m2!1sfr!2scd!4v1635954321077!5m2!1sfr!2scd" 
                                    allowfullscreen="" loading="lazy" class="w-100 border-0"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>



<!-- Call-to-Action Section -->
<section class="cta-section py-5 bg-primary text-white text-center">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 class="mb-4">Vous avez d'autres questions?</h2>
                <p class="lead mb-4">Notre équipe est disponible pour vous aider et répondre à toutes vos questions concernant nos programmes d'études.</p>
                <div class="d-flex flex-column flex-md-row justify-content-center gap-3">
                    <a href="tel:<?php echo htmlspecialchars(str_replace(' ', '', $settings['contact_phone'] ?? '+243123456789')); ?>" class="btn btn-lg btn-light">
                        <i class="fas fa-phone me-2"></i>Appelez-nous
                    </a>
                    <a href="mailto:<?php echo htmlspecialchars($settings['contact_email'] ?? 'contact@istmbeni.ac.cd'); ?>" class="btn btn-lg btn-outline-light">
                        <i class="fas fa-envelope me-2"></i>Envoyez-nous un email
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Styles spécifiques pour la page de contact -->
<style>
.icon-box {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
}

.contact-info li {
    margin-bottom: 1.5rem;
}

.opening-hours li {
    padding: 8px 0;
    border-bottom: 1px dashed #eee;
}

.opening-hours li:last-child {
    border-bottom: none;
}

.social-link {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.social-link:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.map-container {
    border-radius: 4px;
    overflow: hidden;
}

.accordion-button:not(.collapsed) {
    background-color: rgba(var(--primary-color-rgb), 0.1);
    color: var(--primary-color);
}

.accordion-button:focus {
    box-shadow: 0 0 0 0.25rem rgba(var(--primary-color-rgb), 0.25);
}

@media (max-width: 767.98px) {
    .contact-info li {
        margin-bottom: 1rem;
    }
    
    .icon-box {
        width: 35px;
        height: 35px;
        font-size: 0.9rem;
    }
}

/* Animation de formulaire */
.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.25rem rgba(var(--primary-color-rgb), 0.25);
}

#contactForm .form-control {
    transition: all 0.3s ease;
}

#contactForm .form-control:focus {
    transform: translateY(-2px);
}

.btn-primary {
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(var(--primary-color-rgb), 0.3);
}
</style>

<!-- Script pour la validation du formulaire -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.getElementById('contactForm');
    
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            let isValid = true;
            const name = document.getElementById('name');
            const email = document.getElementById('email');
            const message = document.getElementById('message');
            
            // Validation simple côté client
            if (name.value.trim() === '') {
                isValid = false;
                highlightField(name);
            } else {
                resetField(name);
            }
            
            if (email.value.trim() === '') {
                isValid = false;
                highlightField(email);
            } else if (!isValidEmail(email.value.trim())) {
                isValid = false;
                highlightField(email);
            } else {
                resetField(email);
            }
            
            if (message.value.trim() === '') {
                isValid = false;
                highlightField(message);
            } else {
                resetField(message);
            }
            
            if (!isValid) {
                e.preventDefault();
                // Scroll to first error
                const firstInvalidField = document.querySelector('.is-invalid');
                if (firstInvalidField) {
                    firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
        
        // Fonctions auxiliaires
        function highlightField(field) {
            field.classList.add('is-invalid');
            field.classList.remove('is-valid');
        }
        
        function resetField(field) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        }
        
        function isValidEmail(email) {
            const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(email);
        }
        
        // Animation au défilement
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('show');
                }
            });
        }, {
            threshold: 0.15
        });
        
        document.querySelectorAll('.card, .contact-info li, .accordion-item').forEach(el => {
            el.classList.add('fade-in-element');
            observer.observe(el);
        });
    }
});
</script>

<?php 
include "include/footer.php";
?>
