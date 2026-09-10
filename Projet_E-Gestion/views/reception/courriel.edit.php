<?php
include "./views/include/header.php";

$structureModel = new Structure();
$agent = new Agent();
$userId = $_SESSION['id'];

$emailId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$email = $emailId > 0 ? $structureModel->getEmailById($emailId) : null;

if (!$email) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Courriel introuvable.'
        }).then(() => {
            window.location.href = 'index.php?view=reception/courriel.add';
        });
    </script>";
    include "./views/include/footer.php";
    exit();
}

// Fetch services and users for dropdowns
$services = $structureModel->getServicesByUserAccess($userId);
$users = $agent->getAgentsByUserAccess($userId);
?>
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Modifier un Courriel</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="index.php?view=reception/courriel.add">Courriels Entrants</a></li>
                <li class="breadcrumb-item active">Modifier</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Modifier le Courriel</h5>

                        <form id="editEmailForm" method="POST" action="controller/update_courriel.php">
                            <input type="hidden" name="idcouriels_recu" value="<?= (int) $email['idcouriels_recu'] ?>">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="provenance" class="form-label">Provenance <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="provenance" name="provenance" value="<?= htmlspecialchars($email['provenance'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="depositaire" class="form-label">Dépositaire <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="depositaire" name="depositaire" value="<?= htmlspecialchars($email['depositaire'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="dateArrive" class="form-label">Date du document <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="dateArrive" name="dateArrive" value="<?= htmlspecialchars($email['dateArrive'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="service" class="form-label">Service Concerné <span class="text-danger">*</span></label>
                                    <select class="form-select" id="service" name="Service_idService" required>
                                        <option value="">Sélectionner un service</option>
                                        <?php foreach ($services as $service): ?>
                                            <option value="<?= $service['idService'] ?>" <?= ((int) $email['Service_idService'] === (int) $service['idService']) ? 'selected' : '' ?>><?= htmlspecialchars($service['designation']) ?></option>
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
                                            <option value="<?= $user['idAgent'] ?>" <?= ((int) $email['userConcerne'] === (int) $user['idAgent']) ? 'selected' : '' ?>><?= htmlspecialchars($user['noms']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="objet" class="form-label">Objet <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="objet" name="objet" value="<?= htmlspecialchars($email['objet'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="resume" class="form-label">Résumé du Contenu <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="resume" name="resume" rows="3" required><?= htmlspecialchars($email['resumeCouriel'] ?? '') ?></textarea>
                            </div>
                            <div class="d-flex justify-content-end">
                                <a href="index.php?view=reception/courriel.add" class="btn btn-secondary me-2">Annuler</a>
                                <button type="submit" class="btn btn-primary"><span class="bi bi-save"></span> Enregistrer</button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>
