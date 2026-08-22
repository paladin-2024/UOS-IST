<?php
include "./views/include/header.php";
$universite = new Universite();
$ecue = new Ecue();
$agent = new Agent();
$enseignant = new Enseignant();

// Vérifier que l'utilisateur est connecté
$userId = $_SESSION['id'] ?? 0;

// Vérifier si l'utilisateur est un enseignant
if (!$userId || !$enseignant->isUserEnseignant($userId)) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous devez être connecté en tant qu\'enseignant pour accéder à cette page.'
        }).then(() => {
            window.location.href = '?view=dashboard';
        });
    </script>";
    exit;
}

// Récupérer l'ID de l'agent (enseignant)
$idEnseignant = $enseignant->getAgentIdByUserId($userId);
if (!$idEnseignant) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Impossible de récupérer les informations de l\'enseignant.'
        }).then(() => {
            window.location.href = '?view=dashboard';
        });
    </script>";
    exit;
}


$enseignants = $enseignant->getEnseignantByUserId($userId);
// Récupérer l'ID de l'enseignant connecté
$idEnseignant = $enseignants['idAgent'];

// Récupérer l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();

// Récupérer les ECUE affectés à cet enseignant pour l'année en cours
$coursEnseignant = $enseignant->getCoursAffectesEnseignant($idEnseignant, $currentYear['idannee_acad']);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <!-- Toast Notifications -->
    <div class="toast-container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="toast toast-animated align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-check-circle me-2"></i> <?= htmlspecialchars($_SESSION['success']) ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="toast toast-animated align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-exclamation-triangle me-2"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </div>

    <div class="pagetitle">
        <h1>MES COURS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=dashboard">Accueil</a></li>
                <li class="breadcrumb-item active">Mes Cours</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Année académique: <?= htmlspecialchars($currentYear['designation']) ?></h5>
                        <p>Vous trouverez ci-dessous la liste des cours qui vous sont affectés pour l'année académique en cours.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-12">
                <div class="row">
                    <!-- Table des ECUE -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">Mes cours affectés</h5>

                                <?php if (empty($coursEnseignant)): ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Aucun cours ne vous a été affecté pour cette année académique.
                                    </div>
                                <?php else: ?>
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th scope="col">#</th>
                                                <th scope="col">Désignation</th>
                                                <th scope="col">Unité d'enseignement</th>
                                                <th scope="col">Semestre</th>
                                                <th scope="col">Promotion</th>
                                                <th scope="col">Volume horaire</th>
                                                <th scope="col">Rôle</th>
                                                <th scope="col">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Dans la boucle d'affichage des cours, modifions le code pour ajouter une ligne de détails
                                            $i = 1;
                                            foreach ($coursEnseignant as $cours) {
                                                // Vérifier si l'enseignant est titulaire du cours
                                                $isTitulaire = strtolower($cours['poste']) === 'titulaire';
                                                $courseId = $cours['idECUE'];

                                                // Récupérer tous les enseignants affectés à ce cours
                                                $enseignantsAffectes = $enseignant->getEnseignantsAffectesByCours($courseId, $currentYear['idannee_acad']);

                                                echo "
    <tr>
        <td>{$i}</td>
        <td>{$cours['designationECUE']}</td>
        <td>{$cours['designationUE']}</td>
        <td>{$cours['numeroSemestre']}</td>
        <td>{$cours['designationPromotion']}</td>
        <td>
            CMI: {$cours['CMI']}h<br>
            TD: {$cours['TD']}h<br>
            TP: {$cours['TP']}h
        </td>
        <td><span class='badge bg-primary'>{$cours['poste']}</span></td>
        <td>
            <button class='btn btn-sm btn-primary' onclick='window.location.href=\"?view=enseignement/cours.details&id={$courseId}\"'>
                <i class='bi bi-book'></i> Contenu
            </button>
            <button class='btn btn-sm btn-info' onclick='window.location.href=\"?view=enseignement/evaluations&ecue={$courseId}\"'>
                <i class='bi bi-clipboard-check'></i> Évaluations
            </button>
            <button class='btn btn-sm btn-secondary' onclick='toggleEnseignantsRow(\"enseignants-{$courseId}\")'>
                <i class='bi bi-people'></i> Enseignants
            </button>
            <button class='btn btn-sm btn-warning' onclick='openTravauxPratiques({$courseId})'>
                <i class='bi bi-file-earmark-text'></i> TP
            </button>";

                                                

                                                echo "
        </td>
    </tr>";

                                                // Ajouter une ligne cachée pour afficher les enseignants
                                                echo "
    <tr id='enseignants-{$courseId}' class='enseignants-row' style='display: none; background-color: #f8f9fa;'>
        <td colspan='8'>
            <div class='p-3'>
                <h6 class='mb-3'>Enseignants affectés à ce cours</h6>";

                                                if (empty($enseignantsAffectes)) {
                                                    echo "<p class='text-muted'>Aucun autre enseignant n'est affecté à ce cours.</p>";
                                                } else {
                                                    echo "
                <div class='table-responsive'>
                    <table class='table table-sm table-bordered'>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Rôle</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>";

                                                    foreach ($enseignantsAffectes as $ens) {
                                                        // Ne pas afficher l'enseignant connecté dans cette liste
                                                        if ($ens['idAgent'] != $idEnseignant) {
                                                            echo "
                            <tr>
                                <td>{$ens['noms']}</td>
                                <td><span class='badge bg-secondary'>{$ens['poste']}</span></td>
                                <td>";

                                                            // Afficher le bouton de suppression uniquement pour les titulaires
                                                            if ($isTitulaire) {
                                                                echo "
                                    <button class='btn btn-sm btn-danger' onclick='confirmRemoveAssistant({$courseId}, {$ens['idAgent']}, \"{$ens['noms']}\")'>
                                        <i class='bi bi-trash'></i> Retirer
                                    </button>";
                                                            }

                                                            echo "
                                </td>
                            </tr>";
                                                        }
                                                    }

                                                    echo "
                        </tbody>
                    </table>
                </div>";
                                                }

                                                echo "
            </div>
        </td>
    </tr>";

                                                $i++;
                                            }
                                            ?>


                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>


<!-- Modal pour ajouter un assistant -->
<div class="modal fade" id="addAssistantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un assistant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addAssistantForm" action="controller/enseignant_controller.php" method="POST">
                    <input type="hidden" name="action" value="add_assistant">
                    <input type="hidden" name="idECUE" id="ecue_id">
                    <input type="hidden" name="annee_acad_id" value="<?= $currentYear['idannee_acad'] ?>">

                    <div class="mb-3">
                        <label class="form-label">Cours</label>
                        <input type="text" class="form-control" id="ecue_name" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="assistant_id" class="form-label">Sélectionner un enseignant</label>
                        <select class="form-select" id="assistant_id" name="assistant_id" required>
                            <option value="">Sélectionnez un enseignant...</option>
                            <?php
                            // Récupérer la liste des enseignants disponibles
                            $enseignantsList = $enseignant->getAllEnseignants();
                            foreach ($enseignantsList as $ens) {
                                // Exclure l'enseignant actuel
                                if ($ens['idAgent'] != $idEnseignant) {
                                    echo "<option value='{$ens['idAgent']}'>{$ens['noms']}</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="poste" class="form-label">Rôle</label>
                        <select class="form-select" id="poste" name="poste" required>
                            <option value="Assistant">Assistant</option>
                            <option value="Suppléant">Suppléant</option>
                            <option value="Collaborateur">Collaborateur</option>
                        </select>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour les travaux pratiques -->
<div class="modal fade" id="travauxPratiquesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-file-earmark-text me-2"></i>Travaux Pratiques
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="tp_id_ecue" value="">
                
                <!-- Liste des travaux existants -->
                <div id="travaux-existants" class="mb-4">
                    <h6 class="mb-3">Travaux déjà créés</h6>
                    <div id="liste-travaux" class="table-responsive">
                        <p class="text-muted">Chargement...</p>
                    </div>
                </div>
                
                <!-- Formulaire d'ajout de travail -->
                <hr>
                <h6 class="mb-3">Créer un nouveau travail</h6>
                <form id="tp_form" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="tp_titre" class="form-label">Titre du travail <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="tp_titre" name="titre" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tp_description" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="tp_description" name="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tp_date_limite" class="form-label">Date limite</label>
                        <input type="datetime-local" class="form-control" id="tp_date_limite" name="date_limite">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Devise</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="devise" id="tp_devise_usd" value="USD" checked>
                            <label class="btn btn-outline-success" for="tp_devise_usd">USD ($)</label>
                            <input type="radio" class="btn-check" name="devise" id="tp_devise_cdf" value="CDF">
                            <label class="btn btn-outline-success" for="tp_devise_cdf">CDF (FC)</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Type de travail</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="type_travail" id="tp_type_individuel" value="individuel" checked>
                            <label class="btn btn-outline-primary" for="tp_type_individuel">
                                <i class="bi bi-person me-1"></i>Individuel
                            </label>
                            <input type="radio" class="btn-check" name="type_travail" id="tp_type_groupe" value="groupe">
                            <label class="btn btn-outline-primary" for="tp_type_groupe">
                                <i class="bi bi-people me-1"></i>Groupe
                            </label>
                        </div>
                    </div>
                    
                    <!-- Options pour travail individuel -->
                    <div id="options-individuel">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="tp_est_payant_ind" name="est_payant">
                                <label class="form-check-label" for="tp_est_payant_ind">
                                    Travail payable
                                </label>
                            </div>
                        </div>
                        <div class="mb-3" id="prix-individuel-container" style="display:none;">
                            <label for="tp_prix_etudiant" class="form-label">Prix par étudiant (<span class="devise-label">USD</span>)</label>
                            <input type="number" class="form-control" id="tp_prix_etudiant" name="prix_par_etudiant" step="0.01" min="0" value="0">
                        </div>
                    </div>
                    
                    <!-- Options pour travail de groupe -->
                    <div id="options-groupe" style="display:none;">
                        <div class="mb-3">
                            <label for="tp_max_etudiants" class="form-label">Nombre max d'étudiants par groupe</label>
                            <input type="number" class="form-control" id="tp_max_etudiants" name="max_etudiants_groupe" min="2" value="3">
                        </div>
                        <div class="mb-3">
                            <label for="tp_nombre_groupes" class="form-label">Nombre de groupes</label>
                            <input type="number" class="form-control" id="tp_nombre_groupes" name="nombre_groupes" min="1" value="10">
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="tp_fichier_par_groupe" name="fichier_par_groupe">
                                <label class="form-check-label" for="tp_fichier_par_groupe">
                                    Un fichier par groupe (sinon un seul fichier pour tous)
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="tp_est_payant_grp" name="est_payant_grp">
                                <label class="form-check-label" for="tp_est_payant_grp">
                                    Travail payable
                                </label>
                            </div>
                        </div>
                        <div id="prix-groupe-container" style="display:none;">
                            <div class="mb-3">
                                <label class="form-label">Type de prix</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="type_prix_groupe" id="tp_prix_forfaitaire" value="forfaitaire" checked>
                                    <label class="btn btn-outline-secondary" for="tp_prix_forfaitaire">Forfaitaire</label>
                                    <input type="radio" class="btn-check" name="type_prix_groupe" id="tp_prix_par_etudiant" value="par_etudiant">
                                    <label class="btn btn-outline-secondary" for="tp_prix_par_etudiant">Par étudiant</label>
                                </div>
                            </div>
                            <div class="mb-3" id="prix-forfaitaire-container">
                                <label for="tp_prix_forfaitaire" class="form-label">Prix forfaitaire par groupe (<span class="devise-label">USD</span>)</label>
                                <input type="number" class="form-control" id="tp_prix_forfaitaire_val" name="prix_forfaitaire" step="0.01" min="0" value="0">
                            </div>
                            <div class="mb-3" id="prix-par-etudiant-container" style="display:none;">
                                <label for="tp_prix_etudiant_groupe" class="form-label">Prix par étudiant (<span class="devise-label">USD</span>)</label>
                                <input type="number" class="form-control" id="tp_prix_etudiant_groupe" name="prix_par_etudiant_groupe" step="0.01" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Fichier global (pour individuel ou groupe sans fichier par groupe) -->
                    <div class="mb-3" id="fichier-global-container">
                        <label for="tp_fichier" class="form-label">Fichier (PDF ou Word)</label>
                        <input type="file" class="form-control" id="tp_fichier" name="fichier" accept=".pdf,.doc,.docx">
                        <div class="form-text">Taille maximale: 10 Mo. Formats acceptés: PDF, DOC, DOCX</div>
                    </div>
                    
                    <!-- Fichiers par groupe (affiché quand fichier_par_groupe est coché) -->
                    <div class="mb-3" id="fichiers-par-groupe-container" style="display:none;">
                        <label class="form-label fw-bold text-info">
                            <i class="bi bi-files me-1"></i>Fichiers par groupe (PDF ou Word)
                        </label>
                        <div id="liste-fichiers-groupes">
                            <!-- Les inputs seront générés dynamiquement -->
                        </div>
                        <div class="form-text">Chargez un fichier PDF ou Word pour chaque groupe. Taille max: 10 Mo par fichier.</div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un TP -->
<div class="modal fade" id="editTpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Modifier le Travail Pratique</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="edit_tp_form" enctype="multipart/form-data">
                    <input type="hidden" id="edit_tp_id" name="idDevoir">
                    
                    <div class="mb-3">
                        <label class="form-label">Titre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_tp_titre" name="titre" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="edit_tp_description" name="description" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date limite</label>
                        <input type="datetime-local" class="form-control" id="edit_tp_date_limite" name="date_limite">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Devise</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="devise" id="edit_devise_usd" value="USD" checked>
                            <label class="btn btn-outline-success" for="edit_devise_usd">USD ($)</label>
                            <input type="radio" class="btn-check" name="devise" id="edit_devise_cdf" value="CDF">
                            <label class="btn btn-outline-success" for="edit_devise_cdf">CDF (FC)</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type de travail</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="type_travail" id="edit_type_individuel" value="individuel" checked>
                            <label class="btn btn-outline-primary" for="edit_type_individuel"><i class="bi bi-person me-1"></i>Individuel</label>
                            <input type="radio" class="btn-check" name="type_travail" id="edit_type_groupe" value="groupe">
                            <label class="btn btn-outline-primary" for="edit_type_groupe"><i class="bi bi-people me-1"></i>Groupe</label>
                        </div>
                    </div>
                    
                    <!-- Options individuel -->
                    <div id="edit-options-individuel">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_est_payant_ind" name="est_payant">
                                <label class="form-check-label" for="edit_est_payant_ind">Travail payable</label>
                            </div>
                        </div>
                        <div class="mb-3" id="edit-prix-individuel-container" style="display:none;">
                            <label class="form-label">Prix par étudiant (<span class="edit-devise-label">USD</span>)</label>
                            <input type="number" class="form-control" id="edit_prix_etudiant" name="prix_par_etudiant" step="0.01" min="0" value="0">
                        </div>
                    </div>
                    
                    <!-- Options groupe -->
                    <div id="edit-options-groupe" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label">Nombre max d'étudiants par groupe</label>
                            <input type="number" class="form-control" id="edit_max_etudiants" name="max_etudiants_groupe" min="2" value="3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nombre de groupes</label>
                            <input type="number" class="form-control" id="edit_nombre_groupes" name="nombre_groupes" min="1" value="10">
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_fichier_par_groupe" name="fichier_par_groupe">
                                <label class="form-check-label" for="edit_fichier_par_groupe">Un fichier par groupe</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_est_payant_grp" name="est_payant_grp">
                                <label class="form-check-label" for="edit_est_payant_grp">Travail payable</label>
                            </div>
                        </div>
                        <div id="edit-prix-groupe-container" style="display:none;">
                            <div class="mb-3">
                                <label class="form-label">Type de prix</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="type_prix_groupe" id="edit_prix_forfaitaire" value="forfaitaire" checked>
                                    <label class="btn btn-outline-secondary" for="edit_prix_forfaitaire">Forfaitaire</label>
                                    <input type="radio" class="btn-check" name="type_prix_groupe" id="edit_prix_par_etudiant" value="par_etudiant">
                                    <label class="btn btn-outline-secondary" for="edit_prix_par_etudiant">Par étudiant</label>
                                </div>
                            </div>
                            <div class="mb-3" id="edit-prix-forfaitaire-container">
                                <label class="form-label">Prix forfaitaire par groupe (<span class="edit-devise-label">USD</span>)</label>
                                <input type="number" class="form-control" id="edit_prix_forfaitaire_val" name="prix_forfaitaire" step="0.01" min="0" value="0">
                            </div>
                            <div class="mb-3" id="edit-prix-par-etudiant-container" style="display:none;">
                                <label class="form-label">Prix par étudiant (<span class="edit-devise-label">USD</span>)</label>
                                <input type="number" class="form-control" id="edit_prix_etudiant_groupe" name="prix_par_etudiant_groupe" step="0.01" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Fichier global (pour individuel ou groupe sans fichier par groupe) -->
                    <div class="mb-3" id="edit-fichier-global-container">
                        <label class="form-label">Fichier (PDF ou Word)</label>
                        <input type="file" class="form-control" id="edit_tp_fichier" name="fichier" accept=".pdf,.doc,.docx">
                        <div class="form-text">Laisser vide pour conserver le fichier actuel</div>
                    </div>
                    
                    <!-- Fichiers par groupe (affiché quand fichier_par_groupe est coché) -->
                    <div class="mb-3" id="edit-fichiers-par-groupe-container" style="display:none;">
                        <label class="form-label fw-bold text-info">
                            <i class="bi bi-files me-1"></i>Fichiers par groupe (PDF ou Word)
                        </label>
                        <div id="edit-liste-fichiers-groupes">
                            <!-- Les inputs seront générés dynamiquement -->
                        </div>
                        <div class="form-text">Laisser vide pour conserver le fichier actuel de chaque groupe.</div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Mettre à jour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour le suivi des paiements -->
<div class="modal fade" id="suiviPaiementsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-currency-dollar me-2"></i>Suivi des Paiements - <span id="suivi_titre_tp"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Stats cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <h6 class="text-success">Total Reçu</h6>
                                <h4 id="stat_total_recu" class="text-success">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <h6 class="text-warning">En Attente</h6>
                                <h4 id="stat_total_attente" class="text-warning">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <h6 class="text-primary">Paiements Réussis</h6>
                                <h4 id="stat_nb_reussi" class="text-primary">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-danger">
                            <div class="card-body text-center">
                                <h6 class="text-danger">Échoués</h6>
                                <h4 id="stat_nb_echoue" class="text-danger">0</h4>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment table -->
                <div class="table-responsive" id="table-paiements-container">
                    <p class="text-center text-muted">Chargement...</p>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
    // Script pour gérer la navigation
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Page des cours chargée');
    });

    // Fonction pour ouvrir le modal d'ajout d'assistant
    function openAddAssistantModal(ecueId, ecueName) {
        document.getElementById('ecue_id').value = ecueId;
        document.getElementById('ecue_name').value = ecueName;

        // Ouvrir le modal
        const modal = new bootstrap.Modal(document.getElementById('addAssistantModal'));
        modal.show();
    }

    // Fonction pour afficher/masquer la ligne des enseignants
    function toggleEnseignantsRow(rowId) {
        const row = document.getElementById(rowId);
        if (row.style.display === 'none') {
            row.style.display = 'table-row';
        } else {
            row.style.display = 'none';
        }
    }

    // Fonction pour confirmer la suppression d'un assistant
    function confirmRemoveAssistant(ecueId, assistantId, assistantName) {
        Swal.fire({
            title: 'Confirmation',
            text: `Êtes-vous sûr de vouloir retirer ${assistantName} de ce cours ?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, retirer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                // Rediriger vers le contrôleur pour supprimer l'assistant
                window.location.href = `controller/enseignant_controller.php?action=remove_assistant&ecue=${ecueId}&assistant=${assistantId}`;
            }
        });
    }
    
    // Fonction pour ouvrir le modal des travaux pratiques
    function openTravauxPratiques(ecueId, ecueName) {
        document.getElementById('tp_id_ecue').value = ecueId;
        document.getElementById('tp_titre').value = '';
        document.getElementById('tp_description').value = '';
        document.getElementById('tp_date_limite').value = '';
        document.getElementById('tp_fichier').value = '';
        
        // Reset form
        document.getElementById('tp_form').reset();
        document.getElementById('tp_type_individuel').checked = true;
        toggleTypeTravail();
        
        // Charger les travaux existants
        chargerTravauxExistants(ecueId);
        
        const modal = new bootstrap.Modal(document.getElementById('travauxPratiquesModal'));
        modal.show();
    }
    
    // Charger les travaux existants
    function chargerTravauxExistants(ecueId) {
        const container = document.getElementById('liste-travaux');
        container.innerHTML = '<div class="text-center p-3"><div class="spinner-border spinner-border-sm"></div> Chargement...</div>';
        
        // Simuler le chargement (à remplacer par un appel AJAX réel)
        fetch(`controller/travaux_cours_controller.php?action=get_travaux&ecue=${ecueId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.travaux && data.travaux.length > 0) {
                    let html = '<table class="table table-sm table-striped">';
                    html += '<thead><tr><th>Titre</th><th>Type</th><th>Prix</th><th>Devise</th><th>Statut</th><th>Actions</th></tr></thead>';
                    html += '<tbody>';
                    data.travaux.forEach(tp => {
                        const devise = tp.devise || 'USD';
                        const deviseSymbol = devise === 'CDF' ? 'FC' : '$';
                        const typeLabel = tp.type_travail === 'groupe' ? '<span class="badge bg-info">Groupe</span>' : '<span class="badge bg-primary">Individuel</span>';
                        const prix = tp.est_payant ? (tp.type_travail === 'individuel' ? tp.prix_par_etudiant + ' ' + deviseSymbol + '/étud' : (tp.type_prix_groupe === 'forfaitaire' ? tp.prix_forfaitaire + ' ' + deviseSymbol + '/grp' : tp.prix_par_etudiant + ' ' + deviseSymbol + '/étud')) : 'Gratuit';
                        html += `<tr>
                            <td>${tp.titre}</td>
                            <td>${typeLabel}</td>
                            <td>${prix}</td>
                            <td><span class="badge bg-secondary">${devise}</span></td>
                            <td><span class="badge bg-success">Actif</span></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="modifierTravail(${tp.iddevoir})"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-info" onclick="voirPaiements(${tp.iddevoir}, '${tp.titre}')"><i class="bi bi-currency-dollar"></i></button>
                                <button class="btn btn-sm btn-danger" onclick="supprimerTravail(${tp.iddevoir})"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<p class="text-muted">Aucun travail créé pour ce cours.</p>';
                }
            })
            .catch(err => {
                container.innerHTML = '<p class="text-danger">Erreur lors du chargement.</p>';
            });
    }
    
    // Générer les inputs de fichiers pour chaque groupe
    function genererInputsFichiersGroupes(nombreGroupes, containerId, prefix, fichiersExistants) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';
        for (let i = 1; i <= nombreGroupes; i++) {
            const fichiersExist = fichiersExistants && fichiersExistants.find(f => f.numero_groupe == i);
            const labelInfo = fichiersExist ? ` <span class="text-success small"><i class="bi bi-check-circle"></i> Fichier existant: ${fichiersExist.fichier}</span>` : '';
            container.innerHTML += `
                <div class="mb-2 p-2 border rounded">
                    <label class="form-label mb-1">
                        <i class="bi bi-people me-1 text-primary"></i>Groupe ${i}${labelInfo}
                    </label>
                    <input type="file" class="form-control form-control-sm"
                        name="${prefix}${i}"
                        accept=".pdf,.doc,.docx">
                </div>
            `;
        }
    }
    
    // Mettre à jour l'affichage des fichiers selon le type et l'option fichier par groupe
    function updateFichierDisplay() {
        const typeGroupe = document.getElementById('tp_type_groupe').checked;
        const fichierParGroupe = document.getElementById('tp_fichier_par_groupe').checked;
        const globalContainer = document.getElementById('fichier-global-container');
        const groupeContainer = document.getElementById('fichiers-par-groupe-container');
        
        if (typeGroupe && fichierParGroupe) {
            globalContainer.style.display = 'none';
            groupeContainer.style.display = 'block';
            const nb = parseInt(document.getElementById('tp_nombre_groupes').value) || 0;
            if (nb > 0) {
                genererInputsFichiersGroupes(nb, 'liste-fichiers-groupes', 'fichier_groupe_', null);
            }
        } else {
            globalContainer.style.display = 'block';
            groupeContainer.style.display = 'none';
        }
    }
    
    // Basculer entre les options individuel/groupe
    function toggleTypeTravail() {
        const typeIndividuel = document.getElementById('tp_type_individuel').checked;
        document.getElementById('options-individuel').style.display = typeIndividuel ? 'block' : 'none';
        document.getElementById('options-groupe').style.display = !typeIndividuel ? 'block' : 'none';
        updateFichierDisplay();
    }
    
    // Gérer le changement de type de travail
    document.querySelectorAll('input[name="type_travail"]').forEach(radio => {
        radio.addEventListener('change', toggleTypeTravail);
    });
    
    // Gérer le changement de type de prix pour groupe
    document.querySelectorAll('input[name="type_prix_groupe"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const isForfaitaire = this.value === 'forfaitaire';
            document.getElementById('prix-forfaitaire-container').style.display = isForfaitaire ? 'block' : 'none';
            document.getElementById('prix-par-etudiant-container').style.display = !isForfaitaire ? 'block' : 'none';
        });
    });
    
    // Gérer le checkbox fichier_par_groupe et nombre_groupes
    document.getElementById('tp_fichier_par_groupe').addEventListener('change', updateFichierDisplay);
    document.getElementById('tp_nombre_groupes').addEventListener('change', function() {
        if (document.getElementById('tp_type_groupe').checked && document.getElementById('tp_fichier_par_groupe').checked) {
            const nb = parseInt(this.value) || 0;
            if (nb > 0) genererInputsFichiersGroupes(nb, 'liste-fichiers-groupes', 'fichier_groupe_', null);
        }
    });
    document.getElementById('tp_nombre_groupes').addEventListener('input', function() {
        if (document.getElementById('tp_type_groupe').checked && document.getElementById('tp_fichier_par_groupe').checked) {
            const nb = parseInt(this.value) || 0;
            if (nb > 0) genererInputsFichiersGroupes(nb, 'liste-fichiers-groupes', 'fichier_groupe_', null);
        }
    });
    
    // Gérer les checkboxes de paiement
    document.getElementById('tp_est_payant_ind').addEventListener('change', function() {
        document.getElementById('prix-individuel-container').style.display = this.checked ? 'block' : 'none';
    });
    
    document.getElementById('tp_est_payant_grp').addEventListener('change', function() {
        document.getElementById('prix-groupe-container').style.display = this.checked ? 'block' : 'none';
    });
    
    // Gérer le changement de devise
    document.querySelectorAll('#travauxPratiquesModal input[name="devise"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const devise = this.value === 'CDF' ? 'CDF' : 'USD';
            document.querySelectorAll('.devise-label').forEach(el => el.textContent = devise);
        });
    });
    
    // Variable pour éviter les doubles soumissions
    let tpFormSubmitting = false;
    
    // Soumettre le formulaire
    document.getElementById('tp_form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Empêcher la double soumission
        if (tpFormSubmitting) {
            return;
        }
        tpFormSubmitting = true;
        
        // Désactiver le bouton de soumission
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enregistrement...';
        
        const formData = new FormData(this);
        formData.append('action', 'create_travail');
        formData.append('idECUE', document.getElementById('tp_id_ecue').value);
        
        // Ajouter est_payant selon le type
        if (document.getElementById('tp_type_individuel').checked) {
            formData.set('est_payant', document.getElementById('tp_est_payant_ind').checked ? '1' : '0');
        } else {
            formData.set('est_payant', document.getElementById('tp_est_payant_grp').checked ? '1' : '0');
        }
        
        fetch('controller/travaux_cours_controller.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Réactiver le formulaire
            tpFormSubmitting = false;
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: data.message
                }).then(() => {
                    // Recharger les travaux
                    chargerTravauxExistants(document.getElementById('tp_id_ecue').value);
                    document.getElementById('tp_form').reset();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: data.message
                });
            }
        })
        .catch(err => {
            // Réactiver le formulaire en cas d'erreur
            tpFormSubmitting = false;
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue'
            });
        });
    });
    
    // Voir les paiements d'un travail
    function voirPaiements(idDevoir, titre) {
        document.getElementById('suivi_titre_tp').textContent = titre;
        const container = document.getElementById('table-paiements-container');
        container.innerHTML = '<p class="text-center"><div class="spinner-border spinner-border-sm"></div> Chargement...</p>';
        
        const modal = new bootstrap.Modal(document.getElementById('suiviPaiementsModal'));
        modal.show();
        
        fetch(`controller/travaux_cours_controller.php?action=get_paiements&devoir=${idDevoir}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const devise = data.devoir.devise || 'USD';
                    const deviseSymbol = devise === 'CDF' ? 'FC' : '$';
                    
                    // Update stats
                    document.getElementById('stat_total_recu').textContent = data.stats.total_recu.toLocaleString() + ' ' + deviseSymbol;
                    document.getElementById('stat_total_attente').textContent = data.stats.total_en_attente.toLocaleString() + ' ' + deviseSymbol;
                    document.getElementById('stat_nb_reussi').textContent = data.stats.nb_reussi;
                    document.getElementById('stat_nb_echoue').textContent = data.stats.nb_echoue;
                    
                    if (data.paiements.length === 0) {
                        container.innerHTML = '<div class="alert alert-info text-center"><i class="bi bi-info-circle me-2"></i>Aucun paiement enregistré pour ce TP.</div>';
                        return;
                    }
                    
                    let html = '<table class="table table-striped table-bordered table-sm">';
                    html += '<thead class="table-dark"><tr><th>#</th><th>Étudiant</th><th>Matricule</th><th>Groupe</th><th>Montant</th><th>Statut</th><th>Date</th><th>Référence</th></tr></thead>';
                    html += '<tbody>';
                    data.paiements.forEach((p, i) => {
                        const statutBadge = p.statut === 'reussi' ? '<span class="badge bg-success">Réussi</span>' :
                                           p.statut === 'en_attente' ? '<span class="badge bg-warning">En attente</span>' :
                                           p.statut === 'echoue' ? '<span class="badge bg-danger">Échoué</span>' :
                                           '<span class="badge bg-secondary">' + p.statut + '</span>';
                        const groupe = p.numero_groupe ? 'Groupe ' + p.numero_groupe : '-';
                        const date = p.date_paiement ? new Date(p.date_paiement).toLocaleString('fr-FR') : '-';
                        html += `<tr>
                            <td>${i+1}</td>
                            <td>${p.noms}</td>
                            <td>${p.matricule}</td>
                            <td>${groupe}</td>
                            <td>${parseFloat(p.montant).toLocaleString()} ${deviseSymbol}</td>
                            <td>${statutBadge}</td>
                            <td>${date}</td>
                            <td><small>${p.reference_transaction || '-'}</small></td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
                }
            })
            .catch(err => {
                container.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des paiements.</div>';
            });
    }
    
    // Modifier un travail
    function modifierTravail(idDevoir) {
        fetch(`controller/travaux_cours_controller.php?action=get_travail&id=${idDevoir}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tp = data.travail;
                    document.getElementById('edit_tp_id').value = tp.iddevoir;
                    document.getElementById('edit_tp_titre').value = tp.titre;
                    document.getElementById('edit_tp_description').value = tp.description || '';
                    document.getElementById('edit_tp_date_limite').value = tp.date_limite ? tp.date_limite.replace(' ', 'T') : '';
                    
                    // Devise
                    const devise = tp.devise || 'USD';
                    document.getElementById('edit_devise_' + devise.toLowerCase()).checked = true;
                    document.querySelectorAll('.edit-devise-label').forEach(el => el.textContent = devise);
                    
                    // Type travail
                    if (tp.type_travail === 'groupe') {
                        document.getElementById('edit_type_groupe').checked = true;
                        document.getElementById('edit-options-individuel').style.display = 'none';
                        document.getElementById('edit-options-groupe').style.display = 'block';
                        document.getElementById('edit_max_etudiants').value = tp.max_etudiants_groupe || 3;
                        document.getElementById('edit_nombre_groupes').value = tp.nombre_groupes || 10;
                        const fichierParGroupe = tp.fichier_par_groupe == 1;
                        document.getElementById('edit_fichier_par_groupe').checked = fichierParGroupe;
                        // Gérer l'affichage des fichiers par groupe en mode édition
                        if (fichierParGroupe) {
                            document.getElementById('edit-fichier-global-container').style.display = 'none';
                            document.getElementById('edit-fichiers-par-groupe-container').style.display = 'block';
                            const nbGroupes = parseInt(tp.nombre_groupes) || 0;
                            if (nbGroupes > 0) {
                                genererInputsFichiersGroupes(nbGroupes, 'edit-liste-fichiers-groupes', 'fichier_groupe_', tp.fichiers_groupes || []);
                            }
                        } else {
                            document.getElementById('edit-fichier-global-container').style.display = 'block';
                            document.getElementById('edit-fichiers-par-groupe-container').style.display = 'none';
                        }
                        document.getElementById('edit_est_payant_grp').checked = tp.est_payant == 1;
                        document.getElementById('edit-prix-groupe-container').style.display = tp.est_payant == 1 ? 'block' : 'none';
                        if (tp.type_prix_groupe === 'par_etudiant') {
                            document.getElementById('edit_prix_par_etudiant').checked = true;
                            document.getElementById('edit-prix-forfaitaire-container').style.display = 'none';
                            document.getElementById('edit-prix-par-etudiant-container').style.display = 'block';
                        } else {
                            document.getElementById('edit_prix_forfaitaire').checked = true;
                            document.getElementById('edit-prix-forfaitaire-container').style.display = 'block';
                            document.getElementById('edit-prix-par-etudiant-container').style.display = 'none';
                        }
                        document.getElementById('edit_prix_forfaitaire_val').value = tp.prix_forfaitaire || 0;
                        document.getElementById('edit_prix_etudiant_groupe').value = tp.prix_par_etudiant || 0;
                    } else {
                        document.getElementById('edit_type_individuel').checked = true;
                        document.getElementById('edit-options-individuel').style.display = 'block';
                        document.getElementById('edit-options-groupe').style.display = 'none';
                        document.getElementById('edit_est_payant_ind').checked = tp.est_payant == 1;
                        document.getElementById('edit-prix-individuel-container').style.display = tp.est_payant == 1 ? 'block' : 'none';
                        document.getElementById('edit_prix_etudiant').value = tp.prix_par_etudiant || 0;
                    }
                    
                    // Close the TP list modal first
                    const tpModal = bootstrap.Modal.getInstance(document.getElementById('travauxPratiquesModal'));
                    if (tpModal) tpModal.hide();
                    
                    setTimeout(() => {
                        const editModal = new bootstrap.Modal(document.getElementById('editTpModal'));
                        editModal.show();
                    }, 300);
                } else {
                    Swal.fire({ icon: 'error', title: 'Erreur', text: data.message });
                }
            })
            .catch(err => {
                Swal.fire({ icon: 'error', title: 'Erreur', text: 'Impossible de charger les données du TP.' });
            });
    }
    
    // Edit modal: type travail toggle
    document.querySelectorAll('#editTpModal input[name="type_travail"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const isIndividuel = this.value === 'individuel';
            document.getElementById('edit-options-individuel').style.display = isIndividuel ? 'block' : 'none';
            document.getElementById('edit-options-groupe').style.display = !isIndividuel ? 'block' : 'none';
            // Reset fichier display
            document.getElementById('edit-fichier-global-container').style.display = 'block';
            document.getElementById('edit-fichiers-par-groupe-container').style.display = 'none';
        });
    });
    
    // Edit modal: fichier par groupe change
    document.getElementById('edit_fichier_par_groupe').addEventListener('change', function() {
        const fichierParGroupe = this.checked;
        document.getElementById('edit-fichier-global-container').style.display = fichierParGroupe ? 'none' : 'block';
        document.getElementById('edit-fichiers-par-groupe-container').style.display = fichierParGroupe ? 'block' : 'none';
        if (fichierParGroupe) {
            const nb = parseInt(document.getElementById('edit_nombre_groupes').value) || 0;
            if (nb > 0) genererInputsFichiersGroupes(nb, 'edit-liste-fichiers-groupes', 'fichier_groupe_', []);
        }
    });
    document.getElementById('edit_nombre_groupes').addEventListener('change', function() {
        if (document.getElementById('edit_fichier_par_groupe').checked) {
            const nb = parseInt(this.value) || 0;
            if (nb > 0) genererInputsFichiersGroupes(nb, 'edit-liste-fichiers-groupes', 'fichier_groupe_', []);
        }
    });

    // Edit modal: devise change
    document.querySelectorAll('#editTpModal input[name="devise"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.edit-devise-label').forEach(el => el.textContent = this.value);
        });
    });

    // Edit modal: payant checkboxes
    document.getElementById('edit_est_payant_ind').addEventListener('change', function() {
        document.getElementById('edit-prix-individuel-container').style.display = this.checked ? 'block' : 'none';
    });
    document.getElementById('edit_est_payant_grp').addEventListener('change', function() {
        document.getElementById('edit-prix-groupe-container').style.display = this.checked ? 'block' : 'none';
    });

    // Edit modal: type prix groupe
    document.querySelectorAll('#editTpModal input[name="type_prix_groupe"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const isForfaitaire = this.value === 'forfaitaire';
            document.getElementById('edit-prix-forfaitaire-container').style.display = isForfaitaire ? 'block' : 'none';
            document.getElementById('edit-prix-par-etudiant-container').style.display = !isForfaitaire ? 'block' : 'none';
        });
    });

    // Variable pour éviter les doubles soumissions (edit form)
    let editTpFormSubmitting = false;
    
    // Edit form submit
    document.getElementById('edit_tp_form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Empêcher la double soumission
        if (editTpFormSubmitting) {
            return;
        }
        editTpFormSubmitting = true;
        
        // Désactiver le bouton de soumission
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mise à jour...';
        
        const formData = new FormData(this);
        formData.append('action', 'update_travail');
        
        // Set est_payant based on type
        if (document.getElementById('edit_type_individuel').checked) {
            formData.set('est_payant', document.getElementById('edit_est_payant_ind').checked ? '1' : '0');
        } else {
            formData.set('est_payant', document.getElementById('edit_est_payant_grp').checked ? '1' : '0');
        }
        
        fetch('controller/travaux_cours_controller.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Réactiver le formulaire
            editTpFormSubmitting = false;
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Succès', text: data.message }).then(() => {
                    bootstrap.Modal.getInstance(document.getElementById('editTpModal')).hide();
                    // Reopen the TP list modal and refresh
                    setTimeout(() => {
                        openTravauxPratiques(document.getElementById('tp_id_ecue').value);
                    }, 300);
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Erreur', text: data.message });
            }
        })
        .catch(err => {
            // Réactiver le formulaire en cas d'erreur
            editTpFormSubmitting = false;
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            
            Swal.fire({ icon: 'error', title: 'Erreur', text: 'Une erreur est survenue' });
        });
    });
    
    // Supprimer un travail
    function supprimerTravail(idDevoir) {
        Swal.fire({
            title: 'Confirmation',
            text: 'Êtes-vous sûr de vouloir supprimer ce travail ? Cette action est irréversible.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`controller/travaux_cours_controller.php?action=delete_travail&id=${idDevoir}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Succès',
                                text: data.message
                            }).then(() => {
                                chargerTravauxExistants(document.getElementById('tp_id_ecue').value);
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: data.message
                            });
                        }
                    })
                    .catch(err => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Une erreur est survenue lors de la suppression'
                        });
                    });
            }
        });
    }
</script>

<?php include "./views/include/footer_file.php"; ?>