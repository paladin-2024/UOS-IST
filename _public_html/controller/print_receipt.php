<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Frais.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérification que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo 'Accès non autorisé';
    exit();
}

// Vérification des paramètres
if (!isset($_GET['id']) || !isset($_GET['type'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo 'Paramètres manquants';
    exit();
}

$idPaiement = intval($_GET['id']);
$typePaiement = $_GET['type'];

if ($idPaiement <= 0) {
    header('Content-Type: text/html; charset=utf-8');
    echo 'ID de paiement invalide';
    exit();
}

$fraisModel = new Frais();
$universite = new Universite();

// Récupérer les informations de l'université
$infoUniversite = $universite->getConfigurationUniversite();

// Récupérer les détails du paiement
$paiement = null;
$db = Connexion::getInstance()->getPDO();

try {
    // AJOUT: Récupérer les détails du paiement selon le type
    if ($typePaiement == 'academique') {
        // Récupérer les détails du paiement académique
        $query = "SELECT 
                p.*,
                f.designation as frais_designation,
                f.montant as frais_montant,
                f.devise,
                e.noms as nom_etudiant,
                e.matricule,
                pr.designationPromotion,
                o.designationOrientation,
                s.designationSection,
                u.nomUser as utilisateur_nom
            FROM paiement AS p
            INNER JOIN frais AS f ON p.frais_idfrais = f.idfrais
            INNER JOIN etudiant AS e ON p.etudiant_idetudiant = e.idetudiant
            INNER JOIN promotion AS pr ON e.promotion_idpromotion = pr.idpromotion
            INNER JOIN orientation AS o ON pr.orientation_idorientation = o.idorientation
            INNER JOIN section AS s ON o.section_idsection = s.idsection
            INNER JOIN t_users AS u ON p.idUser = u.idUser
            WHERE p.idpaiement = :idPaiement";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':idPaiement', $idPaiement, PDO::PARAM_INT);
        $stmt->execute();
        $paiement = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Récupérer les détails du paiement de soutenance
        $query = "SELECT 
                ps.*,
                fs.designation as frais_designation,
                fs.montant as frais_montant,
                fs.devise,
                e.noms as nom_etudiant,
                e.matricule,
                pr.designationPromotion,
                o.designationOrientation,
                s.designationSection,
                u.nomUser as utilisateur_nom
            FROM paiement_soutenance AS ps
            INNER JOIN frais_soutenance AS fs ON ps.idfrais_soutenance = fs.idfrais_soutenance
            INNER JOIN etudiant AS e ON ps.idetudiant = e.idetudiant
            INNER JOIN promotion AS pr ON e.promotion_idpromotion = pr.idpromotion
            INNER JOIN orientation AS o ON pr.orientation_idorientation = o.idorientation
            INNER JOIN section AS s ON o.section_idsection = s.idsection
            INNER JOIN t_users AS u ON ps.idUser = u.idUser
            WHERE ps.idpaiement_soutenance = :idPaiement";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':idPaiement', $idPaiement, PDO::PARAM_INT);
        $stmt->execute();
        $paiement = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Vérifier si le paiement existe
    if (!$paiement) {
        header('Content-Type: text/html; charset=utf-8');
        echo 'Paiement non trouvé';
        exit();
    }
    
    // Récupérer tous les paiements de l'étudiant pour ce frais
    $totalPaye = 0;
    $soldeRestant = 0;
    
    if ($typePaiement == 'academique') {
        // Récupérer l'ID de l'étudiant et du frais à partir du paiement actuel
        $etudiantId = $paiement['etudiant_idetudiant'];
        $fraisId = $paiement['frais_idfrais'];
        
        // Requête pour obtenir tous les paiements de cet étudiant pour ce frais
        $query = "SELECT SUM(p.montantPaye) as total_paye
                FROM paiement p
                WHERE p.etudiant_idetudiant = :etudiantId 
                AND p.frais_idfrais = :fraisId";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
        $stmt->bindParam(':fraisId', $fraisId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Montant total payé par l'étudiant pour ce frais (tous paiements confondus)
        $totalPaye = $result['total_paye'];
        
        // Calcul du solde restant
        $soldeRestant = $paiement['frais_montant'] - $totalPaye;
    } else {
        // Pour les frais de soutenance
        $etudiantId = $paiement['idetudiant'];
        $fraisSoutenanceId = $paiement['idfrais_soutenance'];
        
        // Requête pour obtenir tous les paiements de cet étudiant pour ce frais de soutenance
        $query = "SELECT SUM(ps.montant_paye) as total_paye
                FROM paiement_soutenance ps
                WHERE ps.idetudiant = :etudiantId 
                AND ps.idfrais_soutenance = :fraisSoutenanceId";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
        $stmt->bindParam(':fraisSoutenanceId', $fraisSoutenanceId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Montant total payé par l'étudiant pour ce frais (tous paiements confondus)
        $totalPaye = $result['total_paye'];
        
        // Calcul du solde restant
        $soldeRestant = $paiement['frais_montant'] - $totalPaye;
    }
    
    // Générer le reçu de paiement en HTML
    $html = '
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reçu de paiement</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                line-height: 1.5;
                margin: 0;
                padding: 20px;
            }
            .receipt {
                width: 100%;
                max-width: 800px;
                margin: 0 auto;
                border: 1px solid #ddd;
                padding: 20px;
                box-sizing: border-box;
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #ddd;
                padding-bottom: 10px;
            }
            .logo {
                max-width: 150px;
                height: auto;
                display: block;
                margin: 0 auto 10px;
            }
            h1, h2, h3 {
                margin: 5px 0;
            }
            .details {
                margin-bottom: 20px;
            }
            .details table {
                width: 100%;
                border-collapse: collapse;
            }
            .details table td {
                padding: 5px;
            }
            .details table td:first-child {
                font-weight: bold;
                width: 30%;
            }
            .payment {
                margin-bottom: 20px;
            }
            .payment table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid #ddd;
            }
            .payment table th, .payment table td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            .payment table th {
                background-color: #f2f2f2;
            }
            .footer {
                margin-top: 30px;
                border-top: 1px solid #ddd;
                padding-top: 10px;
                text-align: center;
            }
            .signatures {
                display: flex;
                justify-content: space-between;
                margin-top: 50px;
            }
            .signature {
                text-align: center;
                width: 45%;
            }
            .signature-line {
                border-top: 1px solid #000;
                margin-top: 50px;
                width: 100%;
            }
            @media print {
                body {
                    padding: 0;
                }
                .receipt {
                    border: none;
                    padding: 0;
                }
                .print-button {
                    display: none;
                }
            }
        </style>
    </head>
    <body>
        <div class="receipt">
            <div class="header">
                ' . (isset($infoUniversite['logo']) ? '<img src="../' . $infoUniversite['logo'] . '" alt="Logo" class="logo">' : '') . '
                <h1>' . (isset($infoUniversite['nom']) ? htmlspecialchars($infoUniversite['nom']) : 'Établissement d\'enseignement supérieur') . '</h1>
                <p>' . (isset($infoUniversite['adresse']) ? htmlspecialchars($infoUniversite['adresse']) : '') . '</p>
                <h2>REÇU DE PAIEMENT</h2>
                <p>N° ' . ($typePaiement == 'academique' ? $paiement['idpaiement'] : $paiement['idpaiement_soutenance']) . '</p>
            </div>

            <div class="details">
                <table>
                    <tr>
                        <td>Date :</td>
                        <td>' . date('d/m/Y H:i', strtotime($typePaiement == 'academique' ? $paiement['datePaiement'] : $paiement['date_paiement'])) . '</td>
                    </tr>
                    <tr>
                        <td>Référence :</td>
                        <td>' . htmlspecialchars($typePaiement == 'academique' ? $paiement['referencePaiement'] : $paiement['reference_paiement']) . '</td>
                    </tr>
                    <tr>
                        <td>Étudiant :</td>
                        <td>' . htmlspecialchars($paiement['nom_etudiant']) . '</td>
                    </tr>
                    <tr>
                        <td>Matricule :</td>
                        <td>' . htmlspecialchars($paiement['matricule']) . '</td>
                    </tr>
                    <tr>
                        <td>Promotion :</td>
                        <td>' . htmlspecialchars($paiement['designationPromotion']) . '</td>
                    </tr>
                    <tr>
                        <td>Section :</td>
                        <td>' . htmlspecialchars($paiement['designationSection']) . '</td>
                    </tr>
                    <tr>
                        <td>Orientation :</td>
                        <td>' . htmlspecialchars($paiement['designationOrientation']) . '</td>
                    </tr>
                </table>
            </div>

            <div class="payment">
                <h3>Détails du paiement</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Désignation</th>
                            <th>Montant total</th>
                            <th>Ce paiement</th>
                            <th>Déjà payé</th>
                            <th>Reste à payer</th>
                            <th>Mode de paiement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>' . htmlspecialchars($paiement['frais_designation']) . '</td>
                            <td>' . number_format($paiement['frais_montant'], 2) . ' ' . $paiement['devise'] . '</td>
                            <td>' . number_format($typePaiement == 'academique' ? $paiement['montantPaye'] : $paiement['montant_paye'], 2) . ' ' . $paiement['devise'] . '</td>
                            <td>' . number_format($totalPaye - ($typePaiement == 'academique' ? $paiement['montantPaye'] : $paiement['montant_paye']), 2) . ' ' . $paiement['devise'] . '</td>
                            <td>' . number_format($soldeRestant, 2) . ' ' . $paiement['devise'] . '</td>
                            <td>' . htmlspecialchars($typePaiement == 'academique' ? $paiement['modePaiement'] : $paiement['mode_paiement']) . '</td>
                        </tr>
                    </tbody>
                </table>
            </div>


            <div class="signatures">
                <div class="signature">
                    <p>Étudiant</p>
                    <div class="signature-line"></div>
                    <p>' . htmlspecialchars($paiement['nom_etudiant']) . '</p>
                </div>
                <div class="signature">
                    <p>Caissier</p>
                    <div class="signature-line"></div>
                    <p>' . htmlspecialchars($paiement['utilisateur_nom']) . '</p>
                </div>
            </div>

            <div class="footer">
                                <p>Ce reçu est une preuve de paiement officielle. Veuillez le conserver soigneusement.</p>
                <p>Imprimé le ' . date('d/m/Y H:i:s') . '</p>
            </div>
        </div>

        <div class="print-button" style="text-align: center; margin-top: 20px;">
            <button onclick="window.print();" style="padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">Imprimer</button>
        </div>
    </body>
    </html>
    ';
    
    echo $html;
    
} catch (Exception $e) {
    header('Content-Type: text/html; charset=utf-8');
    echo 'Erreur: ' . $e->getMessage();
}
?>

