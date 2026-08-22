<?php
include "include/head.php";
?>

<section class="section">
    <div class="container">
        <div class="section-header text-center">
            <h2 class="section-title">Formulaire de Pré-inscription à l'ISTM BUNIA</h2>
        </div>

        <div class="form-container">
            <div class="form-progress">
                <div class="progress-step active" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-label">Identité</div>
                </div>
                <div class="progress-step" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-label">Parcours académique</div>
                </div>
                <div class="progress-step" data-step="3">
                    <div class="step-number">3</div>
                    <div class="step-label">Choix de formation</div>
                </div>
                <div class="progress-step" data-step="4">
                    <div class="step-number">4</div>
                    <div class="step-label">Documents</div>
                </div>
                <div class="progress-step" data-step="5">
                    <div class="step-number">5</div>
                    <div class="step-label">Confirmation</div>
                </div>
            </div>

            <form id="preinscriptionForm" action="controller/process_preinscription.php" method="post" enctype="multipart/form-data">
                <!-- Étape 1: Identité -->
                <div class="form-step active" id="step1">
                    <div class="form-section">
                        <h3 class="form-section-title">I. IDENTITÉ</h3>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="nom">Nom <span class="required">*</span></label>
                                <input type="text" class="form-control" id="nom" name="nom" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="postnom">Post-nom <span class="required">*</span></label>
                                <input type="text" class="form-control" id="postnom" name="postnom" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="prenom">Prénom <span class="required">*</span></label>
                                <input type="text" class="form-control" id="prenom" name="prenom" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="lieu_naissance">Lieu de naissance <span class="required">*</span></label>
                                <input type="text" class="form-control" id="lieu_naissance" name="lieu_naissance" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="date_naissance">Date de naissance <span class="required">*</span></label>
                                <input type="date" class="form-control" id="date_naissance" name="date_naissance" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="sexe">Sexe <span class="required">*</span></label>
                                <select class="form-control" id="sexe" name="sexe" required>
                                    <option value="">Sélectionner</option>
                                    <option value="M">Masculin</option>
                                    <option value="F">Féminin</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="etat_civil">État civil <span class="required">*</span></label>
                                <select class="form-control" id="etat_civil" name="etat_civil" required>
                                    <option value="">Sélectionner</option>
                                    <option value="Célibataire">Célibataire</option>
                                    <option value="Marié(e)">Marié(e)</option>
                                    <option value="Divorcé(e)">Divorcé(e)</option>
                                    <option value="Veuf(ve)">Veuf(ve)</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="nationalite">Nationalité <span class="required">*</span></label>
                                <input type="text" class="form-control" id="nationalite" name="nationalite" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="nom_pere">Nom du père</label>
                                <input type="text" class="form-control" id="nom_pere" name="nom_pere">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="nom_mere">Nom de la mère</label>
                                <input type="text" class="form-control" id="nom_mere" name="nom_mere">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="province">Province d'origine <span class="required">*</span></label>
                                <input type="text" class="form-control" id="province" name="province" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="district">District d'origine</label>
                                <input type="text" class="form-control" id="district" name="district">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="territoire">Territoire d'origine</label>
                                <input type="text" class="form-control" id="territoire" name="territoire">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="secteur">Secteur d'origine</label>
                                <input type="text" class="form-control" id="secteur" name="secteur">
                            </div>
                        </div>

                        <h4 class="form-subsection-title">Adresse à Kinshasa</h4>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="avenue">Avenue/Rue <span class="required">*</span></label>
                                <input type="text" class="form-control" id="avenue" name="avenue" required>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="numero">N° <span class="required">*</span></label>
                                <input type="text" class="form-control" id="numero" name="numero" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="quartier">Quartier <span class="required">*</span></label>
                                <input type="text" class="form-control" id="quartier" name="quartier" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="commune">Commune <span class="required">*</span></label>
                                <input type="text" class="form-control" id="commune" name="commune" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="telephone">Téléphone <span class="required">*</span></label>
                                <input type="tel" class="form-control" id="telephone" name="telephone" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="email">Email <span class="required">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="personne_contact">Personne à contacter en cas d'urgence</label>
                                <input type="text" class="form-control" id="personne_contact" name="personne_contact">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="telephone_contact">Téléphone de la personne à contacter</label>
                                <input type="tel" class="form-control" id="telephone_contact" name="telephone_contact">
                            </div>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-primary next-step">Suivant <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>

                <!-- Étape 2: Parcours académique (dynamique selon le type d'inscription) -->
                <div class="form-step" id="step2">
                    <div class="form-section">
                        <h3 class="form-section-title">II. PARCOURS ACADÉMIQUE</h3>
                        
                        <!-- Sélection du type d'inscription -->
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="type_inscription">Type d'inscription <span class="required">*</span></label>
                                <select class="form-control" id="type_inscription" name="type_inscription" required>
                                    <option value="">Sélectionner</option>
                                    <option value="Nouvelle inscription - Préparatoire">Nouvelle inscription - Préparatoire</option>
                                    <option value="Nouvelle inscription - Master 1">Nouvelle inscription - Master 1</option>
                                    <option value="Réinscription">Réinscription (Étudiant ISTM BENI)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Section pour Préparatoire (études secondaires) -->
                        <div id="section-preparatoire">
                            <h4 class="form-subsection-title">Études Secondaires</h4>
                            
                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="ecole_secondaire">Nom de l'École Secondaire fréquentée <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="ecole_secondaire" name="ecole_secondaire">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="adresse_ecole">Adresse de l'École <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="adresse_ecole" name="adresse_ecole">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="section_humanites">Section suivie aux humanités <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="section_humanites" name="section_humanites">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="option_humanites">Option <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="option_humanites" name="option_humanites">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="centre_examen">Nom du Centre d'Examen d'État <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="centre_examen" name="centre_examen">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="annee_diplome">Année d'obtention du Diplôme d'État <span class="required">*</span></label>
                                    <input type="number" class="form-control" id="annee_diplome" name="annee_diplome" min="1990" max="2023">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="lieu_date_diplome">Lieu et date <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="lieu_date_diplome" name="lieu_date_diplome">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="pourcentage">Pourcentage <span class="required">*</span></label>
                                    <input type="number" class="form-control" id="pourcentage" name="pourcentage" min="50" max="100" step="0.1">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="numero_diplome">Numéro du Diplôme d'État <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="numero_diplome" name="numero_diplome">
                                </div>
                            </div>

                            <h4 class="form-subsection-title">Occupations après les humanités</h4>
                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="activites_professionnelles">Activités professionnelles</label>
                                    <textarea class="form-control" id="activites_professionnelles" name="activites_professionnelles" rows="3"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Section pour Master 1 (études universitaires) -->
                        <div id="section-master" style="display: none;">
                            <h4 class="form-subsection-title">Études Universitaires</h4>
                            
                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="universite">Université/Institut fréquenté <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="universite" name="universite">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="faculte">Faculté/Département <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="faculte" name="faculte">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="option_licence">Option <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="option_licence" name="option_licence">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="annee_licence">Année d'obtention de la Licence <span class="required">*</span></label>
                                    <input type="number" class="form-control" id="annee_licence" name="annee_licence" min="1990" max="2023">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="pourcentage_licence">Pourcentage/Mention <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="pourcentage_licence" name="pourcentage_licence">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="sujet_memoire">Sujet du mémoire de Licence</label>
                                    <textarea class="form-control" id="sujet_memoire" name="sujet_memoire" rows="2"></textarea>
                                </div>
                            </div>

                            <h4 class="form-subsection-title">Diplôme d'État (Secondaire)</h4>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="annee_diplome_master">Année d'obtention du Diplôme d'État <span class="required">*</span></label>
                                    <input type="number" class="form-control" id="annee_diplome_master" name="annee_diplome_master" min="1990" max="2023">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="numero_diplome_master">Numéro du Diplôme d'État <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="numero_diplome_master" name="numero_diplome_master">
                                </div>
                            </div>

                            <h4 class="form-subsection-title">Expérience professionnelle</h4>
                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="experience_professionnelle">Expérience professionnelle</label>
                                    <textarea class="form-control" id="experience_professionnelle" name="experience_professionnelle" rows="3"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Section pour Réinscription -->
                        <div id="section-reinscription" style="display: none;">
                            <h4 class="form-subsection-title">Informations de réinscription</h4>
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="matricule">Matricule <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="matricule" name="matricule">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="annee_academique_precedente">Année académique précédente <span class="required">*</span></label>
                                    <select class="form-control" id="annee_academique_precedente" name="annee_academique_precedente">
                                        <option value="">Sélectionner</option>
                                        <!-- Les options seront chargées dynamiquement via AJAX -->
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="promotion_precedente">Promotion précédente <span class="required">*</span></label>
                                    <select class="form-control" id="promotion_precedente" name="promotion_precedente">
                                        <option value="">Sélectionner</option>
                                        <!-- Les options seront chargées dynamiquement via AJAX -->
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="type_reinscription">Type de réinscription <span class="required">*</span></label>
                                    <select class="form-control" id="type_reinscription" name="type_reinscription">
                                        <option value="">Sélectionner</option>
                                        <option value="Passage en L1">Passage en L1 (Choix d'orientation)</option>
                                        <option value="Passage en L2">Passage en L2</option>
                                        <option value="Passage en L3">Passage en L3</option>
                                        <option value="Passage en M1">Passage en M1</option>
                                        <option value="Passage en M2">Passage en M2</option>
                                        <option value="Reprise">Reprise de classe</option>
                                        <option value="Changement de section">Changement de section</option>
                                        <option value="Réintégration">Réintégration après abandon</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Section spécifique pour le passage en L1 (choix d'orientation) -->
                            <div id="section-passage-l1" style="display: none;">
                                <div class="form-row">
                                    <div class="form-group col-md-12">
                                        <label>Choix d'orientation (par ordre de préférence) <span class="required">*</span></label>
                                        <div class="orientation-choices">
                                            <div class="form-group">
                                                <label for="orientation_choix1_l1">Premier choix <span class="required">*</span></label>
                                                <select class="form-control" id="orientation_choix1_l1" name="orientation_choix1_l1">
                                                    <option value="">Sélectionner une orientation</option>
                                                    <!-- Les options seront chargées dynamiquement via AJAX -->
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="orientation_choix2_l1">Deuxième choix <span class="required">*</span></label>
                                                <select class="form-control" id="orientation_choix2_l1" name="orientation_choix2_l1">
                                                    <option value="">Sélectionner une orientation</option>
                                                    <!-- Les options seront chargées dynamiquement via AJAX -->
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section pour le changement de section -->
                            <div id="section-changement" style="display: none;">
                                <div class="form-row">
                                    <div class="form-group col-md-12">
                                        <label for="nouvelle_section_id">Nouvelle section souhaitée <span class="required">*</span></label>
                                        <select class="form-control" id="nouvelle_section_id" name="nouvelle_section_id">
                                            <option value="">Sélectionner une section</option>
                                            <!-- Les options seront chargées dynamiquement via AJAX -->
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-12">
                                        <label for="motif_changement">Motif du changement <span class="required">*</span></label>
                                        <textarea class="form-control" id="motif_changement" name="motif_changement" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Section pour la réintégration -->
                            <div id="section-reintegration" style="display: none;">
                                <div class="form-row">
                                    <div class="form-group col-md-12">
                                        <label for="annee_abandon">Année d'abandon <span class="required">*</span></label>
                                        <select class="form-control" id="annee_abandon" name="annee_abandon">
                                            <option value="">Sélectionner</option>
                                            <!-- Les options seront chargées dynamiquement via AJAX -->
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-12">
                                        <label for="motif_abandon">Motif de l'abandon <span class="required">*</span></label>
                                        <textarea class="form-control" id="motif_abandon" name="motif_abandon" rows="3"></textarea>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-12">
                                        <label for="motif_reintegration">Motif de la réintégration <span class="required">*</span></label>
                                        <textarea class="form-control" id="motif_reintegration" name="motif_reintegration" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-outline-secondary prev-step"><i class="fas fa-arrow-left"></i> Précédent</button>
                        <button type="button" class="btn btn-primary next-step">Suivant <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>

                <!-- Étape 3: Choix de formation -->
                <div class="form-step" id="step3">
                    <div class="form-section">
                        <h3 class="form-section-title">III. CHOIX DE FORMATION</h3>

                        <!-- Section pour nouvelle inscription (Préparatoire ou Master) -->
                        <div id="section-nouvelle-inscription">
                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="section_id">Section <span class="required">*</span></label>
                                    <select class="form-control" id="section_id" name="section_id">
                                        <option value="">Sélectionner une section</option>
                                        <!-- Les options seront chargées dynamiquement via AJAX -->
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label>Choix d'orientation (par ordre de préférence) <span class="required">*</span></label>
                                    <div class="orientation-choices">
                                        <div class="form-group">
                                            <label for="orientation_choix1">Premier choix <span class="required">*</span></label>
                                            <select class="form-control" id="orientation_choix1" name="orientation_choix1">
                                                <option value="">Sélectionner une orientation</option>
                                                <!-- Les options seront chargées dynamiquement via AJAX -->
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="orientation_choix2">Deuxième choix <span class="required">*</span></label>
                                            <select class="form-control" id="orientation_choix2" name="orientation_choix2">
                                                <option value="">Sélectionner une orientation</option>
                                                <!-- Les options seront chargées dynamiquement via AJAX -->
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-outline-secondary prev-step"><i class="fas fa-arrow-left"></i> Précédent</button>
                        <button type="button" class="btn btn-primary next-step">Suivant <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>

                <!-- Étape 4: Documents -->
                <div class="form-step" id="step4">
                    <div class="form-section">
                        <h3 class="form-section-title">IV. DOCUMENTS REQUIS</h3>
                        <p class="form-section-description">Veuillez télécharger les documents suivants au format PDF ou image (JPG, PNG). Taille maximale: 2 Mo par fichier.</p>

                        <!-- Documents communs à tous les types d'inscription -->
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="photo">Photo d'identité récente <span class="required">*</span></label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="photo" name="photo" accept="image/*" required>
                                    <label class="custom-file-label" for="photo">Choisir un fichier</label>
                                </div>
                                <small class="form-text text-muted">Format 4x4 cm, fond blanc</small>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="attestation_naissance">Attestation de naissance <span class="required">*</span></label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="attestation_naissance" name="attestation_naissance" accept=".pdf,image/*" required>
                                    <label class="custom-file-label" for="attestation_naissance">Choisir un fichier</label>
                                </div>
                            </div>
                        </div>

                        <!-- Documents spécifiques pour Préparatoire -->
                        <div id="documents-preparatoire">
                            <h4 class="form-subsection-title">Documents pour l'inscription en Préparatoire</h4>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="diplome_etat">Diplôme d'État <span class="required">*</span></label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="diplome_etat" name="diplome_etat" accept=".pdf,image/*">
                                        <label class="custom-file-label" for="diplome_etat">Choisir un fichier</label>
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="bulletin_5eme">Bulletin de 5ème année des humanités <span class="required">*</span></label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="bulletin_5eme" name="bulletin_5eme" accept=".pdf,image/*">
                                        <label class="custom-file-label" for="bulletin_5eme">Choisir un fichier</label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="bulletin_6eme">Bulletin de 6ème année des humanités <span class="required">*</span></label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="bulletin_6eme" name="bulletin_6eme" accept=".pdf,image/*">
                                        <label class="custom-file-label" for="bulletin_6eme">Choisir un fichier</label>
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="attestation_aptitude">Attestation d'aptitude physique <span class="required">*</span></label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="attestation_aptitude" name="attestation_aptitude" accept=".pdf,image/*">
                                        <label class="custom-file-label" for="attestation_aptitude">Choisir un fichier</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Documents spécifiques pour Master 1 -->
                        <div id="documents-master" style="display: none;">
                            <h4 class="form-subsection-title">Documents pour l'inscription en Master 1</h4>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="diplome_licence">Diplôme de Licence <span class="required">*</span></label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="diplome_licence" name="diplome_licence" accept=".pdf,image/*">
                                        <label class="custom-file-label" for="diplome_licence">Choisir un fichier</label>
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="releve_notes_licence">Relevé de notes de Licence <span class="required">*</span></label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="releve_notes_licence" name="releve_notes_licence" accept=".pdf,image/*">
                                        <label class="custom-file-label" for="releve_notes_licence">Choisir un fichier</label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="memoire_licence">Mémoire de Licence (facultatif)</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="memoire_licence" name="memoire_licence" accept=".pdf">
                                        <label class="custom-file-label" for="memoire_licence">Choisir un fichier</label>
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="diplome_etat_master">Diplôme d'État (secondaire) <span class="required">*</span></label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="diplome_etat_master" name="diplome_etat_master" accept=".pdf,image/*">
                                        <label class="custom-file-label" for="diplome_etat_master">Choisir un fichier</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="attestation_aptitude_master">Attestation d'aptitude physique <span class="required">*</span></label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="attestation_aptitude_master" name="attestation_aptitude_master" accept=".pdf,image/*">
                                        <label class="custom-file-label" for="attestation_aptitude_master">Choisir un fichier</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Documents spécifiques pour Réinscription -->
                        <div id="documents-reinscription" style="display: none;">
                            <h4 class="form-subsection-title">Documents pour la réinscription</h4>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="fiches_validation">Fiches de validation des crédits <span class="required">*</span></label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="fiches_validation" name="fiches_validation" accept=".pdf,image/*">
                                        <label class="custom-file-label" for="fiches_validation">Choisir un fichier</label>
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="attestation_reussite">Attestation de réussite (si disponible)</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="attestation_reussite" name="attestation_reussite" accept=".pdf,image/*">
                                        <label class="custom-file-label" for="attestation_reussite">Choisir un fichier</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="carte_etudiant">Carte d'étudiant de l'année précédente <span class="required">*</span></label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="carte_etudiant" name="carte_etudiant" accept=".pdf,image/*">
                                        <label class="custom-file-label" for="carte_etudiant">Choisir un fichier</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Preuve de paiement pour tous les types d'inscription -->
                        <div class="form-row mt-4">
                            <div class="form-group col-md-6">
                                <label for="preuve_paiement">Preuve de paiement des frais de pré-inscription <span class="required">*</span></label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="preuve_paiement" name="preuve_paiement" accept=".pdf,image/*" required>
                                    <label class="custom-file-label" for="preuve_paiement">Choisir un fichier</label>
                                </div>
                                <small class="form-text text-muted">Reçu bancaire ou bordereau de versement</small>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="documents_additionnels">Documents supplémentaires (facultatif)</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="documents_additionnels" name="documents_additionnels[]" accept=".pdf,image/*" multiple>
                                    <label class="custom-file-label" for="documents_additionnels">Choisir des fichiers</label>
                                </div>
                                <small class="form-text text-muted">Vous pouvez ajouter d'autres documents pertinents pour votre dossier</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-outline-secondary prev-step"><i class="fas fa-arrow-left"></i> Précédent</button>
                        <button type="button" class="btn btn-primary next-step">Suivant <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>

                <!-- Étape 5: Confirmation -->
                <div class="form-step" id="step5">
                    <div class="form-section">
                        <h3 class="form-section-title">V. CONFIRMATION</h3>

                        <div class="confirmation-message">
                            <div class="alert alert-info">
                                <p><i class="fas fa-info-circle"></i> Veuillez vérifier attentivement toutes les informations saisies avant de soumettre votre demande de pré-inscription.</p>
                            </div>
                        </div>

                        <div class="form-summary">
                            <h4>Récapitulatif de votre demande</h4>
                            <div id="summary-content">
                                <!-- Le contenu sera généré dynamiquement via JavaScript -->
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="declaration" name="declaration" required>
                                    <label class="custom-control-label" for="declaration">Je déclare sur l'honneur que les informations fournies dans ce formulaire sont exactes et complètes. Je suis conscient(e) que toute fausse déclaration peut entraîner l'annulation de mon inscription. <span class="required">*</span></label>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="reglement" name="reglement" required>
                                    <label class="custom-control-label" for="reglement">J'ai lu et j'accepte le règlement intérieur de l'ISTM BENI. <span class="required">*</span></label>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="signature_electronique">Signature électronique (tapez votre nom complet) <span class="required">*</span></label>
                                <input type="text" class="form-control" id="signature_electronique" name="signature_electronique" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="date_signature">Date <span class="required">*</span></label>
                                <input type="date" class="form-control" id="date_signature" name="date_signature" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-outline-secondary prev-step"><i class="fas fa-arrow-left"></i> Précédent</button>
                        <button type="submit" class="btn btn-success submit-btn"><i class="fas fa-check-circle"></i> Soumettre ma demande</button>
                    </div>
                </div>
            </form>

            <div class="form-help">
                <div class="card help-card">
                    <div class="card-header">
                        <h5><i class="fas fa-question-circle"></i> Besoin d'aide ?</h5>
                    </div>
                    <div class="card-body">
                        <div class="help-section">
                            <h6><i class="fas fa-headset"></i> Service des admissions</h6>
                            <ul class="contact-list">
                                <li><i class="fas fa-phone-alt"></i> +243 123 456 789</li>
                                <li><i class="fas fa-envelope"></i> admissions@ISTM BENI.edu.cd</li>
                                <li><i class="fas fa-clock"></i> Lundi au vendredi, 8h à 16h</li>
                            </ul>
                        </div>

                        <div class="help-section">
                            <h6><i class="fas fa-info-circle"></i> Ressources utiles</h6>
                            <div class="resource-links">
                                <a href="#" class="btn btn-outline-primary btn-sm mb-2"><i class="fas fa-file-pdf"></i> Guide de pré-inscription</a>
                                <a href="#" class="btn btn-outline-primary btn-sm mb-2"><i class="fas fa-file-alt"></i> Règlement intérieur</a>
                                <a href="#" class="btn btn-outline-primary btn-sm"><i class="fas fa-question"></i> FAQ</a>
                            </div>
                        </div>

                        <div class="help-section">
                            <h6><i class="fas fa-map-marker-alt"></i> Nous trouver</h6>
                            <p>ISTM BENI - Institut National du Bâtiment et des Travaux Publics<br>
                                Avenue de la Science, Commune de Ngaliema<br>
                                Kinshasa, RD Congo</p>
                        </div>
                    </div>
                    <div class="card-footer text-center">
                        <a href="contact.php" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane"></i> Nous contacter</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Script pour la gestion du formulaire multi-étapes -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gestion des étapes du formulaire
        const steps = document.querySelectorAll('.form-step');
        const progressSteps = document.querySelectorAll('.progress-step');
        const nextButtons = document.querySelectorAll('.next-step');
        const prevButtons = document.querySelectorAll('.prev-step');
        const form = document.getElementById('preinscriptionForm');

        // Fonction pour passer à l'étape suivante
        function goToNextStep(currentStep) {
            // Validation de l'étape actuelle
            const inputs = steps[currentStep].querySelectorAll('input[required]:not([type="file"]), select[required], textarea[required]');
            let isValid = true;

            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                // Utiliser SweetAlert2 pour une notification d'erreur élégante
                Swal.fire({
                    title: 'Champs obligatoires',
                    text: 'Veuillez remplir tous les champs obligatoires avant de continuer.',
                    icon: 'warning',
                    confirmButtonText: 'Compris',
                    confirmButtonColor: '#003366'
                });
                return;
            }

            // Passer à l'étape suivante
            steps[currentStep].classList.remove('active');
            steps[currentStep + 1].classList.add('active');

            // Mettre à jour la barre de progression
            progressSteps[currentStep].classList.add('completed');
            progressSteps[currentStep + 1].classList.add('active');

            // Faire défiler vers le haut
            window.scrollTo(0, 0);

            // Si c'est la dernière étape, générer le récapitulatif
            if (currentStep + 1 === steps.length - 1) {
                generateSummary();
            }
        }

        // Fonction pour revenir à l'étape précédente
        function goToPrevStep(currentStep) {
            steps[currentStep].classList.remove('active');
            steps[currentStep - 1].classList.add('active');

            progressSteps[currentStep].classList.remove('active');
            progressSteps[currentStep - 1].classList.add('active');

            window.scrollTo(0, 0);
        }

        // Attacher les événements aux boutons
        nextButtons.forEach((button, index) => {
            button.addEventListener('click', () => {
                goToNextStep(index);
            });
        });

        prevButtons.forEach((button, index) => {
            button.addEventListener('click', () => {
                goToPrevStep(index + 1);
            });
        });

        // Gestion des types d'inscription
        const typeInscription = document.getElementById('type_inscription');
        const sectionPreparatoire = document.getElementById('section-preparatoire');
        const sectionMaster = document.getElementById('section-master');
        const sectionReinscription = document.getElementById('section-reinscription');
        const documentsPreparatoire = document.getElementById('documents-preparatoire');
        const documentsMaster = document.getElementById('documents-master');
        const documentsReinscription = document.getElementById('documents-reinscription');

        typeInscription.addEventListener('change', function() {
            // Masquer toutes les sections
            sectionPreparatoire.style.display = 'none';
            sectionMaster.style.display = 'none';
            sectionReinscription.style.display = 'none';
            documentsPreparatoire.style.display = 'none';
            documentsMaster.style.display = 'none';
            documentsReinscription.style.display = 'none';
            
            // Supprimer les attributs required
            removeRequiredAttributes();
            
            if (this.value === 'Nouvelle inscription - Préparatoire') {
                // Afficher les sections pour Préparatoire
                sectionPreparatoire.style.display = 'block';
                documentsPreparatoire.style.display = 'block';
                
                // Ajouter required aux champs de Préparatoire
                addRequiredAttributesPreparatoire();
                
                // Notification
                showToast('Mode nouvelle inscription - Préparatoire activé', 'info');
            } 
            else if (this.value === 'Nouvelle inscription - Master 1') {
                // Afficher les sections pour Master 1
                sectionMaster.style.display = 'block';
                documentsMaster.style.display = 'block';
                
                // Ajouter required aux champs de Master
                addRequiredAttributesMaster();
                
                // Notification
                showToast('Mode nouvelle inscription - Master 1 activé', 'info');
            } 
            else if (this.value === 'Réinscription') {
                // Afficher les sections pour réinscription
                sectionReinscription.style.display = 'block';
                documentsReinscription.style.display = 'block';
                
                // Ajouter required aux champs de réinscription
                addRequiredAttributesReinscription();
                
                // Notification
                showToast('Mode réinscription activé', 'info');
            }
        });

        // Fonction pour supprimer tous les attributs required des champs spécifiques
        function removeRequiredAttributes() {
            // Préparatoire
            document.getElementById('ecole_secondaire').removeAttribute('required');
            document.getElementById('adresse_ecole').removeAttribute('required');
            document.getElementById('section_humanites').removeAttribute('required');
            document.getElementById('option_humanites').removeAttribute('required');
            document.getElementById('centre_examen').removeAttribute('required');
            document.getElementById('annee_diplome').removeAttribute('required');
            document.getElementById('lieu_date_diplome').removeAttribute('required');
            document.getElementById('pourcentage').removeAttribute('required');
            document.getElementById('numero_diplome').removeAttribute('required');
            document.getElementById('diplome_etat').removeAttribute('required');
            document.getElementById('bulletin_5eme').removeAttribute('required');
            document.getElementById('bulletin_6eme').removeAttribute('required');
            document.getElementById('attestation_aptitude').removeAttribute('required');
            
            // Master
            document.getElementById('universite').removeAttribute('required');
            document.getElementById('faculte').removeAttribute('required');
            document.getElementById('option_licence').removeAttribute('required');
            document.getElementById('annee_licence').removeAttribute('required');
            document.getElementById('pourcentage_licence').removeAttribute('required');
            document.getElementById('annee_diplome_master').removeAttribute('required');
            document.getElementById('numero_diplome_master').removeAttribute('required');
            document.getElementById('diplome_licence').removeAttribute('required');
            document.getElementById('releve_notes_licence').removeAttribute('required');
            document.getElementById('diplome_etat_master').removeAttribute('required');
            document.getElementById('attestation_aptitude_master').removeAttribute('required');
            
            // Réinscription
            document.getElementById('matricule').removeAttribute('required');
            document.getElementById('annee_academique_precedente').removeAttribute('required');
            document.getElementById('promotion_precedente').removeAttribute('required');
            document.getElementById('type_reinscription').removeAttribute('required');
            document.getElementById('fiches_validation').removeAttribute('required');
            document.getElementById('carte_etudiant').removeAttribute('required');
        }

        // Fonction pour ajouter les attributs required aux champs de Préparatoire
        function addRequiredAttributesPreparatoire() {
            document.getElementById('ecole_secondaire').setAttribute('required', 'required');
            document.getElementById('adresse_ecole').setAttribute('required', 'required');
            document.getElementById('section_humanites').setAttribute('required', 'required');
            document.getElementById('option_humanites').setAttribute('required', 'required');
            document.getElementById('centre_examen').setAttribute('required', 'required');
            document.getElementById('annee_diplome').setAttribute('required', 'required');
            document.getElementById('lieu_date_diplome').setAttribute('required', 'required');
            document.getElementById('pourcentage').setAttribute('required', 'required');
            document.getElementById('numero_diplome').setAttribute('required', 'required');
            document.getElementById('diplome_etat').setAttribute('required', 'required');
            document.getElementById('bulletin_5eme').setAttribute('required', 'required');
            document.getElementById('bulletin_6eme').setAttribute('required', 'required');
            document.getElementById('attestation_aptitude').setAttribute('required', 'required');
        }

        // Fonction pour ajouter les attributs required aux champs de Master
        function addRequiredAttributesMaster() {
            document.getElementById('universite').setAttribute('required', 'required');
            document.getElementById('faculte').setAttribute('required', 'required');
            document.getElementById('option_licence').setAttribute('required', 'required');
            document.getElementById('annee_licence').setAttribute('required', 'required');
            document.getElementById('pourcentage_licence').setAttribute('required', 'required');
            document.getElementById('annee_diplome_master').setAttribute('required', 'required');
            document.getElementById('numero_diplome_master').setAttribute('required', 'required');
            document.getElementById('diplome_licence').setAttribute('required', 'required');
            document.getElementById('releve_notes_licence').setAttribute('required', 'required');
            document.getElementById('diplome_etat_master').setAttribute('required', 'required');
            document.getElementById('attestation_aptitude_master').setAttribute('required', 'required');
        }

        // Fonction pour ajouter les attributs required aux champs de réinscription
        function addRequiredAttributesReinscription() {
            document.getElementById('matricule').setAttribute('required', 'required');
            document.getElementById('annee_academique_precedente').setAttribute('required', 'required');
            document.getElementById('promotion_precedente').setAttribute('required', 'required');
            document.getElementById('type_reinscription').setAttribute('required', 'required');
            document.getElementById('fiches_validation').setAttribute('required', 'required');
            document.getElementById('carte_etudiant').setAttribute('required', 'required');
        }

        // Gestion des types de réinscription
        const typeReinscription = document.getElementById('type_reinscription');
        const sectionChangement = document.getElementById('section-changement');
        const sectionReintegration = document.getElementById('section-reintegration');
        const sectionPassageL1 = document.getElementById('section-passage-l1');

        typeReinscription.addEventListener('change', function() {
            // Masquer toutes les sections spécifiques
            sectionChangement.style.display = 'none';
            sectionReintegration.style.display = 'none';
            sectionPassageL1.style.display = 'none';

            // Supprimer tous les attributs required
            document.getElementById('nouvelle_section_id').removeAttribute('required');
            document.getElementById('motif_changement').removeAttribute('required');
            document.getElementById('annee_abandon').removeAttribute('required');
            document.getElementById('motif_abandon').removeAttribute('required');
            document.getElementById('motif_reintegration').removeAttribute('required');
            document.getElementById('orientation_choix1_l1').removeAttribute('required');
            document.getElementById('orientation_choix2_l1').removeAttribute('required');

            // Afficher la section appropriée en fonction du type de réinscription
            if (this.value === 'Changement de section') {
                sectionChangement.style.display = 'block';
                document.getElementById('nouvelle_section_id').setAttribute('required', 'required');
                document.getElementById('motif_changement').setAttribute('required', 'required');
            } 
            else if (this.value === 'Réintégration') {
                sectionReintegration.style.display = 'block';
                document.getElementById('annee_abandon').setAttribute('required', 'required');
                document.getElementById('motif_abandon').setAttribute('required', 'required');
                document.getElementById('motif_reintegration').setAttribute('required', 'required');
            } 
            else if (this.value === 'Passage en L1') {
                sectionPassageL1.style.display = 'block';
                document.getElementById('orientation_choix1_l1').setAttribute('required', 'required');
                document.getElementById('orientation_choix2_l1').setAttribute('required', 'required');
                loadOrientationsForL1();
            }

            showToast('Type de réinscription modifié', 'info');
        });

        // Gestion des fichiers uploadés
        const fileInputs = document.querySelectorAll('.custom-file-input');

        fileInputs.forEach(input => {
            input.addEventListener('change', function() {
                const fileName = this.files[0] ? this.files[0].name : 'Choisir un fichier';
                this.nextElementSibling.textContent = fileName;

                // Vérifier la taille du fichier (max 2 Mo)
                if (this.files[0] && this.files[0].size > 2 * 1024 * 1024) {
                    Swal.fire({
                        title: 'Fichier trop volumineux',
                        text: 'La taille maximale autorisée est de 2 Mo.',
                        icon: 'error',
                        confirmButtonText: 'Compris'
                    });
                    this.value = '';
                    this.nextElementSibling.textContent = 'Choisir un fichier';
                } else if (this.files[0]) {
                    showToast('Fichier sélectionné', 'success');
                }
            });
        });

        // Fonction pour générer le récapitulatif
        function generateSummary() {
            const summaryContent = document.getElementById('summary-content');
            let html = '<div class="summary-sections">';

            // Identité
            html += '<div class="summary-section"><h5>Identité</h5><table class="table table-sm">';
            html += `<tr><td>Nom complet</td><td>${document.getElementById('nom').value} ${document.getElementById('postnom').value} ${document.getElementById('prenom').value}</td></tr>`;
            html += `<tr><td>Né(e) le</td><td>${document.getElementById('date_naissance').value} à ${document.getElementById('lieu_naissance').value}</td></tr>`;
            html += `<tr><td>Sexe</td><td>${document.getElementById('sexe').options[document.getElementById('sexe').selectedIndex].text}</td></tr>`;
            html += `<tr><td>État civil</td><td>${document.getElementById('etat_civil').options[document.getElementById('etat_civil').selectedIndex].text}</td></tr>`;
            html += `<tr><td>Nationalité</td><td>${document.getElementById('nationalite').value}</td></tr>`;
            html += `<tr><td>Adresse</td><td>Avenue ${document.getElementById('avenue').value}, N° ${document.getElementById('numero').value}, Quartier ${document.getElementById('quartier').value}, Commune de ${document.getElementById('commune').value}</td></tr>`;
            html += `<tr><td>Contact</td><td>Tél: ${document.getElementById('telephone').value}, Email: ${document.getElementById('email').value}</td></tr>`;
            html += '</table></div>';

            const typeInscription = document.getElementById('type_inscription').value;

            // Parcours académique selon le type d'inscription
            if (typeInscription === 'Nouvelle inscription - Préparatoire') {
                html += '<div class="summary-section"><h5>Études secondaires</h5><table class="table table-sm">';
                html += `<tr><td>École</td><td>${document.getElementById('ecole_secondaire').value}</td></tr>`;
                html += `<tr><td>Section/Option</td><td>${document.getElementById('section_humanites').value} / ${document.getElementById('option_humanites').value}</td></tr>`;
                html += `<tr><td>Diplôme d'État</td><td>N° ${document.getElementById('numero_diplome').value}, obtenu en ${document.getElementById('annee_diplome').value} avec ${document.getElementById('pourcentage').value}%</td></tr>`;
                html += `<tr><td>Centre d'examen</td><td>${document.getElementById('centre_examen').value}</td></tr>`;
                html += '</table></div>';
            } 
            else if (typeInscription === 'Nouvelle inscription - Master 1') {
                html += '<div class="summary-section"><h5>Études universitaires</h5><table class="table table-sm">';
                html += `<tr><td>Université/Institut</td><td>${document.getElementById('universite').value}</td></tr>`;
                html += `<tr><td>Faculté/Option</td><td>${document.getElementById('faculte').value} / ${document.getElementById('option_licence').value}</td></tr>`;
                html += `<tr><td>Licence</td><td>Obtenue en ${document.getElementById('annee_licence').value} avec ${document.getElementById('pourcentage_licence').value}</td></tr>`;
                html += `<tr><td>Diplôme d'État</td><td>N° ${document.getElementById('numero_diplome_master').value}, obtenu en ${document.getElementById('annee_diplome_master').value}</td></tr>`;
                html += '</table></div>';
            } 
            else if (typeInscription === 'Réinscription') {
                html += '<div class="summary-section"><h5>Informations de réinscription</h5><table class="table table-sm">';
                html += `<tr><td>Matricule</td><td>${document.getElementById('matricule').value}</td></tr>`;
                
                const anneeSelect = document.getElementById('annee_academique_precedente');
                const anneeText = anneeSelect.options[anneeSelect.selectedIndex].text;
                html += `<tr><td>Année académique précédente</td><td>${anneeText}</td></tr>`;
                
                const promotionSelect = document.getElementById('promotion_precedente');
                const promotionText = promotionSelect.options[promotionSelect.selectedIndex].text;
                html += `<tr><td>Promotion précédente</td><td>${promotionText}</td></tr>`;
                
                const typeReinscriptionSelect = document.getElementById('type_reinscription');
                const typeReinscriptionText = typeReinscriptionSelect.options[typeReinscriptionSelect.selectedIndex].text;
                html += `<tr><td>Type de réinscription</td><td>${typeReinscriptionText}</td></tr>`;
                
                if (document.getElementById('type_reinscription').value === 'Passage en L1') {
                    const orientation1Select = document.getElementById('orientation_choix1_l1');
                    const orientation1Text = orientation1Select.options[orientation1Select.selectedIndex].text;
                    const orientation2Select = document.getElementById('orientation_choix2_l1');
                    const orientation2Text = orientation2Select.options[orientation2Select.selectedIndex].text;
                    
                    html += `<tr><td>Premier choix d'orientation</td><td>${orientation1Text}</td></tr>`;
                    html += `<tr><td>Deuxième choix d'orientation</td><td>${orientation2Text}</td></tr>`;
                } 
                else if (document.getElementById('type_reinscription').value === 'Changement de section') {
                    const nouvelleSectionSelect = document.getElementById('nouvelle_section_id');
                    const nouvelleSectionText = nouvelleSectionSelect.options[nouvelleSectionSelect.selectedIndex].text;
                    
                    html += `<tr><td>Nouvelle section souhaitée</td><td>${nouvelleSectionText}</td></tr>`;
                    html += `<tr><td>Motif du changement</td><td>${document.getElementById('motif_changement').value}</td></tr>`;
                } 
                else if (document.getElementById('type_reinscription').value === 'Réintégration') {
                    const anneeAbandonSelect = document.getElementById('annee_abandon');
                    const anneeAbandonText = anneeAbandonSelect.options[anneeAbandonSelect.selectedIndex].text;
                    
                    html += `<tr><td>Année d'abandon</td><td>${anneeAbandonText}</td></tr>`;
                    html += `<tr><td>Motif de l'abandon</td><td>${document.getElementById('motif_abandon').value}</td></tr>`;
                    html += `<tr><td>Motif de réintégration</td><td>${document.getElementById('motif_reintegration').value}</td></tr>`;
                }
                
                html += '</table></div>';
            }

            // Choix de formation (pour nouvelle inscription)
            if (typeInscription === 'Nouvelle inscription - Préparatoire' || typeInscription === 'Nouvelle inscription - Master 1') {
                html += '<div class="summary-section"><h5>Formation demandée</h5><table class="table table-sm">';
                
                const sectionSelect = document.getElementById('section_id');
                const sectionText = sectionSelect.options[sectionSelect.selectedIndex].text;
                
                const orientation1Select = document.getElementById('orientation_choix1');
                const orientation1Text = orientation1Select.options[orientation1Select.selectedIndex].text;
                
                const orientation2Select = document.getElementById('orientation_choix2');
                const orientation2Text = orientation2Select.options[orientation2Select.selectedIndex].text;
                
                html += `<tr><td>Type d'inscription</td><td>${typeInscription}</td></tr>`;
                html += `<tr><td>Section</td><td>${sectionText}</td></tr>`;
                html += `<tr><td>Premier choix d'orientation</td><td>${orientation1Text}</td></tr>`;
                html += `<tr><td>Deuxième choix d'orientation</td><td>${orientation2Text}</td></tr>`;
                
                html += '</table></div>';
            }

            // Documents fournis
            html += '<div class="summary-section"><h5>Documents fournis</h5><ul class="list-group">';
            html += '<li class="list-group-item">Photo d\'identité</li>';
            html += '<li class="list-group-item">Attestation de naissance</li>';

            if (typeInscription === 'Nouvelle inscription - Préparatoire') {
                html += '<li class="list-group-item">Diplôme d\'État</li>';
                html += '<li class="list-group-item">Bulletins de 5ème et 6ème années</li>';
                html += '<li class="list-group-item">Attestation d\'aptitude physique</li>';
            } 
            else if (typeInscription === 'Nouvelle inscription - Master 1') {
                html += '<li class="list-group-item">Diplôme de Licence</li>';
                html += '<li class="list-group-item">Relevé de notes de Licence</li>';
                html += '<li class="list-group-item">Diplôme d\'État (secondaire)</li>';
                if (document.getElementById('memoire_licence').files.length > 0) {
                    html += '<li class="list-group-item">Mémoire de Licence</li>';
                }
                html += '<li class="list-group-item">Attestation d\'aptitude physique</li>';
            } 
            else if (typeInscription === 'Réinscription') {
                html += '<li class="list-group-item">Fiches de validation des crédits</li>';
                html += '<li class="list-group-item">Carte d\'étudiant de l\'année précédente</li>';
                if (document.getElementById('attestation_reussite').files.length > 0) {
                    html += '<li class="list-group-item">Attestation de réussite</li>';
                }
            }

            html += '<li class="list-group-item">Preuve de paiement</li>';
            html += '</ul></div>';

            html += '</div>';
            summaryContent.innerHTML = html;
        }

        // Fonction pour charger les orientations pour L1
        function loadOrientationsForL1() {
            // Notification de chargement
            const loadingToast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false
            });

            loadingToast.fire({
                title: 'Chargement des orientations...',
                didOpen: () => {
                    loadingToast.showLoading();
                }
            });

            // Récupérer la section actuelle de l'étudiant via son matricule
            const matricule = document.getElementById('matricule').value;

            if (!matricule) {
                loadingToast.close();
                Swal.fire({
                    title: 'Erreur',
                    text: 'Veuillez d\'abord saisir votre matricule',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Appel AJAX pour récupérer la section actuelle et les orientations disponibles
            fetch(`controller/get_student_section.php?matricule=${matricule}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const orientation1Select = document.getElementById('orientation_choix1_l1');
                        const orientation2Select = document.getElementById('orientation_choix2_l1');

                        // Vider les options existantes
                        orientation1Select.innerHTML = '<option value="">Sélectionner une orientation</option>';
                        orientation2Select.innerHTML = '<option value="">Sélectionner une orientation</option>';

                        // Ajouter les nouvelles options
                        data.orientations.forEach(orientation => {
                            const option1 = document.createElement('option');
                            option1.value = orientation.id;
                            option1.textContent = orientation.designation;

                            const option2 = document.createElement('option');
                            option2.value = orientation.id;
                            option2.textContent = orientation.designation;

                            orientation1Select.appendChild(option1);
                            orientation2Select.appendChild(option2);
                        });

                        loadingToast.close();
                        showToast('Orientations chargées', 'success');
                    } else {
                        loadingToast.close();
                        Swal.fire({
                            title: 'Erreur',
                            text: data.message || 'Impossible de récupérer les informations de l\'étudiant',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    loadingToast.close();
                    Swal.fire({
                        title: 'Erreur de connexion',
                        text: 'Impossible de communiquer avec le serveur',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                });
        }

        // Charger les sections et orientations via AJAX
        fetch('controller/get_sections.php')
            .then(response => response.json())
            .then(data => {
                const sectionSelect = document.getElementById('section_id');
                const nouvelleSectionSelect = document.getElementById('nouvelle_section_id');

                data.forEach(section => {
                    const option = document.createElement('option');
                    option.value = section.id;
                    option.textContent = section.designation;
                    sectionSelect.appendChild(option);

                    const option2 = document.createElement('option');
                    option2.value = section.id;
                    option2.textContent = section.designation;
                    nouvelleSectionSelect.appendChild(option2);
                });
            })
            .catch(error => {
                console.error('Erreur lors du chargement des sections:', error);
                showToast('Impossible de charger les sections', 'error');
            });

        // Charger les orientations en fonction de la section sélectionnée
        document.getElementById('section_id').addEventListener('change', function() {
            const sectionId = this.value;
            if (!sectionId) return;

            // Notification de chargement
            const loadingToast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false
            });

            loadingToast.fire({
                title: 'Chargement des orientations...',
                didOpen: () => {
                    loadingToast.showLoading();
                }
            });

            fetch(`controller/get_orientations.php?section_id=${sectionId}`)
                .then(response => response.json())
                .then(data => {
                    const orientation1Select = document.getElementById('orientation_choix1');
                    const orientation2Select = document.getElementById('orientation_choix2');

                    // Vider les options existantes
                    orientation1Select.innerHTML = '<option value="">Sélectionner une orientation</option>';
                    orientation2Select.innerHTML = '<option value="">Sélectionner une orientation</option>';

                    data.forEach(orientation => {
                        const option1 = document.createElement('option');
                        option1.value = orientation.id;
                        option1.textContent = orientation.designation;

                        const option2 = document.createElement('option');
                        option2.value = orientation.id;
                        option2.textContent = orientation.designation;

                        orientation1Select.appendChild(option1);
                        orientation2Select.appendChild(option2);
                    });

                    // Fermer la notification de chargement
                    loadingToast.close();
                    showToast('Orientations chargées', 'success');
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des orientations:', error);
                    loadingToast.close();
                    showToast('Impossible de charger les orientations', 'error');
                });
        });

        // Charger les années académiques
        fetch('controller/get_annees_academiques.php')
            .then(response => response.json())
            .then(data => {
                const anneeSelect = document.getElementById('annee_academique_precedente');
                const anneeAbandonSelect = document.getElementById('annee_abandon');

                data.forEach(annee => {
                    const option1 = document.createElement('option');
                    option1.value = annee.id;
                    option1.textContent = annee.designation;

                    const option2 = document.createElement('option');
                    option2.value = annee.id;
                    option2.textContent = annee.designation;

                    anneeSelect.appendChild(option1);
                    anneeAbandonSelect.appendChild(option2);
                });
            })
            .catch(error => {
                console.error('Erreur lors du chargement des années académiques:', error);
                showToast('Impossible de charger les années académiques', 'error');
            });

        // Charger les promotions en fonction de l'année académique
        document.getElementById('annee_academique_precedente').addEventListener('change', function() {
            const anneeId = this.value;
            if (!anneeId) return;

            // Notification de chargement
            const loadingToast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false
            });

            loadingToast.fire({
                title: 'Chargement des promotions...',
                didOpen: () => {
                    loadingToast.showLoading();
                }
            });

            fetch(`controller/get_promotions.php?annee_id=${anneeId}`)
                .then(response => response.json())
                .then(data => {
                    const promotionSelect = document.getElementById('promotion_precedente');

                    // Vider les options existantes
                    promotionSelect.innerHTML = '<option value="">Sélectionner</option>';

                    data.forEach(promotion => {
                        const option = document.createElement('option');
                        option.value = promotion.id;
                        option.textContent = promotion.designation;

                        promotionSelect.appendChild(option);
                    });

                    // Fermer la notification de chargement
                    loadingToast.close();
                    showToast('Promotions chargées', 'success');
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des promotions:', error);
                    loadingToast.close();
                    showToast('Impossible de charger les promotions', 'error');
                });
        });

        // Fonction pour afficher des notifications toast
        function showToast(message, icon) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });

            Toast.fire({
                icon: icon,
                title: message
            });
        }

        // Soumission du formulaire
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Vérifier si les déclarations sont cochées
            if (!document.getElementById('declaration').checked || !document.getElementById('reglement').checked) {
                Swal.fire({
                    title: 'Attention',
                    text: 'Veuillez accepter les déclarations avant de soumettre votre demande.',
                    icon: 'warning',
                    confirmButtonText: 'Compris',
                    confirmButtonColor: '#003366'
                });
                return;
            }

            // Afficher un message de chargement
            Swal.fire({
                title: 'Traitement en cours',
                html: 'Veuillez patienter pendant que nous traitons votre demande...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Soumettre le formulaire via AJAX
            const formData = new FormData(this);

            fetch('controller/process_preinscription.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Notification de succès
                        Swal.fire({
                            title: 'Pré-inscription réussie !',
                            text: 'Votre demande a été enregistrée avec succès. Vous allez être redirigé vers la page de confirmation.',
                            icon: 'success',
                            confirmButtonText: 'Excellent !',
                            confirmButtonColor: '#003366'
                        }).then(() => {
                            // Rediriger vers la page de confirmation
                            window.location.href = 'confirmation_preinscription.php?ref=' + data.reference;
                        });
                    } else {
                        // Afficher les erreurs
                        Swal.fire({
                            title: 'Erreur',
                            text: data.message || 'Une erreur est survenue lors du traitement de votre demande.',
                            icon: 'error',
                            confirmButtonText: 'Réessayer',
                            confirmButtonColor: '#003366'
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    Swal.fire({
                        title: 'Erreur de connexion',
                        text: 'Une erreur est survenue lors de la communication avec le serveur. Veuillez vérifier votre connexion et réessayer.',
                        icon: 'error',
                        confirmButtonText: 'Réessayer',
                        confirmButtonColor: '#003366'
                    });
                });
        });

        // Notification de bienvenue au chargement de la page
        Swal.fire({
            title: 'Bienvenue sur le formulaire de pré-inscription',
            text: 'Veuillez remplir ce formulaire avec attention. Les champs marqués d\'un astérisque (*) sont obligatoires.',
            icon: 'info',
            confirmButtonText: 'Commencer',
            confirmButtonColor: '#003366'
        });
    });
</script>

<style>
    /* Styles pour le formulaire de pré-inscription */
    .form-container {
        background-color: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        margin: 30px 0;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-wrap: wrap;
    }

    .form-progress {
        display: flex;
        justify-content: space-between;
        padding: 25px 20px;
        background-color: var(--light-bg);
        border-bottom: 1px solid #e9ecef;
        width: 100%;
    }

    .progress-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        flex: 1;
    }

    .progress-step:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 20px;
        right: -50%;
        width: 100%;
        height: 3px;
        background-color: #dee2e6;
        z-index: 1;
    }

    .progress-step.active:not(:last-child)::after,
    .progress-step.completed:not(:last-child)::after {
        background-color: var(--secondary-color);
    }

    .step-number {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: #dee2e6;
        color: #6c757d;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-bottom: 10px;
        position: relative;
        z-index: 2;
        transition: var(--transition);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .progress-step.active .step-number,
    .progress-step.completed .step-number {
        background-color: var(--primary-color);
        color: var(--white);
        box-shadow: 0 3px 8px rgba(0, 51, 102, 0.3);
    }

    .step-label {
        font-size: 14px;
        color: var(--text-light);
        text-align: center;
        font-weight: 500;
    }

    .progress-step.active .step-label {
        color: var(--primary-color);
        font-weight: 600;
    }

    #preinscriptionForm {
        width: 70%;
        padding: 35px;
    }

    .form-help {
        width: 30%;
        padding: 30px 20px;
        background-color: var(--light-bg);
        border-left: 1px solid #e9ecef;
    }

    @media (max-width: 992px) {
        #preinscriptionForm,
        .form-help {
            width: 100%;
        }

        .form-help {
            border-left: none;
            border-top: 1px solid #e9ecef;
        }
    }

    .form-step {
        display: none;
    }

    .form-step.active {
        display: block;
    }

    .form-section {
        margin-bottom: 35px;
        background-color: var(--white);
        border-radius: var(--border-radius);
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
    }

    .form-section-title {
        color: var(--primary-color);
        margin-bottom: 25px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--secondary-color);
        font-weight: 600;
    }

    .form-subsection-title {
        color: var(--text-color);
        margin: 30px 0 15px;
        font-size: 18px;
        font-weight: 600;
        padding-left: 10px;
        border-left: 4px solid var(--secondary-color);
    }

    .form-navigation {
        display: flex;
        justify-content: space-between;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #e9ecef;
    }

    .required {
        color: #dc3545;
        margin-left: 3px;
    }

    .custom-file-label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .orientation-choices {
        background-color: var(--light-bg);
        padding: 20px;
        border-radius: var(--border-radius);
        margin-top: 15px;
        border: 1px solid #e9ecef;
    }

    .summary-sections {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }

    .summary-section {
        background-color: var(--light-bg);
        border-radius: var(--border-radius);
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }

    .summary-section h5 {
        color: var(--primary-color);
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #dee2e6;
        font-weight: 600;
    }

    .help-card {
        border: none;
        box-shadow: var(--box-shadow);
        border-radius: var(--border-radius);
        height: 100%;
    }

    .help-card .card-header {
        background-color: var(--primary-color);
        color: var(--white);
        border-bottom: none;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
        padding: 15px 20px;
    }

    .help-section {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px dashed #e9ecef;
    }

    .help-section:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .contact-list {
        list-style: none;
        padding-left: 0;
        margin-bottom: 0;
    }

    .contact-list li {
        padding: 5px 0;
        display: flex;
        align-items: center;
    }

    .contact-list li i {
        width: 20px;
        color: var(--secondary-color);
        margin-right: 10px;
    }
</style>

<?php
include "include/footer.php";
?>
