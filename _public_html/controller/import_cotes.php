<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // Pour PhpSpreadsheet

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

// Récupérer les paramètres
$ecueId = isset($_POST['ecueId']) ? intval($_POST['ecueId']) : 0;
$sessionId = isset($_POST['sessionId']) ? intval($_POST['sessionId']) : 0;
$anneeId = isset($_POST['anneeId']) ? intval($_POST['anneeId']) : 0;
$promotionId = isset($_POST['promotionId']) ? intval($_POST['promotionId']) : 0;
$bureauId = isset($_POST['bureauId']) ? intval($_POST['bureauId']) : 0;

if ($ecueId <= 0 || $sessionId <= 0 || $anneeId <= 0 || $promotionId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Paramètres invalides.'
        }).then(() => {
            window.history.back();
        });
    </script>";
    exit;
}

/**
 * Compare deux valeurs flottantes avec une tolérance pour les erreurs d'arrondi
 */
function isFloatEqual($a, $b, $epsilon = 0.00001) {
    if ($a === null && $b === null) {
        return true;
    }
    if ($a === null || $b === null) {
        return false;
    }
    return abs($a - $b) < $epsilon;
}

try {
    $ecue = new Ecue();
    $universite = new Universite();
    $agent = new Agent();
    
    // Récupérer les détails de l'ECUE
    $ecueDetails = $ecue->getEcueById($ecueId);
    if (!$ecueDetails) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ECUE introuvable.'
            }).then(() => {
                window.history.back();
            });
        </script>";
        exit;
    }
    
    // Vérifier si l'utilisateur est autorisé
    $isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
    $userId = $_SESSION['id'];
    $agentId = $agent->getAgentIdByUserId($userId);

    if (!$isAdmin) {
        // Vérifier si l'utilisateur est membre d'un jury gérant cette promotion
        $hasAccess = $universite->canAgentAccessPromotion($agentId, $promotionId);
        
        if (!$hasAccess) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Accès refusé',
                    text: 'Vous n\'avez pas accès à cette promotion'
                }).then(() => {
                    window.history.back();
                });
            </script>";
            exit;
        }
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
    
    // Charger le fichier Excel
    $inputFileName = $_FILES['excelFile']['tmp_name'];
    $spreadsheet = IOFactory::load($inputFileName);
    $worksheet = $spreadsheet->getActiveSheet();
    
    // Récupérer les propriétés du fichier pour vérifier l'authenticité
    $properties = $spreadsheet->getProperties();
    $fileToken = $properties->getCustomPropertyValue('FileToken') ?? '';
    $fileEcueId = $properties->getCustomPropertyValue('EcueId') ?? 0;
    $fileSessionId = $properties->getCustomPropertyValue('SessionId') ?? 0;
    $fileAnneeId = $properties->getCustomPropertyValue('AnneeId') ?? 0;
    
    // Vérifier si le fichier correspond aux paramètres de la requête
    if ($fileEcueId != $ecueId || $fileSessionId != $sessionId || $fileAnneeId != $anneeId) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le fichier ne correspond pas aux paramètres de l\'ECUE et de la session sélectionnés.'
            }).then(() => {
                window.history.back();
            });
        </script>";
        exit;
    }
    
    // Si le bureauId n'est pas fourni, essayer de le récupérer
    if ($bureauId <= 0) {
        $jurysPromotion = $universite->getJurysByPromotion($promotionId);
        if (!empty($jurysPromotion)) {
            $bureauId = $jurysPromotion[0]['idbureau'];
        }
    }
    
    // Récupérer la configuration de délibération
    $configDeliberation = null;
    $calculerAvecNotesVides = false;
    
    if ($bureauId > 0) {
        $configDeliberation = $universite->getDeliberationConfig($bureauId, $sessionId, $anneeId);
        $calculerAvecNotesVides = isset($configDeliberation['calculer_moyenne_avec_notes_vides']) ? 
            (bool)$configDeliberation['calculer_moyenne_avec_notes_vides'] : false;
    }
    
    // Récupérer la configuration des pondérations
    $configMoyenne = $ecue->getConfigurationMoyenne($ecueId, $anneeId, $sessionId);
    $ponderationCC = $configMoyenne ? $configMoyenne['ponderation_cc'] : 0.4;
    $ponderationExamen = $configMoyenne ? $configMoyenne['ponderation_ex'] : 0.6;
    
    // Vérifier si c'est une deuxième session
    $sessionInfo = $universite->getSessionById($sessionId);
    $isDeuxiemeSession = stripos($sessionInfo['designSession'], 'deuxième') !== false;
    
    // Parcourir les lignes du fichier Excel pour récupérer les notes
    $highestRow = $worksheet->getHighestRow();
    $notes = [];
    $successCount = 0;
    $errorCount = 0;
    $motif = "Importation Excel"; // Motif pour l'historique
    
    // Commencer à la ligne 16 (après les en-têtes et les instructions)
    for ($row = 16; $row <= $highestRow; $row++) {
        $matricule = $worksheet->getCell('B' . $row)->getValue();
        $ccValue = $worksheet->getCell('D' . $row)->getValue();
        $exValue = $worksheet->getCell('E' . $row)->getValue();
        
        // Ne traiter que les lignes avec des matricules
        if (empty($matricule)) {
            continue; // Ignorer les lignes vides
        }
        
        // Conversion des notes en nombres
        $cc = is_numeric($ccValue) ? floatval($ccValue) : null;
        $ex = is_numeric($exValue) ? floatval($exValue) : null;
        
        // Vérifier que les notes sont dans l'intervalle valide (0-20)
        $validCC = ($cc === null) || ($cc >= 0 && $cc <= 20);
        $validEX = ($ex === null) || ($ex >= 0 && $ex <= 20);
        
        if ($validCC && $validEX) {
            // Calculer la moyenne finale en tenant compte de la configuration
            $mf = null;
            
            if ($calculerAvecNotesVides) {
                // Si on est configuré pour calculer avec des notes vides
                if ($cc !== null && $ex !== null) {
                    $mf = ($cc * $ponderationCC) + ($ex * $ponderationExamen);
                } elseif ($ex !== null) {
                    // S'il n'y a que la note d'examen
                    $mf = $ex;
                } elseif ($cc !== null) {
                    // S'il n'y a que la note de CC
                    $mf = $cc;
                }
            } else {
                // Si on n'est pas configuré pour calculer avec des notes vides
                if ($isDeuxiemeSession) {
                    // En deuxième session, seule la note d'examen est nécessaire
                    if ($ex !== null) {
                        $mf = $ex;
                        $cc=$ex;
                    }
                } else {
                    // En première session, les deux notes sont nécessaires
                    if ($cc !== null && $ex !== null) {
                        $mf = ($cc * $ponderationCC) + ($ex * $ponderationExamen);
                    }
                }
            }
            
            $notes[] = [
                'matricule' => $matricule,
                'cc' => $cc,
                'ex' => $ex,
                'mf' => $mf
            ];
            $successCount++;
        } else {
            $errorCount++;
        }
    }
    
    // Enregistrer les notes et l'historique
    $savedCount = 0;
    $historiquesCount = 0;
    
    foreach ($notes as $noteData) {
        // Récupérer les anciennes valeurs avant modification
        $anciennesCotes = $universite->getCoteGrille($ecueId, $sessionId, $anneeId, $noteData['matricule']);
        
        $result = $universite->saveCoteGrille(
            $ecueId, 
            $sessionId, 
            $anneeId, 
            $noteData['matricule'], 
            $noteData['cc'], 
            $noteData['ex'], 
            $noteData['mf'],
            $userId
        );
        
        if ($result) {
            $savedCount++;
            
            // Si la sauvegarde a réussi, enregistrer l'historique si les valeurs ont changé
            if ($anciennesCotes) {
                $cc_avant = $anciennesCotes['CC'];
                $ex_avant = $anciennesCotes['EX'];
                $mf_avant = $anciennesCotes['MF'];
                
                // Vérifier si les valeurs ont changé en utilisant la fonction isFloatEqual
                if (!isFloatEqual($cc_avant, $noteData['cc']) ||
                    !isFloatEqual($ex_avant, $noteData['ex']) ||
                    !isFloatEqual($mf_avant, $noteData['mf'])) {
                    
                    // Enregistrer l'historique
                    $historique = $universite->saveHistoriqueCotes(
                        $ecueId, 
                        $sessionId, 
                        $anneeId, 
                        $noteData['matricule'], 
                        $cc_avant, 
                        $ex_avant, 
                        $mf_avant, 
                        $noteData['cc'], 
                        $noteData['ex'], 
                        $noteData['mf'], 
                        $motif, 
                        $userId
                    );
                    
                    if ($historique) {
                        $historiquesCount++;
                    }
                }
            }
        }
    }
    
    // Récupérer les paramètres supplémentaires
    $bureauId = isset($_POST['bureauId']) ? intval($_POST['bureauId']) : 0;
    $semestreId = isset($_POST['semestreId']) ? intval($_POST['semestreId']) : 0;
    
    echo "<script>
    Swal.fire({
        icon: 'success',
        title: 'Succès',
        html: 'Importation réussie. " . $savedCount . " notes ont été importées.<br><br>" .
              "<strong>Détails:</strong><br>" .
              "- Notes traitées: " . $successCount . "<br>" .
              "- Notes enregistrées: " . $savedCount . "<br>" .
              "- Modifications historisées: " . $historiquesCount . "<br>" .
              "- Erreurs: " . $errorCount . "',
        allowOutsideClick: false
    }).then(() => {
        window.location.href = '../?view=deliberation/encodage_points&bureau=" . $bureauId . "&promotion=" . $promotionId . "&semestre=" . $semestreId . "&session=" . $sessionId . "&annee=" . $anneeId . "';
    });
</script>";
} catch (Exception $e) {
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
