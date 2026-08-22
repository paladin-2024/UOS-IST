<?php
/**
 * Contrôleur AJAX - Statistiques de présences pour un ECUE
 * Retourne les statistiques globales et par étudiant
 * Paramètres GET : ecue_id, annee_id
 */
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

try {
    $db = Connexion::getInstance()->getPDO();

    // Récupération et validation des paramètres
    $ecue_id = isset($_GET['ecue_id']) ? intval($_GET['ecue_id']) : 0;
    $annee_id = isset($_GET['annee_id']) ? intval($_GET['annee_id']) : 0;

    if ($ecue_id <= 0 || $annee_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Paramètres manquants (ecue_id, annee_id requis)']);
        exit;
    }

    // Nombre total de séances pour cet ECUE cette année
    $stmtSeances = $db->prepare("SELECT COUNT(*) FROM seance_cours WHERE \"idECUE\" = ? AND annee_acad_id = ?");
    $stmtSeances->execute([$ecue_id, $annee_id]);
    $total_seances = (int)$stmtSeances->fetchColumn();

    // Nombre total de présences enregistrées
    $stmtPresences = $db->prepare("
        SELECT COUNT(*) FROM presence_cours pc
        JOIN seance_cours sc ON pc.idseance = sc.idseance
        WHERE sc.\"idECUE\" = ? AND sc.annee_acad_id = ?
    ");
    $stmtPresences->execute([$ecue_id, $annee_id]);
    $total_presences = (int)$stmtPresences->fetchColumn();

    // Récupérer la promotion liée à cet ECUE (ecue -> ue -> semestre -> promotion)
    $stmtPromo = $db->prepare("
        SELECT s.promotion_idpromotion
        FROM ecue e
        JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
        JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
        WHERE e.\"idECUE\" = ?
        LIMIT 1
    ");
    $stmtPromo->execute([$ecue_id]);
    $promotion_id = $stmtPromo->fetchColumn();

    // Nombre total d'étudiants actifs dans cette promotion
    $total_etudiants = 0;
    if ($promotion_id) {
        $stmtEtudiants = $db->prepare("SELECT COUNT(*) FROM etudiant WHERE promotion_idpromotion = ? AND est_actif = 1");
        $stmtEtudiants->execute([$promotion_id]);
        $total_etudiants = (int)$stmtEtudiants->fetchColumn();
    }

    // Taux moyen de présence
    $taux_moyen = 0;
    if ($total_seances > 0 && $total_etudiants > 0) {
        $taux_moyen = round(($total_presences / ($total_seances * $total_etudiants)) * 100, 2);
    }

    // Liste des séances avec le nombre de présents
    $stmtListeSeances = $db->prepare("
        SELECT s.idseance, s.titre, s.date_seance, s.heure_debut, s.heure_fin, s.salle,
               (SELECT COUNT(*) FROM presence_cours WHERE idseance = s.idseance) as nb_presents
        FROM seance_cours s
        WHERE s.\"idECUE\" = ? AND s.annee_acad_id = ?
        ORDER BY s.date_seance, s.heure_debut
    ");
    $stmtListeSeances->execute([$ecue_id, $annee_id]);
    $seances = $stmtListeSeances->fetchAll(PDO::FETCH_ASSOC);

    // Ajouter le total étudiants à chaque séance pour calcul de pourcentage côté frontend
    foreach ($seances as &$seance) {
        $seance['total_etudiants'] = $total_etudiants;
    }
    unset($seance);

    // Statistiques globales
    $global = [
        'total_seances' => $total_seances,
        'total_presences' => $total_presences,
        'total_etudiants' => $total_etudiants,
        'taux_moyen' => $taux_moyen,
        'seances' => $seances
    ];

    // --- Statistiques par étudiant ---
    $etudiants = [];
    if ($promotion_id) {
        $stmtListeEtudiants = $db->prepare("
            SELECT idetudiant, matricule, noms
            FROM etudiant
            WHERE promotion_idpromotion = ? AND est_actif = 1
            ORDER BY noms ASC
        ");
        $stmtListeEtudiants->execute([$promotion_id]);
        $listeEtudiants = $stmtListeEtudiants->fetchAll(PDO::FETCH_ASSOC);

        // Requête préparée pour compter les présences d'un étudiant
        $stmtPresEtudiant = $db->prepare("
            SELECT COUNT(*) FROM presence_cours pc
            JOIN seance_cours sc ON pc.idseance = sc.idseance
            WHERE pc.idetudiant = ? AND sc.\"idECUE\" = ? AND sc.annee_acad_id = ?
        ");

        foreach ($listeEtudiants as $etudiant) {
            $stmtPresEtudiant->execute([$etudiant['idetudiant'], $ecue_id, $annee_id]);
            $nb_present = (int)$stmtPresEtudiant->fetchColumn();

            $taux_presence = 0;
            if ($total_seances > 0) {
                $taux_presence = round(($nb_present / $total_seances) * 100, 2);
            }

            $etudiants[] = [
                'idetudiant' => $etudiant['idetudiant'],
                'matricule' => $etudiant['matricule'],
                'noms' => $etudiant['noms'],
                'nb_present' => $nb_present,
                'total_seances' => $total_seances,
                'taux_presence' => $taux_presence
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'global' => $global,
        'etudiants' => $etudiants
    ]);

} catch (Exception $e) {
    error_log("Erreur get_stats_presences: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement des statistiques de présences']);
}
