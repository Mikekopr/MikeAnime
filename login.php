<?php
$pageTitle = 'Вход';
require_once 'header.php';

// Ако потребителят е вече логнат, пренасочи към началото
if (isLoggedIn()) {
    redirectTo('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = sanitizeInput($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Валидация
    if (empty($login)) {
        $errors[] = 'Моля въведете потребителско име или имейл.';
    }
    
    if (empty($password)) {
        $errors[] = 'Паролата е задължителна.';
    }
    
    // Проверка на потребителя
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Успешен вход
            $_SESSION['user_id'] = $user['id'];
            session_regenerate_id(true);
            
            // Ако е избрано "Запомни ме"
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $stmt->execute([$token, $user['id']]);
                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/'); // 30 дни
            }
            
            // Пренасочване
            $redirect = $_GET['redirect'] ?? 'index.php';
            redirectTo($redirect);
        } else {
            $errors[] = 'Грешно потребителско име/имейл или парола.';
        }
    }
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card anime-card">
                <div class="card-header bg-primary text-white text-center">
                    <h3 class="mb-0"><i class="bi bi-box-arrow-in-right me-2"></i>Вход</h3>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $error ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="login" class="form-label">Потребителско име или имейл</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="login" name="login" 
                                       value="<?= isset($login) ? htmlspecialchars($login) : '' ?>" 
                                       placeholder="Въведете потребителско име или имейл" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Парола</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Въведете паролата" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                    <i class="bi bi-eye" id="passwordToggleIcon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">
                                Запомни ме (30 дни)
                            </label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Влез
                            </button>
                        </div>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="text-muted">Нямате профил?</p>
                        <a href="register.php" class="btn btn-outline-primary">
                            <i class="bi bi-person-plus me-2"></i>Регистрация
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Демо профили за тестване -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Демо профили за тестване</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-6 mb-2">
                            <strong>Администратор:</strong><br>
                            <code>admin@animetalk.bg</code><br>
                            <small class="text-muted">парола: password</small>
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Потребител:</strong><br>
                            <code>otaku@test.bg</code><br>
                            <small class="text-muted">парола: password</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + 'ToggleIcon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        field.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>

<?php require_once 'footer.php'; ?>