<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();
$universite = new Universite();
$agentModel = new Agent();

// Récupérer l'année académique en cours
$anneeEnCours = $universite->getCurrentAcademicYear();
$anneeId = $anneeEnCours['idannee_acad'];

// Récupérer la liste des agents pour le choix du responsable
$query = "SELECT a.\"idAgent\", a.noms 
          FROM agent a 
          WHERE a.type_agent IN ('Enseignant', 'Recherche')
          ORDER BY a.noms";
$stmt = $db->prepare($query);
$stmt->execute();
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Ajouter un laboratoire</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Recherche</li>
                <li class="breadcrumb-item"><a href="laboratoire/laboratoire.list">Laboratoires</a></li>
                <li class="breadcrumb-item active">Ajouter</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations du laboratoire</h5>

                        <form method="POST" action="controller/create_laboratoire.php" class="row g-3">
                            <div class="col-md-6">
                                <label for="nom" class="form-label">Nom du laboratoire</label>
                                <input type="text" class="form-control" id="nom" name="nom" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="localisation" class="form-label">Localisation</label>
                                <input type="text" class="form-control" id="localisation" name="localisation" required>
                            </div>

                            <div class="col-md-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                            </div>

                            <div class="col-md-12">
                                <label for="responsable_id" class="form-label">Responsable</label>
                                <select class="form-select" id="responsable_id" name="responsable_id" required>
                                    <option value="">Sélectionner un responsable</option>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?= $agent['idAgent'] ?>"><?= htmlspecialchars($agent['noms']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <input type="hidden" name="annee_acad_id" value="<?= $anneeId ?>">
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Enregistrer
                                </button>
                                <a href="laboratoire/laboratoire.list" class="btn btn-secondary">Annuler</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include "./views/include/footer.php"; ?>
