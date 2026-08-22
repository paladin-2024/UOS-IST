<?php
include "./views/include/header.php";
$pdo = Connexion::getInstance()->getPDO();

$query = "SELECT 
            p.idpalmares, 
            p.designation, 
            p.annee_academique,
            p.section,
            p.promotion,
            p.session,
            p.date_creation,
            (SELECT COUNT(*) FROM etudiants_palmares_archives WHERE idpalmares = p.idpalmares) as nb_etudiants
          FROM 
            palmares_archives p
          ORDER BY 
            p.date_creation DESC";

$stmt = $pdo->prepare($query);
$stmt->execute();
$palmares = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>PALMARÈS D'ARCHIVES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Palmarès d'archives</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
                            <h5 class="card-title">Liste des palmarès d'archives</h5>
                            <a href="?view=academique/ajouter_palmares_archive" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Ajouter un palmarès
                            </a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover datatable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Désignation</th>
                                        <th>Année académique</th>
                                        <th>Section</th>
                                        <th>Promotion</th>
                                        <th>Session</th>
                                        <th>Nombre d'étudiants</th>
                                        <th>Date de création</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($palmares) > 0): ?>
                                        <?php foreach ($palmares as $p): ?>
                                            <tr>
                                                <td><?= $p['idpalmares'] ?></td>
                                                <td><?= htmlspecialchars($p['designation']) ?></td>
                                                <td><?= htmlspecialchars($p['annee_academique']) ?></td>
                                                <td><?= htmlspecialchars($p['section']) ?></td>
                                                <td><?= htmlspecialchars($p['promotion']) ?></td>
                                                <td><?= htmlspecialchars($p['session']) ?></td>
                                                <td><?= $p['nb_etudiants'] ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($p['date_creation'])) ?></td>
                                                <td>
                                                    <a href="?view=academique/voir_palmares_archive&id=<?= $p['idpalmares'] ?>" class="btn btn-sm btn-info" title="Voir">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="controller/exporter_palmares_archive.php?id=<?= $p['idpalmares'] ?>" class="btn btn-sm btn-success" title="Exporter">
                                                        <i class="bi bi-file-excel"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger delete-palmares" data-id="<?= $p['idpalmares'] ?>" title="Supprimer">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">Aucun palmarès d'archive n'a été trouvé.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script src="assets/js/datatables-fr.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    
    // Gestion de la suppression
    $('.delete-palmares').on('click', function() {
        const palmaresId = $(this).data('id');
        
        Swal.fire({
            title: 'Êtes-vous sûr?',
            text: "Cette action supprimera définitivement ce palmarès et toutes les données associées!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer!',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `controller/delete_palmares_archive.php?id=${palmaresId}`;
            }
        });
    });
});
</script>

<?php include "./views/include/footer.php"; ?>