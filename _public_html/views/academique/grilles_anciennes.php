<?php
include "./views/include/header.php";

$grilleAncienne = new GrilleAncienne();
$universite = new Universite();

// Vérifier les droits d'accès
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$userId = $_SESSION['id'] ?? null;

// Créer les tables si nécessaire
try {
    $grilleAncienne->createTablesIfNotExists();
} catch (Exception $e) {
    error_log("Erreur création tables grilles anciennes: " . $e->getMessage());
}

// Récupérer les imports selon les droits
if ($isAdmin) {
    // L'administrateur voit tous les imports
    $imports = $grilleAncienne->getAllImports();
} else {
    // Les autres utilisateurs voient seulement les imports de leurs sections
    $imports = $grilleAncienne->getImportsByUserSections($userId);
}

// Récupérer les sections pour le formulaire
$sections = $universite->getSections();

// Récupérer les données pour les formulaires
$annees = $universite->getAcademicYears();
$sessions = [
    ['id' => 'principale', 'nom' => 'Session Principale'],
    ['id' => 'rattrapage', 'nom' => 'Session de Rattrapage']
];
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Grilles Anciennes</h1>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                        <li class="breadcrumb-item">Délibération</li>
                        <li class="breadcrumb-item active">Grilles Anciennes</li>
                    </ol>
                </nav>
            </div>
            <div class="text-end">
                <?php if ($isAdmin): ?>
                    <span class="badge bg-danger fs-6">
                        <i class="bi bi-shield-lock-fill me-1"></i>
                        Mode Administrateur
                    </span>
                    <div class="text-muted small mt-1">
                        <i class="bi bi-eye-fill me-1"></i>
                        Visualisation de toutes les grilles
                    </div>
                <?php else: ?>
                    <span class="badge bg-primary fs-6">
                        <i class="bi bi-person-badge-fill me-1"></i>
                        Mode Responsable Section
                    </span>
                    <div class="text-muted small mt-1">
                        <i class="bi bi-funnel-fill me-1"></i>
                        Visualisation limitée à vos sections
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            
            <!-- 1. CARTE D'ACTIONS ET FILTRES -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-file-spreadsheet me-2"></i>
                            Gestion des Grilles Anciennes
                        </h5>
                        
                        <div class="row">
                            <!-- Actions principales -->
                            <div class="col-md-8">
                                <div class="row g-2">
                                    <div class="col-md-12">
                                        <div class="d-grid">
                                            <a href="deliberation/import_grilles_visuelles" class="btn btn-primary">
                                                <i class="bi bi-upload me-2"></i>
                                                Import Visuel (Nouveau)
                                            </a>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="d-grid">
                                            <a href="deliberation/documents_grilles_anciennes" class="btn btn-info">
                                                <i class="bi bi-file-earmark-text me-2"></i>
                                                Générer les documents
                                            </a>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="d-grid">
                                            <a href="deliberation/fees_fiche_validation" class="btn btn-warning">
                                                <i class="bi bi-cash-coin me-2"></i>
                                                Configurer les frais fiche de validation
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Statistiques -->
                            <div class="col-md-4">
                                <div class="card bg-light h-100">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Statistiques</h6>
                                        <div class="row">
                                            <div class="col-4">
                                                <div class="fs-4 text-primary"><?= count($imports) ?></div>
                                                <small class="text-muted">Grilles</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="fs-4 text-success"><?= array_sum(array_column($imports, 'nombre_etudiants')) ?></div>
                                                <small class="text-muted">Étudiants</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="fs-4 text-info"><?= array_sum(array_column($imports, 'nombre_ues')) ?></div>
                                                <small class="text-muted">UEs</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. FILTRES ET RECHERCHE -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-funnel me-2"></i>
                                Filtres et Recherche
                            </h5>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="reinitialiserFiltres()">
                                <i class="bi bi-arrow-clockwise me-1"></i>
                                Réinitialiser
                            </button>
                        </div>
                        
                        <div class="row g-3">
                            <!-- Recherche textuelle -->
                            <div class="col-md-4">
                                <label for="searchText" class="form-label">Recherche</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control" id="searchText" 
                                           placeholder="Promotion, année académique..." 
                                           oninput="appliquerFiltres()"
                                           onkeyup="appliquerFiltres()"
                                           onpaste="setTimeout(appliquerFiltres, 10)">
                                </div>
                            </div>
                            
                            <!-- Filtre par année académique -->
                            <div class="col-md-2">
                                <label for="filterAnnee" class="form-label">Année</label>
                                <select class="form-select" id="filterAnnee" onchange="appliquerFiltres(true)">
                                    <option value="">Toutes</option>
                                    <?php 
                                    $anneesUniques = array_unique(array_column($imports, 'annee_academique'));
                                    sort($anneesUniques);
                                    foreach ($anneesUniques as $annee): ?>
                                        <option value="<?= htmlspecialchars($annee) ?>"><?= htmlspecialchars($annee) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Filtre par session -->
                            <div class="col-md-2">
                                <label for="filterSession" class="form-label">Session</label>
                                <select class="form-select" id="filterSession" onchange="appliquerFiltres(true)">
                                    <option value="">Toutes</option>
                                    <option value="principale">Principale</option>
                                    <option value="rattrapage">Rattrapage</option>
                                </select>
                            </div>
                            
                            <!-- Filtre par semestre -->
                            <div class="col-md-2">
                                <label for="filterSemestre" class="form-label">Semestre</label>
                                <select class="form-select" id="filterSemestre" onchange="appliquerFiltres(true)">
                                    <option value="">Tous</option>
                                    <?php 
                                    $semestresUniques = array_unique(array_column($imports, 'semestre'));
                                    sort($semestresUniques);
                                    foreach ($semestresUniques as $semestre): 
                                        if (!empty($semestre)): ?>
                                            <option value="<?= htmlspecialchars($semestre) ?>"><?= htmlspecialchars($semestre) ?></option>
                                        <?php endif;
                                    endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Tri -->
                            <div class="col-md-2">
                                <label for="sortBy" class="form-label">Trier par</label>
                                <select class="form-select" id="sortBy" onchange="appliquerFiltres(true)">
                                    <option value="date_desc">Date (récent)</option>
                                    <option value="date_asc">Date (ancien)</option>
                                    <option value="promotion_asc">Promotion (A-Z)</option>
                                    <option value="annee_desc">Année (récent)</option>
                                    <option value="etudiants_desc">Nb étudiants</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. LISTE DES IMPORTS EXISTANTS -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-list-ul me-2"></i>
                                Grilles Importées
                                <span class="badge bg-secondary ms-2" id="countBadge"><?= count($imports) ?></span>
                            </h5>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary" onclick="toggleView('table')" id="btnTableView">
                                    <i class="bi bi-table"></i> Tableau
                                </button>
                                <button type="button" class="btn btn-outline-primary" onclick="toggleView('grid')" id="btnGridView">
                                    <i class="bi bi-grid-3x3-gap"></i> Grille
                                </button>
                            </div>
                        </div>

                        <?php if (empty($imports)): ?>
                            <div class="alert alert-info" id="emptyState">
                                <div class="text-center py-4">
                                    <i class="bi bi-inbox display-4 text-muted"></i>
                                    <h5 class="mt-3">Aucune grille importée</h5>
                                    <p class="text-muted">Commencez par importer votre première grille ancienne.</p>
                                    <a href="deliberation/import_grilles_visuelles" class="btn btn-primary">
                                        <i class="bi bi-upload me-2"></i>
                                        Importer une grille
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Vue tableau -->
                            <div class="table-responsive" id="tableView">
                                <table class="table table-striped table-hover" id="grillesTable">
                                    <thead class="table-primary">
                                        <tr>
                                            <th onclick="trierTable('annee_academique')" style="cursor: pointer;">
                                                Année Académique <i class="bi bi-arrow-down-up"></i>
                                            </th>
                                            <th onclick="trierTable('session')" style="cursor: pointer;">
                                                Session <i class="bi bi-arrow-down-up"></i>
                                            </th>
                                            <th onclick="trierTable('semestre')" style="cursor: pointer;">
                                                Semestre <i class="bi bi-arrow-down-up"></i>
                                            </th>
                                            <th onclick="trierTable('promotion')" style="cursor: pointer;">
                                                Promotion <i class="bi bi-arrow-down-up"></i>
                                            </th>
                                            <th>Section</th>
                                            <th onclick="trierTable('date_import')" style="cursor: pointer;">
                                                Date Import <i class="bi bi-arrow-down-up"></i>
                                            </th>
                                            <th>Statistiques</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tableBody">
                                        <?php foreach ($imports as $import): ?>
                                            <tr data-import='<?= htmlspecialchars(json_encode($import), ENT_QUOTES, 'UTF-8') ?>'>
                                                <td class="fw-semibold"><?= htmlspecialchars($import['annee_academique']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $import['session'] === 'principale' ? 'primary' : 'warning' ?>">
                                                        <?= ucfirst(htmlspecialchars($import['session'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($import['semestre']) ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars($import['promotion']) ?></div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($import['designationSection'])): ?>
                                                        <span class="badge bg-info"><?= htmlspecialchars($import['designationSection']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Non définie</span>
                                                    <?php endif; ?>
                                                    <?php if ($isAdmin): ?>
                                                        <button type="button" class="btn btn-sm btn-link p-0 ms-1" 
                                                                onclick="modifierSection(<?= $import['id'] ?>)" 
                                                                title="Modifier la section">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= date('d/m/Y', strtotime($import['date_import'])) ?><br>
                                                        <?= date('H:i', strtotime($import['date_import'])) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <span class="badge bg-success"><?= $import['nombre_etudiants'] ?> étudiants</span>
                                                        <span class="badge bg-info"><?= $import['nombre_ues'] ?> UEs</span>
                                                        <span class="badge bg-secondary"><?= $import['nombre_ecues'] ?> ECUEs</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                onclick="voirDetailsImport(<?= $import['id'] ?>)" 
                                                                title="Voir les détails">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <a href="deliberation/documents_grilles_anciennes?import_id=<?= $import['id'] ?>" 
                                                           class="btn btn-outline-secondary btn-sm" 
                                                           title="Générer documents">
                                                            <i class="bi bi-file-earmark-text"></i>
                                                        </a>
                                                         <div class="btn-group btn-group-sm">
                                                             <button type="button" class="btn btn-outline-warning dropdown-toggle" 
                                                                     data-bs-toggle="dropdown" 
                                                                     title="Génération en masse">
                                                                 <i class="bi bi-files"></i>
                                                             </button>
                                                             <ul class="dropdown-menu">
                                                                 <li>
                                                                     <a class="dropdown-item" 
                                                                        href="deliberation/generation_masse_anciennes?import_id=<?= $import['id'] ?>&type=releves">
                                                                         <i class="bi bi-file-earmark-text me-2"></i>
                                                                         Tous les relevés
                                                                     </a>
                                                                 </li>
                                                                 <li>
                                                                     <a class="dropdown-item" 
                                                                        href="deliberation/generation_masse_anciennes?import_id=<?= $import['id'] ?>&type=fiches">
                                                                         <i class="bi bi-file-earmark-pdf me-2"></i>
                                                                         Toutes les fiches
                                                                     </a>
                                                                 </li>
                                                             </ul>
                                                         </div>
                                                         <button type="button" class="btn btn-outline-success" 
                                                               onclick="editerGrille(<?= $import['id'] ?>)" 
                                                               title="Éditer la grille">
                                                           <i class="bi bi-pencil-square"></i>
                                                         </button>
                                                         <button type="button" class="btn btn-outline-info" 
                                                                onclick="exporterGrilleExcel(<?= $import['id'] ?>)" 
                                                                title="Exporter en Excel">
                                                            <i class="bi bi-file-earmark-excel"></i>
                                                         </button>
                                                         <button type="button" class="btn btn-outline-danger" 
                                                                onclick="supprimerImport(<?= $import['id'] ?>)" 
                                                                title="Supprimer">
                                                            <i class="bi bi-trash"></i>
                                                         </button>
                                                         </div>
                                                         </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Vue grille -->
                            <div class="row g-3" id="gridView" style="display: none;">
                                <?php foreach ($imports as $import): ?>
                                    <div class="col-md-6 col-lg-4 import-card" data-import='<?= htmlspecialchars(json_encode($import), ENT_QUOTES, 'UTF-8') ?>'>
                                        <div class="card h-100 border-0 shadow-sm">
                                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-0"><?= htmlspecialchars($import['promotion']) ?></h6>
                                                    <small class="text-muted"><?= htmlspecialchars($import['annee_academique']) ?></small>
                                                </div>
                                                <span class="badge bg-<?= $import['session'] === 'principale' ? 'primary' : 'warning' ?>">
                                                    <?= ucfirst(htmlspecialchars($import['session'])) ?>
                                                </span>
                                            </div>
                                            <div class="card-body">
                                                <div class="row text-center mb-3">
                                                    <div class="col-4">
                                                        <div class="text-primary fw-bold"><?= $import['nombre_etudiants'] ?></div>
                                                        <small class="text-muted">Étudiants</small>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="text-info fw-bold"><?= $import['nombre_ues'] ?></div>
                                                        <small class="text-muted">UEs</small>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="text-secondary fw-bold"><?= $import['nombre_ecues'] ?></div>
                                                        <small class="text-muted">ECUEs</small>
                                                    </div>
                                                </div>
                                                <div class="text-center">
                                                    <small class="text-muted">
                                                        <i class="bi bi-calendar me-1"></i>
                                                        <?= date('d/m/Y H:i', strtotime($import['date_import'])) ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <div class="d-grid gap-1">
                                                    <div class="btn-group btn-group-sm" role="group">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                    onclick="voirDetailsImport(<?= $import['id'] ?>)">
                                                    <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-success" 
                                                    onclick="editerGrille(<?= $import['id'] ?>)">
                                                    <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <a href="deliberation/documents_grilles_anciennes?import_id=<?= $import['id'] ?>" 
                                                    class="btn btn-outline-secondary btn-sm">
                                                    <i class="bi bi-file-earmark-text"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-info" 
                                                    onclick="exporterGrilleExcel(<?= $import['id'] ?>)">
                                                    <i class="bi bi-file-earmark-excel"></i>
                                                    </button>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                 onclick="supprimerImport(<?= $import['id'] ?>)">
                                                             <i class="bi bi-trash"></i>
                                                         </button>
                                                     </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Message d'absence de résultats -->
                            <div class="alert alert-warning text-center" id="noResultsMessage" style="display: none;">
                                <i class="bi bi-search me-2"></i>
                                Aucune grille ne correspond aux critères de recherche.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </section>

</main><!-- End #main -->

<!-- Modal d'importation -->
<div class="modal fade" id="modalImport" tabindex="-1" aria-labelledby="modalImportLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalImportLabel">
                    <i class="bi bi-upload me-2"></i>
                    Importer une Grille Ancienne
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formImport" enctype="multipart/form-data">
                <div class="modal-body">
                    
                    <!-- Informations de la grille -->
                    <h6 class="mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        Informations de la grille
                    </h6>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="annee_academique" class="form-label">Année Académique</label>
                            <select class="form-select" id="annee_academique" name="annee_academique" required>
                                <option value="">Sélectionner une année</option>
                                <?php foreach ($annees as $annee): ?>
                                    <option value="<?= htmlspecialchars($annee['designation']) ?>">
                                        <?= htmlspecialchars($annee['designation']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                        <label for="session" class="form-label">Session *</label>
                        <select class="form-select" id="session" name="session" required>
                        <option value="">Sélectionner une session</option>
                        <option value="principale">Session Principale</option>
                        <option value="rattrapage">Session de Rattrapage</option>
                        </select>
                        <div class="form-text">
                                <small><strong>Important:</strong> En session de rattrapage, seules les nouvelles notes seront prises en compte pour les matières non validées en session principale.</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="semestre" class="form-label">Semestre</label>
                            <select class="form-select" id="semestre" name="semestre" required>
                                <option value="">Sélectionner un semestre</option>
                                <option value="Semestre 1">Semestre 1</option>
                                <option value="Semestre 2">Semestre 2</option>
                                <option value="Annuel">Annuel (S1 + S2)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="promotion" class="form-label">Promotion</label>
                            <input type="text" class="form-control" id="promotion" name="promotion" 
                                   placeholder="Ex: L1 Informatique 2020-2021" required>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Fichier Excel -->
                    <h6 class="mb-3">
                        <i class="bi bi-file-spreadsheet me-2"></i>
                        Fichier Excel
                    </h6>
                    
                    <div class="mb-3">
                        <label for="fichier_excel" class="form-label">Sélectionner le fichier Excel</label>
                        <input type="file" class="form-control" id="fichier_excel" name="fichier_excel" 
                               accept=".xlsx,.xls" required>
                        <div class="form-text">
                            Formats acceptés: .xlsx, .xls. Taille maximale: 10 MB
                        </div>
                    </div>
                    
                    <!-- Instructions pour le format -->
                    <div class="alert alert-info">
                        <h6><i class="bi bi-lightbulb me-2"></i>Format attendu :</h6>
                        <ul class="mb-0">
                            <li><strong>Détection automatique :</strong> Le système détecte automatiquement les colonnes</li>
                            <li><strong>Colonnes obligatoires :</strong> Une colonne contenant "Matricule" ou "Numero", une colonne "Nom"</li>
                            <li><strong>Colonnes de matières :</strong> Noms des UE/matières avec les notes finales</li>
                            <li><strong>Structure :</strong> En-têtes en première ligne, données à partir de la deuxième ligne</li>
                        </ul>
                        <div class="mt-2">
                            <strong>Exemple de structure Excel :</strong><br>
                            <code>Matricule | Nom | Mathématiques | Physique | Informatique | ...</code>
                        </div>
                    </div>
                    
                    <!-- Prévisualisation (sera remplie dynamiquement) -->
                    <div id="previewContainer" style="display: none;">
                        <h6 class="mb-3">
                            <i class="bi bi-eye me-2"></i>
                            Prévisualisation
                        </h6>
                        <div id="previewContent"></div>
                    </div>
                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload me-2"></i>
                        Importer la grille
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de chargement -->
<div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <p class="mt-3" id="loadingMessage">Importation en cours...</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal de détails d'un import -->
<div class="modal fade" id="modalDetails" tabindex="-1" aria-labelledby="modalDetailsLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetailsLabel">
                    <i class="bi bi-eye me-2"></i>
                    Détails de la grille importée
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier la section -->
<div class="modal fade" id="modalModifierSection" tabindex="-1" aria-labelledby="modalModifierSectionLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalModifierSectionLabel">
                    <i class="bi bi-pencil-square me-2"></i>
                    Modifier la section
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formModifierSection">
                <div class="modal-body">
                    <input type="hidden" id="importIdSection" name="import_id">
                    
                    <div class="mb-3">
                        <label for="sectionSelect" class="form-label">Section / Faculté</label>
                        <select class="form-select" id="sectionSelect" name="section_id" required>
                            <option value="">-- Sélectionner une section --</option>
                            <?php 
                            $sectionsAnneeEnCours = $grilleAncienne->getSectionsAnneeEnCours();
                            foreach ($sectionsAnneeEnCours as $section): ?>
                                <option value="<?= $section['idsection'] ?>">
                                    <?= htmlspecialchars($section['designationSection']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            Sélectionnez la section à laquelle appartient cette grille de notes.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
// Variables globales
let modalImport = null;
let modalDetails = null;
let loadingModal = null;
let modalModifierSection = null;

document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les modals
    modalImport = new bootstrap.Modal(document.getElementById('modalImport'));
    modalDetails = new bootstrap.Modal(document.getElementById('modalDetails'));
    loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    modalModifierSection = new bootstrap.Modal(document.getElementById('modalModifierSection'));
    
    // Gestionnaire du formulaire d'import
    document.getElementById('formImport').addEventListener('submit', function(e) {
        e.preventDefault();
        importerGrille();
    });
    
    // Gestionnaire du formulaire de modification de section
    document.getElementById('formModifierSection').addEventListener('submit', function(e) {
        e.preventDefault();
        enregistrerSection();
    });
    
    // Initialiser la vue par défaut
    toggleView('table');
    
    // Initialiser les données filtrables avec gestion d'erreur
    window.allImports = Array.from(document.querySelectorAll('[data-import]')).map(el => {
        try {
            return JSON.parse(el.getAttribute('data-import'));
        } catch (e) {
            console.error('Erreur de parsing JSON pour l\'élément:', el, 'Erreur:', e);
            return null;
        }
    }).filter(item => item !== null);
});

/**
 * Ouvrir le modal d'importation
 */
function ouvrirModalImport() {
    modalImport.show();
}

/**
 * Importer une grille Excel
 */
function importerGrille() {
    const form = document.getElementById('formImport');
    const formData = new FormData(form);
    
    // Validation côté client
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Vérifier la taille du fichier
    const fichier = document.getElementById('fichier_excel').files[0];
    if (fichier && fichier.size > 10 * 1024 * 1024) { // 10 MB
        Swal.fire({
            icon: 'error',
            title: 'Fichier trop volumineux',
            text: 'Le fichier ne doit pas dépasser 10 MB.'
        });
        return;
    }
    
    // Fermer le modal d'import et afficher le loading
    modalImport.hide();
    document.getElementById('loadingMessage').textContent = 'Importation en cours...';
    loadingModal.show();
    
    // Envoyer la requête
    fetch('controller/import_grille_ancienne_process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        loadingModal.hide();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Import réussi',
                html: `
                    <p>La grille a été importée avec succès !</p>
                    <ul class="text-start">
                        <li><strong>${data.statistiques.etudiants_importes}</strong> étudiants importés</li>
                        <li><strong>${data.statistiques.ues_importees}</strong> UEs créées</li>
                        <li><strong>${data.statistiques.ecues_importees}</strong> ECUEs créées</li>
                        <li><strong>${data.statistiques.notes_importees}</strong> notes importées</li>
                    </ul>
                `,
                confirmButtonText: 'OK'
            }).then(() => {
                // Recharger la page pour afficher le nouvel import
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erreur d\'importation',
                text: data.message
            });
        }
    })
    .catch(error => {
        loadingModal.hide();
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Une erreur s\'est produite lors de l\'importation.'
        });
    });
}

/**
 * Voir les détails d'un import
 */
function voirDetailsImport(importId) {
    document.getElementById('detailsContent').innerHTML = '<div class="text-center"><div class="spinner-border"></div></div>';
    modalDetails.show();
    
    // Charger les détails via AJAX
    fetch(`controller/details_grille_ancienne.php?import_id=${importId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('detailsContent').innerHTML = data.html;
            } else {
                document.getElementById('detailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Erreur lors du chargement des détails: ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('detailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Une erreur s'est produite lors du chargement des détails.
                </div>
            `;
        });
}

/**
 * Générer les fiches de validation pour un import spécifique
 */
function genererFichesValidationImport(importId) {
    Swal.fire({
        title: 'Générer les fiches de validation',
        text: 'Voulez-vous générer les fiches de validation pour cette grille ?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Oui, générer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('loadingMessage').textContent = 'Génération des fiches en cours...';
            loadingModal.show();
            
            // Ouvrir la génération dans un nouvel onglet
            window.open(`controller/export_fiches_validation_anciennes.php?import_id=${importId}`, '_blank');
            
            // Fermer le modal après un délai
            setTimeout(() => {
                loadingModal.hide();
            }, 2000);
        }
    });
}

/**
 * Générer tous les relevés de notes
 */
function genererTousLesReleves() {
    Swal.fire({
        title: 'Générer tous les relevés',
        text: 'Voulez-vous générer tous les relevés de notes pour toutes les grilles importées ?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Oui, générer tout',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Génération en cours...',
                text: 'Génération de tous les relevés, veuillez patienter.',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Ouvrir la génération dans un nouvel onglet
            window.open('deliberation/generation_masse_anciennes?type=releves', '_blank');
            
            // Fermer l'alerte après un délai
            setTimeout(() => {
                Swal.close();
            }, 3000);
        }
    });
}

/**
 * Générer toutes les fiches de validation
 */
function genererToutesLesFiches() {
    Swal.fire({
        title: 'Générer toutes les fiches',
        text: 'Voulez-vous générer les fiches de validation pour toutes les grilles importées ?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Oui, générer tout',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Génération en cours...',
                text: 'Génération de toutes les fiches, veuillez patienter.',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Ouvrir la génération dans un nouvel onglet
            window.open('controller/export_fiches_validation_anciennes.php', '_blank');
            
            // Fermer l'alerte après un délai
            setTimeout(() => {
                Swal.close();
            }, 3000);
        }
    });
}

/**
 * Générer tous les documents (relevés et fiches)
 */
function genererTousLesDocuments() {
    Swal.fire({
        title: 'Génération complète',
        text: 'Voulez-vous générer tous les relevés de notes ET toutes les fiches de validation ?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Oui, tout générer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Génération complète en cours...',
                text: 'Génération de tous les documents, veuillez patienter.',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Générer d'abord tous les relevés
            window.open('deliberation/generation_masse_anciennes?type=releves', '_blank');
            
            // Puis générer toutes les fiches après un délai
            setTimeout(() => {
                window.open('controller/export_fiches_validation_anciennes.php', '_blank');
            }, 1000);
            
            // Fermer l'alerte après un délai
            setTimeout(() => {
                Swal.close();
            }, 4000);
        }
    });
}

/**
 * Générer les fiches de validation pour tous les imports (fonction legacy)
 */
function genererFichesValidation() {
    genererToutesLesFiches();
}

/**
 * Exporter une grille en Excel
 */
function exporterGrilleExcel(importId) {
    Swal.fire({
        title: 'Export Excel en cours...',
        text: 'Génération du fichier Excel, veuillez patienter.',
        icon: 'info',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Ouvrir l'export dans un nouvel onglet
    window.open(`controller/export_grille_ancienne.php?import_id=${importId}`, '_blank');
    
    // Fermer l'alerte après un délai
    setTimeout(() => {
        Swal.close();
    }, 2000);
}

/**
 * Modifier la section d'un import
 */
function modifierSection(importId) {
    // Définir l'ID de l'import dans le formulaire
    document.getElementById('importIdSection').value = importId;
    
    // Récupérer la section actuelle si elle existe
    const importData = window.allImports.find(imp => imp.id == importId);
    if (importData && importData.section_id) {
        document.getElementById('sectionSelect').value = importData.section_id;
    } else {
        document.getElementById('sectionSelect').value = '';
    }
    
    // Ouvrir le modal
    modalModifierSection.show();
}

/**
 * Enregistrer la modification de section
 */
function enregistrerSection() {
    const formData = new FormData(document.getElementById('formModifierSection'));
    
    // Afficher le loading
    modalModifierSection.hide();
    document.getElementById('loadingMessage').textContent = 'Mise à jour en cours...';
    loadingModal.show();
    
    // Envoyer la requête
    fetch('controller/update_import_section.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        loadingModal.hide();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Mise à jour réussie',
                text: 'La section a été mise à jour avec succès.',
                confirmButtonText: 'OK'
            }).then(() => {
                // Recharger la page pour afficher les changements
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: data.message || 'Une erreur s\'est produite lors de la mise à jour.'
            });
        }
    })
    .catch(error => {
        loadingModal.hide();
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Une erreur s\'est produite lors de la mise à jour.'
        });
    });
}

/**
 * Supprimer un import
 */
function supprimerImport(importId) {
    Swal.fire({
        title: 'Supprimer la grille',
        text: 'Êtes-vous sûr de vouloir supprimer cette grille ? Cette action est irréversible.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, supprimer',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`controller/delete_grille_ancienne.php?import_id=${importId}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Suppression réussie',
                        text: 'La grille a été supprimée avec succès.'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur s\'est produite lors de la suppression.'
                });
            });
        }
    });
}

// 🔍 NOUVELLES FONCTIONS POUR FILTRES ET RECHERCHE

/**
 * Basculer entre vue tableau et vue grille
 */
function toggleView(viewType) {
    const tableView = document.getElementById('tableView');
    const gridView = document.getElementById('gridView');
    const btnTable = document.getElementById('btnTableView');
    const btnGrid = document.getElementById('btnGridView');
    
    if (viewType === 'table') {
        tableView.style.display = 'block';
        gridView.style.display = 'none';
        btnTable.classList.add('active');
        btnGrid.classList.remove('active');
    } else {
        tableView.style.display = 'none';
        gridView.style.display = 'flex';
        btnTable.classList.remove('active');
        btnGrid.classList.add('active');
    }
    
    // Sauvegarder la préférence
    localStorage.setItem('grilles_view_mode', viewType);
}

// Variable pour le debounce du filtre de recherche
let filterTimeout;

/**
 * Appliquer les filtres de recherche avec debounce pour la recherche textuelle
 */
function appliquerFiltres(immediate = false) {
    // Si ce n'est pas immédiat et qu'il y a une recherche textuelle, utiliser le debounce
    if (!immediate && document.getElementById('searchText').value.trim() !== '') {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            executeFilters();
        }, 300); // Délai de 300ms
    } else {
        executeFilters();
    }
}

/**
 * Exécuter les filtres
 */
function executeFilters() {
    const searchText = document.getElementById('searchText').value.toLowerCase();
    const filterAnnee = document.getElementById('filterAnnee').value;
    const filterSession = document.getElementById('filterSession').value;
    const filterSemestre = document.getElementById('filterSemestre').value;
    const sortBy = document.getElementById('sortBy').value;
    
    // Filtrer les données
    let filteredImports = window.allImports.filter(importItem => {
        const matchText = !searchText || 
            importItem.promotion.toLowerCase().includes(searchText) ||
            importItem.annee_academique.toLowerCase().includes(searchText) ||
            importItem.session.toLowerCase().includes(searchText) ||
            importItem.semestre.toLowerCase().includes(searchText);
        
        const matchAnnee = !filterAnnee || importItem.annee_academique === filterAnnee;
        const matchSession = !filterSession || importItem.session === filterSession;
        const matchSemestre = !filterSemestre || importItem.semestre === filterSemestre;
        
        return matchText && matchAnnee && matchSession && matchSemestre;
    });
    
    // Trier les données
    filteredImports.sort((a, b) => {
        switch (sortBy) {
            case 'date_asc':
                return new Date(a.date_import) - new Date(b.date_import);
            case 'date_desc':
                return new Date(b.date_import) - new Date(a.date_import);
            case 'promotion_asc':
                return a.promotion.localeCompare(b.promotion);
            case 'annee_desc':
                return b.annee_academique.localeCompare(a.annee_academique);
            case 'etudiants_desc':
                return b.nombre_etudiants - a.nombre_etudiants;
            default:
                return new Date(b.date_import) - new Date(a.date_import);
        }
    });
    
    // Mettre à jour l'affichage
    updateTableView(filteredImports);
    updateGridView(filteredImports);
    updateCountBadge(filteredImports.length);
    
    // Afficher/masquer le message "aucun résultat"
    const noResults = document.getElementById('noResultsMessage');
    if (filteredImports.length === 0 && window.allImports.length > 0) {
        noResults.style.display = 'block';
        document.getElementById('tableView').style.display = 'none';
        document.getElementById('gridView').style.display = 'none';
    } else {
        noResults.style.display = 'none';
        // Restaurer la vue active
        const activeView = localStorage.getItem('grilles_view_mode') || 'table';
        toggleView(activeView);
    }
}

/**
 * Mettre à jour la vue tableau
 */
function updateTableView(imports) {
    const tbody = document.getElementById('tableBody');
    tbody.innerHTML = '';
    
    imports.forEach(importItem => {
        const row = document.createElement('tr');
        row.setAttribute('data-import', JSON.stringify(importItem));
        
        row.innerHTML = `
            <td class="fw-semibold">${escapeHtml(importItem.annee_academique)}</td>
            <td>
                <span class="badge bg-${importItem.session === 'principale' ? 'primary' : 'warning'}">
                    ${escapeHtml(importItem.session.charAt(0).toUpperCase() + importItem.session.slice(1))}
                </span>
            </td>
            <td>${escapeHtml(importItem.semestre)}</td>
            <td>
                <div class="fw-semibold">${escapeHtml(importItem.promotion)}</div>
            </td>
            <td>
                <small class="text-muted">
                    ${formatDate(importItem.date_import)}<br>
                    ${formatTime(importItem.date_import)}
                </small>
            </td>
            <td>
                <div class="d-flex gap-2">
                    <span class="badge bg-success">${importItem.nombre_etudiants} étudiants</span>
                    <span class="badge bg-info">${importItem.nombre_ues} UEs</span>
                    <span class="badge bg-secondary">${importItem.nombre_ecues} ECUEs</span>
                </div>
            </td>
            <td>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary" 
                            onclick="voirDetailsImport(${importItem.id})" 
                            title="Voir les détails">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button type="button" class="btn btn-outline-success" 
                            onclick="editerGrille(${importItem.id})" 
                            title="Éditer la grille">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    <button type="button" class="btn btn-outline-info" 
                            onclick="exporterGrilleExcel(${importItem.id})" 
                            title="Exporter en Excel">
                        <i class="bi bi-file-earmark-excel"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger" 
                            onclick="supprimerImport(${importItem.id})" 
                            title="Supprimer">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        `;
        
        tbody.appendChild(row);
    });
}

/**
 * Mettre à jour la vue grille
 */
function updateGridView(imports) {
    const gridView = document.getElementById('gridView');
    gridView.innerHTML = '';
    
    imports.forEach(importItem => {
        const card = document.createElement('div');
        card.className = 'col-md-6 col-lg-4 import-card';
        card.setAttribute('data-import', JSON.stringify(importItem));
        
        card.innerHTML = `
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">${escapeHtml(importItem.promotion)}</h6>
                        <small class="text-muted">${escapeHtml(importItem.annee_academique)}</small>
                    </div>
                    <span class="badge bg-${importItem.session === 'principale' ? 'primary' : 'warning'}">
                        ${escapeHtml(importItem.session.charAt(0).toUpperCase() + importItem.session.slice(1))}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div class="text-primary fw-bold">${importItem.nombre_etudiants}</div>
                            <small class="text-muted">Étudiants</small>
                        </div>
                        <div class="col-4">
                            <div class="text-info fw-bold">${importItem.nombre_ues}</div>
                            <small class="text-muted">UEs</small>
                        </div>
                        <div class="col-4">
                            <div class="text-secondary fw-bold">${importItem.nombre_ecues}</div>
                            <small class="text-muted">ECUEs</small>
                        </div>
                    </div>
                    <div class="text-center">
                        <small class="text-muted">
                            <i class="bi bi-calendar me-1"></i>
                            ${formatDateTime(importItem.date_import)}
                        </small>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <div class="d-grid gap-1">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary" 
                                    onclick="voirDetailsImport(${importItem.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button type="button" class="btn btn-outline-success" 
                                    onclick="editerGrille(${importItem.id})">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button type="button" class="btn btn-outline-info" 
                                    onclick="exporterGrilleExcel(${importItem.id})">
                                <i class="bi bi-file-earmark-excel"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger" 
                                    onclick="supprimerImport(${importItem.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        gridView.appendChild(card);
    });
}

/**
 * Mettre à jour le badge de comptage
 */
function updateCountBadge(count) {
    document.getElementById('countBadge').textContent = count;
}

/**
 * Réinitialiser tous les filtres
 */
function reinitialiserFiltres() {
    document.getElementById('searchText').value = '';
    document.getElementById('filterAnnee').value = '';
    document.getElementById('filterSession').value = '';
    document.getElementById('filterSemestre').value = '';
    document.getElementById('sortBy').value = 'date_desc';
    
    // Forcer l'exécution immédiate lors de la réinitialisation
    appliquerFiltres(true);
}

/**
 * Trier le tableau par colonne
 */
function trierTable(column) {
    const sortBy = document.getElementById('sortBy');
    
    switch (column) {
        case 'annee_academique':
            sortBy.value = 'annee_desc';
            break;
        case 'promotion':
            sortBy.value = 'promotion_asc';
            break;
        case 'date_import':
            sortBy.value = 'date_desc';
            break;
        default:
            sortBy.value = 'date_desc';
    }
    
    // Tri immédiat
    appliquerFiltres(true);
}

// 🛠️ FONCTIONS UTILITAIRES

/**
 * Échapper le HTML pour éviter les injections XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Formater une date
 */
function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('fr-FR');
}

/**
 * Formater une heure
 */
function formatTime(dateString) {
    return new Date(dateString).toLocaleTimeString('fr-FR', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}

/**
 * Formater date et heure
 */
function formatDateTime(dateString) {
    return new Date(dateString).toLocaleDateString('fr-FR') + ' ' + 
           new Date(dateString).toLocaleTimeString('fr-FR', { 
               hour: '2-digit', 
               minute: '2-digit' 
           });
}

// Stocker toutes les données des imports pour le filtrage
window.allImports = <?= json_encode($imports) ?>;

// Initialisation quand le DOM est chargé
document.addEventListener('DOMContentLoaded', function() {
    // Charger la vue préférée
    const savedViewMode = localStorage.getItem('grilles_view_mode') || 'table';
    toggleView(savedViewMode);
    
    // Ne pas réappliquer les filtres au chargement pour éviter la duplication
    // Les données sont déjà affichées côté PHP
});

/**
 * Ouvrir la page d'édition de la grille
 */
function editerGrille(importId) {
    window.location.href = `deliberation/editer_grille_ancienne?import_id=${importId}`;
}

</script>

<?php include "./views/include/footer_file.php"; ?>
