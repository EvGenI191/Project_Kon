<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireManager();

// Статистика
$db = Database::connect();

// Общая статистика
$stats = [
    'total_products' => $db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'active_products' => $db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn(),
    'total_users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_orders' => $db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'revenue' => $db->query("SELECT SUM(total_amount) FROM orders WHERE status = 'delivered'")->fetchColumn() ?: 0,
];

// Последние заказы
$recentOrders = $db->query("
    SELECT o.*, u.name as customer_name 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Популярные товары
$popularProducts = $db->query("
    SELECT p.id, p.name, p.price, 
           COUNT(oi.id) as sales_count,
           SUM(oi.quantity) as total_quantity
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    GROUP BY p.id
    ORDER BY sales_count DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <aside class="admin-sidebar">
            <nav class="admin-nav">
                <a href="index.php" class="active"><i class="fas fa-tachometer-alt"></i> Дашборд</a>
                <a href="products.php"><i class="fas fa-shoe-prints"></i> Товары</a>
                <a href="add_product.php"><i class="fas fa-plus-circle"></i> Добавить товар</a>
                <a href="orders.php"><i class="fas fa-shopping-bag"></i> Заказы</a>
                <?php if (isAdmin()): ?>
                <a href="users.php"><i class="fas fa-users"></i> Пользователи</a>
                <?php endif; ?>
                <a href="../index.php"><i class="fas fa-store"></i> Вернуться в магазин</a>
            </nav>
        </aside>
        
        <main class="admin-main">
            <h1>Панель управления</h1>
            <p class="admin-welcome">Добро пожаловать, <?= htmlspecialchars($_SESSION['user_name']) ?>!</p>
            
            <!-- Статистика -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['total_products'] ?></h3>
                        <p>Всего товаров</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['active_products'] ?></h3>
                        <p>Активных товаров</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['total_users'] ?></h3>
                        <p>Пользователей</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($stats['revenue'], 0, '.', ' ') ?> ₽</h3>
                        <p>Общая выручка</p>
                    </div>
                </div>
            </div>
            
            <!-- Последние заказы -->
            <div class="admin-section">
                <h2>Последние заказы</h2>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>№ заказа</th>
                                <th>Клиент</th>
                                <th>Дата</th>
                                <th>Сумма</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($order['order_number']) ?></td>
                                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                <td><?= number_format($order['total_amount'], 0, '.', ' ') ?> ₽</td>
                                <td>
                                    <span class="order-status status-<?= $order['status'] ?>">
                                        <?= getStatusText($order['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="orders.php?view=<?= $order['id'] ?>" class="btn btn-small">Просмотр</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Популярные товары -->
            <div class="admin-section">
                <h2>Популярные товары</h2>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Товар</th>
                                <th>Цена</th>
                                <th>Продано</th>
                                <th>Общая сумма</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($popularProducts as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><?= number_format($product['price'], 0, '.', ' ') ?> ₽</td>
                                <td><?= $product['total_quantity'] ?: 0 ?> шт.</td>
                                <td><?= number_format(($product['price'] * ($product['total_quantity'] ?: 0)), 0, '.', ' ') ?> ₽</td>
                                <td>
                                    <a href="../product.php?id=<?= $product['id'] ?>" class="btn btn-small">Просмотр</a>
                                    <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn btn-small btn-outline">Редактировать</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/admin.js"></script>
</body>
</html>

<?php
function getStatusText($status) {
    $statuses = [
        'created' => 'Оформлен',
        'paid' => 'Оплачен',
        'processing' => 'В обработке',
        'shipped' => 'Отправлен',
        'delivered' => 'Доставлен',
        'cancelled' => 'Отменен'
    ];
    
    return $statuses[$status] ?? $status;
}
?>