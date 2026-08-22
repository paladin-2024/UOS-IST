<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$universite = new Universite();
$user = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userId = isset($_POST['userId']) ? intval($_POST['userId']) : 0;
    $fonction = isset($_POST['fonction']) ? trim($_POST['fonction']) : '';
    $sectionId = isset($_POST['sectionId']) ? intval($_POST['sectionId']) : 0;
    $anneeAcadId = isset($_POST['idAnnee']) ? intval($_POST['idAnnee']) : 0;
    
    // Nouveaux champs
    $est_chef = isset($_POST['est_chef']) ? intval($_POST['est_chef']) : 0;
    $date_debut = isset($_POST['date_debut']) ? $_POST['date_debut'] : null;
    $date_fin = isset($_POST['date_fin']) ? $_POST['date_fin'] : null;
    $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : null;
    $email = isset($_POST['email']) ? trim($_POST['email']) : null;

    $getUser = $user->getUserById($userId)->fetch();
    $noms = $getUser['nomUser'];

    // Validate inputs
    if ($userId <= 0 || empty($fonction) || $sectionId <= 0 || $anneeAcadId <= 0 || empty($noms)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../configuration/faculte';
            });
        </script>";
        exit();
    }

    // Handle file upload for signature
    $signature = $_FILES['signature'];
    $signaturePath = '';

    if ($signature['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/';

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $timestamp = time();
        $extension = pathinfo($signature['name'], PATHINFO_EXTENSION);
        $signaturePath = "SIGNATURE_" . $timestamp . '.' . $extension;
        $fullPath = $uploadDir . $signaturePath;

        // Move the uploaded file to the designated directory
        if (!move_uploaded_file($signature['tmp_name'], $fullPath)) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors du téléchargement de la signature.'
                }).then(() => {
                    window.location.href = '../configuration/faculte';
                });
            </script>";
            exit();
        }
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur avec le fichier de signature.'
            }).then(() => {
                window.location.href = '../configuration/faculte';
            });
        </script>";
        exit();
    }

    try {
        $conn = Connexion::getInstance()->getPDO();
        
        // Si c'est un chef de section, désactiver les autres chefs
        if ($est_chef == 1) {
            $updateQuery = "UPDATE responsable_section 
                           SET est_chef = 0 
                           WHERE section_idsection = :sectionId 
                           AND annee_acad_idannee_acad = :anneeAcadId";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bindParam(':sectionId', $sectionId);
            $stmt->bindParam(':anneeAcadId', $anneeAcadId);
            $stmt->execute();
        }
        
        // Insérer le nouveau manager
        $query = "INSERT INTO responsable_section
                  (noms, fonction, signature, \"idUser\", section_idsection, annee_acad_idannee_acad,
                   est_chef, date_debut, date_fin, telephone, email)
                  VALUES (:noms, :fonction, :signature, :idUser, :sectionId, :anneeAcadId,
                          :est_chef, :date_debut, :date_fin, :telephone, :email)";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':noms', $noms);
        $stmt->bindParam(':fonction', $fonction);
        $stmt->bindParam(':signature', $signaturePath);
        $stmt->bindParam(':idUser', $userId);
        $stmt->bindParam(':sectionId', $sectionId);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId);
        $stmt->bindParam(':est_chef', $est_chef);
        $stmt->bindParam(':date_debut', $date_debut);
        $stmt->bindParam(':date_fin', $date_fin);
        $stmt->bindParam(':telephone', $telephone);
        $stmt->bindParam(':email', $email);
        
        if ($stmt->execute()) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Responsable ajouté avec succès.'
                }).then(() => {
                    window.location.href = '../configuration/faculte';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de l\'ajout du responsable.'
                }).then(() => {
                    window.location.href = '../configuration/faculte';
                });
            </script>";
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../configuration/faculte';
            });
        </script>";
    }
} else {
    header("Location: ../configuration/faculte");
    exit();
}
?>