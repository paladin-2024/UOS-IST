<?php 
// Initialiser le contrôleur
require_once 'controllers/HomeController.php';
$homeController = new HomeController();

// Récupérer les données
$latestNews = $homeController->getLatestNews(3);
$featuredFormations = $homeController->getFeaturedFormations(3);
$partners = $homeController->getFeaturedPartners(6);
$siteStats = $homeController->getSiteStats();
$settings = $homeController->getSiteSettings();
$committeeMembers = $homeController->getManagementCommitteeMembers(4);


include "include/head.php";
?>

<!-- Hero Section with Open Days -->
<section class="hero" style="background-image: url('uploads/istmbeni.png'); background-size: cover; background-position: center; position: relative; height: auto; max-height: 800px; ">
    <!-- Overlay avec flou pour l'arrière-plan uniquement -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; backdrop-filter: blur(5px);"></div>
    
    <div class="container" style="display: flex; justify-content: space-between; position: relative; z-index: 2; ">
        <!-- Contenu principal à gauche -->
        <div class="hero-content" style="width: 60%; padding-right: 20px;">
            <h1><?php echo htmlspecialchars($settings['site_name'] ?? 'Institut Supérieur des Techniques Médicales de BENI'); ?></h1>
            <p><?php echo htmlspecialchars($settings['site_description'] ?? "L'ISTM BENI est un établissement d'excellence dédié à la formation, la recherche et l'innovation dans les domaines des sciences médicales et de la santé."); ?></p>
            <div class="hero-btns">
                <a href="formations" class="hero-btn"><i class="fas fa-graduation-cap"></i> Nos formations</a>
                <a href="https://myistmbeni.istmbeni.info/" target="_" class="hero-btn"><i class="fas fa-file-alt"></i> Application mobile pour Etudiant</a>
            </div>
        </div>
        <!-- User Menu à droite -->
        <div class="user-menu" style="width: 35%; margin-top: 0;">
            <h3>Accès rapide</h3>
            <a target="_blank" href="https://istmbeni.info/portail/login" class="user-option">
                <div class="user-icon"><i class="fas fa-user-graduate"></i></div>
                Espace étudiants
                <span class="arrow-right"><i class="fas fa-arrow-right"></i></span>
            </a>
            <a target="_" href="https://istmbeni.info/" class="user-option">
                <div class="user-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                Espace enseignants
                <span class="arrow-right"><i class="fas fa-arrow-right"></i></span>
            </a>
            <a href="staff" class="user-option">
                <div class="user-icon"><i class="fas fa-user-tie"></i></div>
                Notre staff
                <span class="arrow-right"><i class="fas fa-arrow-right"></i></span>
            </a>
            <a href="evenements" class="user-option">
                <div class="user-icon"><i class="fas fa-calendar-alt"></i></div>
                Nos Evénements
                <span class="arrow-right"><i class="fas fa-arrow-right"></i></span>
            </a>
        </div>
    </div>
</section>

<!-- Section Actualités -->
<section class="section news-section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Actualités</h2>
            <p class="section-text">Restez informé des dernières nouvelles et événements de l'ISTM BENI. Découvrez nos avancées, nos succès et nos projets à venir.</p>
            <div class="nav-arrows">
                <div class="nav-arrow" id="news-prev"><i class="fas fa-arrow-left"></i></div>
                <div class="nav-arrow" id="news-next"><i class="fas fa-arrow-right"></i></div>
            </div>
        </div>
        
        <div class="card-grid news-slider">
            <?php foreach ($latestNews as $news): ?>
                <div class="card fade-in-element">
                    <img src=".<?php echo !empty($news['featured_image']) ? htmlspecialchars($news['featured_image']) : 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'; ?>" alt="<?php echo htmlspecialchars($news['title']); ?>" class="card-img">
                    <span class="card-badge"><?php echo htmlspecialchars($news['category_name'] ?? 'Actualité'); ?></span>
                    <div class="card-content">
                        <div class="card-date"><i class="far fa-calendar-alt"></i> <?php echo date('d F Y', strtotime($news['published_at'])); ?></div>
                        <h3 class="card-title"><?php echo htmlspecialchars($news['title']); ?></h3>
                        <p class="card-description"><?php echo htmlspecialchars($news['excerpt']); ?></p>
                        <a href="details_article&slug=<?php echo htmlspecialchars($news['slug']); ?>" class="card-link">Lire la suite <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($latestNews)): ?>
                <!-- Actualités statiques en fallback -->
                <div class="card fade-in-element">
                    <img src="https://images.unsplash.com/photo-1576091160550-2173dba999ef?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Conférence Santé" class="card-img">
                    <span class="card-badge">Événement</span>
                    <div class="card-content">
                        <div class="card-date"><i class="far fa-calendar-alt"></i> 15 Mars 2023</div>
                        <h3 class="card-title">Conférence Internationale sur les Innovations en Santé Publique</h3>
                        <p class="card-description">L'ISTM BENI a accueilli les plus grands experts mondiaux pour discuter des avancées en matière de santé publique et de prévention des maladies.</p>
                        <a href="#" class="card-link">Lire la suite <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="card fade-in-element">
                    <img src="https://images.unsplash.com/photo-1579684385127-1ef15d508118?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Signature de Partenariat" class="card-img">
                    <span class="card-badge">Partenariat</span>
                    <div class="card-content">
                        <div class="card-date"><i class="far fa-calendar-alt"></i> 28 Février 2023</div>
                        <h3 class="card-title">Signature d'un partenariat avec l'Hôpital Général de Référence</h3>
                        <p class="card-description">Ce partenariat stratégique permettra de renforcer les programmes de formation clinique et d'offrir davantage d'opportunités de stage pratique.</p>
                        <a href="#" class="card-link">Lire la suite <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="card fade-in-element">
                    <img src="https://images.unsplash.com/photo-1581056771107-24ca5f033842?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Recherche Innovation" class="card-img">
                    <span class="card-badge">Recherche</span>
                    <div class="card-content">
                        <div class="card-date"><i class="far fa-calendar-alt"></i> 5 Février 2023</div>
                        <h3 class="card-title">Publication d'une étude majeure sur les maladies tropicales</h3>
                        <p class="card-description">L'équipe de recherche de l'ISTM BENI a publié des résultats prometteurs sur le diagnostic précoce des maladies tropicales négligées.</p>
                        <a href="#" class="card-link">Lire la suite <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-40">
            <a href="actualites" class="btn btn-primary animate-hover">Voir toutes les actualités <i class="fas fa-arrow-right"></i></a>
        </div>
    </div>
</section>

<!-- Section anniversaire de l'institut -->
<section class="section anniversary-section">
    <div class="container">
        <div class="anniversary-content">
            <div class="anniversary-info fade-in-element">
                <span class="anniversary-badge">Excellence médicale</span>
                <h2 class="section-title">Former les professionnels de santé de demain</h2>
                <p class="section-text">Depuis sa création, l'ISTM BENI s'est imposé comme un acteur majeur dans la formation des professionnels de santé en République Démocratique du Congo. Notre engagement pour l'excellence académique et la pratique clinique nous permet de former des professionnels compétents et dévoués au service de la santé publique.</p>
                <div class="anniversary-stats">
                    <?php foreach ($siteStats as $index => $stat): ?>
                        <?php if ($index < 3): ?>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo htmlspecialchars($stat['stat_value']); ?></span>
                                <span class="stat-label"><?php echo htmlspecialchars($stat['description']); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <?php if (empty($siteStats)): ?>
                        <div class="stat-item">
                            <span class="stat-number">5,000+</span>
                            <span class="stat-label">Diplômés</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">80+</span>
                            <span class="stat-label">Professeurs</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">25+</span>
                            <span class="stat-label">Partenariats</span>
                        </div>
                    <?php endif; ?>
                </div>
                <a target="_" href="https://istmbeni.info/portail/index" class="btn btn-secondary animate-hover">Découvrir notre portail pour chercheur <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="anniversary-image fade-in-element">
                <img src="https://images.unsplash.com/photo-1516549655169-df83a0774514?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="ISTM BENI" class="rounded-image">
                <div class="anniversary-overlay">
                    <div class="anniversary-logo">
                        <div class="anniversary-logo-inner">ISTM</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section Se Former à l'ISTM -->
<section class="section training-section">
    <div class="container">
        <div class="section-header text-center">
            <h2 class="section-title">Se former à l'ISTM BENI</h2>
            <p class="section-text mx-auto" style="max-width: 800px;">Découvrez nos programmes de formation en sciences médicales et préparez-vous à devenir les acteurs du système de santé de demain.</p>
        </div>
        
        <div class="training-programs">
            <?php foreach ($featuredFormations as $formation): ?>
                <div class="training-card card fade-in-element">
                    <div class="training-icon">
                        <i class="<?php 
                            $icons = [
                                'licence' => 'fas fa-user-nurse',
                                'master' => 'fas fa-vial',
                                'doctorat' => 'fas fa-heartbeat',
                                'formation_continue' => 'fas fa-book-medical'
                            ];
                            echo $icons[$formation['level']] ?? 'fas fa-graduation-cap'; 
                        ?>"></i>
                    </div>
                    <div class="card-img-container">
                        <img src=".<?php echo !empty($formation['featured_image']) ? htmlspecialchars($formation['featured_image']) : 'https://images.unsplash.com/photo-1584515979956-d9f6e5d09982?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'; ?>" alt="<?php echo htmlspecialchars($formation['title']); ?>" class="card-img">
                        <div class="card-img-overlay"></div>
                    </div>
                    <div class="card-content">
                        <h3 class="card-title"><?php echo htmlspecialchars($formation['title']); ?></h3>
                        <p class="card-description"><?php echo htmlspecialchars($formation['short_description']); ?></p>
                        <a style="color: blue !important;" href="formation_details&slug=<?php echo htmlspecialchars($formation['slug']); ?>" class="card-link light-link">Découvrir <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($featuredFormations)): ?>
                <!-- Formations statiques en fallback -->
                <div class="training-card card fade-in-element">
                    <div class="training-icon"><i class="fas fa-user-nurse"></i></div>
                    <div class="card-img-container">
                        <img src="https://images.unsplash.com/photo-1584515979956-d9f6e5d09982?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Sciences Infirmières" class="card-img">
                        <div class="card-img-overlay"></div>
                    </div>
                    <div class="card-content">
                        <h3 class="card-title">Sciences Infirmières</h3>
                        <p class="card-description">Formation complète pour maîtriser les soins infirmiers et la prise en charge des patients</p>
                        <a style="color: blue !important;" href="#" class="card-link light-link">Découvrir <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="training-card card fade-in-element">
                    <div class="training-icon"><i class="fas fa-vial"></i></div>
                    <div class="card-img-container">
                        <img src="https://images.unsplash.com/photo-1579165466949-3180a3d056d5?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Techniques de Laboratoire" class="card-img">
                        <div class="card-img-overlay"></div>
                    </div>
                    <div class="card-content">
                        <h3 class="card-title">Techniques de Laboratoire</h3>
                        <p class="card-description">Spécialisation dans les analyses biomédicales et le diagnostic de laboratoire</p>
                        <a style="color: blue !important;" href="#" class="card-link light-link">Découvrir <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="training-card card fade-in-element">
                    <div class="training-icon"><i class="fas fa-heartbeat"></i></div>
                    <div class="card-img-container">
                        <img src="https://images.unsplash.com/photo-1530497610245-94d3c16cda28?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Santé Publique" class="card-img">
                        <div class="card-img-overlay"></div>
                    </div>
                    <div class="card-content">
                        <h3 class="card-title">Santé Publique</h3>
                        <p class="card-description">Formation en prévention, promotion de la santé et gestion des programmes sanitaires</p>
                        <a style="color: blue !important;" href="#" class="card-link light-link">Découvrir <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="training-features">
            <div class="feature-item fade-in-element">
                <div class="feature-icon"><i class="fas fa-users"></i></div>
                <div class="feature-content">
                    <h3>Encadrement personnalisé</h3>
                    <p>Des professeurs experts et un suivi individuel pour accompagner chaque étudiant dans son parcours académique.</p>
                </div>
            </div>
            
            <div class="feature-item fade-in-element">
                <div class="feature-icon"><i class="fas fa-hospital-user"></i></div>
                <div class="feature-content">
                    <h3>Stages cliniques</h3>
                    <p>Des stages pratiques dans les hôpitaux partenaires pour développer vos compétences en situation réelle.</p>
                </div>
            </div>
            
            <div class="feature-item fade-in-element">
                <div class="feature-icon"><i class="fas fa-flask"></i></div>
                <div class="feature-content">
                    <h3>Laboratoires modernes</h3>
                    <p>Des infrastructures équipées pour mettre en pratique les connaissances théoriques acquises.</p>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-50">
            <a href="formations" class="btn btn-primary animate-hover">Explorer toutes nos formations <i class="fas fa-arrow-right"></i></a>
        </div>
    </div>
</section>

<!-- Section Comité de Gestion -->
<?php if (!empty($committeeMembers)): ?>
<section class="section committee-section" style="background-color: #f8f9fa; padding: 80px 0;">
    <div class="container">
        <div class="section-header text-center">
            <h2 class="section-title">Notre Comité de Gestion</h2>
            <p class="section-text mx-auto" style="max-width: 800px;">Découvrez les membres du comité de direction qui guident notre institution vers l'excellence académique et la réussite de nos étudiants.</p>
        </div>
        
        <div class="row mt-5">
            <?php foreach ($committeeMembers as $member): ?>
                <div class="col-md-6 col-lg-3 mb-4 fade-in-element">
                    <div class="card h-100 committee-card">
                        <div class="card-img-container" style="height: 250px; overflow: hidden; position: relative;">
                            <?php if (!empty($member['profile_image'])): ?>
                                <img src=".<?php echo htmlspecialchars($member['profile_image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($member['full_name']); ?>" style="object-fit: cover; height: 100%; width: 100%;">
                            <?php else: ?>
                                <div style="background-color: #e9ecef; height: 100%; width: 100%; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user fa-4x" style="color: #adb5bd;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title"><?php echo htmlspecialchars($member['full_name']); ?></h5>
                            <div class="card-subtitle mb-2 text-primary"><?php echo htmlspecialchars($member['position']); ?></div>
                            <?php if (!empty($member['expertise'])): ?>
                                <div class="card-subtitle mb-2 text-muted"><i><?php echo htmlspecialchars($member['expertise']); ?></i></div>
                            <?php endif; ?>
                            <p class="card-text small">
                                <?php
                                    $bio = !empty($member['bio']) ? $member['bio'] : 'Membre du comité de direction de l\'ISTM BENI.';
                                    echo htmlspecialchars(substr($bio, 0, 100)) . (strlen($bio) > 100 ? '...' : '');
                                ?>
                            </p>
                        </div>
                        <div class="card-footer bg-transparent border-0 text-center">
                            <?php if (!empty($member['email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" class="btn btn-sm btn-outline-primary mx-1" title="Email">
                                    <i class="fas fa-envelope"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($member['phone'])): ?>
                                <a href="tel:<?php echo htmlspecialchars($member['phone']); ?>" class="btn btn-sm btn-outline-primary mx-1" title="Téléphone">
                                    <i class="fas fa-phone"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
    </div>
</section>
<?php endif; ?>


<!-- Section Partenaires -->
<section class="section anniversary-section">
    <div class="container">
        <h2 class="section-title">Nos partenaires</h2>
        <p class="section-text">L'ISTM BENI collabore avec des hôpitaux, des institutions de santé et des organisations nationales et internationales pour offrir des formations de qualité et des opportunités uniques à nos étudiants.</p>
        
        <div class="partners-logo-container fade-in-element">
            <?php foreach ($partners as $partner): ?>
                <div class="partner-logo">
                    <?php if (!empty($partner['logo'])): ?>
                        <img src=".<?php echo htmlspecialchars($partner['logo']); ?>" alt="<?php echo htmlspecialchars($partner['name']); ?>">
                    <?php else: ?>
                        <img src="https://via.placeholder.com/150x80?text=<?php echo urlencode($partner['name']); ?>" alt="<?php echo htmlspecialchars($partner['name']); ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($partners)): ?>
                <!-- Partenaires statiques en fallback -->
                <div class="partner-logo">
                    <img src="https://via.placeholder.com/150x80?text=OMS" alt="Organisation Mondiale de la Santé">
                </div>
                <div class="partner-logo">
                    <img src="https://via.placeholder.com/150x80?text=MSF" alt="Médecins Sans Frontières">
                </div>
                <div class="partner-logo">
                    <img src="https://via.placeholder.com/150x80?text=HGR+Beni" alt="Hôpital Général de Référence de Beni">
                </div>
                <div class="partner-logo">
                    <img src="https://via.placeholder.com/150x80?text=UNICEF" alt="UNICEF">
                </div>
                <div class="partner-logo">
                    <img src="https://via.placeholder.com/150x80?text=Ministère+Santé" alt="Ministère de la Santé">
                </div>
                <div class="partner-logo">
                    <img src="https://via.placeholder.com/150x80?text=Croix+Rouge" alt="Croix Rouge">
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-40">
            <a href="partenaires" class="btn btn-outline animate-hover">Devenir partenaire <i class="fas fa-handshake"></i></a>
        </div>
    </div>
</section>

<!-- Section Chiffres Clés -->
<section class="section key-figures-section" style="background-color: var(--primary-color); color: var(--white); padding: 80px 0;">
    <div class="container">
        <h2 class="section-title" style="color: var(--white);">L'ISTM BENI en chiffres</h2>
        <p class="section-text" style="color: rgba(255,255,255,0.8); max-width: 700px;">Notre institut en quelques chiffres qui témoignent de notre engagement pour l'excellence et la qualité de l'enseignement médical.</p>
        
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; margin-top: 50px;">
            <?php foreach ($siteStats as $stat): ?>
                <div class="stat-block fade-in-element" style="text-align: center; flex: 1; min-width: 200px; margin: 20px;">
                    <div style="font-size: 48px; font-weight: 700; color: var(--secondary-color); margin-bottom: 10px;">
                        <?php echo htmlspecialchars($stat['stat_value']); ?>
                    </div>
                    <div style="font-size: 18px; color: var(--white);">
                        <?php echo htmlspecialchars($stat['description']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($siteStats)): ?>
                <div class="stat-block fade-in-element" style="text-align: center; flex: 1; min-width: 200px; margin: 20px;">
                    <div style="font-size: 48px; font-weight: 700; color: var(--secondary-color); margin-bottom: 10px;">2500+</div>
                    <div style="font-size: 18px; color: var(--white);">Étudiants</div>
                </div>
                
                <div class="stat-block fade-in-element" style="text-align: center; flex: 1; min-width: 200px; margin: 20px;">
                    <div style="font-size: 48px; font-weight: 700; color: var(--secondary-color); margin-bottom: 10px;">80</div>
                    <div style="font-size: 18px; color: var(--white);">Enseignants</div>
                </div>
                
                <div class="stat-block fade-in-element" style="text-align: center; flex: 1; min-width: 200px; margin: 20px;">
                    <div style="font-size: 48px; font-weight: 700; color: var(--secondary-color); margin-bottom: 10px;">12</div>
                    <div style="font-size: 18px; color: var(--white);">Laboratoires</div>
                </div>
                
                <div class="stat-block fade-in-element" style="text-align: center; flex: 1; min-width: 200px; margin: 20px;">
                    <div style="font-size: 48px; font-weight: 700; color: var(--secondary-color); margin-bottom: 10px;">92%</div>
                    <div style="font-size: 18px; color: var(--white);">Taux d'insertion</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Section Contact Rapide -->
<section class="section contact-section" style="background-color: var(--white); padding: 80px 0;">
    <div class="container">
        <div style="display: flex; flex-wrap: wrap; gap: 50px; align-items: center;">
        <div style="flex: 1; min-width: 300px;">
                <h2 class="section-title">Contactez-nous</h2>
                <p class="section-text">Vous avez des questions sur nos formations, nos services ou vous souhaitez nous rendre visite ? N'hésitez pas à nous contacter.</p>
                
                <div style="margin-top: 30px;">
                    <div style="display: flex; align-items: flex-start; margin-bottom: 20px;">
                        <div style="width: 40px; height: 40px; background-color: var(--primary-color); color: var(--white); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600; margin-bottom: 5px;">Adresse</div>
                            <p style="color: var(--text-light);"><?php echo htmlspecialchars($settings['contact_address'] ?? 'Avenue de la Santé, Quartier Malepe<br>Beni, Nord-Kivu, République Démocratique du Congo'); ?></p>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: flex-start; margin-bottom: 20px;">
                        <div style="width: 40px; height: 40px; background-color: var(--primary-color); color: var(--white); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                            <i class="fas fa-phone-alt"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600; margin-bottom: 5px;">Téléphone</div>
                            <p style="color: var(--text-light);"><?php echo htmlspecialchars($settings['contact_phone'] ?? '+243 123 456 789'); ?></p>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: flex-start;">
                        <div style="width: 40px; height: 40px; background-color: var(--primary-color); color: var(--white); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600; margin-bottom: 5px;">Email</div>
                            <p style="color: var(--text-light);"><?php echo htmlspecialchars($settings['contact_email'] ?? 'contact@istmbeni.ac.cd'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 30px;">
                    <a href="contact" class="btn btn-primary animate-hover">Nous contacter <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            
            <div style="flex: 1; min-width: 300px;">
                <div style="background-color: #f9f9f9; border-radius: var(--border-radius); padding: 30px; box-shadow: var(--box-shadow);">
                    <h3 style="font-size: 20px; margin-bottom: 20px; color: var(--primary-color);">Demande d'information</h3>
                    <form action="controller/contact_form.php" method="post">
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success">
                            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger">
                            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                        </div>
                    <?php endif; ?>
                                            <div style="margin-bottom: 15px;">
                            <label for="name" style="display: block; margin-bottom: 5px; font-weight: 500;">Nom complet</label>
                            <input type="text" id="name" name="name" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: var(--border-radius); outline: none;" required>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label for="email" style="display: block; margin-bottom: 5px; font-weight: 500;">Email</label>
                            <input type="email" id="email" name="email" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: var(--border-radius); outline: none;" required>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label for="subject" style="display: block; margin-bottom: 5px; font-weight: 500;">Sujet</label>
                            <select id="subject" name="subject" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: var(--border-radius); outline: none;" required>
                                <option value="">Sélectionnez un sujet</option>
                                <option value="admission">Admission</option>
                                <option value="formation">Formations</option>
                                <option value="partnership">Partenariats</option>
                                <option value="other">Autre</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label for="message" style="display: block; margin-bottom: 5px; font-weight: 500;">Message</label>
                            <textarea id="message" name="message" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: var(--border-radius); outline: none; resize: vertical;" required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Envoyer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php 
include "include/footer.php";
?>

