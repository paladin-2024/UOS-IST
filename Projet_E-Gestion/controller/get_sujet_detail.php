<?php
// Inclure le fichier de connexion à la base de données
require_once dirname(__DIR__) . "/config/Connexion.php";

// Définir le type de contenu JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Vérifier si l'ID du sujet est fourni
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'ID de sujet invalide ou manquant'
    ]);
    exit;
}

$sujetId = intval($_GET['id']);

try {
    // Établir la connexion à la base de données
    $pdo = Connexion::getInstance()->getPDO();
    
// Requête principale pour récupérer les détails du sujet
    $query = "SELECT 
                s.idsujets,
                s.intitule,
                s.resume,
                s.etatSujet,
                s.cycle,
                s.statut_validation,
                s.commentaire_commission,
                s.date_validation,
                s.idDirecteur,
                s.idEncadreur,
                s.etudiant_idetudiant,
                s.idSpecialisation,
                s.idUser,
                s.idValidateur,
                
                -- Informations de l'année académique
                a.idannee_acad,
                a.designation as annee,
                
                -- Informations de l'étudiant
                e.idetudiant,
                e.noms as etudiant,
                e.matricule as matricule_etudiant,
                e.telephone as telephone_etudiant,
                e.adressemail as email_etudiant,
                e.sexe as sexe_etudiant,
                
                -- Informations du directeur
                d.idAgent as id_directeur,
                d.noms as directeur,
                d.telephone as telephone_directeur,
                d.email as email_directeur,
                gr_d.idgrade as id_grade_directeur,
                gr_d.designation as grade_directeur,
                
                -- Informations de l'encadreur
                enc.idAgent as id_encadreur,
                enc.noms as encadreur,
                enc.telephone as telephone_encadreur,
                enc.email as email_encadreur,
                gr_e.idgrade as id_grade_encadreur,
                gr_e.designation as grade_encadreur,
                
                -- Informations de la spécialisation
                spec.idSpecialisation,
                spec.designation as specialisation,
                
                -- Informations de l'orientation
                o.idorientation,
                o.designationOrientation as orientation,
                
                -- Informations de la section
                sec.idsection,
                sec.designationSection as section,
                
                -- Informations de l'unité de recherche
                ur.idunite_recherche,
                ur.designation_UR as unite_recherche,
                ur.description as description_ur,
                
                -- Informations de la promotion
                p.idpromotion,
                p.designationPromotion as promotion,
                
                -- Informations du validateur
                u.idUser as id_validateur,
                u.nomUser as nom_validateur,
                
                -- Informations de création
                u_creation.idUser as id_createur,
                u_creation.nomUser as nom_createur,
                
                -- Formatage de la date de validation
                CASE 
                    WHEN s.date_validation IS NOT NULL 
                    THEN DATE_FORMAT(s.date_validation, '%d/%m/%Y à %H:%i')
                    ELSE NULL 
                END as date_validation_formatee
                
              FROM sujets s
              
              -- Jointures obligatoires
              LEFT JOIN annee_acad a ON s.annee_acad_idannee_acad = a.idannee_acad
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
              
              -- Jointures pour les agents (directeur et encadreur)
              LEFT JOIN agent d ON s.idDirecteur = d.idAgent
              LEFT JOIN grade gr_d ON d.grade_id = gr_d.idgrade
              LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent 
              LEFT JOIN grade gr_e ON enc.grade_id = gr_e.idgrade
              
              -- Jointures pour la hiérarchie académique
              LEFT JOIN orientation o ON spec.idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              LEFT JOIN unite_recherche ur ON spec.idUnite_recherche = ur.idunite_recherche
              
              -- Jointure pour la promotion de l'étudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              
              -- Jointures pour les utilisateurs
              LEFT JOIN t_users u ON s.idValidateur = u.idUser
              LEFT JOIN t_users u_creation ON s.idUser = u_creation.idUser
              
              WHERE s.idsujets = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$sujetId]);
    
    $sujet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sujet) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Sujet non trouvé'
        ]);
        exit;
    }
    
    // Récupérer l'historique des validations pour ce sujet
    $queryHistory = "SELECT 
                        sh.id,
                        sh.status,
                        sh.commentaire,
                        sh.date_action,
                        u.idUser,
                        u.nomUser as nom_utilisateur,
                        DATE_FORMAT(sh.date_action, '%d/%m/%Y à %H:%i') as date_formatee
                     FROM sujet_validation_history sh
                     LEFT JOIN t_users u ON sh.idUser = u.idUser
                     WHERE sh.idsujets = ?
                     ORDER BY sh.date_action DESC";
    
    $stmtHistory = $pdo->prepare($queryHistory);
    $stmtHistory->execute([$sujetId]);
    $historyRecords = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
    
    // Ajouter l'historique aux données du sujet
    $sujet['historique'] = $historyRecords;
    
    // Récupérer les tâches associées au sujet
    $queryTasks = "SELECT 
                      t.idtaches,
                      t.dateTache,
                      t.description,
                      t.fichierTache,
                      t.validation,
                      t.pourcentage_avancement,
                      t.date_validation,
                      t.commentaire_validation,
                      DATE_FORMAT(t.dateTache, '%d/%m/%Y') as date_tache_formatee
                   FROM taches t
                   WHERE t.sujets_idsujets = ?
                   ORDER BY t.dateTache DESC";
    
    $stmtTasks = $pdo->prepare($queryTasks);
    $stmtTasks->execute([$sujetId]);
    $tasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);
    
    // Ajouter les tâches aux données du sujet
    $sujet['taches'] = $tasks;
    
// Récupérer les informations de dépôt de mémoire
    $queryMemoire = "SELECT 
                        dm.idDepot,
                        dm.dateDepot,
                        dm.fichier,
                        dm.observation,
                        DATE_FORMAT(dm.dateDepot, '%d/%m/%Y') as date_depot_formatee
                     FROM depot_memoire dm
                     WHERE dm.sujets_idsujets = ?
                     ORDER BY dm.dateDepot DESC
                     LIMIT 1";
    
    $stmtMemoire = $pdo->prepare($queryMemoire);
    $stmtMemoire->execute([$sujetId]);
    $memoire = $stmtMemoire->fetch(PDO::FETCH_ASSOC);
    
    // Ajouter les informations de mémoire
    $sujet['memoire'] = $memoire;
    
    // Récupérer les informations de soutenance
    $querySoutenance = "SELECT 
                           s.idsoutenance,
                           s.date_soutenance,
                           s.lieu,
                           s.statut,
                           s.note_finale,
                           s.commentaire,
                           DATE_FORMAT(s.date_soutenance, '%d/%m/%Y à %H:%i') as date_soutenance_formatee
                        FROM soutenance s
                        WHERE s.sujets_idsujets = ?
                        ORDER BY s.date_soutenance DESC
                        LIMIT 1";
    
    $stmtSoutenance = $pdo->prepare($querySoutenance);
    $stmtSoutenance->execute([$sujetId]);
    $soutenance = $stmtSoutenance->fetch(PDO::FETCH_ASSOC);
    
    // Ajouter les informations de soutenance
    $sujet['soutenance'] = $soutenance;
    
    // Nettoyer les valeurs null pour éviter les erreurs JavaScript
    foreach ($sujet as $key => $value) {
        if ($value === null) {
            $sujet[$key] = '';
        }
    }
    
    // Retourner les données au format JSON (structure simple pour compatibilité)
    echo json_encode($sujet, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    // Log de l'erreur
    error_log("Erreur base de données dans get_sujet_detail.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur de base de données',
        'message' => $e->getMessage()
    ]);
    
} catch (Exception $e) {
    // Log de l'erreur générale
    error_log("Erreur générale dans get_sujet_detail.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur interne du serveur',
        'message' => $e->getMessage()
    ]);
}
?>
