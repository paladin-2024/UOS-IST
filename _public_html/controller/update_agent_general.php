<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $idAgent = isset($_POST['idAgent']) ? intval($_POST['idAgent']) : 0;
    $type_agent = isset($_POST['type_agent']) ? trim($_POST['type_agent']) : '';
    $matricule = isset($_POST['matricule']) ? trim($_POST['matricule']) : '';
    $noms = isset($_POST['noms']) ? trim($_POST['noms']) : '';
    $codeAgent = isset($_POST['codeAgent']) ? trim($_POST['codeAgent']) : '';
    $returnTab = isset($_POST['returnTab']) ? trim($_POST['returnTab']) : 'general';

    // Validation des données
    if (empty($idAgent) || empty($type_agent) || empty($matricule) || empty($noms)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../grh/agent.edition&searchType=code&search={$codeAgent}&tab={$returnTab}';
            });
        </script>";
        exit();
    }

    // Traitement de la photo
    $photo = null;
    $photo_actuelle = isset($_POST['photo_actuelle']) ? $_POST['photo_actuelle'] : '';
    $remove_photo = isset($_POST['remove_photo']) ? true : false;

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['photo']['type'], $allowed)) {
            $filename = time() . '_' . basename($_FILES['photo']['name']);
            $upload_path = dirname(__DIR__) . '/uploads/agents/' . $filename;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                $photo = $filename;
                
                // Supprimer l'ancienne photo si elle existe
                if (!empty($photo_actuelle) && file_exists(dirname(__DIR__) . '/uploads/agents/' . $photo_actuelle)) {
                    unlink(dirname(__DIR__) . '/uploads/agents/' . $photo_actuelle);
                }
            }
        }
    } elseif ($remove_photo && !empty($photo_actuelle)) {
        // Supprimer la photo actuelle
        if (file_exists(dirname(__DIR__) . '/uploads/agents/' . $photo_actuelle)) {
            unlink(dirname(__DIR__) . '/uploads/agents/' . $photo_actuelle);
        }
        $photo = '';
    } else {
        $photo = $photo_actuelle;
    }

    try {
        // Connexion à la base de données
        $pdo = Connexion::getInstance()->getPDO();
        
        // Préparer et exécuter la requête SQL directe
        $query = "UPDATE agent SET 
                    type_agent = :type_agent, 
                    matricule = :matricule, 
                    noms = :noms";
        
        // Ajouter la photo à la requête seulement si elle a été modifiée
        if ($photo !== null) {
            $query .= ", photo = :photo";
        }
        
        $query .= " WHERE idAgent = :idAgent";
        
        $stmt = $pdo->prepare($query);
        
        $params = [
            ':type_agent' => $type_agent,
            ':matricule' => $matricule,
            ':noms' => $noms,
            ':idAgent' => $idAgent
        ];
        
        // Ajouter la photo aux paramètres seulement si elle a été modifiée
        if ($photo !== null) {
            $params[':photo'] = $photo;
        }
        
        $result = $stmt->execute($params);
        
        if ($result) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Les informations générales ont été mises à jour avec succès.'
                }).then(() => {
                    window.location.href = '../grh/agent.edition&searchType=code&search={$codeAgent}&tab={$returnTab}';
                });
            </script>";
        } else {
            throw new Exception("Erreur lors de la mise à jour des informations générales.");
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '{$e->getMessage()}'
            }).then(() => {
                window.location.href = '../grh/agent.edition&searchType=code&search={$codeAgent}&tab={$returnTab}';
            });
        </script>";
    }
    exit();
}

// Rediriger si accès direct
header('Location: ../index');
exit();
