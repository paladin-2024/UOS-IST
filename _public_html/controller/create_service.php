<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Service.php';
require_once dirname(__DIR__) . '/models/Structure.php';

// Créer une instance des classes nécessaires
$service = new Service();
$structure = new Structure();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $responsable = isset($_POST['responsable']) ? trim($_POST['responsable']) : '';
    $idStructure = isset($_POST['idStructure']) ? intval($_POST['idStructure']) : 0;

    // Validation des champs requis
    if (empty($designation) || $idStructure <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La désignation et la structure sont obligatoires.'
            }).then(() => {
                window.location.href = '../configuration/service.add';
            });
        </script>";
        exit();
    }

    // Vérifier si la structure existe
    $structureExists = $structure->checkStructureExists($idStructure);
    if (!$structureExists) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La structure sélectionnée est invalide.'
            }).then(() => {
                window.location.href = '../configuration/service.add';
            });
        </script>";
        exit();
    }

    // Vérifier les doublons pour le service
    if ($service->checkDuplicateService($designation, $idStructure)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Un service avec ce nom existe déjà dans cette structure.'
            }).then(() => {
                window.location.href = '../configuration/service.add';
            });
        </script>";
        exit();
    }

    // Appeler la fonction d'ajout de service
    if ($service->addService($designation, $responsable, $idStructure)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Service ajouté avec succès.'
            }).then(() => {
                window.location.href = '../configuration/service.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout du service.'
            }).then(() => {
                window.location.href = '../configuration/service.add';
            });
        </script>";
    }
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../configuration/service.add");
    exit();
}
?>
