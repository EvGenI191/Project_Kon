<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/cart.php';

// Проверяем сессию
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Устанавливаем заголовок JSON
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Метод не поддерживается');
    }
    
    // Получаем данные
    $item_id = intval($_POST['item_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    
    if ($item_id <= 0) {
        throw new Exception('Не указан товар');
    }
    
    // Обновляем корзину
    $cart = get_cart();
    
    if ($quantity <= 0) {
        // Удаляем товар
        $result = $cart->removeItem($item_id);
    } else {
        // Обновляем количество
        $result = $cart->updateQuantity($item_id, $quantity);
    }
    
    if ($result['success']) {
        $response['success'] = true;
        $response['message'] = 'Корзина обновлена';
        $response['cart_count'] = $cart->getTotalCount();
        $response['cart_total'] = $cart->getTotalAmount();
    } else {
        $response['message'] = $result['error'];
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(400);
}

echo json_encode($response);
?>