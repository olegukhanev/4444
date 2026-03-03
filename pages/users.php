<?php
require_once __DIR__ . '/../auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $login = trim($_POST['auth_login'] ?? '');
        $name = trim($_POST['display_name'] ?? '');
        $role = ($_POST['role_name'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $pass = $_POST['password'] ?? '';

        if ($login && $name && $pass) {
            $exists = db()->prepare('SELECT COUNT(*) FROM AuthUsers WHERE auth_login=?');
            $exists->execute([$login]);
            if ((int)$exists->fetchColumn() > 0) {
                set_flash('error', 'Логин уже существует.');
            } else {
                db()->prepare('INSERT INTO AuthUsers(auth_login,password_hash,role_name,display_name,active,created_at) VALUES(?,?,?,?,?,CURDATE())')
                    ->execute([$login, password_hash($pass, PASSWORD_DEFAULT), $role, $name, 'Yes']);
                set_flash('success', 'Пользователь добавлен.');
            }
        }
        redirect('index.php?page=users');
    }

    if ($action === 'edit') {
        db()->prepare('UPDATE AuthUsers SET display_name=?, role_name=?, active=? WHERE AuthUserID=?')
            ->execute([
                trim($_POST['display_name'] ?? ''),
                ($_POST['role_name'] ?? 'user') === 'admin' ? 'admin' : 'user',
                ($_POST['active'] ?? 'No') === 'Yes' ? 'Yes' : 'No',
                (int)($_POST['id'] ?? 0)
            ]);
        set_flash('success', 'Пользователь обновлен.');
        redirect('index.php?page=users');
    }

    if ($action === 'deactivate') {
        db()->prepare("UPDATE AuthUsers SET active='No' WHERE AuthUserID=?")
            ->execute([(int)($_POST['id'] ?? 0)]);
        set_flash('success', 'Пользователь деактивирован.');
        redirect('index.php?page=users');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)(user()['AuthUserID'] ?? 0)) {
            set_flash('error', 'Нельзя удалить текущую учетную запись администратора.');
        } else {
            db()->prepare('DELETE FROM AuthUsers WHERE AuthUserID=?')->execute([$id]);
            set_flash('success', 'Пользователь удален.');
        }
        redirect('index.php?page=users');
    }
}

$q = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM AuthUsers';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE auth_login LIKE ? OR display_name LIKE ?';
    $params = ["%$q%", "%$q%"];
}
$sql .= ' ORDER BY AuthUserID DESC';
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../layout.php';
?>
<div class="card">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">Панель администратора пользователей</h2>
            <p class="panel-subtitle">Только управление учетными записями (AuthUsers).</p>
        </div>
    </div>
    <form method="get" class="form-stack">
        <input type="hidden" name="page" value="users">
        <label>Поиск по логину или имени
            <input name="q" value="<?= h($q) ?>" placeholder="Например: ivanov">
        </label>
        <div class="actions">
            <button class="btn">Поиск</button>
            <a class="btn secondary" href="index.php?page=users">Очистить</a>
        </div>
    </form>
</div>

<div class="card">
    <h3>Добавить пользователя</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="action" value="add">
        <label>Логин <input name="auth_login" required></label>
        <label>Имя <input name="display_name" required></label>
        <label>Пароль <input type="password" name="password" required></label>
        <label>Роль
            <select name="role_name">
                <option value="user">user</option>
                <option value="admin">admin</option>
            </select>
        </label>
        <button class="btn">Создать</button>
    </form>
</div>

<div class="card">
    <h3>Список пользователей</h3>
    <div class="table-wrap">
        <table>
            <tr><th>ID</th><th>Логин</th><th>Имя</th><th>Роль</th><th>Доступ</th><th>Управление</th></tr>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int)$r['AuthUserID'] ?></td>
                    <td><?= h($r['auth_login']) ?></td>
                    <td><?= h($r['display_name']) ?></td>
                    <td><?= h($r['role_name']) ?></td>
                    <td><?= h(yes_no_label($r['active'])) ?></td>
                    <td>
                        <form method="post" class="form-grid">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?= (int)$r['AuthUserID'] ?>">
                            <label>Имя
                                <input name="display_name" value="<?= h($r['display_name']) ?>">
                            </label>
                            <label>Роль
                                <select name="role_name">
                                    <option value="user" <?= $r['role_name'] === 'user' ? 'selected' : '' ?>>user</option>
                                    <option value="admin" <?= $r['role_name'] === 'admin' ? 'selected' : '' ?>>admin</option>
                                </select>
                            </label>
                            <label>Доступ в систему
                                <select name="active">
                                    <option value="Yes" <?= $r['active'] === 'Yes' ? 'selected' : '' ?>>Да</option>
                                    <option value="No" <?= $r['active'] === 'No' ? 'selected' : '' ?>>Нет</option>
                                </select>
                            </label>
                            <button class="btn">Сохранить</button>
                        </form>
                        <form method="post" class="form-stack">
                            <input type="hidden" name="action" value="deactivate">
                            <input type="hidden" name="id" value="<?= (int)$r['AuthUserID'] ?>">
                            <button class="btn danger" <?= $r['active'] === 'No' ? 'disabled' : '' ?>>Деактивировать</button>
                        </form>
                        <form method="post" class="form-stack">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$r['AuthUserID'] ?>">
                            <button class="btn danger" onclick="return confirm('Удалить учетную запись полностью?')" <?= (int)$r['AuthUserID'] === (int)(user()['AuthUserID'] ?? 0) ? 'disabled' : '' ?>>Удалить аккаунт</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
