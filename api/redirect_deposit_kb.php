<?php
require_once("con_main.php");
// Ваш токен
$fa = "";
$error = 0;
$amount = $_POST['amount'];
$user_id = $_POST['user_id'];
if(!is_numeric($amount)) exit;
if(!is_numeric($user_id)) exit;
if($amount < 1) {
   $fa = "error";
   $error = 1;
   $mess = "Минимальная сумма пополнения 1$";
}
if($amount > 1000000) {
   $fa = "error";
   $error = 1;
   $mess = "Максимальная сумма пополнения 1,000,000$";
}
$token = '446713:AAL00syFxp0zuBmMLyS0JrCRAd69brLjhNw';

// Базовый URL API CryptoBot
$api_url = 'https://pay.crypt.bot/api/createInvoice';

if($error == 0){
// Данные для инвойса

$params = [
    'asset' => 'USDT',           // Валюта (USDT, BTC, ETH и др.)
    'amount' => $amount,              // Сумма оплаты
    'description' => 'Пополнение баланса Welp Casino', // Описание
    'hidden_message' => 'Спасибо за пополнение!', // Сообщение после оплаты
    'payload' => (string)$user_id, // 👈 Ваш кастомный параметр (будет возвращен в webhook)
    'allow_comments' => false,
    'allow_anonymous' => false
];

// Инициализация CURL
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Crypto-Pay-API-Token: ' . $token
]);

// Выполнение запроса
$response = curl_exec($ch);
curl_close($ch);

// Декодирование ответа
$result = json_decode($response, true);

if (isset($result['ok']) && $result['ok']) {
    // Успешно, получаем ссылку
    $invoice_id = mysqli_real_escape_string($conn, $result['result']['invoice_id']);
    $pay_url = $result['result']['pay_url'];

    // сохраняем invoice_id в depNums
    $sql = "INSERT INTO depNums (invoice_id, status) VALUES ('$invoice_id', 0)";
    if (!mysqli_query($conn, $sql)) {
        die("Error");	
    }

    $fa = "success";
} else {
    $fa = "error";
    $mess = $result['result'];
}
}
$result1 = array(
   'success' => $fa,
   'mess' => $mess,
   'pay_url' => $pay_url,
);
exit(json_encode($result1));
