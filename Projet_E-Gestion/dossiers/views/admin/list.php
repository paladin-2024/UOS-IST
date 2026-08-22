<?php $pageTitle = 'Liste Étudiants — Espace de Scolarité'; ?>
<?php $currentPage = 'admin_list'; ?>
<?php require 'views/layout/header.php'; ?>

<style>
    .page-header {
        background: #fff;
        border-bottom: 1px solid var(--gray-200);
        padding: 28px 32px;
        margin-bottom: 24px;
    }
    .page-header .header-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    }
    .page-header h1 {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--navy);
        margin: 0;
    }
    .page-header .subtitle {
        font-size: 0.88rem;
        color: var(--gray-500);
        margin-top: 4px;
    }

    .filter-card {
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        padding: 20px 24px;
        box-shadow: var(--shadow-sm);
        margin-bottom: 24px;
    }

    .table-card {
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
    }
    .table-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 24px;
        border-bottom: 1px solid var(--gray-200);
    }
    .table-card-header h6 {
        margin: 0;
        font-weight: 700;
        color: var(--navy);
        font-size: 0.95rem;
    }
    .table-card-header .count-badge {
        background: var(--blue-pale);
        color: var(--blue-mid);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 700;
        margin-left: 10px;
    }
    .table-search-input {
        border: 1px solid var(--gray-300);
        border-radius: 8px;
        padding: 6px 12px 6px 34px;
        font-size: 0.85rem;
        width: 220px;
        background: var(--gray-50) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'/%3E%3C/svg%3E") 10px center no-repeat;
        transition: border-color 0.2s;
    }
    .table-search-input:focus {
        outline: none;
        border-color: var(--blue-mid);
        box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    }

    /* Loader */
    .scroll-loader {
        text-align: center;
        padding: 24px;
        display: none;
    }
    .scroll-loader.active { display: block; }
    .scroll-loader .spinner {
        display: inline-block;
        width: 28px;
        height: 28px;
        border: 3px solid var(--gray-200);
        border-top-color: var(--blue-mid);
        border-radius: 50%;
        animation: spin 0.7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .scroll-loader .loader-text {
        display: block;
        font-size: 0.8rem;
        color: var(--gray-400);
        margin-top: 8px;
    }

    .scroll-end {
        text-align: center;
        padding: 20px;
        font-size: 0.82rem;
        color: var(--gray-400);
        display: none;
    }
    .scroll-end.active { display: block; }

    @media (max-width: 576px) {
        .page-header { padding: 20px 16px; }
        .page-header h1 { font-size: 1.25rem; }
        .page-header .header-content { flex-direction: column; align-items: flex-start; }
        .filter-card { padding: 16px; }
        .table-card-header { flex-direction: column; gap: 12px; align-items: flex-start; }
        .table-search-input { width: 100%; }
        .content-body { padding-left: 16px; padding-right: 16px; }

        .table-responsive table thead { display: none; }
        .table-responsive table tbody tr {
            display: block;
            padding: 12px 16px;
            border-bottom: 1px solid var(--gray-200);
        }
        .table-responsive table tbody td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0 !important;
            border: none !important;
            font-size: 0.85rem;
        }
        .table-responsive table tbody td::before {
            content: attr(data-label);
            font-weight: 600;
            color: var(--gray-500);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            flex-shrink: 0;
            margin-right: 12px;
        }
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="header-content">
        <div>
            <h1><i class="fas fa-users me-2" style="color: var(--blue-mid);"></i>Liste des Étudiants</h1>
            <div class="subtitle">Tous les étudiants finalistes et leurs dossiers</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="index.php?action=admin" class="btn btn-primary">
                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
            </a>
            <button type="button" class="btn btn-gold"
                    onclick="ExportExcel.start('index.php?action=admin_export_excel&annee=<?= $anneeAcadId ?>&section=<?= $sectionId ?? '' ?>&orientation=<?= $orientationId ?? '' ?>&statut=<?= urlencode($statut ?? '') ?>')">
                <i class="fas fa-file-excel me-1"></i>Export Excel
            </button>
        </div>
    </div>
</div>

<div class="content-body" style="padding: 0 32px 32px;">

    <!-- Filtres -->
    <div class="filter-card">
        <form method="GET" action="index.php" class="row g-3 align-items-end">
            <input type="hidden" name="action" value="admin_list">
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Année académique</label>
                <select name="annee" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($annees as $a): ?>
                        <option value="<?= $a['idannee_acad'] ?>" <?= $a['idannee_acad'] == $anneeAcadId ? 'selected' : '' ?>>
                            <?= sanitize($a['designation']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Section</label>
                <select name="section" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Toutes</option>
                    <?php foreach ($sections as $sec): ?>
                        <option value="<?= $sec['idsection'] ?>" <?= $sec['idsection'] == $sectionId ? 'selected' : '' ?>>
                            <?= sanitize($sec['designationSection']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($orientations)): ?>
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Orientation</label>
                <select name="orientation" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Toutes</option>
                    <?php foreach ($orientations as $ori): ?>
                        <option value="<?= $ori['idorientation'] ?>" <?= $ori['idorientation'] == $orientationId ? 'selected' : '' ?>>
                            <?= sanitize($ori['designationOrientation']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-lg-2 col-md-4">
                <label class="form-label">Statut</label>
                <select name="statut" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    <option value="non_commence" <?= $statut === 'non_commence' ? 'selected' : '' ?>>Non commencé</option>
                    <option value="en_cours" <?= $statut === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                    <option value="soumis" <?= $statut === 'soumis' ? 'selected' : '' ?>>Soumis</option>
                    <option value="valide" <?= $statut === 'valide' ? 'selected' : '' ?>>Validé</option>
                    <option value="rejete" <?= $statut === 'rejete' ? 'selected' : '' ?>>Rejeté</option>
                </select>
            </div>
            <div class="col-lg-1 col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="fas fa-filter me-1"></i>Filtrer
                </button>
            </div>
        </form>
    </div>

    <!-- Liste étudiants -->
    <div class="table-card">
        <div class="table-card-header">
            <h6>
                <i class="fas fa-list me-2" style="color: var(--blue-mid);"></i>Étudiants finalistes
                <span class="count-badge" id="countBadge"><?= $totalEtudiants ?></span>
            </h6>
            <input type="text" class="table-search-input" id="searchStudents" placeholder="Rechercher un étudiant…">
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="studentsTable">
                <thead>
                    <tr style="background: var(--gray-50);">
                        <th style="padding: 12px 16px;">Matricule</th>
                        <th>Étudiant</th>
                        <th>Promotion</th>
                        <th>Orientation</th>
                        <th>Statut</th>
                        <th>Complétion</th>
                        <th>Soumission</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="studentsBody">
                    <?php if (empty($etudiants)): ?>
                        <tr class="empty-row"><td colspan="8" class="text-center py-5" style="color: var(--gray-400);">
                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>Aucun étudiant trouvé
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($etudiants as $etu): ?>
                            <?php echo buildStudentRow($etu, $anneeAcadId); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Loader infinite scroll -->
        <div class="scroll-loader" id="scrollLoader">
            <div class="spinner"></div>
            <span class="loader-text">Chargement...</span>
        </div>
        <div class="scroll-end" id="scrollEnd">
            <i class="fas fa-check-circle me-1"></i>Tous les étudiants ont été chargés
        </div>
    </div>

</div>

<?php
function buildStudentRow($etu, $anneeAcadId) {
    $labels = ['en_cours'=>'En cours','soumis'=>'Soumis','valide'=>'Validé','rejete'=>'Rejeté','incomplet'=>'Incomplet'];
    $pct = $etu['pourcentage_completion'] ?? 0;
    $barClass = $pct >= 100 ? 'green' : ($pct >= 50 ? 'gold' : 'red');
    $matricule = sanitize($etu['matricule']);
    $noms = sanitize($etu['noms']);
    $promotion = sanitize($etu['designationPromotion']);
    $orientation = sanitize($etu['designationOrientation']);
    $dateSoum = $etu['date_soumission'] ? date('d/m/Y', strtotime($etu['date_soumission'])) : '—';

    if ($etu['dossier_id']) {
        $statutLabel = $labels[$etu['dossier_statut']] ?? $etu['dossier_statut'];
        $statutBadge = '<span class="badge-status badge-' . $etu['dossier_statut'] . '">' . $statutLabel . '</span>';
    } else {
        $statutBadge = '<span class="badge-status badge-incomplet">Non commencé</span>';
    }

    return '<tr>
        <td data-label="Matricule" style="padding: 12px 16px;">
            <code style="background: var(--gray-100); padding: 3px 8px; border-radius: 4px; font-size: 0.82rem; color: var(--navy);">' . $matricule . '</code>
        </td>
        <td data-label="Étudiant"><strong style="color: var(--navy);">' . $noms . '</strong></td>
        <td data-label="Promotion" style="font-size: 0.85rem;">' . $promotion . '</td>
        <td data-label="Orientation" style="font-size: 0.85rem;">' . $orientation . '</td>
        <td data-label="Statut">' . $statutBadge . '</td>
        <td data-label="Complétion">
            <div class="d-flex align-items-center gap-2">
                <div class="progress-track flex-grow-1" style="min-width: 50px;">
                    <div class="progress-fill ' . $barClass . '" style="width: ' . $pct . '%;"></div>
                </div>
                <small class="fw-bold" style="color: var(--gray-600); font-size: 0.78rem;">' . number_format($pct, 0) . '%</small>
            </div>
        </td>
        <td data-label="Soumission" style="font-size: 0.82rem; color: var(--gray-500);">' . $dateSoum . '</td>
        <td data-label="">
            <a href="index.php?action=admin_detail&etudiant=' . $etu['idetudiant'] . '&annee=' . $anneeAcadId . '"
               class="btn btn-sm btn-primary" style="border-radius: 6px;">
                <i class="fas fa-eye"></i>
            </a>
        </td>
    </tr>';
}
?>

<script>
(function() {
    var state = {
        offset: <?= count($etudiants) ?>,
        total: <?= $totalEtudiants ?>,
        loading: false,
        done: <?= count($etudiants) >= $totalEtudiants ? 'true' : 'false' ?>,
        annee: <?= $anneeAcadId ?>,
        section: '<?= $sectionId ?? '' ?>',
        orientation: '<?= $orientationId ?? '' ?>',
        statut: '<?= addslashes($statut ?? '') ?>'
    };

    var tbody = document.getElementById('studentsBody');
    var loader = document.getElementById('scrollLoader');
    var endMsg = document.getElementById('scrollEnd');
    var searchInput = document.getElementById('searchStudents');
    var searchActive = false;

    if (state.done) {
        endMsg.classList.add('active');
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function buildRowHtml(etu) {
        var labels = {en_cours:'En cours',soumis:'Soumis',valide:'Validé',rejete:'Rejeté',incomplet:'Incomplet'};
        var pct = parseFloat(etu.pourcentage_completion) || 0;
        var barClass = pct >= 100 ? 'green' : (pct >= 50 ? 'gold' : 'red');
        var dateSoum = etu.date_soumission ? new Date(etu.date_soumission).toLocaleDateString('fr-FR') : '—';
        var statutBadge;

        if (etu.dossier_id) {
            var lbl = labels[etu.dossier_statut] || etu.dossier_statut;
            statutBadge = '<span class="badge-status badge-' + etu.dossier_statut + '">' + escapeHtml(lbl) + '</span>';
        } else {
            statutBadge = '<span class="badge-status badge-incomplet">Non commencé</span>';
        }

        return '<tr>' +
            '<td data-label="Matricule" style="padding:12px 16px;"><code style="background:var(--gray-100);padding:3px 8px;border-radius:4px;font-size:0.82rem;color:var(--navy);">' + escapeHtml(etu.matricule) + '</code></td>' +
            '<td data-label="Étudiant"><strong style="color:var(--navy);">' + escapeHtml(etu.noms) + '</strong></td>' +
            '<td data-label="Promotion" style="font-size:0.85rem;">' + escapeHtml(etu.designationPromotion) + '</td>' +
            '<td data-label="Orientation" style="font-size:0.85rem;">' + escapeHtml(etu.designationOrientation) + '</td>' +
            '<td data-label="Statut">' + statutBadge + '</td>' +
            '<td data-label="Complétion"><div class="d-flex align-items-center gap-2"><div class="progress-track flex-grow-1" style="min-width:50px;"><div class="progress-fill ' + barClass + '" style="width:' + pct + '%;"></div></div><small class="fw-bold" style="color:var(--gray-600);font-size:0.78rem;">' + Math.round(pct) + '%</small></div></td>' +
            '<td data-label="Soumission" style="font-size:0.82rem;color:var(--gray-500);">' + dateSoum + '</td>' +
            '<td data-label=""><a href="index.php?action=admin_detail&etudiant=' + etu.idetudiant + '&annee=' + state.annee + '" class="btn btn-sm btn-primary" style="border-radius:6px;"><i class="fas fa-eye"></i></a></td>' +
        '</tr>';
    }

    function loadMore() {
        if (state.loading || state.done || searchActive) return;
        state.loading = true;
        loader.classList.add('active');

        var url = 'index.php?action=admin_list_ajax' +
            '&annee=' + state.annee +
            '&offset=' + state.offset +
            (state.section ? '&section=' + state.section : '') +
            (state.orientation ? '&orientation=' + state.orientation : '') +
            (state.statut ? '&statut=' + encodeURIComponent(state.statut) : '');

        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(json) {
                loader.classList.remove('active');
                state.loading = false;

                if (json.data && json.data.length > 0) {
                    var emptyRow = tbody.querySelector('.empty-row');
                    if (emptyRow) emptyRow.remove();

                    var html = '';
                    for (var i = 0; i < json.data.length; i++) {
                        html += buildRowHtml(json.data[i]);
                    }
                    tbody.insertAdjacentHTML('beforeend', html);
                    state.offset += json.data.length;
                }

                if (!json.hasMore) {
                    state.done = true;
                    endMsg.classList.add('active');
                }
            })
            .catch(function() {
                loader.classList.remove('active');
                state.loading = false;
            });
    }

    // Infinite scroll - observe le bas de la table
    var sentinel = document.createElement('div');
    sentinel.style.height = '1px';
    document.getElementById('scrollLoader').parentNode.appendChild(sentinel);

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function(entries) {
            if (entries[0].isIntersecting) loadMore();
        }, { rootMargin: '200px' });
        observer.observe(sentinel);
    } else {
        // Fallback scroll
        window.addEventListener('scroll', function() {
            if ((window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 300)) {
                loadMore();
            }
        });
    }

    // Recherche locale sur les lignes chargées
    var searchTimer;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimer);
        var q = this.value.toLowerCase().trim();
        searchActive = q.length > 0;

        searchTimer = setTimeout(function() {
            var rows = tbody.querySelectorAll('tr');
            rows.forEach(function(row) {
                if (row.classList.contains('empty-row')) return;
                row.style.display = row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
            });
        }, 150);
    });
})();
</script>

<?php require 'views/layout/footer.php'; ?>
