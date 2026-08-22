-- Tables for daily agent presence and schedule configuration

-- Daily presence table
CREATE TABLE IF NOT EXISTS presence_agent_daily (
  idpresence_agent_daily INT AUTO_INCREMENT PRIMARY KEY,
  Agent_idAgent INT NOT NULL,
  date_presence DATE NOT NULL,
  heure_arrivee DATETIME NULL,
  heure_depart DATETIME NULL,
  methode_enregistrement VARCHAR(20) DEFAULT 'manuel',
  commentaire VARCHAR(255) NULL,
  encode_par INT NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_agent_date (Agent_idAgent, date_presence)
);

-- Schedule/config table
CREATE TABLE IF NOT EXISTS presence_horaire_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  jours_travail VARCHAR(50) NOT NULL DEFAULT '1,2,3,4,5', -- 1=Lundi .. 7=Dimanche
  heure_debut TIME NOT NULL DEFAULT '08:00:00',
  heure_fin TIME NOT NULL DEFAULT '17:00:00',
  tolerance_minutes INT NOT NULL DEFAULT 15,
  pause_debut TIME NULL,
  pause_fin TIME NULL,
  updated_by INT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

