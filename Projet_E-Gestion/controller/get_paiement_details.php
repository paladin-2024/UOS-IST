<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Récupérer le paramètre id du paiement
$paiement_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$paiement_id) {
    echo json_encode(['error' => 'ID de paiement manquant']);
    exit;
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Récupérer les détails du paiement
    $sql = "SELECT 
                pf.*, 
                e.matricule as matricule_etudiant, 
                e.noms as nom_etudiant,
                e.idetudiant as etudiant_id,
                p.\"designationPromotion\" as promotion,
                s.\"designationSection\" as section,
                f.designation as designation_frais,
                f.montant as montant_frais,
                f.est_echelonnable,
                af.montant_specifique,
                cf.designation as categorie_frais,
                af.id as affectation_id
            FROM paiements_frais pf
            JOIN etudiant e ON pf.etudiant_id = e.idetudiant
            JOIN affectation_frais af ON pf.affectation_id = af.id
            JOIN frais f ON af.frais_id = f.id
            JOIN categories_frais cf ON f.categorie_id = cf.id
            JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
            JOIN orientation o ON p.orientation_idorientation = o.idorientation
            JOIN section s ON o.section_idsection = s.idsection
            WHERE pf.id = :paiement_id";
    
    $stmt = $connexion->prepare($sql);
    $stmt->bindParam(':paiement_id', $paiement_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $paiement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$paiement) {
        echo json_encode(['error' => 'Paiement non trouvé']);
        exit;
    }
    
    // Calculer le montant total et payé pour cette affectation (même logique que dans paiements_etudiants.php)
    $sqlMontantPaye = "SELECT COALESCE(SUM(montant), 0) as montant_paye 
                      FROM paiements_frais 
                      WHERE affectation_id = :affectation_id 
                      AND matricule_etudiant = :matricule";
                      
    $stmtMontantPaye = $connexion->prepare($sqlMontantPaye);
    $stmtMontantPaye->bindParam(':affectation_id', $paiement['affectation_id'], PDO::PARAM_INT);
    $stmtMontantPaye->bindParam(':matricule', $paiement['matricule_etudiant'], PDO::PARAM_STR);
    $stmtMontantPaye->execute();
    $resultMontantPaye = $stmtMontantPaye->fetch(PDO::FETCH_ASSOC);
    
    // Déterminer le montant total du frais (même logique que dans paiements_etudiants.php)
    $montant_total = $paiement['montant_specifique'] > 0 ? $paiement['montant_specifique'] : $paiement['montant_frais'];
    $montant_paye = $resultMontantPaye['montant_paye'];
    $montant_restant = $montant_total - $montant_paye;
    
    // Déterminer le statut de paiement (même logique que dans paiements_etudiants.php)
    if ($montant_paye >= $montant_total) {
        $statut_paiement = 'Complet';
    } elseif ($montant_paye > 0) {
        $statut_paiement = 'Partiel';
    } else {
        $statut_paiement = 'Non payé';
    }
    
    // Ajouter ces informations calculées au paiement
    $paiement['montant_total'] = $montant_total;
    $paiement['montant_paye'] = $montant_paye;
    $paiement['montant_restant'] = $montant_restant;
    $paiement['statut_paiement'] = $statut_paiement;
    
    // Récupérer les tranches si le frais est échelonnable
    $tranches = [];
    if ($paiement['est_echelonnable'] == 1) {
        $sqlTranches = "SELECT 
                            ep.id, 
                            ep.numero_tranche, 
                            ep.designation, 
                            ep.montant, 
                            ep.date_echeance,
                            (SELECT COALESCE(SUM(pt.montant), 0) 
                             FROM paiements_tranches pt
                             JOIN paiements_frais pf ON pt.paiement_id = pf.id
                             WHERE pt.echelonnement_id = ep.id 
                             AND pf.matricule_etudiant = :matricule) AS montant_paye
                        FROM echelonnement_paiement ep
                        WHERE ep.affectation_id = :affectation_id
                        ORDER BY ep.numero_tranche";
        
        $stmtTranches = $connexion->prepare($sqlTranches);
        $stmtTranches->bindParam(':affectation_id', $paiement['affectation_id'], PDO::PARAM_INT);
        $stmtTranches->bindParam(':matricule', $paiement['matricule_etudiant'], PDO::PARAM_STR);
        $stmtTranches->execute();
        
        $tranches = $stmtTranches->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer le montant restant et déterminer le statut pour chaque tranche
        // (même logique que dans paiements_etudiants.php)
        foreach ($tranches as &$tranche) {
            $tranche['montant_restant'] = $tranche['montant'] - $tranche['montant_paye'];
            
            // Mettre à jour le statut de la tranche
            if ($tranche['montant_paye'] >= $tranche['montant']) {
                $tranche['statut_paiement'] = 'Complet';
            } elseif ($tranche['montant_paye'] > 0) {
                $tranche['statut_paiement'] = 'Partiel';
            } else {
                $tranche['statut_paiement'] = 'Non payé';
            }
        }
    }
    
    // Ajouter les tranches aux détails du paiement
    $paiement['tranches'] = $tranches;
    
    // Renvoyer les données en JSON
    echo json_encode($paiement);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}
