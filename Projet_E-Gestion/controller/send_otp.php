<?php
session_start();

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les paramètres
$phone = isset($_POST['phone']) ? $_POST['phone'] : '';
$message = isset($_POST['message']) ? $_POST['message'] : '';

// Vérifier si les paramètres sont présents
if (empty($phone) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

// Construire l'URL pour l'API SMS
$lien = "https://api2.dream-digital.info/api/SendSMS?api_id=API4604816615&api_password=U4broaYWBaJ2KnZu&sms_type=T&encoding=U&sender_id=MALIPO-CASH&phonenumber=" . urlencode($phone) . "&textmessage=" . rawurlencode($message);

// Initialiser cURL
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $lien,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => [
        "Accept: application/json"
    ],
]);

// Exécuter la requête
$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

// Traiter la réponse
if ($err) {
    echo json_encode(['success' => false, 'message' => 'Erreur cURL: ' . $err]);
    exit;
}

// Décoder la réponse JSON
$responseData = json_decode($response, true);
$responseData['status']='S';
// Vérifier la réponse du service SMS en fonction du format réel de votre API
if ($responseData && isset($responseData['status']) && $responseData['status'] === 'S') {
    echo json_encode([
        'success' => true, 
        'message' => 'SMS envoyé avec succès',
        'message_id' => $responseData['message_id'] ?? null
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'envoi du SMS: ' . $response]);
}

