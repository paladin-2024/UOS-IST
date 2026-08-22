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

// Formater la date limite pour l'input datetime-local
$dateLimite = date('Y-m-d\TH:i', strtotime($devoir['date_limite']));
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>MODIFICATION DU DEVOIR</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="?view=enseignement/cours">Cours</a></li>
                <li class="breadcrumb-item"><a href="?view=enseignement/cours.details&id=<?= $devoir['idECUE'] ?>">Détails du cours</a></li>
                <li class="breadcrumb-item"><a href="?view=enseignement/devoir.details&id=<?= $idDevoir ?>">Détails du devoir</a></li>
                <li class="breadcrumb-item active">Modifier</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Modifier les informations du devoir</h5>
                        
                        <form method="POST" action="controller/devoir_controller.php" class="needs-validation" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="action" value="update_assignment">
                            <input type="hidden" name="idDevoir" value="<?= $idDevoir ?>">
                            <input type="hidden" name="idECUE" value="<?= $devoir['idECUE'] ?>">
                            
                            <div class="row mb-3">
                                <label for="titre" class="col-sm-2 col-form-label">Titre</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="titre" id="titre" value="<?= htmlspecialchars($devoir['titre']) ?>" required>
                                    <div class="invalid-feedback">Veuillez entrer un titre pour le devoir.</div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label for="description" class="col-sm-2 col-form-label">Description</label>
                                <div class="col-sm-10">
                                    <textarea class="form-control" name="description" id="description" rows="5" required><?= htmlspecialchars($devoir['description']) ?></textarea>
                                    <div class="invalid-feedback">Veuillez fournir une description pour le devoir.</div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label for="date_limite" class="col-sm-2 col-form-label">Date limite</label>
                                <div class="col-sm-10">
                                    <input type="datetime-local" class="form-control" name="date_limite" id="date_limite" value="<?= $dateLimite ?>" required>
                                    <div class="invalid-feedback">Veuillez définir une date limite de remise.</div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label">Fichier actuel</label>
                                <div class="col-sm-10">
                                    <div class="input-group">
                                        <span class="form-control"><?= $devoir['fichier'] ?></span>
                                        <a href="uploads/devoirs/<?= $devoir['fichier'] ?>" class="btn btn-outline-primary" target="_blank">
                                            <i class="bi bi-download"></i> Télécharger
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label for="fichier" class="col-sm-2 col-form-label">Nouveau fichier</label>
                                <div class="col-sm-10">
                                    <input type="file" class="form-control" name="fichier" id="fichier">
                                    <div class="form-text text-muted">Laissez vide pour conserver le fichier actuel.</div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-2">Accès</div>
                                <div class="col-sm-10">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="est_payant" name="est_payant" <?= $devoir['est_payant'] ? 'checked' : '' ?> onchange="toggleFrais()">
                                        <label class="form-check-label" for="est_payant">
                                            Accès payant
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3" id="frais_container" style="display: <?= $devoir['est_payant'] ? 'block' : 'none' ?>;">
                                <label for="idFrais" class="col-sm-2 col-form-label">Frais requis</label>
                                <div class="col-sm-10">
                                    <select class="form-select" name="idFrais" id="idFrais" <?= $devoir['est_payant'] ? 'required' : '' ?>>
                                        <option value="">Sélectionnez un frais</option>
                                        <?php 
                                        $frais = $universite->getFraisByAcademicYear($currentYear['idannee_acad']);
                                        foreach ($frais as $f): 
                                            $selected = ($devoir['idfrais'] == $f['idfrais']) ? 'selected' : '';
                                        ?>
                                            <option value="<?= $f['idfrais'] ?>" <?= $selected ?>><?= htmlspecialchars($f['designation']) ?> - <?= $f['montant'] ?> <?= $f['devise'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Veuillez sélectionner un frais pour l'accès payant.</div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-10 offset-sm-2">
                                    <a href="?view=enseignement/devoir.details&id=<?= $idDevoir ?>" class="btn btn-secondary">
                                        <i class="bi bi-x-circle"></i> Annuler
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Enregistrer les modifications
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
// Fonction pour afficher/masquer le champ de frais
function toggleFrais() {
    const estPayant = document.getElementById('est_payant').checked;
    const fraisContainer = document.getElementById('frais_container');
    const fraisSelect = document.getElementById('idFrais');
    
    if (estPayant) {
        fraisContainer.style.display = 'block';
        fraisSelect.setAttribute('required', 'required');
    } else {
        fraisContainer.style.display = 'none';
        fraisSelect.removeAttribute('required');
    }
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
</script>

<?php include "./views/include/footer.php"; ?>
