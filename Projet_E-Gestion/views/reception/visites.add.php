<?php
include "./views/include/header.php";

// Récupération des données nécessaires
$db = Connexion::getInstance()->getPDO();
$userId = $_SESSION['id'];

// Récupérer les agents avec accès
$stmtAgents = $db->prepare("
    SELECT DISTINCT a.\"idAgent\", a.noms 
    FROM agent a 
    INNER JOIN service s ON a.\"idService\" = s.\"idService\" 
    WHERE a.\"idStructure\" IN (
        SELECT DISTINCT \"idStructure\" FROM agent WHERE \"idAgent\" = 
        (SELECT \"idAgent\" FROM t_users WHERE \"idUser\" = ?)
    )
    ORDER BY a.noms
");
$stmtAgents->execute([$userId]);
$agents = $stmtAgents->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les services
$stmtServices = $db->prepare("
    SELECT s.\"idService\", s.designation 
    FROM service s 
    WHERE s.\"Structure_idStructure\" IN (
        SELECT DISTINCT \"idStructure\" FROM agent WHERE \"idAgent\" = 
        (SELECT \"idAgent\" FROM t_users WHERE \"idUser\" = ?)
    )
    ORDER BY s.designation
");
$stmtServices->execute([$userId]);
$services = $stmtServices->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les visites avec filtre de recherche
$searchFilter = '';
$searchParams = [$userId];
if (isset($_GET['searchNom']) && !empty($_GET['searchNom'])) {
    $searchFilter = " AND (v.nom_visiteur LIKE ? OR v.entreprise_visiteur LIKE ?)";
    $searchParams[] = '%' . $_GET['searchNom'] . '%';
    $searchParams[] = '%' . $_GET['searchNom'] . '%';
}

$stmtVisites = $db->prepare("
    SELECT v.*, a.noms as nom_agent, s.designation as nom_service
    FROM visites v
    LEFT JOIN agent a ON v.\"Agent_idAgent\" = a.\"idAgent\"
    LEFT JOIN service s ON v.\"Service_idService\" = s.\"idService\"
    WHERE v.cree_par = ? {$searchFilter}
    ORDER BY v.date_visite DESC, v.heure_debut DESC
");
$stmtVisites->execute($searchParams);
$visites = $stmtVisites->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Visites</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Visites</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Liste des Visites</h5>

                        <!-- Bouton d'ajout -->
                        <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addVisiteModal">
                            <span class="bi bi-plus-circle"></span> Programmer une Visite
                        </button>

                        <!-- Formulaire de recherche -->
                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="text" name="searchNom" class="form-control" placeholder="Rechercher par nom ou entreprise..." value="<?= htmlspecialchars($_GET['searchNom'] ?? '') ?>">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <!-- Tableau des visites -->
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Heure</th>
                                    <th>Visiteur</th>
                                    <th>Entreprise</th>
                                    <th>Agent à voir</th>
                                    <th>Objet</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $hasResults = false;
                                foreach ($visites as $visite) {
                                    $dateVisite = date('d/m/Y', strtotime($visite['date_visite']));
                                    $heureDebut = date('H:i', strtotime($visite['heure_debut']));
                                    $heureFin = date('H:i', strtotime($visite['heure_fin']));
                                    $hasResults = true;
                                    
                                    $statusClass = '';
                                    switch($visite['statut_visite']) {
                                        case 'programmee': $statusClass = 'badge bg-info'; break;
                                        case 'en_cours': $statusClass = 'badge bg-warning'; break;
                                        case 'terminee': $statusClass = 'badge bg-success'; break;
                                        case 'annulee': $statusClass = 'badge bg-danger'; break;
                                        case 'reportee': $statusClass = 'badge bg-secondary'; break;
                                        default: $statusClass = 'badge bg-light text-dark';
                                    }
                                    
                                    echo "
                                        <tr>
                                            <td>{$dateVisite}</td>
                                            <td>{$heureDebut} - {$heureFin}</td>
                                            <td>
                                                <strong>{$visite['nom_visiteur']} {$visite['prenom_visiteur']}</strong>
                                                <br><small>{$visite['telephone_visiteur']}</small>
                                            </td>
                                            <td>{$visite['entreprise_visiteur']}</td>
                                            <td>{$visite['nom_agent']}</td>
                                            <td>{$visite['objet_visite']}</td>
                                            <td><span class='{$statusClass}'>{$visite['statut_visite']}</span></td>
                                            <td>
                                                <button type='button' class='btn btn-info btn-sm' onclick='voirDetails({$visite['idVisite']})'>
                                                    <i class='bi bi-eye'></i>
                                                </button>
                                                <button type='button' class='btn btn-warning btn-sm' onclick='editVisite({$visite['idVisite']})'>
                                                    <i class='bi bi-pencil'></i>
                                                </button>
                                                <button type='button' class='btn btn-danger btn-sm' onclick='confirmDeleteVisite({$visite['idVisite']})'>
                                                    <i class='bi bi-trash'></i>
                                                </button>
                                            </td>
                                        </tr>
                                    ";
                                }

                                if (!$hasResults) {
                                    echo "<tr><td colspan='8' class='text-center'>Aucune visite trouvée</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>

                        <!-- Modal d'ajout de visite -->
                        <div class="modal fade" id="addVisiteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Programmer une Visite</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="addVisiteForm" method="POST" action="controller/addVisite.php">
                                            <!-- Informations du visiteur -->
                                            <div class="card mb-3">
                                                <div class="card-header">
                                                    <h6 class="mb-0">Informations du Visiteur</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-4">
                                                            <label for="nom_visiteur" class="form-label">Nom <span class="text-danger">*</span></label>
                                                            <input type="text" class="form-control" id="nom_visiteur" name="nom_visiteur" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label for="prenom_visiteur" class="form-label">Prénom</label>
                                                            <input type="text" class="form-control" id="prenom_visiteur" name="prenom_visiteur">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label for="carte_identite" class="form-label">N° Carte d'identité</label>
                                                            <input type="text" class="form-control" id="carte_identite" name="carte_identite">
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label for="entreprise_visiteur" class="form-label">Entreprise/Organisation</label>
                                                            <input type="text" class="form-control" id="entreprise_visiteur" name="entreprise_visiteur">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label for="telephone_visiteur" class="form-label">Téléphone <span class="text-danger">*</span></label>
                                                            <input type="tel" class="form-control" id="telephone_visiteur" name="telephone_visiteur" required>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label for="email_visiteur" class="form-label">Email</label>
                                                            <input type="email" class="form-control" id="email_visiteur" name="email_visiteur">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                                                                        <!-- Informations de la visite -->
                                            <div class="card mb-3">
                                                <div class="card-header">
                                                    <h6 class="mb-0">Détails de la Visite</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label for="agent" class="form-label">Agent à visiter <span class="text-danger">*</span></label>
                                                            <select class="form-select" id="agent" name=Agent_idAgent required>
                                                                <option value="">Sélectionner un agent</option>
                                                                <?php foreach ($agents as $agent): ?>
                                                                    <option value="<?= $agent['idAgent'] ?>"><?= htmlspecialchars($agent['noms']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="service" class="form-label">Service <span class="text-danger">*</span></label>
                                                            <select class="form-select" id="service" name=Service_idService required>
                                                                <option value="">Sélectionner un service</option>
                                                                <?php foreach ($services as $service): ?>
                                                                    <option value="<?= $service['idService'] ?>"><?= htmlspecialchars($service['designation']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-4">
                                                            <label for="date_visite" class="form-label">Date de la visite <span class="text-danger">*</span></label>
                                                            <input type="date" class="form-control" id="date_visite" name="date_visite" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label for="heure_debut" class="form-label">Heure début <span class="text-danger">*</span></label>
                                                            <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label for="heure_fin" class="form-label">Heure fin <span class="text-danger">*</span></label>
                                                            <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-8">
                                                            <label for="objet_visite" class="form-label">Objet de la visite <span class="text-danger">*</span></label>
                                                            <input type="text" class="form-control" id="objet_visite" name="objet_visite" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label for="type_visite" class="form-label">Type de visite</label>
                                                            <select class="form-select" id="type_visite" name="type_visite">
                                                                <option value="professionnelle" selected>Professionnelle</option>
                                                                <option value="personnelle">Personnelle</option>
                                                                <option value="officielle">Officielle</option>
                                                                <option value="urgente">Urgente</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label for="lieu_rencontre" class="form-label">Lieu de rencontre</label>
                                                            <input type="text" class="form-control" id="lieu_rencontre" name="lieu_rencontre" placeholder="Bureau, salle de réunion...">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label for="nombre_accompagnants" class="form-label">Nb accompagnants</label>
                                                            <input type="number" class="form-control" id="nombre_accompagnants" name="nombre_accompagnants" value="0" min="0" max="10">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label for="statut_visite" class="form-label">Statut</label>
                                                            <select class="form-select" id="statut_visite" name="statut_visite">
                                                                <option value="programmee" selected>Programmée</option>
                                                                <option value="en_cours">En cours</option>
                                                                <option value="terminee">Terminée</option>
                                                                <option value="reportee">Reportée</option>
                                                                <option value="annulee">Annulée</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="description" class="form-label">Description détaillée</label>
                                                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Détails de la visite, préparatifs nécessaires..."></textarea>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="observations" class="form-label">Observations</label>
                                                        <textarea class="form-control" id="observations" name="observations" rows="2" placeholder="Remarques particulières, consignes de sécurité..."></textarea>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" id="validation_securite" name="validation_securite" value="1">
                                                                <label class="form-check-label" for="validation_securite">
                                                                    Validation sécurité requise
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="badge_visiteur" class="form-label">N° Badge visiteur</label>
                                                            <input type="text" class="form-control" id="badge_visiteur" name="badge_visiteur" placeholder="Badge temporaire">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-end">
                                                <button type="submit" class="btn btn-primary">
                                                    <span class="bi bi-save"></span> Enregistrer la Visite
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal de détails de visite -->
                        <div class="modal fade" id="detailsVisiteModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Détails de la Visite</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body" id="detailsVisiteContent">
                                        <!-- Contenu chargé dynamiquement -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                            // Fonction pour confirmer la suppression
                            function confirmDeleteVisite(visiteId) {
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
                                        window.location.href = 'controller/deleteVisite.php?id=' + visiteId;
                                    }
                                })
                            }

                            // Fonction pour éditer une visite
                            function editVisite(visiteId) {
                                window.location.href = 'reception/visites.edit?id=' + visiteId;
                            }

                            // Fonction pour voir les détails d'une visite
                            function voirDetails(visiteId) {
                                fetch('controller/getVisiteDetails.php?id=' + visiteId)
                                    .then(response => response.text())
                                    .then(data => {
                                        document.getElementById('detailsVisiteContent').innerHTML = data;
                                        new bootstrap.Modal(document.getElementById('detailsVisiteModal')).show();
                                    })
                                    .catch(error => {
                                        console.error('Erreur:', error);
                                        Swal.fire('Erreur', 'Impossible de charger les détails', 'error');
                                    });
                            }

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
                                document.getElementById('date_visite').min = today;

                                // Auto-complétion heure fin (durée par défaut 1h)
                                document.getElementById('heure_debut').addEventListener('change', function() {
                                    const heureDebut = this.value;
                                    if (heureDebut) {
                                        const [heures, minutes] = heureDebut.split(':');
                                        const dateDebut = new Date();
                                        dateDebut.setHours(parseInt(heures), parseInt(minutes), 0, 0);
                                        
                                        const dateFin = new Date(dateDebut.getTime() + (60 * 60000)); // +1 heure
                                        const heureFin = String(dateFin.getHours()).padStart(2, '0') + ':' + 
                                                       String(dateFin.getMinutes()).padStart(2, '0');
                                        
                                        document.getElementById('heure_fin').value = heureFin;
                                    }
                                });
                            });
                        </script>

                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include "./views/include/footer.php"; ?>
