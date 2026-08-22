<?php
include "./views/include/header.php";
$universite = new Universite();

$search = isset($_GET['search']) ? $_GET['search'] : '';
?>
<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>SESSIONS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Sessions</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table sessions -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des sessions
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createSessionModal" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter
                                        </a>
                                    </span>
                                </h5>

                                <form method="GET" action="" class="mb-3">
                                    <div class="input-group">
                                        <input type="hidden" name="view" value="configuration/session">
                                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher par désignation...">
                                        <button type="submit" class="btn btn-primary">Rechercher</button>
                                    </div>
                                </form>

                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Date de Création</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $listeSessions = $universite->getSessions($search);
                                        $i = 1;

                                        foreach ($listeSessions as $session) {
                                            $dateCreation = date('d/m/Y H:i:s', strtotime($session['dateCreation']));
                                            echo "
            <tr>
                <td>{$i}</td>
                <td>{$session['description']}</td>
                <td>{$dateCreation}</td>
                <td>
                    <button class='btn btn-sm btn-warning' onclick='editSession(
                        {$session['idsession']}, 
                        \"{$session['designSession']}\",
                        \"" . htmlspecialchars($session['description'], ENT_QUOTES) . "\"
                    )'>
                        <i class='bi bi-pencil-square'></i> Modifier
                    </button>
                    <button class='btn btn-sm btn-danger' onclick='confirmDelete({$session['idsession']})'>
                        <i class='bi bi-trash'></i> Supprimer
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

<!-- Modal pour ajouter une session -->
<!-- Modal pour ajouter une session -->
<div class="modal fade" id="createSessionModal" tabindex="-1" role="dialog" aria-labelledby="createSessionModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_session.php" class="needs-validation">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="designSession" class="form-label">Catégorie <span class="text-danger">*</span></label>
                            <select name="designSession" class="form-select" required>
                                <option value="">Sélectionnez une catégorie</option>
                                <option value="Première session">Première session</option>
                                <option value="Deuxième session">Deuxième session</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une catégorie.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="3" required></textarea>
                            <div class="invalid-feedback">Veuillez saisir une description.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addSessionBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour modifier une session -->
<div class="modal fade" id="editSessionModal" tabindex="-1" role="dialog" aria-labelledby="editSessionModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_session.php" class="needs-validation" novalidate>
                    <input type="hidden" name="idsession" id="editSessionId">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="designSession" class="form-label">Catégorie <span class="text-danger">*</span></label>
                            <select name="designSession" id="editSessionDesignation" class="form-select" required>
                                <option value="">Sélectionnez une catégorie</option>
                                <option value="Première session">Première session</option>
                                <option value="Deuxième session">Deuxième session</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" id="editSessionDescription" class="form-control" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editSessionBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<script>
    function editSession(id, designation, description) {
        document.getElementById('editSessionId').value = id;
        document.getElementById('editSessionDesignation').value = designation;
        document.getElementById('editSessionDescription').value = description;

        new bootstrap.Modal(document.getElementById('editSessionModal')).show();
    }

    function confirmDelete(idSession) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action est irréversible !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/delete_session.php?idsession=' + idSession;
            }
        });
    }
</script>


<?php include "./views/include/footer.php"; ?>