<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();
$universite = new Universite();

// Vérifier si l'utilisateur est administrateur
if (!isset($_SESSION['idRole']) || $_SESSION['idRole'] != 1) {
    // Rediriger vers la liste des laboratoires si l'utilisateur n'est pas administrateur
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas les droits nécessaires pour accéder à cette page.'
        }).then(() => {
            window.location.href = 'laboratoire/laboratoire.list';
        });
    </script>";
    exit();
}

// Récupérer l'ID du laboratoire à modifier
$idLabo = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$idLabo) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Identifiant de laboratoire non valide.'
        }).then(() => {
            window.location.href = 'laboratoire/laboratoire.list';
        });
    </script>";
    exit();
}

// Récupérer les informations du laboratoire
$query = "SELECT * FROM laboratoire WHERE idlabo = :idLabo";
$stmt = $db->prepare($query);
$stmt->bindParam(':idLabo', $idLabo);
$stmt->execute();
$labo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$labo) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Laboratoire non trouvé.'
        }).then(() => {
            window.location.href = 'laboratoire/laboratoire.list';
        });
    </script>";
    exit();
}

// Récupérer la liste des agents pour le responsable
$query = "SELECT idAgent, noms FROM agent WHERE type_agent = 'Enseignant' OR type_agent = 'Recherche' ORDER BY noms";
$stmt = $db->prepare($query);
$stmt->execute();
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer l'année académique en cours
$anneeEnCours = $universite->getCurrentAcademicYear();
$anneeId = $anneeEnCours['idannee_acad'];
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Modifier un laboratoire</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Recherche</li>
                <li class="breadcrumb-item"><a href="laboratoire/laboratoire.list">Laboratoires</a></li>
                <li class="breadcrumb-item active">Modifier</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations du laboratoire</h5>

                        <form action="controller/update_laboratoire.php" method="post" class="needs-validation" novalidate>
                            <input type="hidden" name="idlabo" value="<?= $labo['idlabo'] ?>">
                            <input type="hidden" name="annee_acad_id" value="<?= $anneeId ?>">

                            <div class="row mb-3">
                                <label for="nom" class="col-sm-2 col-form-label">Nom du laboratoire</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" id="nom" name="nom" value="<?= htmlspecialchars($labo['nom']) ?>" required>
                                    <div class="invalid-feedback">Veuillez entrer le nom du laboratoire.</div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="description" class="col-sm-2 col-form-label">Description</label>
                                <div class="col-sm-10">
                                    <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($labo['description']) ?></textarea>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="localisation" class="col-sm-2 col-form-label">Localisation</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" id="localisation" name="localisation" value="<?= htmlspecialchars($labo['localisation']) ?>" required>
                                    <div class="invalid-feedback">Veuillez entrer la localisation du laboratoire.</div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="responsable_id" class="col-sm-2 col-form-label">Responsable</label>
                                <div class="col-sm-10">
                                    <select class="form-select" id="responsable_id" name="responsable_id" required>
                                        <option value="">Sélectionner un responsable</option>
                                        <?php foreach ($agents as $agent): ?>
                                            <option value="<?= $agent['idAgent'] ?>" <?= ($agent['idAgent'] == $labo['responsable_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($agent['noms']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Veuillez sélectionner un responsable.</div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label">Coordonnées géographiques</label>
                                <div class="col-sm-5">
                                    <div class="input-group">
                                        <span class="input-group-text">Latitude</span>
                                        <input type="text" class="form-control" id="ref_latitude" name="ref_latitude" value="<?= $labo['ref_latitude'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="col-sm-5">
                                    <div class="input-group">
                                        <span class="input-group-text">Longitude</span>
                                        <input type="text" class="form-control" id="ref_longitude" name="ref_longitude" value="<?= $labo['ref_longitude'] ?? '' ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-10 offset-sm-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="geo_verification_active" name="geo_verification_active" <?= ($labo['geo_verification_active'] ?? true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="geo_verification_active">
                                            Activer la vérification géographique pour les présences
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-10 offset-sm-2">
                                    <button type="submit" class="btn btn-primary">Mettre à jour</button>
                                    <a href="laboratoire/laboratoire.list" class="btn btn-secondary">Annuler</a>
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
    // Script pour la validation du formulaire
    (function() {
        'use strict';
        
        // Fetch all forms we want to apply custom validation styles to
        var forms = document.querySelectorAll('.needs-validation');
        
        // Loop over them and prevent submission
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

    // Fonction pour obtenir la position actuelle (pour les coordonnées géographiques)
    document.getElementById('getLocation').addEventListener('click', function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                document.getElementById('ref_latitude').value = position.coords.latitude;
                document.getElementById('ref_longitude').value = position.coords.longitude;
            }, function(error) {
                console.error("Erreur de géolocalisation: ", error);
                alert("Impossible d'obtenir votre position. Veuillez vérifier vos paramètres de localisation.");
            });
        } else {
            alert("La géolocalisation n'est pas prise en charge par ce navigateur.");
        }
    });
</script>

<?php include "./views/include/footer.php"; ?>
