<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Deliberation.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Récupérer le crédit horaire depuis la configuration
$db = Connexion::getInstance()->getPDO();
$configQuery = $db->query("SELECT credit_heure FROM configuration_universite LIMIT 1");
$config = $configQuery->fetch(PDO::FETCH_ASSOC);
$heureCredit = $config && isset($config['credit_heure']) ? $config['credit_heure'] : 25;

use PhpOffice\PhpSpreadsheet\IOFactory;

// Vérification d'authentification
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
    $universite = new Universite();
    $ecue = new Ecue();
    $deliberation = new Deliberation();

    // Charger le fichier Excel
    $inputFileName = $_FILES['excelFile']['tmp_name'];
    $spreadsheet = IOFactory::load($inputFileName);
    $worksheet = $spreadsheet->getActiveSheet();

    // Récupérer les métadonnées depuis les propriétés personnalisées
    $properties = $spreadsheet->getProperties();
    $bureauId = $properties->getCustomPropertyValue('BureauId');
    $promotionId = $properties->getCustomPropertyValue('PromotionId');
    $sessionId = $properties->getCustomPropertyValue('SessionId');
    $anneeId = $properties->getCustomPropertyValue('AnneeId');
    $semestreId = $properties->getCustomPropertyValue('SemestreId');
    $deuxSemestres = $properties->getCustomPropertyValue('DeuxSemestres') == '1';
    $fileToken = $properties->getCustomPropertyValue('FileToken');

    // Si les métadonnées ne sont pas dans les propriétés, les récupérer depuis la feuille Metadata
    if (!$bureauId || !$promotionId || !$sessionId || !$anneeId) {
        $metadataSheet = null;
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            if ($sheet->getTitle() == 'Metadata') {
                $metadataSheet = $sheet;
                break;
            }
        }

        if ($metadataSheet) {
            for ($row = 2; $row <= $metadataSheet->getHighestRow(); $row++) {
                $type = $metadataSheet->getCell('A' . $row)->getValue();
                $value = $metadataSheet->getCell('B' . $row)->getValue();

                if ($type == 'Session') $sessionId = intval($value);
                if ($type == 'Annee') $anneeId = intval($value);
                if ($type == 'Bureau') $bureauId = intval($value);
                if ($type == 'Promotion') $promotionId = intval($value);
            }
        }
    }

    // Vérifier que toutes les métadonnées sont présentes
    if (!$bureauId || !$promotionId || !$sessionId || !$anneeId) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le fichier importé n\'est pas valide ou a été altéré. Veuillez utiliser un fichier exporté par le système.'
            }).then(() => {
                window.history.back();
            });
        </script>";
        exit;
    }

    // Validation des droits d'accès
    $userId = $_SESSION['id'];
    $isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;

    // Vérifier si c'est une deuxième session
    $sessionInfo = $universite->getSessionById($sessionId);
    $isDeuxiemeSession = $sessionInfo && stripos($sessionInfo['designSession'], 'deuxième') !== false;

    // Récupérer les informations sur les semestres
    $semestres = $deliberation->getSemestresByPromotion($promotionId);
    $semestresToShow = $deuxSemestres ? $semestres : array_values(array_filter($semestres, function ($sem) use ($semestreId) {
        return $sem['idsemestre'] == $semestreId;
    }));

    // Récupérer la configuration de délibération
    $configDeliberation = null;
    $calculerAvecNotesVides = false;
    if ($bureauId > 0) {
        $configDeliberation = $universite->getDeliberationConfig($bureauId, $sessionId, $anneeId);
        $calculerAvecNotesVides = isset($configDeliberation['calculer_moyenne_avec_notes_vides']) ? 
            (bool)$configDeliberation['calculer_moyenne_avec_notes_vides'] : false;
    }

        // CORRECTION : Lignes correspondant exactement à l'export
    $headerRow1 = 9;  // Semestre (#, Matricule, Nom)
    $headerRow2 = 10; // UE
    $headerRow3 = 11; // Type (Moy UE, etc.)
    $headerRow4 = 12; // Crédits
    $headerRow5 = 13; // IDs
    $startRow = 14;   // Début des données étudiants

    // Structure pour identifier les colonnes des moyennes UE
    $ueColumns = [];
    $ecuesByUE = [];

    // Debug : Afficher quelques valeurs pour vérifier
    error_log("Debug import - HeaderRow3: " . $headerRow3);
    error_log("Debug import - HeaderRow5: " . $headerRow5);
    error_log("Debug import - StartRow: " . $startRow);

    // Parcourir les en-têtes pour identifier les colonnes des moyennes UE
    $highestColumn = $worksheet->getHighestColumn();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

    for ($colIndex = 4; $colIndex <= $highestColumnIndex; $colIndex++) { // Commencer à D (colonne 4)
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);

        // Récupérer le type de colonne (ligne 11)
        $headerValue = $worksheet->getCell($colLetter . $headerRow3)->getValue();
        
        // Récupérer l'ID (ligne 13)
        $id = $worksheet->getCell($colLetter . $headerRow5)->getValue();

        // Debug pour les premières colonnes
        if ($colIndex <= 10) {
            error_log("Debug col $colLetter: headerValue='$headerValue', id='$id'");
        }

        // Si c'est une colonne de moyenne UE avec un ID valide
        if ($headerValue == 'Moy UE' && !empty($id) && is_numeric($id)) {
            $ueId = intval($id);
            $ueColumns[$ueId] = $colLetter;

            // Récupérer les ECUE pour cette UE
            $ecueList = $ecue->getECUEsByUE2($ueId);
            $ecuesByUE[$ueId] = $ecueList;
            
            error_log("Debug UE détectée: UE $ueId en colonne $colLetter");
        }
    }

    error_log("Debug total UE détectées: " . count($ueColumns));

    // Récupérer les étudiants
    if ($isDeuxiemeSession) {
        $etudiants = $deliberation->getEtudiantsEligiblesDeuxiemeSession($promotionId, $anneeId, $semestresToShow);
    } else {
        $etudiants = $deliberation->getEtudiantsByPromotion($promotionId, $anneeId);
    }

    error_log("Debug étudiants disponibles: " . count($etudiants));

    // Initialiser les compteurs
    $totalRows = 0;
    $modifiedRows = 0;
    $errorRows = 0;
    $historiquesCount = 0;
    $debugInfo = []; // Pour le débogage
    $motif = "Importation de grille simplifiée - Répartition proportionnelle";

    // Debug : vérifier les premières cellules des matricules
    for ($testRow = $startRow; $testRow <= $startRow + 5; $testRow++) {
        $matriculeTest = $worksheet->getCell('B' . $testRow)->getValue();
        error_log("Debug ligne $testRow, matricule: '$matriculeTest'");
        if (empty($matriculeTest)) break;
    }

    // Pour chaque étudiant, lire et mettre à jour les notes
    $currentRow = $startRow;
    while (!empty($worksheet->getCell('B' . $currentRow)->getValue())) {
        $matricule = $worksheet->getCell('B' . $currentRow)->getValue();

        error_log("Debug traitement matricule: '$matricule' à la ligne $currentRow");

        // Vérifier que le matricule existe dans notre base
        $etudiantExiste = false;
        foreach ($etudiants as $etudiant) {
            if ($etudiant['matricule'] == $matricule) {
                $etudiantExiste = true;
                break;
            }
        }

        if (!$etudiantExiste) {
            error_log("Debug matricule non trouvé: $matricule");
            $errorRows++;
            $currentRow++;
            continue;
        }

        $totalRows++;
        $rowModified = false;

        // Pour chaque UE, vérifier si la moyenne a été modifiée
        foreach ($ueColumns as $ueId => $ueColumn) {
            // En deuxième session, vérifier si l'UE a déjà été validée en première session
            if ($isDeuxiemeSession) {
                $moyenneUEPremiereSession = $deliberation->getMoyenneUEPremiereSession($matricule, $ueId, $anneeId);
                $ueValideeEnPremiereSession = ($moyenneUEPremiereSession !== null && $moyenneUEPremiereSession >= 10);

                // Si l'UE est déjà validée en première session, ne pas la modifier
                if ($ueValideeEnPremiereSession) {
                    continue;
                }
            }

            // Récupérer la moyenne de l'UE depuis le fichier Excel
            $cellValue = $worksheet->getCell($ueColumn . $currentRow)->getValue();
            
            // Traiter la valeur : elle peut être une formule ou une valeur directe
            $moyenneUEFromFile = null;
            if ($cellValue !== null && $cellValue !== '') {
                // Si c'est une formule, la calculer
                if (is_string($cellValue) && strpos($cellValue, '=') === 0) {
                    try {
                        $moyenneUEFromFile = $worksheet->getCell($ueColumn . $currentRow)->getCalculatedValue();
                    } catch (Exception $e) {
                        // En cas d'erreur de calcul, utiliser la valeur brute
                        $moyenneUEFromFile = floatval(str_replace('=', '', $cellValue));
                    }
                } else {
                    $moyenneUEFromFile = $cellValue;
                }
                
                // Convertir en nombre et valider
                if (is_numeric($moyenneUEFromFile)) {
                    $moyenneUEFromFile = floatval($moyenneUEFromFile);
                } else {
                    continue; // Ignorer si ce n'est pas un nombre valide
                }
            } else {
                continue; // Ignorer les cellules vides
            }

            // Limiter à une plage valide (0-20)
            if ($moyenneUEFromFile < 0) $moyenneUEFromFile = 0;
            if ($moyenneUEFromFile > 20) $moyenneUEFromFile = 20;

            // Calculer la moyenne actuelle de l'UE dans la base de données
            $moyenneUEActuelle = calculerMoyenneUEActuelle(
                $ueId, 
                $matricule, 
                $sessionId, 
                $anneeId, 
                $isDeuxiemeSession, 
                $calculerAvecNotesVides,
                $ecuesByUE,
                $universite,
                $deliberation,
                $heureCredit
            );

            // Information de débogage
            $debugInfo[] = [
                'matricule' => $matricule,
                'ueId' => $ueId,
                'moyenneFromFile' => $moyenneUEFromFile,
                'moyenneActuelle' => $moyenneUEActuelle,
                'difference' => abs($moyenneUEFromFile - $moyenneUEActuelle)
            ];

            // Comparer les moyennes (avec une marge d'erreur réduite pour les arrondis)
            if (abs($moyenneUEFromFile - $moyenneUEActuelle) > 0.005) {
                error_log("Debug modification détectée pour $matricule UE $ueId: $moyenneUEFromFile vs $moyenneUEActuelle");
                
                // La moyenne a été modifiée, répartir proportionnellement aux ECUE
                $facteurAjustement = $moyenneUEActuelle > 0 ? $moyenneUEFromFile / $moyenneUEActuelle : 1;

                // Récupérer les données actuelles des ECUE
                $ecueActuels = [];
                $totalCredits = 0;

                foreach ($ecuesByUE[$ueId] as $ecueItem) {
                    $ecueId = $ecueItem['idECUE'];
                    $credits = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / $heureCredit;
                    $totalCredits += $credits;

                    // Récupérer les notes actuelles
                    $cote = $universite->getCoteGrille($ecueId, $sessionId, $anneeId, $matricule);

                    $ecueActuels[$ecueId] = [
                        'credits' => $credits,
                        'cote' => $cote,
                        'mf' => ($cote && isset($cote['MF']) && $cote['MF'] !== null) ? floatval($cote['MF']) : 0
                    ];
                }

                foreach ($ecueActuels as $ecueId => $ecueData) {
                    $anciennesCotes = $ecueData['cote'];

                                        // Cas où l'ECUE n'a pas de note (NULL)
                    if (!$anciennesCotes || $ecueData['mf'] === 0) {
                        // Attribuer une note proportionnelle à la moyenne de l'UE
                        $nouvelleMF = $moyenneUEFromFile;

                        // Récupérer la configuration des pondérations
                        $config = $ecue->getConfigurationMoyenne($ecueId, $anneeId, $sessionId);
                        // Récupérer les pondérations depuis la configuration par défaut si pas de config spécifique
require_once '../models/Universite.php';
$universite = new Universite();
$ponderationsDefaut = $universite->getPonderationsDefaut();
$ponderationCC = $config ? $config['ponderation_cc'] : $ponderationsDefaut['ponderation_cc'];
$ponderationExamen = $config ? $config['ponderation_ex'] : $ponderationsDefaut['ponderation_ex'];

                        $cc = null;
                        $ex = null;

                        // En deuxième session, essayer de récupérer la note de CC de première session
                        if ($isDeuxiemeSession) {
                            $cotePremiereSession = $deliberation->getNotesEtudiantECUEPremiereSession($matricule, $ecueId, $anneeId);
                            if ($cotePremiereSession && isset($cotePremiereSession['CC']) && $cotePremiereSession['CC'] !== null) {
                                $cc = $cotePremiereSession['CC'];
                                
                                // Calculer l'examen nécessaire pour atteindre la moyenne souhaitée
                                $ex = ($nouvelleMF - ($cc * $ponderationCC)) / $ponderationExamen;
                                $ex = min(20, max(0, $ex)); // Limiter entre 0 et 20
                            } else {
                                // Si pas de CC de première session, attribuer la même note pour CC et EX
                                $cc = $nouvelleMF;
                                $ex = $nouvelleMF;
                            }
                        } else {
                            // En première session, attribuer des valeurs équivalentes
                            $cc = $nouvelleMF;
                            $ex = $nouvelleMF;
                        }

                        // Recalculer la MF avec les pondérations exactes
                        $mf = ($cc * $ponderationCC) + ($ex * $ponderationExamen);
                    } else {
                        // ECUE qui a déjà des notes - ajuster proportionnellement
                        $nouvelleMF = min(20, max(0, $ecueData['mf'] * $facteurAjustement));

                        // Récupérer les notes existantes
                        $cc = $anciennesCotes ? $anciennesCotes['CC'] : null;
                        $ex = $anciennesCotes ? $anciennesCotes['EX'] : null;

                        // Récupérer la configuration des pondérations
                        $config = $ecue->getConfigurationMoyenne($ecueId, $anneeId, $sessionId);
                        // Récupérer les pondérations depuis la configuration par défaut si pas de config spécifique
                        require_once '../models/Universite.php';
                        $universite = new Universite();
                        $ponderationsDefaut = $universite->getPonderationsDefaut();
                        $ponderationCC = $config ? $config['ponderation_cc'] : $ponderationsDefaut['ponderation_cc'];
                        $ponderationExamen = $config ? $config['ponderation_ex'] : $ponderationsDefaut['ponderation_ex'];

                        // En deuxième session, ajuster principalement la note d'examen
                        if ($isDeuxiemeSession) {
                            if ($cc !== null) {
                                $ex = ($nouvelleMF - ($cc * $ponderationCC)) / $ponderationExamen;
                                $ex = min(20, max(0, $ex));
                            } else {
                                $ex = $nouvelleMF;
                            }
                        } else {
                            // En première session, ajuster l'examen en priorité
                            if ($cc !== null) {
                                $ex = ($nouvelleMF - ($cc * $ponderationCC)) / $ponderationExamen;
                                $ex = min(20, max(0, $ex));
                            } else {
                                $ex = $nouvelleMF;
                            }
                        }

                        // Recalculer la MF finale
                        if ($cc !== null && $ex !== null) {
                            $mf = ($cc * $ponderationCC) + ($ex * $ponderationExamen);
                        } elseif ($ex !== null) {
                            $mf = $ex;
                        } elseif ($cc !== null) {
                            $mf = $cc;
                        } else {
                            $mf = null;
                        }
                    }

                    // Enregistrer les nouvelles notes
                    $result = $universite->saveCoteGrille(
                        $ecueId,
                        $sessionId,
                        $anneeId,
                        $matricule,
                        $cc,
                        $ex,
                        $mf,
                        $userId
                    );

                    if ($result) {
                        $rowModified = true;

                        // Enregistrer l'historique
                        if ($anciennesCotes) {
                            $cc_avant = $anciennesCotes['CC'];
                            $ex_avant = $anciennesCotes['EX'];
                            $mf_avant = $anciennesCotes['MF'];
                        } else {
                            $cc_avant = null;
                            $ex_avant = null;
                            $mf_avant = null;
                        }

                        $historiqueMotif = $motif . 
                            ($isDeuxiemeSession ? " (Deuxième session)" : "") .
                            (!$anciennesCotes ? " (Première attribution)" : "") .
                            " - UE: " . $ueId . " - Nouvelle moyenne: " . number_format($moyenneUEFromFile, 2) .
                            " (était: " . number_format($moyenneUEActuelle, 2) . ")";

                        $historique = $universite->saveHistoriqueCotes(
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
                            $historiqueMotif,
                            $userId
                        );

                        if ($historique) {
                            $historiquesCount++;
                        }
                    }
                }

                // COMPENSATION INTRA-UE: Si la moyenne de l'UE >= 10, redistribuer les points
                // pour que tous les ECUEs passent, tout en conservant la moyenne UE exacte
                if ($moyenneUEFromFile >= 10) {
                    // Étape 1: Collecter les données de tous les ECUEs
                    $ecueData = [];
                    $totalCreditsUE = 0;
                    $ecuesEnEchec = [];
                    $ecuesEnReussite = [];
                    
                    foreach ($ecuesByUE[$ueId] as $ecueItem) {
                        $ecueIdItem = $ecueItem['idECUE'];
                        $credits = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / $heureCredit;
                        $totalCreditsUE += $credits;
                        
                        $coteActuelle = $universite->getCoteGrille($ecueIdItem, $sessionId, $anneeId, $matricule);
                        $mfActuel = ($coteActuelle && isset($coteActuelle['MF']) && $coteActuelle['MF'] !== null) 
                            ? floatval($coteActuelle['MF']) : null;
                        
                        $ecueData[$ecueIdItem] = [
                            'credits' => $credits,
                            'mf' => $mfActuel,
                            'cc' => $coteActuelle ? $coteActuelle['CC'] : null,
                            'ex' => $coteActuelle ? $coteActuelle['EX'] : null
                        ];
                        
                        if ($mfActuel !== null) {
                            if ($mfActuel < 10) {
                                $ecuesEnEchec[$ecueIdItem] = $ecueData[$ecueIdItem];
                            } else {
                                $ecuesEnReussite[$ecueIdItem] = $ecueData[$ecueIdItem];
                            }
                        }
                    }
                    
                    // Étape 2: Si des ECUEs sont en échec, redistribuer les points
                    if (!empty($ecuesEnEchec)) {
                        // Points totaux à conserver: moyenneUE * totalCredits
                        $pointsTotaux = $moyenneUEFromFile * $totalCreditsUE;
                        
                        // Points nécessaires pour remonter les échecs à 10
                        $pointsNecessairesPourEchecs = 0;
                        foreach ($ecuesEnEchec as $ecueIdEchec => $dataEchec) {
                            $pointsActuels = $dataEchec['mf'] * $dataEchec['credits'];
                            $pointsA10 = 10 * $dataEchec['credits'];
                            $pointsNecessairesPourEchecs += ($pointsA10 - $pointsActuels);
                        }
                        
                        // Points disponibles dans les ECUEs en réussite (excédent au-dessus de 10)
                        $pointsDisponibles = 0;
                        foreach ($ecuesEnReussite as $ecueIdReussite => $dataReussite) {
                            $excedent = ($dataReussite['mf'] - 10) * $dataReussite['credits'];
                            $pointsDisponibles += $excedent;
                        }
                        
                        // Vérifier si la redistribution est possible
                        if ($pointsDisponibles >= $pointsNecessairesPourEchecs) {
                            // Calculer le facteur de réduction pour les ECUEs en réussite
                            $facteurReduction = $pointsDisponibles > 0 
                                ? ($pointsDisponibles - $pointsNecessairesPourEchecs) / $pointsDisponibles 
                                : 1;
                            
                            // Appliquer les nouvelles notes aux ECUEs en échec (les remonter à 10)
                            foreach ($ecuesEnEchec as $ecueIdEchec => $dataEchec) {
                                $nouveauMF = 10;
                                $anciensCC = $dataEchec['cc'];
                                $anciensEX = $dataEchec['ex'];
                                $anciensMF = $dataEchec['mf'];
                                
                                // Calculer la nouvelle note d'examen
                                $configEcue = $ecue->getConfigurationMoyenne($ecueIdEchec, $anneeId, $sessionId);
                                $ponderationsDefautCompens = $universite->getPonderationsDefaut();
                                $pondCC = $configEcue ? $configEcue['ponderation_cc'] : $ponderationsDefautCompens['ponderation_cc'];
                                $pondEX = $configEcue ? $configEcue['ponderation_ex'] : $ponderationsDefautCompens['ponderation_ex'];
                                
                                if ($anciensCC !== null && $pondEX > 0) {
                                    $nouveauEX = ($nouveauMF - ($anciensCC * $pondCC)) / $pondEX;
                                    $nouveauEX = min(20, max(0, $nouveauEX));
                                    $nouveauCC = $anciensCC;
                                } else {
                                    $nouveauEX = $nouveauMF;
                                    $nouveauCC = $anciensCC;
                                }
                                
                                // Sauvegarder
                                $resultCompens = $universite->saveCoteGrille(
                                    $ecueIdEchec, $sessionId, $anneeId, $matricule,
                                    $nouveauCC, $nouveauEX, $nouveauMF, $userId
                                );
                                
                                if ($resultCompens) {
                                    $universite->saveHistoriqueCotes(
                                        $ecueIdEchec, $sessionId, $anneeId, $matricule,
                                        $anciensCC, $anciensEX, $anciensMF,
                                        $nouveauCC, $nouveauEX, $nouveauMF,
                                        "Compensation intra-UE - ECUE remonté de " . number_format($anciensMF, 2) . " à 10.00",
                                        $userId
                                    );
                                    $historiquesCount++;
                                }
                            }
                            
                            // Réduire proportionnellement les ECUEs en réussite
                            foreach ($ecuesEnReussite as $ecueIdReussite => $dataReussite) {
                                $excedent = $dataReussite['mf'] - 10;
                                $nouveauExcedent = $excedent * $facteurReduction;
                                $nouveauMF = 10 + $nouveauExcedent;
                                $nouveauMF = max(10, min(20, $nouveauMF)); // Garder entre 10 et 20
                                
                                // Ne modifier que si la note change significativement
                                if (abs($nouveauMF - $dataReussite['mf']) > 0.005) {
                                    $anciensCC = $dataReussite['cc'];
                                    $anciensEX = $dataReussite['ex'];
                                    $anciensMF = $dataReussite['mf'];
                                    
                                    $configEcue = $ecue->getConfigurationMoyenne($ecueIdReussite, $anneeId, $sessionId);
                                    $ponderationsDefautCompens = $universite->getPonderationsDefaut();
                                    $pondCC = $configEcue ? $configEcue['ponderation_cc'] : $ponderationsDefautCompens['ponderation_cc'];
                                    $pondEX = $configEcue ? $configEcue['ponderation_ex'] : $ponderationsDefautCompens['ponderation_ex'];
                                    
                                    if ($anciensCC !== null && $pondEX > 0) {
                                        $nouveauEX = ($nouveauMF - ($anciensCC * $pondCC)) / $pondEX;
                                        $nouveauEX = min(20, max(0, $nouveauEX));
                                        $nouveauCC = $anciensCC;
                                    } else {
                                        $nouveauEX = $nouveauMF;
                                        $nouveauCC = $anciensCC;
                                    }
                                    
                                    $resultCompens = $universite->saveCoteGrille(
                                        $ecueIdReussite, $sessionId, $anneeId, $matricule,
                                        $nouveauCC, $nouveauEX, $nouveauMF, $userId
                                    );
                                    
                                    if ($resultCompens) {
                                        $universite->saveHistoriqueCotes(
                                            $ecueIdReussite, $sessionId, $anneeId, $matricule,
                                            $anciensCC, $anciensEX, $anciensMF,
                                            $nouveauCC, $nouveauEX, $nouveauMF,
                                            "Compensation intra-UE - Ajustement de " . number_format($anciensMF, 2) . " à " . number_format($nouveauMF, 2),
                                            $userId
                                        );
                                        $historiquesCount++;
                                    }
                                }
                            }
                            
                            error_log("Compensation UE $ueId pour $matricule: " . count($ecuesEnEchec) . " ECUE(s) remonté(s), moyenne UE conservée à $moyenneUEFromFile");
                        }
                    }
                }
            }
        }

        if ($rowModified) {
            $modifiedRows++;
        }

        $currentRow++;
    }

    // Préparer les informations de débogage pour l'affichage
    $debugInfoText = "";
    if (count($debugInfo) > 0) {
        $debugInfoText = "<br><br><strong>Informations de débogage (premiers 10 étudiants):</strong><br>";
        for ($i = 0; $i < min(10, count($debugInfo)); $i++) {
            $debug = $debugInfo[$i];
            $debugInfoText .= "- " . htmlspecialchars($debug['matricule']) . " (UE " . $debug['ueId'] . "): " .
                             "Fichier=" . number_format($debug['moyenneFromFile'], 3) . 
                             ", BD=" . number_format($debug['moyenneActuelle'], 3) . 
                             ", Diff=" . number_format($debug['difference'], 4) . "<br>";
        }
    }

    // Message de succès avec détails
    $messageDetails = "Importation terminée avec succès.<br><br>" .
        "<strong>Détails:</strong><br>" .
        "- Étudiants traités: " . $totalRows . "<br>" .
        "- Étudiants avec notes modifiées: " . $modifiedRows . "<br>" .
        "- Modifications historisées: " . $historiquesCount . "<br>" .
        "- Erreurs: " . $errorRows . "<br>" .
        "- UE détectées: " . count($ueColumns);

    if ($errorRows > 0) {
        $messageDetails .= "<br><br><strong>Note:</strong> " . $errorRows . " ligne(s) ignorée(s) (matricules non trouvés).";
    }

    // Ajouter les informations de debug seulement si en mode développement
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $messageDetails .= $debugInfoText;
    }

    // Rediriger vers la grille de notes avec un message de succès
    $redirectUrl = '../?view=deliberation/grille_notes&bureau=' . $bureauId . 
                   '&promotion=' . $promotionId . 
                   '&session=' . $sessionId . 
                   '&annee=' . $anneeId;
    
    if ($deuxSemestres) {
        $redirectUrl .= '&deux_semestres=1';
    } else {
        $redirectUrl .= '&semestre=' . $semestreId;
    }

    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Import réussi',
            html: '" . addslashes($messageDetails) . "',
            allowOutsideClick: false,
            confirmButtonText: 'Continuer',
            width: '700px'
        }).then(() => {
            window.location.href = '" . $redirectUrl . "';
        });
    </script>";

} catch (Exception $e) {
    // Gestion des erreurs
    $errorMessage = "Erreur lors de l'importation: " . $e->getMessage();
    
    // Log l'erreur pour le débogage
    error_log("Erreur importation grille modifiable: " . $e->getMessage() . " - Fichier: " . $e->getFile() . " - Ligne: " . $e->getLine());
    
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur d\\'importation',
            html: '" . addslashes($errorMessage) . "<br><br>Veuillez vérifier que le fichier est valide et n\\'a pas été modifié manuellement.<br><br>Consultez les logs pour plus d\\'informations.',
            confirmButtonText: 'Retour'
        }).then(() => {
            window.history.back();
        });
    </script>";
}

// Fonction helper pour calculer la moyenne UE actuelle
function calculerMoyenneUEActuelle($ueId, $matricule, $sessionId, $anneeId, $isDeuxiemeSession, $calculerAvecNotesVides, $ecuesByUE, $universite, $deliberation, $heureCredit) {
    // En deuxième session, vérifier si l'UE a été validée en première session
    if ($isDeuxiemeSession) {
        $moyenneUEPremiereSession = $deliberation->getMoyenneUEPremiereSession($matricule, $ueId, $anneeId);
        if ($moyenneUEPremiereSession !== null && $moyenneUEPremiereSession >= 10) {
            return $moyenneUEPremiereSession;
        }
    }

    // Calculer la moyenne de l'UE
    $totalPoints = 0;
    $totalCredits = 0;
    $hasValidNotes = false;
    $hasAllNotes = true;
    
    if (!isset($ecuesByUE[$ueId]) || empty($ecuesByUE[$ueId])) {
        error_log("Debug: Aucun ECUE trouvé pour UE $ueId");
        return 0;
    }
    
    foreach ($ecuesByUE[$ueId] as $ecueItem) {
        $ecueId = $ecueItem['idECUE'];
        $credits = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / $heureCredit;
        $totalCredits += $credits;
        
        $cote = $universite->getCoteGrille($ecueId, $sessionId, $anneeId, $matricule);
        if ($cote && isset($cote['MF']) && $cote['MF'] !== null) {
            $totalPoints += floatval($cote['MF']) * $credits;
            $hasValidNotes = true;
        } else {
            $hasAllNotes = false;
        }
    }
    
    // Calculer la moyenne uniquement si tous les ECUE ont des notes ou si calculerAvecNotesVides est activé
    if ($hasValidNotes && ($hasAllNotes || $calculerAvecNotesVides) && $totalCredits > 0) {
        return $totalPoints / $totalCredits;
    }
    
    return 0;
}
?>
