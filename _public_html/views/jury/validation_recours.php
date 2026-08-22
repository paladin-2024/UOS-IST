<?php
include_once "./views/include/header.php";

// Instancier les modèles nécessaires
$deliberation = new Deliberation();
$agent = new Agent();

// Vérifier si l'utilisateur est administrateur
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$userId = $_SESSION['id'];
$agentId = $agent->getAgentIdByUserId($userId);
$isJuryMember = false;
$juryBureaux = [];

// Récupérer les bureaux de jury où l'agent est membre
if ($agentId) {
    $juryBureaux = $deliberation->getJuryBureauxByAgent($agentId);
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

$conn = Connexion::getInstance()->getPDO();

// Récupérer l'année académique en cours ou celle sélectionnée
$id_annee = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
if ($id_annee == 0) {
    $query_annee = "SELECT idannee_acad FROM annee_acad WHERE est_active = 1 LIMIT 1";
    $stmt_annee = $conn->prepare($query_annee);
    $stmt_annee->execute();
    $annee = $stmt_annee->fetch(PDO::FETCH_ASSOC);
    $id_annee = $annee['idannee_acad'];
}

// Récupérer les filtres
$id_promotion = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$id_session = isset($_GET['session']) ? intval($_GET['session']) : 0;
$statut = isset($_GET['statut']) ? $_GET['statut'] : 'En traitement';
$validated_only = isset($_GET['validated_only']) ? (bool)$_GET['validated_only'] : false;

// Récupérer les années académiques pour le filtre
$query_annees = "SELECT idannee_acad, designation FROM annee_acad ORDER BY \"dateCreation\" DESC";
$stmt_annees = $conn->prepare($query_annees);
$stmt_annees->execute();
$annees = $stmt_annees->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les sessions pour le filtre
$query_sessions = "SELECT idsession, designSession FROM session ORDER BY idsession";
$stmt_sessions = $conn->prepare($query_sessions);
$stmt_sessions->execute();
$sessions = $stmt_sessions->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les promotions gérées par le jury (ou toutes pour les admins)
if ($isAdmin) {
    $query_promotions = "SELECT idpromotion, designationPromotion, o.\"designationOrientation\", s.\"designationSection\"
                        FROM promotion p
                        JOIN orientation o ON p.orientation_idorientation = o.idorientation
                        JOIN section s ON o.section_idsection = s.idsection
                        WHERE p.annee_acad_idannee_acad = :id_annee
                        ORDER BY s.\"designationSection\", o.\"designationOrientation\", p.\"designationPromotion\"";
    $stmt_promotions = $conn->prepare($query_promotions);
    $stmt_promotions->bindParam(':id_annee', $id_annee);
} else {
    // Récupérer les bureaux de jury de l'agent sous forme d'IDs
    $bureauIds = array_column($juryBureaux, 'idbureau');
    if (empty($bureauIds)) {
        $bureauIds = [0]; // Éviter une erreur SQL avec IN ()
    }
    
    // Construire la liste d'IDs directement (pas de placeholders)
    $bureauIdsList = implode(',', array_map('intval', $bureauIds));
    $query_promotions = "ELECT DISTINCT p.idpromotion, p.\"designationPromotion\", o.\"designationOrientation\", s.\"designationSection\"
                        FROM promotion p
                        JOIN orientation o ON p.orientation_idorientation = o.idorientation
                        JOIN section s ON o.section_idsection = s.idsection
                        JOIN bureau_jury_promotion bjp ON p.idpromotion = bjp.idpromotion
                        JOIN bureau_jury_deliberation bjd ON bjp.idbureau = bjd.idbureau
                        WHERE p.annee_acad_idannee_acad = :id_annee
                        AND bjd.idbureau IN $bureauIdsList
                        AND bjd.est_actif = 1
                        ORDER BY s.\"designationSection\", o.\"designationOrientation\", p.designationPromotio";
    
    $stmt_promotions = $conn->prepare($query_promotions);
    $stmt_promotions->bindParam(':id_annee', $id_annee);
}
$stmt_promotions->execute();
$promotions = $stmt_promotions->fetchAll(PDO::FETCH_ASSOC);

// Construire la requête pour récupérer les recours
$query_recours = "
    SELECT r.id_recours, r.matricule, e.noms as nom_etudiant, p.\"designationPromotion\",
           ec.\"designationECUE\", u.\"designationUE\", r.motif, r.date_creation, r.statut,
           s.\"designSession\", a.designation as annee_acad,
           rr.id_reponse, rr.nouvelle_note_cc, rr.nouvelle_note_ex, rr.commentaire,
           rr.date_reponse, rr.valide_jury, ag.noms as nom_enseignant
    FROM recours r
    JOIN etudiant e ON r.matricule = e.matricule
    JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    JOIN ecue ec ON r.id_ecue = ec.\"idECUE\"
    JOIN ue u ON ec.\"UE_idUE\" = u.\"idUE\"
    JOIN session s ON r.id_session = s.idsession
    JOIN annee_acad a ON r.id_annee_acad = a.idannee_acad
    JOIN recours_reponse rr ON r.id_recours = rr.id_recours
    LEFT JOIN agent ag ON rr.id_enseignant = ag.\"idAgent\"
    WHERE r.id_annee_acad = :id_annee
    AND r.statut = :statut";

// Ajouter les filtres optionnels
$params = [':id_annee' => $id_annee, ':statut' => $statut];

if ($id_promotion > 0) {
    $query_recours .= " AND e.promotion_idpromotion = :id_promotion";
    $params[':id_promotion'] = $id_promotion;
} else if (!$isAdmin) {
    // Si l'utilisateur n'est pas admin et qu'aucune promotion n'est sélectionnée,
    // limiter aux promotions qu'il gère
    $bureauIds = array_column($juryBureaux, 'idbureau');
    if (empty($bureauIds)) {
        $bureauIds = [0];
    }
    $bureauIdsList = implode(',', array_map('intval', $bureauIds));
    
    $query_recours .= " AND e.promotion_idpromotion IN (
        SELECT bjp.idpromotion
        FROM bureau_jury_promotion bjp
        JOIN bureau_jury_deliberation bjd ON bjp.idbureau = bjd.idbureau
        WHERE bjd.annee_acad_idannee_acad = :id_annee2
        AND bjd.idbureau IN ($bureauIdsList)
        AND bjd.est_actif = 1
    )";
    $params[':id_annee2'] = $id_annee;
}

if ($id_session > 0) {
    $query_recours .= " AND r.id_session = :id_session";
    $params[':id_session'] = $id_session;
}

if ($validated_only) {
    $query_recours .= " AND rr.valide_jury = 1";
} else {
    $query_recours .= " AND rr.valide_jury = 0";
}

// Trier par date de réponse (plus récent en premier)
$query_recours .= " ORDER BY rr.date_reponse DESC";

$stmt_recours = $conn->prepare($query_recours);
foreach ($params as $key => $value) {
    $stmt_recours->bindValue($key, $value);
}
$stmt_recours->execute();
$recours = $stmt_recours->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les détails du bureau de jury pour le PV
$bureau = null;
if (!$isAdmin && !empty($juryBureaux)) {
    // Prendre le premier bureau disponible
    $firstBureau = $juryBureaux[0];
    $query_bureau = "SELECT bjd.idbureau, bjd.designation, bjd.numero_decision, 
                   p.noms as president, s.noms as secretaire
            FROM bureau_jury_deliberation bjd
            JOIN agent p ON bjd.president_id = p.\"idAgent\"
            JOIN agent s ON bjd.secretaire_id = s.\"idAgent\"
            WHERE bjd.idbureau = :bureau_id
            AND bjd.annee_acad_idannee_acad = :id_annee
            AND bjd.est_actif = 1
            LIMIT 1";
    
    $stmt_bureau = $conn->prepare($query_bureau);
    $stmt_bureau->bindParam(':bureau_id', $firstBureau['idbureau']);
    $stmt_bureau->bindParam(':id_annee', $id_annee);
    $stmt_bureau->execute();
    $bureau = $stmt_bureau->fetch(PDO::FETCH_ASSOC);
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Validation des Recours par le Jury</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Délibération</li>
                <li class="breadcrumb-item active">Validation des Recours</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Filtres -->
        <div class="row mb-3">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filtres</h5>
                        
                        <form action="" method="GET" class="row g-3">
                            <!-- Année académique -->
                            <div class="col-md-3">
                                <label for="annee" class="form-label">Année académique</label>
                                <select class="form-select" id="annee" name="annee">
                                    <?php foreach($annees as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" 
                                                <?= $annee['idannee_acad'] == $id_annee ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Promotion -->
                            <div class="col-md-3">
                                <label for="promotion" class="form-label">Promotion</label>
                                <select class="form-select" id="promotion" name="promotion">
                                    <option value="0">Toutes les promotions</option>
                                    <?php foreach($promotions as $promotion): ?>
                                        <option value="<?= $promotion['idpromotion'] ?>" 
                                                <?= $promotion['idpromotion'] == $id_promotion ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($promotion['designationSection'] . ' - ' . 
                                                                $promotion['designationOrientation'] . ' - ' . 
                                                                $promotion['designationPromotion']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Session -->
                            <div class="col-md-2">
                                <label for="session" class="form-label">Session</label>
                                <select class="form-select" id="session" name="session">
                                    <option value="0">Toutes les sessions</option>
                                    <?php foreach($sessions as $session): ?>
                                        <option value="<?= $session['idsession'] ?>" 
                                                <?= $session['idsession'] == $id_session ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($session['designSession']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Statut -->
                            <div class="col-md-2">
                                <label for="statut" class="form-label">Statut</label>
                                <select class="form-select" id="statut" name="statut">
                                    <option value="En traitement" <?= $statut == 'En traitement' ? 'selected' : '' ?>>En traitement</option>
                                    <option value="Approuvé" <?= $statut == 'Approuvé' ? 'selected' : '' ?>>Approuvé</option>
                                    <option value="Rejeté" <?= $statut == 'Rejeté' ? 'selected' : '' ?>>Rejeté</option>
                                </select>
                            </div>
                            
                            <!-- Validation -->
                            <div class="col-md-2">
                                <label for="validated_only" class="form-label">Validation</label>
                                <select class="form-select" id="validated_only" name="validated_only">
                                    <option value="0" <?= !$validated_only ? 'selected' : '' ?>>En attente de validation</option>
                                    <option value="1" <?= $validated_only ? 'selected' : '' ?>>Déjà validés</option>
                                </select>
                            </div>
                            
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-filter"></i> Filtrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actions sur les recours -->
        <div class="row mb-3">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Actions sur les recours</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <!-- Actions de validation groupée (uniquement pour les non validés) -->
                                <?php if (!$validated_only && count($recours) > 0): ?>
                                <form action="controller/jury_validate_recours.php" method="POST" id="formValidationGroupe">
                                    <input type="hidden" name="action" value="validation_groupe">
                                    <input type="hidden" name="id_annee" value="<?= $id_annee ?>">
                                    <input type="hidden" name="id_session" value="<?= $id_session ?>">
                                    <input type="hidden" name="ids_recours" id="ids_recours" value="">
                                    
                                    <div class="d-grid gap-2 d-md-block">
                                        <button type="button" class="btn btn-success mb-2" id="btnValiderSelectionnes" disabled>
                                            <i class="bi bi-check-circle"></i> Valider les recours sélectionnés
                                        </button>
                                        <button type="button" class="btn btn-danger mb-2" id="btnRejeterSelectionnes" disabled>
                                            <i class="bi bi-x-circle"></i> Rejeter les recours sélectionnés
                                        </button>
                                    </div>
                                </form>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 text-end">
                                <!-- Boutons pour générer le PV et le rapport (uniquement si des recours sont validés) -->
                                <?php if ($validated_only && count($recours) > 0): ?>
                                <a href="controller/export_pv_recours.php?annee=<?= $id_annee ?>&session=<?= $id_session ?>&promotion=<?= $id_promotion ?>&type=pv" class="btn btn-primary mb-2 ms-2" target="_blank">
                                    <i class="bi bi-file-pdf"></i> Générer le PV de validation des recours
                                </a>
                                <a href="controller/export_pv_recours.php?annee=<?= $id_annee ?>&session=<?= $id_session ?>&promotion=<?= $id_promotion ?>&type=rapport" class="btn btn-info mb-2 ms-2" target="_blank">
                                    <i class="bi bi-file-pdf"></i> Générer le rapport détaillé
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Liste des recours -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?= $validated_only ? 'Recours validés par le jury' : 'Recours en attente de validation' ?>
                            <span class="badge bg-primary rounded-pill"><?= count($recours) ?></span>
                        </h5>
                        
                        <?php if (count($recours) == 0): ?>
                        <div class="alert alert-info">
                            Aucun recours trouvé pour les critères sélectionnés.
                        </div>
                        <?php else: ?>
                        <form id="formSelection">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <?php if (!$validated_only): ?>
                                            <th>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="checkAll">
                                                    <label class="form-check-label" for="checkAll"></label>
                                                </div>
                                            </th>
                                            <?php endif; ?>
                                            <th>Matricule</th>
                                            <th>Étudiant</th>
                                            <th>Promotion</th>
                                            <th>ECUE (UE)</th>
                                            <th>Session</th>
                                            <th>Motif</th>
                                            <th>Enseignant</th>
                                            <th>Notes (CC / EX)</th>
                                            <th>Date réponse</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recours as $r): 
                                            // Définir une classe CSS pour le statut
                                            $statut_class = '';
                                            switch($r['statut']) {
                                                case 'En traitement': $statut_class = 'badge bg-info'; break;
                                                case 'Approuvé': $statut_class = 'badge bg-success'; break;
                                                case 'Rejeté': $statut_class = 'badge bg-danger'; break;
                                                default: $statut_class = 'badge bg-secondary';
                                            }
                                        ?>
                                        <tr>
                                            <?php if (!$validated_only): ?>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input recours-check" type="checkbox" name="recours[]" value="<?= $r['id_reponse'] ?>">
                                                </div>
                                            </td>
                                            <?php endif; ?>
                                            <td><?= htmlspecialchars($r['matricule']) ?></td>
                                            <td><?= htmlspecialchars($r['nom_etudiant']) ?></td>
                                            <td><?= htmlspecialchars($r['designationPromotion']) ?></td>
                                            <td><?= htmlspecialchars($r['designationECUE']) ?> (<?= htmlspecialchars($r['designationUE']) ?>)</td>
                                            <td><?= htmlspecialchars($r['designSession']) ?></td>
                                            <td><?= htmlspecialchars($r['motif']) ?></td>
                                            <td><?= htmlspecialchars($r['nom_enseignant']) ?></td>
                                            <td>
                                                <?= $r['nouvelle_note_cc'] !== null ? number_format($r['nouvelle_note_cc'], 2) : '-' ?> / 
                                                <?= $r['nouvelle_note_ex'] !== null ? number_format($r['nouvelle_note_ex'], 2) : '-' ?>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($r['date_reponse'])) ?></td>
                                            <td>
                                                <a href="deliberation/recours.details?id=<?= $r['id_recours'] ?>" class="btn btn-sm btn-info mb-1">
                                                    <i class="bi bi-eye"></i> Détails
                                                </a>
                                                
                                                <?php if (!$validated_only): ?>
                                                <button type="button" class="btn btn-sm btn-success mb-1 btn-valider-unique" data-id="<?= $r['id_reponse'] ?>">
                                                    <i class="bi bi-check-circle"></i> Valider
                                                </button>
                                                
                                                <button type="button" class="btn btn-sm btn-danger mb-1 btn-rejeter-unique" data-id="<?= $r['id_reponse'] ?>">
                                                    <i class="bi bi-x-circle"></i> Rejeter
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal de confirmation pour validation/rejet en masse -->
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Confirmation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalMessage">
                Êtes-vous sûr de vouloir valider les recours sélectionnés ?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="btnConfirmer">Confirmer</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit du formulaire lors du changement des filtres
    document.getElementById('annee').addEventListener('change', function() {
        this.form.submit();
    });
    
    document.getElementById('promotion').addEventListener('change', function() {
        this.form.submit();
    });
    
    document.getElementById('session').addEventListener('change', function() {
        this.form.submit();
    });
    
    document.getElementById('statut').addEventListener('change', function() {
        this.form.submit();
    });
    
    document.getElementById('validated_only').addEventListener('change', function() {
        this.form.submit();
    });
    
    // Gestion des sélections
    const checkAll = document.getElementById('checkAll');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.recours-check');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateButtonsState();
        });
    }
    
    // Mettre à jour l'état des boutons de validation groupée
    function updateButtonsState() {
        const checkboxes = document.querySelectorAll('.recours-check:checked');
        const btnValiderSelectionnes = document.getElementById('btnValiderSelectionnes');
        const btnRejeterSelectionnes = document.getElementById('btnRejeterSelectionnes');
        
        if (btnValiderSelectionnes && btnRejeterSelectionnes) {
            const isDisabled = checkboxes.length === 0;
            btnValiderSelectionnes.disabled = isDisabled;
            btnRejeterSelectionnes.disabled = isDisabled;
        }
    }
    
    // Écouter les changements des cases à cocher individuelles
    const checkboxesIndividual = document.querySelectorAll('.recours-check');
    checkboxesIndividual.forEach(checkbox => {
        checkbox.addEventListener('change', updateButtonsState);
    });
    
    // Validation groupée
    const btnValiderSelectionnes = document.getElementById('btnValiderSelectionnes');
    if (btnValiderSelectionnes) {
        btnValiderSelectionnes.addEventListener('click', function() {
            const selectedIds = getSelectedRecours();
            if (selectedIds.length > 0) {
                showConfirmation('Validation en masse', 'Êtes-vous sûr de vouloir valider les ' + selectedIds.length + ' recours sélectionnés ?', function() {
                    document.getElementById('ids_recours').value = selectedIds.join(',');
                    document.getElementById('formValidationGroupe').action = 'controller/jury_validate_recours.php?action=valider';
                    document.getElementById('formValidationGroupe').submit();
                });
            }
        });
    }
    
    // Rejet groupé
    const btnRejeterSelectionnes = document.getElementById('btnRejeterSelectionnes');
    if (btnRejeterSelectionnes) {
        btnRejeterSelectionnes.addEventListener('click', function() {
            const selectedIds = getSelectedRecours();
            if (selectedIds.length > 0) {
                showConfirmation('Rejet en masse', 'Êtes-vous sûr de vouloir rejeter les ' + selectedIds.length + ' recours sélectionnés ?', function() {
                    document.getElementById('ids_recours').value = selectedIds.join(',');
                    document.getElementById('formValidationGroupe').action = 'controller/jury_validate_recours.php?action=rejeter';
                    document.getElementById('formValidationGroupe').submit();
                });
            }
        });
    }
    
    // Validation/rejet individuel
    const btnValiderUnique = document.querySelectorAll('.btn-valider-unique');
    btnValiderUnique.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            showConfirmation('Validation d\'un recours', 'Êtes-vous sûr de vouloir valider ce recours ?', function() {
                window.location.href = 'controller/jury_validate_recours.php?action=valider&id=' + id;
            });
        });
    });
    
    const btnRejeterUnique = document.querySelectorAll('.btn-rejeter-unique');
    btnRejeterUnique.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            showConfirmation('Rejet d\'un recours', 'Êtes-vous sûr de vouloir rejeter ce recours ?', function() {
                window.location.href = 'controller/jury_validate_recours.php?action=rejeter&id=' + id;
            });
        });
    });
    
    // Fonction pour obtenir les IDs des recours sélectionnés
    function getSelectedRecours() {
        const checkboxes = document.querySelectorAll('.recours-check:checked');
        return Array.from(checkboxes).map(checkbox => checkbox.value);
    }
    
    // Fonction pour afficher la modal de confirmation
    function showConfirmation(title, message, callback) {
        const modal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        document.getElementById('modalTitle').textContent = title;
        document.getElementById('modalMessage').textContent = message;
        
        // Réinitialiser les événements antérieurs
        const btnConfirmer = document.getElementById('btnConfirmer');
        const newBtn = btnConfirmer.cloneNode(true);
        btnConfirmer.parentNode.replaceChild(newBtn, btnConfirmer);
        
        // Ajouter le nouvel événement
        document.getElementById('btnConfirmer').addEventListener('click', function() {
            modal.hide();
            callback();
        });
        
        modal.show();
    }
    
    // Initialiser l'état des boutons
    updateButtonsState();
});
</script>

<?php include_once "./views/include/footer_file.php"; ?>

