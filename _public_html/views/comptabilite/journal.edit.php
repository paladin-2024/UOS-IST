<?php
include "./views/include/header.php";

$structureModel = new Structure();
$search = isset($_GET['search']) ? $_GET['search'] : '';
$structures = $structureModel->getStructures($search);
?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Liste des Journaux</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Journaux</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Journaux</h5>

                        <!-- Search Form -->
                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="hidden" name="view" value="comptabilite/journal.edit">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher une structure...">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <table class="table table-striped table-bordered" id="structureTable">
                            <thead>
                                <tr>
                                    <th>Structure</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $userId = $_SESSION['id'];
                                $hasResults = false;

                                foreach ($structures as $structure) {
                                    $ver1 = $structureModel->getUserPermissionStructure($userId, $structure['idStructure']);
                                    if ($ver1->fetch()) {
                                        $hasResults = true;
                                        echo "
                                            <tr>
                                                <td>{$structure['designation']}</td>
                                                <td>
                                                    <button type='button' class='btn btn-secondary btn-sm' data-bs-toggle='collapse' data-bs-target='#journals{$structure['idStructure']}'>
                                                        <i class='bi bi-eye-fill'></i> Afficher les Journaux
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr class='collapse' id='journals{$structure['idStructure']}'>
                                                <td colspan='2'>
                                                    <table class='table table-sm'>
                                                        <thead>
                                                            <tr>
                                                                <th>Nom du Journal</th>
                                                                <th>Code du Journal</th>
                                                                <th>Description</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                        ";

                                        $journals = $structureModel->getJournaux();
                                        foreach ($journals as $journal) {
                                            if ($journal['Structure_idStructure'] == $structure['idStructure']) {
                                                echo "
                                                    <tr>
                                                        <td>{$journal['nom_journal']}</td>
                                                        <td>{$journal['code_journal']}</td>
                                                        <td>{$journal['description']}</td>
                                                        <td>
                                                            <button type='button' class='btn btn-warning btn-sm' data-bs-toggle='modal' data-bs-target='#editJournalModal'
                                                                data-journal-id='{$journal['idJournaux']}'
                                                                data-nom-journal='{$journal['nom_journal']}'
                                                                data-code-journal='{$journal['code_journal']}'
                                                                data-description='{$journal['description']}'
                                                                data-structure-id='{$journal['Structure_idStructure']}'
                                                                >
                                                                Modifier
                                                            </button>
                                                            <button type='button' class='btn btn-info btn-sm' data-bs-toggle='modal' data-bs-target='#userJournalModal'
                                                                data-journal-id='{$journal['idJournaux']}'>
                                                                Utilisateurs
                                                            </button>
                                                            
                                                            <form action='controller/delete_journal.php' method='POST' class='delete-journal-form' style='display:inline;'>
                                                                <input type='hidden' name='idJournaux' value='{$journal['idJournaux']}'>
                                                                <button type='button' class='btn btn-danger btn-sm delete-journal-btn'>Supprimer</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                ";
                                            }
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
                                    echo "<tr><td colspan='2' class='text-center'>Aucun résultat trouvé</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>

                        <!-- Modal for editing a journal -->
                        <div class="modal fade" id="editJournalModal" tabindex="-1" aria-labelledby="editJournalModalLabel" aria-hidden="true" data-bs-backdrop="static">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="editJournalModalLabel">Modifier un journal</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="editJournalForm" action="controller/update_journal.php" method="POST">
                                            <input type="hidden" name="idJournaux" id="editIdJournaux">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="editNomJournal" class="form-label">Nom du Journal <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="editNomJournal" name="nomJournal" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="editCodeJournal" class="form-label">Code du Journal <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="editCodeJournal" name="codeJournal" required>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="editDescription" class="form-label">Description</label>
                                                <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label for="editStructureId" class="form-label">Structure <span class="text-danger">*</span></label>
                                                <select class="form-select" id="editStructureId" name="structureId" required>
                                                    <option value="">Sélectionner une structure</option>
                                                    <?php foreach ($structures as $structure): ?>
                                                        <?php
                                                        // Check permission for each structure
                                                        $ver1 = $structureModel->getUserPermissionStructure($userId, $structure['idStructure']);
                                                        if ($ver1->fetch()):
                                                        ?>
                                                            <option value="<?= $structure['idStructure'] ?>"><?= $structure['designation'] ?></option>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-save"></i> Enregistrer
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="modal fade" id="userJournalModal" tabindex="-1" aria-labelledby="userJournalModalLabel" aria-hidden="true" data-bs-backdrop="static">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="userJournalModalLabel">Utilisateurs autorisés</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <ul id="userList" class="list-group mb-3">
                                            <!-- User list will be populated here -->
                                        </ul>
                                        <form id="addUserForm" action="controller/add_user_journal.php" method="POST">
                                            <input type="hidden" name="journalId" id="journalIdForUser">
                                            <div class="mb-3">
                                                <label for="userId" class="form-label">Ajouter un utilisateur</label>
                                                <select class="form-control" id="userId" name="userId" required>
                                                    <option value="">Sélectionner un utilisateur</option>
                                                    <?php foreach ($structureModel->getUsers() as $user): ?>
                                                        <option value="<?= $user['idUser'] ?>"><?= $user['nomUser'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Ajouter</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                document.querySelectorAll('.delete-journal-btn').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const form = this.closest('.delete-journal-form');
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

                                document.querySelectorAll('[data-bs-target="#editJournalModal"]').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const journalId = this.getAttribute('data-journal-id');
                                        const nomJournal = this.getAttribute('data-nom-journal');
                                        const codeJournal = this.getAttribute('data-code-journal');
                                        const description = this.getAttribute('data-description');
                                        const structureId = this.getAttribute('data-structure-id');

                                        document.getElementById('editIdJournaux').value = journalId;
                                        document.getElementById('editNomJournal').value = nomJournal;
                                        document.getElementById('editCodeJournal').value = codeJournal;
                                        document.getElementById('editDescription').value = description;
                                        document.getElementById('editStructureId').value = structureId;
                                    });
                                });


                                document.querySelectorAll('[data-bs-target="#userJournalModal"]').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const journalId = this.getAttribute('data-journal-id');
                                        document.getElementById('journalIdForUser').value = journalId;

                                        // Fetch and display users for the selected journal
                                        fetch(`controller/get_users_by_journal.php?journalId=${journalId}`)
                                            .then(response => response.json())
                                            .then(users => {
                                                const userList = document.getElementById('userList');
                                                userList.innerHTML = '';
                                                users.forEach(user => {
                                                    const li = document.createElement('li');
                                                    li.className = 'list-group-item d-flex justify-content-between align-items-center';
                                                    li.textContent = user.nomUser;
                                                    const removeButton = document.createElement('button');
                                                    removeButton.className = 'btn btn-danger btn-sm';
                                                    removeButton.textContent = 'Supprimer';
                                                    removeButton.onclick = function () {
                                                    const formData = new FormData();
                                                    formData.append('userJournalId', user.id_user_journal); // Ensure this is the correct ID

                                                    fetch(`controller/remove_user_journal.php`, {
                                                        method: 'POST',
                                                        body: formData
                                                    })
                                                    .then(response => response.json())
                                                    .then(data => {
                                                        if (data.success) {
                                                            li.remove();
                                                        } else {
                                                            Swal.fire({
                                                                icon: 'error',
                                                                title: 'Erreur',
                                                                text: data.error || 'Erreur lors de la suppression.'
                                                            });
                                                        }
                                                    })
                                                    .catch(() => {
                                                        Swal.fire({
                                                            icon: 'error',
                                                            title: 'Erreur',
                                                            text: 'Erreur lors de la communication avec le serveur.'
                                                        });
                                                    });
                                                };
                                                    li.appendChild(removeButton);
                                                    userList.appendChild(li);
                                                });
                                            });
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