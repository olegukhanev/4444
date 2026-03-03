<?php
require_once __DIR__ . '/../auth.php';
require_user_role();

$departments = db()->query('SELECT OtdelenieID, imya FROM Otdeleniya ORDER BY imya')->fetchAll(PDO::FETCH_ASSOC);
$dmap = [];
foreach ($departments as $d) {
    $dmap[$d['OtdelenieID']] = $d['imya'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        db()->prepare('INSERT INTO Sotrudniki(OtdelenieID,last_name,first_name,middle_name,dolzhnost,phone,date_naima,active) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([
                (int)($_POST['OtdelenieID'] ?? 0),
                trim($_POST['last_name'] ?? ''),
                trim($_POST['first_name'] ?? ''),
                trim($_POST['middle_name'] ?? ''),
                trim($_POST['dolzhnost'] ?? ''),
                trim($_POST['phone'] ?? ''),
                $_POST['date_naima'] ?: null,
                ($_POST['active'] ?? 'Yes') === 'Yes' ? 'Yes' : 'No',
            ]);
        set_flash('success', 'Сотрудник добавлен.');
        redirect('index.php?page=staff');
    }

    if ($action === 'edit') {
        db()->prepare('UPDATE Sotrudniki SET OtdelenieID=?, last_name=?, first_name=?, middle_name=?, dolzhnost=?, phone=?, date_naima=?, active=? WHERE SotrudnikID=?')
            ->execute([
                (int)($_POST['OtdelenieID'] ?? 0),
                trim($_POST['last_name'] ?? ''),
                trim($_POST['first_name'] ?? ''),
                trim($_POST['middle_name'] ?? ''),
                trim($_POST['dolzhnost'] ?? ''),
                trim($_POST['phone'] ?? ''),
                $_POST['date_naima'] ?: null,
                ($_POST['active'] ?? 'No') === 'Yes' ? 'Yes' : 'No',
                (int)($_POST['id'] ?? 0),
            ]);
        set_flash('success', 'Сотрудник обновлен.');
        redirect('index.php?page=staff');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        $cntNaz = db()->prepare('SELECT COUNT(*) FROM Naznacheniya WHERE naznachil_SotrudnikID=?');
        $cntNaz->execute([$id]);
        $cntVyp = db()->prepare('SELECT COUNT(*) FROM Vypolneniya WHERE vypolnil_SotrudnikID=?');
        $cntVyp->execute([$id]);

        if ((int)$cntNaz->fetchColumn() > 0 || (int)$cntVyp->fetchColumn() > 0) {
            set_flash('error', 'Нельзя удалить сотрудника: он используется в назначениях/выполнениях.');
        } else {
            db()->prepare('DELETE FROM Sotrudniki WHERE SotrudnikID=?')->execute([$id]);
            set_flash('success', 'Сотрудник удален.');
        }
        redirect('index.php?page=staff');
    }
}

$q = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM Sotrudniki';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE last_name LIKE ? OR first_name LIKE ? OR dolzhnost LIKE ? OR phone LIKE ?';
    $params = ["%$q%", "%$q%", "%$q%", "%$q%"];
}
$sql .= ' ORDER BY last_name, first_name';
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../layout.php';
?>
<div class="card">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">Сотрудники</h2>
            <p class="panel-subtitle">Справочник сотрудников для назначений и выполнений процедур.</p>
        </div>
        <a class="btn secondary" href="index.php?page=dashboard">← Назад на главную</a>
    </div>
    <form method="get" class="inline-form">
        <input type="hidden" name="page" value="staff">
        <input name="q" value="<?= h($q) ?>" placeholder="Поиск по ФИО/должности/телефону">
        <div class="actions">
            <button class="btn">Поиск</button>
            <a class="btn secondary" href="index.php?page=staff">Очистить</a>
        </div>
    </form>
</div>

<div class="card">
    <h3>Добавить сотрудника</h3>
    <?php if (empty($departments)): ?>
        <div class="flash error">Сначала добавьте хотя бы одно отделение.</div>
    <?php else: ?>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="add">
            <label>Отделение
                <select name="OtdelenieID" required>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['OtdelenieID'] ?>"><?= h($d['imya']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Фамилия <input name="last_name" required></label>
            <label>Имя <input name="first_name" required></label>
            <label>Отчество <input name="middle_name"></label>
            <label>Должность <input name="dolzhnost" required></label>
            <label>Телефон <input name="phone"></label>
            <label>Дата найма <input type="date" name="date_naima"></label>
            <label>Active
                <select name="active">
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </label>
            <button class="btn">Добавить</button>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Список сотрудников</h3>
    <div class="table-wrap">
        <table>
            <tr><th>ID</th><th>ФИО</th><th>Отделение</th><th>Должность</th><th>Телефон</th><th>Дата найма</th><th>Active</th><th>Действия</th></tr>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int)$r['SotrudnikID'] ?></td>
                    <td><?= h(trim($r['last_name'] . ' ' . $r['first_name'] . ' ' . $r['middle_name'])) ?></td>
                    <td><?= h($dmap[$r['OtdelenieID']] ?? ('#' . $r['OtdelenieID'])) ?></td>
                    <td><?= h($r['dolzhnost']) ?></td>
                    <td><?= h($r['phone']) ?></td>
                    <td><?= h($r['date_naima']) ?></td>
                    <td><?= h($r['active']) ?></td>
                    <td>
                        <form method="post" class="form-grid">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?= (int)$r['SotrudnikID'] ?>">
                            <label>Отделение
                                <select name="OtdelenieID" required>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= (int)$d['OtdelenieID'] ?>" <?= (string)$d['OtdelenieID'] === (string)$r['OtdelenieID'] ? 'selected' : '' ?>><?= h($d['imya']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Фамилия <input name="last_name" value="<?= h($r['last_name']) ?>" required></label>
                            <label>Имя <input name="first_name" value="<?= h($r['first_name']) ?>" required></label>
                            <label>Отчество <input name="middle_name" value="<?= h($r['middle_name']) ?>"></label>
                            <label>Должность <input name="dolzhnost" value="<?= h($r['dolzhnost']) ?>" required></label>
                            <label>Телефон <input name="phone" value="<?= h($r['phone']) ?>"></label>
                            <label>Дата найма <input type="date" name="date_naima" value="<?= h($r['date_naima']) ?>"></label>
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
                            <input type="hidden" name="id" value="<?= (int)$r['SotrudnikID'] ?>">
                            <button class="btn danger" onclick="return confirm('Удалить сотрудника?')">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
