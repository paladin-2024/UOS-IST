<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des demandes de prix avec le nom du fournisseur
$query = "SELECT dp.*, f.nom_fournisseur 
          FROM demande_prix dp
          JOIN fournisseur f ON dp.id_fournisseur = f.id_fournisseur
          ORDER BY dp.date_demande DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>DEMANDES DE PRIX</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Achats</li>
                <li class="breadcrumb-item active">Demandes de prix</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">Liste des demandes de prix</h5>
                            <a href="achats/demandes/demandes.add" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nouvelle demande
                            </a>
                        </div>

                        <table class="table table-striped datatable">
                            <thead>
                                <tr>
                                    <th>N° Demande</th>
                                    <th>Date</th>
                                    <th>Fournisseur</th>
                                    <th>État</th>
                                    <th>Créé par</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($demandes as $demande): ?>
                                    <?php
                                    // Récupérer le nom de l'utilisateur qui a créé la demande
                                    $queryUser = "SELECT \"nomUser\" FROM t_users WHERE \"idUser\" = :id";
                                    $stmtUser = $db->prepare($queryUser);
                                    $stmtUser->bindParam(':id', $demande['id_user_creation'], PDO::PARAM_INT);
                                    $stmtUser->execute();
                                    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($demande['numero_demande']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($demande['date_demande'])) ?></td>
                                        <td><?= htmlspecialchars($demande['nom_fournisseur']) ?></td>
                                        <td>
                                            <?php
                                            switch ($demande['etat']) {
                                                case 'En cours':
                                                    echo '<span class="badge bg-warning">En cours</span>';
                                                    break;
                                                case 'Validé':
                                                    echo '<span class="badge bg-success">Validé</span>';
                                                    break;
                                                case 'Transformé':
                                                    echo '<span class="badge bg-info">Transformé</span>';
                                                    break;
                                                case 'Annulé':
                                                    echo '<span class="badge bg-danger">Annulé</span>';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                        <td><?= htmlspecialchars($user['nomUser'] ?? 'N/A') ?></td>
                                        <td>
                                            <a href="achats/demandes/demandes.view&id=<?= $demande['id_demande_prix'] ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($demande['etat'] == 'En cours'): ?>
                                                <a href="achats/demandes/demandes.edit&id=<?= $demande['id_demande_prix'] ?>" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include "./views/include/footer.php"; ?>
