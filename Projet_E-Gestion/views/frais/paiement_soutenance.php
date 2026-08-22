<?php
include "./views/include/header.php";
$universite = new Universite();
$fraisModel = new Frais();

// Récupérer l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();

// Récupérer l'ID de l'étudiant s'il est passé en paramètre
$etudiantId = isset($_GET['etudiant']) ? intval($_GET['etudiant']) : 0;

// Récupérer les informations de l'étudiant
$etudiant = null;
if ($etudiantId > 0) {
    $etudiant = $universite->getEtudiantById($etudiantId);
}

// Récupérer les frais de soutenance disponibles pour l'étudiant
$fraisSoutenance = $fraisModel->getFraisSoutenanceForEtudiant($etudiantId, $currentYear['idannee_acad']);

// Récupérer l'historique des paiements de cet étudiant
$paiements = $etudiantId > 0 ? $fraisModel->getPaiementsSoutenanceByEtudiant($etudiantId, $currentYear['idannee_acad']) : [];
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Paiement des frais de soutenance</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=home">Accueil</a></li>
                <li class="breadcrumb-item">Frais</li>
                <li class="breadcrumb-item active">Paiement des frais de soutenance</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">


        <!-- Ajouter ce code après les filtres, dans la section "Filtres" -->
        <div class="col-12 mt-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Exportation des données</h5>
                    
                    <form id="exportForm" method="GET" action="controller/export_soutenance_controller.php" class="row g-3">
                        <div class="col-md-3">
                            <label for="export_section" class="form-label">Section</label>
                            <select name="section" id="export_section" class="form-select" required onchange="loadPromotionsForExport(this.value)">
                                <?php if ($_SESSION['idRole'] == 1): ?>
                                    <option value="0">Toutes les sections</option>
                                <?php endif; ?>
                                <?php 
                                $userSections = $_SESSION['idRole'] == 1 ? $universite->getSections() : $universite->getUserSections($_SESSION['id']);
                                foreach ($userSections as $section): 
                                    if ($_SESSION['idRole'] != 1) {
                                        $section = $universite->getSectionById($section);
                                    }
                                ?>
                                    <option value="<?= $section['idsection'] ?>">
                                        <?= htmlspecialchars($section['designationSection']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            </div>
                        <div class="col-md-3">
                            <label for="export_promotion" class="form-label">Promotion</label>
                            <select name="promotion" id="export_promotion" class="form-select">
                                <option value="0">Toutes les promotions</option>
                                <!-- Les promotions seront chargées dynamiquement via JavaScript -->
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="export_type" class="form-label">Type d'exportation</label>
                            <select name="type" id="export_type" class="form-select" required>
                                <option value="eligible">Étudiants éligibles à la soutenance</option>
                                <option value="litige">Étudiants avec litiges de frais</option>
                            </select>
                        </div>
                        <input type="hidden" name="annee" value="<?= $currentYear['idannee_acad'] ?>">
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-file-excel"></i> Exporter en Excel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>






            <!-- Boutons de navigation entre modes -->
            <div class="col-12 mb-3">
                <div class="card">
                    <div class="card-body pt-3">
                        <ul class="nav nav-tabs nav-tabs-bordered">
                            <li class="nav-item">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#student-mode">
                                    <i class="bi bi-person-fill"></i> Par étudiant
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#batch-mode">
                                    <i class="bi bi-people-fill"></i> Importation par section
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content pt-3">
                            <!-- Mode par étudiant (existant) -->
                            <div class="tab-pane fade show active" id="student-mode">
            <!-- Sélection de l'étudiant -->
            <?php if (!$etudiant): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Sélectionner un étudiant</h5>
                        
                        <form method="GET" action="">
                            <input type="hidden" name="view" value="frais/paiement_soutenance">
                            <div class="row mb-3">
                                <div class="col-md-10">
                                    <select name="etudiant" class="form-select" required>
                                        <option value="">Sélectionner un étudiant</option>
                                        <?php 
                                        $etudiants = $universite->getEtudiantsByAnnee($currentYear['idannee_acad']);
                                        foreach ($etudiants as $e): 
                                        ?>
                                            <option value="<?= $e['idetudiant'] ?>">
                                                <?= htmlspecialchars($e['noms']) ?> (<?= htmlspecialchars($e['matricule']) ?>) - <?= htmlspecialchars($e['designationOrientation']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Sélectionner
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Informations de l'étudiant -->
            <div class="col-12">
                <div class="card-body">
                <h5 class="card-title">
                    Informations de l'étudiant
                    <a href="?view=frais/paiement_soutenance" class="btn btn-sm btn-outline-secondary float-end">
                        <i class="bi bi-arrow-left"></i> Changer d'étudiant
                    </a>
                </h5>
                
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
                        
                        <div class="row">
                            <div class="col-md-4">
                                <p><strong>Nom:</strong> <?= htmlspecialchars($etudiant['noms']) ?></p>
                                <p><strong>Matricule:</strong> <?= htmlspecialchars($etudiant['matricule']) ?></p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Promotion:</strong> <?= htmlspecialchars($etudiant['designationPromotion']) ?></p>
                                <p><strong>Orientation:</strong> <?= htmlspecialchars($etudiant['designationOrientation']) ?></p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Section:</strong> <?= htmlspecialchars($etudiant['designationSection']) ?></p>
                                <p><strong>Année académique:</strong> <?= htmlspecialchars($etudiant['annee']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Frais de soutenance -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Frais de soutenance
                        </h5>
                        <?php if (empty($fraisSoutenance)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i> Aucun frais de soutenance n'est disponible pour cet étudiant.
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>Désignation</th>
                                        <th>Montant total</th>
                                        <th>Montant payé</th>
                                        <th>Montant restant</th>
                                        <th>Devise</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fraisSoutenance as $frais): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($frais['designation']) ?></td>
                                        <td><?= number_format($frais['montant'], 2) ?></td>
                                        <td><?= number_format($frais['montantPaye'] ?? 0, 2) ?></td>
                                        <td><?= number_format($frais['montantRestant'] ?? $frais['montant'], 2) ?></td>
                                        <td><?= htmlspecialchars($frais['devise']) ?></td>
                                        <td>
                                            <?php if (($frais['montantPaye'] ?? 0) >= $frais['montant']): ?>
                                                <span class="badge bg-success">Payé</span>
                                            <?php elseif (($frais['montantPaye'] ?? 0) > 0): ?>
                                                <span class="badge bg-warning">Partiel</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Non payé</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="openPaymentModal(<?= $frais['idfrais_soutenance'] ?>, '<?= addslashes($frais['designation']) ?>', <?= $frais['montant'] ?>, '<?= $frais['devise'] ?>', <?= $frais['montantRestant'] ?? $frais['montant'] ?>)">
                                                <i class="bi bi-cash-coin"></i> Payer
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Historique des paiements -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Historique des paiements</h5>
                        
                        <?php if (empty($paiements)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i> Aucun paiement n'a été enregistré pour cet étudiant.
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Frais</th>
                                        <th>Montant payé</th>
                                        <th>Devise</th>
                                        <th>Référence</th>
                                        <th>Mode de paiement</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paiements as $paiement): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($paiement['datePaiement'])) ?></td>
                                        <td><?= htmlspecialchars($paiement['designation_frais']) ?></td>
                                        <td><?= number_format($paiement['montantPaye'], 2) ?></td>
                                        <td><?= htmlspecialchars($paiement['devise']) ?></td>
                                        <td><?= htmlspecialchars($paiement['referencePaiement']) ?></td>
                                        <td><?= htmlspecialchars($paiement['modePaiement']) ?></td>
                                        <td>
                                            <?php if ($paiement['estComplet']): ?>
                                                <span class="badge bg-success">Complet</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Partiel</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewPaymentDetails(<?= $paiement['idpaiement_soutenance'] ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="confirmDeletePayment(<?= $paiement['idpaiement_soutenance'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

    


                    <!-- Mode importation par section -->
<div class="tab-pane fade" id="batch-mode">
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Importation des paiements par section</h5>
            <?php
                // Afficher les messages de résultat d'importation (même code que dans l'autre onglet)
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
                
            <form method="POST" action="controller/import_paiement_controller.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="import">
                <input type="hidden" name="type_paiement" value="soutenance">
                <input type="hidden" name="idAnneeAcad" value="<?= $currentYear['idannee_acad'] ?>">
                <input type="hidden" name="bulk_import" value="1">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="section" class="form-label">Section</label>
                        <select name="section" id="section" class="form-select" required onchange="loadPromotionsBySection(this.value)">
                            <option value="">Sélectionnez une section</option>
                            <?php
                            $sections = $universite->getSections();
                            foreach ($sections as $section): ?>
                                <option value="<?= $section['idsection'] ?>">
                                    <?= htmlspecialchars($section['designationSection']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner une section</div>
                    </div>
                    <div class="col-md-6">
                        <label for="promotion" class="form-label">Promotion</label>
                        <select name="promotion" id="promotion" class="form-select" required disabled onchange="loadFraisSoutenanceByPromotion(this.value)">
                            <option value="">Sélectionnez d'abord une section</option>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner une promotion</div>
                    </div>

                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="bulk_frais" class="form-label">Frais de soutenance</label>
                        <select name="frais" id="bulk_frais" class="form-select" required disabled>
                            <option value="">Sélectionnez d'abord une promotion</option>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner un frais</div>
                    </div>
                </div>

                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="bulk_mode_paiement" class="form-label">Mode de paiement</label>
                        <select name="mode_paiement" id="bulk_mode_paiement" class="form-select" required>
                            <option value="">Sélectionnez</option>
                            <option value="Espèces">Espèces</option>
                            <option value="Chèque">Chèque</option>
                            <option value="Virement">Virement bancaire</option>
                            <option value="Mobile Money">Mobile Money</option>
                            <option value="Carte de crédit">Carte de crédit</option>
                            <option value="Autre">Autre</option>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner un mode de paiement</div>
                    </div>
                    <div class="col-md-6">
                        <label for="bulk_import_type" class="form-label">Type d'importation</label>
                        <select name="import_type" id="bulk_import_type" class="form-select" required onchange="toggleBulkImportFields()">
                            <option value="complet">Paiement complet (montant total du frais)</option>
                            <option value="partiel">Paiement partiel (avec montants spécifiés)</option>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner un type d'importation</div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="bulk_date_paiement" class="form-label">Date de paiement</label>
                        <input type="date" name="date_paiement" id="bulk_date_paiement" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        <div class="invalid-feedback">Veuillez sélectionner une date de paiement</div>
                    </div>
                    <div class="col-md-6">
                        <label for="bulk_prefix_reference" class="form-label">Préfixe pour les références</label>
                        <input type="text" name="prefix_reference" id="bulk_prefix_reference" class="form-control" value="SOUT-<?= date('Ymd') ?>-" required>
                        <div class="invalid-feedback">Veuillez entrer un préfixe pour les références</div>
                        <small class="text-muted">Ce préfixe sera suivi d'un numéro incrémental pour chaque paiement</small>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="bulk_file" class="form-label">Fichier d'importation</label>
                        <input type="file" name="import_file" id="bulk_file" class="form-control" accept=".csv,.xlsx,.xls" required>
                        <div class="invalid-feedback">Veuillez sélectionner un fichier à importer</div>
                        <small class="text-muted">Formats acceptés: CSV, Excel (.xlsx, .xls)</small>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <h6>Format du fichier:</h6>
                    <p class="mb-1" id="bulk_format_info_complet">
                        <strong>Pour les paiements complets:</strong> Un matricule par ligne.
                    </p>
                    <p class="mb-1" id="bulk_format_info_partiel" style="display: none;">
                        <strong>Pour les paiements partiels:</strong> Matricule, montant, (date optionnelle) séparés par des virgules.
                    </p>
                    <p class="mb-0">
                        <a href="#" onclick="downloadBulkTemplate(); return false;" class="text-primary">
                            <i class="bi bi-download"></i> Télécharger un modèle
                        </a>
                    </p>
                </div>
                
                <div class="text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Importer les paiements
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>



    </section>
</main>




<!-- Modal pour payer un frais de soutenance -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Paiement de frais de soutenance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm" method="POST" action="controller/frais_controller.php">
                    <input type="hidden" name="action" value="pay_soutenance">
                    <input type="hidden" name="etudiantId" value="<?= $etudiantId ?>">
                    <input type="hidden" name="fraisSoutenanceId" id="fraisSoutenanceId">
                    <input type="hidden" name="anneeAcadId" value="<?= $currentYear['idannee_acad'] ?>">
                    
                    <div class="mb-3">
                        <label for="fraisDesignation" class="form-label">Frais</label>
                        <input type="text" class="form-control" id="fraisDesignation" readonly>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="montantTotal" class="form-label">Montant total</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="montantTotal" readonly>
                                <span class="input-group-text" id="deviseTotal"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="montantRestant" class="form-label">Montant restant</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="montantRestant" readonly>
                                <span class="input-group-text" id="deviseRestant"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="montantPaye" class="form-label">Montant à payer</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="montantPaye" id="montantPaye" step="0.01" min="0.01" required>
                            <span class="input-group-text" id="devisePaiement"></span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="referencePaiement" class="form-label">Référence du paiement</label>
                        <input type="text" class="form-control" name="referencePaiement" id="referencePaiement" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modePaiement" class="form-label">Mode de paiement</label>
                        <select class="form-select" name="modePaiement" id="modePaiement" required>
                            <option value="">Sélectionner un mode de paiement</option>
                            <option value="Espèces">Espèces</option>
                            <option value="Chèque">Chèque</option>
                            <option value="Carte bancaire">Carte bancaire</option>
                            <option value="Virement bancaire">Virement bancaire</option>
                            <option value="Mobile Money">Mobile Money</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="commentaire" class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" name="commentaire" id="commentaire" rows="3"></textarea>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer le paiement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Fonction pour ouvrir le modal de paiement
function openPaymentModal(fraisId, designation, montantTotal, devise, montantRestant) {
    document.getElementById('fraisSoutenanceId').value = fraisId;
    document.getElementById('fraisDesignation').value = designation;
    document.getElementById('montantTotal').value = montantTotal.toFixed(2);
    document.getElementById('montantRestant').value = montantRestant.toFixed(2);
    document.getElementById('montantPaye').value = montantRestant.toFixed(2);
    document.getElementById('montantPaye').max = montantRestant;
    
    document.getElementById('deviseTotal').textContent = devise;
    document.getElementById('deviseRestant').textContent = devise;
    document.getElementById('devisePaiement').textContent = devise;
    
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

// Fonction pour voir les détails d'un paiement
function viewPaymentDetails(paiementId) {
    // Rediriger vers une page de détails ou afficher un modal avec les détails
    window.location.href = `?view=frais/detail_paiement_soutenance&id=${paiementId}`;
}

// Fonction pour confirmer la suppression d'un paiement
function confirmDeletePayment(paiementId) {
    Swal.fire({
        title: 'Êtes-vous sûr?',
        text: "Cette action supprimera ce paiement. Cette action est irréversible!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `controller/frais_controller.php?action=delete_paiement_soutenance&id=${paiementId}&etudiant=<?= $etudiantId ?>`;
        }
    });
}


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
        content = 'Matricule\n<?= $etudiant ? $etudiant['matricule'] : '' ?>';
        filename = 'modele_import_paiements_soutenance_complets.csv';
    } else {
        content = 'Matricule,Montant,Date (optionnelle)\n<?= $etudiant ? $etudiant['matricule'] : '' ?>,150,2023-01-01';
        filename = 'modele_import_paiements_soutenance_partiels.csv';
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


function loadPromotions(sectionId, targetSelectId, defaultOption = "Toutes les promotions", emptyValue = "0", enableDisable = false) {
    const select = document.getElementById(targetSelectId);
    
    if (!sectionId) {
        select.innerHTML = `<option value="${emptyValue}">${defaultOption}</option>`;
        if (enableDisable) select.disabled = true;
        return;
    }
    
    select.innerHTML = '<option value="">Chargement...</option>';
    if (enableDisable) select.disabled = true;
    
    fetch(`controller/get_promotions_by_section.php?section=${sectionId}&annee=<?= $currentYear['idannee_acad'] ?>`)
        .then(response => response.json())
        .then(data => {
            select.innerHTML = `<option value="${emptyValue}">${defaultOption}</option>`;
            if (data.length === 0) {
                select.innerHTML += `<option value="${emptyValue}" disabled>Aucune promotion disponible</option>`;
            } else {
                data.forEach(promotion => {
                    const option = document.createElement('option');
                    option.value = promotion.idpromotion;
                    option.textContent = promotion.designationPromotion;
                    select.appendChild(option);
                });
            }
            if (enableDisable) select.disabled = false;
        })
        .catch(error => {
            console.error('Erreur lors du chargement des promotions:', error);
            select.innerHTML = `<option value="${emptyValue}">Erreur de chargement</option>`;
            if (enableDisable) select.disabled = true;
        });
}


// Pour l'exportation
function loadPromotionsForExport(sectionId) {
    loadPromotions(sectionId, 'export_promotion', "Toutes les promotions", "0", false);
}

// Pour le mode importation
function loadPromotionsBySection(sectionId) {
    loadPromotions(sectionId, 'promotion', "Sélectionnez une promotion", "", true);
}


/**
 * Charge les frais de soutenance disponibles pour une promotion
 */
function loadFraisSoutenanceByPromotion(promotionId) {
    if (!promotionId) {
        document.getElementById('bulk_frais').innerHTML = '<option value="">Sélectionnez d\'abord une promotion</option>';
        document.getElementById('bulk_frais').disabled = true;
        return;
    }
    
    const fraisSelect = document.getElementById('bulk_frais');
    fraisSelect.innerHTML = '<option value="">Chargement...</option>';
    fraisSelect.disabled = true;
    
    fetch(`controller/get_frais_soutenance_by_promotion.php?promotion=${promotionId}&annee=<?= $currentYear['idannee_acad'] ?>`)
        .then(response => response.json())
        .then(data => {
            fraisSelect.innerHTML = '<option value="">Sélectionnez un frais</option>';
            if (data.length === 0) {
                fraisSelect.innerHTML += '<option value="" disabled>Aucun frais disponible pour cette promotion</option>';
            } else {
                data.forEach(frais => {
                    const option = document.createElement('option');
                    option.value = frais.idfrais_soutenance;
                    option.dataset.montant = frais.montant;
                    option.dataset.devise = frais.devise;
                    option.textContent = `${frais.designation} (${parseFloat(frais.montant).toFixed(2)} ${frais.devise})`;
                    fraisSelect.appendChild(option);
                });
            }
            fraisSelect.disabled = false;
        })
        .catch(error => {
            console.error('Erreur lors du chargement des frais:', error);
            fraisSelect.innerHTML = '<option value="">Erreur de chargement</option>';
            fraisSelect.disabled = true;
        });
}


/**
 * Bascule l'affichage des champs d'importation en masse selon le type choisi
 */
function toggleBulkImportFields() {
    const importType = document.getElementById('bulk_import_type').value;
    const formatInfoComplet = document.getElementById('bulk_format_info_complet');
    const formatInfoPartiel = document.getElementById('bulk_format_info_partiel');
    
    if (importType === 'complet') {
        formatInfoComplet.style.display = 'block';
        formatInfoPartiel.style.display = 'none';
    } else {
        formatInfoComplet.style.display = 'none';
        formatInfoPartiel.style.display = 'block';
    }
}

/**
 * Génère et télécharge un fichier modèle pour l'importation en masse
 */
function downloadBulkTemplate() {
    const importType = document.getElementById('bulk_import_type').value;
    let content = '';
    let filename = '';
    
    if (importType === 'complet') {
        content = 'Matricule\nXXX001\nXXX002\nXXX003';
        filename = 'modele_import_masse_soutenance_complets.csv';
    } else {
        content = 'Matricule,Montant,Date (optionnelle)\nXXX001,150,2023-01-01\nXXX002,200,\nXXX003,175,';
        filename = 'modele_import_masse_soutenance_partiels.csv';
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

// Initialiser l'affichage des champs d'importation en masse
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser l'affichage pour l'importation individuelle
    toggleImportFields();
    
    // Initialiser l'affichage pour l'importation en masse
    toggleBulkImportFields();
    
    // Si un onglet est sélectionné via l'URL, l'activer
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('tab') && urlParams.get('tab') === 'bulk') {
        const batchTab = document.querySelector('[data-bs-target="#batch-mode"]');
        if (batchTab) {
            batchTab.click();
        }
    }
});








</script>



<?php include "./views/include/footer_file.php"; ?>

