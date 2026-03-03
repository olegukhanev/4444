<?php
require_once __DIR__ . '/../auth.php';
if (is_logged_in()) {
    redirect(is_admin() ? 'index.php?page=users' : 'index.php?page=dashboard');
}
require_once __DIR__ . '/../layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = login(trim($_POST['login'] ?? ''), $_POST['password'] ?? '');
    if (!$error) {
        redirect(is_admin() ? 'index.php?page=users' : 'index.php?page=dashboard');
    }
    echo '<div class="flash error">' . h($error) . '</div>';
}
?>
<div class="card narrow">
    <div class="panel-header"><h2 class="panel-title">Вход в систему</h2></div>
    <form method="post" class="form-stack">
        <label>Логин
            <input name="login" required>
        </label>
        <label>Пароль
            <input type="password" name="password" required>
        </label>
        <button class="btn">Войти</button>
        <a href="index.php?page=register" class="btn secondary">Регистрация</a>
    </form>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
