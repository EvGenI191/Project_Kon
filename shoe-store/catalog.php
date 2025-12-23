<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

$db = Database::connect();

// Параметры пагинации
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Параметры фильтрации
$where = ['p.status = "active"'];
$params = [];

// Фильтр по категории
if (!empty($_GET['category'])) {
    $where[] = 'p.category = ?';
    $params[] = $_GET['category'];
}

// Фильтр по бренду
if (!empty($_GET['brand'])) {
    $where[] = 'p.brand = ?';
    $params[] = $_GET['brand'];
}

// Фильтр по цвету
if (!empty($_GET['color'])) {
    $where[] = 'p.color = ?';
    $params[] = $_GET['color'];
}

// Фильтр по размеру
if (!empty($_GET['size'])) {
    $where[] = 'ps.size = ? AND ps.quantity > 0';
    $params[] = $_GET['size'];
}

// Фильтр по цене
if (!empty($_GET['min_price']) && is_numeric($_GET['min_price'])) {
    $where[] = 'p.price >= ?';
    $params[] = $_GET['min_price'];
}
if (!empty($_GET['max_price']) && is_numeric($_GET['max_price'])) {
    $where[] = 'p.price <= ?';
    $params[] = $_GET['max_price'];
}

// Поиск
if (!empty($_GET['search'])) {
    $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $searchTerm = '%' . $_GET['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Сортировка
$orderBy = 'p.created_at DESC';
if (!empty($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'price_asc':
            $orderBy = 'p.price ASC';
            break;
        case 'price_desc':
            $orderBy = 'p.price DESC';
            break;
        case 'name':
            $orderBy = 'p.name ASC';
            break;
        case 'popular':
            // Здесь можно добавить логику популярности
            $orderBy = 'p.created_at DESC';
            break;
    }
}

// Получение товаров
$sql = "SELECT p.*, pi.image_url as main_image 
        FROM products p 
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1";
        
if (!empty($_GET['size'])) {
    $sql .= " JOIN product_sizes ps ON p.id = ps.product_id";
}

$sql .= " WHERE " . implode(' AND ', $where);
$sql .= " GROUP BY p.id ORDER BY $orderBy LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Общее количество товаров для пагинации
$countSql = "SELECT COUNT(DISTINCT p.id) as total 
             FROM products p";
             
if (!empty($_GET['size'])) {
    $countSql .= " JOIN product_sizes ps ON p.id = ps.product_id";
}

$countSql .= " WHERE " . implode(' AND ', $where);
$countParams = array_slice($params, 0, -2); // Убираем LIMIT и OFFSET

$stmt = $db->prepare($countSql);
$stmt->execute($countParams);
$totalProducts = $stmt->fetchColumn();
$totalPages = ceil($totalProducts / $limit);

// Получение фильтров
$categories = $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$brands = $db->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL ORDER BY brand")->fetchAll(PDO::FETCH_COLUMN);
$colors = $db->query("SELECT DISTINCT color FROM products WHERE color IS NOT NULL ORDER BY color")->fetchAll(PDO::FETCH_COLUMN);
$sizes = $db->query("SELECT DISTINCT size FROM product_sizes WHERE quantity > 0 ORDER BY size")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Каталог товаров - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #007bff;
            --primary-dark: #0056b3;
            --primary-light: #e7f3ff;
            --secondary: #6c757d;
            --success: #28a745;
            --light: #f8f9fa;
            --dark: #343a40;
            --border: #dee2e6;
            --shadow: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-hover: 0 8px 20px rgba(0,0,0,0.12);
            --radius: 10px;
            --transition: all 0.3s ease;
        }
        
        .catalog-header {
            background: linear-gradient(135deg, var(--primary-light), white);
            padding: 30px;
            border-radius: var(--radius);
            margin-bottom: 30px;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }
        
        .catalog-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill="%23007bff" opacity="0.1" d="M0,0 L100,0 L100,100 Z"/></svg>');
            background-size: cover;
        }
        
        .catalog-header h1 {
            font-size: 2.2rem;
            color: var(--dark);
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        .catalog-header p {
            color: var(--secondary);
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }
        
        .catalog-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        /* Боковая панель фильтров */
        .catalog-filters {
            background: white;
            padding: 25px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            height: fit-content;
            position: sticky;
            top: 90px;
            border: 1px solid var(--border);
        }
        
        .filter-group {
            margin-bottom: 28px;
            padding-bottom: 25px;
            border-bottom: 1px solid var(--border);
        }
        
        .filter-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .filter-group h3 {
            font-size: 1rem;
            margin-bottom: 18px;
            color: var(--dark);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-group h3::before {
            content: '';
            display: block;
            width: 4px;
            height: 16px;
            background: var(--primary);
            border-radius: 2px;
        }
        
        .filter-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .filter-option {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 6px;
            transition: var(--transition);
            position: relative;
        }
        
        .filter-option:hover {
            background: var(--primary-light);
            transform: translateX(5px);
        }
        
        .filter-option input[type="radio"],
        .filter-option input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }
        
        .filter-option span {
            font-size: 0.95rem;
            color: var(--dark);
        }
        
        /* ИСПРАВЛЕННЫЙ БЛОК ЦЕНЫ - теперь не выходит за рамки */
        .price-filter {
            width: 100%;
        }
        
        .price-range {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 10px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        .price-range input {
            width: 100%;
            min-width: 0;
            padding: 10px 12px;
            border: 2px solid var(--border);
            border-radius: 6px;
            font-size: 0.95rem;
            transition: var(--transition);
            box-sizing: border-box;
        }
        
        .price-range input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
            outline: none;
        }
        
        /* Убираем стрелки у числовых полей */
        .price-range input[type="number"]::-webkit-outer-spin-button,
        .price-range input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        
        .price-range input[type="number"] {
            -moz-appearance: textfield;
            appearance: textfield;
        }
        
        .range-separator {
            color: var(--secondary);
            font-weight: 600;
            padding: 0 5px;
            text-align: center;
            min-width: 15px;
            flex-shrink: 0;
        }
        
        /* Альтернативный вариант с полями друг под другом */
        .price-range-vertical {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
        }
        
        .price-range-vertical .price-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .price-range-vertical input {
            flex: 1;
            width: 100%;
            min-width: 0;
        }
        
        /* Стили для размеров */
        .size-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }
        
        .size-option {
            position: relative;
        }
        
        .size-option input {
            display: none;
        }
        
        .size-option span {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 40px;
            border: 2px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            background: white;
        }
        
        .size-option:hover span {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,123,255,0.15);
        }
        
        .size-option input:checked + span {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(0,123,255,0.2);
        }
        
        /* Стили для цветов */
        .color-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
        }
        
        .color-option {
            position: relative;
            width: 36px;
            height: 36px;
        }
        
        .color-option input {
            display: none;
        }
        
        .color-preview {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid transparent;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        
        .color-option:hover .color-preview {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .color-option input:checked + .color-preview {
            border-color: var(--dark);
            transform: scale(1.1);
            box-shadow: 0 0 0 3px rgba(0,123,255,0.3);
        }
        
        .color-option input:checked + .color-preview::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 14px;
            font-weight: bold;
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        
        .filter-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 25px;
        }
        
        .filter-actions .btn {
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: var(--transition);
            width: 100%;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }
        
        .filter-actions .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .filter-actions .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        
        .filter-actions .btn-outline {
            background: white;
            border: 2px solid var(--border);
            color: var(--dark);
        }
        
        .filter-actions .btn-outline:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        
        /* Панель сортировки */
        .catalog-sorting {
            background: white;
            padding: 20px 25px;
            border-radius: var(--radius);
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sort-form {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .sort-form label {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.95rem;
        }
        
        .sort-form .form-control {
            min-width: 220px;
            padding: 10px 15px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: var(--transition);
            background: white;
            cursor: pointer;
        }
        
        .sort-form .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        
        /* Сетка товаров */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .product-card {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--border);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .product-image {
            position: relative;
            height: 200px;
            overflow: hidden;
            background: linear-gradient(135deg, var(--light), white);
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--success);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .product-info {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-info h3 {
            margin-bottom: 8px;
            font-size: 1.1rem;
            line-height: 1.4;
        }
        
        .product-info h3 a {
            color: var(--dark);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .product-info h3 a:hover {
            color: var(--primary);
        }
        
        .product-meta {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .product-category,
        .product-brand {
            font-size: 0.85rem;
            color: var(--secondary);
            background: var(--light);
            padding: 4px 10px;
            border-radius: 4px;
        }
        
        .product-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            margin-top: auto;
            margin-bottom: 15px;
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }
        
        .product-actions .btn {
            flex: 1;
            padding: 10px;
            border-radius: 6px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }
        
        .product-actions .btn-outline {
            border: 2px solid var(--border);
            color: var(--dark);
            background: white;
        }
        
        .product-actions .btn-outline:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        
        .product-actions .btn-primary {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }
        
        .product-actions .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        /* Пустой каталог */
        .empty-catalog {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        
        .empty-catalog i {
            font-size: 64px;
            color: var(--border);
            margin-bottom: 25px;
        }
        
        .empty-catalog h3 {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 15px;
        }
        
        .empty-catalog p {
            color: var(--secondary);
            font-size: 1.1rem;
        }
        
        /* Пагинация */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid var(--border);
        }
        
        .pagination-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            height: 42px;
            padding: 0 15px;
            border: 2px solid var(--border);
            border-radius: 8px;
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            background: white;
        }
        
        .pagination-link:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        
        .pagination-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(0,123,255,0.2);
        }
        
        .pagination-dots {
            color: var(--secondary);
            padding: 0 12px;
            font-weight: 600;
        }
        
        /* Адаптивность */
        @media (max-width: 1200px) {
            .catalog-container {
                grid-template-columns: 250px 1fr;
                gap: 20px;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
            }
            
            .price-range {
                gap: 8px;
            }
        }
        
        @media (max-width: 992px) {
            .catalog-container {
                grid-template-columns: 1fr;
            }
            
            .catalog-filters {
                position: static;
                margin-bottom: 20px;
            }
            
            .catalog-sorting {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }
            
            .sort-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .sort-form .form-control {
                width: 100%;
            }
            
            /* На мобильных делаем поля цены друг под другом */
            .price-range {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .range-separator {
                display: none;
            }
        }
        
        @media (max-width: 576px) {
            .catalog-header {
                padding: 20px;
            }
            
            .catalog-header h1 {
                font-size: 1.8rem;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .size-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .color-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .price-range {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .price-range input {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <div class="catalog-header">
            <h1>Каталог обуви</h1>
            <p>Найдено товаров: <strong><?= $totalProducts ?></strong></p>
        </div>
        
        <div class="catalog-container">
            <!-- Фильтры -->
            <aside class="catalog-filters">
                <form method="get" class="filter-form" id="filter-form">
                    <!-- Поиск -->
                    <div class="filter-group">
                        <h3><i class="fas fa-search"></i> Поиск</h3>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Поиск товаров..." 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                               style="width: 100%; box-sizing: border-box;">
                    </div>
                    
                    <!-- Цена - ИСПРАВЛЕННЫЙ БЛОК -->
                    <div class="filter-group price-filter">
                        <h3><i class="fas fa-tag"></i> Цена</h3>
                        <div class="price-range">
                            <input type="number" name="min_price" class="form-control price-input" 
                                   placeholder="От" min="0" 
                                   value="<?= htmlspecialchars($_GET['min_price'] ?? '') ?>"
                                   oninput="validatePrice(this)">
                            <span class="range-separator">—</span>
                            <input type="number" name="max_price" class="form-control price-input" 
                                   placeholder="До" min="0" 
                                   value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>"
                                   oninput="validatePrice(this)">
                        </div>
                        <!-- Альтернативный вариант для очень маленьких экранов -->
                        <div class="price-range-vertical" style="display: none;">
                            <div class="price-input-group">
                                <label style="min-width: 40px;">От:</label>
                                <input type="number" name="min_price_alt" placeholder="0" min="0">
                            </div>
                            <div class="price-input-group">
                                <label style="min-width: 40px;">До:</label>
                                <input type="number" name="max_price_alt" placeholder="100000" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Категории -->
                    <div class="filter-group">
                        <h3><i class="fas fa-list"></i> Категории</h3>
                        <div class="filter-options">
                            <?php foreach ($categories as $category): ?>
                            <label class="filter-option">
                                <input type="radio" name="category" value="<?= htmlspecialchars($category) ?>"
                                       <?= ($_GET['category'] ?? '') == $category ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars($category) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Бренды -->
                    <div class="filter-group">
                        <h3><i class="fas fa-copyright"></i> Бренды</h3>
                        <div class="filter-options">
                            <?php foreach ($brands as $brand): ?>
                            <label class="filter-option">
                                <input type="checkbox" name="brand[]" value="<?= htmlspecialchars($brand) ?>"
                                       <?= in_array($brand, $_GET['brand'] ?? []) ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars($brand) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Размеры -->
                    <div class="filter-group">
                        <h3><i class="fas fa-ruler"></i> Размеры</h3>
                        <div class="size-grid">
                            <?php foreach ($sizes as $size): ?>
                            <label class="size-option">
                                <input type="radio" name="size" value="<?= $size ?>"
                                       <?= ($_GET['size'] ?? '') == $size ? 'checked' : '' ?>>
                                <span><?= $size ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Цвета -->
                    <div class="filter-group">
                        <h3><i class="fas fa-palette"></i> Цвета</h3>
                        <div class="color-grid">
                            <?php foreach ($colors as $color): ?>
                            <label class="color-option">
                                <input type="radio" name="color" value="<?= htmlspecialchars($color) ?>"
                                       <?= ($_GET['color'] ?? '') == $color ? 'checked' : '' ?>>
                                <div class="color-preview" style="background-color: <?= getColorHex($color) ?>" 
                                     title="<?= htmlspecialchars($color) ?>"></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Применить фильтры
                        </button>
                        <a href="catalog.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Сбросить
                        </a>
                    </div>
                </form>
            </aside>
            
            <!-- Товары -->
            <section class="catalog-products">
                <!-- Сортировка -->
                <div class="catalog-sorting">
                    <div class="results-count">
                        <strong><?= count($products) ?></strong> из <strong><?= $totalProducts ?></strong> товаров
                    </div>
                    <form method="get" class="sort-form">
                        <input type="hidden" name="category" value="<?= htmlspecialchars($_GET['category'] ?? '') ?>">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        
                        <label>Сортировать по:</label>
                        <select name="sort" class="form-control" onchange="this.form.submit()">
                            <option value="">По умолчанию</option>
                            <option value="price_asc" <?= ($_GET['sort'] ?? '') == 'price_asc' ? 'selected' : '' ?>>Цена по возрастанию</option>
                            <option value="price_desc" <?= ($_GET['sort'] ?? '') == 'price_desc' ? 'selected' : '' ?>>Цена по убыванию</option>
                            <option value="name" <?= ($_GET['sort'] ?? '') == 'name' ? 'selected' : '' ?>>По названию</option>
                            <option value="popular" <?= ($_GET['sort'] ?? '') == 'popular' ? 'selected' : '' ?>>По популярности</option>
                        </select>
                    </form>
                </div>
                
                <!-- Сетка товаров -->
                <div class="products-grid">
                    <?php if (empty($products)): ?>
                    <div class="empty-catalog">
                        <i class="fas fa-search"></i>
                        <h3>Товары не найдены</h3>
                        <p>Попробуйте изменить параметры поиска</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <?php if ($product['status'] == 'hidden'): ?>
                            <div class="product-badge">Нет в наличии</div>
                            <?php endif; ?>
                            <div class="product-image">
                                <img src="<?= $product['main_image'] ?: 'assets/images/no-image.jpg' ?>" 
                                     alt="<?= htmlspecialchars($product['name']) ?>"
                                     loading="lazy">
                            </div>
                            <div class="product-info">
                                <h3>
                                    <a href="product.php?id=<?= $product['id'] ?>">
                                        <?= htmlspecialchars($product['name']) ?>
                                    </a>
                                </h3>
                                <div class="product-meta">
                                    <span class="product-category"><?= htmlspecialchars($product['category']) ?></span>
                                    <span class="product-brand"><?= htmlspecialchars($product['brand']) ?></span>
                                </div>
                                <div class="product-price"><?= formatPrice($product['price']) ?> ₽</div>
                                <div class="product-actions">
                                    <a href="product.php?id=<?= $product['id'] ?>" class="btn btn-outline">
                                        <i class="fas fa-eye"></i> Подробнее
                                    </a>
                                    <button class="btn btn-primary add-to-cart-btn" 
                                            data-product-id="<?= $product['id'] ?>">
                                        <i class="fas fa-shopping-cart"></i> В корзину
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                       class="pagination-link">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                           class="pagination-link <?= $i == $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                        <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                        <span class="pagination-dots">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                       class="pagination-link">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/catalog.js"></script>
    <script>
    // Валидация цены
    function validatePrice(input) {
        // Ограничиваем максимальное значение
        if (input.value > 1000000) {
            input.value = 1000000;
        }
        
        // Убираем отрицательные значения
        if (input.value < 0) {
            input.value = 0;
        }
        
        // Проверяем, чтобы "от" было меньше "до"
        const minPriceInput = document.querySelector('input[name="min_price"]');
        const maxPriceInput = document.querySelector('input[name="max_price"]');
        
        if (minPriceInput.value && maxPriceInput.value && 
            parseInt(minPriceInput.value) > parseInt(maxPriceInput.value)) {
            // Меняем местами, если "от" больше "до"
            const temp = minPriceInput.value;
            minPriceInput.value = maxPriceInput.value;
            maxPriceInput.value = temp;
        }
    }
    
    // Обработка добавления в корзину
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const button = this;
            
            // Сохраняем исходное состояние кнопки
            const originalHTML = button.innerHTML;
            const originalText = button.querySelector('span')?.textContent || 'В корзину';
            
            // Показываем состояние загрузки
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;
            
            // Временная реализация - имитация AJAX запроса
            setTimeout(() => {
                // Возвращаем исходное состояние с сообщением об успехе
                button.innerHTML = '<i class="fas fa-check"></i> Добавлено';
                button.style.background = 'var(--success)';
                
                // Через 2 секунды возвращаем исходный вид
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.disabled = false;
                    button.style.background = '';
                    
                    // Показываем уведомление
                    showNotification('Товар добавлен в корзину', 'success');
                }, 2000);
            }, 500);
        });
    });
    
    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        // Анимация появления
        setTimeout(() => notification.classList.add('show'), 10);
        
        // Автоматическое скрытие через 3 секунды
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    // Добавляем стили для уведомлений
    const style = document.createElement('style');
    style.textContent = `
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 1000;
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s ease;
            border-left: 4px solid var(--success);
            max-width: 350px;
        }
        
        .notification.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .notification i {
            font-size: 1.2rem;
            color: var(--success);
        }
        
        .notification span {
            font-size: 0.95rem;
            color: var(--dark);
        }
    `;
    document.head.appendChild(style);
    
    // Адаптивное переключение между горизонтальным и вертикальным расположением полей цены
    function checkPriceLayout() {
        const priceRange = document.querySelector('.price-range');
        const priceRangeVertical = document.querySelector('.price-range-vertical');
        const priceFilter = document.querySelector('.price-filter');
        
        if (!priceRange || !priceRangeVertical) return;
        
        // Проверяем ширину контейнера фильтров
        const filterWidth = priceFilter.offsetWidth;
        
        if (filterWidth < 250) {
            // На очень узких экранах используем вертикальное расположение
            priceRange.style.display = 'none';
            priceRangeVertical.style.display = 'flex';
        } else {
            // На нормальных экранах используем горизонтальное расположение
            priceRange.style.display = 'grid';
            priceRangeVertical.style.display = 'none';
        }
    }
    
    // Проверяем при загрузке и изменении размера окна
    window.addEventListener('load', checkPriceLayout);
    window.addEventListener('resize', checkPriceLayout);
    </script>
</body>
</html>

<?php
function getColorHex($color) {
    $colors = [
        'Черный' => '#000000',
        'Белый' => '#FFFFFF',
        'Красный' => '#FF0000',
        'Синий' => '#0000FF',
        'Зеленый' => '#008000',
        'Желтый' => '#FFFF00',
        'Коричневый' => '#A52A2A',
        'Серый' => '#808080',
        'Бежевый' => '#F5F5DC',
        'Розовый' => '#FFC0CB',
    ];
    
    return $colors[$color] ?? '#CCCCCC';
}
?>