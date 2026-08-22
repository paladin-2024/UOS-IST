<?php
include "./views/include/header.php";
$universite = new Universite();
$ecue = new Ecue();
$agent = new Agent();
$enseignant = new Enseignant();

// Vérifier que l'utilisateur est connecté et a les droits d'administration
$userId = $_SESSION['id'] ?? 0;

// Vérifier si l'utilisateur a les droits d'administration
if (!$userId) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous devez être connecté en tant qu\'administrateur pour accéder à cette page.'
        }).then(() => {
            window.location.href = '?view=dashboard';
        });
    </script>";
    exit;
}

// Récupérer l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();
$defaultAnneeId = $currentYear['idannee_acad'];

// Récupérer toutes les années académiques pour le filtre
$anneesAcademiques = $universite->getAcademicYears();

// Récupérer les sessions (première, deuxième)
$sessions = $universite->getSessions();

// Récupérer les filtres depuis l'URL
$anneeAcadId = isset($_GET['annee_id']) ? intval($_GET['annee_id']) : $defaultAnneeId;
$sessionId = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

// Récupérer les ECUE verrouillés avec les filtres
$filtres = ['annee_id' => $anneeAcadId];
if ($sessionId > 0) {
    $filtres['session_id'] = $sessionId;
}
$ecuesVerrouilles = $ecue->getAllVerrouillages($filtres);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Déverrouillages d'Encodage</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=dashboard">Accueil</a></li>
                <li class="breadcrumb-item">Enseignement</li>
                <li class="breadcrumb-item active">Déverrouillage des Notes</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <!-- Filtres -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filtres</h5>
                        <form id="filterForm" method="GET" action="">
                            <input type="hidden" name="view" value="enseignement/deverrouillage_notes">
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="annee_filter" class="form-label">Année académique</label>
                                    <select class="form-select" id="annee_filter" name="annee_id">
                                        <?php foreach ($anneesAcademiques as $annee): ?>
                                            <option value="<?= $annee['idannee_acad'] ?>" 
                                                <?= ($annee['idannee_acad'] == $anneeAcadId) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($annee['designation']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="session_filter" class="form-label">Session</label>
                                    <select class="form-select" id="session_filter" name="session_id">
                                        <option value="">Toutes les sessions</option>
                                        <?php foreach ($sessions as $session): ?>
                                            <option value="<?= $session['idsession'] ?>">
                                                <?= htmlspecialchars($session['description']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-filter"></i> Filtrer
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Liste des ECUE verrouillés -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">ECUE avec Notes Verrouillées</h5>
                        
                        <?php if (empty($ecuesVerrouilles)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-1"></i> Aucun ECUE avec notes verrouillées trouvé pour les critères sélectionnés.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>ECUE</th>
                                            <th>UE</th>
                                            <th>Promotion</th>
                                            <th>Session</th>
                                            <th>Date de verrouillage</th>
                                            <th>Verrouillé par</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <!-- Modifiez la partie du tableau pour utiliser l'ID de verrouillage -->
<tbody>
    <?php foreach ($ecuesVerrouilles as $verrouillage): ?>
        <tr>
            <td><?= htmlspecialchars($verrouillage['designationECUE']) ?></td>
            <td><?= htmlspecialchars($verrouillage['designationUE']) ?></td>
            <td><?= htmlspecialchars($verrouillage['designationPromotion']) ?></td>
            <td><?= htmlspecialchars($verrouillage['description']) ?></td>
            <td><?= date('d/m/Y H:i', strtotime($verrouillage['date_verrouillage'])) ?></td>
            <td><?= htmlspecialchars($verrouillage['nom_utilisateur']) ?></td>
            <td>
                <button class="btn btn-warning btn-sm" 
                        onclick="confirmerDeverrouillage(
                            <?= $verrouillage['id'] ?>, 
                            '<?= addslashes($verrouillage['designationECUE']) ?>'
                        )">
                    <i class="bi bi-unlock"></i> Déverrouiller
                </button>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>

                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>



<script>
// Fonction simplifiée pour confirmer le déverrouillage
function confirmerDeverrouillage(id, ecueNom) {
    Swal.fire({
        title: 'Confirmer le déverrouillage',
        html: `Êtes-vous sûr de vouloir déverrouiller les notes de l'ECUE <strong>${ecueNom}</strong>?<br><br>
               <div class="alert alert-warning">
                   <i class="bi bi-exclamation-triangle me-2"></i>
                   <strong>Attention:</strong> Cette action permettra à l'enseignant de modifier les notes et de les recompiler.
               </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, déverrouiller',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            // Redirection vers le contrôleur avec l'ID du verrouillage
            window.location.href = `controller/deverrouillage_controller.php?id=${id}`;
        }
    });
}
</script>



<?php include "./views/include/footer.php"; ?>
