<?php
require_once './path/to/your/database/connection.php'; // Adjust the path as needed
require_once './path/to/Agent.php'; // Adjust the path as needed

header('Content-Type: application/json');

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