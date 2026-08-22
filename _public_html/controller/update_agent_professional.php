<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour effectuer cette action.";
    header('Location: ../index');
    exit;
}

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Méthode non autorisée.";
    header('Location: ../index');
    exit;
}

// Récupérer les données du formulaire
$idAgent = isset($_POST['idAgent']) ? intval($_POST['idAgent']) : 0;
$returnTab = isset($_POST['returnTab']) ? $_POST['returnTab'] : 'professional';

// Données professionnelles générales
$gradeId = isset($_POST['grade_id']) ? intval($_POST['grade_id']) : null;
$idStructure = isset($_POST['idStructure']) ? intval($_POST['idStructure']) : null;
$idService = isset($_POST['idService']) ? intval($_POST['idService']) : null;
$anneeEngagement = isset($_POST['annee_engagement']) ? intval($_POST['annee_engagement']) : null;
$referenceActeEngagement = isset($_POST['reference_acte_engagement']) ? trim($_POST['reference_acte_engagement']) : null;

// Options de paiement (checkboxes)
$primeLocale = isset($_POST['prime_locale']) ? 1 : 0;
$salaireEtat = isset($_POST['salaire_etat']) ? 1 : 0;
$primeInstitutionnelle = isset($_POST['prime_institutionnelle']) ? 1 : 0;

// Données spécifiques selon le type d'agent
$direction = isset($_POST['direction']) ? trim($_POST['direction']) : null;
$division = isset($_POST['division']) ? trim($_POST['division']) : null;
$decisionGrade = isset($_POST['decision_grade']) ? trim($_POST['decision_grade']) : null;
$notificationGrade = isset($_POST['notification_grade']) ? trim($_POST['notification_grade']) : null;

$specialisation = isset($_POST['specialisation']) ? trim($_POST['specialisation']) : null;
$domaineRecherche = isset($_POST['domaine_recherche']) ? trim($_POST['domaine_recherche']) : null;

$uniteRecherche = isset($_POST['unite_recherche']) ? trim($_POST['unite_recherche']) : null;
$projetRecherche = isset($_POST['projet_recherche']) ? trim($_POST['projet_recherche']) : null;

// Validation des données
if (empty($idAgent)) {
    $_SESSION['error'] = "ID de l'agent non spécifié.";
    header('Location: ../grh/agent.edition');
    exit;
}

if (empty($gradeId) || empty($idStructure) || empty($idService)) {
    $_SESSION['error'] = "Veuillez remplir tous les champs obligatoires.";
    header('Location: ../grh/agent.edition&tab=' . $returnTab);
    exit;
}

try {
    $pdo = Connexion::getInstance()->getPDO();
    $pdo->beginTransaction();
    
    // 1. Mettre à jour les informations professionnelles générales de l'agent
    $query = "UPDATE agent 
              SET grade_id = :gradeId, 
                  idStructure = :idStructure, 
                  idService = :idService,
                  annee_engagement = :anneeEngagement,
                  reference_acte_engagement = :referenceActeEngagement,
                  prime_locale = :primeLocale,
                  salaire_etat = :salaireEtat,
                  prime_institutionnelle = :primeInstitutionnelle
              WHERE idAgent = :idAgent";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':gradeId', $gradeId, PDO::PARAM_INT);
    $stmt->bindParam(':idStructure', $idStructure, PDO::PARAM_INT);
    $stmt->bindParam(':idService', $idService, PDO::PARAM_INT);
    $stmt->bindParam(':anneeEngagement', $anneeEngagement, PDO::PARAM_INT);
    $stmt->bindParam(':referenceActeEngagement', $referenceActeEngagement, PDO::PARAM_STR);
    $stmt->bindParam(':primeLocale', $primeLocale, PDO::PARAM_INT);
    $stmt->bindParam(':salaireEtat', $salaireEtat, PDO::PARAM_INT);
    $stmt->bindParam(':primeInstitutionnelle', $primeInstitutionnelle, PDO::PARAM_INT);
    $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
    $stmt->execute();
    
    // 2. Récupérer le type d'agent pour traiter les informations spécifiques
    $queryType = "SELECT type_agent FROM agent WHERE idAgent = :idAgent";
    $stmtType = $pdo->prepare($queryType);
    $stmtType->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
    $stmtType->execute();
    $agentType = $stmtType->fetchColumn();
    
    // 3. Traiter les informations spécifiques selon le type d'agent
    if ($agentType == 'Administratif') {
        // Vérifier si l'entrée existe déjà
        $checkQuery = "SELECT id FROM admin_info WHERE idAgent = :idAgent";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->fetchColumn()) {
            // Mise à jour
            $query = "UPDATE admin_info 
                      SET direction = :direction, 
                          division = :division, 
                          decision_grade = :decisionGrade, 
                          notification_grade = :notificationGrade,
                          idUser = :idUser
                      WHERE idAgent = :idAgent";
        } else {
            // Insertion
            $query = "INSERT INTO admin_info 
                      (idAgent, direction, division, decision_grade, notification_grade, idUser) 
                      VALUES 
                      (:idAgent, :direction, :division, :decisionGrade, :notificationGrade, :idUser)";
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->bindParam(':direction', $direction, PDO::PARAM_STR);
        $stmt->bindParam(':division', $division, PDO::PARAM_STR);
        $stmt->bindParam(':decisionGrade', $decisionGrade, PDO::PARAM_STR);
        $stmt->bindParam(':notificationGrade', $notificationGrade, PDO::PARAM_STR);
        $stmt->bindParam(':idUser', $_SESSION['id'], PDO::PARAM_INT);
        $stmt->execute();
    } 
    elseif ($agentType == 'Enseignant') {
        // Vérifier si l'entrée existe déjà
        $checkQuery = "SELECT id FROM teacher_info WHERE idAgent = :idAgent";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->fetchColumn()) {
            // Mise à jour
            $query = "UPDATE teacher_info 
                      SET specialisation = :specialisation, 
                          domaine_recherche = :domaineRecherche,
                          idUser = :idUser
                      WHERE idAgent = :idAgent";
        } else {
            // Insertion
            $query = "INSERT INTO teacher_info 
                      (idAgent, specialisation, domaine_recherche, idUser) 
                      VALUES 
                      (:idAgent, :specialisation, :domaineRecherche, :idUser)";
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->bindParam(':specialisation', $specialisation, PDO::PARAM_STR);
        $stmt->bindParam(':domaineRecherche', $domaineRecherche, PDO::PARAM_STR);
        $stmt->bindParam(':idUser', $_SESSION['id'], PDO::PARAM_INT);
        $stmt->execute();
    } 
    elseif ($agentType == 'Recherche') {
        // Vérifier si l'entrée existe déjà
        $checkQuery = "SELECT id FROM research_info WHERE idAgent = :idAgent";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->fetchColumn()) {
            // Mise à jour
            $query = "UPDATE research_info 
                      SET unite_recherche = :uniteRecherche, 
                          projet_recherche = :projetRecherche,
                          idUser = :idUser
                      WHERE idAgent = :idAgent";
        } else {
            // Insertion
            $query = "INSERT INTO research_info 
                      (idAgent, unite_recherche, projet_recherche, idUser) 
                      VALUES 
                      (:idAgent, :uniteRecherche, :projetRecherche, :idUser)";
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $stmt->bindParam(':uniteRecherche', $uniteRecherche, PDO::PARAM_STR);
        $stmt->bindParam(':projetRecherche', $projetRecherche, PDO::PARAM_STR);
        $stmt->bindParam(':idUser', $_SESSION['id'], PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // 4. Récupérer le code de l'agent pour la redirection
    $queryAgent = "SELECT codeAgent FROM agent WHERE idAgent = :idAgent";
    $stmtAgent = $pdo->prepare($queryAgent);
    $stmtAgent->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
    $stmtAgent->execute();
    $agent = $stmtAgent->fetch(PDO::FETCH_ASSOC);
    
    if (!$agent) {
        throw new Exception("Agent non trouvé.");
    }
    
    // Valider la transaction
    $pdo->commit();
    
    // Préparer le message de succès pour SweetAlert
    $_SESSION['swal_success'] = [
        'title' => 'Succès!',
        'text' => 'Les informations professionnelles ont été mises à jour avec succès.',
        'icon' => 'success'
    ];
    
    header('Location: ../grh/agent.edition&searchType=code&search=' . $agent['codeAgent'] . '&tab=' . $returnTab);
    
} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Préparer le message d'erreur pour SweetAlert
    $_SESSION['swal_error'] = [
        'title' => 'Erreur!',
        'text' => 'Erreur de base de données: ' . $e->getMessage(),
        'icon' => 'error'
    ];
    
    header('Location: ../grh/agent.edition&tab=' . $returnTab);
    exit;
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Préparer le message d'erreur pour SweetAlert
    $_SESSION['swal_error'] = [
        'title' => 'Erreur!',
        'text' => 'Erreur serveur: ' . $e->getMessage(),
        'icon' => 'error'
    ];
    
    header('Location: ../grh/agent.edition&tab=' . $returnTab);
    exit;
}
