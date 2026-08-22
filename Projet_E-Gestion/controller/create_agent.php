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
$structure = new Structure();
$grade = new Grade();
$service = new Service();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Récupérer les données du formulaire
        $noms = isset($_POST['noms']) ? trim($_POST['noms']) : '';
        $lieuNaissance = isset($_POST['lieuNaissance']) ? trim($_POST['lieuNaissance']) : '';
        $dateNaissance = isset($_POST['dateNaissance']) ? $_POST['dateNaissance'] : '';
        $sexe = isset($_POST['sexe']) ? trim($_POST['sexe']) : '';
        $etatCivil = isset($_POST['etatCivil']) ? trim($_POST['etatCivil']) : '';
        $niveauEtude = isset($_POST['niveauEtude']) ? trim($_POST['niveauEtude']) : '';
        $idStructure = isset($_POST['idStructure']) ? intval($_POST['idStructure']) : 0;
        $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $codeAgent = isset($_POST['codeAgent']) ? trim($_POST['codeAgent']) : '';
        $matricule = isset($_POST['matricule']) ? trim($_POST['matricule']) : '';
        $type_agent = isset($_POST['type_agent']) ? trim($_POST['type_agent']) : '';
        $grade_id = isset($_POST['grade_id']) && !empty($_POST['grade_id']) ? intval($_POST['grade_id']) : null;
        $idService = isset($_POST['idService']) && !empty($_POST['idService']) ? intval($_POST['idService']) : null;
        $isRapideMode = isset($_POST['isRapideMode']) ? intval($_POST['isRapideMode']) : 0;

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

        // Validation des champs requis
        // En mode rapide, dateNaissance est optionnelle
        if (empty($noms) || $idStructure <= 0 || empty($type_agent)) {
            throw new Exception('Les noms, le type d\'agent et la structure sont obligatoires.');
        }

        if (!$isRapideMode && empty($dateNaissance)) {
            throw new Exception('La date de naissance est obligatoire en mode complet.');
        }

        // Vérifier si la structure existe
        $structureExists = $structure->checkStructureExists($idStructure);
        if (!$structureExists) {
            throw new Exception('La structure sélectionnée est invalide.');
        }

        // Vérifier si le grade existe (si fourni)
        if ($grade_id !== null) {
            $gradeInfo = $grade->getGradeById($grade_id);
            if (!$gradeInfo) {
                throw new Exception('Le grade sélectionné est invalide.');
            }

            // Vérifier que le grade correspond au type d'agent
            if ($gradeInfo['type_agent'] !== $type_agent) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Incompatibilité',
                        text: 'Le grade sélectionné ne correspond pas au type d\'agent choisi.',
                        footer: '<a href=\"../grh/agent.add?type_agent=" . $type_agent . "\">Retourner au formulaire</a>'
                    });
                </script>";
                exit();
            }
        }

        // Vérifier si le service existe (si fourni)
        if ($idService !== null) {
            $serviceInfo = $service->getServiceById($idService);
            if (!$serviceInfo) {
                throw new Exception('Le service sélectionné est invalide.');
            }

            // Vérifier que le service appartient à la structure sélectionnée
            if ($serviceInfo['Structure_idStructure'] != $idStructure) {
                throw new Exception('Le service sélectionné n\'appartient pas à la structure choisie.');
            }
        }


        // Vérifier si le code existe déjà
        $codeExists = $agent->checkCodeExists($codeAgent);
        if ($codeExists) {
            // Générer un nouveau code
            $prefix = "AG";
            $newCode = $prefix . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
            $codeAgent = $newCode;
        }

        // Vérifier les doublons pour l'agent (seulement si dateNaissance est fournie)
        if (!empty($dateNaissance) && $agent->checkDuplicateAgent($noms, $dateNaissance, $idStructure)) {
            echo "<script>
                Swal.fire({
                    icon: 'warning',
                    title: 'Attention',
                    text: 'Un agent avec ces informations existe déjà dans cette structure. Voulez-vous quand même continuer?',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Oui, continuer',
                    cancelButtonText: 'Non, annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../grh/agent.add" . (!empty($type_agent) ? "?type_agent=" . $type_agent : "") . "';
                    } else {
                        window.location.href = '../grh/agent.add.rapide';
                    }
                });
            </script>";
            exit();
        }


        // Traitement de la photo
        $photo = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__) . '/uploads/agents/';

            // Créer le répertoire s'il n'existe pas
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Générer un nom de fichier unique
            $fileExtension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('agent_') . '.' . $fileExtension;
            $targetFile = $uploadDir . $fileName;

            // Déplacer le fichier téléchargé
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile)) {
                $photo = $fileName;
            } else {
                throw new Exception('Erreur lors du téléchargement de la photo.');
            }
        }

        // Démarrer une transaction
        $db = Connexion::getInstance()->getPDO();
        $db->beginTransaction();

        // Ajouter l'agent et récupérer son ID
        $agentId = $agent->addAgent_returnID(
            $noms,
            $lieuNaissance,
            $dateNaissance,
            $sexe,
            $etatCivil,
            $niveauEtude,
            $telephone,
            $email,
            $codeAgent,
            $matricule,
            $type_agent,
            $grade_id,
            $idStructure,
            $idService
        );

        if (!$agentId) {

            throw new Exception('Erreur lors de l\'ajout de l\'agent.');
            if (!$agentId) {
                // Ajoutez des logs pour déboguer
                error_log("Échec de l'ajout de l'agent. Données: " . json_encode([
                    'noms' => $noms,
                    'grade_id' => $grade_id,
                    'idStructure' => $idStructure,
                    'idService' => $idService
                ]));
                throw new Exception('Erreur lors de l\'ajout de l\'agent.');
            }
        }

        // Mettre à jour les champs supplémentaires
        $agent->updateAgentAdditionalInfo(
            $agentId,
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

        // Traiter les formations académiques
        if (isset($_POST['formations']) && is_array($_POST['formations'])) {
            foreach ($_POST['formations'] as $key => $formation) {
                $niveau = $formation['niveau'] ?? '';
                $etablissement = $formation['etablissement'] ?? '';
                $filiere = $formation['filiere'] ?? '';
                $annee_obtention = $formation['annee_obtention'] ?? null;

                // Traitement du fichier diplôme
                $diplome_fichier = '';
                if (
                    isset($_FILES['formations']['name'][$key]['diplome_fichier']) &&
                    $_FILES['formations']['error'][$key]['diplome_fichier'] === UPLOAD_ERR_OK
                ) {

                    $uploadDir = dirname(__DIR__) . '/uploads/diplomes/';

                    // Créer le répertoire s'il n'existe pas
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    // Générer un nom de fichier unique
                    $fileExtension = pathinfo($_FILES['formations']['name'][$key]['diplome_fichier'], PATHINFO_EXTENSION);
                    $fileName = uniqid('diplome_') . '.' . $fileExtension;
                    $targetFile = $uploadDir . $fileName;

                    // Déplacer le fichier téléchargé
                    if (move_uploaded_file($_FILES['formations']['tmp_name'][$key]['diplome_fichier'], $targetFile)) {
                        $diplome_fichier = 'uploads/diplomes/' . $fileName;
                    }
                }

                // Ajouter la formation
                if (!empty($niveau) && !empty($etablissement)) {
                    $agent->addFormation(
                        $agentId,
                        $niveau,
                        $etablissement,
                        $filiere,
                        $annee_obtention,
                        $diplome_fichier,
                        $userId
                    );
                }
            }
        }

        // Traiter l'historique des grades
        if (isset($_POST['grades_history']) && is_array($_POST['grades_history'])) {
            foreach ($_POST['grades_history'] as $gradeHistory) {
                $idgrade = $gradeHistory['idgrade'] ?? null;
                $date_promotion = $gradeHistory['date_promotion'] ?? null;
                $reference_decision = $gradeHistory['reference_decision'] ?? '';
                $reference_notification = $gradeHistory['reference_notification'] ?? '';

                // Ajouter l'historique de grade
                if ($idgrade && $date_promotion) {
                    $agent->addGradeHistory(
                        $agentId,
                        $idgrade,
                        $date_promotion,
                        $reference_decision,
                        $reference_notification,
                        $userId
                    );
                }
            }
        }


        // Ajouter l'affectation actuelle
        $agent->addAffectation(
            $agentId,
            $idStructure,
            $idService,
            date('Y-m-d'), // Date d'aujourd'hui
            $reference_acte_engagement,
            1, // Est actuelle
            $userId
        );

        // Enregistrer les informations spécifiques selon le type d'agent
        if ($type_agent === 'Administratif' && (!empty($direction) || !empty($division))) {
            $agent->addAdminInfo(
                $agentId,
                $direction,
                $division,
                $decision_grade,
                $notification_grade,
                $userId
            );
        } elseif ($type_agent === 'Enseignant' && (!empty($specialisation) || !empty($domaine_recherche))) {
            $agent->addTeacherInfo(
                $agentId,
                $specialisation,
                $domaine_recherche,
                $userId
            );
        } elseif ($type_agent === 'Recherche' && (!empty($unite_recherche) || !empty($projet_recherche))) {
            $agent->addResearchInfo(
                $agentId,
                $unite_recherche,
                $projet_recherche,
                $userId
            );
        }

        // Valider la transaction
        $db->commit();

        // Afficher un message de succès avec SweetAlert
         $redirectUrl = $isRapideMode 
             ? '../grh/agent.add.rapide' 
             : '../grh/agent.add' . (!empty($type_agent) ? "?type_agent=" . $type_agent : "");
         
         echo "<script>
         Swal.fire({
             icon: 'success',
             title: 'Succès!',
             html: 'L\'agent a été enregistré avec succès.<br><br><strong>CODE AGENT: <span class=\"text-primary\" style=\"font-size: 1.2em;\">' + '$codeAgent' + '</span></strong>',
             showConfirmButton: true,
             confirmButtonText: 'OK',
             confirmButtonColor: '#4e73df'
         }).then(() => {
             window.location.href = '$redirectUrl';
         });
         </script>";
         exit();
    } catch (Exception $e) {
        // En cas d'erreur, annuler la transaction
        if (isset($db)) {
            $db->rollBack();
        }

        $redirectUrl = $isRapideMode 
            ? '../grh/agent.add.rapide' 
            : '../grh/agent.add' . (!empty($type_agent) ? "?type_agent=" . $type_agent : "");
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "',
                confirmButtonColor: '#d33',
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                }
            }).then(() => {
                window.location.href = '$redirectUrl';
            });
        </script>";
        exit();
    }
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../grh/agent.add");
    exit();
}
