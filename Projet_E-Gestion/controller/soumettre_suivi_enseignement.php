<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté et est un étudiant
if (!isset($_SESSION['student_id']) || !isset($_SESSION['student_matricule'])) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Accès non autorisé',
                text: 'Vous devez être connecté en tant qu\'étudiant pour accéder à cette page'
            }).then(() => {
                window.location.href = '../portail/login';
            });
        });
    </script>";
    exit();
}

// Vérifier si l'étudiant est chef de promotion
$connexion = Connexion::getInstance()->getPDO();

// Récupérer l'ID du chef de promotion pour cet étudiant
$queryChef = "SELECT cp.id_chef 
              FROM chef_promotion cp 
              INNER JOIN etudiant e ON cp.idetudiant = e.idetudiant 
              WHERE e.idetudiant = :student_id 
              AND cp.annee_acad_idannee_acad = :annee_acad 
              AND cp.est_actif = 1";

$stmtChef = $connexion->prepare($queryChef);
$stmtChef->bindParam(':student_id', $_SESSION['student_id']);
$stmtChef->bindParam(':annee_acad', $_SESSION['annee_acad']);
$stmtChef->execute();

$chefPromotion = $stmtChef->fetch(PDO::FETCH_ASSOC);

if (!$chefPromotion) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Accès refusé',
                text: 'Vous n\'êtes pas autorisé à soumettre le suivi des enseignements. Seuls les chefs de promotion peuvent effectuer cette action.'
            }).then(() => {
                window.location.href = '../portail/student';
            });
        });
    </script>";
    exit();
}

// Fonction pour récupérer les ECUE de la promotion qui ne sont pas encore terminés
function getECUEsDisponibles($connexion, $studentId, $anneeAcad) {
    $query = "SELECT DISTINCT e.\"idECUE\", e.\"designationECUE\", e.CMI, e.TD, e.TP, u.\"designationUE\",
                     COALESCE(SUM(CASE WHEN se.type_cours = 'CM' THEN EXTRACT(EPOCH FROM (se.heure_fin - se.heure_debut)) / 3600.0 ELSE 0 END), 0) as heures_cm_utilisees,
                     COALESCE(SUM(CASE WHEN se.type_cours = 'TD' THEN EXTRACT(EPOCH FROM (se.heure_fin - se.heure_debut)) / 3600.0 ELSE 0 END), 0) as heures_td_utilisees,
                     COALESCE(SUM(CASE WHEN se.type_cours = 'TP' THEN EXTRACT(EPOCH FROM (se.heure_fin - se.heure_debut)) / 3600.0 ELSE 0 END), 0) as heures_tp_utilisees
              FROM ecue e
              INNER JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
              INNER JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
              INNER JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
              INNER JOIN etudiant et ON et.promotion_idpromotion = p.idpromotion
              LEFT JOIN suivi_enseignements se ON e.\"idECUE\" = se.\"idECUE\" AND se.annee_acad_idannee_acad = :annee_acad
              WHERE et.idetudiant = :student_id
              AND et.annee_acad_idannee_acad = :annee_acad
              AND e.\"estVisible\" = 1
              GROUP BY e.\"idECUE\", e.\"designationECUE\", e.CMI, e.TD, e.TP, u.\"designationUE\"
              HAVING (e.CMI > heures_cm_utilisees) OR (e.TD > heures_td_utilisees) OR (e.TP > heures_tp_utilisees)
              ORDER BY u.\"designationUE\", e.\"designationECUE\"";
    
    $stmt = $connexion->prepare($query);
    $stmt->bindParam(':student_id', $studentId);
    $stmt->bindParam(':annee_acad', $anneeAcad);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation des données
        $idECUE = filter_input(INPUT_POST, 'idECUE', FILTER_VALIDATE_INT);
        $date_cours = filter_input(INPUT_POST, 'date_cours', FILTER_SANITIZE_STRING);
        $heure_debut = filter_input(INPUT_POST, 'heure_debut', FILTER_SANITIZE_STRING);
        $heure_fin = filter_input(INPUT_POST, 'heure_fin', FILTER_SANITIZE_STRING);
        $type_cours = filter_input(INPUT_POST, 'type_cours', FILTER_SANITIZE_STRING);
        $enseignant_id = filter_input(INPUT_POST, 'enseignant_id', FILTER_VALIDATE_INT);
        $salle = filter_input(INPUT_POST, 'salle', FILTER_SANITIZE_STRING);
        $commentaire = filter_input(INPUT_POST, 'commentaire', FILTER_SANITIZE_STRING);

        // Validation des champs obligatoires
        if (!$idECUE || !$date_cours || !$heure_debut || !$heure_fin || !$type_cours) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }

        // Validation de la date (ne peut pas être dans le futur)
        $dateActuelle = date('Y-m-d');
        if ($date_cours > $dateActuelle) {
            throw new Exception("La date du cours ne peut pas être dans le futur.");
        }

        // Validation des heures
        if ($heure_debut >= $heure_fin) {
            throw new Exception("L'heure de début doit être antérieure à l'heure de fin.");
        }

        // Vérifier si l'ECUE appartient à la promotion de l'étudiant et récupérer les heures définies
        $queryECUE = "SELECT e.\"idECUE\", e.\"designationECUE\", e.CMI, e.TD, e.TP, u.\"designationUE\"
                      FROM ecue e
                      INNER JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                      INNER JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                      INNER JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                      INNER JOIN etudiant et ON et.promotion_idpromotion = p.idpromotion
                      WHERE e.\"idECUE\" = :\"idECUE\" 
                      AND et.idetudiant = :student_id
                      AND et.annee_acad_idannee_acad = :annee_acad";

        $stmtECUE = $connexion->prepare($queryECUE);
        $stmtECUE->bindParam(':idECUE', $idECUE);
        $stmtECUE->bindParam(':student_id', $_SESSION['student_id']);
        $stmtECUE->bindParam(':annee_acad', $_SESSION['annee_acad']);
        $stmtECUE->execute();

        $ecueInfo = $stmtECUE->fetch(PDO::FETCH_ASSOC);
        if (!$ecueInfo) {
            throw new Exception("Cette matière n'appartient pas à votre promotion.");
        }

        // Vérifier que l'ECUE fait partie des cours non terminés
        $ecuesDisponibles = getECUEsDisponibles($connexion, $_SESSION['student_id'], $_SESSION['annee_acad']);
        $ecueDisponible = false;
        foreach ($ecuesDisponibles as $ecue) {
            if ($ecue['idECUE'] == $idECUE) {
                $ecueDisponible = true;
                break;
            }
        }
        
        if (!$ecueDisponible) {
            throw new Exception("Ce cours a déjà atteint le nombre d'heures maximum défini ou n'est plus disponible pour le suivi.");
        }

        // Calculer la durée du cours en heures
        $debut = new DateTime($heure_debut);
        $fin = new DateTime($heure_fin);
        $duree = $debut->diff($fin);
        $heuresCours = $duree->h + ($duree->i / 60); // Convertir en heures décimales

        // Vérifier les heures déjà enregistrées pour ce type de cours
        $queryHeuresExistantes = "SELECT COALESCE(SUM(
                                    EXTRACT(EPOCH FROM (heure_fin - heure_debut)) / 3600.0
                                  ), 0) as heures_utilisees
                                  FROM suivi_enseignements
                                  WHERE chef_promotion_id = :chef_id
                                  AND \"idECUE\" = :idECUE
                                  AND type_cours = :type_cours
                                  AND annee_acad_idannee_acad = :annee_acad";

        $stmtHeuresExistantes = $connexion->prepare($queryHeuresExistantes);
        $stmtHeuresExistantes->bindParam(':chef_id', $chefPromotion['id_chef']);
        $stmtHeuresExistantes->bindParam(':idECUE', $idECUE);
        $stmtHeuresExistantes->bindParam(':type_cours', $type_cours);
        $stmtHeuresExistantes->bindParam(':annee_acad', $_SESSION['annee_acad']);
        $stmtHeuresExistantes->execute();

        $heuresExistantes = $stmtHeuresExistantes->fetch(PDO::FETCH_ASSOC)['heures_utilisees'];

        // Vérifier si l'ajout de ce cours ne dépasse pas les heures définies
        $heuresDefinies = 0;
        switch ($type_cours) {
            case 'CM':
                $heuresDefinies = $ecueInfo['CMI'] ?? 0;
                break;
            case 'TD':
                $heuresDefinies = $ecueInfo['TD'] ?? 0;
                break;
            case 'TP':
                $heuresDefinies = $ecueInfo['TP'] ?? 0;
                break;
        }

        if (($heuresExistantes + $heuresCours) > $heuresDefinies) {
            $heuresRestantes = $heuresDefinies - $heuresExistantes;
            throw new Exception("Ce cours dépasserait les heures définies pour le type '$type_cours'. Heures définies: $heuresDefinies, Heures déjà utilisées: $heuresExistantes, Heures restantes: $heuresRestantes");
        }

        // Vérifier si l'enseignant existe (si fourni)
        if ($enseignant_id) {
            $queryEnseignant = "SELECT \"idAgent\" FROM agent WHERE \"idAgent\" = :enseignant_id AND type_agent = 'Enseignant'";
            $stmtEnseignant = $connexion->prepare($queryEnseignant);
            $stmtEnseignant->bindParam(':enseignant_id', $enseignant_id);
            $stmtEnseignant->execute();

            if (!$stmtEnseignant->fetch()) {
                throw new Exception("L'enseignant sélectionné n'existe pas.");
            }
        }

        // Vérifier s'il n'y a pas déjà un enregistrement pour cette matière à cette date et heure
        $queryDuplicate = "SELECT id_suivi 
                          FROM suivi_enseignements 
                          WHERE chef_promotion_id = :chef_id 
                          AND \"idECUE\" = :\"idECUE\" 
                          AND date_cours = :date_cours 
                          AND ((heure_debut <= :heure_debut AND heure_fin > :heure_debut) 
                               OR (heure_debut < :heure_fin AND heure_fin >= :heure_fin)
                               OR (heure_debut >= :heure_debut AND heure_fin <= :heure_fin))";

        $stmtDuplicate = $connexion->prepare($queryDuplicate);
        $stmtDuplicate->bindParam(':chef_id', $chefPromotion['id_chef']);
        $stmtDuplicate->bindParam(':idECUE', $idECUE);
        $stmtDuplicate->bindParam(':date_cours', $date_cours);
        $stmtDuplicate->bindParam(':heure_debut', $heure_debut);
        $stmtDuplicate->bindParam(':heure_fin', $heure_fin);
        $stmtDuplicate->execute();

        if ($stmtDuplicate->fetch()) {
            throw new Exception("Il existe déjà un enregistrement pour cette matière à cette date et dans cette plage horaire.");
        }

        // Insérer le nouveau suivi d'enseignement
        $queryInsert = "INSERT INTO suivi_enseignements 
                       (chef_promotion_id, \"idECUE\", date_cours, heure_debut, heure_fin, type_cours, 
                        enseignant_id, salle, commentaire, annee_acad_idannee_acad, \"idUser\") 
                       VALUES 
                       (:chef_id, :\"idECUE\", :date_cours, :heure_debut, :heure_fin, :type_cours, 
                        :enseignant_id, :salle, :commentaire, :annee_acad, :\"idUser\")";

        $stmtInsert = $connexion->prepare($queryInsert);
        $stmtInsert->bindParam(':chef_id', $chefPromotion['id_chef']);
        $stmtInsert->bindParam(':idECUE', $idECUE);
        $stmtInsert->bindParam(':date_cours', $date_cours);
        $stmtInsert->bindParam(':heure_debut', $heure_debut);
        $stmtInsert->bindParam(':heure_fin', $heure_fin);
        $stmtInsert->bindParam(':type_cours', $type_cours);
        $stmtInsert->bindParam(':enseignant_id', $enseignant_id);
        $stmtInsert->bindParam(':salle', $salle);
        $stmtInsert->bindParam(':commentaire', $commentaire);
        $stmtInsert->bindParam(':annee_acad', $_SESSION['annee_acad']);
        $stmtInsert->bindParam(':idUser', $_SESSION['student_id']);

        if ($stmtInsert->execute()) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: 'Le suivi d\'enseignement a été soumis avec succès.'
                    }).then(() => {
                        window.location.href = '../portail/student#suivi-enseignements';
                    });
                });
            </script>";
        } else {
            throw new Exception("Erreur lors de l'enregistrement du suivi d'enseignement.");
        }

    } catch (Exception $e) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: '" . addslashes($e->getMessage()) . "'
                }).then(() => {
                    window.location.href = '../portail/student#suivi-enseignements';
                });
            });
        </script>";
    }
    exit();
}

// Si c'est une requête GET avec le paramètre 'action=get_ecues_disponibles', retourner les ECUE disponibles en JSON
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_ecues_disponibles') {
    header('Content-Type: application/json');
    $ecuesDisponibles = getECUEsDisponibles($connexion, $_SESSION['student_id'], $_SESSION['annee_acad']);
    echo json_encode($ecuesDisponibles);
    exit();
}

// Si ce n'est pas une requête POST, rediriger vers la page étudiant
echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
echo "<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'warning',
            title: 'Accès direct non autorisé',
            text: 'Vous ne pouvez pas accéder directement à cette page.'
        }).then(() => {
            window.location.href = '../portail/student';
        });
    });
</script>";
exit();
?>