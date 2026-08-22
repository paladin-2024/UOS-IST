<?php
// Vérification des droits d'accès
if (!isset($_SESSION['idRole']) || ($_SESSION['idRole'] != 1 && $_SESSION['idRole'] != 2)) {
    header('Location: index.php');
    exit();
}

// Inclusion du header
include "./views/include/header.php";

$universite = new Universite();
$dette = new Dette();
$grilleAncienne = new GrilleAncienne();
$etudiantModel = new Etudiant();

// Récupération des paramètres
$annee_id = $_GET['annee_id'] ?? '';
$promotion_id = $_GET['promotion_id'] ?? '';

$annees = [];
$promotions = [];
$dettesEtudiants = [];
$stats = [];

// Récupération des années académiques
$annees = $universite->getAllAcademicYears();

// Si une année est sélectionnée, récupérer ses promotions
if (!empty($annee_id)) {
    $promotions = $universite->getPromotionsByYear($annee_id);
}

// Si promotion et année sont sélectionnées, récupérer les stats seulement
if (!empty($promotion_id) && !empty($annee_id)) {
    try {
        $db = Connexion::getInstance()->getPDO();
        $deliberation = new Deliberation();
        $toutes_sessions = $universite->getAllSessions();
        
        // Récupérer TOUS les étudiants pour les stats globales
         $sql = "SELECT DISTINCT e.matricule, e.noms
                 FROM etudiant e
                 WHERE e.promotion_idpromotion = :promotion
                     AND e.annee_acad_idannee_acad = :annee
                     AND e.est_actif = 1
                 ORDER BY e.noms ASC";
         
         $stmt = $db->prepare($sql);
         $stmt->execute([
             ':promotion' => $promotion_id,
             ':annee' => $annee_id
         ]);
         $tousLesEtudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
         
         // Limiter à 100 pour la boucle de calcul stats (sinon timeout)
         $etudiantsATraiter = array_slice($tousLesEtudiants, 0, 100);
         
         // Calculer les stats globales rapidement
         $totalDettesGlobal = 0;
         $totalCreditsGlobal = 0;
         $totalEtudiantsAvecDettes = 0;
         $stats_systeme_dettes = 0;
         $stats_import_dettes = 0;
         
         foreach ($etudiantsATraiter as $etudiant) {
            $matricule = $etudiant['matricule'];
            
            // Récupérer les notes
            $notesParSession = [];
            foreach ($toutes_sessions as $session) {
                $sessionId = $session['idsession'];
                $notesParSession[$sessionId] = $deliberation->getNotesEtudiant($matricule, $sessionId, $annee_id);
            }
            
            // Construire les résultats système - MEILLEURE NOTE
            $resultatsSysteme = [];
            $meilleuresUEs = [];
            
            foreach ($toutes_sessions as $session) {
                $sessionId = $session['idsession'];
                if (!empty($notesParSession[$sessionId])) {
                    foreach ($notesParSession[$sessionId] as $semData) {
                        $semestreKey = 'Semestre ' . $semData['info']['numeroSemestre'];
                        
                        if (!empty($semData['ues'])) {
                            foreach ($semData['ues'] as $ue) {
                                $codeUE = $ue['info']['codeUE'] ?? '';
                                $estValidee = $ue['info']['est_validee'] ?? false;
                                $moyenne = $ue['info']['moyenne'] ?? null;
                                $totalCredits = $ue['info']['nombre_credits'] ?? 0;
                                $creditsValides = 0;
                                
                                // Si moyenne est vide/null, calculer à partir des ECUE
                                if ($moyenne === null || $moyenne === '' || $moyenne === 0) {
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
                                
                                if (!isset($meilleuresUEs[$codeUE]) || 
                                    $estValidee || 
                                    ($moyenne !== null && $moyenne !== '' && $moyenne > 0 && $moyenne > ($meilleuresUEs[$codeUE]['moyenne'] ?? 0))) {
                                    
                                    $meilleuresUEs[$codeUE] = [
                                        'code' => $codeUE,
                                        'designation' => $ue['info']['designationUE'] ?? '',
                                        'moyenne' => $moyenne ?? 0,
                                        'credits_total' => $totalCredits,
                                        'credits_valides' => $creditsValides,
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
            
            // Récupérer les résultats importés
            $resultatsImportesOriginaux = $grilleAncienne->getResultatsEtudiantImportes($matricule);
            
            // Fusionner les UE importées
            $uesParCode = [];
            foreach (array_reverse($resultatsImportesOriginaux) as $import) {
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
                            'mention' => $ue['mention'] ?? ''
                        ];
                    }
                }
            }
            
            $resultatsImportesFusionnes = [];
            if (!empty($uesParCode)) {
                $resultatsImportesFusionnes[] = [
                    'import_id' => 0,
                    'annee_academique' => 'Consolidé',
                    'session' => 'Tous les imports',
                    'ues' => array_values($uesParCode),
                    'is_consolidated' => true
                ];
            }
            
            // Calculer la synthèse
            $synthese = calculerSyntheseCredits($resultatsSysteme, $resultatsImportesFusionnes, []);
            
            // Accumuler les statistiques
            if ($synthese['credits_dettes'] > 0) {
                $totalEtudiantsAvecDettes++;
            }
            $totalDettesGlobal += $synthese['credits_dettes'];
            $totalCreditsGlobal += $synthese['credits_total'];
            $stats_systeme_dettes += $synthese['details']['systeme_dettes'];
            $stats_import_dettes += $synthese['details']['import_dettes'];
        }
        
        // Calculer les statistiques globales
        $stats = [
            'total_etudiants' => count($tousLesEtudiants),
            'etudiants_avec_dettes' => $totalEtudiantsAvecDettes,
            'total_credits_dettes' => $totalDettesGlobal,
            'total_credits' => $totalCreditsGlobal,
            'systeme_dettes' => $stats_systeme_dettes,
            'import_dettes' => $stats_import_dettes
        ];
        
        $stats['taux_etudiants_dettes'] = $stats['total_etudiants'] > 0 
            ? round(($stats['etudiants_avec_dettes'] / $stats['total_etudiants']) * 100, 1) 
            : 0;
        
        // Pour l'affichage, charger seulement les premiers étudiants (le reste via AJAX)
        $dettesEtudiants = [];
            
    } catch (Exception $e) {
        error_log("Erreur récupération dettes par promotion: " . $e->getMessage());
    }
}

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
    
    // Crédits des imports
    foreach ($resultatsImportes as $import) {
        foreach ($import['ues'] as $ue) {
            $total = intval($ue['credits_total'] ?? $ue['credits'] ?? 0);
            
            $importTotal += $total;
            $creditsTotal += $total;
            
            if ($ue['est_valide']) {
                $valides = intval($ue['credits_valides'] ?? $total);
                $importValides += $valides;
                $creditsValides += $valides;
            } else {
                // UE non validée dans les imports = dette importée
                $importDettes += $total;
            }
        }
    }
    
    // Total des dettes = dettes calculées (système + imports)
    $creditsDettesCalculees = $systemeDettes + $importDettes;
    $creditsDettes = max($creditsDettesCalculees, 0);
    
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
            'nb_imports' => count($resultatsImportes)
        ]
    ];
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Dettes par Promotion</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                <li class="breadcrumb-item">LMD</li>
                <li class="breadcrumb-item active">Dettes par Promotion</li>
            </ol>
        </nav>
    </div>

    <style>
        .filter-section {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
        }
        
        .section-header {
            background: linear-gradient(135deg, #6f42c1 0%, #007bff 100%);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem 0.375rem 0 0;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            color: white;
            border: none;
        }
        
        .stats-card .card-body {
            padding: 1.5rem;
        }
        
        .stats-card h6 {
            color: white;
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }
        
        .stats-card small {
            opacity: 0.9;
            font-size: 0.85rem;
        }
        
        .liste-dettes-table {
            background: white;
        }
        
        .dette-row-avec {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .dette-row-sans {
            background-color: #d1e7dd;
            border-left: 4px solid #198754;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table-sm td {
            padding: 0.5rem;
        }

        .badge-custom {
            padding: 0.4rem 0.6rem;
            font-size: 0.85rem;
        }
    </style>

    <section class="section">
        <div class="row">
            <!-- Filtres -->
            <div class="col-lg-12">
                <div class="filter-section">
                    <div class="section-header">
                        <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtres</h5>
                    </div>
                    <div class="p-3">
                        <form method="GET" action="" class="row g-3" id="filterForm">
                            <input type="hidden" name="view" value="lmd/rapports_dettes">
                            
                            <div class="col-md-4">
                                <label for="annee_id" class="form-label">Année académique</label>
                                <select name="annee_id" id="annee_id" class="form-select" required onchange="loadPromotions()">
                                    <option value="">Sélectionner une année</option>
                                    <?php foreach ($annees as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" <?= $annee_id == $annee['idannee_acad'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="promotion_id" class="form-label">Promotion</label>
                                <select name="promotion_id" id="promotion_id" class="form-select" required <?= !$annee_id ? 'disabled' : '' ?>>
                                    <option value="">Sélectionner une promotion</option>
                                    <?php foreach ($promotions as $promo): ?>
                                        <option value="<?= $promo['idpromotion'] ?>" <?= $promotion_id == $promo['idpromotion'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($promo['designationPromotion']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                 <label class="form-label">&nbsp;</label>
                                 <button type="submit" class="btn btn-primary w-100">
                                     <i class="bi bi-search"></i> Afficher
                                 </button>
                             </div>
                             <div class="col-md-2">
                                 <label class="form-label">&nbsp;</label>
                                 <?php if (!empty($stats)): ?>
                                     <button type="button" class="btn btn-danger w-100" onclick="exporterPDF()">
                                         <i class="bi bi-file-pdf"></i> PDF
                                     </button>
                                 <?php else: ?>
                                     <button type="button" class="btn btn-danger w-100" disabled>
                                         <i class="bi bi-file-pdf"></i> PDF
                                     </button>
                                 <?php endif; ?>
                             </div>
                        </form>
                    </div>
                </div>
            </div>

            <?php if (!empty($stats)): ?>
                <!-- Statistiques -->
                <div class="col-lg-12">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body">
                                    <h6><?= $stats['total_etudiants'] ?></h6>
                                    <small>Total étudiants</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body">
                                    <h6><?= $stats['etudiants_avec_dettes'] ?></h6>
                                    <small>Avec dettes (<?= $stats['taux_etudiants_dettes'] ?>%)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body">
                                    <h6><?= $stats['total_credits_dettes'] ?></h6>
                                    <small>Crédits en dette</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body">
                                    <h6><?= $stats['total_credits'] ?? 0 ?></h6>
                                    <small>Total crédits</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Source des dettes -->
                <div class="col-lg-12">
                    <div class="alert alert-info">
                        <strong><i class="bi bi-info-circle"></i> Sources des dettes:</strong><br>
                        <small>
                            Système: <strong><?= $stats['systeme_dettes'] ?></strong> crédits | 
                            Imports (UE non validées): <strong><?= $stats['import_dettes'] ?></strong> crédits | 
                            Total: <strong><?= $stats['total_credits_dettes'] ?></strong> crédits
                        </small>
                    </div>
                </div>

                <!-- Liste des dettes -->
                <div class="col-lg-12">
                    <div class="card liste-dettes-table">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-list"></i> Étudiants et leurs dettes</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($stats)): ?>
                                <!-- Liste des dettes - Toujours présent pour infinite scroll -->
                                <div class="table-responsive" id="tableContainer">
                                    <table class="table table-sm table-hover" id="dettesTable">
                                         <thead class="table-dark">
                                              <tr>
                                                  <th>Matricule</th>
                                                  <th>Nom</th>
                                                  <th class="text-center">Crédits Dettes</th>
                                                  <th class="text-center">Crédits Total</th>
                                                  <th class="text-center">Crédits Validés</th>
                                                  <th class="text-center">Validation %</th>
                                                  <th class="text-center">Système</th>
                                                  <th class="text-center">Imports</th>
                                                  <th class="text-center">Statut</th>
                                                  <th class="text-center">Actions</th>
                                              </tr>
                                          </thead>
                                          <tbody id="dettesBody">
                                          </tbody>
                                     </table>
                                 </div>
                                 
                                 <!-- Loader et indicateurs -->
                                 <div id="loadingIndicator" class="text-center p-3" style="display: none;">
                                     <div class="spinner-border spinner-border-sm text-primary" role="status">
                                         <span class="visually-hidden">Chargement...</span>
                                     </div>
                                     <small class="text-muted ms-2">Chargement des étudiants...</small>
                                 </div>
                                 
                                 <div id="endMessage" class="alert alert-info text-center mt-3" style="display: none;">
                                     <small>Tous les étudiants sont affichés</small>
                                 </div>

                                 <!-- Résumé -->
                                 <div class="mt-3 p-3 bg-light rounded">
                                     <div class="row text-center">
                                         <div class="col-md-6">
                                             <h6 class="mb-2">Résumé des dettes</h6>
                                             <p class="mb-1">
                                                 <strong><?= $stats['etudiants_avec_dettes'] ?></strong> étudiant(s) sur 
                                                 <strong><?= $stats['total_etudiants'] ?></strong> ont des crédits en dette
                                             </p>
                                             <p class="mb-0 text-muted small">
                                                 (<?= $stats['taux_etudiants_dettes'] ?>% de la promotion)
                                             </p>
                                         </div>
                                         <div class="col-md-6">
                                             <h6 class="mb-2">Sources des dettes</h6>
                                             <p class="mb-1">
                                                 Système: <strong><?= $stats['systeme_dettes'] ?></strong> crédits | 
                                                 Imports: <strong><?= $stats['import_dettes'] ?></strong> crédits
                                             </p>
                                             <p class="mb-0 text-muted small">
                                                 Total: <strong><?= $stats['total_credits_dettes'] ?></strong> crédits
                                             </p>
                                         </div>
                                     </div>
                                 </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> Aucun étudiant trouvé pour cette promotion.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php elseif (!empty($promotion_id) && !empty($annee_id)): ?>
                <!-- Aucune donnée -->
                <div class="col-12">
                    <div class="alert alert-warning text-center">
                        <i class="bi bi-exclamation-triangle fs-1"></i>
                        <h4 class="mt-3">Aucune donnée</h4>
                        <p>Aucun étudiant trouvé pour cette promotion.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<script>
    // Charger les promotions selon l'année sélectionnée - retourne une Promise
    function loadPromotions(promotionIdAReselectionner = null) {
        return new Promise((resolve, reject) => {
            const anneeId = document.getElementById('annee_id').value;
            const promotionSelect = document.getElementById('promotion_id');
            
            promotionSelect.innerHTML = '<option value="">Chargement...</option>';
            promotionSelect.disabled = true;
            
            if (!anneeId) {
                promotionSelect.innerHTML = '<option value="">Sélectionner d\'abord une année</option>';
                reject('Pas d\'année sélectionnée');
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
                        promotionSelect.disabled = false;
                        
                        // Re-sélectionner la promotion si on l'avait avant
                        if (promotionIdAReselectionner) {
                            promotionSelect.value = promotionIdAReselectionner;
                            console.log('Promo re-sélectionnée:', promotionIdAReselectionner);
                        }
                        
                        resolve(true);
                    } else {
                        promotionSelect.innerHTML = '<option value="">Aucune promotion trouvée</option>';
                        resolve(false);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    promotionSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                    reject(error);
                });
        });
    }

    // Exporter en PDF
    function exporterPDF() {
        const anneeId = document.getElementById('annee_id').value;
        const promotionId = document.getElementById('promotion_id').value;
        
        if (!anneeId || !promotionId) {
            alert('Veuillez sélectionner une année et une promotion');
            return;
        }
        
        window.open(`controller/export_rapports_dettes_pdf.php?annee_id=${anneeId}&promotion_id=${promotionId}`, '_blank');
    }

    // Variables pour l'infinite scroll
    let currentOffset = 0;
    const pageSize = 20;
    let isLoading = false;
    let hasMore = true;

    // Créer une ligne de tableau pour un étudiant
    function creerLigneEtudiant(etudiant) {
        const classeRow = etudiant.credits_dettes > 0 ? 'dette-row-avec' : 'dette-row-sans';
        const classeBadgeDettes = etudiant.credits_dettes > 0 ? 'bg-danger' : 'bg-success';
        const classeStatut = etudiant.credits_dettes > 0 ? 'bg-warning text-dark' : 'bg-success';
        const classeValidation = etudiant.pourcentage_validation >= 50 ? 'bg-success' : 'bg-warning';
        const classeBadgeSysteme = etudiant.details.systeme_dettes > 0 ? 'bg-danger' : 'bg-secondary';
        const classeBadgeImport = etudiant.details.import_dettes > 0 ? 'bg-warning' : 'bg-secondary';
        const textStatut = etudiant.credits_dettes > 0 ? 'En dette' : 'Régularisé';
        const iconStatut = etudiant.credits_dettes > 0 ? 'bi-exclamation-circle' : 'bi-check-circle';
        const anneeId = document.getElementById('annee_id').value;
        const promotionId = document.getElementById('promotion_id').value;

        const tr = document.createElement('tr');
        tr.className = classeRow;
        tr.innerHTML = `
            <td><strong>${escapeHtml(etudiant.matricule)}</strong></td>
            <td>${escapeHtml(etudiant.noms)}</td>
            <td class="text-center">
                <span class="badge-custom badge ${classeBadgeDettes}">${etudiant.credits_dettes}</span>
            </td>
            <td class="text-center">
                <span class="badge-custom badge bg-info">${etudiant.credits_total}</span>
            </td>
            <td class="text-center">
                <span class="badge-custom badge bg-secondary">${etudiant.credits_valides}</span>
            </td>
            <td class="text-center">
                <span class="badge ${classeValidation}">${etudiant.pourcentage_validation}%</span>
            </td>
            <td class="text-center">
                <span class="badge-custom badge ${classeBadgeSysteme}">${etudiant.details.systeme_dettes}</span>
            </td>
            <td class="text-center">
                <span class="badge-custom badge ${classeBadgeImport}">${etudiant.details.import_dettes}</span>
            </td>
            <td class="text-center">
                <span class="badge ${classeStatut}">
                    <i class="bi ${iconStatut}"></i> ${textStatut}
                </span>
            </td>
            <td class="text-center">
                <a href="index.php?view=lmd/parcours_etudiant&matricule=${encodeURIComponent(etudiant.matricule)}&annee_id=${anneeId}&promotion_id=${promotionId}" 
                   class="btn btn-sm btn-primary" title="Voir parcours">
                    <i class="bi bi-eye"></i>
                </a>
            </td>
        `;
        return tr;
    }

    // Échapper les caractères HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Charger les étudiants avec pagination
    function chargerEtudiants() {
        if (isLoading || !hasMore) return;

        const anneeId = document.getElementById('annee_id').value;
        const promotionId = document.getElementById('promotion_id').value;

        console.log('Chargement dettes - Année:', anneeId, 'Promotion:', promotionId, 'Offset:', currentOffset);

        if (!anneeId || !promotionId) {
            console.error('Paramètres manquants');
            return;
        }

        isLoading = true;
        const loadingInd = document.getElementById('loadingIndicator');
        if (loadingInd) {
            loadingInd.style.display = 'block';
        }

        const url = `controller/ajax_load_dettes_pagination.php?annee_id=${anneeId}&promotion_id=${promotionId}&offset=${currentOffset}&limit=${pageSize}`;
        console.log('URL:', url);

        fetch(url)
            .then(response => {
                console.log('Réponse status:', response.status);
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.text();
            })
            .then(text => {
                console.log('Réponse brute:', text.substring(0, 500));
                return JSON.parse(text);
            })
            .then(data => {
                console.log('Données reçues:', data);
                const tbody = document.getElementById('dettesBody');
                
                if (data.success && data.data && data.data.length > 0) {
                    data.data.forEach(etudiant => {
                        tbody.appendChild(creerLigneEtudiant(etudiant));
                    });

                    currentOffset += data.data.length;
                    hasMore = data.hasMore;

                    const endMsg = document.getElementById('endMessage');
                    if (!hasMore && endMsg) {
                        endMsg.style.display = 'block';
                    }
                } else if (currentOffset === 0) {
                    const message = data.message || 'Aucun étudiant trouvé';
                    console.warn('Pas de données:', message);
                    tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted">' + escapeHtml(message) + '</td></tr>';
                    hasMore = false;
                }
            })
            .catch(error => {
                console.error('Erreur AJAX:', error);
                if (currentOffset === 0) {
                    const tbody = document.getElementById('dettesBody');
                    tbody.innerHTML = '<tr><td colspan="10" class="text-center text-danger">Erreur: ' + escapeHtml(error.message) + '</td></tr>';
                }
            })
            .finally(() => {
                isLoading = false;
                const loadingInd = document.getElementById('loadingIndicator');
                if (loadingInd) {
                    loadingInd.style.display = 'none';
                }
            });
    }

    // Observer Intersection pour l'infinite scroll
    function setupInfiniteScroll() {
        const dettesTable = document.getElementById('dettesTable');
        if (!dettesTable || !dettesTable.parentElement) {
            console.warn('dettesTable ou son parent n\'existe pas');
            return;
        }

        const sentinel = document.createElement('div');
        sentinel.id = 'scroll-sentinel';
        sentinel.style.height = '20px';
        dettesTable.parentElement.appendChild(sentinel);

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && hasMore && !isLoading) {
                    chargerEtudiants();
                }
            });
        }, {
            rootMargin: '100px'
        });

        observer.observe(sentinel);
    }

    // Événements
    document.addEventListener('DOMContentLoaded', async function() {
        console.log('DOM loaded');
        const anneeSelect = document.getElementById('annee_id');
        const promotionSelect = document.getElementById('promotion_id');
        
        const anneeVal = anneeSelect ? anneeSelect.value.trim() : '';
        const promoVal = promotionSelect ? promotionSelect.value.trim() : '';
        
        console.log('Année valeur initiale:', anneeVal, 'Promo valeur initiale:', promoVal);
        
        // Si une année est déjà sélectionnée, charger ses promotions
        if (anneeVal) {
            console.log('Chargement promotions pour année', anneeVal);
            try {
                // Attendre que loadPromotions finisse
                await loadPromotions(promoVal ? promoVal : null);
                
                const anneeValFinal = anneeSelect.value.trim();
                const promoValFinal = promotionSelect.value.trim();
                
                console.log('Après loadPromotions - Année:', anneeValFinal, 'Promo:', promoValFinal);
                
                // Si promotion et année sont sélectionnées, charger les premiers étudiants
                if (anneeValFinal && promoValFinal) {
                    console.log('Démarrage du chargement des étudiants...');
                    currentOffset = 0;
                    hasMore = true;
                    chargerEtudiants();
                    setupInfiniteScroll();
                } else {
                    console.log('Pas assez de sélections:', anneeValFinal, promoValFinal);
                }
            } catch (error) {
                console.error('Erreur lors du chargement des promotions:', error);
            }
        }
    });

    // Charger les stats en AJAX (pour gros volumes)
    function chargerStatsAJAX() {
        const anneeId = document.getElementById('annee_id').value;
        const promotionId = document.getElementById('promotion_id').value;

        if (!anneeId || !promotionId) return;

        fetch(`controller/ajax_stats_dettes_promotion.php?annee_id=${anneeId}&promotion_id=${promotionId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Mettre à jour les stats (optionnel, si on veut rafraîchir)
                    console.log('Stats calculées:', data.stats);
                }
            })
            .catch(e => console.error('Erreur stats:', e));
    }

    // Réinitialiser et charger au changement de promotion
    const promotionSelect = document.getElementById('promotion_id');
    promotionSelect.addEventListener('change', function() {
        if (this.value) {
            currentOffset = 0;
            hasMore = true;
            document.getElementById('dettesBody').innerHTML = '';
            document.getElementById('endMessage').style.display = 'none';
            chargerEtudiants();
            setupInfiniteScroll();
        }
    });
    </script>

<?php include "./views/include/footer.php"; ?>
