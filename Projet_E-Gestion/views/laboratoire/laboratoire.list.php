<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();
$universite = new Universite();

// Récupérer l'année académique en cours
$anneeEnCours = $universite->getCurrentAcademicYear();
$anneeId = $anneeEnCours['idannee_acad'];

// Vérifier si l'utilisateur est administrateur
$isAdmin = false;
if (isset($_SESSION['idRole'])) {
    // Vérifier si le rôle est "Administrateur" (supposons que l'ID du rôle admin est 1)
    // Vous devrez ajuster cette condition selon votre structure de rôles
    $isAdmin = ($_SESSION['idRole'] == 1);
}

// Récupérer l'ID de l'utilisateur connecté
$userId = $_SESSION['id'] ?? 0;

// Récupérer l'ID de l'agent associé à l'utilisateur
$agentId = 0;
if ($userId) {
    $query = "SELECT idAgent FROM t_users WHERE \"idUser\" = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':userId', $userId);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $agentId = $result['idAgent'];
    }
}

// Requête SQL différente selon le rôle
if ($isAdmin) {
    // Administrateur voit tous les laboratoires
    $query = "SELECT l.*, a.noms as responsable, COUNT(al.idautorisation) as nb_utilisateurs
              FROM laboratoire l
              LEFT JOIN agent a ON l.responsable_id = a.\"idAgent\"
              LEFT JOIN autorisation_labo al ON l.idlabo = al.idlabo
              GROUP BY l.idlabo
              ORDER BY l.date_creation DESC";
    $stmt = $db->query($query);
} else {
    // Utilisateur normal voit uniquement les laboratoires où il est autorisé
    $query = "SELECT l.*, a.noms as responsable, COUNT(al2.idautorisation) as nb_utilisateurs
              FROM laboratoire l
              LEFT JOIN agent a ON l.responsable_id = a.\"idAgent\"
              LEFT JOIN autorisation_labo al ON l.idlabo = al.idlabo AND al.\"idAgent\" = :agentId
              LEFT JOIN autorisation_labo al2 ON l.idlabo = al2.idlabo
              WHERE al.idautorisation IS NOT NULL
              GROUP BY l.idlabo
              ORDER BY l.date_creation DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':agentId', $agentId);
}

$stmt->execute();
$laboratoires = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
    <h1>Gestion des laboratoires</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Recherche</li>
                <li class="breadcrumb-item active">Laboratoires</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Liste des laboratoires
                            <?php if ($isAdmin): ?>
                            <span>| <a href="laboratoire/laboratoire.add" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-circle"></i> Nouveau laboratoire
                            </a></span>
                            <?php endif; ?>
                        </h5>

                        <div class="table-responsive">
                            <table class="table table-striped datatable">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Nom du laboratoire</th>
                                        <th scope="col">Localisation</th>
                                        <th scope="col">Responsable</th>
                                        <th scope="col">Utilisateurs</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $i = 1;
                                    foreach ($laboratoires as $labo): 
                                    ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><?= htmlspecialchars($labo['nom']) ?></td>
                                        <td><?= htmlspecialchars($labo['localisation']) ?></td>
                                        <td><?= htmlspecialchars($labo['responsable']) ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <i class="bi bi-people"></i> <?= $labo['nb_utilisateurs'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="laboratoire/laboratoire.view&id=<?= $labo['idlabo'] ?>" class="btn btn-info btn-sm" title="Voir détails">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($isAdmin): ?>
                                                <a href="laboratoire/autorisation.add&id=<?= $labo['idlabo'] ?>" class="btn btn-success btn-sm" title="Gérer les utilisateurs">
                                                    <i class="bi bi-person-plus"></i>
                                                </a>
                                                <?php endif; ?>
                                                <a href="laboratoire/seance.list&id=<?= $labo['idlabo'] ?>" class="btn btn-primary btn-sm" title="Séances">
                                                    <i class="bi bi-calendar-check"></i>
                                                </a>
                                                <?php if ($isAdmin): ?>
                                                <a href="laboratoire/laboratoire.edit&id=<?= $labo['idlabo'] ?>" class="btn btn-warning btn-sm" title="Modifier">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button onclick="deleteLabo(<?= $labo['idlabo'] ?>)" class="btn btn-danger btn-sm" title="Supprimer">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>
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
            }
        });
    });

    <?php if ($isAdmin): ?>
    function deleteLabo(idLabo) {
        Swal.fire({
            title: 'Confirmation',
            text: "Êtes-vous sûr de vouloir supprimer ce laboratoire ?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `controller/delete_laboratoire.php?id=${idLabo}`;
            }
        });
    }
    <?php endif; ?>
</script>

<?php include "./views/include/footer.php"; ?>
