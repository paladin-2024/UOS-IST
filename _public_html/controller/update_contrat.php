<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php'; // Include the 405 error page
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/models/Service.php';

// Create instances of the necessary classes
$agent = new Agent();
$service = new Service();

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve the ID of the contract to modify
    $idContratAgent = isset($_POST['idContratAgent']) ? intval($_POST['idContratAgent']) : 0;

    // Check if the ID is valid
    if ($idContratAgent <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID du contrat invalide.'
            }).then(() => {
                window.location.href = '../grh/agent.contrat.add';
            });
        </script>";
        exit();
    }

    // Retrieve form data
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $typeContrat = isset($_POST['typeContrat']) ? trim($_POST['typeContrat']) : '';
    $dateDebut = isset($_POST['dateDebut']) ? $_POST['dateDebut'] : '';
    $dateFin = isset($_POST['dateFin']) ? $_POST['dateFin'] : '';
    $fonction = isset($_POST['fonction']) ? trim($_POST['fonction']) : '';
    $salaireDeBase = isset($_POST['salaireDeBase']) ? floatval($_POST['salaireDeBase']) : 0.0;
    $transport = isset($_POST['transport']) ? floatval($_POST['transport']) : 0.0;
    $logement = isset($_POST['logement']) ? floatval($_POST['logement']) : 0.0;
    $anciennete = isset($_POST['anciennete']) ? floatval($_POST['anciennete']) : 0.0;
    $serviceId = isset($_POST['serviceId']) ? intval($_POST['serviceId']) : 0;

    // Validate required fields
    if (empty($designation) || empty($typeContrat) || empty($dateDebut) || $serviceId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La désignation, le type de contrat, la date de début et le service sont obligatoires.'
            }).then(() => {
                window.location.href = '../grh/agent.contrat.add';
            });
        </script>";
        exit();
    }

    // Check if the service exists
    if (!$service->getServiceById($serviceId)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le service sélectionné est invalide.'
            }).then(() => {
                window.location.href = '../grh/agent.contrat.add';
            });
        </script>";
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        // Call the function to update the contract
        if ($agent->updateContract($idContratAgent, $designation, $typeContrat, $dateDebut, $dateFin, $fonction, $salaireDeBase, $transport, $logement, $anciennete)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Contrat modifié avec succès.'
                }).then(() => {
                    window.location.href = '../grh/agent.contrat.edit';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de la modification du contrat.'
                }).then(() => {
                    window.location.href = '../grh/agent.contrat.edit';
                });
            </script>";
        }
    }else{
        // Call the function to update the contract
        if ($agent->updateContract($idContratAgent, $designation, $typeContrat, $dateDebut, $dateFin, $fonction, $salaireDeBase, $transport, $logement, $anciennete)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Contrat modifié avec succès.'
                }).then(() => {
                    window.location.href = '../grh/agent.contrat.add';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de la modification du contrat.'
                }).then(() => {
                    window.location.href = '../grh/agent.contrat.add';
                });
            </script>";
        }
    }
} else {
    // Redirect if accessed directly without form submission
    header("Location: ../grh/agent.contrat.add");
    exit();
}
?>