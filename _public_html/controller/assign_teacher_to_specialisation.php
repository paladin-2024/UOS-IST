<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assignSpecialisationBtn'])) {
    // Récupérer les données du formulaire
    $idAgent = isset($_POST['idAgent']) ? intval($_POST['idAgent']) : 0;
    $idSpecialisation = isset($_POST['idSpecialisation']) ? intval($_POST['idSpecialisation']) : 0;
    $idSection = isset($_POST['idSection']) ? intval($_POST['idSection']) : 0;
    
    // Validation des données
    if ($idAgent <= 0 || $idSpecialisation <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Les données soumises sont invalides.'
            }).then(() => {
                window.location.href = '../index.php?view=ur/affecation_ur&section={$idSection}';
            });
        </script>";
        exit;
    }
    
    $db = Connexion::getInstance()->getPDO();
    
    try {
        // Vérifier si l'enseignant est déjà affecté à cette spécialisation
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM enseignant_specialisation WHERE idAgent = ? AND idSpecialisation = ?");
        $stmtCheck->execute([$idAgent, $idSpecialisation]);
        $count = $stmtCheck->fetchColumn();
        
        if ($count > 0) {
            echo "<script>
                Swal.fire({
                    icon: 'warning',
                    title: 'Attention',
                    text: 'Cet enseignant est déjà affecté à cette spécialisation.'
                }).then(() => {
                    window.location.href = '../index.php?view=ur/affecation_ur&section={$idSection}';
                });
            </script>";
            exit;
        }
        
        // Insérer l'affectation
        $idUser = isset($_SESSION['id']) ? $_SESSION['id'] : null;
        $stmt = $db->prepare("INSERT INTO enseignant_specialisation (idAgent, idSpecialisation, dateAffectation, idUser) VALUES (?, ?, NOW(), ?)");
        $stmt->execute([$idAgent, $idSpecialisation, $idUser]);

        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'L\'enseignant a été affecté à la spécialisation avec succès.'
            }).then(() => {
                window.location.href = '../index.php?view=ur/affecation_ur&section={$idSection}';
            });
        </script>";
    } catch (PDOException $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur s\'est produite lors de l\'affectation: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../index.php?view=ur/affecation_ur&section={$idSection}';
            });
        </script>";
    }
} else {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Méthode non autorisée.'
        }).then(() => {
            window.location.href = '../index.php?view=ur/affecation_ur';
        });
    </script>";
}
