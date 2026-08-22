<?php
include "./views/include/header.php";

$grilleAncienne = new GrilleAncienne();
$universite = new Universite();

/**
 * Calculer les moyennes exactement comme dans export_grille_ancienne.php avec logique de décision
 */
function calculerMoyennesCommeExport($grilleAncienne, $importId, $etudiants, $importInfo) {
    // 🔥 CORRECTION: Supprimer les doublons d'étudiants
    $etudiantsUniques = [];
    $etudiantsVus = [];
    
    foreach ($etudiants as $etudiant) {
        $matricule = $etudiant['matricule'];
        if (!isset($etudiantsVus[$matricule])) {
            $etudiantsUniques[] = $etudiant;
            $etudiantsVus[$matricule] = true;
        }
    }
    
    error_log("Étudiants reçus: " . count($etudiants) . ", Étudiants uniques: " . count($etudiantsUniques));
    
    // Déterminer le type de session (logique identique export_grille_ancienne.php)
    $isDeuxiemeSession = stripos($importInfo['session'], 'rattrapage') !== false || 
                         stripos($importInfo['session'], 'deuxième') !== false ||
                         stripos($importInfo['session'], 'deuxieme') !== false ||
                         stripos($importInfo['session'], '2ème') !== false ||
                         stripos($importInfo['session'], '2eme') !== false;
    
    // Récupérer les données nécessaires
    $ues = $grilleAncienne->getUEsByImport($importId);
    $ecues = $grilleAncienne->getECUEsByImport($importId);
    $notes = $grilleAncienne->getNotesByImport($importId);
    
    // Organiser les données
    $uesBySemestre = [];
    $ecuesByUE = [];
    $notesByEtudiantEcue = [];
    
    foreach ($ues as $ue) {
        $semestre = $ue['semestre'] ?? 'S1';
        $uesBySemestre[$semestre][] = $ue;
    }
    
    foreach ($ecues as $ecue) {
        $ecuesByUE[$ecue['ue_id']][] = $ecue;
    }
    
    foreach ($notes as $note) {
        $notesByEtudiantEcue[$note['etudiant_id']][$note['ecue_id']] = $note;
    }
    
    // Trier les semestres
    ksort($uesBySemestre);
    $afficherDeuxSemestres = count($uesBySemestre) > 1;
    
    // Calculer pour chaque étudiant UNIQUE
    $etudiantsAvecMoyennes = [];
    
    foreach ($etudiantsUniques as $etudiant) {
        $etudiantId = $etudiant['id'];
        $etudiantData = $etudiant;
        
        // Variables pour calculs annuels (EXACTE logique export_grille_ancienne.php)
        $moyennesSemestreEtudiant = [];
        $creditsSemestreEtudiant = [];
        $totalCreditsAnnuel = 0;
        $totalCreditsValidesAnnuel = 0;
        $totalMoyennesPonderees = 0;
        
        // Traitement pour chaque semestre
        foreach ($uesBySemestre as $semestreKey => $uesInSemestre) {
            $moyennesUESemestre = [];
            $creditsUESemestre = [];
            $totalCreditsSemestre = 0;
            $totalCreditsValidesSemestre = 0;
            
            // Traitement pour chaque UE
            foreach ($uesInSemestre as $ue) {
                $ueId = $ue['id'];
                $notesUE = [];
                $creditsECUE = [];
                $totalCreditsUE = 0;
                $hasAllNotes = true;
                
                // 🔥 CORRECTION: Calculer d'abord tous les crédits de l'UE (même sans notes)
                if (isset($ecuesByUE[$ueId])) {
                    foreach ($ecuesByUE[$ueId] as $ecue) {
                        $totalCreditsUE += floatval($ecue['coefficient']);
                    }
                }
                
                // Remplir les notes des ECUE
                if (isset($ecuesByUE[$ueId])) {
                    foreach ($ecuesByUE[$ueId] as $ecue) {
                        $ecueId = $ecue['id'];
                        
                        if (isset($notesByEtudiantEcue[$etudiantId][$ecueId])) {
                            $note = $notesByEtudiantEcue[$etudiantId][$ecueId]['note_finale'];
                            $notesUE[] = $note;
                            $creditsECUE[] = floatval($ecue['coefficient']);
                        } else {
                            // Pas de note - cellule vide
                            $hasAllNotes = false;
                        }
                    }
                }
                
                // 🔥 IMPORTANT: Toujours compter les crédits UE dans le total semestre
                $totalCreditsSemestre += $totalCreditsUE;
                
                // Calculer la moyenne UE (seulement si toutes les notes sont présentes)
                if ($hasAllNotes && count($notesUE) > 0) {
                    $moyenneUE = calculerMoyennePonderee($notesUE, $creditsECUE);
                    $moyennesUESemestre[] = $moyenneUE;
                    $creditsUESemestre[] = $totalCreditsUE;
                    
                    // 🔥 CORRECTION: Capitalisation au niveau UE entière
                    if ($moyenneUE >= 10) {
                        $totalCreditsValidesSemestre += $totalCreditsUE; // TOUS les crédits de l'UE validée
                    }
                    // Si moyenne UE < 10, aucun crédit de cette UE n'est validé
                } else {
                    // 🔥 CORRECTION: Pas toutes les notes - UE non validée - 0 crédit validé
                    // totalCreditsUE est compté dans totalCreditsSemestre mais pas dans totalCreditsValidesSemestre
                }
            }
            
            // Calculer moyenne semestre
            if (count($moyennesUESemestre) === count($uesInSemestre)) {
                // Toutes les UE ont une moyenne - calculer moyenne semestre
                $moyenneSemestre = calculerMoyennePonderee($moyennesUESemestre, $creditsUESemestre);
                $moyennesSemestreEtudiant[] = $moyenneSemestre;
                $creditsSemestreEtudiant[] = $totalCreditsSemestre;
                $totalCreditsAnnuel += $totalCreditsSemestre;
                $totalCreditsValidesAnnuel += $totalCreditsValidesSemestre;
                $totalMoyennesPonderees += $moyenneSemestre * $totalCreditsSemestre;
            } else {
                // Pas toutes les UE - pas de moyenne mais afficher crédits
                $totalCreditsAnnuel += $totalCreditsSemestre;
                $totalCreditsValidesAnnuel += $totalCreditsValidesSemestre;
            }
        }
        
        // Calculer moyenne générale et décision
        if (count($moyennesSemestreEtudiant) === count($uesBySemestre)) {
            // Tous les semestres ont une moyenne
            $moyenneAnnuelle = $totalCreditsAnnuel > 0 ? $totalMoyennesPonderees / $totalCreditsAnnuel : 0;
            $etudiantData['moyenne_generale'] = $moyenneAnnuelle;
            $etudiantData['peut_calculer_moyenne'] = true;
            
            // Calculer la décision (EXACTE logique export_grille_ancienne.php)
            $etudiantNotesManquantes = false;
            foreach ($uesBySemestre as $semestreKey => $uesInSemestre) {
                foreach ($uesInSemestre as $ue) {
                    $ueId = $ue['id'];
                    if (isset($ecuesByUE[$ueId])) {
                        foreach ($ecuesByUE[$ueId] as $ecue) {
                            $ecueId = $ecue['id'];
                            if (!isset($notesByEtudiantEcue[$etudiantId][$ecueId])) {
                                $etudiantNotesManquantes = true;
                                break 3; // Sortir de toutes les boucles
                            }
                        }
                    }
                }
            }
            
            // Calculer le pourcentage de crédits validés
            $pourcentageCredits = $totalCreditsAnnuel > 0 ? ($totalCreditsValidesAnnuel / $totalCreditsAnnuel) * 100 : 0;
            $estValideGlobal = (!$etudiantNotesManquantes && $totalCreditsValidesAnnuel == $totalCreditsAnnuel && $moyenneAnnuelle >= 10);
            
            // Logique de décision selon le contexte (EXACTE export_grille_ancienne.php)
            $decision = '';
            if ($afficherDeuxSemestres) {
                // Pour les résultats annuels
                if ($etudiantNotesManquantes) {
                    $decision = 'INCOMPLET';
                } 
                else if ($isDeuxiemeSession) {
                    // En deuxième session
                    if ($estValideGlobal) {
                        $decision = 'ADMIS SANS RACHAT';
                    } else if ($pourcentageCredits >= 75 && $moyenneAnnuelle >= 10) {
                        $decision = 'ADMIS AVEC RACHAT';
                    } else {
                        $decision = 'AJOURNÉ';
                    }
                } 
                else {
                    // En première session
                    if ($estValideGlobal) {
                        $decision = 'ADMIS';
                    } else if ($pourcentageCredits >= 75 && $moyenneAnnuelle >= 10) {
                        $decision = 'AUTORISÉ EN 2ème SESSION';
                    } else {
                        $decision = 'AJOURNÉ';
                    }
                }
            } else {
                // Pour les résultats semestriels
                if ($etudiantNotesManquantes) {
                    $decision = 'INCOMPLET';
                } else if ($estValideGlobal) {
                    $decision = 'VALIDÉ';
                } else {
                    $decision = 'NON VALIDÉ';
                }
            }
            
            $etudiantData['decision'] = $decision;
            
        } else {
            // Ne peut pas calculer la moyenne générale - notes manquantes
            $etudiantData['moyenne_generale'] = null;
            $etudiantData['peut_calculer_moyenne'] = false;
            $etudiantData['decision'] = 'INCOMPLET';
        }
        
        $etudiantsAvecMoyennes[] = $etudiantData;
    }
    
    return $etudiantsAvecMoyennes;
}

/**
 * Calculer la moyenne pondérée - IDENTIQUE export_grille_ancienne.php
 */
function calculerMoyennePonderee($notes, $credits) {
    if (empty($notes) || empty($credits) || count($notes) !== count($credits)) {
        return 0;
    }
    
    $totalPondere = 0;
    $totalCredits = 0;
    
    for ($i = 0; $i < count($notes); $i++) {
        $totalPondere += $notes[$i] * $credits[$i];
        $totalCredits += $credits[$i];
    }
    
    return $totalCredits > 0 ? $totalPondere / $totalCredits : 0;
}

// Vérifier les droits d'accès
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
if (!$isAdmin) {
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
$importId = isset($_GET['import_id']) ? intval($_GET['import_id']) : 0;

// Récupérer les imports existants
$imports = $grilleAncienne->getAllImports();

// Récupérer les étudiants de l'import sélectionné
$etudiants = [];
$importInfo = null;
$etudiantsAvecMoyennes = [];
if ($importId) {
    $importInfo = $grilleAncienne->getImportById($importId);
    $etudiants = $grilleAncienne->getEtudiantsByImport($importId);
    
    // Calculer les moyennes avec la logique exacte d'export_grille_ancienne.php
    if (!empty($etudiants)) {
        $etudiantsAvecMoyennes = calculerMoyennesCommeExport($grilleAncienne, $importId, $etudiants, $importInfo);
    }
}
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Documents des Grilles Anciennes</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Délibération</li>
                <li class="breadcrumb-item"><a href="deliberation/grilles_anciennes">Grilles Anciennes</a></li>
                <li class="breadcrumb-item active">Documents</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- 1. SÉLECTION DE L'IMPORT -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-gear-fill me-2"></i>
                            Sélection de la grille
                        </h5>

                        <form method="GET" action="" class="row g-3 mb-4">
                            <input type="hidden" name="view" value="deliberation/documents_grilles_anciennes">

                            <div class="col-md-6">
                                <label for="import_id" class="form-label">Grille importée</label>
                                <select name="import_id" id="import_id" class="form-select" required onchange="this.form.submit()">
                                    <option value="">Sélectionner une grille</option>
                                    <?php foreach ($imports as $import): ?>
                                        <option value="<?= $import['id'] ?>" <?= ($importId == $import['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($import['promotion']) ?> - 
                                            <?= htmlspecialchars($import['annee_academique']) ?> - 
                                            <?= htmlspecialchars($import['session']) ?> - 
                                            <?= htmlspecialchars($import['semestre']) ?>
                                            (<?= $import['nombre_etudiants'] ?> étudiants)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($importId && $importInfo): ?>
                                <div class="col-md-6">
                                    <div class="card bg-light h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">Informations de la grille</h6>
                                            <div class="row text-center">
                                                <div class="col-3">
                                                    <div class="text-primary fw-bold"><?= $importInfo['nombre_etudiants'] ?></div>
                                                    <small class="text-muted">Étudiants</small>
                                                </div>
                                                <div class="col-3">
                                                    <div class="text-info fw-bold"><?= $importInfo['nombre_ues'] ?></div>
                                                    <small class="text-muted">UEs</small>
                                                </div>
                                                <div class="col-3">
                                                    <div class="text-secondary fw-bold"><?= $importInfo['nombre_ecues'] ?></div>
                                                    <small class="text-muted">ECUEs</small>
                                                </div>
                                                <div class="col-3">
                                                    <div class="text-success fw-bold"><?= date('d/m/Y', strtotime($importInfo['date_import'])) ?></div>
                                                    <small class="text-muted">Importé</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 text-center">
                                    <button type="button" class="btn btn-primary me-2" onclick="recalculerMoyennes()">
                                        <i class="bi bi-calculator me-1"></i> Recalculer les moyennes
                                    </button>
                                    <button type="button" class="btn btn-warning me-2" onclick="genererDocument('palmares')">
                                        <i class="bi bi-trophy me-1"></i> Palmarès
                                    </button>
                                    <a href="?view=deliberation/documents_grilles_anciennes" class="btn btn-secondary">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Réinitialiser
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="col-12">
                                    <a href="?view=deliberation/documents_grilles_anciennes" class="btn btn-secondary">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Réinitialiser
                                    </a>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <?php if ($importId && $importInfo && !empty($etudiantsAvecMoyennes)): ?>
                <!-- 2. LISTE DES ÉTUDIANTS -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-people me-2"></i>
                                Liste des étudiants - <?= htmlspecialchars($importInfo['promotion']) ?>
                                <span class="badge bg-secondary ms-2"><?= count($etudiantsAvecMoyennes) ?> étudiants</span>
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
                                        <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser
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
                                        <?php foreach ($etudiantsAvecMoyennes as $index => $etudiant): ?>
                                            <tr data-matricule="<?= $etudiant['matricule'] ?>">
                                                <td><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars($etudiant['matricule']) ?></td>
                                                <td><?= htmlspecialchars($etudiant['noms']) ?></td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-primary" onclick="genererDocumentIndividuel('releve', '<?= $etudiant['matricule'] ?>')" title="Générer le relevé de notes">
                                                    <i class="bi bi-file-earmark-text"></i> Relevé
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-success" onclick="genererDocumentIndividuel('fiche', '<?= $etudiant['matricule'] ?>')" title="Générer la fiche de validation">
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
                <?php if ($importId): ?>
                    <div class="col-lg-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Aucun étudiant trouvé pour la grille sélectionnée.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="col-lg-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Veuillez sélectionner une grille pour afficher les étudiants et générer les documents.
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
            <form id="optionsDocumentsForm" action="" method="GET">
                <div class="modal-body">
                    <input type="hidden" id="documentType" name="type" value="">
                    <input type="hidden" name="import_id" value="<?= $importId ?>">
                    
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
                                Inclure la signature du responsable
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
    // Fonction pour générer un document individuel
    function genererDocumentIndividuel(type, matricule) {
        // Afficher le modal de chargement
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
        loadingModal.show();

        // Construire l'URL en fonction du type de document
        let url = '';
        switch(type) {
            case 'releve':
                url = 'controller/export_releve_notes_ancienne.php';
                break;
            case 'fiche':
                url = 'controller/export_bulletin_individuel_ancien.php';
                break;
            default:
                console.error('Type de document non reconnu');
                loadingModal.hide();
                return;
        }

        // Ajouter les paramètres à l'URL
        const params = new URLSearchParams();
        params.append('matricule', matricule);
        params.append('import_id', <?= $importId ?>);

        // Ouvrir le document dans un nouvel onglet
        window.open(url + '?' + params.toString(), '_blank');

        // Fermer le modal après un court délai
        setTimeout(() => {
            loadingModal.hide();
        }, 2000);
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
                document.getElementById('optionsDocumentsForm').action = 'controller/export_releves_masse_ancienne.php';
                break;
            case 'fiche':
                modalTitle = 'Génération des fiches de validation';
                document.getElementById('optionsDocumentsForm').action = 'controller/export_bulletins_masse_ancienne.php';
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

    // Fonction pour générer le palmarès
    function genererDocument(type) {
        if (type === 'palmares') {
            // Afficher le modal de chargement
            const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
            loadingModal.show();

            // Ouvrir le palmarès dans un nouvel onglet
            window.open('controller/export_palmares_ancien.php?import_id=<?= $importId ?>', '_blank');

            // Fermer le modal après un court délai
            setTimeout(() => {
                loadingModal.hide();
            }, 2000);
        }
    }
    
    // Fonction pour recalculer les moyennes
    function recalculerMoyennes() {
        Swal.fire({
            title: 'Recalculer les moyennes ?',
            text: 'Cette action va recalculer toutes les moyennes selon la logique : un étudiant qui manque au moins une note sera automatiquement ajourné.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Oui, recalculer',
            cancelButtonText: 'Annuler',
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                // Afficher le modal de chargement
                const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
                document.getElementById('loadingModalLabel').textContent = 'Recalcul des moyennes en cours...';
                loadingModal.show();
                
                // Envoyer la requête AJAX
                fetch('controller/recalculer_moyennes_anciennes.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'import_id=<?= $importId ?>'
                })
                .then(response => response.json())
                .then(data => {
                    loadingModal.hide();
                    
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Moyennes recalculées',
                            text: data.message,
                            timer: 2000
                        }).then(() => {
                            // Recharger la page pour afficher les nouvelles moyennes
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: data.message || 'Erreur lors du recalcul des moyennes'
                        });
                    }
                })
                .catch(error => {
                    loadingModal.hide();
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Erreur de communication avec le serveur'
                    });
                });
            }
        });
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
                    loadingModal.hide();
                }, 3000);
            });
        }
    });
</script>

<?php include "./views/include/footer.php"; ?>
