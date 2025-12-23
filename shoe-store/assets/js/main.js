// Основной JavaScript файл

document.addEventListener('DOMContentLoaded', function() {
    // Инициализация всех компонентов
    
    // Мобильное меню
    initMobileMenu();
    
    // Поиск
    initSearch();
    
    // Счетчик корзины
    updateCartCount();
    
    // Уведомления
    initNotifications();
    
    // Формы
    initForms();
});

// Мобильное меню
function initMobileMenu() {
    const toggle = document.querySelector('.mobile-toggle');
    const navMenu = document.querySelector('.nav-menu');
    
    if (toggle && navMenu) {
        toggle.addEventListener('click', function() {
            navMenu.classList.toggle('show');
            this.classList.toggle('active');
        });
        
        // Закрытие меню при клике вне его
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-menu') && !e.target.closest('.mobile-toggle')) {
                navMenu.classList.remove('show');
                toggle.classList.remove('active');
            }
        });
    }
}

// Поиск
function initSearch() {
    const searchForm = document.querySelector('.search-form');
    const searchInput = searchForm?.querySelector('input[name="search"]');
    
    if (searchInput) {
        // Автодополнение поиска
        searchInput.addEventListener('input', debounce(function(e) {
            const query = e.target.value.trim();
            if (query.length >= 2) {
                searchAutocomplete(query);
            }
        }, 300));
    }
}

// Автодополнение поиска
function searchAutocomplete(query) {
    fetch(`api/products.php?action=search&q=${encodeURIComponent(query)}&limit=5`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.results.length > 0) {
                showSearchSuggestions(data.results);
            }
        })
        .catch(error => console.error('Ошибка поиска:', error));
}

// Показ подсказок поиска
function showSearchSuggestions(results) {
    // Реализация отображения подсказок
    console.log('Результаты поиска:', results);
}

// Обновление счетчика корзины
function updateCartCount() {
    const cartCount = document.getElementById('cart-count');
    if (!cartCount) return;
    
    // Для авторизованных пользователей
    if (typeof isLoggedIn !== 'undefined' && isLoggedIn()) {
        fetch('api/cart.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    cartCount.textContent = data.count;
                }
            })
            .catch(error => console.error('Ошибка обновления корзины:', error));
    } else {
        // Для неавторизованных - из localStorage
        const cartData = localStorage.getItem('cart');
        if (cartData) {
            const cart = JSON.parse(cartData);
            const count = cart.items.reduce((total, item) => total + item.quantity, 0);
            cartCount.textContent = count;
        }
    }
}

// Уведомления
function initNotifications() {
    // Показ flash сообщений
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
}

// Формы
function initForms() {
    // Валидация форм
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
    
    // Маски для телефонов
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            formatPhoneNumber(this);
        });
    });
}

// Валидация формы
function validateForm(form) {
    let isValid = true;
    const required = form.querySelectorAll('[required]');
    
    required.forEach(field => {
        if (!field.value.trim()) {
            markInvalid(field, 'Это поле обязательно для заполнения');
            isValid = false;
        } else {
            markValid(field);
        }
    });
    
    // Дополнительные проверки
    const emailFields = form.querySelectorAll('input[type="email"]');
    emailFields.forEach(field => {
        if (field.value && !isValidEmail(field.value)) {
            markInvalid(field, 'Введите корректный email');
            isValid = false;
        }
    });
    
    return isValid;
}

// Проверка email
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Отметка невалидного поля
function markInvalid(field, message) {
    field.classList.add('is-invalid');
    field.classList.remove('is-valid');
    
    // Удаляем старое сообщение об ошибке
    const existingError = field.parentElement.querySelector('.invalid-feedback');
    if (existingError) {
        existingError.remove();
    }
    
    // Добавляем новое сообщение
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback';
    errorDiv.textContent = message;
    field.parentElement.appendChild(errorDiv);
}

// Отметка валидного поля
function markValid(field) {
    field.classList.remove('is-invalid');
    field.classList.add('is-valid');
    
    // Удаляем сообщение об ошибке
    const existingError = field.parentElement.querySelector('.invalid-feedback');
    if (existingError) {
        existingError.remove();
    }
}

// Форматирование номера телефона
function formatPhoneNumber(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length > 0) {
        value = value.match(/(\d{0,1})(\d{0,3})(\d{0,3})(\d{0,2})(\d{0,2})/);
        input.value = !value[2] ? value[1] : '+7 (' + value[2] + ') ' + value[3] + (value[4] ? '-' + value[4] : '') + (value[5] ? '-' + value[5] : '');
    }
}

// Debounce функция
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// AJAX запросы
function ajaxRequest(url, options = {}) {
    const defaults = {
        method: 'GET',
        data: null,
        headers: {
            'Content-Type': 'application/json'
        }
    };
    
    const config = { ...defaults, ...options };
    
    return fetch(url, {
        method: config.method,
        headers: config.headers,
        body: config.data ? JSON.stringify(config.data) : null,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    });
}

// Показать уведомление
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close">&times;</button>
    `;
    
    document.body.appendChild(notification);
    
    // Анимация появления
    setTimeout(() => notification.classList.add('show'), 10);
    
    // Закрытие
    notification.querySelector('.notification-close').addEventListener('click', () => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    });
    
    // Автоматическое закрытие
    setTimeout(() => {
        if (notification.parentNode) {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

// Проверка авторизации
function checkAuth() {
    return document.cookie.includes('PHPSESSID');
}

// Глобальные функции
window.ajaxRequest = ajaxRequest;
window.showNotification = showNotification;
window.isLoggedIn = checkAuth;

// Стили для уведомлений и валидации
const styles = `
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    padding: 15px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    min-width: 300px;
    max-width: 400px;
    transform: translateX(120%);
    transition: transform 0.3s ease;
    z-index: 9999;
}

.notification.show {
    transform: translateX(0);
}

.notification-success {
    border-left: 4px solid #28a745;
}

.notification-error {
    border-left: 4px solid #dc3545;
}

.notification-info {
    border-left: 4px solid #17a2b8;
}

.notification-content {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.notification-content i {
    font-size: 1.2rem;
}

.notification-success .notification-content i {
    color: #28a745;
}

.notification-error .notification-content i {
    color: #dc3545;
}

.notification-info .notification-content i {
    color: #17a2b8;
}

.notification-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #666;
    cursor: pointer;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.is-invalid {
    border-color: #dc3545 !important;
}

.is-valid {
    border-color: #28a745 !important;
}

.invalid-feedback {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: #dc3545;
}

@media (max-width: 768px) {
    .notification {
        left: 20px;
        right: 20px;
        max-width: none;
    }
}
`;

// Добавляем стили в документ
const styleSheet = document.createElement('style');
styleSheet.textContent = styles;
document.head.appendChild(styleSheet);