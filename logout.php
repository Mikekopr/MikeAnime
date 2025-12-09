<?php
require_once 'config.php';

if (isLoggedIn()) {
    
    if (isset($_COOKIE['remember_token'])) {
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    
    session_destroy();
}

redirectTo('index.php');
?>