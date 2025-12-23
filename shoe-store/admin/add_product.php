<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireManager();

$db = Database::connect();
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Валидация данных
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $color = trim($_POST['color'] ?? '');
    $material = trim($_POST['material'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Валидация
    if (empty($name)) {
        $errors[] = 'Название товара обязательно';
    }
    
    if (empty($category)) {
        $errors[] = 'Категория обязательна';
    }
    
    if (empty($brand)) {
        $errors[] = 'Бренд обязателен';
    }
    
    if ($price <= 0) {
        $errors[] = 'Цена должна быть больше 0';
    }
    
    // Если нет ошибок, сохраняем товар
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Сохраняем основной товар
            $stmt = $db->prepare("
                INSERT INTO products (name, description, category, brand, price, color, material, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $category, $brand, $price, $color, $material, $status]);
            $productId = $db->lastInsertId();
            
            // Сохраняем размеры
            if (isset($_POST['sizes']) && is_array($_POST['sizes'])) {
                foreach ($_POST['sizes'] as $sizeData) {
                    $size = floatval($sizeData['size']);
                    $quantity = intval($sizeData['quantity']);
                    
                    if ($size > 0) {
                        $stmt = $db->prepare("
                            INSERT INTO product_sizes (product_id, size, quantity)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$productId, $size, $quantity]);
                    }
                }
            }
            
            // Обработка загруженных изображений
            if (!empty($_FILES['images']['name'][0])) {
                $mainSet = false;
                
                foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {
                    if ($_FILES['images']['error'][$index] === UPLOAD_OK) {
                        // Генерируем уникальное имя файла
                        $extension = pathinfo($_FILES['images']['name'][$index], PATHINFO_EXTENSION);
                        $filename = uniqid() . '.' . $extension;
                        $destination = UPLOAD_PATH . $filename;
                        
                        if (move_uploaded_file($tmpName, $destination)) {
                            $isMain = (!$mainSet) ? 1 : 0;
                            $mainSet = true;
                            
                            $stmt = $db->prepare("
                                INSERT INTO product_images (product_id, image_url, is_main)
                                VALUES (?, ?, ?)
                            ");
                            $stmt->execute([$productId, 'uploads/products/' . $filename, $isMain]);
                        }
                    }
                }
            }
            
            $db->commit();
            $success = true;
            $_SESSION['message'] = 'Товар успешно добавлен';
            header('Location: products.php');
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Ошибка при сохранении товара: ' . $e->getMessage();
        }
    }
}

// Получаем существующие категории и бренды для подсказок
$existingCategories = $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$existingBrands = $db->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL ORDER BY brand")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить товар - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="admin-main">
            <h1>Добавить новый товар</h1>
            
            <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                Товар успешно добавлен!
            </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="admin-form">
                <!-- Основная информация -->
                <div class="form-section">
                    <h2>Основная информация</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Название товара *</label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="brand">Бренд *</label>
                            <input type="text" id="brand" name="brand" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>" 
                                   list="brands" required>
                            <datalist id="brands">
                                <?php foreach ($existingBrands as $brand): ?>
                                <option value="<?= htmlspecialchars($brand) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Описание</label>
                        <textarea id="description" name="description" class="form-control" rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">Категория *</label>
                            <input type="text" id="category" name="category" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['category'] ?? '') ?>"
                                   list="categories" required>
                            <datalist id="categories">
                                <?php foreach ($existingCategories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Цена *</label>
                            <input type="number" id="price" name="price" class="form-control" 
                                   step="0.01" min="0" 
                                   value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="color">Цвет</label>
                            <input type="text" id="color" name="color" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['color'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="material">Материал</label>
                            <input type="text" id="material" name="material" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['material'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Статус</label>
                            <select id="status" name="status" class="form-control">
                                <option value="active" <?= ($_POST['status'] ?? 'active') == 'active' ? 'selected' : '' ?>>Активен</option>
                                <option value="hidden" <?= ($_POST['status'] ?? '') == 'hidden' ? 'selected' : '' ?>>Скрыт</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Размеры и количество -->
                <div class="form-section">
                    <h2>Размеры и количество</h2>
                    
                    <div id="sizes-container">
                        <div class="size-row">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Размер</label>
                                    <input type="number" name="sizes[0][size]" class="form-control" 
                                           step="0.5" min="20" max="50" placeholder="Например: 42">
                                </div>
                                <div class="form-group">
                                    <label>Количество</label>
                                    <input type="number" name="sizes[0][quantity]" class="form-control" 
                                           min="0" placeholder="Количество на складе">
                                </div>
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="button" class="btn btn-danger remove-size" style="margin-top: 8px;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" id="add-size" class="btn btn-outline">
                        <i class="fas fa-plus"></i> Добавить размер
                    </button>
                </div>
                
                <!-- Изображения -->
                <div class="form-section">
                    <h2>Изображения</h2>
                    
                    <div class="file-upload">
                        <input type="file" id="images" name="images[]" multiple 
                               accept="image/jpeg,image/png,image/gif">
                        <label for="images" class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Перетащите файлы сюда или нажмите для выбора</p>
                            <p class="text-muted">Поддерживаются JPG, PNG, GIF. Максимум 5 файлов по 5МБ каждый.</p>
                        </label>
                    </div>
                    
                    <div id="file-preview" class="file-preview"></div>
                </div>
                
                <!-- Кнопки действий -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Сохранить товар
                    </button>
                    <a href="products.php" class="btn btn-outline">Отмена</a>
                </div>
            </form>
        </main>
    </div>
    
    <script src="../assets/js/admin.js"></script>
    <script>
    let sizeIndex = 1;
    
    // Добавление размера
    document.getElementById('add-size').addEventListener('click', function() {
        const container = document.getElementById('sizes-container');
        const newRow = document.createElement('div');
        newRow.className = 'size-row';
        newRow.innerHTML = `
            <div class="form-row">
                <div class="form-group">
                    <label>Размер</label>
                    <input type="number" name="sizes[${sizeIndex}][size]" class="form-control" 
                           step="0.5" min="20" max="50" placeholder="Например: 42">
                </div>
                <div class="form-group">
                    <label>Количество</label>
                    <input type="number" name="sizes[${sizeIndex}][quantity]" class="form-control" 
                           min="0" placeholder="Количество на складе">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-danger remove-size" style="margin-top: 8px;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(newRow);
        sizeIndex++;
    });
    
    // Удаление размера
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-size') || e.target.closest('.remove-size')) {
            const row = e.target.closest('.size-row');
            if (row && document.querySelectorAll('.size-row').length > 1) {
                row.remove();
            }
        }
    });
    
    // Предпросмотр изображений
    document.getElementById('images').addEventListener('change', function(e) {
        const preview = document.getElementById('file-preview');
        preview.innerHTML = '';
        
        Array.from(e.target.files).forEach((file, index) => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewItem = document.createElement('div');
                    previewItem.className = 'preview-item';
                    previewItem.innerHTML = `
                        <img src="${e.target.result}" alt="Preview">
                        <button type="button" class="remove-btn" data-index="${index}">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    preview.appendChild(previewItem);
                };
                reader.readAsDataURL(file);
            }
        });
    });
    
    // Удаление изображения из предпросмотра
    document.getElementById('file-preview').addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-btn') || e.target.closest('.remove-btn')) {
            const item = e.target.closest('.preview-item');
            if (item) {
                item.remove();
            }
        }
    });
    </script>
</body>
</html>

<style>
.form-section {
    margin-bottom: 40px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.form-section h2 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #495057;
    font-size: 1.3rem;
}

.size-row {
    margin-bottom: 15px;
    padding: 15px;
    background: white;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}

.size-row:last-child {
    margin-bottom: 0;
}

.alert {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert ul {
    margin: 10px 0 0 20px;
    padding: 0;
}

.text-muted {
    color: #6c757d;
    font-size: 0.9rem;
}
</style>