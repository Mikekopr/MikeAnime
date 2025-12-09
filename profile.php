<?php
require_once 'config.php';

// Проверка за логнат потребител
if (!isLoggedIn()) {
    redirectTo('login.php?redirect=profile.php');
}

$currentUser = getCurrentUser();
$pageTitle = 'Профил - ' . $currentUser['username'];

$errors = [];
$success = '';

// Обработка на форми
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            updateProfile();
            break;
        case 'change_password':
            changePassword();
            break;
        case 'upload_avatar':
            uploadAvatar();
            break;
    }
}

function updateProfile() {
    global $pdo, $errors, $success;
    
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    
    // Валидация
    if (empty($username)) {
        $errors[] = 'Потребителското име е задължително.';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Потребителското име трябва да е поне 3 символа.';
    }
    
    if (empty($email)) {
        $errors[] = 'Имейлът е задължителен.';
    } elseif (!validateEmail($email)) {
        $errors[] = 'Моля въведете валиден имейл адрес.';
    }
    
    // Проверка за уникалност (освен за текущия потребител)
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $errors[] = 'Потребителското име или имейл вече се използват от друг потребител.';
        }
    }
    
    // Обновяване
    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
        if ($stmt->execute([$username, $email, $_SESSION['user_id']])) {
            $success = 'Профилът е обновен успешно!';
            // Обновяване на данните в сесията
            $_SESSION['user_data'] = null; // За да се презаредят при следващо извикване
        } else {
            $errors[] = 'Грешка при обновяването на профила.';
        }
    }
}

function changePassword() {
    global $pdo, $errors, $success, $currentUser;
    
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Валидация
    if (empty($currentPassword)) {
        $errors[] = 'Текущата парола е задължителна.';
    } elseif (!password_verify($currentPassword, $currentUser['password_hash'])) {
        $errors[] = 'Текущата парола е грешна.';
    }
    
    if (empty($newPassword)) {
        $errors[] = 'Новата парола е задължителна.';
    } elseif (strlen($newPassword) < 6) {
        $errors[] = 'Новата парола трябва да е поне 6 символа.';
    }
    
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Паролите не съвпадат.';
    }
    
    // Обновяване
    if (empty($errors)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        if ($stmt->execute([$hashedPassword, $_SESSION['user_id']])) {
            $success = 'Паролата е променена успешно!';
        } else {
            $errors[] = 'Грешка при променянето на паролата.';
        }
    }
}

function uploadAvatar() {
    global $pdo, $errors, $success;
    
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Моля изберете файл за качване.';
        return;
    }
    
    $uploadDir = 'uploads/avatars/';
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    
    $fileType = $_FILES['avatar']['type'];
    $fileSize = $_FILES['avatar']['size'];
    
    if (!in_array($fileType, $allowedTypes)) {
        $errors[] = 'Позволени са само JPEG, PNG и GIF изображения.';
        return;
    }
    
    if ($fileSize > $maxSize) {
        $errors[] = 'Размерът на файла не трябва да надвишава 2MB.';
        return;
    }
    
    $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
    $fileName = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
    $uploadPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
        // Изтриване на стария avatar ако съществува
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $oldAvatar = $stmt->fetchColumn();
        
        if ($oldAvatar && file_exists($oldAvatar)) {
            unlink($oldAvatar);
        }
        
        // Обновяване в базата
        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        if ($stmt->execute([$uploadPath, $_SESSION['user_id']])) {
            $success = 'Аватарът е обновен успешно!';
        } else {
            $errors[] = 'Грешка при обновяването на аватара.';
        }
    } else {
        $errors[] = 'Грешка при качването на файла.';
    }
}

// Извличане на статистики за потребителя
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM anime WHERE created_by = ?) as anime_count,
        (SELECT COUNT(*) FROM discussions WHERE created_by = ?) as discussion_count,
        (SELECT COUNT(*) FROM comments WHERE user_id = ?) as comment_count,
        (SELECT COUNT(*) FROM ratings WHERE user_id = ?) as rating_count
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$userStats = $stmt->fetch();

// Извличане на последните дейности на потребителя
$stmt = $pdo->prepare("
    SELECT 
        d.id as discussion_id,
        d.title as discussion_title,
        d.created_at,
        a.title as anime_title,
        a.id as anime_id
    FROM discussions d
    JOIN anime a ON d.anime_id = a.id
    WHERE d.created_by = ?
    ORDER BY d.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$userDiscussions = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT 
        a.id,
        a.title,
        a.genre,
        a.banner_image,
        a.created_at
    FROM anime a
    WHERE a.created_by = ?
    ORDER BY a.created_at DESC
    LIMIT 6
");
$stmt->execute([$_SESSION['user_id']]);
$userAnime = $stmt->fetchAll();

// Извличане на последните активности на потребителя (комбинирани)
$stmt = $pdo->prepare("
    (SELECT 
        'discussion' as activity_type,
        d.id as item_id,
        d.title as activity_title,
        a.title as anime_title,
        a.id as anime_id,
        d.created_at as activity_date
    FROM discussions d
    JOIN anime a ON d.anime_id = a.id
    WHERE d.created_by = ?
    ORDER BY d.created_at DESC
    LIMIT 10)
    
    UNION ALL
    
    (SELECT 
        'comment' as activity_type,
        c.id as item_id,
        CONCAT('Коментар в \"', d.title, '\"') as activity_title,
        a.title as anime_title,
        a.id as anime_id,
        c.created_at as activity_date
    FROM comments c
    JOIN discussions d ON c.discussion_id = d.id
    JOIN anime a ON d.anime_id = a.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
    LIMIT 10)
    
    UNION ALL
    
    (SELECT 
        'anime' as activity_type,
        a.id as item_id,
        CONCAT('Добави аниме \"', a.title, '\"') as activity_title,
        a.title as anime_title,
        a.id as anime_id,
        a.created_at as activity_date
    FROM anime a
    WHERE a.created_by = ?
    ORDER BY a.created_at DESC
    LIMIT 10)
    
    ORDER BY activity_date DESC
    LIMIT 20
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$recentActivities = $stmt->fetchAll();

require_once 'header.php';
?>

<div class="container mt-4">
    <!-- Профилен header -->
    <div class="profile-header mb-4">
        <div class="row align-items-center">
            <div class="col-md-3 text-center">
                <img src="<?= $currentUser['avatar'] ?: 'assets/img/default-avatar.png' ?>" 
                     class="profile-avatar" 
                     alt="<?= htmlspecialchars($currentUser['username']) ?>">
                <div class="mt-3">
                    <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#avatarModal">
                        <i class="bi bi-camera me-1"></i>Смени аватар
                    </button>
                </div>
            </div>
            <div class="col-md-9">
                <h1 class="display-6 mb-2"><?= htmlspecialchars($currentUser['username']) ?></h1>
                <p class="mb-3">
                    <i class="bi bi-envelope me-2"></i><?= htmlspecialchars($currentUser['email']) ?>
                    <br>
                    <i class="bi bi-calendar me-2"></i>Член от <?= formatDate($currentUser['created_at']) ?>
                    <?php if ($currentUser['is_admin']): ?>
                        <span class="badge bg-warning ms-2">
                            <i class="bi bi-shield-check me-1"></i>Администратор
                        </span>
                    <?php endif; ?>
                </p>
                
                <!-- Статистики -->
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="text-center">
                            <div class="fs-4 fw-bold"><?= $userStats['anime_count'] ?></div>
                            <small>Анимета</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center">
                            <div class="fs-4 fw-bold"><?= $userStats['discussion_count'] ?></div>
                            <small>Дискусии</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center">
                            <div class="fs-4 fw-bold"><?= $userStats['comment_count'] ?></div>
                            <small>Коментари</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center">
                            <div class="fs-4 fw-bold"><?= $userStats['rating_count'] ?></div>
                            <small>Оценки</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Уведомления -->
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
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i><?= $success ?>
        </div>
    <?php endif; ?>
    
    <!-- Табове -->
    <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" 
                    data-bs-target="#overview" type="button">
                <i class="bi bi-house me-1"></i>Общ преглед
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="settings-tab" data-bs-toggle="tab" 
                    data-bs-target="#settings" type="button">
                <i class="bi bi-gear me-1"></i>Настройки
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="activity-tab" data-bs-toggle="tab" 
                    data-bs-target="#activity" type="button">
                <i class="bi bi-clock-history me-1"></i>Дейност
            </button>
        </li>
    </ul>
    
    <!-- Съдържание на табовете -->
    <div class="tab-content" id="profileTabsContent">
        <!-- Общ преглед -->
        <div class="tab-pane fade show active" id="overview">
            <div class="row g-4">
                <!-- Последни дискусии -->
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-chat-dots me-2"></i>Последни дискусии
                            </h5>
                            <a href="create_discussion.php" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-circle me-1"></i>Нова
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($userDiscussions)): ?>
                                <div class="empty-state">
                                    <i class="bi bi-chat-left-text"></i>
                                    <p>Все още нямате дискусии</p>
                                    <a href="create_discussion.php" class="btn btn-primary btn-sm">
                                        Започни първата си дискусия
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($userDiscussions as $discussion): ?>
                                    <div class="border-bottom pb-2 mb-2">
                                        <h6 class="mb-1">
                                            <a href="discussion.php?id=<?= $discussion['discussion_id'] ?>" 
                                               class="text-decoration-none">
                                                <?= htmlspecialchars($discussion['discussion_title']) ?>
                                            </a>
                                        </h6>
                                        <small class="text-muted">
                                            в <a href="anime.php?id=<?= $discussion['anime_id'] ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($discussion['anime_title']) ?>
                                            </a>
                                            • <?= formatDate($discussion['created_at']) ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Добавени анимета -->
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-film me-2"></i>Добавени анимета
                            </h5>
                            <a href="create_anime.php" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-circle me-1"></i>Добави
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($userAnime)): ?>
                                <div class="empty-state">
                                    <i class="bi bi-film"></i>
                                    <p>Все още не сте добавили анимета</p>
                                    <a href="create_anime.php" class="btn btn-primary btn-sm">
                                        Добави първото си аниме
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="row g-2">
                                    <?php foreach (array_slice($userAnime, 0, 4) as $anime): ?>
                                        <div class="col-6">
                                            <div class="card card-hover">
                                                <img src="<?= $anime['banner_image'] ?: 'assets/img/default-anime.jpg' ?>" 
                                                     class="card-img-top" style="height: 100px; object-fit: cover;"
                                                     alt="<?= htmlspecialchars($anime['title']) ?>">
                                                <div class="card-body p-2">
                                                    <h6 class="card-title mb-1 small">
                                                        <a href="anime.php?id=<?= $anime['id'] ?>" 
                                                           class="text-decoration-none">
                                                            <?= htmlspecialchars($anime['title']) ?>
                                                        </a>
                                                    </h6>
                                                    <small class="text-muted"><?= htmlspecialchars($anime['genre']) ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($userAnime) > 4): ?>
                                    <div class="text-center mt-3">
                                        <small class="text-muted">и още <?= count($userAnime) - 4 ?> анимета...</small>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Настройки -->
        <div class="tab-pane fade" id="settings">
            <div class="row g-4">
                <!-- Основни настройки -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-person me-2"></i>Основни данни
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="mb-3">
                                    <label for="username" class="form-label">Потребителско име</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?= htmlspecialchars($currentUser['username']) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Имейл адрес</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($currentUser['email']) ?>" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-1"></i>Запази промените
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Смяна на парола -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-lock me-2"></i>Смяна на парола
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Текуща парола</label>
                                    <input type="password" class="form-control" id="current_password" 
                                           name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Нова парола</label>
                                    <input type="password" class="form-control" id="new_password" 
                                           name="new_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Потвърди новата парола</label>
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-key me-1"></i>Промени паролата
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Дейност -->
        <div class="tab-pane fade" id="activity">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history me-2"></i>Последни активности
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentActivities)): ?>
                        <div class="empty-state text-center py-5">
                            <i class="bi bi-clock-history fs-1 text-muted mb-3"></i>
                            <h6 class="text-muted">Все още няmate активности</h6>
                            <p class="text-muted mb-0">Започнете да участвате в дискусии или добавяте анимета</p>
                        </div>
                    <?php else: ?>
                        <div class="activity-timeline">
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="activity-item d-flex align-items-start mb-4">
                                    <div class="activity-icon me-3">
                                        <?php if ($activity['activity_type'] === 'discussion'): ?>
                                            <div class="icon-circle bg-primary">
                                                <i class="bi bi-chat-dots text-white"></i>
                                            </div>
                                        <?php elseif ($activity['activity_type'] === 'comment'): ?>
                                            <div class="icon-circle bg-success">
                                                <i class="bi bi-chat-left-text text-white"></i>
                                            </div>
                                        <?php elseif ($activity['activity_type'] === 'anime'): ?>
                                            <div class="icon-circle bg-warning">
                                                <i class="bi bi-film text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-content flex-grow-1">
                                        <div class="activity-header d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <?php if ($activity['activity_type'] === 'discussion'): ?>
                                                        Създаде дискусия: 
                                                        <a href="discussion.php?id=<?= $activity['item_id'] ?>" 
                                                           class="text-decoration-none">
                                                            "<?= htmlspecialchars($activity['activity_title']) ?>"
                                                        </a>
                                                    <?php elseif ($activity['activity_type'] === 'comment'): ?>
                                                        <?= htmlspecialchars($activity['activity_title']) ?>
                                                    <?php elseif ($activity['activity_type'] === 'anime'): ?>
                                                        <?= htmlspecialchars($activity['activity_title']) ?>
                                                    <?php endif; ?>
                                                </h6>
                                                <p class="text-muted mb-0 small">
                                                    <i class="bi bi-film me-1"></i>
                                                    <a href="anime.php?id=<?= $activity['anime_id'] ?>" 
                                                       class="text-decoration-none">
                                                        <?= htmlspecialchars($activity['anime_title']) ?>
                                                    </a>
                                                </p>
                                            </div>
                                            <small class="text-muted ms-3">
                                                <?= formatDate($activity['activity_date']) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal за качване на аватар -->
<div class="modal fade" id="avatarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-camera me-2"></i>Смени аватар
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="avatarForm">
                    <input type="hidden" name="action" value="upload_avatar">
                    
                    <div class="text-center mb-3">
                        <img src="<?= $currentUser['avatar'] ?: 'assets/img/default-avatar.png' ?>" 
                             class="rounded-circle" style="width: 150px; height: 150px; object-fit: cover;"
                             alt="Current Avatar" id="avatarPreview">
                    </div>
                    
                    <div class="mb-3">
                        <label for="avatar" class="form-label">Избери ново изображение</label>
                        <input type="file" class="form-control" id="avatar" name="avatar" 
                               accept="image/*" onchange="previewAvatar(this)" required>
                        <div class="form-text">
                            Максимален размер: 2MB. Поддържани формати: JPEG, PNG, GIF.
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                <button type="submit" form="avatarForm" class="btn btn-primary">
                    <i class="bi bi-upload me-1"></i>Качи
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.activity-timeline .icon-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.activity-item {
    position: relative;
    padding-bottom: 1rem;
}

.activity-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 19px;
    top: 50px;
    bottom: -16px;
    width: 2px;
    background-color: #e9ecef;
}

.activity-item:hover {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 0.75rem;
    margin: 0 -0.75rem 1rem -0.75rem;
    transition: background-color 0.2s ease;
}

.activity-content h6 a:hover {
    color: #0d6efd !important;
}

.empty-state i {
    display: block;
}
</style>

<script>
function previewAvatar(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}
</script>

<?php require_once 'footer.php'; ?>