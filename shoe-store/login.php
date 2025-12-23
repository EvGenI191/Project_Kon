<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Если пользователь уже авторизован, перенаправляем
if (isLoggedIn()) {
    header('Location: profile.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Валидация
    if (empty($email)) {
        $errors[] = 'Email обязателен';
    }
    
    if (empty($password)) {
        $errors[] = 'Пароль обязателен';
    }
    
    if (empty($errors)) {
        // Пытаемся авторизовать пользователя
        if (loginUser($email, $password)) {
            // Запоминаем сессию если нужно
            if ($remember) {
                setcookie('user_email', $email, time() + 86400 * 30, '/');
            }
            
            // Перенаправляем на предыдущую страницу или профиль
            $redirect = $_GET['redirect'] ?? 'profile.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $errors[] = 'Неверный email или пароль';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в аккаунт - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <h1>Вход в аккаунт</h1>
                    <p>Введите ваши данные для входа</p>
                </div>
                
                <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                   placeholder="example@mail.ru" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Пароль</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" id="password" name="password" 
                                   class="form-control" placeholder="Введите пароль" required>
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" id="remember">
                            <span>Запомнить меня</span>
                        </label>
                        <a href="forgot_password.php" class="forgot-link">Забыли пароль?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Войти
                    </button>
                </form>
                
                <div class="auth-divider">
                    <span>Или войдите через</span>
                </div>
                
                <div class="social-auth">
                    <button class="btn btn-social btn-vk">
                        <i class="fab fa-vk"></i> ВКонтакте
                    </button>
                    <button class="btn btn-social btn-google">
                        <i class="fab fa-google"></i> Google
                    </button>
                </div>
                
                <div class="auth-footer">
                    <p>Еще нет аккаунта? <a href="register.php">Зарегистрироваться</a></p>
                </div>
            </div>
            
            <div class="auth-info">
                <h2>Преимущества регистрации</h2>
                <ul class="benefits-list">
                    <li>
                        <i class="fas fa-shopping-cart"></i>
                        <div>
                            <h3>Быстрое оформление заказов</h3>
                            <p>Сохраняйте данные для быстрой покупки</p>
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-history"></i>
                        <div>
                            <h3>История заказов</h3>
                            <p>Отслеживайте статус заказов</p>
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-percentage"></i>
                        <div>
                            <h3>Специальные предложения</h3>
                            <p>Получайте персональные скидки</p>
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-heart"></i>
                        <div>
                            <h3>Избранное</h3>
                            <p>Сохраняйте понравившиеся товары</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
    // Показать/скрыть пароль
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const icon = this.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
    
    // Заполнить email из cookie если есть
    window.addEventListener('DOMContentLoaded', function() {
        const emailInput = document.getElementById('email');
        const rememberCheckbox = document.getElementById('remember');
        
        // Получаем куки
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'user_email') {
                emailInput.value = decodeURIComponent(value);
                rememberCheckbox.checked = true;
                break;
            }
        }
    });
    </script>
</body>
</html>

<style>
.auth-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    max-width: 1000px;
    margin: 40px auto;
}

.auth-card {
    background: white;
    border-radius: 12px;
    padding: 40px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.auth-header {
    text-align: center;
    margin-bottom: 30px;
}

.auth-header h1 {
    margin-bottom: 10px;
    color: #333;
}

.auth-header p {
    color: #666;
    margin-bottom: 0;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert ul {
    margin: 10px 0 0 20px;
    padding: 0;
}

.auth-form .form-group {
    margin-bottom: 20px;
}

.auth-form label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.input-group {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon {
    position: absolute;
    left: 15px;
    color: #666;
    z-index: 1;
}

.auth-form .form-control {
    width: 100%;
    padding: 12px 15px 12px 45px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s;
}

.auth-form .form-control:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0,123,255,0.25);
}

.password-toggle {
    position: absolute;
    right: 15px;
    background: none;
    border: none;
    color: #666;
    cursor: pointer;
    font-size: 1rem;
    z-index: 1;
}

.form-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    color: #666;
    font-size: 0.9rem;
}

.checkbox-label input {
    margin: 0;
}

.forgot-link {
    color: #007bff;
    text-decoration: none;
    font-size: 0.9rem;
}

.forgot-link:hover {
    text-decoration: underline;
}

.btn-block {
    width: 100%;
    padding: 15px;
    font-size: 1.1rem;
}

.auth-divider {
    display: flex;
    align-items: center;
    margin: 30px 0;
    color: #666;
}

.auth-divider::before,
.auth-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #dee2e6;
}

.auth-divider span {
    padding: 0 15px;
    font-size: 0.9rem;
}

.social-auth {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 30px;
}

.btn-social {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 12px;
    border: 1px solid #ddd;
    background: white;
    color: #333;
    border-radius: 8px;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-social:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.btn-vk:hover {
    background: #0077FF;
    color: white;
    border-color: #0077FF;
}

.btn-google:hover {
    background: #DB4437;
    color: white;
    border-color: #DB4437;
}

.auth-footer {
    text-align: center;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.auth-footer p {
    color: #666;
    margin-bottom: 0;
}

.auth-footer a {
    color: #007bff;
    text-decoration: none;
    font-weight: 500;
}

.auth-footer a:hover {
    text-decoration: underline;
}

.auth-info {
    padding: 20px;
}

.auth-info h2 {
    margin-bottom: 30px;
    color: #333;
}

.benefits-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.benefits-list li {
    display: flex;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 25px;
    padding-bottom: 25px;
    border-bottom: 1px solid #eee;
}

.benefits-list li:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.benefits-list i {
    font-size: 1.5rem;
    color: #007bff;
    margin-top: 5px;
}

.benefits-list h3 {
    margin: 0 0 5px 0;
    color: #333;
    font-size: 1.1rem;
}

.benefits-list p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .auth-container {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .auth-card {
        padding: 25px;
    }
}
</style>