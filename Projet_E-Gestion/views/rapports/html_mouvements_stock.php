<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport des mouvements de stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <style>
        body {
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo {
            max-width: 100px;
            max-height: 100px;
        }
        .title {
            margin-top: 10px;
            font-weight: bold;
            font-size: 22px;
            color: #0057a2;
        }
        .subtitle {
            font-style: italic;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            border: 1px solid #ddd;
            font-size: 14px;
        }
        th {
            background-color: #f2f2f2;
            text-align: center;
            font-weight: bold;
        }
        .entree {
            color: green;
        }
        .sortie {
            color: #dc143c;
        }
        .transfert {
            color: blue;
        }
        .text-right {
            text-align: right;
        }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            text-align: center;
            color: #555;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        

        <!-- Boutons d'action -->
        <div class="mb-3 no-print">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="bi bi-printer"></i> Imprimer
            </button>
            <a href="../../stock/rapports_stock" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        </div>

        <!-- Tableau des mouvements -->
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Référence</th>
                        <th>Type</th>
                        <th>Nature</th>
                        <th>Dépôt</th>
                        <th>Code</th>
                        <th>Désignation</th>
                        <th>Lot</th>
                        <th>Péremption</th>
                        <th>Quantité</th>
                        <th>Prix Unit.</th>
                        <th>Montant</th>
                        <th>Utilisateur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalQuantite = 0;
                    $totalMontant = 0;
                    
                    if (empty($mouvements)): 
                    ?>
                        <tr>
                            <td colspan="13" class="text-center">Aucun mouvement trouvé pour cette période.</td>
                        </tr>
                    <?php else: 
                        foreach ($mouvements as $mouvement): 
                            $totalQuantite += $mouvement['quantite'];
                            $totalMontant += $mouvement['montant_total'];
                    ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($mouvement['date_mouvement'])) ?></td>
                            <td><?= htmlspecialchars($mouvement['reference']) ?></td>
                            <td class="<?= strtolower($mouvement['type_mouvement']) ?>">
                                <?= htmlspecialchars($mouvement['type_mouvement']) ?>
                            </td>
                            <td><?= htmlspecialchars($mouvement['nature']) ?></td>
                            <td><?= htmlspecialchars($mouvement['libelle_depot']) ?></td>
                            <td><?= htmlspecialchars($mouvement['code_produit']) ?></td>
                            <td><?= htmlspecialchars($mouvement['libelle_produit']) ?></td>
                            <td><?= htmlspecialchars($mouvement['numero_lot']) ?></td>
                            <td>
                                <?= $mouvement['date_peremption'] ? date('d/m/Y', strtotime($mouvement['date_peremption'])) : 'N/A' ?>
                            </td>
                            <td class="text-right">
                                <?= number_format($mouvement['quantite'], 2) ?> <?= htmlspecialchars($mouvement['symbole_unite']) ?>
                            </td>
                            <td class="text-right">
                                <?= number_format($mouvement['prix_unitaire'], 2) ?> USD
                            </td>
                            <td class="text-right">
                                <?= number_format($mouvement['montant_total'], 2) ?> USD
                            </td>
                            <td><?= htmlspecialchars($mouvement['utilisateur']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <th colspan="9" class="text-right">TOTAL</th>
                        <th class="text-right"><?= number_format($totalQuantite, 2) ?></th>
                        <th></th>
                        <th class="text-right"><?= number_format($totalMontant, 2) ?> USD</th>
                        <th></th>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Légende -->
        <div class="mt-3">
            <h5>Légende :</h5>
            <p>
                <span class="entree">■</span> Entrée &nbsp;&nbsp;
                <span class="sortie">■</span> Sortie &nbsp;&nbsp;
                <span class="transfert">■</span> Transfert
            </p>
        </div>

        <!-- Pied de page -->
        <div class="footer">
            <p>Rapport généré le <?= date('d/m/Y à H:i:s') ?> | <?= $configInstitution['site_web'] ?? '' ?></p>
            <p>© <?= date('Y') ?> - <?= $configInstitution['nom'] ?? 'E-GESTION' ?> - Tous droits réservés</p>
        </div>
    </div>
</body>
</html>
