<?php
require_once 'config.php';
require_once 'security.php';
session_start();

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Настройка логирования
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/admin_errors.log');

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $conn = Security::db_connect();
        
        if ($_POST['action'] === 'update_user') {
            $user_id = (int)$_POST['user_id'];
            $update_data = [];

            error_log("UPDATE USER REQUEST: UserID: $user_id, POST data: " . print_r($_POST, true));

            foreach ($_POST as $key => $value) {
                if (!in_array($key, ['action', 'user_id', 'avatar'])) {
                    if ($key === 'balance') {
                        $value = (float)$value;
                    } elseif (in_array($key, ['wager','deposit','ref_earned','ref_available','ban','wban','ref_by','role'])) {
                        $value = (int)$value;
                    }
                    $update_data[$key] = $value;
                }
            }

            $stmt = $conn->prepare("SELECT user_id, balance, wager, deposit, ref_earned, ref_available, ban, wban, ref_by, role, token FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $current_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$current_data) {
                $_SESSION['error_message'] = "Пользователь с ID $user_id не найден";
                header("Location: users.php");
                exit;
            }

            $set_parts = [];
            $types = '';
            $values = [];
            $changes_detected = false;

            foreach ($update_data as $field => $new_value) {
                $current_value = $current_data[$field] ?? null;
                if ($current_value != $new_value) {
                    $changes_detected = true;
                    $set_parts[] = "`$field` = ?";
                    $types .= is_int($new_value) ? 'i' : (is_float($new_value)) ? 'd' : 's';
                    $values[] = $new_value;
                }
            }

            if (!$changes_detected) {
                $_SESSION['error_message'] = "Данные не изменены";
                header("Location: users.php");
                exit;
            }

            $sql = "UPDATE users SET " . implode(', ', $set_parts) . " WHERE user_id = ?";
            $types .= 'i';
            $values[] = $user_id;

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$values);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $_SESSION['success_message'] = "Данные пользователя успешно обновлены!";
            } else {
                $_SESSION['error_message'] = "Ошибка обновления";
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Ошибка: " . $e->getMessage();
    }
    header("Location: users.php");
    exit;
}

// Получение пользователей
try {
    $conn = Security::db_connect();

    $search_id = $_GET['search_id'] ?? '';
    $search_mode = !empty($search_id);

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = 50;
    $offset = ($page - 1) * $per_page;

    if ($search_mode) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id LIKE ? ORDER BY user_id DESC");
        $search_param = "%$search_id%";
        $stmt->bind_param("s", $search_param);
    } else {
        $stmt = $conn->prepare("SELECT * FROM users ORDER BY balance DESC LIMIT ? OFFSET ?");
        $stmt->bind_param("ii", $per_page, $offset);
    }

    $stmt->execute();
    $users = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // Преобразуем числовые поля в правильные типы
        $row['balance'] = (float)$row['balance'];
        $row['wager'] = (int)$row['wager'];
        $row['deposit'] = (float)$row['deposit'];
        $row['ref_earned'] = (float)$row['ref_earned'];
        $row['ref_available'] = (float)$row['ref_available'];
        $row['ban'] = (int)$row['ban'];
        $row['wban'] = (int)$row['wban'];
        $row['ref_by'] = (int)$row['ref_by'];
        $row['role'] = (int)$row['role'];
        $users[] = $row;
    }
    $stmt->close();

    // Исключаем только действительно ненужные столбцы
    $excluded_columns = ['id', 'created_at', 'updated_at', 'password', 'email', 'avatar'];
    $columns = !empty($users) ? array_diff(array_keys($users[0]), $excluded_columns) : [];

    if ($search_mode) {
        $total_users = count($users);
    } else {
        $total_stmt = $conn->query("SELECT COUNT(*) as total FROM users");
        $total_users = $total_stmt->fetch_assoc()['total'];
        $total_stmt->close();
    }
    $total_pages = max(1, ceil($total_users / $per_page));

} catch (Exception $e) {
    $_SESSION['error_message'] = "Ошибка: " . $e->getMessage();
    $users = [];
    $columns = [];
    $total_pages = 1;
}

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | Пользователи</title>
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
        .user-table {
            min-width: 100%;
            width: auto;
            margin-bottom: 0;
        }
        .user-table th {
            background-color: #f8f9fa;
            position: sticky;
            white-space: nowrap;
            vertical-align: middle;
        }
        .user-table td {
            vertical-align: middle;
            white-space: nowrap;
        }
        .user-table tr:first-child td {
            border-top: none;
        }
        .edit-btn {
            white-space: nowrap;
        }
        .search-box {
            margin-bottom: 20px;
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
                        <a class="nav-link active" href="users.php"><i class="fas fa-users"></i> Пользователи</a>
                    </li>
					<li class="nav-item"><a class="nav-link" href="deposits.php"><i class="fas fa-money-bill"></i> Депозиты</a></li>
                    <li class="nav-item"><a class="nav-link" href="withdrawals.php"><i class="fas fa-money-bill"></i> Выплаты</a></li>
                    <li class="nav-item"><a class="nav-link" href="promocodes.php"><i class="fas fa-gift"></i> Промокоды</a></li>
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
        <h2 class="mb-4"><i class="fas fa-users"></i> Управление пользователями</h2>
        
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
        
        <div class="search-box">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" name="search_id" class="form-control" placeholder="ID пользователя" value="<?= htmlspecialchars($search_id) ?>">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Найти</button>
                        <?php if ($search_mode): ?>
                            <a href="users.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <table class="user-table table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User ID</th>
                        <?php foreach ($columns as $column): ?>
                            <?php if ($column !== 'user_id' && $column !== 'id'): ?>
                                <th><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $column))) ?></th>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['id'] ?? '') ?></td>
                                <td><?= htmlspecialchars($user['user_id'] ?? '') ?></td>
                                <?php foreach ($columns as $column): ?>
                                    <?php if ($column !== 'user_id' && $column !== 'id'): ?>
                                        <td>
                                            <?php if ($column === 'balance' || $column === 'deposit' || $column === 'ref_earned' || $column === 'ref_available'): ?>
                                                <?= number_format((float)$user[$column], 2) ?>
                                            <?php elseif (is_array($user[$column] ?? null)): ?>
                                                <?= htmlspecialchars(json_encode($user[$column])) ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars($user[$column] ?? '0') ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <td>
                                    <button class="btn btn-sm btn-primary edit-btn" data-bs-toggle="modal" data-bs-target="#editModal" data-user-id="<?= $user['user_id'] ?>">
                                        <i class="fas fa-edit"></i> Редактировать
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= count($columns) + 3 ?>" class="no-results">
                                Пользователи не найдены
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
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

    <!-- Модальное окно редактирования -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Редактирование пользователя</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="modal-body" id="editModalBody">
                        <!-- Форма будет заполнена через JavaScript -->
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
    // Используем JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP для безопасного вывода
    const users = <?php echo json_encode($users, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
    console.log('Users data loaded:', users);

    // Обработка кнопок редактирования
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            document.getElementById('editUserId').value = userId;

            // Находим пользователя
            const user = users.find(u => u.user_id == userId);

            if (!user) {
                console.error('User not found:', userId);
                document.getElementById('editModalBody').innerHTML = '<div class="alert alert-danger">Пользователь не найден</div>';
                return;
            }

            // Создаём форму редактирования
            let formHtml = '';

            const fieldGroups = [
                ['user_id', 'token'],
                ['balance', 'wager'],
                ['deposit', 'ref_earned'],
                ['ref_available', 'ban'],
                ['wban', 'ref_by'],
                ['role']
            ];

            fieldGroups.forEach(group => {
                formHtml += '<div class="row">';
                group.forEach(field => {
                    if (user.hasOwnProperty(field)) {
                        let value = user[field];
                        let inputType = 'text';
                        let stepAttr = '';

                        if (field === 'balance' || field === 'deposit' || field === 'ref_earned' || field === 'ref_available') {
                            value = parseFloat(value).toFixed(2);
                            inputType = 'number';
                            stepAttr = 'step="0.01" min="0"';
                        } else if (['wager', 'role', 'ban', 'wban', 'ref_by'].includes(field)) {
                            value = parseInt(value) || 0;
                            inputType = 'number';
                            stepAttr = 'step="1"';
                        }

                        formHtml += `
                            <div class="col-md-6 mb-3">
                                <label class="form-label">${field.replace(/_/g, ' ')}</label>
                                <input type="${inputType}" class="form-control" name="${field}" value="${value}" ${stepAttr}>
                            </div>
                        `;
                    }
                });
                formHtml += '</div>';
            });

            document.getElementById('editModalBody').innerHTML = formHtml;
        });
    });

    // Авто-скрытие alert через 5 секунд
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
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