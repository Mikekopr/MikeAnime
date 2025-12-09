<?php
require_once 'config.php';

$discussionId = intval($_GET['id'] ?? 0);

if (!$discussionId) {
    redirectTo('index.php');
}

$stmt = $pdo->prepare("
    SELECT 
        d.*,
        a.title as anime_title,
        a.banner_image as anime_banner,
        a.id as anime_id,
        u.username as creator_username
    FROM discussions d
    JOIN anime a ON d.anime_id = a.id
    JOIN users u ON d.created_by = u.id
    WHERE d.id = ?
");
$stmt->execute([$discussionId]);
$discussion = $stmt->fetch();

if (!$discussion) {
    redirectTo('index.php');
}

$pageTitle = $discussion['title'];


$stmt = $pdo->prepare("
    SELECT 
        c.*,
        u.username,
        u.avatar,
        (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) as like_count,
        (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id AND user_id = ?) as user_liked
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.discussion_id = ?
    ORDER BY c.created_at ASC
");
$stmt->execute([isLoggedIn() ? $_SESSION['user_id'] : 0, $discussionId]);
$comments = $stmt->fetchAll();

require_once 'header.php';
?>

<div class="container mt-4">
   
    <div class="row mb-4">
        <div class="col-12">
            <div class="card discussion-card">
                <div class="card-body">
                    <div class="d-flex mb-3">
                        <img src="<?= $discussion['anime_banner'] ?: 'assets/img/default-anime.jpg' ?>" 
                             class="anime-thumbnail me-3" 
                             alt="<?= htmlspecialchars($discussion['anime_title']) ?>">
                        <div class="flex-grow-1">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-2">
                                    <li class="breadcrumb-item"><a href="index.php">Начало</a></li>
                                    <li class="breadcrumb-item">
                                        <a href="anime.php?id=<?= $discussion['anime_id'] ?>">
                                            <?= htmlspecialchars($discussion['anime_title']) ?>
                                        </a>
                                    </li>
                                    <li class="breadcrumb-item active">Дискусия</li>
                                </ol>
                            </nav>
                            <h1 class="h3 mb-2"><?= htmlspecialchars($discussion['title']) ?></h1>
                            <div class="text-muted">
                                <i class="bi bi-person me-1"></i>
                                Започната от <?= htmlspecialchars($discussion['creator_username']) ?>
                                <i class="bi bi-calendar ms-3 me-1"></i>
                                <?= formatDate($discussion['created_at']) ?>
                                <i class="bi bi-chat-left-text ms-3 me-1"></i>
                                <?= count($comments) ?> коментара
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
   
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-chat-dots me-2"></i>Коментари (<?= count($comments) ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($comments)): ?>
                        <div class="empty-state">
                            <i class="bi bi-chat-left-text"></i>
                            <h5>Все още няма коментари</h5>
                            <p class="text-muted">Бъдете първият, който ще коментира!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment-item" data-comment-id="<?= $comment['id'] ?>">
                                <div class="comment-header">
                                    <img src="<?= $comment['avatar'] ?: 'assets/img/default-avatar.png' ?>" 
                                         class="comment-avatar" 
                                         alt="<?= htmlspecialchars($comment['username']) ?>">
                                    <div class="flex-grow-1">
                                        <strong><?= htmlspecialchars($comment['username']) ?></strong>
                                        <small class="text-muted ms-2">
                                            <?= formatDate($comment['created_at']) ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="comment-content">
                                    <?= nl2br(htmlspecialchars($comment['content'])) ?>
                                </div>
                                <div class="comment-actions">
                                    <?php if (isLoggedIn()): ?>
                                        <button class="like-btn <?= $comment['user_liked'] ? 'liked' : '' ?>" 
                                                data-comment-id="<?= $comment['id'] ?>">
                                            <i class="bi bi-heart<?= $comment['user_liked'] ? '-fill' : '' ?>"></i>
                                            <span class="like-count"><?= $comment['like_count'] ?></span>
                                        </button>
                                        <button class="btn btn-link btn-sm text-muted" onclick="replyToComment(<?= $comment['id'] ?>, '<?= htmlspecialchars($comment['username'], ENT_QUOTES) ?>')">
                                            <i class="bi bi-reply me-1"></i>Отговори
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            <i class="bi bi-heart me-1"></i><?= $comment['like_count'] ?> харесвания
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            
            <?php if (isLoggedIn()): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-plus-circle me-2"></i>Добави коментар
                        </h6>
                    </div>
                    <div class="card-body">
                        <form id="commentForm" onsubmit="submitComment(event)">
                            <div class="mb-3">
                                <textarea class="form-control auto-resize" id="commentContent" 
                                          placeholder="Споделете вашето мнение..." 
                                          rows="4" required></textarea>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Бъдете учтиви и спазвайте правилата на общността
                                </small>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send me-1"></i>Изпрати
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card mt-4">
                    <div class="card-body text-center">
                        <i class="bi bi-chat-left-text fs-1 text-muted"></i>
                        <h5 class="mt-3">Участвайте в дискусията</h5>
                        <p class="text-muted">Влезте в профила си, за да можете да коментирате и харесвате.</p>
                        <a href="login.php?redirect=discussion.php?id=<?= $discussionId ?>" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Влез в профила
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
       
        <div class="col-lg-4">
            
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-film me-2"></i>За анимето
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <img src="<?= $discussion['anime_banner'] ?: 'assets/img/default-anime.jpg' ?>" 
                             class="me-3" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;"
                             alt="<?= htmlspecialchars($discussion['anime_title']) ?>">
                        <div>
                            <h6 class="mb-1">
                                <a href="anime.php?id=<?= $discussion['anime_id'] ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($discussion['anime_title']) ?>
                                </a>
                            </h6>
                        </div>
                    </div>
                    <div class="d-grid">
                        <a href="anime.php?id=<?= $discussion['anime_id'] ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye me-1"></i>Виж подробности
                        </a>
                    </div>
                </div>
            </div>
            
            
            <?php
            $stmt = $pdo->prepare("
                SELECT 
                    d.id,
                    d.title,
                    d.created_at,
                    u.username,
                    (SELECT COUNT(*) FROM comments WHERE discussion_id = d.id) as comment_count
                FROM discussions d
                JOIN users u ON d.created_by = u.id
                WHERE d.anime_id = ? AND d.id != ?
                ORDER BY d.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$discussion['anime_id'], $discussionId]);
            $otherDiscussions = $stmt->fetchAll();
            ?>
            
            <?php if (!empty($otherDiscussions)): ?>
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-chat-dots me-2"></i>Други дискусии
                    </h6>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($otherDiscussions as $other): ?>
                        <a href="discussion.php?id=<?= $other['id'] ?>" 
                           class="list-group-item list-group-item-action">
                            <h6 class="mb-1"><?= htmlspecialchars($other['title']) ?></h6>
                            <small class="text-muted">
                                от <?= htmlspecialchars($other['username']) ?> • 
                                <?= $other['comment_count'] ?> коментара
                            </small>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer">
                    <a href="anime.php?id=<?= $discussion['anime_id'] ?>#discussions" class="btn btn-sm btn-outline-primary w-100">
                        Виж всички дискусии
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.comment-item {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}

.comment-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.comment-content {
    margin: 0.75rem 0;
    line-height: 1.6;
}

.like-btn {
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    transition: color 0.2s ease;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
}

.like-btn:hover {
    color: #ff6b35;
    background-color: rgba(255, 107, 53, 0.1);
}

.like-btn.liked {
    color: #ff6b35;
}

.like-btn i {
    margin-right: 0.25rem;
}

.anime-thumbnail {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 10px;
}
</style>

<script>
function submitComment(event) {
    event.preventDefault();
    
    const content = document.getElementById('commentContent').value.trim();
    if (!content) {
        showToast('Моля въведете съдържание на коментара', 'warning');
        return;
    }
    
    
    const submitBtn = event.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Изпращане...';
    
    addComment(<?= $discussionId ?>, content);
}

function replyToComment(commentId, username) {
    const textarea = document.getElementById('commentContent');
    const currentText = textarea.value;
    const replyText = `@${username} `;
    
    if (!currentText.includes(replyText)) {
        textarea.value = replyText + currentText;
    }
    
    textarea.focus();
    textarea.setSelectionRange(replyText.length, replyText.length);
}

// Автоматично преоразмеряване на textarea
autoResizeTextarea();
</script>

<?php require_once 'footer.php'; ?>