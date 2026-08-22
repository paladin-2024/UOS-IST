<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Banque.php';

function redirectToBankList($message, $type = 'error') {
    echo "<script>
        Swal.fire({
            icon: '$type',
            title: '" . ucfirst($type) . "',
            text: '$message'
        }).then(() => {
            window.location.href = '../comptabilite/banque.edit';
        });
    </script>";
    exit();
}

function handleDeleteBanque($banque) {
    $idBanque = isset($_POST['idBanque']) ? intval($_POST['idBanque']) : 0;

    if ($idBanque <= 0) {
        redirectToBankList('ID de banque invalide.');
    }

    if ($banque->deleteBanque($idBanque)) {
        redirectToBankList('Banque supprimée avec succès.', 'success');
    } else {
        redirectToBankList('Erreur lors de la suppression de la banque.');
    }
}

$banque = new Banque();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    handleDeleteBanque($banque);
} else {
    header("Location: ../banque/list");
    exit();
}
?>