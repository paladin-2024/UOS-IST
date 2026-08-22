<?php
include "./views/include/header.php";

$universite = new Universite();
$agent = new Agent();
$ecue = new Ecue();
$deliberation = new Deliberation();

// Vérifier si l'utilisateur est administrateur ou responsable de section
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$userId = $_SESSION['id'];
$agentId = $agent->getAgentIdByUserId($userId);
$isResponsableSection = false;
$sectionsResponsable = [];

// Récupérer les sections dont l'agent est responsable
if ($agentId) {
    $sectionsResponsable = $deliberation->getSectionsResponsable($userId);
    $isResponsableSection = !empty($sectionsResponsable);
}

// Rediriger si l'utilisateur n'a pas les droits
if (!$isAdmin && !$isResponsableSection) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas les droits pour accéder à cette page.'
        }).then(() => {
            window.location.href = 'index';
        });
    </script>";
    exit();
}

// Récupérer les paramètres de sélection
$bureauId = isset($_GET['bureau']) ? intval($_GET['bureau']) : 1;
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$semestreId = isset($_GET['semestre']) ? intval($_GET['semestre']) : 0;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$afficherDeuxSemestres = isset($_GET['deux_semestres']) && $_GET['deux_semestres'] == 1;

// Récupérer l'information sur la session (première ou deuxième)
$sessionInfo = [];
if ($sessionId) {
    $sessionInfo = $universite->getSessionById($sessionId);
}
$isDeuxiemeSession = $sessionInfo && stripos($sessionInfo['designSession'], 'deuxième') !== false;

// Récupérer les données pour les sélecteurs
if ($isAdmin) {
    $bureaux = $deliberation->getJurys('', true); // Tous les jurys actifs
} else {
    // Seulement les jurys des sections dont l'agent est responsable
    $bureaux = $deliberation->getJurysBySections($sectionsResponsable);
}



// Récupérer les promotions associées au bureau sélectionné
$promotions = $deliberation->getAllPromotions();

// Récupérer les semestres associés à la promotion sélectionnée
$semestres = [];
if ($promotionId) {
    $semestres = $deliberation->getSemestresByPromotion($promotionId);
}

// Récupérer les sessions et années académiques
$sessions = $deliberation->getAllSessions();
$annees = $deliberation->getAcademicYears();

// Récupérer les étudiants si tous les paramètres sont sélectionnés
$etudiants = [];
$nomSemestre = '';

if ($bureauId && $promotionId && $sessionId && $anneeId && ($semestreId || $afficherDeuxSemestres)) {
    // Récupérer les semestres à afficher
    $semestresToShow = $afficherDeuxSemestres ? $semestres : array_values(array_filter($semestres, function ($sem) use ($semestreId) {
        return $sem['idsemestre'] == $semestreId;
    }));

   
    // Récupérer les étudiants de la promotion
    if ($isDeuxiemeSession) {
        // En deuxième session, ne récupérer que les étudiants éligibles
        $etudiants = $deliberation->getEtudiantsEligiblesDeuxiemeSession($promotionId, $anneeId, $semestresToShow);
    } else {
        // En première session, récupérer tous les étudiants
        $etudiants = $deliberation->getEtudiantsByPromotion($promotionId, $anneeId);
    }
    
    if (!empty($semestresToShow) && isset($semestresToShow[0]) && is_array($semestresToShow[0]) && isset($semestresToShow[0]['idsemestre'])) {
        $semId = $semestresToShow[0]['idsemestre'];
        $semestre = $universite->getSemestreById($semId);
        $nomSemestre = $semestre && isset($semestre['numeroSemestre']) ? $semestre['numeroSemestre'] : '';
    } else {
        $nomSemestre = 'sélectionné';
    }
}
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Documents des Étudiants</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Délibération</li>
                <li class="breadcrumb-item active">Documents des étudiants</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- 1. SÉLECTION DES PARAMÈTRES -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-gear-fill me-2"></i>
                            Sélection des paramètres
                        </h5>

                        <form method="GET" action="" class="row g-3 mb-4">
                            <input type="hidden" name="view" value="etudiants/documents_etudiants">

                                <div class="col-md-3">
                                    <label for="promotion" class="form-label">Promotion</label>
                                    <select name="promotion" id="promotion" class="form-select" required onchange="this.form.submit()">
                                        <option value="">Sélectionner une promotion</option>
                                        <?php foreach ($promotions as $promotion): ?>
                                            <option value="<?= $promotion['idpromotion'] ?>" <?= ($promotionId == $promotion['idpromotion']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($promotion['designationPromotion'])."-". $promotion['annee_acad'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <?php if ($promotionId): ?>
                                    <div class="col-md-3">
                                        <label for="semestre" class="form-label">Semestre</label>
                                        <select name="semestre" id="semestre" class="form-select" <?= $afficherDeuxSemestres ? 'disabled' : 'required' ?> onchange="toggleDeuxSemestres(false); this.form.submit();">
                                            <option value="">Sélectionner un semestre</option>
                                            <?php foreach ($semestres as $semestre): ?>
                                                <option value="<?= $semestre['idsemestre'] ?>" <?= ($semestreId == $semestre['idsemestre']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($semestre['numeroSemestre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" id="deux_semestres" name="deux_semestres" value="1" <?= $afficherDeuxSemestres ? 'checked' : '' ?> onchange="toggleDeuxSemestres(this.checked); this.form.submit();">
                                            <label class="form-check-label" for="deux_semestres">
                                                Afficher les deux semestres
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <label for="session" class="form-label">Session</label>
                                        <select name="session" id="session" class="form-select" required onchange="this.form.submit()">
                                            <option value="">Sélectionner une session</option>
                                            <?php foreach ($sessions as $session): ?>
                                                <option value="<?= $session['idsession'] ?>" <?= ($sessionId == $session['idsession']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($session['description']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <label for="annee" class="form-label">Année Académique</label>
                                        <select name="annee" id="annee" class="form-select" required onchange="this.form.submit()">
                                            <option value="">Sélectionner une année</option>
                                            <?php foreach ($annees as $annee): ?>
                                                <option value="<?= $annee['idannee_acad'] ?>" <?= ($anneeId == $annee['idannee_acad']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($annee['designation']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                            <?php if ($bureauId && $promotionId && ($semestreId || $afficherDeuxSemestres) && $sessionId && $anneeId): ?>
                                <div class="col-12 text-center">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search me-1"></i> Afficher
                                    </button>
                                    <button type="button" class="btn btn-success" onclick="genererTousDocuments('releve')">
                                        <i class="bi bi-file-earmark-text me-1"></i> Relevés
                                    </button>
                                    <button type="button" class="btn btn-info" onclick="genererTousDocuments('fiche')">
                                        <i class="bi bi-file-earmark-check me-1"></i> Fiches de validation
                                    </button>
                                    <!-- Bouton d'export du palmarès à ajouter dans votre vue de délibération -->
                                    <a href="controller/export_palmares.php?bureau=<?= $bureauId ?>&promotion=<?= $promotionId ?>&semestre=<?= $semestreId ?>&session=<?= $sessionId ?>&annee=<?= $anneeId ?>&deux_semestres=<?= $afficherDeuxSemestres ? '1' : '0' ?>" 
                                        class="btn btn-success me-2" target="_blank">
                                            <i class="fas fa-trophy me-1"></i> Palmarès
                                        </a>

                                    <a href="?view=etudiants/documents_etudiants" class="btn btn-secondary">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Réinitialiser
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="col-12">
                                    <a href="?view=etudiants/documents_etudiants" class="btn btn-secondary">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Réinitialiser
                                    </a>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <?php if ($bureauId && $promotionId && ($semestreId || $afficherDeuxSemestres) && $sessionId && $anneeId && !empty($etudiants)): ?>
                <!-- 2. LISTE DES ÉTUDIANTS -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-people me-2"></i>
                                Liste des étudiants - <?= $afficherDeuxSemestres ? 'Année complète' : 'Semestre ' . $nomSemestre ?>
                            </h5>

                            <!-- Options de filtrage et tri -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" id="searchEtudiants" placeholder="Rechercher un étudiant...">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-outline-secondary" id="resetFilters">
                                        <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser filtres
                                    </button>
                                </div>
                            </div>

                            <!-- Tableau des étudiants -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped" id="tableEtudiants">
                                    <thead class="table-primary">
                                        <tr>
                                            <th>#</th>
                                            <th>Matricule</th>
                                            <th>Nom et prénom</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($etudiants as $index => $etudiant): ?>
                                            <tr data-matricule="<?= $etudiant['matricule'] ?>">
                                                <td><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars($etudiant['matricule']) ?></td>
                                                <td><?= htmlspecialchars($etudiant['noms']) ?></td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-primary" onclick="genererDocument('releve', '<?= $etudiant['matricule'] ?>')" title="Générer le relevé de notes">
                                                    <i class="bi bi-file-earmark-text"></i> Relevé
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-success" onclick="genererDocument('fiche', '<?= $etudiant['matricule'] ?>')" title="Générer la fiche de validation">
                                                        <i class="bi bi-file-earmark-check"></i> Fiche
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Message si aucune donnée n'est disponible -->
                <?php if ($bureauId && $promotionId && ($semestreId || $afficherDeuxSemestres) && $sessionId && $anneeId): ?>
                    <div class="col-lg-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Aucun étudiant disponible pour les paramètres sélectionnés. Veuillez vérifier que des étudiants sont inscrits pour cette promotion et cette année académique.
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<!-- Modal de chargement -->
<div class="modal fade" id="loadingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <h5 id="loadingModalLabel">Génération du document en cours...</h5>
                <p class="text-muted">Veuillez patienter pendant la préparation de votre document.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour les options de génération en masse -->
<div class="modal fade" id="optionsDocumentsModal" tabindex="-1" aria-labelledby="optionsDocumentsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="optionsDocumentsModalLabel">Options de génération</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="optionsDocumentsForm" action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="documentType" name="documentType" value="">
                    <input type="hidden" name="bureau" value="<?= $bureauId ?>">
                    <input type="hidden" name="promotion" value="<?= $promotionId ?>">
                    <input type="hidden" name="semestre" value="<?= $semestreId ?>">
                    <input type="hidden" name="deux_semestres" value="<?= $afficherDeuxSemestres ? '1' : '0' ?>">
                    <input type="hidden" name="session" value="<?= $sessionId ?>">
                    <input type="hidden" name="annee" value="<?= $anneeId ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Format de sortie</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="format" id="formatPDF" value="pdf" checked>
                            <label class="form-check-label" for="formatPDF">
                                <i class="bi bi-file-pdf me-1"></i> PDF (un fichier par étudiant)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="format" id="formatZip" value="zip">
                            <label class="form-check-label" for="formatZip">
                                <i class="bi bi-file-zip me-1"></i> Archive ZIP (tous les documents)
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Options</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="inclure_logo" id="incluireLogo" value="1" checked>
                            <label class="form-check-label" for="incluireLogo">
                                Inclure le logo de l'établissement
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="inclure_signature" id="incluireSignature" value="1" checked>
                            <label class="form-check-label" for="incluireSignature">
                                Inclure la signature du président du jury
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i> Générer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Fonction pour basculer entre l'affichage d'un semestre ou des deux semestres
    function toggleDeuxSemestres(checked) {
        const semestreSelect = document.getElementById('semestre');
        if (checked) {
            semestreSelect.disabled = true;
            semestreSelect.value = '';
        } else {
            semestreSelect.disabled = false;
        }
    }

    // Fonction pour générer un document individuel
    function genererDocument(type, matricule) {
        // Afficher le modal de chargement
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
        loadingModal.show();

        // Construire l'URL en fonction du type de document
        let url = '';
        switch(type) {
            case 'releve':
                url = 'controller/export_releve_notes.php';
                break;
            case 'fiche':
                url = 'controller/export_bulletin_individuel.php';
                break;
            default:
                console.error('Type de document non reconnu');
                loadingModal.hide();
                return;
        }

        // Ajouter les paramètres à l'URL
        const params = new URLSearchParams();
        params.append('matricule', matricule);
        params.append('bureau', <?= $bureauId ?>);
        params.append('promotion', <?= $promotionId ?>);
        params.append('semestre', <?= $semestreId ?>);
        params.append('deux_semestres', <?= $afficherDeuxSemestres ? '1' : '0' ?>);
        params.append('session', <?= $sessionId ?>);
        params.append('annee', <?= $anneeId ?>);

        // Ouvrir le document dans un nouvel onglet
        window.open(url + '?' + params.toString(), '_blank');

        // Fermer le modal après un court délai
        // Recharger la page après un court délai
        setTimeout(() => {
                    location.reload();
                }, 1000);
    }

    // Fonction pour générer des documents en masse
    function genererTousDocuments(type) {
        // Définir le type de document dans le formulaire
        document.getElementById('documentType').value = type;
        
        // Mettre à jour le titre du modal en fonction du type
        let modalTitle = '';
        switch(type) {
            case 'releve':
                modalTitle = 'Génération des relevés de notes';
                document.getElementById('optionsDocumentsForm').action = 'controller/export_releves_masse.php';
                break;
            case 'fiche':
                modalTitle = 'Génération des fiches de validation';
                document.getElementById('optionsDocumentsForm').action = 'controller/export_bulletins_groupe.php';
                break;
            default:
                console.error('Type de document non reconnu');
                return;
        }
        
        document.getElementById('optionsDocumentsModalLabel').textContent = modalTitle;
        
        // Afficher le modal d'options
        const optionsModal = new bootstrap.Modal(document.getElementById('optionsDocumentsModal'));
        optionsModal.show();
    }

    // Attendre que le DOM soit complètement chargé
    document.addEventListener('DOMContentLoaded', function() {
        // Fonction pour rechercher des étudiants
        const searchInput = document.getElementById('searchEtudiants');
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const searchText = this.value.toLowerCase();
                const rows = document.querySelectorAll('#tableEtudiants tbody tr');

                rows.forEach(row => {
                    const matricule = row.getAttribute('data-matricule');
                    const nom = row.cells[2].textContent.toLowerCase();

                    if (matricule.includes(searchText) || nom.includes(searchText)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        // Fonction pour réinitialiser les filtres
        const resetButton = document.getElementById('resetFilters');
        if (resetButton) {
            resetButton.addEventListener('click', function() {
                // Réinitialiser la recherche
                const searchInput = document.getElementById('searchEtudiants');
                if (searchInput) {
                    searchInput.value = '';
                }

                // Réafficher tous les étudiants
                const rows = document.querySelectorAll('#tableEtudiants tbody tr');
                rows.forEach(row => {
                    row.style.display = '';
                });
            });
        }

        // Gestion du formulaire d'options de documents
        const optionsForm = document.getElementById('optionsDocumentsForm');
        if (optionsForm) {
            optionsForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Fermer le modal d'options
                const optionsModal = bootstrap.Modal.getInstance(document.getElementById('optionsDocumentsModal'));
                optionsModal.hide();
                
                // Afficher le modal de chargement
                const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
                document.getElementById('loadingModalLabel').textContent = 'Génération des documents en cours...';
                loadingModal.show();
                
                // Soumettre le formulaire
                const formData = new FormData(this);
                const url = this.action + '?' + new URLSearchParams(formData).toString();
                
                // Ouvrir dans un nouvel onglet
                window.open(url, '_blank');
                
                // Fermer le modal après un délai
                setTimeout(() => {
                    location.reload();
                }, 1000);
            });
        }
    });
</script>

<?php include "./views/include/footer.php"; ?>

