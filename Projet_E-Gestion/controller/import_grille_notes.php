<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Deliberation.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous devez être connecté pour effectuer cette action.'
        }).then(() => {
            window.location.href = '../login';
        });
    </script>";
    exit;
}

// Vérifier si un fichier a été téléchargé
if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Erreur lors du téléchargement du fichier.'
        }).then(() => {
            window.history.back();
        });
    </script>";
    exit;
}

try {
    $deliberation = new Deliberation();
    $ecue = new Ecue();
    $universite = new Universite();
    $agent = new Agent();
    
    // Charger le fichier Excel
    $inputFileName = $_FILES['excelFile']['tmp_name'];
    $spreadsheet = IOFactory::load($inputFileName);
    
    // Vérifier s'il s'agit d'une grille modifiable (nouveau format)
    $isGrilleModifiable = false;
    $customProperties = $spreadsheet->getProperties()->getCustomProperties();
    if (in_array('BureauId', $customProperties)) {
        $isGrilleModifiable = true;
    }
    
    // Variables communes
    $etudiantsTraites = 0;
    $etudiantsAvecModifications = 0;
    $notesModifiees = [];
    $successCount = 0;
    $errorCount = 0;
    $bureauId = 0;
    $promotionId = 0;
    $sessionId = 0;
    $anneeId = 0;
    $semestreId = 0;
    $afficherDeuxSemestres = false;
    
    if ($isGrilleModifiable) {
        // Traitement pour le nouveau format (grille modifiable)
        $properties = $spreadsheet->getProperties();
        $bureauId = intval($properties->getCustomPropertyValue('BureauId'));
        $promotionId = intval($properties->getCustomPropertyValue('PromotionId'));
        $sessionId = intval($properties->getCustomPropertyValue('SessionId'));
        $anneeId = intval($properties->getCustomPropertyValue('AnneeId'));
        $afficherDeuxSemestres = $properties->getCustomPropertyValue('DeuxSemestres') == '1';
        
        // Accéder à la feuille de métadonnées
        $metadataSheet = $spreadsheet->getSheetByName('Metadata');
        if (!$metadataSheet) {
            throw new Exception("Le fichier ne contient pas les métadonnées nécessaires.");
        }
        
        // Extraire le mappage des notes depuis les métadonnées
        // Structure : Type='Note', ID=ecueId, Cellule=cellule complète, Info=matricule
        $notesToProcess = [];
        $ecueNames = [];
        
        $row = 2;
        while ($metadataSheet->getCell('A' . $row)->getValue()) {
            $type = trim($metadataSheet->getCell('A' . $row)->getValue());
            $id = $metadataSheet->getCell('B' . $row)->getValue();
            $cellule = trim($metadataSheet->getCell('C' . $row)->getValue());
            $info = trim($metadataSheet->getCell('D' . $row)->getValue());
            
            if ($type == 'ECUE') {
                // Stocker les noms des ECUE pour le debug
                $ecueNames[intval($id)] = $info;
            } elseif ($type == 'Note') {
                // Pour chaque note : ecueId, cellule, matricule
                $notesToProcess[] = [
                    'ecue_id' => intval($id),
                    'cellule' => $cellule,
                    'matricule' => $info
                ];
            }
            $row++;
        }
        
        // Debug : afficher les mappings récupérés
        error_log("=== DEBUG IMPORT GRILLE MODIFIABLE ===");
        error_log("Nombre d'ECUE trouvés: " . count($ecueNames));
        error_log("Nombre de notes à traiter: " . count($notesToProcess));
        error_log("Premiers ECUE: " . print_r(array_slice($ecueNames, 0, 3, true), true));
        error_log("Premières notes: " . print_r(array_slice($notesToProcess, 0, 5), true));
        
        // Accéder à la feuille principale
        $sheet = $spreadsheet->getActiveSheet();
        
        // Variables pour le traitement
        $motif = "Importation depuis grille modifiable";
        $matriculesModifies = [];
        $matriculesTraites = [];
        
        // Traiter chaque note
        foreach ($notesToProcess as $noteInfo) {
            $ecueId = $noteInfo['ecue_id'];
            $cellule = $noteInfo['cellule'];
            $matricule = $noteInfo['matricule'];
            
            // Compter les étudiants uniques traités
            if (!in_array($matricule, $matriculesTraites)) {
                $matriculesTraites[] = $matricule;
            }
            
            // Récupérer la valeur de la cellule (calculée pour gérer les formules)
            try {
                $cellValue = $sheet->getCell($cellule)->getCalculatedValue();
            } catch (Exception $e) {
                error_log("Erreur lecture cellule $cellule: " . $e->getMessage());
                $cellValue = $sheet->getCell($cellule)->getValue();
            }
            
            // Nettoyer et convertir la valeur
            $cellValue = trim(strval($cellValue));
            $mf = null;
            
            // Vérifier si la cellule contient une valeur numérique valide
            if ($cellValue !== '' && $cellValue !== null && is_numeric($cellValue)) {
                $mf = round(floatval($cellValue), 2);
            }
            
            // Log de la valeur lue
            error_log("Lecture - Matricule: $matricule, ECUE: $ecueId, Cellule: $cellule, Valeur brute: '$cellValue', MF: " . ($mf ?? 'null'));
            
            // Vérifier que la note est dans l'intervalle valide (0-20)
            $validMF = ($mf === null) || ($mf >= 0 && $mf <= 20);
            
            if ($validMF) {
                // Récupérer les anciennes valeurs avant modification
                $anciennesCotes = $universite->getCoteGrille($ecueId, $sessionId, $anneeId, $matricule);
                
                // Pour une grille simplifiée, CC et EX sont null
                $cc = null;
                $ex = null;
                
                // Récupérer l'ancienne valeur MF
                $mf_avant = null;
                $cc_avant = null;
                $ex_avant = null;
                
                if ($anciennesCotes && isset($anciennesCotes['MF'])) {
                    $cc_avant = $anciennesCotes['CC'];
                    $ex_avant = $anciennesCotes['EX'];
                    $mf_avant = $anciennesCotes['MF'] !== null ? round(floatval($anciennesCotes['MF']), 2) : null;
                }
                
                // Log des valeurs pour comparaison
                error_log("Comparaison - Matricule: $matricule, ECUE: $ecueId, Avant: " . ($mf_avant ?? 'null') . ", Après: " . ($mf ?? 'null'));
                
                // Vérifier si la valeur a changé (comparaison plus robuste)
                $hasChanged = false;
                if ($mf_avant === null && $mf !== null) {
                    $hasChanged = true;
                    error_log("Changement détecté (null -> valeur): Matricule $matricule, ECUE $ecueId, Nouvelle valeur: $mf");
                } elseif ($mf_avant !== null && $mf === null) {
                    $hasChanged = true;
                    error_log("Changement détecté (valeur -> null): Matricule $matricule, ECUE $ecueId, Ancienne valeur: $mf_avant");
                } elseif ($mf_avant !== null && $mf !== null && abs($mf_avant - $mf) >= 0.01) {
                    $hasChanged = true;
                    error_log("Changement détecté (valeur modifiée): Matricule $matricule, ECUE $ecueId, Avant: $mf_avant, Après: $mf");
                }
                
                // Sauvegarder les nouvelles notes
                $result = $universite->saveCoteGrille(
                    $ecueId, 
                    $sessionId, 
                    $anneeId, 
                    $matricule, 
                    $cc, 
                    $ex, 
                    $mf,
                    $_SESSION['id']
                );
                
                if ($result) {
                    $successCount++;
                    
                    // Si la valeur a changé, enregistrer l'historique
                    if ($hasChanged) {
                        // Marquer l'étudiant comme modifié
                        if (!in_array($matricule, $matriculesModifies)) {
                            $matriculesModifies[] = $matricule;
                        }
                        
                        // Enregistrer l'historique
                        $historiqueResult = $universite->saveHistoriqueCotes(
                            $ecueId, 
                            $sessionId, 
                            $anneeId, 
                            $matricule, 
                            $cc_avant, 
                            $ex_avant, 
                            $mf_avant, 
                            $cc, 
                            $ex, 
                            $mf, 
                            $motif, 
                            $_SESSION['id']
                        );
                        
                        // Ajouter aux notes modifiées pour le rapport
                        $notesModifiees[] = [
                            'matricule' => $matricule,
                            'ecue_id' => $ecueId,
                            'cc_avant' => $cc_avant,
                            'ex_avant' => $ex_avant,
                            'mf_avant' => $mf_avant,
                            'cc_apres' => $cc,
                            'ex_apres' => $ex,
                            'mf_apres' => $mf
                        ];
                        
                        error_log("Modification enregistrée - Matricule: $matricule, ECUE: $ecueId");
                    }
                } else {
                    $errorCount++;
                    error_log("Erreur sauvegarde: Matricule $matricule, ECUE $ecueId");
                }
                        } else {
                $errorCount++;
                error_log("Note invalide - Matricule: $matricule, ECUE: $ecueId, Valeur: '" . $cellValue . "', MF calculé: " . ($mf ?? 'null'));
            }
        }
        
        // Compter les étudiants traités et avec modifications
        $etudiantsTraites = count($matriculesTraites);
        $etudiantsAvecModifications = count($matriculesModifies);
        
    } else {
        // Traitement pour l'ancien format (grille complète)
        // Récupérer les métadonnées du fichier
        $metadataSheet = $spreadsheet->getSheetByName('Metadata');
        if (!$metadataSheet) {
            throw new Exception("Le fichier ne contient pas les métadonnées nécessaires.");
        }
        
        // Extraire les métadonnées (ancien format)
        $fileToken = $metadataSheet->getCell('B1')->getValue();
        $bureauId = intval($metadataSheet->getCell('B2')->getValue());
        $promotionId = intval($metadataSheet->getCell('B3')->getValue());
        $sessionId = intval($metadataSheet->getCell('B4')->getValue());
        $anneeId = intval($metadataSheet->getCell('B5')->getValue());
        $semestreId = intval($metadataSheet->getCell('B6')->getValue());
        $afficherDeuxSemestres = $metadataSheet->getCell('B7')->getValue() == '1';
        
        // Vérifier si les métadonnées sont valides
        if (!$bureauId || !$promotionId || !$sessionId || !$anneeId || (!$semestreId && !$afficherDeuxSemestres)) {
            throw new Exception("Les métadonnées du fichier sont invalides.");
        }
        
        // Extraire le mappage des ECUE (ancien format)
        $ecueMapping = [];
        $row = 11;
        while ($metadataSheet->getCell('A' . $row)->getValue()) {
            $ecueId = intval($metadataSheet->getCell('A' . $row)->getValue());
            $ccColumn = $metadataSheet->getCell('B' . $row)->getValue();
            $exColumn = $metadataSheet->getCell('C' . $row)->getValue();
            
            $ecueMapping[$ecueId] = [
                'cc_column' => $ccColumn,
                'ex_column' => $exColumn
            ];
            
            $row++;
        }
        
        // Extraire le mappage des étudiants (ancien format)
        $etudiantMapping = [];
        $row = 11;
        while ($metadataSheet->getCell('F' . $row)->getValue()) {
            $matricule = $metadataSheet->getCell('F' . $row)->getValue();
            $dataRow = intval($metadataSheet->getCell('G' . $row)->getValue());
            
            $etudiantMapping[$matricule] = $dataRow;
            
            $row++;
        }
        
        // Accéder à la feuille principale
        $sheet = $spreadsheet->getActiveSheet();
        
        // Variables pour le traitement
        $motif = "Importation depuis Excel";
        $matriculesModifies = [];
        
        // Traiter chaque étudiant et chaque ECUE (ancien format)
        foreach ($etudiantMapping as $matricule => $dataRow) {
            $etudiantsTraites++;
            $etudiantModifie = false;
            
            foreach ($ecueMapping as $ecueId => $columns) {
                $ccColumn = $columns['cc_column'];
                $exColumn = $columns['ex_column'];
                
                // Récupérer les valeurs des cellules
                $ccValue = $sheet->getCell($ccColumn . $dataRow)->getValue();
                $exValue = $sheet->getCell($exColumn . $dataRow)->getValue();
                
                // Convertir en nombres si possible
                $cc = is_numeric($ccValue) ? floatval($ccValue) : null;
                $ex = is_numeric($exValue) ? floatval($exValue) : null;
                
                // Vérifier que les notes sont dans l'intervalle valide (0-20)
                $validCC = ($cc === null) || ($cc >= 0 && $cc <= 20);
                $validEX = ($ex === null) || ($ex >= 0 && $ex <= 20);
                
                if ($validCC && $validEX) {
                    // Récupérer la configuration de pondération pour cet ECUE
                    $config = $ecue->getConfigurationMoyenne($ecueId, $anneeId, $sessionId);
                    $ponderationCC = $config ? $config['ponderation_cc'] : 0.4;
                    $ponderationEX = $config ? $config['ponderation_ex'] : 0.6;
                    
                    // Calculer la moyenne finale
                    $mf = null;
                    if ($cc !== null && $ex !== null) {
                        $mf = ($cc * $ponderationCC) + ($ex * $ponderationEX);
                    } elseif ($ex !== null) {
                        $mf = $ex;
                    } elseif ($cc !== null) {
                        $mf = $cc;
                    }
                    
                    // Récupérer les anciennes valeurs avant modification
                    $anciennesCotes = $universite->getCoteGrille($ecueId, $sessionId, $anneeId, $matricule);
                    
                    // Sauvegarder les nouvelles notes
                    $result = $universite->saveCoteGrille(
                        $ecueId, 
                        $sessionId, 
                        $anneeId, 
                        $matricule, 
                        $cc, 
                        $ex, 
                        $mf,
                        $_SESSION['id']
                    );
                    
                    if ($result) {
                        $successCount++;
                        
                        // Si la sauvegarde a réussi, enregistrer l'historique si les valeurs ont changé
                        if ($anciennesCotes) {
                            $cc_avant = $anciennesCotes['CC'];
                            $ex_avant = $anciennesCotes['EX'];
                            $mf_avant = $anciennesCotes['MF'];
                            
                            // Vérifier si les valeurs ont changé avec une comparaison plus robuste
                            $cc_changed = false;
                            $ex_changed = false;
                            $mf_changed = false;
                            
                            if ($cc_avant === null && $cc !== null) {
                                $cc_changed = true;
                            } elseif ($cc_avant !== null && $cc === null) {
                                $cc_changed = true;
                            } elseif ($cc_avant !== null && $cc !== null && abs(floatval($cc_avant) - floatval($cc)) >= 0.01) {
                                $cc_changed = true;
                            }
                            
                            if ($ex_avant === null && $ex !== null) {
                                $ex_changed = true;
                            } elseif ($ex_avant !== null && $ex === null) {
                                $ex_changed = true;
                            } elseif ($ex_avant !== null && $ex !== null && abs(floatval($ex_avant) - floatval($ex)) >= 0.01) {
                                $ex_changed = true;
                            }
                            
                            if ($mf_avant === null && $mf !== null) {
                                $mf_changed = true;
                            } elseif ($mf_avant !== null && $mf === null) {
                                $mf_changed = true;
                            } elseif ($mf_avant !== null && $mf !== null && abs(floatval($mf_avant) - floatval($mf)) >= 0.01) {
                                $mf_changed = true;
                            }
                            
                            if ($cc_changed || $ex_changed || $mf_changed) {
                                $etudiantModifie = true;
                                
                                // Enregistrer l'historique
                                $universite->saveHistoriqueCotes(
                                    $ecueId, 
                                    $sessionId, 
                                    $anneeId, 
                                    $matricule, 
                                    $cc_avant, 
                                    $ex_avant, 
                                    $mf_avant, 
                                    $cc, 
                                    $ex, 
                                    $mf, 
                                    $motif, 
                                    $_SESSION['id']
                                );
                                
                                // Ajouter aux notes modifiées pour le rapport
                                $notesModifiees[] = [
                                    'matricule' => $matricule,
                                    'ecue_id' => $ecueId,
                                    'cc_avant' => $cc_avant,
                                    'ex_avant' => $ex_avant,
                                    'mf_avant' => $mf_avant,
                                    'cc_apres' => $cc,
                                    'ex_apres' => $ex,
                                    'mf_apres' => $mf
                                ];
                            }
                        }
                    } else {
                        $errorCount++;
                    }
                } else {
                    $errorCount++;
                }
            }
            
            // Marquer l'étudiant comme modifié s'il a eu des changements
            if ($etudiantModifie && !in_array($matricule, $matriculesModifies)) {
                $matriculesModifies[] = $matricule;
            }
        }
        
        // Compter les étudiants avec modifications
        $etudiantsAvecModifications = count($matriculesModifies);
    }
    
    // Vérifier si l'utilisateur est autorisé
    $isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
    $userId = $_SESSION['id'];
    $agentId = $agent->getAgentIdByUserId($userId);
    
    if (!$isAdmin) {
        // Vérifier si l'utilisateur est membre d'un jury gérant cette promotion
        $hasAccess = false;
        $juryBureaux = $deliberation->getJuryBureauxByAgent($agentId);
        
        foreach ($juryBureaux as $jury) {
            if ($jury['idbureau'] == $bureauId) {
                $hasAccess = true;
                break;
            }
        }
        
        if (!$hasAccess) {
            throw new Exception("Vous n'avez pas accès à ce bureau de jury.");
        }
    }
    
    // Log des résultats pour debug
    error_log("=== RÉSULTATS IMPORT ===");
    error_log("Format: " . ($isGrilleModifiable ? "Grille modifiable" : "Ancien format"));
    error_log("Étudiants traités: " . $etudiantsTraites);
    error_log("Étudiants avec modifications: " . $etudiantsAvecModifications);
    error_log("Total modifications: " . count($notesModifiees));
    error_log("Succès: " . $successCount);
    error_log("Erreurs: " . $errorCount);
    
    // Construire le message de succès avec détails
    $message = "Importation terminée avec succès.<br><br>";
    $message .= "<strong>Détails:</strong><br>";
    $message .= "- Format: " . ($isGrilleModifiable ? "Grille modifiable" : "Grille complète") . "<br>";
    $message .= "- Étudiants traités: " . $etudiantsTraites . "<br>";
    $message .= "- Étudiants avec notes modifiées: " . $etudiantsAvecModifications . "<br>";
    $message .= "- Modifications historisées: " . count($notesModifiees) . "<br>";
    $message .= "- Erreurs: " . $errorCount . "<br>";
    
    // Si des notes ont été modifiées, ajouter un résumé
    if (!empty($notesModifiees)) {
        $message .= "<br><strong>Résumé des modifications:</strong><br>";
        $message .= "<small>";
        
        // Limiter à 10 modifications pour ne pas surcharger le message
        $notesToShow = array_slice($notesModifiees, 0, 10);
        foreach ($notesToShow as $note) {
            $ecueInfo = $ecue->getEcueById($note['ecue_id']);
            $ecueNom = $ecueInfo ? $ecueInfo['designationECUE'] : "ECUE #" . $note['ecue_id'];
            
            $message .= "• Matricule " . $note['matricule'] . " - " . $ecueNom . ": ";
            
            if ($isGrilleModifiable) {
                // Pour la grille modifiable, on n'a que la moyenne finale
                $message .= "MF: " . ($note['mf_avant'] ?? '-') . " → " . ($note['mf_apres'] ?? '-');
            } else {
                // Pour l'ancien format, on a CC, EX et MF
                $message .= "CC: " . ($note['cc_avant'] ?? '-') . " → " . ($note['cc_apres'] ?? '-') . ", ";
                $message .= "EX: " . ($note['ex_avant'] ?? '-') . " → " . ($note['ex_apres'] ?? '-') . ", ";
                $message .= "MF: " . ($note['mf_avant'] ?? '-') . " → " . ($note['mf_apres'] ?? '-');
            }
            $message .= "<br>";
        }
        
        // Si plus de 10 modifications, indiquer qu'il y en a d'autres
        if (count($notesModifiees) > 10) {
            $message .= "... et " . (count($notesModifiees) - 10) . " autres modifications.<br>";
        }
        
        $message .= "</small>";
    } else {
        $message .= "<br><strong>Aucune modification détectée.</strong><br>";
        $message .= "<small>Vérifiez que vous avez bien modifié les notes dans les cellules déverrouillées.</small>";
    }
    
    // Afficher le message de succès
    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Succès',
            html: '" . addslashes($message) . "',
            allowOutsideClick: false
        }).then(() => {
            window.location.href = '../index?view=deliberation/grille_notes&bureau=" . $bureauId . "&promotion=" . $promotionId . 
                        ($semestreId ? "&semestre=" . $semestreId : "") . 
            ($afficherDeuxSemestres ? "&deux_semestres=1" : "") . 
            "&session=" . $sessionId . "&annee=" . $anneeId . "';
        });
    </script>";
    
} catch (Exception $e) {
    error_log("Erreur import: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Erreur: " . addslashes($e->getMessage()) . "'
        }).then(() => {
            window.history.back();
        });
    </script>";
}
?>

