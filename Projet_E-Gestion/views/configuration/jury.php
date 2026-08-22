<?php
include "./views/include/header.php";

// Récupérer les années académiques
$pdo = Connexion::getInstance()->getPDO();
$queryAnnees = "SELECT * FROM annee_acad ORDER BY designation DESC";
$stmtAnnees = $pdo->prepare($queryAnnees);
$stmtAnnees->execute();
$academicYears = $stmtAnnees->fetchAll(PDO::FETCH_ASSOC);

// Récupérer l'année académique active
$queryCurrentYear = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
$stmtCurrentYear = $pdo->prepare($queryCurrentYear);
$stmtCurrentYear->execute();
$currentYear = $stmtCurrentYear->fetch(PDO::FETCH_ASSOC);

if (!$currentYear && !empty($academicYears)) {
    // Si aucune année active, prendre la dernière
    $currentYear = $academicYears[0];
}

$universite = new Universite();
$agentModel = new Agent();

// Récupérer les enseignants pour les dropdown
$enseignants = $agentModel->getAgentsByType('Enseignant');

// Paramètres de filtrage
$search = isset($_GET['search']) ? $_GET['search'] : '';
$selectedYear = isset($_GET['annee_acad']) ? $_GET['annee_acad'] : ($currentYear ? $currentYear['idannee_acad'] : null);
?>
<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>JURYS DE DÉLIBÉRATION</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Jurys de délibération</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
    <!-- Filtres -->
    <div class="row mb-4">
    <div class="col-lg-12">
    <div class="card">
    <div class="card-body">
        <h5 class="card-title">Filtres</h5>
    <form method="GET" action="" class="row g-3" id="filterForm">
    <input type="hidden" name="view" value="configuration/jury">

    <div class="col-md-4">
    <label for="annee_acad" class="form-label">Année académique</label>
    <select name="annee_acad" class="form-select" id="annee_acad">
    <option value="">Toutes les années</option>
    <?php
    foreach ($academicYears as $year) {
    $selected = ($selectedYear == $year['idannee_acad']) ? 'selected' : '';
    echo "<option value='{$year['idannee_acad']}' $selected>{$year['designation']}</option>";
    }
    ?>
    </select>
    </div>

    <div class="col-md-8">
    <label for="searchInput" class="form-label">Recherche</label>
    <div class="input-group">
    <span class="input-group-text"><i class="bi bi-search"></i></span>
    <input type="text" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher dans tous les champs..." id="searchInput" name="search">
    <button type="submit" class="btn btn-primary">Rechercher</button>
    </div>
    </div>
    </form>
    </div>
    </div>
    </div>
    </div>

    <div class="row">
    <!-- Tableau de données -->
    <div class="col-lg-12">
    <div class="row">
    <!-- Table jurys -->
    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des jurys de délibération
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createJuryModal" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter
                                        </a>
                                    </span>
                                </h5>

                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Numéro Décision</th>
                                            <th scope="col">Président</th>
                                            <th scope="col">Secrétaire</th>
                                            <th scope="col">Année Académique</th>
                                            <th scope="col">Promotions Assignées</th>
                                            <th scope="col">État</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $listeJurys = $universite->getJurys($search, $selectedYear);
                                        $i = 1;

                                        foreach ($listeJurys as $jury) {
                                            // Récupérer les promotions associées à ce jury
                                            $promotionsJury = $universite->getPromotionsByJury($jury['idbureau']);
                                            $promotionsStr = '';
                                            foreach ($promotionsJury as $prom) {
                                                $promotionsStr .= '<span class="badge bg-info me-1">' . $prom['designationPromotion'] . '</span>';
                                            }
                                            
                                            // État actif ou inactif
                                            $etatBadge = $jury['est_actif'] ? 
                                                '<span class="badge bg-success">Actif</span>' : 
                                                '<span class="badge bg-secondary">Inactif</span>';
                                            
                                            echo "
                                            <tr>
                                                <td>{$i}</td>
                                                <td>{$jury['designation']}</td>
                                                <td>{$jury['numero_decision']}</td>
                                                <td>{$jury['president_nom']}</td>
                                                <td>{$jury['secretaire_nom']}</td>
                                                <td>{$jury['annee_academique']}</td>
                                                <td>{$promotionsStr}</td>
                                                <td>{$etatBadge}</td>
                                                <td>
                                                    <button class='btn btn-sm btn-warning' onclick='editJury(
                                                        {$jury['idbureau']}, 
                                                        \"{$jury['designation']}\",
                                                        \"{$jury['numero_decision']}\",
                                                        \"{$jury['date_decision']}\",
                                                        {$jury['president_id']},
                                                        {$jury['secretaire_id']},
                                                        {$jury['annee_acad_idannee_acad']},
                                                        {$jury['est_actif']}
                                                    )'>
                                                        <i class='bi bi-pencil-square'></i> Modifier
                                                    </button>
                                                    <button class='btn btn-sm btn-primary' onclick='managePromotions({$jury['idbureau']})'>
                                                        <i class='bi bi-list-check'></i> Promotions
                                                    </button>
                                                    <button class='btn btn-sm btn-info' onclick='manageMembers({$jury['idbureau']})'>
                                                        <i class='bi bi-people'></i> Membres
                                                    </button>
                                                    <button class='btn btn-sm btn-danger' onclick='confirmDelete({$jury['idbureau']})'>
                                                        <i class='bi bi-trash'></i> Supprimer
                                                    </button>
                                                </td>
                                            </tr>";
                                            $i++;
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div><!-- End Table -->
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<!-- Modal pour ajouter un jury -->
<div class="modal fade" id="createJuryModal" tabindex="-1" role="dialog" aria-labelledby="createJuryModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Bureau de Jury</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_jury.php" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="designation" class="form-label">Désignation du jury <span class="text-danger">*</span></label>
                            <input type="text" name="designation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="numero_decision" class="form-label">Numéro de Décision <span class="text-danger">*</span></label>
                            <input type="text" name="numero_decision" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir le numéro de décision.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="date_decision" class="form-label">Date de Décision <span class="text-danger">*</span></label>
                            <input type="date" name="date_decision" class="form-control" required>
                            <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                        </div>
                        <div class="col-md-6">
                        <label for="idAnnee" class="form-label">Année Académique <span class="text-danger">*</span></label>
                        <select name="idAnnee" class="form-control" required>
                        <?php
                        foreach ($academicYears as $year) {
                            echo "<option value='{$year['idannee_acad']}'>{$year['designation']}</option>";
                        }
                        ?>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="president_id" class="form-label">Président <span class="text-danger">*</span></label>
                            <select name="president_id" class="form-control select-class" required>
                                <option value="">Sélectionner le président</option>
                                <?php
                                foreach ($enseignants as $enseignant) {
                                    echo "<option value='{$enseignant['idAgent']}'>{$enseignant['noms']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un président.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="secretaire_id" class="form-label">Secrétaire <span class="text-danger">*</span></label>
                            <select name="secretaire_id" class="form-control select-class" required>
                                <option value="">Sélectionner le secrétaire</option>
                                <?php
                                foreach ($enseignants as $enseignant) {
                                    echo "<option value='{$enseignant['idAgent']}'>{$enseignant['noms']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un secrétaire.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="commentaire" class="form-label">Commentaire</label>
                            <textarea name="commentaire" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addJuryBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un jury -->
<div class="modal fade" id="editJuryModal" tabindex="-1" role="dialog" aria-labelledby="editJuryModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un Bureau de Jury</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_jury.php" class="needs-validation" novalidate>
                    <input type="hidden" name="editJuryId" id="editJuryId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editDesignation" class="form-label">Désignation du jury <span class="text-danger">*</span></label>
                            <input type="text" name="editDesignation" id="editDesignation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="editNumeroDecision" class="form-label">Numéro de Décision <span class="text-danger">*</span></label>
                            <input type="text" name="editNumeroDecision" id="editNumeroDecision" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir le numéro de décision.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editDateDecision" class="form-label">Date de Décision <span class="text-danger">*</span></label>
                            <input type="date" name="editDateDecision" id="editDateDecision" class="form-control" required>
                            <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="editAnneeId" class="form-label">Année Académique <span class="text-danger">*</span></label>
                            <select name="editAnneeId" id="editAnneeId" class="form-control" required>
                                <?php
                                foreach ($academicYears as $year) {
                                    echo "<option value='{$year['idannee_acad']}'>{$year['designation']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                        </div>
                        </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editPresidentId" class="form-label">Président <span class="text-danger">*</span></label>
                            <select name="editPresidentId" id="editPresidentId" class="form-control select-class" required>
                                <option value="">Sélectionner le président</option>
                                <?php
                                foreach ($enseignants as $enseignant) {
                                    echo "<option value='{$enseignant['idAgent']}'>{$enseignant['noms']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un président.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="editSecretaireId" class="form-label">Secrétaire <span class="text-danger">*</span></label>
                            <select name="editSecretaireId" id="editSecretaireId" class="form-control select-class" required>
                                <option value="">Sélectionner le secrétaire</option>
                                <?php
                                foreach ($enseignants as $enseignant) {
                                    echo "<option value='{$enseignant['idAgent']}'>{$enseignant['noms']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un secrétaire.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editEstActif" class="form-label">État du jury</label>
                            <select name="editEstActif" id="editEstActif" class="form-control">
                                <option value="1">Actif</option>
                                <option value="0">Inactif</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editCommentaire" class="form-label">Commentaire</label>
                            <textarea name="editCommentaire" id="editCommentaire" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="updateJuryBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour gérer les membres du jury -->
<div class="modal fade" id="manageMembersModal" tabindex="-1" role="dialog" aria-labelledby="manageMembersModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gestion des membres du jury</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="currentJuryId">
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <h6>Ajouter un nouveau membre</h6>
                        <form id="addMemberForm" class="row g-3">
                            <div class="col-md-6">
                                <select id="newMemberId" class="form-control select-class" required>
                                    <option value="">Sélectionner un enseignant</option>
                                    <?php
                                    foreach ($enseignants as $enseignant) {
                                        echo "<option value='{$enseignant['idAgent']}'>{$enseignant['noms']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" id="newMemberFonction" class="form-control" placeholder="Fonction (optionnelle)">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-primary w-100" onclick="addJuryMember()">
                                    <i class="bi bi-plus-circle"></i> Ajouter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <h6>Membres actuels</h6>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Fonction</th>
                                        <th>Date d'ajout</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="membersList">
                                    <!-- Liste des membres chargée via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour gérer les promotions assignées au jury -->
<div class="modal fade" id="managePromotionsModal" tabindex="-1" role="dialog" aria-labelledby="managePromotionsModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gestion des promotions assignées</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="promotionsJuryId">
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <h6>Ajouter une promotion</h6>
                        <form id="addPromotionForm" class="row g-3">
                            <div class="col-md-10">
                                <select id="newPromotionId" class="form-control select-class" required>
                                    <option value="">Sélectionner une promotion</option>
                                    <?php
                                    $promotions = $universite->getPromotions('', $selectedYear);
                                    foreach ($promotions as $promotion) {
                                    echo "<option value='{$promotion['idpromotion']}'>{$promotion['designationPromotion']} - {$promotion['anneeDesignation']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-primary w-100" onclick="assignPromotion()">
                                    <i class="bi bi-plus-circle"></i> Ajouter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <h6>Promotions assignées</h6>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Désignation</th>
                                        <th>Cycle</th>
                                        <th>Orientation</th>
                                        <th>Année académique</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="promotionsList">
                                    <!-- Liste des promotions chargée via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
    function editJury(id, designation, numero_decision, date_decision, president_id, secretaire_id, annee_id, est_actif) {
        document.getElementById('editJuryId').value = id;
        document.getElementById('editDesignation').value = designation;
        document.getElementById('editNumeroDecision').value = numero_decision;
        document.getElementById('editDateDecision').value = date_decision;
        document.getElementById('editPresidentId').value = president_id;
        document.getElementById('editSecretaireId').value = secretaire_id;
        document.getElementById('editAnneeId').value = annee_id;
        document.getElementById('editEstActif').value = est_actif;

        // Rafraîchir les sélecteurs Select2
        $('.select-class').trigger('change');
        
        new bootstrap.Modal(document.getElementById('editJuryModal')).show();
    }







function manageMembers(juryId) {
    // Stocker l'ID du jury
    document.getElementById('currentJuryId').value = juryId;
    
    // Charger les données
    fetch(`controller/get_jury_members.php?jury_id=${juryId}`)
        .then(response => response.json())
        .then(data => {
            // Remplir le tableau
            let html = '';
            if (data.length === 0) {
                html = '<tr><td colspan="4" class="text-center">Aucun membre supplémentaire</td></tr>';
            } else {
                data.forEach(member => {
                    html += `
                        <tr>
                            <td>${member.noms}</td>
                            <td>${member.fonction || '-'}</td>
                            <td>${member.date_ajout}</td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="removeMember(${member.idmembre})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }
            
            document.getElementById('membersList').innerHTML = html;
            
            // Afficher le modal - utiliser jQuery si disponible
            try {
                const modalElement = document.getElementById('manageMembersModal');
                if (window.jQuery) {
                    $('#manageMembersModal').modal('show');
                } else if (window.bootstrap) {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                } else {
                    // Fallback si ni jQuery ni bootstrap ne sont disponibles
                    console.error('Bootstrap ou jQuery non disponible');
                    alert('Erreur d\'affichage du modal');
                }
            } catch (error) {
                console.error('Erreur lors de l\'affichage du modal:', error);
                alert('Erreur d\'affichage du modal');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors du chargement des membres'
            });
        });
}


function managePromotions(juryId) {
    document.getElementById('promotionsJuryId').value = juryId;
    
    // Charger la liste des promotions via AJAX
    fetch(`controller/get_jury_promotions.php?jury_id=${juryId}`)
        .then(response => response.json())
        .then(data => {
            let html = '';
            
            if (data.length === 0) {
                html = '<tr><td colspan="5" class="text-center">Aucune promotion assignée</td></tr>';
            } else {
                data.forEach(promotion => {
                    html += `
                        <tr>
                            <td>${promotion.designationPromotion}</td>
                            <td>${promotion.cycle}</td>
                            <td>${promotion.orientationDesignation}</td>
                            <td>${promotion.anneeDesignation}</td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="removePromotion(${promotion.id})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }
            
            document.getElementById('promotionsList').innerHTML = html;
            
            // Afficher le modal
            const modal = new bootstrap.Modal(document.getElementById('managePromotionsModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Erreur:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors du chargement des promotions'
            });
        });
}






    function addJuryMember() {
    const juryId = document.getElementById('currentJuryId').value;
    const memberId = document.getElementById('newMemberId').value;
    const fonction = document.getElementById('newMemberFonction').value;
    
    if (!memberId) {
        Swal.fire({
            icon: 'warning',
            title: 'Attention',
            text: 'Veuillez sélectionner un enseignant'
        });
        return;
    }
    
    // Envoyer la requête d'ajout via AJAX
    fetch('controller/add_jury_member.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `jury_id=${juryId}&member_id=${memberId}&fonction=${encodeURIComponent(fonction)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Message de succès avec SweetAlert
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Membre ajouté avec succès',
                timer: 1500
            });
            
            // Recharger la liste des membres
            manageMembers(juryId);
            
            // Réinitialiser le formulaire
            document.getElementById('newMemberId').value = '';
            document.getElementById('newMemberFonction').value = '';
            if ($.fn.select2) {
                $('#newMemberId').val('').trigger('change');
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: data.message || 'Une erreur est survenue lors de l\'ajout du membre'
            });
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Une erreur est survenue lors de l\'ajout du membre'
        });
    });
}

function removeMember(memberId) {
    Swal.fire({
        title: 'Êtes-vous sûr ?',
        text: "Voulez-vous vraiment retirer ce membre ?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, retirer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            const juryId = document.getElementById('currentJuryId').value;
            
            fetch('controller/remove_jury_member.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `member_id=${memberId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Message de succès avec SweetAlert
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: 'Membre retiré avec succès',
                        timer: 1500
                    });
                    
                    // Recharger la liste des membres
                    manageMembers(juryId);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message || 'Une erreur est survenue lors de la suppression du membre'
                    });
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la suppression du membre'
                });
            });
        }
    });
}

function assignPromotion() {
    const juryId = document.getElementById('promotionsJuryId').value;
    const promotionId = document.getElementById('newPromotionId').value;
    
    if (!promotionId) {
        Swal.fire({
            icon: 'warning',
            title: 'Attention',
            text: 'Veuillez sélectionner une promotion'
        });
        return;
    }
    
    // Envoyer la requête d'affectation via AJAX
    fetch('controller/assign_jury_promotion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `jury_id=${juryId}&promotion_id=${promotionId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Message de succès avec SweetAlert
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Promotion assignée avec succès'
            }).then(() => {
                window.location.href = 'configuration/jury';
            });
            
            // Recharger la liste des promotions
            managePromotions(juryId);
            
            // Réinitialiser le formulaire
            document.getElementById('newPromotionId').value = '';
            if ($.fn.select2) {
                $('#newPromotionId').val('').trigger('change');
            }
        } else {
            console.error('Données de réponse:', data);
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: data.message || 'Une erreur est survenue lors de l\'assignation de la promotion'
            });
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Une erreur est survenue lors de l\'assignation de la promotion'
        });
    });
}

function removePromotion(associationId) {
    Swal.fire({
        title: 'Êtes-vous sûr ?',
        text: "Voulez-vous vraiment retirer cette promotion du jury ?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, retirer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            const juryId = document.getElementById('promotionsJuryId').value;
            
            fetch('controller/remove_jury_promotion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `association_id=${associationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Message de succès avec SweetAlert
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: 'Promotion retirée avec succès'
                    }).then(() => {
                        window.location.href = 'configuration/jury';
                    });
                    
                    // Recharger la liste des promotions
                    managePromotions(juryId);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message || 'Une erreur est survenue lors du retrait de la promotion'
                    });
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors du retrait de la promotion'
                });
            });
        }
    });
}



    


    function confirmDelete(idJury) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action est irréversible !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/delete_jury.php?idbureau=' + idJury;
            }
        });
    }

    // Initialiser les select2 une fois le document chargé
    document.addEventListener('DOMContentLoaded', function() {
        $('.select-class').select2({
            dropdownParent: $('.modal')
        });

        // Gestion des filtres
        const anneeFilter = document.getElementById('annee_acad');
        if (anneeFilter && typeof $ !== 'undefined' && $.fn.select2) {
            // Initialiser Select2 pour le filtre année
            $(anneeFilter).select2();

            // Soumettre automatiquement lors du changement
            $(anneeFilter).on('select2:select', function() {
                document.getElementById('filterForm').submit();
            });
        }
    });
</script>

<?php include "./views/include/footer.php"; ?>

