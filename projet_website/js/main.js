// Navigation mobile
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    const mainMenu = document.querySelector('.main-menu');
    
    // Toggle menu on mobile
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            mainMenu.classList.toggle('active');
            // Change l'icône du menu
            const icon = menuToggle.querySelector('i');
            if (icon.classList.contains('fa-bars')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    }
    
    // Ajouter le style mobile pour le menu quand il est actif
    const style = document.createElement('style');
    style.innerHTML = `
        @media (max-width: 992px) {
            .main-menu.active {
                display: block;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background-color: white;
                box-shadow: 0 5px 10px rgba(0,0,0,0.1);
                padding: 20px;
                z-index: 99;
            }
            
            .main-menu.active li {
                margin: 0;
                padding: 10px 0;
                border-bottom: 1px solid #eee;
            }
            
            .main-menu.active li:last-child {
                border-bottom: none;
            }
        }
    `;
    document.head.appendChild(style);
    
    // Animation pour les nombres dans la section "Chiffres Clés"
    const numberElements = document.querySelectorAll('.number');
    
    function animateNumber(element) {
        const target = parseInt(element.textContent.replace(/[^0-9]/g, ''));
        const duration = 2000; // durée de l'animation en ms
        const step = 30; // ms entre chaque étape
        let current = 0;
        const increment = target / (duration / step);
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                element.textContent = target.toString() + (element.textContent.includes('+') ? '+' : '');
                clearInterval(timer);
            } else {
                element.textContent = Math.floor(current).toString() + (element.textContent.includes('+') ? '+' : '');
            }
        }, step);
    }
    
    // Observer pour détecter quand la section est visible
    if('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Déclencher l'animation de nombres
                    numberElements.forEach(animateNumber);
                    // Arrêter d'observer une fois que l'animation a commencé
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        
        // Observer la section chiffres clés
        const keyNumbersSection = document.querySelector('.key-numbers');
        if (keyNumbersSection) {
            observer.observe(keyNumbersSection);
        }
    }
    
    // Animation de défilement fluide pour les ancres
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Animation pour les éléments lors du défilement
    function fadeInOnScroll() {
        const elements = document.querySelectorAll('.news-item, .program-category, .number-item');
        
        elements.forEach(element => {
            const rect = element.getBoundingClientRect();
            const windowHeight = window.innerHeight;
            
            if (rect.top < windowHeight - 100) {
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }
        });
    }
    
    // Ajouter le style initial pour l'animation
    const fadeStyle = document.createElement('style');
    fadeStyle.innerHTML = `
        .news-item, .program-category, .number-item {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
    `;
    document.head.appendChild(fadeStyle);
    
    // Écouter l'événement de défilement pour l'animation
    window.addEventListener('scroll', fadeInOnScroll);
    // Déclencher une fois au chargement de la page
    fadeInOnScroll();
});
