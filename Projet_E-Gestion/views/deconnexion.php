<?php
$activite=new SuperUser();

function getUserIP() {
  if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
      return $_SERVER['HTTP_CLIENT_IP']; // IP depuis un fournisseur d'accès
  } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]); // IP derrière un proxy
  } else {
      return $_SERVER['REMOTE_ADDR']; // IP directe
  }
}


if (isset($_SESSION['id'])) {
  $activite->logUserActivity(
    $_SESSION['id'],
    'logout',
    'Déconnexion réussie avec succès',
    getUserIP(),
    $_SERVER['HTTP_USER_AGENT']
  );
}

session_destroy();
  session_unset();
  header("Location:accueil");
  