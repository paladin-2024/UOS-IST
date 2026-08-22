<?php
include "./views/include/header.php";
$universite = new Universite();
$ecu = new ECUE();
$user = $_SESSION['user'];

// Récupérer les ECUE de l'enseignant
$ecues = $ecu->getECUEsByEnseignant($user['idAgent']);
$anneeAcadActuelle = $universite->getCurrentAcademicYear();
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Création d'une Séance</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.html">Accueil</a></li>
                <li class="breadcrumb-item">Cours</li>
                <li class="breadcrumb-item active">Nouvelle Séance</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Créer une nouvelle séance</h5>

                        <form class="row g-3" method="POST" action="../controller/create_seance.php">
                            <div class="col-md-12">
                                <label for="type_seance" class="form-label">Type de séance</label>
                                <select id="type_seance" class="form-select" name="type_seance" required>
                                    <option value="">Choisir un type...</option>
                                    <option value="Cours">Cours magistral</option>
                                    <option value="TD">Travaux dirigés</option>
                                    <option value="TP">Travaux pratiques</option>
                                    <option value="Laboratoire">Séance de laboratoire</option>
                                    <option value="Evaluation">Évaluation</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="idECUE" class="form-label">Cours (ECUE)</label>
                                <select id="idECUE" class="form-select" name="idECUE" required>
                                    <option value="">Sélectionner un cours...</option>
                                    <?php foreach($ecues as $ecue): ?>
                                        <option value="<?= $ecue['idECUE'] ?>"><?= htmlspecialchars($ecue['designationECUE']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="date_seance" class="form-label">Date de la séance</label>
                                <input type="date" class="form-control" id="date_seance" name="date_seance" required min="<?= date('Y-m-d') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="heure_debut" class="form-label">Heure de début</label>
                                <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
                            </div>

                            <div class="col-md-6">
                                <label for="heure_fin" class="form-label">Heure de fin</label>
                                <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
                            </div>

                            <div class="col-md-12">
                                <label for="local" class="form-label">Local/Salle</label>
                                <input type="text" class="form-control" id="local" name="local" required placeholder="Ex: Amphi A, Labo 2, etc.">
                            </div>

                            <div class="col-md-12">
                                <label for="description" class="form-label">Description/Sujet de la séance</label>
                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Décrivez brièvement le contenu de cette séance"></textarea>
                            </div>

                            <input type="hidden" name="annee_acad_id" value="<?= $anneeAcadActuelle['idannee_acad'] ?>">
                            <input type="hidden" name="idCreateur" value="<?= $user['idAgent'] ?>">

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">Créer la séance</button>
                                <button type="reset" class="btn btn-secondary">Réinitialiser</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include "./views/include/footer.php"; ?>
