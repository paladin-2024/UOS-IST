<?php
session_start();
error_reporting(E_ALL); ini_set("display_errors", 1);
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté et est administrateur
if (!isset($_SESSION['id']) || $_SESSION['idRole'] != 1) {
    header('Location: ../login');
    exit();
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $id_user = isset($_POST['id_user']) ? intval($_POST['id_user']) : 0;
        $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
        $id_user_creation = $_SESSION['id'];
        
        // Validation
        if ($id_user <= 0) {
            throw new Exception("Utilisateur invalide.");
        }
        
        // Traiter chaque dépôt
        foreach ($permissions as $id_depot => $perm) {
            $id_depot = intval($id_depot);
            $peut_consulter = isset($perm['peut_consulter']) ? 1 : 0;
            $peut_modifier = isset($perm['peut_modifier']) ? 1 : 0;
            $peut_valider = isset($perm['peut_valider']) ? 1 : 0;
            
            // Vérifier si une autorisation existe déjà
            $stmt = $db->prepare("SELECT id_autorisation FROM autorisation_depot 
                                WHERE id_user = :id_user AND id_depot = :id_depot");
            $stmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
            $stmt->bindParam(':id_depot', $id_depot, PDO::PARAM_INT);
            $stmt->execute();
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Mise à jour
                $stmt = $db->prepare("UPDATE autorisation_depot 
                                    SET peut_consulter = :peut_consulter,
                                        peut_modifier = :peut_modifier,
                                        peut_valider = :peut_valider
                                    WHERE id_autorisation = :id_autorisation");
                $stmt->bindParam(':peut_consulter', $peut_consulter, PDO::PARAM_INT);
                $stmt->bindParam(':peut_modifier', $peut_modifier, PDO::PARAM_INT);
                $stmt->bindParam(':peut_valider', $peut_valider, PDO::PARAM_INT);
                $stmt->bindParam(':id_autorisation', $existing['id_autorisation'], PDO::PARAM_INT);
            } else {
                // Insertion
                $stmt = $db->prepare("INSERT INTO autorisation_depot
                                    (id_user, id_depot, peut_consulter, peut_modifier, 
                                    peut_valider, id_user_creation)
                                    VALUES
                                    (:id_user, :id_depot, :peut_consulter, :peut_modifier,
                                    :peut_valider, :id_user_creation)");
                $stmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
                $stmt->bindParam(':id_depot', $id_depot, PDO::PARAM_INT);
                $stmt->bindParam(':peut_consulter', $peut_consulter, PDO::PARAM_INT);
                $stmt->bindParam(':peut_modifier', $peut_modifier, PDO::PARAM_INT);
                $stmt->bindParam(':peut_valider', $peut_valider, PDO::PARAM_INT);
                $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
            }
            
            $stmt->execute();
        }
        
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'Les autorisations ont été mises à jour avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.location.href = '../configuration/depot_permissions&user=" . $id_user . "';
            });
        </script>";
        
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.history.back();
            });
        </script>";
    }
}
