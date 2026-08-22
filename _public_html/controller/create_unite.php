<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db = Connexion::getInstance()->getPDO();
        
        // Récupérer les données du formulaire
        $code_unite = trim($_POST['code_unite']);
        $libelle_unite = trim($_POST['libelle_unite']);
        $symbole_unite = trim($_POST['symbole_unite']);
        $id_user = $_SESSION['id'];
        
        // Validation des données
        if (empty($code_unite) || empty($libelle_unite)) {
            throw new Exception("Le code et le libellé sont obligatoires.");
        }
        
                // Vérifier si le code existe déjà
                $stmt = $db->prepare("SELECT id_unite FROM unite_mesure WHERE code_unite = :code_unite");
                $stmt->bindParam(':code_unite', $code_unite);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    throw new Exception("Ce code d'unité existe déjà.");
                }
                
                // Insérer la nouvelle unité
                $stmt = $db->prepare("INSERT INTO unite_mesure (code_unite, libelle_unite, symbole_unite, actif, id_user_creation, date_creation) 
                                     VALUES (:code_unite, :libelle_unite, :symbole_unite, 1, :id_user, NOW())");
                
                $stmt->bindParam(':code_unite', $code_unite);
                $stmt->bindParam(':libelle_unite', $libelle_unite);
                $stmt->bindParam(':symbole_unite', $symbole_unite);
                $stmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
                
                $stmt->execute();
                
                // Rediriger avec un message de succès
                $_SESSION['success_message'] = "Unité de mesure créée avec succès.";
                header('Location: ../configuration/unite.list');
                exit();
                
            } catch (Exception $e) {
                $_SESSION['error_message'] = $e->getMessage();
                header('Location: ../configuration/unite.list');
                exit();
            }
        } else {
            // Redirection si accès direct au fichier
            header('Location: ../index');
            exit();
        }
        