<?php $pageTitle = 'Mon Dossier — Espace de Scolarité'; ?>
<?php $currentPage = 'dashboard'; ?>
<?php require 'views/layout/header.php'; ?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--navy) 0%, var(--blue) 100%);
        padding: 28px 32px;
        margin-bottom: 28px;
    }
    .page-header h1 {
        color: #fff;
        font-size: 1.4rem;
        font-weight: 800;
        margin: 0;
    }
    .page-header .subtitle {
        color: var(--gray-300);
        font-size: 0.85rem;
        margin-top: 6px;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 4px;
    }
    .page-header .subtitle .matricule-tag {
        color: var(--gold);
        font-weight: 600;
    }
    .content-body {
        max-width: 1100px;
        margin: 0 auto;
        padding: 0 32px 40px;
    }

    /* ── Profile card ── */
    .profile-card {
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        padding: 20px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
    }
    .profile-icon-box {
        width: 52px;
        height: 52px;
        background: linear-gradient(135deg, var(--blue-pale), var(--blue-50));
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border: 1px solid var(--blue-pale);
    }
    .profile-icon-box i { color: var(--blue-mid); font-size: 1.3rem; }
    .profile-name {
        font-weight: 700;
        font-size: 1.05rem;
        color: var(--navy);
        margin-bottom: 6px;
    }
    .profile-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 8px;
    }
    .profile-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 3px 9px;
        border-radius: 5px;
    }
    .profile-badge-blue { background: var(--blue-pale); color: var(--blue-mid); }
    .profile-badge-gold { background: var(--gold-pale); color: var(--warning); }
    .profile-details {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 14px;
        font-size: 0.78rem;
        color: var(--gray-500);
    }
    .profile-details span {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
    }

    /* ── Progress card ── */
    .progress-card {
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .progress-circle {
        position: relative;
        width: 72px;
        height: 72px;
        flex-shrink: 0;
    }
    .progress-circle svg {
        transform: rotate(-90deg);
        width: 72px;
        height: 72px;
    }
    .progress-circle .track { fill: none; stroke: var(--gray-200); stroke-width: 6; }
    .progress-circle .fill {
        fill: none; stroke-width: 6; stroke-linecap: round;
        transition: stroke-dashoffset 0.8s ease;
    }
    .progress-circle .fill.green { stroke: var(--success); }
    .progress-circle .fill.gold { stroke: var(--gold); }
    .progress-circle .fill.red { stroke: var(--danger); }
    .progress-circle .pct-text {
        position: absolute; inset: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; font-weight: 800; color: var(--navy);
    }
    .progress-info { flex: 1; min-width: 0; }
    .progress-label { font-size: 0.78rem; color: var(--gray-500); font-weight: 500; margin-bottom: 4px; }
    .progress-track {
        height: 6px; background: var(--gray-100); border-radius: 3px; overflow: hidden; margin-top: 8px;
    }
    .progress-fill {
        height: 100%; border-radius: 3px; transition: width 0.8s ease;
    }
    .progress-fill.green { background: var(--success); }
    .progress-fill.gold { background: var(--gold); }
    .progress-fill.red { background: var(--danger); }
    .progress-completion-label { font-size: 0.7rem; color: var(--gray-400); font-weight: 500; margin-top: 4px; }

    /* ── Badge status ── */
    .badge-status {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 4px 10px; border-radius: 6px;
        font-size: 0.78rem; font-weight: 600;
    }
    .badge-en_cours { background: var(--gray-100); color: var(--gray-600); }
    .badge-soumis { background: var(--info-light); color: var(--info); }
    .badge-valide { background: var(--success-light); color: var(--success); }
    .badge-rejete { background: var(--danger-light); color: var(--danger); }
    .badge-incomplet { background: var(--warning-light); color: var(--warning); }

    /* ── Documents section ── */
    .docs-card {
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
    }
    .docs-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 20px;
        background: var(--gray-50);
        border-bottom: 1px solid var(--gray-200);
        flex-wrap: wrap;
        gap: 8px;
    }
    .docs-header h6 { margin: 0; font-weight: 700; color: var(--navy); font-size: 0.92rem; }
    .docs-count {
        font-size: 0.78rem; color: var(--gray-500); font-weight: 600;
        background: #fff; border: 1px solid var(--gray-200);
        padding: 3px 10px; border-radius: 20px; white-space: nowrap;
    }
    .docs-body { padding: 16px; }

    /* ── Document item ── */
    .doc-item {
        background: var(--gray-50);
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        padding: 14px;
        transition: all 0.2s;
    }
    .doc-item:hover { border-color: var(--blue-pale); }
    .doc-item.validated { border-left: 3px solid var(--success); }
    .doc-item.rejected { border-left: 3px solid var(--danger); }
    .doc-item.uploaded { border-left: 3px solid var(--info); }

    .doc-icon {
        width: 38px; height: 38px; border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; font-size: 0.95rem;
    }
    .doc-icon.empty { background: var(--gray-100); color: var(--gray-400); }
    .doc-icon.valid { background: var(--success-light); color: var(--success); }
    .doc-icon.pending { background: var(--info-light); color: var(--info); }
    .doc-icon.rejected { background: var(--danger-light); color: var(--danger); }

    .doc-name { font-weight: 600; font-size: 0.85rem; color: var(--navy); line-height: 1.3; }
    .doc-type-badge {
        display: inline-block; font-size: 0.63rem; font-weight: 600;
        padding: 1px 6px; border-radius: 3px; margin-top: 2px;
    }
    .doc-upload-info {
        font-size: 0.75rem; color: var(--gray-500); margin-top: 6px;
        display: flex; flex-wrap: wrap; gap: 4px 10px; align-items: center;
    }
    .doc-upload-info i { font-size: 0.7rem; }
    .doc-actions { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 6px; }

    /* ── Submit section ── */
    .submit-section {
        text-align: center;
        padding: 24px 20px;
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
    }

    /* ── Docs footer (view all) ── */
    .docs-footer {
        padding: 12px 20px;
        border-top: 1px solid var(--gray-200);
        text-align: center;
    }

    /* ── Status alerts ── */
    .status-alert {
        text-align: center;
        padding: 16px 20px;
        border-radius: var(--radius-lg);
        font-size: 0.9rem;
    }

    /* ── Responsive ── */
    @media (max-width: 992px) {
        .content-body { padding: 0 20px 36px; }
    }

    @media (max-width: 768px) {
        .page-header { padding: 22px 16px; }
        .page-header h1 { font-size: 1.2rem; }
        .content-body { padding: 0 14px 32px; }

        .profile-card { padding: 16px; gap: 12px; }
        .profile-icon-box { width: 44px; height: 44px; border-radius: 10px; }
        .profile-icon-box i { font-size: 1.1rem; }
        .profile-name { font-size: 0.95rem; }
        .profile-details { gap: 4px 10px; font-size: 0.75rem; }

        .progress-card { padding: 16px; gap: 12px; }
        .progress-circle { width: 60px; height: 60px; }
        .progress-circle svg { width: 60px; height: 60px; }
        .progress-circle .pct-text { font-size: 0.85rem; }

        .docs-body { padding: 12px; }
        .doc-item { padding: 12px; }
        .doc-icon { width: 34px; height: 34px; font-size: 0.85rem; }
        .doc-name { font-size: 0.82rem; }

        /* Stack documents to full width */
        .docs-grid { grid-template-columns: 1fr !important; }
    }

    @media (max-width: 400px) {
        .page-header { padding: 18px 12px; }
        .page-header h1 { font-size: 1.1rem; }
        .page-header .subtitle { font-size: 0.78rem; }
        .content-body { padding: 0 10px 28px; }

        .profile-card { flex-direction: column; align-items: center; text-align: center; padding: 16px 12px; }
        .profile-badges { justify-content: center; }
        .profile-details { justify-content: center; }

        .progress-card { flex-direction: column; text-align: center; }
        .progress-info { width: 100%; }

        .docs-header { padding: 12px 14px; }
        .docs-header h6 { font-size: 0.85rem; }
        .doc-item { padding: 10px; }

        .submit-section { padding: 18px 14px; }
        .submit-section .btn { padding: 12px 24px !important; font-size: 0.9rem !important; }
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-folder-open me-2" style="color:var(--gold);"></i>Mon Dossier</h1>
    <div class="subtitle">
        <i class="fas fa-user me-1"></i><?= sanitize($_SESSION['dossier_student_name']) ?>
        <span class="matricule-tag">— <?= sanitize($_SESSION['dossier_student_matricule']) ?></span>
    </div>
</div>

<div class="content-body">
    <!-- Messages -->
    <?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert" style="font-size:0.88rem;">
        <i class="fas fa-check-circle me-2"></i><?= sanitize($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert" style="font-size:0.88rem;">
        <i class="fas fa-exclamation-circle me-2"></i><?= sanitize($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Profil + Progression -->
    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="profile-card h-100">
                <div class="profile-icon-box">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <div class="profile-name"><?= sanitize($_SESSION['dossier_student_name']) ?></div>
                    <div class="profile-badges">
                        <span class="profile-badge profile-badge-blue">
                            <i class="fas fa-id-card"></i><?= sanitize($_SESSION['dossier_student_matricule']) ?>
                        </span>
                        <span class="profile-badge profile-badge-gold">
                            <i class="fas fa-graduation-cap"></i><?= sanitize($_SESSION['dossier_student_promotion']) ?>
                        </span>
                    </div>
                    <div class="profile-details">
                        <span><i class="fas fa-university"></i><?= sanitize($_SESSION['dossier_student_orientation']) ?></span>
                        <span><i class="fas fa-layer-group"></i>Cycle <?= sanitize($_SESSION['dossier_student_cycle']) ?></span>
                        <span><i class="fas fa-calendar"></i><?= sanitize($_SESSION['dossier_student_annee_designation']) ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <?php
            $pct = $dossier['pourcentage_completion'];
            $barClass = $pct >= 100 ? 'green' : ($pct >= 50 ? 'gold' : 'red');
            $radius = 28;
            $circumference = 2 * M_PI * $radius;
            $dashOffset = $circumference - ($pct / 100) * $circumference;
            ?>
            <div class="progress-card h-100">
                <div class="progress-circle">
                    <svg viewBox="0 0 72 72">
                        <circle class="track" cx="36" cy="36" r="<?= $radius ?>"/>
                        <circle class="fill <?= $barClass ?>" cx="36" cy="36" r="<?= $radius ?>"
                                stroke-dasharray="<?= number_format($circumference, 2) ?>"
                                stroke-dashoffset="<?= number_format($dashOffset, 2) ?>"/>
                    </svg>
                    <div class="pct-text"><?= number_format($pct, 0) ?>%</div>
                </div>
                <div class="progress-info">
                    <div class="progress-label">Statut du dossier</div>
                    <span class="badge-status badge-<?= $dossier['statut'] ?>">
                        <?php
                        $statutLabels = [
                            'en_cours' => '<i class="fas fa-spinner fa-spin me-1"></i>En cours',
                            'soumis' => '<i class="fas fa-paper-plane me-1"></i>Soumis',
                            'valide' => '<i class="fas fa-check-circle me-1"></i>Validé',
                            'rejete' => '<i class="fas fa-times-circle me-1"></i>Rejeté',
                            'incomplet' => '<i class="fas fa-exclamation-triangle me-1"></i>Incomplet'
                        ];
                        echo $statutLabels[$dossier['statut']] ?? $dossier['statut'];
                        ?>
                    </span>
                    <div class="progress-track">
                        <div class="progress-fill <?= $barClass ?>" style="width:<?= $pct ?>%;"></div>
                    </div>
                    <div class="progress-completion-label">Complétion du dossier</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Commentaire admin -->
    <?php if ($dossier['commentaire_admin']): ?>
    <div class="alert mb-4" style="background:var(--info-light);color:var(--info);border:1px solid #bae6fd;font-size:0.88rem;">
        <i class="fas fa-comment-alt me-2"></i><strong>Message de l'administration :</strong>
        <div class="mt-1"><?= sanitize($dossier['commentaire_admin']) ?></div>
    </div>
    <?php endif; ?>

    <!-- Documents -->
    <div class="docs-card mb-4">
        <div class="docs-header">
            <h6><i class="fas fa-file-alt me-2" style="color:var(--blue-mid);"></i>Documents requis</h6>
            <?php
            $totalDocs = count($documents);
            $uploadedDocs = count(array_filter($documents, fn($d) => !empty($d['uploads'])));
            ?>
            <span class="docs-count">
                <i class="fas fa-check-circle me-1" style="color:var(--success);"></i><?= $uploadedDocs ?>/<?= $totalDocs ?> soumis
            </span>
        </div>
        <div class="docs-body">
            <div class="docs-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">
                <?php foreach ($documents as $doc): ?>
                    <?php
                    $hasUploads = !empty($doc['uploads']);
                    $itemClass = 'doc-item';
                    $iconClass = 'doc-icon empty';
                    $icon = 'fas fa-cloud-upload-alt';

                    if ($hasUploads) {
                        $latestStatut = $doc['uploads'][0]['statut'];
                        switch ($latestStatut) {
                            case 'valide':
                                $itemClass .= ' validated';
                                $iconClass = 'doc-icon valid';
                                $icon = 'fas fa-check-circle';
                                break;
                            case 'rejete':
                                $itemClass .= ' rejected';
                                $iconClass = 'doc-icon rejected';
                                $icon = 'fas fa-times-circle';
                                break;
                            default:
                                $itemClass .= ' uploaded';
                                $iconClass = 'doc-icon pending';
                                $icon = 'fas fa-clock';
                        }
                    }
                    ?>
                    <div class="<?= $itemClass ?>">
                        <div class="d-flex gap-3">
                            <div class="<?= $iconClass ?>">
                                <i class="<?= $icon ?>"></i>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div class="doc-name"><?= sanitize($doc['designation']) ?></div>
                                <?php if ($doc['est_obligatoire']): ?>
                                    <span class="doc-type-badge" style="background:var(--danger-light);color:var(--danger);">Obligatoire</span>
                                <?php else: ?>
                                    <span class="doc-type-badge" style="background:var(--gray-100);color:var(--gray-500);">Optionnel</span>
                                <?php endif; ?>

                                <?php if ($hasUploads): ?>
                                    <?php foreach ($doc['uploads'] as $j => $upload): ?>
                                        <div class="doc-upload-info" style="<?= $j > 0 ? 'padding-top:6px;border-top:1px solid var(--gray-200);' : '' ?>">
                                            <span><i class="fas fa-file"></i><?= sanitize($upload['nom_fichier_original']) ?></span>
                                            <span><i class="fas fa-clock"></i><?= date('d/m/Y H:i', strtotime($upload['date_upload'])) ?></span>
                                        </div>

                                        <?php if ($upload['commentaire_validation']): ?>
                                        <div class="mt-1 p-2 rounded" style="background:<?= $upload['statut'] === 'rejete' ? 'var(--danger-light)' : 'var(--blue-50)' ?>;font-size:0.75rem;">
                                            <i class="fas fa-comment me-1"></i><?= sanitize($upload['commentaire_validation']) ?>
                                        </div>
                                        <?php endif; ?>

                                        <div class="doc-actions">
                                            <button type="button" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:0.72rem;padding:3px 8px;"
                                                    onclick="DocViewer.openSingle('index.php?action=view_document&id=<?= $upload['id'] ?>', '<?= addslashes(sanitize($upload['nom_fichier_original'])) ?>')">
                                                <i class="fas fa-eye me-1"></i>Voir
                                            </button>
                                            <?php if ($upload['statut'] !== 'valide' && !in_array($dossier['statut'], ['soumis', 'valide'])): ?>
                                            <form method="POST" action="index.php?action=delete_document" class="d-inline delete-form">
                                                <input type="hidden" name="document_id" value="<?= $upload['id'] ?>">
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-doc" style="border-radius:6px;font-size:0.72rem;padding:3px 8px;">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (!in_array($dossier['statut'], ['soumis', 'valide'])): ?>
                                <a href="index.php?action=upload&type=<?= $doc['id'] ?>"
                                   class="btn btn-sm <?= $hasUploads ? 'btn-outline-primary' : 'btn-primary' ?> mt-2" style="border-radius:6px;font-size:0.75rem;padding:4px 10px;">
                                    <i class="fas fa-<?= $hasUploads ? 'plus' : 'upload' ?> me-1"></i><?= $hasUploads ? 'Ajouter' : 'Uploader' ?>
                                </a>
                                <?php elseif (!$hasUploads): ?>
                                <div class="mt-2" style="font-size:0.75rem;color:var(--gray-400);">
                                    <i class="fas fa-info-circle me-1"></i>Non soumis
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        $allUploads = [];
        foreach ($documents as $docType) {
            if (!empty($docType['uploads'])) {
                foreach ($docType['uploads'] as $up) {
                    $allUploads[] = ['url' => 'index.php?action=view_document&id=' . $up['id'], 'title' => $docType['designation'] . ' — ' . $up['nom_fichier_original']];
                }
            }
        }
        ?>
        <?php if (count($allUploads) > 1): ?>
        <div class="docs-footer">
            <button type="button" class="btn btn-sm btn-outline-primary" style="border-radius:8px;font-size:0.8rem;"
                    onclick='DocViewer.openAll(<?= json_encode($allUploads, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                <i class="fas fa-images me-1"></i>Voir tous (<?= count($allUploads) ?>)
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Soumission -->
    <?php if ($dossier['statut'] === 'en_cours' || $dossier['statut'] === 'incomplet'): ?>
    <div class="submit-section">
        <form method="POST" action="index.php?action=submit_dossier" id="submitForm">
            <input type="hidden" name="dossier_id" value="<?= $dossier['id'] ?>">
            <button type="button" class="btn btn-gold btn-lg px-5 py-3" onclick="confirmSubmit()" style="font-size:1rem;">
                <i class="fas fa-paper-plane me-2"></i>Soumettre mon dossier
            </button>
        </form>
        <p class="mt-2 mb-0" style="font-size:0.78rem;color:var(--gray-400);">
            <i class="fas fa-info-circle me-1"></i>Assurez-vous que tous les documents obligatoires sont uploadés.
        </p>
    </div>
    <?php elseif ($dossier['statut'] === 'rejete'): ?>
    <div class="status-alert" style="background:var(--warning-light);color:var(--warning);border:1px solid #fde68a;">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Dossier rejeté.</strong> Corrigez les documents signalés puis re-soumettez.
    </div>
    <?php elseif ($dossier['statut'] === 'soumis'): ?>
    <div class="status-alert" style="background:var(--info-light);color:var(--info);border:1px solid #bae6fd;">
        <i class="fas fa-hourglass-half me-2"></i>
        <strong>Dossier soumis.</strong> Il est en cours de vérification par l'administration.
    </div>
    <?php elseif ($dossier['statut'] === 'valide'): ?>
    <div class="status-alert" style="background:var(--success-light);color:var(--success);border:1px solid #a7f3d0;">
        <i class="fas fa-check-circle me-2"></i>
        <strong>Dossier validé.</strong> Votre dossier est complet et approuvé.
    </div>
    <?php endif; ?>
</div>

<script>
function confirmSubmit() {
    Swal.fire({
        title: 'Confirmer la soumission ?',
        text: 'Vous ne pourrez plus modifier votre dossier tant qu\'il est en cours de vérification.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fas fa-paper-plane me-1"></i> Soumettre',
        cancelButtonText: 'Annuler'
    }).then(function(result) {
        if (result.isConfirmed) document.getElementById('submitForm').submit();
    });
}

document.querySelectorAll('.btn-delete-doc').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var form = this.closest('.delete-form');
        Swal.fire({
            title: 'Supprimer ce document ?',
            text: 'Cette action est irréversible.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="fas fa-trash me-1"></i> Supprimer',
            cancelButtonText: 'Annuler'
        }).then(function(result) {
            if (result.isConfirmed) form.submit();
        });
    });
});
</script>

<?php require 'views/layout/footer.php'; ?>
