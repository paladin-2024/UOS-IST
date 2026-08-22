<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Paramètres de pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Paramètres de filtrage
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';
$id_depot = isset($_GET['id_depot']) ? intval($_GET['id_depot']) : 0;
$type_sortie = isset($_GET['type_sortie']) ? $_GET['type_sortie'] : '';
$etat = isset($_GET['etat']) ? $_GET['etat'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Construction de la requête SQL avec filtres
$whereConditions = [];
$params = [];

if (!empty($date_debut)) {
    $whereConditions[] = "s.date_sortie >= :date_debut";
    $params[':date_debut'] = $date_debut;
}

if (!empty($date_fin)) {
    $whereConditions[] = "s.date_sortie <= :date_fin";
    $params[':date_fin'] = $date_fin;
}

if ($id_depot > 0) {
    $whereConditions[] = "s.id_depot = :id_depot";
    $params[':id_depot'] = $id_depot;
}

if (!empty($type_sortie)) {
    $whereConditions[] = "s.type_sortie = :type_sortie";
    $params[':type_sortie'] = $type_sortie;
}

if (!empty($etat)) {
    $whereConditions[] = "s.etat = :etat";
    $params[':etat'] = $etat;
}

if (!empty($search)) {
    $whereConditions[] = "(s.numero_sortie LIKE :search OR d.libelle_depot LIKE :search OR s.reference_document LIKE :search)";
    $params[':search'] = "%$search%";
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// Récupérer le nombre total de sorties avec ces filtres
$countQuery = "SELECT COUNT(*) FROM sortie_stock s 
              LEFT JOIN depot d ON s.id_depot = d.id_depot 
              $whereClause";
$stmt = $db->prepare($countQuery);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$totalItems = $stmt->fetchColumn();
$totalPages = ceil($totalItems / $limit);

// Récupérer les sorties avec pagination
$query = "SELECT s.*, d.libelle_depot, u.nomUser as user_creation 
          FROM sortie_stock s
          LEFT JOIN depot d ON s.id_depot = d.id_depot
          LEFT JOIN t_users u ON s.id_user_creation = u.idUser
          $whereClause
          ORDER BY s.date_creation DESC
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$sorties = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des dépôts pour le filtre
$queryDepots = "SELECT * FROM depot WHERE actif = 1 ORDER BY libelle_depot";
$stmtDepots = $db->prepare($queryDepots);
$stmtDepots->execute();
$depots = $stmtDepots->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>SORTIES DE STOCK</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item active">Sorties</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Liste des sorties de stock
                            <a href="stock/stock.sortie.add" class="btn btn-primary btn-sm float-end">
                                <i class="bi bi-plus-circle"></i> Nouvelle sortie
                            </a>
                        </h5>
                        
                        <!-- Filtres -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <form method="GET" action="" class="row g-3">
                                    <input type="hidden" name="view" value="stock/stock.sortie.list">
                                    
                                    <div class="col-md-2">
                                        <label for="date_debut" class="form-label">Date début</label>
                                        <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label for="date_fin" class="form-label">Date fin</label>
                                        <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label for="id_depot" class="form-label">Dépôt</label>
                                        <select class="form-select" id="id_depot" name="id_depot">
                                            <option value="">Tous</option>
                                            <?php foreach ($depots as $depot): ?>
                                                <option value="<?= $depot['id_depot'] ?>" <?= ($id_depot == $depot['id_depot']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($depot['libelle_depot']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label for="type_sortie" class="form-label">Type</label>
                                        <select class="form-select" id="type_sortie" name="type_sortie">
                                            <option value="">Tous</option>
                                            <option value="Vente" <?= ($type_sortie == 'Vente') ? 'selected' : '' ?>>Vente</option>
                                            <option value="Transfert" <?= ($type_sortie == 'Transfert') ? 'selected' : '' ?>>Transfert</option>
                                            <option value="Inventaire" <?= ($type_sortie == 'Inventaire') ? 'selected' : '' ?>>Inventaire</option>
                                            <option value="Perte" <?= ($type_sortie == 'Perte') ? 'selected' : '' ?>>Perte</option>
                                            <option value="Autre" <?= ($type_sortie == 'Autre') ? 'selected' : '' ?>>Autre</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label for="etat" class="form-label">État</label>
                                        <select class="form-select" id="etat" name="etat">
                                            <option value="">Tous</option>
                                            <option value="En cours" <?= ($etat == 'En cours') ? 'selected' : '' ?>>En cours</option>
                                            <option value="Validé" <?= ($etat == 'Validé') ? 'selected' : '' ?>>Validé</option>
                                            <option value="Annulé" <?= ($etat == 'Annulé') ? 'selected' : '' ?>>Annulé</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label for="search" class="form-label">Recherche</label>
                                        <input type="text" class="form-control" id="search" name="search" placeholder="N°, dépôt..." value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                    
                                    <div class="col-12 text-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-filter"></i> Filtrer
                                        </button>
                                        <a href="stock/stock.sortie.list" class="btn btn-secondary">
                                            <i class="bi bi-arrow-clockwise"></i> Réinitialiser
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Tableau des sorties -->
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Numéro</th>
                                        <th>Date</th>
                                        <th>Dépôt</th>
                                        <th>Type</th>
                                        <th>Montant</th>
                                        <th>État</th>
                                        <th>Créé par</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($sorties)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center">Aucune sortie de stock trouvée</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $counter = ($page - 1) * $limit + 1; ?>
                                        <?php foreach ($sorties as $sortie): ?>
                                            <tr>
                                                <td><?= $counter++ ?></td>
                                                <td><?= htmlspecialchars($sortie['numero_sortie']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($sortie['date_sortie'])) ?></td>
                                                <td><?= htmlspecialchars($sortie['libelle_depot']) ?></td>
                                                <td><?= htmlspecialchars($sortie['type_sortie']) ?></td>
                                                <td class="text-end"><?= number_format($sortie['montant_total'], 2, ',', ' ') ?> $</td>
                                                <td>
                                                    <?php
                                                    $badgeClass = 'bg-secondary';
                                                    switch ($sortie['etat']) {
                                                        case 'En cours':
                                                            $badgeClass = 'bg-warning';
                                                            break;
                                                        case 'Validé':
                                                            $badgeClass = 'bg-success';
                                                            break;
                                                        case 'Annulé':
                                                            $badgeClass = 'bg-danger';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($sortie['etat']) ?></span>
                                                </td>
                                                <td><?= htmlspecialchars($sortie['user_creation']) ?></td>
                                                <td>
                                                    <a href="stock/stock.sortie.view&id=<?= $sortie['id_sortie'] ?>" class="btn btn-info btn-sm">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    
                                                    <?php if ($sortie['etat'] == 'En cours'): ?>
                                                        <a href="stock/stock.sortie.edit&id=<?= $sortie['id_sortie'] ?>" class="btn btn-warning btn-sm">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        
                                                        <button type="button" class="btn btn-success btn-sm" onclick="confirmerValidation(<?= $sortie['id_sortie'] ?>)">
                                                            <i class="bi bi-check-circle"></i>
                                                        </button>
                                                        
                                                        <button type="button" class="btn btn-danger btn-sm" onclick="confirmerAnnulation(<?= $sortie['id_sortie'] ?>)">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="openPrintModal(<?= $sortie['id_sortie'] ?>)">
                                                        <i class="bi bi-printer"></i>
                                                    </button>

                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?view=stock/stock.sortie.list&page=1<?= isset($_GET['date_debut']) ? '&date_debut='.$date_debut : '' ?><?= isset($_GET['date_fin']) ? '&date_fin='.$date_fin : '' ?><?= isset($_GET['id_depot']) ? '&id_depot='.$id_depot : '' ?><?= isset($_GET['type_sortie']) ? '&type_sortie='.$type_sortie : '' ?><?= isset($_GET['etat']) ? '&etat='.$etat : '' ?><?= isset($_GET['search']) ? '&search='.$search : '' ?>">
                                            <i class="bi bi-chevron-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?view=stock/stock.sortie.list&page=<?= $page-1 ?><?= isset($_GET['date_debut']) ? '&date_debut='.$date_debut : '' ?><?= isset($_GET['date_fin']) ? '&date_fin='.$date_fin : '' ?><?= isset($_GET['id_depot']) ? '&id_depot='.$id_depot : '' ?><?= isset($_GET['type_sortie']) ? '&type_sortie='.$type_sortie : '' ?><?= isset($_GET['etat']) ? '&etat='.$etat : '' ?><?= isset($_GET['search']) ? '&search='.$search : '' ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                            <a class="page-link" href="?view=stock/stock.sortie.list&page=<?= $i ?><?= isset($_GET['date_debut']) ? '&date_debut='.$date_debut : '' ?><?= isset($_GET['date_fin']) ? '&date_fin='.$date_fin : '' ?><?= isset($_GET['id_depot']) ? '&id_depot='.$id_depot : '' ?><?= isset($_GET['type_sortie']) ? '&type_sortie='.$type_sortie : '' ?><?= isset($_GET['etat']) ? '&etat='.$etat : '' ?><?= isset($_GET['search']) ? '&search='.$search : '' ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?view=stock/stock.sortie.list&page=<?= $page+1 ?><?= isset($_GET['date_debut']) ? '&date_debut='.$date_debut : '' ?><?= isset($_GET['date_fin']) ? '&date_fin='.$date_fin : '' ?><?= isset($_GET['id_depot']) ? '&id_depot='.$id_depot : '' ?><?= isset($_GET['type_sortie']) ? '&type_sortie='.$type_sortie : '' ?><?= isset($_GET['etat']) ? '&etat='.$etat : '' ?><?= isset($_GET['search']) ? '&search='.$search : '' ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?view=stock/stock.sortie.list&page=<?= $totalPages ?><?= isset($_GET['date_debut']) ? '&date_debut='.$date_debut : '' ?><?= isset($_GET['date_fin']) ? '&date_fin='.$date_fin : '' ?><?= isset($_GET['id_depot']) ? '&id_depot='.$id_depot : '' ?><?= isset($_GET['type_sortie']) ? '&type_sortie='.$type_sortie : '' ?><?= isset($_GET['etat']) ? '&etat='.$etat : '' ?><?= isset($_GET['search']) ? '&search='.$search : '' ?>">
                                            <i class="bi bi-chevron-double-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                        
                        <div class="mt-3 text-center">
                            <p>Affichage de <?= min(($page - 1) * $limit + 1, $totalItems) ?> à <?= min($page * $limit, $totalItems) ?> sur <?= $totalItems ?> sorties</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main><!-- End #main -->

<!-- Modal pour les options d'impression -->
<div class="modal fade" id="printOptionsModal" tabindex="-1" aria-labelledby="printOptionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="printOptionsModalLabel">Options d'impression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Disposition:</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="printLayout" id="layoutFull" value="full" checked>
                        <label class="form-check-label" for="layoutFull">
                            1 bon par page (standard)
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="printLayout" id="layoutCompact" value="compact">
                        <label class="form-check-label" for="layoutCompact">
                            2 bons par page (si possible)
                        </label>
                    </div>
                </div>
                <input type="hidden" id="sortieIdToPrint" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="printBtn">Imprimer</button>
            </div>
        </div>
    </div>
</div>


<script>
    // Fonction pour ouvrir le modal d'impression et stocker l'ID de la sortie
function openPrintModal(idSortie) {
    document.getElementById('sortieIdToPrint').value = idSortie;
    var modal = new bootstrap.Modal(document.getElementById('printOptionsModal'));
    modal.show();
}

// Fonction pour l'impression avec les options sélectionnées
document.getElementById('printBtn').addEventListener('click', function() {
    const idSortie = document.getElementById('sortieIdToPrint').value;
    const layout = document.querySelector('input[name="printLayout"]:checked').value;
    const layoutParam = layout === 'compact' ? '&format=compact' : '';
    const printUrl = 'controller/stock.sortie.print.php?id=' + idSortie + layoutParam;
    
    window.open(printUrl, '_blank');
    $('#printOptionsModal').modal('hide');
});

    // Fonction pour confirmer la validation d'une sortie
    function confirmerValidation(idSortie) {
        Swal.fire({
            title: 'Confirmer la validation',
            text: "Voulez-vous vraiment valider cette sortie de stock ? Cette action est irréversible et mettra à jour les stocks.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, valider',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/validate_sortie_stock.php?id=' + idSortie;
            }
        });
    }
    
    // Fonction pour confirmer l'annulation d'une sortie
    function confirmerAnnulation(idSortie) {
        Swal.fire({
            title: 'Confirmer l\'annulation',
            text: "Voulez-vous vraiment annuler cette sortie de stock ?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, annuler',
            cancelButtonText: 'Non'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/cancel_sortie_stock.php?id=' + idSortie;
            }
        });
    }
    
    // Validation des dates (date de fin ≥ date de début)
    document.getElementById('date_fin').addEventListener('change', function() {
        const dateDebut = document.getElementById('date_debut').value;
        const dateFin = this.value;
        
        if (dateDebut && dateFin && dateDebut > dateFin) {
            Swal.fire({
                title: 'Erreur de date',
                text: 'La date de fin doit être supérieure ou égale à la date de début',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            this.value = dateDebut;
        }
    });
    
    // Validation de la date de début (date de début ≤ date de fin si celle-ci est définie)
    document.getElementById('date_debut').addEventListener('change', function() {
        const dateDebut = this.value;
        const dateFin = document.getElementById('date_fin').value;
        
        if (dateDebut && dateFin && dateDebut > dateFin) {
            Swal.fire({
                title: 'Erreur de date',
                text: 'La date de début doit être inférieure ou égale à la date de fin',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            this.value = dateFin;
        }
    });
</script>

<?php include "./views/include/footer.php"; ?>


