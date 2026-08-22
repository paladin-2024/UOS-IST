<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Service.php';

// Créer une instance de la classe Service
$service = new Service();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if(isset($_POST['editServiceBtn2'])){
        // Récupérer l'ID du service à modifier
        $id = isset($_POST['idService']) ? intval($_POST['idService']) : 0;

        // Vérifier si l'ID est valide
        if ($id <= 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'ID du service invalide.'
                }).then(() => {
                    window.location.href = '../configuration/service.edit';
                });
            </script>";
            exit();
        }

        // Récupérer les données du formulaire
        $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
        $responsable = isset($_POST['responsable']) ? trim($_POST['responsable']) : '';
        $idStructure = isset($_POST['idStructure']) ? intval($_POST['idStructure']) : 0;

        // Validation des champs requis
        if (empty($designation) || empty($responsable) || $idStructure <= 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'La désignation, le responsable et la structure sont obligatoires.'
                }).then(() => {
                    window.location.href = '../configuration/service.edit';
                });
            </script>";
            exit();
        }

        // Vérifier les doublons pour la désignation dans la même structure
        if ($service->checkDuplicateService($designation, $idStructure, $id)) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Un service avec ce nom existe déjà dans cette structure.'
                }).then(() => {
                    window.location.href = '../configuration/service.edit';
                });
            </script>";
            exit();
        }

        // Appeler la fonction d'édition du service
        if ($service->updateService($id, $designation, $responsable, $idStructure)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Service modifié avec succès.'
                }).then(() => {
                    window.location.href = '../configuration/service.edit';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de la modification du service.'
                }).then(() => {
                    window.location.href = '../configuration/service.edit';
                });
            </script>";
        }
    }else{
    // Récupérer l'ID du service à modifier
    $id = isset($_POST['idService']) ? intval($_POST['idService']) : 0;

    // Vérifier si l'ID est valide
    if ($id <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID du service invalide.'
            }).then(() => {
                window.location.href = '../configuration/service.add';
            });
        </script>";
        exit();
    }

    // Récupérer les données du formulaire
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $responsable = isset($_POST['responsable']) ? trim($_POST['responsable']) : '';
    $idStructure = isset($_POST['idStructure']) ? intval($_POST['idStructure']) : 0;

    // Validation des champs requis
    if (empty($designation) || empty($responsable) || $idStructure <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La désignation, le responsable et la structure sont obligatoires.'
            }).then(() => {
                window.location.href = '../configuration/service.add';
            });
        </script>";
        exit();
    }

    // Vérifier les doublons pour la désignation dans la même structure
    if ($service->checkDuplicateService($designation, $idStructure, $id)) {
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

    // Appeler la fonction d'édition du service
    if ($service->updateService($id, $designation, $responsable, $idStructure)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Service modifié avec succès.'
            }).then(() => {
                window.location.href = '../configuration/service.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la modification du service.'
            }).then(() => {
                window.location.href = '../configuration/service.add';
            });
        </script>";
    }
}
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../configuration/service.add");
    exit();
}
?>
