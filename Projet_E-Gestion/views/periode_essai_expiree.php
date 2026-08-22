<?php
// Inclure les ressources du head.php
require_once 'include/head.php';
?>

<style>
    body {
        background: linear-gradient(135deg, 
            <?= $verificationEssai['status'] === 'suspended' ? '#ffc107 0%, #fd7e14 100%' : '#4154f1 0%, #6f42c1 100%' ?>);
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        padding: 20px 0;
    }

    .trial-page-container {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .trial-expired-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 25px;
        box-shadow: 0 25px 50px rgba(0,0,0,0.15);
        padding: 3rem;
        text-align: center;
        max-width: 800px;
        width: 100%;
        position: relative;
        overflow: hidden;
        animation: slideUp 0.8s ease-out;
    }

    .trial-expired-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(90deg, 
            <?= $verificationEssai['status'] === 'suspended' ? '#ffc107, #fd7e14' : '#4154f1, #6f42c1' ?>);
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .trial-icon {
        font-size: 5rem;
        color: <?= $verificationEssai['status'] === 'suspended' ? '#ffc107' : '#dc3545' ?>;
        margin-bottom: 1.5rem;
        animation: pulse 2s infinite;
        text-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    .trial-title {
        color: #2c3e50;
        font-weight: 700;
        margin-bottom: 1.5rem;
        font-size: 2.5rem;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .trial-subtitle {
        color: #7f8c8d;
        font-size: 1.2rem;
        margin-bottom: 2rem;
        line-height: 1.6;
    }

    .trial-details {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 15px;
        padding: 2rem;
        margin: 2rem 0;
        border: 1px solid rgba(0,0,0,0.05);
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
    }

    .trial-details h4 {
        color: #495057;
        margin-bottom: 1.5rem;
        font-weight: 600;
    }

    .info-item {
        background: white;
        padding: 1rem;
        border-radius: 10px;
        margin-bottom: 1rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        transition: transform 0.3s ease;
    }

    .info-item:hover {
        transform: translateY(-2px);
    }

    .info-label {
        font-weight: 600;
        color: #6c757d;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .info-value {
        font-size: 1.1rem;
        color: #2c3e50;
        margin-top: 0.25rem;
    }

    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 25px;
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-suspended {
        background: linear-gradient(135deg, #ffc107, #fd7e14);
        color: white;
    }

    .status-expired {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
    }

    .features-section {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border-radius: 15px;
        padding: 2rem;
        margin: 2rem 0;
        border: 1px solid rgba(0,0,0,0.05);
    }

    .features-title {
        color: #495057;
        margin-bottom: 1.5rem;
        font-weight: 600;
    }

    .feature-item {
        display: flex;
        align-items: center;
        padding: 1rem;
        margin-bottom: 0.5rem;
        background: white;
        border-radius: 10px;
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
    }

    .feature-item:hover {
        border-left-color: <?= $verificationEssai['status'] === 'suspended' ? '#ffc107' : '#4154f1' ?>;
        transform: translateX(5px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .feature-icon {
        font-size: 1.5rem;
        margin-right: 1rem;
        color: #28a745;
    }

    .contact-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
        margin: 2rem 0;
    }

    .contact-btn {
        background: linear-gradient(135deg, 
            <?= $verificationEssai['status'] === 'suspended' ? '#ffc107 0%, #fd7e14 100%' : '#4154f1 0%, #6f42c1 100%' ?>);
        border: none;
        padding: 12px 30px;
        border-radius: 50px;
        color: white;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }

    .contact-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        color: white;
    }

    .alert-custom {
        border: none;
        border-radius: 15px;
        padding: 1.5rem;
        margin: 2rem 0;
        box-shadow: 0 4px 15px rgba(255, 193, 7, 0.2);
    }

    .footer-note {
        background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
        border-radius: 15px;
        padding: 1.5rem;
        margin-top: 2rem;
        color: #6c757d;
        font-size: 0.95rem;
        line-height: 1.6;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .trial-expired-card {
            padding: 2rem 1.5rem;
            margin: 10px;
        }
        
        .trial-title {
            font-size: 2rem;
        }
        
        .trial-icon {
            font-size: 4rem;
        }
        
        .contact-buttons {
            flex-direction: column;
            align-items: center;
        }
        
        .contact-btn {
            width: 100%;
            max-width: 300px;
            justify-content: center;
        }
        
        .info-item {
            text-align: center;
        }
    }

    @media (max-width: 576px) {
        .trial-expired-card {
            padding: 1.5rem 1rem;
        }
        
        .trial-details,
        .features-section {
            padding: 1.5rem;
        }
    }

    /* Animation pour les éléments */
    .animate-item {
        opacity: 0;
        animation: fadeInUp 0.6s ease-out forwards;
    }

    .animate-item:nth-child(2) { animation-delay: 0.1s; }
    .animate-item:nth-child(3) { animation-delay: 0.2s; }
    .animate-item:nth-child(4) { animation-delay: 0.3s; }
    .animate-item:nth-child(5) { animation-delay: 0.4s; }
    .animate-item:nth-child(6) { animation-delay: 0.5s; }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<body>
    <div class="trial-page-container">
        <div class="trial-expired-card">
            <!-- Icône principale -->
            <div class="trial-icon animate-item">
                <?php if ($verificationEssai['status'] === 'suspended'): ?>
                    <i class="bi bi-pause-circle-fill"></i>
                <?php else: ?>
                    <i class="bi bi-hourglass-bottom"></i>
                <?php endif; ?>
            </div>
            
            <!-- Titre principal -->
            <h1 class="trial-title animate-item">
                <?= $verificationEssai['status'] === 'suspended' ? 'Période d\'essai suspendue' : 'Période d\'essai expirée' ?>
            </h1>
            
            <!-- Sous-titre -->
            <div class="trial-subtitle animate-item">
                <?php if ($verificationEssai['status'] === 'suspended'): ?>
                    Votre accès à <strong>OPT SOLUTION ADMINISTRATION</strong> a été temporairement suspendu.<br>
                    Notre équipe support est là pour vous aider à résoudre cette situation.
                <?php else: ?>
                    Votre période d'essai pour <strong>OPT SOLUTION ADMINISTRATION</strong> s'est terminée le 
                    <strong><?= date('d/m/Y', strtotime($verificationEssai['date_fin'])) ?></strong>.<br>
                    Continuez à profiter de nos services en nous contactant.
                <?php endif; ?>
            </div>
            
            <!-- Détails de l'essai -->
            <div class="trial-details animate-item">
                <h4>
                    <i class="bi bi-info-circle-fill text-primary me-2"></i>
                    <?= $verificationEssai['status'] === 'suspended' ? 'Informations de suspension' : 'Détails de votre période d\'essai' ?>
                </h4>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="info-item">
                            <div class="info-label">Client</div>
                            <div class="info-value"><?= htmlspecialchars($verificationEssai['data']['client_nom']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <div class="info-label">Connexions effectuées</div>
                            <div class="info-value"><?= $verificationEssai['data']['nombre_connexions'] ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <div class="info-label">Date de fin</div>
                            <div class="info-value"><?= date('d/m/Y', strtotime($verificationEssai['date_fin'])) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <div class="info-label">Statut</div>
                            <div class="info-value">
                                <span class="status-badge <?= $verificationEssai['status'] === 'suspended' ? 'status-suspended' : 'status-expired' ?>">
                                    <?= $verificationEssai['status'] === 'suspended' ? 'Suspendu' : 'Expiré' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
                        <!-- Alerte pour suspension -->
            <?php if ($verificationEssai['status'] === 'suspended'): ?>
                <div class="alert alert-warning alert-custom animate-item" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Suspension temporaire</strong><br>
                    Votre accès a été temporairement suspendu. Cette suspension peut être levée à tout moment par notre équipe support.
                </div>
            <?php endif; ?>
            
            <!-- Section des fonctionnalités -->
            <div class="features-section animate-item">
                <h5 class="features-title">
                    <i class="bi bi-star-fill text-warning me-2"></i>
                    <?= $verificationEssai['status'] === 'suspended' ? 'Reprenez où vous vous êtes arrêté' : 'Découvrez nos fonctionnalités avancées' ?>
                </h5>
                
                <div class="feature-item animate-item">
                    <i class="bi bi-calculator feature-icon"></i>
                    <div>
                        <strong>Gestion financière complète</strong>
                        <small class="d-block text-muted">Comptabilité, facturation et suivi budgétaire</small>
                    </div>
                </div>
                
                <div class="feature-item animate-item">
                    <i class="bi bi-people feature-icon"></i>
                    <div>
                        <strong>Gestion des ressources humaines</strong>
                        <small class="d-block text-muted">Personnel, paie et gestion des présences</small>
                    </div>
                </div>
                
                <div class="feature-item animate-item">
                    <i class="bi bi-cash-register feature-icon"></i>
                    <div>
                        <strong>Système de caisse intégré</strong>
                        <small class="d-block text-muted">Point de vente et gestion des transactions</small>
                    </div>
                </div>
                
                <div class="feature-item animate-item">
                    <i class="bi bi-graph-up feature-icon"></i>
                    <div>
                        <strong>Rapports et statistiques</strong>
                        <small class="d-block text-muted">Tableaux de bord et analyses détaillées</small>
                    </div>
                </div>
                
                <div class="feature-item animate-item">
                    <i class="bi bi-headset feature-icon"></i>
                    <div>
                        <strong>Support technique 24/7</strong>
                        <small class="d-block text-muted">Assistance dédiée et formation continue</small>
                    </div>
                </div>
            </div>
            
            
            
            <!-- Note de bas de page -->
            <div class="footer-note animate-item">
                <i class="bi bi-info-circle me-2"></i>
                <?php if ($verificationEssai['status'] === 'suspended'): ?>
                    <strong>Réactivation rapide :</strong> Contactez immédiatement notre équipe support pour réactiver votre période d'essai. 
                    Nous sommes disponibles pour vous aider à résoudre cette situation dans les plus brefs délais.
                <?php else: ?>
                    <strong>Continuez l'aventure :</strong> Ne perdez pas vos données ! Contactez notre équipe pour discuter des 
                    options d'abonnement et continuer à utiliser OPT SOLUTION sans interruption.
                <?php endif; ?>
            </div>
            
            <!-- Informations additionnelles -->
            <div class="row mt-4">
                <div class="col-md-4 text-center animate-item">
                    <div class="stat-circle" style="background: linear-gradient(135deg, #28a745, #20c997);">
                        <i class="bi bi-shield-check text-white"></i>
                    </div>
                    <h6>Sécurisé</h6>
                    <small class="text-muted">Vos données sont protégées</small>
                </div>
                <div class="col-md-4 text-center animate-item">
                    <div class="stat-circle" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                        <i class="bi bi-cloud-check text-white"></i>
                    </div>
                    <h6>Cloud</h6>
                    <small class="text-muted">Accessible partout</small>
                </div>
                <div class="col-md-4 text-center animate-item">
                    <div class="stat-circle" style="background: linear-gradient(135deg, #ffc107, #e0a800);">
                        <i class="bi bi-award text-white"></i>
                    </div>
                    <h6>Certifié</h6>
                    <small class="text-muted">Solution reconnue</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts spécifiques -->
    <script>
        // Animation d'entrée progressive
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter la classe d'animation aux éléments
            const animateItems = document.querySelectorAll('.animate-item');
            animateItems.forEach((item, index) => {
                setTimeout(() => {
                    item.style.animationDelay = (index * 0.1) + 's';
                }, 100);
            });
        });

        // Gestion des effets hover sur les feature items
        document.querySelectorAll('.feature-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(10px) scale(1.02)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0) scale(1)';
            });
        });

        // Notification automatique après 5 minutes
        setTimeout(function() {
            const isSupended = <?= $verificationEssai['status'] === 'suspended' ? 'true' : 'false' ?>;
            const message = isSupended 
                ? 'Votre période d\'essai est suspendue. Souhaitez-vous contacter le support maintenant ?' 
                : 'Souhaitez-vous être redirigé vers notre page de contact pour renouveler votre abonnement ?';
            
            Swal.fire({
                title: isSupended ? 'Suspension active' : 'Période d\'essai expirée',
                text: message,
                icon: isSupended ? 'warning' : 'info',
                showCancelButton: true,
                confirmButtonText: 'Contacter le support',
                cancelButtonText: 'Plus tard',
                confirmButtonColor: isSupended ? '#ffc107' : '#4154f1',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'mailto:support@optsolution.com?subject=' + 
                        encodeURIComponent(isSupended ? 'Réactivation période d\'essai' : 'Renouvellement abonnement') +
                        '&body=' + encodeURIComponent('Bonjour,\n\nJe souhaite obtenir des informations concernant ' + 
                        (isSupended ? 'la réactivation de ma période d\'essai.' : 'le renouvellement de mon abonnement.') + 
                        '\n\nCordialement.');
                }
            });
        }, 300000); // 5 minutes

        // Effet de particules en arrière-plan (optionnel)
        function createParticles() {
            const container = document.querySelector('.trial-page-container');
            
            for (let i = 0; i < 20; i++) {
                const particle = document.createElement('div');
                particle.style.cssText = `
                    position: absolute;
                    width: 4px;
                    height: 4px;
                    background: rgba(255, 255, 255, 0.3);
                    border-radius: 50%;
                    pointer-events: none;
                    animation: float ${3 + Math.random() * 4}s ease-in-out infinite;
                    left: ${Math.random() * 100}%;
                    top: ${Math.random() * 100}%;
                    animation-delay: ${Math.random() * 2}s;
                `;
                container.appendChild(particle);
            }
        }

        // Ajouter l'animation CSS pour les particules
        const style = document.createElement('style');
        style.textContent = `
            @keyframes float {
                0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 0.7; }
                50% { transform: translateY(-20px) rotate(180deg); opacity: 0.3; }
            }
        `;
        document.head.appendChild(style);

        // Créer les particules après le chargement
        setTimeout(createParticles, 1000);

        // Effet de typing pour le titre (optionnel)
        function typeWriter(element, text, speed = 100) {
            let i = 0;
            element.innerHTML = '';
            
            function type() {
                if (i < text.length) {
                    element.innerHTML += text.charAt(i);
                    i++;
                    setTimeout(type, speed);
                }
            }
            type();
        }

        // Animation de compteur pour les connexions
        function animateCounter() {
            const counterElement = document.querySelector('.info-value');
            if (counterElement) {
                const target = parseInt(<?= $verificationEssai['data']['nombre_connexions'] ?>);
                let current = 0;
                const increment = target / 50;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    counterElement.textContent = Math.floor(current);
                }, 50);
            }
        }

        // Démarrer les animations après le chargement
        setTimeout(() => {
            animateCounter();
        }, 2000);
    </script>

    <!-- CSS additionnel pour les animations avancées -->
    <style>
        .trial-expired-card {
            position: relative;
            overflow: visible;
        }

        .trial-expired-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
            pointer-events: none;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .contact-btn {
            position: relative;
            overflow: hidden;
        }

        .contact-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .contact-btn:hover::before {
            left: 100%;
        }

        /* Responsive amélioré */
        @media (max-width: 480px) {
            .trial-title {
                font-size: 1.8rem;
            }
            
            .trial-subtitle {
                font-size: 1rem;
            }
            
            .trial-icon {
                font-size: 3.5rem;
            }
            
            .feature-item {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem 1rem;
            }
            
            .feature-icon {
                margin-bottom: 0.5rem;
                margin-right: 0;
            }
        }
    </style>
    <?php include("include/footer_2.php"); ?>

</body>
</html>
