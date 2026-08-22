<?php $pageTitle = 'Dossier — ' . $dossier['noms']; ?>
<?php $currentPage = 'admin_detail'; ?>
<?php require 'views/layout/header.php'; ?>

<div class="container-fluid py-4 px-4">
    <!-- Page Header -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="index.php?action=admin&annee=<?= $dossier['annee_acad_idannee_acad'] ?>"
           class="d-inline-flex align-items-center justify-content-center"
           style="width:38px;height:38px;border-radius:var(--radius);background:var(--blue-pale);color:var(--blue-mid);text-decoration:none;transition:all 0.2s;flex-shrink:0;"
           title="Retour à la liste">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <nav style="font-size:0.78rem;color:var(--gray-400);">
                <a href="index.php?action=admin&annee=<?= $dossier['annee_acad_idannee_acad'] ?>" style="color:var(--blue-mid);text-decoration:none;">Gestion des dossiers</a>
                <i class="fas fa-chevron-right mx-1" style="font-size:0.6rem;"></i>
                <span style="color:var(--gray-600);">Détail</span>
            </nav>
            <h5 class="mb-0 fw-bold" style="color:var(--navy);">
                <?= sanitize($dossier['noms']) ?>
                <?php $statutLabels = ['en_cours'=>'En cours','soumis'=>'Soumis','valide'=>'Validé','rejete'=>'Rejeté','incomplet'=>'Incomplet']; ?>
                <span class="badge-status badge-<?= $dossier['statut'] ?>" style="font-size:0.72rem;vertical-align:middle;margin-left:8px;">
                    <?= $statutLabels[$dossier['statut']] ?? $dossier['statut'] ?>
                </span>
            </h5>
        </div>
    </div>

    <!-- Messages -->
    <?php if (!empty($success)): ?>
        <div class="alert alert-success d-flex align-items-center"><i class="fas fa-check-circle me-2"></i><?= sanitize($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center"><i class="fas fa-exclamation-circle me-2"></i><?= sanitize($error) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Colonne gauche -->
        <div class="col-lg-4">
            <!-- Infos étudiant -->
            <div class="card mb-4">
                <div class="card-header py-3" style="background:var(--navy);color:#fff;">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-user me-2"></i>Informations</h6>
                </div>
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <?php if (!empty($dossier['photo'])): ?>
                            <img src="../uploads/<?= sanitize($dossier['photo']) ?>" class="rounded-circle" width="72" height="72" style="object-fit:cover;border:3px solid var(--blue-pale);">
                        <?php else: ?>
                            <i class="fas fa-user-circle" style="font-size:72px;color:var(--blue-mid);"></i>
                        <?php endif; ?>
                    </div>
                    <table class="table table-sm table-borderless mb-0" style="font-size:0.85rem;">
                        <tr><td style="color:var(--gray-500);width:40%;">Nom</td><td class="fw-bold" style="color:var(--navy);"><?= sanitize($dossier['noms']) ?></td></tr>
                        <tr><td style="color:var(--gray-500);">Matricule</td><td><?= sanitize($dossier['matricule']) ?></td></tr>
                        <tr><td style="color:var(--gray-500);">Promotion</td><td><?= sanitize($dossier['designationPromotion']) ?></td></tr>
                        <tr><td style="color:var(--gray-500);">Cycle</td><td><?= sanitize($dossier['cycle']) ?></td></tr>
                        <tr><td style="color:var(--gray-500);">Orientation</td><td><?= sanitize($dossier['designationOrientation']) ?></td></tr>
                        <tr><td style="color:var(--gray-500);">Section</td><td><?= sanitize($dossier['designationSection']) ?></td></tr>
                        <tr><td style="color:var(--gray-500);">Année</td><td><?= sanitize($dossier['annee_designation']) ?></td></tr>
                    </table>
                </div>
            </div>

            <!-- Validation -->
            <div class="card">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold" style="color:var(--navy);"><i class="fas fa-check-double me-2" style="color:var(--blue-mid);"></i>Validation</h6>
                </div>
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <span class="badge-status badge-<?= $dossier['statut'] ?>" style="font-size:0.9rem;padding:8px 16px;">
                            <?= $statutLabels[$dossier['statut']] ?? $dossier['statut'] ?>
                        </span>
                        <div class="mt-2 fw-bold" style="color:var(--navy);">
                            <?= number_format($dossier['pourcentage_completion'], 0) ?>% complété
                        </div>
                        <div class="progress-track mt-2">
                            <?php $pct = $dossier['pourcentage_completion']; ?>
                            <div class="progress-fill <?= $pct >= 100 ? 'green' : ($pct >= 50 ? 'gold' : 'red') ?>" style="width:<?= $pct ?>%;"></div>
                        </div>
                    </div>

                    <form method="POST" action="index.php?action=admin_validate">
                        <input type="hidden" name="dossier_id" value="<?= $dossier['id'] ?>">
                        <input type="hidden" name="etudiant_id" value="<?= $dossier['etudiant_idetudiant'] ?>">
                        <input type="hidden" name="annee_acad_id" value="<?= $dossier['annee_acad_idannee_acad'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Nouveau statut</label>
                            <select name="statut" class="form-select form-select-sm" required>
                                <option value="">— Choisir —</option>
                                <option value="valide">&#10004; Valider</option>
                                <option value="rejete">&#10008; Rejeter</option>
                                <option value="incomplet">&#9888; Incomplet</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Commentaire</label>
                            <textarea name="commentaire" class="form-control form-control-sm" rows="3" placeholder="Message pour l'étudiant..."><?= sanitize($dossier['commentaire_admin'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-save me-1"></i>Enregistrer
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Colonne droite -->
        <div class="col-lg-8">
            <!-- Documents -->
            <?php
            $totalTypes = count($documents);
            $typesWithUpload = count(array_filter($documents, fn($d) => !empty($d['uploads'])));
            $allAdminUploads = [];
            $allPendingIds = [];
            foreach ($documents as $docType) {
                if (!empty($docType['uploads'])) {
                    foreach ($docType['uploads'] as $up) {
                        $allAdminUploads[] = ['url' => 'index.php?action=admin_download&id=' . $up['id'], 'title' => $docType['designation'] . ' — ' . $up['nom_fichier_original']];
                        if ($up['statut'] === 'en_attente') {
                            $allPendingIds[] = $up['id'];
                        }
                    }
                }
            }
            $bClasses = ['en_attente'=>'badge-en_cours','valide'=>'badge-valide','rejete'=>'badge-rejete'];
            $bLabels = ['en_attente'=>'En attente','valide'=>'Validé','rejete'=>'Rejeté'];
            ?>
            <div class="card mb-4">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="mb-0 fw-bold" style="color:var(--navy);">
                            <i class="fas fa-file-alt me-2" style="color:var(--blue-mid);"></i>Documents
                            <span style="font-size:0.78rem;font-weight:600;color:var(--gray-400);margin-left:6px;"><?= $typesWithUpload ?>/<?= $totalTypes ?> soumis</span>
                        </h6>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <?php if (count($allAdminUploads) > 1): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:0.75rem;"
                                        onclick='DocViewer.openAll(<?= json_encode($allAdminUploads, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                    <i class="fas fa-images me-1"></i>Voir tout (<?= count($allAdminUploads) ?>)
                                </button>
                            <?php endif; ?>
                            <?php if (!empty($allPendingIds)): ?>
                                <button type="button" class="btn btn-sm btn-success" style="border-radius:6px;font-size:0.75rem;"
                                        onclick="bulkValidateAll('valide')">
                                    <i class="fas fa-check-double me-1"></i>Tout valider (<?= count($allPendingIds) ?>)
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:0.75rem;"
                                        onclick="bulkValidateAll('rejete')">
                                    <i class="fas fa-times me-1"></i>Tout rejeter
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (count($allAdminUploads) > 1): ?>
                        <div class="mt-2 d-flex align-items-center gap-2" style="font-size:0.78rem;">
                            <label style="color:var(--gray-500);cursor:pointer;user-select:none;">
                                <input type="checkbox" id="selectAllDocs" style="margin-right:4px;cursor:pointer;" onchange="toggleSelectAll(this)">
                                Tout sélectionner
                            </label>
                            <span id="selectionCount" style="color:var(--blue-mid);font-weight:600;display:none;"></span>
                            <span id="selectionActions" style="display:none;" class="d-flex gap-1 ms-2">
                                <button type="button" class="btn btn-sm btn-success" style="border-radius:5px;font-size:0.72rem;padding:2px 10px;" onclick="bulkValidateSelected('valide')">
                                    <i class="fas fa-check me-1"></i>Valider la sélection
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" style="border-radius:5px;font-size:0.72rem;padding:2px 10px;" onclick="bulkValidateSelected('rejete')">
                                    <i class="fas fa-times me-1"></i>Rejeter la sélection
                                </button>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body p-3">
                    <?php foreach ($documents as $doc): ?>
                        <?php
                        $pendingInType = [];
                        if (!empty($doc['uploads'])) {
                            foreach ($doc['uploads'] as $up) {
                                if ($up['statut'] === 'en_attente') $pendingInType[] = $up['id'];
                            }
                        }
                        ?>
                        <div style="border:1px solid var(--gray-200);border-radius:var(--radius);margin-bottom:12px;overflow:hidden;">
                            <!-- En-tête type de document -->
                            <div style="background:var(--gray-50);padding:10px 16px;border-bottom:1px solid var(--gray-200);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-bold" style="font-size:0.88rem;color:var(--navy);"><?= sanitize($doc['designation']) ?></span>
                                    <?php if ($doc['est_obligatoire']): ?>
                                        <span class="badge" style="background:var(--danger-light);color:var(--danger);font-size:0.6rem;border-radius:4px;">Obligatoire</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:var(--gray-100);color:var(--gray-500);font-size:0.6rem;border-radius:4px;">Optionnel</span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($doc['uploads'])): ?>
                                        <span style="font-size:0.75rem;color:var(--gray-400);font-weight:600;"><?= count($doc['uploads']) ?> fichier(s)</span>
                                        <?php if (count($pendingInType) > 1): ?>
                                            <button type="button" class="btn btn-sm btn-outline-success" style="border-radius:5px;font-size:0.68rem;padding:2px 8px;"
                                                    onclick="bulkValidateIds(<?= json_encode($pendingInType) ?>, 'valide')" title="Valider tous les fichiers en attente de ce type">
                                                <i class="fas fa-check-double me-1"></i>Tout valider
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge-status badge-incomplet" style="font-size:0.7rem;">Non soumis</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($doc['uploads'])): ?>
                                <?php foreach ($doc['uploads'] as $i => $upload): ?>
                                    <div style="padding:10px 16px;<?= $i > 0 ? 'border-top:1px solid var(--gray-100);' : '' ?>display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                                        <div class="d-flex align-items-center gap-3" style="flex:1;min-width:200px;">
                                            <input type="checkbox" class="doc-checkbox" value="<?= $upload['id'] ?>" data-statut="<?= $upload['statut'] ?>"
                                                   onchange="updateSelectionUI()" style="cursor:pointer;width:16px;height:16px;accent-color:var(--blue-mid);flex-shrink:0;">
                                            <div>
                                                <div style="font-size:0.82rem;color:var(--navy);font-weight:500;">
                                                    <i class="fas fa-file-pdf me-1" style="color:var(--danger);"></i><?= sanitize($upload['nom_fichier_original']) ?>
                                                </div>
                                                <div style="font-size:0.75rem;color:var(--gray-400);margin-top:2px;">
                                                    <i class="fas fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($upload['date_upload'])) ?>
                                                    <span class="ms-2"><?= formatFileSize($upload['taille_fichier']) ?></span>
                                                </div>
                                                <?php if ($upload['commentaire_validation']): ?>
                                                    <div class="mt-1 p-2 rounded" style="background:<?= $upload['statut'] === 'rejete' ? 'var(--danger-light)' : 'var(--blue-50)' ?>;font-size:0.75rem;">
                                                        <i class="fas fa-comment me-1"></i><?= sanitize($upload['commentaire_validation']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge-status <?= $bClasses[$upload['statut']] ?? 'badge-incomplet' ?>" style="font-size:0.7rem;">
                                                <?= $bLabels[$upload['statut']] ?? $upload['statut'] ?>
                                            </span>
                                            <div class="d-flex gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-primary" style="border-radius:6px;" title="Voir"
                                                        onclick="DocViewer.openSingle('index.php?action=admin_download&id=<?= $upload['id'] ?>', '<?= addslashes(sanitize($upload['nom_fichier_original'])) ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-success" style="border-radius:6px;" title="Valider"
                                                        onclick="validateDoc(<?= $upload['id'] ?>, 'valide')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" style="border-radius:6px;" title="Rejeter"
                                                        onclick="validateDoc(<?= $upload['id'] ?>, 'rejete')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="padding:16px;text-align:center;color:var(--gray-400);font-size:0.82rem;">
                                    <i class="fas fa-inbox me-1"></i>Aucun fichier soumis
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Journal -->
            <div class="card">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold" style="color:var(--navy);"><i class="fas fa-history me-2" style="color:var(--blue-mid);"></i>Journal d'activité</h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($journal)): ?>
                        <p class="text-center py-4" style="color:var(--gray-400);font-size:0.85rem;">Aucune activité</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead>
                                    <tr style="background:var(--gray-50);">
                                        <th style="padding:8px 16px;">Date</th>
                                        <th>Utilisateur</th>
                                        <th>Action</th>
                                        <th>Détails</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($journal as $entry): ?>
                                        <tr>
                                            <td style="padding:8px 16px;font-size:0.8rem;color:var(--gray-500);"><?= date('d/m/Y H:i', strtotime($entry['date_action'])) ?></td>
                                            <td>
                                                <span class="badge" style="background:<?= $entry['utilisateur_type'] === 'admin' ? 'var(--navy)' : 'var(--blue-mid)' ?>;color:#fff;font-size:0.68rem;border-radius:4px;">
                                                    <?= $entry['utilisateur_type'] === 'admin' ? 'Admin' : 'Étudiant' ?>
                                                </span>
                                                <small style="color:var(--gray-600);"> <?= sanitize($entry['nom_utilisateur'] ?? '') ?></small>
                                            </td>
                                            <td><code style="font-size:0.78rem;"><?= sanitize($entry['action']) ?></code></td>
                                            <td style="font-size:0.78rem;color:var(--gray-500);"><?= sanitize($entry['details'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Formulaire validation individuelle -->
<form id="validateDocForm" method="POST" action="index.php?action=admin_validate_doc" class="d-none">
    <input type="hidden" name="document_id" id="vDocId">
    <input type="hidden" name="statut" id="vDocStatut">
    <input type="hidden" name="commentaire" id="vDocComment">
    <input type="hidden" name="etudiant_id" value="<?= $dossier['etudiant_idetudiant'] ?>">
    <input type="hidden" name="annee_acad_id" value="<?= $dossier['annee_acad_idannee_acad'] ?>">
</form>

<!-- Formulaire validation groupée -->
<form id="bulkValidateForm" method="POST" action="index.php?action=admin_validate_docs_bulk" class="d-none">
    <div id="bulkDocIds"></div>
    <input type="hidden" name="statut" id="bulkStatut">
    <input type="hidden" name="commentaire" id="bulkComment">
    <input type="hidden" name="etudiant_id" value="<?= $dossier['etudiant_idetudiant'] ?>">
    <input type="hidden" name="annee_acad_id" value="<?= $dossier['annee_acad_idannee_acad'] ?>">
</form>

<script>
// IDs des documents en attente
var allPendingIds = <?= json_encode(array_values($allPendingIds)) ?>;

// Validation individuelle
function validateDoc(docId, statut) {
    Swal.fire({
        title: statut === 'valide' ? 'Valider ce document ?' : 'Rejeter ce document ?',
        input: 'textarea',
        inputPlaceholder: statut === 'valide' ? 'Commentaire (optionnel)' : 'Raison du rejet...',
        showCancelButton: true,
        confirmButtonColor: statut === 'valide' ? '#059669' : '#dc2626',
        confirmButtonText: statut === 'valide' ? '<i class="fas fa-check me-1"></i>Valider' : '<i class="fas fa-times me-1"></i>Rejeter',
        cancelButtonText: 'Annuler'
    }).then(function(result) {
        if (result.isConfirmed) {
            document.getElementById('vDocId').value = docId;
            document.getElementById('vDocStatut').value = statut;
            document.getElementById('vDocComment').value = result.value || '';
            document.getElementById('validateDocForm').submit();
        }
    });
}

// Soumettre le formulaire bulk
function submitBulk(ids, statut, commentaire) {
    var container = document.getElementById('bulkDocIds');
    container.innerHTML = '';
    ids.forEach(function(id) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'document_ids[]';
        input.value = id;
        container.appendChild(input);
    });
    document.getElementById('bulkStatut').value = statut;
    document.getElementById('bulkComment').value = commentaire;
    document.getElementById('bulkValidateForm').submit();
}

// Tout valider / tout rejeter (tous les documents en attente)
function bulkValidateAll(statut) {
    if (allPendingIds.length === 0) return;
    var action = statut === 'valide' ? 'valider' : 'rejeter';
    Swal.fire({
        title: action.charAt(0).toUpperCase() + action.slice(1) + ' tous les documents en attente ?',
        html: '<div style="color:#64748b;font-size:0.9rem;"><strong>' + allPendingIds.length + '</strong> document(s) seront ' + action + 's.</div>',
        input: 'textarea',
        inputPlaceholder: statut === 'valide' ? 'Commentaire (optionnel)' : 'Raison du rejet...',
        showCancelButton: true,
        confirmButtonColor: statut === 'valide' ? '#059669' : '#dc2626',
        confirmButtonText: '<i class="fas fa-' + (statut === 'valide' ? 'check-double' : 'times') + ' me-1"></i>' + action.charAt(0).toUpperCase() + action.slice(1) + ' tout',
        cancelButtonText: 'Annuler'
    }).then(function(result) {
        if (result.isConfirmed) {
            submitBulk(allPendingIds, statut, result.value || '');
        }
    });
}

// Valider/rejeter une liste d'IDs spécifique (par type de document)
function bulkValidateIds(ids, statut) {
    if (!ids || ids.length === 0) return;
    var action = statut === 'valide' ? 'valider' : 'rejeter';
    Swal.fire({
        title: action.charAt(0).toUpperCase() + action.slice(1) + ' ' + ids.length + ' fichier(s) ?',
        input: 'textarea',
        inputPlaceholder: statut === 'valide' ? 'Commentaire (optionnel)' : 'Raison du rejet...',
        showCancelButton: true,
        confirmButtonColor: statut === 'valide' ? '#059669' : '#dc2626',
        confirmButtonText: '<i class="fas fa-check-double me-1"></i>' + action.charAt(0).toUpperCase() + action.slice(1),
        cancelButtonText: 'Annuler'
    }).then(function(result) {
        if (result.isConfirmed) {
            submitBulk(ids, statut, result.value || '');
        }
    });
}

// Sélection par checkbox
function toggleSelectAll(el) {
    document.querySelectorAll('.doc-checkbox').forEach(function(cb) {
        cb.checked = el.checked;
    });
    updateSelectionUI();
}

function updateSelectionUI() {
    var checked = document.querySelectorAll('.doc-checkbox:checked');
    var countEl = document.getElementById('selectionCount');
    var actionsEl = document.getElementById('selectionActions');
    var selectAllEl = document.getElementById('selectAllDocs');

    if (!countEl) return;

    if (checked.length > 0) {
        countEl.style.display = '';
        countEl.textContent = checked.length + ' sélectionné(s)';
        actionsEl.style.display = '';
    } else {
        countEl.style.display = 'none';
        actionsEl.style.display = 'none';
    }

    var total = document.querySelectorAll('.doc-checkbox');
    if (selectAllEl) {
        selectAllEl.checked = checked.length === total.length && total.length > 0;
    }
}

// Valider/rejeter les documents sélectionnés
function bulkValidateSelected(statut) {
    var checked = document.querySelectorAll('.doc-checkbox:checked');
    if (checked.length === 0) return;

    var ids = [];
    checked.forEach(function(cb) { ids.push(parseInt(cb.value)); });

    var action = statut === 'valide' ? 'valider' : 'rejeter';
    Swal.fire({
        title: action.charAt(0).toUpperCase() + action.slice(1) + ' ' + ids.length + ' document(s) sélectionné(s) ?',
        input: 'textarea',
        inputPlaceholder: statut === 'valide' ? 'Commentaire (optionnel)' : 'Raison du rejet...',
        showCancelButton: true,
        confirmButtonColor: statut === 'valide' ? '#059669' : '#dc2626',
        confirmButtonText: '<i class="fas fa-' + (statut === 'valide' ? 'check' : 'times') + ' me-1"></i>' + action.charAt(0).toUpperCase() + action.slice(1) + ' (' + ids.length + ')',
        cancelButtonText: 'Annuler'
    }).then(function(result) {
        if (result.isConfirmed) {
            submitBulk(ids, statut, result.value || '');
        }
    });
}
</script>

<?php require 'views/layout/footer.php'; ?>
