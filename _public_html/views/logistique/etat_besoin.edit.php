<?php
include "./views/include/header.php";
$structure = new Structure();
$userId = $_SESSION['id']; // Assuming user ID is stored in session

// Handle search query
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$etatDeBesoins = $structure->getEtatDeBesoinsByUserStructure($userId, $searchQuery, 50); // Retrieve états de besoin with search and limit
$services = $structure->getServicesByUserAccess($userId); // Fetch available services
$budgetLines = $structure->getLignesDepenseByUser($userId); // Fetch budget lines accessible by the user

?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>MISE A JOUR ÉTATS DE BESOIN</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">États de Besoin</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12 mb-3">
                <form method="GET" action="" class="mb-3">
                    <div class="input-group">
                        <input type="hidden" name="view" value="logistique/etat_besoin.edit">
                        <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" class="form-control" placeholder="Rechercher un état de besoin...">
                        <button type="submit" class="btn btn-primary">Rechercher</button>
                    </div>
                </form>
            </div>

            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des états de besoin</h5>
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Numéro</th>
                                    <th>Libellé</th>
                                    <th>Montant</th>
                                    <th>Date</th>
                                    <th>Etat</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 1;
                                foreach ($etatDeBesoins as $etat) {
                                    $uniqueNumber = sprintf("EDB-%05d", $etat['idEtat_de_besoin']);
                                    if($etat['validation1']==null) $etat2='Encours'; else $etat2='Valid';
                                    $dateE=date('d-m-Y',strtotime($etat['dateElaboration']));
                                    echo "<tr>
                                        <td>{$i}</td>
                                        <td>{$uniqueNumber}</td>
                                        <td>{$etat['libelle']}</td>
                                        <td>{$etat['montant']}</td>
                                        <td>{$dateE}</td>
                                        <td><span class='btn btn-outline-danger'>{$etat2} / {$etat['statut']}</span></td>
                                        <td>
                                            <button class='btn btn-sm btn-info' data-bs-toggle='collapse' data-bs-target='#collapseLignes{$etat['idEtat_de_besoin']}'>
                                                <i class='bi bi-list'></i>
                                            </button>
                                            <button class='btn btn-sm btn-warning' onclick='editEtatDeBesoin({$etat['idEtat_de_besoin']}, \"{$etat['libelle']}\", {$etat['montant']}, {$etat['idLigne_depense']})'>
                                                <i class='bi bi-pencil'></i>
                                            </button>
                                            <button class='btn btn-sm btn-success' onclick='addLigne({$etat['idEtat_de_besoin']})'>
                                                <i class='bi bi-plus'></i>
                                            </button>
                                            <button class='btn btn-sm btn-secondary' onclick='printEtatDeBesoin({$etat['idEtat_de_besoin']})'>
                                                <i class='bi bi-printer'></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class='collapse-row'>
                                        <td colspan='7'>
                                            <div class='collapse' id='collapseLignes{$etat['idEtat_de_besoin']}'>
                                                <table class='table table-sm table-bordered m-0'>
                                                    <thead>
                                                        <tr>
                                                            <th>Désignation</th>
                                                            <th>Quantité</th>
                                                            <th>Prix Unitaire</th>
                                                            <th>Prix Total</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>";
                                    $lignes = $structure->getLignesEtatBesoinByEtat($etat['idEtat_de_besoin']);
                                    foreach ($lignes as $ligne) {
                                        $pt=$ligne['quantite']*$ligne['prixUnitaire'];
                                        echo "<tr>
                                                <td>{$ligne['designation']}</td>
                                                <td>{$ligne['quantite']}</td>
                                                <td>USD {$ligne['prixUnitaire']}</td>
                                                <td>USD {$pt}</td>
                                                <td>
                                                    <a class='btn btn-sm btn-danger' onclick='confirmDeleteLigne({$ligne['idLigne_etat_besoin']},{$ligne['Etat_de_besoin_idEtat_de_besoin']})'>
                                                        <i class='bi bi-trash'></i>
                                                    </a>
                                                    <a class='btn btn-sm btn-warning' onclick='editLigne({$ligne['idLigne_etat_besoin']},\"{$ligne['designation']}\",{$ligne['quantite']},{$ligne['prixUnitaire']},\"{$ligne['observation']}\",{$ligne['Etat_de_besoin_idEtat_de_besoin']})'>
                                                        <i class='bi bi-pencil'></i>
                                                    </a>
                                                </td>
                                            </tr>";
                                    }
                                    echo "</tbody>
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

<!-- Modal Ajouter Ligne -->
<div class="modal fade" id="addLigneModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une Ligne</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/add_ligne_etat_besoin.php" class="needs-validation" novalidate>
                    <input type="hidden" name="etatId" id="addEtatId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="designation" id="addLigneDesignation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="quantite" class="form-label">Quantité <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="quantite" id="addLigneQuantite" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une quantité.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="prixUnitaire" class="form-label">Prix Unitaire</label>
                            <input type="number" step="0.01" name="prixUnitaire" id="addLignePrixUnitaire" class="form-control">
                            <div class="invalid-feedback">Veuillez saisir un prix unitaire.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="observation" class="form-label">Observation</label>
                            <input type="text" name="observation" id="addLigneObservation" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Ajouter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Modal Modifier Ligne -->
<div class="modal fade" id="editLigneModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une Ligne</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
            <form method="POST" action="controller/edit_ligne_etat_besoin.php" class="needs-validation" novalidate>
                    <input type="hidden" name="etatId" id="editEtatId">
                    <input type="hidden" name="ligneId" id="editLigneId">

                    <input type="hidden" name="lastQT" id="lastQT">
                    <input type="hidden" name="lastPU" id="lastPU">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="designation" id="editLigneDesignation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="quantite" class="form-label">Quantité <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="quantite" id="editLigneQuantite" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une quantité.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="prixUnitaire" class="form-label">Prix Unitaire</label>
                            <input type="number" step="0.01" name="prixUnitaire" id="editLignePrixUnitaire" class="form-control">
                            <div class="invalid-feedback">Veuillez saisir un prix unitaire.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="observation" class="form-label">Observation</label>
                            <input type="text" name="observation" id="editLigneObservation" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Modifier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajouter État de Besoin -->
<div class="modal fade" id="addEtatDeBesoinModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un État de Besoin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addEtatDeBesoinForm" action="controller/create_etat_de_besoin.php" method="POST" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="dateElaboration" class="form-label">Date d'Élaboration <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="dateElaboration" name="dateElaboration" required>
                            <div class="invalid-feedback">Veuillez saisir une date d'élaboration.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="libelle" class="form-label">Libellé <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="libelle" name="libelle" required>
                            <div class="invalid-feedback">Veuillez saisir un libellé.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="serviceId" class="form-label">Service <span class="text-danger">*</span></label>
                            <select class="form-select" id="serviceId" name="serviceId" required>
                                <option value="">Sélectionner un service</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?= $service['idService'] ?>"><?= $service['designation']." / ".$service['des_structure'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un service.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="ligneDepenseId" class="form-label">Ligne Budgétaire</label>
                            <select class="form-select" id="ligneDepenseId" name="ligneDepenseId">
                                <option value="">Sélectionner une ligne budgétaire</option>
                                <?php foreach ($budgetLines as $line): ?>
                                    <option value="<?= $line['idligne_depense_structure'] ?>"><?= $line['designation'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Ajouter
                    </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Modifier État de Besoin -->
<div class="modal fade" id="editEtatDeBesoinModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un État de Besoin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_etat_de_besoin.php" class="needs-validation" novalidate>
                    <input type="hidden" name="idEtatDeBesoin" id="editEtatDeBesoinId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="libelle" class="form-label">Libellé <span class="text-danger">*</span></label>
                            <input type="text" name="libelle" id="editEtatDeBesoinLibelle" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir un libellé.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="montant" class="form-label">Montant <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="montant" id="editEtatDeBesoinMontant" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir un montant.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="ligneDepenseId" class="form-label">Ligne Budgétaire</label>
                            <select class="form-select" id="editLigneDepenseId" name="ligneDepenseId">
                                <option value="">Sélectionner une ligne budgétaire</option>
                                <?php foreach ($budgetLines as $line): ?>
                                    <option value="<?= $line['idligne_depense_structure'] ?>"><?= $line['designation'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>



<script>
    function editEtatDeBesoin(id, libelle, montant,ligne) {
        document.getElementById('editEtatDeBesoinId').value = id;
        document.getElementById('editEtatDeBesoinLibelle').value = libelle;
        document.getElementById('editEtatDeBesoinMontant').value = montant;
        document.getElementById('editLigneDepenseId').value = ligne;
        const modal = new bootstrap.Modal(document.getElementById('editEtatDeBesoinModal'));
        modal.show();
    }

    function editLigne(id, designation, quantite, prixUnitaire, observation,etatId) {
        document.getElementById('editLigneId').value = id;
        document.getElementById('editLigneDesignation').value = designation;
        document.getElementById('editLigneQuantite').value = quantite;
        document.getElementById('lastQT').value = quantite;
        document.getElementById('editLignePrixUnitaire').value = prixUnitaire;
        document.getElementById('lastPU').value = prixUnitaire;
        document.getElementById('editLigneObservation').value = observation;
        document.getElementById('editEtatId').value = etatId;
        
        const modal = new bootstrap.Modal(document.getElementById('editLigneModal'));
        modal.show();
    }

    function confirmDeleteLigne(id,etatId) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action est irréversible !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/delete_ligne_etat_besoin.php?id=' + id +'&etatId='+etatId;
            }
        });
    }

    function addLigne(etatId) {
        document.getElementById('addEtatId').value = etatId;
        const modal = new bootstrap.Modal(document.getElementById('addLigneModal'));
        modal.show();
    }

    document.getElementById('searchInput').addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            window.location.href = 'logistique/etat_besoin.edit?search=' + encodeURIComponent(this.value.trim());
        }
    });

    function addLigne(etatId) {
        document.getElementById('addEtatId').value = etatId;
        const modal = new bootstrap.Modal(document.getElementById('addLigneModal'));
        modal.show();
    }

    function printEtatDeBesoin(id) {
        // Redirect to a print page or open a print dialog
        window.open('logistique/print_etat_de_besoin?id=' + id, '_blank');
    }
</script>

<?php include "./views/include/footer.php"; ?>