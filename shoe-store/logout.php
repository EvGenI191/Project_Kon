<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Выход пользователя
logoutUser();

// Удаляем куки
setcookie('user_email', '', time() - 3600, '/');

// Перенаправляем на главную
header('Location: index.php');
exit;
?>