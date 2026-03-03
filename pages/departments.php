<?php
require_once __DIR__ . '/../auth.php';
require_user_role();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        db()->prepare('INSERT INTO Otdeleniya(imya, phone, kabinet, active) VALUES(?,?,?,?)')
            ->execute([
                trim($_POST['imya'] ?? ''),
                trim($_POST['phone'] ?? ''),
                trim($_POST['kabinet'] ?? ''),
                ($_POST['active'] ?? 'Yes') === 'Yes' ? 'Yes' : 'No',
            ]);
        set_flash('success', 'Отделение добавлено.');
        redirect('index.php?page=departments');
    }

    if ($action === 'edit') {
        db()->prepare('UPDATE Otdeleniya SET imya=?, phone=?, kabinet=?, active=? WHERE OtdelenieID=?')
            ->execute([
                trim($_POST['imya'] ?? ''),
                trim($_POST['phone'] ?? ''),
                trim($_POST['kabinet'] ?? ''),
                ($_POST['active'] ?? 'No') === 'Yes' ? 'Yes' : 'No',
                (int)($_POST['id'] ?? 0),
            ]);
        set_flash('success', 'Отделение обновлено.');
        redirect('index.php?page=departments');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $cnt = db()->prepare('SELECT COUNT(*) FROM Sotrudniki WHERE OtdelenieID=?');
        $cnt->execute([$id]);
        if ((int)$cnt->fetchColumn() > 0) {
            set_flash('error', 'Нельзя удалить отделение: в нем есть сотрудники.');
        } else {
            db()->prepare('DELETE FROM Otdeleniya WHERE OtdelenieID=?')->execute([$id]);
            set_flash('success', 'Отделение удалено.');
        }
        redirect('index.php?page=departments');
    }
}

$q = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM Otdeleniya';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE imya LIKE ? OR phone LIKE ? OR kabinet LIKE ?';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$sql .= ' ORDER BY imya';
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../layout.php';
?>
<div class="card">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">Отделения</h2>
            <p class="panel-subtitle">Справочник отделений для привязки сотрудников.</p>
        </div>
        <a class="btn secondary" href="index.php?page=dashboard">← Назад на главную</a>
    </div>
    <form method="get" class="inline-form">
        <input type="hidden" name="page" value="departments">
        <input name="q" value="<?= h($q) ?>" placeholder="Поиск по названию/телефону/кабинету">
        <div class="actions">
            <button class="btn">Поиск</button>
            <a class="btn secondary" href="index.php?page=departments">Очистить</a>
        </div>
    </form>
</div>

<div class="card">
    <h3>Добавить отделение</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="action" value="add">
        <label>Название отделения <input name="imya" required></label>
        <label>Телефон <input name="phone"></label>
        <label>Кабинет <input name="kabinet"></label>
        <label>Active
            <select name="active">
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </label>
        <button class="btn">Добавить</button>
    </form>
</div>

<div class="card">
    <h3>Список отделений</h3>
    <div class="table-wrap">
        <table>
            <tr><th>ID</th><th>Название</th><th>Телефон</th><th>Кабинет</th><th>Active</th><th>Действия</th></tr>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int)$r['OtdelenieID'] ?></td>
                    <td><?= h($r['imya']) ?></td>
                    <td><?= h($r['phone']) ?></td>
                    <td><?= h($r['kabinet']) ?></td>
                    <td><?= h($r['active']) ?></td>
                    <td>
                        <form method="post" class="form-grid">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?= (int)$r['OtdelenieID'] ?>">
                            <label>Название <input name="imya" value="<?= h($r['imya']) ?>" required></label>
                            <label>Телефон <input name="phone" value="<?= h($r['phone']) ?>"></label>
                            <label>Кабинет <input name="kabinet" value="<?= h($r['kabinet']) ?>"></label>
                            <label>Active
                                <select name="active">
                                    <option value="Yes" <?= $r['active'] === 'Yes' ? 'selected' : '' ?>>Yes</option>
                                    <option value="No" <?= $r['active'] === 'No' ? 'selected' : '' ?>>No</option>
                                </select>
                            </label>
                            <button class="btn">Сохранить</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$r['OtdelenieID'] ?>">
                            <button class="btn danger" onclick="return confirm('Удалить отделение?')">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
