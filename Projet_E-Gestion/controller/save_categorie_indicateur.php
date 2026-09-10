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
                $nomCategorie = trim($_POST['nomCategorie'] ?? '');

                if (empty($nomCategorie)) {
                    throw new Exception('Le nom de la catégorie est obligatoire');
                }

                $stmt = $connexion->prepare("SELECT \"idCategorie\" FROM categorie_indicateur WHERE \"nomCategorie\" = :nom");
                $stmt->bindParam(':nom', $nomCategorie);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    throw new Exception('Une catégorie avec ce nom existe déjà');
                }

                $stmt = $connexion->prepare("INSERT INTO categorie_indicateur (\"nomCategorie\") VALUES (:nom)");
                $stmt->bindParam(':nom', $nomCategorie);
                $stmt->execute();

                $_SESSION['message'] = 'La catégorie a été ajoutée avec succès';
                $_SESSION['messageType'] = 'success';
                break;

            case 'modifier':
                $id = intval($_POST['id'] ?? 0);
                $nomCategorie = trim($_POST['nomCategorie'] ?? '');

                if ($id <= 0) {
                    throw new Exception('ID de catégorie invalide');
                }
                if (empty($nomCategorie)) {
                    throw new Exception('Le nom de la catégorie est obligatoire');
                }

                $stmt = $connexion->prepare("SELECT \"idCategorie\" FROM categorie_indicateur WHERE \"nomCategorie\" = :nom AND \"idCategorie\" <> :id");
                $stmt->bindParam(':nom', $nomCategorie);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    throw new Exception('Une autre catégorie avec ce nom existe déjà');
                }

                $stmt = $connexion->prepare("UPDATE categorie_indicateur SET \"nomCategorie\" = :nom WHERE \"idCategorie\" = :id");
                $stmt->bindParam(':nom', $nomCategorie);
                $stmt->bindParam(':id', $id);
                $stmt->execute();

                $_SESSION['message'] = 'La catégorie a été mise à jour avec succès';
                $_SESSION['messageType'] = 'success';
                break;

            case 'supprimer':
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('ID de catégorie invalide');
                }

                $stmt = $connexion->prepare("UPDATE indicateur SET \"Idcategorie\" = NULL WHERE \"Idcategorie\" = :id");
                $stmt->bindParam(':id', $id);
                $stmt->execute();

                $stmt = $connexion->prepare("DELETE FROM categorie_indicateur WHERE \"idCategorie\" = :id");
                $stmt->bindParam(':id', $id);
                $stmt->execute();

                $_SESSION['message'] = 'La catégorie a été supprimée avec succès';
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

header('Location: ../indicateur/categorie.indicateur');
exit();
