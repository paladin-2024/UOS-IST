<?php
include "./views/include/header.php";
$structure = new Structure();
$userId = $_SESSION['id'];

$structures = $structure->getStructures();

$depots = $structure->getDepotsByStructure($userId); // Fetch depots accessible to the user

$clients = $structure->getClientsByUserAccess($userId); // Fetch clients accessible to the user

// Handle search query
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$sorties = $structure->getSortiesByUserAccess($userId, $searchQuery, 50); // Fetch sorties accessible to the user
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES SORTIES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Gestion des Sorties</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12 mb-3">
                <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addSortieModal">
                    <i class="bi bi-plus"></i> Nouvelle Sortie
                </button>
                <input type="text" id="searchInput" class="form-control" placeholder="Rechercher une sortie..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>

            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Liste des Sorties</h5>
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Client</th>
                                    <th scope="col">Référence</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="sortieTableBody">
                                <?php
                                $i = 1;
                                foreach ($sorties as $sortie) {
                                    $date = date('d-m-Y', strtotime($sortie['dateSortie']));
                                    echo "
                                    <tr>
                                        <td>{$i}</td>
                                        <td>{$date}</td>
                                        <td>{$sortie['client']}</td>
                                        <td>{$sortie['reference_document']}</td>
                                        <td>
                                            <button class='btn btn-sm btn-info' data-bs-toggle='collapse' 
                                                data-bs-target='#collapseDetails{$sortie['idManifeste_sortie']}'>
                                                <i class='bi bi-eye'></i> Détails
                                            </button>

                                            <button class='btn btn-sm btn-success' data-bs-toggle='modal' 
                                                data-bs-target='#modalAddDetail{$sortie['idManifeste_sortie']}'>
                                                <i class='bi bi-plus'></i> Ajouter Détail
                                            </button>

                                            <button class='btn btn-sm btn-warning' onclick='editSortie(
                                                {$sortie['idManifeste_sortie']}, 
                                                \"{$sortie['dateSortie']}\",
                                                \"{$sortie['transporteur']}\",
                                                \"{$sortie['reference_document']}\",
                                                \"{$sortie['motif']}\",
                                                \"{$sortie['Depot_idDepot']}\",
                                                \"{$sortie['Client_idClient']}\"
                                            )'>
                                                <i class='bi bi-pencil-square'></i> Modifier
                                            </button>

                                            <button class='btn btn-sm btn-danger' onclick='confirmDeleteSortie({$sortie['idManifeste_sortie']})'>
                                                <i class='bi bi-trash'></i> Supprimer
                                            </button>
                                            <button class='btn btn-sm btn-secondary' onclick='printSortie({$sortie['idManifeste_sortie']})'>
                                                <i class='bi bi-printer'></i> Imprimer
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class='collapse-row'>
                                        <td colspan='5' class='p-0'>
                                            <div class='collapse' id='collapseDetails{$sortie['idManifeste_sortie']}'>
                                                <table class='table table-sm table-bordered m-0'>
                                                    <thead>
                                                        <tr>
                                                            <th>Désignation</th>
                                                            <th>Unité</th>
                                                            <th>Quantité</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>";
                                    
                                    $details = $structure->getDetailsSortieByManifest($sortie['idManifeste_sortie']);
                                    foreach ($details as $detail) {
                                        echo "
                                        <tr>
                                            <td>{$detail['designation']}</td>
                                            <td>{$detail['unite']}</td>
                                            <td>{$detail['quantite']}</td>
                                            <td>
                                                
                                                <button class='btn btn-sm btn-danger' onclick='confirmDeleteDetail({$detail['idDetail_sortie']})'>
                                                    <i class='bi bi-trash'></i> Supprimer
                                                </button>
                                            </td>
                                        </tr>";
                                    }
                                    echo "
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
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
</main>

<!-- Modal for adding a sortie -->
<div class="modal fade" id="addSortieModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une Sortie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addSortieForm" action="controller/create_sortie.php" method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="dateOperation" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="dateOperation" name="dateOperation" required>
                        </div>
                        <div class="col-md-6">
                            <label for="transporteur" class="form-label">Transporteur <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="transporteur" name="transporteur" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="referenceDocument" class="form-label">Référence Document <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="referenceDocument" name="referenceDocument" required>
                        </div>
                        <div class="col-md-6">
                            <label for="observation" class="form-label">Motif</span></label>
                            <input type="text" class="form-control" id="observation" name="observation">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="depot" class="form-label">Sélectionner le Dépôt <span class="text-danger">*</span></label>
                            <select class="form-select" id="depot" name="depotId" required>
                                <option value="">Sélectionner un dépôt</option>
                                <?php foreach ($depots as $depot): ?>
                                    <option value="<?php echo $depot['idDepot']; ?>"><?php echo htmlspecialchars($depot['designation']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="clientSelect" class="form-label">Sélectionner le client <span class="text-danger">*</span></label>
                            <select class="form-select" id="clientSelect" name="clientId" required>
                                <option value="">Sélectionner un client</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['idClient']; ?>"><?php echo htmlspecialchars($client['noms']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Ajouter
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal for editing a sortie -->
<div class="modal fade" id="editSortieModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une Sortie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editSortieForm" action="controller/edit_sortie.php" method="POST">
                    <input type="hidden" id="editSortieId" name="idManifeste_sortie">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editDateOperation" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="editDateOperation" name="dateOperation" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editClient" class="form-label">Transporteur <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editClient" name="transporteur" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editReferenceDocument" class="form-label">Référence Document <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editReferenceDocument" name="referenceDocument" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editObservation" class="form-label">Motif</span></label>
                            <input type="text" class="form-control" id="editObservation" name="observation">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editDepot" class="form-label">Sélectionner le Dépôt <span class="text-danger">*</span></label>
                            <select class="form-select" id="editDepot" name="depotId" required>
                                <option value="">Sélectionner un dépôt</option>
                                <?php foreach ($depots as $depot): ?>
                                    <option value="<?php echo $depot['idDepot']; ?>"><?php echo htmlspecialchars($depot['designation']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editClientSelect" class="form-label">Sélectionner le client <span class="text-danger">*</span></label>
                            <select class="form-select" id="editClientSelect" name="clientId" required>
                                <option value="">Sélectionner un client</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['idClient']; ?>"><?php echo htmlspecialchars($client['noms']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Enregistrer les modifications
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal for adding details to a sortie -->
<?php foreach ($sorties as $sortie): ?>
<div class="modal fade" id="modalAddDetail<?php echo $sortie['idManifeste_sortie']; ?>" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Détail à la Sortie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/add_detail_to_sortie.php" class="needs-validation" novalidate>
                    <input type="hidden" name="idManifeste_sortie" value="<?php echo $sortie['idManifeste_sortie']; ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="designation" required>
                        </div>
                        <div class="col-md-3">
                            <label for="unite" class="form-label">Unité <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="unite" required>
                        </div>
                        <div class="col-md-3">
                            <label for="quantite" class="form-label">Quantité <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="quantite" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addDetailBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Ajouter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
function editSortie(id, dateOperation, client, referenceDocument,motif, depotId, clientId) {
    document.getElementById('editSortieId').value = id;
    document.getElementById('editDateOperation').value = dateOperation;
    document.getElementById('editClient').value = client;
    document.getElementById('editReferenceDocument').value = referenceDocument;
    document.getElementById('editObservation').value = motif;
    document.getElementById('editDepot').value = depotId;
    document.getElementById('editClientSelect').value = clientId;

    const modal = new bootstrap.Modal(document.getElementById('editSortieModal'));
    modal.show();
}

function confirmDeleteSortie(idManifeste_sortie) {
    Swal.fire({
        title: 'Êtes-vous sûr ?',
        text: "Cette action est irréversible !",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'controller/deleteSortie.php?id=' + idManifeste_sortie;
        }
    })
}

function confirmDeleteDetail(idDetail_sortie) {
    Swal.fire({
        title: 'Êtes-vous sûr ?',
        text: "Cette action est irréversible !",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'controller/deleteDetailSortie.php?id=' + idDetail_sortie;
        }
    })
}

document.getElementById('searchInput').addEventListener('keydown', function(event) {
    if (event.key === 'Enter') {
        const searchValue = this.value.trim();
        window.location.href = 'logistique/depot.sortie.add?search=' + encodeURIComponent(searchValue);
    }
});

function printSortie(id) {
    // Redirect to a print page or open a print dialog
    window.open('logistique/printSortie?id=' + id, '_blank');
}
</script>

<?php include "./views/include/footer.php"; ?>