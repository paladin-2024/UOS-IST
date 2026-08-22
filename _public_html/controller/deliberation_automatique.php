<?php
require_once dirname(__DIR__).'/config/Connexion.php';
require_once dirname(__DIR__).'/models/Universite.php';
require_once dirname(__DIR__).'/models/Deliberation.php';

session_start();
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Session expirée']);
    exit();
}

class DeliberationAutomatique {
    private $universite;
    private $deliberation;
    private $config;
    
    public function __construct() {
        $this->universite = new Universite();
        $this->deliberation = new Deliberation();
    }
    
    public function evaluerEtudiant($matricule, $bureauId, $sessionId, $anneeId, $promotionId = null) {
        // Récupérer la configuration de délibération
        $this->config = $this->universite->getDeliberationConfig($bureauId, $sessionId, $anneeId);
        
        if (!$this->config) {
            return [
                'error' => 'Configuration de délibération non trouvée',
                'matricule' => $matricule
            ];
        }
        
        // Récupérer les informations de l'étudiant
        $etudiant = $this->deliberation->getEtudiantByMatricule($matricule, $anneeId);
        if (!$etudiant) {
            return [
                'error' => 'Étudiant non trouvé',
                'matricule' => $matricule
            ];
        }
        
        // Récupérer les semestres de la promotion
        $semestres = [];
        if ($promotionId) {
            $semestres = $this->universite->getSemestresByPromotion($promotionId);
        }
        
        // Évaluer chaque semestre
        $resultatsEvaluation = [];
        $totalCredits = 0;
        $totalCreditsValides = 0;
        $moyennePondereeGlobale = 0;
        $totalCoefficientsGlobaux = 0;
        $notesManquantesGlobal = false;
        
        foreach ($semestres as $semestre) {
            $evaluationSemestre = $this->evaluerSemestre($matricule, $semestre['idsemestre'], $sessionId, $anneeId);
            $resultatsEvaluation[] = $evaluationSemestre;
            
            $totalCredits += $evaluationSemestre['credits_total'];
            $totalCreditsValides += $evaluationSemestre['credits_valides'];
            
            if ($evaluationSemestre['moyenne'] !== null) {
                $moyennePondereeGlobale += ($evaluationSemestre['moyenne'] * $evaluationSemestre['credits_total']);
                $totalCoefficientsGlobaux += $evaluationSemestre['credits_total'];
            }
            
            if ($evaluationSemestre['notes_manquantes']) {
                $notesManquantesGlobal = true;
            }
        }
        
        // Calculer la moyenne générale
        $moyenneGenerale = null;
        if (!$notesManquantesGlobal && $totalCoefficientsGlobaux > 0) {
            $moyenneGenerale = $moyennePondereeGlobale / $totalCoefficientsGlobaux;
        }
        
        // Déterminer la décision finale
        $decision = $this->determinerDecisionFinale($totalCredits, $totalCreditsValides, $moyenneGenerale, $notesManquantesGlobal, $sessionId);
        
        return [
            'matricule' => $matricule,
            'etudiant' => $etudiant,
            'semestres' => $resultatsEvaluation,
            'resultats_globaux' => [
                'total_credits' => $totalCredits,
                'credits_valides' => $totalCreditsValides,
                'moyenne_generale' => $moyenneGenerale,
                'pourcentage_credits' => ($totalCredits > 0) ? ($totalCreditsValides / $totalCredits * 100) : 0,
                'notes_manquantes' => $notesManquantesGlobal
            ],
            'decision' => $decision,
            'config_utilisee' => $this->config
        ];
    }
    
    private function evaluerSemestre($matricule, $semestreId, $sessionId, $anneeId) {
        // Récupérer les notes du semestre
        $notesEtudiant = $this->deliberation->getNotesEtudiant($matricule, $sessionId, $anneeId, $semestreId);
        
        if (empty($notesEtudiant)) {
            return [
                'semestre_id' => $semestreId,
                'credits_total' => 0,
                'credits_valides' => 0,
                'moyenne' => null,
                'notes_manquantes' => true,
                'ues' => []
            ];
        }
        
        $semestreData = $notesEtudiant[0];
        $creditsTotal = 0;
        $creditsValides = 0;
        $moyennePonderee = 0;
        $coefficientsTotal = 0;
        $notesManquantes = false;
        $uesEvaluees = [];
        
        // Évaluer chaque UE
        foreach ($semestreData['ues'] as $ueData) {
            $evaluationUE = $this->evaluerUE($matricule, $ueData, $sessionId, $anneeId);
            $uesEvaluees[] = $evaluationUE;
            
            $credits = floatval($evaluationUE['credits']);
            $creditsTotal += $credits;
            
            if ($evaluationUE['est_validee']) {
                $creditsValides += $credits;
            }
            
            if ($evaluationUE['moyenne'] !== null) {
                $moyennePonderee += ($evaluationUE['moyenne'] * $credits);
                $coefficientsTotal += $credits;
            }
            
            if ($evaluationUE['notes_manquantes']) {
                $notesManquantes = true;
            }
        }
        
        // Application de la compensation inter-UE si activée
        if ($this->config['compensation_inter_ue'] && !$notesManquantes) {
            $uesEvaluees = $this->appliquerCompensationInterUE($uesEvaluees);
            
            // Recalculer les crédits validés après compensation
            $creditsValides = 0;
            foreach ($uesEvaluees as $ue) {
                if ($ue['est_validee']) {
                    $creditsValides += $ue['credits'];
                }
            }
        }
        
        // Calculer la moyenne du semestre
        $moyenneSemestre = null;
        if (!$notesManquantes && $coefficientsTotal > 0) {
            $moyenneSemestre = $moyennePonderee / $coefficientsTotal;
        }
        
        return [
            'semestre_id' => $semestreId,
            'credits_total' => $creditsTotal,
            'credits_valides' => $creditsValides,
            'moyenne' => $moyenneSemestre,
            'notes_manquantes' => $notesManquantes,
            'ues' => $uesEvaluees
        ];
    }
    
    private function evaluerUE($matricule, $ueData, $sessionId, $anneeId) {
        $ue = $ueData['info'];
        $ueId = $ue['idUE'] ?? 0;
        $credits = round(floatval($ue['nombre_credits'] ?? 0), 2);
        
        // Vérifier les notes des ECUEs
        $moyennesPonderees = 0;
        $coefficientsTotal = 0;
        $notesManquantes = false;
        $ecuesEvaluees = [];
        
        foreach ($ueData['ecues'] as $ecue) {
            $ecueId = $ecue['idECUE'] ?? 0;
            $coefficient = round(floatval($ecue['coefficient'] ?? 0), 2);
            
            // Récupérer les notes de l'ECUE
            $notes = $this->deliberation->getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId);
            
            if (!$notes || $notes['MF'] === null) {
                $notesManquantes = true;
                $ecuesEvaluees[] = [
                    'ecue_id' => $ecueId,
                    'nom' => $ecue['designationECUE'],
                    'coefficient' => $coefficient,
                    'note' => null,
                    'notes_manquantes' => true
                ];
            } else {
                $noteEcue = floatval($notes['MF']);
                $moyennesPonderees += ($noteEcue * $coefficient);
                $coefficientsTotal += $coefficient;
                
                $ecuesEvaluees[] = [
                    'ecue_id' => $ecueId,
                    'nom' => $ecue['designationECUE'],
                    'coefficient' => $coefficient,
                    'note' => $noteEcue,
                    'notes_manquantes' => false
                ];
            }
        }
        
        // Calculer la moyenne de l'UE
        $moyenneUE = null;
        $estValidee = false;
        
        if (!$notesManquantes && $coefficientsTotal > 0) {
            $moyenneUE = $moyennesPonderees / $coefficientsTotal;
            
            // Application de la compensation intra-UE si activée
            if ($this->config['compensation_intra_ue']) {
                $estValidee = $this->appliquerCompensationIntraUE($ecuesEvaluees, $moyenneUE);
            } else {
                // Sans compensation, toutes les ECUEs doivent avoir >= note_passage
                $estValidee = true;
                foreach ($ecuesEvaluees as $ecue) {
                    if ($ecue['note'] < $this->config['note_passage']) {
                        $estValidee = false;
                        break;
                    }
                }
            }
        }
        
        return [
            'ue_id' => $ueId,
            'nom' => $ue['designationUE'],
            'code' => $ue['codeUE'],
            'credits' => $credits,
            'moyenne' => $moyenneUE,
            'est_validee' => $estValidee,
            'notes_manquantes' => $notesManquantes,
            'ecues' => $ecuesEvaluees
        ];
    }
    
    private function appliquerCompensationIntraUE($ecues, $moyenneUE) {
        // Vérifier si toutes les ECUEs ont au moins le seuil de compensation
        foreach ($ecues as $ecue) {
            if ($ecue['note'] < $this->config['seuil_compensation_intra_ue']) {
                return false;
            }
        }
        
        // Si la moyenne de l'UE est >= note_passage, l'UE est validée
        return $moyenneUE >= $this->config['note_passage'];
    }
    
    private function appliquerCompensationInterUE($ues) {
        if (!$this->config['compensation_inter_ue']) {
            return $ues;
        }
        
        // Séparer les UEs par nombre de crédits si l'option est activée
        if ($this->config['exiger_meme_credit_ue']) {
            $groupes = [];
            foreach ($ues as $ue) {
                $credits = $ue['credits'];
                if (!isset($groupes[$credits])) {
                    $groupes[$credits] = [];
                }
                $groupes[$credits][] = $ue;
            }
            
            // Appliquer la compensation dans chaque groupe
            $resultats = [];
            foreach ($groupes as $groupe) {
                $resultats = array_merge($resultats, $this->compenserGroupeUEs($groupe));
            }
            
            return $resultats;
        } else {
            // Compensation sur toutes les UEs du semestre
            return $this->compenserGroupeUEs($ues);
        }
    }
    
    private function compenserGroupeUEs($ues) {
        $moyennePondereeGroupe = 0;
        $creditsTotal = 0;
        $peutCompenser = true;
        
        // Vérifier que toutes les UEs respectent le seuil de compensation
        foreach ($ues as $ue) {
            if ($ue['moyenne'] === null || $ue['moyenne'] < $this->config['seuil_compensation_inter_ue']) {
                $peutCompenser = false;
                break;
            }
            
            $moyennePondereeGroupe += ($ue['moyenne'] * $ue['credits']);
            $creditsTotal += $ue['credits'];
        }
        
        if ($peutCompenser && $creditsTotal > 0) {
            $moyenneGroupe = $moyennePondereeGroupe / $creditsTotal;
            
            // Si la moyenne du groupe est >= note_passage, valider toutes les UEs du groupe
            if ($moyenneGroupe >= $this->config['note_passage']) {
                foreach ($ues as &$ue) {
                    if (!$ue['est_validee']) {
                        $ue['est_validee'] = true;
                        $ue['validee_par_compensation'] = true;
                    }
                }
            }
        }
        
        return $ues;
    }
    
    private function determinerDecisionFinale($totalCredits, $creditsValides, $moyenneGenerale, $notesManquantes, $sessionId) {
        if ($notesManquantes) {
            return [
                'code' => 'INCOMPLET',
                'libelle' => 'Dossier incomplet',
                'description' => 'Des notes sont manquantes'
            ];
        }
        
        $pourcentageCredits = ($totalCredits > 0) ? ($creditsValides / $totalCredits * 100) : 0;
        
        // Déterminer si c'est la deuxième session
        $session = $this->universite->getSessionById($sessionId);
        $isDeuxiemeSession = $session && (stripos($session['designSession'], 'deuxième') !== false || 
                                        stripos($session['designSession'], 'deuxieme') !== false);
        
        if ($isDeuxiemeSession) {
            // Logique pour deuxième session
            if ($creditsValides == $totalCredits && $moyenneGenerale >= 10) {
                return [
                    'code' => 'ADMIS_SANS_RACHAT',
                    'libelle' => 'Admis sans rachat',
                    'description' => 'Tous les crédits validés avec moyenne >= 10'
                ];
            } elseif ($pourcentageCredits >= $this->config['pourcentage_passage_semestre'] && $moyenneGenerale >= 10) {
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
            // Logique pour première session
            if ($creditsValides == $totalCredits && $moyenneGenerale >= 10) {
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
    }
    
    public function evaluerPromotion($promotionId, $bureauId, $sessionId, $anneeId, $mode = 'automatique') {
        // Récupérer tous les étudiants de la promotion
        $etudiants = $this->deliberation->getEtudiantsByPromotion($promotionId, $anneeId);
        
        $resultats = [];
        foreach ($etudiants as $etudiant) {
            $evaluation = $this->evaluerEtudiant($etudiant['matricule'], $bureauId, $sessionId, $anneeId, $promotionId);
            $resultats[] = $evaluation;
        }
        
        return [
            'mode' => $mode,
            'promotion_id' => $promotionId,
            'total_etudiants' => count($etudiants),
            'resultats' => $resultats,
            'statistiques' => $this->calculerStatistiques($resultats)
        ];
    }
    
    private function calculerStatistiques($resultats) {
        $stats = [
            'admis_sans_rachat' => 0,
            'admis_avec_rachat' => 0,
            'admis_rattrapage' => 0,
            'ajournes' => 0,
            'incomplets' => 0
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
                }
            }
        }
        
        return $stats;
    }
}

// Traitement de la requête
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$deliberationAuto = new DeliberationAutomatique();

switch ($action) {
    case 'evaluer_etudiant':
        $matricule = $_POST['matricule'] ?? '';
        $bureauId = intval($_POST['bureau_id'] ?? 0);
        $sessionId = intval($_POST['session_id'] ?? 0);
        $anneeId = intval($_POST['annee_id'] ?? 0);
        $promotionId = intval($_POST['promotion_id'] ?? 0);
        
        $resultat = $deliberationAuto->evaluerEtudiant($matricule, $bureauId, $sessionId, $anneeId, $promotionId);
        header('Content-Type: application/json');
        echo json_encode($resultat);
        break;
        
    case 'evaluer_promotion':
        $promotionId = intval($_POST['promotion_id'] ?? 0);
        $bureauId = intval($_POST['bureau_id'] ?? 0);
        $sessionId = intval($_POST['session_id'] ?? 0);
        $anneeId = intval($_POST['annee_id'] ?? 0);
        $mode = $_POST['mode'] ?? 'automatique';
        
        $resultats = $deliberationAuto->evaluerPromotion($promotionId, $bureauId, $sessionId, $anneeId, $mode);
        header('Content-Type: application/json');
        echo json_encode($resultats);
        break;
        
    default:
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Action non reconnue']);
        break;
}
?>
