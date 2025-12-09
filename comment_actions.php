<?php
require_once 'config.php';

// AJAX файл за действия с коментари (добавяне, харесване, etc.)
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Трябва да влезете в профила си.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add_comment':
        addComment();
        break;
    case 'toggle_like':
        toggleCommentLike();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Невалидно действие.']);
        break;
}

function addComment() {
    global $pdo;
    
    $discussionId = intval($_POST['discussion_id'] ?? 0);
    $content = sanitizeInput($_POST['content'] ?? '');
    
    // Валидация
    if (!$discussionId || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Невалидни данни.']);
        return;
    }
    
    // Проверка дали дискусията съществува
    $stmt = $pdo->prepare("SELECT id FROM discussions WHERE id = ?");
    $stmt->execute([$discussionId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Дискусията не съществува.']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO comments (discussion_id, user_id, content) 
            VALUES (?, ?, ?)
        ");
        
        if ($stmt->execute([$discussionId, $_SESSION['user_id'], $content])) {
            echo json_encode([
                'success' => true,
                'message' => 'Коментарът е добавен успешно!',
                'comment_id' => $pdo->lastInsertId()
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Грешка при добавянето на коментара.']);
        }
    } catch (Exception $e) {
        error_log("Comment add error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Грешка при добавянето на коментара.']);
    }
}

function toggleCommentLike() {
    global $pdo;
    
    $commentId = intval($_POST['comment_id'] ?? 0);
    
    // Валидация
    if (!$commentId) {
        echo json_encode(['success' => false, 'message' => 'Невалиден коментар.']);
        return;
    }
    
    // Проверка дали коментарът съществува
    $stmt = $pdo->prepare("SELECT id FROM comments WHERE id = ?");
    $stmt->execute([$commentId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Коментарът не съществува.']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Проверка дали потребителят вече е харесал коментара
        $stmt = $pdo->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ?");
        $stmt->execute([$commentId, $_SESSION['user_id']]);
        $existingLike = $stmt->fetch();
        
        $liked = false;
        
        if ($existingLike) {
            // Премахване на харесване
            $stmt = $pdo->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
            $stmt->execute([$commentId, $_SESSION['user_id']]);
            $liked = false;
        } else {
            // Добавяне на харесване
            $stmt = $pdo->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
            $stmt->execute([$commentId, $_SESSION['user_id']]);
            $liked = true;
        }
        
        // Изчисляване на новия брой харесвания
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM comment_likes WHERE comment_id = ?");
        $stmt->execute([$commentId]);
        $likeCount = $stmt->fetchColumn();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'liked' => $liked,
            'likeCount' => $likeCount
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Comment like error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Грешка при харесването.']);
    }
}
?>