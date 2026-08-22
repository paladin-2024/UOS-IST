<?php
require_once 'config/Connexion.php';

class NewsController {
    private $pdo;
    
    public function __construct() {
        $this->pdo = Connexion::getInstance()->getPDO();
    }
    
    // Récupérer les actualités connexes
public function getRelatedNews($current_id, $category_id, $limit = 3) {
    // Si pas de catégorie, récupérer simplement d'autres actualités récentes
    if (!$category_id) {
        $query = "SELECT n.*, c.name as category_name 
                 FROM news n 
                 LEFT JOIN categories c ON n.category_id = c.id 
                 WHERE n.is_published = 1 AND n.id != :current_id
                 ORDER BY n.published_at DESC 
                 LIMIT :limit";
                 
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':current_id', $current_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    } else {
        // Sinon récupérer des actualités de la même catégorie
        $query = "SELECT n.*, c.name as category_name 
                 FROM news n 
                 LEFT JOIN categories c ON n.category_id = c.id 
                 WHERE n.is_published = 1 AND n.category_id = :category_id AND n.id != :current_id
                 ORDER BY n.published_at DESC 
                 LIMIT :limit";
                 
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->bindParam(':current_id', $current_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Méthode pour récupérer les actualités mises en avant ou toutes les actualités
public function getAllNews($page = 1, $perPage = 9, $category_id = null, $featured_only = false) {
    $offset = ($page - 1) * $perPage;
    
    $whereClause = "WHERE n.is_published = 1";
    $params = [];
    
    if ($category_id) {
        $whereClause .= " AND n.category_id = :category_id";
        $params[':category_id'] = $category_id;
    }
    
    if ($featured_only) {
        $whereClause .= " AND n.is_featured = 1";
    }
    
    $query = "SELECT n.*, c.name as category_name 
             FROM news n 
             LEFT JOIN categories c ON n.category_id = c.id 
             $whereClause
             ORDER BY n.published_at DESC
             LIMIT :perPage OFFSET :offset";
    
    $stmt = $this->pdo->prepare($query);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':perPage', $perPage, PDO::PARAM_INT);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



    // Compter le nombre total d'actualités pour la pagination
    public function countNews($category_id = null) {
        $whereClause = "WHERE is_published = 1";
        $params = [];
        
        if ($category_id) {
            $whereClause .= " AND category_id = :category_id";
            $params[':category_id'] = $category_id;
        }
        
        $query = "SELECT COUNT(*) as total FROM news $whereClause";
        $stmt = $this->pdo->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
    
    // Récupérer toutes les catégories d'actualités
public function getNewsCategories() {
    // Modification de la requête pour obtenir toutes les catégories même sans articles associés
    $query = "SELECT c.* FROM categories c
             WHERE c.type = 'news' OR c.type = 'general'
             ORDER BY c.name";
    
    $stmt = $this->pdo->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Vérification que $categories contient bien des données
    if (!is_array($categories)) {
        return [];
    }
    
    return $categories;
}

    
    // Récupérer une actualité par son slug
    public function getNewsBySlug($slug) {
        $query = "SELECT n.*, c.name as category_name, u.full_name as author_name
                 FROM news n 
                 LEFT JOIN categories c ON n.category_id = c.id 
                 LEFT JOIN users u ON n.created_by = u.id
                 WHERE n.slug = :slug AND n.is_published = 1";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':slug', $slug);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}