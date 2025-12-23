<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

echo "<h1>Инициализация базы данных</h1>";

try {
    if (!is_dir(dirname(DB_PATH))) {
        mkdir(dirname(DB_PATH), 0777, true);
        echo "<p>Создана папка storage</p>";
    }
    
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0777, true);
        echo "<p>Создана папка uploads</p>";
    }
    
    $db = Database::connect();
    echo "<p style='color: green;'> База данных успешно подключена</p>";
    
    $testProducts = [
        [
            'name' => 'Кроссовки Nike Air Max 270',
            'description' => 'Спортивные кроссовки с технологией Air для максимального комфорта',
            'category' => 'Мужская обувь',
            'brand' => 'Nike',
            'price' => 8990,
            'color' => 'Черный',
            'material' => 'Текстиль/Кожа'
        ],
        [
            'name' => 'Туфли женские на каблуке',
            'description' => 'Элегантные туфли для офиса и вечерних мероприятий',
            'category' => 'Женская обувь',
            'brand' => 'Zara',
            'price' => 4590,
            'color' => 'Бежевый',
            'material' => 'Натуральная кожа'
        ],
        [
            'name' => 'Ботинки зимние Columbia',
            'description' => 'Теплые водонепроницаемые ботинки для зимы',
            'category' => 'Мужская обувь',
            'brand' => 'Columbia',
            'price' => 12990,
            'color' => 'Коричневый',
            'material' => 'Замша'
        ],
        [
            'name' => 'Кеды Converse All Star',
            'description' => 'Классические кеды для повседневной носки',
            'category' => 'Унисекс',
            'brand' => 'Converse',
            'price' => 3990,
            'color' => 'Белый',
            'material' => 'Хлопок'
        ],
        [
            'name' => 'Босоножки летние',
            'description' => 'Легкие босоножки для жаркой погоды',
            'category' => 'Женская обувь',
            'brand' => 'Bershka',
            'price' => 2990,
            'color' => 'Черный',
            'material' => 'Искусственная кожа'
        ],
        [
            'name' => 'Сапоги резиновые',
            'description' => 'Непромокаемые сапоги для дождливой погоды',
            'category' => 'Детская обувь',
            'brand' => 'Demar',
            'price' => 1590,
            'color' => 'Желтый',
            'material' => 'Резина'
        ]
    ];
    
    foreach ($testProducts as $productData) {
        $stmt = $db->prepare("
            INSERT INTO products (name, description, category, brand, price, color, material)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $productData['name'],
            $productData['description'],
            $productData['category'],
            $productData['brand'],
            $productData['price'],
            $productData['color'],
            $productData['material']
        ]);
        
        $productId = $db->lastInsertId();
        
        // Добавляем размеры
        $sizes = [36, 37, 38, 39, 40, 41, 42, 43];
        foreach ($sizes as $size) {
            $quantity = rand(0, 15);
            $stmt = $db->prepare("
                INSERT INTO product_sizes (product_id, size, quantity)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$productId, $size, $quantity]);
        }
        
        // Добавляем изображения
        $images = [
            'https://via.placeholder.com/400x400/007bff/ffffff?text=' . urlencode($productData['name']),
            'https://via.placeholder.com/400x400/6c757d/ffffff?text=Side+View',
            'https://via.placeholder.com/400x400/28a745/ffffff?text=Back+View'
        ];
        
        foreach ($images as $index => $imageUrl) {
            $isMain = ($index === 0) ? 1 : 0;
            $stmt = $db->prepare("
                INSERT INTO product_images (product_id, image_url, is_main)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$productId, $imageUrl, $isMain]);
        }
    }
    
    echo "<p style='color: green;'> Добавлено " . count($testProducts) . " тестовых товаров</p>";
    
    // Добавляем тестового менеджера
    $hashedPassword = password_hash('manager123', PASSWORD_BCRYPT);
    $stmt = $db->prepare("
        INSERT OR IGNORE INTO users (email, password, name, role)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute(['manager@store.com', $hashedPassword, 'Менеджер', 'manager']);
    
    echo "<p style='color: green;'> Добавлен тестовый менеджер</p>";
    
    echo "<h2>Тестовые доступы:</h2>";
    echo "<ul>";
    echo "<li><strong>Администратор:</strong> admin@store.com / admin123</li>";
    echo "<li><strong>Менеджер:</strong> manager@store.com / manager123</li>";
    echo "<li><strong>Пользователь:</strong> Зарегистрируйтесь через форму</li>";
    echo "</ul>";
    
    echo "<p style='color: green; font-size: 1.2em;'> Инициализация завершена успешно!</p>";
    echo "<p><a href='index.php'>Перейти на главную страницу</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'> Ошибка: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>