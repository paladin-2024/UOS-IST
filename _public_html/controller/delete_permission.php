<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Module.php';

// Charger le journal serveur
require_once dirname(__DIR__) . '/models/JournalServeur.php';
$journal = new JournalServeur();

// Créer une instance de la classe Module
$permission = new Module();

// Vérifier si l'ID de la permission est passé en paramètre
if (isset($_GET['idPerm'])) {
    $idPerm = intval($_GET['idPerm']);
    $module=$permission->getModuleIdByPermission($idPerm);

    // Vérifier si l'ID est valide
    if ($idPerm > 0) {
        // Récupérer les données avant suppression
        $donneeAvant = $journal->obtenirDonneeAvant('permission', $idPerm);
        
        // Appeler la fonction pour supprimer la permission
        if ($permission->deletePermission($idPerm)) {
            // Enregistrer dans le journal
            $journal->enregistrerAction(
                'DELETE',
                'Permissions',
                "Suppression de la permission ID: $idPerm",
                $_SESSION['id'] ?? null,
                $_SESSION['nom'] ?? null,
                'permission',
                $idPerm,
                $donneeAvant,
                null,
                'succes'
            );
            
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Permission supprimée avec succès.'
                }).then(() => {
                    window.location.href = '../configuration/permissions&m={$module}';
                });
            </script>";
        } else {
            // Enregistrer l'erreur dans le journal
            $journal->enregistrerAction(
                'DELETE',
                'Permissions',
                "Erreur lors de la suppression de la permission ID: $idPerm",
                $_SESSION['id'] ?? null,
                $_SESSION['nom'] ?? null,
                'permission',
                $idPerm,
                $donneeAvant,
                null,
                'erreur',
                'Erreur lors de la suppression de la permission'
            );
            
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de la suppression de la permission.'
                }).then(() => {
                    window.location.href = '../configuration/permissions&m={$module}';
                });
            </script>";
        }
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de permission invalide.'
            }).then(() => {
                window.location.href = '../configuration/modules';
            });
        </script>";
    }
} else {
    // Rediriger si aucun ID de permission n'est fourni
    header("Location: ../configuration/modules");
    exit();
}