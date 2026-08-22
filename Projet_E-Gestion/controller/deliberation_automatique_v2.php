<?php
require_once dirname(__DIR__).'/config/Connexion.php';
require_once dirname(__DIR__).'/models/Universite.php';
require_once dirname(__DIR__).'/models/Deliberation.php';
require_once dirname(__DIR__).'/models/Ecue.php';

session_start();
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Session expirée']);
    exit();
}

class DeliberationAutomatiqueV2 {
    private $universite;
    private $deliberation;
    private $ecue;
    private $config;
    private $creditHeure;
    
    public function __construct() {
        $this->universite = new Universite();
        $this->deliberation = new Deliberation();
        $this->ecue = new Ecue();
        
        // Récupérer le crédit horaire depuis la configuration
        $db = Connexion::getInstance()->getPDO();
        $configQuery = $db->query("SELECT credit_heure FROM configuration_universite LIMIT 1");
        $configUniv = $configQuery->fetch(PDO::FETCH_ASSOC);
        $this->creditHeure = $configUniv && isset($configUniv['credit_heure']) ? $configUniv['credit_heure'] : 25;
    }
    
    public function evaluerPromotion($promotionId, $bureauId, $sessionId, $anneeId, $portee = 'semestre', $semestreId = null, $mode = 'automatique') {
        // Récupérer la configuration de délibération
        $this->config = $this->deliberation->getDeliberationConfig($bureauId, $sessionId, $anneeId);
        
        if (!$this->config) {
            return [
                'error' => 'Configuration de délibération non trouvée',
                'promotion_id' => $promotionId
            ];
        }
        
        $calculerAvecNotesVides = isset($this->config['calculer_moyenne_avec_notes_vides']) ?
            (bool)$this->config['calculer_moyenne_avec_notes_vides'] : false;
        
        // Récupérer l'information sur la session
        $sessionInfo = $this->universite->getSessionById($sessionId);
        $isDeuxiemeSession = $sessionInfo && stripos($sessionInfo['designSession'], 'deuxième') !== false;
        
        // Déterminer les semestres à traiter
        $semestresToShow = [];
        if ($portee === 'annee') {
            $semestresToShow = $this->deliberation->getSemestresByPromotion($promotionId);
        } else {
            // Un seul semestre
            $semestres = $this->deliberation->getSemestresByPromotion($promotionId);
            $semestresToShow = array_values(array_filter($semestres, function ($sem) use ($semestreId) {
                return $sem['idsemestre'] == $semestreId;
            }));
        }
        
        // Récupérer les étudiants de la promotion
        if ($isDeuxiemeSession) {
            $etudiants = $this->deliberation->getEtudiantsEligiblesDeuxiemeSession($promotionId, $anneeId, $semestresToShow);
        } else {
            $etudiants = $this->deliberation->getEtudiantsByPromotion($promotionId, $anneeId);
        }
        
        // Pour chaque semestre, récupérer les UE et ECUE
        $uesBySemestre = [];
        $ecuesByUE = [];
        foreach ($semestresToShow as $semestre) {
            $semId = $semestre['idsemestre'];
            $uesBySemestre[$semId] = $this->deliberation->getUEsBySemestre($semId);
            
            foreach ($uesBySemestre[$semId] as $ue) {
                $ueId = $ue['idUE'];
                $ecuesByUE[$ueId] = $this->ecue->getECUEsByUE2($ueId);
            }
        }
        
        $resultatsEtudiants = [];
        
        // Traiter chaque étudiant
        foreach ($etudiants as $etudiant) {
            $matricule = $etudiant['matricule'];
            $resultatsEtudiant = $this->evaluerEtudiant($matricule, $etudiant, $semestresToShow, $uesBySemestre, $ecuesByUE, $sessionId, $anneeId, $isDeuxiemeSession, $calculerAvecNotesVides, $portee);
            $resultatsEtudiants[] = $resultatsEtudiant;
        }
        
        return [
            'mode' => $mode,
            'portee' => $portee,
            'promotion_id' => $promotionId,
            'total_etudiants' => count($etudiants),
            'resultats' => $resultatsEtudiants,
            'statistiques' => $this->calculerStatistiques($resultatsEtudiants)
        ];
    }
    
    private function evaluerEtudiant($matricule, $etudiant, $semestresToShow, $uesBySemestre, $ecuesByUE, $sessionId, $anneeId, $isDeuxiemeSession, $calculerAvecNotesVides, $portee) {
        $moyennesUE = [];
        $validationsUE = [];
        $moyennesSemestre = [];
        $validationsSemestre = [];
        $moyennesAnnuelles = [];
        $validationsAnnuelles = [];
        
        // Pour chaque semestre à afficher
        foreach ($semestresToShow as $semestre) {
            $semId = $semestre['idsemestre'];
            $totalPointsSemestre = 0;
            $totalCreditsSemestre = 0;
            $creditsValidesSemestre = 0;
            $ueAvecMoyenne = 0;
            $totalUE = 0;
            
            // Pour chaque UE du semestre
            foreach ($uesBySemestre[$semId] as $ue) {
                $ueId = $ue['idUE'];
                $totalUE++;
                $totalPointsUE = 0;
                $totalCoeffUE = 0;
                $ecueCount = 0;
                $ecueWithCompleteNotesCount = 0;
                
                // Vérifier si l'UE a été validée en première session
                $ueValideeEnPremiereSession = false;
                $moyenneUEPremiereSession = null;
                if ($isDeuxiemeSession) {
                    $moyenneUEPremiereSession = $this->deliberation->getMoyenneUEPremiereSession($matricule, $ueId, $anneeId);
                    $ueValideeEnPremiereSession = ($moyenneUEPremiereSession !== null && $moyenneUEPremiereSession >= 10);
                }
                
                // Pour chaque ECUE de l'UE
                foreach ($ecuesByUE[$ueId] as $ecueItem) {
                    $ecueId = $ecueItem['idECUE'];
                    $ecueCount++;
                    
                    // Récupérer la note de l'étudiant pour cet ECUE (même logique que grille_notes.php)
                    if ($isDeuxiemeSession) {
                        $notePremiereSession = $this->deliberation->getNotesEtudiantECUEPremiereSession($matricule, $ecueId, $anneeId);
                        
                        if ($notePremiereSession && $notePremiereSession['MF'] !== null && $notePremiereSession['MF'] >= 10) {
                            $notes = $notePremiereSession;
                        } else {
                            if ($ueValideeEnPremiereSession) {
                                $notes = $notePremiereSession;
                            } else {
                                $notes = $this->deliberation->getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId);
                            }
                        }
                    } else {
                        $notes = $this->deliberation->getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId);
                    }
                    
                    if ($notes) {
                        // Vérifier si les notes sont complètes selon la configuration
                        $notesCompletes = false;
                        if ($isDeuxiemeSession) {
                            $notesCompletes = $notes['EX'] !== null;
                        } else {
                            $notesCompletes = $notes['CC'] !== null && $notes['EX'] !== null;
                        }
                        
                        if ($notesCompletes) {
                            $ecueWithCompleteNotesCount++;
                        }
                        
                        // Calculer le coefficient (crédits) de l'ECUE
                        $coeffECUE = round(($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / $this->creditHeure, 2);
                        $totalCoeffUE += $coeffECUE;
                        
                        if ($notes['MF'] !== null) {
                            $totalPointsUE += $notes['MF'] * $coeffECUE;
                        }
                    } else {
                        $coeffECUE = round(($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / $this->creditHeure, 2);
                        $totalCoeffUE += $coeffECUE;
                    }
                }
                
                // Calculer la moyenne de l'UE (même logique que grille_notes.php)
                if ($ecueCount > 0) {
                    if ($isDeuxiemeSession && $ueValideeEnPremiereSession) {
                        $moyenneUE = $moyenneUEPremiereSession;
                        $moyennesUE[$ueId] = $moyenneUE;
                        $validationsUE[$ueId] = true;
                        $totalPointsSemestre += $totalPointsUE;
                        $ueAvecMoyenne++;
                        $creditsValidesSemestre += $totalCoeffUE;
                    } else if ($calculerAvecNotesVides || $ecueWithCompleteNotesCount == $ecueCount) {
                        if ($totalCoeffUE > 0) {
                            $moyenneUE = $totalPointsUE / $totalCoeffUE;
                            $moyennesUE[$ueId] = round($moyenneUE, 2);
                            $validationsUE[$ueId] = $moyenneUE >= 10;
                            $totalPointsSemestre += $totalPointsUE;
                            $ueAvecMoyenne++;
                            
                            if ($moyenneUE >= 10) {
                                $creditsValidesSemestre += $totalCoeffUE;
                            }
                        }
                    } else {
                        $moyennesUE[$ueId] = null;
                        $validationsUE[$ueId] = false;
                    }
                    
                    $totalCreditsSemestre += $totalCoeffUE;
                }
            }
            
            // Calculer la moyenne du semestre (même logique que grille_notes.php)
            if ($totalCreditsSemestre > 0) {
                if ($calculerAvecNotesVides || $ueAvecMoyenne == $totalUE) {
                    $moyenneSemestre = $totalPointsSemestre / $totalCreditsSemestre;
                    $moyennesSemestre[$semId] = round($moyenneSemestre, 2);
                    $pourcentageValidation = ($moyenneSemestre / 20) * 100;
                    
                    $validationsSemestre[$semId] = [
                        'credits_valides' => round($creditsValidesSemestre, 2),
                        'credits_total' => round($totalCreditsSemestre, 2),
                        'pourcentage' => round($pourcentageValidation, 2),
                        'est_valide' => $moyenneSemestre >= 10
                    ];
                } else {
                    $moyennesSemestre[$semId] = null;
                    $validationsSemestre[$semId] = [
                        'credits_valides' => round($creditsValidesSemestre, 2),
                        'credits_total' => round($totalCreditsSemestre, 2),
                        'pourcentage' => 0,
                        'est_valide' => false
                    ];
                }
            }
        }
        
        // Si délibération annuelle, calculer la moyenne annuelle
        if ($portee === 'annee' && count($semestresToShow) >= 2) {
            $totalPointsAnnee = 0;
            $totalCreditsAnnee = 0;
            $creditsValidesAnnee = 0;
            $semestreAvecMoyenne = 0;
            
            foreach ($semestresToShow as $semestre) {
                $semId = $semestre['idsemestre'];
                
                if (isset($validationsSemestre[$semId])) {
                    $creditsValidesAnnee += $validationsSemestre[$semId]['credits_valides'];
                    $totalCreditsAnnee += $validationsSemestre[$semId]['credits_total'];
                    
                    if (isset($moyennesSemestre[$semId]) && $moyennesSemestre[$semId] !== null) {
                        $semestreAvecMoyenne++;
                        $totalPointsAnnee += $moyennesSemestre[$semId] * $validationsSemestre[$semId]['credits_total'];
                    }
                }
            }
            
            if ($totalCreditsAnnee > 0) {
                if ($calculerAvecNotesVides || $semestreAvecMoyenne == count($semestresToShow)) {
                    $moyenneAnnuelle = $totalPointsAnnee / $totalCreditsAnnee;
                    $moyennesAnnuelles = round($moyenneAnnuelle, 2);
                    $pourcentageValidationAnnee = ($moyenneAnnuelle / 20) * 100;
                    
                    $validationsAnnuelles = [
                        'credits_valides' => round($creditsValidesAnnee, 2),
                        'credits_total' => round($totalCreditsAnnee, 2),
                        'pourcentage' => round($pourcentageValidationAnnee, 2),
                        'est_valide' => $moyenneAnnuelle >= 10
                    ];
                } else {
                    $moyennesAnnuelles = null;
                    $validationsAnnuelles = [
                        'credits_valides' => round($creditsValidesAnnee, 2),
                        'credits_total' => round($totalCreditsAnnee, 2),
                        'pourcentage' => 0,
                        'est_valide' => false
                    ];
                }
            }
        }
        
        // Déterminer la décision finale
        $decision = $this->determinerDecisionFinale($validationsSemestre, $validationsAnnuelles, $moyennesSemestre, $moyennesAnnuelles, $isDeuxiemeSession, $portee);
        
        return [
            'matricule' => $matricule,
            'etudiant' => $etudiant,
            'moyennes_ue' => $moyennesUE,
            'validations_ue' => $validationsUE,
            'moyennes_semestre' => $moyennesSemestre,
            'validations_semestre' => $validationsSemestre,
            'moyennes_annuelles' => $moyennesAnnuelles,
            'validations_annuelles' => $validationsAnnuelles,
            'resultats_globaux' => $this->calculerResultatsGlobaux($validationsSemestre, $validationsAnnuelles, $moyennesSemestre, $moyennesAnnuelles, $portee),
            'decision' => $decision,
            'config_utilisee' => $this->config
        ];
    }
    
    private function calculerResultatsGlobaux($validationsSemestre, $validationsAnnuelles, $moyennesSemestre, $moyennesAnnuelles, $portee) {
        if ($portee === 'annee' && !empty($validationsAnnuelles)) {
            return [
                'total_credits' => $validationsAnnuelles['credits_total'],
                'credits_valides' => $validationsAnnuelles['credits_valides'],
                'moyenne_generale' => $moyennesAnnuelles,
                'pourcentage_credits' => $validationsAnnuelles['pourcentage'],
                'notes_manquantes' => $moyennesAnnuelles === null
            ];
        } else {
            // Pour un semestre ou si pas de données annuelles
            $premierSemestre = array_values($validationsSemestre)[0] ?? null;
            $premiereMoyenne = array_values($moyennesSemestre)[0] ?? null;
            
            return [
                'total_credits' => $premierSemestre['credits_total'] ?? 0,
                'credits_valides' => $premierSemestre['credits_valides'] ?? 0,
                'moyenne_generale' => $premiereMoyenne,
                'pourcentage_credits' => $premierSemestre['pourcentage'] ?? 0,
                'notes_manquantes' => $premiereMoyenne === null
            ];
        }
    }
    
    private function determinerDecisionFinale($validationsSemestre, $validationsAnnuelles, $moyennesSemestre, $moyennesAnnuelles, $isDeuxiemeSession, $portee) {
        // Utiliser les données globales selon la portée
        if ($portee === 'annee' && !empty($validationsAnnuelles)) {
            $creditsTotal = $validationsAnnuelles['credits_total'];
            $creditsValides = $validationsAnnuelles['credits_valides'];
            $moyenne = $moyennesAnnuelles;
            $notesManquantes = $moyenne === null;
        } else {
            $premierSemestre = array_values($validationsSemestre)[0] ?? null;
            $premiereMoyenne = array_values($moyennesSemestre)[0] ?? null;
            
            $creditsTotal = $premierSemestre['credits_total'] ?? 0;
            $creditsValides = $premierSemestre['credits_valides'] ?? 0;
            $moyenne = $premiereMoyenne;
            $notesManquantes = $moyenne === null;
        }
        
        if ($notesManquantes) {
            return [
                'code' => 'INCOMPLET',
                'libelle' => 'Dossier incomplet',
                'description' => 'Des notes sont manquantes'
            ];
        }
        
        $pourcentageCredits = ($creditsTotal > 0) ? ($creditsValides / $creditsTotal * 100) : 0;
        
        if ($portee === 'annee') {
            // Logique pour délibération annuelle
            if ($isDeuxiemeSession) {
                if ($creditsValides == $creditsTotal && $moyenne >= 10) {
                    return [
                        'code' => 'ADMIS_SANS_RACHAT',
                        'libelle' => 'Admis sans rachat',
                        'description' => 'Tous les crédits validés avec moyenne >= 10'
                    ];
                } elseif ($pourcentageCredits >= $this->config['pourcentage_passage_semestre'] && $moyenne >= 10) {
                    return [
                        'code' => 'ADMIS_AVEC_RACHAT',
                        'libelle' => 'Admis avec rachat',
                        'description' => 'Pourcentage de crédits suffisant avec moyenne >= 10'
                    ];
                } else {
                    return [
                        'code' => 'AJOURNE',
                        'libelle' => 'Ajourné',
                        'description' => 'Crédits ou moyenne insuffisants'
                    ];
                }
            } else {
                if ($creditsValides == $creditsTotal && $moyenne >= 10) {
                    return [
                        'code' => 'ADMIS_SANS_RACHAT',
                        'libelle' => 'Admis sans rachat',
                        'description' => 'Tous les crédits validés avec moyenne >= 10'
                    ];
                } else {
                    return [
                        'code' => 'ADMIS_RATTRAPAGE',
                        'libelle' => 'Admis au rattrapage',
                        'description' => 'Passage en deuxième session'
                    ];
                }
            }
        } else {
            // Logique pour délibération semestrielle
            if ($creditsValides == $creditsTotal) {
                return [
                    'code' => 'VALIDE_TOTALEMENT',
                    'libelle' => 'Semestre validé totalement',
                    'description' => 'Tous les crédits du semestre sont validés'
                ];
            } elseif ($creditsValides > 0) {
                return [
                    'code' => 'VALIDE_PARTIELLEMENT',
                    'libelle' => 'Semestre validé partiellement',
                    'description' => 'Une partie des crédits est validée'
                ];
            } else {
                return [
                    'code' => 'NON_VALIDE',
                    'libelle' => 'Semestre non validé',
                    'description' => 'Aucun crédit validé'
                ];
            }
        }
    }
    
    private function calculerStatistiques($resultats) {
        $stats = [
            'admis_sans_rachat' => 0,
            'admis_avec_rachat' => 0,
            'admis_rattrapage' => 0,
            'ajournes' => 0,
            'incomplets' => 0,
            'valide_totalement' => 0,
            'valide_partiellement' => 0,
            'non_valide' => 0
        ];
        
        foreach ($resultats as $resultat) {
            if (isset($resultat['decision']['code'])) {
                switch ($resultat['decision']['code']) {
                    case 'ADMIS_SANS_RACHAT':
                        $stats['admis_sans_rachat']++;
                        break;
                    case 'ADMIS_AVEC_RACHAT':
                        $stats['admis_avec_rachat']++;
                        break;
                    case 'ADMIS_RATTRAPAGE':
                        $stats['admis_rattrapage']++;
                        break;
                    case 'AJOURNE':
                        $stats['ajournes']++;
                        break;
                    case 'INCOMPLET':
                        $stats['incomplets']++;
                        break;
                    case 'VALIDE_TOTALEMENT':
                        $stats['valide_totalement']++;
                        break;
                    case 'VALIDE_PARTIELLEMENT':
                        $stats['valide_partiellement']++;
                        break;
                    case 'NON_VALIDE':
                        $stats['non_valide']++;
                        break;
                }
            }
        }
        
        return $stats;
    }
}

// Traitement de la requête
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$deliberationAuto = new DeliberationAutomatiqueV2();

switch ($action) {
    case 'evaluer_promotion':
        try {
            $promotionId = intval($_POST['promotion_id'] ?? 0);
            $bureauId = intval($_POST['bureau_id'] ?? 0);
            $sessionId = intval($_POST['session_id'] ?? 0);
            $anneeId = intval($_POST['annee_id'] ?? 0);
            $portee = $_POST['portee'] ?? 'semestre';
            $semestreId = intval($_POST['semestre_id'] ?? 0);
            $mode = $_POST['mode'] ?? 'automatique';
            
            // Log des paramètres pour debug
            error_log("Délibération automatique - Paramètres reçus:");
            error_log("Promotion: $promotionId, Bureau: $bureauId, Session: $sessionId, Année: $anneeId");
            error_log("Portée: $portee, Semestre: $semestreId, Mode: $mode");
            
            if (!$promotionId || !$bureauId || !$sessionId || !$anneeId) {
                throw new Exception("Paramètres manquants: promotion=$promotionId, bureau=$bureauId, session=$sessionId, annee=$anneeId");
            }
            
            if ($portee === 'semestre' && !$semestreId) {
                throw new Exception("Semestre requis pour délibération semestrielle");
            }
            
            $resultats = $deliberationAuto->evaluerPromotion($promotionId, $bureauId, $sessionId, $anneeId, $portee, $semestreId, $mode);
            
            if (isset($resultats['error'])) {
                error_log("Erreur délibération: " . $resultats['error']);
            } else {
                error_log("Délibération réussie - " . count($resultats['resultats']) . " étudiants traités");
            }
            
            header('Content-Type: application/json');
            echo json_encode($resultats);
        } catch (Exception $e) {
            error_log("Erreur délibération automatique: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    default:
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Action non reconnue: ' . $action]);
        break;
}
?>
