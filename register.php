<?php
$pageTitle = 'Регистрация';
require_once 'header.php';


if (isLoggedIn()) {
    redirectTo('index.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    
    if (empty($username)) {
        $errors[] = 'Потребителското име е задължително.';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Потребителското име трябва да е поне 3 символа.';
    } elseif (strlen($username) > 50) {
        $errors[] = 'Потребителското име не може да е повече от 50 символа.';
    }
    
    if (empty($email)) {
        $errors[] = 'Имейлът е задължителен.';
    } elseif (!validateEmail($email)) {
        $errors[] = 'Моля въведете валиден имейл адрес.';
    }
    
    if (empty($password)) {
        $errors[] = 'Паролата е задължителна.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Паролата трябва да е поне 6 символа.';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Паролите не съвпадат.';
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Потребителското име или имейл вече се използват.';
        }
    }
    
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        
        if ($stmt->execute([$username, $email, $hashedPassword])) {
            $success = true;
        } else {
            $errors[] = 'Грешка при създаването на профила. Моля опитайте отново.';
        }
    }
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card anime-card">
                <div class="card-header bg-primary text-white text-center">
                    <h3 class="mb-0"><i class="bi bi-person-plus me-2"></i>Регистрация</h3>
                </div>
                <div class="card-body p-4">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            Успешно създадохте профил! Сега можете да <a href="login.php" class="alert-link">влезете</a>.
                        </div>
                    <?php else: ?>
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
                                <label for="username" class="form-label">Потребителско име</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?= isset($username) ? htmlspecialchars($username) : '' ?>" 
                                           placeholder="Въведете потребителско име" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Имейл адрес</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" 
                                           placeholder="Въведете имейл адрес" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Парола</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Въведете парола (минимум 6 символа)" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                        <i class="bi bi-eye" id="passwordToggleIcon"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Потвърдете паролата</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="Потвърдете паролата" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                        <i class="bi bi-eye" id="confirmPasswordToggleIcon"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-person-check me-2"></i>Създай профил
                                </button>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="text-muted">Вече имате профил?</p>
                            <a href="login.php" class="btn btn-outline-primary">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Вход
                            </a>
                        </div>
                    <?php endif; ?>
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