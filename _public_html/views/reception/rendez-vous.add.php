<?php
include "./views/include/header.php";

$structureModel = new Structure();
$agent = new Agent();
$userId = $_SESSION['id'];

// Fetch rendez-vous the user has access to
$rendezVous = $structureModel->getRendezVousByUserAccess($userId);

// Fetch services and agents for dropdowns
$services = $structureModel->getServicesByUserAccess($userId);
$agents = $agent->getAgentsByUserAccess($userId);

// Fetch types de rendez-vous
$typesRendezVous = $structureModel->getTypesRendezVous();
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
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Liste des Rendez-vous</h5>

                        <!-- Add Rendez-vous Button -->
                        <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addRendezVousModal">
                            <span class="bi bi-plus-circle"></span> Planifier un Rendez-vous
                        </button>

                        <!-- Search Form -->
                        <form method="GET" action="" class="mb-3">
                            <div class="row">
                                <div class="col-md-4">
                                    <input type="text" name="searchObjet" class="form-control" placeholder="Rechercher par objet" value="<?= $_GET['searchObjet'] ?? '' ?>">
                                </div>
                                <div class="col-md-3">
                                    <input type="date" name="searchDate" class="form-control" value="<?= $_GET['searchDate'] ?? '' ?>">
                                </div>
                                <div class="col-md-3">
                                    <select name="searchStatut" class="form-select">
                                        <option value="">Tous les statuts</option>
                                        <option value="planifie" <?= ($_GET['searchStatut'] ?? '') === 'planifie' ? 'selected' : '' ?>>Planifié</option>
                                        <option value="confirme" <?= ($_GET['searchStatut'] ?? '') === 'confirme' ? 'selected' : '' ?>>Confirmé</option>
                                        <option value="reporte" <?= ($_GET['searchStatut'] ?? '') === 'reporte' ? 'selected' : '' ?>>Reporté</option>
                                        <option value="annule" <?= ($_GET['searchStatut'] ?? '') === 'annule' ? 'selected' : '' ?>>Annulé</option>
                                        <option value="termine" <?= ($_GET['searchStatut'] ?? '') === 'termine' ? 'selected' : '' ?>>Terminé</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary">Rechercher</button>
                                </div>
                            </div>
                        </form>

                        <table class="table table-striped table-bordered" id="rendezVousTable">
                            <thead>
                                <tr>
                                    <th>Date/Heure</th>
                                    <th>Agent</th>
                                    <th>Contact</th>
                                    <th>Objet</th>
                                    <th>Statut</th>
                                    <th>Priorité</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $hasResults = false;
                                foreach ($rendezVous as $rdv) {
                                    $dateRdv = date('d/m/Y', strtotime($rdv['date_rendez_vous']));
                                    $heureDebut = date('H:i', strtotime($rdv['heure_debut']));
                                    $heureFin = date('H:i', strtotime($rdv['heure_fin']));
                                    $hasResults = true;
                                    
                                    // Définir les classes CSS pour les statuts
                                    $statutClass = '';
                                    switch($rdv['statut_rendez_vous']) {
                                        case 'planifie': $statutClass = 'badge bg-primary'; break;
                                        case 'confirme': $statutClass = 'badge bg-success'; break;
                                        case 'reporte': $statutClass = 'badge bg-warning'; break;
                                        case 'annule': $statutClass = 'badge bg-danger'; break;
                                        case 'termine': $statutClass = 'badge bg-info'; break;
                                        default: $statutClass = 'badge bg-secondary'; break;
                                    }
                                    
                                    // Définir les classes CSS pour les priorités
                                    $prioriteClass = '';
                                    switch($rdv['priorite']) {
                                        case 'urgente': $prioriteClass = 'badge bg-danger'; break;
                                        case 'haute': $prioriteClass = 'badge bg-warning'; break;
                                        case 'normale': $prioriteClass = 'badge bg-primary'; break;
                                        case 'basse': $prioriteClass = 'badge bg-secondary'; break;
                                        default: $prioriteClass = 'badge bg-primary'; break;
                                    }
                                    
                                    echo "
                                        <tr>
                                            <td>
                                                <strong>{$dateRdv}</strong><br>
                                                <small>{$heureDebut} - {$heureFin}</small>
                                            </td>
                                            <td>{$rdv['agent_nom']}</td>
                                            <td>
                                                {$rdv['contact_externe']}<br>
                                                <small>{$rdv['telephone_externe']}</small>
                                            </td>
                                            <td>
                                                <strong>{$rdv['objet']}</strong><br>
                                                <small class='text-muted'>{$rdv['lieu']}</small>
                                            </td>
                                            <td><span class='{$statutClass}'>" . ucfirst($rdv['statut_rendez_vous']) . "</span></td>
                                            <td><span class='{$prioriteClass}'>" . ucfirst($rdv['priorite']) . "</span></td>
                                            <td>
                                                <div class='btn-group' role='group'>
                                                    <button type='button' class='btn btn-info btn-sm' onclick='viewRendezVous({$rdv['idRendez_vous']})' title='Voir détails'>
                                                        <i class='bi bi-eye'></i>
                                                    </button>
                                                    <button type='button' class='btn btn-warning btn-sm' onclick='editRendezVous({$rdv['idRendez_vous']})' title='Modifier'>
                                                        <i class='bi bi-pencil'></i>
                                                    </button>
                                                    <button type='button' class='btn btn-danger btn-sm' onclick='confirmDeleteRendezVous({$rdv['idRendez_vous']})' title='Supprimer'>
                                                        <i class='bi bi-trash'></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ";
                                }

                                if (!$hasResults) {
                                    echo "<tr><td colspan='7' class='text-center'>Aucun résultat trouvé</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>

                        <!-- Add Rendez-vous Modal -->
                        <div class="modal fade" id="addRendezVousModal" tabindex="-1" aria-labelledby="addRendezVousModalLabel" aria-hidden="true" data-bs-backdrop="static">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="addRendezVousModalLabel">Planifier un Rendez-vous</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="addRendezVousForm" method="POST" action="controller/addRendezVous.php">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="Agent_idAgent" class="form-label">Agent concerné <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="Agent_idAgent" name="Agent_idAgent" required>
                                                        <option value="">Sélectionner un agent</option>
                                                        <?php foreach ($agents as $agent): ?>
                                                            <option value="<?= $agent['idAgent'] ?>"><?= htmlspecialchars($agent['noms']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="Service_idService" class="form-label">Service <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="Service_idService" name="Service_idService" required>
                                                        <option value="">Sélectionner un service</option>
                                                        <?php foreach ($services as $service): ?>
                                                            <option value="<?= $service['idService'] ?>"><?= htmlspecialchars($service['designation']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-4">
                                                    <label for="contact_externe" class="form-label">Nom du contact <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="contact_externe" name="contact_externe" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="email_externe" class="form-label">Email</label>
                                                    <input type="email" class="form-control" id="email_externe" name="email_externe">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="telephone_externe" class="form-label">Téléphone <span class="text-danger">*</span></label>
                                                    <input type="tel" class="form-control" id="telephone_externe" name="telephone_externe" required>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-4">
                                                    <label for="date_rendez_vous" class="form-label">Date du rendez-vous <span class="text-danger">*</span></label>
                                                    <input type="date" class="form-control" id="date_rendez_vous" name="date_rendez_vous" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="heure_debut" class="form-label">Heure de début <span class="text-danger">*</span></label>
                                                    <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="heure_fin" class="form-label">Heure de fin <span class="text-danger">*</span></label>
                                                    <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="type_rendez_vous" class="form-label">Type de rendez-vous</label>
                                                    <select class="form-select" id="type_rendez_vous" name="type_rendez_vous">
                                                        <option value="">Sélectionner un type</option>
                                                        <?php foreach ($typesRendezVous as $type): ?>
                                                            <option value="<?= htmlspecialchars($type['designation']) ?>"><?= htmlspecialchars($type['designation']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="priorite" class="form-label">Priorité <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="priorite" name="priorite" required>
                                                        <option value="normale" selected>Normale</option>
                                                        <option value="basse">Basse</option>
                                                        <option value="haute">Haute</option>
                                                        <option value="urgente">Urgente</option>