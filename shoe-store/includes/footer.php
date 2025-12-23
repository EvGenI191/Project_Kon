<?php
// Подвал сайта
?>
<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h3><?= SITE_NAME ?></h3>
                <p>Интернет-магазин качественной обуви для всей семьи</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-vk"></i></a>
                    <a href="#"><i class="fab fa-telegram"></i></a>
                    <a href="#"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
            
            <div class="footer-section">
                <h4>Категории</h4>
                <ul>
                    <li><a href="catalog.php?category=Мужская обувь">Мужская обувь</a></li>
                    <li><a href="catalog.php?category=Женская обувь">Женская обувь</a></li>
                    <li><a href="catalog.php?category=Детская обувь">Детская обувь</a></li>
                    <li><a href="catalog.php?category=Спортивная обувь">Спортивная обувь</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h4>Помощь</h4>
                <ul>
                    <li><a href="#">Доставка и оплата</a></li>
                    <li><a href="#">Возврат и обмен</a></li>
                    <li><a href="#">Размерная сетка</a></li>
                    <li><a href="#">Контакты</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h4>Контакты</h4>
                <ul>
                    <li><i class="fas fa-phone"></i> +7 (999) 123-45-67</li>
                    <li><i class="fas fa-envelope"></i> info@shoe-store.ru</li>
                    <li><i class="fas fa-map-marker-alt"></i> г. Москва, ул. Примерная, 123</li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. Все права защищены.</p>
        </div>
    </div>
</footer>

<style>
.footer {
    background-color: #333;
    color: #fff;
    padding: 3rem 0 1rem;
    margin-top: 3rem;
}

.footer-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
    margin-bottom: 2rem;
}

.footer-section h3,
.footer-section h4 {
    color: #fff;
    margin-bottom: 1rem;
}

.footer-section ul {
    list-style: none;
    padding: 0;
}

.footer-section ul li {
    margin-bottom: 0.5rem;
}

.footer-section a {
    color: #ddd;
    text-decoration: none;
}

.footer-section a:hover {
    color: #fff;
}

.social-links {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.social-links a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background-color: #444;
    border-radius: 50%;
    color: #fff;
    transition: background-color 0.3s;
}

.social-links a:hover {
    background-color: #007bff;
}

.footer-bottom {
    text-align: center;
    padding-top: 1rem;
    border-top: 1px solid #444;
    color: #aaa;
}

.footer-section i {
    margin-right: 0.5rem;
    width: 20px;
}
</style>