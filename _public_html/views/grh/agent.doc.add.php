<?php
include "./views/include/header.php";

$agentModel = new Agent();
$structure = new Structure();
$serviceModel = new Service();
$search = isset($_GET['search']) ? $_GET['search'] : '';
$agents = $agentModel->getAgents($search);

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Liste des Agents</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Agents</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des documents</h5>

                        <!-- Search Form -->
                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="hidden" name="view" value="grh/agent.doc.add">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher par nom...">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Lieu de Naissance</th>
                                    <th>Date de Naissance</th>
                                    <th>Sexe</th>
                                    <th>Structure</th>
                                    <th>Total</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php 
                                $userId = $_SESSION['id'];
                                $hasResults = false;

                                foreach ($agents as $agent) {
                                    $ver1 = $structure->getUserPermissionStructure($userId, $agent['idStructure']);
                                    if ($ver1->fetch()) {
                                        $hasResults = true;
                                        echo "
                                            <tr>
                                                <td>{$agent['idAgent']}</td>
                                                <td>{$agent['noms']}</td>
                                                <td>{$agent['lieuNaissance']}</td>
                                                <td>{$agent['dateNaissance']}</td>
                                                <td>{$agent['sexe']}</td>
                                                <td>{$agent['designationStructure']}</td>
                                                <td>{$agent['totalDocuments']}</td>
                                                <td>
                                                    <button type='button' class='btn btn-info btn-sm add-document-btn' data-agent-id='{$agent['idAgent']}' data-bs-toggle='modal' data-bs-target='#addDocumentModal'>
                                                        <i class='bi bi-file-earmark-plus'></i>
                                                    </button>
                                                    <button type='button' class='btn btn-secondary btn-sm' data-bs-toggle='collapse' data-bs-target='#documents{$agent['idAgent']}'>
                                                        <i class='bi bi-eye-fill'></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr class='collapse' id='documents{$agent['idAgent']}'>
                                                <td colspan='8'>
                                                    <table class='table table-sm'>
                                                        <thead>
                                                            <tr>
                                                                <th>Titre</th>
                                                                <th>Description</th>
                                                                <th>Fichier</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>";
                                                        
                                                        $documents = $agentModel->getDocumentsByAgent($agent['idAgent']);
                                                        foreach ($documents as $document) {
                                                            echo "
                                                                <tr>
                                                                    <td>" . htmlspecialchars($document['titre']) . "</td>
                                                                    <td>" . htmlspecialchars($document['description']) . "</td>
                                                                    <td><a href='uploads/" . htmlspecialchars($document['fichier']) . "' target='_blank'>Voir</a></td>
                                                                    <td>
                                                                        <button type='button' class='btn btn-warning btn-sm edit-document-btn' 
                                                                                data-document-id='" . htmlspecialchars($document['idDocument_agent']) . "'
                                                                                data-titre='" . htmlspecialchars($document['titre'], ENT_QUOTES) . "'
                                                                                data-description='" . htmlspecialchars($document['description'], ENT_QUOTES) . "'
                                                                                data-bs-toggle='modal' data-bs-target='#editDocumentModal'>
                                                                            Modifier
                                                                        </button>
                                                                        <form action='controller/delete_document.php' method='POST' class='delete-document-form' style='display:inline;'>
                                                                            <input type='hidden' name='idDocument' value='" . htmlspecialchars($document['idDocument_agent']) . "'>
                                                                            <button type='button' class='btn btn-danger btn-sm delete-document-btn'>Supprimer</button>
                                                                        </form>
                                                                    </td>
                                                                </tr>
                                                            ";
                                                        }
                                                        echo "
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>
                                        ";
                                    }
                                }
                                if (!$hasResults) {
                                    echo "<tr><td colspan='8' class='text-center'>Aucun résultat trouvé</td></tr>";
                                }
                            ?>
                            </tbody>
                            </table>

                    <!-- Modal for adding a document -->
                    <div class="modal fade" id="addDocumentModal" tabindex="-1" aria-labelledby="addDocumentModalLabel" aria-hidden="true" data-bs-backdrop="static">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="addDocumentModalLabel">Ajouter un document</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="addDocumentForm" action="controller/create_document.php" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="agentId" id="documentAgentId">
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

                                        <!-- Barre de progression -->
                                        <div class="progress mb-3" style="display: none;" id="uploadProgress">
                                            <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="titre" class="form-label">Titre</label>
                                            <input type="text" class="form-control" id="titre" name="titre" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control" id="description" name="description" required></textarea>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Enregistrer</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal for editing a document -->
                    <div class="modal fade" id="editDocumentModal" tabindex="-1" aria-labelledby="editDocumentModalLabel" aria-hidden="true" data-bs-backdrop="static">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editDocumentModalLabel">Modifier un document</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="editDocumentForm" action="controller/update_document.php" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="idDocument" id="editIdDocument">
                                        <input type="hidden" name="source" value="add">
                                        <div class="mb-3">
                                            <label for="editTitre" class="form-label">Titre</label>
                                            <input type="text" class="form-control" id="editTitre" name="titre" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="editDescription" class="form-label">Description</label>
                                            <textarea class="form-control" id="editDescription" name="description" required></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="editFichier" class="form-label">Fichier</label>
                                            <input type="file" class="form-control" id="editFichier" name="fichier">
                                        </div>
                                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Enregistrer</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        document.querySelectorAll('.add-document-btn').forEach(button => {
                            button.addEventListener('click', function () {
                                const agentId = this.getAttribute('data-agent-id');
                                document.getElementById('documentAgentId').value = agentId;
                            });
                        });

                        document.querySelectorAll('.edit-document-btn').forEach(button => {
                            button.addEventListener('click', function () {
                                const documentId = this.getAttribute('data-document-id');
                                const titre = this.getAttribute('data-titre');
                                const description = this.getAttribute('data-description');

                                document.getElementById('editIdDocument').value = documentId;
                                document.getElementById('editTitre').value = titre;
                                document.getElementById('editDescription').value = description;
                                // Note: File input cannot be pre-filled for security reasons
                            });
                        });


                        document.querySelectorAll('.delete-document-btn').forEach(button => {
                            button.addEventListener('click', function () {
                                const form = this.closest('.delete-document-form');
                                Swal.fire({
                                    title: 'Êtes-vous sûr?',
                                    text: "Cette action est irréversible!",
                                    icon: 'warning',
                                    showCancelButton: true,
                                    confirmButtonColor: '#3085d6',
                                    cancelButtonColor: '#d33',
                                    confirmButtonText: 'Oui, supprimer!',
                                    cancelButtonText: 'Annuler'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        form.submit();
                                    }
                                });
                            });
                        });

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

                        const dropZone = document.getElementById("dropZone");
                        const fileInput = document.getElementById("fichier");
                        dropZone.addEventListener("click", () => fileInput.click());

                    });
                    </script>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>