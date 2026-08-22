<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération de l'action (create, update ou delete)
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // Récupération des données communes
    $codeUE = isset($_POST['code']) ? trim($_POST['code']) : '';
    $designationUE = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $semestre_idsemestre = isset($_POST['semestre']) ? intval($_POST['semestre']) : 0;
    $anneeAcadId = isset($_POST['idAnneeAcad']) ? intval($_POST['idAnneeAcad']) : 0;
    $idUser = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;

    $result = false;
    $message = '';

    switch ($action) {
        case 'create':
            // Validation des données obligatoires
            if (empty($codeUE) || empty($designationUE) || $semestre_idsemestre <= 0 || $anneeAcadId <= 0) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Tous les champs obligatoires doivent être remplis.'
                    }).then(() => {
                        window.location.href = '../enseignement/unites_enseignement';
                    });
                </script>";
                exit();
            }

            // Création de l'UE
            $result = $universite->createUE($codeUE, $designationUE, $description, $semestre_idsemestre);
            $message = $result ? 'L\'unité d\'enseignement a été créée avec succès.' : 'Une erreur est survenue lors de la création de l\'unité d\'enseignement.';
            break;
        
        case 'update':
            // Récupération de l'ID de l'UE pour la modification
            $idUE = isset($_POST['idUE']) ? intval($_POST['idUE']) : 0;
            if ($idUE <= 0) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'ID de l\'unité d\'enseignement invalide.'
                    }).then(() => {
                        window.location.href = '../enseignement/unites_enseignement';
                    });
                </script>";
                exit();
            }

            // Validation des données obligatoires
            if (empty($codeUE) || empty($designationUE) || $semestre_idsemestre <= 0) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Tous les champs obligatoires doivent être remplis.'
                    }).then(() => {
                        window.location.href = '../enseignement/unites_enseignement';
                    });
                </script>";
                exit();
            }

            // Modification de l'UE
            $result = $universite->updateUE($idUE, $codeUE, $designationUE, $description, $semestre_idsemestre);
            $message = $result ? 'L\'unité d\'enseignement a été modifiée avec succès.' : 'Une erreur est survenue lors de la modification de l\'unité d\'enseignement.';
            break;
        case 'create_multiple':
            // Validation des données obligatoires
            if (empty($codeUE) || empty($designationUE) || !isset($_POST['semestres']) || empty($_POST['semestres'])) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Tous les champs obligatoires doivent être remplis et au moins un semestre doit être sélectionné.'
                    }).then(() => {
                        window.location.href = '../enseignement/unites_enseignement';
                    });
                </script>";
                exit();
            }
        
            // Récupération des semestres sélectionnés
            $semestres = $_POST['semestres'];
            
            // Création de l'UE dans plusieurs semestres
            $result = $universite->createUEMultiple($codeUE, $designationUE, $description, $semestres);
            $message = $result ? 'L\'unité d\'enseignement a été créée avec succès dans les semestres sélectionnés.' : 'Une erreur est survenue lors de la création de l\'unité d\'enseignement.';
            break;
            
        
            case 'delete':
                // Suppression de l'UE
                $idUE = isset($_POST['idUE']) ? intval($_POST['idUE']) : 0;
                if ($idUE <= 0) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'ID de l\'unité d\'enseignement invalide.'
                        }).then(() => {
                            window.location.href = '../enseignement/unites_enseignement';
                        });
                    </script>";
                    exit();
                }
            
                $deleteResult = $universite->deleteUE($idUE);
                
                if ($deleteResult['success']) {
                    $message = 'L\'unité d\'enseignement a été supprimée avec succès.';
                    $result = true;
                } else {
                    $result = false;
                    switch ($deleteResult['reason']) {
                        case 'not_found':
                            $message = 'L\'unité d\'enseignement n\'existe pas.';
                            break;
                        case 'has_ecues':
                            $message = 'Impossible de supprimer cette unité d\'enseignement car elle contient des ECUEs. Veuillez d\'abord supprimer les ECUEs associées.';
                            break;
                        case 'delete_failed':
                            $message = 'Aucune unité d\'enseignement n\'a été supprimée. Vérifiez qu\'elle n\'est pas utilisée ailleurs.';
                            break;
                        default:
                            $message = 'Une erreur est survenue lors de la suppression de l\'unité d\'enseignement.';
                    }
                }
                break;
            

        case 'import':
            require_once dirname(__DIR__) . '/vendor/autoload.php';
            
            $semestre_id = isset($_POST['semestre_import']) ? intval($_POST['semestre_import']) : 0;
            $skipHeader = isset($_POST['skip_header']) ? (bool)$_POST['skip_header'] : false;
            
            if ($semestre_id <= 0) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Veuillez sélectionner un semestre valide.'
                    }).then(() => {
                        window.location.href = '../enseignement/unites_enseignement';
                    });
                </script>";
                exit();
            }
            
            // Vérifier si un fichier a été uploadé
            if (!isset($_FILES['fichier_import']) || $_FILES['fichier_import']['error'] !== UPLOAD_ERR_OK) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Veuillez sélectionner un fichier valide.'
                    }).then(() => {
                        window.location.href = '../enseignement/unites_enseignement';
                    });
                </script>";
                exit();
            }
            
            // Définir les colonnes à utiliser
            $colCode = isset($_POST['colonne_code']) ? $_POST['colonne_code'] : 'A';
            $colDesignation = isset($_POST['colonne_designation']) ? $_POST['colonne_designation'] : 'B';
            $colDescription = isset($_POST['colonne_description']) ? $_POST['colonne_description'] : 'C';
            
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
                    $code = trim($worksheet->getCell($colCode . $row)->getValue());
                    $designation = trim($worksheet->getCell($colDesignation . $row)->getValue());
                    $description = trim($worksheet->getCell($colDescription . $row)->getValue());
                    
                    // Vérifier les données obligatoires
                    if (empty($code) || empty($designation)) {
                        $errors++;
                        continue;
                    }
                    
                    // Créer l'UE
                    $result = $universite->createUE($code, $designation, $description, $semestre_id);
                    
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
                        html: '{$importedCount} UE(s) importée(s) avec succès.<br>{$errors} erreur(s) rencontrée(s).'
                    }).then(() => {
                        window.location.href = '../enseignement/unites_enseignement';
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
                        window.location.href = '../enseignement/unites_enseignement';
                    });
                </script>";
                exit();
            }
            break;

        default:
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Action non reconnue.'
                }).then(() => {
                    window.location.href = '../enseignement/unites_enseignement';
                });
            </script>";
            exit();
    }
    // Affichage du message de résultat pour les actions principales
    if ($action != 'import') {
        echo "<script>
            Swal.fire({
                icon: '" . ($result ? 'success' : 'error') . "',
                title: '" . ($result ? 'Succès' : 'Erreur') . "',
                text: '" . addslashes($message) . "'
            }).then(() => {
                window.location.href = '../enseignement/unites_enseignement';
            });
        </script>";
    }

// Remplacer la section de traitement GET par celle-ci
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    // Traitement de la suppression via GET
    $idUE = intval($_GET['id']);
    
    if ($idUE <= 0) {
        // Réponse en JSON pour les requêtes AJAX
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID de l\'unité d\'enseignement invalide.']);
        exit();
    }
    
    $result = $universite->deleteUE($idUE);
    
    // Réponse en JSON pour les requêtes AJAX
    header('Content-Type: application/json');
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'L\'unité d\'enseignement a été supprimée avec succès.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Une erreur est survenue lors de la suppression de l\'unité d\'enseignement.']);
    }
    exit();
}

?>
