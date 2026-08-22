<?php
session_start();
require_once '../config/Connexion.php';
require_once '../models/Agent.php';
require_once '../models/Structure.php';
require_once '../models/Service.php';
require_once '../models/Grade.php';

// Vérification de la connexion et des droits
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$agentId = isset($_GET['idAgent']) ? intval($_GET['idAgent']) : 0;

if ($agentId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID d\'agent invalide']);
    exit;
}

$agent = new Agent();
$structure = new Structure();
$service = new Service();
$grade = new Grade();

// Récupérer les détails de l'agent
$agentDetails = $agent->getAgentById($agentId);

if (!$agentDetails) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Agent non trouvé']);
    exit;
}

// Récupérer les informations supplémentaires (structure, service, grade)
$structureInfo = $structure->getStructureById($agentDetails['idStructure']);
$serviceInfo = !empty($agentDetails['idService']) ? $service->getServiceById($agentDetails['idService']) : null;
$gradeInfo = !empty($agentDetails['grade_id']) ? $grade->getGradeById($agentDetails['grade_id']) : null;

// Formater la date de naissance si elle existe
$dateNaissance = !empty($agentDetails['dateNaissance']) ? date('d/m/Y', strtotime($agentDetails['dateNaissance'])) : '-';

// Créer un tableau de données formatées
$formattedData = [
    'idAgent' => $agentDetails['idAgent'],
    'noms' => $agentDetails['noms'],
    'matricule' => $agentDetails['matricule'] ?? '-',
    'type_agent' => $agentDetails['type_agent'] ?? '-',
    'grade' => $gradeInfo ? $gradeInfo['designation'] : '-',
    'structure' => $structureInfo ? $structureInfo['designation'] : '-',
    'service' => $serviceInfo ? $serviceInfo['designation'] : '-',
    'telephone' => $agentDetails['telephone'] ?? '-',
    'email' => $agentDetails['email'] ?? '-',
    'sexe' => $agentDetails['sexe'],
    'etatCivil' => $agentDetails['etatCivil'],
    'dateNaissance' => $dateNaissance,
    'lieuNaissance' => $agentDetails['lieuNaissance'] ?? '-',
    'niveauEtude' => $agentDetails['niveauEtude'],
    'codeAgent' => $agentDetails['codeAgent'] ?? '-',
    'photo' => $agentDetails['photo']
];

// Retourner les données au format JSON
header('Content-Type: application/json');
echo json_encode($formattedData);
exit;
