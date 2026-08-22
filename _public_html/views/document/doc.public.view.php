<?php
include "./views/include/header.php";

$documentModel = new Structure();
$userId = $_SESSION['id']; // Assuming the user ID is stored in the session

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Fetch private documents the user has access to
$documents = $documentModel->getPublicDocumentsByUserAccess2($userId, $search);

?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Explorateur de Documents Publiques</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Documents Publiques</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Documents disponibles</h5>
                        <div class="d-flex mb-3">
                           
                            <form method="GET" action="" class="w-100">
                                <div class="input-group">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher...">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                                </div>
                            </form>
                        </div>
                        <div class="row">
                            <?php if (empty($documents)) : ?>
                                <p class="text-center">Aucun document trouvé.</p>
                            <?php else : ?>
                                <?php foreach ($documents as $document) : ?>
                                    <div class="col-md-4">
                                        <div class="card shadow-sm mb-3">
                                            <div class="card-body text-center">
                                                <i class="bi bi-file-earmark-text display-4"></i>
                                                <h6 class="mt-2"> <?= htmlspecialchars($document['titre']) ?> </h6>
                                                <p class="text-muted small">Ajouté le <?= date('d/m/Y', strtotime($document['date_ajout'])) ?></p>
                                                <p class="text-muted small">Catégorie : <?= htmlspecialchars($document['nom']) ?></p>
                                                <p class="text-muted small">Auteur : <?= htmlspecialchars($document['nomUser']) ?></p>
                                                <div class="btn-group">
                                                    <a href="uploads/<?= htmlspecialchars($document['chemin_fichier']) ?>" class="btn btn-primary btn-sm" download>
                                                        <i class="bi bi-download"></i> Télécharger
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>




<?php include "./views/include/footer.php"; ?>