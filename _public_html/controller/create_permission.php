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

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $idMod = isset($_POST['idMod']) ? intval($_POST['idMod']) : 0;
    $codePerm = isset($_POST['codePerm']) ? trim($_POST['codePerm']) : '';
    $nomPerm = isset($_POST['nomPerm']) ? trim($_POST['nomPerm']) : '';
    $descPerm = isset($_POST['descPerm']) ? trim($_POST['descPerm']) : '';

    // Validation des champs obligatoires
    if (empty($idMod) || empty($codePerm) || empty($nomPerm)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../configuration/permissions&m=$idMod';
            });
        </script>";
        exit();
    }

    // Vérifier les doublons pour la permission
    if ($permission->checkDuplicatePermission($codePerm, $nomPerm)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une permission similaire existe déjà.'
            }).then(() => {
                window.location.href = '../configuration/permissions&m=$idMod';
            });
        </script>";
        exit();
    }

    // Appeler la fonction addPermission si aucun doublon n'est trouvé
    if ($permission->addPermission($idMod, $codePerm, $nomPerm, $descPerm)) {
        // Enregistrer dans le journal
        $journal->enregistrerAction(
            'CREATE',
            'Permissions',
            "Création de la permission: $codePerm - $nomPerm",
            $_SESSION['id'] ?? null,
            $_SESSION['nom'] ?? null,
            'permission',
            null,
            null,
            ['codePerm' => $codePerm, 'nomPerm' => $nomPerm, 'descPerm' => $descPerm, 'idMod' => $idMod],
            'succes'
        );
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Permission ajoutée avec succès.'
            }).then(() => {
                window.location.href = '../configuration/permissions&m=$idMod';
            });
        </script>";
    } else {
        // Enregistrer l'erreur dans le journal
        $journal->enregistrerAction(
            'CREATE',
            'Permissions',
            "Erreur lors de la création de la permission: $codePerm",
            $_SESSION['id'] ?? null,
            $_SESSION['nom'] ?? null,
            'permission',
            null,
            null,
            ['codePerm' => $codePerm, 'nomPerm' => $nomPerm, 'idMod' => $idMod],
            'erreur',
            'Erreur lors de l\'ajout de la permission'
        );
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de la permission.'
            }).then(() => {
                window.location.href = '../configuration/permissions&m=$idMod';
            });
        </script>";
    }
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../configuration/modules");
    exit();
}
