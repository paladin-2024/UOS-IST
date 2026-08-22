<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Frais.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérification que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Récupération des paramètres
$etudiantId = isset($_GET['etudiant']) ? intval($_GET['etudiant']) : 0;
$fraisId = isset($_GET['frais']) ? intval($_GET['frais']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'academique';

if ($etudiantId <= 0) {
    die("ID étudiant invalide");
}

$fraisModel = new Frais();
$universite = new Universite();

// Récupérer l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();
$anneeAcadId = $currentYear['idannee_acad'];

// Récupérer les informations de l'étudiant
$etudiant = $universite->getEtudiantById($etudiantId);
if (!$etudiant) {
    die("Étudiant non trouvé");
}

// Récupérer la promotion de l'étudiant
$promotion = $universite->getPromotionById($etudiant['promotion_idpromotion']);
$orientation = $universite->getOrientationById($promotion['orientation_idorientation']);
$section = $universite->getSectionById($orientation['section_idsection']);

// Récupérer les frais et paiements
$fraisList = [];
$totalDu = 0;
$totalPaye = 0;
$totalRestant = 0;

if ($type == 'academique') {
    if ($fraisId > 0) {
        // Récupérer un frais spécifique
        $frais = $fraisModel->getFraisById($fraisId);
        if ($frais) {
            $paiements = $fraisModel->getPaiementsByFrais($fraisId, $anneeAcadId);
            
            // Filtrer les paiements pour cet étudiant
            $paiementsEtudiant = array_filter($paiements, function($p) use ($etudiantId) {
                return $p['etudiant_idetudiant'] == $etudiantId;
            });
            
            $montantPaye = array_sum(array_column($paiementsEtudiant, 'montantPaye'));
            $montantRestant = max(0, $frais['montant'] - $montantPaye);
            
            $fraisList[] = [
                'designation' => $frais['designation'],
                'montant' => $frais['montant'],
                'devise' => $frais['devise'],
                'estObligatoire' => $frais['estObligatoire'] ?? 0,
                'montant_paye' => $montantPaye,
                'montant_restant' => $montantRestant,
                'paiements' => $paiementsEtudiant
            ];
            
            $totalDu += $frais['montant'];
            $totalPaye += $montantPaye;
            $totalRestant += $montantRestant;
        }
    } else {
        // Récupérer tous les frais pour cette promotion
        $fraisPromotion = $fraisModel->getFraisByPromotion($etudiant['promotion_idpromotion'], $anneeAcadId);
        $paiementsEtudiant = $fraisModel->getPaiementsByEtudiant($etudiantId, $anneeAcadId);
        
        foreach ($fraisPromotion as $frais) {
            // Filtrer les paiements pour ce frais
            $paiementsFrais = array_filter($paiementsEtudiant, function($p) use ($frais) {
                return $p['frais_idfrais'] == $frais['idfrais'];
            });
            
            $montantPaye = array_sum(array_column($paiementsFrais, 'montantPaye'));
            $montantRestant = max(0, $frais['montant'] - $montantPaye);
            
            $fraisList[] = [
                'designation' => $frais['designation'],
                'montant' => $frais['montant'],
                'devise' => $frais['devise'],
                'estObligatoire' => $frais['estObligatoire'] ?? 0,
                'montant_paye' => $montantPaye,
                'montant_restant' => $montantRestant,
                'paiements' => $paiementsFrais
            ];
            
            $totalDu += $frais['montant'];
            $totalPaye += $montantPaye;
            $totalRestant += $montantRestant;
        }
    }
} else {
    // Logique similaire pour les frais de soutenance
    // ...
}

// Déterminer si l'étudiant est en règle
$enRegle = $fraisModel->etudiantEnRegle($etudiantId, $anneeAcadId);

// Générer le PDF ou afficher directement le relevé
// Pour cet exemple, nous utiliserons une sortie HTML directe
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relevé de paiement - <?= htmlspecialchars($etudiant['noms']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 5px 0;
            font-size: 18px;
        }
        .info {
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            width: 150px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .summary {
            margin-top: 20px;
            text-align: right;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
        }
        .status {
            margin: 20px 0;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }
        .status.ok {
            background-color: #d4edda;
            color: #155724;
        }
        .status.not-ok {
            background-color: #f8d7da;
            color: #721c24;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                margin: 0;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right; margin-bottom: 10px;">
        <button onclick="window.print()">Imprimer</button>
    </div>

    <div class="header">
        <h1>RELEVÉ DE PAIEMENT</h1>
        <p>Année académique: <?= htmlspecialchars($currentYear['designation']) ?></p>
    </div>

    <div class="info">
        <div class="info-row">
            <div class="info-label">Matricule:</div>
            <div><?= htmlspecialchars($etudiant['matricule']) ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Nom et prénom:</div>
            <div><?= htmlspecialchars($etudiant['noms']) ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Section:</div>
            <div><?= htmlspecialchars($section['designationSection']) ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Orientation:</div>
            <div><?= htmlspecialchars($orientation['designationOrientation']) ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Promotion:</div>
            <div><?= htmlspecialchars($promotion['designationPromotion']) ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Date d'émission:</div>
            <div><?= date('d/m/Y H:i') ?></div>
        </div>
    </div>

    <div class="status <?= $enRegle ? 'ok' : 'not-ok' ?>">
        <?php if ($enRegle): ?>
            L'étudiant est en règle pour les frais obligatoires
        <?php else: ?>
            L'étudiant n'est pas en règle pour tous les frais obligatoires
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>Désignation du frais</th>
                <th>Obligatoire</th>
                <th>Montant total</th>
                <th>Montant payé</th>
                <th>Montant restant</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($fraisList as $frais): ?>
            <tr>
                <td><?= htmlspecialchars($frais['designation']) ?></td>
                <td><?= $frais['estObligatoire'] ? 'Oui' : 'Non' ?></td>
                <td><?= number_format($frais['montant'], 2) ?> <?= $frais['devise'] ?></td>
                <td><?= number_format($frais['montant_paye'], 2) ?> <?= $frais['devise'] ?></td>
                <td><?= number_format($frais['montant_restant'], 2) ?> <?= $frais['devise'] ?></td>
                <td><?= $frais['montant_restant'] > 0 ? 'Partiel' : 'Complet' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2">TOTAL</th>
                <th><?= number_format($totalDu, 2) ?> <?= $fraisList[0]['devise'] ?? '' ?></th>
                <th><?= number_format($totalPaye, 2) ?> <?= $fraisList[0]['devise'] ?? '' ?></th>
                <th><?= number_format($totalRestant, 2) ?> <?= $fraisList[0]['devise'] ?? '' ?></th>
                <th></th>
            </tr>
        </tfoot>
    </table>

    <h3>Détail des paiements</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Frais</th>
                <th>Montant</th>
                <th>Mode de paiement</th>
                <th>Référence</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $allPaiements = [];
            foreach ($fraisList as $frais) {
                foreach ($frais['paiements'] as $paiement) {
                    $allPaiements[] = [
                        'date' => $type == 'academique' ? $paiement['datePaiement'] : $paiement['date_paiement'],
                        'frais' => $frais['designation'],
                        'montant' => $type == 'academique' ? $paiement['montantPaye'] : $paiement['montant_paye'],
                        'mode' => $type == 'academique' ? $paiement['modePaiement'] : $paiement['mode_paiement'],
                        'reference' => $type == 'academique' ? $paiement['referencePaiement'] : $paiement['reference_paiement'],
                        'devise' => $frais['devise']
                    ];
                }
            }
            
            // Trier par date (du plus récent au plus ancien)
            usort($allPaiements, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
            
            foreach ($allPaiements as $p):
            ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($p['date'])) ?></td>
                <td><?= htmlspecialchars($p['frais']) ?></td>
                <td><?= number_format($p['montant'], 2) ?> <?= $p['devise'] ?></td>
                <td><?= htmlspecialchars($p['mode']) ?></td>
                <td><?= htmlspecialchars($p['reference']) ?></td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($allPaiements)): ?>
            <tr>
                <td colspan="5" style="text-align: center;">Aucun paiement enregistré</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <p>Ce document est généré automatiquement et ne nécessite pas de signature.</p>
        <p>© <?= date('Y') ?> - Système de Gestion Académique</p>
    </div>
</body>
</html>
