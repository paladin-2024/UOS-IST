<?php
include "./views/include/header.php";

$connexion = Connexion::getInstance()->getPDO();
$dependanceModel = new DependanceServiceFrais();

// Récupérer tous les services actifs
$services = $dependanceModel->getAllServices(true);

// Récupérer l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();
$annee_acad_id = isset($_GET['annee_acad_id']) ? intval($_GET['annee_acad_id']) : ($currentYear ? $currentYear['idannee_acad'] : 0);

// Récupérer les années académiques
$stmt = $connexion->prepare("SELECT idannee_acad, designation FROM annee_acad ORDER BY designation DESC");
$stmt->execute();
$annees_academiques = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Vérification d'Accès aux Services/Documents</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Vérification Accès Services</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Vérifier l'accès d'un étudiant</h5>

                <form id="verificationForm" class="row g-3">
                    <div class="col-md-4">
                        <label for="matricule" class="form-label">Matricule de l'étudiant <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="matricule" name="matricule" placeholder="Entrez le matricule" required>
                    </div>

                    <div class="col-md-4">
                        <label for="service_id" class="form-label">Service/Document <span class="text-danger">*</span></label>
                        <select class="form-select select2" id="service_id" name="service_id" required>
                            <option value="">Sélectionnez un service</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= $service['id'] ?>">
                                    [<?= ucfirst($service['type']) ?>] <?= htmlspecialchars($service['designation']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Vérifier l'accès
                        </button>
                    </div>
                </form>

                <!-- Résultat de la vérification -->
                <div id="resultatContainer" class="mt-4" style="display: none;">
                    <div id="resultat"></div>
                </div>
            </div>
        </div>

        <!-- Historique des vérifications (optionnel) -->
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Dernières vérifications</h5>
                <div id="historiqueContainer">
                    <p class="text-muted">Les vérifications apparaîtront ici...</p>
                </div>
            </div>
        </div>
    </section>
</main>

<style>
    .resultat-box {
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .resultat-acces {
        background-color: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }
    
    .resultat-refuse {
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }
    
    .frais-item {
        padding: 10px;
        margin: 5px 0;
        border-radius: 4px;
        background-color: #f8f9fa;
    }
    
    .frais-paye {
        border-left: 4px solid #28a745;
    }
    
    .frais-manquant {
        border-left: 4px solid #dc3545;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({
            width: '100%'
        });
    }

    const form = document.getElementById('verificationForm');
    const matriculeInput = document.getElementById('matricule');
    const resultatContainer = document.getElementById('resultatContainer');
    const resultatDiv = document.getElementById('resultat');

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const matricule = matriculeInput.value.trim();
        const serviceId = document.getElementById('service_id').value;

        if (!matricule || !serviceId) {
            alert('Veuillez remplir tous les champs');
            return;
        }

        // Récupérer les infos de l'étudiant
        fetch(`controller/get_etudiant_info.php?matricule=${encodeURIComponent(matricule)}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    resultatDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle"></i> ${data.error}
                        </div>
                    `;
                    resultatContainer.style.display = 'block';
                    return;
                }

                // Vérifier l'accès
                fetch(`controller/ajax/verifier_acces_service.php?student_id=${data.idetudiant}&service_id=${serviceId}`)
                    .then(response => response.json())
                    .then(acces => {
                        afficherResultat(data, acces);
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        resultatDiv.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-circle"></i> Erreur lors de la vérification
                            </div>
                        `;
                        resultatContainer.style.display = 'block';
                    });
            })
            .catch(error => {
                console.error('Erreur:', error);
                resultatDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle"></i> Erreur lors de la récupération des informations
                    </div>
                `;
                resultatContainer.style.display = 'block';
            });
    });

    function afficherResultat(etudiant, acces) {
        let html = '';

        // En-tête avec infos étudiant
        html += `
            <div class="alert alert-info mb-3">
                <h6 class="mb-2"><strong>${etudiant.nom || 'Non spécifié'}</strong></h6>
                <p class="mb-1">Matricule: ${etudiant.matricule}</p>
                <p class="mb-0">Promotion: ${etudiant.promotion || 'Non spécifiée'}</p>
            </div>
        `;

        // Résultat d'accès
        const resultatClass = acces.acces ? 'resultat-acces' : 'resultat-refuse';
        const icon = acces.acces ? '<i class="bi bi-check-circle-fill"></i>' : '<i class="bi bi-x-circle-fill"></i>';
        const texte = acces.acces ? 'Accès Autorisé' : 'Accès Refusé';

        html += `
            <div class="resultat-box ${resultatClass}">
                <h6 class="mb-2">${icon} ${texte}</h6>
                <p class="mb-0">${acces.raison}</p>
            </div>
        `;

        // Frais payés
        if (acces.frais_payes && acces.frais_payes.length > 0) {
            html += `
                <div class="mt-3">
                    <h6 class="mb-2"><i class="bi bi-check-circle text-success"></i> Frais Payés</h6>
                    <div>
                        ${acces.frais_payes.map(frais => `
                            <div class="frais-item frais-paye">
                                <strong>${frais.designation}</strong>
                                <br><small>${parseFloat(frais.montant).toLocaleString('fr-FR', {minimumFractionDigits: 2})} ${frais.devise}</small>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        // Frais manquants
        if (acces.frais_manquants && acces.frais_manquants.length > 0) {
            html += `
                <div class="mt-3">
                    <h6 class="mb-2"><i class="bi bi-exclamation-circle text-danger"></i> Frais Manquants</h6>
                    <div>
                        ${acces.frais_manquants.map(frais => `
                            <div class="frais-item frais-manquant">
                                <strong>${frais.designation}</strong>
                                <br><small>${parseFloat(frais.montant).toLocaleString('fr-FR', {minimumFractionDigits: 2})} ${frais.devise}</small>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        resultatDiv.innerHTML = html;
        resultatContainer.style.display = 'block';
    }
});
</script>

<?php include "./views/include/footer.php"; ?>
