<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération de la liste des produits avec leurs catégories
$query = "SELECT p.*, c.libelle_categorie, um.symbole_unite
          FROM produit p 
          LEFT JOIN categorie_produit c ON p.id_categorie = c.id_categorie
          LEFT JOIN unite_mesure um ON p.id_unite_stockage = um.id_unite
          ORDER BY p.date_creation DESC 
          LIMIT 100";
$stmt = $db->prepare($query);
$stmt->execute();
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des catégories pour le filtrage
$queryCategories = "SELECT * FROM categorie_produit ORDER BY libelle_categorie";
$stmtCategories = $db->prepare($queryCategories);
$stmtCategories->execute();
$categories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES PRODUITS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item active">Produits</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table Products -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Liste des produits
                                    <span>
                                        | <a href="produits/produits.add" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter
                                        </a>
                                        | <a data-bs-toggle="modal" data-bs-target="#importProductModal" class="btnPage">
                                            <i class="bi bi-file-earmark-excel-fill"></i> Importer
                                        </a>
                                        | <a href="controller/export_produits.php" target="_blank" class="btnPage">
                                            <i class="bi bi-file-earmark-excel-fill"></i> Exporter
                                        </a>
                                    </span>
                                </h5>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <input type="text" id="searchInput" class="form-control" placeholder="Rechercher un produit...">
                                            <button class="btn btn-primary" type="button" id="searchButton">
                                                <i class="bi bi-search"></i> Rechercher
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <select id="filterCategorie" class="form-select">
                                            <option value="">Toutes les catégories</option>
                                            <?php foreach ($categories as $categorie): ?>
                                                <option value="<?= $categorie['id_categorie'] ?>"><?= htmlspecialchars($categorie['libelle_categorie']) ?></option>
                                            <?php endforeach; ?>
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
                                            <th scope="col">Image</th>
                                            <th scope="col">Libellé</th>
                                            <th scope="col">Catégorie</th>
                                            <th scope="col">Unité de stockage</th>
                                            <th scope="col">Statut</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $i = 1;
                                        foreach ($produits as $produit) {
                                            $statut = $produit['actif'] ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-danger">Inactif</span>';
                                            $imagePath = !empty($produit['image_produit']) 
                                                ? './uploads/produits/' . $produit['image_produit'] 
                                                : './uploads/cube.jpg';
                                            
                                            echo "
                                            <tr>
                                                <td>{$i}</td>
                                                <td>{$produit['code_produit']}</td>
                                                <td><img src='{$imagePath}' alt='{$produit['libelle_produit']}' class='img-thumbnail' style='max-height: 50px;'></td>
                                                <td>{$produit['libelle_produit']}</td>
                                                <td>{$produit['libelle_categorie']}</td>
                                                <td>{$produit['symbole_unite']}</td>
                                                <td>{$statut}</td>
                                                <td>
                                                    <a href='produits/produits.view&id={$produit['id_produit']}' class='btn btn-sm btn-info'>
                                                        <i class='bi bi-eye'></i>
                                                    </a>
                                                    <a href='produits/produits.edit&id={$produit['id_produit']}' class='btn btn-sm btn-warning'>
                                                        <i class='bi bi-pencil-square'></i>
                                                    </a>
                                                    <button onclick='confirmDelete({$produit['id_produit']})' class='btn btn-sm btn-danger'>
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

<!-- Modal pour importer des produits -->
<div class="modal fade" id="importProductModal" tabindex="-1" role="dialog" aria-labelledby="importProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importProductModalLabel">Importer des produits</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="controller/import_produits.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="excelFile" class="form-label">Fichier Excel</label>
                        <input type="file" class="form-control" id="excelFile" name="excelFile" accept=".xlsx, .xls" required>
                    </div>
                    <div class="alert alert-info">
                        <strong>Note:</strong> Le fichier Excel doit contenir les colonnes suivantes : Code, Libellé, Description, Catégorie, Type, Unité de stockage, Unité de vente, Prix d'achat, Prix de vente.
                    </div>
                    <button type="submit" class="btn btn-primary">Importer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(idProduit) {
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
                window.location.href = 'controller/delete_produit.php?id=' + idProduit;
            }
        });
    }

    // Filtrage dynamique des produits
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
        $('#filterCategorie, #filterStatus').on('change', function() {
            let categorieFilter = $('#filterCategorie').val();
            let statusFilter = $('#filterStatus').val();
            
            dataTable.column(4).search(categorieFilter).draw();
            
            if (statusFilter) {
                dataTable.column(6).search(statusFilter === '1' ? 'Actif' : 'Inactif').draw();
            } else {
                dataTable.column(6).search('').draw();
            }
        });
    });
</script>

<?php include "./views/include/footer.php"; ?>
