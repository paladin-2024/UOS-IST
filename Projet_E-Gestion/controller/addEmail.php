<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';
require_once dirname(__DIR__) . '/models/Agent.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $provenance = $_POST['provenance'] ?? '';
    $depositaire = $_POST['depositaire'] ?? '';
    $dateArrive = $_POST['dateArrive'] ?? '';
    $serviceId = $_POST['Service_idService'] ?? '';
    $userConcerne = $_POST['userConcerne'] ?? '';
    $objet = $_POST['objet'] ?? '';
    $resume = $_POST['resume'] ?? '';
    $userId = $_SESSION['id']; // Assuming the user ID is stored in the session

    //Récupération du numéro de téléphone
    $agent=new Agent();
    $infos=$agent->getAgentById($userConcerne);

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
    $prov=enleverAccents($provenance);
    $obj=enleverAccents($objet);

    // Préparation du SMS
    $msg = "Bonjour {$nom}.\nNouveau courrier en provenance de {$prov}.\nObjet : {$obj}";

    
    try {
        $structure = new Structure();
        $structure->addEmail($provenance, $depositaire, $dateArrive, $serviceId, $userConcerne, $objet, $resume, $userId);

        if($tel==""){
            $lien="";
        }else{
            envoyerSMS(nettoyerNumero($tel),$msg);
        }
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Courriel ajouté avec succès.'
            }).then(() => {
                window.location.href = '../reception/courriel.add';
            });
        </script>";
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout du courriel: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../reception/courriel.add';
            });
        </script>";
    }
    
} else {
    header("Location: ../reception/courriel.add");
    exit();
}
    
?>