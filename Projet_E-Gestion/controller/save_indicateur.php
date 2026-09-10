<?php
require_once dirname(__DIR__) . '/views/405.php';
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/utils/Security.php';

AppSecurity::verifySession();

$connexion = Connexion::getInstance()->getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    try {
        switch ($action) {
            case 'ajouter':
                $nom = trim($_POST['nom'] ?? '');
                $idCategorie = !empty($_POST['Idcategorie']) ? intval($_POST['Idcategorie']) : null;
                $description = trim($_POST['description'] ?? '');

                if (empty($nom)) {
                    throw new Exception("Le nom de l'indicateur est obligatoire");
                }

                $stmt = $connexion->prepare("
                    INSERT INTO indicateur (nom, \"Idcategorie\", description)
                    VALUES (:nom, :idCategorie, :description)
                ");
                $stmt->bindParam(':nom', $nom);
                $stmt->bindParam(':idCategorie', $idCategorie, $idCategorie === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt->bindParam(':description', $description);
                $stmt->execute();

                $_SESSION['message'] = "L'indicateur a été ajouté avec succès";
                $_SESSION['messageType'] = 'success';
                break;

            case 'modifier':
                $id = intval($_POST['id'] ?? 0);
                $nom = trim($_POST['nom'] ?? '');
                $idCategorie = !empty($_POST['Idcategorie']) ? intval($_POST['Idcategorie']) : null;
                $description = trim($_POST['description'] ?? '');

                if ($id <= 0) {
                    throw new Exception("ID d'indicateur invalide");
                }
                if (empty($nom)) {
                    throw new Exception("Le nom de l'indicateur est obligatoire");
                }

                $stmt = $connexion->prepare("
                    UPDATE indicateur
                    SET nom = :nom, \"Idcategorie\" = :idCategorie, description = :description
                    WHERE \"idIndicateur\" = :id
                ");
                $stmt->bindParam(':nom', $nom);
                $stmt->bindParam(':idCategorie', $idCategorie, $idCategorie === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':id', $id);
                $stmt->execute();

                $_SESSION['message'] = "L'indicateur a été mis à jour avec succès";
                $_SESSION['messageType'] = 'success';
                break;

            case 'supprimer':
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception("ID d'indicateur invalide");
                }

                $stmt = $connexion->prepare("DELETE FROM valeur_indicateur WHERE \"idIndicateur\" = :id");
                $stmt->bindParam(':id', $id);
                $stmt->execute();

                $stmt = $connexion->prepare("DELETE FROM indicateur WHERE \"idIndicateur\" = :id");
                $stmt->bindParam(':id', $id);
                $stmt->execute();

                $_SESSION['message'] = "L'indicateur a été supprimé avec succès";
                $_SESSION['messageType'] = 'success';
                break;

            default:
                throw new Exception('Action non reconnue');
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'Erreur: ' . $e->getMessage();
        $_SESSION['messageType'] = 'danger';
    }
}

header('Location: ../indicateur/indicateur.add');
exit();
