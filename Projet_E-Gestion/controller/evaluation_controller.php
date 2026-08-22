<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Enseignant.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Accès refusé',
                text: 'Vous devez être connecté pour accéder à cette fonctionnalité.'
            }).then(() => {
                window.location.href = '../login';
            });
        });
    </script>";
    exit();
}

$ecue = new Ecue();
$enseignant = new Enseignant();
$universite = new Universite();

// Récupérer l'instance de connexion
$connexion = Connexion::getInstance();
$pdo = $connexion->getPDO();

// Récupérer l'ID de l'utilisateur et vérifier s'il est un enseignant
$userId = $_SESSION['id'];
if (!$enseignant->isUserEnseignant($userId)) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Accès refusé',
                text: 'Vous devez être un enseignant pour accéder à cette fonctionnalité.'
            }).then(() => {
                window.location.href = '../';
            });
        });
    </script>";
    exit();
}

// Récupérer l'ID de l'agent enseignant
$idEnseignant = $enseignant->getAgentIdByUserId($userId);
if (!$idEnseignant) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Impossible de récupérer vos informations d\'enseignant.'
            }).then(() => {
                window.location.href = '../';
            });
        });
    </script>";
    exit();
}

// Traitement des différentes actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer l'action à partir des données POST
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // Action d'ajout d'une évaluation
    if ($action === 'add_evaluation') {
        // Récupération des données du formulaire
        $titre = htmlspecialchars(trim($_POST['titre']));
        $description = htmlspecialchars(trim($_POST['description']));
        $date_evaluation = $_POST['date_evaluation'];
        $idECUE = intval($_POST['idECUE']);
        $idType = intval($_POST['idType']);
        $session_idsession = intval($_POST['session_idsession']);
        $ponderation = isset($_POST['ponderation']) ? floatval($_POST['ponderation']) : 1.00;
        $note_max = isset($_POST['note_max']) ? floatval($_POST['note_max']) : 20.00;
        $est_visible = isset($_POST['est_visible']) ? 1 : 0;
        $annee_acad_id = intval($_POST['annee_acad_id']);
        $idUser = intval($_POST['idUser']);
        
        try {
            // Préparation de la requête SQL
            $sql = "INSERT INTO evaluations (titre, description, date_evaluation, idECUE, idType, 
                    ponderation, note_max, session_idsession, est_visible, annee_acad_id, idUser) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $resultat = $stmt->execute([
                $titre, $description, $date_evaluation, $idECUE, $idType, 
                $ponderation, $note_max, $session_idsession, $est_visible, $annee_acad_id, $idUser
            ]);
            
            if ($resultat) {
                // Rediriger avec un message de succès
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Succès',
                            text: 'L\'évaluation a été ajoutée avec succès.'
                        }).then(() => {
                            window.location.href = '../?view=enseignement/evaluations&ecue={$idECUE}';
                        });
                    });
                </script>";
            } else {
                // Erreur lors de l'ajout
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Une erreur est survenue lors de l\'ajout de l\'évaluation.'
                        }).then(() => {
                            window.location.href = '../?view=enseignement/evaluations&ecue={$idECUE}';
                        });
                    });
                </script>";
            }
        } catch (PDOException $e) {
            // Erreur de base de données
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Erreur de base de données: " . addslashes($e->getMessage()) . "'
                    }).then(() => {
                        window.location.href = '../?view=enseignement/evaluations&ecue={$idECUE}';
                    });
                });
            </script>";
        }
    }  
    // Action de mise à jour d'une évaluation
    else if ($action === 'update_evaluation') {
        // Récupération des données du formulaire
        $idevaluation = intval($_POST['idevaluation']);
        $titre = htmlspecialchars(trim($_POST['titre']));
        $description = htmlspecialchars(trim($_POST['description']));
        $date_evaluation = $_POST['date_evaluation'];
        $idECUE = intval($_POST['idECUE']);
        $idType = intval($_POST['idType']);
        $session_idsession = intval($_POST['session_idsession']);
        $ponderation = isset($_POST['ponderation']) ? floatval($_POST['ponderation']) : 1.00;
        $note_max = isset($_POST['note_max']) ? floatval($_POST['note_max']) : 20.00;
        $est_visible = isset($_POST['est_visible']) ? 1 : 0;
        
        try {
            // Préparation de la requête SQL
            $sql = "UPDATE evaluations SET 
                    titre = ?, 
                    description = ?, 
                    date_evaluation = ?, 
                    idType = ?, 
                    ponderation = ?, 
                    note_max = ?,
                    session_idsession = ?, 
                    est_visible = ? 
                    WHERE idevaluation = ?";
            
            $stmt = $pdo->prepare($sql);
            $resultat = $stmt->execute([
                $titre, $description, $date_evaluation, $idType, 
                $ponderation, $note_max, $session_idsession, $est_visible, $idevaluation
            ]);
            
            if ($resultat) {
                // Rediriger avec un message de succès
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Succès',
                            text: 'L\'évaluation a été mise à jour avec succès.'
                        }).then(() => {
                            window.location.href = '../?view=enseignement/evaluations&ecue={$idECUE}';
                        });
                    });
                </script>";
            } else {
                // Erreur lors de la mise à jour
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Une erreur est survenue lors de la mise à jour de l\'évaluation.'
                        }).then(() => {
                            window.location.href = '../?view=enseignement/evaluations&ecue={$idECUE}';
                        });
                    });
                </script>";
            }
        } catch (PDOException $e) {
            // Erreur de base de données
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Erreur de base de données: " . addslashes($e->getMessage()) . "'
                    }).then(() => {
                        window.location.href = '../?view=enseignement/evaluations&ecue={$idECUE}';
                    });
                });
            </script>";
        }
    }
   
    // Action d'enregistrement des notes
    else if ($action === 'save_grades') {
        // Récupération des données du formulaire
        $evaluationId = intval($_POST['idevaluation']);
        $idECUE = intval($_POST['idECUE']);
        $sessionId = intval($_POST['session_idsession']);
        $notes = isset($_POST['notes']) ? $_POST['notes'] : [];
        
        // Si aucune note à enregistrer
        if (empty($notes)) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Avertissement',
                        text: 'Aucune note à enregistrer.'
                    }).then(() => {
                        window.location.href = '../?view=enseignement/evaluations&ecue={$idECUE}';
                    });
                });
            </script>";
            exit;
        }
        
        try {
            // Récupérer les informations sur l'évaluation, notamment note_max
            $sqlEval = "SELECT * FROM evaluations WHERE idevaluation = ?";
            $stmtEval = $pdo->prepare($sqlEval);
            $stmtEval->execute([$evaluationId]);
            $evaluation = $stmtEval->fetch(PDO::FETCH_ASSOC);
            
            if (!$evaluation) {
                throw new Exception("Évaluation non trouvée");
            }
            
            // Récupérer l'ID du type d'évaluation
            $typeEvaluationId = $evaluation['idType'];
            $note_max = $evaluation['note_max'];
            
            // Commencer une transaction
            $pdo->beginTransaction();
            
            // Préparer la requête pour insérer/mettre à jour les notes
            $sqlCheckExist = "SELECT COUNT(*) FROM points 
                              WHERE matricule = ? AND ECUE_idECUE = ? AND typeEvaluation = ? AND session_idsession = ? AND annee_acad_id = ?";
            $stmtCheckExist = $pdo->prepare($sqlCheckExist);
            
            $sqlInsert = "INSERT INTO points (coteObtenu, typeEvaluation, ECUE_idECUE, session_idsession, matricule, annee_acad_id) 
                         VALUES (?, ?, ?, ?, ?, ?)";
            $stmtInsert = $pdo->prepare($sqlInsert);
            
            $sqlUpdate = "UPDATE points SET coteObtenu = ? 
                         WHERE matricule = ? AND ECUE_idECUE = ? AND typeEvaluation = ? AND session_idsession = ? AND annee_acad_id = ?";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            
            // Récupérer l'année académique actuelle
            $sqlAnnee = "SELECT * FROM annee_acad 
              WHERE dateCreation = (SELECT MAX(dateCreation) FROM annee_acad)";
            $stmtAnnee = $pdo->prepare($sqlAnnee);
            $stmtAnnee->execute();
            $annee = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
            $anneeId = $annee ? $annee['idannee_acad'] : 0;
            
                        // Enregistrer les notes pour chaque étudiant
                        foreach ($notes as $etudiantId => $note) {
                            // Convertir la note et vérifier qu'elle est valide
                            $noteValue = $note === '' ? null : floatval($note);
                            
                            // Si la note est supérieure à la note max, la limiter
                            if ($noteValue !== null && $noteValue > $note_max) {
                                $noteValue = $note_max;
                            }
                            
                            // Récupérer le matricule de l'étudiant
                            $sqlMatricule = "SELECT matricule FROM etudiant WHERE idetudiant = ?";
                            $stmtMatricule = $pdo->prepare($sqlMatricule);
                            $stmtMatricule->execute([$etudiantId]);
                            $matricule = $stmtMatricule->fetchColumn();
                            
                            if (!$matricule) {
                                continue; // Passer à l'étudiant suivant si matricule non trouvé
                            }
                            
                            // Vérifier si une note existe déjà pour cet étudiant dans cette évaluation
                            $stmtCheckExist->execute([$matricule, $idECUE, $typeEvaluationId, $sessionId, $anneeId]);
                            $exists = $stmtCheckExist->fetchColumn() > 0;
                            
                            if ($exists) {
                                // Mettre à jour la note existante
                                $stmtUpdate->execute([$noteValue, $matricule, $idECUE, $typeEvaluationId, $sessionId, $anneeId]);
                            } else {
                                // Insérer une nouvelle note
                                $stmtInsert->execute([$noteValue, $typeEvaluationId, $idECUE, $sessionId, $matricule, $anneeId]);
                            }
                        }
                        
                        // Valider la transaction
                        $pdo->commit();
                        
                        // Rediriger avec un message de succès
                        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                        echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Succès',
                                    text: 'Les notes ont été enregistrées avec succès.'
                                }).then(() => {
                                    window.location.href = '../?view=enseignement/evaluations&ecue={$idECUE}';
                                });
                            });
                        </script>";
                        
                    } catch (Exception $e) {
                        // En cas d'erreur, annuler la transaction
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        
                        // Afficher l'erreur
                        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                        echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erreur',
                                    text: 'Une erreur est survenue lors de l\'enregistrement des notes: " . addslashes($e->getMessage()) . "'
                                }).then(() => {
                                    window.location.href = '../?view=enseignement/evaluations&ecue={$idECUE}';
                                });
                            });
                        </script>";
                    }
                }
                
                // Action d'enregistrement de la configuration des moyennes
                else if ($action === 'save_config') {
                    // Récupération des données du formulaire
                    $idECUE = intval($_POST['idECUE']);
                    $annee_acad_id = intval($_POST['annee_acad_id']);
                    $session_ids = $_POST['session_ids'] ?? [];
                    $ponderation_cc = $_POST['ponderation_cc'] ?? [];
                    $ponderation_ex = $_POST['ponderation_ex'] ?? [];
                    
                    try {
                        // Commencer une transaction
                        $pdo->beginTransaction();
                        
                        // Pour chaque session, enregistrer la configuration
                        foreach ($session_ids as $key => $session_id) {
                            // Si pas d'ID de session valide, passer à la suivante
                            if (empty($session_id)) continue;
                            
                            $sessionId = intval($session_id);
                            $ponderationCC = isset($ponderation_cc[$sessionId]) ? floatval($ponderation_cc[$sessionId]) : 0.4;
                            $ponderationEX = isset($ponderation_ex[$sessionId]) ? floatval($ponderation_ex[$sessionId]) : 0.6;
                            
                            // Vérifier si une configuration existe déjà pour cette session
                            $sqlCheckConfig = "SELECT id FROM configuration_moyenne 
                                             WHERE idECUE = ? AND session_idsession = ? AND annee_acad_id = ?";
                            $stmtCheckConfig = $pdo->prepare($sqlCheckConfig);
                            $stmtCheckConfig->execute([$idECUE, $sessionId, $annee_acad_id]);
                            $configId = $stmtCheckConfig->fetchColumn();
                            
                            if ($configId) {
                                // Mettre à jour la configuration existante
                                $sqlUpdateConfig = "UPDATE configuration_moyenne 
                                                  SET ponderation_cc = ?, ponderation_ex = ?, idUser = ?, dateCreation = NOW() 
                                                  WHERE id = ?";
                                $stmtUpdateConfig = $pdo->prepare($sqlUpdateConfig);
                                $stmtUpdateConfig->execute([$ponderationCC, $ponderationEX, $userId, $configId]);
                            } else {
                                // Insérer une nouvelle configuration
                                $sqlInsertConfig = "INSERT INTO configuration_moyenne 
                                                  (idECUE, session_idsession, annee_acad_id, formule_cc, formule_ex, 
                                                   ponderation_cc, ponderation_ex, idUser, dateCreation) 
                                                  VALUES (?, ?, ?, 'moyenne', 'derniere', ?, ?, ?, NOW())";
                                $stmtInsertConfig = $pdo->prepare($sqlInsertConfig);
                                $stmtInsertConfig->execute([$idECUE, $sessionId, $annee_acad_id, $ponderationCC, $ponderationEX, $userId]);
                            }
                        }
                        
                        // Valider la transaction
                        $pdo->commit();
                        
                        // Rediriger avec un message de succès
                        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                        echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Succès',
                                    text: 'La configuration des moyennes a été enregistrée avec succès.'
                                }).then(() => {
                                    window.location.href = '../?view=enseignement/evaluations&ecue={$idECUE}';
                                });
                            });
                        </script>";
                        
                    } catch (Exception $e) {
                        // En cas d'erreur, annuler la transaction
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        
                        // Afficher l'erreur
                        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                        echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erreur',
                                    text: 'Une erreur est survenue lors de l\'enregistrement de la configuration: " . addslashes($e->getMessage()) . "'
                                }).then(() => {
                                    window.location.href = '../?view=enseignement/evaluations&ecue={$idECUE}';
                                });
                            });
                        </script>";
                    }
                }
                
                // Action inconnue
                else {
                    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                    echo "<script>
                        document.addEventListener('DOMContentLoaded', function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: 'Action inconnue: " . htmlspecialchars($action) . "'
                            }).then(() => {
                                window.history.back();
                            });
                        });
                    </script>";
                }
            }
            // Traitement des requêtes GET (comme la suppression d'évaluation)
            else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $action = isset($_GET['action']) ? $_GET['action'] : '';
                
                // Action de suppression d'une évaluation
                if ($action === 'delete_evaluation') {
                    $evaluationId = isset($_GET['id']) ? intval($_GET['id']) : 0;
                    $idECUE = isset($_GET['ecue']) ? intval($_GET['ecue']) : 0;
                    
                    if (!$evaluationId || !$idECUE) {
                        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                        echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erreur',
                                    text: 'Paramètres manquants pour la suppression.'
                                }).then(() => {
                                    window.location.href = '../?view=enseignement/evaluations&ecue={$idECUE}';
                                });
                            });
                        </script>";
                        exit;
                    }
                    
                    try {
                        // Commencer une transaction
                        $pdo->beginTransaction();
                        
                        // Récupérer les informations sur l'évaluation
                        $sqlEval = "SELECT * FROM evaluations WHERE idevaluation = ?";
                        $stmtEval = $pdo->prepare($sqlEval);
                        $stmtEval->execute([$evaluationId]);
                        $evaluation = $stmtEval->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$evaluation) {
                            throw new Exception("Évaluation non trouvée");
                        }
                        
                        // Vérifier que l'enseignant est autorisé à supprimer cette évaluation
                        $idECUEEval = $evaluation['idECUE'];
                        $currentYear = $universite->getCurrentAcademicYear();
                        
                        if ($idECUEEval != $idECUE || !$enseignant->isEnseignantAssignedToEcue($idEnseignant, $idECUE, $currentYear['idannee_acad'])) {
                            throw new Exception("Vous n'êtes pas autorisé à supprimer cette évaluation");
                        }
                        
                        // Supprimer d'abord les notes associées à cette évaluation
                        $sqlDeleteNotes = "DELETE FROM points 
                                          WHERE typeEvaluation = ? AND ECUE_idECUE = ? AND session_idsession = ? AND annee_acad_id = ?";
                        $stmtDeleteNotes = $pdo->prepare($sqlDeleteNotes);
                        $stmtDeleteNotes->execute([
                            $evaluation['idType'], 
                            $evaluation['idECUE'], 
                            $evaluation['session_idsession'], 
                            $evaluation['annee_acad_id']
                        ]);
                        
                        // Supprimer l'évaluation
                        $sqlDeleteEval = "DELETE FROM evaluations WHERE idevaluation = ?";
                        $stmtDeleteEval = $pdo->prepare($sqlDeleteEval);
                        $stmtDeleteEval->execute([$evaluationId]);
                        
                        // Valider la transaction
                        $pdo->commit();
                        
                        // Rediriger avec un message de succès
                        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                        echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Succès',
                                    text: 'L\'évaluation a été supprimée avec succès.'
                                }).then(() => {
                                    window.location.href = '../?view=enseignement/evaluations&ecue={$idECUE}';
                                });
                            });
                        </script>";
                        
                    } catch (Exception $e) {
                        // En cas d'erreur, annuler la transaction
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        
                        // Afficher l'erreur
                        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                        echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erreur',
                                    text: 'Une erreur est survenue lors de la suppression: " . addslashes($e->getMessage()) . "'
                                }).then(() => {
                                    window.location.href = '../?view=enseignement/evaluations&ecue={$idECUE}';
                                });
                            });
                        </script>";
                    }
                }
                // Action inconnue
                else {
                    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                    echo "<script>
                        document.addEventListener('DOMContentLoaded', function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: 'Action inconnue: " . htmlspecialchars($action) . "'
                            }).then(() => {
                                window.history.back();
                            });
                        });
                    </script>";
                }
            }
            // Méthode non autorisée
            else {
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Méthode non autorisée'
                        }).then(() => {
                            window.history.back();
                        });
                    });
                </script>";
            }
            ?>
            