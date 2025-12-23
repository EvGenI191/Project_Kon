<?php
$cartCount = getCartCount();
?>
<header>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="index.php">
                    <i class="fas fa-shoe-prints"></i>
                    <span><?= SITE_NAME ?></span>
                </a>
            </div>
            
            <div class="nav-search">
                <form action="catalog.php" method="get" class="search-form">
                    <input type="text" name="search" placeholder="Поиск товаров...">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
            
            <div class="nav-menu">
                <a href="catalog.php"><i class="fas fa-th-large"></i> Каталог</a>
                
                <a href="cart.php" class="cart-link">
                    <i class="fas fa-shopping-cart"></i>
                    Корзина
                    <span class="cart-count" id="cart-count"><?= $cartCount ?></span>
                </a>
                
                <?php if (isLoggedIn()): ?>
                    <div class="user-menu">
                        <a href="profile.php" class="user-link">
                            <i class="fas fa-user"></i>
                            <?= htmlspecialchars($_SESSION['user_name']) ?>
                        </a>
                        <div class="user-dropdown">
                            <a href="profile.php"><i class="fas fa-user-circle"></i> Профиль</a>
                            <a href="orders.php"><i class="fas fa-history"></i> Заказы</a>
                            <?php if (isManager()): ?>
                                <a href="admin/"><i class="fas fa-cog"></i> Панель управления</a>
                            <?php endif; ?>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выход</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline">Войти</a>
                    <a href="register.php" class="btn btn-primary">Регистрация</a>
                <?php endif; ?>
            </div>
            
            <button class="mobile-toggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>
</header>