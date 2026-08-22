<script>
console.log('main_scripts.php loaded');

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM chargé - initialisation des composants UI pour pages simples');

    // Sidebar toggle with overlay
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('show');
        if (sidebarOverlay) sidebarOverlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('show');
        if (sidebarOverlay) sidebarOverlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar.classList.contains('show')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        // Close button inside sidebar
        const sidebarClose = document.getElementById('sidebarClose');
        if (sidebarClose) {
            sidebarClose.addEventListener('click', closeSidebar);
        }

        // Close sidebar when clicking overlay
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }

        // Close sidebar on outside click (mobile)
        document.addEventListener('click', function(e) {
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target) && window.innerWidth < 992) {
                closeSidebar();
            }
        });
    }

    // Gestion de la navigation pour les liens data-page (pour les pages avec tabs)
    document.querySelectorAll('[data-page]').forEach(link => {
        link.addEventListener('click', function(e) {
            // Seulement prévenir le défaut si c'est une navigation par onglets
            // Pour les pages séparées, laisser href gérer
            if (!this.getAttribute('href') || this.getAttribute('href') === '#') {
                e.preventDefault();
                // Gérer le changement d'onglet si nécessaire
                console.log('Navigation par onglet:', this.dataset.page);
            }
        });
    });

    // Close sidebar on mobile after navigation
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 992) {
                closeSidebar();
            }
        });
    });

    // Profile dropdown - supporte mobile et desktop
    const mobileProfileIcon = document.getElementById('mobileProfileIcon');
    const mobileProfileMenu = document.getElementById('mobileProfileMenu');
    const profileIcon = document.getElementById('profileIcon');
    const profileMenu = document.getElementById('profileMenu');

    // Priorité aux éléments mobiles si disponibles
    const activeProfileIcon = mobileProfileIcon || profileIcon;
    const activeProfileMenu = mobileProfileMenu || profileMenu;
    const iconId = mobileProfileIcon ? '#mobileProfileIcon' : '#profileIcon';
    const menuId = mobileProfileIcon ? '#mobileProfileMenu' : '#profileMenu';

    if (activeProfileIcon && activeProfileMenu) {
        console.log('Éléments du profil trouvés:', activeProfileIcon, activeProfileMenu, 'Type:', mobileProfileIcon ? 'mobile' : 'desktop');

        activeProfileIcon.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Profile icon clicked');
            // Forcer l'affichage pour test
            activeProfileMenu.style.display = activeProfileMenu.style.display === 'block' ? 'none' : 'block';
            console.log('Profile menu display:', activeProfileMenu.style.display);
        });

        // Fermer le menu au clic à l'extérieur
        document.addEventListener('click', function(event) {
            if (!event.target.closest(iconId) && !event.target.closest(menuId)) {
                activeProfileMenu.classList.remove('show');
            }
        });
    } else {
        console.log('Éléments du profil manquants:', { mobileProfileIcon, mobileProfileMenu, profileIcon, profileMenu });
    }

    console.log('Initialisation UI pour pages simples terminée');
});
</script>
