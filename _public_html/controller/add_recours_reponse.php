<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: login');
    exit;
}

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../deliberation/recours');
    exit;
}

// Récupérer les données du formulaire
$id_recours = isset($_POST['id_recours']) ? intval($_POST['id_recours']) : 0;
$id_enseignant = isset($_POST['id_enseignant']) ? intval($_POST['id_enseignant']) : null;
$nouvelle_note_cc = isset($_POST['nouvelle_note_cc']) && $_POST['nouvelle_note_cc'] !== '' ? floatval($_POST['nouvelle_note_cc']) : null;
$nouvelle_note_ex = isset($_POST['nouvelle_note_ex']) && $_POST['nouvelle_note_ex'] !== '' ? floatval($_POST['nouvelle_note_ex']) : null;
$commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';

// Récupérer les filtres
$filter_annee = isset($_POST['filter_annee']) ? intval($_POST['filter_annee']) : 0;
$filter_session = isset($_POST['filter_session']) ? intval($_POST['filter_session']) : 0;
$filter_promotion = isset($_POST['filter_promotion']) ? intval($_POST['filter_promotion']) : 0;
$filter_statut = isset($_POST['filter_statut']) ? trim($_POST['filter_statut']) : '';
$validated_only = isset($_POST['validated_only']) && $_POST['validated_only'] === 'true';

// Construire l'URL avec les filtres
$filter_params = [];
if ($filter_annee > 0) $filter_params[] = "annee={$filter_annee}";
if ($filter_session > 0) $filter_params[] = "session={$filter_session}";
if ($filter_promotion > 0) $filter_params[] = "promotion={$filter_promotion}";
if (!empty($filter_statut)) $filter_params[] = "statut=" . urlencode($filter_statut);
if ($validated_only) $filter_params[] = "validated=1";

$filter_url = !empty($filter_params) ? '?' . implode('&', $filter_params) : '';



// Valider les données
if ($id_recours <= 0 || empty($commentaire)) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Tous les champs obligatoires doivent être remplis.'
        }).then(() => {
            window.location.href = '../deliberation/recours.details?id=" . $id_recours . "';
        });
    </script>";
    exit();
}

// Si l'utilisateur est un enseignant, récupérer son ID d'agent
if (!$id_enseignant) {
    $conn = Connexion::getInstance()->getPDO();
    $query_agent = "SELECT \"idAgent\" FROM t_users WHERE \"idUser\" = :id_user";
    $stmt_agent = $conn->prepare($query_agent);
    $stmt_agent->bindParam(':id_user', $_SESSION['id']);
    $stmt_agent->execute();
    $agent = $stmt_agent->fetch(PDO::FETCH_ASSOC);
    
    if ($agent) {
        $id_enseignant = $agent['idAgent'];
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Votre compte n\'est pas associé à un profil enseignant.'
            }).then(() => {
                window.location.href = '../deliberation/recours.details?id=" . $id_recours . "';
            });
        </script>";
        exit();
    }
}

// Vérifier si les notes sont dans la plage valide
if (($nouvelle_note_cc !== null && ($nouvelle_note_cc < 0 || $nouvelle_note_cc > 20)) || 
    ($nouvelle_note_ex !== null && ($nouvelle_note_ex < 0 || $nouvelle_note_ex > 20))) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Les notes doivent être comprises entre 0 et 20.'
        }).then(() => {
            window.location.href = '../deliberation/recours.details?id=" . $id_recours . "';
        });
    </script>";
    exit();
}

$conn = Connexion::getInstance()->getPDO();

try {
    // Vérifier si l'enseignant a le droit de répondre à ce recours (enseigne l'ECUE concerné)
    $query_check_ecue = "SELECT ec.\"idECUE\"
                       FROM recours r
                       JOIN ecue ec ON r.id_ecue = ec.\"idECUE\"
                       JOIN enseignant_ecue ee ON ec.\"idECUE\" = ee.\"idECUE\"
                       WHERE r.id_recours = :id_recours
                       AND ee.\"idAgent\" = :id_enseignant";
    
    $stmt_check_ecue = $conn->prepare($query_check_ecue);
    $stmt_check_ecue->bindParam(':id_recours', $id_recours);
    $stmt_check_ecue->bindParam(':id_enseignant', $id_enseignant);
    $stmt_check_ecue->execute();
    
   
    
    // Vérifier si une réponse existe déjà pour ce recours
    $query_check_reponse = "SELECT id_reponse FROM recours_reponse WHERE id_recours = :id_recours";
    $stmt_check_reponse = $conn->prepare($query_check_reponse);
    $stmt_check_reponse->bindParam(':id_recours', $id_recours);
    $stmt_check_reponse->execute();
    
    if ($stmt_check_reponse->rowCount() > 0) {
        // Mettre à jour la réponse existante
        $query = "UPDATE recours_reponse 
                 SET nouvelle_note_cc = :nouvelle_note_cc,
                     nouvelle_note_ex = :nouvelle_note_ex,
                     commentaire = :commentaire, 
                     id_enseignant = :id_enseignant,
                     date_reponse = NOW(),
                     valide_jury = 0,
                     id_validateur = NULL,
                     date_validation = NULL
                 WHERE id_recours = :id_recours";
    } else {
        // Insérer une nouvelle réponse
        $query = "INSERT INTO recours_reponse 
                 (id_recours, nouvelle_note_cc, nouvelle_note_ex, commentaire, id_enseignant)
                 VALUES 
                 (:id_recours, :nouvelle_note_cc, :nouvelle_note_ex, :commentaire, :id_enseignant)";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id_recours', $id_recours);
    $stmt->bindParam(':nouvelle_note_cc', $nouvelle_note_cc);
    $stmt->bindParam(':nouvelle_note_ex', $nouvelle_note_ex);
    $stmt->bindParam(':commentaire', $commentaire);
    $stmt->bindParam(':id_enseignant', $id_enseignant);
    $result = $stmt->execute();
    
    if ($result) {
        // Mettre à jour le statut du recours
        $query_update = "UPDATE recours SET statut = :statut WHERE id_recours = :id_recours";
        $stmt_update = $conn->prepare($query_update);
        $statut = 'En traitement'; // Par défaut, marquer comme en traitement
        $stmt_update->bindParam(':statut', $statut);
        $stmt_update->bindParam(':id_recours', $id_recours);
        $stmt_update->execute();
        
        // Si des nouvelles notes ont été fournies, calculer la moyenne finale
        if ($nouvelle_note_cc !== null || $nouvelle_note_ex !== null) {
            // Récupérer les informations du recours (ECUE, session, matricule, année)
            $query_info = "SELECT id_ecue, id_session, matricule, id_annee_acad 
                          FROM recours WHERE id_recours = :id_recours";
            $stmt_info = $conn->prepare($query_info);
            $stmt_info->bindParam(':id_recours', $id_recours);
            $stmt_info->execute();
            $info_recours = $stmt_info->fetch(PDO::FETCH_ASSOC);
            
            // Récupérer les notes existantes et les pondérations
            $query_notes = "SELECT CG.CC, CG.EX, CG.MF, 
                                  COALESCE(CM.ponderation_cc, (SELECT ponderation_cc_defaut FROM configuration_universite LIMIT 1)) as ponderation_cc, 
                                  COALESCE(CM.ponderation_ex, (SELECT ponderation_ex_defaut FROM configuration_universite LIMIT 1)) as ponderation_ex
                           FROM cotes_grille CG
                           LEFT JOIN configuration_moyenne CM ON 
                               CG.\"ECUE_idECUE\" = CM.\"idECUE\" AND 
                               CG.session_idsession = CM.session_idsession AND 
                               CG.annee_acad_id = CM.annee_acad_id
                           WHERE CG.\"ECUE_idECUE\" = :id_ecue
                           AND CG.session_idsession = :id_session
                           AND CG.matricule = :matricule
                           AND CG.annee_acad_id = :id_annee";
            
            $stmt_notes = $conn->prepare($query_notes);
            $stmt_notes->bindParam(':id_ecue', $info_recours['id_ecue']);
            $stmt_notes->bindParam(':id_session', $info_recours['id_session']);
            $stmt_notes->bindParam(':matricule', $info_recours['matricule']);
            $stmt_notes->bindParam(':id_annee', $info_recours['id_annee_acad']);
            $stmt_notes->execute();
            $notes_existantes = $stmt_notes->fetch(PDO::FETCH_ASSOC); 
        }
        
        // Vérifier si le paramètre reponse_jury est présent dans la requête
        
        $fromJury = isset($_POST['reponse_jury']) && $_POST['reponse_jury'] == 1;

        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Votre réponse a été enregistrée avec succès.'
            }).then(() => {
                window.location.href = '../" . ($_SESSION['role'] === 'Enseignant' ? 
                    'enseignant/mes_recours' : 
                    ($fromJury ? 'deliberation/validation_recours' . $filter_url : 'deliberation/recours.details?id=' . $id_recours)) . "';
            });
        </script>";

    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de l\'enregistrement de la réponse.'
            }).then(() => {
                window.location.href = '../deliberation/recours.details?id=" . $id_recours . "';
            });
        </script>";
    }
} catch (PDOException $e) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur de base de données',
            text: 'Une erreur est survenue: " . addslashes($e->getMessage()) . "'
        }).then(() => {
            window.location.href = '../deliberation/recours.details?id=" . $id_recours . "';
        });
    </script>";
}
?>
