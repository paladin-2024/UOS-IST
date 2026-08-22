<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';
require_once dirname(__DIR__) . '/models/Agent.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération des données du formulaire
    $sujetId = $_POST['sujet_id'] ?? '';
    $directeurId = $_POST['directeur_id'] ?? '';
    $encadreurId = !empty($_POST['encadreur_id']) ? $_POST['encadreur_id'] : null;
    $etudiantId = $_SESSION['student_id'] ?? '';

    // Validation des données requises
    if (!$sujetId || !$directeurId || !$etudiantId) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Veuillez remplir tous les champs obligatoires'
                }).then(() => {
                    window.location.href = '../portail/student';
                });
            });
        </script>";
        exit();
    }

    try {
        $etudiantModel = new Etudiant();
        $agentModel = new Agent();
        
        // Vérifier si l'étudiant a déjà un sujet
        $sujetExistant = $etudiantModel->getSujetAssigne($etudiantId);
        if ($sujetExistant) {
            // Vérifier si le sujet existant n'est pas annulé
            if ($sujetExistant['statut_validation'] !== 'Rejeté') {
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Vous avez déjà un sujet actif. Vous ne pouvez pas en choisir un autre tant que votre sujet actuel n\'est pas annulé.'
                        }).then(() => {
                            window.location.href = '../portail/student';
                        });
                    });
                </script>";
                exit();
            }
        }

        // Vérification que le directeur et l'encadreur sont différents
        if ($encadreurId && $encadreurId === $directeurId) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Le directeur et le co-encadreur doivent être différents'
                    }).then(() => {
                        window.location.href = '../portail/student';
                    });
                });
            </script>";
            exit();
        }

        // Vérifier si le directeur et l'encadreur existent et sont des enseignants
        $directeur = $agentModel->getAgentById($directeurId);
        if (!$directeur || $directeur['type_agent'] !== 'Enseignant') {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Le directeur sélectionné n\'est pas valide'
                    }).then(() => {
                        window.location.href = '../portail/student';
                    });
                });
            </script>";
            exit();
        }

        if ($encadreurId) {
            $encadreur = $agentModel->getAgentById($encadreurId);
            if (!$encadreur || $encadreur['type_agent'] !== 'Enseignant') {
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Le co-encadreur sélectionné n\'est pas valide'
                        }).then(() => {
                            window.location.href = '../portail/student';
                        });
                    });
                </script>";
                exit();
            }
        }

        // Tenter de choisir le sujet
        $success = $etudiantModel->choisirSujet($sujetId, $etudiantId, $directeurId, $encadreurId);
        
        if ($success) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: 'Votre choix de sujet a été enregistré avec succès'
                    }).then(() => {
                        window.location.href = '../portail/student';
                    });
                });
            </script>";
        } else {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Le sujet n\'est plus disponible ou une erreur est survenue'
                    }).then(() => {
                        window.location.href = '../portail/student';
                    });
                });
            </script>";
        }
    } catch (Exception $e) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de l\'enregistrement: " . addslashes($e->getMessage()) . "'
                }).then(() => {
                    window.location.href = '../portail/student';
                });
            });
        </script>";
    }
} else {
    // Redirection si accès direct au fichier
    header('Location: ../portail/student');
    exit();
}
?>
