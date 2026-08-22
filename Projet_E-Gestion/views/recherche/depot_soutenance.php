<?php
include "./views/include/header.php";

error_reporting(E_ALL); ini_set("display_errors", 1);

// Initialisation des classes
$depotSoutenanceModel = new DepotSoutenance();
$universite = new Universite();
$agentModel = new Agent();
$soutenanceModel = new Soutenance(); // Ajoutez cette lign

// Récupération de l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();

// Récupérer toutes les sections accessibles à l'utilisateur
$sections = [];
if ($_SESSION['idRole'] == 1) { // Si administrateur
    $sections = $universite->getSections();
} else {
    // Pour les autres utilisateurs, vérifier les sections associées
    $userSections = $universite->getUserSections($_SESSION['id']);
    foreach ($userSections as $sectionId) {
        $sectionData = $universite->getSectionById($sectionId);
        if ($sectionData) {
            $sections[] = $sectionData;
        }
    }
}

// Récupérer la section sélectionnée
$selectedSection = isset($_GET['section']) ? intval($_GET['section']) : 0;
if ($selectedSection == 0 && !empty($sections)) {
    $selectedSection = $sections[0]['idsection'];
}

// Récupérer l'onglet actif
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'memoires';

// Récupérer l'année académique sélectionnée ou utiliser l'année actuelle
$selectedYear = isset($_GET['annee_acad']) && !empty($_GET['annee_acad']) 
    ? intval($_GET['annee_acad']) 
    : $currentYear['idannee_acad'];

// Récupérer les filtres communs
$filtreEtudiant = isset($_GET['etudiant']) ? $_GET['etudiant'] : '';

// Récupérer les données en fonction de l'onglet
$memoires = [];
$rapports = [];
$soutenances = [];

if ($selectedSection > 0) {
    if ($activeTab == 'memoires' || $activeTab == 'all') {
        // Filtres spécifiques pour les mémoires
        $filtreSujet = isset($_GET['sujet']) ? $_GET['sujet'] : '';
        $filtreDate = isset($_GET['date_depot_memoire']) ? $_GET['date_depot_memoire'] : '';
        
        // Appel à la méthode modifiée pour inclure les filtres
        $memoires = $depotSoutenanceModel->getMemoiresParSection(
            $selectedSection, 
            $selectedYear, 
            $filtreEtudiant, 
            $filtreSujet, 
            $filtreDate
        );
    }
    
    if ($activeTab == 'rapports' || $activeTab == 'all') {
        // Filtres spécifiques pour les rapports
        $filtreTitre = isset($_GET['titre_rapport']) ? $_GET['titre_rapport'] : '';
        $filtreLieuStage = isset($_GET['lieu_stage']) ? $_GET['lieu_stage'] : '';
        
        // Appel à la méthode modifiée pour inclure les filtres
        $rapports = $depotSoutenanceModel->getRapportsStageParSection(
            $selectedSection, 
            $selectedYear, 
            $filtreEtudiant, 
            $filtreTitre, 
            $filtreLieuStage
        );
    }
    
    if ($activeTab == 'soutenances' || $activeTab == 'all') {
        // Filtres spécifiques pour les soutenances
        $filtreDateDebut = isset($_GET['date_soutenance_debut']) ? $_GET['date_soutenance_debut'] : '';
        $filtreStatut = isset($_GET['statut_soutenance']) ? $_GET['statut_soutenance'] : '';
        
        // Appel à la méthode modifiée pour inclure les filtres
        $soutenances = $depotSoutenanceModel->getSoutenancesParSection(
            $selectedSection, 
            $selectedYear, 
            $filtreEtudiant, 
            $filtreDateDebut, 
            $filtreStatut
        );
    }
}

// Récupérer la liste des enseignants pour le jury
$enseignants = $agentModel->getAgentsByType('Enseignant');
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES DÉPÔTS ET SOUTENANCES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Recherche</li>
                <li class="breadcrumb-item active">Dépôts et Soutenances</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Dépôts et Soutenances</h5>
                        
                        <!-- Sélecteur de section -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <form method="GET" action="" id="sectionForm">
                                    <input type="hidden" name="view" value="recherche/depot_soutenance">
                                    <input type="hidden" name="tab" value="<?= $activeTab ?>">
                                    <div class="input-group">
                                        <label class="input-group-text" for="section">Section</label>
                                        <select name="section" id="section" class="form-select" onchange="this.form.submit()">
                                            <?php foreach ($sections as $section): ?>
                                                <option value="<?= $section['idsection'] ?>"
                                                    <?= $selectedSection == $section['idsection'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($section['designationSection']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="col-md-6 text-end">
                                <!-- Boutons d'actions selon l'onglet actif -->
                                <?php if ($activeTab == 'memoires'): ?>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#memoireModal">
                                    <i class="bi bi-plus-circle"></i> Enregistrer un dépôt de mémoire
                                </button>
                                <?php elseif ($activeTab == 'rapports'): ?>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#rapportModal">
                                    <i class="bi bi-plus-circle"></i> Enregistrer un rapport de stage
                                </button>
                                <?php elseif ($activeTab == 'soutenances'): ?>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#soutenanceModal">
                                    <i class="bi bi-plus-circle"></i> Programmer une soutenance
                                </button>
                                <a target="_blank" href="controller/exports/export_section_pdf.php?id_section=<?= $selectedSection ?>&annee_acad=<?= $currentYear['idannee_acad'] ?>" 
                                class="btn btn-success ms-2">
                                    <i class="bi bi-file-earmark-pdf"></i> PDF
                                </a>
                                <a target="_blank" href="controller/exports/export_section_excel.php?id_section=<?= $selectedSection ?>&annee_acad=<?= $currentYear['idannee_acad'] ?>" 
                                class="btn btn-success ms-2">
                                    <i class="bi bi-file-earmark-excel"></i> EXCEL
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>



                        

                        <!-- Filtres de recherche -->
<div class="row mb-4 mt-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Filtres de recherche</h5>
                <form id="searchForm" method="GET" action="">
                    <input type="hidden" name="view" value="recherche/depot_soutenance">
                    <input type="hidden" name="section" value="<?= $selectedSection ?>">
                    <input type="hidden" name="tab" value="<?= $activeTab ?>">
                    
                    <div class="row g-3">
                        <!-- Filtre commun: Année académique -->
                        <div class="col-md-3">
                            <label for="annee_acad" class="form-label">Année académique</label>
                            <select name="annee_acad" id="annee_acad" class="form-select">
                                <option value="">Toutes les années</option>
                                <?php 
                                $annees = $universite->getAcademicYears();
                                foreach ($annees as $annee): 
                                    $selected = (isset($_GET['annee_acad']) && $_GET['annee_acad'] == $annee['idannee_acad']) ? 'selected' : '';
                                ?>
                                <option value="<?= $annee['idannee_acad'] ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($annee['designation']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Filtre commun: Recherche par nom d'étudiant -->
                        <div class="col-md-3">
                            <label for="etudiant" class="form-label">Nom de l'étudiant</label>
                            <input type="text" class="form-control" id="etudiant" name="etudiant" 
                                   value="<?= isset($_GET['etudiant']) ? htmlspecialchars($_GET['etudiant']) : '' ?>" 
                                   placeholder="Rechercher un étudiant">
                        </div>
                        
                        <?php if ($activeTab == 'memoires'): ?>
                        <!-- Filtres spécifiques pour les mémoires -->
                        <div class="col-md-3">
                            <label for="sujet" class="form-label">Sujet contient</label>
                            <input type="text" class="form-control" id="sujet" name="sujet" 
                                   value="<?= isset($_GET['sujet']) ? htmlspecialchars($_GET['sujet']) : '' ?>" 
                                   placeholder="Mots-clés du sujet">
                        </div>
                        <div class="col-md-3">
                            <label for="date_depot_memoire" class="form-label">Date de dépôt</label>
                            <input type="date" class="form-control" id="date_depot_memoire" name="date_depot_memoire"
                                   value="<?= isset($_GET['date_depot_memoire']) ? $_GET['date_depot_memoire'] : '' ?>">
                        </div>
                        <?php elseif ($activeTab == 'rapports'): ?>
                        <!-- Filtres spécifiques pour les rapports -->
                        <div class="col-md-3">
                            <label for="titre_rapport" class="form-label">Titre contient</label>
                            <input type="text" class="form-control" id="titre_rapport" name="titre_rapport" 
                                   value="<?= isset($_GET['titre_rapport']) ? htmlspecialchars($_GET['titre_rapport']) : '' ?>" 
                                   placeholder="Mots-clés du titre">
                        </div>
                        <div class="col-md-3">
                            <label for="lieu_stage" class="form-label">Lieu de stage</label>
                            <input type="text" class="form-control" id="lieu_stage" name="lieu_stage" 
                                   value="<?= isset($_GET['lieu_stage']) ? htmlspecialchars($_GET['lieu_stage']) : '' ?>" 
                                   placeholder="Lieu de stage">
                        </div>
                        <?php elseif ($activeTab == 'soutenances'): ?>
                        <!-- Filtres spécifiques pour les soutenances -->
                        <div class="col-md-3">
                            <label for="date_soutenance_debut" class="form-label">Date début</label>
                            <input type="date" class="form-control" id="date_soutenance_debut" name="date_soutenance_debut"
                                   value="<?= isset($_GET['date_soutenance_debut']) ? $_GET['date_soutenance_debut'] : '' ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="statut_soutenance" class="form-label">Statut</label>
                            <select name="statut_soutenance" id="statut_soutenance" class="form-select">
                                <option value="">Tous les statuts</option>
                                <option value="Programmée" <?= (isset($_GET['statut_soutenance']) && $_GET['statut_soutenance'] == 'Programmée') ? 'selected' : '' ?>>Programmée</option>
                                <option value="Terminée" <?= (isset($_GET['statut_soutenance']) && $_GET['statut_soutenance'] == 'Terminée') ? 'selected' : '' ?>>Terminée</option>
                                <option value="Reportée" <?= (isset($_GET['statut_soutenance']) && $_GET['statut_soutenance'] == 'Reportée') ? 'selected' : '' ?>>Reportée</option>
                                <option value="Annulée" <?= (isset($_GET['statut_soutenance']) && $_GET['statut_soutenance'] == 'Annulée') ? 'selected' : '' ?>>Annulée</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-12 text-end">
                            <a href="?view=recherche/depot_soutenance&section=<?= $selectedSection ?>&tab=<?= $activeTab ?>" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Réinitialiser
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Rechercher
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>








                        
                        <!-- Onglets de navigation -->
                        <ul class="nav nav-tabs nav-tabs-bordered" id="depotTabs" role="tablist">

                            
                            <li class="nav-item" role="presentation">
                                <a class="nav-link <?= $activeTab == 'memoires' ? 'active' : '' ?>" 
                                   href="?view=recherche/depot_soutenance&section=<?= $selectedSection ?>&tab=memoires">
                                    <i class="bi bi-journal-text"></i> Mémoires
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link <?= $activeTab == 'rapports' ? 'active' : '' ?>" 
                                   href="?view=recherche/depot_soutenance&section=<?= $selectedSection ?>&tab=rapports">
                                    <i class="bi bi-file-earmark-text"></i> Rapports de stage
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link <?= $activeTab == 'soutenances' ? 'active' : '' ?>" 
                                   href="?view=recherche/depot_soutenance&section=<?= $selectedSection ?>&tab=soutenances">
                                    <i class="bi bi-calendar-event"></i> Soutenances
                                </a>
                            </li>
                        </ul>
                        
                        <div class="tab-content pt-3" id="depotTabsContent">
                            <!-- Onglet des mémoires -->

                            <?php if ($activeTab == 'memoires'): ?>
                            <div class="tab-pane fade show active">
                                <div class="table-responsive">
                                    <!-- Indicateur de filtres actifs -->
                            <?php
                            $filtresActifs = [];
                            if (isset($_GET['etudiant']) && !empty($_GET['etudiant'])) {
                                $filtresActifs[] = "Étudiant: " . htmlspecialchars($_GET['etudiant']);
                            }
                            if (isset($_GET['annee_acad']) && !empty($_GET['annee_acad'])) {
                                $anneeInfo = $universite->getAcademicYearById($_GET['annee_acad']);
                                if ($anneeInfo) {
                                    $filtresActifs[] = "Année: " . htmlspecialchars($anneeInfo['designation']);
                                }
                            }

                            if ($activeTab == 'memoires') {
                                if (isset($_GET['sujet']) && !empty($_GET['sujet'])) {
                                    $filtresActifs[] = "Sujet: " . htmlspecialchars($_GET['sujet']);
                                }
                                if (isset($_GET['date_depot_memoire']) && !empty($_GET['date_depot_memoire'])) {
                                    $filtresActifs[] = "Date dépôt: " . date('d/m/Y', strtotime($_GET['date_depot_memoire']));
                                }
                            } elseif ($activeTab == 'rapports') {
                                if (isset($_GET['titre_rapport']) && !empty($_GET['titre_rapport'])) {
                                    $filtresActifs[] = "Titre: " . htmlspecialchars($_GET['titre_rapport']);
                                }
                                if (isset($_GET['lieu_stage']) && !empty($_GET['lieu_stage'])) {
                                    $filtresActifs[] = "Lieu: " . htmlspecialchars($_GET['lieu_stage']);
                                }
                            } elseif ($activeTab == 'soutenances') {
                                if (isset($_GET['date_soutenance_debut']) && !empty($_GET['date_soutenance_debut'])) {
                                    $filtresActifs[] = "À partir du: " . date('d/m/Y', strtotime($_GET['date_soutenance_debut']));
                                }
                                if (isset($_GET['statut_soutenance']) && !empty($_GET['statut_soutenance'])) {
                                    $filtresActifs[] = "Statut: " . htmlspecialchars($_GET['statut_soutenance']);
                                }
                            }

                            // Afficher les filtres actifs s'il y en a
                            if (!empty($filtresActifs)) {
                                echo '<div class="alert alert-info mb-3">
                                        <i class="bi bi-funnel-fill me-2"></i>
                                        <strong>Filtres actifs:</strong> ' . implode(' | ', $filtresActifs) . '
                                        <a href="?view=recherche/depot_soutenance&section=' . $selectedSection . '&tab=' . $activeTab . '" 
                                        class="btn btn-sm btn-outline-secondary ms-2">
                                            <i class="bi bi-x-circle"></i> Effacer
                                        </a>
                                    </div>';
                            }
                            ?>
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Date de dépôt</th>
                                                <th>Étudiant</th>
                                                <th>Sujet</th>
                                                <th>Directeur</th>
                                                <th>Encadreur</th>
                                                <th>Fichier</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $i = 1;
                                            foreach ($memoires as $memoire):
                                            ?>
                                            <tr>
                                                <td><?= $i++ ?></td>
                                                <td><?= date('d/m/Y', strtotime($memoire['dateDepot'])) ?></td>
                                                <td><?= htmlspecialchars($memoire['nom_etudiant']) ?></td>
                                                <td><?= htmlspecialchars($memoire['intitule']) ?></td>
                                                <td><?= htmlspecialchars($memoire['nom_directeur'] ?? 'Non assigné') ?></td>
                                                <td><?= htmlspecialchars($memoire['nom_encadreur'] ?? 'Non assigné') ?></td>
                                                <td>
                                                    <?php if (!empty($memoire['fichier'])): ?>
                                                    <a href="<?= htmlspecialchars($memoire['fichier']) ?>" class="btn btn-sm btn-info" target="_blank">
                                                        <i class="bi bi-file-earmark-pdf"></i> Voir
                                                    </a>
                                                    <?php else: ?>
                                                    <span class="badge bg-secondary">Aucun fichier</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" onclick="programmerSoutenance(<?= $memoire['sujets_idsujets'] ?>)">
                                                        <i class="bi bi-calendar-plus"></i> Programmer soutenance
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            
                                            <?php if (empty($memoires)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center">Aucun mémoire déposé trouvé pour cette section.</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Onglet des rapports de stage -->
                            <?php if ($activeTab == 'rapports'): ?>
                            <div class="tab-pane fade show active">
                                <div class="table-responsive">
                                    <!-- Indicateur de filtres actifs -->
                            <?php
                            $filtresActifs = [];
                            if (isset($_GET['etudiant']) && !empty($_GET['etudiant'])) {
                                $filtresActifs[] = "Étudiant: " . htmlspecialchars($_GET['etudiant']);
                            }
                            if (isset($_GET['annee_acad']) && !empty($_GET['annee_acad'])) {
                                $anneeInfo = $universite->getAcademicYearById($_GET['annee_acad']);
                                if ($anneeInfo) {
                                    $filtresActifs[] = "Année: " . htmlspecialchars($anneeInfo['designation']);
                                }
                            }

                            if ($activeTab == 'memoires') {
                                if (isset($_GET['sujet']) && !empty($_GET['sujet'])) {
                                    $filtresActifs[] = "Sujet: " . htmlspecialchars($_GET['sujet']);
                                }
                                if (isset($_GET['date_depot_memoire']) && !empty($_GET['date_depot_memoire'])) {
                                    $filtresActifs[] = "Date dépôt: " . date('d/m/Y', strtotime($_GET['date_depot_memoire']));
                                }
                            } elseif ($activeTab == 'rapports') {
                                if (isset($_GET['titre_rapport']) && !empty($_GET['titre_rapport'])) {
                                    $filtresActifs[] = "Titre: " . htmlspecialchars($_GET['titre_rapport']);
                                }
                                if (isset($_GET['lieu_stage']) && !empty($_GET['lieu_stage'])) {
                                    $filtresActifs[] = "Lieu: " . htmlspecialchars($_GET['lieu_stage']);
                                }
                            } elseif ($activeTab == 'soutenances') {
                                if (isset($_GET['date_soutenance_debut']) && !empty($_GET['date_soutenance_debut'])) {
                                    $filtresActifs[] = "À partir du: " . date('d/m/Y', strtotime($_GET['date_soutenance_debut']));
                                }
                                if (isset($_GET['statut_soutenance']) && !empty($_GET['statut_soutenance'])) {
                                    $filtresActifs[] = "Statut: " . htmlspecialchars($_GET['statut_soutenance']);
                                }
                            }

                            // Afficher les filtres actifs s'il y en a
                            if (!empty($filtresActifs)) {
                                echo '<div class="alert alert-info mb-3">
                                        <i class="bi bi-funnel-fill me-2"></i>
                                        <strong>Filtres actifs:</strong> ' . implode(' | ', $filtresActifs) . '
                                        <a href="?view=recherche/depot_soutenance&section=' . $selectedSection . '&tab=' . $activeTab . '" 
                                        class="btn btn-sm btn-outline-secondary ms-2">
                                            <i class="bi bi-x-circle"></i> Effacer
                                        </a>
                                    </div>';
                            }
                            ?>
                                    <table class="table table-striped table-hover">
                                        <!-- Dans le tableau des rapports, ajoutez une colonne pour le fichier -->
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Date de dépôt</th>
                                                <th>Étudiant</th>
                                                <th>Titre du rapport</th>
                                                <th>Lieu de stage</th>
                                                <th>Période</th>
                                                <th>Encadreur</th>
                                                <th>Fichier</th>
                                                <th>Observation</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $i = 1;
                                            foreach ($rapports as $rapport):
                                                $periode = date('d/m/Y', strtotime($rapport['date_debut'])) . ' au ' . date('d/m/Y', strtotime($rapport['date_fin']));
                                            ?>
                                            <tr>
                                                <td><?= $i++ ?></td>
                                                <td><?= date('d/m/Y', strtotime($rapport['dateDepot'])) ?></td>
                                                <td><?= htmlspecialchars($rapport['nom_etudiant']) ?></td>
                                                <td><?= htmlspecialchars($rapport['titre']) ?></td>
                                                <td><?= htmlspecialchars($rapport['lieu_stage']) ?></td>
                                                <td><?= $periode ?></td>
                                                <td><?= htmlspecialchars($rapport['nom_encadreur'] ?? 'Non assigné') ?></td>
                                                <td>
                                                    <?php if (!empty($rapport['fichier'])): ?>
                                                    <a href="<?= htmlspecialchars($rapport['fichier']) ?>" class="btn btn-sm btn-info" target="_blank">
                                                        <i class="bi bi-file-earmark-pdf"></i> Voir
                                                    </a>
                                                    <?php else: ?>
                                                    <span class="badge bg-secondary">Aucun fichier</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($rapport['observation']) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            
                                            <?php if (empty($rapports)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center">Aucun rapport de stage déposé trouvé pour cette section.</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>

                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Onglet des soutenances -->
                            <?php if ($activeTab == 'soutenances'): ?>
                            <div class="tab-pane fade show active">
                                <div class="table-responsive">

                                    <!-- Indicateur de filtres actifs -->
                            <?php
                            $filtresActifs = [];
                            if (isset($_GET['etudiant']) && !empty($_GET['etudiant'])) {
                                $filtresActifs[] = "Étudiant: " . htmlspecialchars($_GET['etudiant']);
                            }
                            if (isset($_GET['annee_acad']) && !empty($_GET['annee_acad'])) {
                                $anneeInfo = $universite->getAcademicYearById($_GET['annee_acad']);
                                if ($anneeInfo) {
                                    $filtresActifs[] = "Année: " . htmlspecialchars($anneeInfo['designation']);
                                }
                            }

                            if ($activeTab == 'memoires') {
                                if (isset($_GET['sujet']) && !empty($_GET['sujet'])) {
                                    $filtresActifs[] = "Sujet: " . htmlspecialchars($_GET['sujet']);
                                }
                                if (isset($_GET['date_depot_memoire']) && !empty($_GET['date_depot_memoire'])) {
                                    $filtresActifs[] = "Date dépôt: " . date('d/m/Y', strtotime($_GET['date_depot_memoire']));
                                }
                            } elseif ($activeTab == 'rapports') {
                                if (isset($_GET['titre_rapport']) && !empty($_GET['titre_rapport'])) {
                                    $filtresActifs[] = "Titre: " . htmlspecialchars($_GET['titre_rapport']);
                                }
                                if (isset($_GET['lieu_stage']) && !empty($_GET['lieu_stage'])) {
                                    $filtresActifs[] = "Lieu: " . htmlspecialchars($_GET['lieu_stage']);
                                }
                            } elseif ($activeTab == 'soutenances') {
                                if (isset($_GET['date_soutenance_debut']) && !empty($_GET['date_soutenance_debut'])) {
                                    $filtresActifs[] = "À partir du: " . date('d/m/Y', strtotime($_GET['date_soutenance_debut']));
                                }
                                if (isset($_GET['statut_soutenance']) && !empty($_GET['statut_soutenance'])) {
                                    $filtresActifs[] = "Statut: " . htmlspecialchars($_GET['statut_soutenance']);
                                }
                            }

                            // Afficher les filtres actifs s'il y en a
                            if (!empty($filtresActifs)) {
                                echo '<div class="alert alert-info mb-3">
                                        <i class="bi bi-funnel-fill me-2"></i>
                                        <strong>Filtres actifs:</strong> ' . implode(' | ', $filtresActifs) . '
                                        <a href="?view=recherche/depot_soutenance&section=' . $selectedSection . '&tab=' . $activeTab . '" 
                                        class="btn btn-sm btn-outline-secondary ms-2">
                                            <i class="bi bi-x-circle"></i> Effacer
                                        </a>
                                    </div>';
                            }
                            ?>
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Date et heure</th>
                                                <th>Lieu</th>
                                                <th>Étudiant</th>
                                                <th>Sujet</th>
                                                <th>Jury</th>
                                                <th>Lecteurs</th>
                                                <th>Statut</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $i = 1;
                                            foreach ($soutenances as $soutenance):
                                                $statusClass = '';
                                                switch ($soutenance['statut']) {
                                                    case 'Programmée': $statusClass = 'bg-primary'; break;
                                                    case 'Terminée': $statusClass = 'bg-success'; break;
                                                    case 'Reportée': $statusClass = 'bg-warning'; break;
                                                    case 'Annulée': $statusClass = 'bg-danger'; break;
                                                }
                                            ?>
                                            <tr>
                                                <td><?= $i++ ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($soutenance['date_soutenance'])) ?></td>
                                                <td><?= htmlspecialchars($soutenance['lieu']) ?></td>
                                                <td><?= htmlspecialchars($soutenance['nom_etudiant']) ?></td>
                                                <td><?= htmlspecialchars($soutenance['intitule']) ?></td>
                                                <td>
                                                    <?php if (isset($soutenance['jury_designation'])): ?>
                                                        <strong>Jury:</strong> <?= htmlspecialchars($soutenance['jury_designation']) ?><br>
                                                        <small>Président: <?= htmlspecialchars($soutenance['president_nom'] ?? 'Non défini') ?></small><br>
                                                        <small>Secrétaire: <?= htmlspecialchars($soutenance['secretaire_nom'] ?? 'Non défini') ?></small>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Non assigné</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if (!empty($soutenance['lecteurs'])) {
                                                        $lecteurs = explode('|', $soutenance['lecteurs']);
                                                        echo '<strong>1<sup>er</sup> lecteur:</strong> ' . htmlspecialchars($lecteurs[0] ?? 'Non défini') . '<br>';
                                                        echo '<strong>2<sup>ème</sup> lecteur:</strong> ' . htmlspecialchars($lecteurs[1] ?? 'Non défini');
                                                    } else {
                                                        echo '<span class="badge bg-secondary">Non assignés</span>';
                                                    }
                                                    ?>
                                                </td>

                                                <td><span class="badge <?= $statusClass ?>"><?= $soutenance['statut'] ?></span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning" onclick="modifierSoutenance(<?= $soutenance['idsoutenance'] ?>)">
                                                        <i class="bi bi-pencil-square"></i> Modifier
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            
                                            <?php if (empty($soutenances)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center">Aucune soutenance programmée trouvée pour cette section.</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour l'enregistrement d'un dépôt de mémoire -->
<div class="modal fade" id="memoireModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enregistrer un dépôt de mémoire</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="controller/depot_soutenance_controller.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="depot_memoire">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="id_sujet" class="form-label">Sujet de recherche</label>
                            <select name="id_sujet" id="id_sujet" class="form-select" required>
                                <option value="">Sélectionnez un sujet validé</option>
                                <?php 
                                // Récupérer les sujets validés avec étudiants pour la section sélectionnée
                                $sujets = $universite->getSujetsValidesParSection($selectedSection, $currentYear['idannee_acad']);
                                foreach ($sujets as $sujet): 
                                ?>
                                <option value="<?= $sujet['idsujets'] ?>">
                                    <?= htmlspecialchars($sujet['noms']) ?> - <?= htmlspecialchars($sujet['intitule']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un sujet.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="date_depot" class="form-label">Date de dépôt</label>
                            <input type="date" name="date_depot" id="date_depot" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            <div class="invalid-feedback">Veuillez sélectionner une date de dépôt.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="fichier" class="form-label">Fichier du mémoire (PDF)</label>
                            <input type="file" name="fichier" id="fichier" class="form-control" accept=".pdf">
                            <div class="invalid-feedback">Veuillez sélectionner un fichier PDF.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="observation" class="form-label">Observations</label>
                            <textarea name="observation" id="observation" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour l'enregistrement d'un rapport de stage -->
<div class="modal fade" id="rapportModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enregistrer un rapport de stage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="controller/depot_soutenance_controller.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>

                    <input type="hidden" name="action" value="depot_rapport">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="etudiant_id" class="form-label">Étudiant</label>
                            <select name="etudiant_id" id="etudiant_id" class="form-select" required>
                                <option value="">Sélectionnez un étudiant</option>
                                <?php 
                                // Récupérer les étudiants de la section
                                $etudiants = $universite->getEtudiantsBySection($selectedSection, $currentYear['idannee_acad']);
                                foreach ($etudiants as $etudiant): 
                                ?>
                                <option value="<?= $etudiant['idetudiant'] ?>">
                                    <?= htmlspecialchars($etudiant['noms']) ?> (<?= htmlspecialchars($etudiant['matricule']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un étudiant.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="titre" class="form-label">Titre du rapport</label>
                            <input type="text" name="titre" id="titre" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir un titre.</div>
                        </div>
                    </div>

                    <!-- Dans le modal rapportModal, ajoutez ceci après le champ d'observation -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="fichier_rapport" class="form-label">Fichier du rapport (PDF)</label>
                            <input type="file" name="fichier_rapport" id="fichier_rapport" class="form-control" accept=".pdf">
                            <div class="invalid-feedback">Veuillez sélectionner un fichier PDF valide.</div>
                        </div>
                    </div>

                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="date_depot" class="form-label">Date de dépôt</label>
                            <input type="date" name="date_depot" id="date_depot" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            <div class="invalid-feedback">Veuillez sélectionner une date de dépôt.</div>
                        </div>
                        <div class="col-md-6">
                        <label for="lieu_stage" class="form-label">Lieu du stage</label>
                            <input type="text" name="lieu_stage" id="lieu_stage" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir le lieu du stage.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="date_debut" class="form-label">Date de début du stage</label>
                            <input type="date" name="date_debut" id="date_debut" class="form-control" required>
                            <div class="invalid-feedback">Veuillez sélectionner une date de début.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="date_fin" class="form-label">Date de fin du stage</label>
                            <input type="date" name="date_fin" id="date_fin" class="form-control" required>
                            <div class="invalid-feedback">Veuillez sélectionner une date de fin.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="encadreur_id" class="form-label">Encadreur</label>
                            <select name="encadreur_id" id="encadreur_id" class="form-select" required>
                                <option value="">Sélectionnez un encadreur</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                <option value="<?= $enseignant['idAgent'] ?>">
                                    <?= htmlspecialchars($enseignant['noms']) ?> (<?= htmlspecialchars($enseignant['gradeDesignation'] ?? 'Non défini') ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un encadreur.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="observation" class="form-label">Observations</label>
                            <textarea name="observation" id="observation_rapport" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>




<!-- Modal pour programmer une soutenance -->
<div class="modal fade" id="soutenanceModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Programmer une soutenance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="controller/depot_soutenance_controller.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="programmer_soutenance">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="id_sujet_soutenance" class="form-label">Sujet de recherche</label>
                            <select name="id_sujet" id="id_sujet_soutenance" class="form-select" required>
                                <option value="">Sélectionnez un sujet avec dépôt</option>
                                <?php 
                                // Récupérer les sujets avec dépôt pour la section sélectionnée
                                $sujetsAvecDepot = $universite->getSujetsAvecDepotParSection($selectedSection, $currentYear['idannee_acad']);
                                foreach ($sujetsAvecDepot as $sujet): 
                                ?>
                                <option value="<?= $sujet['idsujets'] ?>">
                                    <?= htmlspecialchars($sujet['noms']) ?> - <?= htmlspecialchars($sujet['intitule']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un sujet.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="date_soutenance" class="form-label">Date de soutenance</label>
                            <input type="date" name="date_soutenance" id="date_soutenance" class="form-control" required>
                            <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="heure_soutenance" class="form-label">Heure de soutenance</label>
                            <input type="time" name="heure_soutenance" id="heure_soutenance" class="form-control" required>
                            <div class="invalid-feedback">Veuillez sélectionner une heure.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="lieu" class="form-label">Lieu de la soutenance</label>
                            <input type="text" name="lieu" id="lieu" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir le lieu de la soutenance.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="id_jury" class="form-label">Jury</label>
                            <select name="id_jury" id="id_jury" class="form-select" required>
                                <option value="">Sélectionner un jury</option>
                                <?php 
                                // Récupérer les jurys pour l'année académique actuelle
                                $jurys = $soutenanceModel->getAllJurys($currentYear['idannee_acad'], $selectedSection);
                                foreach ($jurys as $jury): 
                                ?>
                                <option value="<?= $jury['idjury'] ?>">
                                    <?= htmlspecialchars($jury['designation']) ?> - 
                                    Président: <?= htmlspecialchars($jury['president_nom']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un jury.</div>
                            <small class="text-muted">Si le jury souhaité n'est pas dans la liste, veuillez d'abord le créer dans la <a href="?view=recherche/gestion_jurys" target="_blank">gestion des jurys</a>.</small>
                        </div>
                    </div>
                    
                    <h5 class="mt-4 mb-3">Lecteurs du travail</h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="id_lecteur1" class="form-label">Premier lecteur</label>
                            <select name="id_lecteur1" id="id_lecteur1" class="form-select lecteur-select" required>
                                <option value="">Sélectionner un lecteur</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?= $enseignant['idAgent'] ?>">
                                        <?= htmlspecialchars($enseignant['gradeDesignation'] ?? '') ?> 
                                        <?= htmlspecialchars($enseignant['noms']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner le premier lecteur.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="id_lecteur2" class="form-label">Second lecteur</label>
                            <select name="id_lecteur2" id="id_lecteur2" class="form-select lecteur-select" required>
                                <option value="">Sélectionner un lecteur</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?= $enseignant['idAgent'] ?>">
                                        <?= htmlspecialchars($enseignant['gradeDesignation'] ?? '') ?> 
                                        <?= htmlspecialchars($enseignant['noms']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner le second lecteur.</div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Programmer la soutenance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour modifier une soutenance -->
<div class="modal fade" id="editSoutenanceModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une soutenance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="controller/depot_soutenance_controller.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="update_soutenance">
                    <input type="hidden" name="id_soutenance" id="edit_id_soutenance">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_date_soutenance" class="form-label">Date de soutenance</label>
                            <input type="date" name="date_soutenance" id="edit_date_soutenance" class="form-control" required>
                            <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_heure_soutenance" class="form-label">Heure de soutenance</label>
                            <input type="time" name="heure_soutenance" id="edit_heure_soutenance" class="form-control" required>
                            <div class="invalid-feedback">Veuillez sélectionner une heure.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="edit_lieu" class="form-label">Lieu de la soutenance</label>
                            <input type="text" name="lieu" id="edit_lieu" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir le lieu de la soutenance.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_statut" class="form-label">Statut</label>
                            <select name="statut" id="edit_statut" class="form-select" required>
                                <option value="Programmée">Programmée</option>
                                <option value="Terminée">Terminée</option>
                                <option value="Reportée">Reportée</option>
                                <option value="Annulée">Annulée</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_jury_id" class="form-label">Jury</label>
                            <select name="jury_id" id="edit_jury_id" class="form-select">
                                <option value="">Sélectionner un jury</option>
                                <?php 
                                // Récupérer les jurys pour l'année académique actuelle
                                $jurys = $soutenanceModel->getAllJurys($currentYear['idannee_acad'], $selectedSection);
                                foreach ($jurys as $jury): 
                                ?>
                                <option value="<?= $jury['idjury'] ?>">
                                    <?= htmlspecialchars($jury['designation']) ?> - 
                                    Président: <?= htmlspecialchars($jury['president_nom']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_lecteur1" class="form-label">Premier lecteur</label>
                            <select name="lecteurs[0][id_enseignant]" id="edit_lecteur1" class="form-select">
                                <option value="">Sélectionner un enseignant</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                <option value="<?= $enseignant['idAgent'] ?>">
                                    <?= htmlspecialchars($enseignant['noms']) ?> (<?= htmlspecialchars($enseignant['gradeDesignation'] ?? 'Non défini') ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_lecteur2" class="form-label">Deuxième lecteur</label>
                            <select name="lecteurs[1][id_enseignant]" id="edit_lecteur2" class="form-select">
                                <option value="">Sélectionner un enseignant</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                <option value="<?= $enseignant['idAgent'] ?>">
                                    <?= htmlspecialchars($enseignant['noms']) ?> (<?= htmlspecialchars($enseignant['gradeDesignation'] ?? 'Non défini') ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3" id="note_container" style="display: none;">
                        <div class="col-md-12">
                            <label for="edit_note" class="form-label">Note finale</label>
                            <input type="number" name="note_finale" id="edit_note" class="form-control" min="0" max="20" step="0.1">
                            <div class="invalid-feedback">Veuillez saisir une note valide (0-20).</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_commentaire" class="form-label">Commentaire</label>
                            <textarea name="commentaire" id="edit_commentaire" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>






<script>
   
   document.addEventListener('DOMContentLoaded', function () {
    // Initialisation de la validation des formulaires Bootstrap
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Ajout dynamique de membres du jury - Vérifier si l'élément existe
    const addJuryMemberBtn = document.getElementById('addJuryMember');
    if (addJuryMemberBtn) {
        let juryIndex = 2;
        addJuryMemberBtn.addEventListener('click', function() {
            const juryContainer = document.querySelector('.jury-container');
            if (juryContainer) {
                // Code pour ajouter des membres de jury...
            }
        });
    }
    
    // Gestion des lecteurs (pour éviter les doublons)
    const lecteurSelects = document.querySelectorAll('.lecteur-select');
    if (lecteurSelects.length >= 2) {
        lecteurSelects.forEach(select => {
            select.addEventListener('change', function() {
                verifierLecteursDifferents();
            });
        });
    }
    
    function verifierLecteursDifferents() {
        const lecteur1 = document.getElementById('id_lecteur1');
        const lecteur2 = document.getElementById('id_lecteur2');
        
        if (lecteur1 && lecteur2 && lecteur1.value && lecteur2.value && 
            lecteur1.value === lecteur2.value) {
            lecteur2.setCustomValidity('Les deux lecteurs doivent être différents');
        } else if (lecteur2) {
            lecteur2.setCustomValidity('');
        }
    }
    
    // Gestion du statut de soutenance et de l'affichage du champ note
    if (document.getElementById('edit_statut')) {
        document.getElementById('edit_statut').addEventListener('change', function() {
            const noteContainer = document.getElementById('note_container');
            if (this.value === 'Terminée') {
                noteContainer.style.display = 'block';
                document.getElementById('edit_note').setAttribute('required', '');
            } else {
                noteContainer.style.display = 'none';
                document.getElementById('edit_note').removeAttribute('required');
            }
        });
    }
});

    // Fonction pour programmer une soutenance à partir d'un sujet
    function programmerSoutenance(idSujet) {
        document.getElementById('id_sujet_soutenance').value = idSujet;
        new bootstrap.Modal(document.getElementById('soutenanceModal')).show();
    }
    
    // Fonction pour modifier une soutenance
    function modifierSoutenance(idSoutenance) {
    // Récupérer les données de la soutenance via une requête AJAX
    fetch('controller/get_soutenance.php?id=' + idSoutenance)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const soutenance = data.soutenance;
                
                // Remplir le formulaire avec les données
                document.getElementById('edit_id_soutenance').value = soutenance.idsoutenance;
                
                // Découper la date et l'heure
                const dateParts = soutenance.date_soutenance.split(' ');
                document.getElementById('edit_date_soutenance').value = dateParts[0];
                document.getElementById('edit_heure_soutenance').value = dateParts[1].substring(0, 5);
                
                document.getElementById('edit_lieu').value = soutenance.lieu;
                document.getElementById('edit_statut').value = soutenance.statut;
                
                // Sélectionner le jury
                if (soutenance.id_jury) {
                    document.getElementById('edit_jury_id').value = soutenance.id_jury;
                }
                
                // Sélectionner les lecteurs
                if (soutenance.lecteurs && soutenance.lecteurs.length > 0) {
                    // Premier lecteur
                    const premierLecteur = soutenance.lecteurs.find(l => l.est_premier_lecteur == 1);
                    if (premierLecteur) {
                        document.getElementById('edit_lecteur1').value = premierLecteur.idenseignant;
                    }
                    
                    // Deuxième lecteur
                    const deuxiemeLecteur = soutenance.lecteurs.find(l => l.est_premier_lecteur == 0);
                    if (deuxiemeLecteur) {
                        document.getElementById('edit_lecteur2').value = deuxiemeLecteur.idenseignant;
                    }
                }
                
                // Note finale
                if (soutenance.note_finale) {
                    document.getElementById('edit_note').value = soutenance.note_finale;
                }
                
                // Commentaire
                if (soutenance.commentaire) {
                    document.getElementById('edit_commentaire').value = soutenance.commentaire;
                }
                
                // Déclencher l'événement change pour afficher/masquer la note
                const event = new Event('change');
                document.getElementById('edit_statut').dispatchEvent(event);
                
                // Afficher la modal
                new bootstrap.Modal(document.getElementById('editSoutenanceModal')).show();
            } else {
                // Afficher un message d'erreur
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: data.message || 'Impossible de récupérer les données de la soutenance'
                });
            }
        })
        .catch(error => {
            console.error('Erreur lors de la récupération des données:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la communication avec le serveur'
            });
        });
}

    
    // Fonction pour combiner la date et l'heure avant la soumission
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const dateInputId = this.querySelector('[name="date_soutenance"]')?.id;
            const heureInputId = this.querySelector('[name="heure_soutenance"]')?.id;
            
            if (dateInputId && heureInputId) {
                e.preventDefault();
                
                const dateInput = document.getElementById(dateInputId);
                const heureInput = document.getElementById(heureInputId);
                
                if (dateInput && heureInput && dateInput.value && heureInput.value) {
                    // Créer un champ caché pour stocker la date et l'heure combinées
                    const combinedInput = document.createElement('input');
                    combinedInput.type = 'hidden';
                    combinedInput.name = 'date_soutenance';
                    combinedInput.value = `${dateInput.value} ${heureInput.value}:00`;
                    
                    // Supprimer les champs d'origine
                    dateInput.removeAttribute('name');
                    heureInput.removeAttribute('name');
                    
                    // Ajouter le champ combiné au formulaire
                    this.appendChild(combinedInput);
                    
                    // Soumettre le formulaire
                    this.submit();
                }
            }
        });
    });



    // Gestion des filtres dynamiques
document.addEventListener('DOMContentLoaded', function() {
    // Réinitialisation des filtres
    document.querySelector('a.btn-secondary').addEventListener('click', function(e) {
        e.preventDefault();
        window.location.href = this.getAttribute('href');
    });
    
    // Mise à jour des filtres en fonction de l'onglet actif
    const tabLinks = document.querySelectorAll('.nav-tabs .nav-link');
    tabLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Conserver les filtres communs lors du changement d'onglet
            const anneeAcad = document.getElementById('annee_acad').value;
            const etudiant = document.getElementById('etudiant').value;
            
            let url = this.getAttribute('href');
            if (anneeAcad) {
                url += '&annee_acad=' + anneeAcad;
            }
            if (etudiant) {
                url += '&etudiant=' + encodeURIComponent(etudiant);
            }
            
            this.setAttribute('href', url);
        });
    });
});

</script>



<?php include "./views/include/footer.php"; ?>



