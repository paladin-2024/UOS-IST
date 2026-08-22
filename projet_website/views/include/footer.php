<!-- Pied de page -->
<footer class="footer">
        <div class="footer-top">
            <div class="container">
                <div class="footer-grid">
                    <div class="footer-info">
                        <a href="index.php" class="footer-logo">
                            <div class="logo-square"><i class="fas fa-hospital" style="color: white;"></i></div>
                            <span class="logo-text"><?php echo 'ISTM BENI'; ?></span>
                        </a>
                        <p class="footer-desc"><?php echo htmlspecialchars($settings['site_description'] ?? "L'Institut Supérieur des Techniques Médicales de BENI forme les futurs professionnels de la santé et experts médicaux depuis 1963."); ?></p>
                        <div class="social-links">
                            <?php if(!empty($settings['social_facebook'])): ?>
                                <a href="<?php echo htmlspecialchars($settings['social_facebook']); ?>" target="_blank" class="social-link"><i class="fab fa-facebook-f"></i></a>
                            <?php endif; ?>
                            
                            <?php if(!empty($settings['social_twitter'])): ?>
                                <a href="<?php echo htmlspecialchars($settings['social_twitter']); ?>" target="_blank" class="social-link"><i class="fab fa-twitter"></i></a>
                            <?php endif; ?>
                            
                            <?php if(!empty($settings['social_linkedin'])): ?>
                                <a href="<?php echo htmlspecialchars($settings['social_linkedin']); ?>" target="_blank" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                            <?php endif; ?>
                            
                            <?php if(!empty($settings['social_instagram'])): ?>
                                <a href="<?php echo htmlspecialchars($settings['social_instagram']); ?>" target="_blank" class="social-link"><i class="fab fa-instagram"></i></a>
                            <?php endif; ?>
                            
                            <?php if(!empty($settings['social_youtube'])): ?>
                                <a href="<?php echo htmlspecialchars($settings['social_youtube']); ?>" target="_blank" class="social-link"><i class="fab fa-youtube"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="footer-links">
                        <h3 class="footer-title">Liens rapides</h3>
                        <ul class="footer-menu">
                            <li><a href="accueil">Accueil</a></li>
                            <li><a href="formations">Formations</a></li>
                            <li><a target="_blank" href="https://istmbeni.info/portail/index">Bibliothèque médicale</a></li>
                            <li><a target="_blank" href="https://minesu.gouv.cd">ESU</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-links">
                        <h3 class="footer-title">Informations</h3>
                        <ul class="footer-menu">
                            <li><a href="actualites">Actualités</a></li>
                            <li><a href="evenements">Événements</a></li>
                            <li><a href="">Stages cliniques</a></li>
                            <li><a target="" href="https://istmbeni.info/portail/index">Bibliothèque médicale</a></li>
                            <li><a href="">Mentions légales</a></li>
                            <li><a href="">Politique de confidentialité</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-contact">
                        <h3 class="footer-title">Contact</h3>
                        <ul class="contact-info">
                            <li><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($settings['contact_address'] ?? 'Avenue de la Santé, Quartier Malepe, Beni, Nord-Kivu, RDC'); ?></li>
                            <li><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($settings['contact_phone'] ?? '+243 123 456 789'); ?></li>
                            <li><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($settings['contact_email'] ?? 'contact@istmbeni.ac.cd'); ?></li>
                        </ul>
                        <div class="newsletter">
                            <h4>Restez informé</h4>
                            <form class="newsletter-form" action="controller/newsletter_subscribe.php" method="post">
                                <input type="email" name="email" placeholder="Votre email" required>
                                <button type="submit"><i class="fas fa-paper-plane"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="container">
                <p class="copyright"><?php echo htmlspecialchars($settings['footer_text'] ?? '© ' . date('Y') . ' ISTM BENI - Institut Supérieur des Techniques Médicales de BENI. Tous droits réservés.'); ?></p>
                <div class="back-to-top" id="back-to-top"><i class="fas fa-arrow-up"></i></div>
            </div>
        </div>
    </footer>

    <!-- Chatbot flottant -->
    <div id="chatbot-container" class="chatbot-container">
        <div class="chatbot-header">
            <h3><i class="fas fa-robot"></i> Assistant ISTM BENI</h3>
            <button id="close-chatbot"><i class="fas fa-times"></i></button>
        </div>
        <div class="chatbot-messages">
            <div class="message bot-message">
                Bonjour ! Je suis l'assistant virtuel de l'ISTM BENI. Comment puis-je vous aider aujourd'hui ?
            </div>
        </div>
        <div class="chatbot-input-container">
            <input type="text" id="chatbot-input" placeholder="Tapez votre message...">
            <button id="send-message"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
    <button id="open-chatbot" class="open-chatbot-btn"><i class="fas fa-comment-dots"></i></button>
    <!-- Ajout des styles CSS additionnels -->
    <style>
        /* Styles généraux pour les nouvelles sections */
        .text-center {
            text-align: center;
        }
        
        .mt-40 {
            margin-top: 40px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 25px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            color: var(--white);
            border: none;
        }
        
        .btn-secondary:hover {
            background-color: var(--secondary-dark);
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .btn-outline {
            background-color: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }
        
        .btn-outline:hover {
            background-color: var(--primary-color);
            color: var(--white);
            transform: translateY(-3px);
        }
        
        .light-link {
            color: var(--white);
            opacity: 0.9;
        }
        
        .light-link:hover {
            opacity: 1;
            color: var(--white);
        }
        
        .rounded-image {
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Style pour la section 60 ans */
        .anniversary-section {
            background-color: var(--white);
            position: relative;
            overflow: hidden;
        }
        
        .anniversary-content {
            display: flex;
            gap: 50px;
            align-items: center;
        }
        
        .anniversary-info {
            flex: 1;
        }
        
        .anniversary-image {
            flex: 1;
            position: relative;
            height: 400px;
        }
        
        .anniversary-badge {
            display: inline-block;
            background-color: var(--secondary-color);
            color: var(--white);
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .anniversary-stats {
            display: flex;
            gap: 30px;
            margin: 30px 0;
        }
        
        .stat-item {
            display: flex;
            flex-direction: column;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: var(--primary-color);
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 16px;
            color: var(--text-light);
        }
        
        .anniversary-overlay {
            position: absolute;
            bottom: 20px;
            right: 20px;
            z-index: 2;
        }
        
        .anniversary-logo {
            width: 100px;
            height: 100px;
            background-color: var(--white);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .anniversary-logo-inner {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--white);
            font-size: 36px;
            font-weight: 700;
            font-family: 'Montserrat', sans-serif;
        }
        
        /* Style pour la section Formation */
        .training-section {
            background-color: var(--light-bg);
            position: relative;
        }
        
        .training-programs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin: 40px 0;
        }
        
        .training-features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-top: 60px;
        }
        
        .feature-item {
            background-color: var(--white);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }
        
        .feature-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            background-color: rgba(0, 51, 102, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            color: var(--primary-color);
            font-size: 24px;
            transition: var(--transition);
        }
        
        .feature-item:hover .feature-icon {
            background-color: var(--primary-color);
            color: var(--white);
            transform: scale(1.1);
        }
        
        .feature-content h3 {
            font-size: 18px;
            margin-bottom: 10px;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .feature-content p {
            color: var(--text-light);
            line-height: 1.6;
        }
        
        /* Style pour la section Découvrir */
        .discover-section {
            background-color: var(--white);
        }
        
        .discover-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 50px;
        }
        
        .discover-card {
            padding: 30px;
            border-radius: var(--border-radius);
            border: 1px solid #eee;
            transition: var(--transition);
            background-color: #fafafa;
        }
        
        .discover-card:hover {
            box-shadow: var(--box-shadow);
            transform: translateY(-5px);
            border-color: transparent;
            background-color: var(--white);
        }
        
        .discover-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-color) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            color: var(--white);
            font-size: 24px;
        }
        
        .discover-card h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .discover-card p {
            color: var(--text-light);
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .video-container {
            display: flex;
            gap: 40px;
            align-items: center;
            margin-top: 60px;
        }
        
        .video-wrapper {
            flex: 1.5;
            position: relative;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }
        
        .video-placeholder {
            width: 100%;
            display: block;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80px;
            height: 80px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.2);
            transition: var(--transition);
        }
        
        .play-button i {
            color: var(--primary-color);
            font-size: 30px;
            margin-left: 5px;
        }
        
        .video-wrapper:hover .play-button {
            background-color: var(--primary-color);
            transform: translate(-50%, -50%) scale(1.1);
        }
        
        .video-wrapper:hover .play-button i {
            color: var(--white);
        }
        
        .video-text {
            flex: 1;
        }
        
        .video-text h3 {
            font-size: 24px;
            margin-bottom: 15px;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .video-text p {
            color: var(--text-light);
            line-height: 1.8;
        }
        
        /* Style pour la section Partenaires */
        .partners-section {
            background-color: var(--light-bg);
        }
        
        .partners-logo-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin: 40px 0;
        }
        
        .partner-logo {
            background-color: var(--white);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .partner-logo img {
            max-width: 150px;
            max-height: 80px;
            opacity: 0.7;
            transition: var(--transition);
        }
        
        .partner-logo:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-3px);
        }
        
        .partner-logo:hover img {
            opacity: 1;
        }
        
        /* Style pour le pied de page */
        .footer {
            background-color: var(--primary-dark);
            color: var(--white);
        }
        
        .footer-top {
            padding: 60px 0 40px;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1.5fr;
            gap: 40px;
        }
        
        .footer-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            text-decoration: none;
        }
        
        .footer-logo .logo-text {
            color: var(--white);
        }
        
        .footer-desc {
            margin-bottom: 20px;
            line-height: 1.7;
            opacity: 0.8;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
        }
        
        .social-link {
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .social-link:hover {
            background-color: var(--secondary-color);
            transform: translateY(-3px);
        }
        
        .footer-title {
            font-size: 18px;
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 10px;
            font-weight: 600;
        }
        
        .footer-title::after {
            content: '';
            position: absolute;
            width: 40px;
            height: 3px;
            background-color: var(--secondary-color);
            bottom: 0;
            left: 0;
            border-radius: 1.5px;
        }
        
        .footer-menu {
            list-style: none;
        }
        
        .footer-menu li {
            margin-bottom: 12px;
        }
        
        .footer-menu a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
            display: inline-block;
        }
        
        .footer-menu a:hover {
            color: var(--white);
            transform: translateX(5px);
        }
        
        .contact-info {
            list-style: none;
            margin-bottom: 25px;
        }
        
        .contact-info li {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .contact-info li i {
            color: var(--secondary-color);
            margin-top: 5px;
        }
        
        .newsletter h4 {
            font-size: 16px;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .newsletter-form {
            display: flex;
            height: 45px;
        }
        
        .newsletter-form input {
            flex: 1;
            border: none;
            padding: 0 15px;
            outline: none;
            border-radius: 30px 0 0 30px;
        }
        
        .newsletter-form button {
            background-color: var(--secondary-color);
            color: var(--white);
            border: none;
            border-radius: 0 30px 30px 0;
            width: 45px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .newsletter-form button:hover {
            background-color: var(--secondary-dark);
        }
        
        .footer-bottom {
            padding: 20px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .footer-bottom .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .copyright {
            font-size: 14px;
            opacity: 0.7;
        }
        
        .back-to-top {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .back-to-top:hover {
            background-color: var(--secondary-color);
            transform: translateY(-3px);
        }
        
        /* Style pour le chatbot */
        .chatbot-container {
            position: fixed;
            bottom: 90px;
            right: 30px;
            width: 350px;
            height: 450px;
            background-color: var(--white);
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            overflow: hidden;
            transform: translateY(20px);
            opacity: 0;
            pointer-events: none;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        
        .chatbot-container.active {
            transform: translateY(0);
            opacity: 1;
            pointer-events: all;
        }
        
        .chatbot-header {
            padding: 15px 20px;
            background-color: var(--primary-color);
            color: var(--white);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chatbot-header h3 {
            font-size: 16px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        #close-chatbot {
            background: none;
            border: none;
            color: var(--white);
            cursor: pointer;
            font-size: 16px;
        }
        
        .chatbot-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .message {
            max-width: 80%;
            padding: 12px 15px;
            border-radius: 15px;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .bot-message {
            background-color: var(--light-bg);
            color: var(--text-color);
            border-radius: 15px 15px 15px 0;
            align-self: flex-start;
        }
        
        .user-message {
            background-color: var(--primary-color);
            color: var(--white);
            border-radius: 15px 15px 0 15px;
            align-self: flex-end;
        }
        
        .chatbot-input-container {
            padding: 15px;
            display: flex;
            border-top: 1px solid #eee;
        }
        
        #chatbot-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 30px;
            outline: none;
        }
        
        #send-message {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            border-radius: 50%;
            margin-left: 10px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        #send-message:hover {
            background-color: var(--primary-dark);
        }
        
        .open-chatbot-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            border-radius: 50%;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            transition: var(--transition);
        }
        
        .open-chatbot-btn:hover {
            background-color: var(--primary-dark);
            transform: scale(1.1);
        }
        
        /* Animations */
        .fade-in-element {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        
        .fade-in-element.fade-in {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Media Queries pour Responsive Design */
        @media (max-width: 1200px) {
            .footer-grid {
                grid-template-columns: 1fr 1fr;
                gap: 30px;
            }
            
            .footer-info, .footer-contact {
                grid-column: span 2;
            }
        }
        
        @media (max-width: 992px) {
            .container {
                width: 95%;
            }
            
            .anniversary-content {
                flex-direction: column;
            }
            
            .anniversary-image {
                width: 100%;
            }
            
            .training-programs, 
            .training-features {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .discover-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .video-container {
                flex-direction: column;
            }
            
            .video-wrapper {
                width: 100%;
            }
            
            /* Navigation adaptative */
            nav ul {
                gap: 15px;
            }
        }
        
        @media (max-width: 768px) {
            .hero-content {
                max-width: 100%;
            }
            
            .user-menu {
                position: static;
                transform: none;
                width: 100%;
                margin-top: 30px;
            }
            
            .training-programs, 
            .training-features,
            .discover-grid {
                grid-template-columns: 1fr;
            }
            
            .partners-logo-container {
                gap: 15px;
            }
            
            .partner-logo {
                width: calc(50% - 15px);
            }
            
            .footer-grid {
                grid-template-columns: 1fr;
            }
            
            .footer-info, 
            .footer-contact {
                grid-column: span 1;
            }
            
            .footer-bottom .container {
                flex-direction: column;
                gap: 15px;
            }
            
            .copyright {
                text-align: center;
            }
            
            /* Header responsive */
            .logo-nav {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            
            .search-box {
                width: 100%;
                margin-top: 15px;
            }
            
            .search-input {
                width: 100%;
            }
            
            /* Navigation mobile */
            .mobile-menu-toggle {
                display: block;
                position: absolute;
                top: 20px;
                right: 20px;
                font-size: 24px;
                color: var(--primary-color);
                background: none;
                border: none;
                cursor: pointer;
                z-index: 100;
            }
            
            nav {
                position: fixed;
                top: 0;
                right: -100%;
                width: 80%;
                height: 100vh;
                background-color: var(--white);
                z-index: 99;
                transition: right 0.3s ease;
                box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
                overflow-y: auto;
                padding: 80px 20px 20px;
            }
            
            nav.active {
                right: 0;
            }
            
            nav ul {
                flex-direction: column;
                gap: 5px;
            }
            
            nav li {
                width: 100%;
            }
            
            nav a {
                display: block;
                padding: 15px 0;
                border-bottom: 1px solid #eee;
            }
            
            .dropdown-menu {
                position: static;
                width: 100%;
                box-shadow: none;
                display: none;
                opacity: 1;
                transform: none;
                animation: none;
                padding: 10px;
                background-color: var(--light-bg);
            }
            
            nav li:hover .dropdown-menu {
                display: none;
            }
            
            nav li.active .dropdown-menu {
                display: block;
            }
            
            .mobile-dropdown-toggle {
                position: absolute;
                right: 0;
                top: 15px;
                color: var(--primary-color);
                background: none;
                border: none;
                font-size: 20px;
                cursor: pointer;
                display: block;
            }
            
            /* Overlay pour fermer le menu */
            .mobile-menu-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 98;
                display: none;
            }
            
            .mobile-menu-overlay.active {
                display: block;
            }
            
            /* Chatbot responsive */
            .chatbot-container {
                width: 90%;
                right: 5%;
                bottom: 80px;
            }
        }
        
        @media (max-width: 576px) {
            .hero h1 {
                font-size: 32px;
            }
            
            .hero p {
                font-size: 16px;
            }
            
            .hero-btns {
                flex-direction: column;
                width: 100%;
            }
            
            .hero-btn {
                width: 100%;
                text-align: center;
                justify-content: center;
            }
            
            .section-title {
                font-size: 28px;
            }
            
            .anniversary-stats {
                flex-direction: column;
                gap: 20px;
            }
            
            .card-date {
                font-size: 12px;
            }
            
            .card-title {
                font-size: 16px;
            }
            
            .partners-logo-container {
                justify-content: center;
            }
            
            .partner-logo {
                width: 100%;
            }
            
            .top-bar-content {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .top-bar {
                padding: 15px 0;
            }
            
            .dropdown-btn {
                padding: 6px 10px;
                font-size: 12px;
            }
        }
    </style>

    <!-- Scripts nécessaires pour le formulaire de pré-inscription -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
<!-- Script pour la gestion des fichiers dans le formulaire -->
<script>
// Script pour afficher le nom des fichiers sélectionnés dans les input file
$(document).ready(function() {
    $('.custom-file-input').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });
});
</script>



<script>
    // Animation de particules pour le preloader
    document.addEventListener('DOMContentLoaded', function() {
        const canvas = document.getElementById('preloader-canvas');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        
        // Redimensionner le canvas pour qu'il occupe tout l'écran
        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);
        
        // Configuration des particules
        const particlesArray = [];
        const numberOfParticles = 100;
        
        // Couleurs des particules basées sur la charte graphique ISTM-BENI
        const colors = [
            'rgba(0, 51, 102, 0.7)',    // primary-color
            'rgba(0, 85, 164, 0.7)',    // primary-light
            'rgba(0, 34, 68, 0.7)',     // primary-dark
            'rgba(255, 170, 0, 0.7)',   // secondary-color
            'rgba(255, 187, 51, 0.7)',  // secondary-light
            'rgba(255, 136, 0, 0.7)'    // secondary-dark
        ];
        
        // Classe Particule
        class Particle {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.size = Math.random() * 5 + 1;
                this.speedX = Math.random() * 1 - 0.5;
                this.speedY = Math.random() * 1 - 0.5;
                this.color = colors[Math.floor(Math.random() * colors.length)];
            }
            
            update() {
                this.x += this.speedX;
                this.y += this.speedY;
                
                // Rebondir sur les bords
                if (this.x > canvas.width || this.x < 0) {
                    this.speedX = -this.speedX;
                }
                if (this.y > canvas.height || this.y < 0) {
                    this.speedY = -this.speedY;
                }
            }
            
            draw() {
                ctx.fillStyle = this.color;
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
            }
        }
        
        // Initialiser les particules
        function init() {
            for (let i = 0; i < numberOfParticles; i++) {
                particlesArray.push(new Particle());
            }
        }
        
        init();
        
        // Connecter les particules proches
        function connect() {
            const maxDistance = 100;
            for (let a = 0; a < particlesArray.length; a++) {
                for (let b = a; b < particlesArray.length; b++) {
                    const dx = particlesArray[a].x - particlesArray[b].x;
                    const dy = particlesArray[a].y - particlesArray[b].y;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    if (distance < maxDistance) {
                        const opacity = 1 - (distance / maxDistance);
                        ctx.strokeStyle = `rgba(0, 51, 102, ${opacity * 0.5})`;
                        ctx.lineWidth = 1;
                        ctx.beginPath();
                        ctx.moveTo(particlesArray[a].x, particlesArray[a].y);
                        ctx.lineTo(particlesArray[b].x, particlesArray[b].y);
                        ctx.stroke();
                    }
                }
            }
        }
        
        // Animer les particules
        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            for (let i = 0; i < particlesArray.length; i++) {
                particlesArray[i].update();
                particlesArray[i].draw();
            }
            
            connect();
            
            // Continuer l'animation seulement si le preloader est visible
            if (!document.getElementById('preloader').classList.contains('loaded')) {
                requestAnimationFrame(animate);
            }
        }
        
        animate();
    });
    
    // Système de preloader avancé avec background dégradé
    document.addEventListener('DOMContentLoaded', function() {
        // Ajouter la classe loading au body
        document.body.classList.add('loading');
        
        // Fonction pour masquer le preloader avec animation
        function hidePreloader() {
            const preloader = document.getElementById('preloader');
            if (preloader) {
                preloader.classList.add('loaded');
                // Permettre le défilement après le chargement
                document.body.classList.remove('loading');
                
                // Supprimer complètement le preloader après l'animation
                setTimeout(() => {
                    preloader.remove();
                }, 800); // Correspond à la durée de transition
            }
        }
        
        // Simuler un chargement progressif avec pourcentage
        const progressBar = document.querySelector('.loading-progress');
        const percentageText = document.querySelector('.loading-percentage');
        const loadingText = document.querySelector('.loading-text');
        
        // Messages de chargement dynamiques
        const loadingMessages = [
            "Initialisation...",
            "Chargement des ressources...",
            "Préparation des données...",
            "Configuration de l'interface...",
            "Optimisation...",
            "Finalisation...",
            "Prêt !"
        ];
        
        let width = 0;
        let messageIndex = 0;
        
        // Fonction pour mettre à jour le pourcentage et l'animation
        function updateProgress(newWidth) {
            if (progressBar) {
                progressBar.style.width = newWidth + '%';
            }
            
            if (percentageText) {
                percentageText.textContent = Math.round(newWidth) + '%';
            }
            
            // Changer le message en fonction du pourcentage
            if (loadingText) {
                const newMessageIndex = Math.floor(newWidth / (100 / (loadingMessages.length - 1)));
                if (newMessageIndex > messageIndex) {
                    messageIndex = newMessageIndex;
                    // Animation de changement de texte
                    loadingText.style.opacity = 0;
                    setTimeout(() => {
                        loadingText.textContent = loadingMessages[messageIndex];
                        loadingText.style.opacity = 1;
                    }, 200);
                }
            }
        }
        
        // Simuler le chargement des ressources
        const interval = setInterval(function() {
            // Augmentation plus naturelle
            const increment = Math.max(1, Math.floor(Math.random() * 5));
            width += increment;
            
            if (width >= 100) {
                width = 100;
                clearInterval(interval);
                updateProgress(width);
                
                // Attendre un peu avant de masquer le preloader
                setTimeout(hidePreloader, 800);
            } else {
                updateProgress(width);
            }
        }, 100);
        
        // Masquer le preloader si le chargement prend trop de temps
        setTimeout(() => {
            if (width < 100) {
                clearInterval(interval);
                width = 100;
                updateProgress(width);
                setTimeout(hidePreloader, 500);
            }
        }, 8000);
        
        // Masquer le preloader quand la page est complètement chargée
        window.addEventListener('load', function() {
            // Accélérer la progression jusqu'à 100%
            clearInterval(interval);
            
            const finishLoading = setInterval(() => {
                width += 5;
                if (width >= 100) {
                    width = 100;
                    clearInterval(finishLoading);
                    updateProgress(width);
                    setTimeout(hidePreloader, 500);
                } else {
                    updateProgress(width);
                }
            }, 30);
        });
    });
</script>




    
    <!-- JavaScript pour la fonctionnalité -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            // Afficher/masquer le chatbot
            const openChatbotBtn = document.getElementById('open-chatbot');
            const closeChatbotBtn = document.getElementById('close-chatbot');
            const chatbotContainer = document.getElementById('chatbot-container');

            // Défilement vers le haut
            const backToTop = document.getElementById('back-to-top');
            backToTop.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            
        
            // Animation au défilement
            function animateOnScroll() {
                const fadeElements = document.querySelectorAll('.fade-in-element');
                fadeElements.forEach(element => {
                    const elementPosition = element.getBoundingClientRect().top;
                    const screenPosition = window.innerHeight * 0.8;
                    
                    if (elementPosition < screenPosition) {
                        element.classList.add('fade-in');
                    }
                });
            }
            
            // Exécuter l'animation au chargement
            animateOnScroll();
            
            // Exécuter l'animation au défilement
            window.addEventListener('scroll', animateOnScroll);
            
            // Chatbot simulation basique
            const chatbotInput = document.getElementById('chatbot-input');
            const sendMessage = document.getElementById('send-message');
            const chatMessages = document.querySelector('.chatbot-messages');
            
            function addMessage(message, isUser = false) {
                const messageDiv = document.createElement('div');
                messageDiv.classList.add('message');
                messageDiv.classList.add(isUser ? 'user-message' : 'bot-message');
                messageDiv.textContent = message;
                chatMessages.appendChild(messageDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
            
            // Simples réponses automatiques
            function getBotResponse(message) {
                const lowerMsg = message.toLowerCase();
                if (lowerMsg.includes('bonjour') || lowerMsg.includes('salut')) {
                    return "Bonjour ! Comment puis-je vous aider aujourd'hui ?";
                } else if (lowerMsg.includes('admission') || lowerMsg.includes('inscription')) {
                    return "Pour les admissions, veuillez visiter notre section dédiée ou contactez le bureau des admissions au +243 123 456 789.";
                } else if (lowerMsg.includes('formation') || lowerMsg.includes('cours')) {
                    return "Nous proposons des formations en Génie civil, Architecture, Travaux publics et Topographie. Quelle formation vous intéresse ?";
                } else if (lowerMsg.includes('contact') || lowerMsg.includes('adresse')) {
                    return "Vous pouvez nous contacter au +243 123 456 789 ou par email à scolarite@istmbeni.ac.cd. Notre adresse principale est Beni, Nord-Kivu, RDC.";
                } else {
                    return "Merci pour votre message. Un conseiller vous répondra prochainement. Pour une réponse plus rapide, n'hésitez pas à nous appeler au +243 123 456 789.";
                }
            }
            
            function handleSendMessage() {
                const message = chatbotInput.value.trim();
                if (message !== '') {
                    addMessage(message, true);
                    chatbotInput.value = '';
                    
                    // Simuler un délai de réponse
                    setTimeout(() => {
                        const response = getBotResponse(message);
                        addMessage(response);
                    }, 500);
                }
            }
            
            sendMessage.addEventListener('click', handleSendMessage);
            chatbotInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    handleSendMessage();
                }
            });
            
            // Menu fixe au défilement
            const header = document.querySelector('.logo-nav').parentElement;
            const topBar = document.querySelector('.top-bar');
            let headerHeight = header.offsetHeight;
            let topBarHeight = topBar.offsetHeight;
            
            // Remplacez la fonction handleStickyNav par ceci:
function handleStickyNav() {
    const scrollY = window.scrollY;
    
    if (scrollY > topBarHeight) {
        if (!header.classList.contains('sticky-header')) {
            header.classList.add('sticky-header');
            document.body.style.paddingTop = headerHeight + 'px';
        }
    } else {
        header.classList.remove('sticky-header');
        document.body.style.paddingTop = '0';
    }
    
    // S'assurer que le bouton toggle reste visible
    if (window.innerWidth <= 768) {
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        if (mobileToggle) {
            mobileToggle.style.top = (scrollY > topBarHeight) ? '20px' : (topBarHeight + 20) + 'px';
        }
    }
}

// Assurez-vous que cette fonction est exécutée au chargement et au redimensionnement
window.addEventListener('scroll', handleStickyNav);
window.addEventListener('resize', function() {
    // Recalculer les hauteurs
    headerHeight = header.offsetHeight;
    topBarHeight = topBar.offsetHeight;
    handleStickyNav();
});

// Exécuter immédiatement pour initialiser correctement
handleStickyNav();

            
           
            
            // Initialiser la fonctionnalité de slider pour les actualités
            function initializeNewsSlider() {
                const cardWidth = newsCards[0].offsetWidth + 25; // width + gap
                newsSlider.style.transition = 'none';
                newsSlider.style.width = `${cardWidth * newsCards.length}px`;
                newsCards.forEach(card => {
                    card.style.minWidth = `${(newsSlider.offsetWidth / newsCardsToShow) - 25}px`;
                });
                updateNewsSlider();
                newsSlider.style.transition = 'transform 0.5s ease';
            }
            
            // Initialiser au chargement et au redimensionnement
            initializeNewsSlider();
            window.addEventListener('resize', function() {
                // Réinitialiser le slider sur les différentes tailles d'écran
                newsCurrentIndex = 0;
                initializeNewsSlider();
                
                // Mettre à jour le nombre de cartes à afficher
                const newCardsToShow = window.innerWidth < 768 ? 1 : window.innerWidth < 992 ? 2 : 3;
                if (newCardsToShow !== newsCardsToShow) {
                    newsCardsToShow = newCardsToShow;
                    updateNewsSlider();
                }
            });
            
            // Initialiser le lecteur vidéo dans un modal
            const playButton = document.querySelector('.play-button');
            if (playButton) {
                playButton.addEventListener('click', function() {
                    // Créer un modal pour la vidéo
                    const videoModal = document.createElement('div');
                    videoModal.classList.add('video-modal');
                    videoModal.innerHTML = `
                        <div class="video-modal-content">
                            <button class="close-video-modal">&times;</button>
                            <div class="video-modal-container">
                                <iframe width="100%" height="100%" src="https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(videoModal);
                    
                    // Ajouter les styles pour le modal
                    const modalStyle = document.createElement('style');
                    modalStyle.textContent = `
                        .video-modal {
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background-color: rgba(0, 0, 0, 0.8);
                            z-index: 1000;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            animation: fadeIn 0.3s ease;
                        }
                        
                        .video-modal-content {
                            position: relative;
                            width: 80%;
                            max-width: 900px;
                        }
                        
                        .video-modal-container {
                            position: relative;
                            padding-bottom: 56.25%;
                            height: 0;
                            overflow: hidden;
                        }
                        
                        .video-modal-container iframe {
                            position: absolute;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            border-radius: 8px;
                        }
                        
                        .close-video-modal {
                            position: absolute;
                            top: -40px;
                            right: -10px;
                            font-size: 30px;
                            color: white;
                            background: none;
                            border: none;
                            cursor: pointer;
                        }
                        
                        @keyframes fadeIn {
                            from {
                                opacity: 0;
                            }
                            to {
                                opacity: 1;
                            }
                        }
                    `;
                    document.head.appendChild(modalStyle);
                    
                    // Fermer le modal lors du clic sur le bouton de fermeture
                    const closeButton = document.querySelector('.close-video-modal');
                    closeButton.addEventListener('click', function() {
                        document.body.removeChild(videoModal);
                        document.head.removeChild(modalStyle);
                    });
                    
                    // Fermer le modal lors du clic en dehors du contenu
                    videoModal.addEventListener('click', function(e) {
                        if (e.target === videoModal) {
                            document.body.removeChild(videoModal);
                            document.head.removeChild(modalStyle);
                        }
                    });
                });
            }
        });









        //BOUTONS DU TOP BAR
        document.addEventListener('DOMContentLoaded', function() {
    // Fonction pour formater les options avec des icônes
    function formatOption(option) {
        if (!option.id) {
            return option.text;
        }
        
        var $option = $(option.element);
        var icon = $option.data('icon');
        
        if (!icon) {
            return option.text;
        }
        
        return $('<span><i class="' + icon + '"></i> ' + option.text + '</span>');
    }
    
    // Initialiser Select2 sur tous les selects de la top bar
    $('.top-bar-select').select2({
        minimumResultsForSearch: Infinity, // Désactiver la recherche
        templateResult: formatOption,
        templateSelection: formatOption,
        dropdownCssClass: 'top-bar-dropdown',
        width: 'auto'
    });
    
    // Gestion des redirection quand un utilisateur sélectionne une option
    $('#user-type').on('change', function() {
        if (this.value) {
            // Rediriger vers la page correspondante
            console.log('Redirection vers: ' + this.value);
            // window.location.href = this.value + '.php';
        }
    });
    
    $('#my-inbtp').on('change', function() {
        if (this.value) {
            console.log('Redirection vers: ' + this.value);
            // window.location.href = this.value + '.php';
        }
    });
    
    $('#language').on('change', function() {
        if (this.value) {
            console.log('Changement de langue: ' + this.value);
            // window.location.href = '?lang=' + this.value;
        }
    });
});




    </script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Ajustement des hauteurs pour la navbar fixe
        const topBar = document.querySelector('.top-bar');
        const header = document.querySelector('.logo-nav');
        
        // Ajustement simple une seule fois
        header.style.top = topBar.offsetHeight + 'px';
        
        // Navigation mobile - Approche améliorée
        const navMenu = document.querySelector('nav');
        const navContainer = document.querySelector('.nav-container');
        
        // Créer une seule fois l'overlay pour le menu mobile
        const mobileOverlay = document.createElement('div');
        mobileOverlay.classList.add('mobile-menu-overlay');
        document.body.appendChild(mobileOverlay);
        
        // Créer un seul bouton toggle et l'ajouter au bon endroit
        const mobileNavToggle = document.createElement('button');
        mobileNavToggle.classList.add('mobile-menu-toggle');
        mobileNavToggle.innerHTML = '<i class="fas fa-bars"></i>';
        
        // Ajouter le toggle au container de navigation pour les écrans mobiles
        if (window.innerWidth <= 768) {
            navContainer.appendChild(mobileNavToggle);
        } else {
            // Pour les grands écrans, on ne l'ajoute pas du tout
            // car il sera caché par CSS de toute façon
        }
        
        // Fonction unique pour gérer le toggle du menu
        function toggleMobileMenu() {
            navMenu.classList.toggle('active');
            mobileOverlay.classList.toggle('active');
            
            if (navMenu.classList.contains('active')) {
                document.body.style.overflow = 'hidden'; // Empêcher le défilement
                mobileNavToggle.innerHTML = '<i class="fas fa-times"></i>';
            } else {
                document.body.style.overflow = '';
                mobileNavToggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
        }
        
        // Ajouter les event listeners
        mobileNavToggle.addEventListener('click', toggleMobileMenu);
        mobileOverlay.addEventListener('click', toggleMobileMenu);
        
        // Gestion des dropdowns en mobile
        const navItems = document.querySelectorAll('.nav-item');
        
        if (window.innerWidth <= 768) {
            navItems.forEach(item => {
                const link = item.querySelector('.nav-link');
                
                // Ajouter un indicateur visuel pour les éléments avec dropdown
                if (item.querySelector('.dropdown-menu')) {
                    const indicator = document.createElement('span');
                    indicator.innerHTML = '<i class="fas fa-chevron-down"></i>';
                    indicator.style.marginLeft = '5px';
                    indicator.style.fontSize = '12px';
                    link.appendChild(indicator);
                    
                    // Empêcher la navigation et ouvrir le dropdown à la place
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        item.classList.toggle('active');
                        
                        // Fermer les autres dropdowns
                        navItems.forEach(otherItem => {
                            if (otherItem !== item) {
                                otherItem.classList.remove('active');
                            }
                        });
                    });
                }
            });
        }
        
        // Ajuster le comportement lors du redimensionnement
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                // S'assurer que le toggle est dans le container si on redimensionne vers mobile
                if (!navContainer.contains(mobileNavToggle)) {
                    navContainer.appendChild(mobileNavToggle);
                }
            } else {
                // Réinitialiser l'état du menu pour les grands écrans
                navMenu.classList.remove('active');
                mobileOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
</script>

</body>
</html>