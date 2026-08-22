<?php $pageTitle = 'Soumettre documents — Espace de Scolarité'; ?>
<?php $currentPage = 'upload_list'; ?>
<?php require 'views/layout/header.php'; ?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--navy) 0%, var(--blue) 100%);
        padding: 32px 0;
        margin-bottom: 28px;
    }
    .page-header h1 { color: #fff; font-size: 1.5rem; font-weight: 700; margin: 0; }
    .page-header .subtitle { color: var(--gray-300); font-size: 0.88rem; margin-top: 4px; }
    .content-body { max-width: 1100px; margin: 0 auto; padding: 0 20px 40px; }

    .upload-card {
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        padding: 20px;
        background: #fff;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .upload-card:hover { border-color: var(--blue-mid); box-shadow: var(--shadow); }
    .upload-card.has-upload { border-left: 4px solid var(--success); }
    .upload-card.missing { border-left: 4px solid var(--danger); }
    .upload-card.optional-empty { border-left: 4px solid var(--gray-300); }

    .upload-card-icon {
        width: 48px; height: 48px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem; flex-shrink: 0;
    }
    .upload-card-icon.done { background: var(--success-light); color: var(--success); }
    .upload-card-icon.pending { background: var(--danger-light); color: var(--danger); }
    .upload-card-icon.opt { background: var(--gray-100); color: var(--gray-400); }

    @media (max-width: 768px) {
        .page-header { padding: 24px 0; }
        .page-header h1 { font-size: 1.25rem; }
        .content-body { padding: 0 12px 32px; }
        .upload-card { flex-direction: column; text-align: center; }
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="content-body" style="padding-bottom:0;">
        <h1><i class="fas fa-upload me-2" style="color:var(--gold);"></i>Soumettre des documents</h1>
        <div class="subtitle">
            Uploadez les documents requis pour compléter votre dossier
        </div>
    </div>
</div>

<div class="content-body">
    <!-- Messages -->
    <?php if (!empty($success)): ?>
        <div class="alert alert-success d-flex align-items-center">
            <i class="fas fa-check-circle me-2"></i><?= sanitize($success) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center">
            <i class="fas fa-exclamation-circle me-2"></i><?= sanitize($error) ?>
        </div>
    <?php endif; ?>

    <?php if (in_array($dossier['statut'], ['soumis', 'valide'])): ?>
        <div class="alert" style="background:var(--info-light);color:var(--info);border:1px solid #bae6fd;">
            <i class="fas fa-lock me-2"></i>
            <strong>Dossier verrouillé.</strong> Votre dossier est <?= $dossier['statut'] === 'soumis' ? 'en cours de vérification' : 'validé' ?> et ne peut plus être modifié.
        </div>
    <?php endif; ?>

    <!-- Progression rapide -->
    <div class="card mb-4">
        <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <?php
            $pct = $dossier['pourcentage_completion'];
            $barClass = $pct >= 100 ? 'green' : ($pct >= 50 ? 'gold' : 'red');
            $totalDocs = count($documents);
            $uploadedDocs = count(array_filter($documents, fn($d) => !empty($d['uploads'])));
            ?>
            <div class="d-flex align-items-center gap-3">
                <div class="progress-track" style="width:120px;">
                    <div class="progress-fill <?= $barClass ?>" style="width:<?= $pct ?>%;"></div>
                </div>
                <span style="font-size:0.85rem;font-weight:700;color:var(--navy);"><?= number_format($pct, 0) ?>%</span>
            </div>
            <span style="font-size:0.82rem;color:var(--gray-500);font-weight:600;">
                <i class="fas fa-check-circle me-1" style="color:var(--success);"></i><?= $uploadedDocs ?>/<?= $totalDocs ?> documents soumis
            </span>
        </div>
    </div>

    <!-- Liste des types de documents -->
    <div class="row g-3">
        <?php foreach ($documents as $doc): ?>
            <?php
            $hasUploads = !empty($doc['uploads']);
            $cardClass = 'upload-card';
            $iconClass = 'upload-card-icon';
            if ($hasUploads) {
                $cardClass .= ' has-upload';
                $iconClass .= ' done';
                $iconName = 'fas fa-check-circle';
            } elseif ($doc['est_obligatoire']) {
                $cardClass .= ' missing';
                $iconClass .= ' pending';
                $iconName = 'fas fa-exclamation-circle';
            } else {
                $cardClass .= ' optional-empty';
                $iconClass .= ' opt';
                $iconName = 'fas fa-file';
            }
            ?>
            <div class="col-md-6">
                <div class="<?= $cardClass ?>">
                    <div class="<?= $iconClass ?>">
                        <i class="<?= $iconName ?>"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold" style="font-size:0.9rem;color:var(--navy);"><?= sanitize($doc['designation']) ?></div>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            <?php if ($doc['est_obligatoire']): ?>
                                <span class="badge" style="background:var(--danger-light);color:var(--danger);font-size:0.65rem;">Obligatoire</span>
                            <?php else: ?>
                                <span class="badge" style="background:var(--gray-100);color:var(--gray-500);font-size:0.65rem;">Optionnel</span>
                            <?php endif; ?>
                            <?php if ($hasUploads): ?>
                                <span class="badge" style="background:var(--success-light);color:var(--success);font-size:0.65rem;">
                                    <?= count($doc['uploads']) ?> fichier(s) envoyé(s)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <?php if (!in_array($dossier['statut'], ['soumis', 'valide'])): ?>
                            <a href="index.php?action=upload&type=<?= $doc['id'] ?>" class="btn btn-sm <?= $hasUploads ? 'btn-outline-primary' : 'btn-primary' ?>" style="border-radius:8px;">
                                <i class="fas fa-<?= $hasUploads ? 'plus' : 'upload' ?> me-1"></i><?= $hasUploads ? 'Ajouter' : 'Uploader' ?>
                            </a>
                        <?php elseif ($hasUploads): ?>
                            <span class="badge-status badge-valide" style="font-size:0.72rem;">Envoyé</span>
                        <?php else: ?>
                            <span class="badge-status badge-incomplet" style="font-size:0.72rem;">Non soumis</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Bouton soumission -->
    <?php if ($dossier['statut'] === 'en_cours' || $dossier['statut'] === 'incomplet'): ?>
        <div class="text-center mt-4">
            <form method="POST" action="index.php?action=submit_dossier" id="submitForm">
                <input type="hidden" name="dossier_id" value="<?= $dossier['id'] ?>">
                <button type="button" class="btn btn-gold btn-lg px-5 py-3" onclick="confirmSubmit()" style="font-size:1rem;">
                    <i class="fas fa-paper-plane me-2"></i>Soumettre mon dossier
                </button>
            </form>
            <p class="mt-2 mb-0" style="font-size:0.8rem;color:var(--gray-400);">
                <i class="fas fa-info-circle me-1"></i>Tous les documents obligatoires doivent être uploadés avant la soumission.
            </p>
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
    }).then((result) => {
        if (result.isConfirmed) document.getElementById('submitForm').submit();
    });
}
</script>

<?php require 'views/layout/footer.php'; ?>
