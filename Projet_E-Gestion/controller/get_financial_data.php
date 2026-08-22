<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Préparer la réponse
$response = [
    'success' => false,
    'data' => null,
    'message' => ''
];

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Type de données demandé
    $dataType = isset($_GET['type']) ? $_GET['type'] : '';
    
    // Paramètres de filtre
    $annee_acad_id = isset($_GET['annee_acad_id']) ? intval($_GET['annee_acad_id']) : 0;
    $date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-d', strtotime('-1 year'));
    $date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-d');
    
    switch ($dataType) {
        case 'recettes_mensuelles':
            // Récupération des recettes mensuelles
            $stmt = $connexion->prepare("
                SELECT 
                    DATE_FORMAT(t.date_transaction, '%Y-%m') as mois,
                    SUM(t.montant) as montant
                FROM transactions t
                WHERE t.type = 'Recette'
                AND t.date_transaction BETWEEN :date_debut AND :date_fin
                GROUP BY DATE_FORMAT(t.date_transaction, '%Y-%m')
                ORDER BY mois ASC
            ");
            $stmt->bindParam(':date_debut', $date_debut);
            $stmt->bindParam(':date_fin', $date_fin);
            $stmt->execute();
            $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['success'] = true;
            break;
            
        case 'depenses_mensuelles':
            // Récupération des dépenses mensuelles
            $stmt = $connexion->prepare("
                SELECT 
                    DATE_FORMAT(t.date_transaction, '%Y-%m') as mois,
                    SUM(t.montant) as montant
                FROM transactions t
                WHERE t.type = 'Dépense'
                AND t.date_transaction BETWEEN :date_debut AND :date_fin
                GROUP BY DATE_FORMAT(t.date_transaction, '%Y-%m')
                ORDER BY mois ASC
            ");
            $stmt->bindParam(':date_debut', $date_debut);
            $stmt->bindParam(':date_fin', $date_fin);
            $stmt->execute();
            $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['success'] = true;
            break;
            
        case 'recettes_par_categorie':
            // Récupération des recettes par catégorie
            $stmt = $connexion->prepare("
                SELECT 
                    cf.designation as categorie,
                    SUM(pf.montant) as montant
                FROM paiements_frais pf
                JOIN affectation_frais af ON pf.affectation_id = af.id
                JOIN frais f ON af.frais_id = f.id
                JOIN categories_frais cf ON f.categorie_id = cf.id
                JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
                WHERE (aa.idannee_acad = :annee_acad_id OR :annee_acad_id = 0)
                GROUP BY cf.id
                ORDER BY montant DESC
            ");
            $stmt->bindParam(':annee_acad_id', $annee_acad_id);
            $stmt->execute();
            $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['success'] = true;
            break;
            
        case 'taux_recouvrement':
            // Calcul du taux de recouvrement global
            $stmt = $connexion->prepare("
                SELECT 
                    SUM(CASE WHEN af.montant_specifique > 0 THEN af.montant_specifique ELSE f.montant END) as total_facture,
                    SUM(COALESCE(pf.montant, 0)) as total_paye
                FROM affectation_frais af
                JOIN frais f ON af.frais_id = f.id
                JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
                LEFT JOIN (
                    SELECT affectation_id, SUM(montant) as montant 
                    FROM paiements_frais 
                    GROUP BY affectation_id
                ) pf ON pf.affectation_id = af.id
                WHERE (aa.idannee_acad = :annee_acad_id OR :annee_acad_id = 0)
                AND af.est_exempte = 0
            ");
            $stmt->bindParam(':annee_acad_id', $annee_acad_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $total_facture = $result['total_facture'] ?? 0;
            $total_paye = $result['total_paye'] ?? 0;
            $taux = ($total_facture > 0) ? ($total_paye / $total_facture * 100) : 0;
            
            $response['data'] = [
                'total_facture' => $total_facture,
                'total_paye' => $total_paye,
                'taux_recouvrement' => $taux
            ];
            $response['success'] = true;
            break;
            
        default:
            $response['message'] = 'Type de données non pris en charge';
            break;
    }
    
} catch (PDOException $e) {
    $response['message'] = 'Erreur de base de données: ' . $e->getMessage();
}

// Renvoyer la réponse
header('Content-Type: application/json');
echo json_encode($response);