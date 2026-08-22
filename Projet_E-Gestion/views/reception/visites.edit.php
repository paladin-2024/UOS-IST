<?php
include "./views/include/header.php";

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: reception/visites.add");
    exit();
}

$visiteId = intval($_GET['id']);
$db = Connexion::getInstance()->getPDO();
$userId = $_SESSION['id'];

// Récupérer les données de la visite
$stmtVisite = $db->prepare("
    SELECT * FROM visites 
    WHERE idVisite = ? AND cree_par = ?
");
$stmtVisite->execute([$visiteId, $userId]);
$visite = $stmtVisite->fetch(PDO::FETCH_ASSOC);

if (!$visite) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Visite non trouvée ou accès non autorisé.'
        }).then(() => {
            window.location.href = 'reception/visites.add';
        });
    </script>";
    exit();
}

// Récupérer les agents et services (même logique que dans add)
$stmtAgents = $db->prepare("
    SELECT DISTINCT a.idAgent, a.noms 
    FROM agent a 
    INNER JOIN service s ON a.idService = s.idService 
    WHERE a.idStructure IN (
        SELECT DISTINCT idStructure FROM agent WHERE idAgent = 
        (SELECT idAgent FROM t_users WHERE idUser = ?)
    )
    ORDER BY a.noms
");
$stmtAgents->execute([$userId]);
$agents = $stmtAgents->fetchAll(PDO::FETCH_ASSOC);

$stmtServices = $db->prepare("
    SELECT s.idService, s.designation 
    FROM service s 
    WHERE s.Structure_idStructure IN (
        SELECT DISTINCT idStructure FROM agent WHERE idAgent = 
        (SELECT idAgent FROM t_users WHERE idUser = ?)
    )
    ORDER BY s.designation
");
$stmtServices->execute([$userId]);
$services = $stmtServices->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Modifier la Visite</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="reception/visites.add">Visites</a></li>
                <li class="breadcrumb-item active">Modifier</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Modifier la Visite #<?= $visite['idVisite'] ?></h5>

                        <form method="POST" action="controller/updateVisite.php">
                            <input type="hidden" name="idVisite" value="<?= $visite['idVisite'] ?>">
                            
                            <!-- Informations du visiteur -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Informations du Visiteur</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="nom_visiteur" class="form-label">Nom <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="nom_visiteur" name="nom_visiteur" value="<?= htmlspecialchars($visite['nom_visiteur']) ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="prenom_visiteur" class="form-label">Prénom</label>
                                            <input type="text" class="form-control" id="prenom_visiteur" name="prenom_visiteur" value="<?= htmlspecialchars($visite['prenom_visiteur']) ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="carte_identite" class="form-label">N° Carte d'identité</label>
                                            <input type="text" class="form-control" id="carte_identite" name="carte_identite" value="<?= htmlspecialchars($visite['carte_identite']) ?>">
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="entreprise_visiteur" class="form-label">Entreprise/Organisation</label>
                                            <input type="text" class="form-control" id="entreprise_visiteur" name="entreprise_visiteur" value="<?= htmlspecialchars($visite['entreprise_visiteur']) ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="telephone_visiteur" class="form-label">Téléphone <span class="text-danger">*</span></label>
                                            <input type="tel" class="form-control" id="telephone_visiteur" name="telephone_visiteur" value="<?= htmlspecialchars($visite['telephone_visiteur']) ?>" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="email_visiteur" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email_visiteur" name="email_visiteur" value="<?= htmlspecialchars($visite['email_visiteur']) ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Détails de la visite -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Détails de la Visite</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="agent" class="form-label">Agent à visiter <span class="text-danger">*</span></label>
                                            <select class="form-select" id="agent" name="Agent_idAgent" required>
                                                <option value="">Sélectionner un agent</option>
                                                <?php foreach ($agents as $agent): ?>
                                                    <option value="<?= $agent['idAgent'] ?>" <?= $agent['idAgent'] == $visite['Agent_idAgent'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($agent['noms']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="service" class="form-label">Service <span class="text-danger">*</span></label>
                                            <select class="form-select" id="service" name="Service_idService" required>
                                                <option value="">Sélectionner un service</option>
                                                <?php foreach ($services as $service): ?>
                                                                                                        <option value="<?= $service['idService'] ?>" <?= $service['idService'] == $visite['Service_idService'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($service['designation']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="date_visite" class="form-label">Date de la visite <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="date_visite" name="date_visite" value="<?= $visite['date_visite'] ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="heure_debut" class="form-label">Heure début <span class="text-danger">*</span></label>
                                            <input type="time" class="form-control" id="heure_debut" name="heure_debut" value="<?= $visite['heure_debut'] ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="heure_fin" class="form-label">Heure fin <span class="text-danger">*</span></label>
                                            <input type="time" class="form-control" id="heure_fin" name="heure_fin" value="<?= $visite['heure_fin'] ?>" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-8">
                                            <label for="objet_visite" class="form-label">Objet de la visite <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="objet_visite" name="objet_visite" value="<?= htmlspecialchars($visite['objet_visite']) ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="type_visite" class="form-label">Type de visite</label>
                                            <select class="form-select" id="type_visite" name="type_visite">
                                                <option value="professionnelle" <?= $visite['type_visite'] == 'professionnelle' ? 'selected' : '' ?>>Professionnelle</option>
                                                <option value="personnelle" <?= $visite['type_visite'] == 'personnelle' ? 'selected' : '' ?>>Personnelle</option>
                                                <option value="officielle" <?= $visite['type_visite'] == 'officielle' ? 'selected' : '' ?>>Officielle</option>
                                                <option value="urgente" <?= $visite['type_visite'] == 'urgente' ? 'selected' : '' ?>>Urgente</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="lieu_rencontre" class="form-label">Lieu de rencontre</label>
                                            <input type="text" class="form-control" id="lieu_rencontre" name="lieu_rencontre" value="<?= htmlspecialchars($visite['lieu_rencontre']) ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="nombre_accompagnants" class="form-label">Nb accompagnants</label>
                                            <input type="number" class="form-control" id="nombre_accompagnants" name="nombre_accompagnants" value="<?= $visite['nombre_accompagnants'] ?>" min="0" max="10">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="statut_visite" class="form-label">Statut</label>
                                            <select class="form-select" id="statut_visite" name="statut_visite">
                                                <option value="programmee" <?= $visite['statut_visite'] == 'programmee' ? 'selected' : '' ?>>Programmée</option>
                                                <option value="en_cours" <?= $visite['statut_visite'] == 'en_cours' ? 'selected' : '' ?>>En cours</option>
                                                <option value="terminee" <?= $visite['statut_visite'] == 'terminee' ? 'selected' : '' ?>>Terminée</option>
                                                <option value="reportee" <?= $visite['statut_visite'] == 'reportee' ? 'selected' : '' ?>>Reportée</option>
                                                <option value="annulee" <?= $visite['statut_visite'] == 'annulee' ? 'selected' : '' ?>>Annulée</option>
                                            </select>
                                        </div>
                                    </div>

                                    <?php if ($visite['statut_visite'] == 'terminee'): ?>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="heure_arrivee_reelle" class="form-label">Heure d'arrivée réelle</label>
                                            <input type="time" class="form-control" id="heure_arrivee_reelle" name="heure_arrivee_reelle" value="<?= $visite['heure_arrivee_reelle'] ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="heure_depart_reelle" class="form-label">Heure de départ réelle</label>
                                            <input type="time" class="form-control" id="heure_depart_reelle" name="heure_depart_reelle" value="<?= $visite['heure_depart_reelle'] ?>">
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description détaillée</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($visite['description']) ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="observations" class="form-label">Observations</label>
                                        <textarea class="form-control" id="observations" name="observations" rows="2"><?= htmlspecialchars($visite['observations']) ?></textarea>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="validation_securite" name="validation_securite" value="1" <?= $visite['validation_securite'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="validation_securite">
                                                    Validation sécurité requise
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="badge_visiteur" class="form-label">N° Badge visiteur</label>
                                            <input type="text" class="form-control" id="badge_visiteur" name="badge_visiteur" value="<?= htmlspecialchars($visite['badge_visiteur']) ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="reception/visites.add" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Retour
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <span class="bi bi-save"></span> Mettre à jour
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    // Validation des heures
    document.getElementById('heure_debut').addEventListener('change', validateHours);
    document.getElementById('heure_fin').addEventListener('change', validateHours);

    function validateHours() {
        const heureDebut = document.getElementById('heure_debut').value;
        const heureFin = document.getElementById('heure_fin').value;
        
        if (heureDebut && heureFin) {
            if (heureDebut >= heureFin) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Attention',
                    text: 'L\'heure de fin doit être postérieure à l\'heure de début.'
                });
                document.getElementById('heure_fin').value = '';
            }
        }
    }
</script>

<?php include "./views/include/footer.php"; ?>
