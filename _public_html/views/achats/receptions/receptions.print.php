<?php
include "./views/include/header_print.php";
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
            window.close();
        });
    </script>";
    exit;
}

// Récupération des détails de la réception
$queryReception = "SELECT rf.*, f.nom_fournisseur, f.code_fournisseur, f.adresse as adresse_fournisseur, 
                  f.telephone as telephone_fournisseur, f.email as email_fournisseur,
                  cf.numero_commande, cf.date_commande,
                  d.libelle_depot, u.\"nomUser\" as user_creation
                  FROM reception_fournisseur rf 
                  JOIN fournisseur f ON rf.id_fournisseur = f.id_fournisseur 
                  JOIN commande_fournisseur cf ON rf.id_commande = cf.id_commande
                  JOIN entree_stock es ON rf.id_entree_stock = es.id_entree
                  JOIN depot d ON es.id_depot = d.id_depot
                  JOIN t_users u ON rf.id_user_creation = u.\"idUser\"
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
            window.close();
        });
    </script>";
    exit;
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

// Récupération des informations de l'institution
$queryConfig = "SELECT * FROM configuration WHERE id = 1";
$stmtConfig = $db->prepare($queryConfig);
$stmtConfig->execute();
$configInstitution = $stmtConfig->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bon de Réception - <?= htmlspecialchars($reception['numero_reception']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .logo {
            max-width: 150px;
            max-height: 80px;
        }
        .company-info {
            text-align: right;
        }
        .document-title {
            text-align: center;
            margin: 20px 0;
            font-size: 18px;
            font-weight: bold;
            color: #28a745;
        }
        .document-number {
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: bold;
        }
        .info-section {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .info-item {
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
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
        .text-center {
            text-align: center;
        }
        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 40px;
        }
        .signature-box {
            text-align: center;
        }
        .signature-line {
            margin-top: 50px;
            border-top: 1px solid #000;
            display: inline-block;
            width: 80%;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #6c757d;
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
    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button onclick="window.print();" style="padding: 5px 10px;">Imprimer</button>
        <button onclick="window.close();" style="padding: 5px 10px; margin-left: 10px;">Fermer</button>
    </div>

    <div class="header">
        <div>
            <?php if (!empty($configInstitution['logo'])): ?>
                <img src="assets/img/<?= $configInstitution['logo'] ?>" alt="Logo" class="logo">
            <?php endif; ?>
        </div>
        <div class="company-info">
            <h2><?= htmlspecialchars($configInstitution['nom_institution'] ?? 'ENTREPRISE') ?></h2>
            <p><?= htmlspecialchars($configInstitution['adresse'] ?? '') ?></p>
            <p>Tél: <?= htmlspecialchars($configInstitution['telephone'] ?? '') ?> | Email: <?= htmlspecialchars($configInstitution['email'] ?? '') ?></p>
            <p>NIF: <?= htmlspecialchars($configInstitution['nif'] ?? '') ?> | RCCM: <?= htmlspecialchars($configInstitution['rccm'] ?? '') ?></p>
        </div>
    </div>

    <div class="document-title">BON DE RÉCEPTION</div>
    <div class="document-number">N° <?= htmlspecialchars($reception['numero_reception']) ?> du <?= date('d/m/Y', strtotime($reception['date_reception'])) ?></div>

    <div class="info-section">
        <h3>INFORMATIONS GÉNÉRALES</h3>
        <div class="info-grid">
            <div>
                <div class="info-item">
                    <span class="info-label">Commande N°:</span> <?= htmlspecialchars($reception['numero_commande']) ?>
                </div>
                <div class="info-item">
                    <span class="info-label">Date commande:</span> <?= date('d/m/Y', strtotime($reception['date_commande'])) ?>
                </div>
                <div class="info-item">
                    <span class="info-label">Référence BL:</span> <?= htmlspecialchars($reception['reference_bl']) ?>
                </div>
                <div class="info-item">
                    <span class="info-label">Dépôt:</span> <?= htmlspecialchars($reception['libelle_depot']) ?>
                </div>
            </div>
            <div>
                <div class="info-item">
                    <span class="info-label">Fournisseur:</span> <?= htmlspecialchars($reception['code_fournisseur'] . ' - ' . $reception['nom_fournisseur']) ?>
                </div>
                <div class="info-item">
                    <span class="info-label">Adresse:</span> <?= htmlspecialchars($reception['adresse_fournisseur'] ?? 'N/A') ?>
                </div>
                <div class="info-item">
                    <span class="info-label">Téléphone:</span> <?= htmlspecialchars($reception['telephone_fournisseur'] ?? 'N/A') ?>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span> <?= htmlspecialchars($reception['email_fournisseur'] ?? 'N/A') ?>
                </div>
            </div>
        </div>
    </div>

    <h3>DÉTAILS DES PRODUITS REÇUS</h3>
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Produit</th>
                <th>Quantité</th>
                <th>Prix unitaire</th>
                <th>Montant</th>
                <th>N° Lot</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total = 0;
            foreach ($lignes as $ligne): 
                $total += $ligne['montant_total'];
            ?>
                <tr>
                    <td><?= htmlspecialchars($ligne['code_produit']) ?></td>
                    <td><?= htmlspecialchars($ligne['libelle_produit']) ?></td>
                    <td class="text-right"><?= number_format($ligne['quantite'], 2, ',', ' ') ?></td>
                    <td class="text-right"><?= number_format($ligne['prix_unitaire'], 2, ',', ' ') ?> USD</td>
                    <td class="text-right"><?= number_format($ligne['montant_total'], 2, ',', ' ') ?> USD</td>
                    <td class="text-center"><?= htmlspecialchars($ligne['numero_lot']) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="4" class="text-right">TOTAL</td>
                <td class="text-right"><?= number_format($total, 2, ',', ' ') ?> USD</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <?php if (!empty($reception['observation'])): ?>
        <div>
            <strong>Observation:</strong>
            <p><?= nl2br(htmlspecialchars($reception['observation'])) ?></p>
        </div>
    <?php endif; ?>

    <div class="signatures">
        <div class="signature-box">
            <p><strong>Réceptionné par:</strong></p>
            <div class="signature-line"></div>
            <p><?= htmlspecialchars($reception['user_creation']) ?></p>
        </div>
        <div class="signature-box">
            <p><strong>Pour le fournisseur:</strong></p>
            <div class="signature-line"></div>
            <p></p>
        </div>
    </div>

    <div class="footer">
        <p>Document généré le <?= date('d/m/Y à H:i') ?></p>
    </div>
</body>
</html>
