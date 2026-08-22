<?php
include "./views/include/header.php";
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>VÉRIFICATION DE CARTE ÉTUDIANT</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item"><a href="etudiants/etudiant.inscrit">Étudiants</a></li>
                <li class="breadcrumb-item active">Vérification de Carte</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-6 mx-auto">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Scanner une carte étudiant</h5>
                        
                        <div class="alert alert-info mb-4">
                            <p>Utilisez cette page pour vérifier l'authenticité d'une carte étudiant. Vous pouvez:</p>
                            <ul>
                                <li>Scanner le QR code avec la caméra</li>
                                <li>Entrer manuellement l'ID de la carte</li>
                            </ul>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Option 1: Scanner QR Code</h6>
                                        <div class="d-flex justify-content-center">
                                            <button id="startScanBtn" class="btn btn-primary">
                                                <i class="bi bi-camera"></i> Scanner
                                            </button>
                                        </div>
                                        <div id="reader" class="mt-3" style="width: 100%; display: none;"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">Option 2: Vérification Manuelle</h6>
                                        <form id="manualVerificationForm">
                                            <div class="mb-3">
                                                <label for="cardId" class="form-label">ID de la carte</label>
                                                <input type="text" class="form-control" id="cardId" placeholder="ex: CARD-123456789" required>
                                            </div>
                                            <div class="d-flex justify-content-center">
                                                <button type="submit" class="btn btn-success">
                                                    <i class="bi bi-search"></i> Vérifier
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Résultat de la vérification -->
                        <div id="verificationResult" class="mt-4" style="display: none;">
                            <div class="card border-0">
                                <div id="resultHeader" class="card-header">
                                    Résultat de la vérification
                                </div>
                                <div class="card-body">
                                    <div id="resultContent"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Historique des vérifications récentes -->
                        <div class="mt-4">
                            <h6>Vérifications récentes</h6>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date/Heure</th>
                                        <th>Carte ID</th>
                                        <th>Résultat</th>
                                    </tr>
                                </thead>
                                <tbody id="verificationHistory">
                                    <tr>
                                        <td colspan="3" class="text-center">Aucune vérification récente</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Script pour le scanner QR Code -->
<script src="https://unpkg.com/html5-qrcode/minified/html5-qrcode.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Variables
        let html5QrCode;
        let verificationHistory = [];
        
        // Fonction pour initialiser le scanner QR
        document.getElementById('startScanBtn').addEventListener('click', function() {
            const reader = document.getElementById('reader');
            reader.style.display = 'block';
            
            if (html5QrCode) {
                html5QrCode.stop();
            }
            
            html5QrCode = new Html5Qrcode("reader");
            const config = { fps: 10, qrbox: { width: 250, height: 250 } };
            
            html5QrCode.start(
                { facingMode: "environment" },
                config,
                onScanSuccess,
                onScanFailure
            ).catch(err => {
                console.error("Erreur de démarrage du scanner:", err);
                alert("Impossible d'accéder à la caméra. Vérifiez vos permissions.");
            });
        });
        
        // Fonction appelée lors d'un scan réussi
        function onScanSuccess(decodedText) {
            // Arrêter le scanner une fois qu'un code a été détecté
            if (html5QrCode) {
                html5QrCode.stop();
                document.getElementById('reader').style.display = 'none';
            }
            
            try {
                // Le texte décodé devrait être du JSON
                const cardData = JSON.parse(decodedText);
                verifyCardData(cardData);
            } catch (e) {
                showVerificationResult(false, 'Format de QR code non valide. Ce n\'est pas une carte étudiant authentique.');
            }
        }
        
        function onScanFailure(error) {
            // Gérer les erreurs silencieusement (pas besoin d'afficher chaque erreur)
            console.warn(`Scan error: ${error}`);
        }
        
        // Vérification manuelle
        document.getElementById('manualVerificationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const cardId = document.getElementById('cardId').value.trim();
            
            if (!cardId) {
                alert('Veuillez entrer un ID de carte.');
                return;
            }
            
            // Vérifier l'ID de carte manuellement
            verifyCardManually(cardId);
        });
        
        // Fonction pour vérifier des données de carte automatiquement (via QR code)
        function verifyCardData(cardData) {
            // Afficher l'indicateur de chargement
            showLoadingResult();
            
            // Envoyer les données au serveur pour vérification
            fetch('controller/verify_card.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    type: 'qr_data',
                    data: cardData
                })
            })
            .then(response => response.json())
            .then(result => {
                processVerificationResult(result);
            })
            .catch(error => {
                console.error('Erreur:', error);
                showVerificationResult(false, 'Erreur de connexion lors de la vérification.');
            });
        }
        
        // Fonction pour vérifier une carte manuellement (via ID)
        function verifyCardManually(cardId) {
            // Afficher l'indicateur de chargement
            showLoadingResult();
            
            // Envoyer l'ID au serveur pour vérification
            fetch('controller/verify_card.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    type: 'card_id',
                    data: cardId
                })
            })
            .then(response => response.json())
            .then(result => {
                processVerificationResult(result);
            })
            .catch(error => {
                console.error('Erreur:', error);
                showVerificationResult(false, 'Erreur de connexion lors de la vérification.');
            });
        }
        
        // Traiter le résultat de la vérification
        function processVerificationResult(result) {
            const isValid = result.valid === true;
            
            // Ajouter à l'historique
            addToVerificationHistory({
                time: new Date(),
                cardId: result.etudiant?.matricule || result.cardId || 'N/A',
                result: isValid ? 'success' : 'failed',
                message: isValid ? 'Carte valide' : (result.message || 'Carte non valide')
            });
            
            // Afficher le résultat
            if (isValid) {
                const etudiant = result.etudiant;
                showVerificationResult(true, `
                    <div class="d-flex align-items-center mb-3">
                        <div class="fs-1 me-3 text-success">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <div>
                            <h5 class="mb-0">Carte authentique et valide</h5>
                            <p class="text-muted mb-0">Vérification réussie</p>
                        </div>
                    </div>
                    <div class="alert alert-success">
                        <h6>Informations de l'étudiant:</h6>
                        <ul class="mb-0">
                            <li><strong>Matricule:</strong> ${etudiant.matricule}</li>
                            <li><strong>Nom:</strong> ${etudiant.nom}</li>
                            <li><strong>Promotion:</strong> ${etudiant.promotion}</li>
                            <li><strong>Année académique:</strong> ${etudiant.annee}</li>
                            <li><strong>Validité:</strong> jusqu'au ${new Date(etudiant.date_expiration).toLocaleDateString()}</li>
                        </ul>
                    </div>
                `);
            } else {
                showVerificationResult(false, `
                    <div class="d-flex align-items-center mb-3">
                        <div class="fs-1 me-3 text-danger">
                            <i class="bi bi-x-circle-fill"></i>
                        </div>
                        <div>
                            <h5 class="mb-0">Carte non valide</h5>
                            <p class="text-muted mb-0">La vérification a échoué</p>
                        </div>
                    </div>
                    <div class="alert alert-danger">
                        <p class="mb-0"><strong>Raison:</strong> ${result.message || 'Cette carte n\'est pas authentique ou a été révoquée.'}</p>
                    </div>
                    <p class="small text-muted mt-2">Si vous pensez qu'il s'agit d'une erreur, veuillez contacter le service informatique.</p>
                `);
            }
        }
        
        // Afficher un indicateur de chargement pendant la vérification
        function showLoadingResult() {
            const resultElement = document.getElementById('verificationResult');
            const resultHeader = document.getElementById('resultHeader');
            const resultContent = document.getElementById('resultContent');
            
            resultElement.style.display = 'block';
            resultHeader.className = 'card-header bg-secondary text-white';
            resultHeader.textContent = 'Vérification en cours...';
            
            resultContent.innerHTML = `
                <div class="text-center py-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-2">Vérification de la carte en cours...</p>
                </div>
            `;
        }
        
        // Afficher le résultat de la vérification
        function showVerificationResult(isValid, content) {
            const resultElement = document.getElementById('verificationResult');
            const resultHeader = document.getElementById('resultHeader');
            const resultContent = document.getElementById('resultContent');
            
            resultElement.style.display = 'block';
            
            if (isValid) {
                resultHeader.className = 'card-header bg-success text-white';
                resultHeader.textContent = 'Carte Valide';
            } else {
                resultHeader.className = 'card-header bg-danger text-white';
                resultHeader.textContent = 'Carte Non Valide';
            }
            
            resultContent.innerHTML = content;
        }
        
        // Ajouter une entrée à l'historique de vérification
        function addToVerificationHistory(entry) {
            // Ajouter au début de l'historique
            verificationHistory.unshift(entry);
            
            // Limiter l'historique à 10 éléments
            if (verificationHistory.length > 10) {
                verificationHistory.pop();
            }
            
            // Mettre à jour l'affichage
            updateVerificationHistory();
            
            // Sauvegarder dans le stockage local
            localStorage.setItem('cardVerificationHistory', JSON.stringify(verificationHistory));
        }
        
        // Mettre à jour l'affichage de l'historique
        function updateVerificationHistory() {
            const historyElement = document.getElementById('verificationHistory');
            
            if (verificationHistory.length === 0) {
                historyElement.innerHTML = `
                    <tr>
                        <td colspan="3" class="text-center">Aucune vérification récente</td>
                    </tr>
                `;
                return;
            }
            
            historyElement.innerHTML = verificationHistory.map(entry => `
                <tr>
                    <td>${new Date(entry.time).toLocaleString()}</td>
                    <td>${entry.cardId}</td>
                    <td>
                        ${entry.result === 'success' 
                            ? '<span class="badge bg-success">Valide</span>' 
                            : '<span class="badge bg-danger">Non valide</span>'}
                    </td>
                </tr>
            `).join('');
        }
        
        // Charger l'historique depuis le stockage local au chargement
        function loadVerificationHistory() {
            const savedHistory = localStorage.getItem('cardVerificationHistory');
            if (savedHistory) {
                try {
                    verificationHistory = JSON.parse(savedHistory);
                    updateVerificationHistory();
                } catch (e) {
                    console.error('Erreur lors du chargement de l\'historique:', e);
                    verificationHistory = [];
                }
            }
        }
        
        // Initialiser l'historique au chargement
        loadVerificationHistory();
    });
</script>

<?php include "./views/include/footer.php"; ?>
