<?php
include "./views/include/header.php";
$universite = new Universite();
$currentUserId = $_SESSION['id'];

$db = Connexion::getInstance()->getPDO();

// Récupérer l'année académique en cours
$anneeEnCours = $universite->getCurrentAcademicYear();
$anneeId = $anneeEnCours ? $anneeEnCours['idannee_acad'] : null;

$hasFullAccess = $_SESSION['idRole'] == 1;

// Récupérer les sections dont l'utilisateur est responsable
$userSections = [];
if ($anneeId) {
    $stmtSec = $db->prepare("SELECT section_idsection FROM responsable_section WHERE \"idUser\" = :userId AND annee_acad_idannee_acad = :anneeId");
    $stmtSec->bindParam(':userId', $currentUserId);
    $stmtSec->bindParam(':anneeId', $anneeId);
    $stmtSec->execute();
    $userSections = $stmtSec->fetchAll(PDO::FETCH_COLUMN);
}

// Récupérer les séances de cours selon les sections accessibles
$seances = [];

if ($anneeId) {
    $baseQuery = "SELECT s.*, e.\"designationECUE\", p.\"designationPromotion\", sem.\"numeroSemestre\", sec.\"designationSection\",
              (SELECT COUNT(*) FROM presence_cours WHERE idseance = s.idseance) as nb_presents
              FROM seance_cours s
              JOIN ecue e ON s.\"idECUE\" = e.\"idECUE\"
              JOIN ue ON e.\"UE_idUE\" = ue.\"idUE\"
              JOIN semestre sem ON ue.semestre_idsemestre = sem.idsemestre
              JOIN promotion p ON sem.promotion_idpromotion = p.idpromotion
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN section sec ON o.section_idsection = sec.idsection
              WHERE s.annee_acad_id = ?";

    if ($hasFullAccess) {
        $stmt = $db->prepare($baseQuery . " ORDER BY s.date_seance DESC, s.heure_debut DESC");
        $stmt->execute([$anneeId]);
        $seances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (!empty($userSections)) {
        $placeholders = implode(',', array_fill(0, count($userSections), '?'));
        $stmt = $db->prepare($baseQuery . " AND sec.idsection IN ($placeholders) ORDER BY s.date_seance DESC, s.heure_debut DESC");
        $stmt->execute(array_merge([$anneeId], $userSections));
        $seances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des séances de cours</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Enseignement</li>
                <li class="breadcrumb-item active">Séances de cours</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card seances-compact">
                    <div class="card-body">
                        <h5 class="card-title">
                            Séances de cours
                            <span>| <a href="cours/seance.add" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-circle"></i> Nouvelle séance
                            </a>
                            <a href="cours/stats.presences" class="btn btn-info btn-sm ms-1">
                                <i class="bi bi-bar-chart-line"></i> Statistiques
                            </a></span>
                        </h5>

                        <style>
                            .seances-compact .card-body { padding: 0.5rem; }
                            .seances-compact .card-title { font-size: 0.9rem; margin-bottom: 0.3rem; padding-top: 0.5rem; }
                            .seances-compact .table { font-size: 0.78rem; margin-bottom: 0; }
                            .seances-compact .table th, .seances-compact .table td { padding: 0.2rem 0.4rem; vertical-align: middle; }
                            .seances-compact .table .text-muted { font-size: 0.68rem; }
                            .seances-compact .btn-sm { padding: 0.1rem 0.3rem; font-size: 0.72rem; }
                            .seances-compact .badge { font-size: 0.68rem; padding: 0.2rem 0.4rem; }
                            .seances-compact .dataTable-wrapper .dataTable-top,
                            .seances-compact .dataTable-wrapper .dataTable-bottom { font-size: 0.78rem; padding: 0.3rem 0; }
                        </style>

                        <div class="row mb-2 align-items-center g-2">
                            <div class="col-auto">
                                <label class="form-label mb-0 small">Du</label>
                            </div>
                            <div class="col-auto">
                                <input type="date" id="filterDateDebut" class="form-control form-control-sm" style="width:150px;">
                            </div>
                            <div class="col-auto">
                                <label class="form-label mb-0 small">Au</label>
                            </div>
                            <div class="col-auto">
                                <input type="date" id="filterDateFin" class="form-control form-control-sm" style="width:150px;">
                            </div>
                            <div class="col-auto">
                                <button id="resetDate" class="btn btn-outline-secondary btn-sm" title="Réinitialiser">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover datatable" id="seancesTable">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Cours</th>
                                        <th scope="col">Titre</th>
                                        <th scope="col">Date</th>
                                        <th scope="col">Horaire</th>
                                        <th scope="col">Salle</th>
                                        <th scope="col">Présences</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $i = 1;
                                    foreach ($seances as $seance):
                                        $dateSeance = new DateTime($seance['date_seance']);
                                        $heureDebut = new DateTime($seance['heure_debut']);
                                        $heureFin = new DateTime($seance['heure_fin']);
                                    ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td>
                                            <?= htmlspecialchars($seance['designationECUE']) ?>
                                            <div class="small text-muted">
                                                <?= htmlspecialchars($seance['designationPromotion']) ?> - Sem. <?= $seance['numeroSemestre'] ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($seance['titre']) ?></td>
                                        <td><?= $dateSeance->format('d/m/Y') ?></td>
                                        <td><?= $heureDebut->format('H:i') ?> - <?= $heureFin->format('H:i') ?></td>
                                        <td><?= htmlspecialchars($seance['salle']) ?></td>
                                        <td>
                                            <span class="badge bg-success">
                                                <i class="bi bi-people-fill"></i> <?= $seance['nb_presents'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a target="_blank" href="controller/export_liste_presence_pdf.php?id=<?= $seance['idseance'] ?>" class="btn btn-warning btn-sm" title="Liste de présence PDF">
                                                    <i class="bi bi-file-earmark-pdf"></i>
                                                </a>
                                                <a href="cours/presence.list&id=<?= $seance['idseance'] ?>" class="btn btn-info btn-sm" title="Voir les présences">
                                                    <i class="bi bi-clipboard-check"></i>
                                                </a>
                                                <a href="cours/presence.add&id=<?= $seance['idseance'] ?>" class="btn btn-success btn-sm" title="Encoder présence">
                                                    <i class="bi bi-person-plus"></i>
                                                </a>
                                                <button onclick="deleteSeance(<?= $seance['idseance'] ?>)" class="btn btn-danger btn-sm" title="Supprimer">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include "./views/include/footer.php"; ?>

<script>
    function deleteSeance(idSeance) {
        Swal.fire({
            title: 'Confirmation',
            text: "Êtes-vous sûr de vouloir supprimer cette séance ?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `controller/delete_seance_cours.php?id=${idSeance}`;
            }
        });
    }

    // Filtre par plage de dates
    var allRows = document.querySelectorAll('#seancesTable tbody tr');
    function parseDateDMY(str) {
        var p = str.trim().split('/');
        return new Date(p[2], p[1] - 1, p[0]);
    }
    function filterByDate() {
        var debut = document.getElementById('filterDateDebut').value;
        var fin = document.getElementById('filterDateFin').value;
        if (!debut && !fin) { allRows.forEach(function(r) { r.style.display = ''; }); return; }
        var dDebut = debut ? new Date(debut) : null;
        var dFin = fin ? new Date(fin) : null;
        allRows.forEach(function(row) {
            var cell = row.cells[3];
            if (!cell) return;
            var dRow = parseDateDMY(cell.textContent);
            var show = true;
            if (dDebut && dRow < dDebut) show = false;
            if (dFin && dRow > dFin) show = false;
            row.style.display = show ? '' : 'none';
        });
    }
    document.getElementById('filterDateDebut').addEventListener('change', filterByDate);
    document.getElementById('filterDateFin').addEventListener('change', filterByDate);
    document.getElementById('resetDate').addEventListener('click', function() {
        document.getElementById('filterDateDebut').value = '';
        document.getElementById('filterDateFin').value = '';
        allRows.forEach(function(r) { r.style.display = ''; });
    });
</script>

