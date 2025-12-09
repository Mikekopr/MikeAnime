<?php
require_once 'config.php';

// Този файл се извиква чрез AJAX за гласуване
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Трябва да влезете в профила си за да гласувате.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$animeId = intval($_POST['anime_id'] ?? 0);
$rating = intval($_POST['rating'] ?? 0);

// Валидация
if (!$animeId || $rating < 1 || $rating > 10) {
    echo json_encode(['success' => false, 'message' => 'Невалидни данни.']);
    exit;
}

// Проверка дали анимето съществува
$stmt = $pdo->prepare("SELECT id FROM anime WHERE id = ?");
$stmt->execute([$animeId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Анимето не съществува.']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Проверка дали потребителят вече е гласувал
    $stmt = $pdo->prepare("SELECT id FROM ratings WHERE anime_id = ? AND user_id = ?");
    $stmt->execute([$animeId, $_SESSION['user_id']]);
    $existingRating = $stmt->fetch();
    
    if ($existingRating) {
        // Обновяване на съществуваща оценка
        $stmt = $pdo->prepare("UPDATE ratings SET rating = ? WHERE anime_id = ? AND user_id = ?");
        $stmt->execute([$rating, $animeId, $_SESSION['user_id']]);
    } else {
        // Създаване на нова оценка
        $stmt = $pdo->prepare("INSERT INTO ratings (anime_id, user_id, rating) VALUES (?, ?, ?)");
        $stmt->execute([$animeId, $_SESSION['user_id'], $rating]);
    }
    
    // Изчисляване на новата средна оценка
    $stmt = $pdo->prepare("
        SELECT 
            ROUND(AVG(rating), 1) as avg_rating,
            COUNT(*) as total_ratings
        FROM ratings 
        WHERE anime_id = ?
    ");
    $stmt->execute([$animeId]);
    $stats = $stmt->fetch();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Благодарим за оценката!',
        'newAverage' => $stats['avg_rating'],
        'totalRatings' => $stats['total_ratings']
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Rating error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Грешка при записването на оценката. Моля опитайте отново.'
    ]);
}
?>