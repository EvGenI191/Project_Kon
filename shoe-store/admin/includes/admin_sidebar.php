<?php
// Боковое меню админки
?>
<aside class="admin-sidebar">
    <nav class="admin-nav">
        <a href="index.php" <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : '' ?>>
            <i class="fas fa-tachometer-alt"></i> Дашборд
        </a>
        <a href="products.php" <?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'class="active"' : '' ?>>
            <i class="fas fa-shoe-prints"></i> Товары
        </a>
        <a href="add_product.php" <?= basename($_SERVER['PHP_SELF']) == 'add_product.php' ? 'class="active"' : '' ?>>
            <i class="fas fa-plus-circle"></i> Добавить товар
        </a>
        <a href="orders.php" <?= basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'class="active"' : '' ?>>
            <i class="fas fa-shopping-bag"></i> Заказы
        </a>
        <?php if (isAdmin()): ?>
        <a href="users.php" <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'class="active"' : '' ?>>
            <i class="fas fa-users"></i> Пользователи
        </a>
        <?php endif; ?>
        <a href="../index.php">
            <i class="fas fa-store"></i> Вернуться в магазин
        </a>
    </nav>
</aside>