<?php
// Функции для авторизации и регистрации

function registerUser($data) {
    $db = Database::connect();
    
    // Проверяем email на уникальность
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Пользователь с таким email уже существует'];
    }
    
    // Хэшируем пароль
    $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
    
    // Создаем пользователя
    $stmt = $db->prepare("
        INSERT INTO users (email, password, name, phone, role) 
        VALUES (?, ?, ?, ?, 'user')
    ");
    
    try {
        $stmt->execute([
            $data['email'],
            $hashedPassword,
            $data['name'],
            $data['phone'] ?? ''
        ]);
        
        // Автоматически входим после регистрации
        $userId = $db->lastInsertId();
        
        // Сохраняем данные в сессии
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $data['email'];
        $_SESSION['user_name'] = $data['name'];
        $_SESSION['user_role'] = 'user';
        
        // Слияние корзин
        mergeCarts($userId);
        
        return ['success' => true, 'user_id' => $userId];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Ошибка при создании пользователя: ' . $e->getMessage()];
    }
}

function loginUser($email, $password) {
    $db = Database::connect();
    
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return false;
    }
    
    // Проверяем пароль
    if (!password_verify($password, $user['password'])) {
        return false;
    }
    
    // Сохраняем данные в сессии
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    
    // Слияние корзин
    mergeCarts($user['id']);
    
    return true;
}

function logoutUser() {
    session_destroy();
    return true;
}

function updateUserProfile($userId, $data) {
    $db = Database::connect();
    
    $stmt = $db->prepare("
        UPDATE users 
        SET name = ?, phone = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    
    return $stmt->execute([
        $data['name'],
        $data['phone'] ?? '',
        $userId
    ]);
}

function getUserById($userId) {
    $db = Database::connect();
    
    $stmt = $db->prepare("
        SELECT id, email, name, phone, role, created_at 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getAllUsers($role = null) {
    $db = Database::connect();
    
    $sql = "SELECT id, email, name, phone, role, created_at FROM users";
    $params = [];
    
    if ($role) {
        $sql .= " WHERE role = ?";
        $params[] = $role;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function updateUserRole($userId, $role) {
    $db = Database::connect();
    
    $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
    return $stmt->execute([$role, $userId]);
}

// Функция для слияния корзин
function mergeCarts($userId) {
    $db = Database::connect();
    $sessionId = session_id();
    
    // Получаем сессионную корзину
    $stmt = $db->prepare("SELECT id FROM carts WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $sessionCart = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sessionCart) {
        return;
    }
    
    // Получаем или создаем пользовательскую корзину
    $userCartId = getOrCreateCart($userId, null);
    
    // Получаем товары из сессионной корзины
    $stmt = $db->prepare("SELECT * FROM cart_items WHERE cart_id = ?");
    $stmt->execute([$sessionCart['id']]);
    $sessionItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sessionItems as $item) {
        // Проверяем, есть ли уже такой товар в пользовательской корзине
        $checkStmt = $db->prepare("
            SELECT * FROM cart_items 
            WHERE cart_id = ? AND product_id = ? AND size = ?
        ");
        $checkStmt->execute([$userCartId, $item['product_id'], $item['size']]);
        $existingItem = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingItem) {
            // Обновляем количество
            $newQuantity = $existingItem['quantity'] + $item['quantity'];
            $updateStmt = $db->prepare("
                UPDATE cart_items 
                SET quantity = ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$newQuantity, $existingItem['id']]);
        } else {
            // Добавляем новый товар
            $insertStmt = $db->prepare("
                INSERT INTO cart_items (cart_id, product_id, size, quantity) 
                VALUES (?, ?, ?, ?)
            ");
            $insertStmt->execute([
                $userCartId,
                $item['product_id'],
                $item['size'],
                $item['quantity']
            ]);
        }
    }
    
    // Удаляем сессионную корзину
    $deleteStmt = $db->prepare("DELETE FROM carts WHERE id = ?");
    $deleteStmt->execute([$sessionCart['id']]);
    
    // Обновляем session_id в пользовательской корзине
    $updateCartStmt = $db->prepare("UPDATE carts SET session_id = ? WHERE id = ?");
    $updateCartStmt->execute([$sessionId, $userCartId]);
}
?>