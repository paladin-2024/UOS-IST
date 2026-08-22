    <div class="profile-menu" id="profileMenu">
        <div class="profile-menu-header">
            <img src="<?= isset($_SESSION['photo']) && !empty($_SESSION['photo'])
                            ? '../uploads/' . $_SESSION['photo'] . '?t=' . time()
                            : '../uploads/user.png?t=' . time() ?>"
                alt="Profile"
                class="profile-menu-avatar"
                width="48" height="48">
            <div class="profile-menu-info">
                <strong class="profile-menu-name"><?= htmlspecialchars($studentName) ?></strong>
                <span class="profile-menu-matricule"><?= htmlspecialchars($studentMatricule) ?></span>
            </div>
        </div>
        <div class="profile-menu-body">
            <div class="profile-menu-year">
                <i class="fas fa-calendar-alt me-2 text-primary"></i>
                <span>Année Acad : <?= htmlspecialchars($currentYear['designation']) ?></span>
            </div>
            <div class="profile-menu-actions">
                <a href="profile" class="profile-menu-btn profile-menu-btn-profile">
                    <i class="fas fa-user-circle me-2"></i>Mon Profil
                </a>
                <a href="../controller/logout.php" class="profile-menu-btn profile-menu-btn-logout">
                    <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                </a>
            </div>
        </div>
    </div>
