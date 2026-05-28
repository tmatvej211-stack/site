<?php
require_once 'config.php';
require_once 'security.php';

$error = '';
$conn = Security::db_connect();

// Проверка IP
$ip = $_SERVER['REMOTE_ADDR'];
if (!Security::check_login_attempts($conn, $ip)) {
    $error = "Слишком много попыток входа. Попробуйте позже.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Валидация CSRF
    if (!Security::validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Ошибка безопасности. Обновите страницу.";
    } else {
        $login = Security::sanitize_input($_POST['login'] ?? '');
        $password = Security::sanitize_input($_POST['password'] ?? '');
        
        // Получаем настройки админа
        $stmt = $conn->prepare("SELECT cp_login, cp_password FROM settings WHERE id = 1 LIMIT 1");
        $stmt->execute();
        $settings = $stmt->get_result()->fetch_assoc();
        
        if ($settings && $login === $settings['cp_login'] && 
            Security::verify_password($password, $settings['cp_password'])) {
            
            // Успешный вход
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            $_SESSION['ip'] = $ip;
            
            // Сброс попыток входа
            $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip = ?");
            $stmt->bind_param("s", $ip);
            $stmt->execute();
            
            header('Location: index.php');
            exit;
        } else {
            // Неудачная попытка входа
            $error = "Неверные учетные данные";
            
            // Логируем попытку
            $stmt = $conn->prepare("INSERT INTO login_attempts (ip, attempts, last_attempt) 
                                   VALUES (?, 1, NOW()) 
                                   ON DUPLICATE KEY UPDATE 
                                   attempts = attempts + 1, last_attempt = NOW()");
            $stmt->bind_param("s", $ip);
            $stmt->execute();
        }
    }
}

// Генерация CSRF токена
$csrf_token = Security::generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в админ-панель</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="login-container">
        <h1>Административная панель</h1>
        <?php if ($error): ?>
            <div class="alert error"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="form-group">
                <label for="login">Логин:</label>
                <input type="text" id="login" name="login" required>
            </div>
            <div class="form-group">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Войти</button>
        </form>
    </div>
</body>
</html>