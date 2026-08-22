<?php
include "./views/include/header.php";
error_reporting(E_ALL); ini_set("display_errors", 1);
$db = Connexion::getInstance()->getPDO();

// Récupération de l'ID de la facture
$factureId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($factureId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Facture non trouvée'
        }).then(() => {
            window.location.href = 'achats/factures/factures.list';
        });
    </script>";
    exit;
}

// Récupération des détails de la facture
$query = "SELECT f.*, four.nom_fournisseur, four.code_fournisseur, four.telephone, four.email 
          FROM facture_fournisseur f 
          JOIN fournisseur four ON f.id_fournisseur = four.id_fournisseur 
          WHERE f.id_facture = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $factureId, PDO::PARAM_INT);
$stmt->execute();
$facture = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$facture) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Facture non trouvée'
        }).then(() => {
            window.location.href = 'achats/factures/factures.list';
        });
    </script>";
    exit;
}

// Récupération des lignes de la facture
$queryLignes = "SELECT lf.*, p.code_produit, p.libelle_produit 
                FROM ligne_facture_fournisseur lf 
                JOIN produit p ON lf.id_produit = p.id_produit 
                WHERE lf.id_facture = :id_facture";
$stmtLignes = $db->prepare($queryLignes);
$stmtLignes->bindParam(':id_facture', $factureId, PDO::PARAM_INT);
$stmtLignes->execute();
$lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

// Récupération des informations sur les utilisateurs (création et validation)
$queryUserCreation = "SELECT nomUser FROM t_users WHERE idUser = :id";
$stmtUserCreation = $db->prepare($queryUserCreation);
$stmtUserCreation->bindParam(':id', $facture['id_user_creation'], PDO::PARAM_INT);
$stmtUserCreation->execute();
$userCreation = $stmtUserCreation->fetch(PDO::FETCH_ASSOC);

$userValidation = null;
if ($facture['id_user_validation']) {
    $queryUserValidation = "SELECT nomUser FROM t_users WHERE idUser = :id";
    $stmtUserValidation = $db->prepare($queryUserValidation);
    $stmtUserValidation->bindParam(':id', $facture['id_user_validation'], PDO::PARAM_INT);
    $stmtUserValidation->execute();
    $userValidation = $stmtUserValidation->fetch(PDO::FETCH_ASSOC);
}

// Récupération des paiements liés à cette facture
$queryPaiements = "SELECT p.*, mp.libelle_mode 
                  FROM paiement_fournisseur p 
                  JOIN mode_paiement mp ON p.id_mode_paiement = mp.id_mode_paiement 
                  WHERE p.id_facture = :id_facture 
                  ORDER BY p.date_paiement DESC";
$stmtPaiements = $db->prepare($queryPaiements);
$stmtPaiements->bindParam(':id_facture', $factureId, PDO::PARAM_INT);
$stmtPaiements->execute();
$paiements = $stmtPaiements->fetchAll(PDO::FETCH_ASSOC);

// Récupération des informations de la réception si liée
$reception = null;
if ($facture['id_reception']) {
    $queryReception = "SELECT id_reception, numero_reception, date_reception FROM reception_fournisseur WHERE id_reception = :id";
    $stmtReception = $db->prepare($queryReception);
    $stmtReception->bindParam(':id', $facture['id_reception'], PDO::PARAM_INT);
    $stmtReception->execute();
    $reception = $stmtReception->fetch(PDO::FETCH_ASSOC);
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>DÉTAILS DE LA FACTURE FOURNISSEUR</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Achats</li>
                <li class="breadcrumb-item"><a href="achats/factures/factures.list">Factures</a></li>
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
                                Facture N° <?= htmlspecialchars($facture['numero_facture']) ?>
                                <?php
                                switch ($facture['etat']) {
                                    case 'En cours':
                                        echo '<span class="badge bg-warning ms-2">En cours</span>';
                                        break;
                                    case 'Validé':
                                        echo '<span class="badge bg-success ms-2">Validé</span>';
                                        break;
                                    case 'Payé partiellement':
                                        echo '<span class="badge bg-info ms-2">Payé partiellement</span>';
                                        break;
                                    case 'Payé':
                                        echo '<span class="badge bg-primary ms-2">Payé</span>';
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
                                    <?php if ($facture['etat'] == 'En cours'): ?>
                                        <li><a class="dropdown-item" href="achats/factures/factures.edit&id=<?= $factureId ?>"><i class="bi bi-pencil me-2"></i>Modifier</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="validateFacture(<?= $factureId ?>)"><i class="bi bi-check-circle me-2"></i>Valider</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="cancelFacture(<?= $factureId ?>)"><i class="bi bi-x-circle me-2"></i>Annuler</a></li>
                                    <?php endif; ?>
                                    <?php if ($facture['etat'] == 'Validé' || $facture['etat'] == 'Payé partiellement'): ?>
                                        <li><a class="dropdown-item" href="achats/paiements/paiements.add&facture=<?= $factureId ?>"><i class="bi bi-cash-coin me-2"></i>Enregistrer un paiement</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="controller/facture_pdf.php?id=<?= $factureId ?>" target="_blank"><i class="bi bi-printer me-2"></i>Imprimer</a></li>
                                </ul>
                            </div>
                        </div>

                        <!-- Informations de la facture -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Informations générales</h5>
                                        <table class="table table-bordered">
                                            <tr>
                                                <th width="40%">Numéro</th>
                                                <td><?= htmlspecialchars($facture['numero_facture']) ?></td>
                                            </tr>
                                            <?php if ($facture['reference_fournisseur']): ?>
                                            <tr>
                                                <th>Référence fournisseur</th>
                                                <td><?= htmlspecialchars($facture['reference_fournisseur']) ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <th>Date</th>
                                                <td><?= date('d/m/Y', strtotime($facture['date_facture'])) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Date d'échéance</th>
                                                <td><?= date('d/m/Y', strtotime($facture['date_echeance'])) ?></td>
                                            </tr>
                                            <?php if ($reception): ?>
                                            <tr>
                                                <th>Réception liée</th>
                                                <td>
                                                    <a href="achats/receptions/receptions.view&id=<?= $reception['id_reception'] ?>">
                                                        <?= htmlspecialchars($reception['numero_reception']) ?> 
                                                        (<?= date('d/m/Y', strtotime($reception['date_reception'])) ?>)
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <th>État</th>
                                                <td>
                                                    <?php
                                                    switch ($facture['etat']) {
                                                        case 'En cours':
                                                            echo '<span class="badge bg-warning">En cours</span>';
                                                            break;
                                                        case 'Validé':
                                                            echo '<span class="badge bg-success">Validé</span>';
                                                            break;
                                                        case 'Payé partiellement':
                                                            echo '<span class="badge bg-info">Payé partiellement</span>';
                                                            break;
                                                        case 'Payé':
                                                            echo '<span class="badge bg-primary">Payé</span>';
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
                                                <td><?= nl2br(htmlspecialchars($facture['observation'] ?? 'N/A')) ?></td>
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
                                                <td><?= htmlspecialchars($facture['code_fournisseur']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Nom</th>
                                                <td><?= htmlspecialchars($facture['nom_fournisseur']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Téléphone</th>
                                                <td><?= htmlspecialchars($facture['telephone'] ?? 'N/A') ?></td>
                                            </tr>
                                            <tr>
                                                <th>Email</th>
                                                <td><?= htmlspecialchars($facture['email'] ?? 'N/A') ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Détails des produits -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title">Produits facturés</h5>
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
                                                        <td class="text-end"><?= number_format($ligne['remise'], 2, ',', ' ') ?>%</td>
                                                        <td class="text-end"><?= number_format($ligne['montant_ht'], 2, ',', ' ') ?> USD</td>
                                                        <td class="text-end"><?= number_format($ligne['taux_tva'], 2, ',', ' ') ?>%</td>
                                                        <td class="text-end"><?= number_format($ligne['montant_ttc'], 2, ',', ' ') ?> USD</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-secondary">
                                                <th colspan="6" class="text-end">Total HT:</th>
                                                <td class="text-end"><?= number_format($facture['montant_ht'], 2, ',', ' ') ?> USD</td>
                                                <td></td>
                                                <td></td>
                                            </tr>
                                            <tr class="table-secondary">
                                                <th colspan="6" class="text-end">TVA (<?= number_format($facture['taux_tva'], 2, ',', ' ') ?>%):</th>
                                                <td></td>
                                                <td class="text-end"><?= number_format($facture['montant_tva'], 2, ',', ' ') ?> USD</td>
                                                <td></td>
                                            </tr>
                                            <tr class="table-secondary">
                                                <th colspan="6" class="text-end">Total TTC:</th>
                                                <td></td>
                                                <td></td>
                                                <td class="text-end"><?= number_format($facture['montant_ttc'], 2, ',', ' ') ?> USD</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Paiements -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title">Paiements</h5>
                                    <?php if (($facture['etat'] == 'Validé' || $facture['etat'] == 'Payé partiellement') && $facture['solde'] > 0): ?>
                                        <a href="achats/paiements/paiements.add&facture=<?= $factureId ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-plus-circle"></i> Ajouter un paiement
                                        </a>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (empty($paiements)): ?>
                                    <div class="alert alert-info">
                                        Aucun paiement enregistré pour cette facture.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped">
                                            <thead>
                                                <tr>
                                                    <th>N° Paiement</th>
                                                    <th>Date</th>
                                                    <th>Mode de paiement</th>
                                                    <th>Référence</th>
                                                    <th>Montant</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($paiements as $paiement): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($paiement['numero_paiement']) ?></td>
                                                        <td><?= date('d/m/Y', strtotime($paiement['date_paiement'])) ?></td>
                                                        <td><?= htmlspecialchars($paiement['libelle_mode']) ?></td>
                                                        <td><?= htmlspecialchars($paiement['reference_paiement'] ?? 'N/A') ?></td>
                                                        <td class="text-end"><?= number_format($paiement['montant'], 2, ',', ' ') ?> USD</td>
                                                        <td>
                                                            <a href="achats/paiements/paiements.view&id=<?= $paiement['id_paiement'] ?>" class="btn btn-sm btn-info">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr class="table-secondary">
                                                    <th colspan="4" class="text-end">Total payé:</th>
                                                    <td class="text-end"><?= number_format($facture['montant_paye'], 2, ',', ' ') ?> USD</td>
                                                    <td></td>
                                                </tr>
                                                <tr class="table-secondary">
                                                    <th colspan="4" class="text-end">Solde à payer:</th>
                                                    <td class="text-end"><?= number_format($facture['solde'], 2, ',', ' ') ?> USD</td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Informations de traçabilité -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title">Informations de traçabilité</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Créé par:</strong> <?= htmlspecialchars($userCreation['nomUser'] ?? 'N/A') ?></p>
                                        <p><strong>Date de création:</strong> <?= date('d/m/Y H:i', strtotime($facture['date_creation'])) ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($facture['id_user_validation']): ?>
                                            <p><strong>Validé par:</strong> <?= htmlspecialchars($userValidation['nomUser'] ?? 'N/A') ?></p>
                                            <p><strong>Date de validation:</strong> <?= date('d/m/Y H:i', strtotime($facture['date_validation'])) ?></p>
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
    function validateFacture(id) {
        Swal.fire({
            title: 'Confirmer la validation',
            text: "Voulez-vous vraiment valider cette facture?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, valider',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/validate_facture_fournisseur.php?id=' + id + '&action=validate';
            }
        });
    }

    function cancelFacture(id) {
        Swal.fire({
            title: 'Confirmer l\'annulation',
            text: "Voulez-vous vraiment annuler cette facture?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, annuler',
            cancelButtonText: 'Non'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/validate_facture_fournisseur.php?id=' + id + '&action=cancel';
            }
        });
    }
</script>

<?php include "./views/include/footer.php"; ?>
