<?php
include "./views/include/header.php";

// Vérifier si l'ID de la caisse est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "ID de caisse non spécifié";
    $_SESSION['messageType'] = "danger";
    header("Location: ?view=finance/config_caisses");
    exit;
}

$id_caisse = intval($_GET['id']);
$connexion = Connexion::getInstance()->getPDO();

// Récupérer les informations de la caisse
$stmt = $connexion->prepare("
    SELECT c.*, a.noms as responsable_nom 
    FROM caisses c
    LEFT JOIN agent a ON c.\"idAgent_responsable\" = a.\"idAgent\"
    WHERE c.id = :id
");
$stmt->bindParam(':id', $id_caisse);
$stmt->execute();
$caisse = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$caisse) {
    $_SESSION['message'] = "Caisse introuvable";
    $_SESSION['messageType'] = "danger";
    header("Location: ?view=finance/config_caisses");
    exit;
}

// Récupérer les dernières sessions de caisse
$stmt = $connexion->prepare("
    SELECT sc.*, 
           a1.noms as agent_nom,
           a2.noms as validateur_nom
    FROM sessions_caisse sc
    LEFT JOIN agent a1 ON sc.\"idAgent\" = a1.\"idAgent\"
    LEFT JOIN agent a2 ON sc.\"idValidateur\" = a2.\"idAgent\"
    WHERE sc.caisse_id = :id
    ORDER BY sc.date_ouverture DESC
    LIMIT 10
");
$stmt->bindParam(':id', $id_caisse);
$stmt->execute();
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les dernières transactions
$stmt = $connexion->prepare("
    SELECT t.*, a.noms as agent_nom
    FROM transactions t
    LEFT JOIN agent a ON t.\"idAgent\" = a.\"idAgent\"
    WHERE t.source = 'Caisse' AND t.source_id = :id
    ORDER BY t.date_transaction DESC
    LIMIT 20
");
$stmt->bindParam(':id', $id_caisse);
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer l'historique des soldes
$stmt = $connexion->prepare("
    SELECT *
    FROM historique_soldes
    WHERE type = 'Caisse' AND source_id = :id
    ORDER BY date DESC
    LIMIT 12
");
$stmt->bindParam(':id', $id_caisse);
$stmt->execute();
$historique_soldes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les messages d'alerte s'ils existent
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';

// Effacer les messages après les avoir récupérés
unset($_SESSION['message']);
unset($_SESSION['messageType']);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Détails de la Caisse</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item"><a href="?view=finance/config_caisses">Caisses</a></li>
                <li class="breadcrumb-item active">Détails</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                        <?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center mb-4 mt-3">
                            <div class="col-md-8">
                                <h5 class="card-title">
                                    <?= htmlspecialchars($caisse['designation']) ?>
                                    <?php if ($caisse['est_actif']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </h5>
                            </div>
                            <div class="col-md-4 text-end">
                                <a href="?view=finance/config_caisses" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Retour
                                </a>
                                <button type="button" class="btn btn-primary edit-caisse" 
                                        data-id="<?= $caisse['id'] ?>"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editCaisseModal">
                                    <i class="bi bi-pencil"></i> Modifier
                                </button>
                            </div>
                        </div>

                        <!-- Onglets pour la navigation -->
                        <ul class="nav nav-tabs" id="caisseTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab" aria-controls="info" aria-selected="true">
                                    Informations générales
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="sessions-tab" data-bs-toggle="tab" data-bs-target="#sessions" type="button" role="tab" aria-controls="sessions" aria-selected="false">
                                    Sessions de caisse
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab" aria-controls="transactions" aria-selected="false">
                                    Transactions
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="historique-tab" data-bs-toggle="tab" data-bs-target="#historique" type="button" role="tab" aria-controls="historique" aria-selected="false">
                                    Historique des soldes
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content pt-4" id="caisseTabContent">
                            <!-- Onglet Informations générales -->
                            <div class="tab-pane fade show active" id="info" role="tabpanel" aria-labelledby="info-tab">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5 class="text-primary">Caractéristiques</h5>
                                        <table class="table table-bordered">
                                            <tr>
                                                <th style="width: 40%">Désignation</th>
                                                <td><?= htmlspecialchars($caisse['designation']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Responsable</th>
                                                <td><?= htmlspecialchars($caisse['responsable_nom'] ?? 'Non spécifié') ?></td>
                                            </tr>
                                            <tr>
                                                <th>Localisation</th>
                                                <td><?= htmlspecialchars($caisse['localisation'] ?? 'Non spécifié') ?></td>
                                            </tr>
                                            <tr>
                                                <th>Description</th>
                                                <td><?= nl2br(htmlspecialchars($caisse['description'] ?? 'Aucune description')) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Date de création</th>
                                                <td><?= date('d/m/Y H:i', strtotime($caisse['date_creation'])) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="text-primary">Situation financière</h5>
                                        <table class="table table-bordered">
                                            <tr>
                                                <th style="width: 40%">Devise</th>
                                                <td><?= htmlspecialchars($caisse['devise']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Solde initial</th>
                                                <td class="text-end"><?= number_format($caisse['solde_initial'], 2) ?> <?= htmlspecialchars($caisse['devise']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Solde actuel</th>
                                                <td class="text-end fw-bold"><?= number_format($caisse['solde_actuel'], 2) ?> <?= htmlspecialchars($caisse['devise']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Plafond</th>
                                                <td class="text-end"><?= $caisse['plafond_caisse'] ? number_format($caisse['plafond_caisse'], 2) . ' ' . htmlspecialchars($caisse['devise']) : 'Non défini' ?></td>
                                            </tr>
                                            <tr>
                                                <th>Statut</th>
                                                <td>
                                                    <?php if ($caisse['est_actif']): ?>
                                                        <span class="badge bg-success">Caisse active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Caisse inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Onglet Sessions de caisse -->
                            <div class="tab-pane fade" id="sessions" role="tabpanel" aria-labelledby="sessions-tab">
                                <div class="d-flex justify-content-end mb-3">
                                    <a href="?view=finance/ouvrir_session_caisse&caisse_id=<?= $id_caisse ?>" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Ouvrir une nouvelle session
                                    </a>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Date ouverture</th>
                                                <th>Agent</th>
                                                <th>Montant ouverture</th>
                                                <th>Date fermeture</th>
                                                <th>Montant fermeture</th>
                                                <th>Différence</th>
                                                <th>Statut</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($sessions)): ?>
                                                <tr>
                                                    <td colspan="9" class="text-center">Aucune session de caisse enregistrée</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($sessions as $session): ?>
                                                    <tr>
                                                        <td><?= $session['id'] ?></td>
                                                        <td><?= date('d/m/Y H:i', strtotime($session['date_ouverture'])) ?></td>
                                                        <td><?= htmlspecialchars($session['agent_nom']) ?></td>
                                                        <td class="text-end"><?= number_format($session['montant_ouverture'], 2) ?> <?= htmlspecialchars($caisse['devise']) ?></td>
                                                        <td><?= $session['date_fermeture'] ? date('d/m/Y H:i', strtotime($session['date_fermeture'])) : '-' ?></td>
                                                        <td class="text-end"><?= $session['montant_fermeture'] ? number_format($session['montant_fermeture'], 2) . ' ' . htmlspecialchars($caisse['devise']) : '-' ?></td>
                                                        <td class="text-end">
                                                            <?php if ($session['difference']): ?>
                                                                <span class="<?= $session['difference'] < 0 ? 'text-danger' : 'text-success' ?>">
                                                                    <?= number_format($session['difference'], 2) ?> <?= htmlspecialchars($caisse['devise']) ?>
                                                                </span>
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($session['statut'] == 'Ouverte'): ?>
                                                                <span class="badge bg-success">Ouverte</span>
                                                            <?php elseif ($session['statut'] == 'Fermée'): ?>
                                                                <span class="badge bg-secondary">Fermée</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Annulée</span>
                                                                <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <a href="?view=finance/session_caisse_detail&id=<?= $session['id'] ?>" class="btn btn-sm btn-info">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <?php if ($session['statut'] == 'Ouverte'): ?>
                                                                <a href="?view=finance/fermer_session_caisse&id=<?= $session['id'] ?>" class="btn btn-sm btn-warning">
                                                                    <i class="bi bi-box-arrow-right"></i> Fermer
                                                                </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <?php if (!empty($sessions)): ?>
                                    <div class="text-center mt-3">
                                        <a href="?view=finance/sessions_caisse&caisse_id=<?= $id_caisse ?>" class="btn btn-outline-primary">
                                            Voir toutes les sessions
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Onglet Transactions -->
                            <div class="tab-pane fade" id="transactions" role="tabpanel" aria-labelledby="transactions-tab">
                                <div class="d-flex justify-content-end mb-3">
                                    <a href="?view=finance/nouvelle_transaction&source=Caisse&source_id=<?= $id_caisse ?>" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Nouvelle transaction
                                    </a>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Référence</th>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Montant</th>
                                                <th>Description</th>
                                                <th>Agent</th>
                                                <th>Statut</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($transactions)): ?>
                                                <tr>
                                                    <td colspan="8" class="text-center">Aucune transaction enregistrée</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($transactions as $transaction): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($transaction['reference']) ?></td>
                                                        <td><?= date('d/m/Y H:i', strtotime($transaction['date_transaction'])) ?></td>
                                                        <td>
                                                            <?php if ($transaction['type'] == 'Recette'): ?>
                                                                <span class="badge bg-success">Recette</span>
                                                            <?php elseif ($transaction['type'] == 'Dépense'): ?>
                                                                <span class="badge bg-danger">Dépense</span>
                                                            <?php elseif ($transaction['type'] == 'Transfert'): ?>
                                                                <span class="badge bg-info">Transfert</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning">Ajustement</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-end">
                                                            <?php if ($transaction['type'] == 'Dépense'): ?>
                                                                <span class="text-danger">-<?= number_format($transaction['montant'], 2) ?></span>
                                                            <?php else: ?>
                                                                <span class="text-success"><?= number_format($transaction['montant'], 2) ?></span>
                                                            <?php endif; ?>
                                                            <?= htmlspecialchars($transaction['devise']) ?>
                                                        </td>
                                                        <td><?= htmlspecialchars(mb_substr($transaction['description'] ?? '', 0, 50)) . ((strlen($transaction['description'] ?? '') > 50) ? '...' : '') ?></td>
                                                        <td><?= htmlspecialchars($transaction['agent_nom']) ?></td>
                                                        <td>
                                                            <?php if ($transaction['statut'] == 'Confirmée'): ?>
                                                                <span class="badge bg-success">Confirmée</span>
                                                            <?php elseif ($transaction['statut'] == 'Provisoire'): ?>
                                                                <span class="badge bg-warning">Provisoire</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Annulée</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <a href="?view=finance/transaction_detail&id=<?= $transaction['id'] ?>" class="btn btn-sm btn-info">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <?php if (!empty($transactions)): ?>
                                    <div class="text-center mt-3">
                                        <a href="?view=finance/transactions&source=Caisse&source_id=<?= $id_caisse ?>" class="btn btn-outline-primary">
                                            Voir toutes les transactions
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Onglet Historique des soldes -->
                            <div class="tab-pane fade" id="historique" role="tabpanel" aria-labelledby="historique-tab">
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5 class="card-title">Évolution du solde</h5>
                                                <canvas id="soldeChart" style="max-height: 400px;"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Solde d'ouverture</th>
                                                <th>Entrées</th>
                                                <th>Sorties</th>
                                                <th>Solde de fermeture</th>
                                                <th>Ajusté</th>
                                                <th>Commentaire</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($historique_soldes)): ?>
                                                <tr>
                                                    <td colspan="7" class="text-center">Aucun historique de solde disponible</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($historique_soldes as $solde): ?>
                                                    <tr>
                                                        <td><?= date('d/m/Y', strtotime($solde['date'])) ?></td>
                                                        <td class="text-end"><?= number_format($solde['solde_ouverture'], 2) ?> <?= htmlspecialchars($solde['devise']) ?></td>
                                                        <td class="text-end text-success"><?= number_format($solde['entrees'], 2) ?> <?= htmlspecialchars($solde['devise']) ?></td>
                                                        <td class="text-end text-danger"><?= number_format($solde['sorties'], 2) ?> <?= htmlspecialchars($solde['devise']) ?></td>
                                                        <td class="text-end fw-bold"><?= number_format($solde['solde_fermeture'], 2) ?> <?= htmlspecialchars($solde['devise']) ?></td>
                                                        <td>
                                                            <?php if ($solde['est_ajuste']): ?>
                                                                <span class="badge bg-warning">Ajusté</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-success">Non</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($solde['commentaire'] ?? '-') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <?php if (!empty($historique_soldes)): ?>
                                    <div class="text-center mt-3">
                                        <a href="?view=finance/historique_soldes&type=Caisse&id=<?= $id_caisse ?>" class="btn btn-outline-primary">
                                            Voir tout l'historique
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Script pour le graphique d'évolution du solde -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($historique_soldes)): ?>
        // Préparer les données pour le graphique
        var dates = [];
        var soldes = [];
        
        <?php 
        // Inverser le tableau pour afficher dans l'ordre chronologique
        $historique_graph = array_reverse($historique_soldes);
        foreach ($historique_graph as $solde): 
        ?>
            dates.push('<?= date('d/m/Y', strtotime($solde['date'])) ?>');
            soldes.push(<?= $solde['solde_fermeture'] ?>);
        <?php endforeach; ?>
        
        // Créer le graphique
        var ctx = document.getElementById('soldeChart').getContext('2d');
        var soldeChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Solde (<?= htmlspecialchars($caisse['devise']) ?>)',
                    data: soldes,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(context.raw);
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
                            }
                        }
                    }
                }
            }
        });
    <?php endif; ?>
});
</script>

<?php include "./views/include/footer.php"; ?>
