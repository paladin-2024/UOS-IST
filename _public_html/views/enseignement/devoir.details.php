<?php
include "./views/include/header.php";
$universite = new Universite();
$ecue = new Ecue();

// Récupérer l'ID du devoir
$idDevoir = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($idDevoir <= 0) {
    echo "<script>window.location.href = '?view=enseignement/cours';</script>";
    exit;
}

// Récupérer l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();

// Récupérer les détails du devoir
$devoir = $ecue->getAssignmentById($idDevoir);
if (!$devoir) {
    echo "<script>window.location.href = '?view=enseignement/cours';</script>";
    exit;
}

// Récupérer les détails de l'ECUE associé
$ecueDetails = $ecue->getEcueById($devoir['idECUE']);

// Récupérer les réponses des étudiants
$reponses = $ecue->getAssignmentResponses($idDevoir);

// Calculer le statut du devoir
$today = new DateTime();
$deadline = new DateTime($devoir['date_limite']);
$isExpired = $today > $deadline;
$statusClass = $isExpired ? 'danger' : 'success';
$statusText = $isExpired ? 'Expiré' : 'En cours';
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>DÉTAILS DU DEVOIR</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="?view=enseignement/cours">Cours</a></li>
                <li class="breadcrumb-item"><a href="?view=enseignement/cours.details&id=<?= $devoir['idECUE'] ?>">Détails du cours</a></li>
                <li class="breadcrumb-item active">Devoir</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <!-- Informations du devoir -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations du devoir</h5>
                        <div class="devoir-info">
                            <h4><?= htmlspecialchars($devoir['titre']) ?></h4>
                            <div class="mb-3">
                                <span class="badge bg-<?= $statusClass ?> mb-2"><?= $statusText ?></span>
                                <?php if ($devoir['est_payant']): ?>
                                    <span class="badge bg-warning">Accès payant</span>
                                <?php else: ?>
                                    <span class="badge bg-info">Accès libre</span>
                                <?php endif; ?>
                            </div>
                            <p><strong>Cours:</strong> <?= htmlspecialchars($ecueDetails['designationECUE']) ?></p>
                            <p><strong>Date limite:</strong> <?= date('d/m/Y H:i', strtotime($devoir['date_limite'])) ?></p>
                            <p><strong>Description:</strong></p>
                            <div class="devoir-description p-3 bg-light rounded">
                                <?= nl2br(htmlspecialchars($devoir['description'])) ?>
                            </div>
                            
                            <div class="mt-3">
                                <a href="uploads/devoirs/<?= $devoir['fichier'] ?>" class="btn btn-primary" target="_blank">
                                    <i class="bi bi-download"></i> Télécharger le sujet
                                </a>
                                <a href="?view=enseignement/devoir.edit&id=<?= $idDevoir ?>" class="btn btn-warning">
                                    <i class="bi bi-pencil"></i> Modifier
                                </a>
                                <button type="button" class="btn btn-danger" onclick="confirmDeleteAssignment(<?= $idDevoir ?>, <?= $devoir['idECUE'] ?>)">
                                    <i class="bi bi-trash"></i> Supprimer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistiques -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="card-title">Statistiques</h5>
                        
                        <?php 
                        $totalReponses = count($reponses);
                        $notesCount = 0;
                        $totalNotes = 0;
                        
                        foreach ($reponses as $reponse) {
                            if ($reponse['note'] !== null) {
                                $notesCount++;
                                $totalNotes += $reponse['note'];
                            }
                        }
                        
                        $averageNote = $notesCount > 0 ? round($totalNotes / $notesCount, 2) : 0;
                        ?>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span><i class="bi bi-people"></i> Réponses soumises:</span>
                            <span class="badge bg-primary"><?= $totalReponses ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span><i class="bi bi-check-circle"></i> Réponses notées:</span>
                            <span class="badge bg-success"><?= $notesCount ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-calculator"></i> Note moyenne:</span>
                            <span class="badge bg-info"><?= $averageNote ?> / 20</span>
                        </div>
                        <!-- Ajouter le bouton d'export ici -->
                        <div class="mt-3">
                            <form action="controller/export_notes.php" method="POST">
                                <input type="hidden" name="idDevoir" value="<?= $idDevoir ?>">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-file-excel"></i> Exporter les notes en Excel
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Liste des réponses -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Réponses des étudiants</h5>
                        
                        <?php if (empty($reponses)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Aucune réponse n'a encore été soumise pour ce devoir.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Étudiant</th>
                                            <th>Matricule</th>
                                            <th>Date de soumission</th>
                                            <th>Note</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reponses as $reponse): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($reponse['noms']) ?></td>
                                                <td><?= htmlspecialchars($reponse['matricule']) ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($reponse['date_soumission'])) ?></td>
                                                <td>
                                                    <?php if ($reponse['note'] !== null): ?>
                                                        <span class="badge bg-success"><?= $reponse['note'] ?> / 20</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Non noté</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="uploads/reponses/<?= $reponse['fichier'] ?>" class="btn btn-sm btn-info" target="_blank">
                                                        <i class="bi bi-download"></i> Télécharger
                                                    </a>
                                                    <button class="btn btn-sm btn-primary" onclick="openGradeModal(<?= $reponse['idreponse'] ?>, '<?= addslashes(htmlspecialchars($reponse['noms'])) ?>', <?= $reponse['note'] !== null ? $reponse['note'] : 'null' ?>, '<?= addslashes(htmlspecialchars($reponse['feedback_enseignant'] ?? '')) ?>')">
                                                        <i class="bi bi-pencil-square"></i> Noter
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="confirmDeleteResponse(<?= $reponse['idreponse'] ?>, <?= $idDevoir ?>)">
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
        </div>
    </section>
</main>

<!-- Modal pour noter une réponse -->
<div class="modal fade" id="gradeResponseModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Noter la réponse</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="gradeForm" method="POST" action="controller/devoir_controller.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="grade_assignment">
                    <input type="hidden" name="idReponse" id="grade_idReponse">
                    <input type="hidden" name="idDevoir" value="<?= $idDevoir ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Étudiant</label>
                        <p id="grade_student_name" class="form-control-static"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="note" class="form-label">Note (sur 20)</label>
                        <input type="number" name="note" id="note" class="form-control" min="0" max="20" step="0.1" required>
                        <div class="invalid-feedback">Veuillez entrer une note valide entre 0 et 20.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="feedback" class="form-label">Commentaire / Feedback</label>
                        <textarea name="feedback" id="feedback" class="form-control" rows="4"></textarea>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Fonction pour ouvrir le modal de notation
function openGradeModal(idReponse, nomEtudiant, note, feedback) {
    document.getElementById('grade_idReponse').value = idReponse;
    document.getElementById('grade_student_name').textContent = nomEtudiant;
    document.getElementById('note').value = note !== null ? note : '';
    document.getElementById('feedback').value = feedback || '';
    
    new bootstrap.Modal(document.getElementById('gradeResponseModal')).show();
}

// Fonction pour confirmer la suppression d'une réponse
function confirmDeleteResponse(idReponse, idDevoir) {
    Swal.fire({
        title: 'Confirmer la suppression',
        text: "Êtes-vous sûr de vouloir supprimer cette réponse ? Cette action est irréversible.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `controller/devoir_controller.php?action=delete_response&response_id=${idReponse}&id=${idDevoir}`;
        }
    });
}

// Fonction pour confirmer la suppression d'un devoir
function confirmDeleteAssignment(idDevoir, idECUE) {
    Swal.fire({
        title: 'Confirmer la suppression',
        text: "Êtes-vous sûr de vouloir supprimer ce devoir ? Toutes les réponses des étudiants seront également supprimées. Cette action est irréversible.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `controller/devoir_controller.php?action=delete_assignment&id=${idDevoir}&ecue_id=${idECUE}`;
        }
    });
}

// Initialisation des validations de formulaire Bootstrap
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

// Ajouter un écouteur d'événements pour le chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les tooltips Bootstrap si nécessaire
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
    
    // Vous pouvez ajouter d'autres initialisations ici si nécessaire
});
</script>

<?php include "./views/include/footer_file.php"; ?>
