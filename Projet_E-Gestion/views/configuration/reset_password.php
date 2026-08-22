<?php
include "./views/include/header.php";
$universite = new Universite();

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Filters: default to active academic year
$activeYear = $universite->getActiveAcademicYear();
$academicYears = $universite->getAcademicYears();
$defaultAnneeId = $activeYear && isset($activeYear['idannee_acad']) ? (int)$activeYear['idannee_acad'] : (isset($academicYears[0]['idannee_acad']) ? (int)$academicYears[0]['idannee_acad'] : null);

$anneeId = isset($_GET['annee']) ? ($_GET['annee'] !== '' ? (int) $_GET['annee'] : null) : $defaultAnneeId;
$orientationFilterId = isset($_GET['orientation']) && $_GET['orientation'] !== '' ? $_GET['orientation'] : '';
$promotionFilterId = isset($_GET['promotion']) && $_GET['promotion'] !== '' ? $_GET['promotion'] : '';

// Preload filter options
$filterOrientations = $universite->getOrientations('', $anneeId);

$filterPromotions = [];
if (!empty($anneeId) && !empty($orientationFilterId)) {
    $allYearPromotions = $universite->getPromotionsByAnneeAcad($anneeId);
    foreach ($allYearPromotions as $p) {
        if (isset($p['orientation_idorientation']) && (int)$p['orientation_idorientation'] === (int)$orientationFilterId) {
            $filterPromotions[] = $p;
        }
    }
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Réinitialisation des mots de passe</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Configuration</a></li>
                <li class="breadcrumb-item active">Réinitialisation mots de passe</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table students -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des mots de passe étudiants
                                </h5>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> Affichage des 100 derniers étudiants enregistrés. Utilisez la recherche pour trouver un étudiant spécifique.
                                </div>

                                <form id="filtersForm" method="GET" action="" class="mb-3">
                                    <input type="hidden" name="view" value="configuration/reset_password">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-3">
                                            <label class="form-label">Année académique</label>
                                            <select id="filterAnnee" name="annee" class="form-control">
                                            <option value="" <?= ($anneeId === null) ? 'selected' : '' ?>>Toutes</option>
                                            <?php foreach ($academicYears as $year): ?>
                                            <option value="<?= (int)$year['idannee_acad'] ?>" <?= ($anneeId == (int)$year['idannee_acad']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($year['designation']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Orientation</label>
                                            <select id="filterOrientation" name="orientation" class="form-control">
                                                <option value="">Toutes</option>
                                                <?php foreach ($filterOrientations as $o): ?>
                                                    <option value="<?= (int)$o['idorientation'] ?>" <?= ((string)$orientationFilterId !== '' && (int)$orientationFilterId === (int)$o['idorientation']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($o['designationOrientation']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Promotion</label>
                                            <select id="filterPromotion" name="promotion" class="form-control">
                                                <option value="">Toutes</option>
                                                <?php foreach ($filterPromotions as $p): ?>
                                                    <option value="<?= (int)$p['idpromotion'] ?>" <?= ((string)$promotionFilterId !== '' && (int)$promotionFilterId === (int)$p['idpromotion']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($p['designationPromotion']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Recherche</label>
                                            <div class="input-group">
                                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Nom ou matricule">
                                                <button type="submit" class="btn btn-primary">Filtrer</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Matricule</th>
                                            <th scope="col">Noms</th>
                                            <th scope="col">Promotion</th>
                                            <th scope="col">Année</th>
                                            <th scope="col">Téléphone</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $listeEtudiants = $universite->getStudents($search, 100, 0, $anneeId, $orientationFilterId, $promotionFilterId);
                                        $i = 1;

                                        foreach ($listeEtudiants as $etudiant){
                                            echo "
                                            <tr>
                                                <td>{$i}</td>
                                                <td>{$etudiant['matricule']}</td>
                                                <td>{$etudiant['noms']}</td>
                                                <td>{$etudiant['designationPromotion']}</td>
                                                <td>{$etudiant['annee']}</td>
                                                <td>{$etudiant['telephone']}</td>

                                                <td>
                                                    <button class='btn btn-sm btn-warning' onclick='confirmResetPassword({$etudiant['idetudiant']}, \"{$etudiant['noms']}\", \"{$etudiant['matricule']}\")'>
                                                        <i class='bi bi-key'></i> Réinitialiser
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

<script>
function confirmResetPassword(etudiantId, noms, matricule) {
    Swal.fire({
        title: 'Réinitialiser le mot de passe ?',
        text: `Êtes-vous sûr de vouloir réinitialiser le mot de passe de ${noms} (${matricule}) ? Le nouveau mot de passe sera "12345678".`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, réinitialiser',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            resetPassword(etudiantId);
        }
    });
}

function resetPassword(etudiantId) {
    $.ajax({
        url: 'controller/reset_student_password.php',
        type: 'POST',
        data: {
            resetPassword: true,
            etudiantId: etudiantId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire(
                    'Succès!',
                    response.message,
                    'success'
                );
            } else {
                Swal.fire(
                    'Erreur!',
                    response.message,
                    'error'
                );
            }
        },
        error: function() {
            Swal.fire(
                'Erreur!',
                'Une erreur s\'est produite lors de la réinitialisation.',
                'error'
            );
        }
    });
}

// Dynamic filters
$(document).ready(function() {
    var DEFAULT_ACTIVE_YEAR_ID = <?= isset($defaultAnneeId) && $defaultAnneeId ? (int)$defaultAnneeId : 'null' ?>;

    $('#filterAnnee').on('change', function() {
        if ($('#filtersForm').length) { $('#filtersForm').trigger('submit'); }
    });

    $('#filterOrientation').on('change', function() {
        if ($('#filtersForm').length) { $('#filtersForm').trigger('submit'); }
    });

    $('#filterPromotion').on('change', function() {
        if ($('#filtersForm').length) { $('#filtersForm').trigger('submit'); }
    });
});
</script>

<?php include "./views/include/footer.php"; ?>
