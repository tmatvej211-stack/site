<?php
require_once 'config.php';

class Security {
    // Защищенное соединение с БД
    public static function db_connect() {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Database connection failed");
        }
        return $conn;
    }

    // Защищенный хеш пароля
    public static function verify_password($password, $hash) {
        return md5($password . SALT) === $hash;
    }

    public static function hash_password($password) {
        return md5($password . SALT);
    }

    // Защита от CSRF
    public static function generate_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // Валидация CSRF
    public static function validate_csrf($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    // Защита от brute-force
    public static function check_login_attempts($conn, $ip) {
        $stmt = $conn->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE ip = ?");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['attempts'] >= LOGIN_ATTEMPTS_LIMIT && 
                time() - strtotime($row['last_attempt']) < LOGIN_BLOCK_TIME) {
                return false;
            }
        }
        return true;
    }

    // Очистка ввода
    public static function sanitize_input($data) {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}
?>