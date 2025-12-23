<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

$db = Database::connect();
$response = [];

try {
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'get_sizes':
                // Получение размеров товара
                if (!isset($_GET['product_id'])) {
                    throw new Exception('Не указан ID товара');
                }
                
                $stmt = $db->prepare("
                    SELECT size, quantity 
                    FROM product_sizes 
                    WHERE product_id = ? AND quantity > 0
                    ORDER BY size
                ");
                $stmt->execute([$_GET['product_id']]);
                $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response = ['success' => true, 'sizes' => $sizes];
                break;
                
            case 'search':
                // Поиск товаров
                $search = $_GET['q'] ?? '';
                $limit = min(intval($_GET['limit'] ?? 10), 50);
                
                if (empty($search)) {
                    $response = ['success' => true, 'results' => []];
                    break;
                }
                
                $stmt = $db->prepare("
                    SELECT p.id, p.name, p.price, pi.image_url as image
                    FROM products p
                    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1
                    WHERE p.status = 'active' 
                    AND (p.name LIKE ? OR p.description LIKE ?)
                    ORDER BY p.name
                    LIMIT ?
                ");
                $searchTerm = '%' . $search . '%';
                $stmt->bindValue(1, $searchTerm, PDO::PARAM_STR);
                $stmt->bindValue(2, $searchTerm, PDO::PARAM_STR);
                $stmt->bindValue(3, $limit, PDO::PARAM_INT);
                $stmt->execute();
                
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response = ['success' => true, 'results' => $results];
                break;
                
            case 'filter':
                // Фильтрация товаров
                $category = $_GET['category'] ?? null;
                $brand = $_GET['brand'] ?? null;
                $minPrice = $_GET['min_price'] ?? null;
                $maxPrice = $_GET['max_price'] ?? null;
                
                $where = ['p.status = "active"'];
                $params = [];
                
                if ($category) {
                    $where[] = 'p.category = ?';
                    $params[] = $category;
                }
                
                if ($brand) {
                    $where[] = 'p.brand = ?';
                    $params[] = $brand;
                }
                
                if (is_numeric($minPrice)) {
                    $where[] = 'p.price >= ?';
                    $params[] = $minPrice;
                }
                
                if (is_numeric($maxPrice)) {
                    $where[] = 'p.price <= ?';
                    $params[] = $maxPrice;
                }
                
                $sql = "
                    SELECT p.*, pi.image_url as main_image
                    FROM products p
                    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY p.created_at DESC
                ";
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response = ['success' => true, 'products' => $products];
                break;
                
            default:
                $response = ['success' => false, 'error' => 'Неизвестное действие'];
        }
    } else {
        $response = ['success' => false, 'error' => 'Не указано действие'];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response);
?>