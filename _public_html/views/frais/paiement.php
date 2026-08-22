<?php
include "./views/include/header.php";

$universite = new Universite();
$fraisModel = new Frais();

// Récupération des paramètres
$search = isset($_GET['search']) ? $_GET['search'] : '';
$selectedFrais = isset($_GET['frais']) ? intval($_GET['frais']) : 0;
$selectedType = isset($_GET['type']) ? $_GET['type'] : 'academique';
$estComplet = isset($_GET['estComplet']) ? intval($_GET['estComplet']) : null;
$anneeAcadId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;

// Récupérer l'année académique actuelle si non spécifiée
$currentYear = $universite->getCurrentAcademicYear();
$anneeAcadId = $currentYear['idannee_acad'];

// Récupérer les sections accessibles à l'utilisateur
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

// Préparer les filtres pour la requête
$filters = [];
if ($anneeAcadId > 0) {
    $filters['anneeAcadId'] = $anneeAcadId;
}
if ($promotionId > 0) {
    $filters['promotionId'] = $promotionId;
}
if ($estComplet !== null) {
    $filters['estComplet'] = (bool) $estComplet;
}

// Récupérer les paiements
$paiements = [];
if ($selectedType == 'academique') {
    if ($selectedFrais > 0) {
        $paiements = $fraisModel->getPaiementsByFrais($selectedFrais, $anneeAcadId);
        $fraisDetails = $fraisModel->getFraisById($selectedFrais);
    } else {
        $paiements = $fraisModel->getPaiements($search, $filters);
    }
} else {
    if ($selectedFrais > 0) {
        $paiements = $fraisModel->getPaiementsByFraisSoutenance($selectedFrais, $anneeAcadId);
        $fraisDetails = $fraisModel->getFraisSoutenanceById($selectedFrais);
    } else {
        $paiements = $fraisModel->getPaiementsSoutenance($search, $filters);
    }
}

// Récupérer les étudiants pour l'année académique sélectionnée
$etudiants = [];
if ($anneeAcadId > 0) {
    if ($promotionId > 0) {
        $etudiants = $universite->getEtudiantsByPromotion($promotionId, $anneeAcadId);
    } else {
        // Récupérer tous les étudiants (limité si besoin)
        $etudiants = $universite->getEtudiantsByAnneeAcad($anneeAcadId);
    }
}

// Récupérer les frais pour le formulaire d'ajout de paiement
$fraisList = [];
if ($selectedType == 'academique') {
    if ($promotionId > 0) {
        $fraisList = $fraisModel->getFraisByPromotion($promotionId, $anneeAcadId);
    } else {
        // Récupérer tous les frais (limité par section si nécessaire)
        $fraisList = $fraisModel->getAllFrais($anneeAcadId, '');
    }
} else {
    $fraisList = $fraisModel->getAllFraisSoutenance($anneeAcadId, '');
}

// Récupérer les promotions pour les filtres
$promotions = [];
foreach ($sections as $section) {
    $sectionPromotions = $universite->getPromotionsBySection($section['idsection'], $anneeAcadId);
    $promotions = array_merge($promotions, $sectionPromotions);
}




?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES PAIEMENTS<?php echo ($selectedFrais > 0 && isset($fraisDetails)) ? ' - ' . $fraisDetails['designation'] : ''; ?></h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="?view=frais/configuration_frais&type_frais=<?= $selectedType ?>">Frais</a></li>
                <li class="breadcrumb-item active">Paiements</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="row">
                    <!-- Filtres et recherche -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Filtres</h5>
                                <?php
                                
                                    // Traitement des résultats d'importation
                                    $importResult = isset($_GET['result']) ? $_GET['result'] : '';
                                    $importSuccess = isset($_GET['success']) ? intval($_GET['success']) : 0;
                                    $importFailed = isset($_GET['failed']) ? intval($_GET['failed']) : 0;
                                    $importCount = isset($_GET['count']) ? intval($_GET['count']) : 0;

                                    // Message de résultat d'importation
                                    if ($importResult == 'success') {
                                        echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                                                <i class='bi bi-check-circle me-1'></i> Importation réussie! $importCount paiements ont été importés avec succès.
                                                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                                            </div>";
                                    } elseif ($importResult == 'partiel') {
                                        echo "<div class='alert alert-warning alert-dismissible fade show' role='alert'>
                                                <i class='bi bi-exclamation-triangle me-1'></i> Importation partiellement réussie. $importSuccess paiements importés, $importFailed échecs.
                                                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>";
                                        
                                        // Afficher les erreurs
                                        if (isset($_SESSION['import_errors']) && !empty($_SESSION['import_errors'])) {
                                            echo "<ul class='mb-0 mt-2'>";
                                            foreach ($_SESSION['import_errors'] as $error) {
                                                echo "<li>$error</li>";
                                            }
                                            echo "</ul>";
                                            unset($_SESSION['import_errors']);
                                        }
                                        
                                        echo "</div>";
                                    }

                                    // Afficher les autres erreurs
                                    $error = isset($_GET['error']) ? $_GET['error'] : '';
                                    $errorMessage = isset($_GET['message']) ? urldecode($_GET['message']) : '';

                                    if ($error) {
                                        $errorText = "Une erreur s'est produite.";
                                        
                                        switch ($error) {
                                            case 'fichier_non_valide':
                                                $errorText = "Le fichier d'importation n'est pas valide.";
                                                break;
                                            case 'frais_non_trouve':
                                                $errorText = "Le frais sélectionné n'a pas été trouvé.";
                                                break;
                                            case 'donnees_vides':
                                                $errorText = "Aucune donnée valide n'a été trouvée dans le fichier importé.";
                                                break;
                                            case 'erreur_importation':
                                                $errorText = "Erreur lors de l'importation: " . $errorMessage;
                                                break;
                                        }
                                        
                                        echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                                                <i class='bi bi-exclamation-circle me-1'></i> $errorText
                                                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                                            </div>";
                                    }
                                ?>
                                <form method="GET" action="">
                                    <input type="hidden" name="view" value="frais/paiement">
                                    <input type="hidden" name="type" value="<?= $selectedType ?>">
                                    <?php if ($selectedFrais > 0): ?>
                                    <input type="hidden" name="frais" value="<?= $selectedFrais ?>">
                                    <?php endif; ?>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <label for="annee" class="form-label">Année académique</label>
                                            <select name="annee" id="annee" class="form-select">
                                                <?php
                                                $annees = $universite->getAcademicYears();
                                                foreach ($annees as $annee) {
                                                    $selected = ($annee['idannee_acad'] == $anneeAcadId) ? 'selected' : '';
                                                    echo "<option value='{$annee['idannee_acad']}' $selected>{$annee['designation']}</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label for="promotion" class="form-label">Promotion</label>
                                            <select name="promotion" id="promotion" class="form-select">
                                                <option value="0">Toutes les promotions</option>
                                                <?php foreach ($promotions as $promotion): ?>
                                                    <option value="<?= $promotion['idpromotion'] ?>" <?= $promotionId == $promotion['idpromotion'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($promotion['designationPromotion']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label for="estComplet" class="form-label">Statut du paiement</label>
                                            <select name="estComplet" id="estComplet" class="form-select">
                                                <option value="">Tous</option>
                                                <option value="1" <?= $estComplet === 1 ? 'selected' : '' ?>>Complet</option>
                                                <option value="0" <?= $estComplet === 0 ? 'selected' : '' ?>>Partiel</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label for="search" class="form-label">Recherche</label>
                                            <div class="input-group">
                                                <input type="text" name="search" id="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Nom, matricule...">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Liste des paiements -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                            <h5 class="card-title">
                                Liste des paiements
                                <span>
                                    | <a data-bs-toggle="modal" data-bs-target="#addPaiementModal" class="btnPage">
                                        <i class="bi bi-plus-circle-fill"></i> Enregistrer un paiement
                                    </a>
                                    | <a data-bs-toggle="modal" data-bs-target="#importPaiementModal" class="btnPage">
                                        <i class="bi bi-file-earmark-excel"></i> Importer des paiements
                                    </a>
                                </span>
                            </h5>


                                <!-- Tableau des paiements -->
                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Date</th>
                                            <th>Étudiant</th>
                                            <th>Frais</th>
                                            <th>Montant payé</th>
                                            <th>Montant total</th>
                                            <th>Statut</th>
                                            <th>Mode de paiement</th>
                                            <th>Référence</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $i = 1;
                                        foreach ($paiements as $paiement): 
                                            // Déterminer les champs selon le type de paiement
                                            if ($selectedType == 'academique') {
                                                $datePaiement = $paiement['datePaiement'];
                                                $nomEtudiant = $paiement['nom_etudiant'];
                                                $designationFrais = isset($paiement['designation_frais']) ? $paiement['designation_frais'] : $fraisDetails['designation'];
                                                $montantPaye = $paiement['montantPaye'];
                                                $montantTotal = $paiement['montant_total'];
                                                $devise = $paiement['devise'];
                                                $estComplet = $paiement['estComplet'];
                                                $modePaiement = $paiement['modePaiement'];
                                                $reference = $paiement['referencePaiement'];
                                                $idPaiement = $paiement['idpaiement'];
                                            } else {
                                                $datePaiement = $paiement['date_paiement'];
                                                $nomEtudiant = $paiement['nom_etudiant'];
                                                $designationFrais = isset($paiement['designation_frais']) ? $paiement['designation_frais'] : $fraisDetails['designation'];
                                                $montantPaye = $paiement['montant_paye'];
                                                $montantTotal = $paiement['montant_total'];
                                                $devise = $paiement['devise'];
                                                $estComplet = $paiement['est_complet'];
                                                $modePaiement = $paiement['mode_paiement'];
                                                $reference = $paiement['reference_paiement'];
                                                $idPaiement = $paiement['idpaiement_soutenance'];
                                            }
                                        ?>
                                        <tr>
                                            <td><?= $i++ ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($datePaiement)) ?></td>
                                            <td><?= htmlspecialchars($nomEtudiant) ?></td>
                                            <td><?= htmlspecialchars($designationFrais) ?></td>
                                            <td><?= number_format($montantPaye, 2) ?> <?= $devise ?></td>
                                            <td><?= number_format($montantTotal, 2) ?> <?= $devise ?></td>
                                            <td>
                                                <?php if ($estComplet): ?>
                                                    <span class="badge bg-success">Complet</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Partiel</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($modePaiement) ?></td>
                                            <td><?= htmlspecialchars($reference) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" onclick="openEditPaiementModal(
                                                    '<?= $idPaiement ?>', 
                                                    '<?= $selectedType ?>', 
                                                    '<?= $montantPaye ?>', 
                                                                                                        '<?= $reference ?>',
                                                    '<?= $modePaiement ?>',
                                                    '<?= addslashes($paiement['commentaire'] ?? '') ?>'
                                                )">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deletePaiement('<?= $idPaiement ?>', '<?= $selectedType ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <button class="btn btn-sm btn-info" title="Imprimer reçu" onclick="printReceipt('<?= $idPaiement ?>', '<?= $selectedType ?>')">
                                                    <i class="bi bi-printer"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>

                                        <?php if (empty($paiements)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center">Aucun paiement trouvé</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    



</main>



<!-- Modal pour importer des paiements -->
<div class="modal fade" id="importPaiementModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importer des paiements</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="importPaiementForm" method="POST" action="controller/import_paiement_controller.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="import">
                    <input type="hidden" name="type_paiement" value="<?= $selectedType ?>">
                    <input type="hidden" name="idAnneeAcad" value="<?= $anneeAcadId ?>">

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="import_promotion" class="form-label">Promotion</label>
                            <select name="promotion" id="import_promotion" class="form-select" required onchange="loadFraisByPromotionForImport(this.value)">
                                <option value="">Sélectionnez une promotion</option>
                                <?php foreach ($promotions as $promotion): ?>
                                    <option value="<?= $promotion['idpromotion'] ?>">
                                        <?= htmlspecialchars($promotion['designationPromotion']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une promotion.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="import_frais" class="form-label">Frais</label>
                            <select name="frais" id="import_frais" class="form-select" required disabled>
                                <option value="">Sélectionnez d'abord une promotion</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un frais.</div>
                        </div>
                    </div>
                    
                    
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="import_mode_paiement" class="form-label">Mode de paiement</label>
                            <select name="mode_paiement" id="import_mode_paiement" class="form-select" required>
                                <option value="">Sélectionnez</option>
                                <option value="Espèces">Espèces</option>
                                <option value="Chèque">Chèque</option>
                                <option value="Virement">Virement bancaire</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Carte de crédit">Carte de crédit</option>
                                <option value="Autre">Autre</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un mode de paiement.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="import_type" class="form-label">Type d'importation</label>
                            <select name="import_type" id="import_type" class="form-select" required onchange="toggleImportFields()">
                                <option value="complet">Paiement complet (montant total du frais)</option>
                                <option value="partiel">Paiement partiel (avec montants spécifiés)</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un type d'importation.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3" id="date_paiement_row">
                        <div class="col-md-6">
                            <label for="import_date_paiement" class="form-label">Date de paiement</label>
                            <input type="date" name="date_paiement" id="import_date_paiement" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            <div class="invalid-feedback">Veuillez sélectionner une date de paiement.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="import_prefix_reference" class="form-label">Préfixe pour les références</label>
                            <input type="text" name="prefix_reference" id="import_prefix_reference" class="form-control" value="IMP-<?= date('Ymd') ?>-" required>
                            <div class="invalid-feedback">Veuillez entrer un préfixe pour les références.</div>
                            <small class="text-muted">Ce préfixe sera suivi d'un numéro incrémental pour chaque paiement.</small>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="import_file" class="form-label">Fichier d'importation</label>
                            <input type="file" name="import_file" id="import_file" class="form-control" accept=".csv,.xlsx,.xls" required>
                            <div class="invalid-feedback">Veuillez sélectionner un fichier à importer.</div>
                            <small class="text-muted">Formats acceptés: CSV, Excel (.xlsx, .xls)</small>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6>Format du fichier:</h6>
                        <p class="mb-1" id="format_info_complet">
                            <strong>Pour les paiements complets:</strong> Un matricule par ligne.
                        </p>
                        <p class="mb-1" id="format_info_partiel" style="display: none;">
                            <strong>Pour les paiements partiels:</strong> Matricule, montant, (date optionnelle) séparés par des virgules.
                        </p>
                        <p class="mb-0">
                            <a href="#" onclick="downloadTemplate(); return false;" class="text-primary">
                                <i class="bi bi-download"></i> Télécharger un modèle
                            </a>
                        </p>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="importPaiementBtn" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Importer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour ajouter un paiement -->
<div class="modal fade" id="addPaiementModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enregistrer un paiement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="paiementForm" method="POST" action="controller/paiement_controller.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="type_paiement" value="<?= $selectedType ?>">
                    <input type="hidden" name="idAnneeAcad" value="<?= $anneeAcadId ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="etudiant" class="form-label">Étudiant</label>
                            <select name="etudiant" id="etudiant" class="form-select" required onchange="loadFraisForEtudiant(this.value)">
                                <option value="">Sélectionnez un étudiant</option>
                                <?php foreach ($etudiants as $etudiant): ?>
                                    <option value="<?= $etudiant['idetudiant'] ?>">
                                        <?= htmlspecialchars($etudiant['matricule'] . ' - ' . $etudiant['noms']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <div class="invalid-feedback">Veuillez sélectionner un étudiant.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="frais" class="form-label">Frais</label>
                            <select name="frais" id="frais" class="form-select" required onchange="updateFraisDetails()">
                                <?php if ($selectedFrais > 0 && isset($fraisDetails)): ?>
                                    <option value="<?= $selectedFrais ?>" selected data-montant="<?= $fraisDetails['montant'] ?>" data-devise="<?= $fraisDetails['devise'] ?>">
                                        <?= htmlspecialchars($fraisDetails['designation']) ?> (<?= number_format($fraisDetails['montant'], 2) ?> <?= $fraisDetails['devise'] ?>)
                                    </option>
                                <?php else: ?>
                                    <option value="">Sélectionnez un frais</option>
                                    <?php foreach ($fraisList as $frais): ?>
                                        <option value="<?= $selectedType == 'academique' ? $frais['idfrais'] : $frais['idfrais_soutenance'] ?>" 
                                                data-montant="<?= $frais['montant'] ?>" 
                                                data-devise="<?= $frais['devise'] ?>">
                                            <?= htmlspecialchars($frais['designation']) ?> (<?= number_format($frais['montant'], 2) ?> <?= $frais['devise'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un frais.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="montantPaye" class="form-label">Montant payé</label>
                            <div class="input-group">
                                <input type="number" name="montantPaye" id="montantPaye" class="form-control" step="0.01" min="0" required>
                                <span class="input-group-text" id="deviseText">USD</span>
                            </div>
                            <div class="invalid-feedback">Veuillez entrer un montant valide.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="montantTotal" class="form-label">Montant total</label>
                            <div class="input-group">
                                <input type="text" id="montantTotal" class="form-control" readonly>
                                <span class="input-group-text" id="deviseTotalText">USD</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="referencePaiement" class="form-label">Référence du paiement</label>
                            <input type="text" name="referencePaiement" id="referencePaiement" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer une référence.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="modePaiement" class="form-label">Mode de paiement</label>
                            <select name="modePaiement" id="modePaiement" class="form-select" required>
                                <option value="">Sélectionnez</option>
                                <option value="Espèces">Espèces</option>
                                <option value="Chèque">Chèque</option>
                                <option value="Virement">Virement bancaire</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Carte de crédit">Carte de crédit</option>
                                <option value="Autre">Autre</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un mode de paiement.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="commentaire" class="form-label">Commentaire</label>
                            <textarea name="commentaire" id="commentaire" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addPaiementBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un paiement -->
<div class="modal fade" id="editPaiementModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un paiement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editPaiementForm" method="POST" action="controller/paiement_controller.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="type_paiement" value="<?= $selectedType ?>" id="edit_type_paiement">
                    <input type="hidden" name="idPaiement" id="edit_idPaiement">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_montantPaye" class="form-label">Montant payé</label>
                            <div class="input-group">
                                <input type="number" name="montantPaye" id="edit_montantPaye" class="form-control" step="0.01" min="0" required>
                                <span class="input-group-text" id="edit_deviseText">USD</span>
                            </div>
                            <div class="invalid-feedback">Veuillez entrer un montant valide.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_referencePaiement" class="form-label">Référence du paiement</label>
                            <input type="text" name="referencePaiement" id="edit_referencePaiement" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer une référence.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_modePaiement" class="form-label">Mode de paiement</label>
                            <select name="modePaiement" id="edit_modePaiement" class="form-select" required>
                                <option value="">Sélectionnez</option>
                                <option value="Espèces">Espèces</option>
                                <option value="Chèque">Chèque</option>
                                <option value="Virement">Virement bancaire</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Carte de crédit">Carte de crédit</option>
                                <option value="Autre">Autre</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un mode de paiement.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_commentaire" class="form-label">Commentaire</label>
                            <textarea name="commentaire" id="edit_commentaire" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editPaiementBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>


/**
 * Bascule l'affichage des champs d'importation selon le type choisi
 */
function toggleImportFields() {
    const importType = document.getElementById('import_type').value;
    const formatInfoComplet = document.getElementById('format_info_complet');
    const formatInfoPartiel = document.getElementById('format_info_partiel');
    
    if (importType === 'complet') {
        formatInfoComplet.style.display = 'block';
        formatInfoPartiel.style.display = 'none';
    } else {
        formatInfoComplet.style.display = 'none';
        formatInfoPartiel.style.display = 'block';
    }
}

/**
 * Génère et télécharge un fichier modèle pour l'importation
 */
function downloadTemplate() {
    const importType = document.getElementById('import_type').value;
    let content = '';
    let filename = '';
    
    if (importType === 'complet') {
        content = 'Matricule\nXXX001\nXXX002\nXXX003';
        filename = 'modele_import_paiements_complets.csv';
    } else {
        content = 'Matricule,Montant,Date (optionnelle)\nXXX001,150,2023-01-01\nXXX002,200,\nXXX003,175,';
        filename = 'modele_import_paiements_partiels.csv';
    }
    
    // Créer un élément a temporaire pour le téléchargement
    const element = document.createElement('a');
    element.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(content));
    element.setAttribute('download', filename);
    element.style.display = 'none';
    document.body.appendChild(element);
    element.click();
    document.body.removeChild(element);
}

// Initialiser l'affichage des champs d'importation
document.addEventListener('DOMContentLoaded', function() {
    // Les autres initialisations existantes...
    
    // Initialiser l'affichage pour l'importation
    toggleImportFields();
});

// Fonction pour charger les frais spécifiques à un étudiant
function loadFraisForEtudiant(etudiantId) {
    if (!etudiantId) {
        return;
    }
    
    // Afficher un indicateur de chargement
    const fraisSelect = document.getElementById('frais');
    fraisSelect.innerHTML = '<option value="">Chargement...</option>';
    
    // Récupérer l'année académique actuelle
    const anneeAcadId = <?= $anneeAcadId ?>;
    
    // Faire une requête AJAX pour récupérer les frais de l'étudiant
    fetch(`controller/get_frais_etudiant.php?etudiantId=${etudiantId}&anneeAcadId=${anneeAcadId}&type=${encodeURIComponent('<?= $selectedType ?>')}`)
        .then(response => response.json())
        .then(data => {
            // Réinitialiser le select des frais
            fraisSelect.innerHTML = '<option value="">Sélectionnez un frais</option>';
            
            // Si aucun frais n'est disponible
            if (data.length === 0) {
                fraisSelect.innerHTML += '<option value="" disabled>Aucun frais disponible pour cet étudiant</option>';
                document.getElementById('montantTotal').value = '';
                document.getElementById('deviseText').textContent = 'USD';
                document.getElementById('deviseTotalText').textContent = 'USD';
                return;
            }
            
            // Grouper les frais: d'abord ceux qui ont un reste à payer, puis ceux qui sont soldés
            const fraisAPayer = data.filter(frais => parseFloat(frais.montantRestant) > 0);
            const fraisSoldes = data.filter(frais => parseFloat(frais.montantRestant) <= 0);
            
            // Ajouter les options pour les frais à payer
            if (fraisAPayer.length > 0) {
                fraisSelect.innerHTML += '<optgroup label="Frais à payer">';
                fraisAPayer.forEach(frais => {
                    const option = document.createElement('option');
                    option.value = frais.idfrais;
                    option.dataset.montant = frais.montant;
                    option.dataset.montantPaye = frais.montantPaye || 0;
                    option.dataset.montantRestant = frais.montantRestant;
                    option.dataset.devise = frais.devise;
                    option.textContent = `${frais.designation} (${parseFloat(frais.montantRestant).toFixed(2)} ${frais.devise} restant)`;
                    fraisSelect.appendChild(option);
                });
                fraisSelect.innerHTML += '</optgroup>';
            }
            
            // Ajouter les options pour les frais déjà soldés
            if (fraisSoldes.length > 0) {
                fraisSelect.innerHTML += '<optgroup label="Frais soldés">';
                fraisSoldes.forEach(frais => {
                    const option = document.createElement('option');
                    option.value = frais.idfrais;
                    option.dataset.montant = frais.montant;
                    option.dataset.montantPaye = frais.montantPaye || 0;
                    option.dataset.montantRestant = frais.montantRestant;
                    option.dataset.devise = frais.devise;
                    option.textContent = `${frais.designation} (Soldé - ${parseFloat(frais.montant).toFixed(2)} ${frais.devise})`;
                    fraisSelect.appendChild(option);
                });
                fraisSelect.innerHTML += '</optgroup>';
            }
            
            // Mettre à jour les détails du frais
            updateFraisDetails();
        })
        .catch(error => {
            console.error('Erreur lors du chargement des frais:', error);
            fraisSelect.innerHTML = '<option value="">Erreur de chargement</option>';
        });
}


// Fonction pour mettre à jour les détails du frais dans le formulaire d'ajout
function updateFraisDetails() {
    const fraisSelect = document.getElementById('frais');
    const selectedOption = fraisSelect.options[fraisSelect.selectedIndex];
    const montantPayeInput = document.getElementById('montantPaye');
    
    if (selectedOption && selectedOption.value) {
        const montant = selectedOption.dataset.montant;
        const montantPaye = selectedOption.dataset.montantPaye;
        const montantRestant = selectedOption.dataset.montantRestant;
        const devise = selectedOption.dataset.devise;
        
        document.getElementById('montantTotal').value = parseFloat(montant).toFixed(2);
        document.getElementById('deviseText').textContent = devise;
        document.getElementById('deviseTotalText').textContent = devise;
        
        // Afficher le montant déjà payé et le montant restant
        const infoDiv = document.getElementById('paiementInfo') || document.createElement('div');
        infoDiv.id = 'paiementInfo';
        infoDiv.className = 'alert ' + (parseFloat(montantRestant) <= 0 ? 'alert-success' : 'alert-info') + ' mt-2';
        infoDiv.innerHTML = `
            <p class="mb-1"><strong>Montant total:</strong> ${parseFloat(montant).toFixed(2)} ${devise}</p>
            <p class="mb-1"><strong>Déjà payé:</strong> ${parseFloat(montantPaye).toFixed(2)} ${devise}</p>
            <p class="mb-0"><strong>Restant:</strong> ${parseFloat(montantRestant).toFixed(2)} ${devise}</p>
        `;
        
        // Ajouter l'élément d'information après le champ de montant
        const montantPayeParent = montantPayeInput.parentElement.parentElement;
        if (!document.getElementById('paiementInfo')) {
            montantPayeParent.insertAdjacentElement('afterend', infoDiv);
        }
        
        // Si le frais est déjà soldé, désactiver le paiement
        if (parseFloat(montantRestant) <= 0) {
            montantPayeInput.disabled = true;
            montantPayeInput.value = "0.00";
            montantPayeInput.placeholder = "Frais déjà soldé";
            
            // Ajouter un message d'avertissement
            if (!document.getElementById('soldMessage')) {
                const soldMessage = document.createElement('div');
                soldMessage.id = 'soldMessage';
                soldMessage.className = 'alert alert-warning mt-2';
                soldMessage.innerHTML = '<strong>Attention!</strong> Ce frais est déjà entièrement payé.';
                infoDiv.insertAdjacentElement('afterend', soldMessage);
            }
        } else {
            // Limiter le montant maximal à payer au montant restant
            montantPayeInput.disabled = false;
            montantPayeInput.max = montantRestant;
            montantPayeInput.placeholder = `Max: ${parseFloat(montantRestant).toFixed(2)} ${devise}`;
            
            // Supprimer le message d'avertissement s'il existe
            const soldMessage = document.getElementById('soldMessage');
            if (soldMessage) soldMessage.remove();
        }
    } else {
        document.getElementById('montantTotal').value = '';
        document.getElementById('deviseText').textContent = 'USD';
        document.getElementById('deviseTotalText').textContent = 'USD';
        
        // Supprimer l'info de paiement s'il existe
        const infoDiv = document.getElementById('paiementInfo');
        if (infoDiv) infoDiv.remove();
        
        // Supprimer le message d'avertissement s'il existe
        const soldMessage = document.getElementById('soldMessage');
        if (soldMessage) soldMessage.remove();
        
        // Réinitialiser le champ de montant
        montantPayeInput.disabled = false;
        montantPayeInput.max = '';
        montantPayeInput.placeholder = '';
    }
}

// Fonction pour ouvrir le modal de modification d'un paiement
function openEditPaiementModal(idPaiement, typePaiement, montantPaye, referencePaiement, modePaiement, commentaire) {
    document.getElementById('edit_idPaiement').value = idPaiement;
    document.getElementById('edit_type_paiement').value = typePaiement;
    document.getElementById('edit_montantPaye').value = montantPaye;
    document.getElementById('edit_referencePaiement').value = referencePaiement;
    document.getElementById('edit_modePaiement').value = modePaiement;
    document.getElementById('edit_commentaire').value = commentaire;
    
    new bootstrap.Modal(document.getElementById('editPaiementModal')).show();
}

// Fonction pour supprimer un paiement
function deletePaiement(idPaiement, typePaiement) {
    Swal.fire({
        title: 'Êtes-vous sûr?',
        text: "Cette action supprimera le paiement et ne peut pas être annulée!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `controller/paiement_controller.php?action=delete&id=${idPaiement}&type=${typePaiement}&frais=<?= $selectedFrais ?>&type_frais=<?= $selectedType ?>`;
        }
    });
}

// Fonction pour imprimer un reçu de paiement
function printReceipt(idPaiement, typePaiement) {
    window.open(`controller/print_receipt.php?id=${idPaiement}&type=${typePaiement}`, '_blank');
}

// Initialiser les détails du frais
document.addEventListener('DOMContentLoaded', function() {
    updateFraisDetails();
});

// Validation des formulaires Bootstrap
(function () {
    'use strict'
    
    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('.needs-validation')
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                
                form.classList.add('was-validated')
            }, false)
        })
    })()



    // À ajouter après les autres fonctions JavaScript

/**
 * Affiche le formulaire d'ajout de paiement pour un étudiant et un frais spécifiques
 */
function showAddPaymentModal(etudiantId, fraisId) {
    // Pré-remplir le formulaire avec l'étudiant et le frais sélectionnés
    document.getElementById('etudiant').value = etudiantId;
    loadFraisForEtudiant(etudiantId);
    
    // Attendre que les frais soient chargés puis sélectionner le bon frais
    setTimeout(() => {
        document.getElementById('frais').value = fraisId;
        updateFraisDetails();
        
        // Ouvrir le modal
        new bootstrap.Modal(document.getElementById('addPaiementModal')).show();
    }, 500);
}

/**
 * Imprime le relevé de paiement d'un étudiant
 */
function printStudentStatement(etudiantId, fraisId = 0) {
    const type = '<?= $selectedType ?>';
    window.open(`controller/print_student_statement.php?etudiant=${etudiantId}&frais=${fraisId}&type=${type}`, '_blank');
}

/**
 * Exporte l'état des paiements en Excel
 */
function exportPaymentStatus() {
    const frais = document.getElementById('frais_rapport').value;
    const promotion = document.getElementById('promotion_rapport').value;
    const type = '<?= $selectedType ?>';
    
    window.location.href = `controller/export_payment_status.php?frais=${frais}&promotion=${promotion}&type=${type}`;
}

/**
 * Charge les frais pour une promotion lors de l'importation
 */
function loadFraisByPromotionForImport(promotionId) {
    if (!promotionId) {
        // Réinitialiser le sélecteur de frais
        const fraisSelect = document.getElementById('import_frais');
        fraisSelect.innerHTML = '<option value="">Sélectionnez d\'abord une promotion</option>';
        fraisSelect.disabled = true;
        return;
    }
    
    // Afficher indicateur de chargement
    const fraisSelect = document.getElementById('import_frais');
    fraisSelect.innerHTML = '<option value="">Chargement...</option>';
    fraisSelect.disabled = true;
    
    // Appel AJAX pour récupérer les frais de la promotion
    fetch(`controller/get_frais_by_promotion.php?promotionId=${promotionId}&type=${encodeURIComponent('<?= $selectedType ?>')}&anneeAcadId=<?= $anneeAcadId ?>`)
        .then(response => response.json())
        .then(data => {
            fraisSelect.innerHTML = '<option value="">Sélectionnez un frais</option>';
            
            if (data.length === 0) {
                fraisSelect.innerHTML += '<option value="" disabled>Aucun frais disponible pour cette promotion</option>';
                fraisSelect.disabled = true;
                return;
            }
            
            // Ajouter les options de frais
            data.forEach(frais => {
                const option = document.createElement('option');
                option.value = frais.id;
                option.dataset.montant = frais.montant;
                option.dataset.devise = frais.devise;
                option.textContent = `${frais.designation} (${parseFloat(frais.montant).toFixed(2)} ${frais.devise})`;
                fraisSelect.appendChild(option);
            });
            
            fraisSelect.disabled = false;
        })
        .catch(error => {
            console.error('Erreur lors du chargement des frais:', error);
            fraisSelect.innerHTML = '<option value="">Erreur de chargement</option>';
            fraisSelect.disabled = true;
        });
}


</script>

<?php include "./views/include/footer.php"; ?>


