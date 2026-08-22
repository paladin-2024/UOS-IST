<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if (isset($_GET['id'])) {
    $visiteId = intval($_GET['id']);
    
    try {
        $db = Connexion::getInstance()->getPDO();
        
        $stmt = $db->prepare("
            SELECT v.*, a.noms as nom_agent, s.designation as nom_service,
                   u.nomUser as cree_par_nom
            FROM visites v
            LEFT JOIN agent a ON v.Agent_idAgent = a.idAgent
            LEFT JOIN service s ON v.Service_idService = s.idService
            LEFT JOIN t_users u ON v.cree_par = u.idUser
            WHERE v.idVisite = ?
        ");
        $stmt->execute([$visiteId]);
        $visite = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($visite) {
            $dateVisite = date('d/m/Y', strtotime($visite['date_visite']));
            $heureDebut = date('H:i', strtotime($visite['heure_debut']));
            $heureFin = date('H:i', strtotime($visite['heure_fin']));
            $dateCreation = date('d/m/Y H:i', strtotime($visite['date_creation']));
            
            $statusClass = '';
            $statusText = '';
            switch($visite['statut_visite']) {
                case 'programmee': 
                    $statusClass = 'badge bg-info'; 
                    $statusText = 'Programmée';
                    break;
                case 'en_cours': 
                    $statusClass = 'badge bg-warning'; 
                    $statusText = 'En cours';
                    break;
                case 'terminee': 
                    $statusClass = 'badge bg-success'; 
                    $statusText = 'Terminée';
                    break;
                case 'annulee': 
                    $statusClass = 'badge bg-danger'; 
                    $statusText = 'Annulée';
                    break;
                case 'reportee': 
                    $statusClass = 'badge bg-secondary'; 
                    $statusText = 'Reportée';
                    break;
            }
            
            echo "
            <div class='row'>
                <div class='col-md-6'>
                    <h6>Informations du Visiteur</h6>
                    <table class='table table-sm'>
                        <tr><td><strong>Nom complet:</strong></td><td>{$visite['nom_visiteur']} {$visite['prenom_visiteur']}</td></tr>
                        <tr><td><strong>Entreprise:</strong></td><td>" . ($visite['entreprise_visiteur'] ?: 'Non renseignée') . "</td></tr>
                        <tr><td><strong>Téléphone:</strong></td><td>{$visite['telephone_visiteur']}</td></tr>
                        <tr><td><strong>Email:</strong></td><td>" . ($visite['email_visiteur'] ?: 'Non renseigné') . "</td></tr>
                        <tr><td><strong>Carte d'identité:</strong></td><td>" . ($visite['carte_identite'] ?: 'Non renseignée') . "</td></tr>
                        <tr><td><strong>Badge visiteur:</strong></td><td><span class='badge bg-primary'>{$visite['badge_visiteur']}</span></td></tr>
                    </table>
                </div>
                <div class='col-md-6'>
                    <h6>Détails de la Visite</h6>
                    <table class='table table-sm'>
                        <tr><td><strong>Date:</strong></td><td>{$dateVisite}</td></tr>
                        <tr><td><strong>Heure:</strong></td><td>{$heureDebut} - {$heureFin}</td></tr>
                        <tr><td><strong>Agent à voir:</strong></td><td>{$visite['nom_agent']}</td></tr>
                        <tr><td><strong>Service:</strong></td><td>{$visite['nom_service']}</td></tr>
                                                <tr><td><strong>Lieu:</strong></td><td>" . ($visite['lieu_rencontre'] ?: 'Non spécifié') . "</td></tr>
                        <tr><td><strong>Type:</strong></td><td>" . ucfirst($visite['type_visite']) . "</td></tr>
                        <tr><td><strong>Statut:</strong></td><td><span class='{$statusClass}'>{$statusText}</span></td></tr>
                        <tr><td><strong>Accompagnants:</strong></td><td>{$visite['nombre_accompagnants']}</td></tr>
                        <tr><td><strong>Validation sécurité:</strong></td><td>" . ($visite['validation_securite'] ? '<span class="badge bg-success">Requise</span>' : '<span class="badge bg-secondary">Non requise</span>') . "</td></tr>
                    </table>
                </div>
            </div>
            
            <div class='row mt-3'>
                <div class='col-12'>
                    <h6>Objet de la visite</h6>
                    <p class='border p-2 bg-light'>{$visite['objet_visite']}</p>
                </div>
            </div>";
            
            if (!empty($visite['description'])) {
                echo "
                <div class='row mt-2'>
                    <div class='col-12'>
                        <h6>Description</h6>
                        <p class='border p-2'>{$visite['description']}</p>
                    </div>
                </div>";
            }
            
            if (!empty($visite['observations'])) {
                echo "
                <div class='row mt-2'>
                    <div class='col-12'>
                        <h6>Observations</h6>
                        <p class='border p-2 bg-warning bg-opacity-25'>{$visite['observations']}</p>
                    </div>
                </div>";
            }
            
            // Afficher les heures réelles si la visite est terminée
            if ($visite['statut_visite'] == 'terminee' && ($visite['heure_arrivee_reelle'] || $visite['heure_depart_reelle'])) {
                echo "
                <div class='row mt-3'>
                    <div class='col-12'>
                        <h6>Heures réelles</h6>
                        <table class='table table-sm table-bordered'>
                            <tr>
                                <td><strong>Arrivée:</strong></td>
                                <td>" . ($visite['heure_arrivee_reelle'] ? date('H:i', strtotime($visite['heure_arrivee_reelle'])) : 'Non enregistrée') . "</td>
                                <td><strong>Départ:</strong></td>
                                <td>" . ($visite['heure_depart_reelle'] ? date('H:i', strtotime($visite['heure_depart_reelle'])) : 'Non enregistrée') . "</td>
                            </tr>
                        </table>
                    </div>
                </div>";
            }
            
            echo "
            <div class='row mt-3'>
                <div class='col-12'>
                    <small class='text-muted'>
                        Créée le {$dateCreation} par {$visite['cree_par_nom']}
                    </small>
                </div>
            </div>";
            
        } else {
            echo "<div class='alert alert-danger'>Visite non trouvée.</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Erreur lors du chargement des détails: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div class='alert alert-danger'>ID de visite manquant.</div>";
}
?>
