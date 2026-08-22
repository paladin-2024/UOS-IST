<?php
function enregistrer_visite() {
    $db = Connexion::getInstance()->getPDO();
    
    // Récupérer les informations sur le visiteur
    $ip = get_ip_address();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $pageUrl = $_SERVER['REQUEST_URI'] ?? '';
    $referrerUrl = $_SERVER['HTTP_REFERER'] ?? '';
    
    // Gérer la session du visiteur
    if (!isset($_SESSION['visitor_id'])) {
        $_SESSION['visitor_id'] = session_id() . '_' . time();
    }
    $visitorSession = $_SESSION['visitor_id'];
    
    // Récupérer l'ID utilisateur si connecté
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    // Insérer la visite
    $stmt = $db->prepare("INSERT INTO visitors (ip_address, user_agent, page_url, referrer_url, visitor_session, user_id) 
                          VALUES (:ip, :user_agent, :page_url, :referrer_url, :visitor_session, :user_id)");
    
    $stmt->bindParam(':ip', $ip);
    $stmt->bindParam(':user_agent', $userAgent);
    $stmt->bindParam(':page_url', $pageUrl);
    $stmt->bindParam(':referrer_url', $referrerUrl);
    $stmt->bindParam(':visitor_session', $visitorSession);
    $stmt->bindParam(':user_id', $userId);
    
    $result = $stmt->execute();
    
    // Mettre à jour les statistiques de la page
    mettre_a_jour_stats_page($pageUrl);
    
    // Mettre à jour les statistiques quotidiennes
    mettre_a_jour_stats_quotidiennes();
    
    return $result;
}

/**
 * Met à jour les statistiques d'une page spécifique
 * 
 * @param string $pageUrl URL de la page visitée
 * @return bool Succès de l'opération
 */
function mettre_a_jour_stats_page($pageUrl) {
    $db = Connexion::getInstance()->getPDO();
    
    // Récupérer le titre de la page (à adapter selon votre système)
    $pageTitle = '';
    if (isset($GLOBALS['page_title'])) {
        $pageTitle = $GLOBALS['page_title'];
    }
    
    // Vérifier si la page existe déjà dans les statistiques
    $stmt = $db->prepare("SELECT id FROM page_stats WHERE page_url = :page_url");
    $stmt->bindParam(':page_url', $pageUrl);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Mettre à jour les statistiques existantes
        $updateStmt = $db->prepare("UPDATE page_stats SET 
                                    views = views + 1, 
                                    last_visit = CURRENT_TIMESTAMP,
                                    page_title = COALESCE(:page_title, page_title)
                                    WHERE page_url = :page_url");
        $updateStmt->bindParam(':page_url', $pageUrl);
        $updateStmt->bindParam(':page_title', $pageTitle);
        return $updateStmt->execute();
    } else {
        // Créer une nouvelle entrée
        $insertStmt = $db->prepare("INSERT INTO page_stats (page_url, page_title, views, unique_views) 
                                   VALUES (:page_url, :page_title, 1, 1)");
        $insertStmt->bindParam(':page_url', $pageUrl);
        $insertStmt->bindParam(':page_title', $pageTitle);
        return $insertStmt->execute();
    }
}

/**
 * Met à jour les statistiques quotidiennes
 * 
 * @return bool Succès de l'opération
 */
function mettre_a_jour_stats_quotidiennes() {
    $db = Connexion::getInstance()->getPDO();
    $today = date('Y-m-d');
    
    // Vérifier si une entrée existe déjà pour aujourd'hui
    $stmt = $db->prepare("SELECT id FROM daily_stats WHERE stat_date = :date");
    $stmt->bindParam(':date', $today);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Mettre à jour les statistiques existantes
        $updateStmt = $db->prepare("UPDATE daily_stats SET 
                                    page_views = page_views + 1,
                                    updated_at = CURRENT_TIMESTAMP
                                    WHERE stat_date = :date");
        $updateStmt->bindParam(':date', $today);
        return $updateStmt->execute();
    } else {
        // Créer une nouvelle entrée pour aujourd'hui
        $insertStmt = $db->prepare("INSERT INTO daily_stats (stat_date, page_views, unique_visitors, new_visitors) 
                                   VALUES (:date, 1, 1, 1)");
        $insertStmt->bindParam(':date', $today);
        return $insertStmt->execute();
    }
}

/**
 * Récupère l'adresse IP du visiteur
 * 
 * @return string Adresse IP
 */
function get_ip_address() {
    // Vérifier différentes variables serveur pour obtenir l'IP réelle
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * Compte le nombre total de visiteurs
 * 
 * @return int Nombre total de visiteurs
 */
function compter_visiteurs_total() {
    $db = Connexion::getInstance()->getPDO();
    $stmt = $db->query("SELECT COUNT(DISTINCT visitor_session) FROM visitors");
    return $stmt->fetchColumn();
}

/**
 * Compte le nombre de visiteurs du jour
 * 
 * @return int Nombre de visiteurs aujourd'hui
 */
function compter_visiteurs_jour() {
    $db = Connexion::getInstance()->getPDO();
    $stmt = $db->query("SELECT COUNT(DISTINCT visitor_session) FROM visitors WHERE DATE(visit_date) = CURDATE()");
    return $stmt->fetchColumn();
}

/**
 * Compte le nombre total de pages vues
 * 
 * @return int Nombre total de pages vues
 */
function compter_pages_vues() {
    $db = Connexion::getInstance()->getPDO();
    $stmt = $db->query("SELECT COUNT(*) FROM visitors");
    return $stmt->fetchColumn();
}

/**
 * Compte le nombre de nouveaux utilisateurs
 * 
 * @return int Nombre de nouveaux utilisateurs
 */
function compter_nouveaux_utilisateurs() {
    $db = Connexion::getInstance()->getPDO();
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()");
    return $stmt->fetchColumn();
}

/**
 * Récupère les données pour un graphique de visites sur une période
 * 
 * @param int $days Nombre de jours à inclure
 * @return array Données formatées pour le graphique
 */
function obtenir_donnees_graphique_visites($days = 30) {
    $db = Connexion::getInstance()->getPDO();
    
    $result = [
        'labels' => [],
        'pageViews' => [],
        'uniqueVisitors' => []
    ];
    
    // Récupérer les statistiques des X derniers jours
    $stmt = $db->prepare("SELECT stat_date, page_views, unique_visitors 
                         FROM daily_stats 
                         WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                         ORDER BY stat_date ASC");
    $stmt->bindParam(':days', $days, PDO::PARAM_INT);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result['labels'][] = date('d/m', strtotime($row['stat_date']));
        $result['pageViews'][] = $row['page_views'];
        $result['uniqueVisitors'][] = $row['unique_visitors'];
    }
    
    return $result;
}