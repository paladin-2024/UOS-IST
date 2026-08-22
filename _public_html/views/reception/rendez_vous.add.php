<?php
include "./views/include/header.php";

// Récupération des données nécessaires
$db = Connexion::getInstance()->getPDO();
$userId = $_SESSION['id'];

// Récupérer les agents avec accès
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

// Récupérer les rendez-vous avec filtre de recherche
$searchFilter = '';
$searchParams = [$userId];
if (isset($_GET['searchObjet']) && !empty($_GET['searchObjet'])) {
    $searchFilter = " AND rv.objet LIKE ?";
    $searchParams[] = '%' . $_GET['searchObjet'] . '%';
}

$stmtRendezVous = $db->prepare("
    SELECT rv.*, a.noms as nom_agent, s.designation as nom_service,
           tr.designation as type_designation, tr.couleur
    FROM rendez_vous rv
    LEFT JOIN agent a ON rv.Agent_idAgent = a.idAgent
    LEFT JOIN service s ON rv.Service_idService = s.idService
    LEFT JOIN type_rendez_vous tr ON rv.type_rendez_vous = tr.designation
    WHERE rv.cree_par = ? {$searchFilter}
    ORDER BY rv.date_rendez_vous DESC, rv.heure_debut DESC
");
$stmtRendezVous->execute($searchParams);
$rendezVous = $stmtRendezVous->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Rendez-vous</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Rendez-vous</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Liste des Rendez-vous</h5>

                        <!-- Bouton d'ajout -->
                        <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addRendezVousModal">
                            <span class="bi bi-plus-circle"></span> Planifier un Rendez-vous
                        </button>

                        <!-- Formulaire de recherche -->
                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="text" name="searchObjet" class="form-control" placeholder="Rechercher par objet..." value="<?= htmlspecialchars($_GET['searchObjet'] ?? '') ?>">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <!-- Tableau des rendez-vous -->
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Heure</th>
                                    <th>Agent</th>
                                    <th>Service</th>
                                    <th>Objet</th>
                                    <th>Contact</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $hasResults = false;
                                foreach ($rendezVous as $rdv) {
                                    $dateRendezVous = date('d/m/Y', strtotime($rdv['date_rendez_vous']));
                                    $heureDebut = date('H:i', strtotime($rdv['heure_debut']));
                                    $heureFin = date('H:i', strtotime($rdv['heure_fin']));
                                    $hasResults = true;
                                    
                                    $statusClass = '';
                                    switch($rdv['statut_rendez_vous']) {
                                        case 'planifie': $statusClass = 'badge bg-info'; break;
                                        case 'confirme': $statusClass = 'badge bg-success'; break;
                                        case 'reporte': $statusClass = 'badge bg-warning'; break;
                                        case 'annule': $statusClass = 'badge bg-danger'; break;
                                        case 'termine': $statusClass = 'badge bg-secondary'; break;
                                        default: $statusClass = 'badge bg-light text-dark';
                                    }
                                    
                                    echo "
                                        <tr>
                                            <td>{$dateRendezVous}</td>
                                            <td>{$heureDebut} - {$heureFin}</td>
                                            <td>{$rdv['nom_agent']}</td>
                                            <td>{$rdv['nom_service']}</td>
                                            <td>{$rdv['objet']}</td>
                                            <td>";
                                    
                                    if (!empty($rdv['contact_externe'])) {
                                        echo $rdv['contact_externe'];
                                        if (!empty($rdv['telephone_externe'])) {
                                            echo "<br><small>{$rdv['telephone_externe']}</small>";
                                        }
                                    } else {
                                        echo "<em>Contact interne</em>";
                                    }
                                    
                                    echo "</td>
                                            <td><span class='{$statusClass}'>{$rdv['statut_rendez_vous']}</span></td>
                                            <td>
                                                <button type='button' class='btn btn-warning btn-sm' onclick='editRendezVous({$rdv['idRendez_vous']})'>
                                                    <i class='bi bi-pencil'></i>
                                                </button>
                                                <button type='button' class='btn btn-danger btn-sm' onclick='confirmDeleteRendezVous({$rdv['idRendez_vous']})'>
                                                    <i class='bi bi-trash'></i>
                                                </button>
                                            </td>
                                        </tr>
                                    ";
                                }

                                if (!$hasResults) {
                                    echo "<tr><td colspan='8' class='text-center'>Aucun rendez-vous trouvé</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>

                        <!-- Modal d'ajout de rendez-vous -->
                        <div class="modal fade" id="addRendezVousModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Planifier un Rendez-vous</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="addRendezVousForm" method="POST" action="controller/addRendezVous.php">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="agent" class="form-label">Agent concerné <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="agent" name="Agent_idAgent" required>
                                                        <option value="">Sélectionner un agent</option>
                                                        <?php foreach ($agents as $agent): ?>
                                                            <option value="<?= $agent['idAgent'] ?>"><?= htmlspecialchars($agent['noms']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="service" class="form-label">Service <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="service" name="Service_idService" required>
                                                        <option value="">Sélectionner un service</option>
                                                        <?php foreach ($services as $service): ?>
                                                            <option value="<?= $service['idService'] ?>"><?= htmlspecialchars($service['designation']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="date_rendez_vous" class="form-label">Date du rendez-vous <span class="text-danger">*</span></label>
                                                    <input type="date" class="form-control" id="date_rendez_vous" name="date_rendez_vous" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="heure_debut" class="form-label">Heure début <span class="text-danger">*</span></label>
                                                    <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="heure_fin" class="form-label">Heure fin <span class="text-danger">*</span></label>
                                                    <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-md-8">
                                                    <label for="objet" class="form-label">Objet du rendez-vous <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="objet" name="objet" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="type_rendez_vous" class="form-label">Type de rendez-vous</label>
                                                    <select class="form-select" id="type_rendez_vous" name="type_rendez_vous">
                                                        <option value="">Sélectionner un type</option>
                                                        <?php foreach ($typesRendezVous as $type): ?>
                                                            <option value="<?= htmlspecialchars($type['designation']) ?>" data-duree="<?= $type['duree_defaut'] ?>">
                                                                <?= htmlspecialchars($type['designation']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="contact_externe" class="form-label">Contact externe</label>
                                                    <input type="text" class="form-control" id="contact_externe" name="contact_externe" placeholder="Nom du contact externe">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="telephone_externe" class="form-label">Téléphone</label>
                                                    <input type="tel" class="form-control" id="telephone_externe" name="telephone_externe" placeholder="Numéro de téléphone">
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="email_externe" class="form-label">Email</label>
                                                    <input type="email" class="form-control" id="email_externe" name="email_externe" placeholder="Adresse email">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="lieu" class="form-label">Lieu</label>
                                                                                                        <input type="text" class="form-control" id="lieu" name="lieu" placeholder="Lieu du rendez-vous">
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="priorite" class="form-label">Priorité</label>
                                                    <select class="form-select" id="priorite" name="priorite">
                                                        <option value="normale" selected>Normale</option>
                                                        <option value="basse">Basse</option>
                                                        <option value="haute">Haute</option>
                                                        <option value="urgente">Urgente</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="statut_rendez_vous" class="form-label">Statut</label>
                                                    <select class="form-select" id="statut_rendez_vous" name="statut_rendez_vous">
                                                        <option value="planifie" selected>Planifié</option>
                                                        <option value="confirme">Confirmé</option>
                                                        <option value="reporte">Reporté</option>
                                                        <option value="annule">Annulé</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label for="description" class="form-label">Description</label>
                                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Description détaillée du rendez-vous"></textarea>
                                            </div>

                                            <div class="mb-3">
                                                <label for="commentaires" class="form-label">Commentaires</label>
                                                <textarea class="form-control" id="commentaires" name="commentaires" rows="2" placeholder="Commentaires additionnels"></textarea>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="rappel_active" name="rappel_active" value="1" checked>
                                                        <label class="form-check-label" for="rappel_active">
                                                            Activer les rappels
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="delai_rappel" class="form-label">Délai de rappel (minutes)</label>
                                                    <input type="number" class="form-control" id="delai_rappel" name="delai_rappel" value="30" min="5" max="1440">
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-end">
                                                <button type="submit" class="btn btn-primary">
                                                    <span class="bi bi-save"></span> Enregistrer
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                            // Fonction pour confirmer la suppression
                            function confirmDeleteRendezVous(rendezVousId) {
                                Swal.fire({
                                    title: 'Êtes-vous sûr ?',
                                    text: "Cette action est irréversible !",
                                    icon: 'warning',
                                    showCancelButton: true,
                                    confirmButtonColor: '#3085d6',
                                    cancelButtonColor: '#d33',
                                    confirmButtonText: 'Oui, supprimer!',
                                    cancelButtonText: 'Annuler'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        window.location.href = 'controller/deleteRendezVous.php?id=' + rendezVousId;
                                    }
                                })
                            }

                            // Fonction pour éditer un rendez-vous
                            function editRendezVous(rendezVousId) {
                                // Redirection vers la page d'édition
                                window.location.href = 'reception/rendez_vous.edit?id=' + rendezVousId;
                            }

                            // Auto-calcul de l'heure de fin basé sur le type de rendez-vous
                            document.getElementById('type_rendez_vous').addEventListener('change', function() {
                                const selectedOption = this.options[this.selectedIndex];
                                const dureeDefaut = selectedOption.getAttribute('data-duree');
                                const heureDebut = document.getElementById('heure_debut').value;
                                
                                if (dureeDefaut && heureDebut) {
                                    const [heures, minutes] = heureDebut.split(':');
                                    const dateDebut = new Date();
                                    dateDebut.setHours(parseInt(heures), parseInt(minutes), 0, 0);
                                    
                                    const dateFin = new Date(dateDebut.getTime() + (parseInt(dureeDefaut) * 60000));
                                    const heureFin = String(dateFin.getHours()).padStart(2, '0') + ':' + 
                                                   String(dateFin.getMinutes()).padStart(2, '0');
                                    
                                    document.getElementById('heure_fin').value = heureFin;
                                }
                            });

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

                            // Définir la date minimale à aujourd'hui
                            document.addEventListener('DOMContentLoaded', function() {
                                const today = new Date().toISOString().split('T')[0];
                                document.getElementById('date_rendez_vous').min = today;
                            });
                        </script>

                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include "./views/include/footer.php"; ?>
