<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Header avec bouton fermer -->
    <div class="sidebar-header">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <?php if (!empty($configUniversitee['logo'])): ?>
                <img src="../<?= htmlspecialchars($configUniversitee['logo']) ?>" alt="Logo" height="32" class="me-2">
                <?php endif; ?>
                <span class="fw-bold text-primary" style="font-size: 1.05rem;">MY <?= htmlspecialchars($configUniversitee['sigle'] ?? 'E-Gestion') ?></span>
            </div>
            <button class="btn btn-link p-0 sidebar-close-btn" id="sidebarClose" aria-label="Fermer">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <!-- Profil étudiant -->
    <div class="sidebar-profile">
        <div class="d-flex align-items-center">
            <img src="<?= isset($_SESSION['photo']) && !empty($_SESSION['photo'])
                ? '../uploads/'.$_SESSION['photo'].'?t='.time()
                : '../uploads/user.png?t='.time() ?>"
                alt="Photo de profil"
                class="rounded-circle sidebar-avatar me-3"
                width="48" height="48"
                style="object-fit: cover; border: 2px solid var(--primary-color);">
            <div class="flex-grow-1 min-width-0">
                <div class="fw-semibold text-truncate" style="font-size: 0.9rem;"><?= htmlspecialchars($studentName) ?></div>
                <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($studentMatricule) ?></div>
            </div>
        </div>
    </div>

    <!-- Menu de navigation -->
    <div class="sidebar-menu">
        <div class="sidebar-menu-label">MENU PRINCIPAL</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="student" class="nav-link <?= (isset($currentPage) && $currentPage == 'student') ? 'active' : '' ?>">
                    <i class="fas fa-home"></i>
                    <span>Accueil</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-page="courses">
                    <i class="fas fa-book"></i>
                    <span>Mes Cours</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-page="evaluations">
                    <i class="fas fa-chart-bar"></i>
                    <span>Mes Notes</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-page="schedule">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Horaire</span>
                </a>
            </li>
        </ul>

        <?php if ($estPromotionTerminale): ?>
        <div class="sidebar-menu-label">MÉMOIRE</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="#" class="nav-link" data-page="subjects">
                    <i class="fas fa-folder-open"></i>
                    <span>Sujets Disponibles</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-page="tasks">
                    <i class="fas fa-tasks"></i>
                    <span>Mes Tâches</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="memoire" class="nav-link <?= (isset($currentPage) && $currentPage == 'memoire') ? 'active' : '' ?>">
                    <i class="fas fa-file-pdf"></i>
                    <span>Mémoire</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>

        <div class="sidebar-menu-label">ACADÉMIQUE</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="stage" class="nav-link <?= (isset($currentPage) && $currentPage == 'stage') ? 'active' : '' ?>">
                    <i class="fas fa-building"></i>
                    <span>Stage</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="frais_academiques" class="nav-link <?= (isset($currentPage) && $currentPage == 'frais_academiques') ? 'active' : '' ?>">
                    <i class="fas fa-money-check-alt"></i>
                    <span>Frais Académiques</span>
                </a>
            </li>
            <!-- Masqué temporairement
            <li class="nav-item">
                <a href="progression" class="nav-link <?= (isset($currentPage) && $currentPage == 'progression') ? 'active' : '' ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Ma Progression</span>
                </a>
            </li>
            -->
            <li class="nav-item">
                <a href="fiches_validation" class="nav-link <?= (isset($currentPage) && $currentPage == 'fiches_validation') ? 'active' : '' ?>">
                    <i class="fas fa-file-signature"></i>
                    <span>Fiches de Validation</span>
                </a>
            </li>
            <?php if ($deliberationPubliee): ?>
            <li class="nav-item">
                <a href="#" class="nav-link" data-page="recours">
                    <i class="fas fa-gavel"></i>
                    <span>Recours</span>
                    <?php if (!empty($recours)): ?>
                    <span class="badge bg-danger ms-auto"><?= count($recours) ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            <?php if (!empty($estChefPromotion)): ?>
            <li class="nav-item">
                <a href="#" class="nav-link" data-page="suivi-enseignements">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Suivi Enseignements</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <div class="sidebar-menu-label">COMPTE</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="profile" class="nav-link <?= (isset($currentPage) && $currentPage == 'profile') ? 'active' : '' ?>">
                    <i class="fas fa-user-circle"></i>
                    <span>Mon Profil</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-page="messages">
                    <i class="fas fa-comments"></i>
                    <span>Messages</span>
                    <?php if (isset($notifications['messages']) && $notifications['messages'] > 0): ?>
                    <span class="badge bg-danger ms-auto"><?= $notifications['messages'] ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
    </div>

    <!-- Footer sidebar -->
    <div class="sidebar-footer">
        <div class="sidebar-footer-year">
            <i class="fas fa-calendar-alt me-1"></i>
            <?= htmlspecialchars($currentYear['designation']) ?>
        </div>
        <a href="../controller/logout.php" class="btn btn-outline-danger btn-sm w-100">
            <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
        </a>
    </div>
</div>
