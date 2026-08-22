<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Ecue.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

$universite = new Universite();
$ecueModel = new Ecue();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération de l'action
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // Récupération des données communes
    $idUser = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
    
    $result = false;
    $message = '';
    $redirectUrl = '';
    
    switch ($action) {
        case 'create':
            // Récupération des données spécifiques
            $ueId = isset($_POST['ueId']) ? intval($_POST['ueId']) : 0;
            $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
            $cmi = isset($_POST['cmi']) ? floatval($_POST['cmi']) : 0;
            $td = isset($_POST['td']) ? floatval($_POST['td']) : 0;
            $tp = isset($_POST['tp']) ? floatval($_POST['tp']) : 0;
            
            // Validation des données obligatoires
            if (empty($designation) || $ueId <= 0) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'La désignation et l\'UE sont obligatoires.'
                    }).then(() => {
                        window.location.href = '../enseignement/ecues?ue={$ueId}';
                    });
                </script>";
                exit();
            }
            
            // Création de l'ECUE
            $result = $ecueModel->createEcue($designation, $cmi, $td, $tp, $ueId, $idUser);
            $message = $result ? 'L\'ECUE a été créé avec succès.' : 'Une erreur est survenue lors de la création de l\'ECUE.';
            $redirectUrl = "../enseignement/ecues?ue={$ueId}";
            break;
        // Ajouter ce nouveau case dans le switch de l'action POST
case 'create_multiple':
    // Récupération des données spécifiques
    $ues = isset($_POST['ues']) ? $_POST['ues'] : [];
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $cmi = isset($_POST['cmi']) ? floatval($_POST['cmi']) : 0;
    $td = isset($_POST['td']) ? floatval($_POST['td']) : 0;
    $tp = isset($_POST['tp']) ? floatval($_POST['tp']) : 0;
    
    // Validation des données obligatoires
    if (empty($designation) || empty($ues)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La désignation et au moins une UE sont obligatoires.'
            }).then(() => {
                window.history.back();
            });
        </script>";
        exit();
    }
    
    // Création de l'ECUE dans chaque UE sélectionnée
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($ues as $ueId) {
        $result = $ecueModel->createEcue($designation, $cmi, $td, $tp, $ueId, $idUser);
        if ($result) {
            $successCount++;
        } else {
            $errorCount++;
        }
    }
    
    // Message de résultat
    if ($successCount > 0) {
        $message = "L'ECUE a été créé avec succès dans $successCount UE(s).";
        if ($errorCount > 0) {
            $message .= " $errorCount erreur(s) rencontrée(s).";
        }
        $result = true;
    } else {
        $message = "Une erreur est survenue lors de la création de l'ECUE.";
        $result = false;
    }
    
    // Redirection vers la page d'origine ou la première UE
    $redirectUeId = isset($ues[0]) ? $ues[0] : 0;
    $redirectUrl = "../enseignement/ecues?ue={$redirectUeId}";
    break;

            
        case 'update':
            // Récupération des données spécifiques
            $idEcue = isset($_POST['idEcue']) ? intval($_POST['idEcue']) : 0;
            $ueId = isset($_POST['ueId']) ? intval($_POST['ueId']) : 0;
            $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
            $cmi = isset($_POST['cmi']) ? floatval($_POST['cmi']) : 0;
            $td = isset($_POST['td']) ? floatval($_POST['td']) : 0;
            $tp = isset($_POST['tp']) ? floatval($_POST['tp']) : 0;
            
            // Validation des données obligatoires
            if ($idEcue <= 0 || empty($designation)) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'ID ou désignation de l\'ECUE invalide.'
                    }).then(() => {
                        window.location.href = '../enseignement/ecues?ue={$ueId}';
                    });
                </script>";
                exit();
            }
            
            // Mise à jour de l'ECUE
            $result = $ecueModel->updateEcue($idEcue, $designation, $cmi, $td, $tp);
            $message = $result ? 'L\'ECUE a été modifié avec succès.' : 'Une erreur est survenue lors de la modification de l\'ECUE.';
            $redirectUrl = "../enseignement/ecues?ue={$ueId}";
            break;
            
        case 'add_teacher':
            // Récupération des données spécifiques
            $idECUE = isset($_POST['idECUE']) ? intval($_POST['idECUE']) : 0;
            $idAgent = isset($_POST['idAgent']) ? intval($_POST['idAgent']) : 0;
            $poste = isset($_POST['poste']) ? trim($_POST['poste']) : '';
            $anneeAcad = isset($_POST['anneeAcad']) ? intval($_POST['anneeAcad']) : 0;
            
            // Validation des données obligatoires
            if ($idECUE <= 0 || $idAgent <= 0 || empty($poste) || $anneeAcad <= 0) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Tous les champs sont obligatoires.'
                    }).then(() => {
                        window.location.href = '../enseignement/cours.details?id={$idECUE}';
                    });
                </script>";
                exit();
            }
            
            // Vérifier si l'enseignant est déjà affecté à cet ECUE
            $existingTeacher = $ecueModel->checkTeacherAssignment($idECUE, $idAgent, $anneeAcad);
            if ($existingTeacher) {
                echo "<script>
                    Swal.fire({
                        icon: 'warning',
                        title: 'Attention',
                        text: 'Cet enseignant est déjà affecté à ce cours.'
                    }).then(() => {
                        window.location.href = '../enseignement/cours.details?id={$idECUE}';
                    });
                </script>";
                exit();
            }
            
            // Ajouter l'enseignant
            $result = $ecueModel->addTeacherToEcue($idECUE, $idAgent, $poste, $anneeAcad);
            $message = $result ? 'L\'enseignant a été ajouté avec succès.' : 'Une erreur est survenue lors de l\'ajout de l\'enseignant.';
            $redirectUrl = "../enseignement/cours.details?id={$idECUE}";
            break;
            
        case 'add_chapter':
            // Récupération des données spécifiques
            $idECUE = isset($_POST['idECUE']) ? intval($_POST['idECUE']) : 0;
            $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $ordre = isset($_POST['ordre']) ? intval($_POST['ordre']) : 1;
            
            // Validation des données obligatoires
            if ($idECUE <= 0 || empty($titre)) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Le titre du chapitre est obligatoire.'
                    }).then(() => {
                        window.location.href = '../enseignement/cours.details?id={$idECUE}';
                    });
                </script>";
                exit();
            }
            
            // Ajouter le chapitre
            $result = $ecueModel->addChapterToEcue($idECUE, $titre, $description, $ordre);
            $message = $result ? 'Le chapitre a été ajouté avec succès.' : 'Une erreur est survenue lors de l\'ajout du chapitre.';
            $redirectUrl = "../enseignement/cours.details?id={$idECUE}";
            break;
            
        case 'add_resource':
            // Récupération des données spécifiques
            $idpartie = isset($_POST['idpartie']) ? intval($_POST['idpartie']) : 0;
            $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $type_ressource = isset($_POST['type_ressource']) ? trim($_POST['type_ressource']) : '';
            $est_payant = isset($_POST['est_payant']) ? 1 : 0;
            $idfrais = isset($_POST['idfrais']) && $est_payant ? intval($_POST['idfrais']) : null;
            
            // Récupérer l'ECUE associé à cette partie pour la redirection
            $chapitre = $ecueModel->getChapterById($idpartie);
            $idECUE = $chapitre ? $chapitre['idECUE'] : 0;
            
            // Validation des données obligatoires
            if ($idpartie <= 0 || empty($titre) || empty($type_ressource)) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Le titre et le type de ressource sont obligatoires.'
                    }).then(() => {
                        window.location.href = '../enseignement/cours.details?id={$idECUE}';
                    });
                </script>";
                exit();
            }
            
            // Gestion du fichier ou du lien externe
            $fichier = null;
            $lien_externe = null;
            
            if ($type_ressource === 'Lien') {
                $lien_externe = isset($_POST['lien_externe']) ? trim($_POST['lien_externe']) : '';
                if (empty($lien_externe)) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Le lien externe est obligatoire pour ce type de ressource.'
                        }).then(() => {
                            window.location.href = '../enseignement/cours.details?id={$idECUE}';
                        });
                    </script>";
                    exit();
                }
            } else {
                // Gestion du fichier uploadé
                if (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Le fichier est obligatoire pour ce type de ressource.'
                        }).then(() => {
                            window.location.href = '../enseignement/cours.details?id={$idECUE}';
                        });
                    </script>";
                    exit();
                }
                
                // Traitement du fichier
                $uploadDir = dirname(__DIR__) . '/uploads/ressources/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileExtension = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
                $fichier = uniqid() . '.' . $fileExtension;
                $uploadFile = $uploadDir . $fichier;
                
                if (!move_uploaded_file($_FILES['fichier']['tmp_name'], $uploadFile)) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Erreur lors de l\'upload du fichier.'
                        }).then(() => {
                            window.location.href = '../enseignement/cours.details?id={$idECUE}';
                        });
                    </script>";
                    exit();
                }
            }
            
            // Ajouter la ressource
            $result = $ecueModel->addResourceToChapter($idpartie, $titre, $description, $type_ressource, $fichier, $lien_externe, $est_payant, $idfrais);
            $message = $result ? 'La ressource a été ajoutée avec succès.' : 'Une erreur est survenue lors de l\'ajout de la ressource.';
            $redirectUrl = "../enseignement/cours.details?id={$idECUE}";
            break;
            
        case 'add_support':
            // Récupération des données spécifiques
            $idECUE = isset($_POST['idECUE']) ? intval($_POST['idECUE']) : 0;
            $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $est_payant = isset($_POST['est_payant']) ? 1 : 0;
            $idfrais = isset($_POST['idfrais']) && $est_payant ? intval($_POST['idfrais']) : null;
            
            // Validation des données obligatoires
            if ($idECUE <= 0 || empty($titre)) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Le titre du support est obligatoire.'
                    }).then(() => {
                        window.location.href = '../enseignement/cours.details?id={$idECUE}';
                    });
                </script>";
                exit();
            }
            
            // Gestion du fichier uploadé
            if (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Le fichier est obligatoire.'
                    }).then(() => {
                        window.location.href = '../enseignement/cours.details?id={$idECUE}';
                    });
                </script>";
                exit();
            }
            
            // Traitement du fichier
            $uploadDir = dirname(__DIR__) . '/uploads/supports/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExtension = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
            $fichier = uniqid() . '.' . $fileExtension;
            $uploadFile = $uploadDir . $fichier;
            
            if (!move_uploaded_file($_FILES['fichier']['tmp_name'], $uploadFile)) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Erreur lors de l\'upload du fichier.'
                    }).then(() => {
                        window.location.href = '../enseignement/cours.details?id={$idECUE}';
                    });
                </script>";
                exit();
            }
            
            // Ajouter le support
            $result = $ecueModel->addSupportToEcue($idECUE, $titre, $description, $fichier, $est_payant, $idfrais);
            $message = $result ? 'Le support a été ajouté avec succès.' : 'Une erreur est survenue lors de l\'ajout du support.';
            $redirectUrl = "../enseignement/cours.details?id={$idECUE}";
            break;
            
        case 'import':
            // Traitement de l'importation depuis un fichier Excel/CSV
            require_once dirname(__DIR__) . '/vendor/autoload.php';
            
            $ueId = isset($_POST['ueId']) ? intval($_POST['ueId']) : 0;
            $skipHeader = isset($_POST['skip_header']) ? (bool)$_POST['skip_header'] : false;
            
            // Vérifier si un fichier a été uploadé
            if (!isset($_FILES['fichier_import']) || $_FILES['fichier_import']['error'] !== UPLOAD_ERR_OK) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Veuillez sélectionner un fichier valide.'
                    }).then(() => {
                        window.location.href = '../enseignement/ecues?ue={$ueId}';
                    });
                </script>";
                exit();
            }
            
            // Définir les colonnes à utiliser
            $colDesignation = isset($_POST['colonne_designation']) ? $_POST['colonne_designation'] : 'A';
            $colCmi = isset($_POST['colonne_cmi']) ? $_POST['colonne_cmi'] : 'B';
            $colTd = isset($_POST['colonne_td']) ? $_POST['colonne_td'] : 'C';
            $colTp = isset($_POST['colonne_tp']) ? $_POST['colonne_tp'] : 'D';
            
            try {
                // Charger le fichier
                $inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($_FILES['fichier_import']['tmp_name']);
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                $spreadsheet = $reader->load($_FILES['fichier_import']['tmp_name']);
                $worksheet = $spreadsheet->getActiveSheet();
                
                $highestRow = $worksheet->getHighestRow();
                $startRow = $skipHeader ? 2 : 1;
                
                $importedCount = 0;
                $errors = 0;
                
                for ($row = $startRow; $row <= $highestRow; $row++) {
                    $designation = trim($worksheet->getCell($colDesignation . $row)->getValue());
                    $cmi = floatval($worksheet->getCell($colCmi . $row)->getValue());
                    $td = floatval($worksheet->getCell($colTd . $row)->getValue());
                    $tp = floatval($worksheet->getCell($colTp . $row)->getValue());
                    
                    // Vérifier les données obligatoires
                    if (empty($designation)) {
                        $errors++;
                        continue;
                    }
                    
                    // Créer l'ECUE
                    $result = $ecueModel->createEcue($designation, $cmi, $td, $tp, $ueId, $idUser);
                    
                    if ($result) {
                        $importedCount++;
                    } else {
                        $errors++;
                    }
                }
                
                echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Importation terminée',
                        html: '{$importedCount} ECUE(s) importé(s) avec succès.<br>{$errors} erreur(s) rencontrée(s).'
                    }).then(() => {
                        window.location.href = '../enseignement/ecues?ue={$ueId}';
                    });
                </script>";
                exit();
                
            } catch (Exception $e) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Une erreur est survenue lors de l\'importation: " . addslashes($e->getMessage()) . "'
                    }).then(() => {
                        window.location.href = '../enseignement/ecues?ue={$ueId}';
                    });
                </script>";
                exit();
            }
            break;
        
            case 'update_chapter':
                // Récupération des données spécifiques
                $idPartie = isset($_POST['idpartie']) ? intval($_POST['idpartie']) : 0;
                $idECUE = isset($_POST['idECUE']) ? intval($_POST['idECUE']) : 0;
                $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
                $description = isset($_POST['description']) ? $_POST['description'] : ''; // Ne pas utiliser trim() pour préserver le HTML
                $ordre = isset($_POST['ordre']) ? intval($_POST['ordre']) : 1;
                
                // Validation des données obligatoires
                if ($idPartie <= 0 || $idECUE <= 0 || empty($titre)) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Le titre du chapitre est obligatoire.'
                        }).then(() => {
                            window.location.href = '../enseignement/cours.details?id={$idECUE}';
                        });
                    </script>";
                    exit();
                }
                
                // Mettre à jour le chapitre
                $result = $ecueModel->updateChapter($idPartie, $titre, $description, $ordre);
                $message = $result ? 'Le chapitre a été mis à jour avec succès.' : 'Une erreur est survenue lors de la mise à jour du chapitre.';
                $redirectUrl = "../enseignement/cours.details?id={$idECUE}";
                break;
            
            
        default:
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Action non reconnue.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
            exit();
    }
    
    // Affichage du message de résultat pour les actions principales (sauf import qui a son propre message)
    if ($action != 'import') {
        echo "<script>
            Swal.fire({
                icon: '" . ($result ? 'success' : 'error') . "',
                title: '" . ($result ? 'Succès' : 'Erreur') . "',
                text: '" . addslashes($message) . "'
            }).then(() => {
                window.location.href = '{$redirectUrl}';
            });
        </script>";
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action'])) {
    // Traitement des actions via GET
    $action = $_GET['action'];
    $idEcue = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $ueId = isset($_GET['ue']) ? intval($_GET['ue']) : 0;
    
    switch ($action) {
        case 'delete':
            // Validation des données
            if ($idEcue <= 0) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'ID de l\'ECUE invalide.'
                    }).then(() => {
                        window.history.back();
                    });
                </script>";
                exit();
            }
            
            // Suppression de l'ECUE
            $result = $ecueModel->deleteEcue($idEcue);
            $message = $result ? 'L\'ECUE a été supprimé avec succès.' : 'Une erreur est survenue lors de la suppression de l\'ECUE.';
            
            echo "<script>
                Swal.fire({
                    icon: '" . ($result ? 'success' : 'error') . "',
                    title: '" . ($result ? 'Succès' : 'Erreur') . "',
                    text: '" . addslashes($message) . "'
                }).then(() => {
                    window.location.href = '../enseignement/ecues?ue={$ueId}';
                });
            </script>";
            break;
            
        case 'delete_teacher':
            $idEnseignantEcue = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;
            
            // Validation des données
            if ($idEnseignantEcue <= 0 || $idEcue <= 0) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'ID de l\'enseignant ou de l\'ECUE invalide.'
                    }).then(() => {
                        window.history.back();
                    });
                </script>";
                exit();
            }
            
            // Suppression de l'affectation de l'enseignant
            $result = $ecueModel->removeTeacherFromEcue($idEnseignantEcue);
            $message = $result ? 'L\'enseignant a été retiré avec succès.' : 'Une erreur est survenue lors du retrait de l\'enseignant.';
            
            echo "<script>
                Swal.fire({
                    icon: '" . ($result ? 'success' : 'error') . "',
                    title: '" . ($result ? 'Succès' : 'Erreur') . "',
                    text: '" . addslashes($message) . "'
                }).then(() => {
                    window.location.href = '../enseignement/cours.details?id={$idEcue}';
                });
            </script>";
            break;
            
        case 'delete_chapter':
            $idPartie = isset($_GET['chapter_id']) ? intval($_GET['chapter_id']) : 0;
            
            // Validation des données
            if ($idPartie <= 0 || $idEcue <= 0) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'ID du chapitre ou de l\'ECUE invalide.'
                    }).then(() => {
                        window.history.back();
                    });
                </script>";
                exit();
            }
            
            // Suppression du chapitre
            $result = $ecueModel->deleteChapter($idPartie);
            $message = $result ? 'Le chapitre a été supprimé avec succès.' : 'Une erreur est survenue lors de la suppression du chapitre.';
            
            echo "<script>
                Swal.fire({
                    icon: '" . ($result ? 'success' : 'error') . "',
                    title: '" . ($result ? 'Succès' : 'Erreur') . "',
                    text: '" . addslashes($message) . "'
                }).then(() => {
                    window.location.href = '../enseignement/cours.details?id={$idEcue}';
                });
            </script>";
            break;
            
        case 'delete_resource':
            $idRessource = isset($_GET['resource_id']) ? intval($_GET['resource_id']) : 0;
            
            // Récupérer les informations de la ressource pour connaître l'ECUE associé
            $ressource = $ecueModel->getResourceById($idRessource);
            if (!$ressource) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Ressource non trouvée.'
                    }).then(() => {
                        window.history.back();
                    });
                </script>";
                exit();
            }
            
            // Récupérer le chapitre pour connaître l'ECUE associé
            $chapitre = $ecueModel->getChapterById($ressource['idpartie']);
            $idECUE = $chapitre ? $chapitre['idECUE'] : 0;
            
            // Suppression de la ressource
            $result = $ecueModel->deleteResource($idRessource);
            $message = $result ? 'La ressource a été supprimée avec succès.' : 'Une erreur est survenue lors de la suppression de la ressource.';
            
            echo "<script>
                Swal.fire({
                    icon: '" . ($result ? 'success' : 'error') . "',
                    title: '" . ($result ? 'Succès' : 'Erreur') . "',
                    text: '" . addslashes($message) . "'
                }).then(() => {
                    window.location.href = '../enseignement/cours.details?id={$idECUE}';
                });
            </script>";
            break;
            
        case 'delete_support':
            $idSupport = isset($_GET['support_id']) ? intval($_GET['support_id']) : 0;
            
            // Validation des données
            if ($idSupport <= 0 || $idEcue <= 0) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'ID du support ou de l\'ECUE invalide.'
                    }).then(() => {
                        window.history.back();
                    });
                </script>";
                exit();
            }
            
            // Suppression du support
            $result = $ecueModel->deleteSupport($idSupport);
            $message = $result ? 'Le support a été supprimé avec succès.' : 'Une erreur est survenue lors de la suppression du support.';
            
            echo "<script>
                Swal.fire({
                    icon: '" . ($result ? 'success' : 'error') . "',
                    title: '" . ($result ? 'Succès' : 'Erreur') . "',
                    text: '" . addslashes($message) . "'
                }).then(() => {
                    window.location.href = '../enseignement/cours.details?id={$idEcue}';
                });
            </script>";
            break;
            
        default:
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Action non reconnue.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
            exit();
    }
} else {
    // Redirection si accès direct au fichier
    header("Location: ../enseignement/unites_enseignement");
    exit();
}
?>

