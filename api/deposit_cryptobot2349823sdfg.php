<?php
require_once 'con_main.php';

$logFile = __DIR__ . '/deposit_webhook.log';

function logToFile($message) {
    global $logFile;
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}

// Получение и логирование JSON
$json = file_get_contents('php://input');
logToFile("Raw input: " . $json);

$data = json_decode($json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    logToFile("JSON decode error: " . json_last_error_msg());
    http_response_code(400);
    exit('Invalid JSON');
}

// Проверяем, что update_type — invoice_paid
if (!isset($data['update_type']) || $data['update_type'] !== 'invoice_paid') {
    logToFile("Invalid or missing update_type");
    http_response_code(200);
    exit('Not an invoice_paid update');
}

// Данные находятся внутри payload
$payload = $data['payload'] ?? [];

if (!isset($payload['invoice_id'], $payload['status'], $payload['asset'], $payload['amount'], $payload['payload'])) {
    logToFile("Missing fields in payload: " . print_r($data, true));
    http_response_code(400);
    exit('Invalid payload');
}

// Проверка статуса платежа
if ($payload['status'] !== 'paid') {
    logToFile("Invoice not paid. Status: " . $payload['status']);
    http_response_code(200);
    exit('Invoice not paid');
}

// Подготовка данных
$invoice_id = mysqli_real_escape_string($conn, $payload['invoice_id']);
$user_id = intval($payload['payload']); // это твой user_id, который ты передаёшь как payload
$amount = floatval($payload['amount']);
$timestamp = date('Y-m-d H:i:s');
$credited = $amount * 100;

logToFile("Processing deposit — invoice_id: $invoice_id, user_id: $user_id, amount: $amount, credited: $credited");

// Проверка на дубликат
$check = mysqli_query($conn, "SELECT id FROM deposits WHERE invoice_id = '$invoice_id'");
if (!$check) {
    logToFile("Check query error: " . mysqli_error($conn));
} elseif (mysqli_num_rows($check) > 0) {
    logToFile("Invoice already processed: $invoice_id");
    http_response_code(200);
    exit('Already processed');
}

// Вставка в deposits
$insert = mysqli_query($conn, "
    INSERT INTO deposits (invoice_id, user_id, amount, created_at)
    VALUES ('$invoice_id', $user_id, $amount, '$timestamp')
");

if (!$insert) {
    logToFile("Insert error: " . mysqli_error($conn));
} else {
    logToFile("Insert OK: invoice_id = $invoice_id");
}

// Обновление users
$update = mysqli_query($conn, "
    UPDATE users
    SET balance = balance + $credited,
        wager = wager + $credited
    WHERE id = $user_id
");

if (!$update) {
    logToFile("Update error: " . mysqli_error($conn));
} else {
    logToFile("Update OK for user_id = $user_id");
	
    // === Начисление реферального бонуса ===
    $refRes = mysqli_query($conn, "SELECT ref_by FROM users WHERE id = $user_id LIMIT 1");
    if ($refRes && mysqli_num_rows($refRes) > 0) {
        $refRow = mysqli_fetch_assoc($refRes);
        $refBy = intval($refRow['ref_by']);

        if ($refBy > 0 && $refBy !== $user_id) {
            // Проверяем, есть ли такой реферальный пользователь
            $checkRefUser = mysqli_query($conn, "SELECT id FROM users WHERE user_id = $refBy LIMIT 1");
            if ($checkRefUser && mysqli_num_rows($checkRefUser) > 0) {
                $bonus = round($credited * 0.10, 2); // 10% бонус

                $updateRef = mysqli_query($conn, "UPDATE users SET ref_available = ref_available + $bonus, ref_earned = ref_earned + $bonus WHERE user_id = $refBy");

                if ($updateRef) {
                    logToFile("Referral bonus +$bonus credited to user_id = $refBy");
                } else {
                    logToFile("Referral bonus update error: " . mysqli_error($conn));
                }
            } else {
                logToFile("Referrer user not found: $refBy");
            }
        } else {
            logToFile("No valid referrer for user_id = $user_id");
        }
    } else {
        logToFile("Failed to fetch ref_by for user_id = $user_id: " . mysqli_error($conn));
    }
    // === Конец начисления реферального бонуса ===	
}

// Финальный ответ
if ($insert && $update) {
    http_response_code(200);
    echo 'Deposit successful';
    logToFile("Deposit completed successfully.");
} else {
    http_response_code(500);
    echo 'Database error';
    logToFile("Final response: Database error.");
}
?>
