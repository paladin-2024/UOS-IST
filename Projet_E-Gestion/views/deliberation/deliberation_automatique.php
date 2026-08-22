<?php
include "./views/include/header.php";

// Récupérer les paramètres
$deliberationId = isset($_GET['deliberation_id']) ? intval($_GET['deliberation_id']) : 0;
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'automatique';

// Vérifier les droits d'accès
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$userId = $_SESSION['id'];

// Connexion directe à la base de données
$conn = Connexion::getInstance()->getPDO();

// Vérifier les droits d'accès avec requête directe
$canAccess = false;
if ($isAdmin) {
    $canAccess = true;
} else {
    // Vérifier si l'utilisateur est président d'un jury
    try {
        $query = "SELECT COUNT(*) as count FROM bureau_jury_deliberation b 
                  INNER JOIN agent a ON b.president_id = a.idagent 
                  WHERE a.utilisateur_idutilisateur = :user_id AND b.est_actif = 1";
        $stmt = $conn->prepare($query);
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $canAccess = $result['count'] > 0;
    } catch (Exception $e) {
        $canAccess = false;
    }
}

if (!$canAccess) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas les droits pour accéder à cette page.'
        }).then(() => {
            window.location.href = 'index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

// Récupérer les informations de la délibération avec requête directe
$deliberationInfo = null;
if ($deliberationId) {
    try {
        $query = "SELECT d.*, p.\"designationPromotion\", p.idPromotion, b.designation as bureau_nom,
                         s.\"designSession\", a.designation as annee_designation,
                         d.idbureau as bureau_id, d.session_idsession as session_id, 
                         d.annee_acad_id as annee_id
                  FROM deliberation d
                  INNER JOIN promotion p ON d.idpromotion = p.idPromotion
                  INNER JOIN bureau_jury_deliberation b ON d.idbureau = b.idbureau
                  INNER JOIN session s ON d.session_idsession = s.idsession
                  INNER JOIN annee_acad a ON d.annee_acad_id = a.idannee_acad
                  WHERE d.iddeliberation = :deliberation_id";
        $stmt = $conn->prepare($query);
        $stmt->execute(['deliberation_id' => $deliberationId]);
        $deliberationInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la récupération des informations de délibération: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = 'index.php?view=deliberation/seances';
            });
        </script>";
        exit();
    }
}

if (!$deliberationInfo) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Délibération non trouvée',
            text: 'La délibération spécifiée n\'existe pas.'
        }).then(() => {
            window.location.href = 'index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

// Récupérer la configuration de délibération avec requête directe
$configDeliberation = null;
try {
    $query = "SELECT * FROM configuration_deliberation 
              WHERE idbureau = :bureau_id AND session_idsession = :session_id AND annee_acad_idannee_acad = :annee_id";
    $stmt = $conn->prepare($query);
    $stmt->execute([
        'bureau_id' => $deliberationInfo['bureau_id'], 
        'session_id' => $deliberationInfo['session_id'], 
        'annee_id' => $deliberationInfo['annee_id']
    ]);
    $configDeliberation = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Si la table n'existe pas, créer une configuration par défaut
    $configDeliberation = [
        'compensation_intra_ue' => 1,
        'seuil_compensation_intra_ue' => 8.00,
        'compensation_inter_ue' => 1,
        'seuil_compensation_inter_ue' => 8.00,
        'compensation_inter_semestre' => 0,
        'seuil_compensation_inter_semestre' => 8.00,
        'exiger_meme_credit_ue' => 0,
        'limiter_compensation_annee' => 1,
        'note_passage' => 10.00,
        'pourcentage_passage_semestre' => 50.00,
        'calculer_moyenne_avec_notes_vides' => 0
    ];
}

if (!$configDeliberation) {
    echo "<script>
        Swal.fire({
            icon: 'warning',
            title: 'Configuration manquante',
            text: 'Aucune configuration de délibération n\'a été trouvée. Utilisation de la configuration par défaut.',
            showCancelButton: true,
            confirmButtonText: 'Configurer maintenant',
            cancelButtonText: 'Continuer avec défaut'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'index.php?view=deliberation/config_deliberation&bureau=" . $deliberationInfo['bureau_id'] . "&session=" . $deliberationInfo['session_id'] . "&annee=" . $deliberationInfo['annee_id'] . "';
            }
        });
    </script>";
    // Utiliser la configuration par défaut
    $configDeliberation = [
        'compensation_intra_ue' => 1,
        'seuil_compensation_intra_ue' => 8.00,
        'compensation_inter_ue' => 1,
        'seuil_compensation_inter_ue' => 8.00,
        'compensation_inter_semestre' => 0,
        'seuil_compensation_inter_semestre' => 8.00,
        'exiger_meme_credit_ue' => 0,
        'limiter_compensation_annee' => 1,
        'note_passage' => 10.00,
        'pourcentage_passage_semestre' => 50.00,
        'calculer_moyenne_avec_notes_vides' => 0
    ];
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Délibération Automatique - Mode <?= ucfirst($mode) ?></h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item"><a href="index.php?view=deliberation/seances">Délibération</a></li>
                <li class="breadcrumb-item active">Délibération Automatique</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <!-- Informations de la délibération -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations de la délibération</h5>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Promotion:</strong><br>
                                <?= htmlspecialchars($deliberationInfo['designationPromotion']) ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Bureau de jury:</strong><br>
                                <?= htmlspecialchars($deliberationInfo['bureau_nom']) ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Session:</strong><br>
                                <?= htmlspecialchars($deliberationInfo['designSession']) ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Année académique:</strong><br>
                                <?= htmlspecialchars($deliberationInfo['annee_designation']) ?>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <strong>Mode de délibération:</strong> 
                            <span class="badge bg-<?= $mode === 'automatique' ? 'success' : 'warning' ?>">
                                <?= ucfirst($mode) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Configuration utilisée -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Configuration appliquée</h5>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <ul class="list-unstyled">
                                    <li><i class="bi bi-check-circle text-<?= $configDeliberation['compensation_intra_ue'] ? 'success' : 'danger' ?>"></i> 
                                        Compensation intra-UE: <?= $configDeliberation['compensation_intra_ue'] ? 'Activée' : 'Désactivée' ?></li>
                                    <li><i class="bi bi-check-circle text-<?= $configDeliberation['compensation_inter_ue'] ? 'success' : 'danger' ?>"></i> 
                                        Compensation inter-UE: <?= $configDeliberation['compensation_inter_ue'] ? 'Activée' : 'Désactivée' ?></li>
                                    <li><i class="bi bi-check-circle text-<?= $configDeliberation['compensation_inter_semestre'] ? 'success' : 'danger' ?>"></i> 
                                        Compensation inter-semestre: <?= $configDeliberation['compensation_inter_semestre'] ? 'Activée' : 'Désactivée' ?></li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <ul class="list-unstyled">
                                    <li><strong>Note de passage:</strong> <?= number_format($configDeliberation['note_passage'], 2) ?>/20</li>
                                    <li><strong>Seuil compensation intra-UE:</strong> <?= number_format($configDeliberation['seuil_compensation_intra_ue'], 2) ?>/20</li>
                                    <li><strong>Seuil compensation inter-UE:</strong> <?= number_format($configDeliberation['seuil_compensation_inter_ue'], 2) ?>/20</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <ul class="list-unstyled">
                                    <li><strong>Pourcentage passage semestre:</strong> <?= number_format($configDeliberation['pourcentage_passage_semestre'], 2) ?>%</li>
                                    <li><i class="bi bi-check-circle text-<?= $configDeliberation['exiger_meme_credit_ue'] ? 'success' : 'danger' ?>"></i> 
                                        Même crédit pour compensation UE</li>
                                    <li><i class="bi bi-check-circle text-<?= $configDeliberation['calculer_moyenne_avec_notes_vides'] ? 'success' : 'danger' ?>"></i> 
                                        Calcul avec notes manquantes</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Paramètres de délibération -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Paramètres de délibération</h5>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Portée de la délibération</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="portee_deliberation" id="portee_semestre" value="semestre" checked>
                                    <label class="form-check-label" for="portee_semestre">
                                        <i class="bi bi-calendar me-1"></i> Délibération semestrielle
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="portee_deliberation" id="portee_annee" value="annee">
                                    <label class="form-check-label" for="portee_annee">
                                        <i class="bi bi-calendar-range me-1"></i> Délibération annuelle (2 semestres)
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6" id="semestre_selection">
                                <label for="semestre_choix" class="form-label">Semestre à délibérer</label>
                                <select class="form-select" id="semestre_choix">
                                    <?php 
                                    // Récupérer les semestres de la promotion
                                    try {
                                        $query = "SELECT DISTINCT s.idsemestre, s.\"numeroSemestre\" 
                                                  FROM semestre s
                                                  INNER JOIN ue u ON s.idsemestre = u.semestre_idsemestre
                                                  WHERE s.promotion_idpromotion = :promotion_id
                                                  ORDER BY s.\"numeroSemestre\"";
                                        $stmt = $conn->prepare($query);
                                        $stmt->execute(['promotion_id' => $deliberationInfo['idPromotion']]);
                                        $semestres = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        if (empty($semestres)) {
                                            // Si pas de résultats avec UE, essayer sans jointure
                                            $query = "SELECT s.idsemestre, s.\"numeroSemestre\" 
                                                      FROM semestre s
                                                      WHERE s.promotion_idpromotion = :promotion_id
                                                      ORDER BY s.\"numeroSemestre\"";
                                            $stmt = $conn->prepare($query);
                                            $stmt->execute(['promotion_id' => $deliberationInfo['idPromotion']]);
                                            $semestres = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        }
                                        
                                        foreach ($semestres as $semestre): ?>
                                            <option value="<?= $semestre['idsemestre'] ?>">
                                                Semestre <?= $semestre['numeroSemestre'] ?>
                                            </option>
                                        <?php endforeach;
                                    } catch (Exception $e) {
                                        echo '<option value="">Erreur: ' . htmlspecialchars($e->getMessage()) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Zone de contrôle -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Contrôles de délibération</h5>
                        
                        <div class="d-flex gap-3 mb-3">
                            <button type="button" class="btn btn-primary" onclick="demarrerDeliberation()">
                                <i class="bi bi-play-fill"></i> Démarrer la délibération
                            </button>
                            
                            <button type="button" class="btn btn-info" onclick="previsualiserResultats()">
                                <i class="bi bi-eye"></i> Prévisualiser les résultats
                            </button>
                            
                            <button type="button" class="btn btn-secondary" onclick="retourSeances()">
                                <i class="bi bi-arrow-left"></i> Retour aux séances
                            </button>
                        </div>
                        
                        <!-- Barre de progression -->
                        <div id="progress-container" style="display: none;">
                            <div class="mb-2">
                                <strong>Progression:</strong> <span id="progress-text">0/0 étudiants traités</span>
                            </div>
                            <div class="progress mb-3">
                                <div id="progress-bar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Zone des résultats -->
            <div class="col-lg-12">
                <div class="card" id="resultats-container" style="display: none;">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-list-check me-2"></i>
                            Résultats de la délibération
                            <button type="button" class="btn btn-sm btn-outline-success float-end" onclick="exporterResultats()">
                                <i class="bi bi-download"></i> Exporter
                            </button>
                        </h5>
                        
                        <!-- Statistiques globales -->
                        <div id="statistiques" class="row mb-4"></div>
                        
                        <!-- Tableau des résultats -->
                        <div id="tableau-resultats" class="table-responsive"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour validation en mode semi-automatique -->
<div class="modal fade" id="validationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Validation de la décision</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="etudiant-details"></div>
                <div id="decision-proposee"></div>
                <div id="decision-manuelle" style="display: none;">
                    <hr>
                    <h6>Décision manuelle</h6>
                    <div class="mb-3">
                        <label for="decision-select" class="form-label">Nouvelle décision</label>
                        <select class="form-select" id="decision-select">
                            <option value="ADMIS_SANS_RACHAT">Admis sans rachat</option>
                            <option value="ADMIS_AVEC_RACHAT">Admis avec rachat</option>
                            <option value="ADMIS_RATTRAPAGE">Admis au rattrapage</option>
                            <option value="AJOURNE">Ajourné</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="motif-decision" class="form-label">Motif de la modification</label>
                        <textarea class="form-control" id="motif-decision" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" onclick="toggleDecisionManuelle()">
                    <i class="bi bi-pencil"></i> Modifier la décision
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ignorer</button>
                <button type="button" class="btn btn-success" onclick="validerDecision()">
                    <i class="bi bi-check"></i> Valider
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Variables globales
let deliberationId = <?= $deliberationId ?>;
let promotionId = <?= $deliberationInfo['idPromotion'] ?>;
let bureauId = <?= $deliberationInfo['bureau_id'] ?>;
let sessionId = <?= $deliberationInfo['session_id'] ?>;
let anneeId = <?= $deliberationInfo['annee_id'] ?>;
let mode = '<?= $mode ?>';
let resultatsDeliberation = null;
let etudiantActuelIndex = 0;

// Gestion de l'affichage selon la portée
document.addEventListener('DOMContentLoaded', function() {
    const porteeRadios = document.querySelectorAll('input[name="portee_deliberation"]');
    const semestreSelection = document.getElementById('semestre_selection');
    
    porteeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'annee') {
                semestreSelection.style.display = 'none';
            } else {
                semestreSelection.style.display = 'block';
            }
        });
    });
});

function demarrerDeliberation() {
    // Récupérer les paramètres de délibération
    const portee = document.querySelector('input[name="portee_deliberation"]:checked').value;
    const semestreId = portee === 'semestre' ? document.getElementById('semestre_choix').value : null;
    
    // Validation
    if (portee === 'semestre' && !semestreId) {
        Swal.fire('Attention', 'Veuillez sélectionner un semestre', 'warning');
        return;
    }
    
    const textePortee = portee === 'annee' ? 'annuelle (2 semestres)' : `semestrielle (Semestre ${document.getElementById('semestre_choix').selectedOptions[0]?.textContent || ''})`;
    
    Swal.fire({
        title: 'Confirmation',
        text: `Êtes-vous sûr de vouloir démarrer la délibération ${textePortee} en mode ${mode}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, démarrer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('progress-container').style.display = 'block';
            executerDeliberation(portee, semestreId);
        }
    });
}

function executerDeliberation(portee, semestreId) {
    // Préparer les paramètres
    let params = `action=evaluer_promotion&promotion_id=${promotionId}&bureau_id=${bureauId}&session_id=${sessionId}&annee_id=${anneeId}&mode=${mode}&portee=${portee}`;
    
    if (semestreId) {
        params += `&semestre_id=${semestreId}`;
    }
    
    // Appel AJAX pour lancer la délibération
    fetch('controller/deliberation_automatique_v2.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.text();
    })
    .then(text => {
        console.log('Response text:', text);
        try {
            const data = JSON.parse(text);
            if (data.error) {
                console.error('Erreur API:', data.error);
                Swal.fire('Erreur', data.error, 'error');
                return;
            }
            
            console.log('Données reçues:', data);
            resultatsDeliberation = data;
            
            if (mode === 'automatique') {
                afficherResultats(data);
            } else {
                // Mode semi-automatique : afficher étudiant par étudiant
                etudiantActuelIndex = 0;
                afficherEtudiantPourValidation();
            }
        } catch (e) {
            console.error('Erreur parsing JSON:', e);
            console.error('Text reçu:', text);
            Swal.fire('Erreur', 'Réponse invalide du serveur: ' + text.substring(0, 200), 'error');
        }
    })
    .catch(error => {
        console.error('Erreur réseau:', error);
        Swal.fire('Erreur', 'Une erreur est survenue lors de la délibération: ' + error.message, 'error');
    });
}

function previsualiserResultats() {
    // Récupérer les paramètres de délibération
    const portee = document.querySelector('input[name="portee_deliberation"]:checked').value;
    const semestreId = portee === 'semestre' ? document.getElementById('semestre_choix').value : null;
    
    // Validation
    if (portee === 'semestre' && !semestreId) {
        Swal.fire('Attention', 'Veuillez sélectionner un semestre pour la prévisualisation', 'warning');
        return;
    }
    
    document.getElementById('progress-container').style.display = 'block';
    
    // Préparer les paramètres
    let params = `action=evaluer_promotion&promotion_id=${promotionId}&bureau_id=${bureauId}&session_id=${sessionId}&annee_id=${anneeId}&mode=preview&portee=${portee}`;
    
    if (semestreId) {
        params += `&semestre_id=${semestreId}`;
    }
    
    // Simulation de prévisualisation
    fetch('controller/deliberation_automatique_v2.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.text();
    })
    .then(text => {
        console.log('Response text:', text);
        try {
            const data = JSON.parse(text);
            if (data.error) {
                console.error('Erreur API:', data.error);
                Swal.fire('Erreur', data.error, 'error');
                return;
            }
            
            console.log('Données reçues:', data);
            afficherResultats(data, true);
        } catch (e) {
            console.error('Erreur parsing JSON:', e);
            console.error('Text reçu:', text);
            Swal.fire('Erreur', 'Réponse invalide du serveur: ' + text.substring(0, 200), 'error');
        }
    })
    .catch(error => {
        console.error('Erreur réseau:', error);
        Swal.fire('Erreur', 'Une erreur est survenue lors de la prévisualisation: ' + error.message, 'error');
    });
}

// Fonction pour nettoyer les nombres avec virgule flottante
function cleanFloat(num) {
    if (num === null || num === undefined) return 'N/A';
    return Math.round(parseFloat(num) * 100) / 100;
}

function afficherResultats(data, isPreview = false) {
    document.getElementById('progress-container').style.display = 'none';
    document.getElementById('resultats-container').style.display = 'block';
    
    // Afficher les statistiques
    const stats = data.statistiques;
    const portee = data.portee || 'semestre';
    
    let statsHTML = '';
    
    if (portee === 'annee') {
        // Statistiques pour délibération annuelle
        statsHTML = `
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-success">${stats.admis_sans_rachat || 0}</h5>
                        <small>Admis sans rachat</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-info">${stats.admis_avec_rachat || 0}</h5>
                        <small>Admis avec rachat</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-warning">${stats.admis_rattrapage || 0}</h5>
                        <small>Admis au rattrapage</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-danger">${stats.ajournes || 0}</h5>
                        <small>Ajournés</small>
                    </div>
                </div>
            </div>`;
    } else {
        // Statistiques pour délibération semestrielle
        statsHTML = `
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-success">${stats.valide_totalement || 0}</h5>
                        <small>Validé totalement</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-warning">${stats.valide_partiellement || 0}</h5>
                        <small>Validé partiellement</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-danger">${stats.non_valide || 0}</h5>
                        <small>Non validé</small>
                    </div>
                </div>
            </div>`;
    }
    
    // Ajouter les statistiques communes
    statsHTML += `
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-secondary">${stats.incomplets || 0}</h5>
                    <small>Incomplets</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-primary">${data.total_etudiants}</h5>
                    <small>Total étudiants</small>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('statistiques').innerHTML = statsHTML;
    
    // Afficher le tableau des résultats
    let tableauHTML = `
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Matricule</th>
                    <th>Nom et Prénom</th>
                    <th>Moyenne Générale</th>
                    <th>Crédits validés</th>
                    <th>Décision</th>
                    ${!isPreview ? '<th>Actions</th>' : ''}
                </tr>
            </thead>
            <tbody>
    `;
    
    data.resultats.forEach(resultat => {
        const bgClass = getBadgeClass(resultat.decision.code);
        tableauHTML += `
            <tr>
                <td>${resultat.matricule}</td>
                <td>${resultat.etudiant.noms}</td>
                <td>${resultat.resultats_globaux.moyenne_generale ? cleanFloat(resultat.resultats_globaux.moyenne_generale) : 'N/A'}</td>
                <td>${cleanFloat(resultat.resultats_globaux.credits_valides)}/${cleanFloat(resultat.resultats_globaux.total_credits)}</td>
                <td><span class="badge ${bgClass}">${resultat.decision.libelle}</span></td>
                ${!isPreview ? `<td>
                    <button class="btn btn-sm btn-outline-primary" onclick="voirDetails('${resultat.matricule}')">
                        <i class="bi bi-eye"></i> Détails
                    </button>
                </td>` : ''}
            </tr>
        `;
    });
    
    tableauHTML += '</tbody></table>';
    document.getElementById('tableau-resultats').innerHTML = tableauHTML;
}

function afficherEtudiantPourValidation() {
    if (etudiantActuelIndex >= resultatsDeliberation.resultats.length) {
        // Tous les étudiants ont été traités
        Swal.fire('Terminé', 'Tous les étudiants ont été traités', 'success');
        afficherResultats(resultatsDeliberation);
        return;
    }
    
    const resultat = resultatsDeliberation.resultats[etudiantActuelIndex];
    
    // Mise à jour de la barre de progression
    const progress = ((etudiantActuelIndex + 1) / resultatsDeliberation.resultats.length) * 100;
    document.getElementById('progress-bar').style.width = progress + '%';
    document.getElementById('progress-text').textContent = `${etudiantActuelIndex + 1}/${resultatsDeliberation.resultats.length} étudiants traités`;
    
    // Afficher les détails de l'étudiant dans le modal
    document.getElementById('etudiant-details').innerHTML = `
        <h6>Étudiant: ${resultat.etudiant.noms}</h6>
        <p><strong>Matricule:</strong> ${resultat.matricule}</p>
        <p><strong>Moyenne générale:</strong> ${resultat.resultats_globaux.moyenne_generale ? cleanFloat(resultat.resultats_globaux.moyenne_generale) : 'N/A'}</p>
        <p><strong>Crédits validés:</strong> ${cleanFloat(resultat.resultats_globaux.credits_valides)}/${cleanFloat(resultat.resultats_globaux.total_credits)}</p>
    `;
    
    const bgClass = getBadgeClass(resultat.decision.code);
    document.getElementById('decision-proposee').innerHTML = `
        <h6>Décision proposée</h6>
        <p><span class="badge ${bgClass}">${resultat.decision.libelle}</span></p>
        <p><em>${resultat.decision.description}</em></p>
    `;
    
    // Réinitialiser le modal
    document.getElementById('decision-manuelle').style.display = 'none';
    document.getElementById('decision-select').value = resultat.decision.code;
    document.getElementById('motif-decision').value = '';
    
    // Afficher le modal
    const modal = new bootstrap.Modal(document.getElementById('validationModal'));
    modal.show();
}

function toggleDecisionManuelle() {
    const decisionManuelle = document.getElementById('decision-manuelle');
    decisionManuelle.style.display = decisionManuelle.style.display === 'none' ? 'block' : 'none';
}

function validerDecision() {
    // Passer à l'étudiant suivant
    etudiantActuelIndex++;
    bootstrap.Modal.getInstance(document.getElementById('validationModal')).hide();
    
    // Petite pause pour l'effet visuel
    setTimeout(() => {
        afficherEtudiantPourValidation();
    }, 300);
}

function getBadgeClass(decisionCode) {
    switch (decisionCode) {
        case 'ADMIS_SANS_RACHAT': return 'bg-success';
        case 'ADMIS_AVEC_RACHAT': return 'bg-info';
        case 'ADMIS_RATTRAPAGE': return 'bg-warning';
        case 'AJOURNE': return 'bg-danger';
        case 'VALIDE_TOTALEMENT': return 'bg-success';
        case 'VALIDE_PARTIELLEMENT': return 'bg-warning';
        case 'NON_VALIDE': return 'bg-danger';
        case 'INCOMPLET': return 'bg-secondary';
        default: return 'bg-dark';
    }
}

function voirDetails(matricule) {
    const resultat = resultatsDeliberation.resultats.find(r => r.matricule === matricule);
    if (!resultat) return;
    
    // Ouvrir une nouvelle fenêtre avec les détails du bulletin
    const url = `controller/export_bulletin_individuel.php?matricule=${matricule}&bureau=${bureauId}&promotion=${promotionId}&session=${sessionId}&annee=${anneeId}&deux_semestres=1`;
    window.open(url, '_blank');
}

function exporterResultats() {
    // Créer un export Excel des résultats
    window.location.href = `controller/export_resultats_deliberation.php?deliberation_id=${deliberationId}`;
}

function retourSeances() {
    window.location.href = 'index.php?view=deliberation/seances';
}
</script>

<?php include "./views/include/footer.php"; ?>
