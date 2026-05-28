<?php
require_once 'config.php';
require_once 'security.php';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

try {
    $conn = Security::db_connect();
    
    // Получаем текущие настройки
    $stmt = $conn->prepare("SELECT * FROM settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Обработка формы
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Подготовка данных для обновления
        $update_fields = [];
        $update_values = [];
        $types = '';
        
        foreach ($_POST as $key => $value) {
            if (array_key_exists($key, $settings)) {
                $update_fields[] = "$key = ?";
                $update_values[] = $value;
                $types .= is_int($settings[$key]) ? 'i' : 's';
            }
        }
        
        if (!empty($update_fields)) {
            $sql = "UPDATE settings SET " . implode(', ', $update_fields) . " WHERE id = 1";
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param($types, ...$update_values);
                $stmt->execute();
                
                if ($stmt->affected_rows > 0) {
                    $_SESSION['success_message'] = "Настройки успешно обновлены!";
                    // Обновляем локальные данные
                    $stmt = $conn->prepare("SELECT * FROM settings WHERE id = 1");
                    $stmt->execute();
                    $settings = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Settings error: " . $e->getMessage());
    $_SESSION['error_message'] = "Произошла ошибка при загрузке настроек.";
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | Настройки</title>
    <link href="bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="fa.css">
    <style>
        .setting-card {
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            padding: 20px;
            background: white;
        }
        .setting-group {
            margin-bottom: 30px;
        }
        .setting-title {
            font-size: 1.2rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .form-label {
            font-weight: 500;
        }
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
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-chart-bar"></i> Статистика</a></li>
                    <li class="nav-item"><a class="nav-link" href="users.php"><i class="fas fa-users"></i> Пользователи</a></li>
										<li class="nav-item"><a class="nav-link" href="deposits.php"><i class="fas fa-money-bill"></i> Депозиты</a></li>

					<li class="nav-item"><a class="nav-link" href="withdrawals.php"><i class="fas fa-money-bill"></i> Выплаты</a></li>
					<li class="nav-item"><a class="nav-link" href="promocodes.php"><i class="fas fa-gift"></i> Промокоды</a></li>
                    <li class="nav-item"><a class="nav-link active" href="settings.php"><i class="fas fa-cog"></i> Настройки</a></li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Выход</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <h2 class="mb-4"><i class="fas fa-cog"></i> Настройки системы</h2>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <form method="POST">
            <div class="setting-card">
                <h3 class="setting-title"><i class="fas fa-globe"></i> Основные настройки</h3>
                
                <div class="row">
                    <?php foreach ($settings as $key => $value): ?>
                        <?php if (!in_array($key, ['id', 'created_at', 'updated_at'])): ?>
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label class="form-label"><?= ucfirst(str_replace('_', ' ', $key)) ?></label>
                                    <?php if (is_numeric($value) && strpos($key, 'percent') === false): ?>
                                        <input type="number" class="form-control" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                                    <?php elseif (strpos($key, 'is_') === 0): ?>
                                        <select class="form-select" name="<?= $key ?>">
                                            <option value="1" <?= $value ? 'selected' : '' ?>>Включено</option>
                                            <option value="0" <?= !$value ? 'selected' : '' ?>>Выключено</option>
                                        </select>
                                    <?php else: ?>
                                        <input type="text" class="form-control" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
<div class="setting-card">
    <h3 class="setting-title"><i class="fas fa-piggy-bank"></i> Банки игр</h3>
    <div id="banksContainer">
        <p>Загрузка данных банков...</p>
    </div>
    <div class="mt-3 text-center">
        <button class="btn btn-success" id="saveBanksBtn">
            <i class="fas fa-save"></i> Сохранить банки
        </button>
    </div>
</div>
			
            
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Сохранить настройки
                </button>
            </div>
        </form>
    </div>

    <script src="bootstrap.bundle.min.js"></script>
	<script src="jq.js"></script>
	<script src="socket.io.js"></script>	
	<script>
var URL_SERVER = 'https://' + document.domain;
var socket;

$(function() {
	
	var auth_token = 'sdfgdsfgj7ewrjhfvifn34f734hgt8457gh458ghwjdfsb';
socket = io(URL_SERVER, {
    path: '/socket.io/'
});
	
    socket.on('connect', function(msg){	  	
	   socket.emit('hash', auth_token);
	});	
	
    socket.on('ssfgdjgujadfb7n48w5ghaerfufjszldkvjelkw5', function(banks){
        renderBanks(banks);
    });

    socket.on('disconnect', function(){
        alert('Ошибка подключения!');
    });

    socket.on('counter', function(data){
        $(".online").html(data.data);
    });

    // Кнопка сохранения банков
    $('#saveBanksBtn').on('click', function(){
        var updatedBanks = {};
        $('#banksContainer').find('.bank-row').each(function(){
            var game = $(this).data('game');
            updatedBanks[game] = {
                bank: parseFloat($(this).find('.bank-val').val()) || 0,
                minBank: parseFloat($(this).find('.minbank-val').val()) || 0,
                maxBank: parseFloat($(this).find('.maxbank-val').val()) || 0
            };
        });
        socket.emit('fosjwGJw987gh47wefh8W7FHW34F89hwfvujckxzASNDCkwq34fnW49G798djc9QSAIDMNlk', updatedBanks);
        alert('Банки отправлены на сервер!');
    });
});

// Функция рендера банков
function renderBanks(banks) {
    var html = '<table class="table table-bordered table-sm text-center">';
    html += '<thead><tr><th>Игра</th><th>Bank</th><th>MinBank</th><th>MaxBank</th></tr></thead><tbody>';
    for (var game in banks) {
        html += `
            <tr class="bank-row" data-game="${game}">
                <td><strong>${game}</strong></td>
                <td><input type="number" step="0.01" class="form-control bank-val" value="${banks[game].bank}"></td>
                <td><input type="number" step="0.01" class="form-control minbank-val" value="${banks[game].minBank}"></td>
                <td><input type="number" step="0.01" class="form-control maxbank-val" value="${banks[game].maxBank}"></td>
            </tr>
        `;
    }
    html += '</tbody></table>';
    $('#banksContainer').html(html);
}
</script>
    <script>
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