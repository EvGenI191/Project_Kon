<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Проверка авторизации
requireLogin();

$db = Database::connect();

// Получаем ID конкретного заказа или список заказов
$orderId = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];

if ($orderId) {
    // Просмотр конкретного заказа
    $stmt = $db->prepare("
        SELECT o.*, 
               u.name as customer_name,
               u.email as customer_email,
               u.phone as customer_phone
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        header('Location: orders.php');
        exit;
    }
    
    // Получаем товары заказа
    $itemsStmt = $db->prepare("
        SELECT oi.*, p.image_url as product_image
        FROM order_items oi
        LEFT JOIN product_images p ON oi.product_id = p.product_id AND p.is_main = 1
        WHERE oi.order_id = ?
    ");
    $itemsStmt->execute([$orderId]);
    $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Список всех заказов пользователя
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // Получаем заказы
    $ordersStmt = $db->prepare("
        SELECT * FROM orders 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $ordersStmt->bindValue(1, $userId, PDO::PARAM_INT);
    $ordersStmt->bindValue(2, $limit, PDO::PARAM_INT);
    $ordersStmt->bindValue(3, $offset, PDO::PARAM_INT);
    $ordersStmt->execute();
    $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Общее количество заказов
    $countStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $totalOrders = $countStmt->fetchColumn();
    $totalPages = ceil($totalOrders / $limit);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $orderId ? 'Заказ #' . $order['order_number'] : 'Мои заказы' ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <div class="orders-container">
            <!-- Хлебные крошки -->
            <nav class="breadcrumbs">
                <a href="index.php">Главная</a>
                <i class="fas fa-chevron-right"></i>
                <a href="profile.php">Личный кабинет</a>
                <i class="fas fa-chevron-right"></i>
                <span><?= $orderId ? 'Заказ #' . $order['order_number'] : 'Мои заказы' ?></span>
            </nav>
            
            <?php if ($orderId): ?>
            <!-- Детали заказа -->
            <div class="order-detail">
                <div class="order-header">
                    <div class="order-title">
                        <h1>Заказ #<?= $order['order_number'] ?></h1>
                        <p class="order-date">от <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></p>
                    </div>
                    
                    <div class="order-status-section">
                        <span class="status-badge status-<?= $order['status'] ?>">
                            <?php
                            $statuses = [
                                'created' => 'Оформлен',
                                'paid' => 'Оплачен',
                                'processing' => 'В обработке',
                                'shipped' => 'Отправлен',
                                'delivered' => 'Доставлен',
                                'cancelled' => 'Отменен'
                            ];
                            echo $statuses[$order['status']] ?? $order['status'];
                            ?>
                        </span>
                    </div>
                </div>
                
                <div class="order-content">
                    <!-- Информация о заказе -->
                    <div class="order-info">
                        <h2>Информация о заказе</h2>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Номер заказа:</label>
                                <span>#<?= $order['order_number'] ?></span>
                            </div>
                            <div class="info-item">
                                <label>Дата оформления:</label>
                                <span><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></span>
                            </div>
                            <div class="info-item">
                                <label>Сумма заказа:</label>
                                <span class="price"><?= formatPrice($order['total_amount']) ?> ₽</span>
                            </div>
                            <div class="info-item">
                                <label>Способ оплаты:</label>
                                <span>
                                    <?= $order['payment_method'] == 'cash' ? 'Наличными при получении' : 
                                       ($order['payment_method'] == 'card' ? 'Банковской картой онлайн' : 'Банковский перевод') ?>
                                </span>
                            </div>
                            <?php if ($order['shipping_address']): ?>
                            <div class="info-item full-width">
                                <label>Адрес доставки:</label>
                                <span><?= htmlspecialchars($order['shipping_address']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($order['notes']): ?>
                            <div class="info-item full-width">
                                <label>Комментарий:</label>
                                <span><?= htmlspecialchars($order['notes']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Товары в заказе -->
                    <div class="order-items">
                        <h2>Состав заказа</h2>
                        
                        <div class="items-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Товар</th>
                                        <th>Размер</th>
                                        <th>Количество</th>
                                        <th>Цена</th>
                                        <th>Сумма</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td class="item-product">
                                            <div class="product-info">
                                                <?php if ($item['product_image']): ?>
                                                <div class="product-image">
                                                    <img src="<?= $item['product_image'] ?>" 
                                                         alt="<?= htmlspecialchars($item['product_name']) ?>">
                                                </div>
                                                <?php endif; ?>
                                                <div class="product-details">
                                                    <h4><?= htmlspecialchars($item['product_name']) ?></h4>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="item-size"><?= htmlspecialchars($item['size']) ?></td>
                                        <td class="item-quantity"><?= $item['quantity'] ?></td>
                                        <td class="item-price"><?= formatPrice($item['price']) ?> ₽</td>
                                        <td class="item-total"><?= formatPrice($item['price'] * $item['quantity']) ?> ₽</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="total-label">Итого:</td>
                                        <td class="total-amount"><?= formatPrice($order['total_amount']) ?> ₽</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Контактная информация -->
                    <div class="customer-info">
                        <h2>Контактная информация</h2>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <label>ФИО:</label>
                                <span><?= htmlspecialchars($order['customer_name']) ?></span>
                            </div>
                            <div class="info-item">
                                <label>Email:</label>
                                <span><?= htmlspecialchars($order['customer_email']) ?></span>
                            </div>
                            <div class="info-item">
                                <label>Телефон:</label>
                                <span><?= htmlspecialchars($order['customer_phone']) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Действия с заказом -->
                    <div class="order-actions">
                        <?php if ($order['status'] == 'created'): ?>
                        <button class="btn btn-primary">
                            <i class="fas fa-credit-card"></i> Оплатить заказ
                        </button>
                        <?php endif; ?>
                        
                        <?php if (in_array($order['status'], ['created', 'paid', 'processing'])): ?>
                        <button class="btn btn-danger" id="cancel-order">
                            <i class="fas fa-times"></i> Отменить заказ
                        </button>
                        <?php endif; ?>
                        
                        <a href="orders.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> К списку заказов
                        </a>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Список заказов -->
            <div class="orders-list">
                <h1>Мои заказы</h1>
                
                <?php if (empty($orders)): ?>
                <div class="empty-orders">
                    <i class="fas fa-shopping-bag"></i>
                    <h2>У вас пока нет заказов</h2>
                    <p>Сделайте свой первый заказ в нашем магазине</p>
                    <a href="catalog.php" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i> Перейти в каталог
                    </a>
                </div>
                <?php else: ?>
                <div class="orders-table">
                    <table>
                        <thead>
                            <tr>
                                <th>№ заказа</th>
                                <th>Дата</th>
                                <th>Сумма</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td class="order-number">
                                    <strong>#<?= $order['order_number'] ?></strong>
                                </td>
                                <td class="order-date">
                                    <?= date('d.m.Y', strtotime($order['created_at'])) ?>
                                </td>
                                <td class="order-total">
                                    <?= formatPrice($order['total_amount']) ?> ₽
                                </td>
                                <td class="order-status">
                                    <span class="status-badge status-<?= $order['status'] ?>">
                                        <?php
                                        $statuses = [
                                            'created' => 'Оформлен',
                                            'paid' => 'Оплачен',
                                            'processing' => 'В обработке',
                                            'shipped' => 'Отправлен',
                                            'delivered' => 'Доставлен',
                                            'cancelled' => 'Отменен'
                                        ];
                                        echo $statuses[$order['status']] ?? $order['status'];
                                        ?>
                                    </span>
                                </td>
                                <td class="order-actions">
                                    <a href="orders.php?id=<?= $order['id'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> Подробнее
                                    </a>
                                    <?php if ($order['status'] == 'created'): ?>
                                    <button class="btn btn-primary btn-sm">
                                        <i class="fas fa-credit-card"></i> Оплатить
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Пагинация -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="pagination-link">
                        <i class="fas fa-chevron-left"></i> Назад
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                        <a href="?page=<?= $i ?>" class="pagination-link <?= $i == $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                        <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                        <span class="pagination-dots">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="pagination-link">
                        Далее <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
    // Отмена заказа
    document.getElementById('cancel-order')?.addEventListener('click', function() {
        if (confirm('Вы уверены, что хотите отменить заказ?')) {
            // Здесь должен быть AJAX запрос для отмены заказа
            alert('Запрос на отмену заказа отправлен. Мы свяжемся с вами для подтверждения.');
        }
    });
    
    // Печать заказа
    document.getElementById('print-order')?.addEventListener('click', function() {
        window.print();
    });
    </script>
</body>
</html>

<style>
.orders-container {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.order-detail .order-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #dee2e6;
}

.order-title h1 {
    margin: 0 0 5px 0;
    color: #333;
}

.order-date {
    color: #666;
    margin: 0;
}

.order-status-section .status-badge {
    font-size: 1rem;
    padding: 8px 16px;
}

.order-content {
    display: flex;
    flex-direction: column;
    gap: 40px;
}

.order-info h2,
.order-items h2,
.customer-info h2 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
    font-size: 1.3rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    background: #f8f9fa;
    padding: 25px;
    border-radius: 8px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.info-item.full-width {
    grid-column: 1 / -1;
}

.info-item label {
    font-weight: 500;
    color: #666;
    font-size: 0.9rem;
}

.info-item span {
    color: #333;
    font-size: 1rem;
}

.info-item .price {
    color: #007bff;
    font-weight: bold;
    font-size: 1.1rem;
}

.items-table {
    overflow-x: auto;
}

.items-table table {
    width: 100%;
    border-collapse: collapse;
}

.items-table th {
    background: #f8f9fa;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

.items-table td {
    padding: 15px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

.item-product .product-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.product-image {
    width: 60px;
    height: 60px;
    border-radius: 4px;
    overflow: hidden;
    flex-shrink: 0;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-details h4 {
    margin: 0;
    color: #333;
    font-size: 1rem;
}

.item-size {
    font-weight: 500;
}

.item-quantity {
    text-align: center;
}

.item-price,
.item-total {
    text-align: right;
    font-weight: 500;
}

.total-label {
    text-align: right;
    font-weight: bold;
    padding: 20px 15px;
}

.total-amount {
    text-align: right;
    font-size: 1.2rem;
    font-weight: bold;
    color: #007bff;
    padding: 20px 15px;
}

.order-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
}

.empty-orders {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.empty-orders i {
    font-size: 64px;
    color: #dee2e6;
    margin-bottom: 20px;
}

.empty-orders h2 {
    margin-bottom: 10px;
    color: #666;
}

.empty-orders p {
    margin-bottom: 30px;
}

.orders-table {
    overflow-x: auto;
    margin-bottom: 30px;
}

.orders-table table {
    width: 100%;
    border-collapse: collapse;
}

.orders-table th {
    background: #f8f9fa;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

.orders-table td {
    padding: 15px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

.order-number strong {
    color: #333;
}

.order-total {
    font-weight: 500;
    color: #007bff;
}

.order-actions {
    display: flex;
    gap: 10px;
}

@media (max-width: 768px) {
    .order-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .order-actions {
        flex-direction: column;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .orders-table {
        display: block;
        overflow-x: auto;
    }
}
</style>