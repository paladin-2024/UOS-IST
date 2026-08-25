<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';



// Récupérer l'action à effectuer
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

// Validation d'un seul recours
if (($action == 'valider' || $action == 'rejeter') && isset($_GET['id'])) {
    $id_reponse = intval($_GET['id']);
    processSingleResponseValidation($id_reponse, $action);
}
// Validation groupée
elseif (($action == 'valider' || $action == 'rejeter') && isset($_POST['ids_recours']) && !empty($_POST['ids_recours'])) {
    $ids_recours = explode(',', $_POST['ids_recours']);
    processBulkResponseValidation($ids_recours, $action);
}
else {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Action non valide ou paramètres manquants.'
        }).then(() => {
            window.location.href = '../jury/validation_recours';
        });
    </script>";
    exit();
}

/**
 * Traite la validation/rejet d'une réponse individuelle
 */
function processSingleResponseValidation($id_reponse, $action) {
    $conn = Connexion::getInstance()->getPDO();
    
    try {
        // Vérifier si la réponse existe et récupérer l'ID du recours
        $query_check = "SELECT rr.id_recours, r.id_ecue, r.id_session, r.matricule, r.id_annee_acad, r.statut
                       FROM recours_reponse rr
                       JOIN recours r ON rr.id_recours = r.id_recours
                       WHERE rr.id_reponse = :id_reponse";
        $stmt_check = $conn->prepare($query_check);
        $stmt_check->bindParam(':id_reponse', $id_reponse);
        $stmt_check->execute();
        $reponse_info = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$reponse_info) {
            throw new Exception("La réponse spécifiée n'existe pas.");
        }
        
        // Vérifier si l'utilisateur a le droit de valider ce recours (membre du jury pour cette promotion)
        if ($_SESSION['role'] != 'Administrateur') {
            $id_recours = $reponse_info['id_recours'];
            $query_auth = "SELECT bjp.idbureau
                          FROM recours r
                          JOIN etudiant e ON r.matricule = e.matricule
                          JOIN bureau_jury_promotion bjp ON e.promotion_idpromotion = bjp.idpromotion
                          JOIN bureau_jury_deliberation bjd ON bjp.idbureau = bjd.idbureau
                          LEFT JOIN membre_bureau_jury mbj ON bjd.idbureau = mbj.idbureau
                          LEFT JOIN agent a ON a.\"idAgent\" = mbj.idAgent
                          LEFT JOIN t_users u ON u.\"idAgent\" = a.\"idAgent\"
                          WHERE r.id_recours = :id_recours
                          AND bjd.est_actif = 1
                          AND (bjd.president_id = :id_user OR bjd.secretaire_id = :id_user
                               OR u.\"idUser\" = :id_user)";
            
            $stmt_auth = $conn->prepare($query_auth);
            $stmt_auth->bindParam(':id_recours', $id_recours);
            $stmt_auth->bindParam(':id_user', $_SESSION['id']);
            $stmt_auth->execute();
            
            if ($stmt_auth->rowCount() == 0) {
                throw new Exception("Vous n'êtes pas autorisé à valider ce recours.");
            }
        }
        
        // Récupérer les informations nécessaires pour mettre à jour les notes si validé
        $query_notes = "SELECT nouvelle_note_cc, nouvelle_note_ex
                       FROM recours_reponse
                       WHERE id_reponse = :id_reponse";
        $stmt_notes = $conn->prepare($query_notes);
        $stmt_notes->bindParam(':id_reponse', $id_reponse);
        $stmt_notes->execute();
        $notes_info = $stmt_notes->fetch(PDO::FETCH_ASSOC);
        
        // Démarrer une transaction
        $conn->beginTransaction();
        
        // Mettre à jour le statut de la réponse
        $query_update = "UPDATE recours_reponse
                        SET valide_jury = 1,
                            id_validateur = :id_validateur,
                            date_validation = NOW()
                        WHERE id_reponse = :id_reponse";
        
        $stmt_update = $conn->prepare($query_update);
        $stmt_update->bindParam(':id_validateur', $_SESSION['id']);
        $stmt_update->bindParam(':id_reponse', $id_reponse);
        $stmt_update->execute();
        
        // Mettre à jour le statut du recours
        $nouveau_statut = $action == 'valider' ? 'Approuvé' : 'Rejeté';
        $query_update_recours = "UPDATE recours
                                SET statut = :nouveau_statut
                                WHERE id_recours = :id_recours";
        
        $stmt_update_recours = $conn->prepare($query_update_recours);
        $stmt_update_recours->bindParam(':nouveau_statut', $nouveau_statut);
        $stmt_update_recours->bindParam(':id_recours', $reponse_info['id_recours']);
        $stmt_update_recours->execute();
        
        // Si le recours est approuvé et que de nouvelles notes sont disponibles, mettre à jour les notes finales
        if ($action == 'valider' && ($notes_info['nouvelle_note_cc'] !== null || $notes_info['nouvelle_note_ex'] !== null)) {
            // Récupérer les notes actuelles
            $query_notes_actuelles = "SELECT \"CC\", \"EX\", \"MF\"
                                     FROM cotes_grille
                                     WHERE \"ECUE_idECUE\" = :id_ecue
                                     AND session_idsession = :id_session
                                     AND matricule = :matricule
                                     AND annee_acad_id = :id_annee";
            
            $stmt_notes_actuelles = $conn->prepare($query_notes_actuelles);
            $stmt_notes_actuelles->bindParam(':id_ecue', $reponse_info['id_ecue']);
            $stmt_notes_actuelles->bindParam(':id_session', $reponse_info['id_session']);
            $stmt_notes_actuelles->bindParam(':matricule', $reponse_info['matricule']);
            $stmt_notes_actuelles->bindParam(':id_annee', $reponse_info['id_annee_acad']);
            $stmt_notes_actuelles->execute();
            $notes_actuelles = $stmt_notes_actuelles->fetch(PDO::FETCH_ASSOC);
            
            // Récupérer les pondérations
            $query_ponderation = "SELECT ponderation_cc, ponderation_ex
                                 FROM configuration_moyenne
                                 WHERE \"idECUE\" = :id_ecue
                                 AND session_idsession = :id_session
                                 AND annee_acad_id = :id_annee
                                 LIMIT 1";
            
            $stmt_ponderation = $conn->prepare($query_ponderation);
            $stmt_ponderation->bindParam(':id_ecue', $reponse_info['id_ecue']);
            $stmt_ponderation->bindParam(':id_session', $reponse_info['id_session']);
            $stmt_ponderation->bindParam(':id_annee', $reponse_info['id_annee_acad']);
            $stmt_ponderation->execute();
            $ponderation = $stmt_ponderation->fetch(PDO::FETCH_ASSOC);
            
            if (!$ponderation) {
                // Récupérer les pondérations depuis la configuration
                require_once '../models/Universite.php';
                $universite = new Universite();
                $ponderationsDefaut = $universite->getPonderationsDefaut();
                $ponderation = ['ponderation_cc' => $ponderationsDefaut['ponderation_cc'], 'ponderation_ex' => $ponderationsDefaut['ponderation_ex']];
            }
            
            // Utiliser les nouvelles notes ou conserver les anciennes
            $cc_final = $notes_info['nouvelle_note_cc'] !== null ? $notes_info['nouvelle_note_cc'] : ($notes_actuelles ? $notes_actuelles['CC'] : null);
            $ex_final = $notes_info['nouvelle_note_ex'] !== null ? $notes_info['nouvelle_note_ex'] : ($notes_actuelles ? $notes_actuelles['EX'] : null);
            
            // Calculer la nouvelle moyenne finale si les deux notes sont disponibles
            $mf_final = null;
            if ($cc_final !== null && $ex_final !== null) {
                $mf_final = ($cc_final * $ponderation['ponderation_cc']) + ($ex_final * $ponderation['ponderation_ex']);
            }
            
            // Enregistrer dans l'historique
            if ($notes_actuelles) {
                $query_historique = "INSERT INTO historique_cotes
                                    (\"ECUE_idECUE\", session_idsession, annee_acad_id, matricule,
                                     cc_avant, ex_avant, mf_avant,
                                     cc_apres, ex_apres, mf_apres,
                                     motif, \"idUser\")
                                    VALUES
                                    (:id_ecue, :id_session, :id_annee, :matricule,
                                     :cc_avant, :ex_avant, :mf_avant,
                                     :cc_apres, :ex_apres, :mf_apres,
                                     'Validation recours par jury', :idUser)";
                
                $stmt_historique = $conn->prepare($query_historique);
                $stmt_historique->bindParam(':id_ecue', $reponse_info['id_ecue']);
                $stmt_historique->bindParam(':id_session', $reponse_info['id_session']);
                $stmt_historique->bindParam(':id_annee', $reponse_info['id_annee_acad']);
                $stmt_historique->bindParam(':matricule', $reponse_info['matricule']);
                $stmt_historique->bindParam(':cc_avant', $notes_actuelles['CC']);
                $stmt_historique->bindParam(':ex_avant', $notes_actuelles['EX']);
                $stmt_historique->bindParam(':mf_avant', $notes_actuelles['MF']);
                $stmt_historique->bindParam(':cc_apres', $cc_final);
                $stmt_historique->bindParam(':ex_apres', $ex_final);
                $stmt_historique->bindParam(':mf_apres', $mf_final);
                $stmt_historique->bindParam(':idUser', $_SESSION['id']);
                $stmt_historique->execute();
                
                // Mettre à jour les notes
                $query_update_notes = "UPDATE cotes_grille
                                     SET \"CC\" = :cc, \"EX\" = :ex, \"MF\" = :mf,
                                         \"idUser\" = :idUser, date_compilation = NOW()
                                     WHERE \"ECUE_idECUE\" = :id_ecue
                                     AND session_idsession = :id_session
                                     AND matricule = :matricule
                                     AND annee_acad_id = :id_annee";
                
                $stmt_update_notes = $conn->prepare($query_update_notes);
                $stmt_update_notes->bindParam(':cc', $cc_final);
                $stmt_update_notes->bindParam(':ex', $ex_final);
                $stmt_update_notes->bindParam(':mf', $mf_final);
                $stmt_update_notes->bindParam(':idUser', $_SESSION['id']);
                $stmt_update_notes->bindParam(':id_ecue', $reponse_info['id_ecue']);
                $stmt_update_notes->bindParam(':id_session', $reponse_info['id_session']);
                $stmt_update_notes->bindParam(':matricule', $reponse_info['matricule']);
                $stmt_update_notes->bindParam(':id_annee', $reponse_info['id_annee_acad']);
                $stmt_update_notes->execute();
            } else {
                // Créer une nouvelle entrée de notes
                $query_insert_notes = "INSERT INTO cotes_grille
                                     (\"ECUE_idECUE\", session_idsession, matricule, annee_acad_id,
                                      \"CC\", \"EX\", \"MF\", \"idUser\", date_compilation)
                                     VALUES
                                     (:id_ecue, :id_session, :matricule, :id_annee,
                                      :cc, :ex, :mf, :idUser, NOW())";
                
                $stmt_insert_notes = $conn->prepare($query_insert_notes);
                $stmt_insert_notes->bindParam(':id_ecue', $reponse_info['id_ecue']);
                $stmt_insert_notes->bindParam(':id_session', $reponse_info['id_session']);
                $stmt_insert_notes->bindParam(':matricule', $reponse_info['matricule']);
                $stmt_insert_notes->bindParam(':id_annee', $reponse_info['id_annee_acad']);
                $stmt_insert_notes->bindParam(':cc', $cc_final);
                $stmt_insert_notes->bindParam(':ex', $ex_final);
                $stmt_insert_notes->bindParam(':mf', $mf_final);
                $stmt_insert_notes->bindParam(':idUser', $_SESSION['id']);
                $stmt_insert_notes->execute();
            }
        }
        
        // Valider la transaction
        $conn->commit();
        
        // Rediriger avec message de succès
        $message = $action == 'valider' ? 'validée' : 'rejetée';
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'La réponse au recours a été " . $message . " avec succès.'
            }).then(() => {
                window.location.href = '../jury/validation_recours';
            });
        </script>";
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $conn->rollBack();
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../jury/validation_recours';
            });
        </script>";
    }
}

/**
 * Traite la validation/rejet de plusieurs réponses en lot
 */
function processBulkResponseValidation($ids_reponse, $action) {
    $conn = Connexion::getInstance()->getPDO();
    
    try {
        // Démarrer une transaction
        $conn->beginTransaction();
        
        $total_processed = 0;
        $errors = [];
        
        foreach ($ids_reponse as $id_reponse) {
            $id_reponse = intval($id_reponse);
            
            // Vérifier si la réponse existe et récupérer l'ID du recours
            $query_check = "SELECT rr.id_recours, r.id_ecue, r.id_session, r.matricule, r.id_annee_acad, r.statut
                          FROM recours_reponse rr
                          JOIN recours r ON rr.id_recours = r.id_recours
                          WHERE rr.id_reponse = :id_reponse";
            $stmt_check = $conn->prepare($query_check);
            $stmt_check->bindParam(':id_reponse', $id_reponse);
            $stmt_check->execute();
            $reponse_info = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if (!$reponse_info) {
                $errors[] = "La réponse ID #$id_reponse n'existe pas.";
                continue;
            }
            
            /*
            // Vérifier si l'utilisateur a le droit de valider ce recours (seulement pour les utilisateurs non-admin)
            if ($_SESSION['role'] != 'Administrateur') {
                $id_recours = $reponse_info['id_recours'];
                $query_auth = "SELECT bjp.idbureau
                              FROM recours r
                              JOIN etudiant e ON r.matricule = e.matricule
                              JOIN bureau_jury_promotion bjp ON e.promotion_idpromotion = bjp.idpromotion
                              JOIN bureau_jury_deliberation bjd ON bjp.idbureau = bjd.idbureau
                              LEFT JOIN membre_bureau_jury mbj ON bjd.idbureau = mbj.idbureau
                              LEFT JOIN agent a ON a.idAgent = mbj.idAgent
                              WHERE r.id_recours = :id_recours
                              AND bjd.est_actif = 1
                              AND (bjd.president_id = :id_user OR bjd.secretaire_id = :id_user 
                                  OR a.idUser = :id_user)";
                
                $stmt_auth = $conn->prepare($query_auth);
                $stmt_auth->bindParam(':id_recours', $id_recours);
                $stmt_auth->bindParam(':id_user', $_SESSION['id']);
                $stmt_auth->execute();
                
                if ($stmt_auth->rowCount() == 0) {
                    $errors[] = "Vous n'êtes pas autorisé à valider le recours ID #$id_recours.";
                    continue;
                }
            }
                */
            
            // Récupérer les informations sur les notes 
            $query_notes = "SELECT nouvelle_note_cc, nouvelle_note_ex
                           FROM recours_reponse
                           WHERE id_reponse = :id_reponse";
            $stmt_notes = $conn->prepare($query_notes);
            $stmt_notes->bindParam(':id_reponse', $id_reponse);
            $stmt_notes->execute();
            $notes_info = $stmt_notes->fetch(PDO::FETCH_ASSOC);
            
            // Mettre à jour le statut de la réponse
            $query_update = "UPDATE recours_reponse
                            SET valide_jury = 1,
                                id_validateur = :id_validateur,
                                date_validation = NOW()
                            WHERE id_reponse = :id_reponse";
            
            $stmt_update = $conn->prepare($query_update);
            $stmt_update->bindParam(':id_validateur', $_SESSION['id']);
            $stmt_update->bindParam(':id_reponse', $id_reponse);
            $stmt_update->execute();
            
            // Mettre à jour le statut du recours
            $nouveau_statut = $action == 'valider' ? 'Approuvé' : 'Rejeté';
            $query_update_recours = "UPDATE recours
                                    SET statut = :nouveau_statut
                                    WHERE id_recours = :id_recours";
            
            $stmt_update_recours = $conn->prepare($query_update_recours);
            $stmt_update_recours->bindParam(':nouveau_statut', $nouveau_statut);
            $stmt_update_recours->bindParam(':id_recours', $reponse_info['id_recours']);
            $stmt_update_recours->execute();
            
            // Si le recours est approuvé et que de nouvelles notes sont disponibles, mettre à jour les notes finales
            if ($action == 'valider' && ($notes_info['nouvelle_note_cc'] !== null || $notes_info['nouvelle_note_ex'] !== null)) {
                // Récupérer les notes actuelles
                $query_notes_actuelles = "SELECT \"CC\", \"EX\", \"MF\"
                                         FROM cotes_grille
                                         WHERE \"ECUE_idECUE\" = :id_ecue
                                         AND session_idsession = :id_session
                                         AND matricule = :matricule
                                         AND annee_acad_id = :id_annee";
                
                $stmt_notes_actuelles = $conn->prepare($query_notes_actuelles);
                $stmt_notes_actuelles->bindParam(':id_ecue', $reponse_info['id_ecue']);
                $stmt_notes_actuelles->bindParam(':id_session', $reponse_info['id_session']);
                $stmt_notes_actuelles->bindParam(':matricule', $reponse_info['matricule']);
                $stmt_notes_actuelles->bindParam(':id_annee', $reponse_info['id_annee_acad']);
                $stmt_notes_actuelles->execute();
                $notes_actuelles = $stmt_notes_actuelles->fetch(PDO::FETCH_ASSOC);
                
                // Récupérer les pondérations
                $query_ponderation = "SELECT ponderation_cc, ponderation_ex
                                     FROM configuration_moyenne
                                     WHERE \"idECUE\" = :id_ecue
                                     AND session_idsession = :id_session
                                     AND annee_acad_id = :id_annee
                                     LIMIT 1";
                
                $stmt_ponderation = $conn->prepare($query_ponderation);
                $stmt_ponderation->bindParam(':id_ecue', $reponse_info['id_ecue']);
                $stmt_ponderation->bindParam(':id_session', $reponse_info['id_session']);
                $stmt_ponderation->bindParam(':id_annee', $reponse_info['id_annee_acad']);
                $stmt_ponderation->execute();
                $ponderation = $stmt_ponderation->fetch(PDO::FETCH_ASSOC);
                
                if (!$ponderation) {
                    // Récupérer les pondérations depuis la configuration
                    require_once '../models/Universite.php';
                    $universite = new Universite();
                    $ponderationsDefaut = $universite->getPonderationsDefaut();
                    $ponderation = ['ponderation_cc' => $ponderationsDefaut['ponderation_cc'], 'ponderation_ex' => $ponderationsDefaut['ponderation_ex']];
                }
                
                // Utiliser les nouvelles notes ou conserver les anciennes
                $cc_final = $notes_info['nouvelle_note_cc'] !== null ? $notes_info['nouvelle_note_cc'] : ($notes_actuelles ? $notes_actuelles['CC'] : null);
                $ex_final = $notes_info['nouvelle_note_ex'] !== null ? $notes_info['nouvelle_note_ex'] : ($notes_actuelles ? $notes_actuelles['EX'] : null);
                
                // Calculer la nouvelle moyenne finale si les deux notes sont disponibles
                $mf_final = null;
                if ($cc_final !== null && $ex_final !== null) {
                    $mf_final = ($cc_final * $ponderation['ponderation_cc']) + ($ex_final * $ponderation['ponderation_ex']);
                }
                
                // Mettre à jour l'historique et les notes
                if ($notes_actuelles) {
                    $query_historique = "INSERT INTO historique_cotes
                                        (\"ECUE_idECUE\", session_idsession, annee_acad_id, matricule,
                                         cc_avant, ex_avant, mf_avant,
                                         cc_apres, ex_apres, mf_apres,
                                         motif, \"idUser\")
                                        VALUES
                                        (:id_ecue, :id_session, :id_annee, :matricule,
                                         :cc_avant, :ex_avant, :mf_avant,
                                         :cc_apres, :ex_apres, :mf_apres,
                                         'Validation recours par jury', :idUser)";
                    
                    $stmt_historique = $conn->prepare($query_historique);
                    $stmt_historique->bindParam(':id_ecue', $reponse_info['id_ecue']);
                    $stmt_historique->bindParam(':id_session', $reponse_info['id_session']);
                    $stmt_historique->bindParam(':id_annee', $reponse_info['id_annee_acad']);
                    $stmt_historique->bindParam(':matricule', $reponse_info['matricule']);
                    $stmt_historique->bindParam(':cc_avant', $notes_actuelles['CC']);
                    $stmt_historique->bindParam(':ex_avant', $notes_actuelles['EX']);
                    $stmt_historique->bindParam(':mf_avant', $notes_actuelles['MF']);
                    $stmt_historique->bindParam(':cc_apres', $cc_final);
                    $stmt_historique->bindParam(':ex_apres', $ex_final);
                    $stmt_historique->bindParam(':mf_apres', $mf_final);
                    $stmt_historique->bindParam(':idUser', $_SESSION['id']);
                    $stmt_historique->execute();
                    
                    // Mettre à jour les notes
                    $query_update_notes = "UPDATE cotes_grille
                                         SET \"CC\" = :cc, \"EX\" = :ex, \"MF\" = :mf,
                                             \"idUser\" = :idUser, date_compilation = NOW()
                                         WHERE \"ECUE_idECUE\" = :id_ecue
                                         AND session_idsession = :id_session
                                         AND matricule = :matricule
                                         AND annee_acad_id = :id_annee";
                    
                    $stmt_update_notes = $conn->prepare($query_update_notes);
                    $stmt_update_notes->bindParam(':cc', $cc_final);
                    $stmt_update_notes->bindParam(':ex', $ex_final);
                    $stmt_update_notes->bindParam(':mf', $mf_final);
                    $stmt_update_notes->bindParam(':idUser', $_SESSION['id']);
                    $stmt_update_notes->bindParam(':id_ecue', $reponse_info['id_ecue']);
                    $stmt_update_notes->bindParam(':id_session', $reponse_info['id_session']);
                    $stmt_update_notes->bindParam(':matricule', $reponse_info['matricule']);
                    $stmt_update_notes->bindParam(':id_annee', $reponse_info['id_annee_acad']);
                    $stmt_update_notes->execute();
                } else {
                    // Créer une nouvelle entrée de notes
                    $query_insert_notes = "INSERT INTO cotes_grille
                                         (\"ECUE_idECUE\", session_idsession, matricule, annee_acad_id,
                                          \"CC\", \"EX\", \"MF\", \"idUser\", date_compilation)
                                         VALUES
                                         (:id_ecue, :id_session, :matricule, :id_annee,
                                          :cc, :ex, :mf, :idUser, NOW())";
                    
                    $stmt_insert_notes = $conn->prepare($query_insert_notes);
                    $stmt_insert_notes->bindParam(':id_ecue', $reponse_info['id_ecue']);
                    $stmt_insert_notes->bindParam(':id_session', $reponse_info['id_session']);
                    $stmt_insert_notes->bindParam(':matricule', $reponse_info['matricule']);
                    $stmt_insert_notes->bindParam(':id_annee', $reponse_info['id_annee_acad']);
                    $stmt_insert_notes->bindParam(':cc', $cc_final);
                    $stmt_insert_notes->bindParam(':ex', $ex_final);
                    $stmt_insert_notes->bindParam(':mf', $mf_final);
                    $stmt_insert_notes->bindParam(':idUser', $_SESSION['id']);
                    $stmt_insert_notes->execute();
                }
            }
            
            $total_processed++;
        }
        
        // Valider la transaction
        $conn->commit();
        
        if (count($errors) > 0) {
            $error_msg = "Traitement terminé avec " . count($errors) . " erreurs: <br>" . implode("<br>", $errors);
            echo "<script>
                Swal.fire({
                    icon: 'warning',
                    title: 'Traitement avec erreurs',
                    html: '" . addslashes($error_msg) . "',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = '../deliberation/validation_recours';
                });
            </script>";
        } else {
            $message = $action == 'valider' ? 'validés' : 'rejetés';
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: '$total_processed recours ont été $message avec succès.'
                }).then(() => {
                    window.location.href = '../deliberation/validation_recours';
                });
            </script>";
        }
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $conn->rollBack();
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../deliberation/validation_recours';
            });
        </script>";
    }
}
?>

