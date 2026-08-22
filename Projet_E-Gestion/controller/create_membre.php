<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';

// Créer une instance de la classe Agent
$agent = new Agent();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $agentId = isset($_POST['agentId']) ? intval($_POST['agentId']) : 0;
    $noms = isset($_POST['noms']) ? trim($_POST['noms']) : '';
    $sexe = isset($_POST['sexe']) ? trim($_POST['sexe']) : '';
    $dateNaissance = isset($_POST['dateNaissance']) ? $_POST['dateNaissance'] : '';
    $lieuNaissance = isset($_POST['lieuNaissance']) ? trim($_POST['lieuNaissance']) : '';
    $typeLiaison = isset($_POST['typeLiaison']) ? trim($_POST['typeLiaison']) : '';
    $idUser = $_SESSION['id']; // Assuming user ID is stored in session

    // Validation des champs requis
    if (empty($noms) || empty($dateNaissance) || $agentId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Les noms, la date de naissance et l\'agent sont obligatoires.'
            }).then(() => {
                window.location.href = '../grh/agent.famille.add';
            });
        </script>";
        exit();
    }

    // Appeler la fonction d'ajout de membre de la famille
    if ($agent->addFamilyMember($agentId, $noms, $sexe, $dateNaissance, $lieuNaissance, $typeLiaison, $idUser)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Membre de la famille ajouté avec succès.'
            }).then(() => {
                window.location.href = '../grh/agent.famille.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout du membre de la famille.'
            }).then(() => {
                window.location.href = '../grh/agent.famille.add';
            });
        </script>";
    }
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../grh/agent.famille.add");
    exit();
}
?>