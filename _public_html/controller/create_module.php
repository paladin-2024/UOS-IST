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
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $nomMod = isset($_POST['nomMod']) ? trim($_POST['nomMod']) : '';
    $package = isset($_POST['package']) ? trim($_POST['package']) : '';

    // Validation du champ nomMod
    if (empty($nomMod) || empty($package)) {
        // Message d'erreur si le nom est vide
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le nom du module est requis.'
            }).then(() => {
                window.location.href = '../configuration/modules';
            });
        </script>";
        exit();
    }

    // Vérifier les doublons pour le nom du module
    if ($module->checkDuplicateModule($nomMod, $package)) {
        // Message d'erreur pour le doublon
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Un module avec ce nom existe déjà.'
            }).then(() => {
                window.location.href = '../configuration/modules';
            });
        </script>";
        exit();
    }

    // Appeler la fonction addModule si aucun doublon n'est trouvé
    if ($module->addModule($nomMod, $package)) {
        // Enregistrer dans le journal
        $journal->enregistrerAction(
            'CREATE',
            'Modules',
            "Création du module: $nomMod (Package: $package)",
            $_SESSION['id'] ?? null,
            $_SESSION['nom'] ?? null,
            'module',
            null,
            null,
            ['nomMod' => $nomMod, 'package' => $package],
            'succes'
        );
        
        // Redirection avec succès et message Swal
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Module ajouté avec succès.'
            }).then(() => {
                window.location.href = '../configuration/modules';
            });
        </script>";
    } else {
        // Enregistrer l'erreur dans le journal
        $journal->enregistrerAction(
            'CREATE',
            'Modules',
            "Erreur lors de la création du module: $nomMod",
            $_SESSION['id'] ?? null,
            $_SESSION['nom'] ?? null,
            'module',
            null,
            null,
            ['nomMod' => $nomMod, 'package' => $package],
            'erreur',
            'Erreur lors de l\'ajout du module'
        );
        
        // Message d'erreur avec Swal
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout du module.'
            }).then(() => {
                window.location.href = '../configuration/modules';
            });
        </script>";
    }
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../configuration/modules");
    exit();
}
