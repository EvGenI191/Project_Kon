// Логика работы каталога и фильтров

class CatalogManager {
    constructor() {
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.applySavedFilters();
        this.initPriceSlider();
        this.initLazyLoading();
    }
    
    bindEvents() {
        // Применение фильтров
        const filterForm = document.getElementById('filter-form');
        if (filterForm) {
            filterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.applyFilters();
            });
        }
        
        // Сброс фильтров
        const resetBtn = document.querySelector('.filter-form a[href="catalog.php"]');
        if (resetBtn) {
            resetBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.resetFilters();
            });
        }
        
        // Сортировка
        const sortSelect = document.querySelector('select[name="sort"]');
        if (sortSelect) {
            sortSelect.addEventListener('change', () => {
                this.applySorting();
            });
        }
        
        // Ленивая загрузка изображений
        this.initLazyLoading();
        
        // Бесконечная прокрутка
        this.initInfiniteScroll();
        
        // Быстрый просмотр товара
        this.initQuickView();
    }
    
    // Применение фильтров
    applyFilters() {
        const form = document.getElementById('filter-form');
        const formData = new FormData(form);
        const params = new URLSearchParams();
        
        // Собираем параметры фильтров
        for (const [key, value] of formData.entries()) {
            if (value) {
                if (key === 'brand[]') {
                    // Для чекбоксов собираем все значения
                    if (!params.has('brand[]')) {
                        params.append('brand[]', value);
                    } else {
                        // Добавляем дополнительное значение
                        const values = params.getAll('brand[]');
                        values.push(value);
                        params.delete('brand[]');
                        values.forEach(v => params.append('brand[]', v));
                    }
                } else {
                    params.append(key, value);
                }
            }
        }
        
        // Сохраняем фильтры в localStorage
        this.saveFilters(params);
        
        // Переходим на страницу с фильтрами
        window.location.href = 'catalog.php?' + params.toString();
    }
    
    // Сохранение фильтров
    saveFilters(params) {
        const filters = {};
        for (const [key, value] of params.entries()) {
            filters[key] = value;
        }
        localStorage.setItem('catalog_filters', JSON.stringify(filters));
    }
    
    // Применение сохраненных фильтров
    applySavedFilters() {
        const savedFilters = localStorage.getItem('catalog_filters');
        if (!savedFilters) return;
        
        const filters = JSON.parse(savedFilters);
        const form = document.getElementById('filter-form');
        
        if (!form) return;
        
        // Заполняем форму сохраненными значениями
        Object.entries(filters).forEach(([key, value]) => {
            if (key.endsWith('[]')) {
                // Обработка массивов (чекбоксы)
                const checkboxes = form.querySelectorAll(`input[name="${key}"]`);
                checkboxes.forEach(checkbox => {
                    checkbox.checked = Array.isArray(value) ? 
                        value.includes(checkbox.value) : 
                        value === checkbox.value;
                });
            } else {
                const input = form.querySelector(`[name="${key}"]`);
                if (input) {
                    input.value = value;
                    
                    // Для радио-кнопок
                    if (input.type === 'radio') {
                        input.checked = true;
                    }
                }
            }
        });
    }
    
    // Сброс фильтров
    resetFilters() {
        localStorage.removeItem('catalog_filters');
        window.location.href = 'catalog.php';
    }
    
    // Применение сортировки
    applySorting() {
        const sortSelect = document.querySelector('select[name="sort"]');
        if (!sortSelect) return;
        
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('sort', sortSelect.value);
        currentUrl.searchParams.set('page', '1'); // Сбрасываем на первую страницу
        
        window.location.href = currentUrl.toString();
    }
    
    // Инициализация слайдера цены
    initPriceSlider() {
        const priceSlider = document.querySelector('.price-slider');
        if (!priceSlider) return;
        
        const minPriceInput = document.querySelector('input[name="min_price"]');
        const maxPriceInput = document.querySelector('input[name="max_price"]');
        const minPriceValue = document.getElementById('min-price-value');
        const maxPriceValue = document.getElementById('max-price-value');
        
        if (!minPriceInput || !maxPriceInput) return;
        
        // Получаем минимальную и максимальную цены из данных
        const minPrice = parseInt(minPriceInput.dataset.min) || 0;
        const maxPrice = parseInt(maxPriceInput.dataset.max) || 100000;
        
        // Устанавливаем начальные значения
        minPriceInput.min = minPrice;
        minPriceInput.max = maxPrice;
        maxPriceInput.min = minPrice;
        maxPriceInput.max = maxPrice;
        
        // Обновление значений при изменении
        minPriceInput.addEventListener('input', () => {
            if (parseInt(minPriceInput.value) > parseInt(maxPriceInput.value)) {
                minPriceInput.value = maxPriceInput.value;
            }
            if (minPriceValue) {
                minPriceValue.textContent = this.formatPrice(minPriceInput.value);
            }
        });
        
        maxPriceInput.addEventListener('input', () => {
            if (parseInt(maxPriceInput.value) < parseInt(minPriceInput.value)) {
                maxPriceInput.value = minPriceInput.value;
            }
            if (maxPriceValue) {
                maxPriceValue.textContent = this.formatPrice(maxPriceInput.value);
            }
        });
        
        // Форматирование отображаемых цен
        if (minPriceValue) {
            minPriceValue.textContent = this.formatPrice(minPriceInput.value || minPrice);
        }
        if (maxPriceValue) {
            maxPriceValue.textContent = this.formatPrice(maxPriceInput.value || maxPrice);
        }
    }
    
    // Ленивая загрузка изображений
    initLazyLoading() {
        if ('IntersectionObserver' in window) {
            const lazyImages = document.querySelectorAll('img[data-src]');
            
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                });
            });
            
            lazyImages.forEach(img => imageObserver.observe(img));
        } else {
            // Fallback для старых браузеров
            const lazyImages = document.querySelectorAll('img[data-src]');
            lazyImages.forEach(img => {
                img.src = img.dataset.src;
            });
        }
    }
    
    // Бесконечная прокрутка
    initInfiniteScroll() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadMoreProducts();
                }
            });
        }, {
            rootMargin: '100px'
        });
        
        const sentinel = document.getElementById('scroll-sentinel');
        if (sentinel) {
            observer.observe(sentinel);
        }
    }
    
    // Загрузка дополнительных товаров
    async loadMoreProducts() {
        const currentPage = parseInt(document.querySelector('.pagination .active')?.textContent || 1);
        const nextPage = currentPage + 1;
        const url = new URL(window.location.href);
        url.searchParams.set('page', nextPage);
        
        try {
            const response = await fetch(url.toString());
            const text = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(text, 'text/html');
            
            const newProducts = doc.querySelector('.products-grid');
            const pagination = doc.querySelector('.pagination');
            
            if (newProducts) {
                const productsGrid = document.querySelector('.products-grid');
                productsGrid.innerHTML += newProducts.innerHTML;
                
                if (pagination) {
                    document.querySelector('.pagination').innerHTML = pagination.innerHTML;
                }
                
                // Инициализируем новые изображения для ленивой загрузки
                this.initLazyLoading();
                
                // Инициализируем кнопки добавления в корзину для новых товаров
                if (window.cartManager) {
                    window.cartManager.bindEvents();
                }
            }
        } catch (error) {
            console.error('Ошибка загрузки товаров:', error);
        }
    }
    
    // Быстрый просмотр товара
    initQuickView() {
        document.addEventListener('click', (e) => {
            const quickViewBtn = e.target.closest('.quick-view');
            if (quickViewBtn) {
                e.preventDefault();
                const productId = quickViewBtn.dataset.productId;
                this.showQuickView(productId);
            }
        });
    }
    
    // Показ быстрого просмотра
    async showQuickView(productId) {
        try {
            const response = await fetch(`api/products.php?action=get_product&id=${productId}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderQuickView(data.product);
            }
        } catch (error) {
            console.error('Ошибка загрузки товара:', error);
        }
    }
    
    // Рендеринг окна быстрого просмотра
    renderQuickView(product) {
        const modal = document.createElement('div');
        modal.className = 'quick-view-modal';
        modal.innerHTML = `
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <button class="modal-close">&times;</button>
                
                <div class="quick-view-content">
                    <div class="quick-view-images">
                        <div class="main-image">
                            <img src="${product.main_image}" alt="${product.name}">
                        </div>
                    </div>
                    
                    <div class="quick-view-info">
                        <h2>${product.name}</h2>
                        <div class="product-meta">
                            <span class="brand">${product.brand}</span>
                            <span class="category">${product.category}</span>
                        </div>
                        
                        <div class="product-price">
                            <span class="current-price">${this.formatPrice(product.price)} ₽</span>
                        </div>
                        
                        <div class="product-description">
                            <p>${product.description}</p>
                        </div>
                        
                        <div class="product-sizes">
                            <h3>Выберите размер:</h3>
                            <div class="size-options">
                                ${product.sizes ? product.sizes.map(size => `
                                    <label class="size-option ${size.quantity === 0 ? 'disabled' : ''}">
                                        <input type="radio" name="size" value="${size.size}" 
                                               ${size.quantity === 0 ? 'disabled' : ''}>
                                        <span>${size.size}</span>
                                        <small>${size.quantity === 0 ? 'Нет в наличии' : `${size.quantity} шт.`}</small>
                                    </label>
                                `).join('') : '<p>Нет доступных размеров</p>'}
                            </div>
                        </div>
                        
                        <div class="product-actions">
                            <div class="quantity-selector">
                                <button class="quantity-btn minus">-</button>
                                <input type="number" class="quantity-input" value="1" min="1" max="10">
                                <button class="quantity-btn plus">+</button>
                            </div>
                            
                            <button class="btn btn-primary add-to-cart" 
                                    data-product-id="${product.id}">
                                <i class="fas fa-shopping-cart"></i> Добавить в корзину
                            </button>
                            
                            <a href="product.php?id=${product.id}" class="btn btn-outline">
                                Подробнее
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Закрытие модального окна
        modal.querySelector('.modal-close').addEventListener('click', () => modal.remove());
        modal.querySelector('.modal-overlay').addEventListener('click', () => modal.remove());
        
        // Управление количеством
        const quantityInput = modal.querySelector('.quantity-input');
        modal.querySelector('.quantity-btn.minus').addEventListener('click', () => {
            if (parseInt(quantityInput.value) > 1) {
                quantityInput.value = parseInt(quantityInput.value) - 1;
            }
        });
        
        modal.querySelector('.quantity-btn.plus').addEventListener('click', () => {
            if (parseInt(quantityInput.value) < 10) {
                quantityInput.value = parseInt(quantityInput.value) + 1;
            }
        });
        
        // Добавление в корзину
        modal.querySelector('.add-to-cart').addEventListener('click', () => {
            const size = modal.querySelector('input[name="size"]:checked');
            const quantity = parseInt(quantityInput.value);
            
            if (!size) {
                alert('Пожалуйста, выберите размер');
                return;
            }
            
            if (window.cartManager) {
                window.cartManager.addItem(product.id, size.value, quantity);
                modal.remove();
            }
        });
    }
    
    // Форматирование цены
    formatPrice(price) {
        return parseInt(price).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }
    
    // AJAX фильтрация
    async filterProducts() {
        const form = document.getElementById('filter-form');
        const formData = new FormData(form);
        const params = new URLSearchParams();
        
        for (const [key, value] of formData.entries()) {
            if (value) {
                if (key === 'brand[]') {
                    params.append('brand[]', value);
                } else {
                    params.append(key, value);
                }
            }
        }
        
        try {
            const response = await fetch(`api/products.php?action=filter&${params.toString()}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderFilteredProducts(data.products);
            }
        } catch (error) {
            console.error('Ошибка фильтрации:', error);
        }
    }
    
    // Рендеринг отфильтрованных товаров
    renderFilteredProducts(products) {
        const productsGrid = document.querySelector('.products-grid');
        if (!productsGrid) return;
        
        if (products.length === 0) {
            productsGrid.innerHTML = `
                <div class="empty-catalog">
                    <i class="fas fa-search"></i>
                    <h3>Товары не найдены</h3>
                    <p>Попробуйте изменить параметры поиска</p>
                </div>
            `;
            return;
        }
        
        productsGrid.innerHTML = products.map(product => `
            <div class="product-card">
                <div class="product-image">
                    <img src="${product.main_image || 'assets/images/no-image.jpg'}" 
                         alt="${product.name}"
                         data-src="${product.main_image || 'assets/images/no-image.jpg'}">
                    ${product.status === 'hidden' ? '<div class="product-status">Нет в наличии</div>' : ''}
                </div>
                <div class="product-info">
                    <h3>
                        <a href="product.php?id=${product.id}">
                            ${product.name}
                        </a>
                    </h3>
                    <p class="product-category">${product.category}</p>
                    <p class="product-brand">${product.brand}</p>
                    <div class="product-price">${this.formatPrice(product.price)} ₽</div>
                    <div class="product-actions">
                        <button class="btn btn-outline quick-view" data-product-id="${product.id}">
                            <i class="fas fa-eye"></i> Быстрый просмотр
                        </button>
                        <button class="btn btn-primary add-to-cart" data-product-id="${product.id}">
                            <i class="fas fa-shopping-cart"></i> В корзину
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
        
        // Обновляем счетчик товаров
        const totalElement = document.querySelector('.catalog-header p');
        if (totalElement) {
            totalElement.textContent = `Найдено товаров: ${products.length}`;
        }
        
        // Инициализируем новые элементы
        this.initLazyLoading();
        this.initQuickView();
        
        if (window.cartManager) {
            window.cartManager.bindEvents();
        }
    }
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    window.catalogManager = new CatalogManager();
});

// Стили для каталога
const catalogStyles = `
.quick-view-modal {
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

.quick-view-modal .modal-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 900px;
    max-height: 90vh;
    overflow: hidden;
    z-index: 1;
    animation: slideUp 0.3s ease;
    position: relative;
}

.quick-view-modal .modal-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #666;
    cursor: pointer;
    padding: 5px;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
}

.quick-view-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    padding: 30px;
}

.quick-view-images .main-image {
    border: 1px solid #eee;
    border-radius: 8px;
    overflow: hidden;
    background: white;
}

.quick-view-images .main-image img {
    width: 100%;
    height: auto;
    display: block;
}

.quick-view-info h2 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #333;
    font-size: 1.5rem;
}

.quick-view-info .product-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    color: #666;
    font-size: 0.9rem;
}

.quick-view-info .product-price {
    margin-bottom: 20px;
}

.quick-view-info .current-price {
    font-size: 1.8rem;
    font-weight: bold;
    color: #007bff;
}

.quick-view-info .product-description {
    margin-bottom: 25px;
    color: #666;
    line-height: 1.6;
}

.quick-view-info .product-sizes {
    margin-bottom: 25px;
}

.quick-view-info .product-sizes h3 {
    margin-bottom: 15px;
    font-size: 1rem;
    color: #333;
}

.quick-view-info .size-options {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.quick-view-info .size-option {
    display: inline-flex;
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

.quick-view-info .size-option.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.quick-view-info .size-option:not(.disabled):hover {
    border-color: #007bff;
}

.quick-view-info .size-option input {
    display: none;
}

.quick-view-info .size-option input:checked + span {
    background: #007bff;
    color: white;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 2px;
}

.quick-view-info .size-option span {
    font-weight: 500;
    font-size: 0.9rem;
}

.quick-view-info .size-option small {
    font-size: 0.7rem;
    color: #666;
    margin-top: 2px;
}

.quick-view-info .product-actions {
    display: flex;
    gap: 15px;
    align-items: center;
}

.quick-view-info .quantity-selector {
    display: flex;
    align-items: center;
    gap: 5px;
}

.quick-view-info .quantity-btn {
    width: 36px;
    height: 36px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quick-view-info .quantity-btn:hover {
    background: #f8f9fa;
}

.quick-view-info .quantity-input {
    width: 60px;
    height: 36px;
    text-align: center;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

@media (max-width: 768px) {
    .quick-view-content {
        grid-template-columns: 1fr;
        gap: 20px;
        padding: 20px;
    }
    
    .quick-view-info .product-actions {
        flex-direction: column;
        align-items: stretch;
    }
}

.price-slider-container {
    padding: 20px 0;
}

.price-slider {
    width: 100%;
    height: 4px;
    background: #ddd;
    border-radius: 2px;
    outline: none;
    -webkit-appearance: none;
}

.price-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 20px;
    height: 20px;
    background: #007bff;
    border-radius: 50%;
    cursor: pointer;
}

.price-slider::-moz-range-thumb {
    width: 20px;
    height: 20px;
    background: #007bff;
    border-radius: 50%;
    cursor: pointer;
    border: none;
}

.price-values {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
    color: #666;
    font-size: 0.9rem;
}

#scroll-sentinel {
    height: 100px;
    width: 100%;
}
`;

// Добавляем стили
const catalogStyleSheet = document.createElement('style');
catalogStyleSheet.textContent = catalogStyles;
document.head.appendChild(catalogStyleSheet);