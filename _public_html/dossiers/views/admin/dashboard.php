<?php $pageTitle = 'Administration — Espace de Scolarité'; ?>
<?php $currentPage = 'admin'; ?>
<?php require 'views/layout/header.php'; ?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--navy) 0%, var(--blue) 100%);
        padding: 32px;
        margin-bottom: 28px;
    }
    .page-header h1 {
        color: #fff;
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0;
    }
    .page-header .subtitle {
        color: var(--gray-300);
        font-size: 0.88rem;
        margin-top: 4px;
    }
    .content-body {
        padding: 0 32px 40px;
    }

    /* Filter bar */
    .filter-bar {
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        padding: 20px 24px;
        box-shadow: var(--shadow-sm);
        margin-bottom: 28px;
    }

    /* Stats grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 32px;
    }
    .stat-card {
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        padding: 24px;
        display: flex;
        align-items: center;
        gap: 18px;
        box-shadow: var(--shadow-sm);
        border-left: 4px solid var(--gray-300);
        transition: all 0.2s;
    }
    .stat-card:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }
    .stat-card .stat-icon {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    .stat-card .stat-num {
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
    }
    .stat-card .stat-label {
        font-size: 0.82rem;
        color: var(--gray-500);
        font-weight: 500;
        margin-top: 4px;
    }

    /* Quick actions */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 32px;
    }
    .action-card {
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        padding: 28px 24px;
        text-align: center;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s;
        box-shadow: var(--shadow-sm);
    }
    .action-card:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
        border-color: var(--blue-mid);
        color: inherit;
    }
    .action-card .action-icon {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        margin-bottom: 14px;
    }
    .action-card .action-title {
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--navy);
        margin-bottom: 4px;
    }
    .action-card .action-desc {
        font-size: 0.8rem;
        color: var(--gray-500);
        margin: 0;
    }

    /* Overview bar */
    .overview-card {
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        padding: 28px;
        box-shadow: var(--shadow-sm);
    }
    .overview-card h6 {
        font-weight: 700;
        color: var(--navy);
        margin-bottom: 20px;
        font-size: 0.95rem;
    }
    .overview-bar-row {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 14px;
    }
    .overview-bar-label {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--gray-600);
        width: 110px;
        flex-shrink: 0;
    }
    .overview-bar-track {
        flex: 1;
        height: 12px;
        border-radius: 6px;
        background: var(--gray-100);
        overflow: hidden;
    }
    .overview-bar-fill {
        height: 100%;
        border-radius: 6px;
        transition: width 0.6s ease;
    }
    .overview-bar-value {
        font-size: 0.82rem;
        font-weight: 700;
        width: 50px;
        text-align: right;
        flex-shrink: 0;
    }

    @media (max-width: 992px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .quick-actions { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 576px) {
        .stats-grid { grid-template-columns: 1fr; }
        .quick-actions { grid-template-columns: 1fr; }
        .page-header { padding: 24px 16px; }
        .page-header h1 { font-size: 1.25rem; }
        .content-body { padding: 0 16px 32px; }
        .filter-bar { padding: 16px; }
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-tachometer-alt me-2"></i>Dashboard Administration</h1>
    <div class="subtitle">Vue d'ensemble des dossiers de scolarité — Étudiants finalistes</div>
</div>

<div class="content-body">

    <!-- Filtre année / section -->
    <div class="filter-bar">
        <form method="GET" action="index.php" class="row g-3 align-items-end">
            <input type="hidden" name="action" value="admin">
            <div class="col-md-4 col-sm-6">
                <label class="form-label">Année académique</label>
                <select name="annee" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($annees as $a): ?>
                        <option value="<?= $a['idannee_acad'] ?>" <?= $a['idannee_acad'] == $anneeAcadId ? 'selected' : '' ?>>
                            <?= sanitize($a['designation']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-sm-6">
                <label class="form-label">Section</label>
                <select name="section" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Toutes les sections</option>
                    <?php foreach ($sections as $sec): ?>
                        <option value="<?= $sec['idsection'] ?>" <?= $sec['idsection'] == $sectionId ? 'selected' : '' ?>>
                            <?= sanitize($sec['designationSection']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-sm-12">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="fas fa-sync-alt me-1"></i>Actualiser
                </button>
            </div>
        </form>
    </div>

    <!-- Stats -->
    <?php if (!empty($stats)): ?>
    <?php
    $statItems = [
        ['label' => 'Finalistes',      'value' => $stats['total_finalistes'],   'color' => 'var(--blue-mid)',  'bg' => 'var(--blue-pale)',    'icon' => 'fas fa-users'],
        ['label' => 'Dossiers créés',  'value' => $stats['dossiers_crees'],     'color' => 'var(--gray-700)',  'bg' => 'var(--gray-100)',     'icon' => 'fas fa-folder-open'],
        ['label' => 'Soumis',          'value' => $stats['dossiers_soumis'],    'color' => 'var(--info)',      'bg' => 'var(--info-light)',   'icon' => 'fas fa-paper-plane'],
        ['label' => 'Validés',         'value' => $stats['dossiers_valides'],   'color' => 'var(--success)',   'bg' => 'var(--success-light)','icon' => 'fas fa-check-circle'],
        ['label' => 'Rejetés',         'value' => $stats['dossiers_rejetes'],   'color' => 'var(--danger)',    'bg' => 'var(--danger-light)', 'icon' => 'fas fa-times-circle'],
        ['label' => 'Complétion moy.', 'value' => $stats['moyenne_completion'] . '%', 'color' => 'var(--gold)', 'bg' => 'var(--gold-pale)', 'icon' => 'fas fa-chart-pie'],
    ];
    ?>
    <div class="stats-grid">
        <?php foreach ($statItems as $si): ?>
        <div class="stat-card" style="border-left-color: <?= $si['color'] ?>;">
            <div class="stat-icon" style="background: <?= $si['bg'] ?>; color: <?= $si['color'] ?>;">
                <i class="<?= $si['icon'] ?>"></i>
            </div>
            <div>
                <div class="stat-num" style="color: <?= $si['color'] ?>;"><?= $si['value'] ?></div>
                <div class="stat-label"><?= $si['label'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Raccourcis rapides -->
    <div class="quick-actions">
        <a href="index.php?action=admin_list&annee=<?= $anneeAcadId ?>" class="action-card">
            <div class="action-icon" style="background: var(--blue-pale); color: var(--blue-mid);">
                <i class="fas fa-users"></i>
            </div>
            <div class="action-title">Liste Étudiants</div>
            <p class="action-desc">Voir tous les dossiers finalistes</p>
        </a>
        <a href="index.php?action=admin_list&annee=<?= $anneeAcadId ?>&statut=soumis" class="action-card">
            <div class="action-icon" style="background: var(--info-light); color: var(--info);">
                <i class="fas fa-paper-plane"></i>
            </div>
            <div class="action-title">Dossiers à vérifier</div>
            <p class="action-desc">Traiter les dossiers soumis</p>
        </a>
        <a href="javascript:void(0)" onclick="ExportExcel.start('index.php?action=admin_export_excel&annee=<?= $anneeAcadId ?>&section=<?= $sectionId ?? '' ?>')" class="action-card">
            <div class="action-icon" style="background: var(--gold-pale); color: var(--gold);">
                <i class="fas fa-file-excel"></i>
            </div>
            <div class="action-title">Exporter Excel</div>
            <p class="action-desc">Rapport complet avec statistiques</p>
        </a>
    </div>

    <!-- Vue d'ensemble progression -->
    <?php if (!empty($stats) && $stats['total_finalistes'] > 0): ?>
    <div class="overview-card">
        <h6><i class="fas fa-chart-bar me-2" style="color: var(--blue-mid);"></i>Répartition des dossiers</h6>
        <?php
        $total = $stats['total_finalistes'];
        $bars = [
            ['label' => 'Soumis',    'value' => $stats['dossiers_soumis'],  'color' => 'var(--info)',    'text' => 'var(--info)'],
            ['label' => 'Validés',   'value' => $stats['dossiers_valides'], 'color' => 'var(--success)', 'text' => 'var(--success)'],
            ['label' => 'Rejetés',   'value' => $stats['dossiers_rejetes'], 'color' => 'var(--danger)',  'text' => 'var(--danger)'],
            ['label' => 'En cours',  'value' => $stats['dossiers_en_cours'],'color' => 'var(--gold)',    'text' => 'var(--warning)'],
            ['label' => 'Non démarré','value' => $total - $stats['dossiers_crees'], 'color' => 'var(--gray-400)', 'text' => 'var(--gray-500)'],
        ];
        foreach ($bars as $bar):
            $pct = $total > 0 ? round(($bar['value'] / $total) * 100) : 0;
        ?>
        <div class="overview-bar-row">
            <div class="overview-bar-label"><?= $bar['label'] ?></div>
            <div class="overview-bar-track">
                <div class="overview-bar-fill" style="width: <?= $pct ?>%; background: <?= $bar['color'] ?>;"></div>
            </div>
            <div class="overview-bar-value" style="color: <?= $bar['text'] ?>;"><?= $bar['value'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<?php require 'views/layout/footer.php'; ?>
