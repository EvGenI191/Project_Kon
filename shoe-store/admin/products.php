<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireManager();

$db = Database::connect();

// Обработка действий
if (isset($_GET['action'])) {
    $productId = $_GET['id'] ?? 0;
    
    switch ($_GET['action']) {
        case 'delete':
            $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $_SESSION['message'] = 'Товар успешно удален';
            break;
            
        case 'toggle_status':
            $stmt = $db->prepare("UPDATE products SET status = CASE WHEN status = 'active' THEN 'hidden' ELSE 'active' END WHERE id = ?");
            $stmt->execute([$productId]);
            $_SESSION['message'] = 'Статус товара изменен';
            break;
    }
    
    header('Location: products.php');
    exit;
}

// Фильтры
$where = [];
$params = [];

if (isset($_GET['category']) && $_GET['category']) {
    $where[] = "category = ?";
    $params[] = $_GET['category'];
}

if (isset($_GET['brand']) && $_GET['brand']) {
    $where[] = "brand = ?";
    $params[] = $_GET['brand'];
}

if (isset($_GET['status']) && $_GET['status']) {
    $where[] = "status = ?";
    $params[] = $_GET['status'];
}

if (isset($_GET['search']) && $_GET['search']) {
    $where[] = "(name LIKE ? OR description LIKE ?)";
    $searchTerm = '%' . $_GET['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Сборка запроса
$sql = "SELECT p.*, 
               COUNT(DISTINCT pi.id) as image_count,
               GROUP_CONCAT(DISTINCT ps.size) as sizes
        FROM products p
        LEFT JOIN product_images pi ON p.id = pi.product_id
        LEFT JOIN product_sizes ps ON p.id = ps.product_id
        " . (count($where) ? "WHERE " . implode(" AND ", $where) : "") . "
        GROUP BY p.id
        ORDER BY p.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение уникальных категорий и брендов для фильтров
$categories = $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$brands = $db->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL ORDER BY brand")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление товарами - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header-actions">
                <h1>Управление товарами</h1>
                <a href="add_product.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Добавить товар
                </a>
            </div>
            
            <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['message'] ?>
                <?php unset($_SESSION['message']); ?>
            </div>
            <?php endif; ?>
            
            <!-- Фильтры -->
            <div class="filter-section">
                <form method="get" class="filter-form">
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Поиск по названию..." 
                                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <select name="category" class="form-control">
                                <option value="">Все категории</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>" 
                                        <?= ($_GET['category'] ?? '') == $category ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <select name="brand" class="form-control">
                                <option value="">Все бренды</option>
                                <?php foreach ($brands as $brand): ?>
                                <option value="<?= htmlspecialchars($brand) ?>"
                                        <?= ($_GET['brand'] ?? '') == $brand ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($brand) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <select name="status" class="form-control">
                                <option value="">Все статусы</option>
                                <option value="active" <?= ($_GET['status'] ?? '') == 'active' ? 'selected' : '' ?>>Активные</option>
                                <option value="hidden" <?= ($_GET['status'] ?? '') == 'hidden' ? 'selected' : '' ?>>Скрытые</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Фильтровать
                            </button>
                            <a href="products.php" class="btn btn-outline">Сбросить</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Таблица товаров -->
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Изображение</th>
                            <th>Название</th>
                            <th>Категория</th>
                            <th>Бренд</th>
                            <th>Цена</th>
                            <th>Размеры</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td>#<?= $product['id'] ?></td>
                            <td>
                                <?php if ($product['image_count'] > 0): ?>
                                <div class="product-thumb">
                                    <img src="<?= $db->query("SELECT image_url FROM product_images WHERE product_id = {$product['id']} AND is_main = 1 LIMIT 1")->fetchColumn() ?: 'https://via.placeholder.com/50'" ?>"
                                         alt="<?= htmlspecialchars($product['name']) ?>"
                                         width="50" height="50">
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($product['name']) ?></strong><br>
                                <small class="text-muted"><?= mb_substr(strip_tags($product['description']), 0, 50) ?>...</small>
                            </td>
                            <td><?= htmlspecialchars($product['category']) ?></td>
                            <td><?= htmlspecialchars($product['brand']) ?></td>
                            <td><?= number_format($product['price'], 0, '.', ' ') ?> ₽</td>
                            <td><?= $product['sizes'] ?: 'Нет размеров' ?></td>
                            <td>
                                <span class="status-badge status-<?= $product['status'] ?>">
                                    <?= $product['status'] == 'active' ? 'Активен' : 'Скрыт' ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="../product.php?id=<?= $product['id'] ?>" 
                                       class="btn btn-small btn-outline" 
                                       target="_blank"
                                       title="Просмотр">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <a href="edit_product.php?id=<?= $product['id'] ?>" 
                                       class="btn btn-small btn-primary"
                                       title="Редактировать">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <a href="products.php?action=toggle_status&id=<?= $product['id'] ?>" 
                                       class="btn btn-small btn-<?= $product['status'] == 'active' ? 'warning' : 'success' ?>"
                                       title="<?= $product['status'] == 'active' ? 'Скрыть' : 'Активировать' ?>">
                                        <i class="fas fa-<?= $product['status'] == 'active' ? 'eye-slash' : 'eye' ?>"></i>
                                    </a>
                                    
                                    <a href="products.php?action=delete&id=<?= $product['id'] ?>" 
                                       class="btn btn-small btn-danger"
                                       onclick="return confirm('Удалить товар?')"
                                       title="Удалить">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (empty($products)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>Товары не найдены</h3>
                    <p>Попробуйте изменить параметры фильтрации</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/admin.js"></script>
</body>
</html>

<style>
.admin-header-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.filter-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.filter-form .form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 0;
}

.filter-form .form-group {
    flex: 1;
    min-width: 200px;
    margin-bottom: 0;
}

.product-thumb {
    width: 50px;
    height: 50px;
    border-radius: 4px;
    overflow: hidden;
}

.product-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-hidden {
    background: #f8d7da;
    color: #721c24;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.action-buttons .btn-small {
    padding: 4px 8px;
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 20px;
    color: #dee2e6;
}

.empty-state h3 {
    margin-bottom: 10px;
    color: #6c757d;
}

.text-muted {
    color: #6c757d;
}
</style>