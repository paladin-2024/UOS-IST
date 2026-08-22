<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$universite = new Universite();
$user = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $managerId = isset($_POST['editManagerId']) ? intval($_POST['editManagerId']) : 0;
    $userId = isset($_POST['editUserId']) ? intval($_POST['editUserId']) : 0;
    $fonction = isset($_POST['editFonction']) ? trim($_POST['editFonction']) : '';
    $anneeAcadId = isset($_POST['idAnnee']) ? intval($_POST['idAnnee']) : 0;
    
    // Nouveaux champs
    $est_chef = isset($_POST['editEstChef']) ? intval($_POST['editEstChef']) : 0;
    $date_debut = isset($_POST['editDateDebut']) ? $_POST['editDateDebut'] : null;
    $date_fin = isset($_POST['editDateFin']) ? $_POST['editDateFin'] : null;
    $telephone = isset($_POST['editManagerTelephone']) ? trim($_POST['editManagerTelephone']) : null;
    $email = isset($_POST['editManagerEmail']) ? trim($_POST['editManagerEmail']) : null;

    $getUser = $user->getUserById($userId)->fetch();
    $noms = $getUser['nomUser'];

    // Validate inputs
    if ($managerId <= 0 || $userId <= 0 || empty($fonction) || $anneeAcadId <= 0 || empty($noms)) {
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

    // Handle file upload for new signature
    $signaturePath = null;
    if (isset($_FILES['editSignature']) && $_FILES['editSignature']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/';

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $timestamp = time();
        $extension = pathinfo($_FILES['editSignature']['name'], PATHINFO_EXTENSION);
        $signaturePath = "SIGNATURE_" . $timestamp . '.' . $extension;
        $fullPath = $uploadDir . $signaturePath;

        // Move the uploaded file to the designated directory
        if (!move_uploaded_file($_FILES['editSignature']['tmp_name'], $fullPath)) {
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
    }

    try {
        $conn = Connexion::getInstance()->getPDO();
        
        // Récupérer la section du manager
        $getSection = "SELECT section_idsection FROM responsable_section WHERE idresponsable_section = :managerId";
        $stmt = $conn->prepare($getSection);
        $stmt->bindParam(':managerId', $managerId);
        $stmt->execute();
        $sectionData = $stmt->fetch(PDO::FETCH_ASSOC);
        $sectionId = $sectionData['section_idsection'];
        
        // Si c'est un chef de section, désactiver les autres chefs
        if ($est_chef == 1) {
            $updateQuery = "UPDATE responsable_section 
                           SET est_chef = 0 
                           WHERE section_idsection = :sectionId 
                           AND annee_acad_idannee_acad = :anneeAcadId
                           AND idresponsable_section != :managerId";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bindParam(':sectionId', $sectionId);
            $stmt->bindParam(':anneeAcadId', $anneeAcadId);
            $stmt->bindParam(':managerId', $managerId);
            $stmt->execute();
        }
        
        // Mettre à jour le manager
        $query = "UPDATE responsable_section 
                  SET noms = :noms, 
                      fonction = :fonction, 
                      \"idUser\" = :\"idUser\", 
                      annee_acad_idannee_acad = :anneeAcadId,
                      est_chef = :est_chef,
                      date_debut = :date_debut,
                      date_fin = :date_fin,
                      telephone = :telephone,
                      email = :email";
        
        // Ajouter la signature seulement si une nouvelle est fournie
        if ($signaturePath !== null) {
            $query .= ", signature = :signature";
        }
        
        $query .= " WHERE idresponsable_section = :managerId";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':noms', $noms);
        $stmt->bindParam(':fonction', $fonction);
        $stmt->bindParam(':idUser', $userId);
        $stmt->bindParam(':anneeAcadId', $anneeAcadId);
        $stmt->bindParam(':est_chef', $est_chef);
        $stmt->bindParam(':date_debut', $date_debut);
        $stmt->bindParam(':date_fin', $date_fin);
        $stmt->bindParam(':telephone', $telephone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':managerId', $managerId);
        
        if ($signaturePath !== null) {
            $stmt->bindParam(':signature', $signaturePath);
        }
        
        if ($stmt->execute()) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Responsable mis à jour avec succès.'
                }).then(() => {
                    window.location.href = '../configuration/faculte';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de la mise à jour du responsable.'
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