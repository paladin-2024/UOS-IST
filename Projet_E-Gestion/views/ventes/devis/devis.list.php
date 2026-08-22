<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des devis avec les informations du client
$query = "SELECT d.*, c.nom_client, c.code_client 
          FROM devis d 
          JOIN client c ON d.id_client = c.id_client 
          ORDER BY d.date_creation DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$devis = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>LISTE DES DEVIS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Ventes</li>
                <li class="breadcrumb-item active">Devis</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">Devis clients</h5>
                            <a href="ventes/devis/devis.add" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nouveau devis
                            </a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover datatable">
                                <thead>
                                    <tr>
                                        <th scope="col">N° Devis</th>
                                        <th scope="col">Date</th>
                                        <th scope="col">Client</th>
                                        <th scope="col">Montant TTC</th>
                                        <th scope="col">Validité</th>
                                        <th scope="col">État</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($devis as $d): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($d['numero_devis']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($d['date_devis'])) ?></td>
                                            <td><?= htmlspecialchars($d['code_client'] . ' - ' . $d['nom_client']) ?></td>
                                            <td class="text-end"><?= number_format($d['montant_ttc'], 2, ',', ' ') ?> USD</td>
                                            <td>
                                                <?php 
                                                $dateValidite = date('Y-m-d', strtotime($d['date_devis'] . ' + ' . $d['validite'] . ' days'));
                                                $today = date('Y-m-d');
                                                $badgeClass = ($today > $dateValidite) ? 'bg-danger' : 'bg-success';
                                                echo date('d/m/Y', strtotime($dateValidite));
                                                echo ' <span class="badge ' . $badgeClass . '">' . $d['validite'] . ' jours</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                switch ($d['etat']) {
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
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="ventes/devis/devis.view&id=<?= $d['id_devis'] ?>" class="btn btn-sm btn-info" title="Voir">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    
                                                    <?php if ($d['etat'] == 'En cours'): ?>
                                                        <a href="ventes/devis/devis.edit&id=<?= $d['id_devis'] ?>" class="btn btn-sm btn-primary" title="Modifier">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-success" onclick="validateDevis(<?= $d['id_devis'] ?>)" title="Valider">
                                                            <i class="bi bi-check-circle"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" onclick="cancelDevis(<?= $d['id_devis'] ?>)" title="Annuler">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($d['etat'] == 'Validé'): ?>
                                                        <button type="button" class="btn btn-sm btn-secondary" onclick="transformDevis(<?= $d['id_devis'] ?>)" title="Transformer en commande">
                                                            <i class="bi bi-arrow-right-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <a href="ventes/devis/devis.print&id=<?= $d['id_devis'] ?>" class="btn btn-sm btn-secondary" target="_blank" title="Imprimer">
                                                        <i class="bi bi-printer"></i>
                                                    </a>
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
    function validateDevis(id) {
        Swal.fire({
            title: 'Confirmer la validation',
            text: "Voulez-vous vraiment valider ce devis ?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, valider',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/validate_devis.php?id=' + id + '&action=validate';
            }
        });
    }

    function cancelDevis(id) {
        Swal.fire({
            title: 'Confirmer l\'annulation',
            text: "Voulez-vous vraiment annuler ce devis ?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, annuler',
            cancelButtonText: 'Non'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/validate_devis.php?id=' + id + '&action=cancel';
            }
        });
    }

    function transformDevis(id) {
        Swal.fire({
            title: 'Transformer en commande',
            text: "Voulez-vous transformer ce devis en commande client ?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, transformer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'ventes/commandes/commandes.add&devis=' + id;
            }
        });
    }
</script>

<?php include "./views/include/footer.php"; ?>
