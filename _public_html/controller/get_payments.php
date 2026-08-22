<?php
require_once '../models/Connexion.php'; 
require_once '../models/Structure.php'; // Adjust the path as necessary

header('Content-Type: application/json');

if (!isset($_GET['idInvoice'])) {
    echo json_encode([]);
    exit;
}

$idInvoice = intval($_GET['idInvoice']);
$structureModel = new Structure();

// Assuming you have a method to get payments by invoice ID
$payments = $structureModel->getPaymentsByInvoiceId($idInvoice);

echo json_encode($payments);