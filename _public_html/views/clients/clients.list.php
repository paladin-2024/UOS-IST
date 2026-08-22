<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération de la liste des clients
$query = "SELECT * FROM client ORDER BY date_creation DESC LIMIT 100";
$stmt = $db->prepare($query);
$stmt->execute();
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES CLIENTS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Ventes</li>
                <li class="breadcrumb-item active">Clients</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table Clients -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Liste des clients
                                    <span>
                                        | <a href="clients/clients.add" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter
                                        </a>
                                        | <a data-bs-toggle="modal" data-bs-target="#importClientModal" class="btnPage">
                                            <i class="bi bi-file-earmark-excel-fill"></i> Importer
                                        </a>
                                        | <a href="controller/export_clients.php" target="_blank" class="btnPage">
                                            <i class="bi bi-file-earmark-excel-fill"></i> Exporter
                                        </a>
                                    </span>
                                </h5>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <input type="text" id="searchInput" class="form-control" placeholder="Rechercher un client...">
                                            <button class="btn btn-primary" type="button" id="searchButton">
                                                <i class="bi bi-search"></i> Rechercher
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <select id="filterType" class="form-select">
                                            <option value="">Tous les types</option>
                                            <option value="Particulier">Particulier</option>
                                            <option value="Entreprise">Entreprise</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select id="filterStatus" class="form-select">
                                            <option value="">Tous les statuts</option>
                                            <option value="1">Actif</option>
                                            <option value="0">Inactif</option>
                                        </select>
                                    </div>
                                </div>

                                <table class="table table-striped table-bordered datatable">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Code</th>
                                            <th scope="col">Nom</th>
                                            <th scope="col">Type</th>
                                            <th scope="col">Téléphone</th>
                                            <th scope="col">Email</th>
                                            <th scope="col">Statut</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $i = 1;
                                        foreach ($clients as $client) {
                                            $statut = $client['actif'] ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-danger">Inactif</span>';
                                            
                                            echo "
                                            <tr>
                                                <td>{$i}</td>
                                                <td>{$client['code_client']}</td>
                                                <td>{$client['nom_client']}</td>
                                                <td>{$client['type_client']}</td>
                                                <td>{$client['telephone']}</td>
                                                <td>{$client['email']}</td>
                                                <td>{$statut}</td>
                                                <td>
                                                    <a href='clients/clients.view&id={$client['id_client']}' class='btn btn-sm btn-info'>
                                                        <i class='bi bi-eye'></i>
                                                    </a>
                                                    <a href='clients/clients.edit&id={$client['id_client']}' class='btn btn-sm btn-warning'>
                                                        <i class='bi bi-pencil-square'></i>
                                                    </a>
                                                    <button onclick='confirmDelete({$client['id_client']})' class='btn btn-sm btn-danger'>
                                                        <i class='bi bi-trash'></i>
                                                    </button>
                                                </td>
                                            </tr>";
                                            $i++;
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div><!-- End Table -->
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour importer des clients -->
<div class="modal fade" id="importClientModal" tabindex="-1" role="dialog" aria-labelledby="importClientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importClientModalLabel">Importer des clients</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="controller/import_clients.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="excelFile" class="form-label">Fichier Excel</label>
                        <input type="file" class="form-control" id="excelFile" name="excelFile" accept=".xlsx, .xls" required>
                    </div>
                    <div class="alert alert-info">
                        <strong>Note:</strong> Le fichier Excel doit contenir les colonnes suivantes : Code, Nom, Type, Téléphone, Email, Adresse, NIF, RCCM.
                    </div>
                    <button type="submit" class="btn btn-primary">Importer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(idClient) {
        Swal.fire({
            title: 'Êtes-vous sûr?',
            text: "Cette action ne peut pas être annulée!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer!',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/delete_client.php?id=' + idClient;
            }
        });
    }

    // Filtrage dynamique des clients
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
        $('#filterType, #filterStatus').on('change', function() {
            let typeFilter = $('#filterType').val();
            let statusFilter = $('#filterStatus').val();
            
            dataTable.column(3).search(typeFilter).draw();
            
            if (statusFilter) {
                dataTable.column(6).search(statusFilter === '1' ? 'Actif' : 'Inactif').draw();
            } else {
                dataTable.column(6).search('').draw();
            }
        });
    });
</script>

<?php include "./views/include/footer.php"; ?>
