<?php
session_start();

function randomSalt($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $salt = '';
    for ($i = 0; $i < $length; $i++) {
        $salt .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $salt;
}

// ===== Обработка установки числа через GET =====
if (isset($_GET['set'])) {
    $set = intval($_GET['set']);
    if ($set < 0 || $set > 6) $set = 0; // 0 = случайно
    setcookie("forced_number", $set, time() + 3600 * 24 * 30); // на 30 дней
    $_COOKIE['forced_number'] = $set;
    header("Location: ?");
    exit;
}

// Определяем число
if (isset($_COOKIE['forced_number']) && $_COOKIE['forced_number'] > 0) {
    $number = intval($_COOKIE['forced_number']);
} else {
    $number = rand(1, 6);
}

// Генерируем соль и хеш каждый раунд
if (!isset($_SESSION['hash'])) {
    $_SESSION['number'] = $number;
    $_SESSION['salt'] = randomSalt(12);
    $_SESSION['hash'] = md5($_SESSION['number'] . "|" . $_SESSION['salt']);
    $_SESSION['revealed'] = false;
}

// Кнопка «Сделать бросок»
if (isset($_POST['roll'])) {
    $_SESSION['revealed'] = true;
}

// Сброс раунда
if (isset($_GET['reset'])) {
    unset($_SESSION['hash'], $_SESSION['number'], $_SESSION['salt'], $_SESSION['revealed']);
    header("Location: ?");
    exit;
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Dice — проверка на честность</title>
<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; line-height: 1.5; }
pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
button { padding: 10px 20px; font-size: 16px; cursor: pointer; }
.explain { background: #eef; padding: 15px; border-radius: 5px; margin-top: 20px; }
.note { font-size: 14px; color: #666; }
</style>
</head>
<body>

<h1>🎲 Бросок кубика с проверкой на честность</h1>

<?php if (!$_SESSION['revealed']): ?>
    <h3>Хеш (commit), показанный ДО броска:</h3>
    <pre><?= $_SESSION['hash'] ?></pre>

    <form method="post">
        <button type="submit" name="roll">Сделать бросок 🎲</button>
    </form>

    <p class="note">
        Текущий режим:
        <?php if (!empty($_COOKIE['forced_number']) && $_COOKIE['forced_number'] > 0): ?>
            Число зафиксировано на <?= $_COOKIE['forced_number'] ?> (<a href="?set=0">сбросить на случайный</a>)
        <?php else: ?>
            Случайный результат (<a href="?set=1">зафиксировать на 1</a>)
        <?php endif; ?>
    </p>

<?php else: ?>
    <h3>Результат броска</h3>
    <p>Число: <b><?= $_SESSION['number'] ?></b></p>
    <p>Соль: <b><?= $_SESSION['salt'] ?></b></p>
    <p>Строка для проверки: <code><?= $_SESSION['number'] . "|" . $_SESSION['salt'] ?></code></p>
    <p>Проверка: md5(<?= $_SESSION['number'] ?>|<?= $_SESSION['salt'] ?>) = <?= md5($_SESSION['number'] . "|" . $_SESSION['salt']) ?></p>

    <form method="get">
        <button type="submit" name="reset">Начать новый раунд</button>
    </form>
<?php endif; ?>

<div class="explain" style="background:#eef; padding:15px; border-radius:6px; margin-top:25px; font-size:16px; line-height:1.5;">
  <h2>🔍 Проверка честности игры — как это работает и как проверить самому</h2>
  <p>В нашем казино результат броска кубика <b>определяется заранее</b> — до того, как вы нажмёте кнопку «Сделать бросок».</p>

  <h3>1. Что такое «хеш (commit)» и зачем он нужен?</h3>
  <p>Хеш — это особая строка, полученная с помощью алгоритма MD5 от сочетания двух данных: <code>число|соль</code>.</p>
  <ul>
    <li><b>Число</b> — это результат броска кубика (от 1 до 6).</li>
    <li><b>Соль</b> — случайный набор символов, который добавляет уникальность и не даёт заранее угадать число.</li>
  </ul>
  <p>Сначала мы показываем вам только <b>хеш</b>, но не показываем число и соль. Это гарантирует, что результат уже «зафиксирован» и не может быть изменён после того, как вы увидите хеш.</p>

  <h3>2. Как происходит сам бросок?</h3>
  <p>После того, как вы нажмёте кнопку «Сделать бросок», мы покажем число и соль, из которых был создан хеш.</p>
  <p>Вы увидите строку вида <code>число|соль</code> — именно из неё был создан хеш, который вы уже видели.</p>

  <h3>3. Как проверить честность самостоятельно?</h3>
  <ol>
    <li>Скопируйте строку <code>число|соль</code>, которая показывается после броска.</li>
    <li>Перейдите на любой онлайн MD5 генератор, например:
      <ul>
        <li><a href="https://www.md5hashgenerator.com/" target="_blank" rel="noopener noreferrer">md5hashgenerator.com</a></li>
        <li><a href="https://emn178.github.io/online-tools/md5.html" target="_blank" rel="noopener noreferrer">emn178.github.io/online-tools/md5.html</a></li>
      </ul>
    </li>
    <li>Вставьте скопированную строку в поле ввода и нажмите кнопку «Generate» или «Хешировать».</li>
    <li>Проверьте, что полученный MD5 хеш совпадает с тем, что был показан ДО броска.</li>
  </ol>
  <p>Если хеш совпадает — значит результат не менялся после того, как был зафиксирован, а значит игра честна и предсказуема для вас.</p>

  <h3>4. Важно</h3>
  <p>Этот метод — базовый пример <b>принципа честности с коммитом</b>, который используется в реальных казино и играх для доказательства, что результаты не подделываются.</p>
  <p>Если хотите узнать больше — ищите в интернете по запросам <b>«provably fair gambling»</b> и <b>«commit-reveal scheme»</b>.</p>
</div>


</body>
</html>
