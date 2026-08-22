<?php
require_once 'config/Connexion.php';

class HomeController {
    private $pdo;
    
    public function __construct() {
        $this->pdo = Connexion::getInstance()->getPDO();
    }
    
    // Récupérer les actualités récentes
    public function getLatestNews($limit = 3) {
        $query = "SELECT n.*, c.name as category_name 
                 FROM news n 
                 LEFT JOIN categories c ON n.category_id = c.id 
                 WHERE n.is_published = 1 
                 ORDER BY n.published_at DESC 
                 LIMIT :limit";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Récupérer les formations mises en avant
    public function getFeaturedFormations($limit = 3) {
        $query = "SELECT * FROM formations 
                 WHERE is_published = 1 AND is_featured = 1 
                 ORDER BY order_index DESC 
                 LIMIT :limit";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Récupérer les partenaires
    public function getFeaturedPartners($limit = 6) {
        $query = "SELECT * FROM partners 
                 WHERE is_active = 1 AND is_featured = 1 
                 ORDER BY order_index 
                 LIMIT :limit";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Récupérer les statistiques du site
    public function getSiteStats() {
        $query = "SELECT * FROM site_stats 
                 WHERE is_featured = 1 
                 ORDER BY order_index";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Récupérer les paramètres du site
    public function getSiteSettings() {
        $query = "SELECT setting_key, setting_value FROM site_settings";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['setting_key']] = $setting['setting_value'];
        }
        
        return $result;
    }
    // Récupérer les membres du comité de gestion
public function getManagementCommitteeMembers($limit = 5) {
    $query = "SELECT * FROM staff 
              WHERE department = 'Management Committee' 
              AND is_active = 1 
              ORDER BY is_featured DESC, order_index ASC, full_name ASC 
              LIMIT :limit";
    
    $stmt = $this->pdo->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

}