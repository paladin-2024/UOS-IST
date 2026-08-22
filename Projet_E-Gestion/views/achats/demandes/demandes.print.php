<?php
//include "./views/include/header_print.php";
$db = Connexion::getInstance()->getPDO();

// Récupération de l'ID de la demande
$demandeId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($demandeId <= 0) {
    echo "<script>
        alert('Demande de prix non trouvée');
        window.close();
    </script>";
    exit;
}

// Récupération des détails de la demande
$query = "SELECT dp.*, f.nom_fournisseur, f.code_fournisseur, f.adresse, f.telephone, f.email, f.nif, f.rccm 
          FROM demande_prix dp 
          JOIN fournisseur f ON dp.id_fournisseur = f.id_fournisseur 
          WHERE dp.id_demande_prix = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $demandeId, PDO::PARAM_INT);
$stmt->execute();
$demande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demande) {
    echo "<script>
        alert('Demande de prix non trouvée');
        window.close();
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

// Récupération des informations de l'entreprise
$queryEntreprise = "SELECT * FROM configuration_universite";
$stmtEntreprise = $db->prepare($queryEntreprise);
$stmtEntreprise->execute();
$entreprise = $stmtEntreprise->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demande de Prix <?= htmlspecialchars($demande['numero_demande']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 14px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .logo {
            max-width: 200px;
            max-height: 80px;
        }
        .company-info {
            text-align: right;
        }
        .document-title {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            margin: 20px 0;
            text-transform: uppercase;
        }
        .document-info {
            margin-bottom: 20px;
        }
        .supplier-info {
            margin-bottom: 30px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .text-right {
            text-align: right;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .signature-block {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .signature {
            width: 45%;
            border-top: 1px solid #000;
            padding-top: 10px;
            text-align: center;
        }
        @media print {
            body {
                padding: 0;
                margin: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <?php if (!empty($entreprise['logo'])): ?>
                <img src="../uploads/<?= $entreprise['logo'] ?>" alt="Logo" class="logo">
            <?php else: ?>
                <h2><?= htmlspecialchars($entreprise['nom'] ?? 'Entreprise') ?></h2>
            <?php endif; ?>
        </div>
        <div class="company-info">
            <p><strong><?= htmlspecialchars($entreprise['nom'] ?? 'Entreprise') ?></strong></p>
            <p><?= htmlspecialchars($entreprise['adresse'] ?? '') ?></p>
            <p>Tél: <?= htmlspecialchars($entreprise['telephone'] ?? '') ?></p>
            <p>Email: <?= htmlspecialchars($entreprise['email'] ?? '') ?></p>
        </div>
    </div>

    <div class="document-title">Demande de Prix</div>

    <div class="document-info">
        <p><strong>N° Demande:</strong> <?= htmlspecialchars($demande['numero_demande']) ?></p>
        <p><strong>Date:</strong> <?= date('d/m/Y', strtotime($demande['date_demande'])) ?></p>
        <p><strong>État:</strong> 
            <?php
            switch ($demande['etat']) {
                case 'En cours': echo 'En cours'; break;
                case 'Validé': echo 'Validé'; break;
                case 'Transformé': echo 'Transformé en commande'; break;
                case 'Annulé': echo 'Annulé'; break;
            }
            ?>
        </p>
    </div>

    <div class="supplier-info">
        <h3>Fournisseur</h3>
        <p><strong>Code:</strong> <?= htmlspecialchars($demande['code_fournisseur']) ?></p>
        <p><strong>Nom:</strong> <?= htmlspecialchars($demande['nom_fournisseur']) ?></p>
        <p><strong>Adresse:</strong> <?= htmlspecialchars($demande['adresse'] ?? 'N/A') ?></p>
        <p><strong>Téléphone:</strong> <?= htmlspecialchars($demande['telephone'] ?? 'N/A') ?> | <strong>Email:</strong> <?= htmlspecialchars($demande['email'] ?? 'N/A') ?></p>
        <?php if (!empty($demande['nif']) || !empty($demande['rccm'])): ?>
            <p><strong>NIF:</strong> <?= htmlspecialchars($demande['nif'] ?? 'N/A') ?> | <strong>RCCM:</strong> <?= htmlspecialchars($demande['rccm'] ?? 'N/A') ?></p>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>N°</th>
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
                    <td colspan="7" style="text-align: center;">Aucun produit trouvé</td>
                </tr>
            <?php else: ?>
                <?php foreach ($lignes as $index => $ligne): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($ligne['code_produit']) ?></td>
                        <td><?= htmlspecialchars($ligne['libelle_produit']) ?></td>
                        <td><?= htmlspecialchars($ligne['designation']) ?></td>
                        <td class="text-right"><?= number_format($ligne['quantite'], 2, ',', ' ') ?></td>
                        <td class="text-right">
                            <?= $ligne['prix_unitaire'] ? number_format($ligne['prix_unitaire'], 2, ',', ' ') . ' USD' : '' ?>
                        </td>
                        <td class="text-right">
                            <?= $ligne['montant_total'] ? number_format($ligne['montant_total'], 2, ',', ' ') . ' USD' : '' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (!empty($demande['observation'])): ?>
        <div>
            <h3>Observations</h3>
            <p><?= nl2br(htmlspecialchars($demande['observation'])) ?></p>
        </div>
    <?php endif; ?>

    <div class="signature-block">
        <div class="signature">
            <p>Demandeur</p>
        </div>
        <div class="signature">
            <p>Fournisseur</p>
        </div>
    </div>

    <div class="footer">
        <p>Document généré le <?= date('d/m/Y à H:i') ?></p>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print();" style="padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Imprimer
        </button>
        <button onclick="window.close();" style="padding: 10px 20px; background-color: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
            Fermer
        </button>
    </div>

    <script>
        // Imprimer automatiquement
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
