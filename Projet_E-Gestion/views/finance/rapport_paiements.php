<?php
include "./views/include/header.php";

$connexion = Connexion::getInstance()->getPDO();
$idUser = $_SESSION['id'];

$stmt = $connexion->prepare("SELECT idAgent FROM t_users WHERE idUser = :idUser");
$stmt->bindParam(':idUser', $idUser);
$stmt->execute();
$user_agent = $stmt->fetch(PDO::FETCH_ASSOC);
$idAgent = $user_agent['idAgent'] ?? null;

$est_admin = (isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1);

$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';
unset($_SESSION['message'], $_SESSION['messageType']);

$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-d');
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-d');
$filtre_agent = isset($_GET['filtre_agent']) ? $_GET['filtre_agent'] : $idAgent;
if (!$est_admin) {
    $filtre_agent = $idAgent;
}
$filtre_source = isset($_GET['filtre_source']) ? $_GET['filtre_source'] : '';
$filtre_devise = isset($_GET['filtre_devise']) ? $_GET['filtre_devise'] : '';

$agents_list = [];
$stmt = $connexion->prepare("SELECT idAgent, noms FROM agent ORDER BY noms");
$stmt->execute();
$agents_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql = "
    SELECT 
        pf.id,
        pf.recu_numero,
        pf.montant,
        pf.devise,
        pf.mode_paiement,
        pf.reference_externe,
        pf.date_valeur,
        pf.commentaire,
        e.matricule,
        e.noms AS etudiant_nom,
        f.designation AS frais_designation,
        cf.designation AS categorie_frais,
        aa.designation AS annee_academique,
        p.designationPromotion AS promotion_nom,
        CONCAT(s.designationSection, ' - ', o.designationOrientation) AS faculte_nom,
        t.reference AS transaction_reference,
        t.date_transaction,
        t.source,
        t.source_id,
        u.nomUser AS agent_nom,
        CASE 
            WHEN t.source = 'Caisse' THEN (SELECT designation FROM caisses WHERE id = t.source_id)
            WHEN t.source = 'Banque' THEN (SELECT CONCAT(nom_banque, ' - ', intitule_compte) FROM comptes_bancaires WHERE id = t.source_id)
            ELSE 'Non spécifié'
        END AS source_nom
    FROM paiements_frais pf
    INNER JOIN etudiant e ON pf.matricule_etudiant = e.matricule AND e.est_actif = 1
    LEFT JOIN affectation_frais af ON pf.affectation_id = af.id
    LEFT JOIN frais f ON af.frais_id = f.id
    LEFT JOIN categories_frais cf ON f.categorie_id = cf.id
    LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
    LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
    LEFT JOIN section s ON o.section_idsection = s.idsection
    LEFT JOIN transactions t ON pf.transaction_id = t.id
    LEFT JOIN t_users u ON t.idUser = u.idUser
    WHERE pf.date_valeur BETWEEN :date_debut AND :date_fin
    AND pf.est_confirme = 1
";

$params = [
    ':date_debut' => $date_debut,
    ':date_fin' => $date_fin
];

if (!empty($filtre_agent)) {
    $sql .= " AND t.idAgent = :filtre_agent";
    $params[':filtre_agent'] = $filtre_agent;
}

if (!empty($filtre_source)) {
    $sql .= " AND t.source = :filtre_source";
    $params[':filtre_source'] = $filtre_source;
}

if (!empty($filtre_devise)) {
    $sql .= " AND pf.devise = :filtre_devise";
    $params[':filtre_devise'] = $filtre_devise;
}

$sql .= " ORDER BY pf.date_valeur DESC, pf.id DESC";

$stmt = $connexion->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totaux_par_devise = [];
foreach ($paiements as $paiement) {
    $devise = $paiement['devise'] ?? 'USD';
    if (!isset($totaux_par_devise[$devise])) {
        $totaux_par_devise[$devise] = [
            'total' => 0,
            'nombre' => 0
        ];
    }
    $totaux_par_devise[$devise]['total'] += $paiement['montant'];
    $totaux_par_devise[$devise]['nombre']++;
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Rapport des Paiements</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Rapport des Paiements</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filtres</h5>
                        <form action="" method="GET" class="row g-3">
                            <input type="hidden" name="view" value="finance/rapport_paiements">

                            <div class="col-md-3">
                                <label for="date_debut" class="form-label">Date début</label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>" required>
                            </div>

                            <div class="col-md-3">
                                <label for="date_fin" class="form-label">Date fin</label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>" required>
                            </div>

                            <?php if ($est_admin): ?>
                                <div class="col-md-2">
                                    <label for="filtre_agent" class="form-label">Agent</label>
                                    <select class="form-select" id="filtre_agent" name="filtre_agent">
                                        <option value="">Tous</option>
                                        <?php foreach ($agents_list as $agent): ?>
                                            <option value="<?= $agent['idAgent'] ?>" <?= $filtre_agent == $agent['idAgent'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($agent['noms']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="filtre_agent" value="<?= $idAgent ?>">
                            <?php endif; ?>

                            <div class="col-md-2">
                                <label for="filtre_source" class="form-label">Source</label>
                                <select class="form-select" id="filtre_source" name="filtre_source">
                                    <option value="">Toutes</option>
                                    <option value="Caisse" <?= $filtre_source === 'Caisse' ? 'selected' : '' ?>>Caisse</option>
                                    <option value="Banque" <?= $filtre_source === 'Banque' ? 'selected' : '' ?>>Banque</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="filtre_devise" class="form-label">Devise</label>
                                <select class="form-select" id="filtre_devise" name="filtre_devise">
                                    <option value="">Toutes</option>
                                    <option value="USD" <?= $filtre_devise === 'USD' ? 'selected' : '' ?>>USD</option>
                                    <option value="CDF" <?= $filtre_devise === 'CDF' ? 'selected' : '' ?>>CDF</option>
                                    <option value="EUR" <?= $filtre_devise === 'EUR' ? 'selected' : '' ?>>EUR</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-funnel"></i> Filtrer
                                </button>
                                <a href="?view=finance/rapport_paiements" class="btn btn-secondary">
                                    <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser
                                </a>
                                <button type="button" class="btn btn-success" onclick="exporterPDF()">
                                    <i class="bi bi-file-earmark-pdf"></i> Exporter en PDF
                                </button>
                                <button type="button" class="btn btn-info" onclick="window.print()">
                                    <i class="bi bi-printer"></i> Imprimer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($totaux_par_devise)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Résumé</h5>
                            <div class="row">
                                <?php foreach ($totaux_par_devise as $devise => $data): ?>
                                    <div class="col-md-4">
                                        <div class="alert alert-info">
                                            <h6 class="mb-1"><strong>Total en <?= htmlspecialchars($devise) ?></strong></h6>
                                            <p class="mb-0" style="font-size: 1.2rem;">
                                                <?= number_format($data['total'], 2, ',', ' ') ?> <?= htmlspecialchars($devise) ?>
                                            </p>
                                            <small>Nombre de paiements: <?= $data['nombre'] ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Liste des Paiements</h5>
                        <?php if (empty($paiements)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i> Aucun paiement trouvé pour la période sélectionnée.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped table-hover" id="tablePaiements">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>N° Reçu</th>
                                            <th>Date</th>
                                            <th>Étudiant</th>
                                            <th>Matricule</th>
                                            <th>Frais</th>
                                            <th>Montant</th>
                                            <th>Mode</th>
                                            <th>Source</th>
                                            <th>Agent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($paiements as $paiement): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($paiement['recu_numero']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($paiement['date_valeur'])) ?></td>
                                                <td><?= htmlspecialchars($paiement['etudiant_nom']) ?></td>
                                                <td><?= htmlspecialchars($paiement['matricule']) ?></td>
                                                <td><?= htmlspecialchars($paiement['frais_designation']) ?></td>
                                                <td class="text-end">
                                                    <?= number_format($paiement['montant'], 2, ',', ' ') ?> <?= htmlspecialchars($paiement['devise']) ?>
                                                </td>
                                                <td><?= htmlspecialchars($paiement['mode_paiement']) ?></td>
                                                <td><?= htmlspecialchars($paiement['source_nom']) ?></td>
                                                <td><?= htmlspecialchars($paiement['agent_nom']) ?></td>
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
    </section>
</main>

<style>
@media print {
    .pagetitle nav,
    .card-title,
    form,
    .btn,
    .main#main .sidebar {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .table {
        font-size: 0.85rem;
    }
    
    .alert {
        page-break-inside: avoid;
    }
}
</style>

<script>
function exporterPDF() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'pdf');
    window.open('controller/generer_rapport_paiements_pdf.php?' + params.toString(), '_blank');
}
</script>

<?php include "./views/include/footer.php"; ?>
