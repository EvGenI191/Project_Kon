<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$db = Database::connect();
$response = [];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $_GET['action'] ?? '';
            
            switch ($action) {
                case 'login':
                    // Авторизация
                    if (!isset($data['email'], $data['password'])) {
                        throw new Exception('Не указаны email и пароль');
                    }
                    
                    if (loginUser($data['email'], $data['password'])) {
                        $response = [
                            'success' => true,
                            'user' => [
                                'id' => $_SESSION['user_id'],
                                'email' => $_SESSION['user_email'],
                                'name' => $_SESSION['user_name'],
                                'role' => $_SESSION['user_role']
                            ]
                        ];
                    } else {
                        throw new Exception('Неверный email или пароль');
                    }
                    break;
                    
                case 'register':
                    // Регистрация
                    if (!isset($data['email'], $data['password'], $data['name'])) {
                        throw new Exception('Не указаны обязательные поля');
                    }
                    
                    $result = registerUser([
                        'email' => $data['email'],
                        'password' => $data['password'],
                        'name' => $data['name'],
                        'phone' => $data['phone'] ?? null
                    ]);
                    
                    if ($result['success']) {
                        $response = [
                            'success' => true,
                            'user_id' => $result['user_id'],
                            'message' => 'Регистрация успешна'
                        ];
                    } else {
                        throw new Exception($result['error']);
                    }
                    break;
                    
                case 'logout':
                    // Выход
                    logoutUser();
                    $response = ['success' => true];
                    break;
                    
                case 'check_auth':
                    // Проверка авторизации
                    $response = [
                        'success' => isLoggedIn(),
                        'user' => isLoggedIn() ? [
                            'id' => $_SESSION['user_id'],
                            'email' => $_SESSION['user_email'],
                            'name' => $_SESSION['user_name'],
                            'role' => $_SESSION['user_role']
                        ] : null
                    ];
                    break;
                    
                case 'forgot_password':
                    // Восстановление пароля
                    if (!isset($data['email'])) {
                        throw new Exception('Не указан email');
                    }
                    
                    // Проверяем существование пользователя
                    $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ?");
                    $stmt->execute([$data['email']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user) {
                        // Генерируем токен для сброса пароля
                        $token = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                        
                        // Сохраняем токен в базе
                        $stmt = $db->prepare("
                            INSERT INTO password_resets (email, token, expires_at) 
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$data['email'], $token, $expires]);
                        
                        // Отправляем email (в реальном приложении)
                        $resetLink = SITE_URL . "reset_password.php?token=$token";
                        
                        $response = [
                            'success' => true,
                            'message' => 'Инструкции по сбросу пароля отправлены на email',
                            'reset_link' => $resetLink // Для тестирования
                        ];
                    } else {
                        throw new Exception('Пользователь с таким email не найден');
                    }
                    break;
                    
                default:
                    throw new Exception('Неизвестное действие');
            }
            break;
            
        default:
            throw new Exception('Метод не поддерживается');
    }
    
} catch (Exception $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response);
?>