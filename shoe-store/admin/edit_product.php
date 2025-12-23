<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireManager();

$db = Database::connect();

// Проверяем ID товара
$productId = $_GET['id'] ?? 0;
if (!$productId) {
    header('Location: products.php');
    exit;
}

// Получаем информацию о товаре
$stmt = $db->prepare("
    SELECT * FROM products WHERE id = ?
");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: products.php');
    exit;
}

// Получаем размеры товара
$sizes = $db->prepare("SELECT * FROM product_sizes WHERE product_id = ? ORDER BY size");
$sizes->execute([$productId]);
$productSizes = $sizes->fetchAll(PDO::FETCH_ASSOC);

// Получаем изображения товара
$images = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_main DESC");
$images->execute([$productId]);
$productImages = $images->fetchAll(PDO::FETCH_ASSOC);

$errors = [];

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
    
    // Если нет ошибок, обновляем товар
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Обновляем основной товар
            $stmt = $db->prepare("
                UPDATE products 
                SET name = ?, description = ?, category = ?, brand = ?, 
                    price = ?, color = ?, material = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $category, $brand, $price, $color, $material, $status, $productId]);
            
            // Удаляем старые размеры
            $stmt = $db->prepare("DELETE FROM product_sizes WHERE product_id = ?");
            $stmt->execute([$productId]);
            
            // Сохраняем новые размеры
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
            
            // Обработка удаления изображений
            if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
                foreach ($_POST['delete_images'] as $imageId) {
                    $stmt = $db->prepare("SELECT image_url FROM product_images WHERE id = ?");
                    $stmt->execute([$imageId]);
                    $image = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($image) {
                        // Удаляем файл
                        $filePath = '../' . $image['image_url'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                        
                        // Удаляем запись из БД
                        $stmt = $db->prepare("DELETE FROM product_images WHERE id = ?");
                        $stmt->execute([$imageId]);
                    }
                }
            }
            
            // Обработка установки главного изображения
            if (isset($_POST['main_image'])) {
                // Сбрасываем все is_main
                $stmt = $db->prepare("UPDATE product_images SET is_main = 0 WHERE product_id = ?");
                $stmt->execute([$productId]);
                
                // Устанавливаем новое главное
                $stmt = $db->prepare("UPDATE product_images SET is_main = 1 WHERE id = ? AND product_id = ?");
                $stmt->execute([$_POST['main_image'], $productId]);
            }
            
            // Обработка новых загруженных изображений
            if (!empty($_FILES['new_images']['name'][0])) {
                foreach ($_FILES['new_images']['tmp_name'] as $index => $tmpName) {
                    if ($_FILES['new_images']['error'][$index] === UPLOAD_OK) {
                        $extension = pathinfo($_FILES['new_images']['name'][$index], PATHINFO_EXTENSION);
                        $filename = uniqid() . '.' . $extension;
                        $destination = UPLOAD_PATH . $filename;
                        
                        if (move_uploaded_file($tmpName, $destination)) {
                            $isMain = (empty($productImages) && $index === 0) ? 1 : 0;
                            
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
            $_SESSION['message'] = 'Товар успешно обновлен';
            header('Location: edit_product.php?id=' . $productId);
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Ошибка при обновлении товара: ' . $e->getMessage();
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
    <title>Редактировать товар - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="admin-main">
            <h1>Редактировать товар</h1>
            
            <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['message'] ?>
                <?php unset($_SESSION['message']); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
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
                                   value="<?= htmlspecialchars($product['name']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="brand">Бренд *</label>
                            <input type="text" id="brand" name="brand" class="form-control" 
                                   value="<?= htmlspecialchars($product['brand']) ?>" 
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
                        <textarea id="description" name="description" class="form-control" rows="4"><?= htmlspecialchars($product['description']) ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">Категория *</label>
                            <input type="text" id="category" name="category" class="form-control" 
                                   value="<?= htmlspecialchars($product['category']) ?>"
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
                                   value="<?= htmlspecialchars($product['price']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="color">Цвет</label>
                            <input type="text" id="color" name="color" class="form-control" 
                                   value="<?= htmlspecialchars($product['color']) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="material">Материал</label>
                            <input type="text" id="material" name="material" class="form-control" 
                                   value="<?= htmlspecialchars($product['material']) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Статус</label>
                            <select id="status" name="status" class="form-control">
                                <option value="active" <?= $product['status'] == 'active' ? 'selected' : '' ?>>Активен</option>
                                <option value="hidden" <?= $product['status'] == 'hidden' ? 'selected' : '' ?>>Скрыт</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Размеры и количество -->
                <div class="form-section">
                    <h2>Размеры и количество</h2>
                    
                    <div id="sizes-container">
                        <?php if (empty($productSizes)): ?>
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
                        <?php else: ?>
                            <?php foreach ($productSizes as $index => $size): ?>
                            <div class="size-row">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Размер</label>
                                        <input type="number" name="sizes[<?= $index ?>][size]" class="form-control" 
                                               step="0.5" min="20" max="50" 
                                               value="<?= htmlspecialchars($size['size']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Количество</label>
                                        <input type="number" name="sizes[<?= $index ?>][quantity]" class="form-control" 
                                               min="0" value="<?= htmlspecialchars($size['quantity']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="button" class="btn btn-danger remove-size" style="margin-top: 8px;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" id="add-size" class="btn btn-outline">
                        <i class="fas fa-plus"></i> Добавить размер
                    </button>
                </div>
                
                <!-- Существующие изображения -->
                <?php if (!empty($productImages)): ?>
                <div class="form-section">
                    <h2>Существующие изображения</h2>
                    
                    <div class="image-grid">
                        <?php foreach ($productImages as $image): ?>
                        <div class="image-item">
                            <div class="image-preview">
                                <img src="../<?= htmlspecialchars($image['image_url']) ?>" 
                                     alt="Изображение товара">
                                <?php if ($image['is_main']): ?>
                                <span class="main-badge">Главное</span>
                                <?php endif; ?>
                            </div>
                            <div class="image-actions">
                                <label class="radio-label">
                                    <input type="radio" name="main_image" value="<?= $image['id'] ?>"
                                           <?= $image['is_main'] ? 'checked' : '' ?>>
                                    Сделать главным
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="delete_images[]" value="<?= $image['id'] ?>">
                                    Удалить
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Новые изображения -->
                <div class="form-section">
                    <h2>Добавить изображения</h2>
                    
                    <div class="file-upload">
                        <input type="file" id="new_images" name="new_images[]" multiple 
                               accept="image/jpeg,image/png,image/gif">
                        <label for="new_images" class="file-upload-label">
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
                        <i class="fas fa-save"></i> Сохранить изменения
                    </button>
                    <a href="products.php" class="btn btn-outline">Отмена</a>
                    <a href="products.php?action=delete&id=<?= $productId ?>" 
                       class="btn btn-danger"
                       onclick="return confirm('Вы уверены, что хотите удалить этот товар?')">
                        <i class="fas fa-trash"></i> Удалить товар
                    </a>
                </div>
            </form>
        </main>
    </div>
    
    <script src="../assets/js/admin.js"></script>
    <script>
    let sizeIndex = <?= count($productSizes) ?: 1 ?>;
    
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
    
    // Предпросмотр новых изображений
    document.getElementById('new_images').addEventListener('change', function(e) {
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
.image-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.image-item {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
    background: white;
}

.image-preview {
    position: relative;
    height: 150px;
    overflow: hidden;
}

.image-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.main-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #28a745;
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
}

.image-actions {
    padding: 15px;
    background: #f8f9fa;
}

.radio-label,
.checkbox-label {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    font-size: 0.9rem;
    cursor: pointer;
}

.radio-label input,
.checkbox-label input {
    margin-right: 8px;
}
</style>