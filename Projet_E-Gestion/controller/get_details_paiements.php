<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Récupérer les paramètres
$frais_id = isset($_GET['fraisId']) ? intval($_GET['fraisId']) : 0;
$promotion_id = isset($_GET['promotionId']) ? intval($_GET['promotionId']) : 0;

if (!$frais_id || !$promotion_id) {
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Récupérer les détails du frais et de la promotion
    $sql = "SELECT 
                f.id, 
                f.designation, 
                f.montant, 
                f.devise, 
                f.est_obligatoire,
                cf.designation as categorie,
                p.designationPromotion as promotion,
                s.designationSection as section,
                a.designation as annee_academique
            FROM frais f
            JOIN categories_frais cf ON f.categorie_id = cf.id
            JOIN affectation_frais af ON f.id = af.frais_id
            JOIN promotion p ON af.promotion_id = p.idpromotion
            JOIN orientation o ON p.orientation_idorientation = o.idorientation
            JOIN section s ON o.section_idsection = s.idsection
            JOIN annee_acad a ON p.annee_acad_idannee_acad = a.idannee_acad
            WHERE f.id = :frais_id AND p.idpromotion = :promotion_id
            LIMIT 1";
    
    $stmt = $connexion->prepare($sql);
    $stmt->bindParam(':frais_id', $frais_id, PDO::PARAM_INT);
    $stmt->bindParam(':promotion_id', $promotion_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $frais = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$frais) {
        echo json_encode(['error' => 'Frais ou promotion non trouvé']);
        exit;
    }
    
    // Récupérer le nombre d'étudiants dans la promotion
    $sqlEtudiants = "SELECT COUNT(*) as nb_etudiants 
                     FROM etudiant 
                     WHERE promotion_idpromotion = :promotion_id 
                     AND est_actif = 1";
    
    $stmtEtudiants = $connexion->prepare($sqlEtudiants);
    $stmtEtudiants->bindParam(':promotion_id', $promotion_id, PDO::PARAM_INT);
    $stmtEtudiants->execute();
    $resultEtudiants = $stmtEtudiants->fetch(PDO::FETCH_ASSOC);
    $nb_etudiants = $resultEtudiants['nb_etudiants'];
    
    // Récupérer les paiements pour ce frais dans cette promotion
    // Correction: utiliser les champs qui existent réellement dans la table paiements_frais
    $sqlPaiements = "SELECT 
                        pf.id,
                        pf.matricule_etudiant as matricule,
                        e.noms as nom_etudiant,
                        pf.montant,
                        pf.date_valeur as date_paiement,
                        pf.reference_externe as reference,
                        pf.est_confirme
                    FROM paiements_frais pf
                    JOIN etudiant e ON pf.etudiant_id = e.idetudiant
                    JOIN affectation_frais af ON pf.affectation_id = af.id
                    WHERE af.frais_id = :frais_id 
                    AND e.promotion_idpromotion = :promotion_id
                    ORDER BY pf.date_valeur DESC";
    
    $stmtPaiements = $connexion->prepare($sqlPaiements);
    $stmtPaiements->bindParam(':frais_id', $frais_id, PDO::PARAM_INT);
    $stmtPaiements->bindParam(':promotion_id', $promotion_id, PDO::PARAM_INT);
    $stmtPaiements->execute();
    
    $paiements = $stmtPaiements->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer le montant total perçu
    $sqlMontantPercu = "SELECT COALESCE(SUM(pf.montant), 0) as montant_percu
                        FROM paiements_frais pf
                        JOIN etudiant e ON pf.etudiant_id = e.idetudiant
                        JOIN affectation_frais af ON pf.affectation_id = af.id
                        WHERE af.frais_id = :frais_id 
                        AND e.promotion_idpromotion = :promotion_id
                        AND pf.est_confirme = 1";
    
    $stmtMontantPercu = $connexion->prepare($sqlMontantPercu);
    $stmtMontantPercu->bindParam(':frais_id', $frais_id, PDO::PARAM_INT);
    $stmtMontantPercu->bindParam(':promotion_id', $promotion_id, PDO::PARAM_INT);
    $stmtMontantPercu->execute();
    $resultMontantPercu = $stmtMontantPercu->fetch(PDO::FETCH_ASSOC);
    $montant_percu = $resultMontantPercu['montant_percu'];
    
    // Récupérer les étudiants qui n'ont pas effectué de paiement
    $sqlEtudiantsSansPaiement = "SELECT 
                                    e.matricule,
                                    e.noms as nom_etudiant
                                FROM etudiant e
                                WHERE e.promotion_idpromotion = :promotion_id
                                AND e.est_actif = 1
                                AND e.idetudiant NOT IN (
                                    SELECT DISTINCT pf.etudiant_id
                                    FROM paiements_frais pf
                                    JOIN affectation_frais af ON pf.affectation_id = af.id
                                    WHERE af.frais_id = :frais_id
                                )
                                ORDER BY e.noms";
    
    $stmtEtudiantsSansPaiement = $connexion->prepare($sqlEtudiantsSansPaiement);
    $stmtEtudiantsSansPaiement->bindParam(':frais_id', $frais_id, PDO::PARAM_INT);
    $stmtEtudiantsSansPaiement->bindParam(':promotion_id', $promotion_id, PDO::PARAM_INT);
    $stmtEtudiantsSansPaiement->execute();
    
    $etudiants_sans_paiement = $stmtEtudiantsSansPaiement->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer les statistiques
    $montant_attendu = $frais['montant'] * $nb_etudiants;
    $taux_paiement = $montant_attendu > 0 ? ($montant_percu / $montant_attendu) * 100 : 0;
    
    // Préparer les données à retourner
    $result = [
        'frais' => $frais,
        'stats' => [
            'nb_etudiants' => $nb_etudiants,
            'montant_attendu' => $montant_attendu,
            'montant_percu' => $montant_percu,
            'taux_paiement' => $taux_paiement
        ],
        'paiements' => $paiements,
        'etudiants_sans_paiement' => $etudiants_sans_paiement
    ];
    
    // Renvoyer les données en JSON
    echo json_encode($result);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}
