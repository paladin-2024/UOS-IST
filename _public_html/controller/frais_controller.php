<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Frais.php';

$universite = new Frais();

//Frais soutenances


// Pour le cas de création d'un frais de soutenance
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create' && $_POST['type_frais'] == 'soutenance') {
    // Récupération des données communes
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $montant = isset($_POST['montant']) ? floatval($_POST['montant']) : 0;
    $devise = isset($_POST['devise']) ? trim($_POST['devise']) : 'USD';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $anneeAcadId = isset($_POST['idAnneeAcad']) ? intval($_POST['idAnneeAcad']) : 0;
    $sectionId = isset($_POST['section']) ? intval($_POST['section']) : 0;
    $idUser = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
    $estObligatoire = isset($_POST['estObligatoire']) ? 1 : 0;
    
    // Validation des données obligatoires
    if (empty($designation) || $montant <= 0 || empty($devise) || $sectionId <= 0 || $anneeAcadId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../frais/configuration_frais?type_frais=soutenance';
            });
        </script>";
        exit();
    }
    
    // Création du frais de soutenance
    $result = $universite->createFraisSoutenance($designation, $montant, $devise, $description, $anneeAcadId,$sectionId, $estObligatoire, $idUser);
    $message = $result ? 'Le frais de soutenance a été créé avec succès.' : 'Une erreur est survenue lors de la création du frais de soutenance.';
    
    echo "<script>
        Swal.fire({
            icon: '" . ($result ? 'success' : 'error') . "',
            title: '" . ($result ? 'Succès' : 'Erreur') . "',
            text: '" . addslashes($message) . "'
        }).then(() => {
            window.location.href = '../frais/configuration_frais?type_frais=soutenance';
        });
    </script>";
    exit();
}

// Pour le cas de mise à jour d'un frais de soutenance
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update' && $_POST['type_frais'] == 'soutenance') {
    // Récupération des données
    $idFrais = isset($_POST['idFrais']) ? intval($_POST['idFrais']) : 0;
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $montant = isset($_POST['montant']) ? floatval($_POST['montant']) : 0;
    $devise = isset($_POST['devise']) ? trim($_POST['devise']) : 'USD';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $sectionId = isset($_POST['section']) ? intval($_POST['section']) : 0;
    $estObligatoire = isset($_POST['estObligatoire']) ? 1 : 0;
    
    // Validation des données obligatoires
    if ($idFrais <= 0 || empty($designation) || $montant <= 0 || empty($devise) || $sectionId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../frais/configuration_frais?type_frais=soutenance';
            });
        </script>";
        exit();
    }
    
    // Mise à jour du frais de soutenance
    $result = $universite->updateFraisSoutenance($idFrais, $designation, $montant, $devise, $description, $sectionId, $estObligatoire);
    $message = $result ? 'Le frais de soutenance a été modifié avec succès.' : 'Une erreur est survenue lors de la modification du frais de soutenance.';
    
    echo "<script>
        Swal.fire({
            icon: '" . ($result ? 'success' : 'error') . "',
            title: '" . ($result ? 'Succès' : 'Erreur') . "',
            text: '" . addslashes($message) . "'
        }).then(() => {
            window.location.href = '../frais/configuration_frais?type_frais=soutenance';
        });
    </script>";
    exit();
}

// Ajoutez ce code pour la gestion des paiements de frais de soutenance
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'pay_soutenance') {
    // Récupération des données du paiement
    $etudiantId = isset($_POST['etudiantId']) ? intval($_POST['etudiantId']) : 0;
    $fraisSoutenanceId = isset($_POST['fraisSoutenanceId']) ? intval($_POST['fraisSoutenanceId']) : 0;
    $montantPaye = isset($_POST['montantPaye']) ? floatval($_POST['montantPaye']) : 0;
    $referencePaiement = isset($_POST['referencePaiement']) ? trim($_POST['referencePaiement']) : '';
    $modePaiement = isset($_POST['modePaiement']) ? trim($_POST['modePaiement']) : '';
    $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
    $anneeAcadId = isset($_POST['anneeAcadId']) ? intval($_POST['anneeAcadId']) : 0;
    $idUser = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
    
    // Validation des données obligatoires
    if ($etudiantId <= 0 || $fraisSoutenanceId <= 0 || $montantPaye <= 0 || empty($referencePaiement) || empty($modePaiement) || $anneeAcadId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../frais/paiement_soutenance?etudiant=" . $etudiantId . "';
            });
        </script>";
        exit();
    }
    
    
    
    // Enregistrement du paiement
    $result = $universite->enregistrerPaiementSoutenance($fraisSoutenanceId, $etudiantId, $montantPaye, $referencePaiement, $modePaiement, $commentaire, $anneeAcadId, $idUser);
    $message = $result ? 'Le paiement a été enregistré avec succès.' : 'Une erreur est survenue lors de l\'enregistrement du paiement.';
    
    echo "<script>
        Swal.fire({
            icon: '" . ($result ? 'success' : 'error') . "',
            title: '" . ($result ? 'Succès' : 'Erreur') . "',
            text: '" . addslashes($message) . "'
        }).then(() => {
            window.location.href = '../frais/paiement_soutenance?etudiant=" . $etudiantId . "';
        });
    </script>";
    exit();
}

// Suppression d'un paiement de frais de soutenance
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'delete_paiement_soutenance' && isset($_GET['id'])) {
    $idPaiement = intval($_GET['id']);
    $etudiantId = isset($_GET['etudiant']) ? intval($_GET['etudiant']) : 0;
    
    if ($idPaiement <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de paiement invalide.'
            }).then(() => {
                window.location.href = '../frais/paiement_soutenance" . ($etudiantId > 0 ? "?etudiant=" . $etudiantId : "") . "';
            });
        </script>";
        exit();
    }
    
    // Supprimer le paiement
    $result = $universite->deletePaiementSoutenance($idPaiement);
    $message = $result ? 'Le paiement a été supprimé avec succès.' : 'Une erreur est survenue lors de la suppression du paiement.';
    
    echo "<script>
        Swal.fire({
            icon: '" . ($result ? 'success' : 'error') . "',
            title: '" . ($result ? 'Succès' : 'Erreur') . "',
            text: '" . addslashes($message) . "'
        }).then(() => {
            window.location.href = '../frais/paiement_soutenance" . ($etudiantId > 0 ? "?etudiant=" . $etudiantId : "") . "';
        });
    </script>";
    exit();
}

// Mise à jour d'un paiement de frais de soutenance
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_paiement_soutenance') {
    $idPaiement = isset($_POST['idPaiement']) ? intval($_POST['idPaiement']) : 0;
    $etudiantId = isset($_POST['etudiantId']) ? intval($_POST['etudiantId']) : 0;
    $montantPaye = isset($_POST['montantPaye']) ? floatval($_POST['montantPaye']) : 0;
    $referencePaiement = isset($_POST['referencePaiement']) ? trim($_POST['referencePaiement']) : '';
    $modePaiement = isset($_POST['modePaiement']) ? trim($_POST['modePaiement']) : '';
    $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
    
    // Validation
    if ($idPaiement <= 0 || $montantPaye <= 0 || empty($referencePaiement) || empty($modePaiement)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis correctement.'
            }).then(() => {
                window.location.href = '../frais/detail_paiement_soutenance?id=" . $idPaiement . "';
            });
        </script>";
        exit();
    }
    
    // Mettre à jour le paiement
    $result = $universite->updatePaiementSoutenance($idPaiement, $montantPaye, $referencePaiement, $modePaiement, $commentaire);
    
    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le paiement a été mis à jour avec succès.'
            }).then(() => {
                window.location.href = '../frais/detail_paiement_soutenance?id=" . $idPaiement . "';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la mise à jour du paiement.'
            }).then(() => {
                window.location.href = '../frais/detail_paiement_soutenance?id=" . $idPaiement . "';
            });
        </script>";
    }
    exit();
}



//FIN SOUTENANCES

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération de l'action (create, update ou delete)
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // Récupération des données communes
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $montant = isset($_POST['montant']) ? floatval($_POST['montant']) : 0;
    $devise = isset($_POST['devise']) ? trim($_POST['devise']) : 'USD';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $anneeAcadId = isset($_POST['idAnneeAcad']) ? intval($_POST['idAnneeAcad']) : 0;
    $idUser = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
    $type_frais = isset($_POST['type_frais']) ? $_POST['type_frais'] : 'academique';

    $result = false;
    $message = '';

    switch ($action) {
        case 'create':
            // Validation des données obligatoires
            if (empty($designation) || $montant <= 0 || empty($devise) || $anneeAcadId <= 0) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Tous les champs obligatoires doivent être remplis.'
                    }).then(() => {
                        window.location.href = '../frais/configuration_frais?type_frais={$type_frais}';
                    });
                </script>";
                exit();
            }

            if ($type_frais == 'academique') {
                // Récupération des données spécifiques aux frais académiques
                $promotionId = isset($_POST['promotion']) ? intval($_POST['promotion']) : 0;
                $estObligatoire = isset($_POST['estObligatoire']) ? 1 : 0;
                
                if ($promotionId <= 0) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Veuillez sélectionner une promotion valide.'
                        }).then(() => {
                            window.location.href = '../frais/configuration_frais?type_frais={$type_frais}';
                        });
                    </script>";
                    exit();
                }
                
                // Création du frais académique
                $result = $universite->createFrais($designation, $montant, $devise, $description, $estObligatoire, $promotionId, $anneeAcadId);
                $message = $result ? 'Le frais académique a été créé avec succès.' : 'Une erreur est survenue lors de la création du frais académique.';
            } else {
                // Création du frais de soutenance
                $result = $universite->createFraisSoutenance($designation, $montant, $devise, $description, $anneeAcadId, $idUser);
                $message = $result ? 'Le frais de soutenance a été créé avec succès.' : 'Une erreur est survenue lors de la création du frais de soutenance.';
            }
            break;
        
        case 'update':
            // Récupération de l'ID du frais pour la modification
            $idFrais = isset($_POST['idFrais']) ? intval($_POST['idFrais']) : 0;
            if ($idFrais <= 0) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'ID du frais invalide.'
                    }).then(() => {
                        window.location.href = '../frais/configuration_frais?type_frais={$type_frais}';
                    });
                </script>";
                exit();
            }

            // Validation des données obligatoires
            if (empty($designation) || $montant <= 0 || empty($devise)) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Tous les champs obligatoires doivent être remplis.'
                    }).then(() => {
                        window.location.href = '../frais/configuration_frais?type_frais={$type_frais}';
                    });
                </script>";
                exit();
            }

            if ($type_frais == 'academique') {
                // Récupération des données spécifiques aux frais académiques
                $promotionId = isset($_POST['promotion']) ? intval($_POST['promotion']) : 0;
                $estObligatoire = isset($_POST['estObligatoire']) ? 1 : 0;
                
                if ($promotionId <= 0) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Veuillez sélectionner une promotion valide.'
                        }).then(() => {
                            window.location.href = '../frais/configuration_frais?type_frais={$type_frais}';
                        });
                    </script>";
                    exit();
                }
                
                // Modification du frais académique
                $result = $universite->updateFrais($idFrais, $designation, $montant, $devise, $description, $estObligatoire, $promotionId);
                $message = $result ? 'Le frais académique a été modifié avec succès.' : 'Une erreur est survenue lors de la modification du frais académique.';
            } else {
                // Modification du frais de soutenance
                $result = $universite->updateFraisSoutenance($idFrais, $designation, $montant, $devise, $description, $sectionId, $estObligatoire);	
                $message = $result ? 'Le frais de soutenance a été modifié avec succès.' : 'Une erreur est survenue lors de la modification du frais de soutenance.';
            }
            break;
        
        case 'delete':
            // Suppression du frais
            $idFrais = isset($_POST['idFrais']) ? intval($_POST['idFrais']) : 0;
            if ($idFrais <= 0) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'ID du frais invalide.'
                    }).then(() => {
                        window.location.href = '../frais/configuration_frais?type_frais={$type_frais}';
                    });
                </script>";
                exit();
            }
        
            if ($type_frais == 'academique') {
                $result = $universite->deleteFrais($idFrais);
                $message = $result ? 'Le frais académique a été supprimé avec succès.' : 'Une erreur est survenue lors de la suppression du frais académique.';
            } else {
                $result = $universite->deleteFraisSoutenance($idFrais);
                $message = $result ? 'Le frais de soutenance a été supprimé avec succès.' : 'Une erreur est survenue lors de la suppression du frais de soutenance.';
            }
            break;

        case 'import':
            require_once dirname(__DIR__) . '/vendor/autoload.php';
            
            if ($type_frais == 'academique') {
                $promotionId = isset($_POST['promotion_import']) ? intval($_POST['promotion_import']) : 0;
                
                if ($promotionId <= 0) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Veuillez sélectionner une promotion valide.'
                        }).then(() => {
                            window.location.href = '../frais/configuration_frais?type_frais={$type_frais}';
                        });
                    </script>";
                    exit();
                }
            } else {
                $sectionId = isset($_POST['section_import']) ? intval($_POST['section_import']) : 0;
                
                if ($sectionId <= 0) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Veuillez sélectionner une section valide.'
                        }).then(() => {
                            window.location.href = '../frais/configuration_frais?type_frais={$type_frais}';
                        });
                    </script>";
                    exit();
                }
            }
            
            // Vérifier si un fichier a été uploadé
            if (!isset($_FILES['fichier_import']) || $_FILES['fichier_import']['error'] !== UPLOAD_ERR_OK) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Veuillez sélectionner un fichier valide.'
                    }).then(() => {
                        window.location.href = '../frais/configuration_frais?type_frais={$type_frais}';
                    });
                </script>";
                exit();
            }
            
            // Définir les colonnes à utiliser
            $colDesignation = isset($_POST['colonne_designation']) ? $_POST['colonne_designation'] : 'A';
            $colMontant = isset($_POST['colonne_montant']) ? $_POST['colonne_montant'] : 'B';
            $colDevise = isset($_POST['colonne_devise']) ? $_POST['colonne_devise'] : 'C';
            $colDescription = isset($_POST['colonne_description']) ? $_POST['colonne_description'] : 'D';
            $colObligatoire = isset($_POST['colonne_obligatoire']) ? $_POST['colonne_obligatoire'] : 'E';
            
            $skipHeader = isset($_POST['skip_header']) ? (bool)$_POST['skip_header'] : false;
            
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
                    $montant = floatval(trim($worksheet->getCell($colMontant . $row)->getValue()));
                    $devise = trim($worksheet->getCell($colDevise . $row)->getValue());
                    $description = trim($worksheet->getCell($colDescription . $row)->getValue());
                    
                    // Vérifier les données obligatoires
                    if (empty($designation) || $montant <= 0 || empty($devise)) {
                        $errors++;
                        continue;
                    }
                    
                    if ($type_frais == 'academique') {
                        // Récupérer si le frais est obligatoire
                        $obligatoireCell = $worksheet->getCell($colObligatoire . $row)->getValue();
                        $estObligatoire = (!empty($obligatoireCell) && (strtolower($obligatoireCell) == 'oui' || $obligatoireCell == '1')) ? 1 : 0;
                        
                        // Créer le frais académique
                        $result = $universite->createFrais($designation, $montant, $devise, $description, $estObligatoire, $promotionId, $anneeAcadId);
                    } else {
                        // Créer le frais de soutenance
                        $result = $universite->createFraisSoutenance($designation, $montant, $devise, $description, $anneeAcadId, $idUser);
                    }
                    
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
                        html: '{$importedCount} frais importé(s) avec succès.<br>{$errors} erreur(s) rencontrée(s).'
                    }).then(() => {
                        window.location.href = '../frais/configuration_frais?type_frais={$type_frais}';
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
                        window.location.href = '../frais/configuration_frais?type_frais={$type_frais}';
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
                    window.location.href = '../frais/configuration_frais';
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
                window.location.href = '../frais/configuration_frais?type_frais={$type_frais}';
            });
        </script>";
    }

} elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && isset($_GET['type'])) {
    // Traitement de la suppression via GET (pour les liens de suppression directs)
    $idFrais = intval($_GET['id']);
    $type_frais = $_GET['type'];
    
    if ($idFrais <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title:             }).then(() => {
                window.location.href = '../frais/configuration_frais?type_frais={$type_frais}';
            });
        </script>";
        exit();
    }
    
    if ($type_frais == 'academique') {
        $result = $universite->deleteFrais($idFrais);
        $message = $result ? 'Le frais académique a été supprimé avec succès.' : 'Une erreur est survenue lors de la suppression du frais académique.';
    } else {
        $result = $universite->deleteFraisSoutenance($idFrais);
        $message = $result ? 'Le frais de soutenance a été supprimé avec succès.' : 'Une erreur est survenue lors de la suppression du frais de soutenance.';
    }
    
    echo "<script>
        Swal.fire({
            icon: '" . ($result ? 'success' : 'error') . "',
            title: '" . ($result ? 'Succès' : 'Erreur') . "',
            text: '" . addslashes($message) . "'
        }).then(() => {
            window.location.href = '../frais/configuration_frais?type_frais={$type_frais}';
        });
    </script>";
    
} else {
    // Redirection si accès direct au fichier
    header("Location: ../frais/configuration_frais");
    exit();
}
?>
