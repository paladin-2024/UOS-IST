<?php
include "./views/include/header.php";
error_reporting(E_ALL); ini_set("display_errors", 1);
$db = Connexion::getInstance()->getPDO();

// Récupération de l'ID de la commande
$commandeId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($commandeId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Commande non trouvée'
        }).then(() => {
            window.location.href = 'achats/commandes/commandes.list';
        });
    </script>";
    exit;
}

// Récupération des détails de la commande
$query = "SELECT cf.*, f.nom_fournisseur, f.code_fournisseur, f.telephone, f.email, f.adresse, f.nif, f.rccm 
          FROM commande_fournisseur cf 
          JOIN fournisseur f ON cf.id_fournisseur = f.id_fournisseur 
          WHERE cf.id_commande = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $commandeId, PDO::PARAM_INT);
$stmt->execute();
$commande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$commande) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Commande non trouvée'
        }).then(() => {
            window.location.href = 'achats/commandes/commandes.list';
        });
    </script>";
    exit;
}

// Récupération des lignes de la commande
$queryLignes = "SELECT lcf.*, p.code_produit, p.libelle_produit 
                FROM ligne_commande_fournisseur lcf 
                JOIN produit p ON lcf.id_produit = p.id_produit 
                WHERE lcf.id_commande = :id_commande";
$stmtLignes = $db->prepare($queryLignes);
$stmtLignes->bindParam(':id_commande', $commandeId, PDO::PARAM_INT);
$stmtLignes->execute();
$lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

// Récupération des informations sur les utilisateurs (création et validation)
$queryUserCreation = "SELECT nomUser FROM t_users WHERE idUser = :id";
$stmtUserCreation = $db->prepare($queryUserCreation);
$stmtUserCreation->bindParam(':id', $commande['id_user_creation'], PDO::PARAM_INT);
$stmtUserCreation->execute();
$userCreation = $stmtUserCreation->fetch(PDO::FETCH_ASSOC);

$userValidation = null;
if ($commande['id_user_validation']) {
    $queryUserValidation = "SELECT nomUser FROM t_users WHERE idUser = :id";
    $stmtUserValidation = $db->prepare($queryUserValidation);
    $stmtUserValidation->bindParam(':id', $commande['id_user_validation'], PDO::PARAM_INT);
    $stmtUserValidation->execute();
    $userValidation = $stmtUserValidation->fetch(PDO::FETCH_ASSOC);
}

// Vérifier si la commande a été réceptionnée
$isReceptionne = ($commande['etat'] == 'Réceptionné' || $commande['etat'] == 'Facturé');
if ($isReceptionne) {
    $queryReception = "SELECT id_reception, numero_reception, date_reception FROM reception_fournisseur WHERE id_commande = :id_commande LIMIT 1";
    $stmtReception = $db->prepare($queryReception);
    $stmtReception->bindParam(':id_commande', $commandeId, PDO::PARAM_INT);
    $stmtReception->execute();
    $reception = $stmtReception->fetch(PDO::FETCH_ASSOC);
}

// Vérifier si la commande a été facturée
$isFacture = ($commande['etat'] == 'Facturé');
if ($isFacture) {
    $queryFacture = "SELECT id_facture, numero_facture, date_facture, montant_ttc FROM facture_fournisseur WHERE id_reception IN (SELECT id_reception FROM reception_fournisseur WHERE id_commande = :id_commande) LIMIT 1";
    $stmtFacture = $db->prepare($queryFacture);
    $stmtFacture->bindParam(':id_commande', $commandeId, PDO::PARAM_INT);
    $stmtFacture->execute();
    $facture = $stmtFacture->fetch(PDO::FETCH_ASSOC);
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>DÉTAILS DE LA COMMANDE FOURNISSEUR</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Achats</li>
                <li class="breadcrumb-item"><a href="achats/commandes/commandes.list">Commandes</a></li>
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
                                Commande N° <?= htmlspecialchars($commande['numero_commande']) ?>
                                <?php
                                switch ($commande['etat']) {
                                    case 'En cours':
                                        echo '<span class="badge bg-warning ms-2">En cours</span>';
                                        break;
                                    case 'Validé':
                                        echo '<span class="badge bg-success ms-2">Validé</span>';
                                        break;
                                    case 'Réceptionné':
                                        echo '<span class="badge bg-info ms-2">Réceptionné</span>';
                                        break;
                                    case 'Facturé':
                                        echo '<span class="badge bg-primary ms-2">Facturé</span>';
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
                                    <?php if ($commande['etat'] == 'En cours'): ?>
                                        <li><a class="dropdown-item" href="achats/commandes/commandes.edit&id=<?= $commandeId ?>"><i class="bi bi-pencil me-2"></i>Modifier</a></li>
                                        <li><a class="dropdown-item" onclick="validateCommande(<?= $commandeId ?>)"><i class="bi bi-check-circle me-2"></i>Valider</a></li>
                                        <li><a class="dropdown-item" onclick="cancelCommande(<?= $commandeId ?>)"><i class="bi bi-x-circle me-2"></i>Annuler</a></li>
                                    <?php endif; ?>
                                    <?php if ($commande['etat'] == 'Validé'): ?>
                                        <li><a class="dropdown-item" href="achats/receptions/receptions.add&commande=<?= $commandeId ?>"><i class="bi bi-box me-2"></i>Réceptionner</a></li>
                                    <?php endif; ?>
                                    <?php if ($isReceptionne && isset($reception)): ?>
                                        <li><a class="dropdown-item" href="achats/receptions/receptions.view&id=<?= $reception['id_reception'] ?>"><i class="bi bi-eye me-2"></i>Voir la réception</a></li>
                                    <?php endif; ?>
                                    <?php if ($isFacture && isset($facture)): ?>
                                        <li><a class="dropdown-item" href="achats/factures/factures.view&id=<?= $facture['id_facture'] ?>"><i class="bi bi-file-text me-2"></i>Voir la facture</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" onclick="exportPDF(<?= $commandeId ?>)"><i class="bi bi-file-pdf me-2"></i>Exporter en PDF</a></li>
                                    <li><a class="dropdown-item" onclick="printCommande(<?= $commandeId ?>)"><i class="bi bi-printer me-2"></i>Imprimer</a></li>
                                </ul>
                            </div>
                        </div>

                        <!-- Informations de la commande -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Informations générales</h5>
                                        <table class="table table-bordered">
                                            <tr>
                                                <th width="40%">Numéro</th>
                                                <td><?= htmlspecialchars($commande['numero_commande']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Date</th>
                                                <td><?= date('d/m/Y', strtotime($commande['date_commande'])) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Date livraison prévue</th>
                                                <td><?= $commande['date_livraison_prevue'] ? date('d/m/Y', strtotime($commande['date_livraison_prevue'])) : 'N/A' ?></td>
                                            </tr>
                                            <tr>
                                                <th>État</th>
                                                <td>
                                                    <?php
                                                    switch ($commande['etat']) {
                                                        case 'En cours':
                                                            echo '<span class="badge bg-warning">En cours</span>';
                                                            break;
                                                        case 'Validé':
                                                            echo '<span class="badge bg-success">Validé</span>';
                                                            break;
                                                        case 'Réceptionné':
                                                            echo '<span class="badge bg-info">Réceptionné</span>';
                                                            break;
                                                        case 'Facturé':
                                                            echo '<span class="badge bg-primary">Facturé</span>';
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
                                                <td><?= nl2br(htmlspecialchars($commande['observation'] ?? 'N/A')) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <?php if ($isReceptionne && isset($reception)): ?>
                                <div class="card mt-3">
                                    <div class="card-body">
                                        <h5 class="card-title">Réception</h5>
                                        <table class="table table-bordered">
                                            <tr>
                                                <th width="40%">Numéro</th>
                                                <td>
                                                    <a href="achats/receptions/receptions.view&id=<?= $reception['id_reception'] ?>">
                                                        <?= htmlspecialchars($reception['numero_reception']) ?>
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Date</th>
                                                <td><?= date('d/m/Y', strtotime($reception['date_reception'])) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($isFacture && isset($facture)): ?>
                                <div class="card mt-3">
                                    <div class="card-body">
                                        <h5 class="card-title">Facture</h5>
                                        <table class="table table-bordered">
                                            <tr>
                                                <th width="40%">Numéro</th>
                                                <td>
                                                    <a href="achats/factures/factures.view&id=<?= $facture['id_facture'] ?>">
                                                        <?= htmlspecialchars($facture['numero_facture']) ?>
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Date</th>
                                                <td><?= date('d/m/Y', strtotime($facture['date_facture'])) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Montant</th>
                                                <td><?= number_format($facture['montant_ttc'], 2, ',', ' ') ?> USD</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Fournisseur</h5>
                                        <table class="table table-bordered">
                                            <tr>
                                                <th width="40%">Code</th>
                                                <td><?= htmlspecialchars($commande['code_fournisseur']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Nom</th>
                                                <td>
                                                    <a href="fournisseurs/fournisseurs.view&id=<?= $commande['id_fournisseur'] ?>">
                                                        <?= htmlspecialchars($commande['nom_fournisseur']) ?>
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Adresse</th>
                                                <td><?= htmlspecialchars($commande['adresse'] ?? 'N/A') ?></td>
                                            </tr>
                                            <tr>
                                                <th>Téléphone</th>
                                                <td><?= htmlspecialchars($commande['telephone'] ?? 'N/A') ?></td>
                                            </tr>
                                            <tr>
                                                <th>Email</th>
                                                <td><?= htmlspecialchars($commande['email'] ?? 'N/A') ?></td>
                                            </tr>
                                            <tr>
                                                <th>NIF</th>
                                                <td><?= htmlspecialchars($commande['nif'] ?? 'N/A') ?></td>
                                            </tr>
                                            <tr>
                                                <th>RCCM</th>
                                                <td><?= htmlspecialchars($commande['rccm'] ?? 'N/A') ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Détails des produits -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title">Produits commandés</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Produit</th>
                                                <th>Désignation</th>
                                                <th>Quantité</th>
                                                <th>Prix unitaire</th>
                                                <th>Remise</th>
                                                <th>Montant HT</th>
                                                <th>TVA</th>
                                                <th>Montant TTC</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($lignes)): ?>
                                                <tr>
                                                    <td colspan="9" class="text-center">Aucun produit trouvé</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($lignes as $ligne): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($ligne['code_produit']) ?></td>
                                                        <td><?= htmlspecialchars($ligne['libelle_produit']) ?></td>
                                                        <td><?= htmlspecialchars($ligne['designation']) ?></td>
                                                        <td class="text-end"><?= number_format($ligne['quantite'], 2, ',', ' ') ?></td>
                                                        <td class="text-end"><?= number_format($ligne['prix_unitaire'], 2, ',', ' ') ?> USD</td>
                                                        <td class="text-end">
                                                            <?php if ($ligne['remise'] > 0): ?>
                                                                <?= number_format($ligne['remise'], 2, ',', ' ') ?>% 
                                                                (<?= number_format($ligne['montant_remise'], 2, ',', ' ') ?> USD)
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-end"><?= number_format($ligne['montant_ht'], 2, ',', ' ') ?> USD</td>
                                                        <td class="text-end"><?= number_format($ligne['montant_tva'], 2, ',', ' ') ?> USD</td>
                                                        <td class="text-end"><?= number_format($ligne['montant_ttc'], 2, ',', ' ') ?> USD</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="6" class="text-end">Total HT:</th>
                                                <td class="text-end"><?= number_format($commande['montant_ht'], 2, ',', ' ') ?> USD</td>
                                                <td colspan="2"></td>
                                            </tr>
                                            <tr>
                                                <th colspan="6" class="text-end">TVA (<?= number_format($commande['taux_tva'], 2, ',', ' ') ?>%):</th>
                                                <td class="text-end"><?= number_format($commande['montant_tva'], 2, ',', ' ') ?> USD</td>
                                                <td colspan="2"></td>
                                            </tr>
                                            <tr>
                                                <th colspan="6" class="text-end">Total TTC:</th>
                                                <td class="text-end"><?= number_format($commande['montant_ttc'], 2, ',', ' ') ?> USD</td>
                                                <td colspan="2"></td>
                                            </tr>
                                        </tfoot>
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
                                        <p><strong>Date de création:</strong> <?= date('d/m/Y H:i', strtotime($commande['date_creation'])) ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($commande['id_user_validation']): ?>
                                            <p><strong>Validé par:</strong> <?= htmlspecialchars($userValidation['nomUser'] ?? 'N/A') ?></p>
                                            <p><strong>Date de validation:</strong> <?= date('d/m/Y H:i', strtotime($commande['date_validation'])) ?></p>
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
    function validateCommande(id) {
        Swal.fire({
            title: 'Confirmer la validation',
            text: "Voulez-vous vraiment valider cette commande?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, valider',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/validate_commande.php?id=' + id + '&action=validate';
            }
        });
    }

    function cancelCommande(id) {
        Swal.fire({
            title: 'Confirmer l\'annulation',
            text: "Voulez-vous vraiment annuler cette commande?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, annuler',
            cancelButtonText: 'Non'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/validate_commande.php?id=' + id + '&action=cancel';
            }
        });
    }

    function printCommande(id) {
        window.open('achats/commandes/commandes.print&id=' + id, '_blank');
    }
    
    function exportPDF(id) {
        window.open('controller/commande_pdf.php?id=' + id, '_blank');
    }
</script>

<?php include "./views/include/footer.php"; ?>
