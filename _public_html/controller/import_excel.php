<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
error_reporting(E_ALL); ini_set("display_errors", 0);
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excelFile'])) {
    $file = $_FILES['excelFile'];
    $year = $_POST['annee'];
    $month = $_POST['mois'];
    $jours = $_POST['jours'];
    $heure = $_POST['heure'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        die("Upload failed with error code " . $file['error']);
    }

    $fileType = IOFactory::identify($file['tmp_name']);
    $validTypes = ['Xlsx', 'Xls', 'Csv'];
    if (!in_array($fileType, $validTypes)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Invalid file type. Please upload an Excel or CSV file.'
            }).then(() => {
                window.location.href = '../grh/agent.pres.add';
            });
        </script>";
    }

    // Default column positions
    $agentCodeColumn = 3;
    $dateTimeColumn = 9;

    // Check if user specified column positions
    if (isset($_POST['agentCodeColumn']) && is_numeric($_POST['agentCodeColumn'])) {
        $agentCodeColumn = (int)$_POST['agentCodeColumn'];
    }
    if (isset($_POST['dateTimeColumn']) && is_numeric($_POST['dateTimeColumn'])) {
        $dateTimeColumn = (int)$_POST['dateTimeColumn'];
    }

    $spreadsheet = IOFactory::load($file['tmp_name']);
    $worksheet = $spreadsheet->getActiveSheet();

    $agentModel = new Agent();
    $agents = $agentModel->getAgentsByUserAccess($_SESSION['id']);

    $workingDays = $jours;
    $arrivalThreshold = new DateTime($heure);

    $validPresenceData = false;

    foreach ($agents as $agent) {
        $codeAgent = $agent['codeAgent'];
        $presenceCount = 0;
        $retardCount = 0;
        $datesProcessed = [];

        if (in_array($codeAgent, [1, 2, 3, 4, 5, 96])) {
            $presenceCount = $workingDays;
        } else {
            foreach ($worksheet->getRowIterator(2) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $data = [];
                foreach ($cellIterator as $cell) {
                    $data[] = $cell->getValue();
                }

                list(, , $excelCodeAgent, , , , , , $dateTime) = $data;

                // Use user-specified columns
                $excelCodeAgent = $data[$agentCodeColumn - 1];
                $dateTime = $data[$dateTimeColumn - 1];

                


                if ($excelCodeAgent == $codeAgent) {
                    if (is_numeric($dateTime)) {
                        $dateTimeObj = Date::excelToDateTimeObject($dateTime);
                    } else {
                        $dateTimeObj = DateTime::createFromFormat('d/m/Y H:i', $dateTime);
                    }

                    if ($dateTimeObj === false) {
                        echo "<script>
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: 'Format de date non valide dans la colonne " . $row->getRowIndex() . ". SVP, utiliser le format d/m/Y H:i.'
                            }).then(() => {
                                window.location.href = '../grh/agent.pres.add';
                            });
                        </script>";
                        exit;
                    }

                    $date = $dateTimeObj->format('Y-m-d');
                    $arrivalTime = $dateTimeObj->format('H:i');
                    $dateKey = $date . $codeAgent;

                    // Only consider the first occurrence of the date for each agent
                    /*
                    if (!isset($datesProcessed[$dateKey])) {
                        $datesProcessed[$dateKey] = true;
                        if ($presenceCount < $workingDays) {
                            $presenceCount++;
                        }

                        $arrivalDateTime = DateTime::createFromFormat('H:i', $arrivalTime);
                        if ($arrivalDateTime > $arrivalThreshold) {
                            $retardCount++;
                        }
                    }
                    */
                    
                    if (!in_array($dateKey, $datesProcessed)) {
                        $datesProcessed[] = $dateKey;
                        if ($presenceCount < $workingDays) {
                            $presenceCount++;
                        }

                        $arrivalDateTime = DateTime::createFromFormat('H:i', $arrivalTime);
                        if ($arrivalDateTime > $arrivalThreshold) {
                            $retardCount++;
                        }
                        $validPresenceData = true;
                    }
                        
                }
            }
        }

        $absenceCount = $workingDays - $presenceCount;

        if ($validPresenceData && !$agentModel->checkDuplicatePresence($agent['idAgent'], $year, $month)) {
            $agentModel->addPresence($agent['idAgent'], $year, $month, $presenceCount, $absenceCount, $retardCount, $_SESSION['id']);

            $tel = $agent['telephone'];
            if (!empty($tel)) {
                $nom = enleverAccents($agent['noms']);
                $msg = "Bonjour {$nom}. Compilation de vos Presences :\nMois : {$month} / {$year}\nPresences : {$presenceCount}\nAbsences : {$absenceCount}\nRetards : {$retardCount}";
                //envoyerSMS(nettoyerNumero($tel), $msg);
            }
        }
    }

    if (!$validPresenceData) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le fichier ne contient pas de données de présence valides.'
            }).then(() => {
                window.location.href = '../grh/agent.pres.add';
            });
        </script>";
        exit;
    }


    echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Présences compilées avec succès.'
            }).then(() => {
                window.location.href = '../grh/agent.pres.add';
            });
        </script>";
} else {
    echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'No file uploaded.'
            }).then(() => {
                window.location.href = '../grh/agent.pres.add';
            });
        </script>";
}

function nettoyerNumero($telephone) {
    if (strpos($telephone, '+243') === 0) {
        $telephone = substr($telephone, 4);
    } elseif (strpos($telephone, '0') === 0) {
        $telephone = substr($telephone, 1);
    }

    if (preg_match('/^[0-9]{9}$/', $telephone)) {
        return $telephone;
    } else {
        return false;
    }
}

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

function envoyerSMS($phone, $message) {
    $url = "https://api2.dream-digital.info/api/SendSMS?api_id=API4604816615&api_password=28iF7i2aAU&sms_type=T&encoding=T&sender_id=BDOM-BUKAVU&phonenumber=243" . $phone . "&textmessage=" . rawurlencode($message);
    return file_get_contents($url);
}
?>