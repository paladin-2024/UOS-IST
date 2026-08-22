<?php
session_start();

if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__).'/vendor/autoload.php'; // Pour PhpSpreadsheet si disponible

$userId = $_SESSION['id'];
$dateDebut = $_GET['debut'] ?? date('Y-m-01');
$dateFin = $_GET['fin'] ?? date('Y-m-t');

try {
    $db = Connexion::getInstance()->getPDO();
    
    $query = "
        SELECT 
            v.*,
            a.noms as nom_agent,
            s.designation as nom_service,
            CASE 
                WHEN v.statut_visite = 'programmee' THEN 'Programmée'
                WHEN v.statut_visite = 'en_cours' THEN 'En cours'
                WHEN v.statut_visite = 'terminee' THEN 'Terminée'
                WHEN v.statut_visite = 'annulee' THEN 'Annulée'
                WHEN v.statut_visite = 'reportee' THEN 'Reportée'
                ELSE v.statut_visite
            END as statut_libelle,
            CASE 
                WHEN v.type_visite = 'professionnelle' THEN 'Professionnelle'
                WHEN v.type_visite = 'personnelle' THEN 'Personnelle'
                WHEN v.type_visite = 'officielle' THEN 'Officielle'
                WHEN v.type_visite = 'urgente' THEN 'Urgente'
                ELSE v.type_visite
            END as type_libelle
        FROM visites v
        LEFT JOIN agent a ON v.\"Agent_idAgent\" = a.\"idAgent\"
        LEFT JOIN service s ON v.\"Service_idService\" = s.\"idService\"
        WHERE v.cree_par = ? 
        AND v.date_visite BETWEEN ? AND ?
        ORDER BY v.date_visite DESC, v.heure_debut DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$userId, $dateDebut, $dateFin]);
    $visites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Export CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="visites_' . $dateDebut . '_' . $dateFin . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM pour UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // En-têtes
    fputcsv($output, [
        'Date de visite',
        'Heure début',
        'Heure fin',
        'Nom visiteur',
        'Prénom visiteur',
        'Entreprise',
        'Téléphone',
        'Email',
        'Agent à voir',
        'Service',
        'Objet de la visite',
        'Type de visite',
        'Statut',
        'Lieu de rencontre',
        'Nombre accompagnants',
        'Badge visiteur',
        'Date création'
    ], ';');
    
    // Données
    foreach ($visites as $visite) {
        fputcsv($output, [
            date('d/m/Y', strtotime($visite['date_visite'])),
            $visite['heure_debut'],
            $visite['heure_fin'],
            $visite['nom_visiteur'],
            $visite['prenom_visiteur'],
            $visite['entreprise_visiteur'],
            $visite['telephone_visiteur'],
            $visite['email_visiteur'],
            $visite['nom_agent'],
            $visite['nom_service'],
            $visite['objet_visite'],
            $visite['type_libelle'],
            $visite['statut_libelle'],
            $visite['lieu_rencontre'],
            $visite['nombre_accompagnants'],
            $visite['badge_visiteur'],
            date('d/m/Y H:i', strtotime($visite['date_creation']))
        ], ';');
    }
    
    fclose($output);

} catch (Exception $e) {
    error_log("Erreur export visites: " . $e->getMessage());
    echo "Erreur lors de l'export: " . $e->getMessage();
}
?>