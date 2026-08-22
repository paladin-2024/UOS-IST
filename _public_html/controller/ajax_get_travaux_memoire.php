<?php
// controller/ajax_get_travaux_memoire.php
// AJAX endpoint pour l'infinite scroll des travaux de mémoire

session_start();
require_once '../config/Connexion.php';

header('Content-Type: application/json');

try {
    // Vérification de la session
    if (!isset($_SESSION['id'])) {
        throw new Exception('Session non valide');
    }

    $userId = $_SESSION['id'];
    $hasFullAccess = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;

    $connexion = Connexion::getInstance()->getPDO();

    // Paramètres de pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = ($page - 1) * $limit;

    // Filtres
    $anneeId = isset($_GET['annee_acad']) && $_GET['annee_acad'] !== '' ? (int)$_GET['annee_acad'] : null;
    $specialisationId = isset($_GET['specialisation']) && $_GET['specialisation'] !== '' ? (int)$_GET['specialisation'] : null;
    $cycle = isset($_GET['cycle']) && $_GET['cycle'] !== '' ? $_GET['cycle'] : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Récupérer les responsabilités de l'utilisateur si pas admin
    $userResponsibilities = [];
    if (!$hasFullAccess) {
        $query = "SELECT DISTINCT section_idsection FROM responsable_section WHERE \"idUser\" = :userId";
        $stmt = $connexion->prepare($query);
        $stmt->execute(['userId' => $userId]);
        $userResponsibilities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Si pas admin et pas de responsabilités, retourner vide
    if (!$hasFullAccess && empty($userResponsibilities)) {
        echo json_encode([
            'success' => true,
            'travaux' => [],
            'count' => 0,
            'hasMore' => false
        ]);
        exit;
    }

    // Construction de la requête
    $query = "SELECT DISTINCT sj.idsujets, sj.intitule as sujet_titre, sj.cycle, sj.\"idSpecialisation\",
                     e.noms as etudiant_nom, e.matricule, e.idetudiant,
                     d.noms as directeur_nom,
                     sp.designation as specialisation,
                     o.idorientation, o.\"designationOrientation\" as orientation_designation,
                     sec.idsection as section_idsection, sec.\"designationSection\" as section_designation,
                     s.idsoutenance, s.date_soutenance, s.lieu, s.statut,
                     dm.\"idDepot\", dm.fichier as memoire_fichier, dm.\"dateDepot\",
                     j.idjury, j.designation as jury_designation,
                     (SELECT COUNT(*) FROM lecteurs_soutenance WHERE idsoutenance = s.idsoutenance) as nb_lecteurs
              FROM sujets sj
              JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
              LEFT JOIN agent d ON sj.\"idDirecteur\" = d.\"idAgent\"
              LEFT JOIN specialisation sp ON sj.\"idSpecialisation\" = sp.\"idSpecialisation\"
              LEFT JOIN orientation o ON sp.idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              LEFT JOIN soutenance s ON sj.idsujets = s.sujets_idsujets
              LEFT JOIN depot_memoire dm ON sj.idsujets = dm.sujets_idsujets
              LEFT JOIN jury j ON s.jury_id = j.idjury
              WHERE 1=1";

    $executeParams = [];

    // Filtre par année académique
    if ($anneeId) {
        $query .= " AND sj.annee_acad_idannee_acad = :anneeId";
        $executeParams['anneeId'] = $anneeId;
    }

    // Filtre par spécialisation
    if ($specialisationId) {
        $query .= " AND sp.\"idSpecialisation\" = :specialisationId";
        $executeParams['specialisationId'] = $specialisationId;
    }

    // Filtre par cycle
    if ($cycle) {
        $query .= " AND sj.cycle = :cycle";
        $executeParams['cycle'] = $cycle;
    }

    // Filtre par recherche (nom étudiant ou matricule)
    if ($search) {
        $query .= " AND (e.noms LIKE :search OR e.matricule LIKE :searchMatricule OR sj.intitule LIKE :searchTitre)";
        $executeParams['search'] = "%$search%";
        $executeParams['searchMatricule'] = "%$search%";
        $executeParams['searchTitre'] = "%$search%";
    }

    // Filtrer par sections de l'utilisateur si pas admin
    if (!$hasFullAccess && !empty($userResponsibilities)) {
        $sectionPlaceholders = [];
        foreach ($userResponsibilities as $index => $sectionId) {
            $paramName = "section_" . $index;
            $sectionPlaceholders[] = ":$paramName";
            $executeParams[$paramName] = $sectionId;
        }
        $query .= " AND sec.idsection IN (" . implode(',', $sectionPlaceholders) . ")";
    }

    $query .= " ORDER BY CASE 
                    WHEN s.idsoutenance IS NULL THEN 0 
                    WHEN s.date_soutenance IS NULL THEN 1 
                    ELSE 2 
                END, 
                s.date_soutenance DESC, 
                e.noms ASC
                LIMIT :limit OFFSET :offset";

    $stmt = $connexion->prepare($query);
    
    // Bind des paramètres
    foreach ($executeParams as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $travaux = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'travaux' => $travaux,
        'count' => count($travaux),
        'hasMore' => count($travaux) === $limit
    ]);

} catch (Exception $e) {
    error_log("Erreur ajax_get_travaux_memoire: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
