<?php
require_once 'config.php';

// Проверка за логнат потребител
if (!isLoggedIn()) {
    redirectTo('login.php?redirect=create_discussion.php');
}

$errors = [];
$success = false;
$selectedAnimeId = intval($_GET['anime_id'] ?? 0);

// Извличане на информация за избраното аниме (ако има такова)
$selectedAnime = null;
if ($selectedAnimeId) {
    $stmt = $pdo->prepare("SELECT * FROM anime WHERE id = ?");
    $stmt->execute([$selectedAnimeId]);
    $selectedAnime = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $animeId = intval($_POST['anime_id'] ?? 0);
    $title = sanitizeInput($_POST['title'] ?? '');
    $content = sanitizeInput($_POST['content'] ?? '');
    
    // Валидация
    if (!$animeId) {
        $errors[] = 'Моля изберете аниме за дискусията.';
    }
    
    if (empty($title)) {
        $errors[] = 'Заглавието е задължително.';
    } elseif (strlen($title) > 255) {
        $errors[] = 'Заглавието не може да е повече от 255 символа.';
    }
    
    if (empty($content)) {
        $errors[] = 'Съдържанието е задължително.';
    }
    
    // Проверка дали анимето съществува
    if (!$errors && $animeId) {
        $stmt = $pdo->prepare("SELECT id FROM anime WHERE id = ?");
        $stmt->execute([$animeId]);
        if (!$stmt->fetch()) {
            $errors[] = 'Избраното аниме не съществува.';
        }
    }
    
    // Създаване на дискусията
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Създаване на дискусията
            $stmt = $pdo->prepare("
                INSERT INTO discussions (anime_id, title, created_by) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$animeId, $title, $_SESSION['user_id']]);
            $discussionId = $pdo->lastInsertId();
            
            // Добавяне на първия коментар
            $stmt = $pdo->prepare("
                INSERT INTO comments (discussion_id, user_id, content) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$discussionId, $_SESSION['user_id'], $content]);
            
            $pdo->commit();
            redirectTo("discussion.php?id=$discussionId");
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Грешка при създаването на дискусията. Моля опитайте отново.';
        }
    }
}

$pageTitle = 'Създай дискусия';
require_once 'header.php';

// Извличане на всички анимета за dropdown
$stmt = $pdo->prepare("SELECT id, title, genre FROM anime ORDER BY title ASC");
$stmt->execute();
$allAnime = $stmt->fetchAll();
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card anime-card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">
                        <i class="bi bi-chat-left-text me-2"></i>Създай нова дискусия
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
                    
                    <form method="POST" id="createDiscussionForm">
                        <!-- Избор на аниме -->
                        <div class="mb-4">
                            <label for="anime_id" class="form-label fw-semibold">
                                Избери аниме <span class="text-danger">*</span>
                            </label>
                            
                            <!-- Показване на избраното аниме ако има такова -->
                            <?php if ($selectedAnime): ?>
                                <div class="alert alert-info d-flex align-items-center">
                                    <img src="<?= $selectedAnime['banner_image'] ?: 'assets/img/default-anime.jpg' ?>" 
                                         class="me-3" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;"
                                         alt="<?= htmlspecialchars($selectedAnime['title']) ?>">
                                    <div class="flex-grow-1">
                                        <strong><?= htmlspecialchars($selectedAnime['title']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($selectedAnime['genre']) ?></small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="changeAnime()">
                                        Промени
                                    </button>
                                </div>
                                <input type="hidden" name="anime_id" value="<?= $selectedAnime['id'] ?>" id="anime_id">
                            <?php else: ?>
                                <div id="animeSelector">
                                    <div class="input-group mb-3">
                                        <input type="text" class="form-control" id="animeSearch" 
                                               placeholder="Търсете аниме по заглавие...">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#animeModal">
                                            <i class="bi bi-list"></i> Виж всички
                                        </button>
                                    </div>
                                    <input type="hidden" name="anime_id" id="anime_id" required>
                                    <div id="selectedAnimePreview" class="d-none alert alert-success">
                                        <div class="d-flex align-items-center">
                                            <img id="selectedAnimeImage" class="me-3" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                            <div class="flex-grow-1">
                                                <strong id="selectedAnimeTitle"></strong>
                                                <br><small class="text-muted" id="selectedAnimeGenre"></small>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearAnimeSelection()">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <small class="text-muted">
                                    Не намирате желаното аниме?
                                </small>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createAnimeModal">
                                    <i class="bi bi-plus-circle me-1"></i>Създай ново аниме
                                </button>
                            </div>
                        </div>
                        
                        <!-- Заглавие на дискусията -->
                        <div class="mb-4">
                            <label for="title" class="form-label fw-semibold">
                                Заглавие на дискусията <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?= isset($title) ? htmlspecialchars($title) : '' ?>" 
                                   placeholder="Напр.: 'Какво мислите за финала?' или 'Най-добрият момент в серията'"
                                   required>
                        </div>
                        
                        <!-- Съдържание -->
                        <div class="mb-4">
                            <label for="content" class="form-label fw-semibold">
                                Първи коментар <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control auto-resize" id="content" name="content" rows="6"
                                      placeholder="Започнете дискусията с вашето мнение, въпрос или наблюдение..."
                                      required><?= isset($content) ? htmlspecialchars($content) : '' ?></textarea>
                            <div class="form-text">
                                Споделете вашите мисли, задайте въпрос или започнете интересна тема за дискусия.
                            </div>
                        </div>
                        
                        <!-- Бутони -->
                        <div class="d-flex justify-content-between">
                            <a href="javascript:history.back()" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Назад
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle me-2"></i>Създай дискусия
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal за избор на аниме -->
<div class="modal fade" id="animeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-film me-2"></i>Избери аниме
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="modalAnimeSearch" 
                           placeholder="Търсете в списъка...">
                </div>
                <div class="row g-3" id="animeList">
                    <?php foreach ($allAnime as $anime): ?>
                        <div class="col-md-6 anime-option" data-title="<?= strtolower($anime['title']) ?>">
                            <div class="card h-100 anime-select-card" style="cursor: pointer;" 
                                 onclick="selectAnime(<?= $anime['id'] ?>, '<?= htmlspecialchars($anime['title'], ENT_QUOTES) ?>', '<?= htmlspecialchars($anime['genre'], ENT_QUOTES) ?>')">
                                <div class="card-body p-3">
                                    <h6 class="card-title mb-1"><?= htmlspecialchars($anime['title']) ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($anime['genre']) ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal за създаване на ново аниме -->
<div class="modal fade" id="createAnimeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Създай ново аниме
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="quickAnimeForm">
                    <div class="mb-3">
                        <label for="quickTitle" class="form-label">Заглавие *</label>
                        <input type="text" class="form-control" id="quickTitle" required>
                    </div>
                    <div class="mb-3">
                        <label for="quickGenre" class="form-label">Жанр *</label>
                        <input type="text" class="form-control" id="quickGenre" required>
                    </div>
                    <div class="mb-3">
                        <label for="quickDescription" class="form-label">Описание</label>
                        <textarea class="form-control" id="quickDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="quickImageUrl" class="form-label">Изображение на анимето</label>
                        <input type="url" class="form-control" id="quickImageUrl" 
                               placeholder="https://example.com/image.jpg">
                        <div class="form-text">Въведете пълен URL адрес към изображението (по избор)</div>
                        <div id="imagePreview" class="mt-2 d-none">
                            <img id="previewImg" src="" alt="Преглед" 
                                 style="max-width: 200px; max-height: 150px; object-fit: cover; border-radius: 8px;">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                <button type="button" class="btn btn-primary" onclick="createQuickAnime()">
                    <i class="bi bi-check-circle me-1"></i>Създай
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.anime-select-card:hover {
    background-color: #f8f9fa;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

#imagePreview img {
    border: 1px solid #dee2e6;
    transition: opacity 0.3s ease;
}

#imagePreview img:hover {
    opacity: 0.9;
}

.form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

#urlInputGroup .form-control,
#fileInputGroup .form-control {
    transition: border-color 0.3s ease;
}

#urlInputGroup .form-control:focus,
#fileInputGroup .form-control:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
</style>

<script>
// Функции за работа с аниме селекцията
function selectAnime(id, title, genre) {
    selectAnimeWithImage(id, title, genre, 'assets/img/default-anime.jpg');
}

function selectAnimeWithImage(id, title, genre, bannerImage) {
    document.getElementById('anime_id').value = id;
    document.getElementById('selectedAnimeTitle').textContent = title;
    document.getElementById('selectedAnimeGenre').textContent = genre;
    document.getElementById('selectedAnimeImage').src = bannerImage;
    
    document.getElementById('selectedAnimePreview').classList.remove('d-none');
    document.getElementById('animeSearch').value = title;
    
    // Затваряне на модала
    const modal = bootstrap.Modal.getInstance(document.getElementById('animeModal'));
    if (modal) modal.hide();
}

function clearAnimeSelection() {
    document.getElementById('anime_id').value = '';
    document.getElementById('selectedAnimePreview').classList.add('d-none');
    document.getElementById('animeSearch').value = '';
}

function changeAnime() {
    // Показване на селектора отново
    document.querySelector('input[name="anime_id"]').type = 'hidden';
    document.querySelector('#animeSelector').style.display = 'block';
    document.querySelector('.alert-info').style.display = 'none';
}

// Търсене в списъка с аниме в модала
document.getElementById('modalAnimeSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const animeOptions = document.querySelectorAll('.anime-option');
    
    animeOptions.forEach(option => {
        const title = option.dataset.title;
        if (title.includes(searchTerm)) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    });
});

// AJAX търсене в полето за търсене
let searchTimeout;
document.getElementById('animeSearch')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const query = this.value.trim();
    
    if (query.length < 2) return;
    
    searchTimeout = setTimeout(() => {
        // Тук може да добавите AJAX за търсене в реално време
    }, 300);
});

// Създаване на бързо аниме
function createQuickAnime() {
    const title = document.getElementById('quickTitle').value.trim();
    const genre = document.getElementById('quickGenre').value.trim();
    const description = document.getElementById('quickDescription').value.trim();
    const imageUrl = document.getElementById('quickImageUrl').value.trim();
    
    if (!title || !genre) {
        showToast('Моля попълнете заглавие и жанр', 'warning');
        return;
    }
    
    // Подготовка на данните за изпращане
    const formData = new FormData();
    formData.append('title', title);
    formData.append('genre', genre);
    formData.append('description', description);
    formData.append('imageUrl', imageUrl);
    
    // AJAX заявка за създаване на аниме
    fetch('create_anime_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            selectAnimeWithImage(data.anime_id, title, genre, data.banner_image || 'assets/img/default-anime.jpg');
            bootstrap.Modal.getInstance(document.getElementById('createAnimeModal')).hide();
            showToast('Анимето е създадено успешно!', 'success');
            
            // Изчистване на формата
            document.getElementById('quickAnimeForm').reset();
            document.getElementById('imagePreview').classList.add('d-none');
        } else {
            showToast(data.message || 'Грешка при създаването', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Грешка при създаването на анимето', 'error');
    });
}

// Автоматично преоразмеряване на textarea
autoResizeTextarea();

// Изчистване на модала при затваряне
document.getElementById('createAnimeModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('quickAnimeForm').reset();
    document.getElementById('imagePreview').classList.add('d-none');
});

// Настройка на обработчиците за превюто на изображенията
function setupImagePreviewHandlers() {
    // Преглед на изображение от URL
    const urlInput = document.getElementById('quickImageUrl');
    if (urlInput) {
        urlInput.addEventListener('input', function() {
            const url = this.value.trim();
            const preview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');
            
            if (url && isValidImageUrl(url)) {
                previewImg.src = url;
                previewImg.onload = function() {
                    preview.classList.remove('d-none');
                };
                previewImg.onerror = function() {
                    preview.classList.add('d-none');
                };
            } else {
                preview.classList.add('d-none');
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', setupImagePreviewHandlers);
document.getElementById('createAnimeModal')?.addEventListener('shown.bs.modal', setupImagePreviewHandlers);

// Проверка дали URL-ът е валиден за изображение
function isValidImageUrl(url) {
    try {
        new URL(url);
        return /\.(jpg|jpeg|png|gif|webp)$/i.test(url);
    } catch {
        return false;
    }
}
</script>

<?php require_once 'footer.php'; ?>