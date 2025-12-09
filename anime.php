<?php
require_once 'config.php';

$animeId = intval($_GET['id'] ?? 0);

if (!$animeId) {
    redirectTo('index.php');
}

// Извличане на информация за анимето
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        u.username as creator_username,
        ROUND(AVG(r.rating), 1) as avg_rating,
        COUNT(r.id) as rating_count
    FROM anime a
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN ratings r ON a.id = r.anime_id
    WHERE a.id = ?
    GROUP BY a.id
");
$stmt->execute([$animeId]);
$anime = $stmt->fetch();

if (!$anime) {
    redirectTo('index.php');
}

$pageTitle = $anime['title'];

// Извличане на рейтинг разбивката (1-10)
$stmt = $pdo->prepare("
    SELECT rating, COUNT(*) as count 
    FROM ratings 
    WHERE anime_id = ? 
    GROUP BY rating 
    ORDER BY rating DESC
");
$stmt->execute([$animeId]);
$ratingBreakdown = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Проверка дали текущият потребител е гласувал
$userRating = null;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT rating FROM ratings WHERE anime_id = ? AND user_id = ?");
    $stmt->execute([$animeId, $_SESSION['user_id']]);
    $userRating = $stmt->fetchColumn();
}

// Извличане на дискусии за това аниме
$stmt = $pdo->prepare("
    SELECT 
        d.id,
        d.title,
        d.created_at,
        u.username,
        (SELECT COUNT(*) FROM comments WHERE discussion_id = d.id) as comment_count
    FROM discussions d
    JOIN users u ON d.created_by = u.id
    WHERE d.anime_id = ?
    ORDER BY d.created_at DESC
    LIMIT 10
");
$stmt->execute([$animeId]);
$discussions = $stmt->fetchAll();

require_once 'header.php';
?>

<div class="container mt-4">
    <!-- Информация за анимето -->
    <div class="row mb-5">
        <div class="col-lg-4 mb-4">
            <div class="position-sticky" style="top: 100px;">
                <img src="<?= $anime['banner_image'] ?: 'assets/img/default-anime.jpg' ?>" 
                     class="img-fluid rounded shadow anime-poster" 
                     alt="<?= htmlspecialchars($anime['title']) ?>">
                
                <!-- Рейтинг секция -->
                <div class="card mt-3">
                    <div class="card-body text-center">
                        <h5 class="card-title">Рейтинг</h5>
                        <?php if ($anime['rating_count'] > 0): ?>
                            <div class="display-6 text-warning fw-bold mb-2">
                                <span id="averageRating"><?= $anime['avg_rating'] ?></span>/10
                            </div>
                            <div class="rating-stars mb-2">
                                <?php
                                $rating = $anime['avg_rating'];
                                $fullStars = floor($rating / 2);
                                $halfStar = ($rating % 2) >= 1;
                                
                                for ($i = 1; $i <= 5; $i++):
                                    if ($i <= $fullStars):
                                        echo '<i class="bi bi-star-fill"></i>';
                                    elseif ($i == $fullStars + 1 && $halfStar):
                                        echo '<i class="bi bi-star-half"></i>';
                                    else:
                                        echo '<i class="bi bi-star"></i>';
                                    endif;
                                endfor;
                                ?>
                            </div>
                            <small class="text-muted">
                                <span id="totalRatings"><?= $anime['rating_count'] ?></span> гласа
                            </small>
                        <?php else: ?>
                            <div class="text-muted">
                                <i class="bi bi-star fs-1"></i>
                                <p>Все още няма оценки</p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Гласуване (само за логнати потребители) -->
                        <?php if (isLoggedIn()): ?>
                            <hr>
                            <h6>Оценете това аниме:</h6>
                            <div class="rating-form">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <div class="rating-input">
                                        <input type="radio" name="rating" id="rating-<?= $i ?>" value="<?= $i ?>" 
                                               data-anime-id="<?= $animeId ?>" 
                                               <?= ($userRating == $i) ? 'checked' : '' ?>>
                                        <label for="rating-<?= $i ?>" title="<?= $i ?>/10">
                                            <?= $i ?>
                                        </label>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <?php if ($userRating): ?>
                                <small class="text-success">
                                    <i class="bi bi-check-circle me-1"></i>
                                    Вашата оценка: <?= $userRating ?>/10
                                </small>
                            <?php endif; ?>
                        <?php else: ?>
                            <hr>
                            <a href="login.php?redirect=anime.php?id=<?= $animeId ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-box-arrow-in-right me-1"></i>
                                Влезте за да оцените
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Рейтинг разбивка -->
                <?php if (!empty($ratingBreakdown)): ?>
                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="card-title">Разпределение на оценките</h6>
                        <?php for ($i = 10; $i >= 1; $i--): ?>
                            <?php $count = $ratingBreakdown[$i] ?? 0; ?>
                            <?php $percentage = $anime['rating_count'] > 0 ? ($count / $anime['rating_count']) * 100 : 0; ?>
                            <div class="d-flex align-items-center mb-1">
                                <span class="me-2" style="min-width: 20px;"><?= $i ?></span>
                                <div class="progress flex-grow-1 me-2" style="height: 15px;">
                                    <div class="progress-bar bg-warning" style="width: <?= $percentage ?>%"></div>
                                </div>
                                <small class="text-muted" style="min-width: 30px;"><?= $count ?></small>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- Заглавие и основна информация -->
            <div class="mb-4">
                <h1 class="display-5 fw-bold mb-3"><?= htmlspecialchars($anime['title']) ?></h1>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <span class="badge genre-badge fs-6 mb-2">
                            <?= htmlspecialchars($anime['genre']) ?>
                        </span>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <small class="text-muted">
                            <i class="bi bi-person me-1"></i>
                            Добавено от 
                            <a href="profile.php?user=<?= urlencode($anime['creator_username']) ?>" 
                               class="text-decoration-none">
                                <?= htmlspecialchars($anime['creator_username']) ?>
                            </a>
                            <br>
                            <i class="bi bi-calendar me-1"></i>
                            <?= formatDate($anime['created_at']) ?>
                        </small>
                    </div>
                </div>
                
                <?php if ($anime['description']): ?>
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-file-text me-2"></i>Описание
                            </h5>
                            <p class="card-text"><?= nl2br(htmlspecialchars($anime['description'])) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Дискусии -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-chat-dots me-2"></i>Дискусии
                    </h5>
                    <?php if (isLoggedIn()): ?>
                        <a href="create_discussion.php?anime_id=<?= $animeId ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle me-1"></i>Нова дискусия
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($discussions)): ?>
                        <div class="empty-state">
                            <i class="bi bi-chat-left-text"></i>
                            <h5>Все още няма дискусии</h5>
                            <p class="text-muted">Бъдете първият, който ще започне дискусия за това аниме!</p>
                            <?php if (isLoggedIn()): ?>
                                <a href="create_discussion.php?anime_id=<?= $animeId ?>" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-2"></i>Започни дискусия
                                </a>
                            <?php else: ?>
                                <a href="login.php?redirect=anime.php?id=<?= $animeId ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Влезте за да започнете дискусия
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($discussions as $discussion): ?>
                                <div class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <a href="discussion.php?id=<?= $discussion['id'] ?>" 
                                                   class="text-decoration-none">
                                                    <?= htmlspecialchars($discussion['title']) ?>
                                                </a>
                                            </h6>
                                            <small class="text-muted">
                                                от <?= htmlspecialchars($discussion['username']) ?> • 
                                                <?= formatDate($discussion['created_at']) ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-chat-left-text me-1"></i>
                                            <?= $discussion['comment_count'] ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="#" class="btn btn-outline-primary" onclick="loadAllDiscussions(<?= $animeId ?>)">
                                <i class="bi bi-arrow-down-circle me-2"></i>Виж всички дискусии
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.anime-poster {
    max-height: 500px;
    object-fit: cover;
}

.rating-form {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin: 1rem 0;
}

.rating-input {
    position: relative;
}

.rating-input input[type="radio"] {
    display: none;
}

.rating-input label {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border: 2px solid #ddd;
    border-radius: 50%;
    cursor: pointer;
    font-weight: bold;
    font-size: 0.8rem;
    transition: all 0.2s ease;
}

.rating-input:hover label,
.rating-input input:checked + label {
    background: linear-gradient(45deg, #ff6b35, #f7931e);
    color: white;
    border-color: #ff6b35;
}

.progress {
    border-radius: 10px;
}
</style>

<script>
function loadAllDiscussions(animeId) {
    // Това ще се имплементира за зареждане на всички дискусии
    showToast('Функцията ще бъде добавена скоро', 'info');
}
</script>

<?php require_once 'footer.php'; ?>