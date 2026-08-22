<?php
require_once "head_student.php";

// Set page title for mobile header
$pageTitle = 'Ma Carte Provisoire';
$currentPage = 'student_card';

// Initialiser l'objet Universite pour récupérer les informations
$universite = new Universite();
$configUniversite = $universite->getConfigurationUniversite();

// Récupérer les informations de l'étudiant
$etudiant = new Etudiant();
$studentInfo = $etudiant->getEtudiantById($studentId);

// Debug: log des informations
error_log("DEBUG student_card.php - StudentInfo photo: " . ($studentInfo['photo'] ?? 'NULL'));
error_log("DEBUG student_card.php - Config logo: " . ($configUniversite['logo'] ?? 'NULL'));

// Récupérer l'année académique
$anneeActuelle = date('Y');
$anneeProchaine = $anneeActuelle + 1;
$periodeAnnee = $anneeActuelle . ' - ' . $anneeProchaine;

// Récupérer le chemin du logo - utiliser le même pattern que sidebar.php
$logoPath = '';
if (isset($configUniversite['logo']) && !empty($configUniversite['logo'])) {
    $logoPath = '../' . $configUniversite['logo'];
}

// Génération du QR code (données de l'étudiant)
$qrCodeData = json_encode([
    'idetudiant' => $studentInfo['idetudiant'],
    'matricule' => $studentInfo['matricule'],
    'noms' => $studentInfo['noms'],
    'promotion' => $studentInfo['promotion'] ?? '',
    'timestamp' => time()
]);

// Couleurs par défaut de la carte
$primaryColor = '#4361ee';
$secondaryColor = '#3a0ca3';
$textColor = '#ffffff';
$backgroundColor = '#f8f9fa';

// Générer un hologramme de sécurité
$hologramData = [
    'pattern' => [
        ['x' => 0.3, 'y' => 0.3, 'r' => 0.08, 'c' => 'rgba(67, 97, 238, 0.5)'],
        ['x' => 0.7, 'y' => 0.3, 'r' => 0.06, 'c' => 'rgba(58, 12, 163, 0.5)'],
        ['x' => 0.5, 'y' => 0.7, 'r' => 0.07, 'c' => 'rgba(67, 97, 238, 0.4)'],
        ['x' => 0.2, 'y' => 0.6, 'r' => 0.05, 'c' => 'rgba(58, 12, 163, 0.6)'],
        ['x' => 0.8, 'y' => 0.7, 'r' => 0.06, 'c' => 'rgba(67, 97, 238, 0.5)'],
    ],
    'verification_code' => strtoupper(substr(hash('sha256', $studentInfo['matricule']), 0, 8))
];
?>

<?php include "includes/mobile_header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<!-- Content Area -->
<div class="content-area">
    <div class="pagetitle mb-4">
        <h1><i class="fas fa-id-card"></i> Ma Carte Provisoire</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="student">Portail Étudiant</a></li>
                <li class="breadcrumb-item active">Ma Carte Provisoire</li>
            </ol>
        </nav>
    </div>

    <div class="row">
        <div class="col-lg-10 mx-auto">
            <!-- Information Card -->
            <div class="card mb-4">
                <div class="card-body pt-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title">Carte Électronique Étudiant Provisoire</h5>
                        <div>
                            <button class="btn btn-primary me-2" id="printBtn">
                                <i class="bi bi-printer"></i> Imprimer
                            </button>
                            <button class="btn btn-success" id="downloadBtn">
                                <i class="bi bi-download"></i> Télécharger PDF
                            </button>
                        </div>
                    </div>
                    
                    <!-- Information sur la carte -->
                    <div class="alert alert-info mb-3">
                        <strong>Informations de la carte:</strong><br>
                        <strong>Étudiant:</strong> <?= htmlspecialchars($studentInfo['noms']) ?><br>
                        <strong>Matricule:</strong> <?= htmlspecialchars($studentInfo['matricule']) ?><br>
                        <strong>Promotion:</strong> <?= htmlspecialchars($studentInfo['promotion'] ?? 'Non assignée') ?><br>
                        <strong>Année académique:</strong> <?= $periodeAnnee ?>
                    </div>
                    
                    <!-- Debug Info -->
                    <div style="display: none;">
                        Logo path: <?= htmlspecialchars($logoPath) ?><br>
                        Photo: <?= htmlspecialchars($studentInfo['photo'] ?? 'NULL') ?><br>
                    </div>
                    
                    <!-- Remplacer la div du container de la carte par celle-ci -->
                    <div id="eCardContainer" class="mx-auto" style="width: 350px; background-color: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); margin-bottom: 20px;">
                        <!-- En-tête avec effet polygone -->
                        <div style="background-color: <?= $primaryColor ?>; position: relative; padding: 8px; color: white; clip-path: polygon(0 0, 100% 0, 100% 90%, 0 100%);">
                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: <?= $primaryColor ?>; clip-path: polygon(0 0, 100% 0, 95% 100%, 0 85%); z-index: -1;"></div>
                            
                            <!-- Logo -->
                            <div style="position: absolute; top: 8px; left: 8px; width: 40px; height: 40px; background-color: white; border-radius: 3px; overflow: hidden; display: flex; justify-content: center; align-items: center;">
                                <?php 
                                $logoExists = false;
                                if (!empty($logoPath)) {
                                    if (file_exists($logoPath)) {
                                        $logoExists = true;
                                    } elseif (isset($configUniversite['logo']) && !empty($configUniversite['logo']) && file_exists($configUniversite['logo'])) {
                                        $logoExists = true;
                                    }
                                }
                                ?>
                                <?php if ($logoExists): ?>
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
                                <?php if (!empty($studentInfo['photo'])): ?>
                                    <img src="<?= htmlspecialchars('../uploads/' . $studentInfo['photo']) ?>" alt="Photo étudiant" style="width: 100%; height: 100%; object-fit: cover;">
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
                                    <div style="color: <?= $primaryColor ?>; font-weight: bold; margin-left: 5px;">: <?= htmlspecialchars($studentInfo['matricule']) ?></div>
                                </div>
                                <div style="display: flex; margin-bottom: 5px; font-size: 10px;">
                                    <div style="color: <?= $primaryColor ?>; font-weight: bold; width: 70px;">NOMS</div>
                                    <div style="color: <?= $primaryColor ?>; font-weight: bold; margin-left: 5px;">: <?= htmlspecialchars($studentInfo['noms']) ?></div>
                                </div>
                                <div style="display: flex; margin-bottom: 5px; font-size: 10px;">
                                    <div style="color: <?= $primaryColor ?>; font-weight: bold; width: 70px;">PROMOTION</div>
                                    <div style="color: <?= $primaryColor ?>; font-weight: bold; margin-left: 5px;">: <?= htmlspecialchars($studentInfo['promotion'] ?? 'Non assignée') ?></div>
                                </div>
                                
                                <!-- QR Code -->
                                <div id="qrcode" style="width: 60px; height: 60px; margin-left: auto; margin-top: 3px;"></div>
                            </div>
                        </div>
                        
                        <!-- Pied de page avec effet polygone -->
                        <div style="background-color: <?= $primaryColor ?>; padding: 5px 8px; color: white; clip-path: polygon(0 0, 100% 20%, 100% 100%, 0 100%); position: relative;">
                            <div style="font-size: 12px; font-weight: bold; margin-top: 5px;">
                                ID NO : <?= substr(htmlspecialchars($studentInfo['matricule']), -8) ?>
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
                            <li>Cette carte est valide pour l'année académique: <?= $periodeAnnee ?></li>
                        </ul>
                    </div>
                    
                    <!-- Boutons d'action -->
                    <div class="mt-4 d-flex justify-content-between">
                        <a href="student" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Retour à l'accueil
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.3.2/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Générer le QR code
        const qrCodeContainer = document.getElementById('qrcode');
        const qrCodeData = <?= json_encode($qrCodeData) ?>;
        
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
        
        // Fonction de téléchargement PDF
        document.getElementById('downloadBtn').addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            
            // Capturer la carte en tant qu'image
            html2canvas(document.getElementById('eCardContainer')).then(canvas => {
                // Créer un PDF au format de la carte (ratio maintenu)
                const imgWidth = 210; // Largeur A4 en mm
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                
                const pdf = new jsPDF({
                    orientation: imgWidth > imgHeight ? 'landscape' : 'portrait',
                    unit: 'mm',
                    format: [imgWidth, imgHeight]
                });
                
                // Ajouter l'image au PDF
                const imgData = canvas.toDataURL('image/jpeg', 1.0);
                pdf.addImage(imgData, 'JPEG', 0, 0, imgWidth, imgHeight);
                
                // Ajouter des métadonnées au PDF
                pdf.setProperties({
                    title: `Carte Étudiant - ${<?= json_encode($studentInfo['noms']) ?>}`,
                    subject: 'Carte Électronique Étudiant Provisoire',
                    author: <?= json_encode($configUniversite['nom'] ?? 'Université') ?>,
                    keywords: 'carte, étudiant, sécurisé',
                    creator: 'Système de Gestion Universitaire'
                });
                
                // Télécharger le PDF
                pdf.save(`carte_etudiant_${<?= json_encode($studentInfo['matricule']) ?>}.pdf`);
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

<?php include __DIR__ . "/includes/main_scripts.php"; ?>

<?php include "includes/bottom_nav.php"; ?>
