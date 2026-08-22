<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération de l'ID de la réception
$receptionId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($receptionId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Réception non trouvée'
        }).then(() => {
            window.location.href = 'achats/receptions/receptions.list';
        });
    </script>";
    exit;
}

// Récupération des détails de la réception
$queryReception = "SELECT rf.*, f.nom_fournisseur, f.code_fournisseur, 
                  d.libelle_depot, u.\"nomUser\" as user_creation
                  FROM reception_fournisseur rf 
                  JOIN fournisseur f ON rf.id_fournisseur = f.id_fournisseur 
                  JOIN depot d ON rf.id_depot = d.id_depot
                  JOIN t_users u ON rf.id_user_creation = u.\"idUser\"
                  LEFT JOIN entree_stock es ON rf.id_entree_stock = es.id_entree
                  WHERE rf.id_reception = :id";
$stmtReception = $db->prepare($queryReception);
$stmtReception->bindParam(':id', $receptionId, PDO::PARAM_INT);
$stmtReception->execute();
$reception = $stmtReception->fetch(PDO::FETCH_ASSOC);

if (!$reception) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Réception non trouvée'
        }).then(() => {
            window.location.href = 'achats/receptions/receptions.list';
        });
    </script>";
    exit;
}

// Vérifier si la réception est liée à une commande
$hasCommande = !empty($reception['id_commande']);
$commande = null;

if ($hasCommande) {
    // Récupérer les détails de la commande associée
    $queryCommande = "SELECT * FROM commande_fournisseur WHERE id_commande = :id_commande";
    $stmtCommande = $db->prepare($queryCommande);
    $stmtCommande->bindParam(':id_commande', $reception['id_commande'], PDO::PARAM_INT);
    $stmtCommande->execute();
    $commande = $stmtCommande->fetch(PDO::FETCH_ASSOC);
}

// Récupération des lignes de la réception
$queryLignes = "SELECT lr.*, p.code_produit, p.libelle_produit 
                FROM ligne_reception_fournisseur lr 
                JOIN produit p ON lr.id_produit = p.id_produit 
                WHERE lr.id_reception = :id_reception
                ORDER BY p.libelle_produit";
$stmtLignes = $db->prepare($queryLignes);
$stmtLignes->bindParam(':id_reception', $receptionId, PDO::PARAM_INT);
$stmtLignes->execute();
$lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>DÉTAILS DE LA RÉCEPTION</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Achats</li>
                <li class="breadcrumb-item"><a href="achats/receptions/receptions.list">Réceptions</a></li>
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
                                Réception N° <?= htmlspecialchars($reception['numero_reception']) ?>
                                <span class="badge bg-success ms-2">
                                    <?= $reception['etat'] ?>
                                </span>
                                <?php if (!$hasCommande): ?>
                                    <span class="badge bg-info ms-2">Réception directe</span>
                                <?php endif; ?>
                            </h5>
                            
                            <!-- Dans la section des boutons d'action, après la ligne 65 environ -->
                            <div class="btn-group">
                                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    Actions
                                </button>
                                <!-- Dans la section des boutons d'action, modifiez le dropdown menu -->
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php if ($reception['etat'] == 'En cours'): ?>
                                        <li><a class="dropdown-item" href="achats/receptions/receptions.edit&id=<?= $receptionId ?>"><i class="bi bi-pencil me-2"></i>Modifier</a></li>
                                        <li><a class="dropdown-item" onclick="validateReception(<?= $receptionId ?>)"><i class="bi bi-check-circle me-2"></i>Valider</a></li>
                                        <li><a class="dropdown-item" onclick="cancelReception(<?= $receptionId ?>)"><i class="bi bi-x-circle me-2"></i>Annuler</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                    <?php endif; ?>
                                    <?php if ($reception['etat'] == 'Validé'): ?>
                                        <li><a class="dropdown-item" onclick="createInvoice(<?= $receptionId ?>)"><i class="bi bi-file-earmark-text me-2"></i>Créer une facture</a></li>
                                    <?php endif; ?>
                                    <li><a class="dropdown-item" onclick="printReception(<?= $receptionId ?>)"><i class="bi bi-printer me-2"></i>Imprimer</a></li>
                                    <li><a class="dropdown-item" onclick="generatePDF(<?= $receptionId ?>)"><i class="bi bi-file-pdf me-2"></i>Générer PDF</a></li>
                                    <?php if ($hasCommande): ?>
                                    <li><a class="dropdown-item" href="achats/commandes/commandes.view&id=<?= $reception['id_commande'] ?>"><i class="bi bi-eye me-2"></i>Voir la commande</a></li>
                                    <?php endif; ?>
                                </ul>


                            </div>

                        </div>

                        <!-- Informations de la réception -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Informations générales</h5>
                                        <table class="table table-bordered">
                                            <tr>
                                                <th width="40%">Numéro réception</th>
                                                <td><?= htmlspecialchars($reception['numero_reception']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Date réception</th>
                                                <td><?= date('d/m/Y', strtotime($reception['date_reception'])) ?></td>
                                            </tr>
                                            <?php if ($hasCommande): ?>
                                            <tr>
                                                <th>Commande</th>
                                                <td><?= htmlspecialchars($commande['numero_commande']) ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <th>Référence BL</th>
                                                <td><?= htmlspecialchars($reception['reference_bl'] ?? 'N/A') ?></td>
                                            </tr>
                                            <tr>
                                                <th>Dépôt</th>
                                                <td><?= htmlspecialchars($reception['libelle_depot']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Observation</th>
                                                <td><?= nl2br(htmlspecialchars($reception['observation'] ?? 'N/A')) ?></td>
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
                                                <td><?= htmlspecialchars($reception['code_fournisseur']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Nom</th>
                                                <td><?= htmlspecialchars($reception['nom_fournisseur']) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <div class="card mt-3">
                                    <div class="card-body">
                                        <h5 class="card-title">Traçabilité</h5>
                                        <table class="table table-bordered">
                                            <tr>
                                                <th width="40%">Créé par</th>
                                                <td><?= htmlspecialchars($reception['user_creation']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Date création</th>
                                                <td><?= date('d/m/Y H:i', strtotime($reception['date_creation'])) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Détails des produits -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title">Produits reçus</h5>
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
                                                <th>N° Lot</th>
                                                <th>Date péremption</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($lignes)): ?>
                                                <tr>
                                                    <td colspan="8" class="text-center">Aucun produit trouvé</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($lignes as $ligne): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($ligne['code_produit']) ?></td>
                                                        <td><?= htmlspecialchars($ligne['libelle_produit']) ?></td>
                                                        <td><?= htmlspecialchars($ligne['designation']) ?></td>
                                                        <td class="text-end"><?= number_format($ligne['quantite'], 2, ',', ' ') ?></td>
                                                        <td class="text-end"><?= number_format($ligne['prix_unitaire'], 2, ',', ' ') ?> USD</td>
                                                        <td class="text-end"><?= number_format($ligne['montant_total'], 2, ',', ' ') ?> USD</td>
                                                        <td><?= htmlspecialchars($ligne['numero_lot']) ?></td>
                                                        <td><?= $ligne['date_peremption'] ? date('d/m/Y', strtotime($ligne['date_peremption'])) : 'N/A' ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr class="table-secondary">
                                                    <td colspan="5" class="text-end fw-bold">Total:</td>
                                                    <td class="text-end fw-bold">
                                                        <?php
                                                        $total = array_reduce($lignes, function($carry, $item) {
                                                            return $carry + $item['montant_total'];
                                                        }, 0);
                                                        echo number_format($total, 2, ',', ' ') . ' USD';
                                                        ?>
                                                    </td>
                                                    <td colspan="2"></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
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
    function printReception(id) {
        window.open('achats/receptions/receptions.print&id=' + id, '_blank');
    }
    
    function generatePDF(id) {
        window.open('controller/reception_pdf.php?id=' + id, '_blank');
    }
    
    function validateReception(id) {
        Swal.fire({
            title: 'Confirmer la validation',
            text: "Voulez-vous vraiment valider cette réception? Cette action créera les mouvements de stock correspondants.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, valider',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/validate_reception.php?id=' + id + '&action=validate';
            }
        });
    }
    
    function cancelReception(id) {
        Swal.fire({
            title: 'Confirmer l\'annulation',
            text: "Voulez-vous vraiment annuler cette réception?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, annuler',
            cancelButtonText: 'Non'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/validate_reception.php?id=' + id + '&action=cancel';
            }
        });
    }

    function createInvoice(id) {
        Swal.fire({
            title: 'Créer une facture',
            text: "Voulez-vous créer une facture pour cette réception?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, créer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'achats/factures/factures.add&reception=' + id;
            }
        });
    }
</script>



<?php include "./views/include/footer.php"; ?>
