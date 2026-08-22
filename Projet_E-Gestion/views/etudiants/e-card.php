<?php
include "./views/include/header.php";

// Initialiser l'objet Universite pour récupérer les informations
$universite = new Universite();
$configUniversite = $universite->getConfigurationUniversite();

// Vérifier que les données sont disponibles
if (!isset($_SESSION['ecard_data'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Données de carte non disponibles',
            confirmButtonText: 'Retour'
        }).then(() => {
            window.location.href = 'etudiants/etudiant.inscrit';
        });
    </script>";
    include "./views/include/footer.php";
    exit();
}

$etudiant = $_SESSION['ecard_data']['etudiant'];
$qrCode = $_SESSION['ecard_data']['qr_code'];
$dateGeneration = $_SESSION['ecard_data']['date_generation'];
$dateExpiration = $_SESSION['ecard_data']['date_expiration'];
$hologramData = $_SESSION['ecard_data']['hologram_data'];
$colorScheme = $_SESSION['ecard_data']['color_scheme'];
$cardId = $_SESSION['ecard_data']['card_id'];

// Extraire les couleurs du schéma
[$primaryColor, $secondaryColor, $textColor, $backgroundColor] = $colorScheme;

// Récupérer le chemin du logo
$logoPath = isset($configUniversite['logo']) && !empty($configUniversite['logo']) ? 
    '' . $configUniversite['logo'] : '';

// Récupérer l'année académique
$anneeActuelle = date('Y');
$anneeProchaine = $anneeActuelle + 1;
$periodeAnnee = $anneeActuelle . ' - ' . $anneeProchaine;

// Nettoyer la session après utilisation
unset($_SESSION['ecard_data']);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>CARTE ÉLECTRONIQUE ÉTUDIANT</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item"><a href="etudiants/etudiant.inscrit">Étudiants</a></li>
                <li class="breadcrumb-item active">E-Carte</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card">
                    <div class="card-body pt-3">
                        <div class="d-flex justify-content-between mb-3">
                            <h5 class="card-title">E-Carte Étudiant</h5>
                            <div>
                                <button class="btn btn-primary me-2" id="printBtn">
                                    <i class="bi bi-printer"></i> Imprimer
                                </button>
                                <a class="btn btn-success" href="controller/generate_ecard_pdf.php?id=<?= $cardId ?>">
                                    <i class="bi bi-download"></i> Télécharger PDF
                                </a>
                            </div>
                        </div>
                        
                        <!-- Information sur la carte -->
                        <div class="alert alert-info mb-3">
                            <strong>ID Carte:</strong> <?= htmlspecialchars($cardId) ?><br>
                            <strong>Date d'émission:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($dateGeneration))) ?><br>
                            <strong>Date d'expiration:</strong> <?= htmlspecialchars(date('d/m/Y', strtotime($dateExpiration))) ?>
                        </div>
                        
                        <!-- Remplacer la div du container de la carte par celle-ci -->
                        <div id="eCardContainer" class="mx-auto" style="width: 350px; background-color: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); margin-bottom: 20px;">
                            <!-- En-tête avec effet polygone -->
                            <div style="background-color: <?= $primaryColor ?>; position: relative; padding: 8px; color: white; clip-path: polygon(0 0, 100% 0, 100% 90%, 0 100%);">
                                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: <?= $primaryColor ?>; clip-path: polygon(0 0, 100% 0, 95% 100%, 0 85%); z-index: -1;"></div>
                                
                                <!-- Logo -->
                                <div style="position: absolute; top: 8px; left: 8px; width: 40px; height: 40px; background-color: white; border-radius: 3px; overflow: hidden; display: flex; justify-content: center; align-items: center;">
                                    <?php if (!empty($logoPath) && file_exists($logoPath)): ?>
                                        <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;">
                                    <?php else: ?>
                                        <i class="bi bi-building" style="font-size: 24px; color: <?= $primaryColor ?>;"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- En-tête texte -->
                                <div style="margin-left: 50px; padding-top: 5px; text-align:center">
                                    <h1 style="font-size: 11px; font-weight: bold; margin-bottom: 2px;"><?= htmlspecialchars(strtoupper($configUniversite['nom'] ?? 'UNIVERSITÉ')) ?></h1>
                                </div>
                                
                                <!-- Année académique -->
                                <div style="text-align: center; margin-top: 5px; font-size: 14px; font-weight: bold;">
                                    Année académique : <?= $periodeAnnee ?>
                                </div>
                            </div>
                            
                            <!-- Titre de la carte -->
                            <div style="background-color: <?= $secondaryColor ?>; color: white; padding: 5px 10px; font-size: 14px; font-weight: bold; margin-top: 5px; clip-path: polygon(0 0, 100% 0, 100% 100%, 0 85%);">
                                CARTE D'ÉTUDIANT PROVISOIRE
                            </div>
                            
                            <!-- Contenu principal -->
                            <div style="display: flex; padding: 8px;">
                                <!-- Photo de l'étudiant (circulaire avec bordure) -->
                                <div style="width: 90px; height: 90px; border-radius: 50%; border: 4px solid <?= $primaryColor ?>; overflow: hidden; margin-right: 10px;">
                                    <?php if (!empty($etudiant['photo'])): ?>
                                        <img src="<?= htmlspecialchars($etudiant['photo']) ?>" alt="Photo étudiant" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background-color: #e9ecef; color: #6c757d;">
                                            <i class="bi bi-person" style="font-size: 2.5rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Informations de l'étudiant -->
                                <div style="flex: 1; padding: 5px;">
                                    <div style="display: flex; margin-bottom: 5px; font-size: 10px;">
                                        <div style="color: <?= $primaryColor ?>; font-weight: bold; width: 70px;">MATRICULE</div>
                                        <div style="color: <?= $primaryColor ?>; font-weight: bold; margin-left: 5px;">: <?= htmlspecialchars($etudiant['matricule']) ?></div>
                                    </div>
                                    <div style="display: flex; margin-bottom: 5px; font-size: 10px;">
                                        <div style="color: <?= $primaryColor ?>; font-weight: bold; width: 70px;">NOMS</div>
                                        <div style="color: <?= $primaryColor ?>; font-weight: bold; margin-left: 5px;">: <?= htmlspecialchars($etudiant['noms']) ?></div>
                                    </div>
                                    <div style="display: flex; margin-bottom: 5px; font-size: 10px;">
                                        <div style="color: <?= $primaryColor ?>; font-weight: bold; width: 70px;">PROMOTION</div>
                                        <div style="color: <?= $primaryColor ?>; font-weight: bold; margin-left: 5px;">: <?= htmlspecialchars($etudiant['designationPromotion']) ?></div>
                                    </div>
                                    
                                    <!-- QR Code -->
                                    <div id="qrcode" style="width: 60px; height: 60px; margin-left: auto; margin-top: 3px;"></div>
                                </div>
                            </div>
                            
                            <!-- Pied de page avec effet polygone -->
                            <div style="background-color: <?= $primaryColor ?>; padding: 5px 8px; color: white; clip-path: polygon(0 0, 100% 20%, 100% 100%, 0 100%); position: relative;">
                                <div style="font-size: 12px; font-weight: bold; margin-top: 5px;">
                                    ID NO : <?= substr(htmlspecialchars($cardId), -8) ?>
                                </div>
                                <div style="font-size: 7px; margin-top: 5px;">
                                    <?= htmlspecialchars($configUniversite['adresse'] ?? '') ?>
                                </div>
                                
                                <!-- Bandes diagonales décoratives -->
                                <div style="position: absolute; right: 0; top: 0; bottom: 0; width: 60px; display: flex; flex-direction: column; justify-content: center;">
                                    <div style="height: 4px; background-color: white; margin: 2px 0; transform: skewY(-30deg);"></div>
                                    <div style="height: 4px; background-color: white; margin: 2px 0; transform: skewY(-30deg);"></div>
                                    <div style="height: 4px; background-color: white; margin: 2px 0; transform: skewY(-30deg);"></div>
                                </div>
                                
                                <!-- Filigrane de sécurité -->
                                <div id="hologram" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; opacity: 0.04; z-index: 0; mix-blend-mode: overlay;"></div>
                            </div>
                        </div>

                        
                        <!-- Informations de sécurité -->
                        <div class="alert alert-warning">
                            <p><strong>Informations de sécurité:</strong></p>
                            <ul>
                                <li>Cette carte électronique contient un QR code sécurisé unique avec signature cryptographique</li>
                                <li>Un hologramme numérique est intégré dans le design de la carte</li>
                                <li>La validité de cette carte peut être vérifiée via le système universitaire</li>
                                <li>Si vous perdez votre carte, veuillez le signaler immédiatement</li>
                                <li>Carte valide jusqu'au: <?= htmlspecialchars(date('d/m/Y', strtotime($dateExpiration))) ?></li>
                            </ul>
                        </div>
                        
                        <!-- Boutons d'action -->
                        <div class="mt-4 d-flex justify-content-between">
                            <a href="etudiants/etudiant.inscrit" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Retour à la liste
                            </a>
                            <button id="revokeBtn" class="btn btn-danger">
                                <i class="bi bi-x-circle"></i> Signaler comme perdue
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour signaler une carte perdue -->
<div class="modal fade" id="revokeCardModal" tabindex="-1" aria-labelledby="revokeCardModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="revokeCardModalLabel">Signaler une carte perdue ou volée</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="revokeForm">
                <input type="hidden" id="cardIdInput" value="<?= htmlspecialchars($cardId) ?>">
                    <div class="mb-3">
                        <label for="revocationReason" class="form-label">Raison</label>
                        <select class="form-select" id="revocationReason" required>
                            <option value="">Sélectionner une raison</option>
                            <option value="Perdue">Carte perdue</option>
                            <option value="Volée">Carte volée</option>
                            <option value="Endommagée">Carte endommagée</option>
                            <option value="Autre">Autre raison</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="revocationDetails" class="form-label">Détails</label>
                        <textarea class="form-control" id="revocationDetails" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="confirmRevokeBtn">Confirmer</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Générer le QR code
        const qrCodeContainer = document.getElementById('qrcode');
        const qrCodeData = <?= json_encode($qrCode) ?>;
        
        // Remplacer la partie de génération du QR code par celle-ci
        new QRCode(qrCodeContainer, {
            text: qrCodeData,
            width: 60,
            height: 60,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });

        
        // Générer le hologramme dynamique
        const hologramData = <?= json_encode($hologramData) ?>;
        generateHologram(hologramData);
        
        // Fonction d'impression
        document.getElementById('printBtn').addEventListener('click', function() {
            const printContent = document.getElementById('eCardContainer').outerHTML;
            const originalContent = document.body.innerHTML;
            
            document.body.innerHTML = `
                <div style="display: flex; justify-content: center; padding: 20px;">
                    ${printContent}
                </div>
                <style>
                    @media print {
                        body { margin: 0; padding: 0; }
                        #hologram { opacity: 0.1 !important; }
                    }
                </style>
            `;
            
            window.print();
            document.body.innerHTML = originalContent;
            location.reload();
        });
        
        // Le téléchargement PDF est géré par le lien serveur (controller/generate_ecard_pdf.php)
        
        // Gestion de la révocation de carte
        document.getElementById('revokeBtn').addEventListener('click', function() {
            const revokeModal = new bootstrap.Modal(document.getElementById('revokeCardModal'));
            revokeModal.show();
        });
        
        document.getElementById('confirmRevokeBtn').addEventListener('click', function() {
            const cardId = document.getElementById('cardIdInput').value;
            const reason = document.getElementById('revocationReason').value;
            const details = document.getElementById('revocationDetails').value;
            
            if (!reason || !details) {
                alert('Veuillez remplir tous les champs.');
                return;
            }
            
            // Appel AJAX pour révoquer la carte
            fetch('controller/revoke_card.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    cardId: cardId,
                    reason: reason,
                    details: details
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Carte révoquée',
                        text: 'La carte a été marquée comme perdue/volée avec succès.',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = 'etudiants/etudiant.inscrit';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message || 'Une erreur est survenue lors de la révocation de la carte.',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur de connexion est survenue.',
                    confirmButtonText: 'OK'
                });
            });
        });
        
        // Fonction pour générer le hologramme dynamique
        function generateHologram(data) {
            const hologramElement = document.getElementById('hologram');
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            canvas.width = hologramElement.offsetWidth;
            canvas.height = hologramElement.offsetHeight;
            
            // Dessiner le motif du hologramme
            ctx.fillStyle = 'rgba(255, 255, 255, 0.8)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            const pattern = data.pattern;
            pattern.forEach(item => {
                const x = item.x * canvas.width;
                const y = item.y * canvas.height;
                const radius = item.r * Math.min(canvas.width, canvas.height);
                
                ctx.beginPath();
                ctx.arc(x, y, radius, 0, Math.PI * 2);
                ctx.fillStyle = item.c;
                ctx.fill();
                
                // Lignes de connexion entre les cercles
                if (pattern.indexOf(item) > 0) {
                    const prevItem = pattern[pattern.indexOf(item) - 1];
                    const prevX = prevItem.x * canvas.width;
                    const prevY = prevItem.y * canvas.height;
                    
                    ctx.beginPath();
                    ctx.moveTo(prevX, prevY);
                    ctx.lineTo(x, y);
                    ctx.strokeStyle = `rgba(100, 100, 255, 0.3)`;
                    ctx.lineWidth = 0.5;
                    ctx.stroke();
                }
            });
            
            // Ajouter texte de sécurité en diagonale
            ctx.save();
            ctx.font = '14px Arial';
            ctx.fillStyle = 'rgba(50, 50, 255, 0.5)';
            ctx.translate(canvas.width / 2, canvas.height / 2);
            ctx.rotate(-Math.PI / 4);
            
            // Répéter le texte en diagonale
            for (let i = -5; i <= 5; i++) {
                ctx.fillText('SÉCURISÉ • AUTHENTIQUE', i * 100, i * 30);
            }
            ctx.restore();
            
            // Ajouter le code de vérification en filigrane
            ctx.font = '8px Arial';
            ctx.fillStyle = 'rgba(0, 0, 0, 0.3)';
            ctx.fillText(`VCODE: ${data.verification_code}`, 10, canvas.height - 10);
            
            // Appliquer le canvas au hologramme
            hologramElement.style.backgroundImage = `url(${canvas.toDataURL('image/png')})`;
        }
    });

</script>

<?php include "./views/include/footer.php"; ?>
