<?php
include "./views/include/header.php";

// Vérifier les paramètres
$idEtudiant = isset($_GET['id']) ? intval($_GET['id']) : 0;
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'etudiant.inscrit';

if ($idEtudiant <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'ID étudiant invalide',
            confirmButtonText: 'Retour'
        }).then(() => {
            window.location.href = 'etudiants/etudiant.inscrit';
        });
    </script>";
    include "./views/include/footer.php";
    exit();
}

// Récupérer les informations de l'étudiant
$universite = new Universite();
$etudiant = $universite->getStudentById($idEtudiant);

if (!$etudiant) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Étudiant non trouvé',
            confirmButtonText: 'Retour'
        }).then(() => {
            window.location.href = 'etudiants/etudiant.inscrit';
        });
    </script>";
    include "./views/include/footer.php";
    exit();
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>TÉLÉCHARGER LA PHOTO DE L'ÉTUDIANT</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item"><a href="etudiants/etudiant.inscrit">Étudiants</a></li>
                <li class="breadcrumb-item active">Télécharger Photo</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-6 mx-auto">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Photo pour <?= htmlspecialchars($etudiant['noms']) ?></h5>
                        
                        <div class="alert alert-info">
                            <p><strong>Instructions pour la photo:</strong></p>
                            <ul>
                                <li>Format JPEG ou PNG uniquement</li>
                                <li>Taille maximale de 5MB</li>
                                <li>Photo de type portrait/identité</li>
                                <li>Fond uni de préférence</li>
                                <li>Visage clairement visible</li>
                            </ul>
                        </div>
                        
                        <form action="controller/upload_student_photo.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <input type="hidden" name="idEtudiant" value="<?= $idEtudiant ?>">
                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                            
                            <div class="mb-3">
                                <label for="photoFile" class="form-label">Sélectionner une photo</label>
                                <input type="file" class="form-control" id="photoFile" name="photoFile" accept=".jpg,.jpeg,.png" required>
                                <div class="invalid-feedback">Veuillez sélectionner une photo.</div>
                            </div>
                            
                            <div class="mb-3">
                                <div id="imagePreview" class="text-center d-none">
                                    <img id="preview" src="#" alt="Aperçu de l'image" class="img-fluid img-thumbnail" style="max-height: 300px;">
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="etudiants/etudiant.inscrit" class="btn btn-secondary">Annuler</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-upload"></i> Télécharger
                                </button>
                            </div>
                        </form>
                        
                        <?php if ($redirect === 'ecard'): ?>
                        <div class="mt-3">
                            <p class="text-muted">Vous pouvez aussi <a href="controller/generate_ecard.php?id=<?= $idEtudiant ?>&skip_photo=1">continuer sans photo</a>, mais votre E-carte sera moins sécurisée.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Prévisualisation de l'image
        const photoInput = document.getElementById('photoFile');
        const imagePreview = document.getElementById('imagePreview');
        const preview = document.getElementById('preview');
        
        photoInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    imagePreview.classList.remove('d-none');
                };
                
                reader.readAsDataURL(file);
            } else {
                imagePreview.classList.add('d-none');
            }
        });
        
        // Validation du formulaire
        const form = document.querySelector('form');
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        });
    });
</script>

<?php include "./views/include/footer.php"; ?>
