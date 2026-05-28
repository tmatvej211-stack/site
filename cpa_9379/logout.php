<?php
require_once 'config.php';

// Уничтожение сессии
$_SESSION = array();
session_destroy();

// Удаление куки
setcookie(session_name(), '', time() - 3600, '/');

header('Location: login.php');
exit;
?>