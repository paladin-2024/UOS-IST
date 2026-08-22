<?php
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID invalide']);
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    $rdvId = intval($_GET['id']);
    
    $query = $db->prepare("
        SELECT rv.*, 
               a.noms as nom_agent, 
               s.designation as nom_service,
               tr.designation as type_designation, 
               tr.couleur
        FROM rendez_vous rv
        LEFT JOIN agent a ON rv.Agent_idAgent = a.idAgent
        LEFT JOIN service s ON rv.Service_idService = s.idService
        LEFT JOIN type_rendez_vous tr ON rv.type_rendez_vous = tr.designation
        WHERE rv.idRendez_vous = ?
    ");
    
    $query->execute([$rdvId]);
    $rdv = $query->fetch(PDO::FETCH_ASSOC);
    
    if (!$rdv) {
        echo json_encode(['success' => false, 'message' => 'Rendez-vous non trouvé']);
        exit;
    }
    
    // Générer le HTML des détails
    $html = '
        <div class="row">
            <div class="col-md-6">
                <h6><i class="bi bi-calendar3"></i> Informations générales</h6>
                <table class="table table-sm">
                    <tr><td><strong>Date:</strong></td><td>' . date('d/m/Y', strtotime($rdv['date_rendez_vous'])) . '</td></tr>
                    <tr><td><strong>Heure:</strong></td><td>' . date('H:i', strtotime($rdv['heure_debut'])) . ' - ' . date('H:i', strtotime($rdv['heure_fin'])) . '</td></tr>
                    <tr><td><strong>Service:</strong></td><td>' . htmlspecialchars($rdv['nom_service']) . '</td></tr>
                    <tr><td><strong>Type:</strong></td><td>' . htmlspecialchars($rdv['type_rendez_vous'] ?? 'Non spécifié') . '</td></tr>
                    <tr><td><strong>Priorité:</strong></td><td><span class="badge bg-' . getPriorityColor($rdv['priorite']) . '">' . ucfirst($rdv['priorite']) . '</span></td></tr>
                    <tr><td><strong>Statut:</strong></td><td><span class="badge bg-' . getStatusColor($rdv['statut_rendez_vous']) . '">' . ucfirst($rdv['statut_rendez_vous']) . '</span></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6><i class="bi bi-person"></i> Informations contact</h6>
                <table class="table table-sm">';
    
    if (!empty($rdv['contact_externe'])) {
        $html .= '
                    <tr><td><strong>Nom:</strong></td><td>' . htmlspecialchars($rdv['contact_externe']) . '</td></tr>';
        if (!empty($rdv['telephone_externe'])) {
            $html .= '<tr><td><strong>Téléphone:</strong></td><td>' . htmlspecialchars($rdv['telephone_externe']) . '</td></tr>';
        }
        if (!empty($rdv['email_externe'])) {
            $html .= '<tr><td><strong>Email:</strong></td><td>' . htmlspecialchars($rdv['email_externe']) . '</td></tr>';
        }
    } else {
        $html .= '<tr><td colspan="2"><em>Contact interne</em></td></tr>';
    }
    
    if (!empty($rdv['lieu'])) {
        $html .= '<tr><td><strong>Lieu:</strong></td><td>' . htmlspecialchars($rdv['lieu']) . '</td></tr>';
    }
    
    $html .= '
                </table>
            </div>
        </div>';
    
    if (!empty($rdv['objet'])) {
        $html .= '
        <div class="row mt-3">
            <div class="col-12">
                <h6><i class="bi bi-chat-text"></i> Objet</h6>
                <p class="border p-2 rounded">' . htmlspecialchars($rdv['objet']) . '</p>
            </div>
        </div>';
    }
    
    if (!empty($rdv['description'])) {
        $html .= '
        <div class="row mt-3">
            <div class="col-12">
                <h6><i class="bi bi-file-text"></i> Description</h6>
                <p class="border p-2 rounded">' . nl2br(htmlspecialchars($rdv['description'])) . '</p>
            </div>
        </div>';
    }
    
    if (!empty($rdv['commentaires'])) {
        $html .= '
        <div class="row mt-3">
            <div class="col-12">
                <h6><i class="bi bi-chat-dots"></i> Commentaires</h6>
                <p class="border p-2 rounded">' . nl2br(htmlspecialchars($rdv['commentaires'])) . '</p>
            </div>
        </div>';
    }
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    error_log("Erreur getRendezVousDetails: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}

function getStatusColor($statut) {
    switch ($statut) {
        case 'planifie': return 'info';
        case 'confirme': return 'success';
        case 'reporte': return 'warning';
        case 'annule': return 'danger';
        case 'termine': return 'secondary';
        default: return 'light';
    }
}

function getPriorityColor($priorite) {
    switch ($priorite) {
        case 'basse': return 'secondary';
        case 'normale': return 'primary';
        case 'haute': return 'warning';
        case 'urgente': return 'danger';
        default: return 'secondary';
    }
}
?>
