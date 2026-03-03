<?php
require_once __DIR__ . '/../auth.php';
if (is_logged_in()) {
    redirect(is_admin() ? 'index.php?page=users' : 'index.php?page=dashboard');
}
require_once __DIR__ . '/../layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = register(trim($_POST['login'] ?? ''), trim($_POST['name'] ?? ''), $_POST['password'] ?? '', $_POST['password2'] ?? '');
    if (!$error) {
        set_flash('success', 'Регистрация успешна. Выполните вход.');
        redirect('index.php?page=login');
    }
    echo '<div class="flash error">' . h($error) . '</div>';
}
?>
<div class="card narrow">
    <div class="panel-header"><h2 class="panel-title">Регистрация</h2></div>
    <form method="post" class="form-stack">
        <label>Логин<input name="login" required></label>
        <label>Отображаемое имя<input name="name" required></label>
        <label>Пароль<input type="password" name="password" required></label>
        <label>Повтор пароля<input type="password" name="password2" required></label>
        <button class="btn">Создать аккаунт</button>
        <a href="index.php?page=login" class="btn secondary">Назад к входу</a>
    </form>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
