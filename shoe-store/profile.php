<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Проверка авторизации
requireLogin();

$db = Database::connect();
$errors = [];
$success = false;

// Получаем информацию о пользователе
$userId = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Обновление профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Валидация
        if (empty($name)) {
            $errors[] = 'Имя обязательно';
        }
        
        if (empty($phone)) {
            $errors[] = 'Телефон обязателен';
        } elseif (!preg_match('/^[\d\s\-\+\(\)]{10,20}$/', $phone)) {
            $errors[] = 'Некорректный номер телефона';
        }
        
        if (empty($errors)) {
            if (updateUserProfile($userId, ['name' => $name, 'phone' => $phone])) {
                $_SESSION['user_name'] = $name;
                $success = true;
                $_SESSION['message'] = 'Профиль успешно обновлен';
            } else {
                $errors[] = 'Ошибка при обновлении профиля';
            }
        }
    } elseif ($_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Валидация
        if (empty($current_password)) {
            $errors[] = 'Текущий пароль обязателен';
        }
        
        if (empty($new_password)) {
            $errors[] = 'Новый пароль обязателен';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'Новый пароль должен содержать минимум 6 символов';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'Новые пароли не совпадают';
        }
        
        if (empty($errors)) {
            // Проверяем текущий пароль
            if (password_verify($current_password, $user['password'])) {
                // Обновляем пароль
                $hashedPassword = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$hashedPassword, $userId])) {
                    $success = true;
                    $_SESSION['message'] = 'Пароль успешно изменен';
                } else {
                    $errors[] = 'Ошибка при изменении пароля';
                }
            } else {
                $errors[] = 'Неверный текущий пароль';
            }
        }
    }
}

// Получаем последние заказы пользователя
$ordersStmt = $db->prepare("
    SELECT * FROM orders 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$ordersStmt->execute([$userId]);
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <div class="profile-header">
            <h1>Личный кабинет</h1>
            <p class="welcome-message">Добро пожаловать, <?= htmlspecialchars($user['name']) ?>!</p>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success">
            <?= $_SESSION['message'] ?>
            <?php unset($_SESSION['message']); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            Профиль успешно обновлен!
        </div>
        <?php endif; ?>
        
        <div class="profile-layout">
            <!-- Боковое меню -->
            <aside class="profile-sidebar">
                <div class="user-card">
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="user-info">
                        <h3><?= htmlspecialchars($user['name']) ?></h3>
                        <p class="user-email"><?= htmlspecialchars($user['email']) ?></p>
                        <p class="user-role"><?= $user['role'] == 'admin' ? 'Администратор' : ($user['role'] == 'manager' ? 'Менеджер' : 'Пользователь') ?></p>
                    </div>
                </div>
                
                <nav class="profile-nav">
                    <a href="#profile" class="active" data-tab="profile">
                        <i class="fas fa-user"></i> Профиль
                    </a>
                    <a href="#orders" data-tab="orders">
                        <i class="fas fa-shopping-bag"></i> Мои заказы
                    </a>
                    <a href="#addresses" data-tab="addresses">
                        <i class="fas fa-map-marker-alt"></i> Адреса доставки
                    </a>
                    <a href="#wishlist" data-tab="wishlist">
                        <i class="fas fa-heart"></i> Избранное
                    </a>
                    <a href="#settings" data-tab="settings">
                        <i class="fas fa-cog"></i> Настройки
                    </a>
                    <?php if (isManager()): ?>
                    <a href="admin/" target="_blank">
                        <i class="fas fa-cog"></i> Панель управления
                    </a>
                    <?php endif; ?>
                </nav>
            </aside>
            
            <!-- Основное содержимое -->
            <div class="profile-content">
                <!-- Вкладка Профиль -->
                <div class="tab-content active" id="profile-tab">
                    <h2>Личные данные</h2>
                    
                    <form method="POST" class="profile-form">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Имя и фамилия *</label>
                                <input type="text" id="name" name="name" class="form-control" 
                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" class="form-control" 
                                       value="<?= htmlspecialchars($user['email']) ?>" disabled>
                                <small class="form-text">Email нельзя изменить</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Телефон *</label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?= htmlspecialchars($user['phone']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Дата регистрации</label>
                            <p class="form-static"><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></p>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Сохранить изменения
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Вкладка Заказы -->
                <div class="tab-content" id="orders-tab">
                    <div class="orders-header">
                        <h2>Мои заказы</h2>
                        <a href="orders.php" class="btn btn-outline">Все заказы</a>
                    </div>
                    
                    <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-bag"></i>
                        <h3>У вас пока нет заказов</h3>
                        <p>Сделайте свой первый заказ в нашем магазине</p>
                        <a href="catalog.php" class="btn btn-primary">Перейти в каталог</a>
                    </div>
                    <?php else: ?>
                    <div class="orders-list">
                        <?php foreach ($orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div class="order-info">
                                    <h3>Заказ #<?= $order['order_number'] ?></h3>
                                    <p class="order-date"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></p>
                                </div>
                                <div class="order-status">
                                    <span class="status-badge status-<?= $order['status'] ?>">
                                        <?php
                                        $statuses = [
                                            'created' => 'Оформлен',
                                            'paid' => 'Оплачен',
                                            'processing' => 'В обработке',
                                            'shipped' => 'Отправлен',
                                            'delivered' => 'Доставлен',
                                            'cancelled' => 'Отменен'
                                        ];
                                        echo $statuses[$order['status']] ?? $order['status'];
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="order-body">
                                <div class="order-details">
                                    <p><strong>Сумма:</strong> <?= formatPrice($order['total_amount']) ?> ₽</p>
                                    <p><strong>Способ оплаты:</strong> 
                                        <?= $order['payment_method'] == 'cash' ? 'Наличные' : 
                                           ($order['payment_method'] == 'card' ? 'Карта онлайн' : 'Банковский перевод') ?>
                                    </p>
                                    <?php if ($order['shipping_address']): ?>
                                    <p><strong>Адрес доставки:</strong> <?= htmlspecialchars($order['shipping_address']) ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="order-actions">
                                    <a href="orders.php?id=<?= $order['id'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> Подробнее
                                    </a>
                                    <?php if ($order['status'] == 'created'): ?>
                                    <button class="btn btn-primary btn-sm">
                                        <i class="fas fa-credit-card"></i> Оплатить
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Вкладка Смена пароля -->
                <div class="tab-content" id="settings-tab">
                    <h2>Смена пароля</h2>
                    
                    <form method="POST" class="password-form">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password">Текущий пароль *</label>
                            <div class="input-group">
                                <input type="password" id="current_password" name="current_password" 
                                       class="form-control" placeholder="Введите текущий пароль" required>
                                <button type="button" class="password-toggle">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">Новый пароль *</label>
                                <div class="input-group">
                                    <input type="password" id="new_password" name="new_password" 
                                           class="form-control" placeholder="Минимум 6 символов" required>
                                    <button type="button" class="password-toggle">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Подтверждение пароля *</label>
                                <div class="input-group">
                                    <input type="password" id="confirm_password" name="confirm_password" 
                                           class="form-control" placeholder="Повторите новый пароль" required>
                                    <button type="button" class="password-toggle">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key"></i> Сменить пароль
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Остальные вкладки (заглушки) -->
                <div class="tab-content" id="addresses-tab">
                    <h2>Адреса доставки</h2>
                    <div class="empty-state">
                        <i class="fas fa-map-marker-alt"></i>
                        <h3>Адреса доставки</h3>
                        <p>Функция управления адресами скоро будет доступна</p>
                    </div>
                </div>
                
                <div class="tab-content" id="wishlist-tab">
                    <h2>Избранное</h2>
                    <div class="empty-state">
                        <i class="fas fa-heart"></i>
                        <h3>Избранные товары</h3>
                        <p>Функция избранного скоро будет доступна</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
    // Переключение вкладок
    document.querySelectorAll('.profile-nav a').forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.href.includes('admin')) {
                return; // Не обрабатываем ссылку на админку
            }
            
            e.preventDefault();
            
            // Удаляем активный класс у всех вкладок и ссылок
            document.querySelectorAll('.profile-nav a').forEach(a => {
                a.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Добавляем активный класс текущей вкладке и ссылке
            this.classList.add('active');
            const tabId = this.dataset.tab + '-tab';
            document.getElementById(tabId).classList.add('active');
            
            // Сохраняем выбранную вкладку в URL
            history.pushState(null, null, this.getAttribute('href'));
        });
    });
    
    // Восстановление вкладки из URL
    window.addEventListener('DOMContentLoaded', function() {
        const hash = window.location.hash || '#profile';
        const tabLink = document.querySelector(`.profile-nav a[href="${hash}"]`);
        if (tabLink) {
            tabLink.click();
        }
    });
    
    // Маска для телефона
    document.getElementById('phone').addEventListener('input', function(e) {
        let x = e.target.value.replace(/\D/g, '').match(/(\d{0,1})(\d{0,3})(\d{0,3})(\d{0,2})(\d{0,2})/);
        e.target.value = !x[2] ? x[1] : '+7 (' + x[2] + ') ' + x[3] + (x[4] ? '-' + x[4] : '') + (x[5] ? '-' + x[5] : '');
    });
    
    // Показать/скрыть пароль
    document.querySelectorAll('.password-toggle').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    // Валидация формы смены пароля
    document.querySelector('.password-form').addEventListener('submit', function(e) {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (newPassword.length < 6) {
            e.preventDefault();
            alert('Новый пароль должен содержать минимум 6 символов');
            return;
        }
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('Новые пароли не совпадают');
            return;
        }
    });
    </script>
</body>
</html>

<style>
.profile-header {
    margin-bottom: 30px;
}

.profile-header h1 {
    margin-bottom: 10px;
    color: #333;
}

.welcome-message {
    color: #666;
    font-size: 1.1rem;
}

.profile-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 40px;
}

.profile-sidebar {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    padding: 25px;
    height: fit-content;
}

.user-card {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 25px;
    border-bottom: 1px solid #eee;
}

.user-avatar {
    width: 80px;
    height: 80px;
    background: #e3f2fd;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
}

.user-avatar i {
    font-size: 3rem;
    color: #007bff;
}

.user-info h3 {
    margin: 0 0 5px 0;
    color: #333;
}

.user-email {
    color: #666;
    margin: 0 0 5px 0;
    font-size: 0.9rem;
}

.user-role {
    color: #007bff;
    font-weight: 500;
    margin: 0;
    font-size: 0.9rem;
}

.profile-nav {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.profile-nav a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    color: #333;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s;
}

.profile-nav a:hover {
    background: #f8f9fa;
    color: #007bff;
}

.profile-nav a.active {
    background: #007bff;
    color: white;
}

.profile-nav a i {
    width: 20px;
}

.profile-content {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    padding: 30px;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.profile-form .form-row,
.password-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0,123,255,0.25);
}

.form-control:disabled {
    background: #f8f9fa;
    cursor: not-allowed;
}

.form-static {
    margin: 8px 0 0 0;
    color: #666;
    font-size: 0.9rem;
}

.form-text {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 0.85rem;
}

.input-group {
    position: relative;
    display: flex;
}

.input-group .password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #666;
    cursor: pointer;
    font-size: 1rem;
}

.form-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.orders-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.empty-state i {
    font-size: 48px;
    color: #dee2e6;
    margin-bottom: 20px;
}

.empty-state h3 {
    margin-bottom: 10px;
    color: #666;
}

.empty-state p {
    margin-bottom: 30px;
}

.orders-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.order-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
    transition: box-shadow 0.3s;
}

.order-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.order-info h3 {
    margin: 0 0 5px 0;
    color: #333;
    font-size: 1.1rem;
}

.order-date {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    display: inline-block;
}

.status-created { background: #e3f2fd; color: #007bff; }
.status-paid { background: #e8f5e9; color: #4caf50; }
.status-processing { background: #fff3e0; color: #ff9800; }
.status-shipped { background: #e8eaf6; color: #3f51b5; }
.status-delivered { background: #e8f5e9; color: #4caf50; }
.status-cancelled { background: #ffebee; color: #f44336; }

.order-body {
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.order-details p {
    margin: 0 0 10px 0;
    color: #666;
}

.order-details p:last-child {
    margin-bottom: 0;
}

.order-actions {
    display: flex;
    gap: 10px;
}

.btn-sm {
    padding: 8px 16px;
    font-size: 0.85rem;
}

@media (max-width: 992px) {
    .profile-layout {
        grid-template-columns: 1fr;
    }
    
    .profile-form .form-row,
    .password-form .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
    
    .order-body {
        flex-direction: column;
        gap: 15px;
    }
    
    .order-actions {
        align-self: flex-start;
    }
}
</style>