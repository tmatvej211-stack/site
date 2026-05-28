<?php
if(!preg_match("/^[0-9a-zA-Z]+$/", $_COOKIE['sid']) && $_COOKIE['sid'] != "") {
    exit();
}

$bd_login = 'root';
$bd_pass = 'W#$IFJ348gjadunz239';
$bd_name = 'fkSUHFfn34iSzz';

// Подключение с обработкой ошибок
$conn = mysqli_connect("localhost", $bd_login, $bd_pass, $bd_name);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8");
?>