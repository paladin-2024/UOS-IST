<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    
    $chefId = filter_input(INPUT_GET, 'chef_id', FILTER_VALIDATE_INT);
    $promotionId = filter_input(INPUT_GET, 'promotion_id', FILTER_VALIDATE_INT);
    
    if (!$chefId || !$promotionId) {
        echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
        exit;
    }
    
    // Récupérer les détails du chef actuel
    $queryChef = "SELECT cp.*, e.matricule, e.noms, 
                         p.\"designationPromotion\", o.\"designationOrientation\", s.\"designationSection\"
                  FROM chef_promotion cp
                  JOIN etudiant e ON cp.idetudiant = e.idetudiant
                  JOIN promotion p ON cp.promotion_idpromotion = p.idpromotion
                  JOIN orientation o ON p.orientation_idorientation = o.idorientation
                  JOIN section s ON o.section_idsection = s.idsection
                  WHERE cp.idetudiant = :chef_id AND cp.est_actif = 1";
    
    $stmtChef = $db->prepare($queryChef);
    $stmtChef->bindParam(':chef_id', $chefId, PDO::PARAM_INT);
    $stmtChef->execute();
    
    $chef = $stmtChef->fetch(PDO::FETCH_ASSOC);
    
    if (!$chef) {
        echo json_encode(['success' => false, 'message' => 'Chef de promotion non trouvé']);
        exit;
    }
    
    // Récupérer tous les étudiants de la promotion (y compris le chef actuel)
    $queryEtudiants = "SELECT e.idetudiant, e.matricule, e.noms 
                       FROM etudiant e
                       WHERE e.promotion_idpromotion = :promotion_id 
                       AND e.est_actif = 1
                       AND (e.idetudiant = :chef_etudiant_id OR e.idetudiant NOT IN (
                           SELECT cp.idetudiant 
                           FROM chef_promotion cp 
                           WHERE cp.est_actif = 1 
                           AND cp.annee_acad_idannee_acad = e.annee_acad_idannee_acad
                           AND cp.id_chef != :chef_id
                       ))
                       ORDER BY e.noms";
    
    $stmtEtudiants = $db->prepare($queryEtudiants);
    $stmtEtudiants->bindParam(':promotion_id', $promotionId, PDO::PARAM_INT);
    $stmtEtudiants->bindParam(':chef_etudiant_id', $chef['idetudiant'], PDO::PARAM_INT);
    $stmtEtudiants->bindParam(':chef_id', $chefId, PDO::PARAM_INT);
    $stmtEtudiants->execute();
    
    $etudiants = $stmtEtudiants->fetchAll(PDO::FETCH_ASSOC);
    
    // Générer le HTML du formulaire
    $html = '
        <input type="hidden" name="chef_id" value="' . $chef['id_chef'] . '">
        <input type="hidden" name="promotion_id" value="' . $chef['promotion_idpromotion'] . '">
        <input type="hidden" name="annee_acad_id" value="' . $chef['annee_acad_idannee_acad'] . '">
        
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info">
                    <strong>Promotion :</strong> ' . htmlspecialchars($chef['designationSection'] . ' - ' . $chef['designationOrientation'] . ' - ' . $chef['designationPromotion']) . '
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <label for="edit_etudiant_id" class="form-label">Nouvel Étudiant <span class="text-danger">*</span></label>
                <select class="form-select" id="edit_etudiant_id" name="etudiant_id" required>';
    
    foreach ($etudiants as $etudiant) {
        $selected = ($etudiant['idetudiant'] == $chef['idetudiant']) ? 'selected' : '';
        $html .= '<option value="' . $etudiant['idetudiant'] . '" ' . $selected . '>' . 
                 htmlspecialchars($etudiant['matricule'] . ' - ' . $etudiant['noms']) . '</option>';
    }
    
        $html .= '
                </select>
            </div>
            <div class="col-md-6">
                <label for="edit_date_nomination" class="form-label">Date de Nomination <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="edit_date_nomination" name="date_nomination" 
                       value="' . $chef['date_nomination'] . '" required>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Attention :</strong> La modification du chef de promotion affectera les fonctionnalités liées à cette promotion.
                </div>
            </div>
        </div>';
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'chef' => $chef
    ]);
    
} catch (Exception $e) {
    error_log("Erreur getChefDetails: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur système']);
}
?>

    