<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Définir l'en-tête Content-Type dès le début
header('Content-Type: application/json; charset=UTF-8');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier si le terme de recherche est spécifié
if (!isset($_GET['search']) || strlen($_GET['search']) < 3) {
    echo json_encode(['error' => 'Terme de recherche trop court ou manquant']);
    exit;
}

$searchTerm = $_GET['search'];
$anneeAcademique = isset($_GET['annee_academique']) ? $_GET['annee_academique'] : '';

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    // Logging pour débogage
    error_log("Recherche étudiant avec terme: $searchTerm");
    
    // Rechercher l'étudiant
    $searchQuery = "SELECT DISTINCT epa.matricule, epa.nom_complet,
                    COUNT(DISTINCT epa.idpalmares) as nb_palmares
                    FROM etudiants_palmares_archives epa
                    WHERE (epa.matricule LIKE :search OR epa.nom_complet LIKE :search)
                    GROUP BY epa.matricule, epa.nom_complet";
                    
    $searchParams = [':search' => "%$searchTerm%"];
    
    $stmt = $pdo->prepare($searchQuery);
    $stmt->execute($searchParams);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode([
            'success' => false,
            'error' => 'Aucun étudiant trouvé avec ces critères',
            'student' => null,
            'palmares' => []
        ]);
        exit;
    }
    
    error_log("Étudiant trouvé: " . $student['nom_complet']);
    
    // Récupérer les palmarès où cet étudiant apparaît
    $palmaresQuery = "SELECT epa.idetudiant_palmares as id, epa.idpalmares, epa.matricule, epa.nom_complet, 
                      epa.pourcentage, epa.decision,
                      pa.annee_academique, pa.section, pa.promotion, pa.session
                  FROM etudiants_palmares_archives epa
                  JOIN palmares_archives pa ON epa.idpalmares = pa.idpalmares
                  WHERE (epa.matricule = :matricule OR epa.nom_complet = :nom_complet)";

    $palmaresParams = [
        ':matricule' => $student['matricule'],
        ':nom_complet' => $student['nom_complet']
    ];
    
    // Filtrer par année académique si spécifiée
    if (!empty($anneeAcademique)) {
        $palmaresQuery .= " AND pa.annee_academique = :annee_academique";
        $palmaresParams[':annee_academique'] = $anneeAcademique;
    }
    
    // Ajouter l'ordre de tri
    $palmaresQuery .= " ORDER BY pa.annee_academique DESC, pa.promotion ASC, pa.session ASC";
    
    $stmt = $pdo->prepare($palmaresQuery);
    $stmt->execute($palmaresParams);
    $palmares = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Nombre de palmarès trouvés: " . count($palmares));
    
    // Préparer la structure de la réponse
    $response = [
        'success' => true,
        'student' => [
            'matricule' => $student['matricule'],
            'nom_complet' => $student['nom_complet'],
            'count' => count($palmares)
        ],
        'palmares' => $palmares
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Erreur SQL dans search_palmares_student.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erreur de base de données: ' . $e->getMessage(),
        'student' => null,
        'palmares' => []
    ]);
    exit;
} catch (Exception $e) {
    error_log("Exception dans search_palmares_student.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erreur: ' . $e->getMessage(),
        'student' => null,
        'palmares' => []
    ]);
    exit;
}
