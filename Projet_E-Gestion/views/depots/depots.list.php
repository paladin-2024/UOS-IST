<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();
// Au début du fichier, après la connexion à la base de données
$userId = $_SESSION['id'];
$userRole = $_SESSION['idRole'];
$isAdmin = ($userRole == 1); // Ajustez selon votre logique de rôles

// Requête de récupération des dépôts
if ($isAdmin) {
    // Les administrateurs voient tous les dépôts
    $query = "SELECT * FROM depot ORDER BY libelle_depot";
    $stmt = $db->prepare($query);
    $stmt->execute();
} else {
    // Les autres utilisateurs ne voient que les dépôts auxquels ils ont accès
    $query = "SELECT d.* FROM depot d
              INNER JOIN autorisation_depot ad ON d.id_depot = ad.id_depot
              WHERE ad.id_user = :user_id AND ad.peut_consulter = 1
              ORDER BY d.libelle_depot";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
}

$depots = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES DÉPÔTS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item active">Dépôts</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table Dépôts -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Liste des dépôts
                                    <span>
                                        | <a href="depots/depots.add" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter
                                        </a>
                                    </span>
                                </h5>

                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <div class="input-group">
                                            <input type="text" id="searchInput" class="form-control" placeholder="Rechercher un dépôt...">
                                            <button class="btn btn-primary" type="button" id="searchButton">
                                                <i class="bi bi-search"></i> Rechercher
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
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
                                            <th scope="col">Libellé</th>
                                            <th scope="col">Adresse</th>
                                            <th scope="col">Responsable</th>
                                            <th scope="col">Statut</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $i = 1;
                                        foreach ($depots as $depot) {
                                            $statut = $depot['actif'] ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-danger">Inactif</span>';
                                            
                                            echo "
                                            <tr>
                                                <td>{$i}</td>
                                                <td>{$depot['code_depot']}</td>
                                                <td>{$depot['libelle_depot']}</td>
                                                <td>{$depot['adresse']}</td>
                                                <td>{$depot['responsable']}</td>
                                                <td>{$statut}</td>
                                                <td>
                                                    <a href='depots/depots.view&id={$depot['id_depot']}' class='btn btn-sm btn-info'>
                                                        <i class='bi bi-eye'></i>
                                                    </a>
                                                    <a href='depots/depots.edit&id={$depot['id_depot']}' class='btn btn-sm btn-warning'>
                                                        <i class='bi bi-pencil-square'></i>
                                                    </a>
                                                    <button onclick='confirmDelete({$depot['id_depot']})' class='btn btn-sm btn-danger'>
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
</main><!-- End #main -->

<script>
    function confirmDelete(idDepot) {
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
                window.location.href = 'controller/delete_depot.php?id=' + idDepot;
            }
        });
    }

    // Filtrage dynamique
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

        // Filtre par statut
        $('#filterStatus').on('change', function() {
            let statusFilter = $('#filterStatus').val();
            
            if (statusFilter) {
                dataTable.column(5).search(statusFilter === '1' ? 'Actif' : 'Inactif').draw();
            } else {
                dataTable.column(5).search('').draw();
            }
        });
    });
</script>

<?php include "./views/include/footer.php"; ?>
