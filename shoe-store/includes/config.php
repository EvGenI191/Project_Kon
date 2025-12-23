<?php
// config.php
define('SITE_NAME', 'Магазин обуви');
define('DB_PATH', __DIR__ . '/../database.sqlite');
define('BASE_URL', 'http://localhost:8000/');

// Настройки сессии
ini_set('session.cookie_lifetime', 86400);
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Начинаем сессию
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Генерация CSRF токена
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>