<?php $pageTitle = 'Mes Documents — Espace de Scolarité'; ?>
<?php $currentPage = 'mes_documents'; ?>
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

    .doc-file-card {
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        background: #fff;
        overflow: hidden;
        transition: all 0.2s;
    }
    .doc-file-card:hover { border-color: var(--blue-mid); box-shadow: var(--shadow); }
    .doc-file-header {
        padding: 12px 16px;
        background: var(--gray-50);
        border-bottom: 1px solid var(--gray-200);
        display: flex; align-items: center; justify-content: space-between;
    }
    .doc-file-body {
        padding: 14px 16px;
        display: flex; align-items: center; gap: 14px;
    }
    .doc-file-icon {
        width: 44px; height: 44px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem; flex-shrink: 0;
        background: var(--danger-light); color: var(--danger);
    }
    .doc-file-actions {
        display: flex; gap: 6px; flex-shrink: 0;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--gray-400);
    }
    .empty-state i { font-size: 3rem; margin-bottom: 16px; display: block; color: var(--gray-300); }

    @media (max-width: 768px) {
        .page-header { padding: 24px 0; }
        .page-header h1 { font-size: 1.25rem; }
        .content-body { padding: 0 12px 32px; }
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="content-body" style="padding-bottom:0;">
        <h1><i class="fas fa-file-alt me-2" style="color:var(--gold);"></i>Mes Documents</h1>
        <div class="subtitle">
            Consultez et visualisez tous vos documents envoyés
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

    <?php
    // Collecter tous les uploads
    $allUploads = [];
    $totalFiles = 0;
    foreach ($documents as $docType) {
        if (!empty($docType['uploads'])) {
            foreach ($docType['uploads'] as $up) {
                $allUploads[] = [
                    'url' => 'index.php?action=view_document&id=' . $up['id'],
                    'title' => $docType['designation'] . ' — ' . $up['nom_fichier_original']
                ];
                $totalFiles++;
            }
        }
    }
    ?>

    <!-- Barre d'actions -->
    <div class="card mb-4">
        <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span style="font-size:0.88rem;font-weight:700;color:var(--navy);">
                <i class="fas fa-folder-open me-2" style="color:var(--blue-mid);"></i><?= $totalFiles ?> document(s) envoyé(s)
            </span>
            <?php if ($totalFiles > 1): ?>
                <button type="button" class="btn btn-sm btn-primary" style="border-radius:8px;"
                        onclick='DocViewer.openAll(<?= json_encode($allUploads, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                    <i class="fas fa-images me-1"></i>Voir tous les documents
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($totalFiles === 0): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h5 style="color:var(--gray-500);font-weight:700;">Aucun document envoyé</h5>
            <p style="font-size:0.88rem;">Rendez-vous dans <a href="index.php?action=upload_list" style="color:var(--blue-mid);">Soumettre documents</a> pour uploader vos fichiers.</p>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($documents as $doc): ?>
                <?php if (empty($doc['uploads'])) continue; ?>
                <div class="col-12">
                    <div class="doc-file-card">
                        <div class="doc-file-header">
                            <div>
                                <span class="fw-bold" style="font-size:0.88rem;color:var(--navy);"><?= sanitize($doc['designation']) ?></span>
                                <?php if ($doc['est_obligatoire']): ?>
                                    <span class="badge ms-1" style="background:var(--danger-light);color:var(--danger);font-size:0.6rem;">Obligatoire</span>
                                <?php else: ?>
                                    <span class="badge ms-1" style="background:var(--gray-100);color:var(--gray-500);font-size:0.6rem;">Optionnel</span>
                                <?php endif; ?>
                            </div>
                            <span style="font-size:0.75rem;color:var(--gray-400);font-weight:600;"><?= count($doc['uploads']) ?> fichier(s)</span>
                        </div>

                        <?php foreach ($doc['uploads'] as $i => $upload): ?>
                            <div class="doc-file-body" style="<?= $i > 0 ? 'border-top:1px solid var(--gray-100);' : '' ?>">
                                <div class="doc-file-icon">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div class="flex-grow-1" style="min-width:0;">
                                    <div class="fw-semibold text-truncate" style="font-size:0.85rem;color:var(--navy);">
                                        <?= sanitize($upload['nom_fichier_original']) ?>
                                    </div>
                                    <div style="font-size:0.75rem;color:var(--gray-400);margin-top:2px;">
                                        <i class="fas fa-clock me-1"></i><?= date('d/m/Y à H:i', strtotime($upload['date_upload'])) ?>
                                        <?php if (!empty($upload['taille_fichier'])): ?>
                                            <span class="ms-2"><i class="fas fa-weight-hanging me-1"></i><?= formatFileSize($upload['taille_fichier']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($upload['commentaire_validation']): ?>
                                        <div class="mt-1 p-2 rounded" style="background:<?= $upload['statut'] === 'rejete' ? 'var(--danger-light)' : 'var(--blue-50)' ?>;font-size:0.75rem;">
                                            <i class="fas fa-comment me-1"></i><?= sanitize($upload['commentaire_validation']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    <?php
                                    $statutLabels = ['en_attente' => 'En attente', 'valide' => 'Validé', 'rejete' => 'Rejeté'];
                                    $statutBadge = ['en_attente' => 'badge-en_cours', 'valide' => 'badge-valide', 'rejete' => 'badge-rejete'];
                                    ?>
                                    <span class="badge-status <?= $statutBadge[$upload['statut']] ?? 'badge-incomplet' ?>" style="font-size:0.7rem;">
                                        <?= $statutLabels[$upload['statut']] ?? $upload['statut'] ?>
                                    </span>
                                    <div class="doc-file-actions">
                                        <button type="button" class="btn btn-sm btn-outline-primary" style="border-radius:6px;" title="Visualiser"
                                                onclick="DocViewer.openSingle('index.php?action=view_document&id=<?= $upload['id'] ?>', '<?= addslashes(sanitize($upload['nom_fichier_original'])) ?>')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require 'views/layout/footer.php'; ?>
