<?php
include "./views/include/header.php";
error_reporting(E_ALL); ini_set("display_errors", 1);
$db = Connexion::getInstance()->getPDO();


// Récupération de l'ID de la demande
$demandeId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($demandeId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Demande de prix non trouvée'
        }).then(() => {
            window.location.href = 'achats/demandes.list';
        });
    </script>";
    exit;
}

// Récupération des détails de la demande
$query = "SELECT dp.*, f.nom_fournisseur, f.code_fournisseur, f.telephone, f.email 
          FROM demande_prix dp 
          JOIN fournisseur f ON dp.id_fournisseur = f.id_fournisseur 
          WHERE dp.id_demande_prix = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $demandeId, PDO::PARAM_INT);
$stmt->execute();
$demande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demande) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Demande de prix non trouvée'
        }).then(() => {
            window.location.href = 'achats/demandes/demandes.list';
        });
    </script>";
    exit;
}

// Récupération des lignes de la demande
$queryLignes = "SELECT ldp.*, p.code_produit, p.libelle_produit 
                FROM ligne_demande_prix ldp 
                JOIN produit p ON ldp.id_produit = p.id_produit 
                WHERE ldp.id_demande_prix = :id_demande";
$stmtLignes = $db->prepare($queryLignes);
$stmtLignes->bindParam(':id_demande', $demandeId, PDO::PARAM_INT);
$stmtLignes->execute();
$lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

// Récupération des informations sur les utilisateurs (création et validation)
$queryUserCreation = "SELECT \"nomUser\" FROM t_users WHERE \"idUser\" = :id";
$stmtUserCreation = $db->prepare($queryUserCreation);
$stmtUserCreation->bindParam(':id', $demande['id_user_creation'], PDO::PARAM_INT);
$stmtUserCreation->execute();
$userCreation = $stmtUserCreation->fetch(PDO::FETCH_ASSOC);

$userValidation = null;
if ($demande['id_user_validation']) {
    $queryUserValidation = "SELECT \"nomUser\" FROM t_users WHERE \"idUser\" = :id";
    $stmtUserValidation = $db->prepare($queryUserValidation);
    $stmtUserValidation->bindParam(':id', $demande['id_user_validation'], PDO::PARAM_INT);
    $stmtUserValidation->execute();
    $userValidation = $stmtUserValidation->fetch(PDO::FETCH_ASSOC);
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>DÉTAILS DE LA DEMANDE DE PRIX</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Achats</li>
                <li class="breadcrumb-item"><a href="achats/demandes/demandes.list">Demandes de prix</a></li>
                <li class="breadcrumb-item active">Détails</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
            <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">
                                Demande de prix N° <?= htmlspecialchars($demande['numero_demande']) ?>
                                <?php
                                switch ($demande['etat']) {
                                    case 'En cours':
                                        echo '<span class="badge bg-warning ms-2">En cours</span>';
                                        break;
                                    case 'Validé':
                                        echo '<span class="badge bg-success ms-2">Validé</span>';
                                        break;
                                    case 'Transformé':
                                        echo '<span class="badge bg-info ms-2">Transformé</span>';
                                        break;
                                    case 'Annulé':
                                        echo '<span class="badge bg-danger ms-2">Annulé</span>';
                                        break;
                                }
                                ?>
                            </h5>
                            
                            <div class="btn-group">
                                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    Actions
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php if ($demande['etat'] == 'En cours'): ?>
                                        <li><a class="dropdown-item" href="achats/demandes/demandes.edit&id=<?= $demandeId ?>"><i class="bi bi-pencil me-2"></i>Modifier</a></li>
                                        <li><a class="dropdown-item" onclick="validateDemande(<?= $demandeId ?>)"><i class="bi bi-check-circle me-2"></i>Valider</a></li>
                                        <li><a class="dropdown-item" onclick="cancelDemande(<?= $demandeId ?>)"><i class="bi bi-x-circle me-2"></i>Annuler</a></li>
                                    <?php endif; ?>
                                    <?php if ($demande['etat'] == 'Validé'): ?>
                                        <li><a class="dropdown-item" href="controller/create_commande_from_demande.php?id=<?= $demandeId ?>"><i class="bi bi-arrow-right-circle me-2"></i>Transformer en commande</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" onclick="exportPDF(<?= $demandeId ?>)"><i class="bi bi-file-pdf me-2"></i>Exporter en PDF</a></li>
                                    <li><a class="dropdown-item" onclick="printDemande(<?= $demandeId ?>)"><i class="bi bi-printer me-2"></i>Imprimer</a></li>
                                </ul>
                            </div>
                        </div>

                        <!-- Informations de la demande -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Informations générales</h5>
                                        <table class="table table-bordered">
                                            <tr>
                                                <th width="40%">Numéro</th>
                                                <td><?= htmlspecialchars($demande['numero_demande']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Date</th>
                                                <td><?= date('d/m/Y', strtotime($demande['date_demande'])) ?></td>
                                            </tr>
                                            <tr>
                                                <th>État</th>
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
                                            </tr>
                                            <tr>
                                                <th>Observation</th>
                                                <td><?= nl2br(htmlspecialchars($demande['observation'] ?? 'N/A')) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Fournisseur</h5>
                                        <table class="table table-bordered">
                                            <tr>
                                                <th width="40%">Code</th>
                                                <td><?= htmlspecialchars($demande['code_fournisseur']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Nom</th>
                                                <td><?= htmlspecialchars($demande['nom_fournisseur']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Téléphone</th>
                                                <td><?= htmlspecialchars($demande['telephone'] ?? 'N/A') ?></td>
                                            </tr>
                                            <tr>
                                                <th>Email</th>
                                                <td><?= htmlspecialchars($demande['email'] ?? 'N/A') ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Détails des produits -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title">Produits demandés</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Produit</th>
                                                <th>Désignation</th>
                                                <th>Quantité</th>
                                                <th>Prix unitaire</th>
                                                <th>Montant total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($lignes)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">Aucun produit trouvé</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($lignes as $ligne): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($ligne['code_produit']) ?></td>
                                                        <td><?= htmlspecialchars($ligne['libelle_produit']) ?></td>
                                                        <td><?= htmlspecialchars($ligne['designation']) ?></td>
                                                        <td class="text-end"><?= number_format($ligne['quantite'], 2, ',', ' ') ?></td>
                                                        <td class="text-end">
                                                            <?= $ligne['prix_unitaire'] ? number_format($ligne['prix_unitaire'], 2, ',', ' ') . ' USD' : 'N/A' ?>
                                                        </td>
                                                        <td class="text-end">
                                                            <?= $ligne['montant_total'] ? number_format($ligne['montant_total'], 2, ',', ' ') . ' USD' : 'N/A' ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Informations de traçabilité -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title">Informations de traçabilité</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Créé par:</strong> <?= htmlspecialchars($userCreation['nomUser'] ?? 'N/A') ?></p>
                                        <p><strong>Date de création:</strong> <?= date('d/m/Y H:i', strtotime($demande['date_creation'])) ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($demande['id_user_validation']): ?>
                                            <p><strong>Validé par:</strong> <?= htmlspecialchars($userValidation['nomUser'] ?? 'N/A') ?></p>
                                            <p><strong>Date de validation:</strong> <?= date('d/m/Y H:i', strtotime($demande['date_validation'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    function validateDemande(id) {
        Swal.fire({
            title: 'Confirmer la validation',
            text: "Voulez-vous vraiment valider cette demande de prix?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, valider',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/validate_demande_prix.php?id=' + id + '&action=validate';
            }
        });
    }

    function cancelDemande(id) {
        Swal.fire({
            title: 'Confirmer l\'annulation',
            text: "Voulez-vous vraiment annuler cette demande de prix?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, annuler',
            cancelButtonText: 'Non'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/validate_demande_prix.php?id=' + id + '&action=cancel';
            }
        });
    }

    function printDemande(id) {
        // Version simplifiée sans confirmation
        window.open('achats/demandes/demandes.print&id=' + id, '_blank');
    }
    
    function exportPDF(id) {
        // Version simplifiée sans confirmation
        window.open('controller/demande_prix_pdf.php?id=' + id, '_blank');
    }
</script>

<?php include "./views/include/footer.php"; ?>

