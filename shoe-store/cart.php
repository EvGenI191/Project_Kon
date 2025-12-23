<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/cart.php';

$db = Database::connect();

// Получаем корзину
if (isLoggedIn()) {
    $cartId = getOrCreateCart($_SESSION['user_id']);
    $cartItems = getCartItems($cartId);
    $cartTotal = getCartTotal($cartId);
} else {
    // Для неавторизованных используем сессию
    $sessionId = session_id();
    $cartId = getOrCreateCart(null, $sessionId);
    $cartItems = getCartItems($cartId);
    $cartTotal = getCartTotal($cartId);
}

// Обработка действий с корзиной
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update':
                foreach ($_POST['quantity'] as $itemId => $quantity) {
                    updateCartItemQuantity($itemId, intval($quantity));
                }
                break;
                
            case 'remove':
                if (isset($_POST['item_id'])) {
                    removeFromCart($_POST['item_id']);
                }
                break;
                
            case 'clear':
                clearCart($cartId);
                break;
        }
        
        header('Location: cart.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Корзина - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <div class="cart-container">
            <h1>Корзина покупок</h1>
            
            <?php if (empty($cartItems)): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h2>Ваша корзина пуста</h2>
                <p>Добавьте товары из каталога, чтобы сделать покупку</p>
                <a href="catalog.php" class="btn btn-primary">Перейти в каталог</a>
            </div>
            <?php else: ?>
            <form method="POST" class="cart-form">
                <div class="cart-items">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Товар</th>
                                <th>Цена</th>
                                <th>Размер</th>
                                <th>Количество</th>
                                <th>Сумма</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $item): ?>
                            <tr class="cart-item" data-item-id="<?= $item['id'] ?>">
                                <td class="cart-item-product">
                                    <div class="product-info">
                                        <div class="product-image">
                                            <img src="<?= $item['image'] ?: 'assets/images/no-image.jpg' ?>" 
                                                 alt="<?= htmlspecialchars($item['name']) ?>">
                                        </div>
                                        <div class="product-details">
                                            <h3>
                                                <a href="product.php?id=<?= $item['product_id'] ?>">
                                                    <?= htmlspecialchars($item['name']) ?>
                                                </a>
                                            </h3>
                                            <p class="product-brand"><?= htmlspecialchars($item['brand']) ?></p>
                                            <p class="product-stock <?= $item['stock'] > 0 ? 'in-stock' : 'out-of-stock' ?>">
                                                <?= $item['stock'] > 0 ? 'В наличии' : 'Нет в наличии' ?>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="cart-item-price">
                                    <span class="price"><?= formatPrice($item['price']) ?> ₽</span>
                                </td>
                                <td class="cart-item-size">
                                    <span class="size"><?= htmlspecialchars($item['size']) ?></span>
                                </td>
                                <td class="cart-item-quantity">
                                    <div class="quantity-control">
                                        <button type="button" class="quantity-btn minus">-</button>
                                        <input type="number" name="quantity[<?= $item['id'] ?>]" 
                                               value="<?= $item['quantity'] ?>" 
                                               min="1" max="<?= min(10, $item['stock']) ?>" 
                                               class="quantity-input">
                                        <button type="button" class="quantity-btn plus">+</button>
                                    </div>
                                </td>
                                <td class="cart-item-total">
                                    <span class="total"><?= formatPrice($item['price'] * $item['quantity']) ?> ₽</span>
                                </td>
                                <td class="cart-item-actions">
                                    <button type="submit" name="action" value="remove" 
                                            form="remove-form-<?= $item['id'] ?>" 
                                            class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <form id="remove-form-<?= $item['id'] ?>" method="POST" style="display: none;">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="cart-summary">
                    <div class="summary-section">
                        <h3>Итого</h3>
                        <div class="summary-row">
                            <span>Товары (<?= array_sum(array_column($cartItems, 'quantity')) ?> шт.)</span>
                            <span><?= formatPrice($cartTotal) ?> ₽</span>
                        </div>
                        <div class="summary-row">
                            <span>Доставка</span>
                            <span>Рассчитывается при оформлении</span>
                        </div>
                        <div class="summary-divider"></div>
                        <div class="summary-row total-row">
                            <span>Общая сумма</span>
                            <span class="total-amount"><?= formatPrice($cartTotal) ?> ₽</span>
                        </div>
                    </div>
                    
                    <div class="cart-actions">
                        <button type="submit" name="action" value="update" class="btn btn-outline">
                            <i class="fas fa-sync-alt"></i> Обновить корзину
                        </button>
                        <button type="submit" name="action" value="clear" 
                                onclick="return confirm('Очистить всю корзину?')" 
                                class="btn btn-danger">
                            <i class="fas fa-trash"></i> Очистить корзину
                        </button>
                        <a href="checkout.php" class="btn btn-primary btn-checkout">
                            <i class="fas fa-credit-card"></i> Оформить заказ
                        </a>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($cartItems)): ?>
        <div class="cart-recommendations">
            <h2>Рекомендуем также</h2>
            <div class="products-grid">
                <?php
                // Получаем рекомендованные товары
                $recommendedStmt = $db->query("
                    SELECT p.*, pi.image_url as main_image 
                    FROM products p 
                    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1 
                    WHERE p.status = 'active' 
                    ORDER BY RANDOM() 
                    LIMIT 4
                ");
                $recommendedProducts = $recommendedStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($recommendedProducts as $product):
                ?>
                <div class="product-card">
                    <div class="product-image">
                        <img src="<?= $product['main_image'] ?: 'assets/images/no-image.jpg' ?>" 
                             alt="<?= htmlspecialchars($product['name']) ?>">
                    </div>
                    <div class="product-info">
                        <h3>
                            <a href="product.php?id=<?= $product['id'] ?>">
                                <?= htmlspecialchars($product['name']) ?>
                            </a>
                        </h3>
                        <p class="product-brand"><?= htmlspecialchars($product['brand']) ?></p>
                        <div class="product-price"><?= formatPrice($product['price']) ?> ₽</div>
                        <a href="product.php?id=<?= $product['id'] ?>" class="btn btn-small">Подробнее</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/cart.js"></script>
    <script>
    // Управление количеством
    document.querySelectorAll('.quantity-btn').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('.cart-item');
            const input = row.querySelector('.quantity-input');
            const max = parseInt(input.max);
            const min = parseInt(input.min);
            let value = parseInt(input.value);
            
            if (this.classList.contains('minus') && value > min) {
                value--;
            } else if (this.classList.contains('plus') && value < max) {
                value++;
            }
            
            input.value = value;
            updateRowTotal(row);
        });
    });
    
    // Обновление суммы при изменении количества
    document.querySelectorAll('.quantity-input').forEach(input => {
        input.addEventListener('change', function() {
            const row = this.closest('.cart-item');
            updateRowTotal(row);
        });
    });
    
    function updateRowTotal(row) {
        const price = parseFloat(row.querySelector('.price').textContent.replace(/[^\d.]/g, ''));
        const quantity = parseInt(row.querySelector('.quantity-input').value);
        const totalElement = row.querySelector('.total');
        
        if (!isNaN(price) && !isNaN(quantity)) {
            const total = price * quantity;
            totalElement.textContent = formatPrice(total) + ' ₽';
        }
    }
    
    function formatPrice(price) {
        return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }
    
    // Подтверждение удаления
    document.querySelectorAll('.btn-danger').forEach(button => {
        if (button.type === 'submit' && button.value === 'remove') {
            button.addEventListener('click', function(e) {
                if (!confirm('Удалить товар из корзины?')) {
                    e.preventDefault();
                }
            });
        }
    });
    </script>
</body>
</html>

<style>
.cart-container {
    background: white;
    border-radius: 8px;
    padding: 30px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.05);
}

.cart-container h1 {
    margin-bottom: 30px;
    color: #333;
}

.empty-cart {
    text-align: center;
    padding: 60px 20px;
}

.empty-cart i {
    font-size: 64px;
    color: #dee2e6;
    margin-bottom: 20px;
}

.empty-cart h2 {
    margin-bottom: 10px;
    color: #666;
}

.empty-cart p {
    color: #999;
    margin-bottom: 30px;
}

.cart-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
}

.cart-table th {
    background: #f8f9fa;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

.cart-table td {
    padding: 20px 15px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

.cart-item-product .product-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.cart-item-product .product-image {
    width: 80px;
    height: 80px;
    border-radius: 4px;
    overflow: hidden;
    flex-shrink: 0;
}

.cart-item-product .product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cart-item-product .product-details h3 {
    margin: 0 0 5px 0;
    font-size: 1rem;
}

.cart-item-product .product-details h3 a {
    color: #333;
    text-decoration: none;
}

.cart-item-product .product-details h3 a:hover {
    color: #007bff;
}

.cart-item-product .product-brand {
    color: #666;
    font-size: 0.9rem;
    margin: 0 0 5px 0;
}

.cart-item-product .product-stock {
    font-size: 0.8rem;
    font-weight: 500;
}

.cart-item-product .in-stock {
    color: #28a745;
}

.cart-item-product .out-of-stock {
    color: #dc3545;
}

.cart-item-price .price {
    font-weight: 500;
    color: #333;
}

.cart-item-size .size {
    padding: 6px 12px;
    background: #f8f9fa;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
}

.cart-item-quantity .quantity-control {
    display: flex;
    align-items: center;
    gap: 5px;
}

.cart-item-quantity .quantity-btn {
    width: 32px;
    height: 32px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cart-item-quantity .quantity-btn:hover {
    background: #f8f9fa;
}

.cart-item-quantity .quantity-input {
    width: 50px;
    height: 32px;
    text-align: center;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.9rem;
}

.cart-item-total .total {
    font-weight: bold;
    color: #007bff;
    font-size: 1.1rem;
}

.cart-item-actions .btn-sm {
    padding: 6px 10px;
    font-size: 0.8rem;
}

.cart-summary {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 25px;
    margin-top: 30px;
}

.summary-section h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    color: #666;
}

.summary-divider {
    height: 1px;
    background: #dee2e6;
    margin: 20px 0;
}

.total-row {
    font-size: 1.2rem;
    font-weight: bold;
    color: #333;
}

.total-amount {
    color: #007bff;
    font-size: 1.5rem;
}

.cart-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
}

.btn-checkout {
    padding: 15px 30px;
    font-size: 1.1rem;
}

.cart-recommendations {
    margin-top: 60px;
    padding-top: 40px;
    border-top: 1px solid #eee;
}

.cart-recommendations h2 {
    margin-bottom: 30px;
    color: #333;
}

@media (max-width: 768px) {
    .cart-table {
        display: block;
        overflow-x: auto;
    }
    
    .cart-table th,
    .cart-table td {
        white-space: nowrap;
    }
    
    .cart-actions {
        flex-direction: column;
        gap: 15px;
    }
    
    .cart-actions .btn {
        width: 100%;
        text-align: center;
    }
}
</style>