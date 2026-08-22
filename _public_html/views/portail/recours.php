<?php
include_once "./views/include/header.php";
// Connexion à la base de données
$db = new Connexion();
$conn = $db->getInstance()->getPDO();

// Déterminer l'année académique en cours (la plus récente)
$query_annee_encours = "SELECT idannee_acad, designation FROM annee_acad ORDER BY dateCreation DESC LIMIT 1";
$stmt_annee = $conn->prepare($query_annee_encours);
$stmt_annee->execute();
$annee_encours = $stmt_annee->fetch(PDO::FETCH_ASSOC);
$id_annee_encours = $annee_encours['idannee_acad'];

// Récupérer les sessions
$query_sessions = "SELECT idsession, designSession, description FROM session ORDER BY idsession";
$stmt_sessions = $conn->prepare($query_sessions);
$stmt_sessions->execute();
$sessions = $stmt_sessions->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les années académiques
$query_annees = "SELECT idannee_acad, designation FROM annee_acad ORDER BY dateCreation DESC";
$stmt_annees = $conn->prepare($query_annees);
$stmt_annees->execute();
$annees = $stmt_annees->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les ECUEs
$query_ecues = "SELECT e.idECUE, e.designationECUE, u.designationUE 
                FROM ecue e 
                JOIN ue u ON e.UE_idUE = u.idUE 
                WHERE e.estVisible = 1
                ORDER BY u.designationUE, e.designationECUE";
$stmt_ecues = $conn->prepare($query_ecues);
$stmt_ecues->execute();
$ecues = $stmt_ecues->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les recours récemment encodés
$query_recours = "SELECT r.id_recours, r.matricule, e.noms as nom_etudiant,
                  ec.designationECUE, r.motif, r.date_creation, r.statut,
                  s.designSession, a.designation as annee_acad
                  FROM recours r
                  LEFT JOIN etudiant e ON r.matricule = e.matricule
                  LEFT JOIN ecue ec ON r.id_ecue = ec.idECUE
                  LEFT JOIN session s ON r.id_session = s.idsession
                  LEFT JOIN annee_acad a ON r.id_annee_acad = a.idannee_acad
                  WHERE r.id_annee_acad = :id_annee
                  ORDER BY r.date_creation DESC
                  LIMIT 10";
$stmt_recours = $conn->prepare($query_recours);
$stmt_recours->bindParam(':id_annee', $id_annee_encours);
$stmt_recours->execute();
$recours = $stmt_recours->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Encoder un Recours Physique</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Portail</li>
                <li class="breadcrumb-item active">Recours</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Formulaire d'encodage de recours</h5>
                        
                        <!-- Formulaire d'encodage -->
                        <form class="row g-3 needs-validation" action="controller/create_recours.php" method="POST" enctype="multipart/form-data" novalidate>
                            
                            <!-- Recherche étudiant -->
                            <div class="col-md-6">
                                <label for="matricule" class="form-label">Matricule de l'étudiant <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="matricule" name="matricule" required>
                                    <button class="btn btn-outline-secondary" type="button" id="rechercher-etudiant">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Veuillez saisir un matricule valide.</div>
                                <div id="info-etudiant" class="mt-2"></div>
                            </div>
                            
                            <!-- Année académique (pré-sélectionné à l'année en cours) -->
                            <div class="col-md-6">
                                <label for="annee_acad" class="form-label">Année académique <span class="text-danger">*</span></label>
                                <select class="form-select" id="annee_acad" name="annee_acad" required>
                                    <?php foreach($annees as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" 
                                        <?= ($annee['idannee_acad'] == $id_annee_encours) ? 'selected' : '' ?>>
                                            <?= $annee['designation'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                            </div>
                            
                            <!-- Session -->
                            <div class="col-md-6">
                                <label for="session" class="form-label">Session <span class="text-danger">*</span></label>
                                <select class="form-select" id="session" name="session" required>
                                    <option value="">Sélectionnez...</option>
                                    <?php foreach($sessions as $session): ?>
                                        <option value="<?= $session['idsession'] ?>">
                                            <?= $session['designSession'] ?> 
                                            <?= !empty($session['description']) ? '- '.$session['description'] : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner une session.</div>
                            </div>
                            
                            <!-- ECUE -->
                            <div class="col-md-6">
                                <label for="ecue" class="form-label">ECUE concerné <span class="text-danger">*</span></label>
                                <select class="form-select" id="ecue" name="ecue" required>
                                    <option value="">Sélectionnez...</option>
                                    <?php foreach($ecues as $ecue): ?>
                                        <option value="<?= $ecue['idECUE'] ?>">
                                            <?= $ecue['designationECUE'] ?> (<?= $ecue['designationUE'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un ECUE.</div>
                            </div>
                            
                            <!-- Type de recours -->
                            <div class="col-md-6">
                                <label for="motif" class="form-label">Motif du recours <span class="text-danger">*</span></label>
                                <select class="form-select" id="motif" name="motif" required>
                                    <option value="">Sélectionnez...</option>
                                    <option value="Omission de cote">Omission de cote</option>
                                    <option value="Calcul inexact">Calcul inexact</option>
                                    <option value="Autre">Autre</option>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un motif.</div>
                            </div>
                            
                            <!-- Date de dépôt -->
                            <div class="col-md-6">
                                <label for="date_depot" class="form-label">Date de dépôt physique <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_depot" name="date_depot" 
                                       value="<?= date('Y-m-d') ?>" required>
                                <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                            </div>
                            
                            <!-- Description détaillée -->
                            <div class="col-md-12">
                                <label for="description" class="form-label">Description détaillée</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            
                            <!-- Fichier preuve -->
                            <div class="col-md-12">
                                <label for="preuve" class="form-label">Document numérisé (PDF)</label>
                                <input class="form-control" type="file" id="preuve" name="preuve" accept=".pdf">
                                <div class="form-text">Taille maximale: 5 MB. Format accepté: PDF</div>
                            </div>
                            
                            <!-- Champ caché pour l'ID de l'utilisateur actuel -->
                            <input type="hidden" name="id_createur" value="<?= $_SESSION['id'] ?>">
                            
                            <!-- Boutons de soumission -->
                            <div class="col-12 text-center mt-4">
                                <button class="btn btn-secondary me-2" type="reset">Réinitialiser</button>
                                <button class="btn btn-primary" type="submit">
                                    <i class="bi bi-save me-1"></i> Enregistrer le recours
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Section pour afficher les recours récemment encodés -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Recours récemment encodés</h5>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Matricule</th>
                                        <th>Nom étudiant</th>
                                        <th>ECUE</th>
                                        <th>Session</th>
                                        <th>Motif</th>
                                        <th>Date de dépôt</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (empty($recours)) {
                                        echo '<tr><td colspan="8" class="text-center">Aucun recours encodé récemment</td></tr>';
                                    } else {
                                        foreach ($recours as $r) {
                                            // Définir une classe CSS pour le statut
                                            $statut_class = '';
                                            switch($r['statut']) {
                                                case 'En attente': $statut_class = 'badge bg-warning'; break;
                                                case 'En traitement': $statut_class = 'badge bg-info'; break;
                                                case 'Approuvé': $statut_class = 'badge bg-success'; break;
                                                case 'Rejeté': $statut_class = 'badge bg-danger'; break;
                                                default: $statut_class = 'badge bg-secondary';
                                            }
                                            
                                            echo '<tr>';
                                            echo '<td>' . htmlspecialchars($r['matricule']) . '</td>';
                                            echo '<td>' . htmlspecialchars($r['nom_etudiant']) . '</td>';
                                            echo '<td>' . htmlspecialchars($r['designationECUE']) . '</td>';
                                            echo '<td>' . htmlspecialchars($r['designSession']) . '</td>';
                                            echo '<td>' . htmlspecialchars($r['motif']) . '</td>';
                                            echo '<td>' . date('d/m/Y', strtotime($r['date_creation'])) . '</td>';
                                            echo '<td><span class="' . $statut_class . '">' . htmlspecialchars($r['statut']) . '</span></td>';
                                            echo '<td>
                                                <a href="portail/recours.details?id=' . $r['id_recours'] . '" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>';
                                            echo '</tr>';
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Recherche étudiant par matricule
    document.getElementById('rechercher-etudiant').addEventListener('click', rechercherEtudiant);
    document.getElementById('matricule').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            rechercherEtudiant();
        }
    });
    
    function rechercherEtudiant() {
        const matricule = document.getElementById('matricule').value;
        if (matricule.trim() !== '') {
            // Requête AJAX pour rechercher l'étudiant
            fetch('controller/ajax/get_etudiant_info.php?matricule=' + encodeURIComponent(matricule))
                .then(response => response.json())
                .then(data => {
                    const infoDiv = document.getElementById('info-etudiant');
                    if (data.error) {
                        infoDiv.innerHTML = `<div class="alert alert-danger mt-2">${data.error}</div>`;
                    } else {
                        infoDiv.innerHTML = `
                            <div class="alert alert-success mt-2">
                                Étudiant: ${data.noms} <br>
                                Promotion: ${data.promotion ? data.promotion : 'Non spécifiée'} <br>
                                Section: ${data.section ? data.section : 'Non spécifiée'}
                            </div>`;
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    document.getElementById('info-etudiant').innerHTML = 
                        '<div class="alert alert-danger mt-2">Erreur lors de la recherche de l\'étudiant</div>';
                });
        }
    }
    
    // Validation du formulaire Bootstrap
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    })();
    
    // Ajouter une validation spécifique pour le fichier PDF
    document.getElementById('preuve').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Vérifier l'extension du fichier
            const fileName = file.name;
            const fileExt = fileName.split('.').pop().toLowerCase();
            
            if (fileExt !== 'pdf') {
                alert('Seuls les fichiers PDF sont autorisés.');
                this.value = ''; // Réinitialiser le champ
                return;
            }
            
            // Vérifier la taille du fichier (5MB max)
            const fileSize = file.size / 1024 / 1024; // en MB
            if (fileSize > 5) {
                alert('La taille du fichier ne doit pas dépasser 5 MB.');
                this.value = ''; // Réinitialiser le champ
            }
        }
    });
});
</script>

<!-- Script AJAX pour la recherche d'étudiants -->
<script>
// Vous pourriez également implémenter un système d'autocomplétion pour faciliter la recherche d'étudiants
// Ou une suggestion de liste d'étudiants correspondant au début de la saisie
</script>

<?php include_once "./views/include/footer.php"; ?>

