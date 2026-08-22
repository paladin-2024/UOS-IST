<!-- Sidebar pour student.php avec tabs -->
<div class="sidebar" id="sidebar">
    <!-- Profil -->
    <div class="sidebar-profile p-3 border-bottom">
        <div class="d-flex align-items-center">
            <div class="position-relative">
                <img src="../uploads/<?= isset($_SESSION['photo']) && !empty($_SESSION['photo'])
? '../uploads/'.$_SESSION['photo']
: '../uploads/user.png' ?>"
alt="Photo de profil"
                     class="rounded-circle me-3"
                     width="60"
                     height="60"
                     style="object-fit: cover; border: 3px solid var(--primary-light);">
            </div>
            <div>
                <h6 class="mb-1 fw-bold"><?= htmlspecialchars($studentName) ?></h6>
                <div class="d-flex align-items-center">
                    <span class="badge bg-primary me-2">Étudiant</span>
                    <small class="text-muted"><?= htmlspecialchars($studentMatricule) ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Menu de navigation -->
    <div class="sidebar-menu py-2">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="#" class="nav-link active" data-page="home">
                    <i class="fas fa-home"></i>
                    <span>Accueil</span>
                </a>
            </li>
            <?php if ($estPromotionTerminale): ?>
            <li class="nav-item">
                <a href="#" class="nav-link" data-page="tasks">
                    <i class="fas fa-tasks"></i>
                    <span>Mes Tâches</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-page="subjects">
                    <i class="fas fa-book"></i>
                    <span>Sujets Disponibles</span>
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a href="#" class="nav-link" data-page="evaluations">
                    <i class="fas fa-chart-bar"></i>
                    <span>Mes Notes</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="stage" class="nav-link">
                    <i class="fas fa-building"></i>
                    <span>Stage</span>
                </a>
            </li>
            <!-- In the sidebar-menu section, add this item if deliberation is published -->
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

            <li class="nav-item">
                <a href="#" class="nav-link" data-page="progress">
                    <i class="fas fa-chart-line"></i>
                    <span>Ma Progression</span>
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
            <li class="nav-item">
                <a href="profile" class="nav-link" data-page="profile">
                    <i class="fas fa-user"></i>
                        <span>Mon Profil</span>
                    </a>
            </li>
            <?php if ($estPromotionTerminale): ?>
            <li class="nav-item mt-3">
                <a href="#" class="nav-link bg-primary-light text-primary" id="proposerSujetSidebar">
                    <i class="fas fa-plus-circle"></i>
                    <span>Proposer un Sujet</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Informations supplémentaires -->
    <div class="p-3 mt-4 border-top">
        <div class="d-flex align-items-center mb-3">
            <i class="fas fa-calendar-alt text-primary me-2"></i>
            <div>
                <small class="text-muted d-block">Année académique</small>
                <strong><?= htmlspecialchars($currentYear['designation']) ?></strong>
            </div>
        </div>
        <?php if (isset($_SESSION['promotion_id']) && !empty($_SESSION['promotion_id'])):
            $promotionInfo = $universite->getPromotionById($_SESSION['promotion_id']);
            if ($promotionInfo):
        ?>
        <div class="d-flex align-items-center">
            <i class="fas fa-users text-primary me-2"></i>
            <div>
                <small class="text-muted d-block">Promotion</small>
                <strong><?= htmlspecialchars($promotionInfo['designationPromotion']) ?></strong>
            </div>
        </div>
        <?php
            endif;
        endif;
        ?>
    </div>
</div>
