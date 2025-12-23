<?php
// Заголовок для админки
?>
<header class="admin-header">
    <div class="admin-header-content">
        <div class="admin-logo">
            <a href="index.php">
                <i class="fas fa-cog"></i>
                <span>Панель управления</span>
            </a>
        </div>
        
        <div class="admin-user-info">
            <div class="user-dropdown">
                <button class="user-toggle">
                    <i class="fas fa-user-circle"></i>
                    <span><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="user-dropdown-menu">
                    <a href="../profile.php"><i class="fas fa-user"></i> Мой профиль</a>
                    <a href="../"><i class="fas fa-store"></i> Магазин</a>
                    <div class="dropdown-divider"></div>
                    <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Выход</a>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
.admin-header {
    background: #343a40;
    color: white;
    padding: 0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.admin-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 20px;
    height: 60px;
}

.admin-logo a {
    color: white;
    text-decoration: none;
    font-size: 1.2rem;
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 10px;
}

.admin-logo i {
    color: #007bff;
}

.admin-user-info .user-dropdown {
    position: relative;
}

.user-toggle {
    background: none;
    border: none;
    color: white;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 8px 16px;
    border-radius: 4px;
    transition: background-color 0.3s;
}

.user-toggle:hover {
    background-color: rgba(255,255,255,0.1);
}

.user-dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    min-width: 200px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-radius: 4px;
    display: none;
    z-index: 1001;
}

.user-dropdown:hover .user-dropdown-menu {
    display: block;
}

.user-dropdown-menu a {
    display: block;
    padding: 10px 16px;
    color: #333;
    text-decoration: none;
    border-bottom: 1px solid #eee;
    transition: background-color 0.3s;
}

.user-dropdown-menu a:hover {
    background-color: #f8f9fa;
}

.user-dropdown-menu a i {
    width: 20px;
    margin-right: 8px;
}

.dropdown-divider {
    height: 1px;
    background: #eee;
    margin: 5px 0;
}
</style>