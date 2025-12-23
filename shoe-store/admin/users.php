<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

$db = Database::connect();

// Обработка действий с пользователями
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['user_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    if ($userId && $action) {
        switch ($action) {
            case 'update_role':
                $newRole = $_POST['role'] ?? '';
                if ($newRole && in_array($newRole, ['admin', 'manager', 'user'])) {
                    $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $stmt->execute([$newRole, $userId]);
                    $_SESSION['message'] = 'Роль пользователя обновлена';
                }
                break;
                
            case 'delete_user':
                // Нельзя удалить самого себя
                if ($userId != $_SESSION['user_id']) {
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $_SESSION['message'] = 'Пользователь удален';
                } else {
                    $_SESSION['error'] = 'Нельзя удалить самого себя';
                }
                break;
                
            case 'reset_password':
                // Сброс пароля пользователя
                $newPassword = bin2hex(random_bytes(8)); // Генерируем случайный пароль
                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                $_SESSION['message'] = "Пароль сброшен. Новый пароль: $newPassword";
                break;
        }
        
        header('Location: users.php');
        exit;
    }
}

// Получаем список пользователей с фильтрами
$where = [];
$params = [];

// Фильтр по роли
if (!empty($_GET['role'])) {
    $where[] = 'role = ?';
    $params[] = $_GET['role'];
}

// Фильтр по поиску
if (!empty($_GET['search'])) {
    $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $searchTerm = '%' . $_GET['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Параметры пагинации
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Получаем пользователей
$sql = "SELECT id, email, name, phone, role, created_at FROM users";
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Общее количество пользователей
$countSql = "SELECT COUNT(*) FROM users";
if (!empty($where)) {
    $countSql .= " WHERE " . implode(' AND ', $where);
}

$countStmt = $db->prepare($countSql);
$countStmt->execute(array_slice($params, 0, -2));
$totalUsers = $countStmt->fetchColumn();
$totalPages = ceil($totalUsers / $limit);

// Статистика
$stats = [
    'total' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'admins' => $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'managers' => $db->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn(),
    'users' => $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
    'new_today' => $db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = DATE('now')")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление пользователями - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header-actions">
                <h1>Управление пользователями</h1>
            </div>
            
            <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['message'] ?>
                <?php unset($_SESSION['message']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= $_SESSION['error'] ?>
                <?php unset($_SESSION['error']); ?>
            </div>
            <?php endif; ?>
            
            <!-- Статистика -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['total'] ?></h3>
                        <p>Всего пользователей</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['admins'] ?></h3>
                        <p>Администраторы</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['managers'] ?></h3>
                        <p>Менеджеры</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['users'] ?></h3>
                        <p>Пользователи</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['new_today'] ?></h3>
                        <p>Новых сегодня</p>
                    </div>
                </div>
            </div>
            
            <!-- Фильтры -->
            <div class="filter-section">
                <form method="get" class="filter-form">
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Поиск по имени, email или телефону" 
                                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <select name="role" class="form-control">
                                <option value="">Все роли</option>
                                <option value="admin" <?= ($_GET['role'] ?? '') == 'admin' ? 'selected' : '' ?>>Администратор</option>
                                <option value="manager" <?= ($_GET['role'] ?? '') == 'manager' ? 'selected' : '' ?>>Менеджер</option>
                                <option value="user" <?= ($_GET['role'] ?? '') == 'user' ? 'selected' : '' ?>>Пользователь</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Поиск
                            </button>
                            <a href="users.php" class="btn btn-outline">Сбросить</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Таблица пользователей -->
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Пользователь</th>
                            <th>Контактная информация</th>
                            <th>Роль</th>
                            <th>Дата регистрации</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>#<?= $user['id'] ?></td>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar">
                                        <i class="fas fa-user-circle"></i>
                                    </div>
                                    <div class="user-info">
                                        <strong><?= htmlspecialchars($user['name']) ?></strong><br>
                                        <small>ID: <?= $user['id'] ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="contact-info">
                                    <div class="contact-item">
                                        <i class="fas fa-envelope"></i>
                                        <span><?= htmlspecialchars($user['email']) ?></span>
                                    </div>
                                    <?php if ($user['phone']): ?>
                                    <div class="contact-item">
                                        <i class="fas fa-phone"></i>
                                        <span><?= htmlspecialchars($user['phone']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <form method="POST" class="role-form">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <input type="hidden" name="action" value="update_role">
                                    <select name="role" class="form-control role-select" 
                                            onchange="this.form.submit()" 
                                            <?= $user['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                        <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Администратор</option>
                                        <option value="manager" <?= $user['role'] == 'manager' ? 'selected' : '' ?>>Менеджер</option>
                                        <option value="user" <?= $user['role'] == 'user' ? 'selected' : '' ?>>Пользователь</option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <?= date('d.m.Y', strtotime($user['created_at'])) ?><br>
                                <small><?= date('H:i', strtotime($user['created_at'])) ?></small>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-outline btn-sm" 
                                            onclick="viewUserProfile(<?= $user['id'] ?>)"
                                            title="Просмотр профиля">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn btn-warning btn-sm" 
                                            onclick="resetUserPassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')"
                                            title="Сбросить пароль">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    
                                    <form method="POST" class="delete-form" 
                                          onsubmit="return confirmDelete('<?= htmlspecialchars($user['name']) ?>')">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Удалить">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (empty($users)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>Пользователи не найдены</h3>
                    <p>Попробуйте изменить параметры поиска</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Пагинация -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                   class="pagination-link">
                    <i class="fas fa-chevron-left"></i> Назад
                </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                       class="pagination-link <?= $i == $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                    <span class="pagination-dots">...</span>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                   class="pagination-link">
                    Далее <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="../assets/js/admin.js"></script>
    <script>
    function viewUserProfile(userId) {
        // Открываем модальное окно с информацией о пользователе
        alert('Просмотр профиля пользователя #' + userId);
        // Здесь можно реализовать AJAX запрос для получения полной информации
    }
    
    function resetUserPassword(userId, userName) {
        if (confirm(`Сбросить пароль пользователю "${userName}"? Новый пароль будет сгенерирован автоматически.`)) {
            // Отправляем форму сброса пароля
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="user_id" value="${userId}">
                <input type="hidden" name="action" value="reset_password">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    function confirmDelete(userName) {
        return confirm(`Удалить пользователя "${userName}"? Это действие нельзя отменить.`);
    }
    
    // Модальное окно для просмотра профиля пользователя
    function showUserModal(userId) {
        // AJAX запрос для получения информации о пользователе
        fetch(`../api/users.php?action=get_user&id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Показываем модальное окно с информацией
                    console.log('Информация о пользователе:', data.user);
                }
            })
            .catch(error => console.error('Ошибка:', error));
    }
    </script>
</body>
</html>

<style>
.user-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e3f2fd;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.user-avatar i {
    font-size: 1.5rem;
    color: #007bff;
}

.contact-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
}

.contact-item i {
    width: 16px;
    color: #666;
}

.role-form {
    margin: 0;
}

.role-select {
    min-width: 120px;
}

.delete-form {
    display: inline;
}

.btn-warning {
    background-color: #ffc107;
    color: #212529;
    border-color: #ffc107;
}

.btn-warning:hover {
    background-color: #e0a800;
    border-color: #d39e00;
}
</style>