<?php
include "./views/include/header.php";

$structureModel = new Structure();

$agent = new Agent();
$userId = $_SESSION['id']; // Assuming the user ID is stored in the session

// Fetch emails the user has access to
$emails = $structureModel->getEmailsByUserAccess2($userId);

// Fetch services and users for dropdowns
$services = $structureModel->getServicesByUserAccess($userId);
$users = $agent->getAgentsByUserAccess($userId);
?>
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Gestion des Courriels Entrants</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Courriels Entrants</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Liste des Courriels Entrants</h5>

                        <!-- Add Email Button -->
                        <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addEmailModal">
                        <span class="bi bi-plus-circle"></span> Ajouter un Courriel
                        </button>

                        <!-- Search Form -->
                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="text" name="searchProvenance" class="form-control" placeholder="Provenance">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <table class="table table-striped table-bordered" id="emailTable">
                            <thead>
                                <tr>
                                    <th>Date du courrier</th>
                                    <th>Provenance</th>
                                    <th>Dépositaire</th>
                                    <th>Destinataire</th>
                                    <th>Objet</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $hasResults = false;
                                foreach ($emails as $email) {
                                    $dateArrive = date('d/m/Y', strtotime($email['dateArrive']));
                                    $dateEnregistrement = date('d/m/Y H:i', strtotime($email['dateEnregistrement']));
                                    $hasResults = true;
                                    echo "
                                        <tr>
                                            <td>{$dateArrive}</td>
                                            <td>{$email['provenance']}</td>
                                            <td>{$email['depositaire']}</td>
                                            <td>{$email['noms']}</td>
                                            <td>{$email['objet']}</td>
                                            <td>
                                                <button type='button' class='btn btn-danger btn-sm' onclick='confirmDeleteEmail({$email['idcouriels_recu']})'>
                                                    Supprimer
                                                </button>
                                            </td>
                                        </tr>
                                    ";
                                }

                                if (!$hasResults) {
                                    echo "<tr><td colspan='6' class='text-center'>Aucun résultat trouvé</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>

                        <!-- Add Email Modal -->
                        <div class="modal fade" id="addEmailModal" tabindex="-1" aria-labelledby="addEmailModalLabel" aria-hidden="true" data-bs-backdrop="static">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="addEmailModalLabel">Ajouter un Courriel</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="addEmailForm" method="POST" action="controller/addEmail.php">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="provenance" class="form-label">Provenance <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="provenance" name="provenance" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="depositaire" class="form-label">Dépositaire <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="depositaire" name="depositaire" required>
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="dateArrive" class="form-label">Date du document <span class="text-danger">*</span></label>
                                                    <input type="date" class="form-control" id="dateArrive" name="dateArrive" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="service" class="form-label">Service Concerné <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="service" name="Service_idService" required>
                                                        <option value="">Sélectionner un service</option>
                                                        <?php foreach ($services as $service): ?>
                                                            <option value="<?= $service['idService'] ?>"><?= htmlspecialchars($service['designation']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="userConcerne" class="form-label">Agent Concerné <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="userConcerne" name="userConcerne" required>
                                                        <option value="">Sélectionner un agent</option>
                                                        <?php foreach ($users as $user): ?>
                                                            <option value="<?= $user['idAgent'] ?>"><?= htmlspecialchars($user['noms']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="objet" class="form-label">Objet <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="objet" name="objet" required>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="resume" class="form-label">Résumé du Contenu <span class="text-danger">*</span></label>
                                                <textarea class="form-control" id="resume" name="resume" rows="3" required></textarea>
                                            </div>
                                            <div class="d-flex justify-content-end">
                                                <button type="submit" class="btn btn-primary"><span class="bi bi-save"></span> Enregistrer</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                            function confirmDeleteEmail(emailId) {
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
                                        window.location.href = 'controller/deleteEmail.php?id=' + emailId;
                                    }
                                })
                            }
                        </script>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>