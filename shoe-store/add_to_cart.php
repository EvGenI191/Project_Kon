<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/cart.php';

$cart = getCart();
$cartItems = $cart->getItems();
$totalAmount = $cart->getTotalAmount();
$totalCount = $cart->getTotalCount();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Корзина - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #007bff;
            --primary-dark: #0056b3;
            --primary-light: #e7f3ff;
            --secondary: #6c757d;
            --success: #28a745;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --border: #dee2e6;
            --radius: 10px;
            --shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .cart-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .cart-header {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .cart-header h1 {
            font-size: 2.5rem;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .cart-summary {
            background: var(--light);
            padding: 20px;
            border-radius: var(--radius);
            margin-bottom: 30px;
            border: 1px solid var(--border);
        }
        
        .cart-items {
            margin-bottom: 30px;
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            padding: 20px;
            background: white;
            border-radius: var(--radius);
            margin-bottom: 15px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }
        
        .cart-item-image {
            width: 120px;
            height: 120px;
            margin-right: 20px;
        }
        
        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .cart-item-info {
            flex-grow: 1;
        }
        
        .cart-item-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .cart-item-details {
            color: var(--secondary);
            margin-bottom: 10px;
        }
        
        .cart-item-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .cart-item-total {
            font-size: 1.1rem;
            color: var(--dark);
        }
        
        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-btn {
            width: 36px;
            height: 36px;
            border: 1px solid var(--border);
            background: white;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .quantity-input {
            width: 60px;
            text-align: center;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 4px;
        }
        
        .remove-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .cart-total {
            background: white;
            padding: 30px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }
        
        .cart-total h3 {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: var(--dark);
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        
        .total-amount {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .checkout-btn {
            display: block;
            width: 100%;
            padding: 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            text-decoration: none;
            text-align: center;
        }
        
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-cart i {
            font-size: 64px;
            color: var(--border);
            margin-bottom: 20px;
        }
        
        .empty-cart h3 {
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .continue-shopping {
            display: inline-block;
            padding: 12px 30px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: var(--radius);
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .cart-item {
                flex-direction: column;
                text-align: center;
            }
            
            .cart-item-image {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .cart-item-actions {
                justify-content: center;
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="cart-container">
        <div class="cart-header">
            <h1>Корзина</h1>
            <p>Ваши товары готовы к оформлению</p>
        </div>
        
        <?php if (empty($cartItems)): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h3>Ваша корзина пуста</h3>
                <p>Добавьте товары из каталога, чтобы начать покупки</p>
                <a href="catalog.php" class="continue-shopping">
                    <i class="fas fa-arrow-left"></i> Вернуться в каталог
                </a>
            </div>
        <?php else: ?>
            <div class="cart-summary">
                <p>Товаров в корзине: <strong><?= $totalCount ?></strong></p>
                <p>Общая сумма: <strong><?= formatPrice($totalAmount) ?> ₽</strong></p>
            </div>
            
            <div class="cart-items">
                <?php foreach ($cartItems as $item): ?>
                <div class="cart-item" data-item-id="<?= $item['id'] ?>">
                    <div class="cart-item-image">
                        <img src="<?= $item['image_url'] ?: 'assets/images/no-image.jpg' ?>" 
                             alt="<?= htmlspecialchars($item['name']) ?>">
                    </div>
                    
                    <div class="cart-item-info">
                        <h3 class="cart-item-name"><?= htmlspecialchars($item['name']) ?></h3>
                        <div class="cart-item-details">
                            <span>Бренд: <?= htmlspecialchars($item['brand']) ?></span> • 
                            <span>Размер: <?= htmlspecialchars($item['size']) ?></span>
                        </div>
                        <div class="cart-item-price"><?= formatPrice($item['price']) ?> ₽</div>
                    </div>
                    
                    <div class="cart-item-actions">
                        <div class="quantity-control">
                            <button class="quantity-btn minus" onclick="updateQuantity(<?= $item['id'] ?>, -1)">-</button>
                            <input type="number" class="quantity-input" 
                                   value="<?= $item['quantity'] ?>" 
                                   min="1" max="<?= $item['stock_quantity'] ?? 99 ?>"
                                   onchange="updateQuantityInput(<?= $item['id'] ?>, this.value)">
                            <button class="quantity-btn plus" onclick="updateQuantity(<?= $item['id'] ?>, 1)">+</button>
                        </div>
                        
                        <div class="cart-item-total">
                            <?= formatPrice($item['total_price']) ?> ₽
                        </div>
                        
                        <button class="remove-btn" onclick="removeItem(<?= $item['id'] ?>)">
                            <i class="fas fa-trash"></i> Удалить
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="cart-total">
                <h3>Итог заказа</h3>
                
                <div class="total-row">
                    <span>Товары (<?= $totalCount ?> шт.):</span>
                    <span><?= formatPrice($totalAmount) ?> ₽</span>
                </div>
                
                <div class="total-row">
                    <span>Доставка:</span>
                    <span>Бесплатно</span>
                </div>
                
                <div class="total-row" style="border-bottom: none;">
                    <strong>Итого к оплате:</strong>
                    <strong class="total-amount"><?= formatPrice($totalAmount) ?> ₽</strong>
                </div>
                
                <a href="checkout.php" class="checkout-btn">
                    <i class="fas fa-lock"></i> Перейти к оформлению
                </a>
            </div>
        <?php endif; ?>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
    // Глобальный CSRF токен
    const csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    
    // Функция для обновления количества
    async function updateQuantity(itemId, change) {
        const input = document.querySelector(`[data-item-id="${itemId}"] .quantity-input`);
        let newQuantity = parseInt(input.value) + change;
        
        if (newQuantity < 1) newQuantity = 1;
        
        await updateCartItem(itemId, newQuantity);
    }
    
    // Функция для обновления через input
    async function updateQuantityInput(itemId, value) {
        let quantity = parseInt(value);
        if (isNaN(quantity) || quantity < 1) quantity = 1;
        
        await updateCartItem(itemId, quantity);
    }
    
    // Функция удаления товара
    async function removeItem(itemId) {
        if (!confirm('Удалить товар из корзины?')) return;
        
        await updateCartItem(itemId, 0);
    }
    
    // Основная функция обновления корзины
    async function updateCartItem(itemId, quantity) {
        try {
            const response = await fetch('update_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    item_id: itemId,
                    quantity: quantity,
                    csrf_token: csrfToken
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Перезагружаем страницу для обновления данных
                location.reload();
            } else {
                alert(result.message || 'Произошла ошибка');
            }
            
        } catch (error) {
            console.error('Error:', error);
            alert('Ошибка соединения с сервером');
        }
    }
    
    // Обработка нажатия клавиш в поле количества
    document.querySelectorAll('.quantity-input').forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const itemId = this.closest('.cart-item').dataset.itemId;
                updateQuantityInput(itemId, this.value);
            }
        });
    });
    </script>
</body>
</html>