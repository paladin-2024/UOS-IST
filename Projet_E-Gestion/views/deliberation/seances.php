<?php
include "./views/include/header.php";

$universite = new Universite();
$agent = new Agent();

// Vérifier les droits d'accès
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$userId = $_SESSION['id'];
$agentId = $agent->getAgentIdByUserId($userId);
$isJuryPresident = $universite->isJuryPresident($agentId);
$isJuryMember = false;

// Récupérer les bureaux de jury où l'agent est membre
$juryBureaux = [];
if ($agentId) {
    $juryBureaux = $universite->getJuryBureauxByAgent($agentId);
    $isJuryMember = !empty($juryBureaux);
}

// Rediriger si l'utilisateur n'a pas les droits
if (!$isAdmin && !$isJuryMember) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas les droits pour accéder à cette page.'
        }).then(() => {
            window.location.href = 'index';
        });
    </script>";
    exit();
}

// Récupérer les paramètres de filtre
$bureauId = isset($_GET['bureau']) ? intval($_GET['bureau']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;

// Récupérer les données pour les filtres
$annees = $universite->getAcademicYears();
$sessions = $universite->getAllSessions();

// Récupérer les bureaux de jury selon le rôle
if ($isAdmin) {
    $bureaux = $universite->getJurys('', true); // Tous les jurys actifs
} else {
    $bureaux = $juryBureaux; // Seulement les jurys où l'agent est membre
}

// Récupérer les séances de délibération
$seances = [];
if ($bureauId && $anneeId && $sessionId) {
    $seances = $universite->getDeliberationsByFilters($bureauId, $anneeId, $sessionId);
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Séances de Délibération</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Délibération</li>
                <li class="breadcrumb-item active">Séances</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <!-- Filtres -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Sélection du bureau de jury, de l'année académique et de la session</h5>
                        
                        <form method="GET" action="" class="row g-3 mb-4">
                            <input type="hidden" name="view" value="deliberation/seances">
                            
                            <div class="col-md-4">
                                <label for="bureau" class="form-label">Bureau de Jury</label>
                                <select name="bureau" id="bureau" class="form-select" required onchange="this.form.submit()">
                                    <option value="">Sélectionner un bureau</option>
                                    <?php foreach ($bureaux as $bureau): ?>
                                        <option value="<?= $bureau['idbureau'] ?>" <?= ($bureauId == $bureau['idbureau']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($bureau['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if ($bureauId): ?>
                            <div class="col-md-4">
                                <label for="annee" class="form-label">Année Académique</label>
                                <select name="annee" id="annee" class="form-select" required onchange="this.form.submit()">
                                    <option value="">Sélectionner une année</option>
                                    <?php foreach ($annees as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" <?= ($anneeId == $annee['idannee_acad']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="session" class="form-label">Session</label>
                                <select name="session" id="session" class="form-select" required onchange="this.form.submit()">
                                    <option value="">Sélectionner une session</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?= $session['idsession'] ?>" <?= ($sessionId == $session['idsession']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($session['designSession']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </form>
                        
                        <?php if ($bureauId && $anneeId && $sessionId && ($isAdmin || $isJuryPresident)): ?>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newDeliberationModal">
                                <i class="bi bi-plus-circle me-1"></i> Nouvelle séance de délibération
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Liste des séances de délibération -->
            <?php if ($bureauId && $anneeId && $sessionId): ?>
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-list-check me-2"></i>
                            Séances de délibération
                        </h5>
                        
                        <?php if (empty($seances)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Aucune séance de délibération n'a été trouvée pour ces critères.
                            <?php if ($isAdmin || $isJuryPresident): ?>
                            Vous pouvez créer une nouvelle séance en cliquant sur le bouton "Nouvelle séance de délibération".
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Promotion</th>
                                        <th scope="col">Commentaire</th>
                                        <th scope="col">Date</th>
                                        <th scope="col">Statut</th>
                                        <th scope="col">Créé par</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($seances as $index => $seance): 
                                        // Déterminer les permissions en fonction du statut et du rôle
                                        $canEdit = ($isAdmin || ($isJuryPresident && $seance['statut'] != 'Publiée'));
                                        $canRelaunch = ($isAdmin || ($isJuryPresident && in_array($seance['statut'], ['En préparation', 'Effectuée'])));
                                        
                                        // Définir la classe de badge selon le statut
                                        $statusClass = '';
                                        switch($seance['statut']) {
                                            case 'En préparation': $statusClass = 'bg-warning'; break;
                                            case 'Effectuée': $statusClass = 'bg-info'; break;
                                            case 'Validée': $statusClass = 'bg-success'; break;
                                            case 'Publiée': $statusClass = 'bg-primary'; break;
                                            default: $statusClass = 'bg-secondary';
                                        }
                                    ?>
                                    <tr>
                                        <th scope="row"><?= $index + 1 ?></th>
                                        <td><?= htmlspecialchars($seance['designationPromotion']) ?></td>
                                        <td><?= htmlspecialchars($seance['commentaire']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($seance['date_deliberation'])) ?></td>
                                        <td><span class="badge <?= $statusClass ?>"><?= $seance['statut'] ?></span></td>
                                        <td><?= htmlspecialchars($seance['nom_createur']) ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if ($canRelaunch): ?>
                                                <?php if ($seance['statut'] === 'En préparation'): ?>
                                                <button type="button" class="btn btn-sm btn-success" 
                                                    onclick="lancerDeliberationAutomatique(<?= $seance['iddeliberation'] ?>, 'automatique')">
                                                <i class="bi bi-magic"></i> Auto
                                                </button>
                                                
                                                <button type="button" class="btn btn-sm btn-warning" 
                                                onclick="lancerDeliberationAutomatique(<?= $seance['iddeliberation'] ?>, 'semi-automatique')">
                                                <i class="bi bi-person-check"></i> Semi-auto
                                                </button>
                                                
                                                <button type="button" class="btn btn-sm btn-primary" 
                                                onclick="lancerDeliberation(<?= $seance['iddeliberation'] ?>)">
                                                <i class="bi bi-play-fill"></i> Manuel
                                                </button>
                                                <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        onclick="lancerDeliberation(<?= $seance['iddeliberation'] ?>)">
                                                    <i class="bi bi-arrow-repeat"></i> Relancer
                                </button>
                                <?php endif; ?>
                                <?php endif; ?>
                                                
                                                <?php if ($canEdit): ?>
                                                <button type="button" class="btn btn-sm btn-warning" 
                                                        onclick="modifierStatut(<?= $seance['iddeliberation'] ?>, '<?= $seance['statut'] ?>')">
                                                    <i class="bi bi-pencil"></i> Modifier statut
                                                </button>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        onclick="consulterDocuments(<?= $seance['iddeliberation'] ?>)">
                                                    <i class="bi bi-file-earmark-text"></i> Documents
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<!-- Modal pour nouvelle délibération -->
<div class="modal fade" id="newDeliberationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouvelle séance de délibération</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="newDeliberationForm" action="controller/create_deliberation.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="bureau_id" value="<?= $bureauId ?>">
                    <input type="hidden" name="annee_id" value="<?= $anneeId ?>">
                    <input type="hidden" name="session_id" value="<?= $sessionId ?>">
                    
                    <div class="mb-3">
                        <label for="promotion_id" class="form-label">Promotion</label>
                        <select name="promotion_id" id="promotion_id" class="form-select" required>
                            <option value="">Sélectionner une promotion</option>
                            <?php 
                            // Récupérer les promotions associées au bureau sélectionné
                            $promotions = $universite->getPromotionsByJury($bureauId);
                            foreach($promotions as $promotion): 
                            ?>
                            <option value="<?= $promotion['idpromotion'] ?>">
                                <?= htmlspecialchars($promotion['designationPromotion']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_deliberation" class="form-label">Date et heure de la délibération</label>
                        <input type="datetime-local" class="form-control" id="date_deliberation" name="date_deliberation" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="commentaire" class="form-label">Commentaire (facultatif)</label>
                        <textarea class="form-control" id="commentaire" name="commentaire" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour modifier le statut -->
<div class="modal fade" id="modifierStatutModal" tabindex="-1" aria-hidden="true" >
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le statut de la délibération</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="modifierStatutForm" action="controller/update_deliberation_status.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="deliberation_id" id="statut_deliberation_id" value="">
                    <input type="hidden" name="annee_acad_idannee_acad" id="annee_id">
                    
                    <div class="mb-3">
                        <label for="nouveau_statut" class="form-label">Nouveau statut</label>
                        <select name="nouveau_statut" id="nouveau_statut" class="form-select" required>
                            <option value="En préparation">En préparation</option>
                            <option value="Effectuée">Effectuée</option>
                            <option value="Validée">Validée</option>
                            <?php if ($isAdmin): // Seul l'admin peut publier ?>
                            <option value="Publiée">Publiée</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="motif_changement" class="form-label">Motif du changement</label>
                        <textarea class="form-control" id="motif_changement" name="motif_changement" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour consulter les documents -->
<div class="modal fade" id="documentsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Documents de délibération</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="documents_container">
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
// Initialisation des modals
document.addEventListener('DOMContentLoaded', function() {
    // Définir la date actuelle par défaut pour la nouvelle délibération
    const dateInput = document.getElementById('date_deliberation');
    if (dateInput) {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        dateInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
    }
});

// Fonction pour lancer ou relancer une délibération
function lancerDeliberation(deliberationId) {
    Swal.fire({
        title: 'Confirmation',
        text: 'Êtes-vous sûr de vouloir lancer/relancer cette délibération?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Oui, lancer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `controller/start_deliberation.php?deliberation_id=${deliberationId}`;
        }
    });
}

// Fonction pour modifier le statut d'une délibération
function modifierStatut(deliberationId, statutActuel) {
    document.getElementById('statut_deliberation_id').value = deliberationId;
    document.getElementById('annee_id').value = <?php echo $anneeId; ?>;
    
    // Pré-sélectionner le statut actuel dans le dropdown
    const statutSelect = document.getElementById('nouveau_statut');
    for (let i = 0; i < statutSelect.options.length; i++) {
        if (statutSelect.options[i].value === statutActuel) {
            statutSelect.selectedIndex = i;
            break;
        }
    }
    
    // Ouvrir le modal
    const modal = new bootstrap.Modal(document.getElementById('modifierStatutModal'));
    modal.show();
}

// Fonction pour lancer la délibération automatique
function lancerDeliberationAutomatique(deliberationId, mode) {
    const modeText = mode === 'automatique' ? 'automatique' : 'semi-automatique';
    
    Swal.fire({
        title: `Délibération ${modeText}`,
        text: `Voulez-vous lancer la délibération en mode ${modeText}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Oui, lancer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            // Rediriger vers la page de délibération automatique
            window.location.href = `index.php?view=deliberation/deliberation_automatique&deliberation_id=${deliberationId}&mode=${mode}`;
        }
    });
}

// Fonction pour consulter les documents
function consulterDocuments(deliberationId) {
    // Afficher le modal
    const modal = new bootstrap.Modal(document.getElementById('documentsModal'));
    modal.show();
    
    // Charger les informations de la délibération
    fetch(`controller/get_deliberation_info.php?deliberation_id=${deliberationId}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('documents_container');
            
            if (data.error) {
                container.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }
            
            // Récupérer les paramètres nécessaires
            const promotionId = data.promotion_id;
            const bureauId = <?php echo $bureauId; ?>;
            const anneeId = <?php echo $anneeId; ?>;
            const sessionId = <?php echo $sessionId; ?>;
            
            // Définir la liste des documents
            const documents = [
                { nom: "Grille après délibération", page: "grille_deliberation", icon: "table" },
                { nom: "PV de délibération", page: "pv_deliberation", icon: "file-earmark-text" },
                { nom: "Fiches de validation de crédits", page: "validation_credits", icon: "check-square" },
                { nom: "Palmarès", page: "palmares", icon: "trophy" },
                { nom: "Relevé des notes", page: "releve_notes", icon: "list-ol" }
            ];
            
            // Afficher les documents
            let html = `
                <div class="list-group">
                    <div class="list-group-item active">
                        <div class="row">
                            <div class="col-md-7">Document</div>
                            <div class="col-md-5">Actions</div>
                        </div>
                    </div>
            `;
            
            documents.forEach(doc => {
                html += `
                    <div class="list-group-item">
                        <div class="row align-items-center">
                            <div class="col-md-7">
                                <i class="bi bi-${doc.icon} me-2"></i>
                                ${doc.nom}
                            </div>
                            <div class="col-md-5">
                                <a href="index.php?view=deliberation/${doc.page}&deliberation_id=${deliberationId}&promotion_id=${promotionId}&bureau_id=${bureauId}&annee_id=${anneeId}&session_id=${sessionId}" 
                                   class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="bi bi-eye"></i> Consulter
                                </a>
                                <a href="controller/generate_${doc.page}.php?deliberation_id=${deliberationId}&promotion_id=${promotionId}&bureau_id=${bureauId}&annee_id=${anneeId}&session_id=${sessionId}" 
                                   class="btn btn-sm btn-outline-success" target="_blank">
                                    <i class="bi bi-download"></i> Télécharger PDF
                                </a>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += `</div>`;
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Erreur:', error);
            document.getElementById('documents_container').innerHTML = `
                <div class="alert alert-danger">
                    Une erreur est survenue lors du chargement des documents.
                </div>
            `;
        });
}

</script>

<?php include "./views/include/footer.php"; ?>
