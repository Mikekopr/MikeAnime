<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirectTo('login.php?redirect=create_anime.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $genre = sanitizeInput($_POST['genre'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $imageUrl = sanitizeInput($_POST['image_url'] ?? '');
    
    if (empty($title)) {
        $errors[] = 'Заглавието е задължително.';
    } elseif (strlen($title) > 255) {
        $errors[] = 'Заглавието не може да е повече от 255 символа.';
    }
    
    if (empty($genre)) {
        $errors[] = 'Жанрът е задължителен.';
    }
    
    $bannerImage = null;
    if (isset($_FILES['banner_upload']) && $_FILES['banner_upload']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/anime_banners/';
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024;
        
        $fileType = $_FILES['banner_upload']['type'];
        $fileSize = $_FILES['banner_upload']['size'];
        
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = 'Позволени са само JPEG, PNG, GIF и WebP изображения.';
        } elseif ($fileSize > $maxSize) {
            $errors[] = 'Размерът на файла не трябва да надвишава 5MB.';
        } else {
            $extension = pathinfo($_FILES['banner_upload']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('anime_') . '.' . $extension;
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['banner_upload']['tmp_name'], $uploadPath)) {
                $bannerImage = $uploadPath;
            } else {
                $errors[] = 'Грешка при качването на файла.';
            }
        }
    } elseif (!empty($imageUrl)) {
        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $bannerImage = $imageUrl;
        } else {
            $errors[] = 'Моля въведете валиден URL за изображението.';
        }
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM anime WHERE title = ?");
        $stmt->execute([$title]);
        if ($stmt->fetch()) {
            $errors[] = 'Аниме с това заглавие вече съществува.';
        }
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO anime (title, genre, description, banner_image, created_by) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$title, $genre, $description, $bannerImage, $_SESSION['user_id']])) {
            $animeId = $pdo->lastInsertId();
            redirectTo("anime.php?id=$animeId");
        } else {
            $errors[] = 'Грешка при създаването на анимето. Моля опитайте отново.';
        }
    }
}

$pageTitle = 'Създай ново аниме';
require_once 'header.php';

$popularGenres = [
    'Екшън', 'Приключения', 'Комедия', 'Драма', 'Романтика', 
    'Научна фантастика', 'Фентъзи', 'Ужаси', 'Мистерия', 'Трилър',
    'Шонен', 'Шоджо', 'Сейнен', 'Джоsei', 'Меха', 'Спорт',
    'Свръхестествено', 'Военен', 'Исторически', 'Музикален',
    'Психологически', 'Училищен', 'Slice of Life'
];
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card anime-card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">
                        <i class="bi bi-plus-circle me-2"></i>Създай ново аниме
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $error ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data" id="createAnimeForm">
                        
                        <div class="mb-4">
                            <label for="title" class="form-label fw-semibold">
                                Заглавие <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?= isset($title) ? htmlspecialchars($title) : '' ?>" 
                                   placeholder="Въведете заглавие на анимето..." required>
                        </div>
                        
                        
                        <div class="mb-4">
                            <label for="genre" class="form-label fw-semibold">
                                Жанр <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="genre" name="genre" 
                                   value="<?= isset($genre) ? htmlspecialchars($genre) : '' ?>" 
                                   placeholder="Напишете или изберете жанр..." 
                                   list="genreOptions" required>
                            <datalist id="genreOptions">
                                <?php foreach ($popularGenres as $g): ?>
                                    <option value="<?= $g ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <div class="form-text">
                                Популярни жанрове: 
                                <?php foreach (array_slice($popularGenres, 0, 8) as $i => $g): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1 mb-1" 
                                            onclick="selectGenre('<?= $g ?>')">
                                        <?= $g ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                      
                        <div class="mb-4">
                            <label for="description" class="form-label fw-semibold">Описание</label>
                            <textarea class="form-control auto-resize" id="description" name="description" rows="4"
                                      placeholder="Кратко описание на анимето..."><?= isset($description) ? htmlspecialchars($description) : '' ?></textarea>
                        </div>
                        
                       
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Банер изображение</label>
                            
                            
                            <ul class="nav nav-tabs mb-3" id="imageTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="upload-tab" data-bs-toggle="tab" 
                                            data-bs-target="#upload-pane" type="button">
                                        <i class="bi bi-upload me-1"></i>Качи файл
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="url-tab" data-bs-toggle="tab" 
                                            data-bs-target="#url-pane" type="button">
                                        <i class="bi bi-link-45deg me-1"></i>URL адрес
                                    </button>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="imageTabContent">
                                
                                <div class="tab-pane fade show active" id="upload-pane">
                                    <input type="file" class="form-control" id="banner_upload" name="banner_upload" 
                                           accept="image/*" onchange="previewImage(this, 'imagePreview')">
                                    <div class="form-text">
                                        Максимален размер: 5MB. Поддържани формати: JPEG, PNG, GIF, WebP.
                                    </div>
                                </div>
                                
                               
                                <div class="tab-pane fade" id="url-pane">
                                    <input type="url" class="form-control" id="image_url" name="image_url" 
                                           value="<?= isset($imageUrl) ? htmlspecialchars($imageUrl) : '' ?>"
                                           placeholder="https://example.com/image.jpg">
                                    <div class="form-text">
                                        Въведете директен линк към изображението.
                                    </div>
                                </div>
                            </div>
                            
                            
                            <div class="mt-3">
                                <img id="imagePreview" class="img-fluid rounded" style="max-height: 200px; display: none;" alt="Preview">
                            </div>
                        </div>
                        
                        
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Назад
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle me-2"></i>Създай аниме
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectGenre(genre) {
    document.getElementById('genre').value = genre;
}

document.getElementById('image_url').addEventListener('input', function() {
    const url = this.value;
    const preview = document.getElementById('imagePreview');
    
    if (url) {
        preview.src = url;
        preview.style.display = 'block';
        preview.onerror = function() {
            this.style.display = 'none';
        };
    } else {
        preview.style.display = 'none';
    }
});

autoResizeTextarea();

document.getElementById('createAnimeForm').addEventListener('submit', function(e) {
    if (!validateForm('createAnimeForm')) {
        e.preventDefault();
        showToast('Моля попълнете всички задължителни полета', 'warning');
    }
});
</script>

<?php require_once 'footer.php'; ?>