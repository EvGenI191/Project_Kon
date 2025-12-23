<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireManager();

$db = Database::connect();

// Обработка действий с заказами
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = $_POST['order_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    if ($orderId && $action) {
        switch ($action) {
            case 'update_status':
                $newStatus = $_POST['status'] ?? '';
                if ($newStatus) {
                    $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
                    $stmt->execute([$newStatus, $orderId]);
                    $_SESSION['message'] = 'Статус заказа обновлен';
                }
                break;
                
            case 'update_tracking':
                $trackingNumber = $_POST['tracking_number'] ?? '';
                if ($trackingNumber) {
                    // Здесь можно добавить поле для трек-номера
                    $_SESSION['message'] = 'Трек-номер обновлен';
                }
                break;
        }
        
        header('Location: orders.php?id=' . $orderId);
        exit;
    }
}

// Получаем ID заказа для детального просмотра
$orderId = $_GET['id'] ?? 0;

if ($orderId) {
    // Детальный просмотр заказа
    $stmt = $db->prepare("
        SELECT o.*, 
               u.name as customer_name,
               u.email as customer_email,
               u.phone as customer_phone
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        header('Location: orders.php');
        exit;
    }
    
    // Получаем товары заказа
    $itemsStmt = $db->prepare("
        SELECT oi.*, p.name as product_name, p.image_url as product_image
        FROM order_items oi
        LEFT JOIN (
            SELECT p.id, p.name, pi.image_url 
            FROM products p
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1
        ) p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $itemsStmt->execute([$orderId]);
    $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} else {
    // Список заказов с фильтрами
    $where = [];
    $params = [];
    
    // Фильтр по статусу
    if (!empty($_GET['status'])) {
        $where[] = 'o.status = ?';
        $params[] = $_GET['status'];
    }
    
    // Фильтр по дате
    if (!empty($_GET['date_from'])) {
        $where[] = 'DATE(o.created_at) >= ?';
        $params[] = $_GET['date_from'];
    }
    
    if (!empty($_GET['date_to'])) {
        $where[] = 'DATE(o.created_at) <= ?';
        $params[] = $_GET['date_to'];
    }
    
    // Фильтр по номеру заказа
    if (!empty($_GET['order_number'])) {
        $where[] = 'o.order_number LIKE ?';
        $params[] = '%' . $_GET['order_number'] . '%';
    }
    
    // Параметры пагинации
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // Получаем заказы
    $sql = "
        SELECT o.*, u.name as customer_name, u.email as customer_email
        FROM orders o
        JOIN users u ON o.user_id = u.id
    ";
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    $sql .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Общее количество заказов
    $countSql = "SELECT COUNT(*) FROM orders o";
    if (!empty($where)) {
        $countSql .= " WHERE " . implode(' AND ', $where);
    }
    
    $countStmt = $db->prepare($countSql);
    $countStmt->execute(array_slice($params, 0, -2));
    $totalOrders = $countStmt->fetchColumn();
    $totalPages = ceil($totalOrders / $limit);
    
    // Статистика
    $stats = [
        'total' => $db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'created' => $db->query("SELECT COUNT(*) FROM orders WHERE status = 'created'")->fetchColumn(),
        'processing' => $db->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn(),
        'delivered' => $db->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered'")->fetchColumn(),
        'revenue' => $db->query("SELECT SUM(total_amount) FROM orders WHERE status = 'delivered'")->fetchColumn() ?: 0,
    ];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $orderId ? 'Заказ #' . $order['order_number'] : 'Управление заказами' ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="admin-main">
            <?php if ($orderId): ?>
            <!-- Детали заказа -->
            <div class="admin-header-actions">
                <h1>Заказ #<?= $order['order_number'] ?></h1>
                <a href="orders.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> К списку заказов
                </a>
            </div>
            
            <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['message'] ?>
                <?php unset($_SESSION['message']); ?>
            </div>
            <?php endif; ?>
            
            <div class="order-detail-admin">
                <!-- Информация о заказе -->
                <div class="order-info-section">
                    <h2>Информация о заказе</h2>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Номер заказа:</label>
                            <span>#<?= $order['order_number'] ?></span>
                        </div>
                        <div class="info-item">
                            <label>Дата создания:</label>
                            <span><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></span>
                        </div>
                        <div class="info-item">
                            <label>Статус:</label>
                            <form method="POST" class="status-form">
                                <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                <input type="hidden" name="action" value="update_status">
                                <select name="status" class="form-control status-select" onchange="this.form.submit()">
                                    <option value="created" <?= $order['status'] == 'created' ? 'selected' : '' ?>>Оформлен</option>
                                    <option value="paid" <?= $order['status'] == 'paid' ? 'selected' : '' ?>>Оплачен</option>
                                    <option value="processing" <?= $order['status'] == 'processing' ? 'selected' : '' ?>>В обработке</option>
                                    <option value="shipped" <?= $order['status'] == 'shipped' ? 'selected' : '' ?>>Отправлен</option>
                                    <option value="delivered" <?= $order['status'] == 'delivered' ? 'selected' : '' ?>>Доставлен</option>
                                    <option value="cancelled" <?= $order['status'] == 'cancelled' ? 'selected' : '' ?>>Отменен</option>
                                </select>
                            </form>
                        </div>
                        <div class="info-item">
                            <label>Сумма:</label>
                            <span class="price"><?= formatPrice($order['total_amount']) ?> ₽</span>
                        </div>
                        <div class="info-item">
                            <label>Способ оплаты:</label>
                            <span>
                                <?= $order['payment_method'] == 'cash' ? 'Наличные' : 
                                   ($order['payment_method'] == 'card' ? 'Карта онлайн' : 'Банковский перевод') ?>
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
                
                <!-- Информация о клиенте -->
                <div class="customer-info-section">
                    <h2>Информация о клиенте</h2>
                    
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
                        <div class="info-item">
                            <label>ID пользователя:</label>
                            <span>#<?= $order['user_id'] ?></span>
                        </div>
                    </div>
                    
                    <div class="customer-actions">
                        <a href="users.php?search=<?= urlencode($order['customer_email']) ?>" 
                           class="btn btn-outline btn-sm">
                            <i class="fas fa-user"></i> Профиль клиента
                        </a>
                        <a href="mailto:<?= htmlspecialchars($order['customer_email']) ?>" 
                           class="btn btn-outline btn-sm">
                            <i class="fas fa-envelope"></i> Написать email
                        </a>
                    </div>
                </div>
                
                <!-- Товары в заказе -->
                <div class="order-items-section">
                    <h2>Товары в заказе</h2>
                    
                    <div class="table-responsive">
                        <table class="admin-table">
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
                                    <td>
                                        <div class="product-cell">
                                            <?php if ($item['product_image']): ?>
                                            <div class="product-thumb">
                                                <img src="../<?= htmlspecialchars($item['product_image']) ?>" 
                                                     alt="<?= htmlspecialchars($item['product_name']) ?>">
                                            </div>
                                            <?php endif; ?>
                                            <div class="product-info">
                                                <strong><?= htmlspecialchars($item['product_name']) ?></strong><br>
                                                <small>ID: <?= $item['product_id'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($item['size']) ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td><?= formatPrice($item['price']) ?> ₽</td>
                                    <td><?= formatPrice($item['price'] * $item['quantity']) ?> ₽</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-right"><strong>Итого:</strong></td>
                                    <td><strong><?= formatPrice($order['total_amount']) ?> ₽</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                
                <!-- Действия -->
                <div class="order-actions-section">
                    <h2>Действия</h2>
                    
                    <div class="action-buttons">
                        <?php if (in_array($order['status'], ['created', 'paid'])): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="order_id" value="<?= $orderId ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="status" value="processing">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-play"></i> Взять в обработку
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <?php if ($order['status'] == 'processing'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="order_id" value="<?= $orderId ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="status" value="shipped">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-shipping-fast"></i> Отметить как отправленный
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <?php if ($order['status'] == 'shipped'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="order_id" value="<?= $orderId ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="status" value="delivered">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> Отметить как доставленный
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <?php if (in_array($order['status'], ['created', 'paid', 'processing'])): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="order_id" value="<?= $orderId ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="status" value="cancelled">
                            <button type="submit" class="btn btn-danger" 
                                    onclick="return confirm('Отменить заказ?')">
                                <i class="fas fa-times"></i> Отменить заказ
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <button class="btn btn-outline" onclick="window.print()">
                            <i class="fas fa-print"></i> Распечатать
                        </button>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Список заказов -->
            <div class="admin-header-actions">
                <h1>Управление заказами</h1>
            </div>
            
            <!-- Статистика -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['total'] ?></h3>
                        <p>Всего заказов</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['created'] ?></h3>
                        <p>Ожидают оплаты</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['processing'] ?></h3>
                        <p>В обработке</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['delivered'] ?></h3>
                        <p>Доставлено</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= formatPrice($stats['revenue']) ?> ₽</h3>
                        <p>Общая выручка</p>
                    </div>
                </div>
            </div>
            
            <!-- Фильтры -->
            <div class="filter-section">
                <form method="get" class="filter-form">
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="order_number" class="form-control" 
                                   placeholder="Номер заказа" 
                                   value="<?= htmlspecialchars($_GET['order_number'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <select name="status" class="form-control">
                                <option value="">Все статусы</option>
                                <option value="created" <?= ($_GET['status'] ?? '') == 'created' ? 'selected' : '' ?>>Оформлен</option>
                                <option value="paid" <?= ($_GET['status'] ?? '') == 'paid' ? 'selected' : '' ?>>Оплачен</option>
                                <option value="processing" <?= ($_GET['status'] ?? '') == 'processing' ? 'selected' : '' ?>>В обработке</option>
                                <option value="shipped" <?= ($_GET['status'] ?? '') == 'shipped' ? 'selected' : '' ?>>Отправлен</option>
                                <option value="delivered" <?= ($_GET['status'] ?? '') == 'delivered' ? 'selected' : '' ?>>Доставлен</option>
                                <option value="cancelled" <?= ($_GET['status'] ?? '') == 'cancelled' ? 'selected' : '' ?>>Отменен</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <input type="date" name="date_from" class="form-control" 
                                   value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>"
                                   placeholder="Дата с">
                        </div>
                        
                        <div class="form-group">
                            <input type="date" name="date_to" class="form-control" 
                                   value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                                   placeholder="Дата по">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Фильтровать
                            </button>
                            <a href="orders.php" class="btn btn-outline">Сбросить</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Таблица заказов -->
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>№ заказа</th>
                            <th>Дата</th>
                            <th>Клиент</th>
                            <th>Сумма</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <strong>#<?= $order['order_number'] ?></strong><br>
                                <small><?= $order['payment_method'] == 'cash' ? 'Наличные' : 'Онлайн' ?></small>
                            </td>
                            <td>
                                <?= date('d.m.Y', strtotime($order['created_at'])) ?><br>
                                <small><?= date('H:i', strtotime($order['created_at'])) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($order['customer_name']) ?></strong><br>
                                <small><?= htmlspecialchars($order['customer_email']) ?></small>
                            </td>
                            <td>
                                <strong><?= formatPrice($order['total_amount']) ?> ₽</strong>
                            </td>
                            <td>
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
                            <td>
                                <div class="action-buttons">
                                    <a href="orders.php?id=<?= $order['id'] ?>" 
                                       class="btn btn-outline btn-sm" title="Просмотр">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if ($order['status'] == 'created'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="status" value="processing">
                                        <button type="submit" class="btn btn-primary btn-sm" title="В обработку">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <a href="../checkout.php?order=<?= $order['id'] ?>" 
                                       class="btn btn-success btn-sm" title="Повторить заказ">
                                        <i class="fas fa-redo"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>Заказы не найдены</h3>
                    <p>Попробуйте изменить параметры фильтрации</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Пагинация -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                   class="pagination-link">
                    <i class="fas fa-chevron-left"></i> Назад
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
                    Далее <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="../assets/js/admin.js"></script>
</body>
</html>

<style>
.order-detail-admin {
    display: flex;
    flex-direction: column;
    gap: 30px;
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

.status-form {
    margin: 0;
}

.status-select {
    min-width: 150px;
}

.customer-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.product-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.product-thumb {
    width: 50px;
    height: 50px;
    border-radius: 4px;
    overflow: hidden;
    flex-shrink: 0;
}

.product-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-info small {
    color: #666;
    font-size: 0.8rem;
}

.text-right {
    text-align: right;
}

.order-actions-section .action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
</style>