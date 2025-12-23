<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

$db = Database::connect();

// Получаем ID товара
$productId = $_GET['id'] ?? 0;
if (!$productId) {
    header('Location: catalog.php');
    exit;
}

// Получаем информацию о товаре
$stmt = $db->prepare("
    SELECT p.*, 
           GROUP_CONCAT(DISTINCT pi.image_url) as images,
           GROUP_CONCAT(DISTINCT ps.size || ':' || ps.quantity) as sizes_data
    FROM products p
    LEFT JOIN product_images pi ON p.id = pi.product_id
    LEFT JOIN product_sizes ps ON p.id = ps.product_id
    WHERE p.id = ? AND p.status = 'active'
    GROUP BY p.id
");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: catalog.php');
    exit;
}

// Парсим размеры
$sizes = [];
if ($product['sizes_data']) {
    $sizesArray = explode(',', $product['sizes_data']);
    foreach ($sizesArray as $sizeData) {
        list($size, $quantity) = explode(':', $sizeData);
        $sizes[] = [
            'size' => $size,
            'quantity' => $quantity,
            'available' => $quantity > 0
        ];
    }
}

// Парсим изображения
$images = $product['images'] ? explode(',', $product['images']) : [];
$mainImage = !empty($images) ? $images[0] : 'assets/images/no-image.jpg';

// Похожие товары
$similarStmt = $db->prepare("
    SELECT p.*, pi.image_url as main_image 
    FROM products p 
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1 
    WHERE p.category = ? AND p.id != ? AND p.status = 'active' 
    ORDER BY RANDOM() 
    LIMIT 4
");
$similarStmt->execute([$product['category'], $productId]);
$similarProducts = $similarStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <!-- Хлебные крошки -->
        <nav class="breadcrumbs">
            <a href="index.php">Главная</a>
            <i class="fas fa-chevron-right"></i>
            <a href="catalog.php">Каталог</a>
            <i class="fas fa-chevron-right"></i>
            <a href="catalog.php?category=<?= urlencode($product['category']) ?>"><?= htmlspecialchars($product['category']) ?></a>
            <i class="fas fa-chevron-right"></i>
            <span><?= htmlspecialchars($product['name']) ?></span>
        </nav>
        
        <div class="product-detail">
            <!-- Галерея изображений -->
            <div class="product-gallery">
                <div class="main-image">
                    <a href="<?= $mainImage ?>" data-lightbox="product-images">
                        <img src="<?= $mainImage ?>" alt="<?= htmlspecialchars($product['name']) ?>" id="main-product-image">
                    </a>
                </div>
                
                <?php if (count($images) > 1): ?>
                <div class="thumbnail-images">
                    <?php foreach ($images as $index => $image): ?>
                    <div class="thumbnail <?= $index === 0 ? 'active' : '' ?>" 
                         onclick="changeMainImage('<?= $image ?>', this)">
                        <img src="<?= $image ?>" alt="Изображение <?= $index + 1 ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Информация о товаре -->
            <div class="product-info">
                <h1><?= htmlspecialchars($product['name']) ?></h1>
                
                <div class="product-meta">
                    <span class="product-brand">Бренд: <?= htmlspecialchars($product['brand']) ?></span>
                    <span class="product-category">Категория: <?= htmlspecialchars($product['category']) ?></span>
                    <span class="product-sku">Артикул: #<?= $product['id'] ?></span>
                </div>
                
                <div class="product-price">
                    <span class="price"><?= formatPrice($product['price']) ?> ₽</span>
                    <?php if ($product['old_price']): ?>
                    <span class="old-price"><?= formatPrice($product['old_price']) ?> ₽</span>
                    <span class="discount">-<?= round(100 - ($product['price'] / $product['old_price'] * 100)) ?>%</span>
                    <?php endif; ?>
                </div>
                
                <div class="product-description">
                    <h3>Описание</h3>
                    <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                </div>
                
                <div class="product-specs">
                    <h3>Характеристики</h3>
                    <table class="specs-table">
                        <?php if ($product['color']): ?>
                        <tr>
                            <td>Цвет</td>
                            <td><?= htmlspecialchars($product['color']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($product['material']): ?>
                        <tr>
                            <td>Материал</td>
                            <td><?= htmlspecialchars($product['material']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td>Статус</td>
                            <td>
                                <?php if (array_reduce($sizes, function($carry, $item) { return $carry || $item['available']; }, false)): ?>
                                <span class="in-stock">В наличии</span>
                                <?php else: ?>
                                <span class="out-of-stock">Нет в наличии</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Выбор размера и добавление в корзину -->
                <div class="product-purchase">
                    <?php if (!empty($sizes)): ?>
                    <div class="size-selector">
                        <h3>Выберите размер:</h3>
                        <div class="size-options">
                            <?php foreach ($sizes as $sizeInfo): ?>
                            <label class="size-option <?= !$sizeInfo['available'] ? 'disabled' : '' ?>">
                                <input type="radio" name="size" value="<?= $sizeInfo['size'] ?>" 
                                       <?= !$sizeInfo['available'] ? 'disabled' : '' ?>>
                                <span class="size-label"><?= $sizeInfo['size'] ?></span>
                                <?php if (!$sizeInfo['available']): ?>
                                <span class="size-stock">Нет в наличии</span>
                                <?php else: ?>
                                <span class="size-stock"><?= $sizeInfo['quantity'] ?> шт.</span>
                                <?php endif; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="quantity-selector">
                        <label for="quantity">Количество:</label>
                        <div class="quantity-control">
                            <button type="button" class="quantity-btn minus">-</button>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" max="10" class="quantity-input">
                            <button type="button" class="quantity-btn plus">+</button>
                        </div>
                    </div>
                    
                    <div class="purchase-actions">
                        <button class="btn btn-primary btn-add-to-cart" 
                                data-product-id="<?= $product['id'] ?>"
                                <?= empty($sizes) || !array_reduce($sizes, function($carry, $item) { return $carry || $item['available']; }, false) ? 'disabled' : '' ?>>
                            <i class="fas fa-shopping-cart"></i> Добавить в корзину
                        </button>
                        <button class="btn btn-outline btn-wishlist">
                            <i class="far fa-heart"></i> В избранное
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Похожие товары -->
        <?php if (!empty($similarProducts)): ?>
        <section class="similar-products">
            <h2>Похожие товары</h2>
            <div class="products-grid">
                <?php foreach ($similarProducts as $similar): ?>
                <div class="product-card">
                    <div class="product-image">
                        <img src="<?= $similar['main_image'] ?: 'assets/images/no-image.jpg' ?>" 
                             alt="<?= htmlspecialchars($similar['name']) ?>">
                    </div>
                    <div class="product-info">
                        <h3>
                            <a href="product.php?id=<?= $similar['id'] ?>">
                                <?= htmlspecialchars($similar['name']) ?>
                            </a>
                        </h3>
                        <p class="product-brand"><?= htmlspecialchars($similar['brand']) ?></p>
                        <div class="product-price"><?= formatPrice($similar['price']) ?> ₽</div>
                        <a href="product.php?id=<?= $similar['id'] ?>" class="btn btn-small">Подробнее</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
    <script src="assets/js/product.js"></script>
    <script>
    function changeMainImage(src, element) {
        document.getElementById('main-product-image').src = src;
        document.querySelectorAll('.thumbnail').forEach(thumb => {
            thumb.classList.remove('active');
        });
        element.classList.add('active');
    }
    
    // Управление количеством
    document.querySelector('.quantity-btn.minus').addEventListener('click', function() {
        const input = document.getElementById('quantity');
        if (parseInt(input.value) > 1) {
            input.value = parseInt(input.value) - 1;
        }
    });
    
    document.querySelector('.quantity-btn.plus').addEventListener('click', function() {
        const input = document.getElementById('quantity');
        if (parseInt(input.value) < 10) {
            input.value = parseInt(input.value) + 1;
        }
    });
    
    // Добавление в корзину
    document.querySelector('.btn-add-to-cart').addEventListener('click', function() {
        const productId = this.dataset.productId;
        const quantity = parseInt(document.getElementById('quantity').value);
        const sizeInput = document.querySelector('input[name="size"]:checked');
        
        if (!sizeInput) {
            alert('Пожалуйста, выберите размер');
            return;
        }
        
        const size = sizeInput.value;
        
        if (typeof cartManager !== 'undefined') {
            cartManager.addItem(productId, size, quantity);
            alert('Товар добавлен в корзину!');
        } else {
            alert('Система корзины не загружена. Пожалуйста, обновите страницу.');
        }
    });
    
    // Включение/выключение кнопки добавления в корзину
    document.querySelectorAll('input[name="size"]').forEach(input => {
        input.addEventListener('change', function() {
            const addToCartBtn = document.querySelector('.btn-add-to-cart');
            addToCartBtn.disabled = this.disabled;
        });
    });
    </script>
</body>
</html>

<style>
.breadcrumbs {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 30px;
    font-size: 0.9rem;
    color: #666;
}

.breadcrumbs a {
    color: #666;
    text-decoration: none;
}

.breadcrumbs a:hover {
    color: #007bff;
}

.breadcrumbs i {
    font-size: 0.8rem;
    color: #999;
}

.breadcrumbs span {
    color: #333;
    font-weight: 500;
}

.product-detail {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-bottom: 60px;
}

.product-gallery {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.main-image {
    border: 1px solid #eee;
    border-radius: 8px;
    overflow: hidden;
    background: white;
}

.main-image img {
    width: 100%;
    height: auto;
    display: block;
    cursor: zoom-in;
}

.thumbnail-images {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.thumbnail {
    width: 80px;
    height: 80px;
    border: 2px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
    cursor: pointer;
    transition: border-color 0.3s;
}

.thumbnail:hover,
.thumbnail.active {
    border-color: #007bff;
}

.thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-info h1 {
    font-size: 1.8rem;
    margin-bottom: 15px;
    color: #333;
}

.product-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
    color: #666;
    font-size: 0.9rem;
}

.product-meta span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.product-price {
    margin-bottom: 30px;
}

.product-price .price {
    font-size: 2rem;
    font-weight: bold;
    color: #007bff;
}

.product-price .old-price {
    font-size: 1.2rem;
    color: #999;
    text-decoration: line-through;
    margin-left: 15px;
}

.product-price .discount {
    background: #dc3545;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.9rem;
    margin-left: 10px;
}

.product-description {
    margin-bottom: 30px;
}

.product-description h3 {
    margin-bottom: 10px;
    color: #333;
}

.product-description p {
    line-height: 1.6;
    color: #666;
}

.product-specs {
    margin-bottom: 30px;
}

.product-specs h3 {
    margin-bottom: 15px;
    color: #333;
}

.specs-table {
    width: 100%;
    border-collapse: collapse;
}

.specs-table tr {
    border-bottom: 1px solid #eee;
}

.specs-table td {
    padding: 10px 0;
}

.specs-table td:first-child {
    width: 150px;
    font-weight: 500;
    color: #666;
}

.in-stock {
    color: #28a745;
    font-weight: 500;
}

.out-of-stock {
    color: #dc3545;
    font-weight: 500;
}

.product-purchase {
    background: #f8f9fa;
    padding: 25px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.size-selector {
    margin-bottom: 25px;
}

.size-selector h3 {
    margin-bottom: 15px;
    font-size: 1rem;
    color: #333;
}

.size-options {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.size-option {
    position: relative;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    border: 2px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
}

.size-option input {
    display: none;
}

.size-option:not(.disabled):hover {
    border-color: #007bff;
}

.size-option input:checked + .size-label {
    background: #007bff;
    color: white;
}

.size-option.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.size-label {
    font-weight: 500;
    font-size: 0.9rem;
}

.size-stock {
    font-size: 0.7rem;
    color: #666;
    margin-top: 2px;
}

.quantity-selector {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
}

.quantity-selector label {
    font-weight: 500;
    color: #333;
}

.quantity-control {
    display: flex;
    align-items: center;
    gap: 5px;
}

.quantity-btn {
    width: 36px;
    height: 36px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quantity-btn:hover {
    background: #f8f9fa;
}

.quantity-input {
    width: 60px;
    height: 36px;
    text-align: center;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

.purchase-actions {
    display: flex;
    gap: 15px;
}

.btn-add-to-cart {
    flex: 1;
    padding: 15px;
    font-size: 1.1rem;
}

.btn-add-to-cart:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-wishlist {
    padding: 15px 25px;
}

.similar-products {
    margin-top: 60px;
    padding-top: 40px;
    border-top: 1px solid #eee;
}

.similar-products h2 {
    margin-bottom: 30px;
    color: #333;
}

@media (max-width: 768px) {
    .product-detail {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .purchase-actions {
        flex-direction: column;
    }
    
    .product-info h1 {
        font-size: 1.5rem;
    }
    
    .product-price .price {
        font-size: 1.5rem;
    }
}
</style>