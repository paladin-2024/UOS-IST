<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/models/Structure.php';
require_once dirname(__DIR__) . '/models/Grade.php';
require_once dirname(__DIR__) . '/models/Service.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login.php');
    exit();
}

// Récupérer l'ID de l'utilisateur connecté
$userId = $_SESSION['id'];

// Créer une instance des classes nécessaires
$agent = new Agent();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['idAgent'])) {
    try {
        // Récupération des données générales
        $idAgent = intval($_POST['idAgent']);
        $type_agent = isset($_POST['type_agent']) ? trim($_POST['type_agent']) : null;
        $matricule = isset($_POST['matricule']) ? trim($_POST['matricule']) : null;
        $codeAgent = isset($_POST['codeAgent']) ? trim($_POST['codeAgent']) : null;
        $noms = isset($_POST['noms']) ? trim($_POST['noms']) : null;
        
        // Vérification des champs obligatoires
        if (empty($type_agent) || empty($matricule) || empty($noms)) {
            throw new Exception('Veuillez remplir tous les champs obligatoires.');
        }
        
        // Informations personnelles
        $sexe = isset($_POST['sexe']) ? trim($_POST['sexe']) : null;
        $dateNaissance = isset($_POST['dateNaissance']) ? trim($_POST['dateNaissance']) : null;
        $lieuNaissance = isset($_POST['lieuNaissance']) ? trim($_POST['lieuNaissance']) : null;
        $etatCivil = isset($_POST['etatCivil']) ? trim($_POST['etatCivil']) : null;
        $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : null;
        $email = isset($_POST['email']) ? trim($_POST['email']) : null;
        $niveauEtude = isset($_POST['niveauEtude']) ? trim($_POST['niveauEtude']) : null;

        // Informations professionnelles
        $grade_id = isset($_POST['grade_id']) && !empty($_POST['grade_id']) ? intval($_POST['grade_id']) : null;
        $idStructure = isset($_POST['idStructure']) && !empty($_POST['idStructure']) ? intval($_POST['idStructure']) : null;
        $idService = isset($_POST['idService']) && !empty($_POST['idService']) ? intval($_POST['idService']) : null;
        
        // Données supplémentaires
        $adresse_avenue = isset($_POST['adresse_avenue']) ? trim($_POST['adresse_avenue']) : '';
        $adresse_quartier = isset($_POST['adresse_quartier']) ? trim($_POST['adresse_quartier']) : '';
        $adresse_commune = isset($_POST['adresse_commune']) ? trim($_POST['adresse_commune']) : '';
        $conjoint = isset($_POST['conjoint']) ? trim($_POST['conjoint']) : '';
        $contact_urgence = isset($_POST['contact_urgence']) ? trim($_POST['contact_urgence']) : '';
        $degre_parente_urgence = isset($_POST['degre_parente_urgence']) ? trim($_POST['degre_parente_urgence']) : '';
        $telephone_urgence = isset($_POST['telephone_urgence']) ? trim($_POST['telephone_urgence']) : '';
        $annee_engagement = isset($_POST['annee_engagement']) ? intval($_POST['annee_engagement']) : null;
        $reference_acte_engagement = isset($_POST['reference_acte_engagement']) ? trim($_POST['reference_acte_engagement']) : '';
        $prime_locale = isset($_POST['prime_locale']) ? 1 : 0;
        $salaire_etat = isset($_POST['salaire_etat']) ? 1 : 0;
        $prime_institutionnelle = isset($_POST['prime_institutionnelle']) ? 1 : 0;
        
        // Champs spécifiques selon le type d'agent
        $direction = isset($_POST['direction']) ? trim($_POST['direction']) : '';
        $division = isset($_POST['division']) ? trim($_POST['division']) : '';
        $decision_grade = isset($_POST['decision_grade']) ? trim($_POST['decision_grade']) : '';
        $notification_grade = isset($_POST['notification_grade']) ? trim($_POST['notification_grade']) : '';
        $specialisation = isset($_POST['specialisation']) ? trim($_POST['specialisation']) : '';
        $domaine_recherche = isset($_POST['domaine_recherche']) ? trim($_POST['domaine_recherche']) : '';
        $unite_recherche = isset($_POST['unite_recherche']) ? trim($_POST['unite_recherche']) : '';
        $projet_recherche = isset($_POST['projet_recherche']) ? trim($_POST['projet_recherche']) : '';
        
        // Gestion de la photo
        $photo = '';
        $removePhoto = isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1';
        $photoActuelle = isset($_POST['photo_actuelle']) ? $_POST['photo_actuelle'] : '';
        
        if ($removePhoto) {
            // Si l'utilisateur a demandé à supprimer la photo
            if (!empty($photoActuelle) && file_exists(dirname(__DIR__) . '/' . $photoActuelle)) {
                unlink(dirname(__DIR__) . '/' . $photoActuelle);
            }
            $photo = '';
        } else if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            // Si une nouvelle photo est téléchargée
            $uploadDir = dirname(__DIR__) . '/uploads/agents/photos/';
            
            // Créer le dossier s'il n'existe pas
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileInfo = pathinfo($_FILES['photo']['name']);
            $extension = strtolower($fileInfo['extension']);
            $allowedExtensions = ['jpg', 'jpeg', 'png'];
            
            if (in_array($extension, $allowedExtensions)) {
                $filename = uniqid() . '_' . $codeAgent . '.' . $extension;
                $uploadFile = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadFile)) {
                    // Supprimer l'ancienne photo si elle existe
                    if (!empty($photoActuelle) && file_exists(dirname(__DIR__) . '/' . $photoActuelle)) {
                        unlink(dirname(__DIR__) . '/' . $photoActuelle);
                    }
                    $photo = 'uploads/agents/photos/' . $filename;
                } else {
                    throw new Exception('Échec du téléchargement de la photo.');
                }
            } else {
                throw new Exception('Format de photo non autorisé. Utilisez JPG, JPEG ou PNG.');
            }
        } else {
            // Conserver la photo actuelle
            $photo = $photoActuelle;
        }
        
        // Préparation des données de l'agent
        $agentData = [
            'type_agent' => $type_agent,
            'matricule' => $matricule,
            'codeAgent' => $codeAgent,
            'noms' => $noms,
            'sexe' => $sexe,
            'dateNaissance' => $dateNaissance,
            'lieuNaissance' => $lieuNaissance,
            'etatCivil' => $etatCivil,
            'telephone' => $telephone,
            'email' => $email,
            'niveauEtude' => $niveauEtude,
            'grade_id' => $grade_id,
            'idStructure' => $idStructure,
            'idService' => $idService,
            'adresse_avenue' => $adresse_avenue,
            'adresse_quartier' => $adresse_quartier,
            'adresse_commune' => $adresse_commune,
            'conjoint' => $conjoint,
            'contact_urgence' => $contact_urgence,
            'degre_parente_urgence' => $degre_parente_urgence,
            'telephone_urgence' => $telephone_urgence,
            'annee_engagement' => $annee_engagement,
            'reference_acte_engagement' => $reference_acte_engagement,
            'prime_locale' => $prime_locale,
            'salaire_etat' => $salaire_etat,
            'prime_institutionnelle' => $prime_institutionnelle,
            'photo' => $photo,
            'date_modification' => date('Y-m-d H:i:s'),
            'modifie_par' => $userId
        ];
        
        // Ajout des champs spécifiques selon le type d'agent
        if ($type_agent === 'Administratif') {
            $agentData['direction'] = $direction;
            $agentData['division'] = $division;
            $agentData['decision_grade'] = $decision_grade;
            $agentData['notification_grade'] = $notification_grade;
        } else if ($type_agent === 'Enseignant') {
            $agentData['specialisation'] = $specialisation;
            $agentData['domaine_recherche'] = $domaine_recherche;
        } else if ($type_agent === 'Recherche') {
            $agentData['unite_recherche'] = $unite_recherche;
            $agentData['projet_recherche'] = $projet_recherche;
        }
        
        // Mise à jour des données de l'agent
        $updateResult = $agent->editAgent($idAgent, $agentData);
        
        // Traitement des formations
        if (isset($_POST['formations']) && is_array($_POST['formations'])) {
            // Récupérer les ID des formations existantes
            $formationIds = [];
            
            foreach ($_POST['formations'] as $index => $formationData) {
                $formationId = isset($formationData['id']) ? intval($formationData['id']) : null;
                
                // Données de base pour la formation
                $niveau = $formationData['niveau'] ?? '';
                $etablissement = $formationData['etablissement'] ?? '';
                $filiere = $formationData['filiere'] ?? '';
                $annee_obtention = $formationData['annee_obtention'] ?? null;
                $diplome_fichier = null;
                
                // Gestion du fichier diplôme
                if (isset($_FILES['formations_files']) && 
                    isset($_FILES['formations_files']['name'][$index]) && 
                    $_FILES['formations_files']['error'][$index] == 0) {
                    
                    $uploadDir = dirname(__DIR__) . '/uploads/agents/diplomes/';
                    
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $fileInfo = pathinfo($_FILES['formations']['name'][$index]['diplome_fichier']);
                    $extension = strtolower($fileInfo['extension']);
                    
                    if ($extension === 'pdf') {
                        $filename = uniqid() . '_' . $codeAgent . '_diplome.' . $extension;
                        $uploadFile = $uploadDir . $filename;
                        
                        if (move_uploaded_file($_FILES['formations']['tmp_name'][$index]['diplome_fichier'], $uploadFile)) {
                            // Si mise à jour, supprimer l'ancien fichier
                            if ($formationId && !empty($formationData['diplome_fichier_actuel']) && 
                                file_exists(dirname(__DIR__) . '/' . $formationData['diplome_fichier_actuel'])) {
                                unlink(dirname(__DIR__) . '/' . $formationData['diplome_fichier_actuel']);
                            }
                            
                            $diplome_fichier = 'uploads/agents/diplomes/' . $filename;
                        }
                    }
                } else if ($formationId && isset($formationData['diplome_fichier_actuel'])) {
                    // Conserver le fichier actuel
                    $diplome_fichier = $formationData['diplome_fichier_actuel'];
                }
                
                if ($formationId) {
                    // Formation existante à mettre à jour
                    $formationIds[] = $formationId;
                    
                    $formation = [
                        'niveau' => $niveau,
                        'etablissement' => $etablissement,
                        'filiere' => $filiere,
                        'annee_obtention' => $annee_obtention,
                        'diplome_fichier' => $diplome_fichier
                    ];
                    
                    $agent->updateFormation($formationId, $formation);
                } else {
                    // Nouvelle formation à ajouter
                    $agent->addFormation(
                        $idAgent,
                        $niveau,
                        $etablissement,
                        $filiere,
                        $annee_obtention,
                        $diplome_fichier,
                        $userId
                    );
                }
            }
            
            // Supprimer les formations qui n'existent plus
            $agent->deleteFormationsNotIn($idAgent, $formationIds);
        }
        
        // Traitement de l'historique des grades
        if (isset($_POST['grades_history']) && is_array($_POST['grades_history'])) {
            // Récupérer les ID des historiques de grades existants
            $gradeHistoryIds = [];
            
            foreach ($_POST['grades_history'] as $index => $gradeData) {
                $gradeHistoryId = isset($gradeData['id']) && is_numeric($gradeData['id']) ? intval($gradeData['id']) : null;
                // Récupérer les données du grade
                $idgrade = $gradeData['idgrade'] ?? null;
                $date_promotion = $gradeData['date_promotion'] ?? null;
                $reference_decision = $gradeData['reference_decision'] ?? '';
                $reference_notification = $gradeData['reference_notification'] ?? '';
                
                if ($gradeHistoryId) {
                    // Historique de grade existant à mettre à jour
                    $gradeHistoryIds[] = $gradeHistoryId;
                    
                    $gradeHistory = [
                        'idgrade' => $idgrade,
                        'date_promotion' => $date_promotion,
                        'reference_decision' => $reference_decision,
                        'reference_notification' => $reference_notification
                    ];
                    
                    $agent->updateGradeHistory($gradeHistoryId, $gradeHistory);
                } else {
                    // Nouvel historique de grade à ajouter
                    $agent->addGradeHistory(
                        $idAgent,
                        $idgrade,
                        $date_promotion,
                        $reference_decision,
                        $reference_notification,
                        $userId
                    );
                }
            }
            
            // Supprimer les historiques de grades qui n'existent plus
            $agent->deleteGradeHistoriesNotIn($idAgent, $gradeHistoryIds);
        }
        
        // Mise à jour des informations supplémentaires
        $agent->updateAgentAdditionalInfo(
            $idAgent,
            $adresse_avenue,
            $adresse_quartier,
            $adresse_commune,
            $conjoint,
            $contact_urgence,
            $degre_parente_urgence,
            $telephone_urgence,
            $annee_engagement,
            $reference_acte_engagement,
            $prime_locale,
            $salaire_etat,
            $prime_institutionnelle,
            $photo
        );
        
        // Redirection avec message de succès
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Les informations de l\'agent ont été mises à jour avec succès.',
                confirmButtonColor: '#4e73df'
            }).then(() => {
                window.location.href = '../grh/agent.list';
            });
        </script>";
        
    } catch (Exception $e) {
        // Affichage du message d'erreur
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "',
                confirmButtonColor: '#4e73df'
            }).then(() => {
                window.history.back();
            });
        </script>";
    }
} else {
    // Redirection en cas d'accès non autorisé
    header('Location: ../grh/agent.list');
    exit();
}
?>

