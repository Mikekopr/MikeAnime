<?php
// Конфигурация за връзка с базата данни и общи настройки

// Настройки за базата данни
define('DB_HOST', 'localhost');
define('DB_NAME', 'animetalk_bg');
define('DB_USER', 'root');
define('DB_PASS', '');

// Други настройки
define('SITE_NAME', 'AnimeTalk BG');
define('SITE_URL', 'http://localhost/MikeAnime');
define('UPLOADS_DIR', 'uploads/');

// Стартиране на сесия
if (!session_id()) {
    session_start();
}

// PDO връзка с базата данни
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Грешка при свързването с базата данни: " . $e->getMessage());
}

// Помощни функции
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function redirectTo($url) {
    header("Location: " . SITE_URL . "/" . ltrim($url, '/'));
    exit;
}

function sanitizeInput($input) {
    return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function formatDate($datetime) {
    $date = new DateTime($datetime);
    return $date->format('d.m.Y в H:i');
}

function checkRememberToken() {
    global $pdo;
    
    if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $stmt = $pdo->prepare("SELECT id FROM users WHERE remember_token = ? AND remember_token IS NOT NULL");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            // Регенерираме токена за безопасност
            $newToken = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $stmt->execute([$newToken, $user['id']]);
            setcookie('remember_token', $newToken, time() + (30 * 24 * 60 * 60), '/');
        }
    }
}

// Проверяваме remember token при всяко зареждане
checkRememberToken();
?>