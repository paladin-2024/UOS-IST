CREATE TABLE IF NOT EXISTS `annee_acad` (
  `idannee_acad` INT NOT NULL AUTO_INCREMENT,
  `designation` VARCHAR(145) NULL,
  `dateCreation` DATETIME NULL,
  PRIMARY KEY (`idannee_acad`)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `section` (
  `idsection` INT NOT NULL AUTO_INCREMENT,
  `designationSection` VARCHAR(245) NULL,
  `dateCreation` DATETIME NULL,
  idAnnee INT,
  PRIMARY KEY (`idsection`)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `departement` (
  `iddepartement` INT NOT NULL AUTO_INCREMENT,
  `designationDepartement` VARCHAR(245) NULL,
  `dateCreation` DATETIME NULL,
  `section_idsection` INT NOT NULL,
  PRIMARY KEY (`iddepartement`),
  INDEX `fk_departement_section_idx` (`section_idsection` ASC)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `promotion` (
  `idpromotion` INT NOT NULL AUTO_INCREMENT,
  `designationPromotion` VARCHAR(245) NULL,
  `dateCreation` DATETIME NULL,
  `departement_iddepartement` INT NOT NULL,
  `annee_acad_idannee_acad` INT NOT NULL,
  PRIMARY KEY (`idpromotion`),
  INDEX `fk_promotion_departement1_idx` (`departement_iddepartement` ASC),
  INDEX `fk_promotion_annee_acad1_idx` (`annee_acad_idannee_acad` ASC)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `etudiant` (
  `idetudiant` INT NOT NULL AUTO_INCREMENT,
  `matricule` VARCHAR(245) NULL,
  `noms` VARCHAR(245) NULL,
  `lieuNaissance` VARCHAR(145) NULL,
  `dateNaissance` DATE NULL,
  `adressemail` VARCHAR(45) NULL,
  `telephone` VARCHAR(45) NULL,
  `idUtilisateur` INT NULL,
  `dateEnregistrement` DATETIME NULL,
  `annee_acad_idannee_acad` INT NOT NULL,
  `promotion_idpromotion` INT NOT NULL,
  PRIMARY KEY (`idetudiant`),
  INDEX `fk_etudiant_annee_acad1_idx` (`annee_acad_idannee_acad` ASC),
  INDEX `fk_etudiant_promotion1_idx` (`promotion_idpromotion` ASC)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `unite_recherche` (
  `idunite_recherche` INT NOT NULL AUTO_INCREMENT,
  `designation_UR` VARCHAR(245) NULL,
  `description` TEXT NULL,
  `idUser` INT NULL,
  `dateCreation` DATETIME NULL,
  `departement_iddepartement` INT NOT NULL,
  PRIMARY KEY (`idunite_recherche`),
  INDEX `fk_unite_recherche_departement1_idx` (`departement_iddepartement` ASC)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `enseignant` (
  `idenseignant` INT NOT NULL AUTO_INCREMENT,
  `nomEnseignant` VARCHAR(245) NULL,
  `grade` VARCHAR(145) NULL,
  `idAgent` INT NULL,
  `idUtilisateur` INT NULL,
  `unite_recherche_idunite_recherche` INT NULL,
  PRIMARY KEY (`idenseignant`),
  INDEX `fk_enseignant_unite_recherche1_idx` (`unite_recherche_idunite_recherche` ASC)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `session` (
  `idsession` INT NOT NULL AUTO_INCREMENT,
  `designSession` VARCHAR(45) NULL,
  `dateCreation` DATETIME NULL,
  PRIMARY KEY (`idsession`)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `semestre` (
  `idsemestre` INT NOT NULL AUTO_INCREMENT,
  `numeroSemestre` VARCHAR(45) NULL,
  `dateEnregistrement` DATETIME NULL,
  `promotion_idpromotion` INT NOT NULL,
  `session_idsession` INT NOT NULL,
  PRIMARY KEY (`idsemestre`),
  INDEX `fk_semestre_promotion1_idx` (`promotion_idpromotion` ASC),
  INDEX `fk_semestre_session1_idx` (`session_idsession` ASC)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `responsable_departement` (
  `idresponsable_departement` INT NOT NULL,
  `noms` VARCHAR(145) NULL,
  `fonction` VARCHAR(145) NULL,
  `signature` VARCHAR(145) NULL,
  `idUser` INT NULL,
  `dateEnregistrement` DATETIME NULL,
  `departement_iddepartement` INT NOT NULL,
  `annee_acad_idannee_acad` INT NOT NULL,
  PRIMARY KEY (`idresponsable_departement`),
  INDEX `fk_responsable_departement_departement1_idx` (`departement_iddepartement` ASC),
  INDEX `fk_responsable_departement_annee_acad1_idx` (`annee_acad_idannee_acad` ASC)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `responsable_section` (
  `idresponsable_section` INT NOT NULL AUTO_INCREMENT,
  `noms` VARCHAR(245) NULL,
  `fonction` VARCHAR(145) NULL,
  `signature` VARCHAR(145) NULL,
  `idUser` INT NULL,
  `section_idsection` INT NOT NULL,
  `annee_acad_idannee_acad` INT NOT NULL,
  PRIMARY KEY (`idresponsable_section`),
  INDEX `fk_responsable_section_section1_idx` (`section_idsection` ASC),
  INDEX `fk_responsable_section_annee_acad1_idx` (`annee_acad_idannee_acad` ASC)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `user_section` (
  `iduser_section` INT NOT NULL AUTO_INCREMENT,
  `idSection` INT NULL,
  `idUser` INT NULL,
  PRIMARY KEY (`iduser_section`)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `user_departement` (
  `iduser_departement` INT NOT NULL AUTO_INCREMENT,
  `idDepartement` INT NULL,
  `idUser` INT NULL,
  PRIMARY KEY (`iduser_departement`)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `user_etudiant` (
  `iduser_etudiant` INT NOT NULL AUTO_INCREMENT,
  `matriculeEtudiant` VARCHAR(145) NULL,
  `idUser` INT NULL,
  `anneeAcademique` VARCHAR(45) NULL,
  PRIMARY KEY (`iduser_etudiant`)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `sujets` (
  `idsujets` INT NOT NULL AUTO_INCREMENT,
  `intitule` TEXT NULL,
  `idDirecteur` INT NULL,
  `idEncadreur` INT NULL,
  `idEtudiant` INT NULL,
  `matriculeEtudiant` VARCHAR(145) NULL,
  `idUser` INT NULL,
  `dateEnregistrement` DATETIME NULL,
  `etatSujet` VARCHAR(145) NULL,
  `enseignant_idenseignant` INT NOT NULL,
  `etudiant_idetudiant` INT NOT NULL,
  `annee_acad_idannee_acad` INT NOT NULL,
  PRIMARY KEY (`idsujets`),
  INDEX `fk_sujets_enseignant1_idx` (`enseignant_idenseignant` ASC),
  INDEX `fk_sujets_etudiant1_idx` (`etudiant_idetudiant` ASC),
  INDEX `fk_sujets_annee_acad1_idx` (`annee_acad_idannee_acad` ASC)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `taches` (
  `idtaches` INT NOT NULL AUTO_INCREMENT,
  `dateTache` DATE NULL,
  `description` TEXT NULL,
  `observationDirecteur` TEXT NULL,
  `observationEncadreur` TEXT NULL,
  `fichierTache` VARCHAR(145) NULL,
  `validation` VARCHAR(145) NULL,
  `sujets_idsujets` INT NOT NULL,
  `idUser` INT NULL,
  PRIMARY KEY (`idtaches`),
  INDEX `fk_taches_sujets1_idx` (`sujets_idsujets` ASC)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `depot_memoire` (
  `iddepot_memoire` INT NOT NULL AUTO_INCREMENT,
  `dateDepot` DATE NULL,
  `fichier` VARCHAR(145) NULL,
  `observation` TEXT NULL,
  `idUser` INT NULL,
  `sujets_idsujets` INT NOT NULL,
  PRIMARY KEY (`iddepot_memoire`),
  INDEX `fk_depot_memoire_sujets1_idx` (`sujets_idsujets` ASC)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `depot_rapport` (
  `iddepot_rapport` INT NOT NULL,
  `dateDepot` DATE NULL,
  `titre` TEXT NULL,
  `lieu_stage` VARCHAR(245) NULL,
  `date_debut` DATE NULL,
  `date_fin` DATE NULL,
  `observation` TEXT NULL,
  `encadreur` INT NOT NULL,
  `etudiant_idetudiant` INT NOT NULL,
  `idUser` INT NULL,
  PRIMARY KEY (`iddepot_rapport`),
  INDEX `fk_depot_rapport_enseignant1_idx` (`encadreur` ASC),
  INDEX `fk_depot_rapport_etudiant1_idx` (`etudiant_idetudiant` ASC)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `UE` (
  `idUE` INT NOT NULL AUTO_INCREMENT,
  `codeUE` VARCHAR(45) NULL,
  `designationUE` VARCHAR(245) NULL,
  `CMI` FLOAT NULL,
  `TD` FLOAT NULL,
  `TP` FLOAT NULL,
  `semestre_idsemestre` INT NOT NULL,
  PRIMARY KEY (`idUE`),
  INDEX `fk_UE_semestre1_idx` (`semestre_idsemestre` ASC)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `ECUE` (
  `idECUE` INT NOT NULL AUTO_INCREMENT,
  `designationECUE` VARCHAR(245) NULL,
  `CMI` FLOAT NULL,
  `TD` FLOAT NULL,
  `TP` FLOAT NULL,
  `UE_idUE` INT NOT NULL,
  PRIMARY KEY (`idECUE`),
  INDEX `fk_ECUE_UE1_idx` (`UE_idUE` ASC)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `enseignant_ecue` (
  `idenseignant_ecue` INT NOT NULL AUTO_INCREMENT,
  `poste` VARCHAR(145) NULL,
  `idEnseignant` INT NULL,
  `idECUE` INT NULL,
  `anneeAcad` VARCHAR(45) NULL,
  PRIMARY KEY (`idenseignant_ecue`)
) ENGINE = MyISAM;

CREATE TABLE IF NOT EXISTS `points` (
  `idpoints` INT NOT NULL AUTO_INCREMENT,
  `CC` FLOAT NULL,
  `EX` FLOAT NULL,
  `ECUE_idECUE` INT NOT NULL,
  `session_idsession` INT NOT NULL,
  PRIMARY KEY (`idpoints`),
  INDEX `fk_points_ECUE1_idx` (`ECUE_idECUE` ASC),
  INDEX `fk_points_session1_idx` (`session_idsession` ASC)
) ENGINE = MyISAM;