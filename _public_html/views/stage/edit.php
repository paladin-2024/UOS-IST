<?php
include "./views/include/header.php";

$stage = new Stage();
$stageId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Vérifier les droits
if ($_SESSION['idRole'] != 1) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas les droits pour modifier un stage.'
        }).then(() => {
            window.location.href = '?view=stage';
        });
    </script>";
    exit;
}

// Récupérer les détails du stage
$db = Connexion::getInstance()->getPDO();
$query = "SELECT s.*, e.noms as nom_etudiant, e.matricule
          FROM stage_assignments s
          JOIN etudiant e ON s.idetudiant = e.idetudiant
          WHERE s.idstage = :id";
$stmt = $db->prepare($query);
$stmt->execute(['id' => $stageId]);
$stageData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$stageData) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Stage introuvable'
        }).then(() => {
            window.location.href = '?view=stage';
        });
    </script>";
    exit;
}

// Récupérer la liste des encadreurs
$agentModel = new Agent();
$encadreurs = $agentModel->getAgentsByType('Enseignant');
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Modifier un Stage</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item"><a href="stage">Stages</a></li>
                <li class="breadcrumb-item active">Modifier</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                <div class="card-header">
                <h5 class="card-title mb-0">
                <i class="bi bi-pencil me-2"></i>Modifier le Stage
                </h5>
                </div>
                    <div class="card-body mt-3">
                        <form id="editStageForm" method="POST" action="controller/update_stage.php">
                            <input type="hidden" name="stage_id" value="<?= $stageId ?>">
                            
                            <!-- Informations étudiant -->
                            <div class="mb-4">
                                <h6 class="text-primary border-bottom pb-2">
                                    <i class="bi bi-person me-2"></i>Étudiant
                                </h6>
                                <p class="mb-0">
                                    <strong><?= htmlspecialchars($stageData['matricule']) ?></strong> - 
                                    <?= htmlspecialchars($stageData['nom_etudiant']) ?>
                                </p>
                            </div>
                            
                            <!-- Lieu de stage -->
                            <div class="mb-3">
                                <label for="lieu_stage" class="form-label required">
                                    <i class="bi bi-building me-1"></i>Lieu de Stage
                                </label>
                                <input type="text" class="form-control" id="lieu_stage" name="lieu_stage" 
                                       value="<?= htmlspecialchars($stageData['lieu_stage'] ?? '') ?>" required>
                            </div>
                            
                            <!-- Encadreur -->
                            <div class="mb-3">
                                <label for="idencadreur" class="form-label">
                                    <i class="bi bi-person-badge me-1"></i>Encadreur
                                </label>
                                <select class="form-select" id="idencadreur" name="idencadreur">
                                    <option value="">Aucun encadreur</option>
                                    <?php foreach ($encadreurs as $enc): ?>
                                        <option value="<?= $enc['idAgent'] ?>" 
                                                <?= ($stageData['idencadreur'] == $enc['idAgent']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(($enc['gradeDesignation'] ? $enc['gradeDesignation'] . ' ' : '') . $enc['noms']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Période -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="date_debut" class="form-label required">
                                        <i class="bi bi-calendar-check me-1"></i>Date de début
                                    </label>
                                    <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                           value="<?= $stageData['date_debut'] ?? '' ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="date_fin" class="form-label required">
                                        <i class="bi bi-calendar-x me-1"></i>Date de fin
                                    </label>
                                    <input type="date" class="form-control" id="date_fin" name="date_fin" 
                                           value="<?= $stageData['date_fin'] ?? '' ?>" required>
                                </div>
                            </div>
                            
                            <!-- Cote entreprise -->
                            <div class="mb-3">
                                <label for="cote_entreprise" class="form-label">
                                    <i class="bi bi-star me-1"></i>Cote de l'entreprise (sur 20)
                                </label>
                                <input type="number" class="form-control" id="cote_entreprise" name="cote_entreprise" 
                                       value="<?= $stageData['cote_entreprise'] ?? '' ?>" 
                                       min="0" max="20" step="0.01">
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="?view=stage" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-1"></i>Retour
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-1"></i>Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Informations supplémentaires -->
            <div class="col-lg-4">
                <div class="card">
                <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informations</h6>
                </div>
                    <div class="card-body">
                        <p><strong>Rapport:</strong><br>
                            <?php if (!empty($stageData['rapport_path'])): ?>
                                <span class="badge bg-success">Déposé</span><br>
                                <a href="<?= $stageData['rapport_path'] ?>" target="_blank" class="btn btn-sm btn-primary mt-2">
                                    <i class="bi bi-download me-1"></i>Télécharger
                                </a>
                            <?php else: ?>
                                <span class="badge bg-warning">En attente</span>
                            <?php endif; ?>
                        </p>
                        
                        <?php if (!empty($stageData['idlecteur'])): ?>
                        <hr>
                        <p><strong>Lecteur:</strong><br>
                            <?php 
                            $lectQuery = 'SELECT noms FROM agent WHERE "idAgent" = :id';
                            $lectStmt = $db->prepare($lectQuery);
                            $lectStmt->execute(['id' => $stageData['idlecteur']]);
                            $lecteur = $lectStmt->fetch();
                            echo htmlspecialchars($lecteur['noms'] ?? 'Non trouvé');
                            ?>
                        </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($stageData['cote_lecteur'])): ?>
                        <p><strong>Cote lecteur:</strong><br>
                            <span class="badge bg-success fs-6"><?= $stageData['cote_lecteur'] ?>/20</span>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
document.getElementById('editStageForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validation des dates
    const dateDebut = new Date(document.getElementById('date_debut').value);
    const dateFin = new Date(document.getElementById('date_fin').value);
    
    if (dateFin <= dateDebut) {
        Swal.fire({
            icon: 'error',
            title: 'Erreur de validation',
            text: 'La date de fin doit être postérieure à la date de début.',
            confirmButtonColor: '#d33'
        });
        return;
    }
    
    const formData = new FormData(this);
    
    Swal.fire({
        title: 'Enregistrement...',
        text: 'Mise à jour du stage en cours',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('controller/update_stage.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Stage mis à jour!',
                text: data.message || 'Le stage a été modifié avec succès.',
                confirmButtonColor: '#4CAF50'
            }).then(() => {
                window.location.href = '?view=stage';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: data.message || 'Une erreur est survenue lors de la mise à jour.',
                confirmButtonColor: '#d33'
            });
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur de connexion',
            text: 'Impossible de communiquer avec le serveur.',
            confirmButtonColor: '#d33'
        });
    });
});
</script>

<?php include "./views/include/footer_file.php"; ?>
