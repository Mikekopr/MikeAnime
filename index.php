<?php
$pageTitle = 'Начало';
require_once 'header.php';


$stmt = $pdo->prepare("
    SELECT 
        d.id as discussion_id,
        d.title as discussion_title,
        d.created_at as discussion_date,
        a.id as anime_id,
        a.title as anime_title,
        a.banner_image,
        u.username,
        (SELECT COUNT(*) FROM comments WHERE discussion_id = d.id) as comment_count,
        (SELECT COUNT(*) FROM comment_likes cl 
         JOIN comments c ON cl.comment_id = c.id 
         WHERE c.discussion_id = d.id) as total_likes,
        (SELECT content FROM comments WHERE discussion_id = d.id ORDER BY created_at ASC LIMIT 1) as first_comment
    FROM discussions d
    JOIN anime a ON d.anime_id = a.id
    JOIN users u ON d.created_by = u.id
    ORDER BY d.created_at DESC
    LIMIT 12
");
$stmt->execute();
$discussions = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT 
        a.*,
        ROUND(AVG(r.rating), 1) as avg_rating,
        COUNT(r.id) as rating_count
    FROM anime a
    LEFT JOIN ratings r ON a.id = r.anime_id
    GROUP BY a.id
    HAVING rating_count > 0
    ORDER BY avg_rating DESC, rating_count DESC
    LIMIT 6
");
$stmt->execute();
$topAnime = $stmt->fetchAll();

$stats = [
    'anime' => $pdo->query("SELECT COUNT(*) FROM anime")->fetchColumn(),
    'discussions' => $pdo->query("SELECT COUNT(*) FROM discussions")->fetchColumn(),
    'comments' => $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn(),
    'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn()
];
?>


<section class="bg-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="display-4 fw-bold mb-3">Добре дошли в <?= SITE_NAME ?>!</h1>
                <p class="lead mb-4">
                    Българският форум за аниме любители. Открийте нови анимета, 
                    споделете мнения и се включете в оживени дискусии с единомишленици.
                </p>
                <?php if (!isLoggedIn()): ?>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="register.php" class="btn btn-warning btn-lg">
                            <i class="bi bi-person-plus me-2"></i>Регистрация
                        </a>
                        <a href="login.php" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Вход
                        </a>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="create_anime.php" class="btn btn-warning btn-lg">
                            <i class="bi bi-plus-circle me-2"></i>Добави Аниме
                        </a>
                        <a href="create_discussion.php" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-chat-left-text me-2"></i>Създай Дискусия
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-lg-4 text-center">
                <i class="bi bi-play-circle display-1 japanese-wave position-relative"></i>
            </div>
        </div>
    </div>
</section>


<section class="py-5 bg-light">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <i class="bi bi-film fs-1 mb-2"></i>
                    <span class="stats-number"><?= number_format($stats['anime']) ?></span>
                    <div>Анимета</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <i class="bi bi-chat-dots fs-1 mb-2"></i>
                    <span class="stats-number"><?= number_format($stats['discussions']) ?></span>
                    <div>Дискусии</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <i class="bi bi-chat-left-text fs-1 mb-2"></i>
                    <span class="stats-number"><?= number_format($stats['comments']) ?></span>
                    <div>Коментара</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <i class="bi bi-people fs-1 mb-2"></i>
                    <span class="stats-number"><?= number_format($stats['users']) ?></span>
                    <div>Потребители</div>
                </div>
            </div>
        </div>
    </div>
</section>


<?php if (!empty($topAnime)): ?>
<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-5">
            <i class="bi bi-star-fill text-warning me-2"></i>Най-високо оценени анимета
        </h2>
        <div class="row g-4">
            <?php foreach ($topAnime as $anime): ?>
                <div class="col-lg-4 col-md-6 fade-in-up">
                    <div class="card anime-card h-100">
                        <img src="<?= $anime['banner_image'] ?: 'assets/img/default-anime.jpg' ?>" 
                             class="card-img-top" alt="<?= htmlspecialchars($anime['title']) ?>">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($anime['title']) ?></h5>
                            <span class="badge genre-badge mb-2 align-self-start">
                                <?= htmlspecialchars($anime['genre']) ?>
                            </span>
                            
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
                                <small class="text-muted ms-2">
                                    <?= $rating ?>/10 (<?= $anime['rating_count'] ?> гласа)
                                </small>
                            </div>
                            
                            <p class="card-text flex-grow-1">
                                <?= htmlspecialchars(substr($anime['description'] ?? '', 0, 100)) ?>
                                <?= strlen($anime['description'] ?? '') > 100 ? '...' : '' ?>
                            </p>
                            
                            <div class="mt-auto">
                                <a href="anime.php?id=<?= $anime['id'] ?>" class="btn btn-primary">
                                    <i class="bi bi-eye me-1"></i>Виж детайли
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>


<section class="py-5 bg-light">
    <div class="container">
        <h2 class="text-center mb-5">
            <i class="bi bi-chat-dots me-2"></i>Най-нови дискусии
        </h2>
        
        <?php if (empty($discussions)): ?>
            <div class="empty-state">
                <i class="bi bi-chat-left-text"></i>
                <h4>Все още няма дискусии</h4>
                <p>Бъдете първият, който ще започне дискусия!</p>
                <?php if (isLoggedIn()): ?>
                    <a href="create_discussion.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Създай първата дискусия
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($discussions as $discussion): ?>
                    <div class="col-lg-6 fade-in-up">
                        <div class="card discussion-card h-100">
                            <div class="card-body">
                                <div class="d-flex mb-3">
                                    <img src="<?= $discussion['banner_image'] ?: 'assets/img/default-anime.jpg' ?>" 
                                         class="anime-thumbnail me-3" 
                                         alt="<?= htmlspecialchars($discussion['anime_title']) ?>">
                                    <div class="flex-grow-1">
                                        <h6 class="text-primary mb-1">
                                            <a href="anime.php?id=<?= $discussion['anime_id'] ?>" 
                                               class="text-decoration-none">
                                                <?= htmlspecialchars($discussion['anime_title']) ?>
                                            </a>
                                        </h6>
                                        <h5 class="card-title mb-1">
                                            <a href="discussion.php?id=<?= $discussion['discussion_id'] ?>" 
                                               class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($discussion['discussion_title']) ?>
                                            </a>
                                        </h5>
                                        <small class="text-muted">
                                            от <?= htmlspecialchars($discussion['username']) ?> • 
                                            <?= formatDate($discussion['discussion_date']) ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <p class="card-text">
                                    <?= htmlspecialchars(substr($discussion['first_comment'] ?? '', 0, 150)) ?>
                                    <?= strlen($discussion['first_comment'] ?? '') > 150 ? '...' : '' ?>
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-muted small">
                                        <i class="bi bi-chat-left-text me-1"></i>
                                        <?= $discussion['comment_count'] ?> коментара
                                        <i class="bi bi-heart-fill ms-3 me-1"></i>
                                        <?= $discussion['total_likes'] ?> харесвания
                                    </div>
                                    <a href="discussion.php?id=<?= $discussion['discussion_id'] ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        Участвай
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-5">
                <p class="text-muted">Това са само част от дискусиите.</p>
                <a href="#" class="btn btn-outline-primary" onclick="loadMoreDiscussions()">
                    <i class="bi bi-arrow-down-circle me-2"></i>Зареди още дискусии
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
function loadMoreDiscussions() {

    alert('Функцията за зареждане на още дискусии ще бъде добавена в main.js');
}
</script>

<?php require_once 'footer.php'; ?>