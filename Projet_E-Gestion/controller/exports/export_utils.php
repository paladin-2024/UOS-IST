<?php
require_once dirname(dirname(__DIR__)) . '/config/Connexion.php';
require_once dirname(dirname(__DIR__)) . '/assets/html2pdf/vendor/autoload.php';

/**
 * Fonction utilitaire pour générer un PDF avec Html2Pdf
 * 
 * @param string $html Contenu HTML du PDF
 * @param string $filename Nom du fichier à générer
 * @param string $orientation Orientation du document (P ou L)
 * @return void
 */
function generatePdf($html, $filename, $orientation = 'L') {
    try {
        $html2pdf = new Spipu\Html2Pdf\Html2Pdf($orientation, 'A4', 'fr', true, 'UTF-8', array(10, 15, 10, 15));
        $html2pdf->writeHTML($html);
        $html2pdf->output($filename, 'D');
    } catch (Exception $e) {
        echo 'Erreur lors de la génération du PDF: ' . $e->getMessage();
        exit;
    }
}

/**
 * Fonction pour obtenir l'en-tête HTML commun pour les PDF
 * 
 * @param array $configUni Configuration de l'université
 * @param string $title Titre du document
 * @param string $subtitle Sous-titre du document
 * @return string Code HTML de l'en-tête
 */
function getPdfHeader($configUni, $title, $subtitle) {
    $logoPath = '';
    if (isset($configUni['logo']) && !empty($configUni['logo'])) {
        $logoPath = dirname(dirname(__DIR__)) . '/' . $configUni['logo'];
        if (file_exists($logoPath)) {
            $logoPath = '<img src="' . $logoPath . '" width="60" height="70"/>';
        } else {
            $logoPath = '';
        }
    }
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . $title . '</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 10px; }
            h1 { font-size: 14px; font-weight: bold; text-align: center; }
            h2 { font-size: 16px; font-weight: bold; text-align: center; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; }
            table, th, td { border: 1px solid black; }
            th, td { padding: 5px; font-size: 10px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .header { text-align: center; margin-bottom: 20px; }
            .footer { font-size: 12px; margin-top: 20px; text-align: right; }
            .logo { text-align: center; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="logo">
                ' . $logoPath . '
            </div>
            <div class="header">
                <h2>' . htmlspecialchars($configUni['nom']) . '</h2>
                <p>' . htmlspecialchars($configUni['adresse']) . '</p>
                <h3>' . $title . '</h3>
                <p>' . $subtitle . '</p>
            </div>';
}
