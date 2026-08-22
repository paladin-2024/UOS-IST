<?php
// Vérification des droits d'accès
if (!isset($_SESSION['idRole'])) {
    header('Location: index.php');
    exit();
}

// Inclusion du header
include "./views/include/header.php";

$universite = new Universite();
$dette = new Dette();
$grilleAncienne = new GrilleAncienne();
$etudiantModel = new Etudiant();
// Note: Deliberation sera instancié dans la section de traitement si nécessaire

// Récupération des paramètres
$matricule = $_GET['matricule'] ?? '';
$annee_id = $_GET['annee_id'] ?? '';
$promotion_id = $_GET['promotion_id'] ?? '';

$etudiant = null;
$promotions = [];
$annees = [];
$resultatsSysteme = [];
$resultatsImportes = [];
$dettes = [];
$syntheseCredits = [];

if (!empty($matricule)) {
    // Récupérer l'étudiant - d'abord par matricule, sinon chercher par nom
    $etudiant = $etudiantModel->getEtudiantByMatricule($matricule);
    
    // Si pas trouvé par matricule, chercher par nom dans la promotion/année
    if (!$etudiant && !empty($promotion_id) && !empty($annee_id)) {
        $etudiants = $etudiantModel->searchStudentsByNameOrMatricule($matricule, $promotion_id, $annee_id);
        if (!empty($etudiants)) {
            // Prendre le premier résultat et récupérer ses détails complets
            $etudiant = $etudiantModel->getEtudiantByMatricule($etudiants[0]['matricule']);
            $matricule = $etudiants[0]['matricule']; // Utiliser le vrai matricule
        }
    }
    
    if ($etudiant) {
         // Récupération des données du système actuel
         // Si année et promotion sont spécifiées, récupérer pour cette année
         // Sinon, récupérer TOUTES les années de l'étudiant
         if (!empty($annee_id) && !empty($promotion_id)) {
                     $deliberation = new Deliberation();
                     $toutes_sessions = $universite->getAllSessions();
                     $notesParSession = [];
                     
                     foreach ($toutes_sessions as $session) {
                         $sessionId = $session['idsession'];
                         $notesParSession[$sessionId] = $deliberation->getNotesEtudiant($matricule, $sessionId, $annee_id);
                     }
                     
                     // Récupérer les dettes
                     $dettes = $dette->getDettesEtudiant($matricule);
                     
                     // Construire les résultats système à partir des notes - MEILLEURE NOTE parmi les sessions
                     $resultatsSysteme = [];
                     $meilleuresUEs = []; // Tracker les meilleures UE par code
                     
                     foreach ($toutes_sessions as $session) {
                         $sessionId = $session['idsession'];
                         if (!empty($notesParSession[$sessionId])) {
                             foreach ($notesParSession[$sessionId] as $semData) {
                                 $semestreKey = 'Semestre ' . $semData['info']['numeroSemestre'];
                                 
                                 if (!empty($semData['ues'])) {
                                     foreach ($semData['ues'] as $ue) {
                                         $codeUE = $ue['info']['codeUE'] ?? '';
                                         $estValidee = $ue['info']['est_validee'] ?? false;
                                         $moyenne = $ue['info']['moyenne'] ?? 0;
                                         
                                         // Si cette UE n'a pas encore été vue, ou si cette note est meilleure
                                         if (!isset($meilleuresUEs[$codeUE]) || 
                                             $estValidee || 
                                             ($moyenne > 0 && $moyenne > ($meilleuresUEs[$codeUE]['moyenne'] ?? 0))) {
                                             
                                             $meilleuresUEs[$codeUE] = [
                                                 'code' => $codeUE,
                                                 'designation' => $ue['info']['designationUE'] ?? '',
                                                 'moyenne' => $moyenne,
                                                 'credits_total' => $ue['info']['nombre_credits'] ?? 0,
                                                 'credits_valides' => $estValidee ? ($ue['info']['nombre_credits'] ?? 0) : 0,
                                                 'est_valide' => $estValidee,
                                                 'semestre' => $semestreKey,
                                                 'info' => $semData['info']
                                             ];
                                         }
                                     }
                                 }
                             }
                         }
                     }
                     
                     // Organiser par semestre
                     foreach ($meilleuresUEs as $ue) {
                         $semestreKey = $ue['semestre'];
                         
                         if (!isset($resultatsSysteme[$semestreKey])) {
                             $resultatsSysteme[$semestreKey] = [
                                 'info' => $ue['info'],
                                 'annee_id' => $annee_id,
                                 'ues' => []
                             ];
                         }
                         
                         $resultatsSysteme[$semestreKey]['ues'][] = [
                             'code' => $ue['code'],
                             'designation' => $ue['designation'],
                             'moyenne' => $ue['moyenne'],
                             'credits_total' => $ue['credits_total'],
                             'credits_valides' => $ue['credits_valides'],
                             'est_valide' => $ue['est_valide']
                         ];
                     }
         } else if (!empty($matricule)) {
             // Récupérer TOUTES les années de l'étudiant
             $deliberation = new Deliberation();
             $toutes_sessions = $universite->getAllSessions();
             $anneeEtudiant = $etudiant['annee_acad_idannee_acad'] ?? null;
             
             // Récupérer toutes les années pour cet étudiant
             $annees_etudiant = [];
             try {
                 $conn = Connexion::getInstance()->getPDO();
                 $query = "SELECT DISTINCT annee_acad_idannee_acad FROM etudiant WHERE matricule = :matricule ORDER BY annee_acad_idannee_acad DESC";
                 $stmt = $conn->prepare($query);
                 $stmt->execute([':matricule' => $matricule]);
                 $annees_etudiant = $stmt->fetchAll(PDO::FETCH_COLUMN);
             } catch (Exception $e) {
                 error_log("Erreur récupération années étudiant: " . $e->getMessage());
             }
             
             $resultatsSysteme = [];
             
             // Pour chaque année de l'étudiant
             foreach ($annees_etudiant as $annee_acad_id) {
                 $notesParSession = [];
                 
                 foreach ($toutes_sessions as $session) {
                     $sessionId = $session['idsession'];
                     $notesParSession[$sessionId] = $deliberation->getNotesEtudiant($matricule, $sessionId, $annee_acad_id);
                 }
                 
                 // Fusionner les données de cette année
                 foreach (array_reverse($toutes_sessions) as $session) {
                     $sessionId = $session['idsession'];
                     
                     if (empty($notesParSession[$sessionId])) {
                         continue;
                     }
                     
                     foreach ($notesParSession[$sessionId] as $semData) {
                         $anneeDesignation = '';
                         foreach ($annees as $a) {
                             if ($a['idannee_acad'] == $annee_acad_id) {
                                 $anneeDesignation = $a['designation'];
                                 break;
                             }
                         }
                         
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
                             $totalCredits = $ue['info']['nombre_credits'] ?? 0;
                             $creditsValides = 0;
                             
                             // Si moyenne est vide/null, calculer à partir des ECUE
                             if ($moyenneUE === null || $moyenneUE === '' || $moyenneUE === 0) {
                                 $creditsValides = 0;
                                 $totalCredits = 0;
                                 
                                 if (!empty($ue['ecues'])) {
                                     foreach ($ue['ecues'] as $ecue) {
                                         $ecueCredit = (floatval($ecue['CMI']) + floatval($ecue['TP']) + floatval($ecue['TD'])) / 22;
                                         $ecueCredit = round($ecueCredit, 1);
                                         $totalCredits += $ecueCredit;
                                         
                                         if ($ecue['note'] !== null && floatval($ecue['note']) >= 10) {
                                             $creditsValides += $ecueCredit;
                                         }
                                     }
                                 }
                                 
                                 $estValidee = ($totalCredits > 0 && $creditsValides == $totalCredits);
                                 // Si toujours pas de moyenne après calcul ECUE, c'est une UE sans notes
                                 if ($totalCredits == 0) {
                                     $estValidee = false;
                                     $totalCredits = $ue['info']['nombre_credits'] ?? 0;
                                 }
                             } else {
                                 // Si moyenne est présente, utiliser les crédits de l'UE
                                 $creditsValides = $estValidee ? ($ue['info']['nombre_credits'] ?? 0) : 0;
                             }
                             
                             // Vérifier si une UE avec ce code existe déjà dans TOUS les semestres du système
                             // Si oui, comparer et prendre la meilleure note
                             $ueExistDansSysteme = false;
                             foreach ($resultatsSysteme as &$semestresIterator) {
                                 foreach ($semestresIterator['ues'] as &$ueExistante) {
                                     if ($ueExistante['code'] === $codeUE) {
                                         // Comparer les notes et prendre la meilleure
                                         if ($estValidee || 
                                             ($moyenneUE !== null && $moyenneUE !== '' && $ueExistante['moyenne'] !== null && $moyenneUE > $ueExistante['moyenne'])) {
                                             $ueExistante['designation'] = $ue['info']['designationUE'];
                                             $ueExistante['credits_total'] = $totalCredits;
                                             $ueExistante['credits_valides'] = $creditsValides;
                                             $ueExistante['moyenne'] = $moyenneUE ?? 0;
                                             $ueExistante['est_valide'] = $estValidee;
                                         }
                                         $ueExistDansSysteme = true;
                                         break 2; // Sort des deux boucles
                                     }
                                 }
                             }
                             
                             // Si l'UE n'existe pas encore dans le système, l'ajouter
                             if (!$ueExistDansSysteme) {
                                 $resultatsSysteme[$semestreKey]['ues'][] = [
                                     'code' => $codeUE,
                                     'designation' => $ue['info']['designationUE'],
                                     'credits_total' => $totalCredits,
                                     'credits_valides' => $creditsValides,
                                     'moyenne' => $moyenneUE ?? 0,
                                     'est_valide' => $estValidee
                                 ];
                             }
                         }
                     }
                 }
             }
             
             $dettes = $dette->getDettesEtudiant($matricule);
         }
             
             // Debug spécifique pour les dettes
             if (isset($_GET['debug']) && $_GET['debug'] == '1') {
                echo "<div class='alert alert-warning'><h6>🔍 Debug Dettes:</h6>";
                echo "Nombre de dettes récupérées: " . count($dettes) . "<br>";
                if (!empty($dettes)) {
                    echo "Première dette: <br>";
                    echo "<pre style='font-size: 10px;'>" . print_r($dettes[0], true) . "</pre>";
                    
                    $totalCreditsDebug = 0;
                    foreach ($dettes as $d) {
                        $creditsItem = intval($d['credits_ecue'] ?? 0);
                        $totalCreditsDebug += $creditsItem;
                        echo "Dette {$d['id_dette']}: {$creditsItem} crédits<br>";
                    }
                    echo "<strong>Total calculé: $totalCreditsDebug</strong>";
                } else {
                    echo "🔍 Vérification directe en base...";
                    try {
                        $db = Connexion::getInstance()->getPDO();
                        $check = $db->prepare("SELECT COUNT(*) as count FROM dette_etudiant WHERE matricule = :matricule");
                        $check->execute([':matricule' => $matricule]);
                        $result = $check->fetch();
                        echo "<br>Dettes en base pour $matricule: {$result['count']}";
                    } catch (Exception $e) {
                        echo "<br>Erreur vérification: " . $e->getMessage();
                    }
                }
                echo "</div>";
            }
        }
        
        // Récupération des résultats importés (grilles anciennes)
         $resultatsImportes = $grilleAncienne->getResultatsEtudiantImportes($matricule);
         
         // Enrichir les imports avec l'année académique dans le libellé
         $resultatsImportesEnriches = [];
         foreach ($resultatsImportes as $import) {
             $import['display_label'] = $import['session'] . ' - ' . $import['annee_academique'];
             $resultatsImportesEnriches[] = $import;
         }
         
         // Fusionner les UE importées en prenant le meilleur résultat si une UE apparaît dans plusieurs imports
         $resultatsImportesFusionnes = [];
         $uesParCode = []; // Tracker les UE par code pour fusion
         
         // Parcourir les imports dans l'ordre inverse (derniers d'abord)
         foreach (array_reverse($resultatsImportesEnriches) as $import) {
             foreach ($import['ues'] as $ue) {
                 $codeUE = $ue['code_ue'];
                 $estValidee = $ue['est_valide'] ?? false;
                 $moyenne = $ue['moyenne'] ?? null;
                 $totalCredits = $ue['credits_total'] ?? $ue['credits'] ?? 0;
                 $creditsValides = 0;
                 
                 // Si moyenne est vide/null, la traiter comme non validée
                 if ($moyenne === null || $moyenne === '' || $moyenne === 0) {
                     $estValidee = false;
                     $creditsValides = 0;
                 } else {
                     $creditsValides = $estValidee ? $totalCredits : 0;
                 }
                 
                 // Si cette UE n'a pas encore été rencontrée, ou si le nouveau résultat est meilleur
                 if (!isset($uesParCode[$codeUE]) || 
                     $estValidee || 
                     ($moyenne !== null && $moyenne !== '' && $uesParCode[$codeUE]['moyenne'] !== null && $moyenne > $uesParCode[$codeUE]['moyenne'])) {
                     
                     $uesParCode[$codeUE] = [
                         'code_ue' => $ue['code_ue'],
                         'designation_ue' => $ue['designation_ue'],
                         'credits' => $ue['credits'],
                         'credits_total' => $totalCredits,
                         'credits_valides' => $creditsValides,
                         'moyenne' => $moyenne ?? 0,
                         'est_valide' => $estValidee,
                         'mention' => $ue['mention'] ?? '',
                         'type_resultat' => $ue['type_resultat'] ?? 'Import',
                         'import_source' => [
                             'annee_academique' => $import['annee_academique'],
                             'session' => $import['session'],
                             'date_import' => $import['date_import'],
                             'fichier_origine' => $import['fichier_origine']
                         ]
                     ];
                 }
             }
         }
         
         // Créer un import consolidé avec toutes les UE fusionnées
         if (!empty($uesParCode)) {
             $resultatsImportesFusionnes[] = [
                 'import_id' => 0,
                 'annee_academique' => 'Consolidé',
                 'session' => 'Tous les imports',
                 'semestre' => '',
                 'promotion' => '',
                 'date_import' => date('Y-m-d H:i:s'),
                 'fichier_origine' => 'Fusion de plusieurs grilles anciennes',
                 'ues' => array_values($uesParCode),
                 'is_consolidated' => true
             ];
         }
         
         // Utiliser les imports enrichis pour l'affichage
         $resultatsImportes = $resultatsImportesEnriches;
        
        // Synthèse des crédits (utiliser les données fusionnées pour la cohérence)
        $syntheseCredits = calculerSyntheseCredits($resultatsSysteme, $resultatsImportesFusionnes, $dettes);
        
        // Debug temporaire - à supprimer après résolution
        if (isset($_GET['debug']) && $_GET['debug'] == '1') {
            echo "<div class='alert alert-info'><h5>🐛 Debug Info:</h5>";
            echo "<strong>Résultats système:</strong> " . count($resultatsSysteme) . " semestre(s)<br>";
            echo "<strong>Résultats importés:</strong> " . count($resultatsImportes) . " import(s)<br>";
            echo "<strong>Dettes:</strong> " . count($dettes) . " dette(s)<br>";
            
            if (!empty($dettes)) {
                echo "<h6>📋 Détail des dettes:</h6>";
                echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
                echo "<tr><th>ID</th><th>ECUE</th><th>Credits</th><th>Note</th><th>Statut</th></tr>";
                foreach ($dettes as $d) {
                    echo "<tr>";
                    echo "<td>" . ($d['id_dette'] ?? 'N/A') . "</td>";
                    echo "<td>" . htmlspecialchars($d['designationECUE'] ?? 'N/A') . "</td>";
                    echo "<td>" . ($d['credits_ecue'] ?? 'N/A') . "</td>";
                    echo "<td>" . ($d['note_obtenue'] ?? 'N/A') . "</td>";
                    echo "<td>" . ($d['statut'] ?? 'N/A') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<h6>⚠️ Aucune dette trouvée</h6>";
            }
            
            echo "<h6>📊 Synthèse calculée:</h6>";
            echo "<pre>" . print_r($syntheseCredits, true) . "</pre>";
            
            // Détail du calcul des dettes
            echo "<h6>📉 Détail calcul dettes:</h6>";
            echo "<ul>";
            echo "<li>Système - UEs non validées: " . ($syntheseCredits['details']['systeme_dettes'] ?? 0) . " crédits</li>";
            echo "<li>Imports - UEs non validées: " . ($syntheseCredits['details']['import_dettes'] ?? 0) . " crédits</li>";
            echo "<li>Total crédits: " . $syntheseCredits['credits_total'] . "</li>";
            echo "<li>Crédits validés: " . $syntheseCredits['credits_valides'] . "</li>";
            echo "<li><strong>Crédits en dette (calculé): " . $syntheseCredits['credits_dettes'] . "</strong></li>";
            echo "</ul>";
            echo "</div>";
            }
            }

            // Récupération des listes pour les filtres
$annees = $universite->getAllAcademicYears();
$promotions = [];

// Si une année est sélectionnée, récupérer ses promotions
if (!empty($annee_id)) {
    $promotions = $universite->getPromotionsByYear($annee_id);
}
?>

<!-- Le début du HTML et les styles -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>Parcours Étudiant</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">LMD</li>
                <li class="breadcrumb-item active">Parcours Étudiant</li>
            </ol>
        </nav>
    </div>

    <style>
        .parcours-section {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }
        
        .section-header {
            background: linear-gradient(135deg, #6f42c1 0%, #007bff 100%);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem 0.375rem 0 0;
        }
        
        .credit-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        
        .dette-highlight {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .valide-highlight {
            background-color: #d1e7dd;
            border-left: 4px solid #198754;
        }
        
        .import-highlight {
            background-color: #e7f3ff;
            border-left: 4px solid #0d6efd;
        }
        
        .synthese-card {
            background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            color: white;
        }
        
        .progress-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            position: relative;
            margin: 0 auto;
        }
        
        .search-container {
            position: relative;
        }
        
        #suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1000;
            background: white;
            border: 1px solid #ccc;
            border-top: none;
            border-radius: 0 0 0.375rem 0.375rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        
        #suggestions .dropdown-item {
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        #suggestions .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        
        #suggestions .dropdown-item:last-child {
            border-bottom: none;
        }
        
        .form-control:focus + #suggestions,
        #suggestions:hover {
            display: block !important;
        }
        
        /* Timeline styles */
        .timeline {
            position: relative;
            padding: 2rem 0;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 2px;
            height: 100%;
            background: linear-gradient(180deg, #007bff 0%, #6f42c1 100%);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 2rem;
            width: 45%;
        }
        
        .timeline-left {
            left: 0;
            text-align: right;
        }
        
        .timeline-right {
            right: 0;
            left: auto;
            text-align: left;
        }
        
        .timeline-marker {
            position: absolute;
            top: 1rem;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .timeline-left .timeline-marker {
            right: -70px;
        }
        
        .timeline-right .timeline-marker {
            left: -70px;
        }
        
        .timeline-content {
            max-width: 100%;
        }
        
        @media (max-width: 768px) {
            .timeline::before {
                left: 30px;
            }
            
            .timeline-item {
                width: calc(100% - 80px);
                margin-left: 80px;
                text-align: left !important;
            }
            
            .timeline-left .timeline-marker,
            .timeline-right .timeline-marker {
                left: -70px;
                right: auto;
            }
        }
        
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
            .timeline::before { display: none; }
            .timeline-marker { display: none; }
        }
    </style>

    <section class="section">
        <!-- Formulaire de recherche -->
        <div class="row no-print mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-search"></i> Rechercher un Parcours Étudiant</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3" id="searchForm">
                            <div class="col-md-3">
                                 <label class="form-label">Année académique <small>(optionnel)</small></label>
                                 <select class="form-select" name="annee_id" id="annee_id" onchange="loadPromotions()">
                                     <option value="">Toutes les années</option>
                                     <?php foreach ($annees as $annee): ?>
                                         <option value="<?= $annee['idannee_acad'] ?>" <?= $annee['idannee_acad'] == $annee_id ? 'selected' : '' ?>>
                                             <?= htmlspecialchars($annee['designation']) ?>
                                         </option>
                                     <?php endforeach; ?>
                                 </select>
                             </div>
                             <div class="col-md-3">
                                 <label class="form-label">Promotion <small>(optionnel)</small></label>
                                 <select class="form-select" name="promotion_id" id="promotion_id">
                                     <option value="">Toutes les promotions</option>
                                     <?php foreach ($promotions as $promo): ?>
                                         <option value="<?= $promo['idpromotion'] ?>" <?= $promo['idpromotion'] == $promotion_id ? 'selected' : '' ?>>
                                             <?= htmlspecialchars($promo['designationPromotion']) ?>
                                         </option>
                                     <?php endforeach; ?>
                                 </select>
                             </div>
                            <div class="col-md-4">
                                <label class="form-label">Matricule ou Nom de l'étudiant *</label>
                                <div class="search-container">
                                    <input type="text" class="form-control" name="matricule" id="searchInput" 
                                           value="<?= htmlspecialchars($matricule) ?>" 
                                           placeholder="Ex: 20U001 ou DUPONT Jean" required
                                           autocomplete="off">
                                    <div id="suggestions" class="dropdown-menu" style="width: 100%; max-height: 200px; overflow-y: auto;"></div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100" id="searchBtn" disabled>
                                    <i class="bi bi-search"></i> Rechercher
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($etudiant): ?>
            <!-- Informations de l'étudiant -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h4 class="mb-0">
                                        <i class="bi bi-person-badge"></i>
                                        <?= htmlspecialchars($etudiant['noms']) ?>
                                    </h4>
                                    <small>Matricule: <?= htmlspecialchars($etudiant['matricule']) ?></small>
                                </div>
                                <div class="col-auto no-print">
                                    <a href="?<?= http_build_query(array_merge($_GET, ['debug' => '1'])) ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-bug"></i> Debug
                                    </a>
                                    <button class="btn btn-light btn-sm" onclick="window.print()">
                                        <i class="bi bi-printer"></i> Imprimer
                                    </button>
                                    <button class="btn btn-light btn-sm" onclick="exporterPDF()">
                                        <i class="bi bi-file-pdf"></i> PDF
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Email:</strong> <?= htmlspecialchars($etudiant['adressemail'] ?? 'Non renseigné') ?></p>
                                    <p><strong>Téléphone:</strong> <?= htmlspecialchars($etudiant['telephone'] ?? 'Non renseigné') ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Lieu de naissance:</strong> <?= htmlspecialchars($etudiant['lieuNaissance'] ?? 'Non renseigné') ?></p>
                                    <p><strong>Date de naissance:</strong> <?= htmlspecialchars($etudiant['dateNaissance'] ?? 'Non renseigné') ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Synthèse des crédits -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card synthese-card">
                        <div class="card-header">
                            <h5 class="mb-0 text-white"><i class="bi bi-award"></i> Synthèse des Crédits</h5>
                        </div>
                        <div class="card-body text-white">
                            <!-- Synthèse principale -->
                            <div class="row text-center mb-3">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <h3><?= $syntheseCredits['credits_valides'] ?? 0 ?></h3>
                                        <p class="mb-0">Crédits Validés</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <h3><?= $syntheseCredits['credits_dettes'] ?? 0 ?></h3>
                                        <p class="mb-0">Crédits en Dette</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <h3><?= $syntheseCredits['credits_total'] ?? 0 ?></h3>
                                        <p class="mb-0">Total Crédits</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <div class="progress-circle bg-white text-dark d-flex align-items-center justify-content-center">
                                            <strong><?= $syntheseCredits['pourcentage'] ?? 0 ?>%</strong>
                                        </div>
                                        <p class="mb-0 mt-2">Progression</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Détail par source -->
                            <?php if (!empty($syntheseCredits['details'])): ?>
                                <hr class="border-light">
                                <div class="row text-center">
                                    <div class="col-md-4">
                                        <div class="border-end border-light pe-3">
                                            <h6><i class="bi bi-laptop"></i> Système Actuel</h6>
                                            <p class="mb-1"><strong><?= $syntheseCredits['details']['systeme_valides'] ?? 0 ?></strong> crédits validés</p>
                                            <p class="mb-0"><small><?= $syntheseCredits['details']['systeme_total'] ?? 0 ?> crédits au total</small></p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border-end border-light pe-3">
                                            <h6><i class="bi bi-upload"></i> Grilles Importées</h6>
                                            <p class="mb-1"><strong><?= $syntheseCredits['details']['import_valides'] ?? 0 ?></strong> crédits validés</p>
                                            <p class="mb-0"><small><?= count($resultatsImportes) ?> import(s) - <?= $syntheseCredits['details']['import_total'] ?? 0 ?> crédits</small></p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div>
                                            <h6><i class="bi bi-exclamation-triangle"></i> Dettes</h6>
                                            <p class="mb-1"><strong><?= count($dettes) ?></strong> ECUE(s) en dette</p>
                                            <p class="mb-0"><small><?= $syntheseCredits['credits_dettes'] ?? 0 ?> crédits concernés</small></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Résultats du système actuel -->
            <?php if (!empty($resultatsSysteme)): ?>
                <div class="parcours-section">
                    <div class="section-header">
                        <h5 class="mb-0"><i class="bi bi-laptop"></i> Résultats du Système Actuel</h5>
                    </div>
                    <div class="p-3">
                        <?php foreach ($resultatsSysteme as $semestre => $semData): ?>
                            <div class="mb-4">
                                <h6 class="text-primary border-bottom pb-2">
                                    <?= htmlspecialchars($semestre) ?>
                                    <span class="badge bg-primary ms-2"><?= count($semData['ues']) ?> UE(s)</span>
                                </h6>
                                
                                <?php foreach ($semData['ues'] as $ue): ?>
                                    <div class="card mb-2 <?= ($ue['est_valide'] ?? false) ? 'valide-highlight' : 'dette-highlight' ?>">
                                        <div class="card-body py-2">
                                            <div class="row align-items-center">
                                                <div class="col-md-5">
                                                    <strong><?= htmlspecialchars($ue['designation'] ?? 'N/A') ?></strong>
                                                    <br><small class="text-muted"><?= htmlspecialchars($ue['code'] ?? 'N/A') ?></small>
                                                </div>
                                                <div class="col-md-2 text-center">
                                                    <span class="badge <?= ($ue['est_valide'] ?? false) ? 'bg-success' : 'bg-warning' ?>">
                                                        <?= number_format($ue['moyenne'] ?? 0, 2) ?>/20
                                                    </span>
                                                </div>
                                                <div class="col-md-2 text-center">
                                                    <span class="credit-badge badge bg-info">
                                                        <?= ($ue['credits_valides'] ?? 0) ?>/<?= ($ue['credits_total'] ?? 0) ?> crédits
                                                    </span>
                                                </div>
                                                <div class="col-md-2 text-center">
                                                    <span class="badge <?= ($ue['est_valide'] ?? false) ? 'bg-success' : 'bg-danger' ?>">
                                                        <?= ($ue['est_valide'] ?? false) ? 'Validé' : 'Non validé' ?>
                                                    </span>
                                                </div>
                                                <div class="col-md-1 text-end">
                                                    <i class="bi <?= ($ue['est_valide'] ?? false) ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-warning' ?>"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Résultats importés -->
             <?php if (!empty($resultatsImportesFusionnes)): ?>
                 <div class="parcours-section">
                     <div class="section-header">
                         <h5 class="mb-0">
                             <i class="bi bi-upload"></i> Résultats Importés (Grilles Anciennes)
                             <span class="badge bg-light text-dark ms-2"><?= count($resultatsImportes) ?> import(s)</span>
                         </h5>
                     </div>
                     <div class="p-3">
                         <?php foreach ($resultatsImportesFusionnes as $import): ?>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                    <h6 class="text-info mb-0">
                                        <i class="bi bi-calendar-event"></i>
                                        <?= htmlspecialchars($import['session']) ?> - <?= htmlspecialchars($import['annee_academique']) ?>
                                        <?php if (!empty($import['semestre'])): ?>
                                            <span class="badge bg-info ms-1"><?= htmlspecialchars($import['semestre']) ?></span>
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted">
                                        <i class="bi bi-upload"></i> Importé le <?= date('d/m/Y à H:i', strtotime($import['date_import'])) ?>
                                        <br><i class="bi bi-file-text"></i> <?= basename($import['fichier_origine']) ?>
                                    </small>
                                </div>
                                
                                <?php 
                                $totalCreditsImport = array_sum(array_column($import['ues'], 'credits_total'));
                                $creditsValidesImport = array_sum(array_filter(array_map(function($ue) {
                                    return $ue['est_valide'] ? $ue['credits_valides'] : 0;
                                }, $import['ues'])));
                                ?>
                                
                                <div class="alert alert-info py-2 mb-3">
                                    <div class="row text-center">
                                        <div class="col-md-3">
                                            <strong><?= count($import['ues']) ?></strong><br>
                                            <small>UE(s) importée(s)</small>
                                        </div>
                                        <div class="col-md-3">
                                            <strong><?= $creditsValidesImport ?></strong><br>
                                            <small>Crédits validés</small>
                                        </div>
                                        <div class="col-md-3">
                                            <strong><?= $totalCreditsImport ?></strong><br>
                                            <small>Total crédits</small>
                                        </div>
                                        <div class="col-md-3">
                                            <strong><?= $totalCreditsImport > 0 ? round(($creditsValidesImport / $totalCreditsImport) * 100, 1) : 0 ?>%</strong><br>
                                            <small>Taux de réussite</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php foreach ($import['ues'] as $ue): ?>
                                    <div class="card mb-2 import-highlight">
                                        <div class="card-body py-2">
                                            <div class="row align-items-center">
                                                <div class="col-md-4">
                                                    <strong><?= htmlspecialchars($ue['designation_ue']) ?></strong>
                                                    <br><small class="text-muted"><?= htmlspecialchars($ue['code_ue'] ?? '') ?></small>
                                                    <?php if (!empty($ue['import_source']['annee_academique'])): ?>
                                                        <br><small class="badge bg-light text-dark"><?= htmlspecialchars($ue['import_source']['annee_academique']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-2 text-center">
                                                    <span class="badge <?= $ue['est_valide'] ? 'bg-success' : 'bg-warning' ?>">
                                                        <?= number_format($ue['moyenne'] ?? 0, 2) ?>/20
                                                    </span>
                                                </div>
                                                <div class="col-md-2 text-center">
                                                    <span class="credit-badge badge bg-info">
                                                        <?= $ue['credits_valides'] ?? 0 ?>/<?= $ue['credits_total'] ?? $ue['credits'] ?? 0 ?> crédits
                                                    </span>
                                                </div>
                                                <div class="col-md-2 text-center">
                                                    <?php if (!empty($ue['mention'])): ?>
                                                        <span class="badge bg-primary"><?= htmlspecialchars($ue['mention']) ?></span>
                                                    <?php else: ?>
                                                        <span class="badge <?= $ue['est_valide'] ? 'bg-success' : 'bg-secondary' ?>">
                                                            <?= $ue['est_valide'] ? 'Validé' : 'Non validé' ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-1 text-center">
                                                    <?= htmlspecialchars($ue['type_resultat'] ?? 'Import') ?>
                                                </div>
                                                <div class="col-md-1 text-end">
                                                    <i class="bi bi-upload text-info" title="Résultat importé"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Timeline du parcours (chronologique) -->
            <?php if (!empty($resultatsSysteme) || !empty($resultatsImportes) || !empty($dettes)): ?>
                <div class="parcours-section">
                    <div class="section-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Timeline du Parcours Académique</h5>
                    </div>
                    <div class="p-3">
                        <?php
                        // Créer une timeline combinée
                        $timelineEvents = [];
                        
                        // Ajouter les imports (plus anciens)
                        foreach ($resultatsImportes as $import) {
                            $timelineEvents[] = [
                                'date' => $import['date_import'],
                                'type' => 'import',
                                'title' => $import['session'] . ' - ' . $import['annee_academique'],
                                'subtitle' => count($import['ues']) . ' UE(s) importée(s)',
                                'data' => $import,
                                'icon' => 'bi-upload',
                                'color' => 'info'
                            ];
                        }
                        
                        // Ajouter le système actuel (estimation basée sur l'année académique)
                        if (!empty($resultatsSysteme)) {
                            $timelineEvents[] = [
                                'date' => date('Y-m-d H:i:s'), // Date actuelle comme estimation
                                'type' => 'systeme',
                                'title' => 'Résultats Système Actuel',
                                'subtitle' => count($resultatsSysteme) . ' semestre(s) évalué(s)',
                                'data' => $resultatsSysteme,
                                'icon' => 'bi-laptop',
                                'color' => 'primary'
                            ];
                        }
                        
                        // Trier par date
                        usort($timelineEvents, function($a, $b) {
                            return strtotime($a['date']) - strtotime($b['date']);
                        });
                        ?>
                        
                        <div class="timeline">
                            <?php foreach ($timelineEvents as $index => $event): ?>
                                <div class="timeline-item <?= $index % 2 == 0 ? 'timeline-left' : 'timeline-right' ?>">
                                    <div class="timeline-marker bg-<?= $event['color'] ?>">
                                        <i class="bi <?= $event['icon'] ?> text-white"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="card">
                                            <div class="card-header bg-<?= $event['color'] ?> text-white">
                                                <h6 class="mb-0"><?= htmlspecialchars($event['title']) ?></h6>
                                                <small><?= $event['subtitle'] ?></small>
                                            </div>
                                            <div class="card-body">
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar"></i> <?= date('d/m/Y à H:i', strtotime($event['date'])) ?>
                                                </small>
                                                
                                                <?php if ($event['type'] == 'import'): ?>
                                                    <p class="mt-2 mb-1"><strong>Fichier:</strong> <?= basename($event['data']['fichier_origine']) ?></p>
                                                    <div class="row text-center">
                                                        <?php 
                                                        $importCredits = array_sum(array_column($event['data']['ues'], 'credits_total'));
                                                        $importValides = array_sum(array_filter(array_map(function($ue) {
                                                            return $ue['est_valide'] ? ($ue['credits_valides'] ?? $ue['credits']) : 0;
                                                        }, $event['data']['ues'])));
                                                        ?>
                                                        <div class="col-4">
                                                            <strong><?= count($event['data']['ues']) ?></strong><br>
                                                            <small>UE</small>
                                                        </div>
                                                        <div class="col-4">
                                                            <strong><?= $importValides ?></strong><br>
                                                            <small>Crédits validés</small>
                                                        </div>
                                                        <div class="col-4">
                                                            <strong><?= $importCredits > 0 ? round(($importValides / $importCredits) * 100, 1) : 0 ?>%</strong><br>
                                                            <small>Réussite</small>
                                                        </div>
                                                    </div>
                                                <?php elseif ($event['type'] == 'systeme'): ?>
                                                    <div class="row text-center mt-2">
                                                        <div class="col-4">
                                                            <strong><?= array_sum(array_map('count', array_column($event['data'], 'ues'))) ?></strong><br>
                                                            <small>UE</small>
                                                        </div>
                                                        <div class="col-4">
                                                            <strong><?= $syntheseCredits['details']['systeme_valides'] ?? 0 ?></strong><br>
                                                            <small>Crédits validés</small>
                                                        </div>
                                                        <div class="col-4">
                                                            <strong><?= count($dettes) ?></strong><br>
                                                            <small>Dette(s)</small>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Dettes en cours -->
            <?php if (!empty($dettes)): ?>
                <div class="parcours-section">
                    <div class="section-header">
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Dettes en Cours</h5>
                    </div>
                    <div class="p-3">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ECUE</th>
                                        <th>UE</th>
                                        <th>Note Obtenue</th>
                                        <th>Crédits</th>
                                        <th>Statut</th>
                                        <th>Note Rachat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dettes as $dette_item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($dette_item['designationECUE']) ?></td>
                                            <td><?= htmlspecialchars($dette_item['designationUE']) ?></td>
                                            <td>
                                                <span class="badge bg-danger"><?= number_format($dette_item['note_obtenue'], 2) ?>/20</span>
                                            </td>
                                            <td>
                                                <span class="credit-badge badge bg-warning"><?= $dette_item['credits_ecue'] ?></span>
                                            </td>
                                            <td>
                                                <span class="badge <?= $dette_item['statut'] == 'Validée' ? 'bg-success' : 'bg-warning' ?>">
                                                    <?= htmlspecialchars($dette_item['statut']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($dette_item['note_rachat']): ?>
                                                    <span class="badge bg-success"><?= number_format($dette_item['note_rachat'], 2) ?>/20</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif (!empty($matricule)): ?>
            <!-- Aucun résultat trouvé -->
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-warning text-center">
                        <i class="bi bi-exclamation-triangle fs-1"></i>
                        <h4 class="mt-3">Aucun résultat trouvé</h4>
                        <p>Aucun étudiant trouvé avec le matricule "<?= htmlspecialchars($matricule) ?>"</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>

<script>
    // Variables globales
    let searchTimeout;
    let selectedStudent = null;

    // Charger les promotions selon l'année sélectionnée
    function loadPromotions() {
        const anneeId = document.getElementById('annee_id').value;
        const promotionSelect = document.getElementById('promotion_id');
        const searchBtn = document.getElementById('searchBtn');
        
        // Reset promotion et désactiver le bouton
        promotionSelect.innerHTML = '<option value="">Chargement...</option>';
        searchBtn.disabled = true;
        
        if (!anneeId) {
            promotionSelect.innerHTML = '<option value="">Choisir d\'abord une année</option>';
            checkFormValidity();
            return;
        }
        
        // Requête AJAX pour charger les promotions
        fetch(`controller/ajax_get_promotions.php?annee_id=${anneeId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                
                promotionSelect.innerHTML = '<option value="">Sélectionner une promotion</option>';
                
                if (data.success && data.promotions && data.promotions.length > 0) {
                    data.promotions.forEach(promotion => {
                        const option = document.createElement('option');
                        option.value = promotion.idpromotion;
                        option.textContent = promotion.designationPromotion;
                        promotionSelect.appendChild(option);
                    });
                } else {
                    promotionSelect.innerHTML = '<option value="">Aucune promotion trouvée</option>';
                }
                
                // Réactiver les champs après le chargement
                checkFormValidity();
            })
            .catch(error => {
                console.error('Erreur:', error);
                promotionSelect.innerHTML = '<option value="">Erreur de chargement</option>';
            });
    }

    // Recherche d'étudiants avec suggestions
    function searchStudents() {
        const query = document.getElementById('searchInput').value.trim();
        const promotionId = document.getElementById('promotion_id').value;
        const anneeId = document.getElementById('annee_id').value;
        const suggestionsDiv = document.getElementById('suggestions');
        
        if (query.length < 2 || !promotionId || !anneeId) {
            suggestionsDiv.classList.remove('show');
            return;
        }
        
        // Requête AJAX pour rechercher des étudiants
        fetch(`controller/ajax_search_students.php?q=${encodeURIComponent(query)}&promotion_id=${promotionId}&annee_id=${anneeId}`)
            .then(response => response.json())
            .then(data => {
                suggestionsDiv.innerHTML = '';
                
                if (data.success && data.students.length > 0) {
                    data.students.forEach(student => {
                        const item = document.createElement('a');
                        item.className = 'dropdown-item';
                        item.href = '#';
                        item.innerHTML = `
                            <div>
                                <strong>${student.matricule}</strong> - ${student.noms}
                                <br><small class="text-muted">${student.promotion}</small>
                            </div>
                        `;
                        
                        item.addEventListener('click', (e) => {
                            e.preventDefault();
                            selectStudent(student);
                        });
                        
                        suggestionsDiv.appendChild(item);
                    });
                    
                    suggestionsDiv.classList.add('show');
                } else {
                    suggestionsDiv.classList.remove('show');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                suggestionsDiv.classList.remove('show');
            });
    }

    // Sélectionner un étudiant
    function selectStudent(student) {
        selectedStudent = student;
        document.getElementById('searchInput').value = student.matricule;
        document.getElementById('suggestions').classList.remove('show');
        checkFormValidity();
    }

    // Vérifier la validité du formulaire (année et promotion sont optionnelles)
     function checkFormValidity() {
         const matricule = document.getElementById('searchInput').value.trim();
         const searchBtn = document.getElementById('searchBtn');
         
         // Seul le matricule est obligatoire
         searchBtn.disabled = !(matricule.length >= 2);
     }

    // Export PDF
    function exporterPDF() {
        const matricule = '<?= $matricule ?>';
        const annee_id = '<?= $annee_id ?>';
        const promotion_id = '<?= $promotion_id ?>';
        
        window.open(`controller/export_parcours_pdf.php?matricule=${matricule}&annee_id=${annee_id}&promotion_id=${promotion_id}`, '_blank');
    }

    // Événements
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const suggestionsDiv = document.getElementById('suggestions');
        
        // Recherche avec délai
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(searchStudents, 300);
            checkFormValidity();
        });
        
        // Cacher les suggestions quand on clique ailleurs
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                suggestionsDiv.classList.remove('show');
            }
        });
        
        // Vérifier la validité au chargement
        checkFormValidity();
        
        // Événement sur le changement d'année
        const anneeSelect = document.getElementById('annee_id');
        anneeSelect.addEventListener('change', function() {
            loadPromotions();
        });
        
        // Si une année est déjà sélectionnée, charger ses promotions
        if (anneeSelect.value) {
            loadPromotions();
        }
        });

    // Auto-refresh des données toutes les 30 secondes si on est en mode visualisation
    <?php if (!empty($matricule)): ?>
    setInterval(function() {
        // Optionnel: actualiser automatiquement
    }, 30000);
    <?php endif; ?>
</script>

<?php
/**
 * Calculer la synthèse des crédits pour un étudiant
 * Prend en compte les dettes du système ET des grilles importées
 */
function calculerSyntheseCredits($resultatsSysteme, $resultatsImportes, $dettes) {
    $creditsValides = 0;
    $creditsDettes = 0;
    $creditsTotal = 0;
    
    // Détails par source
    $systemeValides = 0;
    $systemeTotal = 0;
    $systemeDettes = 0;
    $importValides = 0;
    $importTotal = 0;
    $importDettes = 0;
    
    // Crédits du système actuel
    foreach ($resultatsSysteme as $semestre) {
        foreach ($semestre['ues'] as $ue) {
            $total = intval($ue['credits_total'] ?? 0);
            $valides = intval($ue['credits_valides'] ?? 0);
            
            $systemeTotal += $total;
             $creditsTotal += $total;
             
             if ($ue['est_valide'] ?? false) {
                 $systemeValides += $valides;
                 $creditsValides += $valides;
             } else {
                 // UE non validée = dette du système
                 $systemeDettes += $total;
             }
        }
    }
    
    // Crédits des imports (grilles anciennes) - éviter les doublons
    foreach ($resultatsImportes as $import) {
        foreach ($import['ues'] as $ue) {
            // Priorité à credits_total, puis credits, puis 0 - convertir en entier
            $total = intval($ue['credits_total'] ?? $ue['credits'] ?? 0);
            
            // Ne pas ajouter les crédits qui pourraient déjà être dans le système actuel
            // On assume que les imports sont des années antérieures différentes
            $importTotal += $total;
            $creditsTotal += $total;
            
            if ($ue['est_valide']) {
                // Pour les imports, utiliser credits_valides si disponible, sinon le total si validé
                $valides = intval($ue['credits_valides'] ?? $total);
                $importValides += $valides;
                $creditsValides += $valides;
            } else {
                // UE non validée dans les imports = dette importée
                $importDettes += $total;
            }
        }
    }
    
    // Crédits en dette = UEs non validées du système + UEs non validées des imports
    // Les dettes de la table dette_etudiant peuvent être des doublons, mais on les prend en compte si présentes
    $creditsDettesTable = 0;
    foreach ($dettes as $dette) {
        $creditsDettesTable += intval($dette['credits_ecue'] ?? 0);
    }
    
    // Total des dettes = dettes calculées (système + imports)
    // Note: on évite de doubler les dettes en prenant le max entre les dettes calculées et celles de la table
    $creditsDettesCalculees = $systemeDettes + $importDettes;
    $creditsDettes = max($creditsDettesCalculees, $creditsDettesTable);
    
    // Alternative: simplement crédits total - crédits validés si c'est plus fiable
    $creditsDettesSimple = $creditsTotal - $creditsValides;
    if ($creditsDettes == 0 && $creditsDettesSimple > 0) {
        $creditsDettes = $creditsDettesSimple;
    }
    
    $pourcentage = $creditsTotal > 0 ? round(($creditsValides / $creditsTotal) * 100, 1) : 0;
    
    return [
        'credits_valides' => $creditsValides,
        'credits_dettes' => $creditsDettes,
        'credits_total' => $creditsTotal,
        'pourcentage' => $pourcentage,
        'details' => [
            'systeme_valides' => $systemeValides,
            'systeme_total' => $systemeTotal,
            'systeme_dettes' => $systemeDettes,
            'import_valides' => $importValides,
            'import_total' => $importTotal,
            'import_dettes' => $importDettes,
            'nb_imports' => count($resultatsImportes),
            'nb_dettes' => count($dettes)
        ]
    ];
}

// Inclusion du footer
include "./views/include/footer.php";
?>
