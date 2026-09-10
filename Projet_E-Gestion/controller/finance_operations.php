<?php
require_once dirname(__DIR__) . '/views/405.php';

// Dispatcher for views/finance/configuration_frais.php's forms. Each action
// below is already implemented, tested, and field-compatible in an existing
// single-purpose controller -- this just remaps the one or two field names
// that differ (categorie_id/frais_id -> id) and forwards the action value,
// then delegates via require so the exact same logic runs (including that
// file's own auth check, validation and header()+exit() redirect).
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'ajouter_categorie':
        $_POST['action'] = 'ajouter';
        require dirname(__DIR__) . '/controller/categories_frais_operations.php';
        break;

    case 'modifier_categorie':
        $_POST['id'] = $_POST['categorie_id'] ?? null;
        $_POST['action'] = 'modifier';
        require dirname(__DIR__) . '/controller/categories_frais_operations.php';
        break;

    case 'supprimer_categorie':
        $_POST['id'] = $_POST['categorie_id'] ?? null;
        $_POST['action'] = 'supprimer';
        require dirname(__DIR__) . '/controller/categories_frais_operations.php';
        break;

    case 'ajouter_frais':
        $_POST['action'] = 'ajouter';
        require dirname(__DIR__) . '/controller/frais_operations.php';
        break;

    case 'modifier_frais':
        $_POST['id'] = $_POST['frais_id'] ?? null;
        $_POST['action'] = 'modifier';
        require dirname(__DIR__) . '/controller/frais_operations.php';
        break;

    case 'supprimer_frais':
        $_POST['id'] = $_POST['frais_id'] ?? null;
        $_POST['action'] = 'supprimer';
        require dirname(__DIR__) . '/controller/frais_operations.php';
        break;

    case 'configurer_tranches':
        // Field names and action value already match frais_operations.php's
        // own 'configurer_tranches' case exactly -- no remapping needed.
        require dirname(__DIR__) . '/controller/frais_operations.php';
        break;

    case 'affecter_frais':
        require dirname(__DIR__) . '/controller/affecter_frais.php';
        break;

    case 'exemption_frais':
        require dirname(__DIR__) . '/controller/exemption_frais.php';
        break;

    case 'supprimer_affectation':
        // No existing controller handles this one -- affectation_frais rows
        // are otherwise only ever deleted as a side effect of deleting the
        // parent frais (see frais_operations.php's 'supprimer' case).
        session_start();
        require_once dirname(__DIR__) . '/config/Connexion.php';

        if (!isset($_SESSION['id'])) {
            header('Location: ../accueil');
            exit();
        }

        $idUser = $_SESSION['id'];
        $connexion = Connexion::getInstance()->getPDO();

        try {
            $affectation_id = intval($_POST['affectation_id'] ?? 0);

            if ($affectation_id <= 0) {
                throw new Exception('ID d\'affectation invalide');
            }

            // Mirrors the modal's own copy: "La suppression n'est possible
            // que pour les frais non payés."
            $stmt = $connexion->prepare("SELECT statut_paiement FROM affectation_frais WHERE id = :id");
            $stmt->bindParam(':id', $affectation_id);
            $stmt->execute();
            $affectation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$affectation) {
                throw new Exception('Affectation non trouvée');
            }

            if ($affectation['statut_paiement'] !== 'Non payé') {
                throw new Exception('Seules les affectations non payées peuvent être supprimées');
            }

            $stmt = $connexion->prepare("DELETE FROM affectation_frais WHERE id = :id");
            $stmt->bindParam(':id', $affectation_id);

            if ($stmt->execute()) {
                $_SESSION['message'] = 'L\'affectation a été supprimée avec succès';
                $_SESSION['messageType'] = 'success';
            } else {
                throw new Exception('Erreur lors de la suppression de l\'affectation');
            }
        } catch (Exception $e) {
            error_log('Erreur dans finance_operations.php (supprimer_affectation): ' . $e->getMessage());
            $_SESSION['message'] = 'Erreur: ' . $e->getMessage();
            $_SESSION['messageType'] = 'danger';
        }

        header('Location: ../?view=finance/configuration_frais');
        exit();

    default:
        session_start();
        $_SESSION['message'] = 'Erreur: Action non reconnue';
        $_SESSION['messageType'] = 'danger';
        header('Location: ../?view=finance/configuration_frais');
        exit();
}
