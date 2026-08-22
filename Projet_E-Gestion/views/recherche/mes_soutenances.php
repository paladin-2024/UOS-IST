<?php
include "./views/include/header.php";

// Initialisation des classes
$soutenanceModel = new Soutenance();
$universite = new Universite();
$agentModel = new Agent();

// Récupération de l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();
$yearId = $currentYear['idannee_acad'];

// ID de l'enseignant connecté
$idEnseignant = $_SESSION['id'];

// Récupérer les soutenances où l'enseignant est lecteur
$soutenancesLecteur = $soutenanceModel->getSoutenancesParLecteur($idEnseignant, $yearId);

// Récupérer les soutenances où l'enseignant est directeur
$soutenancesDirecteur = $soutenanceModel->getSoutenancesParDirecteur($idEnseignant, $yearId);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>MES SOUTENANCES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Recherche</li>
                <li class="breadcrumb-item active">Mes Soutenances</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Tab navigation -->
        <ul class="nav nav-tabs" id="soutenanceTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="lecteur-tab" data-bs-toggle="tab" data-bs-target="#lecteur" 
                        type="button" role="tab" aria-controls="lecteur" aria-selected="true">
                    <i class="bi bi-book"></i> Travaux à évaluer comme Lecteur
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="directeur-tab" data-bs-toggle="tab" data-bs-target="#directeur" 
                        type="button" role="tab" aria-controls="directeur" aria-selected="false">
                    <i class="bi bi-person-badge"></i> Travaux comme Directeur
                </button>
            </li>
        </ul>
        
        <div class="tab-content pt-3">
            <!-- Tab content for Lecteur -->
            <div class="tab-pane fade show active" id="lecteur" role="tabpanel" aria-labelledby="lecteur-tab">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Travaux où je suis Lecteur</h5>
                        
                        <?php if (empty($soutenancesLecteur)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Vous n'êtes pas assigné comme lecteur pour des soutenances en cours.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Étudiant</th>
                                            <th>Sujet</th>
                                            <th>Date & Lieu</th>
                                            <th>Type Lecteur</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($soutenancesLecteur as $soutenance): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($soutenance['etudiant_nom']) ?></td>
                                                <td><?= htmlspecialchars($soutenance['sujet']) ?></td>
                                                <td>
                                                    <?= date('d/m/Y H:i', strtotime($soutenance['date_soutenance'])) ?><br>
                                                    <small><?= htmlspecialchars($soutenance['lieu']) ?></small>
                                                </td>
                                                <td>
                                                    <?= $soutenance['est_premier_lecteur'] ? 'Premier lecteur' : 'Second lecteur' ?>
                                                </td>
                                                <td>
                                                    <?php if ($soutenance['a_note']): ?>
                                                        <span class="badge bg-success">Noté</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">En attente</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" 
                                                            onclick="noterTravailLecteur(<?= $soutenance['idsoutenance'] ?>, <?= $idEnseignant ?>)">
                                                        <?= $soutenance['a_note'] ? 'Modifier note' : 'Noter' ?>
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
            
            <!-- Tab content for Directeur -->
            <div class="tab-pane fade" id="directeur" role="tabpanel" aria-labelledby="directeur-tab">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Travaux où je suis Directeur</h5>
                        
                        <?php if (empty($soutenancesDirecteur)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Vous n'avez pas d'étudiants programmés en soutenance.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Étudiant</th>
                                            <th>Sujet</th>
                                            <th>Date & Lieu</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($soutenancesDirecteur as $soutenance): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($soutenance['etudiant_nom']) ?></td>
                                                <td><?= htmlspecialchars($soutenance['sujet']) ?></td>
                                                <td>
                                                    <?= date('d/m/Y H:i', strtotime($soutenance['date_soutenance'])) ?><br>
                                                    <small><?= htmlspecialchars($soutenance['lieu']) ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($soutenance['a_note']): ?>
                                                        <span class="badge bg-success">Noté</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">En attente</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" 
                                                            onclick="noterTravailDirecteur(<?= $soutenance['idsoutenance'] ?>, <?= $idEnseignant ?>)">
                                                        <?= $soutenance['a_note'] ? 'Modifier note' : 'Noter' ?>
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
        </div>
    </section>
</main>

<!-- Modal pour noter comme Lecteur -->
<div class="modal fade" id="noteLecteurModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Évaluer le travail (Lecteur)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formNoteLecteur" action="controller/depot_soutenance_controller.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="enregistrer_notes_lecteur">
                    <input type="hidden" name="id_soutenance" id="lecteur_id_soutenance">
                    <input type="hidden" name="id_enseignant" id="lecteur_id_enseignant">
                    
                    <div class="mb-3">
                        <label for="note_fond" class="form-label">Note sur le fond (/20)</label>
                        <input type="number" name="note_fond" id="note_fond" class="form-control" 
                               min="0" max="20" step="0.1" required>
                        <div class="invalid-feedback">Veuillez saisir une note valide entre 0 et 20.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="note_forme" class="form-label">Note sur la forme (/20)</label>
                        <input type="number" name="note_forme" id="note_forme" class="form-control" 
                               min="0" max="20" step="0.1" required>
                        <div class="invalid-feedback">Veuillez saisir une note valide entre 0 et 20.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="commentaire" class="form-label">Commentaires (optionnel)</label>
                        <textarea name="commentaire" id="commentaire" class="form-control" rows="4"></textarea>
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

<!-- Modal pour noter comme Directeur -->
<div class="modal fade" id="noteDirecteurModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Évaluer la soutenance (Directeur)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formNoteDirecteur" action="controller/depot_soutenance_controller.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="enregistrer_note_directeur">
                    <input type="hidden" name="id_soutenance" id="directeur_id_soutenance">
                    <input type="hidden" name="id_enseignant" id="directeur_id_enseignant">
                    
                    <div class="mb-3">
                        <label for="note_soutenance" class="form-label">Note de soutenance (/20)</label>
                        <input type="number" name="note_soutenance" id="note_soutenance" class="form-control" 
                               min="0" max="20" step="0.1" required>
                        <div class="invalid-feedback">Veuillez saisir une note valide entre 0 et 20.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="commentaire_directeur" class="form-label">Commentaires (optionnel)</label>
                        <textarea name="commentaire" id="commentaire_directeur" class="form-control" rows="4"></textarea>
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

<script>
    // Fonction pour noter un travail en tant que lecteur
    function noterTravailLecteur(idSoutenance, idEnseignant) {
        // Récupérer les notes existantes si disponibles
        fetch(`controller/depot_soutenance_controller.php?action=get_soutenance_details&id=${idSoutenance}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const soutenanceDetails = data.data;
                    
                    // Remplir les champs du formulaire
                    document.getElementById('lecteur_id_soutenance').value = idSoutenance;
                    document.getElementById('lecteur_id_enseignant').value = idEnseignant;
                    
                    // Chercher si l'enseignant connecté a déjà noté ce travail
                    const noteExistante = soutenanceDetails.notes.find(note => 
                        note.idenseignant == idEnseignant && note.type_notation === 'Lecteur'
                    );
                    
                    if (noteExistante) {
                        document.getElementById('note_fond').value = noteExistante.note_fond;
                        document.getElementById('note_forme').value = noteExistante.note_forme;
                        document.getElementById('commentaire').value = noteExistante.commentaire || '';
                    } else {
                        // Réinitialiser le formulaire
                        document.getElementById('formNoteLecteur').reset();
                    }
                    
                    // Afficher la modal
                    new bootstrap.Modal(document.getElementById('noteLecteurModal')).show();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message || 'Impossible de récupérer les détails de la soutenance'
                    });
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la communication avec le serveur'
                });
            });
    }
    
    // Fonction pour noter un travail en tant que directeur
    function noterTravailDirecteur(idSoutenance, idEnseignant) {
        // Récupérer les notes existantes si disponibles
        fetch(`controller/depot_soutenance_controller.php?action=get_soutenance_details&id=${idSoutenance}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const soutenanceDetails = data.data;
                    
                    // Remplir les champs du formulaire
                    document.getElementById('directeur_id_soutenance').value = idSoutenance;
                    document.getElementById('directeur_id_enseignant').value = idEnseignant;
                    
                    // Chercher si le directeur a déjà noté cette soutenance
                    const noteExistante = soutenanceDetails.notes.find(note => 
                        note.idenseignant == idEnseignant && note.type_notation === 'Directeur'
                    );
                    
                    if (noteExistante) {
                        document.getElementById('note_soutenance').value = noteExistante.note_soutenance;
                        document.getElementById('commentaire_directeur').value = noteExistante.commentaire || '';
                    } else {
                        // Réinitialiser le formulaire
                        document.getElementById('formNoteDirecteur').reset();
                    }
                    
                    // Afficher la modal
                    new bootstrap.Modal(document.getElementById('noteDirecteurModal')).show();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message || 'Impossible de récupérer les détails de la soutenance'
                    });
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la communication avec le serveur'
                });
            });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Initialiser la validation des formulaires Bootstrap
        var forms = document.querySelectorAll('.needs-validation');
        Array.prototype.slice.call(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    });
</script>

<?php include "./views/include/footer.php"; ?>

