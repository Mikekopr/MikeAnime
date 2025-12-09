<?php
require_once 'config.php';

// Изтриване на сесията
if (isLoggedIn()) {
    // Изтриване на remember token
    if (isset($_COOKIE['remember_token'])) {
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    // Унищожаване на сесията
    session_destroy();
}

// Пренасочване към началната страница
redirectTo('index.php');
?>