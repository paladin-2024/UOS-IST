<?php
header('Content-Type: application/json');
session_start();

require_once dirname(__DIR__) . '/config/Connexion.php';

if (!isset($_SESSION['id']) || !isset($_SESSION['idRole'])) {
    echo json_encode(['error' => 'Session non valide']);
    exit;
}

$currentUserId = $_SESSION['id'];
$hasFullAccess = $_SESSION['idRole'] == 1;
$pdo = Connexion::getInstance()->getPDO();

$userSections = [];
if (!$hasFullAccess) {
    $query = "SELECT section_idsection FROM responsable_section WHERE idUser = :userId";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['userId' => $currentUserId]);
    $userSections = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($userSections)) {
        echo json_encode(['error' => 'Acces refuse']);
        exit;
    }
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
if ($limit < 5) {
    $limit = 5;
} elseif ($limit > 100) {
    $limit = 100;
}
$offset = ($page - 1) * $limit;

$context = isset($_GET['context']) ? $_GET['context'] : 'affectation';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_cycle = isset($_GET['filter_cycle']) ? $_GET['filter_cycle'] : (isset($_GET['cycle']) ? $_GET['cycle'] : '');
$filter_specialisation = isset($_GET['filter_specialisation']) ? $_GET['filter_specialisation'] : (isset($_GET['specialisation']) ? $_GET['specialisation'] : '');
$filter_statut = isset($_GET['filter_statut']) ? $_GET['filter_statut'] : (isset($_GET['status']) ? $_GET['status'] : '');
$filter_annee = isset($_GET['filter_annee']) ? $_GET['filter_annee'] : (isset($_GET['annee']) ? $_GET['annee'] : '');
$filter_affectation = isset($_GET['filter_affectation']) ? $_GET['filter_affectation'] : '';
$sectionFilter = isset($_GET['section']) ? intval($_GET['section']) : 0;
$hasStudentFilter = isset($_GET['has_student']) ? $_GET['has_student'] : '';

try {
    $baseQuery = [];
    $baseQuery[] = "SELECT s.*,";
    $baseQuery[] = "       a.designation AS annee_label,";
    $baseQuery[] = "       spec.designation AS specialisation_label,";
    $baseQuery[] = "       e.idetudiant AS etudiant_id,";
    $baseQuery[] = "       e.noms AS etudiant_nom,";
    $baseQuery[] = "       e.matricule AS etudiant_matricule,";
    $baseQuery[] = "       dir.idAgent AS directeur_id,";
    $baseQuery[] = "       dir.noms AS directeur_nom,";
    $baseQuery[] = "       gdir.designation AS directeur_grade,";
    $baseQuery[] = "       enc.idAgent AS encadreur_id,";
    $baseQuery[] = "       enc.noms AS encadreur_nom,";
    $baseQuery[] = "       genc.designation AS encadreur_grade,";
    $baseQuery[] = "       (SELECT COUNT(*) FROM sujet_reformulations sr WHERE sr.idsujets = s.idsujets AND sr.statut_reformulation = 'En attente') AS reformulation_pending";
    $baseQuery[] = "  FROM sujets s";
    $baseQuery[] = "  LEFT JOIN annee_acad a ON s.annee_acad_idannee_acad = a.idannee_acad";
    $baseQuery[] = "  LEFT JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation";
    $baseQuery[] = "  LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant";
    $baseQuery[] = "  LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent";
    $baseQuery[] = "  LEFT JOIN grade gdir ON dir.grade_id = gdir.idgrade";
    $baseQuery[] = "  LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent";
    $baseQuery[] = "  LEFT JOIN grade genc ON enc.grade_id = genc.idgrade";
    $baseQuery[] = "  LEFT JOIN orientation o ON spec.idorientation = o.idorientation";
    $baseQuery[] = "  LEFT JOIN section sec ON o.section_idsection = sec.idsection";
    $baseQuery[] = " WHERE 1=1";

    $bindings = [];

    if (!$hasFullAccess && !empty($userSections)) {
        $sectionPlaceholders = [];
        foreach ($userSections as $index => $sectionId) {
            $placeholder = ":section{$index}";
            $sectionPlaceholders[] = $placeholder;
            $bindings[$placeholder] = $sectionId;
        }
        if (!empty($sectionPlaceholders)) {
            $baseQuery[] = "   AND sec.idsection IN (" . implode(', ', $sectionPlaceholders) . ")";
        }
    }

    if ($sectionFilter > 0) {
        $baseQuery[] = "   AND sec.idsection = :section_filter";
        $bindings[':section_filter'] = $sectionFilter;
    }

    if (!empty($search)) {
        $baseQuery[] = "   AND (s.intitule LIKE :search OR e.noms LIKE :search OR e.matricule LIKE :search)";
        $bindings[':search'] = '%' . $search . '%';
    }

    if (!empty($filter_cycle)) {
        $baseQuery[] = "   AND s.cycle = :filter_cycle";
        $bindings[':filter_cycle'] = $filter_cycle;
    }

    if (!empty($filter_specialisation)) {
        $baseQuery[] = "   AND s.idSpecialisation = :filter_specialisation";
        $bindings[':filter_specialisation'] = $filter_specialisation;
    }

    if (!empty($filter_statut)) {
        $baseQuery[] = "   AND s.statut_validation = :filter_statut";
        $bindings[':filter_statut'] = $filter_statut;
    }

    if (!empty($filter_annee)) {
        $baseQuery[] = "   AND s.annee_acad_idannee_acad = :filter_annee";
        $bindings[':filter_annee'] = $filter_annee;
    }

    if ($hasStudentFilter !== '') {
        if ($hasStudentFilter === '1') {
            $baseQuery[] = "   AND s.etudiant_idetudiant IS NOT NULL";
        } elseif ($hasStudentFilter === '0') {
            $baseQuery[] = "   AND s.etudiant_idetudiant IS NULL";
        }
    }

    $baseQuery[] = " ORDER BY s.annee_acad_idannee_acad DESC, s.idsujets DESC";
    $baseQuery[] = " LIMIT :limit OFFSET :offset";

    $sql = implode("\n", $baseQuery);
    $stmt = $pdo->prepare($sql);

    foreach ($bindings as $placeholder => $value) {
        if ($placeholder === ':limit' || $placeholder === ':offset') {
            continue;
        }
        $stmt->bindValue($placeholder, $value);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $sujets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($filter_affectation) && !empty($sujets)) {
        $filtered = [];
        foreach ($sujets as $sujet) {
            $hasEtudiant = !empty($sujet['etudiant_idetudiant']);
            $hasDirecteur = !empty($sujet['idDirecteur']);
            $hasEncadreur = !empty($sujet['idEncadreur']);

            $includeSubject = false;
            switch ($filter_affectation) {
                case 'avec_etudiant':
                    $includeSubject = $hasEtudiant;
                    break;
                case 'sans_etudiant':
                    $includeSubject = !$hasEtudiant;
                    break;
                case 'avec_directeur':
                    $includeSubject = $hasDirecteur;
                    break;
                case 'sans_directeur':
                    $includeSubject = !$hasDirecteur;
                    break;
                case 'avec_encadreur':
                    $includeSubject = $hasEncadreur;
                    break;
                case 'sans_encadreur':
                    $includeSubject = !$hasEncadreur;
                    break;
                case 'complet':
                    $includeSubject = $hasEtudiant && $hasDirecteur && $hasEncadreur;
                    break;
                case 'incomplet':
                    $includeSubject = !($hasEtudiant && $hasDirecteur && $hasEncadreur);
                    break;
                default:
                    $includeSubject = true;
            }

            if ($includeSubject) {
                $filtered[] = $sujet;
            }
        }
        $sujets = $filtered;
    }

    $startIndex = $offset + 1;
    $formatted = [];

    foreach ($sujets as $idx => $sujet) {
        $formatted[] = [
            'index' => $startIndex + $idx,
            'id' => isset($sujet['idsujets']) ? (int) $sujet['idsujets'] : null,
            'intitule' => $sujet['intitule'] ?? '',
            'cycle' => $sujet['cycle'] ?? '',
            'specialisation' => [
                'id' => $sujet['idSpecialisation'] ?? null,
                'label' => $sujet['specialisation_label'] ?? ''
            ],
            'statut' => $sujet['statut_validation'] ?? '',
            'annee' => [
                'id' => $sujet['annee_acad_idannee_acad'] ?? null,
                'label' => $sujet['annee_label'] ?? ''
            ],
            'etudiant' => [
                'id' => $sujet['etudiant_idetudiant'] ?? null,
                'label' => !empty($sujet['etudiant_nom'])
                    ? trim($sujet['etudiant_nom'] . ' (' . ($sujet['etudiant_matricule'] ?? '') . ')')
                    : null,
                'nom' => $sujet['etudiant_nom'] ?? null,
                'matricule' => $sujet['etudiant_matricule'] ?? null,
            ],
            'directeur' => [
                'id' => $sujet['idDirecteur'] ?? ($sujet['directeur_id'] ?? null),
                'nom' => $sujet['directeur_nom'] ?? ($sujet['directeur'] ?? null),
                'grade' => $sujet['directeur_grade'] ?? null,
            ],
            'encadreur' => [
                'id' => $sujet['idEncadreur'] ?? ($sujet['encadreur_id'] ?? null),
                'nom' => $sujet['encadreur_nom'] ?? ($sujet['encadreur'] ?? null),
                'grade' => $sujet['encadreur_grade'] ?? null,
            ],
            'has_reformulation_pending' => !empty($sujet['reformulation_pending']) && intval($sujet['reformulation_pending']) > 0,
            'context' => $context,
            'can_edit' => $hasFullAccess || (!empty($userSections)),
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $formatted,
        'page' => $page,
        'limit' => $limit,
        'hasMore' => count($sujets) === $limit,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Erreur lors du chargement: ' . $e->getMessage(),
    ]);
}
?>
