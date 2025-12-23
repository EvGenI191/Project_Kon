<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Проверяем авторизацию для API
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
    exit;
}

$db = Database::connect();
$response = [];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $userId = $_SESSION['user_id'];
    
    switch ($method) {
        case 'GET':
            // Получение списка заказов пользователя
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'get_orders':
                        $page = max(1, intval($_GET['page'] ?? 1));
                        $limit = intval($_GET['limit'] ?? 10);
                        $offset = ($page - 1) * $limit;
                        
                        $sql = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
                        $stmt = $db->prepare($sql);
                        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
                        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
                        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
                        $stmt->execute();
                        
                        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Получаем общее количество
                        $countStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
                        $countStmt->execute([$userId]);
                        $total = $countStmt->fetchColumn();
                        
                        $response = [
                            'success' => true,
                            'orders' => $orders,
                            'total' => $total,
                            'page' => $page,
                            'total_pages' => ceil($total / $limit)
                        ];
                        break;
                        
                    case 'get_order':
                        $orderId = $_GET['order_id'] ?? 0;
                        
                        if (!$orderId) {
                            throw new Exception('Не указан ID заказа');
                        }
                        
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
                            throw new Exception('Заказ не найден');
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
                        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $response = [
                            'success' => true,
                            'order' => $order,
                            'items' => $items
                        ];
                        break;
                        
                    default:
                        throw new Exception('Неизвестное действие');
                }
            } else {
                throw new Exception('Не указано действие');
            }
            break;
            
        case 'POST':
            // Создание нового заказа
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['cart_id'])) {
                throw new Exception('Не указана корзина');
            }
            
            // Проверяем корзину
            $cartStmt = $db->prepare("SELECT * FROM carts WHERE id = ? AND user_id = ?");
            $cartStmt->execute([$data['cart_id'], $userId]);
            $cart = $cartStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cart) {
                throw new Exception('Корзина не найдена');
            }
            
            // Получаем товары из корзины
            $itemsStmt = $db->prepare("
                SELECT ci.*, p.price, p.name, ps.quantity as stock
                FROM cart_items ci
                JOIN products p ON ci.product_id = p.id
                JOIN product_sizes ps ON ci.product_id = ps.product_id AND ci.size = ps.size
                WHERE ci.cart_id = ?
            ");
            $itemsStmt->execute([$data['cart_id']]);
            $cartItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($cartItems)) {
                throw new Exception('Корзина пуста');
            }
            
            // Проверяем наличие товаров
            foreach ($cartItems as $item) {
                if ($item['stock'] < $item['quantity']) {
                    throw new Exception("Товар '{$item['name']}' недоступен в нужном количестве");
                }
            }
            
            // Рассчитываем общую сумму
            $totalAmount = 0;
            foreach ($cartItems as $item) {
                $totalAmount += $item['price'] * $item['quantity'];
            }
            
            // Создаем заказ
            $db->beginTransaction();
            
            try {
                // Генерируем номер заказа
                $orderNumber = generateOrderNumber();
                
                // Создаем запись заказа
                $orderStmt = $db->prepare("
                    INSERT INTO orders (user_id, order_number, total_amount, status, 
                                       shipping_address, payment_method, customer_name, 
                                       customer_phone, notes)
                    VALUES (?, ?, ?, 'created', ?, ?, ?, ?, ?)
                ");
                
                $orderStmt->execute([
                    $userId,
                    $orderNumber,
                    $totalAmount,
                    $data['shipping_address'] ?? '',
                    $data['payment_method'] ?? 'cash',
                    $data['customer_name'] ?? '',
                    $data['customer_phone'] ?? '',
                    $data['notes'] ?? ''
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
                    
                    // Обновляем остатки
                    $updateStmt = $db->prepare("
                        UPDATE product_sizes 
                        SET quantity = quantity - ? 
                        WHERE product_id = ? AND size = ?
                    ");
                    $updateStmt->execute([
                        $item['quantity'],
                        $item['product_id'],
                        $item['size']
                    ]);
                }
                
                // Очищаем корзину
                $clearStmt = $db->prepare("DELETE FROM cart_items WHERE cart_id = ?");
                $clearStmt->execute([$data['cart_id']]);
                
                $db->commit();
                
                $response = [
                    'success' => true,
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'total_amount' => $totalAmount
                ];
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'PUT':
            // Обновление статуса заказа
            parse_str(file_get_contents('php://input'), $data);
            
            if (!isset($data['order_id'], $data['status'])) {
                throw new Exception('Не указаны ID заказа и статус');
            }
            
            $orderId = $data['order_id'];
            
            // Проверяем принадлежность заказа пользователю
            $checkStmt = $db->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
            $checkStmt->execute([$orderId, $userId]);
            
            if (!$checkStmt->fetch()) {
                throw new Exception('Заказ не найден');
            }
            
            // Обновляем статус
            $updateStmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $updateStmt->execute([$data['status'], $orderId]);
            
            $response = ['success' => true];
            break;
            
        default:
            throw new Exception('Метод не поддерживается');
    }
    
} catch (Exception $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response);
?>