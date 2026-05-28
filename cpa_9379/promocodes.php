<?php
require_once 'config.php';
require_once 'security.php';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = Security::db_connect();
        
        // Создание нового промокода
        if (isset($_POST['action']) && $_POST['action'] === 'create_promo') {
            $promo = trim($_POST['promo']);
            $amount = (int)$_POST['amount'];
            $activates = (int)$_POST['activates'];
            
            if (empty($promo)) {
                throw new Exception("Промокод не может быть пустым");
            }
            
            $stmt = $conn->prepare("INSERT INTO promocodes (promo, amount, activates) VALUES (?, ?, ?)");
            $stmt->bind_param("sii", $promo, $amount, $activates);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Промокод успешно создан!";
            header("Location: promocodes.php");
            exit;
        }
        
        // Удаление промокода
        if (isset($_POST['action']) && $_POST['action'] === 'delete_promo') {
            $id = (int)$_POST['id'];
            
            $stmt = $conn->prepare("DELETE FROM promocodes WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Промокод успешно удален!";
            header("Location: promocodes.php");
            exit;
        }
        
        // Редактирование промокода
        if (isset($_POST['action']) && $_POST['action'] === 'update_promo') {
            $id = (int)$_POST['id'];
            $promo = trim($_POST['promo']);
            $amount = (int)$_POST['amount'];
            $activates = (int)$_POST['activates'];
            
            $stmt = $conn->prepare("UPDATE promocodes SET promo = ?, amount = ?, activates = ? WHERE id = ?");
            $stmt->bind_param("siii", $promo, $amount, $activates, $id);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Промокод успешно обновлен!";
            header("Location: promocodes.php");
            exit;
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Ошибка: " . $e->getMessage();
        header("Location: promocodes.php");
        exit;
    }
}

// Получение списка промокодов
try {
    $conn = Security::db_connect();
    
    // Пагинация
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = 50;
    $offset = ($page - 1) * $per_page;
    
    // Получение промокодов
    $stmt = $conn->prepare("SELECT * FROM promocodes ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $per_page, $offset);
    $stmt->execute();
    $promocodes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Получение общего количества
    $total_stmt = $conn->query("SELECT COUNT(*) as total FROM promocodes");
    $total_promocodes = $total_stmt->fetch_assoc()['total'];
    $total_stmt->close();
    $total_pages = max(1, ceil($total_promocodes / $per_page));
    
} catch (Exception $e) {
    error_log("Promocodes error: " . $e->getMessage());
    $_SESSION['error_message'] = "Ошибка: " . $e->getMessage();
    $promocodes = [];
    $total_pages = 1;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | Промокоды</title>
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
        .promo-table {
            min-width: 100%;
            width: auto;
            margin-bottom: 0;
        }
        .promo-table th {
            background-color: #f8f9fa;
            position: sticky;
            white-space: nowrap;
            vertical-align: middle;
        }
        .promo-table td {
            vertical-align: middle;
            white-space: nowrap;
        }
        .promo-table tr:first-child td {
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
					
					<li class="nav-item"><a class="nav-link" href="withdrawals.php"><i class="fas fa-money-bill"></i> Выплаты</a></li>
                    <li class="nav-item">
                        <a class="nav-link active" href="promocodes.php"><i class="fas fa-tags"></i> Промокоды</a>
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
        <h2 class="mb-4"><i class="fas fa-tags"></i> Управление промокодами</h2>
        
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
        
        <!-- Форма создания нового промокода -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Создать новый промокод</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create_promo">
                    <div class="row g-3">
<div class="col-md-4">
    <label class="form-label">Промокод</label>
    <div class="input-group">
        <input type="text" class="form-control" name="promo" id="promoInput" required>
        <button class="btn btn-primary" type="button" id="generatePromoBtn">
            <i class="fas fa-refresh"></i>
        </button>
    </div>
</div>

                        <div class="col-md-3">
                            <label class="form-label">Сумма (в центах)</label>
                            <input type="number" class="form-control" name="amount" min="0" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Макс. активаций</label>
                            <input type="number" class="form-control" name="activates" min="1" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-plus"></i> Создать
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Таблица промокодов -->
        <div class="table-container">
            <table class="promo-table table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Промокод</th>
                        <th>Сумма</th>
                        <th>Активаций</th>
                        <th>Использовано</th>
                        <th>Дата создания</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($promocodes)): ?>
                        <?php foreach ($promocodes as $promo): ?>
                            <tr>
                                <td><?= htmlspecialchars($promo['id']) ?></td>
                                <td><?= htmlspecialchars($promo['promo']) ?></td>
                                <td>$<?= number_format($promo['amount'] / 100, 2) ?></td>
                                <td><?= htmlspecialchars($promo['activates']) ?></td>
                                <td><?= htmlspecialchars($promo['activated']) ?></td>
                                <td><?= htmlspecialchars($promo['created_at']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary action-btn edit-promo-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal"
                                            data-id="<?= $promo['id'] ?>"
                                            data-promo="<?= htmlspecialchars($promo['promo']) ?>"
                                            data-amount="<?= $promo['amount'] ?>"
                                            data-activates="<?= $promo['activates'] ?>">
                                        <i class="fas fa-edit"></i> Изменить
                                    </button>
                                    <form method="POST" style="display: inline-block;">
                                        <input type="hidden" name="action" value="delete_promo">
                                        <input type="hidden" name="id" value="<?= $promo['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger action-btn" onclick="return confirm('Удалить этот промокод?')">
                                            <i class="fas fa-trash"></i> Удалить
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-results">
                                Промокоды не найдены
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
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- Модальное окно редактирования -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Редактирование промокода</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_promo">
                    <input type="hidden" name="id" id="editPromoId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Промокод</label>
                            <input type="text" class="form-control" name="promo" id="editPromoCode" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Сумма (в центах)</label>
                            <input type="number" class="form-control" name="amount" id="editPromoAmount" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Макс. активаций</label>
                            <input type="number" class="form-control" name="activates" id="editPromoActivates" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="bootstrap.bundle.min.js"></script>
<script>
document.getElementById('generatePromoBtn').addEventListener('click', function () {
    const length = 10; // длина промокода
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let promo = '';
    for (let i = 0; i < length; i++) {
        promo += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('promoInput').value = promo;
});
</script>
	
    <script>
        // Обработка кнопок редактирования
        document.querySelectorAll('.edit-promo-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('editPromoId').value = this.getAttribute('data-id');
                document.getElementById('editPromoCode').value = this.getAttribute('data-promo');
                document.getElementById('editPromoAmount').value = this.getAttribute('data-amount');
                document.getElementById('editPromoActivates').value = this.getAttribute('data-activates');
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