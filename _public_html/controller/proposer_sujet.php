<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';
require_once dirname(__DIR__) . '/models/Agent.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['student_id'])) {
    header('Location: ../login');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $universite = new Universite();
    $etudiantModel = new Etudiant();
    $agentModel = new Agent();

// Récupérer les données du formulaire
    $etudiantId = $_SESSION['student_id'];
    $intitule = trim($_POST['intitule']);
    $resume = !empty($_POST['resume']) ? trim($_POST['resume']) : null;
    $idSpecialisation = intval($_POST['idSpecialisation']);
    $anneeAcadId = intval($_POST['annee_acad']);
    $directeurId = intval($_POST['directeur_id']);
    $encadreurId = !empty($_POST['encadreur_id']) ? intval($_POST['encadreur_id']) : null;
    $cycle = $_SESSION['cycle'];

    try {
        // Vérifications
        if (empty($intitule) || empty($idSpecialisation) || empty($anneeAcadId) || empty($directeurId)) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Tous les champs obligatoires doivent être remplis.'
                    }).then(() => {
                        window.location.href = '../portail/student';
                    });
                });
            </script>";
            exit();
        }

        // Vérifier si le directeur existe et est un enseignant
        $directeur = $agentModel->getAgentById($directeurId);
        if (!$directeur || $directeur['type_agent'] !== 'Enseignant') {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Le directeur sélectionné n\\'est pas valide ou n\\'est pas un enseignant.'
                    }).then(() => {
                        window.location.href = '../portail/student';
                    });
                });
            </script>";
            exit();
        }


        // Vérifier si l'encadreur existe et est un enseignant
        if ($encadreurId) {
            $encadreur = $agentModel->getAgentById($encadreurId);
            if (!$encadreur || $encadreur['type_agent'] !== 'Enseignant') {
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Le co-encadreur sélectionné n\\'est pas valide ou n\\'est pas un enseignant.'
                        }).then(() => {
                            window.location.href = '../portail/student';
                        });
                    });
                </script>";
                exit();
            }
        }

        // Vérifier que le directeur et l'encadreur sont différents
        if ($encadreurId && $directeurId === $encadreurId) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Le directeur et le co-encadreur doivent être différents.'
                    }).then(() => {
                        window.location.href = '../portail/student';
                    });
                });
            </script>";
            exit();
        }

// Vérifier si l'étudiant n'a pas déjà un sujet
        $sujetExistant = $etudiantModel->getSujetAssigne($etudiantId);
        if ($sujetExistant) {
            $status = $sujetExistant['statut_validation'];
            // Si le sujet n'est pas rejeté (A reformulé), on ne peut pas en proposer un nouveau
            if ($status !== 'A reformulé') {
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Attention',
                            text: 'Vous avez déjà un sujet en ' + '" . strtolower($status) . "' + '. Vous ne pouvez pas en proposer un nouveau.'
                        }).then(() => {
                            window.location.href = '../portail/student';
                        });
                    });
                </script>";
                exit();
            }
        }

        // Vérifier si l'étudiant a payé les frais requis pour proposer un sujet
        if (!$etudiantModel->hasStudentPaidSujetFees($etudiantId)) {
            $sujetFeesStatus = $etudiantModel->getSujetFeesStatus($etudiantId);
            $unpaidFeesList = [];
            foreach ($sujetFeesStatus as $fee) {
                if ($fee['statut_paiement'] !== 'paye') {
                    $unpaidFeesList[] = $fee['designation'] . ' (' . number_format($fee['montant'], 2) . ' ' . $fee['devise'] . ')';
                }
            }
            $feesHtml = !empty($unpaidFeesList) ? implode(', ', $unpaidFeesList) : 'des frais requis';
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Paiement requis',
                        text: 'Vous devez d\\'abord payer $feesHtml avant de pouvoir proposer votre sujet.'
                    }).then(() => {
                        window.location.href = '../portail/student';
                    });
                });
            </script>";
            exit();
        }

// Créer le sujet
        $data = [
            'intitule' => $intitule,
            'resume' => $resume,
            'directeur_id' => $directeurId,
            'encadreur_id' => $encadreurId,
            'etudiant_id' => $etudiantId,
            'annee_acad' => $anneeAcadId,
            'cycle' => $cycle,
            'idSpecialisation' => $idSpecialisation,
            'idUser' => $_SESSION['id'] ?? null
        ];

        $result = $etudiantModel->proposerSujet($data);

        if ($result) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: 'Votre proposition de sujet a été soumise avec succès et est en attente de validation.'
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
                        text: 'Une erreur est survenue lors de la création du sujet.'
                    }).then(() => {
                        window.location.href = '../portail/student';
                    });
                });
            </script>";
        }
        exit();

    } catch (Exception $e) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: '" . addslashes($e->getMessage()) . "'
                }).then(() => {
                    window.location.href = '../portail/student';
                });
            });
        </script>";
        exit();
    }
} else {
    header('Location: ../portail/student');
    exit();
}
