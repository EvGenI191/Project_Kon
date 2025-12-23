<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/cart.php';

// Проверка авторизации
requireLogin();

$db = Database::connect();
$errors = [];
$success = false;

// Получаем корзину пользователя
$cartId = getOrCreateCart($_SESSION['user_id']);
$cartItems = getCartItems($cartId);
$cartTotal = getCartTotal($cartId);

// Проверяем, что корзина не пуста
if (empty($cartItems)) {
    header('Location: cart.php');
    exit;
}

// Получаем информацию о пользователе
$userStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$_SESSION['user_id']]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

// Оформление заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'cash';
    $notes = trim($_POST['notes'] ?? '');
    
    // Валидация
    if (empty($name)) {
        $errors[] = 'Имя обязательно';
    }
    
    if (empty($phone)) {
        $errors[] = 'Телефон обязателен';
    } elseif (!preg_match('/^[\d\s\-\+\(\)]{10,20}$/', $phone)) {
        $errors[] = 'Некорректный номер телефона';
    }
    
    if (empty($email)) {
        $errors[] = 'Email обязателен';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный email';
    }
    
    if (empty($address)) {
        $errors[] = 'Адрес доставки обязателен';
    }
    
    // Проверяем наличие товаров
    foreach ($cartItems as $item) {
        if ($item['stock'] < $item['quantity']) {
            $errors[] = 'Товар "' . htmlspecialchars($item['name']) . '" недоступен в нужном количестве';
        }
    }
    
    // Если нет ошибок, оформляем заказ
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Генерируем номер заказа
            $orderNumber = generateOrderNumber();
            
            // Создаем заказ
            $orderStmt = $db->prepare("
                INSERT INTO orders (user_id, order_number, total_amount, status, 
                                   shipping_address, payment_method, customer_name, 
                                   customer_phone, notes)
                VALUES (?, ?, ?, 'created', ?, ?, ?, ?, ?)
            ");
            $orderStmt->execute([
                $_SESSION['user_id'],
                $orderNumber,
                $cartTotal,
                $address,
                $paymentMethod,
                $name,
                $phone,
                $notes
            ]);
            $orderId = $db->lastInsertId();
            
            // Добавляем товары в заказ
            foreach ($cartItems as $item) {
                $orderItemStmt = $db->prepare("
                    INSERT INTO order_items (order_id, product_id, product_name, 
                                           size, quantity, price)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $orderItemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['name'],
                    $item['size'],
                    $item['quantity'],
                    $item['price']
                ]);
                
                // Обновляем остатки на складе
                $updateStockStmt = $db->prepare("
                    UPDATE product_sizes 
                    SET quantity = quantity - ? 
                    WHERE product_id = ? AND size = ?
                ");
                $updateStockStmt->execute([
                    $item['quantity'],
                    $item['product_id'],
                    $item['size']
                ]);
            }
            
            // Очищаем корзину
            clearCart($cartId);
            
            $db->commit();
            
            // Обновляем информацию о пользователе
            $updateUserStmt = $db->prepare("
                UPDATE users 
                SET name = ?, phone = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $updateUserStmt->execute([$name, $phone, $_SESSION['user_id']]);
            
            $_SESSION['user_name'] = $name;
            
            $success = true;
            $_SESSION['order_number'] = $orderNumber;
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Ошибка при оформлении заказа: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оформление заказа - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <?php if ($success): ?>
        <div class="order-success">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Заказ успешно оформлен!</h1>
            <p>Номер вашего заказа: <strong>#<?= $_SESSION['order_number'] ?></strong></p>
            <p>Мы свяжемся с вами в ближайшее время для подтверждения заказа.</p>
            <div class="success-actions">
                <a href="profile.php" class="btn btn-primary">
                    <i class="fas fa-user"></i> Перейти в личный кабинет
                </a>
                <a href="index.php" class="btn btn-outline">
                    <i class="fas fa-home"></i> На главную
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="checkout-container">
            <h1>Оформление заказа</h1>
            
            <?php if ($errors): ?>
            <div class="alert alert-danger">
                <h3>Ошибки при оформлении заказа:</h3>
                <ul>
                    <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="checkout-form">
                <div class="checkout-layout">
                    <!-- Информация о заказе -->
                    <div class="checkout-summary">
                        <h2>Ваш заказ</h2>
                        
                        <div class="order-items">
                            <?php foreach ($cartItems as $item): ?>
                            <div class="order-item">
                                <div class="item-image">
                                    <img src="<?= $item['image'] ?: 'assets/images/no-image.jpg' ?>" 
                                         alt="<?= htmlspecialchars($item['name']) ?>">
                                </div>
                                <div class="item-details">
                                    <h4><?= htmlspecialchars($item['name']) ?></h4>
                                    <p class="item-meta">
                                        Размер: <?= htmlspecialchars($item['size']) ?> | 
                                        Количество: <?= $item['quantity'] ?>
                                    </p>
                                </div>
                                <div class="item-price">
                                    <?= formatPrice($item['price'] * $item['quantity']) ?> ₽
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="order-total">
                            <div class="total-row">
                                <span>Сумма заказа:</span>
                                <span><?= formatPrice($cartTotal) ?> ₽</span>
                            </div>
                            <div class="total-row">
                                <span>Доставка:</span>
                                <span>Бесплатно</span>
                            </div>
                            <div class="total-row final-total">
                                <span>Итого к оплате:</span>
                                <span class="total-amount"><?= formatPrice($cartTotal) ?> ₽</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Форма оформления -->
                    <div class="checkout-form-fields">
                        <h2>Контактная информация</h2>
                        
                        <div class="form-group">
                            <label for="name">ФИО *</label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['name'] ?? $user['name']) ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Телефон *</label>
                                <input type="tel" id="phone" name="phone" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? $user['email']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Адрес доставки *</label>
                            <textarea id="address" name="address" class="form-control" rows="3" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Способ оплаты</label>
                            <div class="payment-options">
                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="cash" 
                                           <?= ($_POST['payment_method'] ?? 'cash') == 'cash' ? 'checked' : '' ?>>
                                    <div class="payment-option-content">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <span>Наличными при получении</span>
                                    </div>
                                </label>
                                
                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="card" 
                                           <?= ($_POST['payment_method'] ?? '') == 'card' ? 'checked' : '' ?>>
                                    <div class="payment-option-content">
                                        <i class="fas fa-credit-card"></i>
                                        <span>Банковской картой онлайн</span>
                                    </div>
                                </label>
                                
                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="transfer" 
                                           <?= ($_POST['payment_method'] ?? '') == 'transfer' ? 'checked' : '' ?>>
                                    <div class="payment-option-content">
                                        <i class="fas fa-university"></i>
                                        <span>Банковский перевод</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Комментарий к заказу</label>
                            <textarea id="notes" name="notes" class="form-control" rows="3"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" required>
                                <span>Я согласен с <a href="#" target="_blank">правилами обработки персональных данных</a></span>
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-check"></i> Подтвердить заказ
                            </button>
                            <a href="cart.php" class="btn btn-outline">Вернуться в корзину</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/checkout.js"></script>
    <script>
    // Маска для телефона
    document.getElementById('phone').addEventListener('input', function(e) {
        let x = e.target.value.replace(/\D/g, '').match(/(\d{0,1})(\d{0,3})(\d{0,3})(\d{0,2})(\d{0,2})/);
        e.target.value = !x[2] ? x[1] : '+7 (' + x[2] + ') ' + x[3] + (x[4] ? '-' + x[4] : '') + (x[5] ? '-' + x[5] : '');
    });
    
    // Валидация формы
    document.querySelector('.checkout-form').addEventListener('submit', function(e) {
        const phone = document.getElementById('phone').value;
        const phoneRegex = /^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/;
        
        if (!phoneRegex.test(phone)) {
            e.preventDefault();
            alert('Пожалуйста, введите корректный номер телефона в формате +7 (XXX) XXX-XX-XX');
            document.getElementById('phone').focus();
        }
    });
    </script>
</body>
</html>

<style>
.order-success {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.05);
    max-width: 600px;
    margin: 0 auto;
}

.success-icon {
    font-size: 64px;
    color: #28a745;
    margin-bottom: 20px;
}

.order-success h1 {
    margin-bottom: 15px;
    color: #333;
}

.order-success p {
    color: #666;
    margin-bottom: 10px;
    font-size: 1.1rem;
}

.success-actions {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 30px;
}

.checkout-container {
    background: white;
    border-radius: 8px;
    padding: 30px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.05);
}

.checkout-container h1 {
    margin-bottom: 30px;
    color: #333;
}

.alert {
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-danger h3 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 1.2rem;
}

.alert-danger ul {
    margin: 0;
    padding-left: 20px;
}

.checkout-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
}

.checkout-summary {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 25px;
}

.checkout-summary h2 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
}

.order-items {
    margin-bottom: 30px;
}

.order-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid #dee2e6;
}

.order-item:last-child {
    border-bottom: none;
}

.item-image {
    width: 60px;
    height: 60px;
    border-radius: 4px;
    overflow: hidden;
    flex-shrink: 0;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.item-details {
    flex: 1;
}

.item-details h4 {
    margin: 0 0 5px 0;
    font-size: 0.9rem;
    color: #333;
}

.item-meta {
    margin: 0;
    color: #666;
    font-size: 0.8rem;
}

.item-price {
    font-weight: 500;
    color: #007bff;
}

.order-total {
    padding-top: 20px;
    border-top: 2px solid #dee2e6;
}

.total-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    color: #666;
}

.total-row.final-total {
    font-size: 1.2rem;
    font-weight: bold;
    color: #333;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #dee2e6;
}

.total-amount {
    color: #007bff;
    font-size: 1.5rem;
}

.checkout-form-fields h2 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
}

.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.form-row .form-group {
    flex: 1;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
    transition: border-color 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0,123,255,0.25);
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

.payment-options {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.payment-option {
    display: block;
    cursor: pointer;
}

.payment-option input {
    display: none;
}

.payment-option-content {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    border: 2px solid #ddd;
    border-radius: 8px;
    transition: all 0.3s;
}

.payment-option input:checked + .payment-option-content {
    border-color: #007bff;
    background: #e3f2fd;
}

.payment-option-content i {
    font-size: 1.5rem;
    color: #007bff;
}

.payment-option-content span {
    font-weight: 500;
    color: #333;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    color: #666;
}

.checkbox-label input {
    margin: 0;
}

.form-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.btn-lg {
    padding: 15px 30px;
    font-size: 1.1rem;
}

@media (max-width: 992px) {
    .checkout-layout {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
}
</style>