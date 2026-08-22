<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();
$universite = new Universite();

// Récupérer l'ID de la séance
$idSeance = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$idSeance) {
    echo "<script>window.location.href='laboratoire/laboratoire.list';</script>";
    exit;
}

// Récupérer l'année académique en cours
$anneeEnCours = $universite->getCurrentAcademicYear();
$anneeId = $anneeEnCours['idannee_acad'];

// Récupérer les informations de la séance
$querySeance = "SELECT sl.*, l.nom as nom_labo, l.idlabo, l.geo_verification_active, 
                l.ref_latitude, l.ref_longitude 
                FROM seance_labo sl
                JOIN laboratoire l ON sl.idlabo = l.idlabo
                WHERE sl.idseance_labo = :idSeance";
$stmtSeance = $db->prepare($querySeance);
$stmtSeance->bindParam(':idSeance', $idSeance);
$stmtSeance->execute();
$seance = $stmtSeance->fetch(PDO::FETCH_ASSOC);

if (!$seance) {
    echo "<script>window.location.href='laboratoire/laboratoire.list';</script>";
    exit;
}

// Formater les dates et heures pour l'affichage dans le formulaire
$dateSeance = new DateTime($seance['date_seance']);
$heureDebut = new DateTime($seance['heure_debut']);
$heureFin = new DateTime($seance['heure_fin']);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Modifier une séance de laboratoire</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Recherche</li>
                <li class="breadcrumb-item"><a href="laboratoire/laboratoire.list">Laboratoires</a></li>
                <li class="breadcrumb-item"><a href="laboratoire/seance.list&id=<?= $seance['idlabo'] ?>">Séances</a></li>
                <li class="breadcrumb-item active">Modifier</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Modifier la séance du <?= $dateSeance->format('d/m/Y') ?></h5>

                        <form action="controller/update_seance_labo.php" method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="idSeance" value="<?= $idSeance ?>">
                            <input type="hidden" name="idLabo" value="<?= $seance['idlabo'] ?>">

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="titre" class="form-label">Titre de la séance</label>
                                    <input type="text" class="form-control" id="titre" name="titre" value="<?= htmlspecialchars($seance['titre']) ?>" required>
                                    <div class="invalid-feedback">Veuillez entrer un titre pour la séance.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="date_seance" class="form-label">Date de la séance</label>
                                    <input type="date" class="form-control" id="date_seance" name="date_seance" value="<?= $dateSeance->format('Y-m-d') ?>" required>
                                    <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="heure_debut" class="form-label">Heure de début</label>
                                    <input type="time" class="form-control" id="heure_debut" name="heure_debut" value="<?= $heureDebut->format('H:i') ?>" required>
                                    <div class="invalid-feedback">Veuillez sélectionner une heure de début.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="heure_fin" class="form-label">Heure de fin</label>
                                    <input type="time" class="form-control" id="heure_fin" name="heure_fin" value="<?= $heureFin->format('H:i') ?>" required>
                                    <div class="invalid-feedback">Veuillez sélectionner une heure de fin.</div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($seance['description']) ?></textarea>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="geo_verification_active" name="geo_verification_active" <?= $seance['geo_verification_active'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="geo_verification_active">Activer la vérification géographique</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3" id="coordsSection" <?= !$seance['geo_verification_active'] ? 'style="display:none;"' : '' ?>>
                                <div class="col-md-6">
                                    <label for="ref_latitude" class="form-label">Latitude de référence</label>
                                    <input type="text" class="form-control" id="ref_latitude" name="ref_latitude" value="<?= $seance['ref_latitude'] ?>">
                                    <small class="text-muted">Laissez vide pour utiliser les coordonnées du laboratoire</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="ref_longitude" class="form-label">Longitude de référence</label>
                                    <input type="text" class="form-control" id="ref_longitude" name="ref_longitude" value="<?= $seance['ref_longitude'] ?>">
                                    <small class="text-muted">Laissez vide pour utiliser les coordonnées du laboratoire</small>
                                </div>
                                <div class="col-md-12 mt-3">
                                    <button type="button" id="getLocation" class="btn btn-secondary btn-sm">
                                        <i class="bi bi-geo-alt"></i> Utiliser ma position actuelle
                                    </button>
                                    <small class="text-muted ms-2">Cliquez pour remplir automatiquement les coordonnées</small>
                                </div>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Enregistrer les modifications
                                </button>
                                <a href="laboratoire/seance.list&id=<?= $seance['idlabo'] ?>" class="btn btn-secondary">Annuler</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    // Validation du formulaire
    (function() {
        'use strict';
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
    })();

    // Afficher/masquer la section des coordonnées géographiques
    document.getElementById('geo_verification_active').addEventListener('change', function() {
        const coordsSection = document.getElementById('coordsSection');
        if (this.checked) {
            coordsSection.style.display = 'flex';
        } else {
            coordsSection.style.display = 'none';
        }
    });

    // Récupérer la position actuelle
    document.getElementById('getLocation').addEventListener('click', function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    document.getElementById('ref_latitude').value = position.coords.latitude;
                    document.getElementById('ref_longitude').value = position.coords.longitude;
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Position récupérée',
                        text: 'Les coordonnées ont été mises à jour avec votre position actuelle.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                },
                function(error) {
                    let message = "Impossible de récupérer votre position.";
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            message = "Vous avez refusé l'accès à votre position.";
                            break;
                        case error.POSITION_UNAVAILABLE:
                            message = "Les informations de localisation ne sont pas disponibles.";
                            break;
                        case error.TIMEOUT:
                            message = "La demande de localisation a expiré.";
                            break;
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur de géolocalisation',
                        text: message
                    });
                }
            );
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Géolocalisation non supportée',
                text: 'Votre navigateur ne prend pas en charge la géolocalisation.'
            });
        }
    });
</script>

<?php include "./views/include/footer.php"; ?>
