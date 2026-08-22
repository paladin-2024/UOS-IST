<?php
header('Content-Type: application/json');

try {
    // Lire les données JSON envoyées
    $input = file_get_contents('php://input');
    $donnees = json_decode($input, true);
    
    if (!$donnees) {
        throw new Exception('Données non reçues ou format JSON invalide.');
    }
    
    // Valider les données requises
    if (!isset($donnees['metadonnees']) || !isset($donnees['mapping']) || !isset($donnees['donneesBrutes'])) {
        throw new Exception('Données incomplètes reçues.');
    }
    
    $metadonnees = $donnees['metadonnees'];
    $mapping = $donnees['mapping'];
    $mappingNotes = $donnees['mappingNotes'] ?? [];
    $donneesBrutes = $donnees['donneesBrutes'];
    
    // Valider le mapping minimal
    if (!isset($mapping['matricule']) || !isset($mapping['nom'])) {
        throw new Exception('Le mapping du matricule et du nom est obligatoire.');
    }
    
    require_once '../config/Connexion.php';
    
    $pdo = Connexion::getConnexion();
    $pdo->beginTransaction();
    
    try {
        // 1. Créer les tables si elles n'existent pas
        creerTablesGrillesAnciennes($pdo);
        
        // 2. Insérer l'enregistrement principal de l'import
        $sqlImport = "INSERT INTO grilles_anciennes_imports 
                      (annee_academique, session, semestre, promotion, etablissement, 
                       notes_supplementaires, date_import, nombre_etudiants, statut) 
                      VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 'en_cours')";
        
        $stmtImport = $pdo->prepare($sqlImport);
        $stmtImport->execute([
            $metadonnees['annee'],
            $metadonnees['session'],
            $metadonnees['semestre'],
            $metadonnees['promotion'],
            $metadonnees['etablissement'],
            $metadonnees['notes'],
            count($donneesBrutes)
        ]);
        
        $importId = $pdo->lastInsertId();
        
        // 3. Insérer les étudiants
        $sqlEtudiant = "INSERT INTO grilles_anciennes_etudiants 
                        (import_id, matricule, nom, prenoms, date_naissance) 
                        VALUES (?, ?, ?, ?, ?)";
        $stmtEtudiant = $pdo->prepare($sqlEtudiant);
        
        $etudiantsMap = [];
        $compteurEtudiants = 0;
        
        foreach ($donneesBrutes as $ligne) {
            $matricule = trim($ligne[$mapping['matricule']] ?? '');
            $nom = trim($ligne[$mapping['nom']] ?? '');
            
            if (empty($matricule) || empty($nom)) {
                continue; // Ignorer les lignes avec matricule ou nom vide
            }
            
            $prenoms = trim($ligne[$mapping['prenoms']] ?? '');
            $dateNaissance = null;
            
            // Traitement de la date de naissance si mappée
            if (isset($mapping['datenaissance']) && !empty($ligne[$mapping['datenaissance']])) {
                $dateStr = trim($ligne[$mapping['datenaissance']]);
                $dateNaissance = convertirDate($dateStr);
            }
            
            $stmtEtudiant->execute([
                $importId,
                $matricule,
                $nom,
                $prenoms,
                $dateNaissance
            ]);
            
            $etudiantId = $pdo->lastInsertId();
            $etudiantsMap[$matricule] = $etudiantId;
            $compteurEtudiants++;
        }
        
        // 4. Créer les UEs/ECUEs et insérer les notes
        $uesCreees = [];
        $ecuesCreees = [];
        $compteurNotes = 0;
        
        foreach ($mappingNotes as $noteMapping) {
            $colonneIndex = $noteMapping['colonne'];
            $nomMatiere = trim($noteMapping['nom']);
            $typeNote = $noteMapping['type'];
            
            if (empty($nomMatiere)) {
                continue;
            }
            
            // Créer l'UE si elle n'existe pas
            $codeUE = genererCodeUE($nomMatiere);
            if (!isset($uesCreees[$codeUE])) {
                $sqlUE = "INSERT INTO grilles_anciennes_ues (import_id, code, nom, credits) VALUES (?, ?, ?, ?)";
                $stmtUE = $pdo->prepare($sqlUE);
                $stmtUE->execute([$importId, $codeUE, $nomMatiere, 3]); // 3 crédits par défaut
                
                $uesCreees[$codeUE] = $pdo->lastInsertId();
            }
            
            $ueId = $uesCreees[$codeUE];
            
            // Créer l'ECUE
            $codeECUE = $codeUE . '_' . strtoupper(substr($typeNote, 0, 3));
            $nomECUE = $nomMatiere . ' (' . ucfirst($typeNote) . ')';
            
            $sqlECUE = "INSERT INTO grilles_anciennes_ecues (ue_id, code, nom, coefficient) VALUES (?, ?, ?, ?)";
            $stmtECUE = $pdo->prepare($sqlECUE);
            $stmtECUE->execute([$ueId, $codeECUE, $nomECUE, 1]);
            
            $ecueId = $pdo->lastInsertId();
            $ecuesCreees[$codeECUE] = $ecueId;
            
            // Insérer les notes pour cette ECUE
            $sqlNote = "INSERT INTO grilles_anciennes_notes (etudiant_id, ecue_id, note, type_note) VALUES (?, ?, ?, ?)";
            $stmtNote = $pdo->prepare($sqlNote);
            
            foreach ($donneesBrutes as $ligne) {
                $matricule = trim($ligne[$mapping['matricule']] ?? '');
                
                if (!isset($etudiantsMap[$matricule])) {
                    continue;
                }
                
                $noteStr = trim($ligne[$colonneIndex] ?? '');
                $note = convertirNote($noteStr);
                
                if ($note !== null) {
                    $stmtNote->execute([
                        $etudiantsMap[$matricule],
                        $ecueId,
                        $note,
                        $typeNote
                    ]);
                    $compteurNotes++;
                }
            }
        }
        
        // 5. Mettre à jour les statistiques de l'import
        $sqlUpdate = "UPDATE grilles_anciennes_imports 
                      SET nombre_ues = ?, nombre_ecues = ?, nombre_notes = ?, statut = 'termine'
                      WHERE id = ?";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([
            count($uesCreees),
            count($ecuesCreees),
            $compteurNotes,
            $importId
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'importId' => $importId,
            'statistiques' => [
                'etudiants' => $compteurEtudiants,
                'ues' => count($uesCreees),
                'ecues' => count($ecuesCreees),
                'notes' => $compteurNotes
            ],
            'message' => 'Import réalisé avec succès'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Créer les tables pour les grilles anciennes
 */
function creerTablesGrillesAnciennes($pdo) {
    $tables = [
        // Table principale des imports
        "CREATE TABLE IF NOT EXISTS grilles_anciennes_imports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            annee_academique VARCHAR(20) NOT NULL,
            session VARCHAR(50) NOT NULL,
            semestre VARCHAR(50) NOT NULL,
            promotion VARCHAR(100) NOT NULL,
            etablissement VARCHAR(200) DEFAULT NULL,
            notes_supplementaires TEXT DEFAULT NULL,
            date_import DATETIME NOT NULL,
            nombre_etudiants INT DEFAULT 0,
            nombre_ues INT DEFAULT 0,
            nombre_ecues INT DEFAULT 0,
            nombre_notes INT DEFAULT 0,
            statut ENUM('en_cours', 'termine', 'erreur') DEFAULT 'en_cours',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",
        
        // Table des étudiants
        "CREATE TABLE IF NOT EXISTS grilles_anciennes_etudiants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            import_id INT NOT NULL,
            matricule VARCHAR(50) NOT NULL,
            nom VARCHAR(100) NOT NULL,
            prenoms VARCHAR(100) DEFAULT NULL,
            date_naissance DATE DEFAULT NULL,
            INDEX idx_import_id (import_id),
            INDEX idx_matricule (matricule),
            FOREIGN KEY (import_id) REFERENCES grilles_anciennes_imports(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",
        
        // Table des UEs
        "CREATE TABLE IF NOT EXISTS grilles_anciennes_ues (
            id INT AUTO_INCREMENT PRIMARY KEY,
            import_id INT NOT NULL,
            code VARCHAR(20) NOT NULL,
            nom VARCHAR(200) NOT NULL,
            credits INT DEFAULT 3,
            INDEX idx_import_id (import_id),
            FOREIGN KEY (import_id) REFERENCES grilles_anciennes_imports(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",
        
        // Table des ECUEs
        "CREATE TABLE IF NOT EXISTS grilles_anciennes_ecues (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ue_id INT NOT NULL,
            code VARCHAR(30) NOT NULL,
            nom VARCHAR(200) NOT NULL,
            coefficient DECIMAL(3,1) DEFAULT 1.0,
            INDEX idx_ue_id (ue_id),
            FOREIGN KEY (ue_id) REFERENCES grilles_anciennes_ues(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",
        
        // Table des notes
        "CREATE TABLE IF NOT EXISTS grilles_anciennes_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            etudiant_id INT NOT NULL,
            ecue_id INT NOT NULL,
            note DECIMAL(4,2) DEFAULT NULL,
            type_note VARCHAR(50) DEFAULT 'note_finale',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_etudiant_id (etudiant_id),
            INDEX idx_ecue_id (ecue_id),
            FOREIGN KEY (etudiant_id) REFERENCES grilles_anciennes_etudiants(id) ON DELETE CASCADE,
            FOREIGN KEY (ecue_id) REFERENCES grilles_anciennes_ecues(id) ON DELETE CASCADE
        ) ENGINE=InnoDB"
    ];
    
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
}

/**
 * Générer un code UE à partir du nom
 */
function genererCodeUE($nom) {
    // Nettoyer et normaliser le nom
    $nom = strtoupper(trim($nom));
    $nom = preg_replace('/[^A-Z0-9\s]/', '', $nom);
    
    // Prendre les premières lettres des mots
    $mots = explode(' ', $nom);
    $code = '';
    
    foreach ($mots as $mot) {
        if (strlen($mot) > 0) {
            $code .= substr($mot, 0, 3);
        }
        if (strlen($code) >= 6) break;
    }
    
    // Compléter si nécessaire
    if (strlen($code) < 3) {
        $code = substr($nom, 0, 6);
    }
    
    return substr($code, 0, 8);
}

/**
 * Convertir une note en format numérique
 */
function convertirNote($noteStr) {
    if (empty($noteStr) || trim($noteStr) === '') {
        return null;
    }
    
    $noteStr = trim($noteStr);
    
    // Remplacer virgule par point
    $noteStr = str_replace(',', '.', $noteStr);
    
    // Extraire le nombre
    if (preg_match('/(\d+(?:\.\d+)?)/', $noteStr, $matches)) {
        $note = floatval($matches[1]);
        
        // Vérifier que la note est dans une plage raisonnable
        if ($note >= 0 && $note <= 20) {
            return $note;
        }
    }
    
    return null;
}

/**
 * Convertir une date en format SQL
 */
function convertirDate($dateStr) {
    if (empty($dateStr) || trim($dateStr) === '') {
        return null;
    }
    
    $dateStr = trim($dateStr);
    
    // Essayer différents formats
    $formats = [
        'Y-m-d',
        'd/m/Y',
        'm/d/Y',
        'd-m-Y',
        'm-d-Y',
        'Y/m/d'
    ];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateStr);
        if ($date !== false) {
            return $date->format('Y-m-d');
        }
    }
    
    return null;
}
?>
