<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des identifiants utilisateur et de son rôle
$userId = $_SESSION['id'];
$userRole = $_SESSION['idRole']; 
$isAdmin = ($userRole == 1); // Ajustez selon votre logique de rôles

// Récupération des dépôts auxquels l'utilisateur a accès
if ($isAdmin) {
    // Les administrateurs ont accès à tous les dépôts
    $queryDepots = "SELECT id_depot, libelle_depot FROM depot WHERE actif = 1 ORDER BY libelle_depot";
    $stmtDepots = $db->prepare($queryDepots);
    $stmtDepots->execute();
} else {
    // Utilisateurs normaux - seulement les dépôts autorisés
    $queryDepots = "SELECT d.id_depot, d.libelle_depot 
                    FROM depot d
                    INNER JOIN autorisation_depot ad ON d.id_depot = ad.id_depot
                    WHERE ad.id_user = :user_id AND ad.peut_consulter = 1 AND d.actif = 1
                    ORDER BY d.libelle_depot";
    $stmtDepots = $db->prepare($queryDepots);
    $stmtDepots->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmtDepots->execute();
}
$depots = $stmtDepots->fetchAll(PDO::FETCH_ASSOC);

// Créer une liste des IDs de dépôts accessibles pour la requête
$accessibleDepotIds = [];
foreach($depots as $depot) {
    $accessibleDepotIds[] = $depot['id_depot'];
}

// Si l'utilisateur n'a accès à aucun dépôt, afficher un message
if (empty($accessibleDepotIds) && !$isAdmin) {
    echo "<script>
        Swal.fire({
            icon: 'warning',
            title: 'Accès limité',
            text: 'Vous n\'avez accès à aucun dépôt. Veuillez contacter l\'administrateur.'
        }).then(() => {
            window.location.href = 'dashboard';
        });
    </script>";
    exit;
}

// Construction de la condition pour les dépôts accessibles
$depotCondition = "";
if (!$isAdmin && !empty($accessibleDepotIds)) {
    $placeholders = implode(',', array_fill(0, count($accessibleDepotIds), '?'));
    $depotCondition = " AND e.id_depot IN ($placeholders)";
}

// Récupération des entrées de stock (filtrées selon les permissions)
$query = "SELECT e.*, d.libelle_depot 
          FROM entree_stock e 
          LEFT JOIN depot d ON e.id_depot = d.id_depot
          WHERE 1=1" . $depotCondition . "
          ORDER BY e.date_creation DESC 
          LIMIT 100";
$stmt = $db->prepare($query);

// Binding des ID de dépôts si nécessaire
if (!$isAdmin && !empty($accessibleDepotIds)) {
    $paramIndex = 1;
    foreach ($accessibleDepotIds as $depotId) {
        $stmt->bindValue($paramIndex++, $depotId, PDO::PARAM_INT);
    }
}

$stmt->execute();
$entrees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Préparation des permissions par dépôt pour les actions (modifier, valider)
$userPermissions = [];
if (!$isAdmin) {
    $queryPerms = "SELECT id_depot, peut_modifier, peut_valider 
                  FROM autorisation_depot 
                  WHERE id_user = :user_id";
    $stmtPerms = $db->prepare($queryPerms);
    $stmtPerms->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmtPerms->execute();
    
    while ($perm = $stmtPerms->fetch(PDO::FETCH_ASSOC)) {
        $userPermissions[$perm['id_depot']] = [
            'peut_modifier' => $perm['peut_modifier'],
            'peut_valider' => $perm['peut_valider']
        ];
    }
}
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>ENTRÉES DE STOCK</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item active">Entrées</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Liste des entrées de stock
                            <span>
                                <?php 
                                // N'afficher le bouton d'ajout que si l'utilisateur a accès à au moins un dépôt avec droits de modification
                                $canAddToAnyDepot = $isAdmin;
                                if (!$isAdmin) {
                                    foreach ($userPermissions as $depotId => $perms) {
                                        if ($perms['peut_modifier']) {
                                            $canAddToAnyDepot = true;
                                            break;
                                        }
                                    }
                                }
                                
                                if ($canAddToAnyDepot): 
                                ?>
                                | <a href="stock/stock.entree.add" class="btnPage">
                                    <i class="bi bi-plus-circle-fill"></i> Nouvelle entrée
                                </a>
                                <?php endif; ?>
                            </span>
                        </h5>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" id="searchInput" class="form-control" placeholder="Rechercher...">
                                    <button class="btn btn-primary" type="button" id="searchButton">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select id="filterDepot" class="form-select">
                                    <option value="">Tous les dépôts</option>
                                    <?php foreach ($depots as $depot): ?>
                                        <option value="<?= $depot['id_depot'] ?>"><?= htmlspecialchars($depot['libelle_depot']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select id="filterType" class="form-select">
                                    <option value="">Tous les types</option>
                                    <option value="Achat">Achat</option>
                                    <option value="Transfert">Transfert</option>
                                    <option value="Inventaire">Inventaire</option>
                                    <option value="Autre">Autre</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select id="filterEtat" class="form-select">
                                    <option value="">Tous les états</option>
                                    <option value="En cours">En cours</option>
                                    <option value="Validé">Validé</option>
                                    <option value="Annulé">Annulé</option>
                                </select>
                            </div>
                        </div>

                        <table class="table table-striped table-bordered datatable">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Numéro</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Dépôt</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Référence</th>
                                    <th scope="col">État</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 1;
                                foreach ($entrees as $entree) {
                                    $etat_badge = '';
                                    switch ($entree['etat']) {
                                        case 'En cours':
                                            $etat_badge = '<span class="badge bg-warning">En cours</span>';
                                            break;
                                        case 'Validé':
                                            $etat_badge = '<span class="badge bg-success">Validé</span>';
                                            break;
                                        case 'Annulé':
                                            $etat_badge = '<span class="badge bg-danger">Annulé</span>';
                                            break;
                                    }
                                    
                                    // Vérifier les permissions pour ce dépôt spécifique
                                    $canModify = $isAdmin || 
                                        (isset($userPermissions[$entree['id_depot']]) && 
                                         $userPermissions[$entree['id_depot']]['peut_modifier'] == 1);
                                    
                                    $canValidate = $isAdmin || 
                                        (isset($userPermissions[$entree['id_depot']]) && 
                                         $userPermissions[$entree['id_depot']]['peut_valider'] == 1);
                                    
                                    echo "
                                    <tr>
                                        <td>{$i}</td>
                                        <td>{$entree['numero_entree']}</td>
                                        <td>" . date('d/m/Y', strtotime($entree['date_entree'])) . "</td>
                                        <td>{$entree['libelle_depot']}</td>
                                        <td>{$entree['type_entree']}</td>
                                        <td>" . ($entree['reference_document'] ?: '-') . "</td>
                                        <td>{$etat_badge}</td>
                                        <td>
                                            <a href='stock/stock.entree.view&id={$entree['id_entree']}' class='btn btn-sm btn-info'>
                                                <i class='bi bi-eye'></i>
                                            </a>";
                                            
                                            if ($entree['etat'] == 'En cours') {
                                                if ($canModify) {
                                                    echo "
                                                    <a href='stock/stock.entree.edit&id={$entree['id_entree']}' class='btn btn-sm btn-warning'>
                                                        <i class='bi bi-pencil-square'></i>
                                                    </a>";
                                                }
                                                
                                                if ($canValidate) {
                                                    echo "
                                                    <button onclick='confirmValidate({$entree['id_entree']})' class='btn btn-sm btn-success'>
                                                        <i class='bi bi-check-lg'></i>
                                                    </button>
                                                    <button onclick='confirmCancel({$entree['id_entree']})' class='btn btn-sm btn-danger'>
                                                        <i class='bi bi-x-lg'></i>
                                                    </button>";
                                                }
                                            }
                                            
                                        echo "</td>
                                    </tr>";
                                    $i++;
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main><!-- End #main -->

<script>
    function confirmValidate(idEntree) {
        Swal.fire({
            title: 'Confirmer la validation?',
            text: "Cette action va valider définitivement l'entrée de stock.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, valider',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/validate_entree_stock.php?id=' + idEntree;
            }
        });
    }
    
    function confirmCancel(idEntree) {
        Swal.fire({
            title: 'Confirmer l\'annulation?',
            text: "Cette action va annuler l'entrée de stock.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, annuler',
            cancelButtonText: 'Retour'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/cancel_entree_stock.php?id=' + idEntree;
            }
        });
    }

    // Filtrage dynamique des entrées
    $(document).ready(function() {
        const dataTable = $('.datatable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/French.json"
            }
        });

        // Recherche
        $('#searchButton').on('click', function() {
            dataTable.search($('#searchInput').val()).draw();
        });

        // Filtres
        $('#filterDepot, #filterType, #filterEtat').on('change', function() {
            let depotFilter = $('#filterDepot').val();
            let typeFilter = $('#filterType').val();
            let etatFilter = $('#filterEtat').val();
            
            // Appliquer les filtres
            dataTable.column(3).search(depotFilter).draw();
            dataTable.column(4).search(typeFilter).draw();
            dataTable.column(6).search(etatFilter).draw();
        });
    });
</script>

<?php include "./views/include/footer.php"; ?>

