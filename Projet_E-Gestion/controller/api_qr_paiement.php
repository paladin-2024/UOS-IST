<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Retourner les headers JSON
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['erreur' => 'ID de paiement manquant']);
    exit();
}

$id = intval($_GET['id']);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['erreur' => 'ID de paiement invalide']);
    exit();
}

try {
    $connexion = Connexion::getInstance()->getPDO();

    $stmt = $connexion->prepare("
    SELECT 
        pf.id,
        pf.recu_numero,
        pf.montant,
        pf.devise,
        pf.date_paiement,
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
        p.designationPromotion AS promotion_nom,
        CONCAT(s.designationSection, ' - ', o.designationOrientation) AS faculte_nom,
        t.reference AS transaction_reference,
        t.date_transaction,
        u.nomUser AS agent_nom,
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
    LEFT JOIN t_users u ON t.idUser = u.idUser
    LEFT JOIN annee_acad a ON f.annee_acad_id = a.idannee_acad
    WHERE pf.id = :id
    ");

    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $paiement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paiement) {
        http_response_code(404);
        echo json_encode(['erreur' => 'Paiement non trouvé']);
        exit();
    }

    // Préparer les données de réponse
    $montantTotalFrais = !empty($paiement['montant_specifique']) ? $paiement['montant_specifique'] : $paiement['montant_frais'];
    $totalDejaPaye = $paiement['total_deja_paye'];
    $resteAPayer = $montantTotalFrais - $totalDejaPaye;

    $response = [
        'recu_numero' => $paiement['recu_numero'],
        'etudiant' => [
            'noms' => $paiement['noms'],
            'matricule' => $paiement['matricule'],
            'promotion' => $paiement['promotion_nom'] ?? 'Non spécifiée',
            'faculte' => $paiement['faculte_nom'] ?? 'Non spécifiée'
        ],
        'paiement' => [
            'frais' => $paiement['frais_designation'],
            'montant_paye' => floatval($paiement['montant']),
            'devise' => $paiement['devise'],
            'reference' => $paiement['transaction_reference'],
            'date' => date('d/m/Y H:i', strtotime($paiement['date_transaction']))
        ],
        'situation' => [
            'montant_total' => floatval($montantTotalFrais),
            'total_paye' => floatval($totalDejaPaye),
            'reste' => floatval($resteAPayer),
            'statut' => $paiement['statut_paiement'] ?? 'Non spécifié'
        ],
        'caissier' => $paiement['agent_nom'] ?? 'Non spécifié',
        'annee_academique' => $paiement['annee_academique'] ?? 'Non spécifiée',
        'lieu_paiement' => $paiement['lieu_paiement'] ?? 'Non spécifié'
    ];

    http_response_code(200);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log('Erreur dans api_qr_paiement.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erreur' => 'Erreur serveur']);
}
?>
