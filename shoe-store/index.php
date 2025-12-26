<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'Магазин обуви');
}

$dbFile = __DIR__ . '/database.sqlite';

if (!file_exists($dbFile)) {
    die("<div style='padding: 40px; text-align: center; font-family: Arial, sans-serif;'>
        <h2 style='color: #dc3545;'>База данных не найдена</h2>
        <p>Файл <strong>database.sqlite</strong> не существует.</p>
        <p>Пожалуйста, создайте базу данных:</p>
        <p>
            <a href='quick_setup.php' style='display: inline-block; margin: 10px; padding: 12px 24px; 
               background: #007bff; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                Быстрая настройка
            </a>
        </p>
    </div>");
}

try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='products'")->fetch();
    
    if (!$tableCheck) {
        die("<div style='padding: 40px; text-align: center; font-family: Arial, sans-serif;'>
            <h2 style='color: #dc3545;'>Таблица товаров не найдена</h2>
            <p>Таблица <strong>products</strong> не существует в базе данных.</p>
            <p>Пожалуйста, создайте структуру базы данных:</p>
            <p>
                <a href='quick_setup.php' style='display: inline-block; margin: 10px; padding: 12px 24px; 
                   background: #28a745; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                   🔧 Создать таблицы и тестовые данные
                </a>
            </p>
        </div>");
    }
    
    $stmt = $db->query("
        SELECT p.*, 
               (SELECT image_url FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image 
        FROM products p 
        WHERE p.status = 'active' OR p.status IS NULL
        ORDER BY p.created_at DESC 
        LIMIT 8
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'no such table') !== false) {
        die("<div style='padding: 40px; text-align: center; font-family: Arial, sans-serif;'>
            <h2 style='color: #dc3545;'>Ошибка базы данных</h2>
            <p>В базе данных отсутствуют необходимые таблицы.</p>
            <p><strong>Ошибка:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p>Пожалуйста, выполните настройку:</p>
            <p>
                <a href='quick_setup.php' style='display: inline-block; margin-top: 20px; padding: 12px 24px; 
                   background: #dc3545; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                   🔨 Восстановить базу данных
                </a>
            </p>
        </div>");
    }
    
    die("<div style='padding: 40px; text-align: center; font-family: Arial, sans-serif;'>
        <h2 style='color: #dc3545;'>Ошибка подключения к базе данных</h2>
        <p><strong>Ошибка:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
    </div>");
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Главная</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Простые стили */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        header {
            background: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 15px 0;
            margin-bottom: 30px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo a {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            text-decoration: none;
        }
        
        nav a {
            margin-left: 20px;
            color: #333;
            text-decoration: none;
        }
        
        .hero {
            text-align: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, #e3f2fd, #fff);
            border-radius: 10px;
            margin-bottom: 40px;
        }
        
        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: #333;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .category-card {
            background: white;
            padding: 25px;
            text-align: center;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #007bff;
        }
        
        .category-card i {
            font-size: 40px;
            color: #007bff;
            margin-bottom: 15px;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
            margin: 40px 0;
        }
        
        .product-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #ddd;
            transition: transform 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .product-image {
            height: 180px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-price {
            font-size: 1.3rem;
            font-weight: bold;
            color: #007bff;
            margin: 10px 0;
        }
        
        .btn-small {
            display: inline-block;
            padding: 8px 16px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        
        footer {
            background: #343a40;
            color: white;
            padding: 40px 0;
            margin-top: 60px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            nav {
                margin-top: 15px;
            }
            
            nav a {
                margin: 0 10px;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="index.php"><?= SITE_NAME ?></a>
                </div>
                <nav>
                    <a href="catalog.php">Каталог</a>
                    <a href="cart.php">Корзина</a>
                    <a href="check_db.php">Проверка БД</a>
                </nav>
            </div>
        </div>
    </header>
    
    <main class="container">
        <section class="hero">
            <h1>Добро пожаловать в магазин обуви</h1>
            <p>Стильная и качественная обувь для всей семьи</p>
            <a href="catalog.php" class="btn">Перейти в каталог</a>
        </section>
        
        <?php if (!empty($categories)): ?>
        <section>
            <h2 style="text-align: center; margin-bottom: 20px;">Категории</h2>
            <div class="categories-grid">
                <?php foreach ($categories as $category): ?>
                <a href="catalog.php?category=<?= urlencode($category) ?>" class="category-card">
                    <i class="fas fa-shoe-prints"></i>
                    <h3><?= htmlspecialchars($category) ?></h3>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
        
        <section>
            <h2 style="text-align: center; margin-bottom: 20px;">Новинки</h2>
            <div class="products-grid">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if (!empty($product['main_image'])): ?>
                                <img src="<?= htmlspecialchars($product['main_image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                            <?php else: ?>
                                <i class="fas fa-shoe-prints" style="font-size: 60px; color: #ccc;"></i>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3><?= htmlspecialchars($product['name']) ?></h3>
                            <p style="color: #666; font-size: 14px; margin: 5px 0;"><?= htmlspecialchars($product['brand'] ?? '') ?></p>
                            <div class="product-price"><?= number_format($product['price'], 0, ',', ' ') ?> ₽</div>
                            <a href="product.php?id=<?= $product['id'] ?>" class="btn-small">Подробнее</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; grid-column: 1 / -1; padding: 40px; color: #666;">
                        Товары пока не добавлены. <a href="quick_setup.php">Добавить тестовые данные</a>
                    </p>
                <?php endif; ?>
            </div>
        </section>
    </main>
    
    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. Все права защищены.</p>
            <p style="margin-top: 20px; font-size: 14px; color: #aaa;">
                База данных: <?= basename($dbFile) ?> (<?= filesize($dbFile) ?> байт)
            </p>
        </div>
    </footer>
</body>
</html>