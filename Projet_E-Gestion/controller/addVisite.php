<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nomVisiteur = $_POST['nom_visiteur'] ?? '';
    $prenomVisiteur = $_POST['prenom_visiteur'] ?? null;
    $entrepriseVisiteur = $_POST['entreprise_visiteur'] ?? null;
    $telephoneVisiteur = $_POST['telephone_visiteur'] ?? '';
    $emailVisiteur = $_POST['email_visiteur'] ?? null;
    $carteIdentite = $_POST['carte_identite'] ?? null;
    $agentId = $_POST['Agent_idAgent'] ?? '';
    $serviceId = $_POST['Service_idService'] ?? '';
    $dateVisite = $_POST['date_visite'] ?? '';
    $heureDebut = $_POST['heure_debut'] ?? '';
    $heureFin = $_POST['heure_fin'] ?? '';
    $objetVisite = $_POST['objet_visite'] ?? '';
    $description = $_POST['description'] ?? null;
    $lieuRencontre = $_POST['lieu_rencontre'] ?? null;
    $statutVisite = $_POST['statut_visite'] ?? 'programmee';
    $typeVisite = $_POST['type_visite'] ?? 'professionnelle';
    $nombreAccompagnants = $_POST['nombre_accompagnants'] ?? 0;
    $observations = $_POST['observations'] ?? null;
    $validationSecurite = isset($_POST['validation_securite']) ? 1 : 0;
    $badgeVisiteur = $_POST['badge_visiteur'] ?? null;
    $userId = $_SESSION['id'];

    // Validation des champs obligatoires
    if (empty($nomVisiteur) || empty($telephoneVisiteur) || empty($agentId) || 
        empty($serviceId) || empty($dateVisite) || empty($heureDebut) || 
        empty($heureFin) || empty($objetVisite)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../reception/visites.add';
            });
        </script>";
        exit();
    }

    // Validation des heures
    if ($heureDebut >= $heureFin) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'L\'heure de fin doit être postérieure à l\'heure de début.'
            }).then(() => {
                window.location.href = '../reception/visites.add';
            });
        </script>";
        exit();
    }

    // Validation de la date (ne peut pas être dans le passé)
    if ($dateVisite < date('Y-m-d')) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La date de la visite ne peut pas être dans le passé.'
            }).then(() => {
                window.location.href = '../reception/visites.add';
            });
        </script>";
        exit();
    }

    // Validation du nombre d'accompagnants
    if ($nombreAccompagnants < 0 || $nombreAccompagnants > 10) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le nombre d\'accompagnants doit être entre 0 et 10.'
            }).then(() => {
                window.location.href = '../reception/visites.add';
            });
        </script>";
        exit();
    }

    try {
        $db = Connexion::getInstance()->getPDO();

        // Vérifier la disponibilité de l'agent
        $stmtCheck = $db->prepare("
            SELECT COUNT(*) as conflicts 
            FROM visites 
            WHERE \"Agent_idAgent\" = ? 
            AND date_visite = ? 
            AND statut_visite NOT IN ('annulee', 'terminee')
            AND (
                (heure_debut <= ? AND heure_fin > ?) OR
                (heure_debut < ? AND heure_fin >= ?) OR
                (heure_debut >= ? AND heure_fin <= ?)
            )
        ");
        $stmtCheck->execute([
            $agentId, $dateVisite, 
            $heureDebut, $heureDebut,
            $heureFin, $heureFin,
            $heureDebut, $heureFin
        ]);
        
        $conflicts = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($conflicts['conflicts'] > 0) {
            echo "<script>
                Swal.fire({
                    icon: 'warning',
                    title: 'Conflit détecté',
                    text: 'L\'agent a déjà une visite programmée à cette période.'
                }).then(() => {
                    window.location.href = '../reception/visites.add';
                });
            </script>";
            exit();
        }

        // Génération automatique du badge si pas fourni
        if (empty($badgeVisiteur)) {
            $badgeVisiteur = 'VIS' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        }

        // Insertion de la visite
        $stmt = $db->prepare("
            INSERT INTO visites (
                nom_visiteur, prenom_visiteur, entreprise_visiteur, 
                telephone_visiteur, email_visiteur, carte_identite,
                \"Agent_idAgent\", \"Service_idService\", date_visite, 
                heure_debut, heure_fin, objet_visite, description, 
                lieu_rencontre, statut_visite, type_visite, 
                nombre_accompagnants, observations, validation_securite,
                badge_visiteur, cree_par, date_creation
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $nomVisiteur, $prenomVisiteur, $entrepriseVisiteur,
            $telephoneVisiteur, $emailVisiteur, $carteIdentite,
            $agentId, $serviceId, $dateVisite,
            $heureDebut, $heureFin, $objetVisite, $description,
            $lieuRencontre, $statutVisite, $typeVisite,
            $nombreAccompagnants, $observations, $validationSecurite,
            $badgeVisiteur, $userId
        ]);

        if ($result) {
            $visiteId = $db->lastInsertId();

            // Enregistrer dans l'historique
            $stmtHistorique = $db->prepare("
                INSERT INTO historique_visites (idVisite, action, nouveau_statut, commentaire, \"idUser\")
                VALUES (?, 'Création', ?, 'Visite programmée', ?)
            ");
            $stmtHistorique->execute([$visiteId, $statutVisite, $userId]);

            // Optionnel: Envoi de notification SMS au visiteur
            if (!empty($telephoneVisiteur)) {
                $dateFormatted = date('d/m/Y', strtotime($dateVisite));
                $heureFormatted = date('H:i', strtotime($heureDebut));
                
                // Récupérer le nom de l'agent
                $stmtAgent = $db->prepare("SELECT noms FROM agent WHERE \"idAgent\" = ?");
                $stmtAgent->execute([$agentId]);
                $agent = $stmtAgent->fetch(PDO::FETCH_ASSOC);
                
                $message = "Visite confirmée le {$dateFormatted} à {$heureFormatted} avec {$agent['noms']}. Badge: {$badgeVisiteur}. Objet: {$objetVisite}";
                
                // Fonction d'envoi SMS
                function nettoyerNumero($telephone) {
                    if (strpos($telephone, '+243') === 0) {
                        $telephone = substr($telephone, 4);
                    } elseif (strpos($telephone, '0') === 0) {
                        $telephone = substr($telephone, 1);
                    }
                    
                    if (preg_match('/^[0-9]{9}$/', $telephone)) {
                        return $telephone;
                    }
                    return false;
                }

                function envoyerSMS($phone, $message) {
                    $url = "https://api2.dream-digital.info/api/SendSMS?api_id=API4604816615&api_password=28iF7i2aAU&sms_type=T&encoding=T&sender_id=BDOM-BUKAVU&phonenumber=243" . $phone . "&textmessage=" . rawurlencode($message);
                    return file_get_contents($url);
                }

                $numeroNettoye = nettoyerNumero($telephoneVisiteur);
                if ($numeroNettoye) {
                    envoyerSMS($numeroNettoye, $message);
                }
            }

            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Visite programmée avec succès. Badge: {$badgeVisiteur}'
                }).then(() => {
                    window.location.href = '../reception/visites.add';
                });
            </script>";
        } else {
            throw new Exception("Erreur lors de l'insertion de la visite");
        }
        
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la programmation de la visite: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../reception/visites.add';
            });
        </script>";
    }
    
} else {
    header("Location: ../reception/visites.add");
    exit();
}
?>
