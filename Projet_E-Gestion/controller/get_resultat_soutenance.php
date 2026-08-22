<?php
/**
 * API publique pour récupérer le résultat de soutenance d'un étudiant
 * Accessible sans authentification
 */

require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Récupérer le matricule
$matricule = isset($_GET['matricule']) ? trim($_GET['matricule']) : '';

if (empty($matricule)) {
    echo json_encode([
        'success' => false,
        'message' => 'Veuillez entrer votre matricule'
    ]);
    exit();
}

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    // Récupérer l'année académique active
    $stmtAnnee = $pdo->query("SELECT idannee_acad, designation FROM annee_acad WHERE est_active = 1 LIMIT 1");
    $anneeActive = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
    
    if (!$anneeActive) {
        // Prendre la plus récente si aucune n'est active
        $stmtAnnee = $pdo->query("SELECT idannee_acad, designation FROM annee_acad ORDER BY \"dateCreation\" DESC LIMIT 1");
        $anneeActive = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$anneeActive) {
        echo json_encode([
            'success' => false,
            'message' => 'Aucune année académique configurée'
        ]);
        exit();
    }
    
    // Rechercher la soutenance de l'étudiant
    $query = "
        SELECT 
            e.matricule,
            e.noms AS etudiant_nom,
            sj.intitule AS titre_memoire,
            sj.cycle,
            d.noms AS directeur_nom,
            sp.designation AS specialisation,
            so.date_soutenance,
            so.lieu,
            so.note_finale,
            so.statut,
            aa.designation AS annee_academique
        FROM soutenance so
        INNER JOIN sujets sj ON so.sujets_idsujets = sj.idsujets
        INNER JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
        LEFT JOIN agent d ON sj.\"idDirecteur\" = d.\"idAgent\"
        LEFT JOIN specialisation sp ON sj.\"idSpecialisation\" = sp.\"idSpecialisation\"
        LEFT JOIN annee_acad aa ON so.annee_acad_idannee_acad = aa.idannee_acad
        WHERE e.matricule = :matricule
        AND so.annee_acad_idannee_acad = :annee_id
        AND so.note_finale IS NOT NULL
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'matricule' => $matricule,
        'annee_id' => $anneeActive['idannee_acad']
    ]);
    
    $resultat = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resultat) {
        // Vérifier si l'étudiant existe mais n'a pas de note
        $checkQuery = "
            SELECT e.noms, so.idsoutenance, so.note_finale
            FROM etudiant e
            LEFT JOIN sujets sj ON sj.etudiant_idetudiant = e.idetudiant
            LEFT JOIN soutenance so ON so.sujets_idsujets = sj.idsujets 
                AND so.annee_acad_idannee_acad = :annee_id
            WHERE e.matricule = :matricule
            LIMIT 1
        ";
        
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([
            'matricule' => $matricule,
            'annee_id' => $anneeActive['idannee_acad']
        ]);
        $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$checkResult) {
            echo json_encode([
                'success' => false,
                'message' => 'Matricule non trouvé dans le système'
            ]);
        } elseif ($checkResult['idsoutenance'] === null) {
            echo json_encode([
                'success' => false,
                'message' => 'Aucune soutenance programmée pour cette année académique'
            ]);
        } elseif ($checkResult['note_finale'] === null) {
            echo json_encode([
                'success' => false,
                'message' => 'Votre note n\'a pas encore été encodée. Veuillez réessayer ultérieurement.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Résultat non disponible'
            ]);
        }
        exit();
    }
    
    // Calculer la mention
    $note = floatval($resultat['note_finale']);
    $pourcentage = ($note / 20) * 100;
    
    if ($pourcentage < 50) {
        $mention = 'Ajourné';
    } elseif ($pourcentage >= 50 && $pourcentage <= 69) {
        $mention = 'Satisfaction';
    } elseif ($pourcentage >= 70 && $pourcentage <= 79) {
        $mention = 'Distinction';
    } elseif ($pourcentage >= 80 && $pourcentage <= 89) {
        $mention = 'Grande Distinction';
    } else {
        $mention = 'Plus Grande Distinction';
    }
    
    // Retourner le résultat
    echo json_encode([
        'success' => true,
        'message' => 'Résultat trouvé',
        'data' => [
            'matricule' => $resultat['matricule'],
            'etudiant_nom' => $resultat['etudiant_nom'],
            'titre_memoire' => $resultat['titre_memoire'],
            'cycle' => $resultat['cycle'],
            'directeur_nom' => $resultat['directeur_nom'],
            'specialisation' => $resultat['specialisation'],
            'date_soutenance' => $resultat['date_soutenance'],
            'lieu' => $resultat['lieu'],
            'note_finale' => $resultat['note_finale'],
            'mention' => $mention,
            'annee_academique' => $resultat['annee_academique']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur get_resultat_soutenance: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur technique. Veuillez réessayer.'
    ]);
}
