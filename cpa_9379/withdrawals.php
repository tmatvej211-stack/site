<?php
require_once 'config.php';
require_once 'security.php';

// Настройка логирования
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/admin_errors.log');
error_log("========== START withdrawals.php ==========");

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    error_log("Redirect to login: admin not logged in");
    header('Location: login.php');
    exit;
}

// Статусы выплат
$statuses = [
    0 => '<span style="color:orange;font-weight:600">Ожидание</span>',
    1 => '<span style="color:#4bb543;font-weight:600">Выплачено</span>',
    2 => '<span style="color:#ff0000;font-weight:600">Отклонено</span>'
];

// Платежные системы
$systems = [
    1 => 'CryptoBot',
    2 => 'Банковская карта',
    3 => 'Qiwi',
    4 => 'ЮMoney'
];

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        error_log("POST request received: " . print_r($_POST, true));
        $conn = Security::db_connect();
        
        // Изменение статуса выплаты
        if (isset($_POST['action']) && in_array($_POST['action'], ['approve', 'reject', 'cancel'])) {
            $id = (int)$_POST['id'];
			$user_id = (int)$_POST['user_id'];
            $new_status = $_POST['action'] === 'approve' ? 1 : 2;
            error_log("Changing withdrawal status. ID: $id, New status: $new_status");
            
			if($_POST['action'] == "reject"){
               $stmt = $conn->prepare("UPDATE withdrawals SET status = ? WHERE id = ?");
               $stmt->bind_param("ii", $new_status, $id);
               $stmt->execute();
			}
			
			if($_POST['action'] == "cancel"){
			   $amounts = $_POST['amounts'];
               $stmt = $conn->prepare("UPDATE withdrawals SET status = ? WHERE id = ?");
			   $new_status = 2;
               $stmt->bind_param("ii", $new_status, $id);
               $stmt->execute();		
               $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
               $stmt->bind_param("ii", $amounts, $user_id);
               $stmt->execute();					   
			}
            
            $action = $_POST['action'] === 'approve' ? 'одобрена' : 'отклонена';
if ($_POST['action'] === "approve") {
    $id = intval($_POST['id']);
    $apiToken = '446713:AAL00syFxp0zuBmMLyS0JrCRAd69brLjhNw';
    $apiUrl = 'https://pay.crypt.bot/api/transfer';

    $currency = 'USDT';
    $amount = $_POST['amount_in_usdt'];
    $userId = $_POST['tg_id'];
    $spendId = uniqid('transfer_', true);		

    $postData = [
        'asset' => $currency,
        'amount' => $amount,
        'user_id' => $userId,
        'spend_id' => $spendId			   
    ];	

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Crypto-Pay-API-Token: ' . $apiToken,
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        die('Ошибка cURL: ' . curl_error($ch));
    }
    curl_close($ch);

    $result = json_decode($response, true);		
    $text = var_export($result, true);

    if (!$result['ok']) {
        $_SESSION['error_message'] = "Ошибка выплаты $text";
    } else {
        $_SESSION['success_message'] = "Выплата успешно совершена!";
        $stmt = $conn->prepare("UPDATE withdrawals SET unique_id = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sii", $spendId, 1, $id); // <- исправил типы
        $stmt->execute();
    }

    error_log("Withdrawal status changed successfully. ID: $id, spendId: $spendId");
    header("Location: withdrawals.php");
    exit;
}

            error_log("Withdrawal status changed successfully");
            header("Location: withdrawals.php");
            exit;
        }
        
    } catch (Exception $e) {
        error_log("ERROR in POST processing: " . $e->getMessage());
        $_SESSION['error_message'] = "Ошибка: " . $e->getMessage();
        header("Location: withdrawals.php");
        exit;
    }
}

// Логирование GET параметров
error_log("GET parameters: " . print_r($_GET, true));

// Получение списка выплат
try {
    $conn = Security::db_connect();

    // ===== Фильтры =====
    $status_filter = isset($_GET['status']) && is_numeric($_GET['status'])
                   ? (int)$_GET['status'] : null;
    $user_filter = isset($_GET['user_id']) && is_numeric($_GET['user_id'])
                 ? (int)$_GET['user_id'] : null;

    error_log("Filters - status: " . var_export($status_filter, true) .
              ", user_id: " . var_export($user_filter, true));

    // ===== Пагинация =====
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = 50;
    $offset = ($page - 1) * $per_page;
    error_log("Pagination - page: $page, per_page: $per_page, offset: $offset");

    // ===== WHERE условия =====
    $where = [];
    $params = [];
    $types = '';

    if ($status_filter !== null) {
        $where[] = "w.status = ?";
        $params[] = $status_filter;
        $types .= 'i';
    }

    if ($user_filter !== null) {
        $where[] = "w.user_id = ?";
        $params[] = $user_filter;
        $types .= 'i';
    }

    // Отдельные параметры для COUNT-запроса
    $count_params = $params;
    $count_types = $types;

    // ===== Основной SQL =====
$sql = "SELECT w.*, u.name as user_name, u.user_id as tg_id
        FROM withdrawals w
        LEFT JOIN users u ON w.user_id = u.id";

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY w.id DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    $types .= 'ii';

    error_log("Main SQL query: $sql");
    error_log("Query params: " . print_r($params, true));
    error_log("Param types: $types");

    // Выполняем основной запрос
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $withdrawals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    error_log("Found withdrawals: " . count($withdrawals));

    // ===== COUNT-запрос =====
    $count_sql = "SELECT COUNT(*) as total FROM withdrawals w";
    if (!empty($where)) {
        $count_sql .= " WHERE " . implode(" AND ", $where);
    }

    error_log("Count SQL query: $count_sql");

    $count_stmt = $conn->prepare($count_sql);
    if (!$count_stmt) {
        throw new Exception("Count prepare failed: " . $conn->error);
    }

    if (!empty($where)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }

    $count_stmt->execute();
    $total_withdrawals = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();

    $total_pages = max(1, ceil($total_withdrawals / $per_page));
    error_log("Total withdrawals: $total_withdrawals, total pages: $total_pages");

} catch (Exception $e) {
    error_log("ERROR in withdrawals processing: " . $e->getMessage());
    $_SESSION['error_message'] = "Ошибка при загрузке выплат";
    $withdrawals = [];
    $total_pages = 1;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | Выплаты</title>
    <link href="bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="fa.css">
    <style>
        body {
            padding-top: 56px;
            background-color: #f8f9fa;
        }
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow-x: auto;
            margin-bottom: 20px;
        }
        .withdrawals-table {
            min-width: 100%;
            width: auto;
            margin-bottom: 0;
        }
        .withdrawals-table th {
            background-color: #f8f9fa;
            position: sticky;
            white-space: nowrap;
            vertical-align: middle;
        }
        .withdrawals-table td {
            vertical-align: middle;
            white-space: nowrap;
        }
        .withdrawals-table tr:first-child td {
            border-top: none;
        }
        .action-btn {
            white-space: nowrap;
            margin: 2px;
        }
        .no-results {
            padding: 20px;
            text-align: center;
            color: #6c757d;
        }
        .modal-content {
            max-height: 80vh;
            overflow-y: auto;
        }
        .status-0 { color: #ffc107; } /* Ожидание */
        .status-1 { color: #28a745; } /* Выплачено */
        .status-2 { color: #dc3545; } /* Отклонено */
        .filter-box {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-dice"></i> Welp Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="fas fa-chart-bar"></i> Статистика</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php"><i class="fas fa-users"></i> Пользователи</a>
                    </li>
										<li class="nav-item"><a class="nav-link" href="deposits.php"><i class="fas fa-money-bill"></i> Депозиты</a></li>

                    <li class="nav-item">
                        <a class="nav-link active" href="withdrawals.php"><i class="fas fa-money-bill-wave"></i> Выплаты</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="promocodes.php"><i class="fas fa-tags"></i> Промокоды</a>
                    </li>					
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i> Настройки</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Выход</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-money-bill-wave"></i> Управление выплатами</h2>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
<!-- Фильтры -->
<div class="filter-box mb-4">
    <form method="GET" class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Статус</label>
            <select name="status" class="form-select">
                <option value="">Все статусы</option>
                <?php foreach ($statuses as $value => $name): ?>
                    <option value="<?= $value ?>" <?= isset($_GET['status']) && (string)$_GET['status'] === (string)$value ? 'selected' : '' ?>>
                        <?= $name ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">ID пользователя</label>
            <input type="number" name="user_id" class="form-control" 
                   value="<?= isset($_GET['user_id']) ? htmlspecialchars($_GET['user_id']) : '' ?>" 
                   placeholder="ID пользователя">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-filter"></i> Фильтровать
            </button>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <a href="withdrawals.php" class="btn btn-outline-secondary w-100">
                <i class="fas fa-times"></i> Сбросить
            </a>
        </div>
    </form>
</div>
        
        <!-- Таблица выплат -->
        <div class="table-container">
            <table class="withdrawals-table table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Пользователь</th>
                        <th>Сумма</th>
                        <th>Сумма (USDT)</th>
                        <th>Система</th>
                        <th>Статус</th>
                        <th>Чек</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($withdrawals)): ?>
                        <?php foreach ($withdrawals as $withdrawal): ?>
                            <tr>
                                <td><?= htmlspecialchars($withdrawal['id']) ?></td>
                                <td>
                                    <?= htmlspecialchars($withdrawal['user_name'] ?? $withdrawal['user_id']) ?>
                                    <small class="text-muted">(ID: <?= $withdrawal['user_id'] ?>)</small>
                                </td>
                                <td>$<?= number_format($withdrawal['amount'] / 100, 2) ?></td>
                                <td>$<?= number_format($withdrawal['amount_in_usdt'], 2) ?></td>
                                <td><?= $systems[$withdrawal['system']] ?? 'Неизвестно' ?></td>
                                <td class="status-<?= $withdrawal['status'] ?>">
                                    <?= $statuses[$withdrawal['status']] ?? 'Неизвестно' ?>
                                </td>
                                <td>
                                    <?php if (!empty($withdrawal['check_link'])): ?>
                                        <a href="<?= htmlspecialchars($withdrawal['check_link']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-external-link-alt"></i> Посмотреть
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Нет</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($withdrawal['created_at']) ?></td>
                                <td>
                                    <?php if ($withdrawal['status'] == 0): ?>
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="id" value="<?= $withdrawal['id'] ?>">
											<input type="hidden" name="amount_in_usdt" value="<?= $withdrawal['amount_in_usdt'] ?>">
											<input type="hidden" name="tg_id" value="<?= $withdrawal['tg_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success action-btn">
                                                <i class="fas fa-check"></i> Выплатить
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="id" value="<?= $withdrawal['id'] ?>">
											<input type="hidden" name="user_id" value="<?= $withdrawal['user_id'] ?>">
											<input type="hidden" name="amounts" value="<?= $withdrawal['amount'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger action-btn" onclick="return confirm('Отклонить эту выплату?')">
                                                <i class="fas fa-times"></i> Отклонить
                                            </button>
                                        </form>										
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="id" value="<?= $withdrawal['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger action-btn" onclick="return confirm('Отказать в этой выплате?')">
                                                <i class="fas fa-times"></i> Отказать
                                            </button>
                                        </form>
										
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="no-results">
                                Выплаты не найдены
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
<!-- Пагинация -->
<?php if ($total_pages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>
                        <?= isset($_GET['status']) && $_GET['status'] !== '' ? '&status='.htmlspecialchars($_GET['status']) : '' ?>
                        <?= isset($_GET['user_id']) && $_GET['user_id'] !== '' ? '&user_id='.htmlspecialchars($_GET['user_id']) : '' ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>
    </div>

    <!-- Модальное окно для добавления/изменения чека -->
    <div class="modal fade" id="checkModal" tabindex="-1" aria-labelledby="checkModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="checkModalLabel">Ссылка на чек</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_check_link">
                    <input type="hidden" name="id" id="checkWithdrawalId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Ссылка на подтверждение выплаты</label>
                            <input type="url" class="form-control" name="check_link" id="checkLinkInput" placeholder="https://example.com/check.jpg">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="bootstrap.bundle.min.js"></script>
    <script>
        // Обработка кнопок обновления чека
        document.querySelectorAll('.update-check-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('checkWithdrawalId').value = this.getAttribute('data-id');
                document.getElementById('checkLinkInput').value = this.getAttribute('data-check-link');
            });
        });

        // Автоматическое скрытие alert через 5 секунд
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                new bootstrap.Alert(alert).close();
            });
        }, 5000);
    </script>
</body>
</html>