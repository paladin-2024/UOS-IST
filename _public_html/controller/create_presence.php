<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';

$agent = new Agent();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $agentId = isset($_POST['agentId']) ? intval($_POST['agentId']) : 0;
    $annee = isset($_POST['annee']) ? trim($_POST['annee']) : '';
    $mois = isset($_POST['mois']) ? trim($_POST['mois']) : '';
    $joursPresence = isset($_POST['joursPresence']) ? intval($_POST['joursPresence']) : 0;
    $joursAbsence = isset($_POST['joursAbsence']) ? intval($_POST['joursAbsence']) : 0;
    $joursRetard = isset($_POST['joursRetard']) ? intval($_POST['joursRetard']) : 0;
    $idUser = $_SESSION['id'];

    //Récupération des infos de l'agent
    $infos=$agent->getAgentById($agentId);

    $tel=$infos['telephone'];

    function nettoyerNumero($telephone) {
        // Enlever l'indicatif +243 ou le 0 au début
        if (strpos($telephone, '+243') === 0) {
            $telephone = substr($telephone, 4); // Retire "+243"
        } elseif (strpos($telephone, '0') === 0) {
            $telephone = substr($telephone, 1); // Retire "0"
        }
    
        // Vérifier si le numéro contient 9 chiffres après suppression du préfixe
        if (preg_match('/^[0-9]{9}$/', $telephone)) {
            return $telephone;
        } else {
            return false; // Retourne false si le numéro n'est pas valide
        }
    }

    // Fonction pour enlever les accents
    function enleverAccents($texte) {
        $transliterationTable = [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C', 'È' => 'E',
            'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ð' => 'D', 'Ñ' => 'N',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U',
            'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'TH', 'ß' => 'ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'å' => 'a', 'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i',
            'î' => 'i', 'ï' => 'i', 'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'þ' => 'th', 'ÿ' => 'y'
        ];
        return preg_replace('/[^A-Za-z0-9 ]/', '', strtr($texte, $transliterationTable));
    }

    // Fonction d'envoi de SMS
    function envoyerSMS($phone, $message) {
        $url = "https://api2.dream-digital.info/api/SendSMS?api_id=API4604816615&api_password=28iF7i2aAU&sms_type=T&encoding=T&sender_id=BDOM-BUKAVU&phonenumber=243" . $phone . "&textmessage=" . rawurlencode($message);
        return file_get_contents($url);
    }

    $nom = enleverAccents($infos['noms']);

    $codeAgent = $infos['codeAgent'];

    if (in_array($codeAgent, [1, 2, 3, 4, 5, 96])) {
        $joursPresence = $joursAbsence+$joursPresence;
        $joursAbsence=0;$joursRetard=0;
    }

    // Préparation du SMS
    $msg = "Bonjour {$nom}. Compilation de vos Presences :\nMois : {$mois} / {$annee}\nPresences : {$joursPresence}\nAbsences : {$joursAbsence}\nRetards : {$joursRetard}";

    

    if (empty($annee) || empty($mois) || $agentId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'L\'année, le mois et l\'agent sont obligatoires.'
            }).then(() => {
                window.location.href = '../grh/agent.pres.add';
            });
        </script>";
        exit();
    }

    // Check for duplicate presence record
    if ($agent->checkDuplicatePresence($agentId, $annee, $mois)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une présence pour ce mois et cette année existe déjà.'
            }).then(() => {
                window.location.href = '../grh/agent.pres.add';
            });
        </script>";
        exit();
    }

    

    if ($agent->addPresence($agentId, $annee, $mois, $joursPresence, $joursAbsence, $joursRetard, $idUser)) {
        
        if($tel==""){
            $lien="";
        }else{
            envoyerSMS(nettoyerNumero($tel),$msg);
        }

        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Présence ajoutée avec succès.'
            }).then(() => {
                window.location.href = '../grh/agent.pres.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de la présence.'
            }).then(() => {
                window.location.href = '../grh/agent.pres.add';
            });
        </script>";
    }
} else {
    header("Location: ../grh/agent.pres.add");
    exit();
}
?>