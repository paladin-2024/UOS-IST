<?php
include "./views/include/header.php";
require_once 'config/Connexion.php';

$journal = new JournalServeur();

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$par_page = isset($_GET['par_page']) ? intval($_GET['par_page']) : 50;

// Obtenir les données depuis la table user_activity_log
$db = Connexion::getInstance()->getPDO();
$sql = "SELECT u.\"idUser\", u.\"nomUser\", ual.* FROM user_activity_log ual 
        LEFT JOIN t_users u ON ual.user_id = u.\"idUser\" 
        WHERE 1=1";
$parametres = [];

if (!empty($_GET['recherche'])) {
    $sql .= " AND (ual.description LIKE ? OR u.\"nomUser\" LIKE ? OR ual.ip_address LIKE ?)";
    $recherche = '%' . $_GET['recherche'] . '%';
    $parametres[] = $recherche;
    $parametres[] = $recherche;
    $parametres[] = $recherche;
}

if (!empty($_GET['date_debut'])) {
    $sql .= " AND DATE(ual.created_at) >= ?";
    $parametres[] = $_GET['date_debut'];
}

if (!empty($_GET['date_fin'])) {
    $sql .= " AND DATE(ual.created_at) <= ?";
    $parametres[] = $_GET['date_fin'];
}

// Compter total
$sqlBase = explode(' ORDER BY', $sql)[0];
$sqlCount = "SELECT COUNT(*) as total FROM user_activity_log ual 
        LEFT JOIN t_users u ON ual.user_id = u.\"idUser\" 
        WHERE 1=1";
if (strpos($sqlBase, 'AND') !== false) {
    $sqlCount = $sqlBase;
    $sqlCount = str_replace('SELECT u."idUser", u."nomUser", ual.*', 'SELECT COUNT(*) as total', $sqlCount);
}

$stmtCount = $db->prepare($sqlCount);
$stmtCount->execute($parametres);
$total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

// Pagination
$offset = ($page - 1) * $par_page;
$sql .= " ORDER BY ual.created_at DESC LIMIT " . intval($par_page) . " OFFSET " . intval($offset);

$stmt = $db->prepare($sql);
$stmt->execute($parametres);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$pages = ceil($total / $par_page);

$resultats = [
    'logs' => $logs,
    'total' => (int)$total,
    'pages' => (int)$pages,
    'page_actuelle' => $page,
    'par_page' => $par_page
];
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Journal des Connexions</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="">Configuration</a></li>
                <li class="breadcrumb-item"><a href="configuration/journal_serveur">Journal Serveur</a></li>
                <li class="breadcrumb-item active">Connexions</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="row">
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-box-arrow-in-right"></i> Historique des Connexions/Déconnexions
                                </h5>

                                <?php if (isset($_SESSION['message_succes'])): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="bi bi-check-circle"></i> <?= $_SESSION['message_succes'] ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                    <?php unset($_SESSION['message_succes']); ?>
                                <?php endif; ?>

                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> Affichage des logs de connexion et déconnexion des utilisateurs.
                                </div>

                                <form id="filtersForm" method="GET" action="" class="mb-3">
                                    <input type="hidden" name="view" value="configuration/journal_connexions">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-4">
                                            <label class="form-label">Nom, Prénom, IP ou Recherche</label>
                                            <input type="text" name="recherche" class="form-control" 
                                                   value="<?= isset($_GET['recherche']) ? htmlspecialchars($_GET['recherche']) : '' ?>" 
                                                   placeholder="Nom, prénom, IP...">
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">Du</label>
                                            <input type="date" name="date_debut" class="form-control" 
                                                   value="<?= isset($_GET['date_debut']) ? htmlspecialchars($_GET['date_debut']) : '' ?>">
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">Au</label>
                                            <input type="date" name="date_fin" class="form-control" 
                                                   value="<?= isset($_GET['date_fin']) ? htmlspecialchars($_GET['date_fin']) : '' ?>">
                                        </div>

                                        <div class="col-md-4">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-search"></i> Filtrer
                                            </button>
                                            <a href="?view=configuration/journal_connexions" class="btn btn-secondary">
                                                <i class="bi bi-arrow-clockwise"></i> Réinitialiser
                                            </a>
                                        </div>
                                    </div>
                                </form>

                                <div class="row mb-3">
                                    <div class="col-12">
                                        <a href="?view=configuration/journal_serveur" class="btn btn-secondary btn-sm">
                                            <i class="bi bi-arrow-left"></i> Retour Journal Serveur
                                        </a>
                                        <a href="controller/export_journal_connexions_excel.php?<?= http_build_query($_GET) ?>" class="btn btn-success btn-sm">
                                            <i class="bi bi-download"></i> Exporter Excel
                                        </a>
                                    </div>
                                </div>

                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Date/Heure</th>
                                            <th>Utilisateur</th>
                                            <th>Type d'Action</th>
                                            <th>Description</th>
                                            <th>Adresse IP</th>
                                            <th>Navigateur/Agent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($resultats['logs'])): ?>
                                            <?php $i = (($page - 1) * $par_page) + 1; foreach ($resultats['logs'] as $log): ?>
                                                <tr>
                                                    <td><?= $i ?></td>
                                                    <td><small><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></small></td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($log['nomUser'] ?? '-') ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $badgeType = $log['action_type'] === 'login' ? 'bg-success' : 'bg-info';
                                                        $icon = $log['action_type'] === 'login' ? 'bi-box-arrow-in-right' : 'bi-box-arrow-right';
                                                        ?>
                                                        <span class="badge <?= $badgeType ?>">
                                                            <i class="bi <?= $icon ?>"></i> <?= htmlspecialchars(ucfirst($log['action_type'])) ?>
                                                        </span>
                                                    </td>
                                                    <td><small><?= htmlspecialchars(substr($log['description'] ?? '-', 0, 100)) ?></small></td>
                                                    <td><small><code><?= htmlspecialchars($log['ip_address']) ?></code></small></td>
                                                    <td><small><?= htmlspecialchars(substr($log['user_agent'] ?? '-', 0, 50)) ?>...</small></td>
                                                </tr>
                                                <?php $i++; endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4">
                                                    <i class="bi bi-inbox"></i> Aucun log de connexion trouvé
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>

                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            Total: <strong><?= $resultats['total'] ?? 0 ?></strong> connexions/déconnexions
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            Affichage: <strong><?= (($page - 1) * $par_page) + 1 ?></strong> - 
                                            <strong><?= min($page * $par_page, $resultats['total'] ?? 0) ?></strong>
                                        </small>
                                    </div>
                                </div>

                                <?php if (isset($resultats['pages']) && $resultats['pages'] > 1): ?>
                                    <nav aria-label="Pagination">
                                        <ul class="pagination justify-content-center">
                                            <?php 
                                            $queryString = http_build_query(array_merge($_GET, ['view' => 'configuration/journal_connexions']));
                                            $baseUrl = "?" . $queryString . "&page=";
                                            ?>
                                            
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?= $baseUrl ?>1">
                                                        <i class="bi bi-chevron-left"></i> Première
                                                    </a>
                                                </li>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?= $baseUrl . ($page - 1) ?>">Précédente</a>
                                                </li>
                                            <?php endif; ?>

                                            <?php 
                                            $debut = max(1, $page - 2);
                                            $fin = min($resultats['pages'], $page + 2);
                                            for ($i = $debut; $i <= $fin; $i++):
                                            ?>
                                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="<?= $baseUrl . $i ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>

                                            <?php if ($page < $resultats['pages']): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?= $baseUrl . ($page + 1) ?>">Suivante</a>
                                                </li>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?= $baseUrl . $resultats['pages'] ?>">
                                                        Dernière <i class="bi bi-chevron-right"></i>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<?php include "./views/include/footer.php"; ?>
