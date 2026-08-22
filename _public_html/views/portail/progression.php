<?php
require_once "head_student.php";
 
// Set page title for mobile header
$pageTitle = 'Ma Progression';
$currentPage = 'progression';

// Récupérer les informations de l'étudiant
$studentId = $_SESSION['student_id'] ?? 0;
$studentMatricule = $_SESSION['student_matricule'] ?? '';
$currentYear = $universite->getAnneeAcademiqueById($_SESSION['annee_acad']);

// Initialiser les modèles
$deliberation = new Deliberation();
$dette = new Dette();
$grilleAncienne = new GrilleAncienne();

// Récupérer toutes les années de l'étudiant
$resultatsSysteme = [];
$resultatsImportes = [];
$dettes = [];
$toutes_sessions = $universite->getAllSessions();
$annees = $universite->getAllAcademicYears();

if (!empty($studentMatricule)) {
    // Récupérer toutes les années pour cet étudiant
    $annees_etudiant = [];
    try {
        $conn = Connexion::getInstance()->getPDO();
        $query = "SELECT DISTINCT annee_acad_idannee_acad FROM etudiant WHERE matricule = :matricule ORDER BY annee_acad_idannee_acad DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute([':matricule' => $studentMatricule]);
        $annees_etudiant = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Erreur récupération années étudiant: " . $e->getMessage());
    }
    
    // Pour chaque année de l'étudiant
    foreach ($annees_etudiant as $annee_acad_id) {
        $notesParSession = [];
        
        foreach ($toutes_sessions as $session) {
            $sessionId = $session['idsession'];
            $notesParSession[$sessionId] = $deliberation->getNotesEtudiant($studentMatricule, $sessionId, $annee_acad_id);
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
                    
                    $ueExists = false;
                    foreach ($resultatsSysteme[$semestreKey]['ues'] as &$ueExistante) {
                        if ($ueExistante['code'] === $codeUE) {
                            if ($estValidee || ($moyenneUE !== null && $moyenneUE !== '' && $ueExistante['moyenne'] !== null && $moyenneUE > $ueExistante['moyenne'])) {
                                $ueExistante['code'] = $codeUE;
                                $ueExistante['designation'] = $ue['info']['designationUE'];
                                $ueExistante['credits_total'] = $totalCredits;
                                $ueExistante['credits_valides'] = $creditsValides;
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
    
    // Récupérer les dettes
    $dettes = $dette->getDettesEtudiant($studentMatricule);
    
    // Récupération des résultats importés (grilles anciennes) avec enrichissement
    $resultatsImportesOriginaux = $grilleAncienne->getResultatsEtudiantImportes($studentMatricule);
    
    // Enrichir les imports avec l'année académique dans le libellé
    $resultatsImportesEnriches = [];
    foreach ($resultatsImportesOriginaux as $import) {
        $import['display_label'] = $import['session'] . ' - ' . $import['annee_academique'];
        $resultatsImportesEnriches[] = $import;
    }
    
    // Fusionner les UE importées en prenant le meilleur résultat
    $resultatsImportesFusionnes = [];
    $uesParCode = [];
    
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
}

// Calculer la synthèse des crédits (utiliser les données fusionnées pour la cohérence)
$syntheseCredits = calculerSyntheseCredits($resultatsSysteme, $resultatsImportesFusionnes, $dettes);

// Calculer le taux de réussite
$tauxReussite = $syntheseCredits['credits_total'] > 0 
    ? round(($syntheseCredits['credits_valides'] / $syntheseCredits['credits_total']) * 100) 
    : 0;

/**
 * Calculer la synthèse des crédits pour un étudiant
 */
function calculerSyntheseCredits($resultatsSysteme, $resultatsImportes, $dettes) {
    $creditsValides = 0;
    $creditsDettes = 0;
    $creditsTotal = 0;
    
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
    
    // Les dettes de la table dette_etudiant peuvent être des doublons, on utilise le max
    $creditsDettesTable = 0;
    foreach ($dettes as $dette) {
        $creditsDettesTable += intval($dette['credits_ecue'] ?? 0);
    }
    
    $creditsDettesCalculees = $creditsDettes;
    $creditsDettes = max($creditsDettesCalculees, $creditsDettesTable);
    
    // Alternative: crédits total - crédits validés si c'est plus fiable
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
        'nombre_dettes' => count($dettes)
    ];
}
?>

<?php include "includes/mobile_header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<!-- Content Area -->
<div class="content-area">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary">
            <i class="fas fa-chart-line me-2"></i>Mon Parcours Académique
        </h2>
        <?php 
            $appRoot = str_replace('/views/portail/progression.php', '', $_SERVER['SCRIPT_NAME']);
        ?>
        <a href="<?= $appRoot ?>/controller/export_parcours_pdf.php?matricule=<?= htmlspecialchars($studentMatricule) ?>" 
           class="btn btn-outline-primary" target="_blank">
            <i class="fas fa-file-pdf me-2"></i>Exporter en PDF
        </a>
    </div>

    <!-- Synthesis Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-2">
                        <i class="fas fa-coins me-2 text-primary"></i>Crédits Totaux
                    </h6>
                    <h3 class="fw-bold text-primary"><?= $syntheseCredits['credits_total'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-2">
                        <i class="fas fa-check-circle me-2 text-success"></i>Crédits Obtenus
                    </h6>
                    <h3 class="fw-bold text-success"><?= $syntheseCredits['credits_valides'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-2">
                        <i class="fas fa-chart-pie me-2 text-info"></i>Taux de Réussite
                    </h6>
                    <h3 class="fw-bold text-info"><?= $tauxReussite ?>%</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-2">
                        <i class="fas fa-exclamation-circle me-2 text-<?= ($syntheseCredits['credits_dettes'] > 0) ? 'danger' : 'success' ?>"></i>Crédits en Dette
                    </h6>
                    <h3 class="fw-bold text-<?= ($syntheseCredits['credits_dettes'] > 0) ? 'danger' : 'success' ?>"><?= $syntheseCredits['credits_dettes'] ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <?php
    // Collecter les années académiques distinctes des imports
    $anneesImportees = [];
    foreach ($resultatsImportes as $import) {
        $aa = $import['annee_academique'] ?? '';
        if ($aa !== '' && !in_array($aa, $anneesImportees)) {
            $anneesImportees[] = $aa;
        }
    }
    // Collecter les années académiques distinctes du système
    $anneesSysteme = [];
    foreach ($resultatsSysteme as $semestre) {
        $aa = $semestre['annee_designation'] ?? '';
        if ($aa !== '' && !in_array($aa, $anneesSysteme)) {
            $anneesSysteme[] = $aa;
        }
    }
    $toutesAnneesDistinctes = array_unique(array_merge($anneesImportees, $anneesSysteme));
    sort($toutesAnneesDistinctes);
    ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <div class="row align-items-center g-2">
                <div class="col-md-4">
                    <label for="filtreAnnee" class="form-label mb-0 fw-semibold">
                        <i class="fas fa-filter me-1"></i>Année Académique
                    </label>
                    <select id="filtreAnnee" class="form-select form-select-sm mt-1">
                        <option value="">Toutes les années</option>
                        <?php foreach ($toutesAnneesDistinctes as $aa): ?>
                            <option value="<?= htmlspecialchars($aa) ?>"><?= htmlspecialchars($aa) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch mt-md-4">
                        <input class="form-check-input" type="checkbox" id="inclureSysteme">
                        <label class="form-check-label" for="inclureSysteme">
                            <i class="fas fa-desktop me-1"></i>Inclure les résultats du système
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Résultats Importés par Année Académique -->
    <?php if (!empty($resultatsImportes)): ?>
    <div id="sectionImportes">
        <h4 class="fw-bold text-primary mb-3">
            <i class="fas fa-file-import me-2"></i>Résultats Importés
        </h4>
        
        <?php foreach ($resultatsImportes as $import): ?>
        <?php if (empty($import['ues'])) continue; ?>
        <div class="card mb-3 border-0 shadow-sm bloc-import" data-annee="<?= htmlspecialchars($import['annee_academique'] ?? '') ?>">
            <div class="card-header bg-light border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold text-primary mb-0">
                            <?= htmlspecialchars($import['session'] ?? 'Import') ?>
                        </h6>
                        <small class="text-muted"><?= htmlspecialchars($import['annee_academique'] ?? '') ?> — <?= htmlspecialchars($import['semestre'] ?? '') ?></small>
                    </div>
                    <?php
                    $totalCreditsImport = 0;
                    $creditsValidesImport = 0;
                    foreach ($import['ues'] as $ue) {
                        $ct = intval($ue['credits_total'] ?? $ue['credits'] ?? 0);
                        $totalCreditsImport += $ct;
                        if ($ue['est_valide'] ?? false) {
                            $creditsValidesImport += intval($ue['credits_valides'] ?? $ct);
                        }
                    }
                    ?>
                    <span class="badge bg-info p-2">
                        <i class="fas fa-coins me-1"></i>
                        Crédits: <?= $creditsValidesImport ?>/<?= $totalCreditsImport ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>UE</th>
                                <th class="text-center">Code</th>
                                <th class="text-center">Moyenne</th>
                                <th class="text-center">Crédits</th>
                                <th class="text-center">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($import['ues'] as $ue): ?>
                            <tr>
                                <td>
                                    <small><?= htmlspecialchars($ue['designation_ue'] ?? 'N/A') ?></small>
                                </td>
                                <td class="text-center">
                                    <small><?= htmlspecialchars($ue['code_ue'] ?? '') ?></small>
                                </td>
                                <td class="text-center">
                                    <strong><?= number_format($ue['moyenne'] ?? 0, 2, ',', ' ') ?></strong>
                                </td>
                                <td class="text-center">
                                    <small><?= intval($ue['credits_valides'] ?? 0) ?>/<?= intval($ue['credits_total'] ?? $ue['credits'] ?? 0) ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= ($ue['est_valide'] ?? false) ? 'success' : 'danger' ?>">
                                        <?= ($ue['est_valide'] ?? false) ? 'Validée' : 'Non validée' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Résultats du Système (masqués par défaut) -->
    <?php if (!empty($resultatsSysteme)): ?>
    <div id="sectionSysteme" style="display:none;">
        <h4 class="fw-bold text-primary mb-3">
            <i class="fas fa-desktop me-2"></i>Résultats du Système
        </h4>
        
        <?php foreach ($resultatsSysteme as $semestreKey => $semestre): ?>
        <div class="card mb-3 border-0 shadow-sm bloc-systeme" data-annee="<?= htmlspecialchars($semestre['annee_designation'] ?? '') ?>">
            <div class="card-header bg-light border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold text-primary mb-0"><?= htmlspecialchars($semestreKey) ?></h6>
                        <?php if (!empty($semestre['annee_designation'])): ?>
                            <small class="text-muted"><?= htmlspecialchars($semestre['annee_designation']) ?></small>
                        <?php endif; ?>
                    </div>
                    <?php 
                        $creditsTotal = 0;
                        $creditsValides = 0;
                        foreach ($semestre['ues'] as $ue) {
                            $creditsTotal += intval($ue['credits_total'] ?? 0);
                            if ($ue['est_valide'] ?? false) {
                                $creditsValides += intval($ue['credits_valides'] ?? 0);
                            }
                        }
                    ?>
                    <span class="badge bg-info p-2">
                        <i class="fas fa-coins me-1"></i>
                        Crédits: <?= $creditsValides ?>/<?= $creditsTotal ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($semestre['ues'])): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>UE</th>
                                <th class="text-center">Code</th>
                                <th class="text-center">Moyenne</th>
                                <th class="text-center">Crédits</th>
                                <th class="text-center">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($semestre['ues'] as $ue): ?>
                            <tr>
                                <td>
                                    <small><?= htmlspecialchars($ue['designation'] ?? 'N/A') ?></small>
                                </td>
                                <td class="text-center">
                                    <small><?= htmlspecialchars($ue['code'] ?? '') ?></small>
                                </td>
                                <td class="text-center">
                                    <strong><?= number_format($ue['moyenne'] ?? 0, 2, ',', ' ') ?></strong>
                                </td>
                                <td class="text-center">
                                    <small><?= intval($ue['credits_valides'] ?? 0) ?>/<?= intval($ue['credits_total'] ?? 0) ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= ($ue['est_valide'] ?? false) ? 'success' : 'danger' ?>">
                                        <?= ($ue['est_valide'] ?? false) ? 'Validée' : 'Non validée' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i>Aucune note enregistrée pour cette année.
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>



    <!-- Dettes Académiques -->
    <?php if (!empty($dettes)): ?>
    <div class="mb-4">
        <h4 class="fw-bold text-danger mb-3">
            <i class="fas fa-exclamation-circle me-2"></i>Dettes Académiques
        </h4>
        
        <div class="alert alert-danger mb-3">
            <i class="fas fa-warning me-2"></i>
            <strong>Attention!</strong> Vous avez <?= count($dettes) ?> dette(s) à régulariser.
        </div>
        
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ECUE</th>
                                <th class="text-center">Type de Détail</th>
                                <th class="text-center">Année</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dettes as $dette): ?>
                            <tr>
                                <td>
                                    <small><?= htmlspecialchars($dette['designationECUE'] ?? 'N/A') ?></small>
                                </td>
                                <td class="text-center">
                                    <small>
                                        <span class="badge bg-warning">
                                            <?= htmlspecialchars($dette['type_dette'] ?? 'Non spécifié') ?>
                                        </span>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <small><?= htmlspecialchars($dette['designationAnnee'] ?? 'N/A') ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- No Data Message -->
    <?php if (empty($resultatsSysteme) && empty($resultatsImportes) && empty($dettes)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        Aucun résultat disponible pour votre parcours académique.
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . "/includes/main_scripts.php"; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filtreAnnee = document.getElementById('filtreAnnee');
    const inclureSysteme = document.getElementById('inclureSysteme');
    const sectionImportes = document.getElementById('sectionImportes');
    const sectionSysteme = document.getElementById('sectionSysteme');

    function appliquerFiltres() {
        const annee = filtreAnnee.value;
        const showSysteme = inclureSysteme.checked;

        // Section importés — toujours visible
        if (sectionImportes) {
            sectionImportes.style.display = '';
            sectionImportes.querySelectorAll('.bloc-import').forEach(function(bloc) {
                if (annee === '' || bloc.dataset.annee === annee) {
                    bloc.style.display = '';
                } else {
                    bloc.style.display = 'none';
                }
            });
            // Cacher le titre si aucun bloc visible
            const visibles = sectionImportes.querySelectorAll('.bloc-import:not([style*="display: none"])');
            sectionImportes.querySelector('h4').style.display = visibles.length > 0 ? '' : 'none';
        }

        // Section système
        if (sectionSysteme) {
            sectionSysteme.style.display = showSysteme ? '' : 'none';
            if (showSysteme) {
                sectionSysteme.querySelectorAll('.bloc-systeme').forEach(function(bloc) {
                    if (annee === '' || bloc.dataset.annee === annee) {
                        bloc.style.display = '';
                    } else {
                        bloc.style.display = 'none';
                    }
                });
                var visiblesS = sectionSysteme.querySelectorAll('.bloc-systeme:not([style*="display: none"])');
                sectionSysteme.querySelector('h4').style.display = visiblesS.length > 0 ? '' : 'none';
            }
        }
    }

    filtreAnnee.addEventListener('change', appliquerFiltres);
    inclureSysteme.addEventListener('change', appliquerFiltres);
});
</script>

<?php require_once "footer_student.php"; ?>
