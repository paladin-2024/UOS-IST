<?php
/**
 * Classe ParcoursEtudiant
 * Centralise la récupération et fusion des données de parcours académique
 */
class ParcoursEtudiant
{
    private $connexion;
    private $deliberation;
    private $grilleAncienne;
    private $dette;
    private $universite;

    public function __construct()
    {
        $this->connexion = Connexion::getInstance()->getPDO();
        $this->deliberation = new Deliberation();
        $this->grilleAncienne = new GrilleAncienne();
        $this->dette = new Dette();
        $this->universite = new Universite();
    }

    /**
     * Récupère tous les résultats consolidés d'un étudiant
     */
    public function getParcoursComplet($matricule, $annee_id = null, $promotion_id = null)
    {
        $resultatsSysteme = [];
        $resultatsImportes = [];
        $dettes = [];

        // Récupérer les notes du système
        if (!empty($annee_id)) {
            $resultatsSysteme = $this->getResultatsSystemeParAnnee($matricule, $annee_id);
        } else {
            $resultatsSysteme = $this->getResultatsSystemeComplets($matricule);
        }

        // Récupérer et fusionner les imports
        $resultatsImportesOriginaux = $this->grilleAncienne->getResultatsEtudiantImportes($matricule);
        $resultatsImportes = $this->fusionnerImports($resultatsImportesOriginaux);

        // Récupérer les dettes
        $dettes = $this->dette->getDettesEtudiant($matricule);

        // Calculer la synthèse
        $synthese = $this->calculerSynthese($resultatsSysteme, $resultatsImportes, $dettes);

        return [
            'resultatsSysteme' => $resultatsSysteme,
            'resultatsImportes' => $resultatsImportes,
            'dettes' => $dettes,
            'synthese' => $synthese
        ];
    }

    /**
     * Récupère les résultats système pour une année spécifique
     */
    private function getResultatsSystemeParAnnee($matricule, $annee_id)
    {
        $toutes_sessions = $this->universite->getAllSessions();
        $notesParSession = [];
        $resultatsSysteme = [];

        foreach ($toutes_sessions as $session) {
            $sessionId = $session['idsession'];
            $notesParSession[$sessionId] = $this->deliberation->getNotesEtudiant($matricule, $sessionId, $annee_id);
        }

        foreach ($toutes_sessions as $session) {
            $sessionId = $session['idsession'];
            if (!empty($notesParSession[$sessionId])) {
                foreach ($notesParSession[$sessionId] as $semData) {
                    $semestreKey = 'Semestre ' . ($semData['info']['numeroSemestre'] ?? 'N/A');

                    $uesNormalisees = [];
                    if (!empty($semData['ues'])) {
                        foreach ($semData['ues'] as $ue) {
                            $uesNormalisees[] = [
                                'code' => $ue['info']['codeUE'] ?? '',
                                'designation' => $ue['info']['designationUE'] ?? '',
                                'moyenne' => $ue['info']['moyenne'] ?? 0,
                                'credits_total' => $ue['info']['nombre_credits'] ?? 0,
                                'credits_valides' => ($ue['info']['est_validee'] ?? false) ? ($ue['info']['nombre_credits'] ?? 0) : 0,
                                'est_valide' => $ue['info']['est_validee'] ?? false
                            ];
                        }
                    }

                    $resultatsSysteme[$semestreKey] = [
                        'info' => $semData['info'],
                        'annee_id' => $annee_id,
                        'ues' => $uesNormalisees
                    ];
                }
            }
        }

        return $resultatsSysteme;
    }

    /**
     * Récupère tous les résultats système avec fusion par code UE
     */
    private function getResultatsSystemeComplets($matricule)
    {
        $toutes_sessions = $this->universite->getAllSessions();
        $anneeEtudiant = null;
        
        // Récupérer toutes les années pour cet étudiant
        $annees_etudiant = [];
        try {
            $query = "SELECT DISTINCT annee_acad_idannee_acad FROM etudiant WHERE matricule = :matricule ORDER BY annee_acad_idannee_acad DESC";
            $stmt = $this->connexion->prepare($query);
            $stmt->execute([':matricule' => $matricule]);
            $annees_etudiant = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Erreur récupération années: " . $e->getMessage());
        }

        $resultatsSysteme = [];

        // Pour chaque année de l'étudiant
        foreach ($annees_etudiant as $annee_acad_id) {
            $notesParSession = [];

            foreach ($toutes_sessions as $session) {
                $sessionId = $session['idsession'];
                $notesParSession[$sessionId] = $this->deliberation->getNotesEtudiant($matricule, $sessionId, $annee_acad_id);
            }

            // Fusionner les données par session et code UE
            foreach (array_reverse($toutes_sessions) as $session) {
                $sessionId = $session['idsession'];

                if (empty($notesParSession[$sessionId])) {
                    continue;
                }

                foreach ($notesParSession[$sessionId] as $semData) {
                    $anneeDesignation = $this->getAnneeDesignation($annee_acad_id);
                    $semestreKey = 'Semestre ' . $semData['info']['numeroSemestre'] . ' (' . $anneeDesignation . ')';

                    if (!isset($resultatsSysteme[$semestreKey])) {
                        $resultatsSysteme[$semestreKey] = [
                            'info' => $semData['info'],
                            'annee_id' => $annee_acad_id,
                            'annee_designation' => $anneeDesignation,
                            'ues' => []
                        ];
                    }

                    foreach ($semData['ues'] as $ue) {
                        $codeUE = $ue['info']['codeUE'];
                        $estValidee = isset($ue['info']['est_validee']) ? $ue['info']['est_validee'] == 1 : false;
                        $moyenneUE = isset($ue['info']['moyenne']) ? $ue['info']['moyenne'] : null;

                        // Vérifier si UE existe déjà
                        $ueExists = false;
                        foreach ($resultatsSysteme[$semestreKey]['ues'] as &$ueExistante) {
                            if ($ueExistante['code'] === $codeUE) {
                                // Garder le meilleur résultat
                                if ($estValidee || ($moyenneUE !== null && $ueExistante['moyenne'] !== null && $moyenneUE > $ueExistante['moyenne'])) {
                                    $ueExistante['designation'] = $ue['info']['designationUE'];
                                    $ueExistante['credits_total'] = $ue['info']['nombre_credits'] ?? 0;
                                    $ueExistante['credits_valides'] = $estValidee ? ($ue['info']['nombre_credits'] ?? 0) : 0;
                                    $ueExistante['moyenne'] = $moyenneUE ?? 0;
                                    $ueExistante['est_valide'] = $estValidee;
                                }
                                $ueExists = true;
                                break;
                            }
                        }

                        if (!$ueExists) {
                            $resultatsSysteme[$semestreKey]['ues'][] = [
                                'code' => $codeUE,
                                'designation' => $ue['info']['designationUE'],
                                'credits_total' => $ue['info']['nombre_credits'] ?? 0,
                                'credits_valides' => $estValidee ? ($ue['info']['nombre_credits'] ?? 0) : 0,
                                'moyenne' => $moyenneUE ?? 0,
                                'est_valide' => $estValidee
                            ];
                        }
                    }
                }
            }
        }

        return $resultatsSysteme;
    }

    /**
     * Fusionne les imports en les organisant par année académique
     * Prend le meilleur résultat par UE au sein de chaque année
     */
    private function fusionnerImports($resultatsImportesOriginaux)
    {
        // Organiser par année académique
        $importParAnnee = [];
        
        foreach ($resultatsImportesOriginaux as $import) {
            $annee = $import['annee_academique'] ?? 'N/A';
            
            if (!isset($importParAnnee[$annee])) {
                $importParAnnee[$annee] = [
                    'annee_academique' => $annee,
                    'session' => $import['session'] ?? '',
                    'ues' => [],
                    'date_import' => $import['date_import'] ?? date('Y-m-d H:i:s'),
                    'fichier_origine' => $import['fichier_origine'] ?? ''
                ];
            }
            
            // Fusionner les UEs pour cette année (meilleur résultat)
            foreach ($import['ues'] ?? [] as $ue) {
                $codeUE = $ue['code_ue'] ?? '';
                
                // Si l'UE n'existe pas encore ou si le nouveau résultat est meilleur
                if (!isset($importParAnnee[$annee]['ues'][$codeUE]) || 
                    ($ue['est_valide'] ?? false) || 
                    (($ue['moyenne'] ?? 0) > ($importParAnnee[$annee]['ues'][$codeUE]['moyenne'] ?? 0))) {
                    
                    $importParAnnee[$annee]['ues'][$codeUE] = [
                        'code_ue' => $ue['code_ue'],
                        'designation_ue' => $ue['designation_ue'],
                        'credits' => $ue['credits'],
                        'credits_total' => $ue['credits_total'] ?? $ue['credits'] ?? 0,
                        'credits_valides' => ($ue['est_valide'] ?? false) ? ($ue['credits_total'] ?? $ue['credits'] ?? 0) : 0,
                        'moyenne' => $ue['moyenne'] ?? 0,
                        'est_valide' => $ue['est_valide'] ?? false,
                        'mention' => $ue['mention'] ?? '',
                        'type_resultat' => $ue['type_resultat'] ?? 'Import'
                    ];
                }
            }
        }
        
        // Convertir en array de imports avec UEs comme array
        $resultatsImportes = [];
        foreach ($importParAnnee as $annee => $data) {
            $resultatsImportes[] = [
                'annee_academique' => $annee,
                'session' => $data['session'],
                'date_import' => $data['date_import'],
                'fichier_origine' => $data['fichier_origine'],
                'ues' => array_values($data['ues'])
            ];
        }
        
        // Trier par année décroissante
        usort($resultatsImportes, function($a, $b) {
            return strnatcmp($b['annee_academique'], $a['annee_academique']);
        });
        
        return $resultatsImportes;
    }

    /**
     * Calcule la synthèse des crédits
     */
    private function calculerSynthese($resultatsSysteme, $resultatsImportes, $dettes)
    {
        $creditsValides = 0;
        $creditsDettes = 0;
        $creditsTotal = 0;
        $nombreDettes = count($dettes);

        // Crédits du système
        foreach ($resultatsSysteme as $semestre) {
            foreach ($semestre['ues'] as $ue) {
                $total = intval($ue['credits_total'] ?? 0);
                $creditsTotal += $total;
                
                if ($ue['est_valide'] ?? false) {
                    $creditsValides += intval($ue['credits_valides'] ?? 0);
                } else {
                    $creditsDettes += $total;
                }
            }
        }

        // Crédits des imports
        foreach ($resultatsImportes as $import) {
            foreach ($import['ues'] as $ue) {
                $total = intval($ue['credits_total'] ?? $ue['credits'] ?? 0);
                $creditsTotal += $total;
                
                if ($ue['est_valide']) {
                    $creditsValides += intval($ue['credits_valides'] ?? $total);
                } else {
                    $creditsDettes += $total;
                }
            }
        }

        $pourcentage = $creditsTotal > 0 ? round(($creditsValides / $creditsTotal) * 100, 1) : 0;

        return [
            'credits_total' => $creditsTotal,
            'credits_valides' => $creditsValides,
            'credits_dettes' => $creditsDettes,
            'pourcentage' => $pourcentage,
            'nombre_dettes' => $nombreDettes
        ];
    }

    /**
     * Récupère la désignation de l'année académique
     */
    private function getAnneeDesignation($annee_id)
    {
        try {
            $query = "SELECT designation FROM annee_acad WHERE idannee_acad = :id";
            $stmt = $this->connexion->prepare($query);
            $stmt->execute([':id' => $annee_id]);
            $result = $stmt->fetch();
            return $result['designation'] ?? 'N/A';
        } catch (Exception $e) {
            return 'N/A';
        }
    }
}
