<?php
include "./views/include/header.php";
$structure = new Structure();

$projet = new Projet();
$userId = $_SESSION['id'];

// Fetch projects the user has access to
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$projects = $projet->getProjetByUserAccess($userId, '', 200);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES ACTIVITÉS DE PROJET</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Gestion des Activités</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12 mb-3">
                <form method="GET" action="" class="mb-3">
                    <div class="input-group">
                        <input type="hidden" name="view" value="projet/document.add">
                        <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" class="form-control" placeholder="Rechercher une activité...">
                        <button type="submit" class="btn btn-primary">Rechercher</button>
                    </div>
                </form>
            </div>

            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Liste des Activités</h5>
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Intitulé</th>
                                    <th scope="col">Date Début</th>
                                    <th scope="col">Date Fin</th>
                                    <th scope="col">Budget</th>
                                    <th scope="col">État</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="activityTableBody">
                                <?php
                                $i = 1;
                                foreach ($projects as $project) {
                                    $activities = $projet->getActivitiesByProjectWithAccess($project['idProjet'], $userId, $searchQuery);
                                    if (!empty($activities)) {
                                        echo "<tr><td colspan='7'><strong>Projet: {$project['nomProjet']}</strong></td></tr>";
                                        foreach ($activities as $activity) {
                                            $dd = date('d/m/Y', strtotime($activity['dateDebut']));
                                            $df = date('d/m/Y', strtotime($activity['dateFin']));
                                            $currentDate = date('Y-m-d');
                                            $status = (strtotime($activity['dateFin']) < strtotime($currentDate)) ? 'Terminé' : 'En cours';
                                            echo "
                                            <tr>
                                                <td>{$i}</td>
                                                <td>{$activity['intitule']}</td>
                                                <td>{$dd}</td>
                                                <td>{$df}</td>
                                                <td>{$activity['budget']}</td>
                                                <td>{$status}</td>
                                                <td>
                                                    <button class='btn btn-sm btn-info' data-bs-toggle='collapse' 
                                                        data-bs-target='#collapseDocuments{$activity['idActivite_projet']}'>
                                                        <i class='bi bi-eye'></i>
                                                    </button>

                                                    <button class='btn btn-sm btn-secondary' data-bs-toggle='modal' 
                                                        data-bs-target='#modalAddDocument{$activity['idActivite_projet']}'>
                                                        <i class='bi bi-file-earmark-plus'></i> Document
                                                    </button>
                                                </td>
                                            </tr>";

                                            // Display documents
                                            echo "
                                            <tr class='collapse-row'>
                                                <td colspan='7' class='p-0'>
                                                    <div class='collapse' id='collapseDocuments{$activity['idActivite_projet']}'>
                                                        <table class='table table-sm table-bordered m-0'>
                                                            <thead>
                                                                <tr>
                                                                    <th>Titre</th>
                                                                    <th>Date Document</th>
                                                                    <th>Auteur</th>
                                                                    <th>Date Enregistrement</th>
                                                                    <th>Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>";
                                            
                                            $documents = $projet->getDocumentsByActivity($activity['idActivite_projet']);
                                            foreach ($documents as $document) {
                                                $author = $structure->getUserById($document['idUser'])->fetch(); // Assuming a method to get user details
                                                echo "
                                                <tr>
                                                    <td>{$document['titre']}</td>
                                                    <td>{$document['dateDocument']}</td>
                                                    <td>{$author['nomUser']}</td>
                                                    <td>{$document['dateEnregistrement']}</td>
                                                    <td>
                                                        <a href='uploads/{$document['fichier']}' class='btn btn-sm btn-success' download>
                                                            <i class='bi bi-download'></i>
                                                        </a>
                                                        <a class='btn btn-sm btn-danger' onclick='confirmDeleteDocument({$document['idDoc_activite']})'>
                                                            <i class='bi bi-trash'></i>
                                                        </a>
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
                                    }
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

<!-- Modal for adding a document -->
<?php foreach ($projects as $project): ?>
    <?php 
    $activities = $projet->getActivitiesByProject($project['idProjet']);
    foreach ($activities as $activity): ?>
        <div class="modal fade" id="modalAddDocument<?php echo $activity['idActivite_projet']; ?>" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Ajouter un Document</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="controller/add_document_to_activity.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <input type="hidden" name="idActivite_projet" value="<?php echo $activity['idActivite_projet']; ?>">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="titre" class="form-label">Titre <span class="text-danger">*</span></label>
                                    <input type="text" id="titre" name="titre" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="dateDocument" class="form-label">Date du Document <span class="text-danger">*</span></label>
                                    <input type="date" name="dateDocument" class="form-control" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea id="description" name="description" class="form-control"></textarea>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="fichier" class="form-label">Fichier <span class="text-danger">*</span></label>
                                    <div id="dropZone" class="border border-primary rounded d-flex flex-column align-items-center justify-content-center p-4 text-center" style="cursor: pointer;">
                                        <i class="bi bi-cloud-upload fs-1 text-primary"></i>
                                        <p class="mb-1">Glissez-déposez un fichier ici ou cliquez pour sélectionner</p>
                                        <small class="text-muted">Formats acceptés : PDF, DOCX, XLSX, PNG, JPG</small>
                                        <input type="file" class="form-control d-none" id="fichier" name="fichier" required>
                                    </div>
                                    <div class="progress mb-3" style="display: none;" id="uploadProgress">
                                        <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <button type="submit" name="addDocumentBtn" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Ajouter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endforeach; ?>

<script>
function confirmDeleteDocument(idDoc_activite) {
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
            window.location.href = 'controller/deleteDoc_projet.php?id=' + idDoc_activite;
        }
    })
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