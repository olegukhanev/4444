<?php
require_once __DIR__ . '/../auth.php';
require_user_role();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'add') {
        db()->prepare('INSERT INTO IstoriiBolezni(PacientID,nomer_istorii,data_otkrytiya,data_zakrytiya,ist_status,primechanie) VALUES(?,?,?,?,?,?)')
            ->execute([(int)$_POST['PacientID'], trim($_POST['nomer_istorii']), $_POST['data_otkrytiya'], $_POST['data_zakrytiya'] ?: null, trim($_POST['ist_status']), trim($_POST['primechanie'])]);
        set_flash('success', 'История болезни добавлена.');
        redirect('index.php?page=histories');
    }
    if ($a === 'edit') {
        db()->prepare('UPDATE IstoriiBolezni SET PacientID=?, nomer_istorii=?, data_otkrytiya=?, data_zakrytiya=?, ist_status=?, primechanie=? WHERE IstoriyaID=?')
            ->execute([(int)$_POST['PacientID'], trim($_POST['nomer_istorii']), $_POST['data_otkrytiya'], $_POST['data_zakrytiya'] ?: null, trim($_POST['ist_status']), trim($_POST['primechanie']), (int)$_POST['id']]);
        set_flash('success', 'История обновлена.');
        redirect('index.php?page=histories');
    }
    if ($a === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        $cntDiag = db()->prepare('SELECT COUNT(*) FROM IstDiagnozy WHERE IstoriyaID=?');
        $cntDiag->execute([$id]);
        $cntNaz = db()->prepare('SELECT COUNT(*) FROM Naznacheniya WHERE IstoriyaID=?');
        $cntNaz->execute([$id]);

        if ((int)$cntDiag->fetchColumn() > 0 || (int)$cntNaz->fetchColumn() > 0) {
            set_flash('error', 'Нельзя удалить историю: есть связанные диагнозы или назначения.');
        } else {
            db()->prepare('DELETE FROM IstoriiBolezni WHERE IstoriyaID=?')->execute([$id]);
            set_flash('success', 'История удалена.');
        }
        redirect('index.php?page=histories');
    }
}

$patients = db()->query("SELECT PacientID, CONCAT(last_name,' ',first_name,' ',COALESCE(middle_name,'')) fio FROM Pacienty ORDER BY last_name, first_name")->fetchAll(PDO::FETCH_ASSOC);
$pmap = [];
foreach ($patients as $p) { $pmap[$p['PacientID']] = trim($p['fio']); }

$q = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM IstoriiBolezni';
$params = [];
if ($q) {
    $sql .= ' WHERE nomer_istorii LIKE ? OR ist_status LIKE ?';
    $params = ["%$q%", "%$q%"];
}
$sql .= ' ORDER BY IstoriyaID DESC';
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../layout.php';
?>
<div class="card">
    <div class="panel-header">
        <div><h2 class="panel-title">Истории болезни</h2><p class="panel-subtitle">Карточки историй по пациентам.</p></div>
        <a class="btn secondary" href="index.php?page=dashboard">← Назад на главную</a>
    </div>
    <form method="get" class="inline-form">
        <input type="hidden" name="page" value="histories">
        <input name="q" value="<?= h($q) ?>" placeholder="Поиск по номеру/статусу">
        <div class="actions">
            <button class="btn">Поиск</button>
            <a class="btn secondary" href="index.php?page=histories">Очистить</a>
        </div>
    </form>
</div>

<div class="card">
    <h3>Добавить историю болезни</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="action" value="add">
        <label>Пациент
            <select name="PacientID" required><?php foreach($patients as $p):?><option value="<?= $p['PacientID'] ?>"><?= h(trim($p['fio'])) ?></option><?php endforeach;?></select>
        </label>
        <label>Номер истории<input name="nomer_istorii" required placeholder="ИБ-2026-0001"></label>
        <label>Дата открытия<input type="date" name="data_otkrytiya" required></label>
        <label>Дата закрытия<input type="date" name="data_zakrytiya"></label>
        <label>Статус
            <select name="ist_status"><option value="Открыта">Открыта</option><option value="Закрыта">Закрыта</option><option value="На лечении">На лечении</option></select>
        </label>
        <label>Примечание<textarea name="primechanie"></textarea></label>
        <button class="btn">Добавить</button>
    </form>
</div>

<div class="card">
    <h3>Список историй</h3>
    <div class="table-wrap"><table><tr><th>ID</th><th>Пациент</th><th>Номер</th><th>Период</th><th>Статус</th><th>Действия</th></tr>
    <?php foreach($rows as $r):?><tr><td><?= $r['IstoriyaID'] ?></td><td><?= h($pmap[$r['PacientID']] ?? '') ?></td><td><?= h($r['nomer_istorii']) ?></td><td><?= h($r['data_otkrytiya']) ?> — <?= h($r['data_zakrytiya']) ?></td><td><?= h($r['ist_status']) ?></td><td>
    <form method="post" class="form-grid"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= $r['IstoriyaID'] ?>">
    <label>Пациент<select name="PacientID"><?php foreach($patients as $p):?><option value="<?= $p['PacientID'] ?>" <?= $p['PacientID']==$r['PacientID']?'selected':'' ?>><?= h(trim($p['fio'])) ?></option><?php endforeach;?></select></label>
    <label>Номер<input name="nomer_istorii" value="<?= h($r['nomer_istorii']) ?>" placeholder="ИБ-2026-0001"></label>
    <label>Дата открытия<input type="date" name="data_otkrytiya" value="<?= h($r['data_otkrytiya']) ?>"></label>
    <label>Дата закрытия<input type="date" name="data_zakrytiya" value="<?= h($r['data_zakrytiya']) ?>"></label>
    <label>Статус<input name="ist_status" value="<?= h($r['ist_status']) ?>"></label>
    <label>Примечание<textarea name="primechanie"><?= h($r['primechanie']) ?></textarea></label>
    <button class="btn">Сохранить</button></form>
    <form method="post"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['IstoriyaID'] ?>"><button class="btn danger">Удалить</button></form>
    </td></tr><?php endforeach;?></table></div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
