// Логика работы корзины

class CartManager {
    constructor() {
        this.localStorageKey = 'shoe_store_cart';
        this.init();
    }
    
    init() {
        // Создаем корзину в localStorage если ее нет
        if (!this.getLocalCart()) {
            this.createLocalCart();
        }
        
        // Обновляем счетчик корзины
        this.updateCartCount();
        
        // Назначаем обработчики событий
        this.bindEvents();
    }
    
    // Локальная корзина (для неавторизованных)
    getLocalCart() {
        const cartJson = localStorage.getItem(this.localStorageKey);
        return cartJson ? JSON.parse(cartJson) : null;
    }
    
    createLocalCart() {
        const cart = {
            id: 'local_' + Date.now(),
            items: [],
            created_at: new Date().toISOString()
        };
        this.saveLocalCart(cart);
        return cart;
    }
    
    saveLocalCart(cart) {
        localStorage.setItem(this.localStorageKey, JSON.stringify(cart));
    }
    
    // Работа с элементами корзины
    addItem(productId, size, quantity = 1) {
        if (window.isLoggedIn && window.isLoggedIn()) {
            // Для авторизованных - через API
            return this.addItemToServer(productId, size, quantity);
        } else {
            // Для неавторизованных - в localStorage
            return this.addItemToLocal(productId, size, quantity);
        }
    }
    
    addItemToLocal(productId, size, quantity) {
        const cart = this.getLocalCart();
        const existingItemIndex = cart.items.findIndex(item => 
            item.product_id == productId && item.size == size
        );
        
        if (existingItemIndex >= 0) {
            cart.items[existingItemIndex].quantity += quantity;
        } else {
            cart.items.push({
                product_id: productId,
                size: size,
                quantity: quantity,
                added_at: new Date().toISOString()
            });
        }
        
        this.saveLocalCart(cart);
        this.updateCartCount();
        
        // Показываем уведомление
        if (window.showNotification) {
            window.showNotification('Товар добавлен в корзину', 'success');
        }
        
        return Promise.resolve({ success: true });
    }
    
    async addItemToServer(productId, size, quantity) {
        try {
            const response = await window.ajaxRequest('api/cart.php', {
                method: 'POST',
                data: { product_id: productId, size: size, quantity: quantity }
            });
            
            if (response.success) {
                this.updateCartCount();
                if (window.showNotification) {
                    window.showNotification('Товар добавлен в корзину', 'success');
                }
            } else {
                if (window.showNotification) {
                    window.showNotification(response.error || 'Ошибка добавления в корзину', 'error');
                }
            }
            
            return response;
        } catch (error) {
            console.error('Ошибка добавления в корзину:', error);
            if (window.showNotification) {
                window.showNotification('Ошибка добавления в корзину', 'error');
            }
            return { success: false, error: 'Ошибка сети' };
        }
    }
    
    // Обновление количества
    async updateQuantity(itemId, quantity) {
        try {
            const response = await window.ajaxRequest('api/cart.php', {
                method: 'PUT',
                data: { item_id: itemId, quantity: quantity }
            });
            
            if (response.success) {
                this.updateCartCount();
                if (window.showNotification) {
                    window.showNotification('Количество обновлено', 'success');
                }
            }
            
            return response;
        } catch (error) {
            console.error('Ошибка обновления количества:', error);
            return { success: false, error: 'Ошибка сети' };
        }
    }
    
    // Удаление из корзины
    async removeItem(itemId) {
        try {
            const response = await window.ajaxRequest('api/cart.php', {
                method: 'DELETE',
                data: { item_id: itemId }
            });
            
            if (response.success) {
                this.updateCartCount();
                if (window.showNotification) {
                    window.showNotification('Товар удален из корзины', 'success');
                }
            }
            
            return response;
        } catch (error) {
            console.error('Ошибка удаления из корзины:', error);
            return { success: false, error: 'Ошибка сети' };
        }
    }
    
    // Обновление счетчика корзины
    async updateCartCount() {
        const cartCount = document.getElementById('cart-count');
        if (!cartCount) return;
        
        if (window.isLoggedIn && window.isLoggedIn()) {
            try {
                const response = await window.ajaxRequest('api/cart.php');
                if (response.success) {
                    cartCount.textContent = response.count;
                }
            } catch (error) {
                console.error('Ошибка обновления счетчика корзины:', error);
            }
        } else {
            const cart = this.getLocalCart();
            const count = cart.items.reduce((total, item) => total + item.quantity, 0);
            cartCount.textContent = count;
        }
    }
    
    // Слияние корзин при авторизации
    async mergeCarts() {
        if (!window.isLoggedIn || !window.isLoggedIn()) return;
        
        const localCart = this.getLocalCart();
        if (!localCart || localCart.items.length === 0) return;
        
        try {
            // Добавляем все товары из локальной корзины в серверную
            for (const item of localCart.items) {
                await this.addItemToServer(item.product_id, item.size, item.quantity);
            }
            
            // Очищаем локальную корзину
            this.createLocalCart();
            this.updateCartCount();
            
        } catch (error) {
            console.error('Ошибка слияния корзин:', error);
        }
    }
    
    // Получение общего количества товаров
    getTotalCount() {
        if (window.isLoggedIn && window.isLoggedIn()) {
            // Для авторизованных нужно делать запрос к серверу
            return 0; // Будет обновлено через updateCartCount
        } else {
            const cart = this.getLocalCart();
            return cart.items.reduce((total, item) => total + item.quantity, 0);
        }
    }
    
    // Получение общей суммы
    async getTotalAmount() {
        if (window.isLoggedIn && window.isLoggedIn()) {
            try {
                const response = await window.ajaxRequest('api/cart.php');
                return response.success ? response.total : 0;
            } catch (error) {
                console.error('Ошибка получения суммы корзины:', error);
                return 0;
            }
        } else {
            // Для локальной корзины нужно получать цены товаров
            // Это упрощенная версия
            return 0;
        }
    }
    
    // Обработчики событий
    bindEvents() {
        // Кнопки "Добавить в корзину"
        document.addEventListener('click', (e) => {
            const addToCartBtn = e.target.closest('.add-to-cart, .btn-add-to-cart');
            if (addToCartBtn) {
                e.preventDefault();
                
                const productId = addToCartBtn.dataset.productId;
                const sizeSelect = addToCartBtn.parentElement.querySelector('select[name="size"]') || 
                                 document.querySelector(`#size-${productId}`);
                
                if (sizeSelect) {
                    const size = sizeSelect.value;
                    if (!size) {
                        if (window.showNotification) {
                            window.showNotification('Пожалуйста, выберите размер', 'error');
                        }
                        return;
                    }
                    this.addItem(productId, size, 1);
                } else {
                    // Если нет выбора размера, добавляем с размером по умолчанию
                    this.addItem(productId, 'M', 1);
                }
            }
        });
        
        // Обновление количества в корзине
        document.addEventListener('change', (e) => {
            const quantityInput = e.target.closest('.cart-quantity, .quantity-input');
            if (quantityInput && quantityInput.dataset.itemId) {
                const itemId = quantityInput.dataset.itemId;
                const quantity = parseInt(quantityInput.value);
                
                if (!isNaN(quantity) && quantity >= 0) {
                    this.updateQuantity(itemId, quantity);
                    
                    // Обновляем сумму в строке
                    this.updateRowTotal(quantityInput.closest('tr, .cart-item'));
                }
            }
        });
        
        // Удаление из корзины
        document.addEventListener('click', (e) => {
            const removeBtn = e.target.closest('.remove-from-cart, .btn-remove-item');
            if (removeBtn && removeBtn.dataset.itemId) {
                if (confirm('Удалить товар из корзины?')) {
                    this.removeItem(removeBtn.dataset.itemId);
                    
                    // Удаляем строку из таблицы
                    const row = removeBtn.closest('tr, .cart-item');
                    if (row) {
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 300);
                    }
                }
            }
        });
        
        // Очистка корзины
        const clearCartBtn = document.querySelector('.clear-cart');
        if (clearCartBtn) {
            clearCartBtn.addEventListener('click', (e) => {
                if (confirm('Очистить всю корзину?')) {
                    // Реализация очистки корзины
                    console.log('Очистка корзины');
                }
            });
        }
    }
    
    // Обновление суммы в строке корзины
    updateRowTotal(row) {
        const priceElement = row.querySelector('.item-price, .cart-item-price');
        const quantityInput = row.querySelector('.cart-quantity, .quantity-input');
        const totalElement = row.querySelector('.item-total, .cart-item-total');
        
        if (priceElement && quantityInput && totalElement) {
            const price = parseFloat(priceElement.textContent.replace(/[^\d.]/g, ''));
            const quantity = parseInt(quantityInput.value);
            
            if (!isNaN(price) && !isNaN(quantity)) {
                const total = price * quantity;
                totalElement.textContent = this.formatPrice(total) + ' ₽';
            }
        }
    }
    
    // Форматирование цены
    formatPrice(price) {
        return price.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }
    
    // Открытие модального окна для выбора размера
    showSizeModal(productId, productName) {
        // Создаем модальное окно
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Выберите размер</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Выберите размер для товара: <strong>${productName}</strong></p>
                    <div class="size-options">
                        <!-- Размеры будут загружены через AJAX -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline modal-cancel">Отмена</button>
                    <button class="btn btn-primary modal-add" disabled>Добавить в корзину</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Загружаем доступные размеры
        this.loadSizes(productId, modal);
        
        // Обработчики событий модального окна
        modal.querySelector('.modal-close').addEventListener('click', () => {
            modal.remove();
        });
        
        modal.querySelector('.modal-cancel').addEventListener('click', () => {
            modal.remove();
        });
        
        modal.querySelector('.modal-overlay').addEventListener('click', () => {
            modal.remove();
        });
    }
    
    // Загрузка доступных размеров
    async loadSizes(productId, modal) {
        try {
            const response = await window.ajaxRequest(`api/products.php?action=get_sizes&product_id=${productId}`);
            const sizeOptions = modal.querySelector('.size-options');
            
            if (response.success && response.sizes.length > 0) {
                sizeOptions.innerHTML = response.sizes.map(size => `
                    <label class="size-option">
                        <input type="radio" name="size" value="${size.size}">
                        <span>${size.size}</span>
                        <small>${size.quantity} шт.</small>
                    </label>
                `).join('');
                
                // Включаем кнопку добавления
                modal.querySelector('.modal-add').disabled = false;
                
                // Обработчик выбора размера
                sizeOptions.querySelectorAll('input[name="size"]').forEach(input => {
                    input.addEventListener('change', () => {
                        modal.querySelector('.modal-add').dataset.size = input.value;
                    });
                });
                
                // Обработчик добавления в корзину
                modal.querySelector('.modal-add').addEventListener('click', () => {
                    const selectedSize = modal.querySelector('input[name="size"]:checked');
                    if (selectedSize) {
                        this.addItem(productId, selectedSize.value, 1);
                        modal.remove();
                    }
                });
            } else {
                sizeOptions.innerHTML = '<p>Нет доступных размеров</p>';
            }
        } catch (error) {
            console.error('Ошибка загрузки размеров:', error);
            modal.querySelector('.size-options').innerHTML = '<p>Ошибка загрузки размеров</p>';
        }
    }
}

// Инициализация менеджера корзины при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    window.cartManager = new CartManager();
    
    // Слияние корзин при авторизации
    if (window.isLoggedIn && window.isLoggedIn()) {
        window.cartManager.mergeCarts();
    }
});

// Стили для модального окна
const cartStyles = `
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}

.modal-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 400px;
    max-height: 90vh;
    overflow: hidden;
    z-index: 1;
    animation: slideUp 0.3s ease;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.modal-header h3 {
    margin: 0;
    color: #333;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #666;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body {
    padding: 20px;
    max-height: 60vh;
    overflow-y: auto;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.size-options {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 15px;
}

.size-option {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    border: 2px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
}

.size-option input {
    display: none;
}

.size-option:hover {
    border-color: #007bff;
}

.size-option input:checked + span {
    background: #007bff;
    color: white;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 2px;
}

.size-option span {
    font-weight: 500;
    font-size: 0.9rem;
}

.size-option small {
    font-size: 0.7rem;
    color: #666;
    margin-top: 2px;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { 
        transform: translateY(50px);
        opacity: 0;
    }
    to { 
        transform: translateY(0);
        opacity: 1;
    }
}
`;

// Добавляем стили
const cartStyleSheet = document.createElement('style');
cartStyleSheet.textContent = cartStyles;
document.head.appendChild(cartStyleSheet);