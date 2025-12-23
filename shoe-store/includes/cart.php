<?php
// Функции для работы с корзиной

function getOrCreateCart($userId = null, $sessionId = null) {
    $db = Database::connect();
    
    if ($userId) {
        // Ищем корзину пользователя
        $stmt = $db->prepare("SELECT * FROM carts WHERE user_id = ?");
        $stmt->execute([$userId]);
        $cart = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cart) {
            return $cart['id'];
        }
        
        // Создаем новую корзину
        $stmt = $db->prepare("INSERT INTO carts (user_id, session_id) VALUES (?, ?)");
        $stmt->execute([$userId, session_id()]);
        return $db->lastInsertId();
    } elseif ($sessionId) {
        // Ищем корзину по session_id
        $stmt = $db->prepare("SELECT * FROM carts WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $cart = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cart) {
            return $cart['id'];
        }
        
        // Создаем новую корзину
        $stmt = $db->prepare("INSERT INTO carts (session_id) VALUES (?)");
        $stmt->execute([$sessionId]);
        return $db->lastInsertId();
    }
    
    return null;
}

function addToCart($cartId, $productId, $size, $quantity = 1) {
    $db = Database::connect();
    
    // Проверяем доступность товара
    $stmt = $db->prepare("
        SELECT ps.id, ps.quantity as stock 
        FROM product_sizes ps 
        JOIN products p ON ps.product_id = p.id 
        WHERE ps.product_id = ? AND ps.size = ? AND p.status = 'active'
    ");
    $stmt->execute([$productId, $size]);
    $sizeInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sizeInfo) {
        return ['success' => false, 'error' => 'Товар недоступен в выбранном размере'];
    }
    
    // Проверяем, есть ли уже такой товар в корзине
    $stmt = $db->prepare("
        SELECT * FROM cart_items 
        WHERE cart_id = ? AND product_id = ? AND size = ?
    ");
    $stmt->execute([$cartId, $productId, $size]);
    $existingItem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingItem) {
        // Обновляем количество
        $newQuantity = $existingItem['quantity'] + $quantity;
        if ($sizeInfo['stock'] < $newQuantity) {
            return ['success' => false, 'error' => 'Недостаточно товара на складе'];
        }
        
        $stmt = $db->prepare("
            UPDATE cart_items 
            SET quantity = ? 
            WHERE id = ?
        ");
        $stmt->execute([$newQuantity, $existingItem['id']]);
    } else {
        // Добавляем новый товар
        if ($sizeInfo['stock'] < $quantity) {
            return ['success' => false, 'error' => 'Недостаточно товара на складе'];
        }
        
        $stmt = $db->prepare("
            INSERT INTO cart_items (cart_id, product_id, size, quantity) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$cartId, $productId, $size, $quantity]);
    }
    
    return ['success' => true];
}

function getCartItems($cartId) {
    $db = Database::connect();
    
    $stmt = $db->prepare("
        SELECT 
            ci.id,
            ci.product_id,
            ci.size,
            ci.quantity,
            p.name,
            p.price,
            p.brand,
            (SELECT image_url FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image,
            ps.quantity as stock
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        LEFT JOIN product_sizes ps ON p.id = ps.product_id AND ps.size = ci.size
        WHERE ci.cart_id = ?
        ORDER BY ci.id DESC
    ");
    $stmt->execute([$cartId]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function updateCartItemQuantity($cartItemId, $quantity) {
    $db = Database::connect();
    
    // Получаем информацию о товаре
    $stmt = $db->prepare("
        SELECT ci.*, ps.quantity as stock 
        FROM cart_items ci
        LEFT JOIN product_sizes ps ON ci.product_id = ps.product_id AND ci.size = ps.size
        WHERE ci.id = ?
    ");
    $stmt->execute([$cartItemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        return ['success' => false, 'error' => 'Товар не найден'];
    }
    
    if ($quantity <= 0) {
        // Удаляем товар из корзины
        $stmt = $db->prepare("DELETE FROM cart_items WHERE id = ?");
        $stmt->execute([$cartItemId]);
        return ['success' => true];
    }
    
    if ($item['stock'] !== null && $quantity > $item['stock']) {
        return ['success' => false, 'error' => 'Недостаточно товара на складе'];
    }
    
    // Обновляем количество
    $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
    $stmt->execute([$quantity, $cartItemId]);
    
    return ['success' => true];
}

function removeFromCart($cartItemId) {
    $db = Database::connect();
    
    $stmt = $db->prepare("DELETE FROM cart_items WHERE id = ?");
    return $stmt->execute([$cartItemId]);
}

function clearCart($cartId) {
    $db = Database::connect();
    
    $stmt = $db->prepare("DELETE FROM cart_items WHERE cart_id = ?");
    return $stmt->execute([$cartId]);
}

function getCartTotal($cartId) {
    $db = Database::connect();
    
    $stmt = $db->prepare("
        SELECT SUM(ci.quantity * p.price) as total
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        WHERE ci.cart_id = ?
    ");
    $stmt->execute([$cartId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['total'] ? (float)$result['total'] : 0;
}
?>