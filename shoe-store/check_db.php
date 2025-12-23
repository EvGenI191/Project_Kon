<?php
// check_db.php
echo "<h2>Проверка базы данных</h2>";

$dbFile = __DIR__ . 'includes/database.sqlite';

if (!file_exists($dbFile)) {
    echo "<p style='color: red;'>❌ Файл базы данных НЕ найден: " . realpath($dbFile) . "</p>";
    echo "<p><a href='create_database.php'>Создать базу данных</a></p>";
    exit;
}

echo "<p style='color: green;'>✅ Файл базы данных найден: " . realpath($dbFile) . "</p>";
echo "<p>Размер файла: " . filesize($dbFile) . " байт</p>";

try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Проверяем таблицы
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Таблицы в базе данных:</h3>";
    
    if (empty($tables)) {
        echo "<p style='color: red;'>❌ Таблицы не найдены</p>";
        echo "<p><a href='create_database.php'>Создать таблицы</a></p>";
    } else {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
    }
    
    // Проверяем конкретно таблицу products
    echo "<h3>Проверка таблицы products:</h3>";
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM products");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p style='color: green;'>✅ Таблица products существует, записей: " . $result['count'] . "</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Таблица products НЕ существует: " . $e->getMessage() . "</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Ошибка подключения: " . $e->getMessage() . "</p>";
}
?>