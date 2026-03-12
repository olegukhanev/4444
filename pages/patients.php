<?php
require_once __DIR__ . '/../auth.php';
require_user_role();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        db()->prepare('INSERT INTO Pacienty(last_name,first_name,middle_name,birth_date,pol,phone,adres,polis_number) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([
                trim($_POST['last_name'] ?? ''), trim($_POST['first_name'] ?? ''), trim($_POST['middle_name'] ?? ''),
                $_POST['birth_date'] ?: null, trim($_POST['pol'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['adres'] ?? ''), trim($_POST['polis_number'] ?? '')
            ]);
        set_flash('success', 'Пациент добавлен.');
        redirect('index.php?page=patients');
    }
    if ($action === 'edit') {
        db()->prepare('UPDATE Pacienty SET last_name=?, first_name=?, middle_name=?, birth_date=?, pol=?, phone=?, adres=?, polis_number=? WHERE PacientID=?')
            ->execute([
                trim($_POST['last_name'] ?? ''), trim($_POST['first_name'] ?? ''), trim($_POST['middle_name'] ?? ''),
                $_POST['birth_date'] ?: null, trim($_POST['pol'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['adres'] ?? ''), trim($_POST['polis_number'] ?? ''), (int)$_POST['id']
            ]);
        set_flash('success', 'Пациент обновлен.');
        redirect('index.php?page=patients');
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $cnt = db()->prepare('SELECT COUNT(*) FROM IstoriiBolezni WHERE PacientID=?');
        $cnt->execute([$id]);
        if ((int)$cnt->fetchColumn() > 0) {
            set_flash('error', 'Нельзя удалить пациента: есть связанные истории болезни.');
        } else {
            db()->prepare('DELETE FROM Pacienty WHERE PacientID=?')->execute([$id]);
            set_flash('success', 'Пациент удален.');
        }
        redirect('index.php?page=patients');
    }
}
$q = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM Pacienty';
$params = [];
if ($q) {
    $sql .= ' WHERE last_name LIKE ? OR first_name LIKE ? OR polis_number LIKE ?';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$sql .= ' ORDER BY last_name, first_name';
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/../layout.php';
?>
<div class="card">
    <div class="panel-header">
        <div><h2 class="panel-title">Пациенты</h2><p class="panel-subtitle">Добавление, редактирование, удаление и поиск пациентов.</p></div>
        <a class="btn secondary" href="index.php?page=dashboard">← Назад на главную</a>
    </div>
    <form method="get" class="inline-form">
        <input type="hidden" name="page" value="patients">
        <input name="q" value="<?= h($q) ?>" placeholder="Поиск по ФИО или номеру полиса">
        <div class="actions">
            <button class="btn">Поиск</button>
            <a class="btn secondary" href="index.php?page=patients">Очистить</a>
        </div>
    </form>
</div>

<div class="card">
    <h3>Добавить пациента</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="action" value="add">
        <label>Фамилия<input name="last_name" required></label>
        <label>Имя<input name="first_name" required></label>
        <label>Отчество<input name="middle_name"></label>
        <label>Дата рождения<input type="date" name="birth_date"></label>
        <label>Пол
            <select name="pol">
                <option value="">— выбрать —</option>
                <option value="Мужской">Мужской</option>
                <option value="Женский">Женский</option>
            </select>
        </label>
        <label>Телефон<input name="phone" placeholder="+7 (___) ___-__-__" pattern="[0-9+()\-\s]{10,20}" title="Например: +7 (987) 123-45-67"></label>
        <label>Адрес<input name="adres"></label>
        <label>Номер полиса<input name="polis_number" placeholder="0000 0000 0000 0000" pattern="[0-9\s-]{10,30}" title="Только цифры, пробелы и дефис"></label>
        <button class="btn">Добавить</button>
    </form>
</div>

<div class="card">
    <h3>Список пациентов</h3>
    <div class="table-wrap">
        <table>
            <tr><th>ID</th><th>ФИО</th><th>Рождение</th><th>Пол</th><th>Телефон</th><th>Полис</th><th>Действия</th></tr>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int)$r['PacientID'] ?></td>
                    <td><?= h(trim($r['last_name'] . ' ' . $r['first_name'] . ' ' . $r['middle_name'])) ?></td>
                    <td><?= h($r['birth_date']) ?></td>
                    <td><?= h($r['pol']) ?></td>
                    <td><?= h($r['phone']) ?></td>
                    <td><?= h($r['polis_number']) ?></td>
                    <td>
                        <form method="post" class="form-grid">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?= (int)$r['PacientID'] ?>">
                            <label>Фамилия<input name="last_name" value="<?= h($r['last_name']) ?>"></label>
                            <label>Имя<input name="first_name" value="<?= h($r['first_name']) ?>"></label>
                            <label>Отчество<input name="middle_name" value="<?= h($r['middle_name']) ?>"></label>
                            <label>Дата рождения<input type="date" name="birth_date" value="<?= h($r['birth_date']) ?>"></label>
                            <label>Пол
                                <select name="pol">
                                    <option value="Мужской" <?= $r['pol'] === 'Мужской' ? 'selected' : '' ?>>Мужской</option>
                                    <option value="Женский" <?= $r['pol'] === 'Женский' ? 'selected' : '' ?>>Женский</option>
                                </select>
                            </label>
                            <label>Телефон<input name="phone" value="<?= h($r['phone']) ?>" placeholder="+7 (___) ___-__-__" pattern="[0-9+()\-\s]{10,20}" title="Например: +7 (987) 123-45-67"></label>
                            <label>Адрес<input name="adres" value="<?= h($r['adres']) ?>"></label>
                            <label>Номер полиса<input name="polis_number" value="<?= h($r['polis_number']) ?>" placeholder="0000 0000 0000 0000" pattern="[0-9\s-]{10,30}" title="Только цифры, пробелы и дефис"></label>
                            <button class="btn">Сохранить</button>
                        </form>
                        <form method="post" class="form-stack">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$r['PacientID'] ?>">
                            <button class="btn danger" onclick="return confirm('Удалить пациента?')">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
