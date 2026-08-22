<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $agentId = $_POST['Agent_idAgent'] ?? '';
    $serviceId = $_POST['Service_idService'] ?? '';
    $contactExterne = $_POST['contact_externe'] ?? null;
    $emailExterne = $_POST['email_externe'] ?? null;
    $telephoneExterne = $_POST['telephone_externe'] ?? null;
    $dateRendezVous = $_POST['date_rendez_vous'] ?? '';
    $heureDebut = $_POST['heure_debut'] ?? '';
    $heureFin = $_POST['heure_fin'] ?? '';
    $objet = $_POST['objet'] ?? '';
    $description = $_POST['description'] ?? null;
    $lieu = $_POST['lieu'] ?? null;
    $statutRendezVous = $_POST['statut_rendez_vous'] ?? 'planifie';
    $typeRendezVous = $_POST['type_rendez_vous'] ?? null;
    $priorite = $_POST['priorite'] ?? 'normale';
    $rappelActive = isset($_POST['rappel_active']) ? 1 : 0;
    $delaiRappel = $_POST['delai_rappel'] ?? 30;
    $commentaires = $_POST['commentaires'] ?? null;
    $userId = $_SESSION['id'];

    // Validation des champs obligatoires
    if (empty($agentId) || empty($serviceId) || empty($dateRendezVous) || 
        empty($heureDebut) || empty($heureFin) || empty($objet)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../reception/rendez_vous.add';
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
                window.location.href = '../reception/rendez_vous.add';
            });
        </script>";
        exit();
    }

    // Validation de la date (ne peut pas être dans le passé)
    if ($dateRendezVous < date('Y-m-d')) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La date du rendez-vous ne peut pas être dans le passé.'
            }).then(() => {
                window.location.href = '../reception/rendez_vous.add';
            });
        </script>";
        exit();
    }

    try {
        $db = Connexion::getInstance()->getPDO();

        // Vérifier la disponibilité de l'agent
        $stmtCheck = $db->prepare("
            SELECT COUNT(*) as conflicts 
            FROM rendez_vous 
            WHERE Agent_idAgent = ? 
            AND date_rendez_vous = ? 
            AND statut_rendez_vous NOT IN ('annule', 'termine')
            AND (
                (heure_debut <= ? AND heure_fin > ?) OR
                (heure_debut < ? AND heure_fin >= ?) OR
                (heure_debut >= ? AND heure_fin <= ?)
            )
        ");
        $stmtCheck->execute([
            $agentId, $dateRendezVous, 
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
                    text: 'L\'agent a déjà un rendez-vous à cette période.'
                }).then(() => {
                    window.location.href = '../reception/rendez_vous.add';
                });
            </script>";
            exit();
        }

        // Insertion du rendez-vous
        $stmt = $db->prepare("
            INSERT INTO rendez_vous (
                Agent_idAgent, Service_idService, contact_externe, 
                email_externe, telephone_externe, date_rendez_vous, 
                heure_debut, heure_fin, objet, description, lieu, 
                statut_rendez_vous, type_rendez_vous, priorite, 
                rappel_active, delai_rappel, commentaires, 
                cree_par, date_creation
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $agentId, $serviceId, $contactExterne,
            $emailExterne, $telephoneExterne, $dateRendezVous,
            $heureDebut, $heureFin, $objet, $description, $lieu,
            $statutRendezVous, $typeRendezVous, $priorite,
            $rappelActive, $delaiRappel, $commentaires,
            $userId
        ]);

        if ($result) {
            // Optionnel: Envoi de notification SMS si numéro fourni
            if (!empty($telephoneExterne)) {
                $dateFormatted = date('d/m/Y', strtotime($dateRendezVous));
                $heureFormatted = date('H:i', strtotime($heureDebut));
                
                // Récupérer le nom de l'agent
                $stmtAgent = $db->prepare("SELECT noms FROM agent WHERE idAgent = ?");
                $stmtAgent->execute([$agentId]);
                $agent = $stmtAgent->fetch(PDO::FETCH_ASSOC);
                
                $message = "Rendez-vous confirmé le {$dateFormatted} à {$heureFormatted} avec {$agent['noms']}. Objet: {$objet}";
                
                // Fonction d'envoi SMS (similaire à celle dans addEmail.php)
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

                $numeroNettoye = nettoyerNumero($telephoneExterne);
                if ($numeroNettoye) {
                    envoyerSMS($numeroNettoye, $message);
                }
            }

            echo "<script>
                Swal.fire({
                    icon: 'success',
                                        title: 'Succès',
                    text: 'Rendez-vous planifié avec succès.'
                }).then(() => {
                    window.location.href = '../reception/rendez_vous.add';
                });
            </script>";
        } else {
            throw new Exception("Erreur lors de l'insertion du rendez-vous");
        }
        
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la planification du rendez-vous: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../reception/rendez_vous.add';
            });
        </script>";
    }
    
} else {
    header("Location: ../reception/rendez_vous.add");
    exit();
}
?>
