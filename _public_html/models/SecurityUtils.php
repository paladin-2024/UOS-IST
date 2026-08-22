<?php

class SecurityUtils
{
    private $db;
    private $salt = "university_secure_cards_2023"; // Pour renforcer la sécurité des hash

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    /**
     * Génère une signature cryptographique pour les données de carte
     * 
     * @param string $data Les données à signer
     * @param string $privateKey Clé privée pour la signature
     * @return string Signature au format base64
     */
    public function generateSignature($data, $privateKey)
    {
        // En production, utiliser des algorithmes plus robustes (RSA ou ECDSA)
        // Pour cet exemple, nous utilisons HMAC-SHA256
        return hash_hmac('sha256', $data, $privateKey);
    }

    /**
     * Vérifie la signature d'une carte
     * 
     * @param string $data Les données originales
     * @param string $signature La signature à vérifier
     * @param string $publicKey Clé publique pour la vérification
     * @return bool True si la signature est valide
     */
    public function verifySignature($data, $signature, $publicKey)
    {
        $expectedSignature = hash_hmac('sha256', $data, $publicKey);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Génère un motif d'hologramme unique basé sur le matricule de l'étudiant
     * 
     * @param string $matricule Matricule de l'étudiant
     * @return array Données du motif d'hologramme
     */
    public function generateHologramPattern($matricule)
    {
        // Créer un hash du matricule pour garantir la reproductibilité
        $seed = md5($matricule . $this->salt);
        $pattern = [];
        
        // Générer un pattern pseudo-aléatoire mais reproductible
        for ($i = 0; $i < 10; $i++) {
            $seedPart = substr($seed, $i * 3, 3);
            $value = hexdec($seedPart) / 0xFFF; // Valeur entre 0 et 1
            
            $pattern[] = [
                'x' => 0.1 + 0.8 * ($i % 3 / 2 + fmod($value, 0.3)),
                'y' => 0.1 + 0.8 * (floor($i / 3) / 2 + fmod($value * 7, 0.3)),
                'r' => 0.02 + 0.04 * fmod($value * 13, 1),
                'c' => 'rgba(' . (100 + floor($value * 155)) . ',' . 
                       (100 + floor($value * 50)) . ',' . 
                       (200 + floor($value * 55)) . ',' . 
                       (0.5 + $value * 0.5) . ')'
            ];
        }
        
        return $pattern;
    }

    /**
     * Obtient un schéma de couleur pour une promotion
     * 
     * @param int $promotionId ID de la promotion
     * @return array Tableau des couleurs [primary, secondary, text, background]
     */
    public function getCardColorScheme($promotionId)
    {
        // Palette de couleurs par défaut
        $defaultScheme = ['#1a5276', '#2980b9', '#ffffff', '#f8f9fa'];
        
        try {
            // Récupérer les couleurs de la promotion si elles existent
            $query = "SELECT color_primary, color_secondary FROM promotion 
                      WHERE idpromotion = :promotionId AND color_primary IS NOT NULL";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['promotionId' => $promotionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['color_primary'])) {
                return [
                    $result['color_primary'],
                    $result['color_secondary'] ?? '#2980b9',
                    '#ffffff', // Texte blanc par défaut
                    '#f8f9fa'  // Fond gris clair par défaut
                ];
            }
            
            // Si aucune couleur n'est définie, générer des couleurs basées sur l'ID
            $hash = md5('promo_' . $promotionId . $this->salt);
            $hue = (hexdec(substr($hash, 0, 2)) % 360);
            
            return [
                'hsl(' . $hue . ', 60%, 30%)', // Couleur primaire
                'hsl(' . $hue . ', 50%, 50%)', // Couleur secondaire
                '#ffffff',                      // Texte blanc
                '#f8f9fa'                       // Fond gris clair
            ];
            
        } catch (Exception $e) {
            error_log('Erreur lors de la récupération des couleurs: ' . $e->getMessage());
            return $defaultScheme;
        }
    }

    /**
     * Enregistre une nouvelle émission de carte
     * 
     * @param array $cardData Données de la carte
     * @return bool Succès de l'opération
     */
    public function recordCardIssuance($cardData)
    {
        try {
            // Vérifier s'il existe déjà une carte active pour cet étudiant
            $query = "SELECT card_id FROM etudiants_cards 
                      WHERE student_id = :studentId AND status = 'active'";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['studentId' => $cardData['student_id']]);
            $existingCard = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Si une carte active existe, la désactiver
            if ($existingCard) {
                $updateQuery = "UPDATE etudiants_cards SET status = 'revoked', 
                               revoked_at = NOW(), revocation_reason = 'Nouvelle carte émise'
                               WHERE card_id = :cardId";
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->execute(['cardId' => $existingCard['card_id']]);
            }
            
            // Insérer la nouvelle carte
            $insertQuery = "INSERT INTO etudiants_cards (card_id, student_id, issued_at, expires_at, status)
                           VALUES (:cardId, :studentId, :issuedAt, :expiresAt, :status)";
            $insertStmt = $this->db->prepare($insertQuery);
            return $insertStmt->execute([
                'cardId' => $cardData['card_id'],
                'studentId' => $cardData['student_id'],
                'issuedAt' => $cardData['issued_at'],
                'expiresAt' => $cardData['expires_at'],
                'status' => $cardData['status']
            ]);
            
        } catch (Exception $e) {
            error_log('Erreur lors de l\'enregistrement de la carte: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifie la validité d'une carte étudiant
     * 
     * @param array $cardData Données de la carte à vérifier
     * @param array $verifierInfo Informations sur le vérificateur
     * @return array Résultat de la vérification
     */
    public function verifyCard($cardData, $verifierInfo = null)
    {
        try {
            // 1. Vérifier que toutes les données nécessaires sont présentes
            if (!isset($cardData['id']) || !isset($cardData['matricule']) || 
                !isset($cardData['expires_at']) || !isset($cardData['signature']) ||
                !isset($cardData['card_id'])) {
                return ['valid' => false, 'message' => 'Données de carte incomplètes'];
            }
            
            // 2. Vérifier que la carte n'est pas expirée
            if (time() > $cardData['expires_at']) {
                return ['valid' => false, 'message' => 'Carte expirée'];
            }
            
            // 3. Vérifier que la carte n'a pas été révoquée
            $query = "SELECT status, revocation_reason FROM etudiants_cards 
                      WHERE card_id = :cardId";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['cardId' => $cardData['card_id']]);
            $cardStatus = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cardStatus) {
                return ['valid' => false, 'message' => 'Carte inconnue'];
            }
            
            if ($cardStatus['status'] !== 'active') {
                return [
                    'valid' => false, 
                    'message' => 'Carte révoquée: ' . $cardStatus['revocation_reason']
                ];
            }
            
            // 4. Vérifier la signature (retirer d'abord la signature pour créer le même message)
            $signatureToVerify = $cardData['signature'];
            unset($cardData['signature']);
            $dataToVerify = json_encode($cardData);

            $publicKey = getenv('CONFIG_PUBLIC_KEY') ?: $this->salt;
            
            if (!$this->verifySignature($dataToVerify, $signatureToVerify, $publicKey)) {
                return ['valid' => false, 'message' => 'Signature invalide'];
            }
            
            // 5. Récupérer les données actuelles de l'étudiant
            $studentQuery = "SELECT e.matricule, e.noms, p.\"designationPromotion\", a.designation as annee
                           FROM etudiants e
                           JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                           JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
                           WHERE e.idetudiant = :idEtudiant";
            $studentStmt = $this->db->prepare($studentQuery);
            $studentStmt->execute(['idEtudiant' => $cardData['id']]);
            $studentData = $studentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$studentData) {
                return ['valid' => false, 'message' => 'Étudiant non trouvé'];
            }
            
            // 6. Enregistrer cette vérification pour audit
            if ($verifierInfo) {
                $this->logVerification($cardData['card_id'], $verifierInfo, true);
            }
            
            // 7. Carte valide, retourner les infos de l'étudiant
            return [
                'valid' => true,
                'etudiant' => [
                    'matricule' => $studentData['matricule'],
                    'nom' => $studentData['noms'],
                    'promotion' => $studentData['designationPromotion'],
                    'annee' => $studentData['annee'],
                    'date_expiration' => date('Y-m-d', $cardData['expires_at'])
                ],
                'card_id' => $cardData['card_id']
            ];
            
        } catch (Exception $e) {
            error_log('Erreur lors de la vérification de la carte: ' . $e->getMessage());
            // Enregistrer cette vérification échouée pour audit
            if ($verifierInfo && isset($cardData['card_id'])) {
                $this->logVerification($cardData['card_id'], $verifierInfo, false, $e->getMessage());
            }
            return ['valid' => false, 'message' => 'Erreur système'];
        }
    }

    /**
     * Révoque une carte (perdue, volée)
     * 
     * @param string $cardId ID de la carte
     * @param string $reason Raison de la révocation
     * @param int $userId ID de l'utilisateur effectuant l'action
     * @return bool Succès de l'opération
     */
    public function revokeCard($cardId, $reason, $userId)
    {
        try {
            $query = "UPDATE etudiants_cards SET 
                      status = 'revoked', 
                      revoked_at = NOW(), 
                      revoked_by = :userId,
                      revocation_reason = :reason
                      WHERE card_id = :cardId";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'cardId' => $cardId,
                'reason' => $reason,
                'userId' => $userId
            ]);
        } catch (Exception $e) {
            error_log('Erreur lors de la révocation de la carte: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enregistre une tentative de vérification pour audit
     * 
     * @param string $cardId ID de la carte
     * @param array $verifierInfo Informations sur le vérificateur
     * @param bool $success Résultat de la vérification
     * @param string $errorMessage Message d'erreur éventuel
     * @return bool Succès de l'opération
     */
    private function logVerification($cardId, $verifierInfo, $success, $errorMessage = null)
    {
        try {
            $query = "INSERT INTO ecard_verification_log 
                     (card_id, user_id, ip_address, user_agent, location, success, error_message, verification_date) 
                     VALUES (:cardId, :userId, :ipAddress, :userAgent, :location, :success, :errorMessage, NOW())";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'cardId' => $cardId,
                'userId' => $verifierInfo['user_id'] ?? null,
                'ipAddress' => $verifierInfo['ip'] ?? null,
                'userAgent' => $verifierInfo['user_agent'] ?? null,
                'location' => $verifierInfo['location'] ?? null,
                'success' => $success ? 1 : 0,
                'errorMessage' => $errorMessage
            ]);
        } catch (Exception $e) {
            error_log('Erreur lors de l\'enregistrement de la vérification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les données d'une carte à partir de son ID
     * 
     * @param string $cardId ID de la carte
     * @return array|null Données de la carte ou null si non trouvée
     */
    public function getCardDataById($cardId) {
        try {
            // Récupérer les données de base de la carte
            $sql = "SELECT ec.*, e.idetudiant, e.matricule, e.noms, 
                         p.\"designationPromotion\", a.designation as annee_academique
                  FROM etudiants_cards ec
                  JOIN etudiant e ON ec.student_id = e.idetudiant
                  JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                  JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
                  WHERE ec.card_id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$cardId]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);
            /*
            if (!$card) {
                return null;
            }
            
            // Créer un objet de données de carte similaire à celui du QR code
            $expiresTimestamp = strtotime($card['expires_at']);
            $issuedTimestamp = strtotime($card['issued_at']);
            
            // Générer la signature pour cette carte
            $cardData = [
                'id' => $card['student_id'],
                'matricule' => $card['matricule'],
                'nom' => $card['noms'],
                'issued_at' => $issuedTimestamp,
                'expires_at' => $expiresTimestamp,
                'card_id' => $card['card_id']
            ];
            
            // Générer la signature pour vérifier l'intégrité
            $publicKey = getenv('CONFIG_PUBLIC_KEY') ?: $this->salt;
            $dataToSign = json_encode($cardData);
            $cardData['signature'] = $this->generateSignature($dataToSign, $publicKey);
            
            return $cardData;
            */
            return $card;
            
        } catch (Exception $e) {
            error_log('Erreur lors de la récupération des données de carte: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Génère une clé d'accès temporaire pour la vérification de carte
     * 
     * @param int $userId ID de l'utilisateur demandant l'accès
     * @param int $validity Durée de validité en secondes (par défaut 1 heure)
     * @return string Code d'accès temporaire
     */
    public function generateTemporaryAccessKey($userId, $validity = 3600) {
        $expiration = time() + $validity;
        $keyData = $userId . '|' . $expiration . '|' . bin2hex(random_bytes(8));
        $signature = hash_hmac('sha256', $keyData, $this->salt);
        
        $accessKey = base64_encode($keyData . '|' . $signature);
        
        try {
            // Enregistrer la clé dans la base de données
            $query = "INSERT INTO ecard_access_keys 
                     (access_key, user_id, expires_at, created_at) 
                     VALUES (:accessKey, :userId, :expiresAt, NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'accessKey' => $accessKey,
                'userId' => $userId,
                'expiresAt' => date('Y-m-d H:i:s', $expiration)
            ]);
            
            return $accessKey;
        } catch (Exception $e) {
            error_log('Erreur lors de la génération de la clé d\'accès: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Vérifie si une clé d'accès temporaire est valide
     * 
     * @param string $accessKey Clé d'accès à vérifier
     * @return bool True si la clé est valide
     */
    public function verifyAccessKey($accessKey) {
        try {
            $decodedKey = base64_decode($accessKey);
            $parts = explode('|', $decodedKey);
            
            if (count($parts) !== 4) {
                return false;
            }
            
            $userId = $parts[0];
            $expiration = $parts[1];
            $randomData = $parts[2];
            $signature = $parts[3];
            
            // Vérifier si la clé a expiré
            if (time() > $expiration) {
                return false;
            }
            
            // Vérifier la signature
            $keyData = $userId . '|' . $expiration . '|' . $randomData;
            $expectedSignature = hash_hmac('sha256', $keyData, $this->salt);
            
            if (!hash_equals($expectedSignature, $signature)) {
                return false;
            }
            
            // Vérifier si la clé existe et est valide dans la base de données
            $query = "SELECT * FROM ecard_access_keys 
                     WHERE access_key = :accessKey 
                     AND expires_at > NOW() 
                     AND revoked = 0";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['accessKey' => $accessKey]);
            
            return $stmt->rowCount() > 0;
            
        } catch (Exception $e) {
            error_log('Erreur lors de la vérification de la clé d\'accès: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Génère les scripts SQL pour créer les tables nécessaires
     * 
     * @return array Tableau de scripts SQL
     */
    public static function getTableCreationScripts() {
        return [
            // Table des cartes étudiant
            "CREATE TABLE IF NOT EXISTS etudiants_cards (
                id INT AUTO_INCREMENT PRIMARY KEY,
                card_id VARCHAR(50) NOT NULL UNIQUE,
                student_id INT NOT NULL,
                issued_at DATETIME NOT NULL,
                expires_at DATETIME NOT NULL,
                status ENUM('active', 'revoked', 'expired') NOT NULL DEFAULT 'active',
                revoked_at DATETIME NULL,
                revoked_by INT NULL,
                revocation_reason TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES etudiants(idetudiant) ON DELETE CASCADE,
                INDEX (card_id),
                INDEX (student_id),
                INDEX (status)
            ) ENGINE=InnoDB;",
            
            // Table des logs de vérification
            "CREATE TABLE IF NOT EXISTS ecard_verification_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                card_id VARCHAR(50) NOT NULL,
                user_id INT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                location VARCHAR(255) NULL,
                success TINYINT(1) NOT NULL DEFAULT 0,
                error_message TEXT NULL,
                verification_date DATETIME NOT NULL,
                INDEX (card_id),
                INDEX (user_id),
                INDEX (verification_date)
            ) ENGINE=InnoDB;",
            
            // Table des clés d'accès temporaires
            "CREATE TABLE IF NOT EXISTS ecard_access_keys (
                id INT AUTO_INCREMENT PRIMARY KEY,
                access_key VARCHAR(255) NOT NULL UNIQUE,
                user_id INT NOT NULL,
                expires_at DATETIME NOT NULL,
                revoked TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                INDEX (access_key),
                INDEX (user_id),
                INDEX (expires_at)
            ) ENGINE=InnoDB;"
        ];
    }


    /**
 * Génère un identifiant unique pour une carte étudiant
 * 
 * @param int $studentId ID de l'étudiant
 * @param string $matricule Matricule de l'étudiant
 * @return string Identifiant unique de carte
 */
public function generateUniqueCardId($studentId, $matricule)
{
    // Créer un préfixe basé sur l'année en cours
    $yearPrefix = 'CARD-' . date('Y');
    
    // Générer une partie aléatoire unique
    $randomPart = bin2hex(random_bytes(4));
    
    // Ajouter une partie basée sur l'étudiant pour traçabilité
    $studentPart = substr(md5($matricule . $studentId), 0, 6);
    
    // Construire l'ID complet
    $cardId = $yearPrefix . '-' . $studentPart . '-' . $randomPart;
    
    // Vérifier que l'ID n'existe pas déjà dans la base de données
    $query = "SELECT COUNT(*) FROM etudiants_cards WHERE card_id = :cardId";
    $stmt = $this->db->prepare($query);
    $stmt->execute(['cardId' => $cardId]);
    
    // Si l'ID existe déjà, en générer un nouveau (rare mais possible)
    if ($stmt->fetchColumn() > 0) {
        return $this->generateUniqueCardId($studentId, $matricule);
    }
    
    return $cardId;
}

/**
 * Signe cryptographiquement des données avec une clé
 * 
 * @param mixed $data Données à signer (sera converti en JSON)
 * @param string $privateKey Clé privée pour la signature
 * @return array Données originales avec signature ajoutée
 */
public function signData($data, $privateKey)
{
    // Convertir en JSON si les données ne sont pas déjà une chaîne
    $jsonData = is_string($data) ? $data : json_encode($data);
    
    // Générer la signature
    $signature = $this->generateSignature($jsonData, $privateKey);
    
    // Si les données d'entrée sont un tableau, ajouter la signature
    if (is_array($data)) {
        $data['signature'] = $signature;
        $data['signature_timestamp'] = time();
        return $data;
    } else {
        // Sinon, retourner un tableau avec les données et la signature
        return [
            'data' => $data,
            'signature' => $signature,
            'signature_timestamp' => time()
        ];
    }
}

/**
 * Génère les données complètes d'un hologramme numérique
 * 
 * @param string $matricule Matricule de l'étudiant
 * @param string $cardId ID unique de la carte
 * @param int $promotionId ID de la promotion (pour les couleurs)
 * @return array Données de l'hologramme
 */
public function generateHologram($matricule, $cardId, $promotionId)
{
    // Récupérer les couleurs de la promotion
    $colorScheme = $this->getCardColorScheme($promotionId);
    
    // Générer le motif de base
    $pattern = $this->generateHologramPattern($matricule);
    
    // Ajouter des éléments spécifiques basés sur le cardId
    $cardHash = md5($cardId . $this->salt);
    
    // Générer 3 éléments de sécurité supplémentaires basés sur le hash
    $securityElements = [];
    for ($i = 0; $i < 3; $i++) {
        $hashPart = substr($cardHash, $i * 10, 10);
        $value = hexdec($hashPart) / 0xFFFFFFFF; // Valeur entre 0 et 1
        
        $securityElements[] = [
            'type' => ($i % 3 == 0) ? 'circle' : (($i % 3 == 1) ? 'hexagon' : 'triangle'),
            'position' => [
                'x' => 0.2 + 0.6 * fmod($value * 7, 1),
                'y' => 0.2 + 0.6 * fmod($value * 13, 1)
            ],
            'size' => 0.05 + 0.1 * fmod($value * 5, 1),
            'rotation' => floor($value * 360),
            'color' => $colorScheme[$i % 2],
            'opacity' => 0.4 + 0.4 * fmod($value * 11, 1)
        ];
    }
    
    // Générer un code de vérification visuel (derniers caractères du cardId)
    $verificationCode = substr($cardId, -6);
    
    // Construire l'objet hologramme complet
    return [
        'base_pattern' => $pattern,
        'security_elements' => $securityElements,
        'verification_code' => $verificationCode,
        'signature' => substr(hash_hmac('sha256', $matricule . $cardId, $this->salt), 0, 16),
        'issued_timestamp' => time(),
        'colors' => [
            'primary' => $colorScheme[0],
            'secondary' => $colorScheme[1],
            'highlight' => 'rgba(' . rand(200, 255) . ',' . rand(200, 255) . ',' . rand(200, 255) . ',0.8)'
        ],
        'animation_seed' => hexdec(substr($cardHash, 0, 8)),
        'watermark_text' => 'AUTHENTIC ' . date('Y')
    ];
}






}