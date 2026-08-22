<?php
include "./views/include/header.php";

// Vérifier si un ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ../reception/rendez_vous.add");
    exit();
}

$rendezVousId = intval($_GET['id']);
$userId = $_SESSION['id'];
$db = Connexion::getInstance()->getPDO();

// Récupérer les données du rendez-vous
$stmtRendezVous = $db->prepare("
    SELECT rv.* 
    FROM rendez_vous rv 
    WHERE rv.idRendez_vous = ? AND rv.cree_par = ?
");
$stmtRendezVous->execute([$rendezVousId, $userId]);
$rendezVous = $stmtRendezVous->fetch(PDO::FETCH_ASSOC);

if (!$rendezVous) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Rendez-vous non trouvé.'
        }).then(() => {
            window.location.href = '../reception/rendez_vous.add';
        });
    </script>";
    exit();
}

// Récupérer les agents
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

// Récupérer les services
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

// Récupérer les types de rendez-vous
$stmtTypes = $db->prepare("
    SELECT idType_rendez_vous, designation, duree_defaut, couleur 
    FROM type_rendez_vous 
    WHERE actif = 1 
    ORDER BY designation
");
$stmtTypes->execute();
$typesRendezVous = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Modifier le Rendez-vous</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="../reception/rendez_vous.add">Rendez-vous</a></li>
                <li class="breadcrumb-item active">Modifier</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations du Rendez-vous</h5>

                        <form method="POST" action="controller/updateRendezVous.php">
                            <input type="hidden" name="idRendez_vous" value="<?= $rendezVous['idRendez_vous'] ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="agent" class="form-label">Agent concerné <span class="text-danger">*</span></label>
                                    <select class="form-select" id="agent" name="Agent_idAgent" required>
                                        <option value="">Sélectionner un agent</option>
                                        <?php foreach ($agents as $agent): ?>
                                            <option value="<?= $agent['idAgent'] ?>" <?= $agent['idAgent'] == $rendezVous['Agent_idAgent'] ? 'selected' : '' ?>>
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
                                            <option value="<?= $service['idService'] ?>" <?= $service['idService'] == $rendezVous['Service_idService'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($service['designation']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="date_rendez_vous" class="form-label">Date du rendez-vous <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_rendez_vous" name="date_rendez_vous" 
                                           value="<?= $rendezVous['date_rendez_vous'] ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="heure_debut" class="form-label">Heure début <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="heure_debut" name="heure_debut" 
                                           value="<?= $rendezVous['heure_debut'] ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="heure_fin" class="form-label">Heure fin <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="heure_fin" name="heure_fin" 
                                           value="<?= $rendezVous['heure_fin'] ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label for="objet" class="form-label">Objet du rendez-vous <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="objet" name="objet" 
                                           value="<?= htmlspecialchars($rendezVous['objet']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="type_rendez_vous" class="form-label">Type de rendez-vous</label>
                                    <select class="form-select" id="type_rendez_vous" name="type_rendez_vous">
                                        <option value="">Sélectionner un type</option>
                                        <?php foreach ($typesRendezVous as $type): ?>
                                            <option value="<?= htmlspecialchars($type['designation']) ?>" 
                                                    <?= $type['designation'] == $rendezVous['type_rendez_vous'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($type['designation']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="contact_externe" class="form-label">Contact externe</label>
                                    <input type="text" class="form-control" id="contact_externe" name="contact_externe" 
                                           value="<?= htmlspecialchars($rendezVous['contact_externe']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="telephone_externe" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="telephone_externe" name="telephone_externe" 
                                           value="<?= htmlspecialchars($rendezVous['telephone_externe']) ?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="email_externe" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email_externe" name="email_externe" 
                                           value="<?= htmlspecialchars($rendezVous['email_externe']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="lieu" class="form-label">Lieu</label>
                                    <input type="text" class="form-control" id="lieu" name="lieu" 
                                           value="<?= htmlspecialchars($rendezVous['lieu']) ?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="priorite" class="form-label">Priorité</label>
                                    <select class="form-select" id="priorite" name="priorite">
                                        <option value="basse" <?= $rendezVous['priorite'] == 'basse' ? 'selected' : '' ?>>Basse</option>
                                        <option value="normale" <?= $rendezVous['priorite'] == 'normale' ? 'selected' : '' ?>>Normale</option>
                                        <option value="haute" <?= $rendezVous['priorite'] == 'haute' ? 'selected' : '' ?>>Haute</option>
                                        <option value="urgente" <?= $rendezVous['priorite'] == 'urgente' ? 'selected' : '' ?>>Urgente</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="statut_rendez_vous" class="form-label">Statut</label>
                                    <select class="form-select" id="statut_rendez_vous" name="statut_rendez_vous">
                                        <option value="planifie" <?= $rendezVous['statut_rendez_vous'] == 'planifie' ? 'selected' : '' ?>>Planifié</option>
                                        <option value="confirme" <?= $rendezVous['statut_rendez_vous'] == 'confirme' ? 'selected' : '' ?>>Confirmé</option>
                                                                                <option value="reporte" <?= $rendezVous['statut_rendez_vous'] == 'reporte' ? 'selected' : '' ?>>Reporté</option>
                                        <option value="annule" <?= $rendezVous['statut_rendez_vous'] == 'annule' ? 'selected' : '' ?>>Annulé</option>
                                        <option value="termine" <?= $rendezVous['statut_rendez_vous'] == 'termine' ? 'selected' : '' ?>>Terminé</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($rendezVous['description']) ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="commentaires" class="form-label">Commentaires</label>
                                <textarea class="form-control" id="commentaires" name="commentaires" rows="2"><?= htmlspecialchars($rendezVous['commentaires']) ?></textarea>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="rappel_active" name="rappel_active" value="1" 
                                               <?= $rendezVous['rappel_active'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="rappel_active">
                                            Activer les rappels
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="delai_rappel" class="form-label">Délai de rappel (minutes)</label>
                                    <input type="number" class="form-control" id="delai_rappel" name="delai_rappel" 
                                           value="<?= $rendezVous['delai_rappel'] ?>" min="5" max="1440">
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="../reception/rendez_vous.add" class="btn btn-secondary">
                                    <span class="bi bi-arrow-left"></span> Retour
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <span class="bi bi-save"></span> Mettre à jour
                                </button>
                            </div>
                        </form>

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

                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include "./views/include/footer.php"; ?>
