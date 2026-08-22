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
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['idPerm'])) {
    // Récupérer les données du formulaire
    $idPerm = $_POST['idPerm'];
    $idMod = $_POST['idMod'];
    $codePerm = trim($_POST['codePerm']);
    $nomPerm = trim($_POST['nomPerm']);
    $descPerm = trim($_POST['descPerm']);

    // Vérifier si les champs requis sont remplis
    if (empty($idPerm) || empty($idMod)  || empty($codePerm) || empty($nomPerm) || empty($descPerm)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs doivent être remplis.'
            }).then(() => {
                window.location.href = '../configuration/permissions&m=$idMod';
            });
        </script>";
        exit();
    }

     // Récupérer les données avant modification
     $donneeAvant = $journal->obtenirDonneeAvant('permission', $idPerm);

     // Appeler la méthode updatePermission pour mettre à jour la permission
     $success = $permission->updatePermission($idPerm, $idMod, $codePerm, $nomPerm, $descPerm);

     if ($success) {
          // Enregistrer dans le journal
          $journal->enregistrerAction(
              'UPDATE',
              'Permissions',
              "Modification de la permission: $codePerm - $nomPerm",
              $_SESSION['id'] ?? null,
              $_SESSION['nom'] ?? null,
              'permission',
              $idPerm,
              $donneeAvant,
              ['codePerm' => $codePerm, 'nomPerm' => $nomPerm, 'descPerm' => $descPerm, 'idMod' => $idMod],
              'succes'
          );
         
         // Message de succès
         echo "<script>
             Swal.fire({
                 icon: 'success',
                 title: 'Succès',
                 text: 'Permission mise à jour avec succès.'
             }).then(() => {
                 window.location.href = '../configuration/permissions&m=$idMod';
             });
         </script>";
     } else {
         // Enregistrer l'erreur dans le journal
         $journal->enregistrerAction(
             'UPDATE',
             'Permissions',
             "Erreur lors de la modification de la permission: $codePerm",
             $_SESSION['id'] ?? null,
             $_SESSION['nom'] ?? null,
             'permission',
             $idPerm,
             $donneeAvant,
             ['codePerm' => $codePerm, 'nomPerm' => $nomPerm, 'idMod' => $idMod],
             'erreur',
             'Erreur lors de la mise à jour de la permission'
         );
         
         // Message d'erreur en cas d'échec
         echo "<script>
             Swal.fire({
                 icon: 'error',
                 title: 'Erreur',
                 text: 'Une erreur s\'est produite lors de la mise à jour de la permission.'
             }).then(() => {
                 window.location.href = '../configuration/permissions&m=$idMod';
             });
         </script>";
     }
} else {
    // Redirection en cas d'accès direct au fichier sans soumission du formulaire
    header("Location: ../configuration/modules");
    exit();
}
