<?php
/**
 * Contrôleur pour la gestion du journal serveur
 */
require_once __DIR__ . '/../models/JournalServeur.php';

$journal = new JournalServeur();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Récupérer les paramètres de filtrage
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$par_page = isset($_GET['par_page']) ? intval($_GET['par_page']) : 50;

$filtres = [];
if (!empty($_GET['type_action'])) $filtres['type_action'] = $_GET['type_action'];
if (!empty($_GET['module'])) $filtres['module'] = $_GET['module'];
if (!empty($_GET['statut'])) $filtres['statut'] = $_GET['statut'];
if (!empty($_GET['date_debut'])) $filtres['date_debut'] = $_GET['date_debut'];
if (!empty($_GET['date_fin'])) $filtres['date_fin'] = $_GET['date_fin'];
if (!empty($_GET['id_utilisateur'])) $filtres['id_utilisateur'] = $_GET['id_utilisateur'];
if (!empty($_GET['recherche'])) $filtres['recherche'] = $_GET['recherche'];

switch ($action) {
    case 'list':
        $resultats = $journal->obtenirLogs($filtres, $page, $par_page);
        break;
    
    case 'details':
        $id_log = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $log = $journal->obtenirLog($id_log);
        break;
    
    case 'statistiques':
        $date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : null;
        $date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : null;
        $statistiques = $journal->obtenirStatistiques($date_debut, $date_fin);
        $par_type = $journal->obtenirLogsParType();
        $utilisateurs_actifs = $journal->obtenirUtilisateursPlusActifs(10);
        break;
    
    case 'export':
        $journal->exporterEnCSV($filtres);
        break;
    
    case 'nettoyer':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $jours = isset($_POST['jours']) ? intval($_POST['jours']) : 90;
            $supprimés = $journal->supprimerLogsAnciens($jours);
            $_SESSION['message'] = "Logs anciens supprimés: " . $supprimés . " enregistrements.";
        }
        break;

    default:
        $resultats = $journal->obtenirLogs($filtres, $page, $par_page);
}
?>
