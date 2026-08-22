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
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;

// Récupérer les jurys
if ($isAdmin) {
    // Si admin, récupérer tous les jurys
    $jurys = $soutenanceModel->getAllJurys($yearId);
} else {
    // Sinon, seulement les jurys où l'enseignant est président ou secrétaire
    $jurys = $soutenanceModel->getJurysByRole($idEnseignant, $yearId);
}

// Si un jury spécifique est sélectionné
$selectedJuryId = isset($_GET['jury_id']) ? intval($_GET['jury_id']) : 0;
$soutenances = [];

if ($selectedJuryId > 0) {
    // Vérifier que l'utilisateur a accès à ce jury
    $hasAccess = $isAdmin; // L'admin a accès par défaut
    if (!$hasAccess) { // Vérifier seulement si ce n'est pas un admin
        foreach ($jurys as $jury) {
            if ($jury['idjury'] == $selectedJuryId) {
                $hasAccess = true;
                $selectedJury = $jury;
                break;
            }
        }
    } else {
        // Pour l'admin, trouver le jury sélectionné
        foreach ($jurys as $jury) {
            if ($jury['idjury'] == $selectedJuryId) {
                $selectedJury = $jury;
                break;
            }
        }
    }

    if ($hasAccess) {
        // Récupérer les soutenances pour ce jury - même requête que memoire/index.php
        $soutenances = [];
        try {
            $connexion = Connexion::getInstance()->getPDO();
            
            // Utiliser exactement la même requête que memoire/index.php mais filtrer par jury
            $query = "SELECT s.*, 
                             sj.intitule as sujet_titre, sj.idsujets,
                             e.noms as etudiant_nom, e.matricule,
                             d.noms as directeur_nom,
                             sp.designation as specialisation, sp.\"idSpecialisation\",
                             dm.\"idDepot\", dm.fichier as memoire_fichier, dm.\"dateDepot\"
                      FROM soutenance s
                      JOIN sujets sj ON s.sujets_idsujets = sj.idsujets
                      JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
                      LEFT JOIN agent d ON sj.\"idDirecteur\" = d.\"idAgent\"
                      LEFT JOIN specialisation sp ON sj.\"idSpecialisation\" = sp.\"idSpecialisation\"
                      LEFT JOIN depot_memoire dm ON sj.idsujets = dm.sujets_idsujets
                      WHERE s.annee_acad_idannee_acad = ? AND s.jury_id = ?
                      ORDER BY s.date_soutenance DESC";
            
            $stmt = $connexion->prepare($query);
            $stmt->execute([$yearId, $selectedJuryId]);
            $soutenances = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des soutenances du jury: " . $e->getMessage());
            $soutenances = [];
        }
    } else {
        // Rediriger si pas d'accès
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Accès refusé',
                text: 'Vous n\'avez pas accès à ce jury.'
            }).then(() => {
                window.location.href = '?view=recherche/jury_soutenances';
            });
        </script>";
    }
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES SOUTENANCES PAR JURY</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Recherche</li>
                <li class="breadcrumb-item active">Jury et Soutenances</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <?php if (empty($jurys)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                <?php if ($isAdmin): ?>
                    Aucun jury n'a été créé pour l'année académique en cours.
                <?php else: ?>
                    Vous n'êtes pas membre d'un jury pour l'année académique en cours.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Sélection de jury -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Sélectionner un jury</h5>
                            <form action="" method="GET" class="d-flex gap-2">
                                <input type="hidden" name="view" value="recherche/jury_soutenances">
                                <select name="jury_id" class="form-select" required>
                                    <option value="">-- Choisir un jury --</option>
                                    <?php foreach ($jurys as $jury): ?>
                                        <option value="<?= $jury['idjury'] ?>" <?= $selectedJuryId == $jury['idjury'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($jury['designation']) ?>
                                            <?php
                                            if (!$isAdmin) {
                                                echo $jury['id_president'] == $idEnseignant ? ' (Président)' : ' (Secrétaire)';
                                            } else {
                                                echo ' - ' . htmlspecialchars($jury['section_nom'] ?? 'Toutes sections');
                                            }
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary flex-shrink-0">Consulter</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($selectedJuryId > 0): ?>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title d-flex justify-content-between align-items-center">
                            <span>Soutenances du jury: <?= htmlspecialchars($selectedJury['designation']) ?></span>
                            <?php if (!empty($soutenances)): ?>
                                <a href="controller/export_jury_soutenances_pdf.php?jury_id=<?= $selectedJuryId ?>&annee_acad=<?= $yearId ?>"
                                    class="btn btn-success" target="_blank">
                                    <i class="bi bi-file-earmark-pdf"></i> Exporter en PDF
                                </a>
                            <?php endif; ?>
                        </h5>

                        <?php if (empty($soutenances)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Aucune soutenance n'a été programmée pour ce jury.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered datatable" id="juryMemoiresTable">
                                    <thead>
                                        <tr>
                                            <th>Matricule</th>
                                            <th>Étudiant</th>
                                            <th>Titre du Mémoire</th>
                                            <th>Directeur</th>
                                            <th>Dépôt</th>
                                            <th>Statut</th>
                                            <th>Date Soutenance</th>
                                            <th>Lieu</th>
                                            <th>Lecteurs</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if (!empty($soutenances)) {
                                            foreach ($soutenances as $soutenance):
                                        ?>
                                                <tr data-soutenance-id="<?php echo htmlspecialchars($soutenance['idsoutenance']); ?>">
                                                    <td><?php echo htmlspecialchars($soutenance['matricule'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($soutenance['etudiant_nom']); ?></td>
                                                    <td><?php echo htmlspecialchars($soutenance['sujet_titre']); ?></td>
                                                    <td><?php echo htmlspecialchars($soutenance['directeur_nom'] ?? '-'); ?></td>
                                                    <td>
                                                        <?php if (!empty($soutenance['idDepot']) && !empty($soutenance['memoire_fichier'])): ?>
                                                            <span class="badge bg-success" title="Dépôt le <?php echo date('d/m/Y', strtotime($soutenance['dateDepot'])); ?>">
                                                                <i class="bi bi-check-circle me-1"></i>Déposé
                                                            </span>
                                                            <a href="<?php echo htmlspecialchars($soutenance['memoire_fichier']); ?>" class="btn btn-sm btn-outline-info ms-2" target="_blank" title="Télécharger le mémoire">
                                                                <i class="bi bi-download"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">
                                                                <i class="bi bi-clock me-1"></i>En attente
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php
                                                                                echo ($soutenance['statut'] == 'Programmée') ? 'success' : (($soutenance['statut'] == 'Terminée') ? 'primary' :
                                                                                        'secondary');
                                                                                ?>">
                                                            <?php echo htmlspecialchars($soutenance['statut'] ?? 'Non programmée'); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $soutenance['date_soutenance'] ? date('d/m/Y', strtotime($soutenance['date_soutenance'])) : '-'; ?></td>
                                                    <td><?php echo htmlspecialchars($soutenance['lieu'] ?? '-'); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-info" onclick="viewLecteurs(<?php echo $soutenance['idsoutenance']; ?>)" title="Voir les lecteurs">
                                                            <i class="bi bi-person-check"></i> Lecteurs
                                                        </button>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-info" onclick="viewMemoireDetails(<?php echo $soutenance['idsoutenance']; ?>)" title="Voir détails">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                        <?php
                                            endforeach;
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<!-- Modal pour voir les détails d'une soutenance -->
<div class="modal fade" id="memoireDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails de la Soutenance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="memoireDetailsContent">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
        </div>
    </div>
</div>

<!-- Modal pour voir les lecteurs -->
<div class="modal fade" id="lecteursModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Lecteurs Assignés</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="lecteursContent">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
        </div>
    </div>
</div>


<!-- Modal pour voir les détails d'une soutenance -->
<div class="modal fade" id="detailsSoutenanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails de la soutenance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="soutenance-loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-2">Chargement des détails...</p>
                </div>

                <div id="soutenance-details" style="display: none;">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Étudiant:</h6>
                            <p id="detail-etudiant" class="fw-bold"></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Date et lieu:</h6>
                            <p id="detail-date-lieu"></p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h6>Sujet:</h6>
                            <p id="detail-sujet" class="fw-bold"></p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Directeur:</h6>
                            <p id="detail-directeur"></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Lecteurs:</h6>
                            <p id="detail-lecteurs"></p>
                        </div>
                    </div>

                    <hr>

                    <h5 class="mb-3">Notes d'évaluation</h5>

                    <div id="notes-container">
                        <!-- Les notes seront insérées ici dynamiquement -->
                    </div>

                    <div id="moyennes-container" class="mt-4">
                        <h6 class="bg-light p-2">Moyennes</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <p>Moyenne fond: <span id="moyenne-fond" class="fw-bold"></span>/20</p>
                            </div>
                            <div class="col-md-4">
                                <p>Moyenne forme: <span id="moyenne-forme" class="fw-bold"></span>/20</p>
                            </div>
                            <div class="col-md-4">
                                <p>Note soutenance: <span id="note-soutenance" class="fw-bold"></span>/20</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <p class="bg-info text-white p-2 text-center">
                                    Moyenne finale: <span id="moyenne-finale" class="fw-bold"></span>/20
                                </p>
                            </div>
                        </div>
                    </div>

                    <div id="validation-container" class="mt-3">
                        <!-- Information de validation -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour valider les notes -->
<div class="modal fade" id="validationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Validation des notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formValidation" action="controller/depot_soutenance_controller.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="valider_notes_soutenance">
                    <input type="hidden" name="id_soutenance" id="validation_id_soutenance">
                    <input type="hidden" name="id_validateur" value="<?= $idEnseignant ?>">

                    <div class="mb-3">
                        <label class="form-label">Décision</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="est_valide" id="decision_valide" value="1" checked>
                            <label class="form-check-label" for="decision_valide">
                                Valider les notes
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="est_valide" id="decision_rejete" value="0">
                            <label class="form-check-label" for="decision_rejete">
                                Rejeter les notes
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="commentaire_validation" class="form-label">Commentaire</label>
                        <textarea name="commentaire" id="commentaire_validation" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="est_visible" name="est_visible" value="1">
                        <label class="form-check-label" for="est_visible">
                            Rendre les résultats visibles pour l'étudiant
                        </label>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer la validation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Fonction pour voir les détails d'une soutenance
    function voirDetailsSoutenance(idSoutenance) {
        // Afficher l'indicateur de chargement
        document.getElementById('soutenance-loading').style.display = 'block';
        document.getElementById('soutenance-details').style.display = 'none';

        // Ouvrir la modal
        new bootstrap.Modal(document.getElementById('detailsSoutenanceModal')).show();

        // Charger les détails
        fetch(`controller/get_soutenance_details.php?id=${idSoutenance}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Masquer le chargement
                    document.getElementById('soutenance-loading').style.display = 'none';
                    document.getElementById('soutenance-details').style.display = 'block';

                    // Remplir les détails de base
                    const soutenance = data.data.soutenance;
                    const lecteurs = data.data.lecteurs;
                    const notes = data.data.notes;
                    const moyennes = data.data.moyennes;
                    const validation = data.data.validation;

                    document.getElementById('detail-etudiant').textContent = soutenance.etudiant_nom + ' (' + soutenance.matricule + ')';
                    document.getElementById('detail-date-lieu').textContent = formatDate(soutenance.date_soutenance) + ' à ' + soutenance.lieu;
                    document.getElementById('detail-sujet').textContent = soutenance.intitule;
                    document.getElementById('detail-directeur').textContent = soutenance.directeur_nom || 'Non défini';

                    // Afficher les lecteurs
                    let lecteursText = '';
                    lecteurs.forEach(lecteur => {
                        lecteursText += (lecteur.est_premier_lecteur ? '1er lecteur: ' : '2e lecteur: ') +
                            lecteur.grade + ' ' + lecteur.noms + '<br>';
                    });
                    document.getElementById('detail-lecteurs').innerHTML = lecteursText;

                    // Afficher les notes
                    const notesContainer = document.getElementById('notes-container');
                    notesContainer.innerHTML = '';

                    if (notes && notes.length > 0) {
                        notes.forEach(note => {
                            const noteCard = document.createElement('div');
                            noteCard.className = 'card mb-2';
                            noteCard.innerHTML = `
                                <div class="card-header bg-light">
                                    <strong>${note.type_notation}:</strong> ${note.noms}
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        ${note.type_notation === 'Lecteur' ? `
                                            <div class="col-md-6">
                                                <p>Note fond: <strong>${note.note_fond}</strong>/20</p>
                                            </div>
                                            <div class="col-md-6">
                                                <p>Note forme: <strong>${note.note_forme}</strong>/20</p>
                                            </div>
                                        ` : `
                                            <div class="col-md-12">
                                                <p>Note soutenance: <strong>${note.note_soutenance}</strong>/20</p>
                                            </div>
                                        `}
                                    </div>
                                    ${note.commentaire ? `<p class="mb-0 mt-2">Commentaire: ${note.commentaire}</p>` : ''}
                                </div>
                            `;
                            notesContainer.appendChild(noteCard);
                        });
                    } else {
                        notesContainer.innerHTML = '<div class="alert alert-warning">Aucune note n\'a encore été attribuée.</div>';
                    }

                    // Afficher les moyennes si disponibles
                    if (moyennes) {
                        document.getElementById('moyenne-fond').textContent = moyennes.moyenne_fond ? moyennes.moyenne_fond.toFixed(2) : 'N/A';
                        document.getElementById('moyenne-forme').textContent = moyennes.moyenne_forme ? moyennes.moyenne_forme.toFixed(2) : 'N/A';
                        document.getElementById('note-soutenance').textContent = moyennes.note_soutenance ? moyennes.note_soutenance.toFixed(2) : 'N/A';
                        document.getElementById('moyenne-finale').textContent = moyennes.moyenne_finale ? moyennes.moyenne_finale.toFixed(2) : 'N/A';

                        document.getElementById('moyennes-container').style.display = 'block';
                    } else {
                        document.getElementById('moyennes-container').style.display = 'none';
                    }

                    // Afficher les infos de validation si disponibles
                    const validationContainer = document.getElementById('validation-container');
                    if (validation) {
                        let statusClass = validation.est_valide ? 'success' : 'danger';
                        let statusText = validation.est_valide ? 'validées' : 'rejetées';
                        let visibilityText = validation.est_visible ? 'visibles pour l\'étudiant' : 'non visibles pour l\'étudiant';

                        validationContainer.innerHTML = `
                            <div class="alert alert-${statusClass}">
                                <strong>Statut:</strong> Notes ${statusText} le ${formatDate(validation.date_validation)}
                                par ${validation.validateur_nom || 'Administrateur'}<br>
                                <strong>Visibilité:</strong> Notes ${visibilityText}<br>
                                ${validation.commentaire ? `<strong>Commentaire:</strong> ${validation.commentaire}` : ''}
                            </div>
                        `;
                    } else {
                        validationContainer.innerHTML = `
                            <div class="alert alert-warning">
                                Les notes n'ont pas encore été validées.
                            </div>
                        `;
                    }
                } else {
                    // Afficher une erreur
                    document.getElementById('soutenance-loading').style.display = 'none';
                    document.getElementById('soutenance-details').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            ${data.message || 'Impossible de récupérer les détails de la soutenance.'}
                        </div>
                    `;
                    document.getElementById('soutenance-details').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                document.getElementById('soutenance-loading').style.display = 'none';
                document.getElementById('soutenance-details').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Une erreur est survenue lors de la communication avec le serveur.
                    </div>
                `;
                document.getElementById('soutenance-details').style.display = 'block';
            });
    }

    // Fonction pour valider les notes d'une soutenance
    function validerNotesSoutenance(idSoutenance) {
        // Récupérer les détails pour pré-remplir le formulaire
        fetch(`controller/depot_soutenance_controller.php?action=get_soutenance_details&id=${idSoutenance}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remplir le formulaire
                    document.getElementById('validation_id_soutenance').value = idSoutenance;

                    const validation = data.data.validation;
                    if (validation) {
                        // Si déjà validé, pré-remplir avec les valeurs existantes
                        document.getElementById(validation.est_valide ? 'decision_valide' : 'decision_rejete').checked = true;
                        document.getElementById('commentaire_validation').value = validation.commentaire || '';
                        document.getElementById('est_visible').checked = validation.est_visible == 1;
                    } else {
                        // Réinitialiser le formulaire
                        document.getElementById('formValidation').reset();
                        document.getElementById('decision_valide').checked = true;
                    }

                    // Afficher la modal
                    new bootstrap.Modal(document.getElementById('validationModal')).show();
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

    // Fonction pour formater la date
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Afficher les détails d'une soutenance
    function viewMemoireDetails(idSoutenance) {
        const modalContent = document.getElementById('memoireDetailsContent');
        if (!modalContent) {
            console.error('Modal content element not found');
            return;
        }
        
        modalContent.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2">Chargement des détails...</p>
        </div>
    `;

        const modal = new bootstrap.Modal(document.getElementById('memoireDetailsModal'));
        modal.show();

        fetch('controller/get_soutenance_details.php?id=' + idSoutenance)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const soutenance = data.soutenance;
                    modalContent.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="bi bi-person me-2"></i>Étudiant</h6>
                            <p><strong>${soutenance.matricule || '-'}</strong><br>${soutenance.etudiant_nom || '-'}</p>
                            
                            <h6 class="text-primary mt-3"><i class="bi bi-book me-2"></i>Titre du Mémoire</h6>
                            <p>${soutenance.sujet_titre || '-'}</p>
                            
                            <h6 class="text-primary mt-3"><i class="bi bi-person-badge me-2"></i>Directeur</h6>
                            <p>${soutenance.directeur_nom || 'Non attribué'}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="bi bi-calendar-range me-2"></i>Date de Soutenance</h6>
                            <p>${soutenance.date_soutenance ? new Date(soutenance.date_soutenance).toLocaleDateString('fr-FR', {year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'}) : '-'}</p>
                            
                            <h6 class="text-primary mt-3"><i class="bi bi-building me-2"></i>Lieu</h6>
                            <p>${soutenance.lieu || '-'}</p>
                            
                            <h6 class="text-primary mt-3"><i class="bi bi-info-circle me-2"></i>Statut</h6>
                            <p><span class="badge bg-${soutenance.statut == 'Programmée' ? 'success' : 'secondary'}">${soutenance.statut || 'Non programmée'}</span></p>
                        </div>
                    </div>
                `;
                } else {
                    modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        ${data.message || 'Erreur lors du chargement des détails'}
                    </div>
                `;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                modalContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Erreur de connexion au serveur
                </div>
            `;
            });
    }

    // Afficher les lecteurs d'une soutenance
    function viewLecteurs(idSoutenance) {
        const modalContent = document.getElementById('lecteursContent');
        if (!modalContent) {
            console.error('Modal content element not found');
            return;
        }
        
        modalContent.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2">Chargement des lecteurs...</p>
        </div>
    `;

        const modal = new bootstrap.Modal(document.getElementById('lecteursModal'));
        modal.show();

        fetch('controller/get_soutenance_lecteurs.php?id=' + idSoutenance)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.lecteurs.length > 0) {
                    let html = '';
                    data.lecteurs.forEach(lecteur => {
                        html += `
                        <div class="card mb-2">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="bi bi-person-badge me-2"></i>
                                            ${lecteur.noms}
                                        </h6>
                                        <small class="text-muted">
                                            ${lecteur.est_premier_lecteur ? 'Premier Lecteur' : 'Deuxième Lecteur'}
                                        </small>
                                    </div>
                                    <span class="badge bg-info">${lecteur.grade || ''}</span>
                                </div>
                            </div>
                        </div>
                        `;
                    });
                    modalContent.innerHTML = html;
                } else {
                    modalContent.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Aucun lecteur assigné à cette soutenance.
                    </div>
                `;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                modalContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Erreur de connexion au serveur
                </div>
            `;
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