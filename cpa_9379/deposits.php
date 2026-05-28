<?php
require_once 'config.php';
require_once 'security.php';

// Настройка логирования
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/admin_errors.log');
error_log("========== START deposits.php ==========");

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    error_log("Redirect to login: admin not logged in");
    header('Location: login.php');
    exit;
}

// Обработка поиска
$search_query = $_GET['search'] ?? '';
$search_mode = !empty($search_query);

// Получение списка депозитов
try {
    $conn = Security::db_connect();

    // Пагинация
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = 50;
    $offset = ($page - 1) * $per_page;

    if ($search_mode) {
        // Поиск по ID или invoice_id
        $stmt = $conn->prepare("
            SELECT d.*, u.name as user_name, u.user_id as tg_id 
            FROM deposits d
            LEFT JOIN users u ON d.user_id = u.id
            WHERE d.id = ? OR d.invoice_id LIKE ?
            ORDER BY d.id DESC LIMIT ? OFFSET ?
        ");
        $search_param_id = is_numeric($search_query) ? (int)$search_query : 0;
        $search_param_invoice = "%$search_query%";
        $stmt->bind_param("issi", $search_param_id, $search_param_invoice, $per_page, $offset);
    } else {
        // Обычный запрос без поиска
        $stmt = $conn->prepare("
            SELECT d.*, u.name as user_name, u.user_id as tg_id 
            FROM deposits d
            LEFT JOIN users u ON d.user_id = u.id
            ORDER BY d.id DESC LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("ii", $per_page, $offset);
    }

    $stmt->execute();
    $deposits = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Получение общего количества
    if ($search_mode) {
        $total_deposits = count($deposits);
    } else {
        $total_stmt = $conn->query("SELECT COUNT(*) as total FROM deposits");
        $total_deposits = $total_stmt->fetch_assoc()['total'];
        $total_stmt->close();
    }

    $total_pages = max(1, ceil($total_deposits / $per_page));

} catch (Exception $e) {
    error_log("ERROR in deposits processing: " . $e->getMessage());
    $_SESSION['error_message'] = "Ошибка при загрузке депозитов";
    $deposits = [];
    $total_pages = 1;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | Депозиты</title>
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
        .deposits-table {
            min-width: 100%;
            width: auto;
            margin-bottom: 0;
        }
        .deposits-table th {
            background-color: #f8f9fa;
            position: sticky;
            white-space: nowrap;
            vertical-align: middle;
        }
        .deposits-table td {
            vertical-align: middle;
            white-space: nowrap;
        }
        .deposits-table tr:first-child td {
            border-top: none;
        }
        .no-results {
            padding: 20px;
            text-align: center;
            color: #6c757d;
        }
        .search-box {
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
					<li class="nav-item"><a class="nav-link active" href="deposits.php"><i class="fas fa-money-bill"></i> Депозиты</a></li>
                    <li class="nav-item">
                        <a class="nav-link" href="withdrawals.php"><i class="fas fa-money-bill-wave"></i> Выплаты</a>
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
        <h2 class="mb-4"><i class="fas fa-money-bill-alt"></i> История депозитов</h2>
        
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
        
        <!-- Поиск -->
        <div class="search-box">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="ID или invoice_id" value="<?= htmlspecialchars($search_query) ?>">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Найти</button>
                        <?php if ($search_mode): ?>
                            <a href="deposits.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i> Сбросить</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Таблица депозитов -->
        <div class="table-container">
            <table class="deposits-table table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Пользователь</th>
                        <th>Invoice ID</th>
                        <th>Сумма</th>
                        <th>Дата</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($deposits)): ?>
                        <?php foreach ($deposits as $deposit): ?>
                            <tr>
                                <td><?= htmlspecialchars($deposit['id']) ?></td>
                                <td>
                                    <?= htmlspecialchars($deposit['user_name'] ?? $deposit['user_id']) ?>
                                    <small class="text-muted">(ID: <?= $deposit['user_id'] ?>)</small>
                                </td>
                                <td><?= htmlspecialchars($deposit['invoice_id']) ?></td>
                                <td>$<?= number_format($deposit['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($deposit['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="no-results">
                                Депозиты не найдены
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Пагинация -->
        <?php if (!$search_mode && $total_pages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <script src="bootstrap.bundle.min.js"></script>
    <script>
        // Автоматическое скрытие alert через 5 секунд
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (typeof bootstrap !== 'undefined') {
                    new bootstrap.Alert(alert).close();
                } else {
                    alert.style.display = 'none';
                }
            });
        }, 5000);
    </script>
</body>
</html>