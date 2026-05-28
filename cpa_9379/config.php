<?php
// Настройки безопасности
date_default_timezone_set('Europe/Moscow');
define('SALT', 'SDFJ#W$(GFcsdCXMio3059xmZa'); // Уникальная соль для каждого проекта
define('SESSION_NAME', 'SECURE_ADMIN_SESS');
define('SESSION_LIFETIME', 1800); // 30 минут
define('LOGIN_ATTEMPTS_LIMIT', 5);
define('LOGIN_BLOCK_TIME', 900); // 15 минут

// Настройки БД
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Специальный пользователь БД с ограниченными правами
define('DB_PASS', 'W#$IFJ348gjadunz239');
define('DB_NAME', 'fkSUHFfn34iSzz');

// CSP и другие заголовки
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; connect-src 'self' https://welpgame.com:8443 wss://welpgame.com:8443; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

// Отключение вывода ошибок в production
error_reporting(0);
ini_set('display_errors', 0);

// Настройка сессии
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Только для HTTPS
ini_set('session.cookie_samesite', 'Lax'); // Меняем на Lax для работы с WebSocket
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
session_name(SESSION_NAME);
session_start();

// Регенерация ID сессии
if (!isset($_SESSION['created'])) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}
?>