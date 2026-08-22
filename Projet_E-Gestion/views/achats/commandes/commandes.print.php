<?php
$db = Connexion::getInstance()->getPDO();

// Récupération de l'ID de la commande
$commandeId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($commandeId <= 0) {
    echo "<script>
        alert('Commande non trouvée');
        window.close();
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
        alert('Commande non trouvée');
        window.close();
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

// Récupération des informations de l'entreprise
$queryEntreprise = "SELECT * FROM configuration_universite";
$stmtEntreprise = $db->prepare($queryEntreprise);
$stmtEntreprise->execute();
$entreprise = $stmtEntreprise->fetch(PDO::FETCH_ASSOC);
?>

<div class="container mt-4 mb-4">
    <div class="row">
        <div class="col-12 text-center mb-4">
            <h2>BON DE COMMANDE</h2>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Notre entreprise</h5>
                    <p>
                        <strong><?= htmlspecialchars($entreprise['nom'] ?? 'Nom de l\'entreprise') ?></strong><br>
                        <?= htmlspecialchars($entreprise['adresse'] ?? 'Adresse') ?><br>
                        Tél: <?= htmlspecialchars($entreprise['telephone'] ?? 'Téléphone') ?><br>
                        Email: <?= htmlspecialchars($entreprise['email'] ?? 'Email') ?><br>
                        NIF: <?= htmlspecialchars($entreprise['nif'] ?? 'NIF') ?><br>
                        RCCM: <?= htmlspecialchars($entreprise['rccm'] ?? 'RCCM') ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Fournisseur</h5>
                    <p>
                        <strong><?= htmlspecialchars($commande['nom_fournisseur']) ?></strong><br>
                        <?= htmlspecialchars($commande['adresse'] ?? 'Adresse non spécifiée') ?><br>
                        Tél: <?= htmlspecialchars($commande['telephone'] ?? 'Non spécifié') ?><br>
                        Email: <?= htmlspecialchars($commande['email'] ?? 'Non spécifié') ?><br>
                        NIF: <?= htmlspecialchars($commande['nif'] ?? 'Non spécifié') ?><br>
                        RCCM: <?= htmlspecialchars($commande['rccm'] ?? 'Non spécifié') ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Informations de la commande</h5>
                    <div class="row">
                        <div class="col-6">
                            <p><strong>N° Commande:</strong> <?= htmlspecialchars($commande['numero_commande']) ?></p>
                            <p><strong>Date:</strong> <?= date('d/m/Y', strtotime($commande['date_commande'])) ?></p>
                        </div>
                        <div class="col-6">
                            <p><strong>Date livraison prévue:</strong> <?= $commande['date_livraison_prevue'] ? date('d/m/Y', strtotime($commande['date_livraison_prevue'])) : 'Non spécifiée' ?></p>
                            <p><strong>État:</strong> <?= htmlspecialchars($commande['etat']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Produits commandés</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Code</th>
                                    <th>Produit</th>
                                    <th>Désignation</th>
                                    <th>Quantité</th>
                                    <th>Prix unitaire</th>
                                    <th>Remise</th>
                                    <th>Montant HT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($lignes)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Aucun produit trouvé</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $i = 1; foreach ($lignes as $ligne): ?>
                                        <tr>
                                            <td><?= $i++ ?></td>
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
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="7" class="text-end">Total HT:</th>
                                    <td class="text-end"><?= number_format($commande['montant_ht'], 2, ',', ' ') ?> USD</td>
                                </tr>
                                <tr>
                                    <th colspan="7" class="text-end">TVA (<?= number_format($commande['taux_tva'], 2, ',', ' ') ?>%):</th>
                                    <td class="text-end"><?= number_format($commande['montant_tva'], 2, ',', ' ') ?> USD</td>
                                </tr>
                                <tr>
                                    <th colspan="7" class="text-end">Total TTC:</th>
                                    <td class="text-end"><?= number_format($commande['montant_ttc'], 2, ',', ' ') ?> USD</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($commande['observation'])): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Observations</h5>
                    <p><?= nl2br(htmlspecialchars($commande['observation'])) ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Signatures</h5>
                    <div class="row">
                        <div class="col-4 text-center">
                            <p><strong>Préparé par</strong></p>
                            <p><?= htmlspecialchars($userCreation['nomUser'] ?? 'N/A') ?></p>
                            <p><?= date('d/m/Y', strtotime($commande['date_creation'])) ?></p>
                            <div style="height: 50px; border-bottom: 1px solid #000;"></div>
                        </div>
                        <div class="col-4 text-center">
                            <p><strong>Approuvé par</strong></p>
                            <?php if ($commande['id_user_validation']): ?>
                                <p><?= htmlspecialchars($userValidation['nomUser'] ?? 'N/A') ?></p>
                                <p><?= date('d/m/Y', strtotime($commande['date_validation'])) ?></p>
                                <div style="height: 50px; border-bottom: 1px solid #000;"></div>
                            <?php else: ?>
                                <p>Non validé</p>
                                <div style="height: 50px; border-bottom: 1px dashed #000;"></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-4 text-center">
                            <p><strong>Fournisseur</strong></p>
                            <p>Cachet et signature</p>
                            <div style="height: 50px; border-bottom: 1px dashed #000;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12 text-center">
            <p class="small">Document généré le <?= date('d/m/Y à H:i:s') ?></p>
        </div>
    </div>
</div>

<script>
    window.onload = function() {
        window.print();
    }
</script>

</body>
</html>
