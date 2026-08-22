<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$universite = new Universite();
$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orientationId = $_POST['editOrientationId'] ?? '';
    $designationOrientation = $_POST['editOrientationDesignation'] ?? '';
    $sectionId = $_POST['editSectionId'] ?? '';
    $chefDepartementId = $_POST['editChefDepartement'] ?? '';

    // Validate inputs
    if (empty($orientationId) || empty($designationOrientation) || empty($sectionId)) {
        $_SESSION['error'] = 'Tous les champs obligatoires doivent être remplis.';
        header("Location: ../index.php?view=configuration/orientation");
        exit();
    }

    try {
        // Commencer une transaction
        $conn = Connexion::getInstance()->getPDO();
        $conn->beginTransaction();

        // Update the orientation
        $result = $universite->updateOrientation($orientationId, $designationOrientation, $sectionId);

        if ($result) {
            // Si un chef de département est sélectionné
            if (!empty($chefDepartementId)) {
                // Récupérer l'année académique active
                $activeYear = $universite->getActiveAcademicYear();
                $activeYearId = $activeYear ? $activeYear['idannee_acad'] : null;

                if ($activeYearId) {
                    // Récupérer les informations de l'utilisateur
                    $userInfoStmt = $structure->getUserById($chefDepartementId);
                    $userInfo = $userInfoStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($userInfo) {
                        // Vérifier s'il existe déjà un chef pour cette orientation et cette année
                        $checkQuery = "SELECT idresponsable_orientation FROM responsable_orientation 
                                     WHERE orientation_idorientation = :orientationId 
                                     AND est_chef = 1 
                                     AND annee_acad_idannee_acad = :anneeId";
                        $checkStmt = $conn->prepare($checkQuery);
                        $checkStmt->execute([
                            'orientationId' => $orientationId,
                            'anneeId' => $activeYearId
                        ]);
                        $existingChef = $checkStmt->fetch();

                        // Si un chef existe déjà, le mettre à jour pour ne plus être chef
                        if ($existingChef) {
                            $updateQuery = "UPDATE responsable_orientation 
                                          SET est_chef = 0 
                                          WHERE idresponsable_orientation = :id";
                            $updateStmt = $conn->prepare($updateQuery);
                            $updateStmt->execute(['id' => $existingChef['idresponsable_orientation']]);
                        }

                        // Vérifier si cet utilisateur est déjà responsable pour cette orientation
                        $checkUserQuery = "SELECT idresponsable_orientation FROM responsable_orientation 
                                         WHERE orientation_idorientation = :orientationId 
                                         AND \"idUser\" = :userId 
                                         AND annee_acad_idannee_acad = :anneeId";
                        $checkUserStmt = $conn->prepare($checkUserQuery);
                        $checkUserStmt->execute([
                            'orientationId' => $orientationId,
                            'userId' => $chefDepartementId,
                            'anneeId' => $activeYearId
                        ]);
                        $existingUser = $checkUserStmt->fetch();

                        if ($existingUser) {
                            // Mettre à jour le responsable existant pour être chef
                            $updateUserQuery = "UPDATE responsable_orientation 
                                              SET est_chef = 1, 
                                                  fonction = 'Chef de Département' 
                                              WHERE idresponsable_orientation = :id";
                            $updateUserStmt = $conn->prepare($updateUserQuery);
                            $updateUserStmt->execute(['id' => $existingUser['idresponsable_orientation']]);
                        } else {
                            // Créer un nouveau responsable avec une signature par défaut
                            $insertQuery = "INSERT INTO responsable_orientation 
                                          (noms, fonction, signature, \"idUser\", orientation_idorientation, 
                                           annee_acad_idannee_acad, est_chef, date_debut) 
                                          VALUES (:noms, :fonction, :signature, :userId, :orientationId, 
                                                  :anneeId, :estChef, :dateDebut)";
                            $insertStmt = $conn->prepare($insertQuery);
                            $insertStmt->execute([
                                'noms' => $userInfo['nomUser'],
                                'fonction' => 'Chef de Département',
                                'signature' => 'default_signature.png', // Signature par défaut
                                'userId' => $chefDepartementId,
                                'orientationId' => $orientationId,
                                'anneeId' => $activeYearId,
                                'estChef' => 1,
                                'dateDebut' => date('Y-m-d')
                            ]);
                        }
                    }
                }
            }

            // Valider la transaction
            $conn->commit();
            $_SESSION['success'] = 'Orientation modifiée avec succès.';
        } else {
            throw new Exception('Erreur lors de la modification de l\'orientation.');
        }
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $conn->rollBack();
        $_SESSION['error'] = 'Erreur : ' . $e->getMessage();
    }

    header("Location: ../index.php?view=configuration/orientation");
    exit();
} else {
    header("Location: ../index.php?view=configuration/orientation");
    exit();
}
?>
