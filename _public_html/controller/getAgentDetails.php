<?php
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';


if (isset($_GET['idAgent'])) {
    $agentId = $_GET['idAgent'];
    $agentModel = new Agent();

    // Fetch contracts
    $contracts = $agentModel->getContractsByAgent($agentId);

    // Fetch family members
    $familyMembers = $agentModel->getFamilyMembersByAgent($agentId);

    // Fetch documents
    $documents = $agentModel->getDocumentsByAgent($agentId);

    // Prepare the response
    $response = [
        'contracts' => $contracts,
        'familyMembers' => $familyMembers,
        'documents' => $documents
    ];

    echo json_encode($response);
} else {
    echo json_encode(['error' => 'Agent ID not provided']);
}
?>