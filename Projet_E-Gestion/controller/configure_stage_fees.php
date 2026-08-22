<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Stage.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
$promotionId = isset($_POST['promotion_id']) ? intval($_POST['promotion_id']) : 0;
$stageFees = isset($_POST['stage_fees']) ? $_POST['stage_fees'] : [];

if ($promotionId <= 0) {
echo "<script>
Swal.fire({
icon: 'error',
title: 'Erreur',
text: 'Promotion non spécifiée.'
}).then(() => {
window.location.href = '../?view=stage';
});
</script>";
exit();
}

$stage = new Stage();

try {
// Set the required fees for the promotion
$stage->setRequiredFeesForPromotion($promotionId, $stageFees);

echo "<script>
Swal.fire({
    icon: 'success',
    title: 'Succès',
    text: 'Frais requis pour les stages configurés avec succès.'
            }).then(() => {
    window.location.href = '../?view=stage/fees&promotion={$promotionId}';
});
</script>";
} catch (Exception $e) {
echo "<script>
Swal.fire({
icon: 'error',
title: 'Erreur',
text: '" . addslashes($e->getMessage()) . "'
}).then(() => {
window.location.href = '../?view=stage/fees&promotion={$promotionId}';
});
</script>";
}
} else {
    header("Location: ../?view=stage");
    exit();
}
?>
