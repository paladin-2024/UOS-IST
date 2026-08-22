<?php
include "./views/include/header.php";
require_once "./models/SuiviCours.php";

$model = new SuiviCours();

$annees      = $model->getAnneesAcad();
$anneeActive = $model->getAnneeActive();
$idannee     = isset($_GET['annee']) ? (int)$_GET['annee'] : ($anneeActive['idannee_acad'] ?? 0);
$anneeChoisie = null;
foreach ($annees as $a) {
    if ($a['idannee_acad'] == $idannee) { $anneeChoisie = $a; break; }
}

$allSections     = $model->getAllSections($idannee);
$idsectionFilter = isset($_GET['section']) ? (int)$_GET['section'] : 0;

// ── Données selon le filtre actif ──────────────────────────────────
if ($idsectionFilter && $idannee) {
    // Détail de la section filtrée
    $detailSection = $model->getDetailBySection($idsectionFilter, $idannee);

    // KPI calculés depuis le détail (évite une requête supplémentaire)
    $nbT = count(array_filter($detailSection, fn($d) => $d['statut'] === 'termine'));
    $nbE = count(array_filter($detailSection, fn($d) => $d['statut'] === 'en_cours'));
    $nbN = count(array_filter($detailSection, fn($d) => $d['statut'] === 'non_commence'));
    $statsGlobales = ['total' => count($detailSection), 'termines' => $nbT, 'en_cours' => $nbE, 'non_commences' => $nbN];

    // Graphique : avancement par promotion (dans la section)
    $statsParGraph = $model->getStatistiquesByPromotion($idsectionFilter, $idannee);
    $chartLabelKey = 'designationPromotion';

    // Tableau récap : seulement la ligne de cette section
    $statsSections = array_values(array_filter(
        $model->getStatistiquesBySection($idannee),
        fn($s) => $s['idsection'] == $idsectionFilter
    ));
    $chartTitle = 'Avancement par promotion';
} else {
    $detailSection = [];
    $statsGlobales = $idannee ? $model->getStatistiquesGlobales($idannee) : null;
    $statsSections = $idannee ? $model->getStatistiquesBySection($idannee) : [];
    $statsParGraph = $statsSections;
    $chartLabelKey = 'designationSection';
    $chartTitle    = 'Avancement par section';
}

// ── Données graphiques ─────────────────────────────────────────────
$chartLabels   = [];
$chartTermines = [];
$chartEnCours  = [];
$chartNonComm  = [];
foreach ($statsParGraph as $s) {
    $chartLabels[]   = $s[$chartLabelKey];
    $chartTermines[] = (int)$s['termines'];
    $chartEnCours[]  = (int)$s['en_cours'];
    $chartNonComm[]  = (int)$s['non_commences'];
}

$total        = (int)($statsGlobales['total']         ?? 0);
$termines     = (int)($statsGlobales['termines']      ?? 0);
$enCours      = (int)($statsGlobales['en_cours']      ?? 0);
$nonCommences = (int)($statsGlobales['non_commences'] ?? 0);
$pctGlobal    = $total > 0 ? round($termines / $total * 100) : 0;
$pctEC        = $total > 0 ? round($enCours  / $total * 100) : 0;
$pctNC        = $total > 0 ? round($nonCommences / $total * 100) : 0;

$exportParams = ['annee' => $idannee];
if ($idsectionFilter) {
    $exportParams['section'] = $idsectionFilter;
}
$exportUrl = 'controller/export_suivi_cours_tableau_bord_excel.php?' . http_build_query($exportParams);
?>

<style>
/* ── Variables de couleur ─────────────────────────────────────── */
:root {
    --sc-success : #1a9e5c;
    --sc-warning : #e8920a;
    --sc-secondary: #5c6370;
    --sc-primary : #2d5be3;
    --sc-card-radius: 12px;
}

/* ── Page title ──────────────────────────────────────────────── */
.sc-page-header {
    background: linear-gradient(135deg, #1e3a5f 0%, #2d5be3 100%);
    border-radius: var(--sc-card-radius);
    padding: 1.4rem 1.6rem;
    margin-bottom: 1.5rem;
    color: #fff;
}
.sc-page-header h1 { font-size: 1.35rem; font-weight: 700; margin-bottom: .2rem; }
.sc-page-header .breadcrumb { margin: 0; }
.sc-page-header .breadcrumb-item a  { color: rgba(255,255,255,.75); text-decoration: none; }
.sc-page-header .breadcrumb-item.active { color: rgba(255,255,255,.55); }
.sc-page-header .breadcrumb-item + .breadcrumb-item::before { color: rgba(255,255,255,.4); }

/* ── Filtre card ─────────────────────────────────────────────── */
.sc-filter-card {
    background: #f8faff;
    border: 1px solid #dde6ff;
    border-radius: var(--sc-card-radius);
    padding: .85rem 1.2rem;
    margin-bottom: 1.5rem;
}

/* ── KPI cards ───────────────────────────────────────────────── */
.kpi-card {
    border: none;
    border-radius: var(--sc-card-radius);
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    transition: transform .15s, box-shadow .15s;
    position: relative;
}
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.11); }
.kpi-card::before {
    content: '';
    position: absolute; left: 0; top: 0; bottom: 0;
    width: 5px;
    border-radius: var(--sc-card-radius) 0 0 var(--sc-card-radius);
}
.kpi-card.kpi-primary::before  { background: var(--sc-primary); }
.kpi-card.kpi-success::before  { background: var(--sc-success); }
.kpi-card.kpi-warning::before  { background: var(--sc-warning); }
.kpi-card.kpi-secondary::before{ background: var(--sc-secondary); }

.kpi-card .kpi-icon {
    width: 52px; height: 52px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
}
.kpi-card.kpi-primary  .kpi-icon { background: #e8eeff; color: var(--sc-primary); }
.kpi-card.kpi-success  .kpi-icon { background: #e4f7ee; color: var(--sc-success); }
.kpi-card.kpi-warning  .kpi-icon { background: #fff3e0; color: var(--sc-warning); }
.kpi-card.kpi-secondary .kpi-icon{ background: #f1f1f3; color: var(--sc-secondary); }

.kpi-value { font-size: 2rem; font-weight: 800; line-height: 1.1; }
.kpi-label { font-size: .78rem; color: #7a828e; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; margin-top: .15rem; }
.kpi-badge { font-size: .7rem; padding: .25em .6em; border-radius: 20px; font-weight: 600; }

/* ── Progress global ─────────────────────────────────────────── */
.sc-progress-card {
    border: none;
    border-radius: var(--sc-card-radius);
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
}
.sc-progress-stacked {
    height: 22px;
    border-radius: 11px;
    overflow: hidden;
    background: #eef0f3;
}
.sc-progress-stacked .progress-bar {
    font-size: .71rem;
    font-weight: 600;
    line-height: 22px;
    transition: width .8s ease;
}
.legend-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 5px;
}

/* ── Chart cards ─────────────────────────────────────────────── */
.sc-chart-card {
    border: none;
    border-radius: var(--sc-card-radius);
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    height: 100%;
}
.sc-chart-card .card-header {
    background: transparent;
    border-bottom: 1px solid #f0f2f5;
    padding: 1rem 1.2rem .75rem;
    font-weight: 600;
    font-size: .9rem;
    color: #1c2b4a;
}

/* ── Table sections ──────────────────────────────────────────── */
.sc-table-card {
    border: none;
    border-radius: var(--sc-card-radius);
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    overflow: hidden;
}
.sc-table-card .card-header {
    background: #f8faff;
    border-bottom: 1px solid #e5ecff;
    padding: .9rem 1.2rem;
    font-weight: 600;
    color: #1c2b4a;
}
#tableSections thead th, #tableDetail thead th {
    font-size: .74rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #5c6575;
    font-weight: 600;
    background: #f8faff;
    border-bottom: 2px solid #e5ecff;
    white-space: nowrap;
}
#tableSections tbody tr:hover { background: #f4f8ff; }
#tableDetail  tbody tr:hover  { background: #f4f8ff; }

.badge-statut {
    font-size: .72rem;
    padding: .3em .75em;
    border-radius: 20px;
    font-weight: 600;
}
.badge-termine   { background: #d4f5e4; color: #156b3e; }
.badge-en_cours  { background: #fff1cc; color: #8a5c00; }
.badge-non_commence { background: #e9eaec; color: #44505f; }

/* Progress mini */
.mini-progress {
    height: 6px;
    border-radius: 3px;
    background: #e9eaec;
    flex: 1;
    overflow: hidden;
}
.mini-progress-bar { height: 100%; background: var(--sc-success); border-radius: 3px; }

/* ── Détail section ──────────────────────────────────────────── */
.sc-detail-card {
    border: none;
    border-radius: var(--sc-card-radius);
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    overflow: hidden;
}
.sc-detail-card .card-header {
    background: linear-gradient(90deg, #f0f5ff, #f8faff);
    border-bottom: 1px solid #dde6ff;
    padding: .9rem 1.2rem;
}
.search-box {
    position: relative; max-width: 280px;
}
.search-box .bi { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9aa3af; }
.search-box input { padding-left: 32px; border-radius: 8px; border-color: #d5dce8; font-size: .85rem; }
.search-box input:focus { border-color: var(--sc-primary); box-shadow: 0 0 0 .2rem rgba(45,91,227,.15); }

/* ── Écran vide ──────────────────────────────────────────────── */
.sc-empty {
    text-align: center; padding: 3rem 1rem;
    color: #8a93a2;
}
.sc-empty i { font-size: 3rem; opacity: .35; display: block; margin-bottom: .75rem; }
</style>

<main id="main" class="main">

    <!-- En-tête de page -->
    <div class="sc-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1><i class="bi bi-bar-chart-line-fill me-2 opacity-75"></i>Suivi des cours</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                    <li class="breadcrumb-item"><a href="index.php?view=suivi_cours/chef_section">E-Suivi Cours</a></li>
                    <li class="breadcrumb-item active">Tableau de bord</li>
                </ol>
            </nav>
        </div>
        <?php if ($anneeChoisie): ?>
        <span class="px-3 py-2 d-inline-flex align-items-center gap-1" style="font-size:.85rem;border-radius:8px;background:rgba(255,255,255,.18);color:#fff;font-weight:600;border:1.5px solid rgba(255,255,255,.45);backdrop-filter:blur(4px)">
            <i class="bi bi-calendar3"></i><?= htmlspecialchars($anneeChoisie['designation']) ?>
        </span>
        <?php endif; ?>
    </div>

    <section class="section">
        <?php if (!$idannee): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2 rounded-3">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            Aucune année académique active trouvée.
        </div>
        <?php else: ?>

        <!-- ── Filtres ───────────────────────────────────────────── -->
        <div class="sc-filter-card d-flex flex-wrap gap-3 align-items-center">
            <i class="bi bi-funnel text-primary me-1"></i>
            <span class="text-muted small fw-semibold me-1">Filtres :</span>

            <form method="GET" class="d-flex align-items-center gap-2">
                <input type="hidden" name="view" value="suivi_cours/tableau_bord">
                <?php if ($idsectionFilter): ?>
                <input type="hidden" name="section" value="<?= $idsectionFilter ?>">
                <?php endif; ?>
                <label class="text-muted small fw-semibold">Année</label>
                <select name="annee" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:145px;border-radius:8px">
                    <?php foreach ($annees as $a): ?>
                    <option value="<?= $a['idannee_acad'] ?>" <?= $a['idannee_acad'] == $idannee ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['designation']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <form method="GET" class="d-flex align-items-center gap-2">
                <input type="hidden" name="view"  value="suivi_cours/tableau_bord">
                <input type="hidden" name="annee" value="<?= $idannee ?>">
                <label class="text-muted small fw-semibold">Section</label>
                <select name="section" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:170px;border-radius:8px">
                    <option value="">Toutes les sections</option>
                    <?php foreach ($allSections as $sec): ?>
                    <option value="<?= $sec['idsection'] ?>" <?= $sec['idsection'] == $idsectionFilter ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sec['designationSection']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if ($idsectionFilter): ?>
            <a href="index.php?view=suivi_cours/tableau_bord&annee=<?= $idannee ?>"
               class="btn btn-sm btn-outline-secondary" style="border-radius:8px">
                <i class="bi bi-x-lg me-1"></i>Réinitialiser
            </a>
            <?php endif; ?>

            <div class="ms-auto d-flex flex-wrap gap-2">
                <a href="<?= htmlspecialchars($exportUrl) ?>"
                   class="btn btn-sm btn-success" style="border-radius:8px">
                    <i class="bi bi-file-earmark-excel me-1"></i>Exporter Excel
                </a>
            </div>
        </div>

        <!-- ── KPI Cards ─────────────────────────────────────────── -->
        <?php if ($statsGlobales): ?>
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <div class="card kpi-card kpi-primary h-100">
                    <div class="card-body d-flex align-items-center gap-3 p-3 ps-4">
                        <div class="kpi-icon"><i class="bi bi-journal-text"></i></div>
                        <div>
                            <div class="kpi-value text-primary"><?= $total ?></div>
                            <div class="kpi-label">Total cours</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card kpi-card kpi-success h-100">
                    <div class="card-body d-flex align-items-center gap-3 p-3 ps-4">
                        <div class="kpi-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div>
                            <div class="kpi-value" style="color:var(--sc-success)"><?= $termines ?></div>
                            <div class="kpi-label">Terminés</div>
                            <span class="kpi-badge" style="background:#d4f5e4;color:#156b3e"><?= $pctGlobal ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card kpi-card kpi-warning h-100">
                    <div class="card-body d-flex align-items-center gap-3 p-3 ps-4">
                        <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
                        <div>
                            <div class="kpi-value" style="color:var(--sc-warning)"><?= $enCours ?></div>
                            <div class="kpi-label">En cours</div>
                            <span class="kpi-badge" style="background:#fff1cc;color:#8a5c00"><?= $pctEC ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card kpi-card kpi-secondary h-100">
                    <div class="card-body d-flex align-items-center gap-3 p-3 ps-4">
                        <div class="kpi-icon"><i class="bi bi-clock"></i></div>
                        <div>
                            <div class="kpi-value" style="color:var(--sc-secondary)"><?= $nonCommences ?></div>
                            <div class="kpi-label">Non commencés</div>
                            <span class="kpi-badge" style="background:#e9eaec;color:#44505f"><?= $pctNC ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Progression globale ────────────────────────────────── -->
        <div class="card sc-progress-card mb-4">
            <div class="card-body p-3 px-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold" style="color:#1c2b4a;font-size:.9rem">
                        <?php if ($idsectionFilter):
                            $sNomProg = '';
                            foreach ($allSections as $sec) { if ($sec['idsection'] == $idsectionFilter) { $sNomProg = $sec['designationSection']; break; } }
                        ?>
                        <?= htmlspecialchars($sNomProg) ?>
                        <?php else: ?>Avancement global<?php endif; ?>
                        <span class="text-muted fw-normal ms-1" style="font-size:.8rem">— <?= htmlspecialchars($anneeChoisie['designation'] ?? '') ?></span>
                    </span>
                    <span class="fw-bold" style="color:var(--sc-success);font-size:1.05rem"><?= $pctGlobal ?>% complétés</span>
                </div>
                <div class="sc-progress-stacked d-flex">
                    <?php if ($pctGlobal > 0): ?>
                    <div class="progress-bar" style="width:<?= $pctGlobal ?>%;background:var(--sc-success)">
                        <?php if ($pctGlobal > 8): ?><?= $pctGlobal ?>%<?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($pctEC > 0): ?>
                    <div class="progress-bar" style="width:<?= $pctEC ?>%;background:var(--sc-warning)">
                        <?php if ($pctEC > 8): ?><?= $pctEC ?>%<?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-3 mt-2 flex-wrap" style="font-size:.78rem;color:#7a828e">
                    <span><span class="legend-dot" style="background:var(--sc-success)"></span>Terminés (<?= $termines ?>)</span>
                    <span><span class="legend-dot" style="background:var(--sc-warning)"></span>En cours (<?= $enCours ?>)</span>
                    <span><span class="legend-dot" style="background:#e9eaec"></span>Non commencés (<?= $nonCommences ?>)</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Graphiques ─────────────────────────────────────────── -->
        <?php if (!empty($chartLabels)): ?>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card sc-chart-card">
                    <div class="card-header"><i class="bi bi-pie-chart-fill me-2 text-primary opacity-75"></i>Répartition globale</div>
                    <div class="card-body p-2">
                        <div id="chartDonut"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card sc-chart-card">
                    <div class="card-header"><i class="bi bi-bar-chart-horizontal-fill me-2 text-primary opacity-75"></i><?= htmlspecialchars($chartTitle) ?></div>
                    <div class="card-body p-2">
                        <div id="chartBarSections"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Tableau récap ──────────────────────────────────────── -->
        <div class="card sc-table-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-table me-2 text-primary opacity-75"></i>Récapitulatif par section</span>
                <span class="badge bg-primary bg-opacity-10 text-primary" style="font-size:.78rem;border-radius:8px">
                    <?= count($statsSections) ?> section<?= count($statsSections) > 1 ? 's' : '' ?>
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tableSections">
                        <thead>
                            <tr>
                                <th class="ps-3">Section</th>
                                <th class="text-center">Total</th>
                                <th class="text-center">Terminés</th>
                                <th class="text-center">En cours</th>
                                <th class="text-center">Non commencés</th>
                                <th style="min-width:160px">Progression</th>
                                <th class="text-center pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statsSections as $s):
                                $t  = (int)$s['total'];
                                $tr = (int)$s['termines'];
                                $ec = (int)$s['en_cours'];
                                $nc = (int)$s['non_commences'];
                                $p  = $t > 0 ? round($tr/$t*100) : 0;
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <span class="fw-semibold" style="color:#1c2b4a"><?= htmlspecialchars($s['designationSection']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold"><?= $t ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge-statut badge-termine"><?= $tr ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge-statut badge-en_cours"><?= $ec ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge-statut badge-non_commence"><?= $nc ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="mini-progress">
                                            <div class="mini-progress-bar" style="width:<?= $p ?>%"></div>
                                        </div>
                                        <small class="fw-semibold" style="min-width:30px;color:#44505f"><?= $p ?>%</small>
                                    </div>
                                </td>
                                <td class="text-center pe-3">
                                    <a href="index.php?view=suivi_cours/tableau_bord&annee=<?= $idannee ?>&section=<?= $s['idsection'] ?>"
                                       class="btn btn-sm btn-outline-primary" style="border-radius:8px;font-size:.78rem">
                                        <i class="bi bi-eye me-1"></i>Détail
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Détail section filtrée ─────────────────────────────── -->
        <?php if ($idsectionFilter && !empty($detailSection)):
            $sectionNom = '';
            foreach ($allSections as $sec) {
                if ($sec['idsection'] == $idsectionFilter) { $sectionNom = $sec['designationSection']; break; }
            }
            $nbTermines    = count(array_filter($detailSection, fn($d) => $d['statut'] === 'termine'));
            $nbEnCours     = count(array_filter($detailSection, fn($d) => $d['statut'] === 'en_cours'));
            $nbNonCommences= count(array_filter($detailSection, fn($d) => $d['statut'] === 'non_commence'));
        ?>
        <div class="card sc-detail-card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <i class="bi bi-layers me-2 text-primary"></i>
                    <span class="fw-semibold" style="color:#1c2b4a"><?= htmlspecialchars($sectionNom) ?></span>
                    <span class="ms-2 text-muted" style="font-size:.8rem"><?= count($detailSection) ?> cours</span>
                </div>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <span class="badge-statut badge-termine"><?= $nbTermines ?> terminé<?= $nbTermines > 1 ? 's' : '' ?></span>
                    <span class="badge-statut badge-en_cours"><?= $nbEnCours ?> en cours</span>
                    <span class="badge-statut badge-non_commence"><?= $nbNonCommences ?> non commencé<?= $nbNonCommences > 1 ? 's' : '' ?></span>
                    <a href="index.php?view=suivi_cours/chef_section&section=<?= $idsectionFilter ?>&annee=<?= $idannee ?>"
                       class="btn btn-sm btn-primary ms-2" style="border-radius:8px;font-size:.8rem">
                        <i class="bi bi-pencil me-1"></i>Mettre à jour
                    </a>
                </div>
            </div>
            <div class="card-body pt-3 pb-1 px-3">
                <div class="search-box mb-3">
                    <i class="bi bi-search"></i>
                    <input type="text" id="rechercheDetail" class="form-control form-control-sm"
                           placeholder="Filtrer par cours, UE, promotion…">
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle" id="tableDetail">
                        <thead>
                            <tr>
                                <th class="ps-2">Promotion</th>
                                <th>Semestre</th>
                                <th>UE</th>
                                <th>Cours (ECUE)</th>
                                <th class="text-center d-none d-md-table-cell">CM</th>
                                <th class="text-center d-none d-md-table-cell">TD</th>
                                <th class="text-center d-none d-md-table-cell">TP</th>
                                <th class="text-center">Statut</th>
                                <th class="text-center d-none d-lg-table-cell pe-2">MAJ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detailSection as $d):
                                $statut = $d['statut'];
                                $badgeCls = [
                                    'non_commence' => 'badge-non_commence',
                                    'en_cours'     => 'badge-en_cours',
                                    'termine'      => 'badge-termine',
                                ][$statut] ?? 'badge-non_commence';
                                $labels = [
                                    'non_commence' => 'Non commencé',
                                    'en_cours'     => 'En cours',
                                    'termine'      => 'Terminé',
                                ];
                            ?>
                            <tr class="detail-row" data-search="<?= htmlspecialchars(strtolower($d['designationPromotion'] . ' ' . $d['designationUE'] . ' ' . $d['designationECUE'])) ?>">
                                <td class="ps-2 fw-semibold" style="font-size:.83rem"><?= htmlspecialchars($d['designationPromotion']) ?></td>
                                <td style="font-size:.82rem;color:#5c6575"><?= htmlspecialchars($d['numeroSemestre']) ?></td>
                                <td style="font-size:.82rem">
                                    <?php if (!empty($d['codeUE'])): ?>
                                    <span class="badge bg-light text-muted border me-1" style="font-size:.7rem"><?= htmlspecialchars($d['codeUE']) ?></span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($d['designationUE']) ?>
                                </td>
                                <td style="font-size:.83rem"><?= htmlspecialchars($d['designationECUE']) ?></td>
                                <td class="text-center d-none d-md-table-cell" style="font-size:.8rem;color:#5c6575"><?= $d['CMI'] ?? 0 ?>h</td>
                                <td class="text-center d-none d-md-table-cell" style="font-size:.8rem;color:#5c6575"><?= $d['TD']  ?? 0 ?>h</td>
                                <td class="text-center d-none d-md-table-cell" style="font-size:.8rem;color:#5c6575"><?= $d['TP']  ?? 0 ?>h</td>
                                <td class="text-center">
                                    <span class="badge-statut <?= $badgeCls ?>"><?= $labels[$statut] ?? $statut ?></span>
                                </td>
                                <td class="text-center d-none d-lg-table-cell pe-2" style="font-size:.78rem;color:#9aa3af">
                                    <?= $d['date_mise_a_jour'] ? date('d/m/Y', strtotime($d['date_mise_a_jour'])) : '—' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php elseif ($idsectionFilter && empty($detailSection)): ?>
        <div class="card sc-detail-card">
            <div class="card-body">
                <div class="sc-empty">
                    <i class="bi bi-inbox"></i>
                    Aucun cours enregistré pour cette section dans l'année
                    <strong><?= htmlspecialchars($anneeChoisie['designation'] ?? '') ?></strong>.
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; // fin if idannee ?>
    </section>
</main>

<script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
<script>
(function () {
    // ── Donut ──────────────────────────────────────────────────────
    <?php if ($statsGlobales && $total > 0): ?>
    const donutEl = document.getElementById('chartDonut');
    if (donutEl && typeof ApexCharts !== 'undefined') {
        new ApexCharts(donutEl, {
            chart  : { type: 'donut', height: 290, fontFamily: 'inherit' },
            series : [<?= $termines ?>, <?= $enCours ?>, <?= $nonCommences ?>],
            labels : ['Terminés', 'En cours', 'Non commencés'],
            colors : ['#1a9e5c', '#e8920a', '#c4c9d4'],
            legend : { position: 'bottom', fontSize: '13px' },
            dataLabels: { style: { fontSize: '13px', fontWeight: 600 } },
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total',
                                fontSize: '14px',
                                fontWeight: 700,
                                color: '#1c2b4a',
                                formatter: () => '<?= $total ?>'
                            }
                        }
                    }
                }
            },
            stroke: { width: 2 },
        }).render();
    }
    <?php endif; ?>

    // ── Barres horizontales ────────────────────────────────────────
    <?php if (!empty($chartLabels)): ?>
    const barEl = document.getElementById('chartBarSections');
    if (barEl && typeof ApexCharts !== 'undefined') {
        const barH = Math.max(220, <?= count($chartLabels) ?> * 48 + 70);
        new ApexCharts(barEl, {
            chart  : { type: 'bar', height: barH, stacked: true, fontFamily: 'inherit', toolbar: { show: false } },
            series : [
                { name: 'Terminés',      data: <?= json_encode($chartTermines) ?> },
                { name: 'En cours',      data: <?= json_encode($chartEnCours)  ?> },
                { name: 'Non commencés', data: <?= json_encode($chartNonComm)  ?> },
            ],
            colors : ['#1a9e5c', '#e8920a', '#c4c9d4'],
            xaxis  : { categories: <?= json_encode($chartLabels) ?>, labels: { style: { fontSize: '12px' } } },
            yaxis  : { labels: { style: { fontSize: '12px' } } },
            plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } },
            legend : { position: 'bottom', fontSize: '13px' },
            dataLabels: { enabled: false },
            grid   : { borderColor: '#f0f2f5', strokeDashArray: 4 },
            tooltip: { shared: true, intersect: false },
        }).render();
    }
    <?php endif; ?>

    // ── Filtre tableau de détail ───────────────────────────────────
    const rechercheDetail = document.getElementById('rechercheDetail');
    if (rechercheDetail) {
        rechercheDetail.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('.detail-row').forEach(function (row) {
                row.style.display = !q || row.dataset.search.includes(q) ? '' : 'none';
            });
        });
    }
})();
</script>

<?php include "./views/include/footer.php"; ?>
