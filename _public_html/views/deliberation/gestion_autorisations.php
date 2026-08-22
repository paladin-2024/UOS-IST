<?php
include "./views/include/header.php";

$universite = new Universite();
$agent = new Agent();
$ecue = new Ecue();

// Vérifier si l'utilisateur est connecté et est président d'un jury
$userId = $_SESSION['id'] ?? 0;
$agentId = $agent->getAgentIdByUserId($userId);
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;

// Récupérer les bureaux de jury où l'agent est président
$juryBureaux = [];
$isJuryPresident = false;
if ($agentId) {
    $isJuryPresident = $universite->isJuryPresident($agentId);
    if ($isJuryPresident || $isAdmin) {
        $juryBureaux = $isAdmin 
            ? $universite->getJurys('', true) 
            : $universite->getJuryBureauxByPresident($agentId);
    }
}

// Rediriger si l'utilisateur n'a pas les droits
if (!$isAdmin && !$isJuryPresident) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous devez être président du jury pour accéder à cette page.'
        }).then(() => {
            window.location.href = 'index';
        });
    </script>";
    exit();
}

// Récupérer les paramètres de sélection
$bureauId = isset($_GET['bureau']) ? intval($_GET['bureau']) : 0;
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$semestreId = isset($_GET['semestre']) ? intval($_GET['semestre']) : 0;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;

// Validation des accès pour les présidents (non admins)
if ($bureauId && !$isAdmin) {
    // Vérifier si l'agent est président de ce bureau
    $hasAccess = false;
    foreach ($juryBureaux as $jury) {
        if ($jury['idbureau'] == $bureauId && $jury['president_id'] == $agentId) {
            $hasAccess = true;
            break;
        }
    }
    
    if (!$hasAccess) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Accès refusé',
                text: 'Vous n\'êtes pas président de ce jury.'
            }).then(() => {
                window.location.href = 'deliberation/gestion_autorisations';
            });
        </script>";
        exit();
    }
}

// Récupérer les promotions associées au bureau sélectionné
$promotions = [];
if ($bureauId) {
    $promotions = $universite->getPromotionsByJury($bureauId);
}

// Récupérer les semestres de la promotion sélectionnée
$semestres = [];
if ($promotionId) {
    $semestres = $universite->getSemestresByPromotion($promotionId);
}

// Récupérer les sessions et années académiques
$sessions = $universite->getAllSessions();
$annees = $universite->getAcademicYears();

// Gérer l'ajout ou la suppression d'autorisations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'add_authorization') {
            $memberId = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
            $ecueIds = isset($_POST['ecue_ids']) ? $_POST['ecue_ids'] : [];
            
            if ($memberId && !empty($ecueIds) && $bureauId && $sessionId && $anneeId) {
                foreach ($ecueIds as $ecueId) {
                    $universite->addJuryMemberAuthorization($bureauId, $memberId, $ecueId, $sessionId, $anneeId, $userId);
                }
                
                echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: 'Les autorisations ont été ajoutées avec succès.'
                    });
                </script>";
            } else {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Veuillez remplir tous les champs requis.'
                    });
                </script>";
            }
        } elseif ($action === 'remove_authorization') {
            $authorizationId = isset($_POST['authorization_id']) ? intval($_POST['authorization_id']) : 0;
            
            if ($authorizationId) {
                $universite->removeJuryMemberAuthorization($authorizationId);
                
                echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: 'L\'autorisation a été supprimée avec succès.'
                    });
                </script>";
            } else {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'ID d\'autorisation invalide.'
                    });
                </script>";
            }
        }
    }
}

// Récupérer les données pour afficher dans la page
$juryMembers = [];
$ecuesList = [];
$memberAuthorizations = [];

if ($bureauId) {
    // Récupérer les membres du jury (excepté le président)
    $juryMembers = $universite->getJuryMembersByBureau($bureauId, true);
}

if ($bureauId && $semestreId && $sessionId && $anneeId) {
    // Récupérer les cours (ECUE) du semestre
    $ecuesList = $universite->getEcuesBySemestre($semestreId);
    
    // Récupérer les autorisations existantes pour ce bureau
    $memberAuthorizations = $universite->getJuryMemberAuthorizations($bureauId, $sessionId, $anneeId);
}

// Fonction pour vérifier si un ECUE est déjà autorisé pour n'importe quel membre
function isEcueAlreadyAuthorized($ecueId, $memberAuthorizations) {
    foreach ($memberAuthorizations as $auth) {
        if ($auth['idECUE'] == $ecueId) {
            return true;
        }
    }
    return false;
}
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Gestion des autorisations d'encodage</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Délibération</li>
                <li class="breadcrumb-item active">Gestion des autorisations</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Sélection des paramètres -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-shield-lock me-1"></i>
                            Sélection du bureau et des paramètres
                        </h5>

                        <form method="GET" action="" class="row g-3 mb-4">
                            <input type="hidden" name="view" value="deliberation/gestion_autorisations">
                            
                            <div class="col-md-3">
                                <label for="bureau" class="form-label">Bureau de Jury</label>
                                <select name="bureau" id="bureau" class="form-select" required onchange="this.form.submit()">
                                    <option value="">Sélectionner un bureau</option>
                                    <?php foreach ($juryBureaux as $jury): ?>
                                        <option value="<?= $jury['idbureau'] ?>" <?= ($bureauId == $jury['idbureau']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($jury['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if ($bureauId): ?>
                            <div class="col-md-3">
                                <label for="promotion" class="form-label">Promotion</label>
                                <select name="promotion" id="promotion" class="form-select" required onchange="this.form.submit()">
                                    <option value="">Sélectionner une promotion</option>
                                    <?php foreach ($promotions as $promotion): ?>
                                        <option value="<?= $promotion['idpromotion'] ?>" <?= ($promotionId == $promotion['idpromotion']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($promotion['designationPromotion']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($promotionId): ?>
                            <div class="col-md-2">
                                <label for="semestre" class="form-label">Semestre</label>
                                <select name="semestre" id="semestre" class="form-select" required>
                                    <option value="">Sélectionner un semestre</option>
                                    <?php foreach ($semestres as $semestre): ?>
                                        <option value="<?= $semestre['idsemestre'] ?>" <?= ($semestreId == $semestre['idsemestre']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($semestre['numeroSemestre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="session" class="form-label">Session</label>
                                <select name="session" id="session" class="form-select" required>
                                    <option value="">Sélectionner une session</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?= $session['idsession'] ?>" <?= ($sessionId == $session['idsession']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($session['description']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="annee" class="form-label">Année</label>
                                <select name="annee" id="annee" class="form-select" required>
                                    <option value="">Sélectionner une année</option>
                                    <?php foreach ($annees as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" <?= ($anneeId == $annee['idannee_acad']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Afficher les données
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php if ($bureauId && $semestreId && $sessionId && $anneeId): ?>
            <!-- Gestion des autorisations -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                        <i class="bi bi-person-check me-1"></i>
                            Attribution des autorisations d'encodage
                        </h5>

                        <?php if (empty($juryMembers)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Aucun membre n'est associé à ce jury. Veuillez d'abord ajouter des membres au jury.
                            </div>
                        <?php elseif (empty($ecuesList)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Aucun cours n'est disponible pour ce semestre.
                            </div>
                        <?php else: ?>
                            <!-- Formulaire d'ajout d'autorisations -->
                            <form action="" method="POST" class="mb-4">
                                <input type="hidden" name="action" value="add_authorization">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="member_id" class="form-label">Membre du jury</label>
                                        <select name="member_id" id="member_id" class="form-select" required>
                                            <option value="">Sélectionner un membre</option>
                                            <?php foreach ($juryMembers as $member): ?>
                                                <option value="<?= $member['idAgent'] ?>">
                                                    <?= htmlspecialchars($member['noms']) ?> 
                                                    <?= $member['role'] ? '(' . htmlspecialchars($member['role']) . ')' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <label class="form-label">Cours à autoriser</label>
                                        <div class="border p-3 rounded bg-light">
                                            <div class="row">
                                                <div class="col-md-12 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="select-all-courses">
                                                        <label class="form-check-label fw-bold" for="select-all-courses">
                                                            Sélectionner/Désélectionner tous les cours
                                                        </label>
                                                    </div>
                                                </div>
                                                
                                                <?php 
                                                    $availableEcueCount = 0;
                                                    foreach ($ecuesList as $ecueItem): 
                                                        // Vérifier si ce cours est déjà autorisé pour un membre du jury
                                                        if (isEcueAlreadyAuthorized($ecueItem['idECUE'], $memberAuthorizations)) {
                                                            continue; // Ignorer ce cours car il est déjà autorisé
                                                        }
                                                        $availableEcueCount++;
                                                    ?>
                                                        <div class="col-md-6 mb-2">
                                                            <div class="form-check">
                                                                <input class="form-check-input course-checkbox" type="checkbox" 
                                                                    name="ecue_ids[]" value="<?= $ecueItem['idECUE'] ?>" 
                                                                    id="ecue_<?= $ecueItem['idECUE'] ?>">
                                                                <label class="form-check-label" for="ecue_<?= $ecueItem['idECUE'] ?>">
                                                                    <?= htmlspecialchars($ecueItem['designationECUE']) ?>
                                                                    <small class="text-muted d-block">
                                                                        UE: <?= htmlspecialchars($ecueItem['designationUE']) ?>
                                                                    </small>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>

                                                    <?php if ($availableEcueCount === 0): ?>
                                                        <div class="col-12">
                                                            <div class="alert alert-info">
                                                                <i class="bi bi-info-circle me-2"></i>
                                                                Tous les cours de ce semestre ont déjà été assignés pour l'encodage.
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-1"></i> Ajouter les autorisations
                                </button>
                            </form>
                            
                            <hr class="my-4">
                            
                            <!-- Tableau des autorisations existantes -->
                            <h5>Autorisations d'encodage actuelles</h5>
                            
                            <?php if (empty($memberAuthorizations)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Aucune autorisation n'a encore été attribuée.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead class="table-primary">
                                            <tr>
                                                <th>Membre du jury</th>
                                                <th>Cours</th>
                                                <th>UE</th>
                                                <th>Date d'autorisation</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($memberAuthorizations as $auth): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($auth['nom_agent']) ?></td>
                                                    <td><?= htmlspecialchars($auth['designationECUE']) ?></td>
                                                    <td><?= htmlspecialchars($auth['designationUE']) ?></td>
                                                    <td><?= date('d/m/Y H:i', strtotime($auth['date_autorisation'])) ?></td>
                                                    <td>
                                                        <form action="" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette autorisation?');">
                                                            <input type="hidden" name="action" value="remove_authorization">
                                                            <input type="hidden" name="authorization_id" value="<?= $auth['id_autorisation'] ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm">
                                                                <i class="bi bi-trash"></i> Supprimer
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </section>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gestion du bouton "Sélectionner tous les cours"
        const selectAllCheckbox = document.getElementById('select-all-courses');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.course-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = selectAllCheckbox.checked;
                });
            });
        }
        
        // Mise à jour du bouton "Sélectionner tous" si on désélectionne manuellement une case
        const courseCheckboxes = document.querySelectorAll('.course-checkbox');
        courseCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allChecked = [...courseCheckboxes].every(cb => cb.checked);
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = allChecked;
                }
            });
        });
    });
</script>

<?php include "./views/include/footer.php"; ?>
