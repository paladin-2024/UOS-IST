<?php
session_start();
include_once "../config/Connexion.php";
include_once "../models/PlanTravail.php";

header('Content-Type: application/json');

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

if (!isset($_GET['etudiant_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID étudiant manquant']);
    exit;
}

$etudiantId = (int)$_GET['etudiant_id'];
$connexion = Connexion::getInstance()->getPDO();
$planTravailModel = new PlanTravail();

// Vérifier les droits d'accès
$currentUserId = $_SESSION['id'];
$hasFullAccess = $_SESSION['idRole'] == 1;

// Fonctions utilitaires
function getUserSections($db, $userId, $anneeAcadId) {
    $query = "SELECT section_idsection FROM responsable_section 
              WHERE idUser = :userId AND annee_acad_idannee_acad = :anneeId";
    $stmt = $db->prepare($query);
    $stmt->execute(['userId' => $userId, 'anneeId' => $anneeAcadId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getCurrentAcademicYear($db) {
    $query = "SELECT idannee_acad FROM annee_acad WHERE est_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['idannee_acad'] : null;
}

// Vérifier les permissions
if (!$hasFullAccess) {
    $currentAcademicYear = getCurrentAcademicYear($connexion);
    $userSections = getUserSections($connexion, $currentUserId, $currentAcademicYear);
    
    // Vérifier si l'étudiant appartient aux sections de l'utilisateur
    $queryCheck = "SELECT COUNT(*) FROM etudiant e
                   JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                   JOIN orientation o ON p.orientation_idorientation = o.idorientation
                   JOIN section sec ON o.section_idsection = sec.idsection
                   WHERE e.idetudiant = ? AND sec.idsection IN (" . str_repeat('?,', count($userSections) - 1) . "?)";
    
    $stmtCheck = $connexion->prepare($queryCheck);
    $stmtCheck->execute(array_merge([$etudiantId], $userSections));
    
    if ($stmtCheck->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
        exit;
    }
}

try {
    // Récupérer les informations détaillées de l'étudiant
    $query = "SELECT e.*, 
                     p.designationPromotion as promotion,
                     o.designationOrientation as orientation,
                     sec.designationSection as section,
                     aa.designation as annee_academique
              FROM etudiant e
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              LEFT JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
              WHERE e.idetudiant = ?";
    
    $stmt = $connexion->prepare($query);
    $stmt->execute([$etudiantId]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$etudiant) {
        echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé']);
        exit;
    }
    
    // Récupérer les sujets de l'étudiant
    $querySujets = "SELECT s.*, 
                           dir.noms as directeur_nom, gd.designation as grade_directeur,
                           enc.noms as encadreur_nom, ge.designation as grade_encadreur,
                           spec.designation as specialisation
                    FROM sujets s
                    LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
                    LEFT JOIN grade gd ON dir.grade_id = gd.idgrade
                    LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
                    LEFT JOIN grade ge ON enc.grade_id = ge.idgrade
                    LEFT JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
                    WHERE s.etudiant_idetudiant = ?
                    ORDER BY s.idsujets DESC";
    
    $stmtSujets = $connexion->prepare($querySujets);
    $stmtSujets->execute([$etudiantId]);
    $sujets = $stmtSujets->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer la progression
    $progressionData = calculerProgressionEtudiant($connexion, $etudiantId, $planTravailModel);
    
    // Récupérer le plan de travail s'il existe (prioriser les plans validés)
    $planTravail = null;
    $chapitres = [];
    $planEnAttente = null;
    $chapitresEnAttente = [];
    
    if (!empty($sujets)) {
        foreach ($sujets as $sujet) {
            $plan = $planTravailModel->getPlanBySujet($sujet['idsujets']);
            if ($plan) {
                // Prioriser les plans validés
                if ($sujet['statut_validation'] === 'Validé') {
                    $planTravail = $plan;
                    $chapitres = $planTravailModel->getChapitresByPlan($plan['idplan_travail']);
                    break; // Plan validé trouvé, on s'arrête
                } else if (!$planEnAttente) {
                    // Garder le premier plan non-validé comme fallback
                    $planEnAttente = $plan;
                    $chapitresEnAttente = $planTravailModel->getChapitresByPlan($plan['idplan_travail']);
                }
            }
        }
        
        // Si aucun plan validé trouvé, utiliser le plan en attente
        if (!$planTravail && $planEnAttente) {
            $planTravail = $planEnAttente;
            $chapitres = $chapitresEnAttente;
        }
    }
    
    // Récupérer les tâches récentes
    $queryTaches = "SELECT t.*, s.intitule as sujet_intitule
                    FROM taches t
                    JOIN sujets s ON t.sujets_idsujets = s.idsujets
                    WHERE s.etudiant_idetudiant = ?
                    ORDER BY t.dateTache DESC
                    LIMIT 5";
    
    $stmtTaches = $connexion->prepare($queryTaches);
    $stmtTaches->execute([$etudiantId]);
    $tachesRecentes = $stmtTaches->fetchAll(PDO::FETCH_ASSOC);
    
    // Générer le HTML des détails
    ob_start();
    ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <?php if (!empty($etudiant['photo'])): ?>
                        <img src="<?= htmlspecialchars($etudiant['photo']) ?>" 
                             class="rounded-circle mb-3" width="120" height="120" alt="Photo">
                    <?php else: ?>
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                             style="width: 120px; height: 120px;">
                            <i class="bi bi-person text-white" style="font-size: 3rem;"></i>
                        </div>
                    <?php endif; ?>
                    
                    <h5><?= htmlspecialchars($etudiant['noms']) ?></h5>
                    <p class="text-muted"><?= htmlspecialchars($etudiant['matricule']) ?></p>
                    
                    <div class="row text-center">
                        <div class="col-12 mb-2">
                            <span class="badge bg-info"><?= htmlspecialchars($etudiant['section']) ?></span>
                        </div>
                        <div class="col-12">
                            <small class="text-muted"><?= htmlspecialchars($etudiant['promotion']) ?></small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Informations personnelles -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-person-lines-fill"></i> Informations</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td><?= htmlspecialchars($etudiant['email'] ?? 'Non renseigné') ?></td>
                        </tr>
                        <tr>
                            <td><strong>Téléphone:</strong></td>
                            <td><?= htmlspecialchars($etudiant['telephone'] ?? 'Non renseigné') ?></td>
                        </tr>
                        <tr>
                            <td><strong>Orientation:</strong></td>
                            <td><?= htmlspecialchars($etudiant['orientation']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Année académique:</strong></td>
                            <td><?= htmlspecialchars($etudiant['annee_academique']) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Progression -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-graph-up"></i> Progression Globale</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Progression Générale</span>
                        <span class="badge bg-primary"><?= $progressionData['pourcentage_global'] ?>%</span>
                    </div>
                    <div class="progress mb-3" style="height: 10px;">
                        <div class="progress-bar bg-primary" 
                             style="width: <?= $progressionData['pourcentage_global'] ?>%"></div>
                    </div>
                    
                    <?php if ($progressionData['plan_valide']): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Plan de Travail</span>
                            <span class="badge bg-success"><?= $progressionData['pourcentage_plan'] ?>%</span>
                        </div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-success" 
                                 style="width: <?= $progressionData['pourcentage_plan'] ?>%"></div>
                        </div>
                        <small class="text-muted">
                            <?= $progressionData['chapitres_valides'] ?>/<?= $progressionData['total_chapitres'] ?> chapitres validés
                        </small>
                    <?php endif; ?>
                    
                    <?php if ($progressionData['total_taches'] > 0): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 mt-3">
                            <span>Tâches Traditionnelles</span>
                            <span class="badge bg-info"><?= $progressionData['pourcentage_taches'] ?>%</span>
                        </div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-info" 
                                 style="width: <?= $progressionData['pourcentage_taches'] ?>%"></div>
                        </div>
                        <small class="text-muted">
                            <?= $progressionData['taches_validees'] ?>/<?= $progressionData['total_taches'] ?> tâches validées
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Sujets de recherche -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-book"></i> Sujets de Recherche</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($sujets)): ?>
                        <?php foreach ($sujets as $sujet): ?>
                            <div class="border-start border-3 border-primary ps-3 mb-3">
                                <h6><?= htmlspecialchars($sujet['intitule']) ?></h6>
                                <p class="text-muted mb-2"><?= htmlspecialchars($sujet['specialisation'] ?? 'Spécialisation non définie') ?></p>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <small><strong>Directeur:</strong><br>
                                        <?= htmlspecialchars($sujet['directeur_nom'] ?? 'Non assigné') ?>
                                        <?= !empty($sujet['grade_directeur']) ? '(' . htmlspecialchars($sujet['grade_directeur']) . ')' : '' ?>
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <small><strong>Encadreur:</strong><br>
                                        <?= htmlspecialchars($sujet['encadreur_nom'] ?? 'Non assigné') ?>
                                        <?= !empty($sujet['grade_encadreur']) ? '(' . htmlspecialchars($sujet['grade_encadreur']) . ')' : '' ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="mt-2">
                                    <span class="badge <?= getBadgeClass($sujet['statut_validation']) ?>">
                                        <?= $sujet['statut_validation'] ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Aucun sujet de recherche assigné.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Plan de travail -->
            <?php if ($planTravail): ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-list-check"></i> Plan de Travail</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6><?= htmlspecialchars($planTravail['titre_plan']) ?></h6>
                            <span class="badge <?= getStatutPlanBadgeClass($planTravail['statut_validation']) ?>">
                                <?= $planTravail['statut_validation'] ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($chapitres)): ?>
                            <h6 class="mt-3">Chapitres (<?= count($chapitres) ?>)</h6>
                            <div class="list-group list-group-flush">
                                <?php foreach ($chapitres as $chapitre): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Chapitre <?= $chapitre['numero_chapitre'] ?>:</strong>
                                            <?= htmlspecialchars($chapitre['titre_chapitre']) ?>
                                        </div>
                                        <span class="badge <?= getBadgeClass($chapitre['statut']) ?>">
                                            <?= $chapitre['statut'] ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Tâches récentes -->
            <?php if (!empty($tachesRecentes)): ?>
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-clock-history"></i> Tâches Récentes</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($tachesRecentes as $tache): ?>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong><?= htmlspecialchars($tache['description']) ?></strong><br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($tache['sujet_intitule']) ?> - 
                                        <?= date('d/m/Y', strtotime($tache['dateTache'])) ?>
                                    </small>
                                </div>
                                <span class="badge <?= getBadgeClass($tache['validation']) ?>">
                                    <?= $tache['validation'] ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    
    $html = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (Exception $e) {
    error_log("Erreur get_student_details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}

// Fonctions utilitaires (copiées du fichier principal)
function calculerProgressionEtudiant($connexion, $etudiantId, $planTravailModel = null) {
    try {
        $querySubjects = "SELECT idsujets FROM sujets WHERE etudiant_idetudiant = :etudiantId AND statut_validation = 'Validé'";
        $stmtSubjects = $connexion->prepare($querySubjects);
        $stmtSubjects->execute(['etudiantId' => $etudiantId]);
        $sujets = $stmtSubjects->fetchAll(PDO::FETCH_COLUMN);

        if (empty($sujets)) {
            return [
                'pourcentage_global' => 0,
                'pourcentage_plan' => 0,
                'pourcentage_taches' => 0,
                'total_chapitres' => 0,
                'chapitres_valides' => 0,
                'total_taches' => 0,
                'taches_validees' => 0,
                'plan_valide' => false,
                'statut_plan' => 'Aucun plan'
            ];
        }

        $progressionGlobale = 0;
        $progressionPlan = 0;
        $progressionTaches = 0;
        $totalChapitres = 0;
        $chapitresValides = 0;
        $totalTaches = 0;
        $tachesValidees = 0;
        $planValide = false;
        $statutPlan = 'Aucun plan';

        foreach ($sujets as $sujetId) {
            $plan = $planTravailModel ? $planTravailModel->getPlanBySujet($sujetId) : null;

            if ($plan) {
                $statutPlan = $plan['statut_validation'];
                $planValide = ($plan['statut_validation'] === 'Validé');

                if ($planValide) {
                    $chapitres = $planTravailModel->getChapitresByPlan($plan['idplan_travail']);
                    $totalChapitres += count($chapitres);

                    foreach ($chapitres as $chapitre) {
                        if ($chapitre['statut'] === 'Terminé') {
                            $chapitresValides++;
                        }
                    }

                    if ($totalChapitres > 0) {
                        $progressionPlan = round(($chapitresValides / $totalChapitres) * 100);
                    }
                } else {
                    $progressionPlan = 0;
                }
            }

            $queryTaches = "SELECT 
                COUNT(*) as total_taches,
                SUM(CASE WHEN validation = 'Validé' THEN 1 ELSE 0 END) as taches_validees
            FROM taches 
            WHERE sujets_idsujets = :sujetId";

            $stmtTaches = $connexion->prepare($queryTaches);
            $stmtTaches->execute(['sujetId' => $sujetId]);
            $dataTaches = $stmtTaches->fetch(PDO::FETCH_ASSOC);

            if ($dataTaches) {
                $totalTaches += $dataTaches['total_taches'] ?? 0;
                $tachesValidees += $dataTaches['taches_validees'] ?? 0;
            }
        }

        if ($totalTaches > 0) {
            $progressionTaches = round(($tachesValidees / $totalTaches) * 100);
        }

        if ($planValide && $totalChapitres > 0) {
            $progressionGlobale = round(($progressionPlan * 0.8) + ($progressionTaches * 0.2));
        } else if ($totalTaches > 0) {
            $progressionGlobale = $progressionTaches;
        }

        return [
            'pourcentage_global' => max(0, min(100, $progressionGlobale)),
            'pourcentage_plan' => $progressionPlan,
            'pourcentage_taches' => $progressionTaches,
            'total_chapitres' => $totalChapitres,
            'chapitres_valides' => $chapitresValides,
            'total_taches' => $totalTaches,
            'taches_validees' => $tachesValidees,
            'plan_valide' => $planValide,
            'statut_plan' => $statutPlan
        ];
    } catch (Exception $e) {
        error_log("Erreur calcul progression: " . $e->getMessage());
        return [
            'pourcentage_global' => 0,
            'pourcentage_plan' => 0,
            'pourcentage_taches' => 0,
            'total_chapitres' => 0,
            'chapitres_valides' => 0,
            'total_taches' => 0,
            'taches_validees' => 0,
            'plan_valide' => false,
            'statut_plan' => 'Erreur'
        ];
    }
}

function getStatutPlanBadgeClass($statut) {
    switch ($statut) {
        case 'Validé':
            return 'bg-success';
        case 'En attente':
            return 'bg-warning text-dark';
        case 'Rejeté':
            return 'bg-danger';
        case 'Modifié':
            return 'bg-info';
        case 'Aucun plan':
            return 'bg-secondary';
        default:
            return 'bg-light text-dark';
    }
}

function getBadgeClass($validation) {
    switch ($validation) {
        case 'Validé':
        case 'Terminé':
            return 'bg-success';
        case 'En cours':
            return 'bg-warning';
        case 'Rejeté':
            return 'bg-danger';
        case 'En attente':
        default:
            return 'bg-secondary';
    }
}
?>
