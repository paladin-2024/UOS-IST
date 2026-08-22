<?php
error_reporting(E_ALL); ini_set("display_errors", 0);
session_start();
include "config/Connexion.php";
include "config/chargement.php";
include "includes/visitor_tracking.php"; 
charger();

// Enregistrer la visite
enregistrer_visite();

//Récupération des paramètres globaux de l'app
if (isset($_GET['view'])) {
    $filename = "views/" . $_GET['view'] . ".php";
    if (is_file($filename))
        include "views/" . $_GET['view'] . ".php";
    else
        include "views/404.php";
}else {
    include "views/accueil.php";
}
?>