<?php
// functions.php

/**
 * Проверка авторизации пользователя
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'];
}

/**
 * Форматирование цены
 */
function formatPrice($price) {
    return number_format($price, 0, ',', ' ');
}

/**
 * Получение HEX цвета из названия
 */
function getColorHex($color) {
    $colors = [
        'Черный' => '#000000',
        'Белый' => '#FFFFFF',
        'Красный' => '#FF0000',
        'Синий' => '#0000FF',
        'Зеленый' => '#008000',
        'Желтый' => '#FFFF00',
        'Коричневый' => '#A52A2A',
        'Серый' => '#808080',
        'Бежевый' => '#F5F5DC',
        'Розовый' => '#FFC0CB',
    ];
    
    return $colors[$color] ?? '#CCCCCC';
}

/**
 * Санитизация строки для вывода в HTML
 */
function sanitize($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Редирект на другую страницу
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}
?>