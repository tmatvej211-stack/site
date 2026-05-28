<?php

// Ваш токен
$fa = "";
$error = 0;
$amount = $_GET['amount'];
if($amount < 1) {
   $fa = "error";
   $mess = "Минимальная сумма пополнения 1$";
}
if($amount > 1000000) {
   $fa = "error";
   $mess = "Максимальная сумма пополнения 1,000,000$";
}
$token = '440277:AAahgeDvgS1SQysjpUGbKJ3OrEO3uVgaWJe';

// Базовый URL API CryptoBot
$api_url = 'https://pay.crypt.bot/api/createInvoice';

// Данные для инвойса
$params = [
    'asset' => 'USDT',           // Валюта (USDT, BTC, ETH и др.)
    'amount' => $amount,              // Сумма оплаты
    'description' => 'Пополнение баланса Welp Casino', // Описание
    'hidden_message' => 'Спасибо за пополнение!', // Сообщение после оплаты
    'payload' => $user_id, // 👈 Ваш кастомный параметр (будет возвращен в webhook)
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
print_r($result);
