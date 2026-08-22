<?php
include "./views/include/header.php";
require_once "./models/SuiviCours.php";

$model      = new SuiviCours();
$currentUser = (int) $_SESSION['id'];

$annees      = $model->getAnneesAcad();
$anneeActive = $model->getAnneeActive();
$idannee     = isset($_GET['annee']) ? (int)$_GET['annee'] : ($anneeActive['idannee_acad'] ?? 0);
$anneeChoisie = null;
foreach ($annees as $a) {
    if ($a['idannee_acad'] == $idannee) { $anneeChoisie = $a; break; }
}

$userSections = $model->getUserSections($currentUser, $idannee);
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
if ($isAdmin) {
    $userSections = $model->getAllSections($idannee);
}

$idsection = isset($_GET['section']) ? (int)$_GET['section'] : 0;
if (!$idsection && !empty($userSections)) {
    $idsection = (int)$userSections[0]['idsection'];
}

$sectionChoisie = null;
foreach ($userSections as $sec) {
    if ($sec['idsection'] == $idsection) { $sectionChoisie = $sec; break; }
}

$rows = ($idsection && $idannee) ? $model->getSuiviBySection($idsection, $idannee) : [];

$grouped = [];
foreach ($rows as $row) {
    $pId = $row['idpromotion'];
    $sId = $row['idsemestre'];
    $uId = $row['idUE'];
    if (!isset($grouped[$pId])) {
        $grouped[$pId] = ['designation' => $row['designationPromotion'], 'cycle' => $row['cycle'], 'semestres' => []];
    }
    if (!isset($grouped[$pId]['semestres'][$sId])) {
        $grouped[$pId]['semestres'][$sId] = ['numero' => $row['numeroSemestre'], 'ues' => []];
    }
    if (!isset($grouped[$pId]['semestres'][$sId]['ues'][$uId])) {
        $grouped[$pId]['semestres'][$sId]['ues'][$uId] = ['code' => $row['codeUE'], 'designation' => $row['designationUE'], 'ecues' => []];
    }
    $grouped[$pId]['semestres'][$sId]['ues'][$uId]['ecues'][] = $row;
}

$statsPromo = $model->getStatistiquesByPromotion($idsection, $idannee);
$statsMap = [];
foreach ($statsPromo as $sp) { $statsMap[$sp['idpromotion']] = $sp; }

$statSection = ['total' => 0, 'termines' => 0, 'en_cours' => 0, 'non_commences' => 0];
foreach ($statsPromo as $sp) {
    $statSection['total']         += (int)$sp['total'];
    $statSection['termines']      += (int)$sp['termines'];
    $statSection['en_cours']      += (int)$sp['en_cours'];
    $statSection['non_commences'] += (int)$sp['non_commences'];
}
$pctSection = $statSection['total'] > 0 ? round($statSection['termines'] / $statSection['total'] * 100) : 0;
$pctEC      = $statSection['total'] > 0 ? round($statSection['en_cours']  / $statSection['total'] * 100) : 0;

$orientationsSection = $idsection ? $model->getOrientationsBySection($idsection) : [];
$promotionsSection   = ($idsection && $idannee) ? $model->getPromotionsBySection($idsection, $idannee) : [];

$exportParams = ['annee' => $idannee];
if ($idsection) {
    $exportParams['section'] = $idsection;
}
$exportUrl = 'controller/export_suivi_cours_tableau_bord_excel.php?' . http_build_query($exportParams);
?>

<style>
:root {
    --sc-success : #1a9e5c;
    --sc-warning : #e8920a;
    --sc-secondary: #5c6370;
    --sc-primary : #2d5be3;
    --sc-radius  : 12px;
}

/* ── En-tête de page ─────────────────────────────────── */
.sc-page-header {
    background: linear-gradient(135deg, #1e3a5f 0%, #2d5be3 100%);
    border-radius: var(--sc-radius);
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.4rem;
    color: #fff;
}
.sc-page-header h1 { font-size: 1.3rem; font-weight: 700; margin: 0 0 .2rem; }
.sc-page-header .breadcrumb { margin: 0; }
.sc-page-header .breadcrumb-item a { color: rgba(255,255,255,.75); text-decoration: none; }
.sc-page-header .breadcrumb-item.active { color: rgba(255,255,255,.5); }
.sc-page-header .breadcrumb-item + .breadcrumb-item::before { color: rgba(255,255,255,.35); }
.sc-annee-badge {
    padding: .45rem 1rem;
    border-radius: 8px;
    background: rgba(255,255,255,.18);
    color: #fff;
    font-weight: 600;
    font-size: .82rem;
    border: 1.5px solid rgba(255,255,255,.4);
}

/* ── Barre de filtres ────────────────────────────────── */
.sc-toolbar {
    background: #f8faff;
    border: 1px solid #dde6ff;
    border-radius: var(--sc-radius);
    padding: .75rem 1.1rem;
    margin-bottom: 1.25rem;
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
    align-items: center;
}

/* ── Tabs sections ───────────────────────────────────── */
.sc-section-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
    padding: .7rem 1rem;
    background: #f8faff;
    border: 1px solid #dde6ff;
    border-radius: var(--sc-radius);
    margin-bottom: 1.25rem;
}
.sc-section-tabs .sc-tab {
    padding: .3rem .9rem;
    border-radius: 20px;
    font-size: .82rem;
    font-weight: 500;
    text-decoration: none;
    border: 1.5px solid #d0d9f0;
    color: #5c6575;
    background: #fff;
    transition: all .15s;
    white-space: nowrap;
}
.sc-section-tabs .sc-tab:hover { border-color: var(--sc-primary); color: var(--sc-primary); }
.sc-section-tabs .sc-tab.active {
    background: var(--sc-primary);
    border-color: var(--sc-primary);
    color: #fff;
    font-weight: 600;
}

/* ── Carte KPI section ───────────────────────────────── */
.sc-global-card {
    border: none;
    border-radius: var(--sc-radius);
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    margin-bottom: 1.25rem;
}
.sc-global-card .card-body { padding: 1rem 1.25rem; }
.sc-kpi-mini { text-align: center; padding: .25rem .5rem; }
.sc-kpi-mini .val { font-size: 1.6rem; font-weight: 800; line-height: 1.1; }
.sc-kpi-mini .lbl { font-size: .7rem; color: #8a93a2; text-transform: uppercase; letter-spacing: .04em; font-weight: 500; }
.sc-progress-bar {
    height: 10px;
    border-radius: 5px;
    overflow: hidden;
    background: #e9eaec;
    display: flex;
}
.sc-progress-bar .seg { height: 100%; transition: width .6s ease; }

/* ── Barre de recherche ──────────────────────────────── */
.sc-search-wrap {
    position: relative;
    margin-bottom: 1.1rem;
}
.sc-search-wrap .bi { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9aa3af; font-size: 1rem; pointer-events: none; }
.sc-search-wrap input {
    padding-left: 36px;
    border-radius: 10px;
    border: 1.5px solid #d5dce8;
    height: 40px;
    font-size: .9rem;
    width: 100%;
    background: #fff;
}
.sc-search-wrap input:focus {
    outline: none;
    border-color: var(--sc-primary);
    box-shadow: 0 0 0 3px rgba(45,91,227,.12);
}

/* ── Accordéon promotions ────────────────────────────── */
.promo-card {
    border: none !important;
    border-radius: var(--sc-radius) !important;
    box-shadow: 0 2px 10px rgba(0,0,0,.07) !important;
    margin-bottom: 1rem !important;
    overflow: hidden;
}
.promo-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: .85rem 1.1rem;
    cursor: pointer;
    background: #fff;
    user-select: none;
    border-bottom: 2px solid transparent;
    transition: background .12s;
}
.promo-header:hover { background: #f6f9ff; }
.promo-header.collapsed .chevron { transform: rotate(0deg); }
.promo-header .chevron { transition: transform .25s; transform: rotate(180deg); color: #9aa3af; }
.promo-header.collapsed .chevron { transform: rotate(0deg); }

.cycle-badge {
    font-size: .68rem;
    padding: .2em .6em;
    border-radius: 4px;
    font-weight: 600;
    background: #e8eeff;
    color: var(--sc-primary);
    text-transform: uppercase;
    letter-spacing: .03em;
}
.promo-stat-pill {
    font-size: .72rem;
    padding: .22em .65em;
    border-radius: 20px;
    font-weight: 600;
}
.pill-termine    { background: #d4f5e4; color: #156b3e; }
.pill-en_cours   { background: #fff1cc; color: #8a5c00; }
.pill-non_comm   { background: #e9eaec; color: #44505f; }

.promo-progress-wrap { min-width: 110px; }
.promo-progress-bar {
    height: 6px;
    border-radius: 3px;
    background: #e9eaec;
    overflow: hidden;
    margin-top: 2px;
}
.promo-progress-bar .fill { height: 100%; background: var(--sc-success); border-radius: 3px; transition: width .6s; }

/* ── Semestre header ─────────────────────────────────── */
.sem-header {
    padding: .55rem 1.1rem;
    background: linear-gradient(90deg, #f0f5ff, #f8faff);
    border-top: 1px solid #e8eeff;
    border-bottom: 1px solid #e8eeff;
    display: flex;
    align-items: center;
    gap: .5rem;
    font-weight: 600;
    font-size: .82rem;
    color: #2d4070;
}
.sem-header i { color: var(--sc-primary); font-size: .9rem; }

/* ── UE row ──────────────────────────────────────────── */
.ue-row {
    padding: .45rem 1.1rem;
    display: flex;
    align-items: center;
    gap: .5rem;
    border-top: 1px solid #f0f2f5;
    background: #fafbff;
    font-size: .8rem;
    color: #5c6575;
}
.ue-code-badge {
    font-size: .68rem;
    padding: .2em .55em;
    border-radius: 4px;
    background: #e0e8ff;
    color: var(--sc-primary);
    font-weight: 700;
    white-space: nowrap;
}

/* ── Table ECUE ──────────────────────────────────────── */
.ecue-table { font-size: .83rem; }
.ecue-table thead th {
    font-size: .7rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #7a828e;
    font-weight: 600;
    background: #f8faff;
    border-bottom: 2px solid #edf0f5;
    padding: .45rem .75rem;
    white-space: nowrap;
}
.ecue-table tbody tr { transition: background .1s; }
.ecue-table tbody tr:hover { background: #f4f8ff; }
.ecue-table td { padding: .5rem .75rem; vertical-align: middle; }
.ecue-table .hours-cell { font-size: .78rem; color: #7a828e; text-align: center; }

/* ── Boutons statut ──────────────────────────────────── */
.statut-group { display: flex; gap: 0; }
.statut-group .btn-statut {
    font-size: .72rem;
    padding: .3rem .65rem;
    font-weight: 600;
    border: 1.5px solid;
    line-height: 1.3;
    transition: all .12s;
}
.statut-group .btn-statut:first-child { border-radius: 6px 0 0 6px; }
.statut-group .btn-statut:last-child  { border-radius: 0 6px 6px 0; }
.statut-group .btn-statut:not(:first-child):not(:last-child) { border-radius: 0; border-left-width: 0; border-right-width: 0; }
.statut-group .btn-statut:first-child + .btn-statut { border-left-width: 0; }

/* Couleurs actives */
.btn-statut.btn-secondary        { background:#5c6370; border-color:#5c6370; color:#fff; }
.btn-statut.btn-outline-secondary{ background:#fff; border-color:#d0d5db; color:#7a828e; }
.btn-statut.btn-warning          { background:var(--sc-warning); border-color:var(--sc-warning); color:#fff; }
.btn-statut.btn-outline-warning  { background:#fff; border-color:#e8d08a; color:#c07a00; }
.btn-statut.btn-success          { background:var(--sc-success); border-color:var(--sc-success); color:#fff; }
.btn-statut.btn-outline-success  { background:#fff; border-color:#a3d9be; color:#1a7a4a; }
.btn-statut:hover { filter: brightness(.93); }

/* ── États vides ─────────────────────────────────────── */
.sc-empty-card {
    border: 2px dashed #c8d5f0;
    border-radius: var(--sc-radius);
    background: #f8faff;
}
.sc-empty-card .card-body { padding: 3rem 1.5rem; text-align: center; }
.sc-empty-card i { font-size: 2.8rem; opacity: .3; display: block; margin-bottom: .75rem; }

/* ── Modal wizard ────────────────────────────────────── */
.wizard-steps {
    display: flex;
    gap: 0;
    margin-bottom: 1.25rem;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e0e8ff;
}
.wizard-step {
    flex: 1;
    padding: .6rem .5rem;
    text-align: center;
    font-size: .78rem;
    font-weight: 600;
    color: #8a93a2;
    background: #f8faff;
    cursor: pointer;
    border: none;
    transition: all .15s;
    position: relative;
}
.wizard-step.active { background: var(--sc-primary); color: #fff; }
.wizard-step .step-num {
    display: inline-flex;
    width: 20px; height: 20px;
    border-radius: 50%;
    align-items: center;
    justify-content: center;
    font-size: .7rem;
    margin-right: .3rem;
    background: rgba(255,255,255,.25);
}
.wizard-step:not(.active) .step-num { background: #e0e8ff; color: var(--sc-primary); }
.wizard-step + .wizard-step { border-left: 1px solid #e0e8ff; }
</style>

<main id="main" class="main">

    <!-- ── En-tête ───────────────────────────────────────────────── -->
    <div class="sc-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1><i class="bi bi-journal-check me-2 opacity-75"></i>E-Suivi Cours</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                    <li class="breadcrumb-item active">Suivi des cours</li>
                </ol>
            </nav>
        </div>
        <?php if ($anneeChoisie): ?>
        <span class="sc-annee-badge"><i class="bi bi-calendar3 me-1"></i><?= htmlspecialchars($anneeChoisie['designation']) ?></span>
        <?php endif; ?>
    </div>

    <section class="section">

        <!-- ── Barre d'outils ────────────────────────────────────── -->
        <div class="sc-toolbar">
            <i class="bi bi-sliders text-primary"></i>
            <form method="GET" class="d-flex align-items-center gap-2" id="formAnnee">
                <input type="hidden" name="view" value="suivi_cours/chef_section">
                <?php if ($idsection): ?>
                <input type="hidden" name="section" value="<?= $idsection ?>">
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
            <div class="ms-auto d-flex gap-2 align-items-center">
                <?php if ($idsection): ?>
                <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-sm btn-success" style="border-radius:8px;font-size:.8rem">
                    <i class="bi bi-file-earmark-excel me-1"></i>Exporter Excel
                </a>
                <?php endif; ?>
                <?php if ($idsection): ?>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalWizard" style="border-radius:8px;font-size:.8rem">
                    <i class="bi bi-plus-circle me-1"></i>Ajouter des cours
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Sélecteur de sections ──────────────────────────────── -->
        <?php if (count($userSections) > 1): ?>
        <div class="sc-section-tabs">
            <span class="text-muted small fw-semibold me-1" style="line-height:2">Section :</span>
            <?php foreach ($userSections as $sec): ?>
            <a href="index.php?view=suivi_cours/chef_section&section=<?= $sec['idsection'] ?>&annee=<?= $idannee ?>"
               class="sc-tab <?= $sec['idsection'] == $idsection ? 'active' : '' ?>">
                <?= htmlspecialchars($sec['designationSection']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!$idsection): ?>
        <!-- ── Aucune section ─────────────────────────────────────── -->
        <div class="sc-empty-card card">
            <div class="card-body">
                <i class="bi bi-building-x text-secondary"></i>
                <h5 class="text-muted">Aucune section affectée</h5>
                <p class="text-muted small mb-0">Vous n'êtes pas encore affecté à une section pour cette année académique.</p>
            </div>
        </div>

        <?php elseif (empty($grouped)): ?>
        <!-- ── Section vide ───────────────────────────────────────── -->
        <div class="sc-empty-card card">
            <div class="card-body">
                <i class="bi bi-journal-plus" style="color:var(--sc-primary);opacity:.35"></i>
                <h5 style="color:#1c2b4a">Aucun cours pour cette section</h5>
                <p class="text-muted mb-4">
                    <strong><?= htmlspecialchars($sectionChoisie['designationSection'] ?? '') ?></strong>
                    — <em><?= htmlspecialchars($anneeChoisie['designation'] ?? '') ?></em>
                </p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalWizard">
                    <i class="bi bi-magic me-1"></i>Configurer les cours
                </button>
            </div>
        </div>

        <?php else: ?>

        <!-- ── Carte KPI section ─────────────────────────────────── -->
        <div class="card sc-global-card" id="globalSectionCard">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold" style="font-size:.7rem;letter-spacing:.05em">Situation globale</div>
                        <div class="fw-bold" style="color:#1c2b4a;font-size:.95rem">
                            <?= htmlspecialchars($sectionChoisie['designationSection'] ?? '') ?>
                        </div>
                    </div>
                    <div class="d-flex gap-1 gap-md-3 flex-wrap align-items-center">
                        <div class="sc-kpi-mini">
                            <div class="val text-primary" data-stat="total"><?= $statSection['total'] ?></div>
                            <div class="lbl">Total</div>
                        </div>
                        <div class="sc-kpi-mini">
                            <div class="val" style="color:var(--sc-success)" data-stat="termines"><?= $statSection['termines'] ?></div>
                            <div class="lbl">Terminés</div>
                        </div>
                        <div class="sc-kpi-mini">
                            <div class="val" style="color:var(--sc-warning)" data-stat="en_cours"><?= $statSection['en_cours'] ?></div>
                            <div class="lbl">En cours</div>
                        </div>
                        <div class="sc-kpi-mini">
                            <div class="val" style="color:var(--sc-secondary)" data-stat="non_commences"><?= $statSection['non_commences'] ?></div>
                            <div class="lbl">Non commencés</div>
                        </div>
                        <div style="min-width:150px">
                            <div class="d-flex justify-content-between mb-1" style="font-size:.75rem">
                                <span class="text-muted">Avancement</span>
                                <span class="fw-bold" style="color:var(--sc-success)" data-stat="pct"><?= $pctSection ?>%</span>
                            </div>
                            <div class="sc-progress-bar">
                                <div class="seg" style="width:<?= $pctSection ?>%;background:var(--sc-success)"></div>
                                <div class="seg" style="width:<?= $pctEC ?>%;background:var(--sc-warning)"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-1" style="font-size:.68rem;color:#9aa3af">
                                <span>&#9632; Terminés</span>
                                <span>&#9632; En cours</span>
                                <span>&#9632; Non comm.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Barre de recherche ─────────────────────────────────── -->
        <div class="sc-search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" id="rechercheCours" placeholder="Rechercher un cours, UE, promotion…">
        </div>

        <!-- ── Accordéon promotions ───────────────────────────────── -->
        <div id="accordionPromotions">
            <?php $promoIndex = 0; foreach ($grouped as $pId => $promo):
                $st  = $statsMap[$pId] ?? ['total' => 0, 'termines' => 0, 'en_cours' => 0, 'non_commences' => 0];
                $pct = $st['total'] > 0 ? round($st['termines'] / $st['total'] * 100) : 0;
                $promoIndex++;
                $isOpen = $promoIndex === 1;
            ?>
            <div class="card promo-card" data-promo="<?= htmlspecialchars($promo['designation']) ?>">

                <!-- En-tête promotion -->
                <div class="promo-header <?= !$isOpen ? 'collapsed' : '' ?>"
                     data-bs-toggle="collapse"
                     data-bs-target="#promo<?= $pId ?>"
                     aria-expanded="<?= $isOpen ? 'true' : 'false' ?>">

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="fw-bold" style="color:#1c2b4a;font-size:.92rem"><?= htmlspecialchars($promo['designation']) ?></span>
                        <span class="cycle-badge"><?= htmlspecialchars($promo['cycle']) ?></span>
                        <div class="d-none d-sm-flex gap-1 ms-1">
                            <span class="promo-stat-pill pill-termine"><?= $st['termines'] ?> terminés</span>
                            <span class="promo-stat-pill pill-en_cours"><?= $st['en_cours'] ?> en cours</span>
                            <span class="promo-stat-pill pill-non_comm"><?= $st['non_commences'] ?> non comm.</span>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <div class="promo-progress-wrap d-none d-md-block">
                            <div class="d-flex justify-content-between" style="font-size:.72rem;color:#7a828e">
                                <span>Progression</span>
                                <span class="fw-semibold" style="color:var(--sc-success)"><?= $pct ?>%</span>
                            </div>
                            <div class="promo-progress-bar">
                                <div class="fill" style="width:<?= $pct ?>%"></div>
                            </div>
                        </div>
                        <i class="bi bi-chevron-down chevron"></i>
                    </div>
                </div>

                <!-- Corps : semestres → UEs → ECUEs -->
                <div id="promo<?= $pId ?>" class="collapse <?= $isOpen ? 'show' : '' ?>">
                    <?php foreach ($promo['semestres'] as $sId => $semestre): ?>

                    <div class="sem-header">
                        <i class="bi bi-layers"></i>
                        <span><?= htmlspecialchars($semestre['numero']) ?></span>
                    </div>

                    <?php foreach ($semestre['ues'] as $uId => $ue): ?>
                    <div class="ue-row">
                        <i class="bi bi-folder2-open" style="color:var(--sc-primary);font-size:.85rem"></i>
                        <?php if ($ue['code']): ?>
                        <span class="ue-code-badge"><?= htmlspecialchars($ue['code']) ?></span>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($ue['designation']) ?></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table ecue-table mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">Cours (ECUE)</th>
                                    <th class="d-none d-md-table-cell">CM</th>
                                    <th class="d-none d-md-table-cell">TD</th>
                                    <th class="d-none d-md-table-cell">TP</th>
                                    <th class="text-center">Statut</th>
                                    <th class="text-center d-none d-lg-table-cell pe-3">Dernière MAJ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ue['ecues'] as $ecue): ?>
                                <tr class="ecue-row" data-search="<?= htmlspecialchars(strtolower($ecue['designationECUE'] . ' ' . $ue['designation'] . ' ' . $promo['designation'])) ?>">
                                    <td class="ps-3 ecue-nom fw-semibold" style="color:#1c2b4a"><?= htmlspecialchars($ecue['designationECUE']) ?></td>
                                    <td class="hours-cell d-none d-md-table-cell"><?= $ecue['CMI'] ?? 0 ?>h</td>
                                    <td class="hours-cell d-none d-md-table-cell"><?= $ecue['TD']  ?? 0 ?>h</td>
                                    <td class="hours-cell d-none d-md-table-cell"><?= $ecue['TP']  ?? 0 ?>h</td>
                                    <td class="text-center">
                                        <div class="statut-group"
                                             data-idecue="<?= $ecue['idECUE'] ?>"
                                             data-idannee="<?= $idannee ?>"
                                             data-statut="<?= $ecue['statut'] ?>">
                                            <button type="button"
                                                    class="btn btn-statut btn-sm <?= $ecue['statut'] === 'non_commence' ? 'btn-secondary' : 'btn-outline-secondary' ?>"
                                                    data-statut="non_commence"
                                                    title="Non commencé">
                                                <i class="bi bi-circle"></i>
                                                <span class="d-none d-xl-inline ms-1">Non commencé</span>
                                            </button>
                                            <button type="button"
                                                    class="btn btn-statut btn-sm <?= $ecue['statut'] === 'en_cours' ? 'btn-warning' : 'btn-outline-warning' ?>"
                                                    data-statut="en_cours"
                                                    title="En cours">
                                                <i class="bi bi-play-circle"></i>
                                                <span class="d-none d-xl-inline ms-1">En cours</span>
                                            </button>
                                            <button type="button"
                                                    class="btn btn-statut btn-sm <?= $ecue['statut'] === 'termine' ? 'btn-success' : 'btn-outline-success' ?>"
                                                    data-statut="termine"
                                                    title="Terminé">
                                                <i class="bi bi-check-circle"></i>
                                                <span class="d-none d-xl-inline ms-1">Terminé</span>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="text-center d-none d-lg-table-cell pe-3" style="font-size:.75rem;color:#9aa3af">
                                        <?= $ecue['date_mise_a_jour'] ? date('d/m/Y H:i', strtotime($ecue['date_mise_a_jour'])) : '—' ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endforeach; // UEs ?>

                    <?php endforeach; // Semestres ?>
                </div>
            </div>
            <?php endforeach; // Promotions ?>
        </div>

        <?php endif; ?>
    </section>
</main>

<!-- ============================================================
     MODAL WIZARD
     ============================================================ -->
<div class="modal fade" id="modalWizard" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none">
            <div class="modal-header" style="background:linear-gradient(135deg,#1e3a5f,#2d5be3);color:#fff;border:none">
                <h5 class="modal-title fw-bold"><i class="bi bi-magic me-2"></i>Configurer les cours</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">

                <!-- Indicateur d'étapes -->
                <div class="wizard-steps mb-0" id="wizardTabs" role="tablist">
                    <button class="wizard-step active" role="tab" aria-selected="true"
                            data-bs-toggle="tab" data-bs-target="#tabPromotion"
                            id="tabPromotionLink" type="button">
                        <span class="step-num">1</span>Promotion
                    </button>
                    <button class="wizard-step" role="tab" aria-selected="false"
                            data-bs-toggle="tab" data-bs-target="#tabUE"
                            id="tabUELink" type="button">
                        <span class="step-num">2</span>UE
                    </button>
                    <button class="wizard-step" role="tab" aria-selected="false"
                            data-bs-toggle="tab" data-bs-target="#tabECUE"
                            id="tabECUELink" type="button">
                        <span class="step-num">3</span>Cours
                    </button>
                </div>

                <div class="tab-content pt-4">

                    <!-- ÉTAPE 1 -->
                    <div class="tab-pane fade show active" id="tabPromotion">
                        <form id="formPromotion" method="post" action="javascript:void(0)">
                            <input type="hidden" name="idsection" value="<?= $idsection ?>">
                            <input type="hidden" name="idannee"   value="<?= $idannee ?>">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold" style="font-size:.85rem">Nom de la promotion <span class="text-danger">*</span></label>
                                    <input type="text" name="designationPromotion" class="form-control" placeholder="ex : L1 Informatique" required style="border-radius:8px">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" style="font-size:.85rem">Cycle <span class="text-danger">*</span></label>
                                    <select name="cycle" class="form-select" required style="border-radius:8px">
                                        <option value="">Choisir…</option>
                                        <option value="Premier">Licence (L)</option>
                                        <option value="Deuxieme">Master (M)</option>
                                        <option value="Troisieme">Doctorat (D)</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label fw-semibold mb-0" style="font-size:.85rem">Orientation / Département</label>
                                        <button type="button" class="btn btn-link btn-sm p-0 text-primary" id="btnNouvelleOrientation" style="font-size:.8rem">
                                            <i class="bi bi-plus-circle"></i> Nouvelle orientation
                                        </button>
                                    </div>
                                    <select name="idorientation" class="form-select" id="selectOrientation" style="border-radius:8px">
                                        <option value="">— Choisir une orientation —</option>
                                        <?php foreach ($orientationsSection as $ori): ?>
                                        <option value="<?= $ori['idorientation'] ?>"><?= htmlspecialchars($ori['designationOrientation']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="panelNouvelleOrientation" class="d-none mt-2">
                                        <div class="input-group" style="border-radius:8px;overflow:hidden">
                                            <input type="text" id="inputNouvelleOrientation" class="form-control" placeholder="Nom de la nouvelle orientation…" autocomplete="off">
                                            <button type="button" class="btn btn-success" id="btnCreerOrientation"><i class="bi bi-check-lg"></i> Créer</button>
                                            <button type="button" class="btn btn-outline-secondary" id="btnAnnulerOrientation"><i class="bi bi-x-lg"></i></button>
                                        </div>
                                        <div id="alertOrientation" class="mt-1"></div>
                                    </div>
                                    <input type="hidden" name="nouvelleOrientation" value="">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" style="font-size:.85rem">Semestres à créer</label>
                                    <input type="text" name="semestres" class="form-control" placeholder="ex : 1, 2" value="1, 2" style="border-radius:8px">
                                    <small class="text-muted">Numéros séparés par des virgules</small>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="est_terminale" value="1" id="chkTerminale">
                                        <label class="form-check-label" for="chkTerminale" style="font-size:.85rem">Promotion terminale</label>
                                    </div>
                                </div>
                                <div class="col-12 d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-outline-secondary" id="btnPromoExistantes" style="border-radius:8px">
                                        <i class="bi bi-journal-bookmark me-1"></i>Les promotions existent déjà
                                    </button>
                                    <button type="button" class="btn btn-primary" id="btnSavePromotion" style="border-radius:8px">
                                        <i class="bi bi-arrow-right-circle me-1"></i>Créer et passer aux UE
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div id="alertPromotion" class="mt-3"></div>
                        <div id="promotionsCrees" class="mt-2"></div>
                    </div>

                    <!-- ÉTAPE 2 -->
                    <div class="tab-pane fade" id="tabUE">
                        <form id="formUE">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" style="font-size:.85rem">Promotion <span class="text-danger">*</span></label>
                                    <select name="idpromotion" class="form-select" id="selectPromotionUE" style="border-radius:8px">
                                        <option value="">— Sélectionner une promotion —</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" style="font-size:.85rem">Semestre <span class="text-danger">*</span></label>
                                    <select name="idsemestre" class="form-select" id="selectSemestreUE" disabled style="border-radius:8px">
                                        <option value="">— D'abord choisir une promotion —</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" style="font-size:.85rem">Code UE <span class="text-danger">*</span></label>
                                    <input type="text" name="codeUE" class="form-control" placeholder="ex : MATH101" style="border-radius:8px">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold" style="font-size:.85rem">Désignation UE <span class="text-danger">*</span></label>
                                    <input type="text" name="designationUE" class="form-control" placeholder="ex : Mathématiques générales" style="border-radius:8px">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" style="font-size:.85rem">Description</label>
                                    <input type="text" name="description" class="form-control" placeholder="Optionnel" style="border-radius:8px">
                                </div>
                                <div class="col-12 d-flex justify-content-end">
                                    <button type="button" class="btn btn-primary" id="btnSaveUE" style="border-radius:8px">
                                        <i class="bi bi-arrow-right-circle me-1"></i>Créer et passer aux cours
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div id="alertUE" class="mt-3"></div>
                        <div id="uesCrees" class="mt-2"></div>
                    </div>

                    <!-- ÉTAPE 3 -->
                    <div class="tab-pane fade" id="tabECUE">
                        <form id="formECUE">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold" style="font-size:.85rem">UE parente <span class="text-danger">*</span></label>
                                    <select name="idUE" class="form-select" id="selectUEECUE" style="border-radius:8px">
                                        <option value="">— Sélectionner une UE —</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" style="font-size:.85rem">Nom du cours (ECUE) <span class="text-danger">*</span></label>
                                    <input type="text" name="designationECUE" class="form-control" placeholder="ex : Algèbre linéaire" style="border-radius:8px">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" style="font-size:.85rem">Heures CM</label>
                                    <input type="number" name="CMI" class="form-control" value="0" min="0" step="0.5" style="border-radius:8px">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" style="font-size:.85rem">Heures TD</label>
                                    <input type="number" name="TD" class="form-control" value="0" min="0" step="0.5" style="border-radius:8px">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" style="font-size:.85rem">Heures TP</label>
                                    <input type="number" name="TP" class="form-control" value="0" min="0" step="0.5" style="border-radius:8px">
                                </div>
                                <div class="col-12 d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-success" id="btnSaveECUE" style="border-radius:8px">
                                        <i class="bi bi-plus-circle me-1"></i>Ajouter ce cours
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="btnTerminerWizard" style="border-radius:8px">
                                        <i class="bi bi-check-lg me-1"></i>Terminer
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div id="alertECUE" class="mt-3"></div>
                        <div id="ecuesCrees" class="mt-2"></div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    // Synchroniser la classe active et aria-selected des wizard-steps avec Bootstrap
    document.getElementById('wizardTabs').addEventListener('shown.bs.tab', function(e) {
        document.querySelectorAll('#wizardTabs .wizard-step').forEach(function(b) {
            const isActive = b === e.target;
            b.classList.toggle('active', isActive);
            b.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    });

    // ===========================================================
    // 1. Changement de statut AJAX
    // ===========================================================
    document.querySelectorAll('.statut-group').forEach(function (group) {
        group.querySelectorAll('.btn-statut').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const idECUE  = group.dataset.idecue;
                const idannee = group.dataset.idannee;
                const statut  = btn.dataset.statut;

                const fd = new FormData();
                fd.append('idECUE',  idECUE);
                fd.append('idannee', idannee);
                fd.append('statut',  statut);

                setGroupStatut(group, statut);

                fetch('controller/updateSuiviCours.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(function (data) {
                        if (data.success) {
                            toastOK(data.message);
                            updateMAJ(group);
                            refreshProgressBar(group);
                        } else {
                            toastErr(data.message);
                        }
                    })
                    .catch(function () { toastErr('Erreur réseau'); });
            });
        });
    });

    function setGroupStatut(group, statut) {
        const classes = {
            non_commence : ['btn-secondary',     'btn-outline-warning', 'btn-outline-success'],
            en_cours     : ['btn-outline-secondary', 'btn-warning',      'btn-outline-success'],
            termine      : ['btn-outline-secondary', 'btn-outline-warning', 'btn-success'],
        };
        const btns = group.querySelectorAll('.btn-statut');
        btns.forEach(function (b, i) {
            b.className = 'btn btn-statut btn-sm ' + classes[statut][i];
        });
        group.dataset.statut = statut;
    }

    function updateMAJ(group) {
        const row = group.closest('tr');
        if (!row) return;
        const cell = row.querySelector('td:last-child');
        if (cell) {
            const now = new Date();
            cell.textContent = now.toLocaleDateString('fr-FR') + ' ' +
                now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        }
    }

    function refreshProgressBar(group) {
        const card = group.closest('.promo-card');
        if (!card) return;
        const ecueRows = card.querySelectorAll('.statut-group');
        let total = ecueRows.length, termines = 0, enCours = 0;
        ecueRows.forEach(function (g) {
            if (g.dataset.statut === 'termine')  termines++;
            if (g.dataset.statut === 'en_cours') enCours++;
        });
        const pct = total > 0 ? Math.round(termines / total * 100) : 0;

        // Barre de progression dans l'en-tête
        const fill = card.querySelector('.promo-progress-bar .fill');
        if (fill) fill.style.width = pct + '%';

        // Pourcentage texte
        const pctEls = card.querySelectorAll('.promo-progress-wrap span:last-child');
        pctEls.forEach(el => { if (el.style.color) el.textContent = pct + '%'; });

        // Pills de statut
        const pills = card.querySelectorAll('.promo-stat-pill');
        const nonComm = total - termines - enCours;
        pills.forEach(function(pill) {
            if (pill.classList.contains('pill-termine'))  pill.textContent = termines + ' terminés';
            if (pill.classList.contains('pill-en_cours')) pill.textContent = enCours  + ' en cours';
            if (pill.classList.contains('pill-non_comm')) pill.textContent = nonComm  + ' non comm.';
        });

        refreshSectionGlobale();
    }

    function refreshSectionGlobale() {
        const allGroups = document.querySelectorAll('.statut-group');
        let total = allGroups.length, termines = 0, enCours = 0;
        allGroups.forEach(function (g) {
            if (g.dataset.statut === 'termine')  termines++;
            if (g.dataset.statut === 'en_cours') enCours++;
        });
        const nonComm = total - termines - enCours;
        const pct     = total > 0 ? Math.round(termines / total * 100) : 0;
        const pctEC   = total > 0 ? Math.round(enCours  / total * 100) : 0;

        const el = document.getElementById('globalSectionCard');
        if (!el) return;
        el.querySelector('[data-stat=total]').textContent        = total;
        el.querySelector('[data-stat=termines]').textContent     = termines;
        el.querySelector('[data-stat=en_cours]').textContent     = enCours;
        el.querySelector('[data-stat=non_commences]').textContent= nonComm;
        el.querySelector('[data-stat=pct]').textContent          = pct + '%';
        const segs = el.querySelectorAll('.sc-progress-bar .seg');
        if (segs[0]) segs[0].style.width = pct   + '%';
        if (segs[1]) segs[1].style.width = pctEC + '%';
    }

    // ===========================================================
    // 2. Recherche temps réel
    // ===========================================================
    const inputRecherche = document.getElementById('rechercheCours');
    if (inputRecherche) {
        inputRecherche.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();

            // 1. Lignes ECUE
            document.querySelectorAll('.ecue-row').forEach(function (row) {
                row.style.display = !q || row.dataset.search.includes(q) ? '' : 'none';
            });

            // 2. UE rows + leur table : masquer si aucun ECUE visible
            document.querySelectorAll('.ue-row').forEach(function (ueRow) {
                const table = ueRow.nextElementSibling;
                if (!q) {
                    ueRow.style.display = '';
                    if (table) table.style.display = '';
                    return;
                }
                const visibles = table ? table.querySelectorAll('.ecue-row:not([style*="none"])').length : 0;
                ueRow.style.display  = visibles === 0 ? 'none' : '';
                if (table) table.style.display = visibles === 0 ? 'none' : '';
            });

            // 3. En-têtes semestre : masquer si toutes les UE du semestre sont cachées
            document.querySelectorAll('.sem-header').forEach(function (semHeader) {
                if (!q) { semHeader.style.display = ''; return; }
                let sibling = semHeader.nextElementSibling;
                let hasVisible = false;
                while (sibling && !sibling.classList.contains('sem-header')) {
                    if (sibling.classList.contains('ue-row') && sibling.style.display !== 'none') {
                        hasVisible = true;
                        break;
                    }
                    sibling = sibling.nextElementSibling;
                }
                semHeader.style.display = hasVisible ? '' : 'none';
            });

            // 4. Cartes promotion : masquer si aucun ECUE visible
            document.querySelectorAll('.promo-card').forEach(function (card) {
                const visible = card.querySelectorAll('.ecue-row:not([style*="none"])').length;
                card.style.display = visible > 0 ? '' : 'none';
            });
        });
    }

    // ===========================================================
    // 3. Wizard — orientation inline
    // ===========================================================
    const selOrientation = document.getElementById('selectOrientation');
    const panelNouv      = document.getElementById('panelNouvelleOrientation');
    const inputNouv      = document.getElementById('inputNouvelleOrientation');
    const btnNouvelleOri = document.getElementById('btnNouvelleOrientation');
    const btnCreerOri    = document.getElementById('btnCreerOrientation');
    const btnAnnulerOri  = document.getElementById('btnAnnulerOrientation');

    if (btnNouvelleOri && panelNouv) {
        btnNouvelleOri.addEventListener('click', function () {
            panelNouv.classList.remove('d-none');
            inputNouv.focus();
            btnNouvelleOri.classList.add('d-none');
        });
        btnAnnulerOri.addEventListener('click', function () {
            panelNouv.classList.add('d-none');
            inputNouv.value = '';
            document.getElementById('alertOrientation').innerHTML = '';
            btnNouvelleOri.classList.remove('d-none');
        });
        btnCreerOri.addEventListener('click', function () {
            const nom = inputNouv.value.trim();
            if (!nom) {
                document.getElementById('alertOrientation').innerHTML =
                    '<small class="text-danger"><i class="bi bi-exclamation-circle"></i> Saisissez un nom</small>';
                inputNouv.focus(); return;
            }
            btnCreerOri.disabled = true;
            btnCreerOri.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            const fd = new FormData();
            fd.append('idsection', <?= $idsection ?: 0 ?>);
            fd.append('designationOrientation', nom);
            fetch('controller/addSuiviOrientation.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(function (data) {
                    btnCreerOri.disabled = false;
                    btnCreerOri.innerHTML = '<i class="bi bi-check-lg"></i> Créer';
                    if (data.success) {
                        const option = new Option(data.designationOrientation, data.idorientation, true, true);
                        selOrientation.appendChild(option);
                        // Notifier Select2 si actif
                        if (typeof $ !== 'undefined' && $(selOrientation).data('select2')) {
                            $(selOrientation).trigger('change');
                        }
                        panelNouv.classList.add('d-none');
                        inputNouv.value = '';
                        document.getElementById('alertOrientation').innerHTML = '';
                        btnNouvelleOri.classList.remove('d-none');
                        toastOK('Orientation « ' + escHtml(data.designationOrientation) + ' » créée');
                    } else {
                        document.getElementById('alertOrientation').innerHTML =
                            '<small class="text-danger"><i class="bi bi-exclamation-circle"></i> ' + escHtml(data.message) + '</small>';
                    }
                })
                .catch(function () {
                    btnCreerOri.disabled = false;
                    btnCreerOri.innerHTML = '<i class="bi bi-check-lg"></i> Créer';
                    document.getElementById('alertOrientation').innerHTML = '<small class="text-danger">Erreur réseau</small>';
                });
        });
        inputNouv.addEventListener('keydown', function (e) {
            if (e.key === 'Enter')  { e.preventDefault(); btnCreerOri.click(); }
            if (e.key === 'Escape') { btnAnnulerOri.click(); }
        });
    }

    // ===========================================================
    // 4. Wizard — promotions
    // ===========================================================
    const promotionsExistantes = <?= json_encode(array_values(array_map(function($p) {
        return ['id' => (int)$p['idpromotion'], 'nom' => $p['designationPromotion'], 'semestres' => []];
    }, $promotionsSection))) ?>;

    const promotionsCrees = [];
    const uesCrees        = [];

    const btnSavePromotion = document.getElementById('btnSavePromotion');
    const btnPromoExistantes = document.getElementById('btnPromoExistantes');
    const formPromo        = document.getElementById('formPromotion');

    if (btnPromoExistantes) {
        btnPromoExistantes.addEventListener('click', function () {
            populateSelectPromotionUE();
            const selectPromo = document.getElementById('selectPromotionUE');
            
            if (selectPromo.options.length > 1) {
                document.getElementById('tabUELink').click();
                if (typeof $ !== 'undefined' && $.fn.select2) {
                    $(selectPromo).select2('open');
                } else {
                    selectPromo.focus();
                }
            } else {
                showAlert('alertPromotion', 'warning', 'Aucune promotion existante pour cette section');
            }
        });
    }

    if (formPromo && btnSavePromotion) {
        btnSavePromotion.addEventListener('click', function () {
            const fd = new FormData(formPromo);
            const selOri = document.getElementById('selectOrientation');
            if (selOri && !selOri.value) {
                showAlert('alertPromotion', 'warning', 'Veuillez choisir ou créer une orientation');
                return;
            }
            btnSavePromotion.disabled = true;
            btnSavePromotion.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Création…';
            fetch('controller/addSuiviPromotion.php', { method: 'POST', body: fd })
                .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(function (data) {
                    btnSavePromotion.disabled = false;
                    btnSavePromotion.innerHTML = '<i class="bi bi-arrow-right-circle me-1"></i>Créer et passer aux UE';
                    if (data.success) {
                        const nom = fd.get('designationPromotion');
                        promotionsCrees.push({ id: data.idpromotion, nom: nom, semestres: data.semestres });
                        renderPromotionsCrees();
                        populateSelectPromotionUE();
                        toastOK('Promotion « ' + nom + ' » créée !');
                        formPromo.querySelector('[name=designationPromotion]').value = '';
                        formPromo.querySelector('[name=cycle]').value = '';
                        formPromo.querySelector('[name=semestres]').value = '1, 2';
                        document.getElementById('tabUELink').click();
                    } else {
                        showAlert('alertPromotion', 'danger', data.message);
                    }
                })
                .catch(function (err) {
                    btnSavePromotion.disabled = false;
                    btnSavePromotion.innerHTML = '<i class="bi bi-arrow-right-circle me-1"></i>Créer et passer aux UE';
                    toastErr('Erreur : ' + err.message);
                });
        });
    }

    function renderPromotionsCrees() {
        const el = document.getElementById('promotionsCrees');
        if (!el || promotionsCrees.length === 0) return;
        let html = '<hr><p class="text-muted small mb-1">Promotions créées dans cette session :</p><div class="d-flex flex-wrap gap-2">';
        promotionsCrees.forEach(function (p) {
            html += '<span class="badge" style="background:#d4f5e4;color:#156b3e">' + escHtml(p.nom) + '</span>';
        });
        html += '</div>';
        el.innerHTML = html;
    }

    function populateSelectPromotionUE() {
        const sel = document.getElementById('selectPromotionUE');
        if (!sel) return;
        if (typeof $ !== 'undefined' && $.fn.select2) {
            try { $(sel).select2('destroy'); } catch (e) {}
        }
        sel.innerHTML = '<option value="">— Sélectionner une promotion —</option>';
        if (promotionsExistantes.length > 0) {
            const grpExist = document.createElement('optgroup');
            grpExist.label = 'Promotions existantes';
            promotionsExistantes.forEach(function (p) {
                const opt = document.createElement('option');
                opt.value = p.id; opt.textContent = p.nom;
                grpExist.appendChild(opt);
            });
            sel.appendChild(grpExist);
        }
        if (promotionsCrees.length > 0) {
            const grpNew = document.createElement('optgroup');
            grpNew.label = 'Créées maintenant';
            promotionsCrees.forEach(function (p) {
                const opt = document.createElement('option');
                opt.value = p.id; opt.textContent = p.nom;
                grpNew.appendChild(opt);
            });
            sel.appendChild(grpNew);
        }
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(sel).select2({ dropdownParent: $('#modalWizard'), width: '100%' });
        }
    }
    populateSelectPromotionUE();

    function reloadSelect2(sel) {
        if (typeof $ !== 'undefined' && $.fn.select2) {
            try { $(sel).select2('destroy'); } catch(e) {}
            $(sel).select2({ dropdownParent: $('#modalWizard'), width: '100%',
                             placeholder: 'Sélectionner une option', allowClear: true });
        }
    }

    function chargerSemestres(idpromotion) {
        const selSem = document.getElementById('selectSemestreUE');
        if (!selSem) return;
        if (!idpromotion) {
            selSem.innerHTML = '<option value="">— D\'abord choisir une promotion —</option>';
            selSem.disabled = true;
            reloadSelect2(selSem);
            return;
        }
        const trouvee = promotionsCrees.find(function (p) { return p.id == idpromotion; });
        if (trouvee && trouvee.semestres && trouvee.semestres.length > 0) {
            selSem.innerHTML = '<option value="">— Choisir un semestre —</option>';
            trouvee.semestres.forEach(function (s) {
                const opt = document.createElement('option');
                opt.value = s.id; opt.textContent = s.numero;
                selSem.appendChild(opt);
            });
            selSem.disabled = false;
            reloadSelect2(selSem);
            return;
        }
        selSem.innerHTML = '<option value="">Chargement…</option>';
        selSem.disabled = true;
        reloadSelect2(selSem);
        fetch('controller/getSuiviData.php?action=semestres&idpromotion=' + encodeURIComponent(idpromotion))
            .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(function (data) {
                selSem.innerHTML = '<option value="">— Choisir un semestre —</option>';
                if (data.success && data.data.length > 0) {
                    data.data.forEach(function (s) {
                        const opt = document.createElement('option');
                        opt.value = s.idsemestre; opt.textContent = s.numeroSemestre;
                        selSem.appendChild(opt);
                    });
                    selSem.disabled = false;
                } else {
                    selSem.innerHTML = '<option value="">Aucun semestre trouvé</option>';
                    selSem.disabled = true;
                }
                reloadSelect2(selSem);
            })
            .catch(function () {
                selSem.innerHTML = '<option value="">Erreur de chargement</option>';
                selSem.disabled = true;
                reloadSelect2(selSem);
                toastErr('Impossible de charger les semestres');
            });
    }

    $(document).on('change', '#selectPromotionUE', function () { chargerSemestres($(this).val()); });

    const formUE    = document.getElementById('formUE');
    const btnSaveUE = document.getElementById('btnSaveUE');
    if (formUE && btnSaveUE) {
        btnSaveUE.addEventListener('click', function () {
            const fd = new FormData(formUE);
            if (!fd.get('idsemestre') || !fd.get('codeUE') || !fd.get('designationUE')) {
                showAlert('alertUE', 'warning', 'Veuillez remplir tous les champs obligatoires'); return;
            }
            btnSaveUE.disabled = true;
            fetch('controller/addSuiviUE.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(function (data) {
                    btnSaveUE.disabled = false;
                    if (data.success) {
                        uesCrees.push({ id: data.idUE, code: data.codeUE, nom: data.designationUE });
                        renderUesCrees();
                        populateSelectUEECUE();
                        toastOK('UE « ' + data.codeUE + ' » créée !');
                        formUE.reset();
                        document.getElementById('tabECUELink').click();
                    } else {
                        showAlert('alertUE', 'danger', data.message);
                    }
                })
                .catch(function () { btnSaveUE.disabled = false; toastErr('Erreur réseau'); });
        });
    }

    function renderUesCrees() {
        const el = document.getElementById('uesCrees');
        if (!el || uesCrees.length === 0) return;
        let html = '<hr><p class="text-muted small mb-1">UE créées :</p><div class="d-flex flex-wrap gap-2">';
        uesCrees.forEach(function (u) {
            html += '<span class="badge" style="background:#e0e8ff;color:var(--sc-primary)">' + escHtml(u.code) + ' — ' + escHtml(u.nom) + '</span>';
        });
        html += '</div>';
        el.innerHTML = html;
    }

    function populateSelectUEECUE() {
        const sel = document.getElementById('selectUEECUE');
        if (!sel) return;
        sel.innerHTML = '<option value="">— Sélectionner une UE —</option>';
        uesCrees.forEach(function (u) {
            sel.innerHTML += '<option value="' + u.id + '">' + escHtml(u.code) + ' — ' + escHtml(u.nom) + '</option>';
        });
    }

    const formECUE    = document.getElementById('formECUE');
    const btnSaveECUE = document.getElementById('btnSaveECUE');
    if (formECUE && btnSaveECUE) {
        btnSaveECUE.addEventListener('click', function () {
            const fd = new FormData(formECUE);
            if (!fd.get('idUE') || !fd.get('designationECUE')) {
                showAlert('alertECUE', 'warning', 'Champs obligatoires manquants'); return;
            }
            btnSaveECUE.disabled = true;
            fetch('controller/addSuiviECUE.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(function (data) {
                    btnSaveECUE.disabled = false;
                    if (data.success) {
                        toastOK('Cours « ' + data.designationECUE + ' » ajouté !');
                        const el = document.getElementById('ecuesCrees');
                        if (el) {
                            if (el.childNodes.length === 0) {
                                el.innerHTML = '<hr><p class="text-muted small mb-1">Cours ajoutés :</p>';
                            }
                            const badge = document.createElement('span');
                            badge.className = 'badge me-1 mb-1';
                            badge.style.cssText = 'background:#d4f5e4;color:#156b3e';
                            badge.textContent = data.designationECUE;
                            el.appendChild(badge);
                        }
                        formECUE.querySelector('[name=designationECUE]').value = '';
                        formECUE.querySelector('[name=CMI]').value = '0';
                        formECUE.querySelector('[name=TD]').value  = '0';
                        formECUE.querySelector('[name=TP]').value  = '0';
                        formECUE.querySelector('[name=designationECUE]').focus();
                    } else {
                        showAlert('alertECUE', 'danger', data.message);
                    }
                })
                .catch(function () { btnSaveECUE.disabled = false; toastErr('Erreur réseau'); });
        });
    }

    const btnTerminer = document.getElementById('btnTerminerWizard');
    if (btnTerminer) {
        btnTerminer.addEventListener('click', function () { window.location.reload(); });
    }

    // ===========================================================
    // Utilitaires
    // ===========================================================
    function showAlert(id, type, msg) {
        const el = document.getElementById(id);
        if (!el) return;
        el.innerHTML = '<div class="alert alert-' + type + ' py-2 rounded-3">' + escHtml(msg) + '</div>';
        setTimeout(function () { el.innerHTML = ''; }, 5000);
    }
    function toastOK(msg) {
        if (typeof Swal !== 'undefined') {
            Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2500, timerProgressBar: true })
                .fire({ icon: 'success', title: msg });
        }
    }
    function toastErr(msg) {
        if (typeof Swal !== 'undefined') {
            Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 })
                .fire({ icon: 'error', title: msg });
        }
    }
    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }
})();
</script>

<?php include "./views/include/footer.php"; ?>
