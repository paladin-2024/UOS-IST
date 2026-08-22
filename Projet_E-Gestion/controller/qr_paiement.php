<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

if (!isset($_GET['id'])) {
    die("ID de paiement manquant");
}

$id = intval($_GET['id']);

if ($id <= 0) {
    die("ID de paiement invalide");
}

try {
    $connexion = Connexion::getInstance()->getPDO();

    $stmt = $connexion->prepare("
    SELECT 
        pf.id,
        pf.recu_numero,
        pf.montant,
        pf.devise,
        pf.date_valeur,
        pf.matricule_etudiant,
        af.id AS affectation_id,
        af.montant_specifique,
        af.montant_restant,
        af.statut_paiement,
        e.noms,
        e.matricule,
        f.designation AS frais_designation,
        f.montant AS montant_frais,
        f.lieu_paiement,
        a.designation AS annee_academique,
        p.\"designationPromotion\" AS promotion_nom,
        CONCAT(s.\"designationSection\", ' - ', o.\"designationOrientation\") AS faculte_nom,
        s.telephone AS section_telephone,
        s.email AS section_email,
        s.adresse AS section_adresse,
        t.reference AS transaction_reference,
        t.date_transaction,
        u.\"nomUser\" AS agent_nom,
        (SELECT COALESCE(SUM(pf2.montant), 0) 
        FROM paiements_frais pf2
        JOIN transactions t2 ON pf2.transaction_id = t2.id
        WHERE pf2.affectation_id = af.id
        AND pf2.matricule_etudiant = e.matricule
        AND (t2.statut = 'Confirmée' OR pf2.est_confirme = 1)) AS total_deja_paye
    FROM paiements_frais pf
    INNER JOIN affectation_frais af ON pf.affectation_id = af.id
    INNER JOIN frais f ON af.frais_id = f.id
    INNER JOIN etudiant e ON pf.matricule_etudiant = e.matricule
    LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
    LEFT JOIN section s ON o.section_idsection = s.idsection
    LEFT JOIN transactions t ON pf.transaction_id = t.id
    LEFT JOIN t_users u ON t.\"idUser\" = u.\"idUser\"
    LEFT JOIN annee_acad a ON f.annee_acad_id = a.idannee_acad
    WHERE pf.id = :id
    ");

    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $paiement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paiement) {
        die("Paiement non trouvé");
    }

    $universite = new Universite();
    $configUniversite = $universite->getConfigurationUniversite();

    // Déterminer les informations de contact à utiliser selon le lieu de paiement
    $usesFacultyContact = ($paiement['lieu_paiement'] === 'Faculté');
    
    // Informations de contact (université ou faculté)
    $contactInfo = array(
        'nom' => $usesFacultyContact 
            ? ($paiement['faculte_nom'] ?? $configUniversite['nom']) 
            : $configUniversite['nom'],
        'adresse' => $usesFacultyContact 
            ? ($paiement['section_adresse'] ?? $configUniversite['adresse']) 
            : $configUniversite['adresse'],
        'telephone' => $usesFacultyContact 
            ? ($paiement['section_telephone'] ?? $configUniversite['telephone']) 
            : $configUniversite['telephone'],
        'email' => $usesFacultyContact 
            ? ($paiement['section_email'] ?? $configUniversite['email']) 
            : $configUniversite['email']
    );

    $montantTotalFrais = !empty($paiement['montant_specifique']) ? $paiement['montant_specifique'] : $paiement['montant_frais'];
    $totalDejaPaye = $paiement['total_deja_paye'];
    $resteAPayer = $montantTotalFrais - $totalDejaPaye;

    // Statut et couleur
    $statusColor = 'secondary';
    $statusText = 'NON PAYÉ';
    
    if ($paiement['statut_paiement'] === 'Complet') {
        $statusColor = 'success';
        $statusText = 'PAYÉ INTÉGRALEMENT';
    } elseif ($paiement['statut_paiement'] === 'Partiel') {
        $statusColor = 'warning';
        $statusText = 'PAIEMENT PARTIEL';
    }

} catch (Exception $e) {
    error_log('Erreur dans qr_paiement.php: ' . $e->getMessage());
    die("Une erreur est survenue: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Paiement - <?php echo htmlspecialchars($paiement['recu_numero']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container-card {
            max-width: 600px;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 25px;
            text-align: center;
            border: none;
        }
        .card-header h2 {
            margin: 0;
            font-weight: 600;
            font-size: 1.8em;
        }
        .card-header p {
            margin: 5px 0 0;
            opacity: 0.9;
            font-size: 0.95em;
        }
        .card-body {
            padding: 30px;
        }
        .info-group {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .info-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .info-label {
            font-weight: 600;
            color: #667eea;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 5px;
        }
        .info-value {
            color: #333;
            font-size: 1.1em;
            margin-bottom: 10px;
        }
        .row-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .row-info .label {
            font-weight: 600;
            color: #666;
            font-size: 0.95em;
        }
        .row-info .value {
            color: #333;
            font-weight: 500;
        }
        .badge-status {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.95em;
            margin-top: 10px;
        }
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #333;
        }
        .badge-secondary {
            background-color: #6c757d;
            color: white;
        }
        .montant-total {
            font-size: 1.8em;
            font-weight: 700;
            color: #667eea;
            text-align: center;
            padding: 15px;
            background-color: #f8f9ff;
            border-radius: 10px;
            margin: 15px 0;
        }
        .montant-paye {
            font-size: 1.5em;
            font-weight: 600;
            color: #28a745;
        }
        .montant-reste {
            font-size: 1.5em;
            font-weight: 600;
            color: #dc3545;
        }
        .header-info {
            text-align: center;
            margin-bottom: 20px;
        }
        .header-info p {
            margin: 5px 0;
            font-size: 0.9em;
            color: #666;
        }
        .divider {
            height: 1px;
            background-color: #e0e0e0;
            margin: 20px 0;
        }
        .btn-group-custom {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-custom {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-print {
            background-color: #667eea;
            color: white;
        }
        .btn-print:hover {
            background-color: #5568d3;
            color: white;
            text-decoration: none;
        }
        .btn-home {
            background-color: #f0f0f0;
            color: #333;
        }
        .btn-home:hover {
            background-color: #e0e0e0;
            color: #333;
            text-decoration: none;
        }
        .qr-section {
            text-align: center;
            padding: 20px 0;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        .qr-section p {
            color: #999;
            font-size: 0.85em;
            margin-bottom: 0;
        }
        .establishment-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
        }
        .establishment-header h5 {
            color: #667eea;
            font-weight: 700;
            margin: 0 0 5px;
        }
        .establishment-header p {
            color: #666;
            font-size: 0.85em;
            margin: 3px 0;
        }
        @media print {
            body {
                background: white;
            }
            .btn-group-custom {
                display: none;
            }
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="container-card">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-receipt"></i> Détails du Paiement</h2>
                <p>N° de Reçu: <?php echo htmlspecialchars($paiement['recu_numero']); ?></p>
            </div>
            <div class="card-body">
                <!-- En-tête établissement -->
                <div class="establishment-header">
                    <h5><?php echo htmlspecialchars($contactInfo['nom']); ?></h5>
                    <?php if (!empty($contactInfo['adresse'])): ?>
                        <p><?php echo htmlspecialchars($contactInfo['adresse']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($contactInfo['email'])): ?>
                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($contactInfo['email']); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Informations Étudiant -->
                <div class="info-group">
                    <span class="info-label"><i class="fas fa-user"></i> Étudiant</span>
                    <div class="row-info">
                        <span class="label">Nom:</span>
                        <span class="value"><?php echo htmlspecialchars($paiement['noms']); ?></span>
                    </div>
                    <div class="row-info">
                        <span class="label">Matricule:</span>
                        <span class="value"><?php echo htmlspecialchars($paiement['matricule']); ?></span>
                    </div>
                    <div class="row-info">
                        <span class="label">Promotion:</span>
                        <span class="value"><?php echo htmlspecialchars($paiement['promotion_nom'] ?? 'Non spécifiée'); ?></span>
                    </div>
                    <div class="row-info">
                        <span class="label">Faculté:</span>
                        <span class="value"><?php echo htmlspecialchars($paiement['faculte_nom'] ?? 'Non spécifiée'); ?></span>
                    </div>
                </div>

                <!-- Informations Paiement -->
                <div class="info-group">
                    <span class="info-label"><i class="fas fa-money-bill"></i> Détails du Paiement</span>
                    <div class="row-info">
                        <span class="label">Frais:</span>
                        <span class="value"><?php echo htmlspecialchars($paiement['frais_designation']); ?></span>
                    </div>
                    <div class="row-info">
                        <span class="label">Référence:</span>
                        <span class="value"><?php echo htmlspecialchars($paiement['transaction_reference']); ?></span>
                    </div>
                    <div class="row-info">
                        <span class="label">Date:</span>
                        <span class="value"><?php echo date('d/m/Y à H:i', strtotime($paiement['date_transaction'])); ?></span>
                    </div>
                    <div class="row-info">
                        <span class="label">Caissier:</span>
                        <span class="value"><?php echo htmlspecialchars($paiement['agent_nom'] ?? 'N/A'); ?></span>
                    </div>
                </div>

                <!-- Situation du paiement -->
                <div class="info-group">
                    <span class="info-label"><i class="fas fa-chart-pie"></i> Situation du Paiement</span>
                    
                    <div class="montant-total">
                        <?php echo htmlspecialchars($montantTotalFrais); ?> <?php echo htmlspecialchars($paiement['devise']); ?>
                    </div>

                    <div class="row-info">
                        <span class="label">Montant total du frais:</span>
                        <span class="value"><?php echo number_format($montantTotalFrais, 2, ',', ' '); ?> <?php echo htmlspecialchars($paiement['devise']); ?></span>
                    </div>
                    
                    <div class="row-info">
                        <span class="label">Total payé:</span>
                        <span class="montant-paye"><?php echo number_format($totalDejaPaye, 2, ',', ' '); ?> <?php echo htmlspecialchars($paiement['devise']); ?></span>
                    </div>

                    <?php if ($resteAPayer > 0): ?>
                        <div class="row-info">
                            <span class="label">Reste à payer:</span>
                            <span class="montant-reste"><?php echo number_format($resteAPayer, 2, ',', ' '); ?> <?php echo htmlspecialchars($paiement['devise']); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="row-info">
                            <span class="label">Statut:</span>
                            <span class="badge badge-success">✓ PAYÉ INTÉGRALEMENT</span>
                        </div>
                    <?php endif; ?>

                    <span class="badge badge-status badge-<?php echo $statusColor; ?>"><?php echo $statusText; ?></span>
                </div>

                <!-- Section supplémentaire -->
                <div class="establishment-header" style="border-bottom: none; padding-bottom: 0; margin-bottom: 0;">
                    <p style="font-size: 0.8em; color: #999;">
                        <i class="fas fa-calendar-alt"></i> Année académique: <?php echo htmlspecialchars($paiement['annee_academique'] ?? 'Non spécifiée'); ?><br>
                        Document généré le <?php echo date('d/m/Y à H:i'); ?>
                    </p>
                </div>

                <!-- Boutons d'action -->
                <div class="btn-group-custom">
                    <button class="btn-custom btn-print" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                    <a href="../index.php" class="btn-custom btn-home">
                        <i class="fas fa-home"></i> Accueil
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>
</body>
</html>
