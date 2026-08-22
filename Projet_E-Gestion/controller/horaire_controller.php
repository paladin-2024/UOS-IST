<?php
error_reporting(E_ALL); ini_set("display_errors", 1);
session_start();
require_once dirname(__DIR__) . '/views/405.php'; // Importer la page qui contient SweetAlert
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Horaire.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/JournalServeur.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit;
}

// Créer une instance de la classe Horaire
$horaire = new Horaire();
$universite = new Universite();
$journalServeur = new JournalServeur();

// Récupérer l'action demandée
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Récupérer les paramètres de redirection
$idPromotion = isset($_REQUEST['promotion']) ? intval($_REQUEST['promotion']) : 0;
$weekOffset = isset($_REQUEST['week']) ? intval($_REQUEST['week']) : 0;
$redirectParams = "promotion=$idPromotion&week=$weekOffset";

// Vérifier si l'utilisateur est administrateur
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$userId = $_SESSION['id'];

// Fonction pour vérifier si l'utilisateur a accès à une promotion
function hasAccessToPromotion($promotionId, $userId, $isAdmin, $universite) {
    if ($isAdmin) {
        return true; // Les administrateurs ont accès à tout
    }
    
    // Récupérer l'année académique actuelle
    $currentYear = $universite->getCurrentAcademicYear();
    if (!$currentYear) {
        return false;
    }
    
    // Vérifier si l'utilisateur est responsable de la section de cette promotion
    $pdo = Connexion::getInstance()->getPDO();
    $query = "SELECT COUNT(*) 
              FROM responsable_section rs
              INNER JOIN section s ON s.idsection = rs.section_idsection
              INNER JOIN orientation o ON o.section_idsection = s.idsection
              INNER JOIN promotion p ON p.orientation_idorientation = o.idorientation
              WHERE rs.idUser = :userId 
              AND rs.annee_acad_idannee_acad = :anneeId
              AND p.idpromotion = :promotionId";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':userId', $userId);
    $stmt->bindParam(':anneeId', $currentYear['idannee_acad']);
    $stmt->bindParam(':promotionId', $promotionId);
    $stmt->execute();
    
    return $stmt->fetchColumn() > 0;
}

// Fonction pour vérifier si l'utilisateur a accès à un horaire via l'ECUE
function hasAccessToHoraire($horaireId, $userId, $isAdmin, $universite) {
    if ($isAdmin) {
        return true; // Les administrateurs ont accès à tout
    }
    
    // Récupérer la promotion liée à cet horaire via l'ECUE
    $pdo = Connexion::getInstance()->getPDO();
    $query = "SELECT p.idpromotion
              FROM horaire h
              INNER JOIN ecue e ON e.idECUE = h.ecue_idECUE
              INNER JOIN ue u ON u.idUE = e.ue_idUE
              INNER JOIN semestre sem ON sem.idsemestre = u.semestre_idsemestre
              INNER JOIN promotion p ON p.idpromotion = sem.promotion_idpromotion
              WHERE h.idhoraire = :horaireId";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':horaireId', $horaireId);
    $stmt->execute();
    $promotionId = $stmt->fetchColumn();
    
    if (!$promotionId) {
        return false;
    }
    
    return hasAccessToPromotion($promotionId, $userId, $isAdmin, $universite);
}

switch ($action) {
    case 'add_horaire_tronc':
        // Récupérer les données du formulaire
        $promotions = isset($_POST['promotions']) ? $_POST['promotions'] : [];
        $jour = isset($_POST['jour']) ? trim($_POST['jour']) : '';
        $heureDebut = isset($_POST['heure_debut']) ? trim($_POST['heure_debut']) : '';
        $heureFin = isset($_POST['heure_fin']) ? trim($_POST['heure_fin']) : '';
        $salle = isset($_POST['salle']) ? trim($_POST['salle']) : '';
        $idECUE = isset($_POST['idECUE']) ? intval($_POST['idECUE']) : 0;
        $typeCours = isset($_POST['type_cours']) ? trim($_POST['type_cours']) : 'CM';
        $idAnneeAcad = isset($_POST['idAnneeAcad']) ? intval($_POST['idAnneeAcad']) : 0;
        $idUser = $_SESSION['id'];
        $date_cours = isset($_POST['date_cours']) ? trim($_POST['date_cours']) : '';

        // Validation des données
        if (empty($promotions) || empty($jour) || empty($heureDebut) || empty($heureFin) || empty($salle) || $idECUE <= 0 || $idAnneeAcad <= 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Tous les champs sont obligatoires.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
            exit();
        }

        // Vérifier que l'heure de fin est après l'heure de début
        if ($heureDebut >= $heureFin) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'L\\'heure de fin doit être postérieure à l\\'heure de début.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
            exit();
        }

        // Récupérer la désignation de l'ECUE sélectionnée
        $pdo = Connexion::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT designationECUE FROM ecue WHERE idECUE = :idECUE");
        $stmt->execute(['idECUE' => $idECUE]);
        $ecueData = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ecueData) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'ECUE non trouvée.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
            exit();
        }
        $designationECUE = $ecueData['designationECUE'];

        // Créer les horaires pour chaque promotion sélectionnée
        $successCount = 0;
        $errors = [];

        foreach ($promotions as $promotionId) {
            // Vérifier l'accès à la promotion
            if (!hasAccessToPromotion($promotionId, $userId, $isAdmin, $universite)) {
                $errors[] = "Accès refusé pour la promotion $promotionId";
                continue;
            }

            // Trouver l'ECUE correspondante dans cette promotion
            $stmt = $pdo->prepare("
                SELECT e.idECUE
                FROM ecue e
                JOIN ue u ON e.UE_idUE = u.idUE
                JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                WHERE s.promotion_idpromotion = :promotionId
                AND e.designationECUE = :designationECUE
                LIMIT 1
            ");
            $stmt->execute([
                'promotionId' => $promotionId,
                'designationECUE' => $designationECUE
            ]);
            $promotionEcue = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$promotionEcue) {
                $errors[] = "ECUE '" . addslashes($designationECUE) . "' non trouvée pour la promotion $promotionId";
                continue;
            }

            // Créer l'horaire avec l'ECUE de cette promotion (avec skipConflicts = true pour tronc commun)
            $result = $horaire->addHoraire($jour, $heureDebut, $heureFin, $salle, $promotionEcue['idECUE'], $idAnneeAcad, $idUser, $typeCours, $date_cours, true);

            if ($result['success']) {
                $successCount++;
                
                // Journaliser l'ajout d'horaire en tronc commun
                $description = "Horaire tronc commun créé pour l'ECUE '$designationECUE' - Jour: $jour, Salle: $salle, Horaire: $heureDebut - $heureFin";
                $journalServeur->enregistrerAction(
                    'CREATE',
                    'HORAIRE',
                    $description,
                    $userId,
                    $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
                    'horaire',
                    $result['id'] ?? null,
                    null,
                    ['jour' => $jour, 'heure_debut' => $heureDebut, 'heure_fin' => $heureFin, 'salle' => $salle, 'type_cours' => $typeCours, 'tronc_commun' => true],
                    'succes'
                );
            } else {
                $errors[] = "Erreur pour la promotion $promotionId: " . $result['message'];
                
                // Journaliser l'erreur
                $journalServeur->enregistrerAction(
                    'CREATE',
                    'HORAIRE',
                    "Tentative de création d'horaire tronc commun - ECUE: $designationECUE",
                    $userId,
                    $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
                    'horaire',
                    null,
                    null,
                    null,
                    'erreur',
                    $result['message']
                );
            }
        }

        if ($successCount > 0) {
            $message = "$successCount horaire(s) créé(s) avec succès.";
            if (!empty($errors)) {
                $message .= " Erreurs: " . implode(', ', $errors);
            }
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: " . json_encode($message) . "
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
        } else {
            $errorText = 'Aucun horaire n\'a pu être créé. Erreurs: ' . implode(', ', $errors);
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: " . json_encode($errorText) . "
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
        }
        break;

    case 'add_horaire':
        // Récupérer les données du formulaire
        $jour = isset($_POST['jour']) ? trim($_POST['jour']) : '';
        $heureDebut = isset($_POST['heure_debut']) ? trim($_POST['heure_debut']) : '';
        $heureFin = isset($_POST['heure_fin']) ? trim($_POST['heure_fin']) : '';
        $salle = isset($_POST['salle']) ? trim($_POST['salle']) : '';
        $idECUE = isset($_POST['idECUE']) ? intval($_POST['idECUE']) : 0;
        $typeCours = isset($_POST['type_cours']) ? trim($_POST['type_cours']) : 'CM';
        $idAnneeAcad = isset($_POST['idAnneeAcad']) ? intval($_POST['idAnneeAcad']) : 0;
        $idUser = $_SESSION['id'];
        $date_cours = isset($_POST['date_cours']) ? trim($_POST['date_cours']) : '';
        $troncCommun = isset($_POST['tronc_commun']) && $_POST['tronc_commun'] == 'on';
        
        // Vérifier l'accès à la promotion
        if (!hasAccessToPromotion($idPromotion, $userId, $isAdmin, $universite)) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Accès refusé',
                    text: 'Vous n\\'avez pas les droits pour ajouter des horaires à cette promotion.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires';
                });
            </script>";
            exit();
        }
        
        // Validation des données
        if (empty($jour) || empty($heureDebut) || empty($heureFin) || empty($salle) || $idECUE <= 0 || $idAnneeAcad <= 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Tous les champs sont obligatoires.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
            exit();
        }
        
        // Vérifier que l'heure de fin est après l'heure de début
        if ($heureDebut >= $heureFin) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'L\\'heure de fin doit être postérieure à l\\'heure de début.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
            exit();
        }
        
        // Ajouter l'horaire
        $result = $horaire->addHoraire($jour, $heureDebut, $heureFin, $salle, $idECUE, $idAnneeAcad, $idUser, $typeCours, $date_cours, $troncCommun);
        
        if ($result['success']) {
            // Journaliser l'ajout d'horaire
            $description = "Horaire créé - Jour: $jour, Salle: $salle, Horaire: $heureDebut - $heureFin, Type: $typeCours";
            $journalServeur->enregistrerAction(
                'CREATE',
                'HORAIRE',
                $description,
                $userId,
                $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
                'horaire',
                $result['id'] ?? null,
                null,
                ['jour' => $jour, 'heure_debut' => $heureDebut, 'heure_fin' => $heureFin, 'salle' => $salle, 'type_cours' => $typeCours, 'tronc_commun' => $troncCommun],
                'succes'
            );
            
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'L\\'horaire a été ajouté avec succès.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
        } else {
            // Journaliser l'erreur
            $journalServeur->enregistrerAction(
                'CREATE',
                'HORAIRE',
                "Tentative de création d'horaire - Jour: $jour, Salle: $salle",
                $userId,
                $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
                'horaire',
                null,
                null,
                null,
                'erreur',
                $result['message']
            );
            
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: '" . addslashes($result['message']) . "'
                }).then(() => {
                    window.history.back();
                });
            </script>";
        }
        break;
    
    case 'edit_horaire':
        // Récupérer les données du formulaire
        $idHoraire = isset($_POST['idHoraire']) ? intval($_POST['idHoraire']) : 0;
        $jour = isset($_POST['jour']) ? trim($_POST['jour']) : '';
        $heureDebut = isset($_POST['heure_debut']) ? trim($_POST['heure_debut']) : '';
        $heureFin = isset($_POST['heure_fin']) ? trim($_POST['heure_fin']) : '';
        $salle = isset($_POST['salle']) ? trim($_POST['salle']) : '';
        $idECUE = isset($_POST['idECUE']) ? intval($_POST['idECUE']) : 0;
        $date_cours = isset($_POST['date_cours']) ? trim($_POST['date_cours']) : '';
        $typeCours = isset($_POST['type_cours']) ? trim($_POST['type_cours']) : 'CM';
        $troncCommun = isset($_POST['tronc_commun']) && $_POST['tronc_commun'] == 'on';
        
        // Vérifier l'accès à l'horaire
        if (!hasAccessToHoraire($idHoraire, $userId, $isAdmin, $universite)) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Accès refusé',
                    text: 'Vous n\\'avez pas les droits pour modifier cet horaire.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
            exit();
        }
        
        // Validation des données
        if (empty($jour) || empty($heureDebut) || empty($heureFin) || empty($salle) || $idECUE <= 0 || $idHoraire <= 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Tous les champs sont obligatoires.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
            exit();
        }
        
        // Vérifier que l'heure de fin est après l'heure de début
        if ($heureDebut >= $heureFin) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'L\\'heure de fin doit être postérieure à l\\'heure de début.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
            exit();
        }
        
        // Récupérer les données avant modification pour le journal
        $donneeAvant = $journalServeur->obtenirDonneeAvant('horaire', $idHoraire);
        
        // Mettre à jour l'horaire
        $result = $horaire->updateHoraire($idHoraire, $jour, $heureDebut, $heureFin, $salle, $idECUE, $typeCours,$date_cours, $troncCommun);
        
        if ($result['success']) {
            // Journaliser la modification d'horaire
            $description = "Horaire modifié - Jour: $jour, Salle: $salle, Horaire: $heureDebut - $heureFin, Type: $typeCours";
            $journalServeur->enregistrerAction(
                'UPDATE',
                'HORAIRE',
                $description,
                $userId,
                $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
                'horaire',
                $idHoraire,
                $donneeAvant,
                ['jour' => $jour, 'heure_debut' => $heureDebut, 'heure_fin' => $heureFin, 'salle' => $salle, 'type_cours' => $typeCours, 'tronc_commun' => $troncCommun],
                'succes'
            );
            
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'L\\'horaire a été modifié avec succès.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
        } else {
            // Journaliser l'erreur
            $journalServeur->enregistrerAction(
                'UPDATE',
                'HORAIRE',
                "Tentative de modification d'horaire - Jour: $jour, Salle: $salle",
                $userId,
                $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
                'horaire',
                $idHoraire,
                $donneeAvant,
                null,
                'erreur',
                $result['message']
            );
            
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: '" . addslashes($result['message']) . "'
                }).then(() => {
                    window.history.back();
                });
            </script>";
        }
        break;
    
    case 'delete_horaire':
        // Récupérer l'ID de l'horaire à supprimer
        $idHoraire = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($idHoraire <= 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'ID d\\'horaire invalide.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
            exit();
        }
        
        // Vérifier l'accès à l'horaire
        if (!hasAccessToHoraire($idHoraire, $userId, $isAdmin, $universite)) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Accès refusé',
                    text: 'Vous n\\'avez pas les droits pour supprimer cet horaire.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
            exit();
        }
        
        // Récupérer les données avant suppression pour le journal
        $donneeAvant = $journalServeur->obtenirDonneeAvant('horaire', $idHoraire);
        
        // Supprimer l'horaire
        if ($horaire->deleteHoraire($idHoraire)) {
            // Journaliser la suppression d'horaire
            $description = "Horaire supprimé - ID: $idHoraire";
            $journalServeur->enregistrerAction(
                'DELETE',
                'HORAIRE',
                $description,
                $userId,
                $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
                'horaire',
                $idHoraire,
                $donneeAvant,
                null,
                'succes'
            );
            
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'L\\'horaire a été supprimé avec succès.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
        } else {
            // Journaliser l'erreur
            $journalServeur->enregistrerAction(
                'DELETE',
                'HORAIRE',
                "Tentative de suppression d'horaire - ID: $idHoraire",
                $userId,
                $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
                'horaire',
                $idHoraire,
                $donneeAvant,
                null,
                'erreur',
                'Erreur lors de la suppression'
            );
            
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la suppression de l\\'horaire.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
        }
        break;
    
    
    // Ajouter un nouveau cas dans le switch pour la duplication
case 'duplicate_horaire':
    // Récupérer les paramètres
    $idHoraire = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $newDate = isset($_GET['new_date']) ? trim($_GET['new_date']) : '';
    
    if ($idHoraire <= 0 || empty($newDate)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Paramètres invalides pour la duplication.'
            }).then(() => {
                window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
            });
        </script>";
        exit();
    }
    
    // Vérifier l'accès à l'horaire
    if (!hasAccessToHoraire($idHoraire, $userId, $isAdmin, $universite)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Accès refusé',
                text: 'Vous n\\'avez pas les droits pour dupliquer cet horaire.'
            }).then(() => {
                window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
            });
        </script>";
        exit();
    }
    
    // Récupérer les données avant duplication pour le journal
    $donneeAvant = $journalServeur->obtenirDonneeAvant('horaire', $idHoraire);
    
    // Dupliquer l'horaire
    if ($horaire->duplicateHoraire($idHoraire, $newDate)) {
        // Journaliser la duplication d'horaire
        $description = "Horaire dupliqué - ID source: $idHoraire, Nouvelle date: $newDate";
        $journalServeur->enregistrerAction(
            'DUPLICATE',
            'HORAIRE',
            $description,
            $userId,
            $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
            'horaire',
            $idHoraire,
            $donneeAvant,
            ['nouvelle_date' => $newDate],
            'succes'
        );
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'L\\'horaire a été dupliqué avec succès.'
            }).then(() => {
                window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
            });
        </script>";
    } else {
        // Journaliser l'erreur
        $journalServeur->enregistrerAction(
            'DUPLICATE',
            'HORAIRE',
            "Tentative de duplication d'horaire - ID: $idHoraire, Nouvelle date: $newDate",
            $userId,
            $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
            'horaire',
            $idHoraire,
            $donneeAvant,
            null,
            'erreur',
            'Erreur lors de la duplication - chevauchement ou autre'
        );
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la duplication de l\\'horaire. Vérifiez qu\\'il n\\'y a pas de chevauchement à la date sélectionnée.'
            }).then(() => {
                window.history.back();
            });
        </script>";
    }
    break;

    
    default:
        // Redirection par défaut
        header("Location: ../index.php?view=enseignement/horaires&$redirectParams");
        exit();
}




