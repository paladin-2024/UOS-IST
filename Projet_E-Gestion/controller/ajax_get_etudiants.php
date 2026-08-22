<?php
// controller/ajax_get_etudiants.php
require_once '../config/Connexion.php';
require_once '../models/Universite.php';

header('Content-Type: application/json');

try {
    $universite = new Universite();

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50; // Default to 50 for infinite scroll batches
    $offset = ($page - 1) * $limit;

    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $anneeId = isset($_GET['annee']) && $_GET['annee'] !== '' ? (int)$_GET['annee'] : null;
    $orientationId = isset($_GET['orientation']) && $_GET['orientation'] !== '' ? $_GET['orientation'] : null;
    $promotionId = isset($_GET['promotion']) && $_GET['promotion'] !== '' ? $_GET['promotion'] : null;

    $students = $universite->getStudents($search, $limit, $offset, $anneeId, $orientationId, $promotionId);

    // Retrieve total count for information (optional, but good for "End of list")
    // Since getStudents doesn't return count, we might check if we got fewer than limit
    // or we could add a count method later. For now, we rely on the number of results returned.
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'count' => count($students),
        'hasMore' => count($students) === $limit
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
