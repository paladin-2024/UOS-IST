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
    $sexe = isset($_POST['sexe']) ? trim($_POST['sexe']) : '';
    $dateNaissance = isset($_POST['dateNaissance']) ? trim($_POST['dateNaissance']) : '';
    $lieuNaissance = isset($_POST['lieuNaissance']) ? trim($_POST['lieuNaissance']) : '';
    $etatCivil = isset($_POST['etatCivil']) ? trim($_POST['etatCivil']) : '';
    $conjoint = isset($_POST['conjoint']) ? trim($_POST['conjoint']) : '';
    $adresse_avenue = isset($_POST['adresse_avenue']) ? trim($_POST['adresse_avenue']) : '';
    $adresse_quartier = isset($_POST['adresse_quartier']) ? trim($_POST['adresse_quartier']) : '';
    $adresse_commune = isset($_POST['adresse_commune']) ? trim($_POST['adresse_commune']) : '';
    $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $contact_urgence = isset($_POST['contact_urgence']) ? trim($_POST['contact_urgence']) : '';
    $degre_parente_urgence = isset($_POST['degre_parente_urgence']) ? trim($_POST['degre_parente_urgence']) : '';
    $telephone_urgence = isset($_POST['telephone_urgence']) ? trim($_POST['telephone_urgence']) : '';
    $returnTab = isset($_POST['returnTab']) ? trim($_POST['returnTab']) : 'personal';

    // Validation des données
    if (empty($idAgent) || empty($sexe) || empty($dateNaissance) || empty($lieuNaissance) || empty($etatCivil) || empty($telephone)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../grh/agent.edition&searchType=id&search={$idAgent}&tab={$returnTab}';
            });
        </script>";
        exit();
    }

    try {
        // Connexion à la base de données
        $pdo = Connexion::getInstance()->getPDO();
        
        // Préparer et exécuter la requête SQL directe
        $query = "UPDATE agent SET 
                    sexe = :sexe, 
                    dateNaissance = :dateNaissance, 
                    lieuNaissance = :lieuNaissance, 
                    etatCivil = :etatCivil, 
                    conjoint = :conjoint, 
                    adresse_avenue = :adresse_avenue, 
                    adresse_quartier = :adresse_quartier, 
                    adresse_commune = :adresse_commune, 
                    telephone = :telephone, 
                    email = :email, 
                    contact_urgence = :contact_urgence, 
                    degre_parente_urgence = :degre_parente_urgence, 
                    telephone_urgence = :telephone_urgence 
                  WHERE idAgent = :idAgent";
                  
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            ':sexe' => $sexe,
            ':dateNaissance' => $dateNaissance,
            ':lieuNaissance' => $lieuNaissance,
            ':etatCivil' => $etatCivil,
            ':conjoint' => $conjoint,
            ':adresse_avenue' => $adresse_avenue,
            ':adresse_quartier' => $adresse_quartier,
            ':adresse_commune' => $adresse_commune,
            ':telephone' => $telephone,
            ':email' => $email,
            ':contact_urgence' => $contact_urgence,
            ':degre_parente_urgence' => $degre_parente_urgence,
            ':telephone_urgence' => $telephone_urgence,
            ':idAgent' => $idAgent
        ]);

        // Récupérer le codeAgent pour la redirection
        $queryCode = "SELECT codeAgent FROM agent WHERE idAgent = :idAgent";
        $stmtCode = $pdo->prepare($queryCode);
        $stmtCode->execute([':idAgent' => $idAgent]);
        $codeAgent = $stmtCode->fetchColumn();
        
        
        if ($result) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Les informations personnelles ont été mises à jour avec succès.'
                }).then(() => {
                    window.location.href = '../grh/agent.edition&searchType=code&search={$codeAgent}&tab=personal';
                });
            </script>";
        } else {
            throw new Exception("Erreur lors de la mise à jour des informations personnelles.");
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '{$e->getMessage()}'
            }).then(() => {
                window.location.href = '../grh/agent.edition&searchType=id&search={$codeAgent}&tab=personal';
            });
        </script>";
    }
    exit();
}

// Rediriger si accès direct
header('Location: ../index');
exit();
