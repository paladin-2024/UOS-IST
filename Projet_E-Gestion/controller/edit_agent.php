<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/models/Structure.php';
require_once dirname(__DIR__) . '/models/Grade.php';
require_once dirname(__DIR__) . '/models/Service.php';

// Créer une instance des classes nécessaires
$agent = new Agent();
$structure = new Structure();
$grade = new Grade();
$service = new Service();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $redirectPage = isset($_POST['editAgentBtn2']) ? 'agent.edit' : 'agent.add.rapide';

    // Récupérer les données du formulaire
    $idAgent = isset($_POST['idAgent']) ? intval($_POST['idAgent']) : 0;
    $noms = isset($_POST['noms']) ? trim($_POST['noms']) : '';
    $lieuNaissance = isset($_POST['lieuNaissance']) ? trim($_POST['lieuNaissance']) : '';
    $dateNaissance = isset($_POST['dateNaissance']) ? $_POST['dateNaissance'] : '';
    $sexe = isset($_POST['sexe']) ? trim($_POST['sexe']) : '';
    $etatCivil = isset($_POST['etatCivil']) ? trim($_POST['etatCivil']) : '';
    $niveauEtude = isset($_POST['niveauEtude']) ? trim($_POST['niveauEtude']) : '';
    $idStructure = isset($_POST['idStructure']) ? intval($_POST['idStructure']) : 0;
    $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $codeAgent = isset($_POST['codeAgent']) ? trim($_POST['codeAgent']) : '';
    $matricule = isset($_POST['matricule']) ? trim($_POST['matricule']) : '';
    $type_agent = isset($_POST['type_agent']) ? trim($_POST['type_agent']) : '';
    $grade_id = isset($_POST['grade_id']) && !empty($_POST['grade_id']) ? intval($_POST['grade_id']) : null;
    $idService = isset($_POST['idService']) && !empty($_POST['idService']) ? intval($_POST['idService']) : null;
    $photo = isset($_POST['pictureAgent']) ? trim($_POST['pictureAgent']) : '';

    // Validation des champs requis
    if (empty($noms) || empty($dateNaissance) || $idStructure <= 0 || empty($type_agent) || $idAgent <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Les noms, la date de naissance, le type d\'agent et la structure sont obligatoires.'
            }).then(() => {
                window.location.href = '../grh/{$redirectPage}';
            });
        </script>";
        exit();
    }

    // Vérifier si l'agent existe
    $agentExists = $agent->getAgentById($idAgent);
    if (!$agentExists) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'L\'agent sélectionné n\'existe pas.'
            }).then(() => {
                window.location.href = '../grh/{$redirectPage}';
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
                window.location.href = '../grh/{$redirectPage}';
            });
        </script>";
        exit();
    }
    
    // Vérifier si le grade existe (si fourni)
    if ($grade_id !== null) {
        $gradeInfo = $grade->getGradeById($grade_id);
        if (!$gradeInfo) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Le grade sélectionné est invalide.'
                }).then(() => {
                    window.location.href = '../grh/{$redirectPage}';
                });
            </script>";
            exit();
        }
        
        // Vérifier que le grade correspond au type d'agent
        if ($gradeInfo['type_agent'] !== $type_agent) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Le grade sélectionné ne correspond pas au type d\'agent.'
                }).then(() => {
                    window.location.href = '../grh/{$redirectPage}';
                });
            </script>";
            exit();
        }
    }
    
    // Vérifier si le service existe (si fourni)
    if ($idService !== null) {
        $serviceInfo = $service->getServiceById($idService);
        if (!$serviceInfo) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Le service sélectionné est invalide.'
                }).then(() => {
                    window.location.href = '../grh/{$redirectPage}';
                });
            </script>";
            exit();
        }
        
        // Vérifier que le service appartient à la structure sélectionnée
        if ($serviceInfo['Structure_idStructure'] != $idStructure) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Le service sélectionné n\'appartient pas à la structure choisie.'
                }).then(() => {
                    window.location.href = '../grh/{$redirectPage}';
                });
            </script>";
            exit();
        }
    }


    $photoPath = '';
    // Vérifier si une image a été envoyée
    if (isset($_POST['croppedImage']) && !empty($_POST['croppedImage'])) {
        $croppedImageData = $_POST['croppedImage'];

        // Retirer le préfixe "data:image/png;base64," si présent
        $imageData = base64_decode(str_replace('data:image/png;base64,', '', $croppedImageData));

        // Créer une ressource image à partir de la chaîne de données
        $imageResource = imagecreatefromstring($imageData);
        if (!$imageResource) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de la création de l\'image à partir des données.'
                }).then(() => {
                    window.location.href = '../grh/{$redirectPage}';
                });
            </script>";
            exit();
        }

        // Définir le chemin où enregistrer l'image
        $uploadDir = '../uploads/agents/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Générer un nom de fichier unique pour l'image .jpg
        $fileName = 'agent_' . $idAgent . '_cropped_' . time() . '.jpg';
        $filePath = $uploadDir . $fileName;

        // Enregistrer l'image au format .jpg
        if (imagejpeg($imageResource, $filePath, 60)) { // 90 est la qualité de l'image (0-100)
            $photoPath = '../uploads/agents/' . $fileName;
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de la sauvegarde de l\'image rognée en format JPG.'
                }).then(() => {
                    window.location.href = '../grh/{$redirectPage}';
                });
            </script>";
            exit();
        }

        // Libérer la mémoire
        imagedestroy($imageResource);
    }else{
        $fileName=$_POST['pictureAgent'];
    }

    // Appeler la fonction de mise à jour d'agent
    if ($agent->updateAgent($idAgent, $noms, $lieuNaissance, $dateNaissance, $sexe, $etatCivil, $niveauEtude, $telephone, $email, $codeAgent, $matricule, $type_agent, $grade_id, $fileName, $idStructure, $idService)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Agent mis à jour avec succès.'
            }).then(() => {
                window.location.href = '../grh/{$redirectPage}';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour de l\'agent.'
            }).then(() => {
                window.location.href = '../grh/{$redirectPage}';
            });
        </script>";
    }
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../grh/agent.add");
    exit();
}
?>
