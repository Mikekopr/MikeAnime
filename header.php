<?php
require_once 'config.php';
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?><?= SITE_NAME ?></title>
    
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-play-circle me-2"></i><?= SITE_NAME ?>
            </a>

            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            
            <div class="collapse navbar-collapse" id="navbarNav">
                
                <div class="mx-auto position-relative" style="width: 400px; max-width: 100%;">
                    <div class="input-group">
                        <input type="text" class="form-control" id="searchInput" 
                               placeholder="Търси аниме по заглавие, жанр или описание...">
                        <button class="btn btn-warning" type="button">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                    
                    <div id="searchResults" class="search-results position-absolute w-100 bg-white border border-top-0 rounded-bottom shadow-lg" style="display: none; z-index: 1000;">
                    </div>
                </div>

                
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-house me-1"></i>Начало
                        </a>
                    </li>
                    
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="create_anime.php">
                                <i class="bi bi-plus-circle me-1"></i>Добави Аниме
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="create_discussion.php">
                                <i class="bi bi-chat-left-text me-1"></i>Създай Дискусия
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i><?= sanitizeInput($currentUser['username']) ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php">
                                    <i class="bi bi-person me-2"></i>Профил
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Изход
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Вход
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">
                                <i class="bi bi-person-plus me-1"></i>Регистрация
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    
    <main class="flex-grow-1"><?php
    
    if (!isset($_COOKIE['cookies_accepted'])):
    ?>
    <div id="cookieBanner" class="alert alert-info alert-dismissible fade show mb-0 rounded-0 text-center" role="alert">
        <i class="bi bi-info-circle me-2"></i>
        Този сайт използва cookies за подобряване на потребителското изживяване.
        <button type="button" class="btn btn-sm btn-outline-primary ms-3" onclick="acceptCookies()">Разбирам</button>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>