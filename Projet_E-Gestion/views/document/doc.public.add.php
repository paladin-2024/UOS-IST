<?php
include "./views/include/header.php";

$documentModel = new Structure();
$userId = $_SESSION['id']; // Assuming the user ID is stored in the session

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Fetch private documents the user has access to
$documents = $documentModel->getPublicDocumentsByUserAccess($userId, $search);

// Fetch categories for dropdown
$categories = $documentModel->getCategoriesByUserAccess($userId);

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
                        <h5 class="card-title">Mes Documents</h5>
                        <div class="d-flex mb-3">
                            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addDocumentModal">
                                <i class="bi bi-plus-circle"></i>
                            </button>
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
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                    <button class="btn btn-danger btn-sm" onclick="confirmDeleteDocument(<?= $document['id_document'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
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



                        <!-- Add Document Modal -->
<div class="modal fade" id="addDocumentModal" tabindex="-1" aria-labelledby="addDocumentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDocumentModalLabel">Ajouter un Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addDocumentForm" method="POST" action="controller/addDocument_public.php" enctype="multipart/form-data">
                    <!-- Zone de dépôt de fichier -->
                    <div class="mb-3">
                        <label class="form-label">Fichier <span class="text-danger">*</span></label>
                        <div id="dropZone" class="border border-primary rounded d-flex flex-column align-items-center justify-content-center p-4 text-center" style="cursor: pointer;">
                            <i class="bi bi-cloud-upload fs-1 text-primary"></i>
                            <p class="mb-1">Glissez-déposez un fichier ici ou cliquez pour sélectionner</p>
                            <small class="text-muted">Formats acceptés : PDF, DOCX, XLSX, PNG, JPG</small>
                            <input type="file" class="form-control d-none" id="fichier" name="fichier" required>
                        </div>
                    </div>
                    <div class="progress mb-3" style="display: none;" id="uploadProgress">
                        <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="titre" class="form-label">Titre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="titre" name="titre" required>
                        </div>
                        <div class="col-md-6">
                            <label for="categorie" class="form-label">Catégorie <span class="text-danger">*</span></label>
                            <select class="form-select" id="categorie" name="id_categorie" required>
                                <option value="">Sélectionner une catégorie</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id_categorie'] ?>"><?= htmlspecialchars($category['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btnModSave ladda-button"><span class="bi bi-save"></span> Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

                        

                        <script>
function confirmDeleteDocument(documentId) {
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
            window.location.href = 'controller/deleteDocument_public.php?id=' + documentId;
        }
    });
}




document.getElementById('fichier').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        const fileName = file.name;
        const title = fileName.substring(0, fileName.lastIndexOf('.')) || fileName;
        document.getElementById('titre').value = title;
        document.getElementById('description').value = title;

        const reader = new FileReader();
        const progressBar = document.querySelector('#uploadProgress .progress-bar');
        document.getElementById('uploadProgress').style.display = 'block'; // Show the progress bar

        reader.onprogress = function(e) {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                progressBar.style.width = percentComplete + '%';
                progressBar.setAttribute('aria-valuenow', percentComplete);
            }
        };

        reader.onloadend = function() {
            progressBar.style.width = '100%';
            setTimeout(() => {
                document.getElementById('uploadProgress').style.display = 'none'; // Hide the progress bar after a short delay
            }, 500);
        };

        reader.readAsArrayBuffer(file); // Use readAsArrayBuffer for better progress tracking
    }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const dropZone = document.getElementById("dropZone");
    const fileInput = document.getElementById("fichier");

    dropZone.addEventListener("click", () => fileInput.click());

    
});
</script>


<?php include "./views/include/footer.php"; ?>