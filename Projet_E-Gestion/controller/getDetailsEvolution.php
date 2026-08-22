<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $idSuivi = $_GET['id'] ?? '';
    
    if (empty($idSuivi)) {
        echo json_encode(['success' => false, 'message' => 'ID manquant']);
        exit();
    }

    try {
        $db = Connexion::getInstance()->getPDO();
        
        $query = "SELECT se.*, e.\"designationECUE\", ue.\"designationUE\",
                         p.\"designationPromotion\", o.\"designationOrientation\",
                         et.noms as chef_promotion_nom, et.matricule as chef_promotion_matricule,
                         ag.noms as appariteur_nom, aa.designation as annee_acad
                  FROM suivi_enseignement_ecue se
                  JOIN ecue e ON se.\"idECUE\" = e.\"idECUE\"
                  JOIN ue ON e.\"UE_idUE\" = ue.\"idUE\"
                  JOIN promotion p ON se.promotion_idpromotion = p.idpromotion
                  JOIN orientation o ON p.orientation_idorientation = o.idorientation
                  JOIN etudiant et ON se.chef_promotion_id = et.idetudiant
                  JOIN annee_acad aa ON se.annee_acad_idannee_acad = aa.idannee_acad
                  LEFT JOIN agent ag ON se.appariteur_id = ag.\"idAgent\"
                  WHERE se.id_suivi = :idSuivi";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':idSuivi', $idSuivi, PDO::PARAM_INT);
        $stmt->execute();
        
        $evolution = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$evolution) {
            throw new Exception('Évolution non trouvée');
        }

        // Générer le HTML des détails
        $html = "
        <div class='row'>
            <div class='col-md-6'>
                <h6>Informations Générales</h6>
                <p><strong>ECUE:</strong> " . htmlspecialchars($evolution['designationECUE']) . "</p>
                <p><strong>UE:</strong> " . htmlspecialchars($evolution['designationUE']) . "</p>
                <p><strong>Promotion:</strong> " . htmlspecialchars($evolution['designationPromotion']) . "</p>
                <p><strong>Orientation:</strong> " . htmlspecialchars($evolution['designationOrientation']) . "</p>
                <p><strong>Année Académique:</strong> " . htmlspecialchars($evolution['annee_acad']) . "</p>
            </div>
            <div class='col-md-6'>
                <h6>Détails de la Séance</h6>
                <p><strong>Date:</strong> " . date('d/m/Y', strtotime($evolution['date_seance'])) . "</p>
                <p><strong>Horaire:</strong> " . date('H:i', strtotime($evolution['heure_debut'])) . " - " . date('H:i', strtotime($evolution['heure_fin'])) . "</p>
                <p><strong>Durée:</strong> " . $evolution['nombre_heures_reelles'] . " heures</p>
                <p><strong>Statut:</strong> 
                    <span class='badge " . ($evolution['statut_validation'] == 'Validé' ? 'bg-success' : ($evolution['statut_validation'] == 'Rejeté' ? 'bg-danger' : 'bg-warning')) . "'>
                        " . $evolution['statut_validation'] . "
                    </span>
                </p>
            </div>
        </div>
        <div class='row mt-3'>
            <div class='col-12'>
                <h6>Matière Vue</h6>
                <div class='alert alert-light'>
                    " . nl2br(htmlspecialchars($evolution['matiere_vue'])) . "
                </div>
            </div>
        </div>
        <div class='row'>
            <div class='col-md-6'>
                <h6>Chef de Promotion</h6>
                <p><strong>Nom:</strong> " . htmlspecialchars($evolution['chef_promotion_nom']) . "</p>
                <p><strong>Matricule:</strong> " . htmlspecialchars($evolution['chef_promotion_matricule']) . "</p>
                <p><strong>Date d'encodage:</strong> " . date('d/m/Y H:i', strtotime($evolution['date_encodage'])) . "</p>
            </div>
            <div class='col-md-6'>
                <h6>Validation</h6>";
        
        if ($evolution['statut_validation'] != 'En attente') {
            $html .= "
                <p><strong>Appariteur:</strong> " . ($evolution['appariteur_nom'] ?: 'N/A') . "</p>
                                <p><strong>Date de validation:</strong> " . ($evolution['date_validation'] ? date('d/m/Y H:i', strtotime($evolution['date_validation'])) : 'N/A') . "</p>";
            
            if ($evolution['commentaire_validation']) {
                $html .= "<p><strong>Commentaire:</strong> " . nl2br(htmlspecialchars($evolution['commentaire_validation'])) . "</p>";
            }
        } else {
            $html .= "<p class='text-muted'>En attente de validation</p>";
        }
        
        $html .= "
            </div>
        </div>";

        // Générer les boutons selon le statut
        $buttons = "";
        if ($evolution['statut_validation'] == 'En attente') {
            $buttons = "
                <button type='button' class='btn btn-success' onclick='validerEvolution(" . $evolution['id_suivi'] . ")'>
                    <i class='bi bi-check-circle'></i> Valider
                </button>
                <button type='button' class='btn btn-danger' onclick='rejeterEvolution(" . $evolution['id_suivi'] . ")'>
                    <i class='bi bi-x-circle'></i> Rejeter
                </button>
                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>
                    <i class='bi bi-x'></i> Fermer
                </button>
            ";
        } else {
            $buttons = "
                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>
                    <i class='bi bi-x'></i> Fermer
                </button>
            ";
        }
        
        echo json_encode([
            'success' => true,
            'html' => $html,
            'buttons' => $buttons
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
}
?>
