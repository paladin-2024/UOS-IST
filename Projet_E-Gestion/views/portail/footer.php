<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row gy-4">
            <div class="col-md-4" data-aos="fade-up">
                <h4 class="footer-heading">À propos de ScienceHub</h4>
                <p>ScienceHub est une plateforme dédiée à la gestion et au partage des travaux scientifiques, permettant aux chercheurs d'accéder à une vaste collection de ressources académiques.</p>
                <div class="mt-3">
                    <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
            
            <div class="col-md-2" data-aos="fade-up" data-aos-delay="100">
                <h4 class="footer-heading">Liens rapides</h4>
                <a href="index" class="footer-link">Accueil</a>
                <a href="recherche_avancee" class="footer-link">Recherche avancée</a>
                <a href="login" class="footer-link">Soumettre un travail</a>
                <a href="statistique" class="footer-link">Statistiques</a>
            </div>
            
            <div class="col-md-3" data-aos="fade-up" data-aos-delay="200">
                <h4 class="footer-heading">Ressources</h4>
                <a href="#" class="footer-link">Guide d'utilisation</a>
                <a href="#" class="footer-link">FAQ</a>
                <a href="#" class="footer-link">Normes bibliographiques</a>
                <a href="#" class="footer-link">Propriété intellectuelle</a>
            </div>
            
            <div class="col-md-3" data-aos="fade-up" data-aos-delay="300">
                <h4 class="footer-heading">Contact</h4>
                <?php if (!empty($config['email'])): ?>
                    <p><i class="fas fa-envelope me-2"></i> <?= htmlspecialchars($config['email']) ?></p>
                <?php endif; ?>
                
                <?php if (!empty($config['telephone'])): ?>
                    <p><i class="fas fa-phone me-2"></i> <?= htmlspecialchars($config['telephone']) ?></p>
                <?php endif; ?>
                
                <?php if (!empty($config['adresse']) || !empty($config['ville'])): ?>
                    <p>
                        <i class="fas fa-map-marker-alt me-2"></i>
                        <?= htmlspecialchars($config['adresse']) ?>
                        <?php if (!empty($config['ville'])): ?>
                            <?= htmlspecialchars($config['ville']) ?>
                        <?php endif; ?>
                        <?php if (!empty($config['pays'])): ?>
                            , <?= htmlspecialchars($config['pays']) ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                
                <?php if (!empty($config['site_web'])): ?>
                    <p><i class="fas fa-globe me-2"></i> <a href="<?= htmlspecialchars($config['site_web']) ?>" target="_blank" class="text-white"><?= htmlspecialchars($config['site_web']) ?></a></p>
                <?php endif; ?>
            </div>
        </div>
        
        <hr class="mt-4 mb-3 border-secondary">
        
        <div class="row">
            <div class="col-md-6">
                <p class="mb-0">&copy; <?= date('Y') ?> ScienceHub. Tous droits réservés.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="#" class="footer-link d-inline-block me-3">Mentions légales</a>
                <a href="#" class="footer-link d-inline-block me-3">Politique de confidentialité</a>
                <a href="#" class="footer-link d-inline-block">Cookies</a>
            </div>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>

<!-- Script personnalisé -->
<script>
    // Initialisation des animations AOS
    AOS.init({
        once: true,
        duration: 800,
        offset: 100
    });
    
    // Gestion du preloader
    window.addEventListener('load', function() {
        const preloader = document.getElementById('preloader');
        setTimeout(() => {
            preloader.style.opacity = '0';
            setTimeout(() => {
                preloader.style.display = 'none';
            }, 500);
        }, 800);
    });
    
    // Animation des compteurs de statistiques
    const statNumbers = document.querySelectorAll('.stat-number');
    
    function animateCounter(el) {
        const target = parseInt(el.getAttribute('data-count'));
        const duration = 2000; // 2 secondes
        const step = Math.ceil(target / (duration / 20)); // Incrément par étape
        let current = 0;
        
        const timer = setInterval(() => {
            current += step;
            if (current >= target) {
                el.textContent = new Intl.NumberFormat().format(target);
                clearInterval(timer);
            } else {
                el.textContent = new Intl.NumberFormat().format(current);
            }
        }, 20);
    }
    
    // Observer pour démarrer l'animation quand les éléments sont visibles
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });
    
    statNumbers.forEach(number => {
        observer.observe(number);
    });
    
    // Amélioration de l'expérience utilisateur pour la barre de recherche
    const searchInput = document.querySelector('.search-bar .form-control');
    if (searchInput) {
        searchInput.addEventListener('focus', function() {
            this.parentElement.classList.add('shadow-sm');
        });
        
        searchInput.addEventListener('blur', function() {
            this.parentElement.classList.remove('shadow-sm');
        });
    }
    
    // Effet de survol pour les cartes
    const cards = document.querySelectorAll('.featured-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.querySelector('.featured-img-container').style.height = '190px';
        });
        
        card.addEventListener('mouseleave', function() {
            this.querySelector('.featured-img-container').style.height = '180px';
        });
    });
    
    // Gestion du banner EU
    const euBanner = document.querySelector('.eu-banner [role="button"]');
    if (euBanner) {
        euBanner.addEventListener('click', function() {
            // Ici vous pouvez ajouter une logique pour afficher plus d'informations
            alert('Cette plateforme est développée selon les normes académiques universitaires.');
        });
    }
</script>
</body>
</html>
