<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

// Fonctions utilitaires pour le contrôle d'accès
function getUserSections($db, $userId, $anneeAcadId) {
    $query = 'SELECT section_idsection FROM responsable_section
              WHERE "idUser" = :userId AND annee_acad_idannee_acad = :anneeId';
    $stmt = $db->prepare($query);
    $stmt->execute(['userId' => $userId, 'anneeId' => $anneeAcadId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getCurrentAcademicYear($db) {
    $query = "SELECT idannee_acad FROM annee_acad WHERE est_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['idannee_acad'] : null;
}

function isSubjectAccessible($db, $sujetId, $userSections, $hasFullAccess) {
    if ($hasFullAccess) {
        return true;
    }
    
    if (empty($userSections)) {
        return false;
    }
    
    $sectionsParams = str_repeat('?,', count($userSections) - 1) . '?';
    $query = "SELECT COUNT(*) as count
              FROM sujets s
              LEFT JOIN specialisation spec ON s.\"idSpecialisation\" = spec.\"idSpecialisation\"
              LEFT JOIN orientation o ON spec.idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              WHERE s.idsujets = ? AND sec.idsection IN ($sectionsParams)";
    
    $params = array_merge([$sujetId], $userSections);
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] > 0;
}

function isSpecialisationAccessible($db, $specialisationId, $userSections, $hasFullAccess) {
    if ($hasFullAccess) {
        return true;
    }
    
    if (empty($userSections)) {
        return false;
    }
    
    $sectionsParams = str_repeat('?,', count($userSections) - 1) . '?';
    $query = "SELECT COUNT(*) as count
              FROM specialisation spec
              LEFT JOIN orientation o ON spec.idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              WHERE spec.\"idSpecialisation\" = ? AND sec.idsection IN ($sectionsParams)";
    
    $params = array_merge([$specialisationId], $userSections);
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] > 0;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération de l'action (create ou update)
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // Récupération des données communes
    $intitule = isset($_POST['intitule']) ? trim($_POST['intitule']) : '';
    $cycle = isset($_POST['cycle']) ? trim($_POST['cycle']) : '';
    $idSpecialisation = isset($_POST['idSpecialisation']) ? intval($_POST['idSpecialisation']) : 0;
    $anneeAcadId = isset($_POST['annee_acad']) ? intval($_POST['annee_acad']) : 0;
    $idUser = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
    $etatSujet = isset($_POST['etatSujet']) ? trim($_POST['etatSujet']) : 'En attente';
    
    // Récupération des données optionnelles
    $etudiantId = isset($_POST['etudiant']) && !empty($_POST['etudiant']) ? intval($_POST['etudiant']) : null;
    $directeurId = isset($_POST['directeur']) && !empty($_POST['directeur']) ? intval($_POST['directeur']) : null;
    $encadreurId = isset($_POST['encadreur']) && !empty($_POST['encadreur']) ? intval($_POST['encadreur']) : null;

    try {
        $pdo = Connexion::getInstance()->getPDO();
        $result = false;
        $message = '';
        
        // Vérification des droits d'accès
        $currentUserId = $_SESSION['id']; 
        $hasFullAccess = $_SESSION['idRole'] == 1; // Administrateur
        $currentAcademicYear = getCurrentAcademicYear($pdo);
        $userSections = [];
        
        if (!$hasFullAccess && $currentAcademicYear) {
            $userSections = getUserSections($pdo, $currentUserId, $currentAcademicYear);
        }

        switch ($action) {
            case 'create':
            case 'update':
                // Validation des données obligatoires uniquement pour création et modification
                if (empty($intitule) || empty($cycle) || $idSpecialisation <= 0 || $anneeAcadId <= 0 || $idUser <= 0) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Tous les champs obligatoires doivent être remplis.'
                        }).then(() => {
                            window.location.href = '../recherche/affectation';
                        });
                    </script>";
                    exit();
                }

                // Vérifier si l'utilisateur peut accéder à cette spécialisation
                if (!isSpecialisationAccessible($pdo, $idSpecialisation, $userSections, $hasFullAccess)) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Accès refusé',
                            text: 'Vous n\'avez pas les droits pour créer/modifier un sujet dans cette spécialisation.'
                        }).then(() => {
                            window.location.href = '../recherche/affectation';
                        });
                    </script>";
                    exit();
                }

                if ($action === 'create') {
                    // Création du sujet avec requête directe
                    $query = "INSERT INTO sujets (intitule, cycle, \"idSpecialisation\", annee_acad_idannee_acad,
                              \"idUser\", statut_validation, etudiant_idetudiant, \"idDirecteur\", \"idEncadreur\")
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($query);
                    $result = $stmt->execute([$intitule, $cycle, $idSpecialisation, $anneeAcadId, 
                                             $idUser, $etatSujet, $etudiantId, $directeurId, $encadreurId]);
                    
                    $message = $result ? 'Le sujet de recherche a été créé avec succès.' : 'Une erreur est survenue lors de la création du sujet.';
                } else {
                    // Récupération de l'ID du sujet pour la modification
                    $idSujet = isset($_POST['idsujets']) ? intval($_POST['idsujets']) : 0;
                    if ($idSujet <= 0) {
                        echo "<script>
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: 'ID du sujet invalide.'
                            }).then(() => {
                                window.location.href = '../recherche/affectation';
                            });
                        </script>";
                        exit();
                    }

                    // Vérifier si l'utilisateur peut modifier ce sujet
                    if (!isSubjectAccessible($pdo, $idSujet, $userSections, $hasFullAccess)) {
                        echo "<script>
                            Swal.fire({
                                icon: 'error',
                                title: 'Accès refusé',
                                text: 'Vous n\'avez pas les droits pour modifier ce sujet.'
                            }).then(() => {
                                window.location.href = '../recherche/affectation';
                            });
                        </script>";
                        exit();
                    }

                    // Modification du sujet avec requête directe
                    $query = "UPDATE sujets SET
                              intitule = ?,
                              cycle = ?,
                              \"idSpecialisation\" = ?,
                              annee_acad_idannee_acad = ?,
                              statut_validation = ?,
                              etudiant_idetudiant = ?,
                              \"idDirecteur\" = ?,
                              \"idEncadreur\" = ?
                              WHERE idsujets = ?";
                    $stmt = $pdo->prepare($query);
                    $result = $stmt->execute([$intitule, $cycle, $idSpecialisation, $anneeAcadId, 
                                           $etatSujet, $etudiantId, $directeurId, $encadreurId, $idSujet]);
                    
                    $message = $result ? 'Le sujet de recherche a été modifié avec succès.' : 'Une erreur est survenue lors de la modification du sujet.';
                }
                break;
            
            case 'update2':
                // Récupération des données pour la mise à jour simplifiée
                $idSujet = isset($_POST['idsujets']) ? intval($_POST['idsujets']) : 0;
                $intitule = isset($_POST['intitule']) ? trim($_POST['intitule']) : '';
                $idSpecialisation = isset($_POST['idSpecialisation']) ? intval($_POST['idSpecialisation']) : 0;
                $encadreurId = isset($_POST['encadreur']) && !empty($_POST['encadreur']) ? intval($_POST['encadreur']) : null;

                // Validation des données
                if ($idSujet <= 0 || empty($intitule) || $idSpecialisation <= 0) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Tous les champs obligatoires doivent être remplis.'
                        }).then(() => {
                            window.location.href = '../recherche/projet.recherche';
                        });
                    </script>";
                    exit();
                }

                // Récupérer l'ID de l'enseignant connecté
                $query = "SELECT a.\"idAgent\" FROM agent a
                         INNER JOIN t_users u ON a.\"idAgent\" = u.\"idAgent\"
                         WHERE u.\"idUser\" = ? AND a.type_agent = 'Enseignant'";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$idUser]);
                $idEnseignant = $stmt->fetchColumn();

                if (!$idEnseignant) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Vous n\'êtes pas autorisé à effectuer cette action.'
                        }).then(() => {
                            window.location.href = '../recherche/projet.recherche';
                        });
                    </script>";
                    exit();
                }

                // Vérifier si l'enseignant est le directeur du sujet
                $queryCheck = "SELECT idsujets FROM sujets WHERE idsujets = ? AND idDirecteur = ?";
                $stmtCheck = $pdo->prepare($queryCheck);
                $stmtCheck->execute([$idSujet, $idEnseignant]);
                $canModify = $stmtCheck->rowCount() > 0;

                if ($canModify) {
                    // Mettre à jour le sujet
                    $query = "UPDATE sujets SET
                             intitule = ?,
                             \"idSpecialisation\" = ?,
                             \"idEncadreur\" = ?,
                             statut_validation = 'Modifié'
                             WHERE idsujets = ?";
                    $stmt = $pdo->prepare($query);
                    $result = $stmt->execute([$intitule, $idSpecialisation, $encadreurId, $idSujet]);

                    // Ajouter l'historique de la modification
                    if ($result) {
                        $queryHistory = "INSERT INTO sujet_validation_history
                                        (idsujets, status, date_action, commentaire, \"idUser\")
                                        VALUES (?, 'Modifié', NOW(), 'Modification par le directeur', ?)";
                        $stmtHistory = $pdo->prepare($queryHistory);
                        $stmtHistory->execute([$idSujet, $idUser]);
                    }
                } else {
                    $result = false;
                }

                echo "<script>
                    Swal.fire({
                        icon: '" . ($result ? 'success' : 'error') . "',
                        title: '" . ($result ? 'Succès' : 'Erreur') . "',
                        text: '" . ($result ? 'Le sujet a été modifié avec succès.' : 'Vous n\'êtes pas autorisé à modifier ce sujet ou une erreur est survenue.') . "'
                    }).then(() => {
                        window.location.href = '../recherche/projet.recherche';
                    });
                </script>";
                exit();
                break;

            case 'delete':
                // Suppression du sujet
                $idSujet = isset($_POST['idsujets']) ? intval($_POST['idsujets']) : 0;
                if ($idSujet <= 0) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'ID du sujet invalide.'
                        }).then(() => {
                            window.location.href = '../recherche/affectation';
                        });
                    </script>";
                    exit();
                }
                
                // Vérifier si l'utilisateur peut supprimer ce sujet
                if (!isSubjectAccessible($pdo, $idSujet, $userSections, $hasFullAccess)) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Accès refusé',
                            text: 'Vous n\'avez pas les droits pour supprimer ce sujet.'
                        }).then(() => {
                            window.location.href = '../recherche/affectation';
                        });
                    </script>";
                    exit();
                }
                
                // Vérifier si des tâches sont liées au sujet
                $queryCheckTasks = "SELECT COUNT(*) FROM taches WHERE sujets_idsujets = ?";
                $stmtCheckTasks = $pdo->prepare($queryCheckTasks);
                $stmtCheckTasks->execute([$idSujet]);
                $taskCount = $stmtCheckTasks->fetchColumn();
                
                if ($taskCount > 0) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Ce sujet possède des tâches associées. Veuillez d\'abord supprimer les tâches.'
                        }).then(() => {
                            window.location.href = '../recherche/affectation';
                        });
                    </script>";
                    exit();
                }
                
                // Supprimer l'historique des validations d'abord
                $queryDeleteHistory = "DELETE FROM sujet_validation_history WHERE idsujets = ?";
                $stmtDeleteHistory = $pdo->prepare($queryDeleteHistory);
                $stmtDeleteHistory->execute([$idSujet]);
                
                // Supprimer le sujet
                $queryDelete = "DELETE FROM sujets WHERE idsujets = ?";
                $stmtDelete = $pdo->prepare($queryDelete);
                $result = $stmtDelete->execute([$idSujet]);
                
                $message = $result ? 'Le sujet de recherche a été supprimé avec succès.' : 'Une erreur est survenue lors de la suppression du sujet.';
                break;

            case 'commission_validation':
                // Récupération de l'ID du validateur
                $queryAgent = "SELECT a.\"idAgent\" FROM agent a
                              INNER JOIN t_users u ON a.\"idAgent\" = u.\"idAgent\"
                              WHERE u.\"idUser\" = ?";
                $stmtAgent = $pdo->prepare($queryAgent);
                $stmtAgent->execute([$idUser]);
                $idValidateur = $stmtAgent->fetchColumn();
                
                if (!$idValidateur) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Vous n\'êtes pas autorisé à effectuer cette action.'
                        }).then(() => {
                            window.location.href = '../recherche/choix_etudiant';
                        });
                    </script>";
                    exit();
                }
                
                // Récupération de l'ID du sujet
                $idSujet = isset($_POST['idsujets']) ? intval($_POST['idsujets']) : 0;
                if ($idSujet <= 0) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'ID du sujet invalide.'
                        }).then(() => {
                            window.location.href = '../recherche/choix_etudiant';
                        });
                    </script>";
                    exit();
                }
                
                // Si le statut est "Validé", vérifier que le sujet a un directeur et un étudiant
                $statut_validation = $_POST['statut_validation'];
                if ($statut_validation === 'Validé') {
                    $directeurId = isset($_POST['directeur']) && !empty($_POST['directeur']) ? intval($_POST['directeur']) : null;
                    $etudiantId = isset($_POST['etudiant']) && !empty($_POST['etudiant']) ? intval($_POST['etudiant']) : null;
                    
                    if (!$directeurId || !$etudiantId) {
                        echo "<script>
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation impossible',
                                text: 'Un sujet doit avoir au minimum un directeur et un étudiant assignés pour être validé.'
                            }).then(() => {
                                window.location.href = '../recherche/choix_etudiant';
                            });
                        </script>";
                        exit();
                                        }
                }
                
                // Récupération du commentaire de la commission
                $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
                
                // Mise à jour du statut du sujet
                $queryUpdateStatus = "UPDATE sujets SET 
                                     statut_validation = ?,
                                     commentaire_commission = ?,
                                     date_validation = NOW(),
                                     idValidateur = ?
                                     WHERE idsujets = ?";
                $stmtUpdateStatus = $pdo->prepare($queryUpdateStatus);
                $result = $stmtUpdateStatus->execute([$statut_validation, $commentaire, $idValidateur, $idSujet]);
                
                // Enregistrer l'historique de validation
                if ($result) {
                    $queryHistory = "INSERT INTO sujet_validation_history
                                    (idsujets, status, date_action, commentaire, \"idUser\")
                                    VALUES (?, ?, NOW(), ?, ?)";
                    $stmtHistory = $pdo->prepare($queryHistory);
                    $stmtHistory->execute([$idSujet, $statut_validation, $commentaire, $idUser]);
                }
                
                $message = $result ? 'Le statut du sujet a été mis à jour avec succès.' : 'Une erreur est survenue lors de la mise à jour du statut.';
                
                echo "<script>
                    Swal.fire({
                        icon: '" . ($result ? 'success' : 'error') . "',
                        title: '" . ($result ? 'Succès' : 'Erreur') . "',
                        text: '" . $message . "'
                    }).then(() => {
                        window.location.href = '../recherche/choix_etudiant';
                    });
                </script>";
                exit();
                break;
                
            default:
                // Action non reconnue
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Action non reconnue.'
                    }).then(() => {
                        window.location.href = '../index.php';
                    });
                </script>";
                exit();
        }
        
        // Affichage du message de succès ou d'erreur
        if ($action != 'commission_validation') {
            // Déterminer la page de redirection selon l'action
            $redirect = ($action == 'update2') ? '../recherche/projet.recherche' : '../recherche/affectation';
            
            echo "<script>
                Swal.fire({
                    icon: '" . ($result ? 'success' : 'error') . "',
                    title: '" . ($result ? 'Succès' : 'Erreur') . "',
                    text: '" . $message . "'
                }).then(() => {
                    window.location.href = '$redirect';
                });
            </script>";
        }
        
    } catch (PDOException $e) {
        // Gestion des erreurs de base de données
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur de base de données',
                text: 'Une erreur est survenue : " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../index.php';
            });
        </script>";
    }
} else {
    // Méthode HTTP non autorisée
    header('HTTP/1.1 405 Method Not Allowed');
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Méthode non autorisée'
        }).then(() => {
            window.location.href = '../index.php';
        });
    </script>";
}

