<?php
require_once dirname(__DIR__) . '/config/Connexion.php';

class GrilleAncienne
{
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    /**
     * Créer les tables pour les grilles anciennes si elles n'existent pas
     */
    public function createTablesIfNotExists()
    {
        $tables = [
            'grilles_anciennes_imports' => "
                CREATE TABLE IF NOT EXISTS grilles_anciennes_imports (
                    id SERIAL PRIMARY KEY,
                    annee_academique VARCHAR(50) NOT NULL,
                    session VARCHAR(100) NOT NULL,
                    semestre VARCHAR(50) NOT NULL,
                    promotion VARCHAR(255) NOT NULL,
                    section_id INT NULL,
                    fichier_origine VARCHAR(255) NOT NULL,
                    date_import TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    mapping_config JSON,
                    nombre_etudiants INT DEFAULT 0,
                    nombre_ues INT DEFAULT 0,
                    nombre_ecues INT DEFAULT 0,
                    FOREIGN KEY (section_id) REFERENCES section(idsection) ON DELETE SET NULL
                )",

            'grilles_anciennes_ue' => "
                CREATE TABLE IF NOT EXISTS grilles_anciennes_ue (
                    id SERIAL PRIMARY KEY,
                    import_id INT NOT NULL,
                    code_ue VARCHAR(50) NOT NULL,
                    designation_ue VARCHAR(255) NOT NULL,
                    credits DECIMAL(5,2) NOT NULL DEFAULT 0,
                    semestre VARCHAR(10) DEFAULT 'S1',
                    ordre_affichage INT DEFAULT 0,
                    FOREIGN KEY (import_id) REFERENCES grilles_anciennes_imports(id) ON DELETE CASCADE
                )",

            'grilles_anciennes_ecue' => "
                CREATE TABLE IF NOT EXISTS grilles_anciennes_ecue (
                    id SERIAL PRIMARY KEY,
                    ue_id INT NOT NULL,
                    code_ecue VARCHAR(50),
                    designation_ecue VARCHAR(255) NOT NULL,
                    coefficient DECIMAL(5,2) NOT NULL DEFAULT 1,
                    ordre_affichage INT DEFAULT 0,
                    FOREIGN KEY (ue_id) REFERENCES grilles_anciennes_ue(id) ON DELETE CASCADE
                )",

            'grilles_anciennes_etudiants' => "
                CREATE TABLE IF NOT EXISTS grilles_anciennes_etudiants (
                    id SERIAL PRIMARY KEY,
                    import_id INT NOT NULL,
                    matricule VARCHAR(50) NOT NULL,
                    noms VARCHAR(255) NOT NULL,
                    ordre_affichage INT DEFAULT 0,
                    FOREIGN KEY (import_id) REFERENCES grilles_anciennes_imports(id) ON DELETE CASCADE,
                    CONSTRAINT unique_import_matricule UNIQUE (import_id, matricule)
                )",

            'grilles_anciennes_notes' => "
                CREATE TABLE IF NOT EXISTS grilles_anciennes_notes (
                    id SERIAL PRIMARY KEY,
                    etudiant_id INT NOT NULL,
                    ecue_id INT NOT NULL,
                    note_cc DECIMAL(5,2) NULL,
                    note_examen DECIMAL(5,2) NULL,
                    note_finale DECIMAL(5,2) NULL,
                    FOREIGN KEY (etudiant_id) REFERENCES grilles_anciennes_etudiants(id) ON DELETE CASCADE,
                    FOREIGN KEY (ecue_id) REFERENCES grilles_anciennes_ecue(id) ON DELETE CASCADE,
                    CONSTRAINT unique_etudiant_ecue UNIQUE (etudiant_id, ecue_id)
                )",

            'grilles_anciennes_resultats' => "
                CREATE TABLE IF NOT EXISTS grilles_anciennes_resultats (
                    id SERIAL PRIMARY KEY,
                    etudiant_id INT NOT NULL,
                    ue_id INT NULL,
                    import_id INT NOT NULL,
                    moyenne DECIMAL(5,2) NULL,
                    credits_valides DECIMAL(5,2) DEFAULT 0,
                    credits_total DECIMAL(5,2) DEFAULT 0,
                    est_valide BOOLEAN DEFAULT FALSE,
                    mention VARCHAR(50) NULL,
                    type_resultat VARCHAR(20) NOT NULL CHECK (type_resultat IN ('ue', 'semestre', 'annuel')),
                    FOREIGN KEY (etudiant_id) REFERENCES grilles_anciennes_etudiants(id) ON DELETE CASCADE,
                    FOREIGN KEY (ue_id) REFERENCES grilles_anciennes_ue(id) ON DELETE CASCADE,
                    FOREIGN KEY (import_id) REFERENCES grilles_anciennes_imports(id) ON DELETE CASCADE,
                    CONSTRAINT unique_etudiant_ue_resultat UNIQUE (etudiant_id, ue_id, import_id, type_resultat)
                )"
        ];

        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_grilles_anciennes_imports_annee_session ON grilles_anciennes_imports (annee_academique, session)",
            "CREATE INDEX IF NOT EXISTS idx_grilles_anciennes_imports_promotion ON grilles_anciennes_imports (promotion)",
            "CREATE INDEX IF NOT EXISTS idx_grilles_anciennes_imports_section_id ON grilles_anciennes_imports (section_id)",
            "CREATE INDEX IF NOT EXISTS idx_grilles_anciennes_ue_import_ue ON grilles_anciennes_ue (import_id, code_ue)",
            "CREATE INDEX IF NOT EXISTS idx_grilles_anciennes_ecue_ue_ecue ON grilles_anciennes_ecue (ue_id, code_ecue)",
            "CREATE INDEX IF NOT EXISTS idx_grilles_anciennes_etudiants_import_matricule ON grilles_anciennes_etudiants (import_id, matricule)",
            "CREATE INDEX IF NOT EXISTS idx_grilles_anciennes_notes_etudiant ON grilles_anciennes_notes (etudiant_id)",
            "CREATE INDEX IF NOT EXISTS idx_grilles_anciennes_notes_ecue ON grilles_anciennes_notes (ecue_id)",
            "CREATE INDEX IF NOT EXISTS idx_grilles_anciennes_resultats_etudiant_type ON grilles_anciennes_resultats (etudiant_id, type_resultat)",
            "CREATE INDEX IF NOT EXISTS idx_grilles_anciennes_resultats_ue ON grilles_anciennes_resultats (ue_id)",
        ];

        foreach ($tables as $tableName => $sql) {
            try {
                $this->db->exec($sql);
            } catch (PDOException $e) {
                error_log("Erreur création table $tableName: " . $e->getMessage());
                throw new Exception("Erreur lors de la création de la table $tableName");
            }
        }

        foreach ($indexes as $sql) {
            try {
                $this->db->exec($sql);
            } catch (PDOException $e) {
                error_log("Erreur création index: " . $e->getMessage());
            }
        }

        // Mise à jour de la structure des tables existantes
        $this->updateTableStructure();
    }
    
    /**
     * Mettre à jour la structure des tables existantes
     */
    private function updateTableStructure()
    {
        try {
            // Vérifier si la colonne semestre existe dans grilles_anciennes_ue
            $stmt = $this->db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'grilles_anciennes_ue' AND column_name = 'semestre'");
            if ($stmt->rowCount() == 0) {
                // Ajouter la colonne semestre
                $this->db->exec("ALTER TABLE grilles_anciennes_ue ADD COLUMN semestre VARCHAR(10) DEFAULT 'S1'");
            }
        } catch (PDOException $e) {
            // Ignorer les erreurs de structure (table peut ne pas exister encore)
            error_log("Erreur mise à jour structure: " . $e->getMessage());
        }
    }

    /**
     * Créer un nouvel import de grille ancienne
     */
    public function createImport($data)
    {
        $sql = "INSERT INTO grilles_anciennes_imports 
                (annee_academique, session, semestre, promotion, section_id, fichier_origine, mapping_config) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['annee_academique'],
            $data['session'],
            $data['semestre'],
            $data['promotion'],
            $data['section_id'] ?? null,
            $data['fichier_origine'],
            json_encode($data['mapping_config'] ?? [])
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Insérer une UE pour une grille ancienne
     */
    public function insertUE($importId, $ueData)
    {
        $sql = "INSERT INTO grilles_anciennes_ue 
                (import_id, code_ue, designation_ue, credits, semestre, ordre_affichage) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $importId,
            $ueData['code_ue'],
            $ueData['designation_ue'],
            $ueData['credits'],
            $ueData['semestre'] ?? 'S1',
            $ueData['ordre_affichage'] ?? 0
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Insérer une ECUE pour une UE
     */
    public function insertECUE($ueId, $ecueData)
    {
        $sql = "INSERT INTO grilles_anciennes_ecue 
                (ue_id, code_ecue, designation_ecue, coefficient, ordre_affichage) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $ueId,
            $ecueData['code_ecue'] ?? '',
            $ecueData['designation_ecue'],
            $ecueData['coefficient'],
            $ecueData['ordre_affichage'] ?? 0
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Insérer un étudiant pour une grille ancienne
     */
    public function insertEtudiant($importId, $etudiantData)
    {
        $sql = "INSERT INTO grilles_anciennes_etudiants
                (import_id, matricule, noms, ordre_affichage)
                VALUES (?, ?, ?, ?)
                ON CONFLICT (import_id, matricule) DO UPDATE SET noms = EXCLUDED.noms
                RETURNING id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $importId,
            $etudiantData['matricule'],
            $etudiantData['noms'],
            $etudiantData['ordre_affichage'] ?? 0
        ]);

        return $stmt->fetchColumn();
    }

    /**
     * Insérer une note pour un étudiant et une ECUE
     */
    public function insertNote($etudiantId, $ecueId, $noteData)
    {
        $sql = "INSERT INTO grilles_anciennes_notes
                (etudiant_id, ecue_id, note_cc, note_examen, note_finale)
                VALUES (?, ?, ?, ?, ?)
                ON CONFLICT (etudiant_id, ecue_id) DO UPDATE SET
                note_cc = EXCLUDED.note_cc,
                note_examen = EXCLUDED.note_examen,
                note_finale = EXCLUDED.note_finale
                RETURNING id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $etudiantId,
            $ecueId,
            $noteData['note_cc'],
            $noteData['note_examen'],
            $noteData['note_finale']
        ]);

        return $stmt->fetchColumn() ?: true;
    }

    /**
     * Insérer un résultat (moyenne UE, semestre ou annuel)
     */
    public function insertResultat($etudiantId, $importId, $resultatData)
    {
        $sql = "INSERT INTO grilles_anciennes_resultats
                (etudiant_id, ue_id, import_id, moyenne, credits_valides, credits_total, est_valide, mention, type_resultat)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (etudiant_id, ue_id, import_id, type_resultat) DO UPDATE SET
                moyenne = EXCLUDED.moyenne,
                credits_valides = EXCLUDED.credits_valides,
                credits_total = EXCLUDED.credits_total,
                est_valide = EXCLUDED.est_valide,
                mention = EXCLUDED.mention
                RETURNING id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $etudiantId,
            $resultatData['ue_id'] ?? null,
            $importId,
            $resultatData['moyenne'],
            $resultatData['credits_valides'] ?? 0,
            $resultatData['credits_total'] ?? 0,
            $resultatData['est_valide'] ?? false,
            $resultatData['mention'] ?? null,
            $resultatData['type_resultat']
        ]);

        return $stmt->fetchColumn() ?: true;
    }

    /**
     * Récupérer tous les imports avec les informations de section
     */
    public function getAllImports()
    {
        $sql = "SELECT gai.*, s.\"designationSection\" 
                FROM grilles_anciennes_imports gai
                LEFT JOIN section s ON gai.section_id = s.idsection
                ORDER BY gai.date_import DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupérer les imports des sections où l'utilisateur est responsable
     */
    public function getImportsByUserSections($userId)
    {
        $sql = "SELECT DISTINCT gai.*, s.\"designationSection\" 
                FROM grilles_anciennes_imports gai
                LEFT JOIN section s ON gai.section_id = s.idsection
                INNER JOIN responsable_section rs ON s.idsection = rs.section_idsection
                WHERE rs.\"idUser\" = ?
                ORDER BY gai.date_import DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mettre à jour la section d'un import
     */
    public function updateImportSection($importId, $sectionId)
    {
        $sql = "UPDATE grilles_anciennes_imports SET section_id = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$sectionId, $importId]);
    }

    /**
     * Récupérer un import par ID
     */
    public function getImportById($importId)
    {
        $sql = "SELECT * FROM grilles_anciennes_imports WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$importId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer un import avec les informations de la section
     */
    public function getImportWithSection($importId)
    {
        $sql = "SELECT gi.*, 
                       s.\"designationSection\", s.telephone as section_telephone, 
                       s.email as section_email, s.adresse as section_adresse,
                       s.boite_postale as section_boite_postale, s.site_web as section_site_web,
                       rs.noms as chef_section_nom, rs.fonction as chef_section_fonction,
                       rs.telephone as chef_section_telephone, rs.email as chef_section_email,
                       rs.signature as chef_section_signature
                FROM grilles_anciennes_imports gi
                LEFT JOIN section s ON gi.section_id = s.idsection
                LEFT JOIN responsable_section rs ON s.idsection = rs.section_idsection 
                    AND rs.est_chef = 1 
                    AND (rs.date_fin IS NULL OR rs.date_fin = '0000-00-00' OR rs.date_fin > CURDATE())
                WHERE gi.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$importId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtenir les sections de l'année académique en cours
     */
    public function getSectionsAnneeEnCours()
    {
        $sql = "SELECT s.idsection, s.\"designationSection\" 
                FROM section s
                JOIN annee_acad a ON s.\"idAnnee\" = a.idannee_acad
                WHERE a.est_active = 1
                ORDER BY s.designationSection";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer les UE d'un import
     */
    public function getUEsByImport($importId)
    {
        $sql = "SELECT * FROM grilles_anciennes_ue WHERE import_id = ? ORDER BY ordre_affichage, code_ue";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$importId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer les ECUE d'une UE
     */
    public function getECUEsByUE($ueId)
    {
        $sql = "SELECT * FROM grilles_anciennes_ecue WHERE ue_id = ? ORDER BY ordre_affichage, designation_ecue";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$ueId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer les étudiants d'un import
     */
    public function getEtudiantsByImport($importId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT e.*, 
                       r.moyenne as moyenne_generale
                FROM grilles_anciennes_etudiants e
                LEFT JOIN grilles_anciennes_resultats r ON e.id = r.etudiant_id AND r.type_resultat = 'annuel'
                WHERE e.import_id = ?
                ORDER BY e.ordre_affichage, e.noms
            ");
            $stmt->execute([$importId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des étudiants: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer les notes d'un étudiant
     */
    public function getNotesByEtudiant($etudiantId)
    {
        $sql = "SELECT n.*, e.designation_ecue, e.code_ecue, e.coefficient,
                       u.designation_ue, u.code_ue, u.credits
                FROM grilles_anciennes_notes n
                JOIN grilles_anciennes_ecue e ON n.ecue_id = e.id
                JOIN grilles_anciennes_ue u ON e.ue_id = u.id
                WHERE n.etudiant_id = ?
                ORDER BY u.ordre_affichage, e.ordre_affichage";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$etudiantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer les résultats d'un étudiant
     */
    public function getResultatsByEtudiant($etudiantId, $typeResultat = null)
    {
        $sql = "SELECT r.*, u.designation_ue, u.code_ue 
                FROM grilles_anciennes_resultats r
                LEFT JOIN grilles_anciennes_ue u ON r.ue_id = u.id
                WHERE r.etudiant_id = ?";
        
        $params = [$etudiantId];
        
        if ($typeResultat) {
            $sql .= " AND r.type_resultat = ?";
            $params[] = $typeResultat;
        }
        
        $sql .= " ORDER BY r.type_resultat, u.ordre_affichage";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculer et sauvegarder les moyennes pour un import
     */
    public function calculerMoyennes($importId)
    {
        // 1. Calculer les moyennes UE
        $ues = $this->getUEsByImport($importId);
        $etudiants = $this->getEtudiantsByImport($importId);
        
        foreach ($etudiants as $etudiant) {
            $etudiantId = $etudiant['id'];
            $creditsValidesTotal = 0;
            $creditsTotalGlobal = 0;
            $totalPoints = 0;
            
            foreach ($ues as $ue) {
                $ueId = $ue['id'];
                $ecues = $this->getECUEsByUE($ueId);
                
                $totalPointsUE = 0;
                $totalCoeffUE = 0;
                $notesCompletes = true;
                
                foreach ($ecues as $ecue) {
                    $ecueId = $ecue['id'];
                    
                    // Récupérer la note de cet étudiant pour cette ECUE
                    $stmt = $this->db->prepare("SELECT * FROM grilles_anciennes_notes WHERE etudiant_id = ? AND ecue_id = ?");
                    $stmt->execute([$etudiantId, $ecueId]);
                    $note = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($note && $note['note_finale'] !== null) {
                        $totalPointsUE += $note['note_finale'] * $ecue['coefficient'];
                        $totalCoeffUE += $ecue['coefficient'];
                    } else {
                        $notesCompletes = false;
                    }
                }
                
                // Calculer la moyenne UE
                if ($totalCoeffUE > 0 && $notesCompletes) {
                    $moyenneUE = $totalPointsUE / $totalCoeffUE;
                    $estValide = $moyenneUE >= 10;
                    
                    // Sauvegarder le résultat UE
                    $this->insertResultat($etudiantId, $importId, [
                        'ue_id' => $ueId,
                        'moyenne' => $moyenneUE,
                        'credits_valides' => $estValide ? $ue['credits'] : 0,
                        'credits_total' => $ue['credits'],
                        'est_valide' => $estValide,
                        'type_resultat' => 'ue'
                    ]);
                    
                    // Accumuler pour la moyenne générale
                    $totalPoints += $moyenneUE * $ue['credits'];
                    $creditsTotalGlobal += $ue['credits'];
                    if ($estValide) {
                        $creditsValidesTotal += $ue['credits'];
                    }
                }
            }
            
            // Calculer la moyenne générale
            if ($creditsTotalGlobal > 0) {
                $moyenneGenerale = $totalPoints / $creditsTotalGlobal;
                
                // Déterminer la mention
                $mention = '';
                if ($moyenneGenerale >= 16) {
                    $mention = 'Très Bien';
                } elseif ($moyenneGenerale >= 14) {
                    $mention = 'Bien';
                } elseif ($moyenneGenerale >= 12) {
                    $mention = 'Assez Bien';
                } elseif ($moyenneGenerale >= 10) {
                    $mention = 'Satisfaction';
                }
                
                // Sauvegarder le résultat général
                $this->insertResultat($etudiantId, $importId, [
                    'moyenne' => $moyenneGenerale,
                    'credits_valides' => $creditsValidesTotal,
                    'credits_total' => $creditsTotalGlobal,
                    'est_valide' => $moyenneGenerale >= 10,
                    'mention' => $mention,
                    'type_resultat' => 'annuel'
                ]);
            }
        }
        
        // Mettre à jour les statistiques de l'import
        $this->updateImportStats($importId);
    }

    /**
     * Mettre à jour les statistiques d'un import
     */
    private function updateImportStats($importId)
    {
        $stats = [
            'nombre_etudiants' => 0,
            'nombre_ues' => 0,
            'nombre_ecues' => 0
        ];
        
        // Compter les étudiants
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM grilles_anciennes_etudiants WHERE import_id = ?");
        $stmt->execute([$importId]);
        $stats['nombre_etudiants'] = $stmt->fetchColumn();
        
        // Compter les UE
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM grilles_anciennes_ue WHERE import_id = ?");
        $stmt->execute([$importId]);
        $stats['nombre_ues'] = $stmt->fetchColumn();
        
        // Compter les ECUE
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM grilles_anciennes_ecue e 
                                   JOIN grilles_anciennes_ue u ON e.ue_id = u.id 
                                   WHERE u.import_id = ?");
        $stmt->execute([$importId]);
        $stats['nombre_ecues'] = $stmt->fetchColumn();
        
        // Mettre à jour
        $sql = "UPDATE grilles_anciennes_imports 
                SET nombre_etudiants = ?, nombre_ues = ?, nombre_ecues = ? 
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $stats['nombre_etudiants'],
            $stats['nombre_ues'],
            $stats['nombre_ecues'],
            $importId
        ]);
    }


    /**
     * Récupérer les ECUE d'un import
     */
    public function getECUEsByImport($importId)
    {
        $sql = "SELECT e.*, u.code_ue, u.designation_ue 
                FROM grilles_anciennes_ecue e
                JOIN grilles_anciennes_ue u ON e.ue_id = u.id
                WHERE u.import_id = ? 
                ORDER BY u.ordre_affichage, e.ordre_affichage";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$importId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupérer les notes d'un import
     */
    public function getNotesByImport($importId)
    {
        $sql = "SELECT n.*, et.matricule, et.noms, ec.code_ecue, ec.designation_ecue
                FROM grilles_anciennes_notes n
                JOIN grilles_anciennes_etudiants et ON n.etudiant_id = et.id
                JOIN grilles_anciennes_ecue ec ON n.ecue_id = ec.id
                JOIN grilles_anciennes_ue u ON ec.ue_id = u.id
                WHERE et.import_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$importId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupérer les résultats d'un import
     */
    public function getResultatsByImport($importId)
    {
        $sql = "SELECT r.*, et.matricule, et.noms
                FROM grilles_anciennes_resultats r
                JOIN grilles_anciennes_etudiants et ON r.etudiant_id = et.id
                WHERE et.import_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$importId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer tous les résultats importés pour un étudiant
     */
    public function getResultatsEtudiantImportes($matricule)
    {
        $resultats = [];
        
        try {
            // Récupérer tous les imports où l'étudiant apparaît
            $query = "SELECT DISTINCT i.id, i.annee_academique, i.session, i.semestre, 
                             i.promotion, i.date_import, i.fichier_origine
                     FROM grilles_anciennes_imports i
                     INNER JOIN grilles_anciennes_etudiants e ON e.import_id = i.id
                     WHERE e.matricule = :matricule
                     ORDER BY i.date_import DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
            $stmt->execute();
            $imports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($imports as $import) {
                // Récupérer les résultats de cet import pour cet étudiant
                $queryResultats = "SELECT u.code_ue, u.designation_ue, u.credits,
                                         r.moyenne, r.credits_valides, r.credits_total,
                                         r.est_valide, r.mention, r.type_resultat
                                  FROM grilles_anciennes_resultats r
                                  INNER JOIN grilles_anciennes_etudiants e ON e.id = r.etudiant_id
                                  INNER JOIN grilles_anciennes_ue u ON u.id = r.ue_id
                                  WHERE e.matricule = :matricule 
                                  AND r.import_id = :import_id
                                  ORDER BY u.ordre_affichage";
                
                $stmtResultats = $this->db->prepare($queryResultats);
                $stmtResultats->bindParam(':matricule', $matricule, PDO::PARAM_STR);
                $stmtResultats->bindParam(':import_id', $import['id'], PDO::PARAM_INT);
                $stmtResultats->execute();
                $ues = $stmtResultats->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($ues)) {
                    $resultats[] = [
                        'import_id' => $import['id'],
                        'annee_academique' => $import['annee_academique'],
                        'session' => $import['session'],
                        'semestre' => $import['semestre'],
                        'promotion' => $import['promotion'],
                        'date_import' => $import['date_import'],
                        'fichier_origine' => $import['fichier_origine'],
                        'ues' => $ues
                    ];
                }
            }
            
        } catch (Exception $e) {
            error_log("Erreur getResultatsEtudiantImportes: " . $e->getMessage());
        }
        
        return $resultats;
    }

    /**
     * Supprimer un import et toutes ses données
     */
    public function deleteImport($importId)
    {
        $sql = "DELETE FROM grilles_anciennes_imports WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$importId]);
    }

    /**
     * Rechercher des imports par critères
     */
    public function searchImports($criteria = [])
    {
        $sql = "SELECT * FROM grilles_anciennes_imports WHERE 1=1";
        $params = [];
        
        if (!empty($criteria['annee_academique'])) {
            $sql .= " AND annee_academique LIKE ?";
            $params[] = "%{$criteria['annee_academique']}%";
        }
        
        if (!empty($criteria['session'])) {
            $sql .= " AND session LIKE ?";
            $params[] = "%{$criteria['session']}%";
        }
        
        if (!empty($criteria['promotion'])) {
            $sql .= " AND promotion LIKE ?";
            $params[] = "%{$criteria['promotion']}%";
        }
        
        $sql .= " ORDER BY date_import DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtenir un étudiant par matricule dans un import
     */
    public function getEtudiantByMatricule($importId, $matricule)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT e.*, e.noms as nom
                FROM grilles_anciennes_etudiants e
                WHERE e.import_id = ? AND e.matricule = ?
            ");
            $stmt->execute([$importId, $matricule]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération de l'étudiant: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtenir les UEs avec leurs notes pour un étudiant
     */
    public function getUEsWithNotes($importId, $matricule)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT ue.id, ue.code_ue, ue.designation_ue as nom_ue, ue.credits,
                       AVG(n.note_finale) as note
                FROM grilles_anciennes_etudiants e
                JOIN grilles_anciennes_notes n ON e.id = n.etudiant_id
                JOIN grilles_anciennes_ecue ec ON n.ecue_id = ec.id
                JOIN grilles_anciennes_ue ue ON ec.ue_id = ue.id
                WHERE e.import_id = ? AND e.matricule = ?
                GROUP BY ue.id
                ORDER BY ue.ordre_affichage
            ");
            $stmt->execute([$importId, $matricule]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des UEs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtenir les UEs avec leurs ECUEs pour un étudiant
     */
    public function getUEsAvecECUEs($importId, $matricule)
    {
        try {
            // Récupérer TOUTES les UEs de l'import (même sans notes pour cet étudiant)
            $stmt = $this->db->prepare("
                SELECT DISTINCT ue.id, ue.code_ue, ue.designation_ue as nom_ue, ue.credits as credits_ue, ue.semestre
                FROM grilles_anciennes_ue ue
                WHERE ue.import_id = ?
                ORDER BY ue.ordre_affichage
            ");
            $stmt->execute([$importId]);
            $ues = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Récupérer l'ID de l'étudiant
            $stmtEtudiant = $this->db->prepare("
                SELECT id FROM grilles_anciennes_etudiants 
                WHERE import_id = ? AND matricule = ?
            ");
            $stmtEtudiant->execute([$importId, $matricule]);
            $etudiant = $stmtEtudiant->fetch(PDO::FETCH_ASSOC);
            
            if (!$etudiant) {
                return [];
            }
            
            $etudiantId = $etudiant['id'];

            // Pour chaque UE, récupérer TOUTES ses ECUEs (même sans notes)
            foreach ($ues as &$ue) {
                $stmt = $this->db->prepare("
                    SELECT ec.id, ec.code_ecue, ec.designation_ecue as nom_ecue, ec.coefficient as credits,
                           n.note_cc as cc, n.note_examen as ex, n.note_finale
                    FROM grilles_anciennes_ecue ec
                    LEFT JOIN grilles_anciennes_notes n ON ec.id = n.ecue_id AND n.etudiant_id = ?
                    WHERE ec.ue_id = ?
                    ORDER BY ec.ordre_affichage
                ");
                $stmt->execute([$etudiantId, $ue['id']]);
                $ue['ecues'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return $ues;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des UEs avec ECUEs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculer la moyenne générale d'un étudiant
     */
    public function calculerMoyenneGenerale($importId, $matricule)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT r.moyenne as moyenne_generale
                FROM grilles_anciennes_etudiants e
                LEFT JOIN grilles_anciennes_resultats r ON e.id = r.etudiant_id AND r.type_resultat = 'annuel'
                WHERE e.import_id = ? AND e.matricule = ?
            ");
            $stmt->execute([$importId, $matricule]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['moyenne_generale'] : null;
        } catch (PDOException $e) {
            error_log("Erreur lors du calcul de la moyenne générale: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculer les crédits obtenus par un étudiant
     */
    public function calculerCreditsObtenus($importId, $matricule)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT SUM(ue.credits) as credits_obtenus
                FROM (
                    SELECT ue.id, ue.credits, AVG(n.note_finale) as moyenne_ue
                    FROM grilles_anciennes_etudiants e
                    JOIN grilles_anciennes_notes n ON e.id = n.etudiant_id
                    JOIN grilles_anciennes_ecue ec ON n.ecue_id = ec.id
                    JOIN grilles_anciennes_ue ue ON ec.ue_id = ue.id
                    WHERE e.import_id = ? AND e.matricule = ? AND n.note_finale IS NOT NULL
                    GROUP BY ue.id
                    HAVING moyenne_ue >= 10
                ) as ues_validees
            ");
            $stmt->execute([$importId, $matricule]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? intval($result['credits_obtenus']) : 0;
        } catch (PDOException $e) {
            error_log("Erreur lors du calcul des crédits obtenus: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtenir le total des crédits d'un import
     */
    public function getCreditsTotal($importId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT SUM(credits) as credits_total
                FROM grilles_anciennes_ue
                WHERE import_id = ?
            ");
            $stmt->execute([$importId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? intval($result['credits_total']) : 0;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération du total des crédits: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtenir tous les étudiants avec leurs moyennes pour le palmarès
     */
    public function getEtudiantsAvecMoyennes($importId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT e.matricule, e.noms as nom, 
                       r.moyenne as moyenne_generale
                FROM grilles_anciennes_etudiants e
                LEFT JOIN grilles_anciennes_resultats r ON e.id = r.etudiant_id AND r.type_resultat = 'annuel'
                WHERE e.import_id = ?
                ORDER BY r.moyenne DESC
            ");
            $stmt->execute([$importId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des étudiants avec moyennes: " . $e->getMessage());
            return [];
        }
    }
}
