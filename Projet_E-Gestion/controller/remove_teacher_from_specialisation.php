<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['idAffectation'])) {
    $idAffectation = intval($_GET['idAffectation']);
    
    // Récupérer les paramètres de redirection
    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'ur/unite_recherche';
    $section = isset($_GET['section']) ? intval($_GET['section']) : 0;
    $annee = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    // Construire l'URL de redirection
    $redirectUrl = "../index.php?view={$redirect}";
    if ($section > 0) $redirectUrl .= "&section={$section}";
    if ($annee > 0) $redirectUrl .= "&annee={$annee}";
    if (!empty($search)) $redirectUrl .= "&search=" . urlencode($search);
    
    if ($idAffectation <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID d\'affectation invalide.'
            }).then(() => {
                window.location.href = '{$redirectUrl}';
            });
        </script>";
        exit;
    }
    
    $db = Connexion::getInstance()->getPDO();
    
    try {
        $stmt = $db->prepare("DELETE FROM enseignant_specialisation WHERE id = ?");
        $stmt->execute([$idAffectation]);
        
        if ($stmt->rowCount() > 0) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'L\'enseignant a été retiré de la spécialisation avec succès.'
                }).then(() => {
                    window.location.href = '{$redirectUrl}';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'warning',
                    title: 'Attention',
                    text: 'Aucune affectation trouvée avec cet ID.'
                }).then(() => {
                    window.location.href = '{$redirectUrl}';
                });
            </script>";
        }
    } catch (PDOException $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur s\'est produite lors de la suppression: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '{$redirectUrl}';
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
            window.location.href = '../index.php?view=ur/unite_recherche';
        });
    </script>";
}
