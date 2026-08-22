class ChatbotManager {
    constructor() {
        this.isOpen = false;
        this.createChatbotUI();
        this.initEventListeners();
        this.responses = this.getUniversityResponses();
        this.userRole = this.detectUserRole();
        this.conversationHistory = [];
        this.suggestedQuestions = this.getSuggestedQuestions();
    }

    createChatbotUI() {
        // Créer l'élément du chatbot
        const chatbotHTML = `
            <div id="e-gestion-chatbot" class="chatbot-container">
                <div class="chatbot-toggle" id="chatbotToggle">
                    <i class="bi bi-chat-dots-fill"></i>
                </div>
                <div class="chatbot-box" id="chatbotBox">
                    <div class="chatbot-header">
                        <div class="chatbot-header-title">
                            <div class="chatbot-avatar">
                                <i class="bi bi-person-circle"></i>
                            </div>
                            <h5>Assistant E-Gestion</h5>
                        </div>
                        <button id="chatbotClose" class="btn-close"></button>
                    </div>
                    <div class="chatbot-messages" id="chatbotMessages">
                        <div class="message bot-message">
                            <div class="message-content">
                                Bonjour, je suis l'assistant E-Gestion. Comment puis-je vous aider aujourd'hui?
                            </div>
                        </div>
                    </div>
                    <div class="chatbot-suggestions" id="chatbotSuggestions">
                        <div class="suggestion-chips">
                            <button class="suggestion-chip">Frais universitaires</button>
                            <button class="suggestion-chip">Bibliothèque</button>
                            <button class="suggestion-chip">Départements</button>
                        </div>
                    </div>
                    <div class="chatbot-input">
                        <input type="text" id="chatbotInput" placeholder="Tapez votre question ici...">
                        <button id="chatbotSend">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    
        // Ajouter le chatbot au body
        document.body.insertAdjacentHTML('beforeend', chatbotHTML);
    }
    
    initEventListeners() {
        // Toggle chatbot visibility
        document.getElementById('chatbotToggle').addEventListener('click', () => this.toggleChatbot());
        document.getElementById('chatbotClose').addEventListener('click', () => this.toggleChatbot(false));
        
        // Send message on button click
        document.getElementById('chatbotSend').addEventListener('click', () => this.sendMessage());
        
        // Send message on Enter key
        document.getElementById('chatbotInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.sendMessage();
            }
        });

        // Suggestion chips
        document.querySelectorAll('.suggestion-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const question = chip.textContent;
                document.getElementById('chatbotInput').value = question;
                this.sendMessage();
            });
        });
    }

    detectUserRole() {
        // Tenter de détecter le rôle de l'utilisateur à partir du DOM
        // Cette fonction pourrait être améliorée selon la structure de l'application
        if (document.querySelector('.breadcrumb-item:first-child')) {
            const roleText = document.querySelector('.breadcrumb-item:first-child').textContent.trim();
            if (roleText.includes('ADMIN')) return 'admin';
            if (roleText.includes('ETUDIANT')) return 'etudiant';
            if (roleText.includes('ENSEIGNANT')) return 'enseignant';
            if (roleText.includes('BIBLIOTHECAIRE')) return 'bibliothecaire';
        }
        return 'utilisateur'; // Rôle par défaut
    }

    toggleChatbot(forceState = null) {
        const chatbotBox = document.getElementById('chatbotBox');
        this.isOpen = forceState !== null ? forceState : !this.isOpen;
        
        if (this.isOpen) {
            chatbotBox.classList.add('open');
            document.getElementById('chatbotInput').focus();
            this.showRoleBasedSuggestions();
        } else {
            chatbotBox.classList.remove('open');
        }
    }

    showRoleBasedSuggestions() {
        const suggestionsContainer = document.getElementById('chatbotSuggestions');
        const suggestionsForRole = this.suggestedQuestions[this.userRole] || this.suggestedQuestions.utilisateur;
        
        let chipsHTML = '<div class="suggestion-chips">';
        suggestionsForRole.slice(0, 3).forEach(suggestion => {
            chipsHTML += `<button class="suggestion-chip">${suggestion}</button>`;
        });
        chipsHTML += '</div>';
        
        suggestionsContainer.innerHTML = chipsHTML;
        
        // Réattacher les événements
        document.querySelectorAll('.suggestion-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const question = chip.textContent;
                document.getElementById('chatbotInput').value = question;
                this.sendMessage();
            });
        });
    }

    sendMessage() {
        const inputElement = document.getElementById('chatbotInput');
        const message = inputElement.value.trim();
        
        if (message === '') return;
        
        // Add user message to chat
        this.addMessage(message, 'user');
        inputElement.value = '';
        
        // Ajouter à l'historique
        this.conversationHistory.push({ role: 'user', content: message });
        
        // Process the message and get a response
        setTimeout(() => {
            const response = this.processMessage(message);
            this.addMessage(response, 'bot');
            
            // Ajouter à l'historique
            this.conversationHistory.push({ role: 'bot', content: response });
            
            // Mettre à jour les suggestions
            this.updateSuggestions(message);
        }, 500);
    }

    addMessage(message, sender) {
        const messagesContainer = document.getElementById('chatbotMessages');
        const messageHTML = `
            <div class="message ${sender}-message">
                <div class="message-content">
                    ${this.escapeHtml(message)}
                </div>
            </div>
        `;
        
        messagesContainer.insertAdjacentHTML('beforeend', messageHTML);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    updateSuggestions(lastUserMessage) {
        // Analyser le dernier message pour proposer des suggestions contextuelles
        const lowerMessage = lastUserMessage.toLowerCase();
        let newSuggestions = [];
        
        if (lowerMessage.includes('frais')) {
            newSuggestions = [
                'Comment ajouter des frais?',
                'Vérifier les paiements',
                'Importer des paiements'
            ];
        } else if (lowerMessage.includes('bibliothèque') || lowerMessage.includes('livre')) {
            newSuggestions = [
                'Comment valider un travail?',
                'Rechercher un document',
                'Gestion des emprunts'
            ];
        } else if (lowerMessage.includes('département') || lowerMessage.includes('faculté')) {
            newSuggestions = [
                'Ajouter un département',
                'Liste des départements',
                'Modifier un département'
            ];
        } else {
            // Suggestions par défaut basées sur le rôle
            newSuggestions = this.suggestedQuestions[this.userRole].slice(0, 3);
        }
        
        const suggestionsContainer = document.getElementById('chatbotSuggestions');
        let chipsHTML = '<div class="suggestion-chips">';
        newSuggestions.forEach(suggestion => {
            chipsHTML += `<button class="suggestion-chip">${suggestion}</button>`;
        });
        chipsHTML += '</div>';
        
        suggestionsContainer.innerHTML = chipsHTML;
        
        // Réattacher les événements
        document.querySelectorAll('.suggestion-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const question = chip.textContent;
                document.getElementById('chatbotInput').value = question;
                this.sendMessage();
            });
        });
    }

    processMessage(message) {
        // Convertir le message en minuscules pour une recherche insensible à la casse
        const lowerMessage = message.toLowerCase();
        
        // Rechercher des mots-clés dans le message
        for (const [keywords, response] of Object.entries(this.responses)) {
            if (keywords.split('|').some(keyword => lowerMessage.includes(keyword.toLowerCase()))) {
                return response;
            }
        }
        
        // Réponse par défaut si aucun mot-clé n'est trouvé
        return "Je ne suis pas sûr de comprendre votre demande. Pouvez-vous reformuler ou choisir parmi les sujets suivants: frais universitaires, bibliothèque, départements, ou gestion des étudiants?";
    }

    getUniversityResponses() {
        return {
            // Réponses générales
            "bonjour|salut|hello": "Bonjour! Comment puis-je vous aider avec la gestion universitaire aujourd'hui?",
            
            "aide|help|besoin d'aide": "Je peux vous aider avec plusieurs aspects de la gestion universitaire comme les frais, la bibliothèque, les départements, etc. Que souhaitez-vous savoir?",
            
            "merci|thanks": "Je vous en prie! N'hésitez pas si vous avez d'autres questions sur la gestion universitaire.",
            
            "au revoir|bye": "Au revoir! N'hésitez pas à revenir si vous avez d'autres questions concernant l'université.",
            
            // Frais universitaires
            "frais|paiement|facture": "La gestion des frais universitaires vous permet de configurer, suivre et gérer les paiements des étudiants. Vous pouvez accéder à ces fonctionnalités depuis le menu 'Frais'.",
            
            "ajouter frais|créer frais|nouveau frais": "Pour ajouter de nouveaux frais, accédez à la section 'Frais' puis cliquez sur le bouton 'Ajouter un frais'. Vous devrez spécifier la désignation, le montant, la promotion et l'année académique concernée.",
            
            "modifier frais|éditer frais": "Pour modifier des frais existants, accédez à la liste des frais et cliquez sur l'icône de modification à côté du frais concerné. Attention aux doublons: le système vérifie si un frais avec la même désignation existe déjà pour la même promotion et année académique.",
            
            "paiement frais|payer frais": "Les paiements peuvent être enregistrés individuellement ou importés en masse via un fichier CSV ou Excel. Accédez à la section 'Paiements' pour ces opérations.",
            
            "importer paiement|import csv": "Pour importer des paiements en masse, utilisez la fonction d'importation dans la section 'Paiements'. Le format du fichier dépend du type de paiement (complet ou partiel).",
            
            // Bibliothèque
            "bibliothèque|livre|document": "Le module de bibliothèque permet de gérer les documents, les travaux et les emprunts. Vous pouvez y accéder depuis le menu principal.",
            
            "valider travail|validation bibliothèque": "Pour valider un travail soumis à la bibliothèque, accédez à la section 'Valider' dans le module Bibliothèque. Vous verrez la liste des travaux en attente de validation.",
            
            "télécharger document|download": "Pour télécharger un document de la bibliothèque, utilisez l'icône de téléchargement à côté du document concerné dans la liste des documents.",
            
            // Départements et structure
            "département|faculté|structure": "La gestion des départements vous permet d'organiser la structure académique de l'université. Accédez-y via le menu 'Configuration' puis 'Départements'.",
            
            "ajouter département|créer département": "Pour ajouter un nouveau département, accédez à la section 'Départements' puis cliquez sur 'Ajouter un département'. Vous devrez spécifier le nom et éventuellement le rattacher à une faculté.",
            
            "modifier département|éditer département": "Pour modifier un département existant, accédez à la liste des départements et cliquez sur l'icône de modification à côté du département concerné.",
            
            // Étudiants et personnel
            "étudiant|inscription|matricule": "La gestion des étudiants vous permet de suivre les inscriptions, les dossiers académiques et les paiements. Accédez-y via le menu 'Étudiants'.",
            
            "personnel|enseignant|agent": "La gestion du personnel vous permet de suivre les enseignants, les agents administratifs et leurs contrats. Accédez-y via le menu 'GRH'.",
            
            "contrat|embauche": "Pour gérer les contrats du personnel, accédez à la section 'Contrats' dans le module GRH. Vous pouvez y ajouter, modifier ou consulter les contrats.",
            
            // Modules et cours
            "module|cours|matière": "La gestion des modules permet de configurer les cours dispensés dans chaque formation. Accédez-y via le menu 'Configuration' puis 'Modules'.",
            
            "ajouter module|créer module": "Pour ajouter un nouveau module, accédez à la section 'Modules' puis cliquez sur 'Ajouter un module'. Vous devrez spécifier le nom, les crédits et le rattacher à une formation.",
            
            // Authentification et sécurité
            "connexion|login|mot de passe": "Pour vous connecter, utilisez vos identifiants sur la page d'accueil. Si c'est votre première connexion, vous serez invité à changer votre mot de passe.",
            
            "changer mot de passe|password": "Pour changer votre mot de passe, accédez à votre profil utilisateur en cliquant sur votre nom en haut à droite, puis 'Modifier le profil'.",
            
            "déconnexion|logout": "Pour vous déconnecter, cliquez sur votre nom en haut à droite, puis sur 'Déconnexion'."
        };
    }

    getSuggestedQuestions() {
        return {
            // Suggestions pour tous les utilisateurs
            utilisateur: [
                "Comment accéder aux frais universitaires?",
                "Comment fonctionne la bibliothèque?",
                "Comment voir les départements?",
                "Comment changer mon mot de passe?",
                "Quelles sont les fonctionnalités principales?"
            ],
            
            // Suggestions spécifiques pour les administrateurs
            admin: [
                "Comment ajouter un nouveau frais?",
                "Comment gérer les utilisateurs?",
                "Comment configurer un département?",
                "Comment valider un paiement?",
                "Comment générer des rapports?"
            ],
            
            // Suggestions spécifiques pour les étudiants
            etudiant: [
                "Comment voir mes frais à payer?",
                "Comment accéder à la bibliothèque?",
                "Comment soumettre un document?",
                "Comment voir mon emploi du temps?",
                "Comment contacter un enseignant?"
            ],
            
            // Suggestions spécifiques pour les enseignants
            enseignant: [
                "Comment voir mes modules?",
                "Comment soumettre des notes?",
                "Comment accéder aux ressources?",
                "Comment voir mon emploi du temps?",
                "Comment contacter l'administration?"
            ],
            
            // Suggestions spécifiques pour les bibliothécaires
            bibliothecaire: [
                "Comment valider un travail?",
                "Comment ajouter un document?",
                "Comment gérer les emprunts?",
                "Comment rechercher un document?",
                "Comment générer des statistiques?"
            ]
        };
    }

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // Fonction pour analyser la page actuelle et offrir une aide contextuelle
    analyzeCurrentPage() {
        const currentPath = window.location.pathname;
        const pageTitle = document.title || '';
        
        // Déterminer la page actuelle
        if (currentPath.includes('frais')) {
            return "Je vois que vous êtes sur la page de gestion des frais. Vous pouvez ajouter, modifier ou consulter les frais universitaires ici. Besoin d'aide spécifique?";
        } else if (currentPath.includes('bibliotheque')) {
            return "Vous êtes dans le module de bibliothèque. Vous pouvez gérer les documents, valider des travaux ou gérer les emprunts. Comment puis-je vous aider?";
        } else if (currentPath.includes('departement')) {
            return "Vous êtes sur la page des départements. Ici, vous pouvez gérer la structure académique de l'université. Besoin d'aide pour une action spécifique?";
        } else if (currentPath.includes('module')) {
            return "Vous êtes dans la gestion des modules de cours. Vous pouvez configurer les enseignements pour chaque formation. Que souhaitez-vous faire?";
        } else if (currentPath.includes('etudiant')) {
            return "Vous êtes dans la gestion des étudiants. Vous pouvez consulter les dossiers, les inscriptions et les paiements. Comment puis-je vous aider?";
        } else if (currentPath.includes('personnel') || currentPath.includes('grh')) {
            return "Vous êtes dans la gestion des ressources humaines. Vous pouvez gérer le personnel, les contrats et les affectations. Besoin d'aide?";
        }
        
        // Page par défaut
        return "Comment puis-je vous aider avec la gestion universitaire aujourd'hui?";
    }

    // Fonction pour offrir de l'aide contextuelle basée sur les actions récentes
    offerContextualHelp() {
        // Analyser la page actuelle
        const contextualMessage = this.analyzeCurrentPage();
        
        // Ajouter le message contextuel
        this.addMessage(contextualMessage, 'bot');
        
        // Mettre à jour les suggestions en fonction du contexte
        this.updateSuggestionsBasedOnContext();
    }

    updateSuggestionsBasedOnContext() {
        const currentPath = window.location.pathname;
        let contextualSuggestions = [];
        
        if (currentPath.includes('frais')) {
            contextualSuggestions = [
                "Comment ajouter un frais?",
                "Comment importer des paiements?",
                "Comment vérifier les paiements?"
            ];
        } else if (currentPath.includes('bibliotheque')) {
            contextualSuggestions = [
                "Comment valider un document?",
                "Comment gérer les emprunts?",
                "Comment rechercher un document?"
            ];
        } else if (currentPath.includes('departement')) {
            contextualSuggestions = [
                "Comment ajouter un département?",
                "Comment modifier un département?",
                "Comment voir la structure complète?"
            ];
        } else {
            // Suggestions par défaut basées sur le rôle
            contextualSuggestions = this.suggestedQuestions[this.userRole].slice(0, 3);
        }
        
        const suggestionsContainer = document.getElementById('chatbotSuggestions');
        let chipsHTML = '<div class="suggestion-chips">';
        contextualSuggestions.forEach(suggestion => {
            chipsHTML += `<button class="suggestion-chip">${suggestion}</button>`;
        });
        chipsHTML += '</div>';
        
        suggestionsContainer.innerHTML = chipsHTML;
        
        // Réattacher les événements
        document.querySelectorAll('.suggestion-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const question = chip.textContent;
                document.getElementById('chatbotInput').value = question;
                this.sendMessage();
            });
        });
    }
}

// Initialiser le chatbot lorsque le DOM est chargé
document.addEventListener('DOMContentLoaded', () => {
    const chatbot = new ChatbotManager();
    
    // Offrir de l'aide contextuelle après un court délai
    setTimeout(() => {
        if (!chatbot.isOpen) {
            // Faire clignoter l'icône du chatbot pour attirer l'attention
            const chatbotToggle = document.getElementById('chatbotToggle');
            chatbotToggle.classList.add('pulse-animation');
            
            setTimeout(() => {
                chatbotToggle.classList.remove('pulse-animation');
            }, 3000);
        }
    }, 10000); // 10 secondes après le chargement
});
