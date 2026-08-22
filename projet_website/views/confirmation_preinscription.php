<?php
// Vérifier si une référence est fournie
if (!isset($_GET['ref']) || empty($_GET['ref'])) {
    header('Location: index');
    exit;
}

$db=Connexion::getInstance()->getPDO();

$reference = $_GET['ref'];

// Récupérer les informations de la pré-inscription
$stmt = $db->prepare("SELECT * FROM preinscription WHERE reference = ?");
$stmt->execute([$reference]);
$preinscription = $stmt->fetch(PDO::FETCH_ASSOC);

// Si la pré-inscription n'existe pas, rediriger vers la page d'accueil
if (!$preinscription) {
    header('Location: index.php');
    exit;
}

// Inclure l'en-tête
include "include/head.php";
?>

<section class="section">
    <div class="container">
        <div class="confirmation-container">
            <div class="confirmation-header">
                <div class="confirmation-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1>Pré-inscription enregistrée avec succès</h1>
            </div>
            
            <div class="confirmation-details">
                <div class="reference-box">
                    <h3>Référence de votre demande</h3>
                    <div class="reference-number"><?php echo htmlspecialchars($reference); ?></div>
                    <p class="reference-note">Veuillez conserver précieusement cette référence</p>
                </div>
                
                <div class="confirmation-message">
                    <p>Cher(e) <strong><?php echo htmlspecialchars($preinscription['prenom'] . ' ' . $preinscription['nom']); ?></strong>,</p>
                    
                    <p>Nous vous remercions pour votre demande de pré-inscription à l'Institut Supérieur des Techniques Médicales de Beni (ISTM-BENI).</p>
                    
                    <p>Votre demande a été enregistrée avec succès et sera examinée par notre service des admissions dans les plus brefs délais.</p>
                    
                    <p>Un email de confirmation a été envoyé à l'adresse <strong><?php echo htmlspecialchars($preinscription['email']); ?></strong> avec toutes les informations concernant votre pré-inscription.</p>
                </div>
                
                <div class="next-steps">
                    <h3>Prochaines étapes</h3>
                    <ol>
                        <li>
                            <div class="step-icon"><i class="fas fa-file-alt"></i></div>
                            <div class="step-content">
                                <h4>Examen de votre dossier</h4>
                                <p>Notre service des admissions examinera votre dossier dans un délai d'environ 2 semaines.</p>
                            </div>
                        </li>
                        <li>
                            <div class="step-icon"><i class="fas fa-envelope"></i></div>
                            <div class="step-content">
                                <h4>Notification du résultat</h4>
                                <p>Vous recevrez une notification par email concernant l'acceptation ou le rejet de votre demande.</p>
                            </div>
                        </li>
                        <li>
                            <div class="step-icon"><i class="fas fa-user-graduate"></i></div>
                            <div class="step-content">
                                <h4>Finalisation de l'inscription</h4>
                                <p>En cas d'acceptation, vous recevrez les instructions pour finaliser votre inscription.</p>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>
            
            <div class="confirmation-actions">
                <a href="accueil" class="btn btn-primary"><i class="fas fa-home"></i> Retour à l'accueil</a>
                <a href="javascript:window.print();" class="btn btn-outline-secondary"><i class="fas fa-print"></i> Imprimer cette page</a>
            </div>
            
            <div class="confirmation-contact">
                <h3>Besoin d'aide ?</h3>
                <p>Pour toute question concernant votre pré-inscription, veuillez contacter le service des admissions :</p>
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <span>scolarite@istmbeni.ac.cd</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <span>+243 123 456 789</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.confirmation-container {
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
    margin: 50px 0;
    padding: 40px;
}

.confirmation-header {
    text-align: center;
    margin-bottom: 40px;
}

.confirmation-icon {
    font-size: 80px;
    color: #28a745;
    margin-bottom: 20px;
}

.confirmation-header h1 {
    color: var(--primary-color);
    font-size: 32px;
}

.confirmation-details {
    margin-bottom: 40px;
}

.reference-box {
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 25px;
    text-align: center;
    margin-bottom: 30px;
}

.reference-box h3 {
    color: var(--text-color);
    margin-bottom: 15px;
    font-size: 18px;
}

.reference-number {
    font-size: 28px;
    font-weight: 700;
    color: var(--primary-color);
    padding: 10px;
    border: 2px dashed var(--primary-color);
    border-radius: 5px;
    display: inline-block;
    margin-bottom: 10px;
    letter-spacing: 2px;
}

.reference-note {
    color: #6c757d;
    font-style: italic;
    margin-bottom: 0;
}

.confirmation-message {
    margin-bottom: 30px;
    line-height: 1.6;
}

.next-steps {
    margin-bottom: 30px;
}

.next-steps h3 {
    color: var(--primary-color);
    margin-bottom: 20px;
    font-size: 22px;
}

.next-steps ol {
    list-style: none;
    padding: 0;
    counter-reset: step-counter;
}

.next-steps li {
    display: flex;
    margin-bottom: 20px;
    position: relative;
    counter-increment: step-counter;
}

.step-icon {
    width: 50px;
    height: 50px;
    background-color: var(--primary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    margin-right: 20px;
    position: relative;
}

.step-icon::before {
    content: counter(step-counter);
    position: absolute;
    top: -5px;
    right: -5px;
    width: 25px;
    height: 25px;
    background-color: var(--secondary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: bold;
}

.step-content {
    flex: 1;
}

.step-content h4 {
    color: var(--text-color);
    margin-bottom: 5px;
    font-size: 18px;
}

.step-content p {
    color: #6c757d;
    margin-bottom: 0;
}

.confirmation-actions {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-bottom: 40px;
}

.confirmation-contact {
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 25px;
    text-align: center;
}

.confirmation-contact h3 {
    color: var(--text-color);
    margin-bottom: 15px;
    font-size: 18px;
}

.contact-info {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin-top: 15px;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.contact-item i {
    color: var(--primary-color);
    font-size: 18px;
}

@media print {
    .confirmation-actions {
        display: none;
    }
    
    body {
        background-color: white;
    }
    
    .confirmation-container {
        box-shadow: none;
        margin: 0;
        padding: 20px;
    }
    
    .confirmation-icon {
        color: black !important;
    }
    
    .step-icon {
        border: 1px solid black;
        color: black !important;
        background-color: white !important;
    }
    
    .step-icon::before {
        border: 1px solid black;
        color: black !important;
        background-color: white !important;
    }
    
    .reference-number {
        border: 2px dashed black;
        color: black !important;
    }
}
</style>

<?php 
include "include/footer.php";
?>
