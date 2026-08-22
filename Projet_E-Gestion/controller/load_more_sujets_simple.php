<?php
header('Content-Type: application/json');
session_start();

try {
    // Inclure le fichier de connexion
    require_once __DIR__ . '/../config/Connexion.php';

    // Vérification de la session
    if (!isset($_SESSION['id']) || !isset($_SESSION['idRole'])) {
        echo json_encode(['error' => 'Session non valide']);
        exit;
    }

    $connexion = Connexion::getInstance()->getPDO();
    $currentUserId = $_SESSION['id']; 
    $hasFullAccess = $_SESSION['idRole'] == 1;

    // Paramètres de pagination
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $offset = ($page - 1) * $limit;

    // Paramètres de recherche
    $search = isset($_GET['search']) ? $_GET['search'] : '';

    // Requête simple pour commencer
    $query = "SELECT s.*, 
                 a.designation as annee, 
                 spec.designation as specialisation,
                 s.statut_validation
              FROM sujets s
              LEFT JOIN annee_acad a ON s.annee_acad_idannee_acad = a.idannee_acad
              LEFT JOIN specialisation spec ON s.\"idSpecialisation\" = spec.\"idSpecialisation\"";

    $params = [];

    // Ajouter la recherche si elle existe
    if (!empty($search)) {
        $query .= " WHERE s.intitule LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }

    $query .= " ORDER BY s.annee_acad_idannee_acad DESC LIMIT :limit OFFSET :offset";

    $stmt = $connexion->prepare($query);
    
    // Lier les paramètres
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $sujets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Générer le HTML simple
    $html = '';
    $i = ($page - 1) * $limit + 1;
    
    foreach ($sujets as $sujet) {
        $cycleLabel = $sujet['cycle'] == 'Premier' ? 'Licence' : 
                     ($sujet['cycle'] == 'Deuxieme' ? 'Master' : 'Doctorat');
        
        $badgeClass = '';
        switch ($sujet['statut_validation']) {
            case 'Validé': $badgeClass = 'bg-success'; break;
            case 'En attente': $badgeClass = 'bg-warning'; break;
            case 'A reformulé': $badgeClass = 'bg-danger'; break;
            case 'Modifié': $badgeClass = 'bg-info'; break;
            default: $badgeClass = 'bg-secondary';
        }

        $html .= "
            <tr>
                <td>{$i}</td>
                <td>" . htmlspecialchars($sujet['intitule']) . "</td>
                <td>{$cycleLabel}</td>
                <td>" . htmlspecialchars($sujet['specialisation']) . "</td>
                <td><span class='badge {$badgeClass}'>" . htmlspecialchars($sujet['statut_validation']) . "</span></td>
                <td><span class='text-muted'>Non assigné</span></td>
                <td><span class='text-muted'>Non assigné</span></td>
                <td><span class='text-muted'>Non assigné</span></td>
                <td>" . htmlspecialchars($sujet['annee']) . "</td>
                <td>
                    <button class='btn btn-sm btn-primary' data-sujet-id='{$sujet['idsujets']}'>
                        <i class='bi bi-pencil'></i>
                    </button>
                </td>
            </tr>";
        $i++;
    }

    // Compter le total de sujets pour debug
    $countQuery = "SELECT COUNT(*) FROM sujets";
    $countStmt = $connexion->prepare($countQuery);
    $countStmt->execute();
    $totalSujets = $countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'html' => $html,
        'hasMore' => count($sujets) === $limit,
        'debug' => [
            'version' => 'SIMPLE_VERSION_OK',
            'total_sujets_db' => $totalSujets,
            'sujets_returned' => count($sujets),
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
            'search' => $search,
            'query_used' => $query
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'debug' => 'EXCEPTION',
        'line' => $e->getLine()
    ]);
}
?>
