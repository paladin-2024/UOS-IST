<?php
include "./views/include/header.php";

$search = isset($_GET['search']) ? $_GET['search'] : '';
$userId = $_SESSION['id']; // Assuming the user ID is stored in the session

$documentModel = new Structure();
$categories = $documentModel->getDocumentCategories($search); // Récupère toutes les catégories
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Explorateur de Documents</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Documents par Catégorie</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Documents par Catégorie</h5>

                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="hidden" name="view" value="document/doc.consulter">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher par titre...">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <div class="explorer">
                            <div class="category-container">
                                <?php foreach ($categories as $category): ?>
                                    <div class="category-item">
                                        <a href="#" class="category-link" data-category-id="<?= $category['id_categorie'] ?>">
                                            <i class="bi bi-folder-fill text-warning"></i> 
                                            <?= htmlspecialchars($category['nom']) ?>
                                        </a>
                                        <ul class="document-list" id="category-<?= $category['id_categorie'] ?>" style="display: none;">
                                            <?php
                                            $documents = $documentModel->getDocumentsByCategory($category['id_categorie'],$userId);
                                            foreach ($documents as $document):
                                            ?>
                                                <li class="document-item">
                                                    <a href="uploads/<?= htmlspecialchars($document['chemin_fichier']) ?>" download>
                                                        <i class="bi bi-file-earmark-text"></i>
                                                        <?= htmlspecialchars($document['titre']) ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<style>
    .explorer {
        padding: 10px;
        background-color: #f8f9fa;
        border-radius: 5px;
    }
    .category-container {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }
    .category-item {
        flex: 1 1 calc(50% - 15px);
        max-width: calc(50% - 15px);
        background: #fff;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        text-align: center;
    }
    .category-link {
        display: flex;
        flex-direction: column;
        align-items: center;
        font-weight: bold;
        cursor: pointer;
        text-decoration: none;
        color: #007bff;
    }
    .category-link:hover {
        text-decoration: underline;
    }
    .document-list {
        list-style: none;
        padding-left: 0;
        margin-top: 5px;
    }
    .document-item a {
        display: flex;
        align-items: center;
        text-decoration: none;
        color: #000;
    }
    .document-item a:hover {
        text-decoration: underline;
    }
    .bi {
        font-size: 2rem;
        margin-bottom: 5px;
    }
</style>

<script>
    document.querySelectorAll('.category-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const categoryId = this.getAttribute('data-category-id');
            const documentList = document.getElementById('category-' + categoryId);
            documentList.style.display = documentList.style.display === 'none' ? 'block' : 'none';
        });
    });
</script>

<?php include "./views/include/footer.php"; ?>
