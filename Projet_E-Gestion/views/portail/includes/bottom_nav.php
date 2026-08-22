<!-- Bottom Navigation -->
<nav class="bottom-nav d-lg-none">
    <div class="d-flex justify-content-around w-100">
        <a href="student" class="nav-item <?php echo (isset($currentPage) && $currentPage == 'student') ? 'active' : ''; ?>">
            <i class="fas fa-home d-block"></i>
            <small>Accueil</small>
        </a>
        <a href="#" class="nav-item" data-page="evaluations">
            <i class="fas fa-chart-bar d-block"></i>
            <small>Notes</small>
        </a>
        <a href="#" class="nav-item" data-page="courses">
            <i class="fas fa-book d-block"></i>
            <small>Cours</small>
        </a>
        <a href="stage" class="nav-item <?php echo (isset($currentPage) && $currentPage == 'stage') ? 'active' : ''; ?>">
            <i class="fas fa-building d-block"></i>
            <small>Stage</small>
        </a>
        <a href="frais_academiques" class="nav-item <?php echo (isset($currentPage) && $currentPage == 'frais_academiques') ? 'active' : ''; ?>">
            <i class="fas fa-money-check-alt d-block"></i>
            <small>Frais</small>
        </a>
        <a href="profile" class="nav-item <?php echo (isset($currentPage) && $currentPage == 'profile') ? 'active' : ''; ?>">
            <i class="fas fa-user d-block"></i>
            <small>Profil</small>
        </a>
    </div>
</nav>
