<?php
require_once 'config.php';
require_once 'security.php';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Установка временной зоны
date_default_timezone_set('Europe/Moscow');

try {
    $conn = Security::db_connect();
    
    // Определение периода
    $period = $_GET['period'] ?? 'today';
    $current_time = new DateTime('now', new DateTimeZone('Europe/Moscow'));
    
    switch ($period) {
        case 'week':
            $start_date = $current_time->modify('Monday this week')->format('Y-m-d 00:00:00');
            $end_date = $current_time->modify('Sunday this week')->format('Y-m-d 23:59:59');
            $title = "Неделя (" . date('d.m.Y', strtotime($start_date)) . " - " . date('d.m.Y', strtotime($end_date)) . ")";
            break;
        case 'month':
            $start_date = $current_time->format('Y-m-01 00:00:00');
            $end_date = $current_time->format('Y-m-t 23:59:59');
            $title = "Месяц (" . date('m.Y', strtotime($start_date)) . ")";
            break;
        case 'year':
            $start_date = $current_time->format('Y-01-01 00:00:00');
            $end_date = $current_time->format('Y-12-31 23:59:59');
            $title = "Год (" . date('Y', strtotime($start_date)) . ")";
            break;
        case 'all':
            $start_date = '1970-01-01 00:00:00';
            $end_date = $current_time->format('Y-m-d H:i:s');
            $title = "За все время";
            break;
        default: // today
            $start_date = $current_time->format('Y-m-d 00:00:00');
            $end_date = $current_time->format('Y-m-d H:i:s');
            $title = "Сегодня (" . date('d.m.Y', strtotime($start_date)) . ")";
    }

    // Инициализация статистики
    $stats = [
        'cube' => [
            'total_bets' => 0,
            'total_wins' => 0,
            'house_profit' => 0,
            'games_count' => 0
        ],
        'new_registrations' => 0,
        'deposits_amount' => 0,
        'withdrawals_amount' => 0,
        'pending_withdrawals' => [
            'count' => 0,
            'amount' => 0
        ],
        'period_title' => $title
    ];

    // Статистика по Cube играм
    $stmt = $conn->prepare("SELECT bet_amount, win_amount FROM cube_games WHERE created_at BETWEEN ? AND ?");
    if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $stats['cube']['total_bets'] += (float)$row['bet_amount'];
            
            if ($row['win_amount'] > 0) {
                $stats['cube']['total_wins'] += (float)$row['win_amount'] + (float)$row['bet_amount'];
            }
            
            $stats['cube']['games_count']++;
        }
        $stmt->close();
        
        // Конвертация в доллары
        $stats['cube']['total_bets'] /= 100;
        $stats['cube']['total_wins'] /= 100;
        $stats['cube']['house_profit'] = $stats['cube']['total_bets'] - $stats['cube']['total_wins'];
    }
	
    // Статистика по Mines играм
    $stmt = $conn->prepare("SELECT bet, result FROM minesBets WHERE created_at BETWEEN ? AND ?");
    if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $stats['mines']['total_bets'] += (float)$row['bet'];
            
            if ($row['result'] > 0) {
                $stats['mines']['total_wins'] += (float)$row['result'];
            }
            
            $stats['mines']['games_count']++;
        }
        $stmt->close();
        
        // Конвертация в доллары
        $stats['mines']['total_bets'] /= 100;
        $stats['mines']['total_wins'] /= 100;
        $stats['mines']['house_profit'] = $stats['mines']['total_bets'] - $stats['mines']['total_wins'];
    }	
	
    // Статистика по X50 играм
$stmt = $conn->prepare("
    SELECT bet, result 
    FROM x50_bets 
    WHERE created_at BETWEEN ? AND ? 
      AND bet > 0
");
if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $stats['x50']['total_bets'] += (float)$row['bet'];
            
            if ($row['result'] > 0) {
                $stats['x50']['total_wins'] += (float)$row['result'] - (float)$row['bet'] * $row['coeff'];
            }
            
            $stats['x50']['games_count']++;
        }
        $stmt->close();
        
        // Конвертация в доллары
        $stats['x50']['total_bets'] /= 100;
        $stats['x50']['total_wins'] /= 100;
        $stats['x50']['house_profit'] = $stats['x50']['total_bets'] - $stats['x50']['total_wins'];
    }		
	
	// Статистика все игры
	$stats['all']['games_count'] = $stats['cube']['games_count'] + $stats['mines']['games_count'] + $stats['x50']['games_count'];
	$stats['all']['total_bets'] = $stats['cube']['total_bets'] + $stats['mines']['total_bets'] + $stats['x50']['total_bets'];
	$stats['all']['total_wins'] = $stats['cube']['total_wins'] + $stats['mines']['total_wins'] + $stats['x50']['total_wins'];
	$stats['all']['house_profit'] = $stats['cube']['house_profit'] + $stats['mines']['house_profit'] + $stats['x50']['house_profit'];

    // 2. Новые регистрации
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE created_at BETWEEN ? AND ?");
    if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stats['new_registrations'] = $row['count'];
        }
        $stmt->close();
    }

    // 3. Депозиты
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM deposits WHERE created_at BETWEEN ? AND ?");
    if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stats['deposits_amount'] = $row['total'] ? $row['total'] / 100 : 0;
        }
        $stmt->close();
    }

    // 4. Выплаты (статус 0 или 1)
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM withdrawals WHERE status IN (0, 1) AND created_at BETWEEN ? AND ?");
    if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stats['withdrawals_amount'] = $row['total'] ? $row['total'] / 100 : 0;
        }
        $stmt->close();
    }

    // 5. Выплаты в обработке (статус 0)
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM withdrawals WHERE status = 0 AND created_at BETWEEN ? AND ?");
    if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stats['pending_withdrawals']['count'] = $row['count'];
            $stats['pending_withdrawals']['amount'] = $row['total'] ? $row['total'] / 100 : 0;
        }
        $stmt->close();
    }
    
    $stats['deposits_profit']['amount'] = $stats['deposits_amount'] - $stats['withdrawals_amount'];

} catch (Exception $e) {
    error_log("Admin stats error: " . $e->getMessage());
    die("Произошла ошибка при загрузке статистики. Пожалуйста, попробуйте позже.");
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | Статистика</title>
    <link href="bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="fa.css">
    <style>
        .stat-card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .profit-positive { color: #28a745; }
        .profit-negative { color: #dc3545; }
        body { background-color: #f8f9fa; }
        .section-title { margin: 20px 0 15px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
        .card-title { font-size: 1rem; }
        .card-value { font-size: 1.5rem; font-weight: bold; }
        .period-btn.active { background-color: #0d6efd; color: white; }
        .period-nav { margin-bottom: 20px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-dice"></i> Welp Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link active" href="index.php"><i class="fas fa-chart-bar"></i> Статистика</a></li>
                    <li class="nav-item"><a class="nav-link" href="users.php"><i class="fas fa-users"></i> Пользователи</a></li>
										<li class="nav-item"><a class="nav-link" href="deposits.php"><i class="fas fa-money-bill"></i> Депозиты</a></li>

					<li class="nav-item"><a class="nav-link" href="withdrawals.php"><i class="fas fa-money-bill"></i> Выплаты</a></li>
					<li class="nav-item"><a class="nav-link" href="promocodes.php"><i class="fas fa-gift"></i> Промокоды</a></li>
                    <li class="nav-item"><a class="nav-link" href="settings.php"><i class="fas fa-cog"></i> Настройки</a></li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Выход</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="period-nav">
            <div class="btn-group d-flex" role="group">
                <a href="?period=today" class="btn btn-outline-primary <?= ($period === 'today') ? 'active' : '' ?>">Сегодня</a>
                <a href="?period=week" class="btn btn-outline-primary <?= ($period === 'week') ? 'active' : '' ?>">Неделя</a>
                <a href="?period=month" class="btn btn-outline-primary <?= ($period === 'month') ? 'active' : '' ?>">Месяц</a>
                <a href="?period=year" class="btn btn-outline-primary <?= ($period === 'year') ? 'active' : '' ?>">Год</a>
                <a href="?period=all" class="btn btn-outline-primary <?= ($period === 'all') ? 'active' : '' ?>">Все время</a>
            </div>
        </div>
        
        <h3 class="section-title">Статистика за <?= $stats['period_title'] ?></h3>
   
        <h4 class="mt-4 mb-3">Все игры</h4>
        <div class="row mb-4">
            <div class="col-md-3 mb-3">    
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-gamepad"></i> Всего игр</h5>
                        <div class="card-value"><?= $stats['all']['games_count'] ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-coins"></i> Общие ставки</h5>
                        <div class="card-value">$<?= number_format($stats['all']['total_bets'], 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-hand-holding-usd"></i> Выигрыши</h5>
                        <div class="card-value">$<?= number_format($stats['all']['total_wins'], 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-chart-line"></i> Профит</h5>
                        <div class="card-value <?= $stats['all']['house_profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                            <?= $stats['all']['house_profit'] >= 0 ? '$' : '-$' ?><?= number_format(abs($stats['all']['house_profit']), 2) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
   
        <h4 class="mt-4 mb-3">Cube игры</h4>
        <div class="row mb-4">
            <div class="col-md-3 mb-3">    
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-gamepad"></i> Всего игр</h5>
                        <div class="card-value"><?= $stats['cube']['games_count'] ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-coins"></i> Общие ставки</h5>
                        <div class="card-value">$<?= number_format($stats['cube']['total_bets'], 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-hand-holding-usd"></i> Выигрыши</h5>
                        <div class="card-value">$<?= number_format($stats['cube']['total_wins'], 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-chart-line"></i> Профит</h5>
                        <div class="card-value <?= $stats['cube']['house_profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                            <?= $stats['cube']['house_profit'] >= 0 ? '$' : '-$' ?><?= number_format(abs($stats['cube']['house_profit']), 2) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
		
        <h4 class="mt-4 mb-3">Mines игры</h4>
        <div class="row mb-4">
            <div class="col-md-3 mb-3">    
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-gamepad"></i> Всего игр</h5>
                        <div class="card-value"><?= $stats['mines']['games_count'] ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-coins"></i> Общие ставки</h5>
                        <div class="card-value">$<?= number_format($stats['mines']['total_bets'], 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-hand-holding-usd"></i> Выигрыши</h5>
                        <div class="card-value">$<?= number_format($stats['mines']['total_wins'], 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-chart-line"></i> Профит</h5>
                        <div class="card-value <?= $stats['mines']['house_profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                            <?= $stats['mines']['house_profit'] >= 0 ? '$' : '-$' ?><?= number_format(abs($stats['mines']['house_profit']), 2) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>	

        <h4 class="mt-4 mb-3">X50 игры</h4>
        <div class="row mb-4">
            <div class="col-md-3 mb-3">    
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-gamepad"></i> Всего игр</h5>
                        <div class="card-value"><?= $stats['x50']['games_count'] ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-coins"></i> Общие ставки</h5>
                        <div class="card-value">$<?= number_format($stats['x50']['total_bets'], 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-hand-holding-usd"></i> Выигрыши</h5>
                        <div class="card-value">$<?= number_format($stats['x50']['total_wins'], 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-chart-line"></i> Профит</h5>
                        <div class="card-value <?= $stats['x50']['house_profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                            <?= $stats['x50']['house_profit'] >= 0 ? '$' : '-$' ?><?= number_format(abs($stats['x50']['house_profit']), 2) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>				

        <h4 class="mt-4 mb-3"><i class="fas fa-users"></i> Пользователи и финансы</h4>
        <div class="row mb-4">
            <div class="col-md mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-user-plus"></i> Новые регистрации</h5>
                        <div class="card-value"><?= $stats['new_registrations'] ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-money-bill-wave"></i> Депозиты</h5>
                        <div class="card-value">$<?= number_format($stats['deposits_amount'], 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-wallet"></i> Выплаты</h5>
                        <div class="card-value">$<?= number_format($stats['withdrawals_amount'], 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-clock"></i> Выплат в обработке</h5>
                        <div class="card-value">
                            <?= $stats['pending_withdrawals']['count'] ?><br>
                            <small>$<?= number_format($stats['pending_withdrawals']['amount'], 2) ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title text-muted"><i class="fas fa-calculator"></i> Профит депы/выводы</h5>
                        <div class="card-value <?= $stats['deposits_profit']['amount'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                            <?= $stats['deposits_profit']['amount'] >= 0 ? '$' : '-$' ?><?= number_format(abs($stats['deposits_profit']['amount']), 2) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="bootstrap.bundle.min.js"></script>
</body>
</html>