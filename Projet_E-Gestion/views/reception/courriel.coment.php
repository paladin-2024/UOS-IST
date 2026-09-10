<?php
include "./views/include/header.php";

$structureModel = new Structure();

$emailId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$email = $emailId > 0 ? $structureModel->getEmailById($emailId) : null;

if (!$email) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Courriel introuvable.'
        }).then(() => {
            window.location.href = 'index.php?view=reception/courriel.add';
        });
    </script>";
    include "./views/include/footer.php";
    exit();
}

$comments = $structureModel->getCourrielComments($emailId);
?>
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Commenter un Courriel</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="index.php?view=reception/courriel.add">Courriels Entrants</a></li>
                <li class="breadcrumb-item active">Commenter</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Courriel : <?= htmlspecialchars($email['objet'] ?? '') ?></h5>
                        <p class="text-muted mb-4">
                            De <?= htmlspecialchars($email['provenance'] ?? '') ?>
                            &mdash; reçu le <?= htmlspecialchars($email['dateArrive'] ?? '') ?>
                        </p>

                        <?php if (empty($comments)): ?>
                            <p class="text-muted">Aucun commentaire pour l'instant.</p>
                        <?php else: ?>
                            <ul class="list-group mb-4">
                                <?php foreach ($comments as $comment): ?>
                                    <li class="list-group-item">
                                        <div class="d-flex justify-content-between">
                                            <strong><?= htmlspecialchars($comment['nomUser'] ?? '') ?></strong>
                                            <small class="text-muted"><?= htmlspecialchars($comment['dateCommentaire'] ?? '') ?></small>
                                        </div>
                                        <div><?= nl2br(htmlspecialchars($comment['commentaire'] ?? '')) ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <form id="commentForm" method="POST" action="controller/comment_courriel.php">
                            <input type="hidden" name="idcouriels_recu" value="<?= (int) $emailId ?>">
                            <div class="mb-3">
                                <label for="commentaire" class="form-label">Ajouter un commentaire <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="commentaire" name="commentaire" rows="3" required></textarea>
                            </div>
                            <div class="d-flex justify-content-end">
                                <a href="index.php?view=reception/courriel.add" class="btn btn-secondary me-2">Retour</a>
                                <button type="submit" class="btn btn-primary"><span class="bi bi-chat-left-text"></span> Commenter</button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>
