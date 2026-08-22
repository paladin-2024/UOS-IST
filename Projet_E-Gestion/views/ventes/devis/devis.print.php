<?php
error_reporting(E_ALL); ini_set("display_errors", 1);
require_once dirname(__DIR__, 3) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Récupération de l'ID du devis
$devisId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($devisId <= 0) {
    echo "<script>
        alert('Devis non trouvé');
        window.close();
    </script>";
    exit;
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

// Récupération des détails du devis
$query = "SELECT d.*, c.nom_client, c.code_client, c.telephone, c.email, c.adresse, c.nif, c.rccm 
          FROM devis d 
          JOIN client c ON d.id_client = c.id_client 
          WHERE d.id_devis = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $devisId, PDO::PARAM_INT);
$stmt->execute();
$devis = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$devis) {
    echo "<script>
        alert('Devis non trouvé');
        window.close();
    </script>";
    exit;
}

// Récupération des lignes du devis
$queryLignes = "SELECT ld.*, p.code_produit, p.libelle_produit 
                FROM ligne_devis ld 
                JOIN produit p ON ld.id_produit = p.id_produit 
                WHERE ld.id_devis = :id_devis";
$stmtLignes = $db->prepare($queryLignes);
$stmtLignes->bindParam(':id_devis', $devisId, PDO::PARAM_INT);
$stmtLignes->execute();
$lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

// Calcul de la date de validité
$dateValidite = date('d/m/Y', strtotime($devis['date_devis'] . ' + ' . $devis['validite'] . ' days'));

// Récupération des informations de l'entreprise
$queryEntreprise = "SELECT * FROM configuration_universite";
$stmtEntreprise = $db->prepare($queryEntreprise);
$stmtEntreprise->execute();
$entreprise = $stmtEntreprise->fetch(PDO::FETCH_ASSOC);

// Si les informations de l'entreprise n'existent pas, utiliser des valeurs par défaut
if (!$entreprise) {
    $entreprise = [
        'nom_entreprise' => 'Votre Entreprise',
        'adresse' => 'Adresse de l\'entreprise',
        'telephone' => 'Téléphone',
        'email' => 'email@entreprise.com',
        'site_web' => 'www.entreprise.com',
        'nif' => 'NIF',
        'rccm' => 'RCCM',
        'logo' => 'assets/img/logo.png'
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devis <?= htmlspecialchars($devis['numero_devis']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 14px;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
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
            margin: 20px 0;
            font-size: 22px;
            font-weight: bold;
            color: #2c3e50;
        }
        .document-info {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .client-info {
            margin-bottom: 20px;
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
        .total-section {
            margin-top: 20px;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #777;
        }
        .signature {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 45%;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            text-align: center;
        }
        .conditions {
            margin-top: 30px;
            font-size: 12px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <img src="../../<?= $entreprise['logo'] ?? 'assets/img/logo.png' ?>" alt="Logo" class="logo">
            </div>
            <div class="company-info">
                <h3><?= htmlspecialchars($entreprise['nom'] ?? 'Votre Entreprise') ?></h3>
                <p><?= nl2br(htmlspecialchars($entreprise['adresse'] ?? 'Adresse de l\'entreprise')) ?></p>
                <p>Tél: <?= htmlspecialchars($entreprise['telephone'] ?? 'Téléphone') ?></p>
                <p>Email: <?= htmlspecialchars($entreprise['email'] ?? 'email@entreprise.com') ?></p>
                <p>NIF: <?= htmlspecialchars($entreprise['nif'] ?? 'NIF') ?> | RCCM: <?= htmlspecialchars($entreprise['rccm'] ?? 'RCCM') ?></p>
            </div>
        </div>

        <div class="document-title">
            DEVIS N° <?= htmlspecialchars($devis['numero_devis']) ?>
        </div>

        <div class="document-info">
            <table>
                <tr>
                    <td><strong>Date:</strong> <?= date('d/m/Y', strtotime($devis['date_devis'])) ?></td>
                    <td><strong>Validité:</strong> <?= $dateValidite ?> (<?= $devis['validite'] ?> jours)</td>
                </tr>
            </table>
        </div>

        <div class="client-info">
            <h3>Client</h3>
            <table>
                <tr>
                    <td width="50%"><strong>Nom:</strong> <?= htmlspecialchars($devis['nom_client']) ?></td>
                    <td width="50%"><strong>Code:</strong> <?= htmlspecialchars($devis['code_client']) ?></td>
                </tr>
                <tr>
                    <td><strong>Adresse:</strong> <?= nl2br(htmlspecialchars($devis['adresse'] ?? 'N/A')) ?></td>
                    <td><strong>Téléphone:</strong> <?= htmlspecialchars($devis['telephone'] ?? 'N/A') ?></td>
                </tr>
                <tr>
                    <td><strong>Email:</strong> <?= htmlspecialchars($devis['email'] ?? 'N/A') ?></td>
                    <td>
                        <?php if (!empty($devis['nif']) || !empty($devis['rccm'])): ?>
                            <strong>NIF:</strong> <?= htmlspecialchars($devis['nif'] ?? 'N/A') ?> | 
                            <strong>RCCM:</strong> <?= htmlspecialchars($devis['rccm'] ?? 'N/A') ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <h3>Détails des produits</h3>
        <table>
            <thead>
                <tr>
                <th>Code</th>
                    <th>Désignation</th>
                    <th>Quantité</th>
                    <th>Prix unitaire</th>
                    <th>Remise</th>
                    <th>Montant HT</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lignes as $ligne): ?>
                    <tr>
                        <td><?= htmlspecialchars($ligne['code_produit']) ?></td>
                        <td><?= htmlspecialchars($ligne['designation']) ?></td>
                        <td class="text-right"><?= number_format($ligne['quantite'], 2, ',', ' ') ?></td>
                        <td class="text-right"><?= number_format($ligne['prix_unitaire'], 2, ',', ' ') ?> USD</td>
                        <td class="text-right">
                            <?php if ($ligne['remise'] > 0): ?>
                                <?= number_format($ligne['remise'], 2, ',', ' ') ?>% 
                                (<?= number_format($ligne['montant_remise'], 2, ',', ' ') ?> USD)
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?= number_format($ligne['montant_ht'], 2, ',', ' ') ?> USD</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right"><strong>Total HT:</strong></td>
                    <td class="text-right"><?= number_format($devis['montant_ht'], 2, ',', ' ') ?> USD</td>
                </tr>
                <tr>
                    <td colspan="5" class="text-right"><strong>TVA (<?= number_format($devis['taux_tva'], 2, ',', ' ') ?>%):</strong></td>
                    <td class="text-right"><?= number_format($devis['montant_tva'], 2, ',', ' ') ?> USD</td>
                </tr>
                <tr>
                    <td colspan="5" class="text-right"><strong>Total TTC:</strong></td>
                    <td class="text-right"><strong><?= number_format($devis['montant_ttc'], 2, ',', ' ') ?> USD</strong></td>
                </tr>
            </tfoot>
        </table>

        <?php if (!empty($devis['observation'])): ?>
            <div class="conditions">
                <h4>Observations:</h4>
                <p><?= nl2br(htmlspecialchars($devis['observation'])) ?></p>
            </div>
        <?php endif; ?>

        <div class="conditions">
            <h4>Conditions générales:</h4>
            <p>1. Ce devis est valable jusqu'au <?= $dateValidite ?>.</p>
            <p>2. Les prix sont exprimés en USD et sont hors taxes sauf indication contraire.</p>
            <p>3. Les délais de livraison sont donnés à titre indicatif et ne constituent pas un engagement ferme.</p>
            <p>4. Toute commande implique l'acceptation de nos conditions générales de vente.</p>
        </div>

        <div class="signature">
            <div class="signature-box">
                <p>Pour <?= htmlspecialchars($entreprise['nom_entreprise'] ?? 'l\'entreprise') ?></p>
                <p>Nom et signature</p>
            </div>
            <div class="signature-box">
                <p>Pour le client</p>
                <p>Nom et signature</p>
            </div>
        </div>

        <div class="footer">
            <p><?= htmlspecialchars($entreprise['sigle'] ?? 'Votre Entreprise') ?> - <?= htmlspecialchars($entreprise['adresse'] ?? 'Adresse') ?></p>
            <p>Tél: <?= htmlspecialchars($entreprise['telephone'] ?? 'Téléphone') ?> - Email: <?= htmlspecialchars($entreprise['email'] ?? 'Email') ?> - Web: <?= htmlspecialchars($entreprise['site_web'] ?? 'Site web') ?></p>
        </div>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print();" style="padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Imprimer
        </button>
        <button onclick="window.close();" style="padding: 10px 20px; background-color: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
            Fermer
        </button>
    </div>
</body>
</html>

