<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: profile.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $agree    = !empty($_POST['agree']);

    if (mb_strlen($name) < 2) {
        $errors[] = 'Имя должно содержать минимум 2 символа';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный email';
    }

    if ($phone && !preg_match('/^[\d\s\-\+\(\)]{10,20}$/', $phone)) {
        $errors[] = 'Некорректный номер телефона';
    }

    if (mb_strlen($password) < 6) {
        $errors[] = 'Пароль должен содержать минимум 6 символов';
    }

    if ($password !== $confirm) {
        $errors[] = 'Пароли не совпадают';
    }

    if (!$agree) {
        $errors[] = 'Необходимо согласиться с правилами';
    }

    if (empty($errors)) {
        $db = Database::connect();

        $stmt = $db->prepare("SELECT 1 FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $errors[] = 'Пользователь с таким email уже существует';
        } else {
            $result = registerUser([
                'name'     => $name,
                'email'    => $email,
                'phone'    => $phone,
                'password' => $password
            ]);

            if ($result['success']) {
                header('Location: profile.php');
                exit;
            }

            $errors[] = $result['error'] ?? 'Ошибка регистрации';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Регистрация — <?= SITE_NAME ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Inter, Segoe UI, sans-serif;
    min-height: 100vh;
    background: linear-gradient(135deg,
        #22c55e 0%,
        #16a34a 50%,
        #15803d 100%
    );
    background-attachment: fixed;
    color: #111;
}


.container {
    max-width: 420px;
    margin: 80px auto;
    padding: 0 15px;
}

.card {
    background: #fff;
    padding: 35px;
    border-radius: 14px;
    box-shadow: 0 20px 40px rgba(0,0,0,.1);
}

h1 {
    text-align: center;
    margin-bottom: 8px;
}

.subtitle {
    text-align: center;
    color: #666;
    margin-bottom: 25px;
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
}

input[type="text"],
input[type="email"],
input[type="password"],
input[type="tel"] {
    width: 100%;
    padding: 12px 14px;
    border-radius: 10px;
    border: 1px solid #ddd;
    font-size: 15px;
}

input:focus {
    outline: none;
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79,70,229,.2);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.checkbox {
    display: flex;
    gap: 8px;
    font-size: 14px;
    color: #555;
}

button {
    width: 100%;
    padding: 14px;
    border-radius: 999px;
    border: none;
    background: #31bd1fff;
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 10px;
}

button:hover {
    background: #31bd1fff;
}

.alert {
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.alert-danger {
    background: #fee2e2;
    color: #991b1b;
}

.alert ul {
    margin-left: 20px;
}

.footer {
    text-align: center;
    margin-top: 20px;
    font-size: 14px;
}

.footer a {
    color: #31bd1fff;
    text-decoration: none;
}

@media (max-width: 480px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<div class="container">
    <div class="card">

        <h1>Регистрация</h1>
        <p class="subtitle">Создайте новый аккаунт</p>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label>Имя *</label>
                <input type="text" name="name"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Телефон</label>
                <input type="tel" name="phone"
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Пароль *</label>
                    <input type="password" name="password" required>
                </div>

                <div class="form-group">
                    <label>Подтверждение *</label>
                    <input type="password" name="confirm_password" required>
                </div>
            </div>

            <div class="form-group checkbox">
                <input type="checkbox" name="agree" required>
                <span>Я согласен с правилами</span>
            </div>

            <button type="submit">Зарегистрироваться</button>
        </form>

        <div class="footer">
            Уже есть аккаунт? <a href="login.php">Войти</a>
        </div>

    </div>
</div>

</body>
</html>
