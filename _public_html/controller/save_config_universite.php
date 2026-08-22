<?php
session_start();
//require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $universite = new Universite();
    $response = ['success' => false, 'message' => ''];

    try {
        // Validation des données requises
        if (empty($_POST['nom']) || empty($_POST['type_etablissement'])) {
            throw new Exception("Les champs obligatoires doivent être remplis");
        }

        // Prepare data array
        $configData = [
            'type_etablissement' => $_POST['type_etablissement'],
            'sigle' => $_POST['sigle'] ?? '',
            'nom' => $_POST['nom'],
            'nom_application' => $_POST['nom_application'] ?? 'E-GESTION',
            'ministere_tutelle' => $_POST['ministere_tutelle'] ?? '',
            'adresse' => $_POST['adresse'] ?? '',
            'ville' => $_POST['ville'] ?? '',
            'pays' => $_POST['pays'] ?? '',
            'telephone' => $_POST['telephone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'site_web' => $_POST['site_web'] ?? '',
            'nom_responsable' => $_POST['nom_responsable'] ?? '',
            'titre_responsable' => $_POST['titre_responsable'] ?? '',
            'nom_responsable_academique' => $_POST['nom_responsable_academique'] ?? '',
            'titre_responsable_academique' => $_POST['titre_responsable_academique'] ?? '',
            'nom_secretaire_general' => $_POST['nom_secretaire_general'] ?? '',
            'titre_secretaire_general' => $_POST['titre_secretaire_general'] ?? 'Secrétaire Général Académique',
            'credit_heure' => intval($_POST['credit_heure'] ?? 25), // Ajout du paramètre crédit_heure
            'ponderation_cc_defaut' => floatval($_POST['ponderation_cc_defaut'] ?? 0.4),
            'ponderation_ex_defaut' => floatval($_POST['ponderation_ex_defaut'] ?? 0.6),
            'flexpay_actif' => intval($_POST['flexpay_actif'] ?? 0),
            'flexpay_merchant' => $_POST['flexpay_merchant'] ?? '',
            'flexpay_token' => $_POST['flexpay_token'] ?? '',
            'flexpay_callback_url' => $_POST['flexpay_callback_url'] ?? '',
            'flexpay_timeout' => intval($_POST['flexpay_timeout'] ?? 30),
            'flexpay_endpoint_mobile_money' => $_POST['flexpay_endpoint_mobile_money'] ?? 'https://backend.flexpay.cd/api/rest/v1/paymentService',
            'flexpay_endpoint_card_payment' => $_POST['flexpay_endpoint_card_payment'] ?? 'https://backend.flexpay.cd/api/rest/v1.1/pay',
            'flexpay_endpoint_check_transaction' => $_POST['flexpay_endpoint_check_transaction'] ?? 'https://backend.flexpay.cd/api/rest/v1/check/'
        ];

        // Gestion des fichiers
        $uploadDir = dirname(__DIR__) . '/uploads/config/';
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                throw new Exception("Impossible de créer le dossier d'upload");
            }
        }

        // Traitement des fichiers
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        foreach (['logo', 'signature_responsable', 'cachet'] as $fileField) {
            if (isset($_FILES[$fileField]) && $_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$fileField];

                // Vérifications de sécurité
                if (!in_array($file['type'], $allowedTypes)) {
                    throw new Exception("Type de fichier non autorisé pour $fileField");
                }
                if ($file['size'] > $maxSize) {
                    throw new Exception("Le fichier $fileField est trop volumineux");
                }

                $fileName = $fileField . '_' . time() . '_' . basename($file['name']);
                $filePath = $uploadDir . $fileName;

                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $configData[$fileField] = 'uploads/config/' . $fileName;
                } else {
                    throw new Exception("Erreur lors de l'upload du fichier $fileField");
                }
            }
        }

        // Sauvegarde de la configuration
        if ($universite->saveConfiguration($configData)) {
            $response['success'] = true;
            $response['message'] = 'Configuration enregistrée avec succès';
        } else {
            throw new Exception('Erreur lors de l\'enregistrement de la configuration');
        }

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("Erreur configuration université: " . $e->getMessage());
    }

    // Retour de la réponse JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
} else {
    header('Location: ../configuration/config_universite');
    exit();
}