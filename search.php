<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$query = sanitizeInput($_POST['query'] ?? '');

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $searchTerm = '%' . $query . '%';
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            title, 
            genre, 
            description, 
            banner_image
        FROM anime 
        WHERE 
            title LIKE ? OR 
            genre LIKE ? OR 
            description LIKE ?
        ORDER BY 
            CASE 
                WHEN title LIKE ? THEN 1
                WHEN genre LIKE ? THEN 2
                ELSE 3
            END,
            title ASC
        LIMIT 10
    ");
    
    $stmt->execute([
        $searchTerm, $searchTerm, $searchTerm,
        $query . '%', $query . '%'
    ]);
    
    $results = $stmt->fetchAll();
    
    $formattedResults = array_map(function($anime) {
        return [
            'id' => $anime['id'],
            'title' => htmlspecialchars($anime['title']),
            'genre' => htmlspecialchars($anime['genre']),
            'description' => htmlspecialchars(substr($anime['description'] ?? '', 0, 100)),
            'banner_image' => $anime['banner_image'] ?: 'assets/img/default-anime.jpg'
        ];
    }, $results);
    
    echo json_encode($formattedResults);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Грешка при търсенето']);
}
?>