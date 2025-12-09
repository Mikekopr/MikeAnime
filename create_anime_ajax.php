<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Трябва да влезете в профила си.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$title = sanitizeInput($_POST['title'] ?? '');
$genre = sanitizeInput($_POST['genre'] ?? '');
$description = sanitizeInput($_POST['description'] ?? '');
$imageUrl = $_POST['imageUrl'] ?? '';
$bannerImage = 'assets/img/default-anime.jpg';

if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Заглавието е задължително.']);
    exit;
}

if (empty($genre)) {
    echo json_encode(['success' => false, 'message' => 'Жанрът е задължителен.']);
    exit;
}

if (!empty($imageUrl)) {
    $validatedUrl = filter_var($imageUrl, FILTER_VALIDATE_URL);
    if ($validatedUrl) {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $urlPath = parse_url($validatedUrl, PHP_URL_PATH);
        $extension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
        
        if (in_array($extension, $imageExtensions)) {
            $bannerImage = $validatedUrl;
        }
    }
}

$stmt = $pdo->prepare("SELECT id FROM anime WHERE title = ?");
$stmt->execute([$title]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Аниме с това заглавие вече съществува.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO anime (title, genre, description, banner_image, created_by) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$title, $genre, $description, $bannerImage, $_SESSION['user_id']])) {
        echo json_encode([
            'success' => true,
            'anime_id' => $pdo->lastInsertId(),
            'title' => $title,
            'genre' => $genre,
            'banner_image' => $bannerImage
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Грешка при създаването на анимето.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Грешка при създаването на анимето.']);
}
?>