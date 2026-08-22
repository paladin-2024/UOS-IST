<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();
$universite = new Universite();

// Récupérer l'ID du laboratoire
$idLabo = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$idLabo) {
    echo "<script>window.location.href='laboratoire/laboratoire.list';</script>";
    exit;
}

// Récupérer l'année académique en cours
$anneeEnCours = $universite->getCurrentAcademicYear();
$anneeId = $anneeEnCours['idannee_acad'];

// Récupérer les informations du laboratoire
$queryLabo = "SELECT * FROM laboratoire WHERE idlabo = :idLabo";
$stmtLabo = $db->prepare($queryLabo);
$stmtLabo->bindParam(':idLabo', $idLabo);
$stmtLabo->execute();
$labo = $stmtLabo->fetch(PDO::FETCH_ASSOC);

if (!$labo) {
    echo "<script>window.location.href='laboratoire/laboratoire.list';</script>";
    exit;
}

// Récupérer les séances du laboratoire
$querySeances = "SELECT sl.*, u.\"nomUser\" as responsable_nom, 
                 (SELECT COUNT(*) FROM presence_labo pl WHERE pl.idseance_labo = sl.idseance_labo) as nb_presents
                 FROM seance_labo sl
                 LEFT JOIN t_users u ON sl.\"idUser\" = u.\"idUser\"
                 WHERE sl.idlabo = :idLabo
                 ORDER BY sl.date_seance DESC, sl.heure_debut DESC";

                 
$stmtSeances = $db->prepare($querySeances);
$stmtSeances->bindParam(':idLabo', $idLabo);
$stmtSeances->execute();
$seances = $stmtSeances->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Séances de laboratoire</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Recherche</li>
                <li class="breadcrumb-item"><a href="laboratoire/laboratoire.list">Laboratoires</a></li>
                <li class="breadcrumb-item active">Séances</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Séances du laboratoire: <?= htmlspecialchars($labo['nom']) ?>
                            <span>| <a href="laboratoire/seance.add&id=<?= $idLabo ?>" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-circle"></i> Nouvelle séance
                            </a></span>
                        </h5>

                        <div class="alert alert-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Laboratoire:</strong> <?= htmlspecialchars($labo['nom']) ?><br>
                                    <strong>Localisation:</strong> <?= htmlspecialchars($labo['localisation']) ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Total des séances:</strong> <?= count($seances) ?>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped datatable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Horaire</th>
                                        <th>Titre</th>
                                        <th>Responsable</th>
                                        <th>Présences</th>
                                        <th>Actions</th>
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
                                        <td><?= $dateSeance->format('d/m/Y') ?></td>
                                        <td><?= $heureDebut->format('H:i') ?> - <?= $heureFin->format('H:i') ?></td>
                                        <td><?= htmlspecialchars($seance['titre']) ?></td>
                                        <td><?= htmlspecialchars($seance['responsable_nom']) ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <i class="bi bi-people"></i> <?= $seance['nb_presents'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="laboratoire/presence.list&id=<?= $seance['idseance_labo'] ?>" class="btn btn-primary btn-sm" title="Présences">
                                                    <i class="bi bi-clipboard-check"></i>
                                                </a>
                                                <a target="_" href="controller/seance.qr.php?id=<?= $seance['idseance_labo'] ?>" class="btn btn-info btn-sm" title="QR Code">
                                                    <i class="bi bi-qr-code"></i>
                                                </a>
                                                <a href="laboratoire/seance.edit&id=<?= $seance['idseance_labo'] ?>" class="btn btn-warning btn-sm" title="Modifier">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button onclick="deleteSeance(<?= $seance['idseance_labo'] ?>)" class="btn btn-danger btn-sm" title="Supprimer">
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

<script>
    $(document).ready(function() {
        $('.datatable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/French.json"
            },
            "order": [[1, 'desc'], [2, 'desc']]
        });
    });

    function deleteSeance(idSeance) {
        Swal.fire({
            title: 'Confirmation',
            text: "Êtes-vous sûr de vouloir supprimer cette séance ? Cette action est irréversible.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `controller/delete_seance_labo.php?id=${idSeance}&idLabo=<?= $idLabo ?>`;
            }
        });
    }
</script>

<?php include "./views/include/footer.php"; ?>

