<?php
include "./views/include/header.php";
error_reporting(E_ALL); ini_set("display_errors", 1);
$db = Connexion::getInstance()->getPDO();

// Récupération de l'ID du devis
$devisId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($devisId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Devis non trouvé'
        }).then(() => {
            window.location.href = 'ventes/devis/devis.list';
        });
    </script>";
    exit;
}

// Récupération des détails du devis
$query = "SELECT d.*, c.nom_client, c.code_client, c.telephone, c.email, c.adresse 
          FROM devis d 
          JOIN client c ON d.id_client = c.id_client 
          WHERE d.id_devis = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $devisId, PDO::PARAM_INT);
$stmt->execute();
$devis = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$devis) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Devis non trouvé'
        }).then(() => {
            window.location.href = 'ventes/devis/devis.list';
        });
    </script>";
    exit;
}

// Récupération des lignes du devis
$queryLignes = "SELECT ld.*, p.code_produit, p.libelle_produit 
                FROM ligne_devis ld 
                JOIN produit p ON ld.id_produit = p.id_produit 
                WHERE ld.id_devis = :id_devis";
$stmtLignes = $db->prepare($queryLignes);
$stmtLignes->bindParam(':id_devis', $devisId, PDO::PARAM_INT);
$stmtLignes->execute();
$lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

// Récupération des informations sur les utilisateurs (création et validation)
$queryUserCreation = "SELECT \"nomUser\" FROM t_users WHERE \"idUser\" = :id";
$stmtUserCreation = $db->prepare($queryUserCreation);
$stmtUserCreation->bindParam(':id', $devis['id_user_creation'], PDO::PARAM_INT);
$stmtUserCreation->execute();
$userCreation = $stmtUserCreation->fetch(PDO::FETCH_ASSOC);

$userValidation = null;
if ($devis['id_user_validation']) {
    $queryUserValidation = "SELECT \"nomUser\" FROM t_users WHERE \"idUser\" = :id";
    $stmtUserValidation = $db->prepare($queryUserValidation);
    $stmtUserValidation->bindParam(':id', $devis['id_user_validation'], PDO::PARAM_INT);
    $stmtUserValidation->execute();
    $userValidation = $stmtUserValidation->fetch(PDO::FETCH_ASSOC);
}

// Calcul de la date de validité
$dateValidite = date('Y-m-d', strtotime($devis['date_devis'] . ' + ' . $devis['validite'] . ' days'));
$isExpired = (date('Y-m-d') > $dateValidite);
?>


<main id="main" class="main">
    <div class="pagetitle">
        <h1>DÉTAILS DU DEVIS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Ventes</li>
                <li class="breadcrumb-item"><a href="ventes/devis/devis.list">Devis</a></li>
                <li class="breadcrumb-item active">Détails</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">
                                Devis N° <?= htmlspecialchars($devis['numero_devis']) ?>
                                <?php
                                switch ($devis['etat']) {
                                    case 'En cours':
                                        echo '<span class="badge bg-warning ms-2">En cours</span>';
                                        break;
                                    case 'Validé':
                                        echo '<span class="badge bg-success ms-2">Validé</span>';
                                        break;
                                    case 'Transformé':
                                        echo '<span class="badge bg-info ms-2">Transformé</span>';
                                        break;
                                    case 'Annulé':
                                        echo '<span class="badge bg-danger ms-2">Annulé</span>';
                                        break;
                                }
                                
                                if ($isExpired && $devis['etat'] != 'Annulé' && $devis['etat'] != 'Transformé') {
                                    echo '<span class="badge bg-danger ms-2">Expiré</span>';
                                }
                                ?>
                            </h5>
                            
                            <!-- Remplacer la section du menu déroulant des actions dans views/ventes/devis/devis.view.php -->
                            <div class="btn-group">
                                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    Actions
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php if ($devis['etat'] == 'En cours'): ?>
                                        <li><a class="dropdown-item" href="ventes/devis/devis.edit&id=<?= $devisId ?>"><i class="bi bi-pencil me-2"></i>Modifier</a></li>
                                        <li><a class="dropdown-item" onclick="validateDevis(<?= $devisId ?>)"><i class="bi bi-check-circle me-2"></i>Valider</a></li>
                                        <li><a class="dropdown-item" onclick="cancelDevis(<?= $devisId ?>)"><i class="bi bi-x-circle me-2"></i>Annuler</a></li>
                                    <?php endif; ?>
                                    <?php if ($devis['etat'] == 'Validé'): ?>
                                        <li><a class="dropdown-item" href="ventes/commandes/commandes.add&devis=<?= $devisId ?>"><i class="bi bi-arrow-right-circle me-2"></i>Transformer en commande</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="ventes/devis/devis.print&id=<?= $devisId ?>" target="_blank"><i class="bi bi-printer me-2"></i>Format standard</a></li>
                                            <li><a class="dropdown-item" href="controller/devis_pdf.php?id=<?= $devisId ?>" target="_blank"><i class="bi bi-file-earmark-pdf me-2"></i>Facture Proforma</a></li>
                                    <li><a class="dropdown-item" onclick="sendDevisByEmail(<?= $devisId ?>)"><i class="bi bi-envelope me-2"></i>Envoyer par email</a></li>
                                    <li><a class="dropdown-item" onclick="duplicateDevis(<?= $devisId ?>)"><i class="bi bi-files me-2"></i>Dupliquer</a></li>
                                </ul>
                            </div>

                        </div>

                        <!-- Informations du devis -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Informations générales</h5>
                                        <table class="table table-bordered">
                                            <tr>
                                                <th width="40%">Numéro</th>
                                                <td><?= htmlspecialchars($devis['numero_devis']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Date</th>
                                                <td><?= date('d/m/Y', strtotime($devis['date_devis'])) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Validité</th>
                                                <td>
                                                    <?= date('d/m/Y', strtotime($dateValidite)) ?>
                                                    <span class="badge <?= $isExpired ? 'bg-danger' : 'bg-success' ?>"><?= $devis['validite'] ?> jours</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>État</th>
                                                <td>
                                                    <?php
                                                    switch ($devis['etat']) {
                                                        case 'En cours':
                                                            echo '<span class="badge bg-warning">En cours</span>';
                                                            break;
                                                        case 'Validé':
                                                            echo '<span class="badge bg-success">Validé</span>';
                                                            break;
                                                        case 'Transformé':
                                                            echo '<span class="badge bg-info">Transformé</span>';
                                                            break;
                                                        case 'Annulé':
                                                            echo '<span class="badge bg-danger">Annulé</span>';
                                                            break;
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Observation</th>
                                                <td><?= nl2br(htmlspecialchars($devis['observation'] ?? 'N/A')) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Client</h5>
                                        <table class="table table-bordered">
                                            <tr>
                                                <th width="40%">Code</th>
                                                <td><?= htmlspecialchars($devis['code_client']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Nom</th>
                                                <td><?= htmlspecialchars($devis['nom_client']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Téléphone</th>
                                                <td><?= htmlspecialchars($devis['telephone'] ?? 'N/A') ?></td>
                                            </tr>
                                            <tr>
                                                <th>Email</th>
                                                <td><?= htmlspecialchars($devis['email'] ?? 'N/A') ?></td>
                                            </tr>
                                            <tr>
                                                <th>Adresse</th>
                                                <td><?= nl2br(htmlspecialchars($devis['adresse'] ?? 'N/A')) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Détails des produits -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title">Produits</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Produit</th>
                                                <th>Désignation</th>
                                                <th>Quantité</th>
                                                <th>Prix unitaire</th>
                                                <th>Remise</th>
                                                <th>Montant HT</th>
                                                <th>TVA</th>
                                                <th>Montant TTC</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($lignes)): ?>
                                                <tr>
                                                    <td colspan="9" class="text-center">Aucun produit trouvé</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($lignes as $ligne): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($ligne['code_produit']) ?></td>
                                                        <td><?= htmlspecialchars($ligne['libelle_produit']) ?></td>
                                                        <td><?= htmlspecialchars($ligne['designation']) ?></td>
                                                        <td class="text-end"><?= number_format($ligne['quantite'], 2, ',', ' ') ?></td>
                                                        <td class="text-end"><?= number_format($ligne['prix_unitaire'], 2, ',', ' ') ?> USD</td>
                                                        <td class="text-end">
                                                            <?php if ($ligne['remise'] > 0): ?>
                                                                <?= number_format($ligne['remise'], 2, ',', ' ') ?>% 
                                                                (<?= number_format($ligne['montant_remise'], 2, ',', ' ') ?> USD)
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-end"><?= number_format($ligne['montant_ht'], 2, ',', ' ') ?> USD</td>
                                                        <td class="text-end"><?= number_format($ligne['montant_tva'], 2, ',', ' ') ?> USD</td>
                                                        <td class="text-end"><?= number_format($ligne['montant_ttc'], 2, ',', ' ') ?> USD</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="6" class="text-end">Total HT:</th>
                                                <td class="text-end"><?= number_format($devis['montant_ht'], 2, ',', ' ') ?> USD</td>
                                                <td colspan="2"></td>
                                            </tr>
                                            <tr>
                                                <th colspan="6" class="text-end">TVA (<?= number_format($devis['taux_tva'], 2, ',', ' ') ?>%):</th>
                                                <td class="text-end"><?= number_format($devis['montant_tva'], 2, ',', ' ') ?> USD</td>
                                                <td colspan="2"></td>
                                            </tr>
                                            <tr>
                                                <th colspan="6" class="text-end">Total TTC:</th>
                                                <td class="text-end" colspan="3"><strong><?= number_format($devis['montant_ttc'], 2, ',', ' ') ?> USD</strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Informations de traçabilité -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title">Informations de traçabilité</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Créé par:</strong> <?= htmlspecialchars($userCreation['nomUser'] ?? 'N/A') ?></p>
                                        <p><strong>Date de création:</strong> <?= date('d/m/Y H:i', strtotime($devis['date_creation'])) ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($devis['id_user_validation']): ?>
                                            <p><strong>Validé par:</strong> <?= htmlspecialchars($userValidation['nomUser'] ?? 'N/A') ?></p>
                                            <p><strong>Date de validation:</strong> <?= date('d/m/Y H:i', strtotime($devis['date_validation'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    function validateDevis(id) {
        Swal.fire({
            title: 'Confirmer la validation',
            text: "Voulez-vous vraiment valider ce devis ?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, valider',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/validate_devis.php?id=' + id + '&action=validate';
            }
        });
    }

    function cancelDevis(id) {
        Swal.fire({
            title: 'Confirmer l\'annulation',
            text: "Voulez-vous vraiment annuler ce devis ?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, annuler',
            cancelButtonText: 'Non'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/validate_devis.php?id=' + id + '&action=cancel';
            }
        });
    }

    function sendDevisByEmail(id) {
        Swal.fire({
            title: 'Envoyer par email',
            text: "Voulez-vous envoyer ce devis par email au client ?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, envoyer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/send_devis_email.php?id=' + id;
            }
        });
    }
    // Ajouter ce code dans la section script existante
function duplicateDevis(id) {
    Swal.fire({
        title: 'Dupliquer le devis',
        text: "Voulez-vous créer une copie de ce devis ?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, dupliquer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'controller/duplicate_devis.php?id=' + id;
        }
    });
}

// Gestion du sous-menu déroulant
$(document).ready(function(){
    $('.dropdown-submenu > a').on("click", function(e) {
        var submenu = $(this).next('ul');
        submenu.toggleClass('show');
        e.stopPropagation();
        e.preventDefault();
    });
});

function sendDevisByEmail(id) {
    // Récupérer les informations du client
    fetch('controller/get_client_info.php?devis_id=' + id, {
        method: 'GET',
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: data.error
            });
            return;
        }
        
        // Construire le sujet et le message par défaut
        const subject = `Devis N° ${data.numero_devis} - ${data.nom_client}`;
        const message = `Cher client,\n\nVeuillez trouver ci-joint notre devis N° ${data.numero_devis} d'un montant de ${parseFloat(data.montant_ttc).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} USD.\n\nCe devis est valable pour une durée de ${data.validite} jours à compter de sa date d'émission.\n\nN'hésitez pas à nous contacter pour toute information complémentaire.\n\nCordialement,\nL'équipe commerciale`;
        
        // Afficher la fenêtre modale
        Swal.fire({
            title: 'Envoyer le devis par email',
            html: `
                <form id="emailForm" class="text-start">
                    <input type="hidden" name="id_devis" value="${id}">
                    <div class="mb-3">
                        <label for="recipient_email" class="form-label">Email du destinataire</label>
                        <input type="email" class="form-control" id="recipient_email" name="recipient_email" value="${data.email || ''}" required>
                    </div>
                    <div class="mb-3">
                        <label for="recipient_name" class="form-label">Nom du destinataire</label>
                        <input type="text" class="form-control" id="recipient_name" name="recipient_name" value="${data.nom_client || ''}" required>
                    </div>
                    <div class="mb-3">
                        <label for="email_subject" class="form-label">Sujet</label>
                        <input type="text" class="form-control" id="email_subject" name="email_subject" value="${subject}" required>
                    </div>
                    <div class="mb-3">
                        <label for="email_message" class="form-label">Message</label>
                        <textarea class="form-control" id="email_message" name="email_message" rows="6" required>${message}</textarea>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="include_pdf" name="include_pdf" checked>
                        <label class="form-check-label" for="include_pdf">Joindre le PDF du devis</label>
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: 'Envoyer',
            cancelButtonText: 'Annuler',
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            width: '600px',
            preConfirm: () => {
                const form = document.getElementById('emailForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return false;
                }
                
                return {
                    id_devis: form.elements.id_devis.value,
                    recipient_email: form.elements.recipient_email.value,
                    recipient_name: form.elements.recipient_name.value,
                    email_subject: form.elements.email_subject.value,
                    email_message: form.elements.email_message.value,
                    include_pdf: form.elements.include_pdf.checked
                };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Afficher un indicateur de chargement
                Swal.fire({
                    title: 'Envoi en cours...',
                    html: 'Veuillez patienter pendant l\'envoi de l\'email.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Créer un formulaire pour soumettre les données
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'controller/send_devis_email.php';
                form.style.display = 'none';
                
                // Ajouter les champs du formulaire
                Object.entries(result.value).forEach(([key, value]) => {
                    const input = document.createElement('input');
                    input.type = key === 'include_pdf' ? 'checkbox' : 'text';
                    input.name = key;
                    input.value = value;
                    if (key === 'include_pdf' && value) {
                        input.checked = true;
                    }
                    form.appendChild(input);
                });
                
                // Ajouter le formulaire au document et le soumettre
                document.body.appendChild(form);
                form.submit();
            }
        });
    })
    .catch(error => {
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Une erreur est survenue lors de la récupération des informations du client.'
        });
    });
}


</script>

<?php include "./views/include/footer.php"; ?>

