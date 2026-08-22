<?php
require_once 'connexion.php';

class Auth {
    private $conn;
    
    public function __construct() {
        $this->conn = Connexion::getInstance()->getPDO();
    }
    
    public function authenticate() {
        // Get authorization header
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        // Check if token exists
        if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return false;
        }
        
        $token = $matches[1];
        
        // Verify token in database
        try {
            $stmt = $this->conn->prepare("SELECT etudiant_idetudiant, expiration FROM tokens WHERE token = ?");
            $stmt->execute([$token]);
            $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tokenData) {
                return false;
            }
            
            // Check if token is expired
            if (strtotime($tokenData['expiration']) < time()) {
                // Delete expired token
                $this->invalidateToken($token);
                return false;
            }
            
            // Return student ID
            return $tokenData['etudiant_idetudiant'];
            
        } catch (PDOException $e) {
            error_log("Authentication error: " . $e->getMessage());
            return false;
        }
    }
    
    public function generateToken($studentId) {
        // Generate a random token
        $token = bin2hex(random_bytes(32));
        
        // Set expiration to 7 days from now
        $expiration = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        // Store token in database
        try {
            // Delete any existing tokens for this student
            $stmt = $this->conn->prepare("DELETE FROM tokens WHERE etudiant_idetudiant = ?");
            $stmt->execute([$studentId]);
            
            // Insert new token
            $stmt = $this->conn->prepare("INSERT INTO tokens (token, etudiant_idetudiant, expiration, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$token, $studentId, $expiration]);
            
            return $token;
            
        } catch (PDOException $e) {
            error_log("Token generation error: " . $e->getMessage());
            return false;
        }
    }
    
    public function invalidateToken($token) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM tokens WHERE token = ?");
            $stmt->execute([$token]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Token invalidation error: " . $e->getMessage());
            return false;
        }
    }
    
    public function invalidateAllTokensForUser($studentId) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM tokens WHERE etudiant_idetudiant = ?");
            $stmt->execute([$studentId]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Token invalidation error: " . $e->getMessage());
            return false;
        }
    }
}
?>
