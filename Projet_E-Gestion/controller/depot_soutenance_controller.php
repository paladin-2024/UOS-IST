<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/DepotSoutenance.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/models/Soutenance.php';

$soutenanceModel = new Soutenance();
$depotSoutenanceModel = new DepotSoutenance();
$universite = new Universite();
$agentModel = new Agent();

$redirectUrl="";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération de l'action
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $userId = isset($_SESSION['id']) ? $_SESSION['id'] : 0;
    
    if (empty($userId)) {
        echo json_encode([
            'success' => false,
            'message' => 'Utilisateur non connecté.'
        ]);
        exit;
    }
    
    switch ($action) {
        case 'depot_memoire':
            $idSujet = isset($_POST['id_sujet']) ? intval($_POST['id_sujet']) : 0;
            $dateDepot = isset($_POST['date_depot']) ? $_POST['date_depot'] : date('Y-m-d');
            $observation = isset($_POST['observation']) ? $_POST['observation'] : '';
            
            // Traitement du fichier
            $fichier = '';
            if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__) . '/uploads/memoires/';
                
                // Créer le répertoire s'il n'existe pas
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileName = time() . '_' . basename($_FILES['fichier']['name']);
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['fichier']['tmp_name'], $filePath)) {
                    $fichier = 'uploads/memoires/' . $fileName;
                }
            }
            
            $result = $depotSoutenanceModel->enregistrerDepotMemoire($idSujet, $dateDepot, $fichier, $observation);
            
            if (isset($_POST['format']) && $_POST['format'] == 'json') {
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
            } else {
                $messageClass = $result['success'] ? 'success' : 'error';
                $redirectUrl = "../?view=recherche/depot_soutenance";
                
                echo "<script>
                    Swal.fire({
                        icon: '" . ($result['success'] ? 'success' : 'error') . "',
                        title: '" . ($result['success'] ? 'Succès' : 'Erreur') . "',
                        text: '" . addslashes($result['message']) . "'
                    }).then(() => {
                        window.location.href = '$redirectUrl';
                    });
                </script>";
                exit;
            }
            break;
            
        case 'depot_rapport':
            case 'depot_rapport':
                $etudiantId = isset($_POST['etudiant_id']) ? intval($_POST['etudiant_id']) : 0;
                $encadreurId = isset($_POST['encadreur_id']) ? intval($_POST['encadreur_id']) : 0;
                $dateDepot = isset($_POST['date_depot']) ? $_POST['date_depot'] : date('Y-m-d');
                $titre = isset($_POST['titre']) ? $_POST['titre'] : '';
                $lieuStage = isset($_POST['lieu_stage']) ? $_POST['lieu_stage'] : '';
                $dateDebut = isset($_POST['date_debut']) ? $_POST['date_debut'] : null;
                $dateFin = isset($_POST['date_fin']) ? $_POST['date_fin'] : null;
                $observation = isset($_POST['observation']) ? $_POST['observation'] : '';
                
                // Traitement du fichier rapport
                $fichier = '';
                if (isset($_FILES['fichier_rapport']) && $_FILES['fichier_rapport']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = dirname(__DIR__) . '/uploads/rapports/';
                    
                    // Créer le répertoire s'il n'existe pas
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $fileName = time() . '_' . basename($_FILES['fichier_rapport']['name']);
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['fichier_rapport']['tmp_name'], $filePath)) {
                        $fichier = 'uploads/rapports/' . $fileName;
                    }
                }
                
                $result = $depotSoutenanceModel->enregistrerDepotRapport(
                    $etudiantId, $dateDepot, $titre, $lieuStage, 
                    $dateDebut, $dateFin, $observation, $encadreurId, $fichier
                );
                
                // Le reste du code reste inchangé...
            
            
            if (isset($_POST['format']) && $_POST['format'] == 'json') {
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
            } else {
                $messageClass = $result['success'] ? 'success' : 'error';
                $redirectUrl = "../?view=recherche/depot_soutenance";
                
                echo "<script>
                    Swal.fire({
                        icon: '" . ($result['success'] ? 'success' : 'error') . "',
                        title: '" . ($result['success'] ? 'Succès' : 'Erreur') . "',
                        text: '" . addslashes($result['message']) . "'
                    }).then(() => {
                        window.location.href = '$redirectUrl';
                    });
                </script>";
                exit;
            }
            break;
            
            case 'programmer_soutenance':
                $idSujet = isset($_POST['id_sujet']) ? intval($_POST['id_sujet']) : 0;
                $dateSoutenance = isset($_POST['date_soutenance']) ? $_POST['date_soutenance'] : '';
                $lieu = isset($_POST['lieu']) ? $_POST['lieu'] : '';
                $idJury = isset($_POST['id_jury']) ? intval($_POST['id_jury']) : 0;
                $idLecteur1 = isset($_POST['id_lecteur1']) ? intval($_POST['id_lecteur1']) : 0;
                $idLecteur2 = isset($_POST['id_lecteur2']) ? intval($_POST['id_lecteur2']) : 0;
                
                // Vérifier que les données obligatoires sont présentes
                if (empty($idSujet) || empty($dateSoutenance) || empty($lieu) || empty($idJury) || 
                    empty($idLecteur1) || empty($idLecteur2)) {
                    
                    $result = [
                        'success' => false,
                        'message' => 'Tous les champs sont obligatoires pour programmer une soutenance.'
                    ];
                } else if ($idLecteur1 == $idLecteur2) {
                    $result = [
                        'success' => false,
                        'message' => 'Les deux lecteurs doivent être différents.'
                    ];
                } else {
                    // Initialiser le modèle de soutenance
                    $soutenanceModel = new Soutenance();
                    
                    // Programmer la soutenance
                    $soutenanceResult = $soutenanceModel->programmerSoutenance($dateSoutenance, $lieu, $idSujet, $userId);
                    
                    if ($soutenanceResult['success']) {
                        $idSoutenance = $soutenanceResult['id'];
                        
                        // Mettre à jour le jury
                        $soutenanceModel->updateJurySoutenance($idSoutenance, $idJury);

                        
                        // Associer les lecteurs
                        $lecteursResult = $soutenanceModel->assignerLecteurs($idSoutenance, $idLecteur1, $idLecteur2);
                        
                        $result = [
                            'success' => true,
                            'message' => 'La soutenance a été programmée avec succès.',
                            'idSoutenance' => $idSoutenance
                        ];
                    } else {
                        $result = $soutenanceResult;
                    }
                }
                
                // Gérer la réponse
                if (isset($_POST['format']) && $_POST['format'] == 'json') {
                    header('Content-Type: application/json');
                    echo json_encode($result);
                    exit;
                } else {
                    $redirectUrl = "../?view=recherche/depot_soutenance&tab=soutenances";
                    
                    echo "<script>
                        Swal.fire({
                            icon: '" . ($result['success'] ? 'success' : 'error') . "',
                            title: '" . ($result['success'] ? 'Succès' : 'Erreur') . "',
                            text: '" . addslashes($result['message']) . "'
                        }).then(() => {
                            window.location.href = '$redirectUrl';
                        });
                    </script>";
                    exit;
                }
                break;
            
                
            case 'create_jury':
                $designation = isset($_POST['designation']) ? $_POST['designation'] : '';
                $idPresident = isset($_POST['id_president']) ? intval($_POST['id_president']) : 0;
                $idSecretaire = isset($_POST['id_secretaire']) ? intval($_POST['id_secretaire']) : 0;
                $idAnneeAcad = isset($_POST['id_annee_acad']) ? intval($_POST['id_annee_acad']) : 0;
                $idSection = isset($_POST['id_section']) ? intval($_POST['id_section']) : null;
                
                // Vérifier que les données obligatoires sont présentes
                if (empty($designation) || empty($idPresident) || empty($idSecretaire) || empty($idAnneeAcad)) {
                    $result = [
                        'success' => false,
                        'message' => 'Tous les champs sont obligatoires pour créer un jury.'
                    ];
                } else {
                    // Créer le jury
                    $result = $soutenanceModel->createJury($designation, $idPresident, $idSecretaire, $idAnneeAcad, $idSection);
                }
                
                if (isset($_POST['format']) && $_POST['format'] == 'json') {
                    header('Content-Type: application/json');
                    echo json_encode($result);
                    exit;
                } else {
                    $messageClass = $result['success'] ? 'success' : 'error';
                    $redirectUrl = "../?view=recherche/gestion_jurys";
                    
                    echo "<script>
                        Swal.fire({
                            icon: '" . ($result['success'] ? 'success' : 'error') . "',
                            title: '" . ($result['success'] ? 'Succès' : 'Erreur') . "',
                            text: '" . addslashes($result['message']) . "'
                        }).then(() => {
                            window.location.href = '$redirectUrl';
                        });
                    </script>";
                    exit;
                }
                break;
                
            case 'enregistrer_notes_lecteur':
                $idSoutenance = isset($_POST['id_soutenance']) ? intval($_POST['id_soutenance']) : 0;
                $idEnseignant = isset($_POST['id_enseignant']) ? intval($_POST['id_enseignant']) : 0;
                $noteFond = isset($_POST['note_fond']) ? floatval($_POST['note_fond']) : null;
                $noteForme = isset($_POST['note_forme']) ? floatval($_POST['note_forme']) : null;
                $commentaire = isset($_POST['commentaire']) ? $_POST['commentaire'] : null;
                
                // Vérifications
                if (empty($idSoutenance) || empty($idEnseignant) || 
                    $noteFond === null || $noteForme === null) {
                    $result = [
                        'success' => false,
                        'message' => 'Tous les champs sont obligatoires pour enregistrer les notes.'
                    ];
                } else {
                    
                                    
                                    if (!$soutenanceModel->isLecteurForSoutenance($idSoutenance, $idEnseignant)) {
                                        $result = [
                                            'success' => false,
                                            'message' => "Vous n'êtes pas autorisé à noter cette soutenance."
                                        ];
                                    } else {
                                        // Enregistrer les notes
                                        $noteResult = $soutenanceModel->enregistrerNote(
                                            $idSoutenance, 
                                            $idEnseignant, 
                                            'Lecteur', 
                                            $noteFond, 
                                            $noteForme, 
                                            null, 
                                            $commentaire
                                        );
                                        
                                        if ($noteResult) {
                                            $result = [
                                                'success' => true,
                                                'message' => 'Les notes ont été enregistrées avec succès.'
                                            ];
                                        } else {
                                            $result = [
                                                'success' => false,
                                                'message' => "Une erreur est survenue lors de l'enregistrement des notes."
                                            ];
                                        }
                                    }
                                }
                                
                                if (isset($_POST['format']) && $_POST['format'] == 'json') {
                                    header('Content-Type: application/json');
                                    echo json_encode($result);
                                    exit;
                                } else {
                                    $messageClass = $result['success'] ? 'success' : 'error';
                                    $redirectUrl = "../?view=recherche/mes_soutenances";
                                    
                                    echo "<script>
                                        Swal.fire({
                                            icon: '" . ($result['success'] ? 'success' : 'error') . "',
                                            title: '" . ($result['success'] ? 'Succès' : 'Erreur') . "',
                                            text: '" . addslashes($result['message']) . "'
                                        }).then(() => {
                                            window.location.href = '$redirectUrl';
                                        });
                                    </script>";
                                    exit;
                                }
                                break;
                                
                            case 'enregistrer_note_directeur':
                                $idSoutenance = isset($_POST['id_soutenance']) ? intval($_POST['id_soutenance']) : 0;
                                $idEnseignant = isset($_POST['id_enseignant']) ? intval($_POST['id_enseignant']) : 0;
                                $noteSoutenance = isset($_POST['note_soutenance']) ? floatval($_POST['note_soutenance']) : null;
                                $commentaire = isset($_POST['commentaire']) ? $_POST['commentaire'] : null;
                                
                                // Vérifications
                                if (empty($idSoutenance) || empty($idEnseignant) || $noteSoutenance === null) {
                                    $result = [
                                        'success' => false,
                                        'message' => 'Tous les champs sont obligatoires pour enregistrer la note.'
                                    ];
                                } else {
                                    // Vérifier que l'enseignant est bien le directeur pour cette soutenance
                                    
                                    
                                    if (!$soutenanceModel->isDirecteurForSoutenance($idSoutenance, $idEnseignant)) {
                                        $result = [
                                            'success' => false,
                                            'message' => "Vous n'êtes pas autorisé à noter cette soutenance."
                                        ];
                                    } else {
                                        // Enregistrer la note
                                        $noteResult = $soutenanceModel->enregistrerNote(
                                            $idSoutenance, 
                                            $idEnseignant, 
                                            'Directeur', 
                                            null, 
                                            null, 
                                            $noteSoutenance, 
                                            $commentaire
                                        );
                                        
                                        if ($noteResult) {
                                            $result = [
                                                'success' => true,
                                                'message' => 'La note a été enregistrée avec succès.'
                                            ];
                                        } else {
                                            $result = [
                                                'success' => false,
                                                'message' => "Une erreur est survenue lors de l'enregistrement de la note."
                                            ];
                                        }
                                    }
                                }
                                
                                if (isset($_POST['format']) && $_POST['format'] == 'json') {
                                    header('Content-Type: application/json');
                                    echo json_encode($result);
                                    exit;
                                } else {
                                    $messageClass = $result['success'] ? 'success' : 'error';
                                    $redirectUrl = "../?view=recherche/mes_soutenances";
                                    
                                    echo "<script>
                                        Swal.fire({
                                            icon: '" . ($result['success'] ? 'success' : 'error') . "',
                                            title: '" . ($result['success'] ? 'Succès' : 'Erreur') . "',
                                            text: '" . addslashes($result['message']) . "'
                                        }).then(() => {
                                            window.location.href = '$redirectUrl';
                                        });
                                    </script>";
                                    exit;
                                }
                                break;
                                
                            case 'valider_notes_soutenance':
                                $idSoutenance = isset($_POST['id_soutenance']) ? intval($_POST['id_soutenance']) : 0;
                                $idValidateur = isset($_POST['id_validateur']) ? intval($_POST['id_validateur']) : 0;
                                $estValide = isset($_POST['est_valide']) ? boolval($_POST['est_valide']) : false;
                                $estVisible = isset($_POST['est_visible']) ? boolval($_POST['est_visible']) : false;
                                $commentaire = isset($_POST['commentaire']) ? $_POST['commentaire'] : null;
                                
                                // Vérifications
                                if (empty($idSoutenance) || empty($idValidateur)) {
                                    $result = [
                                        'success' => false,
                                        'message' => 'Données invalides pour la validation.'
                                    ];
                                } else {
                                    // Vérifier que le validateur est bien le président du jury
                                    
                                    
                                    if (!$soutenanceModel->isPresidentForSoutenance($idSoutenance, $idValidateur)) {
                                        $result = [
                                            'success' => false,
                                            'message' => "Vous n'êtes pas autorisé à valider les notes pour cette soutenance."
                                        ];
                                    } else {
                                        // Valider les notes
                                        $validationResult = $soutenanceModel->validerNotesSoutenance(
                                            $idSoutenance, 
                                            $idValidateur, 
                                            $estValide, 
                                            $commentaire, 
                                            $estVisible
                                        );
                                        
                                        if ($validationResult) {
                                            $result = [
                                                'success' => true,
                                                'message' => 'La validation des notes a été effectuée avec succès.'
                                            ];
                                        } else {
                                            $result = [
                                                'success' => false,
                                                'message' => "Une erreur est survenue lors de la validation des notes."
                                            ];
                                        }
                                    }
                                }
                                
                                if (isset($_POST['format']) && $_POST['format'] == 'json') {
                                    header('Content-Type: application/json');
                                    echo json_encode($result);
                                    exit;
                                } else {
                                    $messageClass = $result['success'] ? 'success' : 'error';
                                    $redirectUrl = "../?view=recherche/jury_soutenances";
                                    
                                    echo "<script>
                                        Swal.fire({
                                            icon: '" . ($result['success'] ? 'success' : 'error') . "',
                                            title: '" . ($result['success'] ? 'Succès' : 'Erreur') . "',
                                            text: '" . addslashes($result['message']) . "'
                                        }).then(() => {
                                            window.location.href = '$redirectUrl';
                                        });
                                    </script>";
                                    exit;
                                }
                                break;
                                case 'update_soutenance':
                                    $idSoutenance = isset($_POST['id_soutenance']) ? intval($_POST['id_soutenance']) : 0;
                                    $dateSoutenance = isset($_POST['date_soutenance']) ? $_POST['date_soutenance'] : '';
                                    $heureSoutenance = isset($_POST['heure_soutenance']) ? $_POST['heure_soutenance'] : '';
                                    $lieu = isset($_POST['lieu']) ? $_POST['lieu'] : '';
                                    $statut = isset($_POST['statut']) ? $_POST['statut'] : '';
                                    $juryId = isset($_POST['jury_id']) ? intval($_POST['jury_id']) : null;
                                    $notePrincipale = isset($_POST['note_finale']) ? floatval($_POST['note_finale']) : null;
                                    $commentaire = isset($_POST['commentaire']) ? $_POST['commentaire'] : null;
                                    $lecteurs = isset($_POST['lecteurs']) ? $_POST['lecteurs'] : null;
                                    
                                    // Vérifier les données requises
                                    if (empty($idSoutenance) || empty($dateSoutenance) || empty($lieu) || empty($statut)) {
                                        $result = [
                                            'success' => false,
                                            'message' => 'Tous les champs obligatoires doivent être remplis.'
                                        ];
                                    } else {
                                        // Combiner la date et l'heure
                                        $dateTimeComplete = $dateSoutenance;
                                        if (!empty($heureSoutenance)) {
                                            $dateTimeComplete .= ' ' . $heureSoutenance . ':00';
                                        }
                                        
                                        // Initialiser le modèle
                                        $soutenanceModel = new Soutenance();
                                        
                                        try {
                                            // Mettre à jour la soutenance
                                            $result = $soutenanceModel->updateSoutenance(
                                                $idSoutenance, 
                                                $dateTimeComplete, 
                                                $lieu, 
                                                $statut, 
                                                $notePrincipale, 
                                                $commentaire, 
                                                $juryId, 
                                                $lecteurs
                                            );
                                            
                                            $result = [
                                                'success' => true,
                                                'message' => 'La soutenance a été mise à jour avec succès.'
                                            ];
                                        } catch (Exception $e) {
                                            $result = [
                                                'success' => false,
                                                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
                                            ];
                                        }
                                    }
                                    
                                    if (isset($_POST['format']) && $_POST['format'] == 'json') {
                                        header('Content-Type: application/json');
                                        echo json_encode($result);
                                        exit;
                                    } else {
                                        $redirectUrl = "../?view=recherche/depot_soutenance&tab=soutenances";
                                        
                                        echo "<script>
                                            Swal.fire({
                                                icon: '" . ($result['success'] ? 'success' : 'error') . "',
                                                title: '" . ($result['success'] ? 'Succès' : 'Erreur') . "',
                                                text: '" . addslashes($result['message']) . "'
                                            }).then(() => {
                                                window.location.href = '$redirectUrl';
                                            });
                                        </script>";
                                        exit;
                                    }
                                    break;
                                
                                
                            default:
                                // Code existant pour le cas par défaut
                                header('Content-Type: application/json');
                                echo json_encode([
                                    'success' => false,
                                    'message' => 'Action non reconnue.'
                                ]);
                                exit;
                        }
                    } elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action'])) {
                        $action = $_GET['action'];
                        $userId = isset($_SESSION['id']) ? $_SESSION['id'] : 0;
                        
                        switch ($action) {
                            case 'get_soutenance_details':
                                $idSoutenance = isset($_GET['id']) ? intval($_GET['id']) : 0;
                                if (empty($idSoutenance)) {
                                    header('Content-Type: application/json');
                                    echo json_encode(['success' => false, 'message' => 'ID de soutenance non fourni']);
                                    exit;
                                }
                                
                                try {
                                    // S'assurer que la classe Soutenance est correctement instanciée
                                    if (!isset($soutenanceModel) || !($soutenanceModel instanceof Soutenance)) {
                                        $soutenanceModel = new Soutenance();
                                    }
                                    
                                    $details = $soutenanceModel->getSoutenanceAvecNotes($idSoutenance);
                                    
                                    if ($details) {
                                        header('Content-Type: application/json');
                                        echo json_encode(['success' => true, 'data' => $details]);
                                    } else {
                                        header('Content-Type: application/json');
                                        echo json_encode(['success' => false, 'message' => 'Soutenance non trouvée']);
                                    }
                                } catch (Exception $e) {
                                    header('Content-Type: application/json');
                                    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
                                }
                                exit;
                                break;
                            
                            default:
                                // Redirection si accès direct sans POST ou GET attendu
                                header("Location: ../recherche/depot_soutenance");
                                exit();
                            }
                        } else {
                        // Redirection si accès direct sans POST ou GET attendu
                        header("Location: ../recherche/depot_soutenance");
                        exit();
                    }
                    ?>
                    

