<?php
include "./views/include/header.php";

$universite = new Universite();
$agent = new Agent();

// Récupérer l'ID du bureau de jury si spécifié
$idBureau = isset($_GET['bureau']) ? intval($_GET['bureau']) : 0;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;

// Vérifier si l'utilisateur est administrateur ou président d'un jury
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$userId = $_SESSION['id'];
$agentId = $agent->getAgentIdByUserId($userId);
$isJuryPresident = false;

// Récupérer les bureaux de jury actifs
if ($isAdmin) {
    $bureaux = $universite->getJurys('', true); // Tous les jurys actifs
} else {
    $bureaux = $jurysPresides; // Seulement les jurys où l'agent est président
}

// Récupérer les sessions et années académiques
$sessions = $universite->getAllSessions();
$annees = $universite->getAcademicYears();

// Récupérer la configuration existante si un jury est sélectionné
$configDeliberation = null;
if ($idBureau && $sessionId && $anneeId) {
    $configDeliberation = $universite->getDeliberationConfig($idBureau, $sessionId, $anneeId);
}

// Récupérer les promotions associées au jury sélectionné
$promotionsJury = [];
if ($idBureau) {
    $promotionsJury = $universite->getPromotionsByJury($idBureau);
}
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Configuration des Critères de Délibération</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Délibération</li>
                <li class="breadcrumb-item active">Configuration des critères</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Configuration des critères -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Sélection du jury et de la session</h5>

                        <!-- Sélecteur de jury, session et année -->
                        <form method="GET" action="" class="row g-3 mb-4">
                            <div class="col-md-4">
                                <input type="hidden" name="view" value="deliberation/config_deliberation">
                                <label for="bureau" class="form-label">Bureau de Jury</label>
                                <select name="bureau" id="bureau" class="form-select" required>
                                    <option value="">Sélectionner un bureau</option>
                                    <?php foreach ($bureaux as $bureau): ?>
                                        <option value="<?= $bureau['idbureau'] ?>" <?= ($idBureau == $bureau['idbureau']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($bureau['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="session" class="form-label">Session</label>
                                <select name="session" id="session" class="form-select" required>
                                    <option value="">Sélectionner une session</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?= $session['idsession'] ?>" <?= ($sessionId == $session['idsession']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($session['description']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="annee" class="form-label">Année Académique</label>
                                <select name="annee" id="annee" class="form-select" required>
                                    <option value="">Sélectionner une année</option>
                                    <?php foreach ($annees as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" <?= ($anneeId == $annee['idannee_acad']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Charger la configuration
                                </button>
                            </div>
                        </form>

                        <?php if ($idBureau && $sessionId && $anneeId): ?>
                            <!-- Affichage des promotions associées -->
                            <div class="alert alert-info mb-4">
                                <h6><i class="bi bi-info-circle me-2"></i>Promotions gérées par ce jury:</h6>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <?php if (empty($promotionsJury)): ?>
                                        <span class="badge bg-secondary">Aucune promotion associée</span>
                                    <?php else: ?>
                                        <?php foreach ($promotionsJury as $promotion): ?>
                                            <span class="badge bg-primary"><?= htmlspecialchars($promotion['designationPromotion']) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Formulaire de configuration des critères -->
                            <form method="POST" action="controller/config_deliberation.php" class="needs-validation" novalidate>
                                <input type="hidden" name="idbureau" value="<?= $idBureau ?>">
                                <input type="hidden" name="session_id" value="<?= $sessionId ?>">
                                <input type="hidden" name="annee_id" value="<?= $anneeId ?>">

                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Règles de compensation</h5>
                                        
                                        <!-- Compensation intra-UE -->
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <div class="form-check form-switch">
                                                    <!-- Ligne 139 -->
                                                    <input class="form-check-input" type="checkbox" id="compensation_intra_ue" name="compensation_intra_ue" 
                                                        <?= (is_array($configDeliberation) && isset($configDeliberation['compensation_intra_ue']) && $configDeliberation['compensation_intra_ue']) ? 'checked' : '' ?>>

                                                    <label class="form-check-label" for="compensation_intra_ue">
                                                        Activer la compensation intra-UE
                                                    </label>
                                                </div>
                                                <div class="form-text">Permettre la compensation entre ECUE d'une même UE</div>
                                            </div>
                                            <div class="col-md-8">
                                                <label for="seuil_compensation_intra_ue" class="form-label">Seuil minimal pour la compensation intra-UE</label>
                                                <div class="input-group">
                                                    <!-- Ligne 163 -->
                                                    <input type="number" class="form-control" id="seuil_compensation_intra_ue" name="seuil_compensation_intra_ue" 
                                                        min="0" max="20" step="0.01" 
                                                        value="<?= is_array($configDeliberation) && isset($configDeliberation['seuil_compensation_intra_ue']) ? $configDeliberation['seuil_compensation_intra_ue'] : 8.00 ?>">

                                                    <span class="input-group-text">/20</span>
                                                </div>
                                                <div class="form-text">Note minimale requise pour qu'un ECUE puisse être compensé</div>
                                            </div>
                                        </div>

                                        <!-- Compensation inter-UE -->
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <div class="form-check form-switch">
                                                    <!-- Ligne 186 -->
                                                    <input class="form-check-input" type="checkbox" id="compensation_inter_ue" name="compensation_inter_ue" 
                                                            <?= (is_array($configDeliberation) && isset($configDeliberation['compensation_inter_ue']) && $configDeliberation['compensation_inter_ue']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="compensation_inter_ue">
                                                        Activer la compensation inter-UE
                                                    </label>
                                                </div>
                                                <div class="form-text">Permettre la compensation entre UE d'un même semestre</div>
                                            </div>
                                            <div class="col-md-8">
                                                <label for="seuil_compensation_inter_ue" class="form-label">Seuil minimal pour la compensation inter-UE</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="seuil_compensation_inter_ue" name="seuil_compensation_inter_ue" min="0" max="20" step="0.01" value="<?= is_array($configDeliberation) && isset($configDeliberation['seuil_compensation_inter_ue']) ? $configDeliberation['seuil_compensation_inter_ue'] : 8.00 ?>">
                                                    <span class="input-group-text">/20</span>
                                                </div>
                                                <div class="form-text">Note minimale requise pour qu'une UE puisse être compensée</div>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-12">
                                                <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="exiger_meme_credit_ue" name="exiger_meme_credit_ue" 
                                                    <?= (is_array($configDeliberation) && isset($configDeliberation['exiger_meme_credit_ue']) && $configDeliberation['exiger_meme_credit_ue']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="exiger_meme_credit_ue">
                                                        Exiger le même nombre de crédits pour la compensation entre UE
                                                    </label>
                                                </div>
                                                <div class="form-text">Si activé, seules les UE ayant le même nombre de crédits pourront être compensées entre elles</div>
                                            </div>
                                        </div>

                                        <!-- Compensation inter-semestre -->
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="compensation_inter_semestre" name="compensation_inter_semestre" 
                                                        <?= (isset($configDeliberation) && $configDeliberation['compensation_inter_semestre']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="compensation_inter_semestre">
                                                        Activer la compensation inter-semestre
                                                    </label>
                                                </div>
                                                <div class="form-text">Permettre la compensation entre UE de semestres différents</div>
                                            </div>
                                            <div class="col-md-8">
                                                <label for="seuil_compensation_inter_semestre" class="form-label">Seuil minimal pour la compensation inter-semestre</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="seuil_compensation_inter_semestre" name="seuil_compensation_inter_semestre" 
                                                        min="0" max="20" step="0.01" 
                                                        value="<?= isset($configDeliberation) ? $configDeliberation['seuil_compensation_inter_semestre'] : 8.00 ?>">
                                                    <span class="input-group-text">/20</span>
                                                </div>
                                                <div class="form-text">Note minimale requise pour qu'une UE puisse être compensée entre semestres</div>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-12">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="limiter_compensation_annee" name="limiter_compensation_annee" 
                                                        <?= (isset($configDeliberation) && $configDeliberation['limiter_compensation_annee']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="limiter_compensation_annee">
                                                        Limiter la compensation aux semestres de la même année académique
                                                    </label>
                                                </div>
                                                <div class="form-text">Si activé, la compensation inter-semestre ne sera possible qu'entre semestres de la même année académique</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mt-4">
                                    <div class="card-body">
                                        <h5 class="card-title">Règles de validation</h5>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="note_passage" class="form-label">Note minimale de passage (ECUE/UE)</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="note_passage" name="note_passage" 
                                                        min="0" max="20" step="0.01" 
                                                        value="<?= isset($configDeliberation) ? $configDeliberation['note_passage'] : 10.00 ?>">
                                                    <span class="input-group-text">/20</span>
                                                </div>
                                                <div class="form-text">Note minimale pour valider un ECUE ou une UE sans compensation</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="pourcentage_passage_semestre" class="form-label">Pourcentage minimal pour valider un semestre</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="pourcentage_passage_semestre" name="pourcentage_passage_semestre" 
                                                        min="0" max="100" step="0.01" 
                                                        value="<?= isset($configDeliberation) ? $configDeliberation['pourcentage_passage_semestre'] : 50.00 ?>">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                                <div class="form-text">Pourcentage minimal de crédits à obtenir pour valider un semestre</div>
                                            </div>
                                            <!-- Après le bloc pourcentage_passage_semestre -->
                                            <div class="row mb-3">
                                                <div class="col-md-12">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="calculer_moyenne_avec_notes_vides" name="calculer_moyenne_avec_notes_vides" 
                                                            <?= (isset($configDeliberation) && isset($configDeliberation['calculer_moyenne_avec_notes_vides']) && $configDeliberation['calculer_moyenne_avec_notes_vides']) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="calculer_moyenne_avec_notes_vides">
                                                            Calculer la moyenne même avec des notes manquantes
                                                        </label>
                                                    </div>
                                                    <div class="form-text">Si activé, le système calculera les moyennes même si certaines notes sont manquantes. Sinon, une note manquante empêchera le calcul de la moyenne.</div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>

                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Enregistrer la configuration
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Veuillez sélectionner un bureau de jury, une session et une année académique pour configurer les critères de délibération.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    // Script pour activer/désactiver les champs en fonction des options sélectionnées
    document.addEventListener('DOMContentLoaded', function() {
        const compensationIntraUe = document.getElementById('compensation_intra_ue');
        const seuilCompensationIntraUe = document.getElementById('seuil_compensation_intra_ue');
        const compensationInterUe = document.getElementById('compensation_inter_ue');
        const seuilCompensationInterUe = document.getElementById('seuil_compensation_inter_ue');
        const exigerMemeCredit = document.getElementById('exiger_meme_credit_ue');
        const compensationInterSemestre = document.getElementById('compensation_inter_semestre');
        const seuilCompensationInterSemestre = document.getElementById('seuil_compensation_inter_semestre');
        const limiterCompensationAnnee = document.getElementById('limiter_compensation_annee');

        // Fonction pour mettre à jour l'état activé/désactivé des champs
        function updateFieldStates() {
            if (compensationIntraUe) {
                seuilCompensationIntraUe.disabled = !compensationIntraUe.checked;
            }
            
            if (compensationInterUe) {
                seuilCompensationInterUe.disabled = !compensationInterUe.checked;
                exigerMemeCredit.disabled = !compensationInterUe.checked;
            }
            
            if (compensationInterSemestre) {
                seuilCompensationInterSemestre.disabled = !compensationInterSemestre.checked;
                limiterCompensationAnnee.disabled = !compensationInterSemestre.checked;
            }
        }

        // Ajouter des écouteurs d'événements
        if (compensationIntraUe) {
            compensationIntraUe.addEventListener('change', updateFieldStates);
        }
        
        if (compensationInterUe) {
            compensationInterUe.addEventListener('change', updateFieldStates);
        }
        
        if (compensationInterSemestre) {
            compensationInterSemestre.addEventListener('change', updateFieldStates);
        }

        // Initialiser l'état des champs au chargement de la page
        updateFieldStates();
    });
</script>

<?php include "./views/include/footer.php"; ?>

