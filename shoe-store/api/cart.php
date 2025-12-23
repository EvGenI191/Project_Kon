<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/cart.php';

header('Content-Type: application/json');

// Проверяем метод запроса
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Получение корзины
        if (isLoggedIn()) {
            $cartId = getOrCreateCart($_SESSION['user_id']);
            $items = getCartItems($cartId);
            $total = getCartTotal($cartId);
        } else {
            $sessionId = session_id();
            $cartId = getOrCreateCart(null, $sessionId);
            $items = getCartItems($cartId);
            $total = getCartTotal($cartId);
        }
        
        echo json_encode([
            'success' => true,
            'items' => $items,
            'total' => $total,
            'count' => array_sum(array_column($items, 'quantity'))
        ]);
        break;
        
    case 'POST':
        // Добавление в корзину
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['product_id'], $data['size'])) {
            echo json_encode(['success' => false, 'error' => 'Недостаточно данных']);
            exit;
        }
        
        if (isLoggedIn()) {
            $cartId = getOrCreateCart($_SESSION['user_id']);
        } else {
            $sessionId = session_id();
            $cartId = getOrCreateCart(null, $sessionId);
        }
        
        $quantity = $data['quantity'] ?? 1;
        $result = addToCart($cartId, $data['product_id'], $data['size'], $quantity);
        
        echo json_encode($result);
        break;
        
    case 'PUT':
        // Обновление количества
        parse_str(file_get_contents('php://input'), $data);
        
        if (!isset($data['item_id'], $data['quantity'])) {
            echo json_encode(['success' => false, 'error' => 'Недостаточно данных']);
            exit;
        }
        
        $result = updateCartItemQuantity($data['item_id'], intval($data['quantity']));
        echo json_encode($result);
        break;
        
    case 'DELETE':
        // Удаление из корзины
        parse_str(file_get_contents('php://input'), $data);
        
        if (!isset($data['item_id'])) {
            echo json_encode(['success' => false, 'error' => 'Не указан ID товара']);
            exit;
        }
        
        $result = removeFromCart($data['item_id']);
        echo json_encode(['success' => $result]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
        break;
}
?>