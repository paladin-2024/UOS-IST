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
                <li class="breadcrumb-item active">Documents</li>
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
                                <input type="hidden" name="view" value="grh/agent.doc.edit">
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
                                <th>Total Documents</th>
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
                                                    <tbody>
                                    ";

                                    $documents = $agentModel->getDocumentsByAgent($agent['idAgent']);
                                    foreach ($documents as $document) {
                                        echo "
                                            <tr>
                                                <td>{$document['titre']}</td>
                                                <td>{$document['description']}</td>
                                                <td><a href='uploads/{$document['fichier']}' target='_blank'>Voir</a></td>
                                                <td>
                                                    <button type='button' class='btn btn-warning btn-sm' 
                                                            data-bs-toggle='modal' 
                                                            data-bs-target='#editDocumentModal' 
                                                            data-document-id='{$document['idDocument_agent']}'
                                                            data-titre='" . htmlspecialchars($document['titre'], ENT_QUOTES) . "'
                                                            data-description='" . htmlspecialchars($document['description'], ENT_QUOTES) . "'>
                                                        Modifier
                                                    </button>
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
                                        <input type="hidden" name="source" value="edit">
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
                        document.querySelectorAll('[data-bs-target="#editDocumentModal"]').forEach(button => {
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
                    });
                    </script>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>