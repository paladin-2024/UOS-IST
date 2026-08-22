<?php
require_once __DIR__ . '/../config/env.php';
loadEnv(__DIR__ . '/../.env');

class Connexion {
    private static $instance = null;
    private $pdo = null;

    private function __construct() {
        $driver = getenv('DB_DRIVER') ?: 'pgsql';
        $host   = getenv('DB_HOST') ?: '127.0.0.1';
        $port   = getenv('DB_PORT') ?: '5432';
        $bdd    = getenv('DB_NAME') ?: '';
        $user   = getenv('DB_USER') ?: '';
        $pwd    = getenv('DB_PASS') ?: '';

        try {
            $dsn = "{$driver}:host={$host};port={$port};dbname={$bdd}";
            $this->pdo = new PDO($dsn, $user, $pwd);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Erreur de connexion : " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPDO() {
        return $this->pdo;
    }
}