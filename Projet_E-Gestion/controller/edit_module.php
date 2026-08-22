<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Module.php';

// Charger le journal serveur
require_once dirname(__DIR__) . '/models/JournalServeur.php';
$journal = new JournalServeur();

// Créer une instance de la classe Module
$module = new Module();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['idMod'])) {
    // Récupérer les données du formulaire
    $idMod = $_POST['idMod'];
    $nomMod = trim($_POST['nomMod']);
    $package = trim($_POST['package']);

    // Vérifier si les champs requis sont remplis
    if (empty($idMod) || empty($nomMod) | empty($package)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs doivent être remplis.'
            }).then(() => {
                window.location.href = '../configuration/modules';
            });
        </script>";
        exit();
    }

    // Récupérer les données avant modification
    $donneeAvant = $journal->obtenirDonneeAvant('module', $idMod);

    // Appeler la méthode updateModule pour mettre à jour le module
    $success = $module->updateModule($idMod, $nomMod, $package);

    if ($success) {
        // Enregistrer dans le journal
        $journal->enregistrerAction(
            'UPDATE',
            'Modules',
            "Modification du module: $nomMod (Package: $package)",
            $_SESSION['id'] ?? null,
            $_SESSION['nom'] ?? null,
            'module',
            $idMod,
            $donneeAvant,
            ['nomMod' => $nomMod, 'package' => $package],
            'succes'
        );
        
        // Message de succès
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Module mis à jour avec succès.'
            }).then(() => {
                window.location.href = '../configuration/modules';
            });
        </script>";
    } else {
        // Enregistrer l'erreur dans le journal
        $journal->enregistrerAction(
            'UPDATE',
            'Modules',
            "Erreur lors de la modification du module: $nomMod",
            $_SESSION['id'] ?? null,
            $_SESSION['nom'] ?? null,
            'module',
            $idMod,
            $donneeAvant,
            ['nomMod' => $nomMod, 'package' => $package],
            'erreur',
            'Erreur lors de la mise à jour du module'
        );
        
        // Message d'erreur en cas d'échec
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur s\'est produite lors de la mise à jour du module.'
            }).then(() => {
                window.location.href = '../configuration/modules';
            });
        </script>";
    }
} else {
    // Redirection en cas d'accès direct au fichier sans soumission du formulaire
    header("Location: ../configuration/modules");
    exit();
}
