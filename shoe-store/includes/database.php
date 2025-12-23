<?php
require_once 'config.php';

class Database {
    private static $connection = null;
    
    public static function connect() {
        if (self::$connection === null) {
            try {
                self::$connection = new PDO('sqlite:' . DB_PATH);
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$connection->exec('PRAGMA foreign_keys = ON');
                
            } catch (PDOException $e) {
                die("Ошибка подключения к базе данных: " . $e->getMessage());
            }
        }
        return self::$connection;
    }
    
    public static function query($sql, $params = []) {
        $pdo = self::connect();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
?>