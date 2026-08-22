<?php
include "./views/include/header.php";
$structure = new Structure();
$userId = $_SESSION['id'];

$structures = $structure->getStructures();

$depots = $structure->getDepotsByStructure($userId); // Fetch depots accessible to the user

$fournisseurs=$structure->getFournisseursByUserAccess($userId);

// Handle search query
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$entries = $structure->getEntreesByUserAccess($userId, $searchQuery, 1500); // Fetch entries accessible to the user
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES DÉPÔTS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Gestion des Dépôts</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12 mb-3">
                <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addEntryModal">
                    <i class="bi bi-plus"></i> Nouvelle Entrée
                </button>
                <input type="text" id="searchInput" class="form-control" placeholder="Rechercher un dépôt..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>

            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Liste des Entrées</h5>
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Fournisseur</th>
                                    <th scope="col">Référence</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="entryTableBody">
                                <?php
                                $i = 1;
                                foreach ($entries as $entry) {
                                    $date=date('d-m-Y',strtotime($entry['dateOperation']));
                                    echo "
                                    <tr>
                                        <td>{$i}</td>
                                        <td>{$date}</td>
                                        <td>{$entry['fournisseur']}</td>
                                        <td>{$entry['reference_document']}</td>
                                        <td>
                                            <button class='btn btn-sm btn-info' data-bs-toggle='collapse' 
                                                data-bs-target='#collapseDetails{$entry['idManifeste_entree']}'>
                                                <i class='bi bi-eye'></i> Détails
                                            </button>

                                            <button class='btn btn-sm btn-success' data-bs-toggle='modal' 
                                                data-bs-target='#modalAddDetail{$entry['idManifeste_entree']}'>
                                                <i class='bi bi-plus'></i> Ajouter Détail
                                            </button>

                                            <button class='btn btn-sm btn-warning' onclick='editEntry(
                                                {$entry['idManifeste_entree']}, 
                                                \"{$entry['dateOperation']}\",
                                                \"{$entry['transporteur']}\",
                                                \"{$entry['reference_document']}\",
                                                \"{$entry['Depot_idDepot']}\",
                                                \"{$entry['Fournisseur_idFournisseur']}\"
                                            )'>
                                                <i class='bi bi-pencil-square'></i> Modifier
                                            </button>

                                            <button class='btn btn-sm btn-danger' onclick='confirmDeleteEntry({$entry['idManifeste_entree']})'>
                                                <i class='bi bi-trash'></i> Supprimer
                                            </button>
                                            <button class='btn btn-sm btn-secondary' onclick='printEntry({$entry['idManifeste_entree']})'>
                                                <i class='bi bi-printer'></i> Imprimer
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class='collapse-row'>
                                        <td colspan='5' class='p-0'>
                                            <div class='collapse' id='collapseDetails{$entry['idManifeste_entree']}'>
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
                                    
                                    $details = $structure->getDetailsEntreeByManifest($entry['idManifeste_entree']);
                                    foreach ($details as $detail) {
                                        echo "
                                        <tr>
                                            <td>{$detail['designation']}</td>
                                            <td>{$detail['unite']}</td>
                                            <td>{$detail['quantite']}</td>
                                            <td>
                                                
                                                <button class='btn btn-sm btn-danger' onclick='confirmDeleteDetail({$detail['idDetail_entree']})'>
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

<!-- Modal for adding an entry -->
<div class="modal fade" id="addEntryModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une Entrée</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addEntryForm" action="controller/create_entree.php" method="POST">
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
                        <div class="col-md-12">
                            <label for="referenceDocument" class="form-label">Référence Document <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="referenceDocument" name="referenceDocument" required>
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
                            <label for="fourni" class="form-label">Sélectionner le fournisseur <span class="text-danger">*</span></label>
                            <select class="form-select" id="fourni" name="fournisseurId" required>
                                <option value="">Sélectionner un fournisseur</option>
                                <?php foreach ($fournisseurs as $fournisseur): ?>
                                    <option value="<?php echo $fournisseur['idFournisseur']; ?>"><?php echo htmlspecialchars($fournisseur['nom']); ?></option>
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

<!-- Modal for editing an entry -->
<div class="modal fade" id="editEntryModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une Entrée</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editEntryForm" action="controller/edit_entree.php" method="POST">
                    <input type="hidden" id="editEntryId" name="idManifeste_entree">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editDateOperation" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="editDateOperation" name="dateOperation" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editTransporteur" class="form-label">Transporteur <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editTransporteur" name="transporteur" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="editReferenceDocument" class="form-label">Référence Document <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editReferenceDocument" name="referenceDocument" required>
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
                            <label for="editFournisseur" class="form-label">Sélectionner le fournisseur <span class="text-danger">*</span></label>
                            <select class="form-select" id="editFournisseur" name="fournisseurId" required>
                                <option value="">Sélectionner un fournisseur</option>
                                <?php foreach ($fournisseurs as $fournisseur): ?>
                                    <option value="<?php echo $fournisseur['idFournisseur']; ?>"><?php echo htmlspecialchars($fournisseur['nom']); ?></option>
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

<!-- Modal for adding details to an entry -->
<?php foreach ($entries as $entry): ?>
<div class="modal fade" id="modalAddDetail<?php echo $entry['idManifeste_entree']; ?>" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Détail à l'Entrée</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/add_detail_to_entry.php" class="needs-validation" novalidate>
                    <input type="hidden" name="idManifeste_entree" value="<?php echo $entry['idManifeste_entree']; ?>">
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
function editEntry(id, dateOperation, transporteur, referenceDocument,depotId, fournisseurId) {
    document.getElementById('editEntryId').value = id;
    document.getElementById('editDateOperation').value = dateOperation;
    document.getElementById('editTransporteur').value = transporteur;
    document.getElementById('editReferenceDocument').value = referenceDocument;
    document.getElementById('editDepot').value = depotId;
    document.getElementById('editFournisseur').value = fournisseurId;

    

    const modal = new bootstrap.Modal(document.getElementById('editEntryModal'));
    modal.show();
}

function confirmDeleteEntry(idManifeste_entree) {
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
            window.location.href = 'controller/deleteEntry.php?id=' + idManifeste_entree;
        }
    })
}


function confirmDeleteDetail(idDetail_entree) {
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
            window.location.href = 'controller/deleteDetail.php?id=' + idDetail_entree;
        }
    })
}

document.getElementById('searchInput').addEventListener('keydown', function(event) {
    if (event.key === 'Enter') {
        const searchValue = this.value.trim();
        window.location.href = 'logistique/depot.entree.add?search=' + encodeURIComponent(searchValue);
    }
});

function printEntry(id) {
        // Redirect to a print page or open a print dialog
        window.open('logistique/printEntry?id=' + id, '_blank');
    }
</script>

<?php include "./views/include/footer.php"; ?>