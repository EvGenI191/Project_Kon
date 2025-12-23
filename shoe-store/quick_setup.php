<?php
// quick_setup.php - Быстрая настройка базы данных
echo "<h2>Быстрая настройка базы данных</h2>";

$dbFile = __DIR__ . '/database.sqlite';

// Удаляем старую базу если существует
if (file_exists($dbFile)) {
    unlink($dbFile);
    echo "<p>Старая база данных удалена</p>";
}

try {
    // Создаем новую базу
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON');
    
    echo "<p style='color: green;'>✅ База данных создана</p>";
    
    // Простые таблицы без сложных проверок
    $db->exec("
        CREATE TABLE products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            category TEXT,
            brand TEXT,
            price REAL NOT NULL,
            color TEXT,
            material TEXT,
            status TEXT DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    echo "<p style='color: green;'>✅ Таблица products создана</p>";
    
    $db->exec("
        CREATE TABLE product_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            image_url TEXT NOT NULL,
            is_main INTEGER DEFAULT 0
        )
    ");
    
    echo "<p style='color: green;'>✅ Таблица product_images создана</p>";
    
    $db->exec("
        CREATE TABLE product_sizes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            size TEXT NOT NULL,
            quantity INTEGER DEFAULT 0
        )
    ");
    
    echo "<p style='color: green;'>✅ Таблица product_sizes создана</p>";
    
    // Добавляем тестовые данные
    $testProducts = [
        ['Кроссовки Nike Air Max', 'Стильные кроссовки', 'Кроссовки', 'Nike', 8990, 'Черный', 'Текстиль'],
        ['Туфли офисные', 'Элегантные туфли', 'Туфли', 'Steve Madden', 5990, 'Черный', 'Кожа'],
        ['Кеды Converse', 'Классические кеды', 'Кеды', 'Converse', 4590, 'Красный', 'Хлопок'],
        ['Ботинки зимние', 'Теплые ботинки', 'Ботинки', 'Columbia', 12990, 'Коричневый', 'Замша'],
        ['Сандалии летние', 'Удобные сандалии', 'Сандалии', 'Birkenstock', 7990, 'Коричневый', 'Кожа']
    ];
    
    foreach ($testProducts as $product) {
        $stmt = $db->prepare("INSERT INTO products (name, description, category, brand, price, color, material) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute($product);
    }
    
    echo "<p style='color: green;'>✅ Добавлено 5 тестовых товаров</p>";
    
    // Добавляем изображения
    $images = [
        [1, 'assets/images/nike.jpg', 1],
        [2, 'assets/images/heels.jpg', 1],
        [3, 'assets/images/converse.jpg', 1],
        [4, 'assets/images/boots.jpg', 1],
        [5, 'assets/images/sandals.jpg', 1]
    ];
    
    foreach ($images as $image) {
        $stmt = $db->prepare("INSERT INTO product_images (product_id, image_url, is_main) VALUES (?, ?, ?)");
        $stmt->execute($image);
    }
    
    echo "<p style='color: green;'>✅ Добавлены изображения</p>";
    
    // Проверяем
    $count = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
    echo "<p>Всего товаров в базе: $count</p>";
    
    echo "<h3>✅ Настройка завершена!</h3>";
    echo "<p><a href='index.php' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>Перейти на главную</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Ошибка: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>