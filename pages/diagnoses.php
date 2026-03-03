<?php
require_once __DIR__ . '/../auth.php';
require_user_role();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'add_diag') {
        db()->prepare('INSERT INTO Diagnozy(mkb_code,imya) VALUES(?,?)')->execute([trim($_POST['mkb_code']), trim($_POST['imya'])]);
        set_flash('success', 'Диагноз добавлен.'); redirect('index.php?page=diagnoses');
    }
    if ($a === 'edit_diag') {
        db()->prepare('UPDATE Diagnozy SET mkb_code=?, imya=? WHERE DiagnozID=?')->execute([trim($_POST['mkb_code']), trim($_POST['imya']), (int)$_POST['id']]);
        set_flash('success', 'Диагноз обновлен.'); redirect('index.php?page=diagnoses');
    }
    if ($a === 'del_diag') {
        $id = (int)($_POST['id'] ?? 0);
        $cnt = db()->prepare('SELECT COUNT(*) FROM IstDiagnozy WHERE DiagnozID=?');
        $cnt->execute([$id]);
        if ((int)$cnt->fetchColumn() > 0) {
            set_flash('error', 'Нельзя удалить диагноз: он уже привязан к истории болезни.');
        } else {
            db()->prepare('DELETE FROM Diagnozy WHERE DiagnozID=?')->execute([$id]);
            set_flash('success', 'Диагноз удален.');
        }
        redirect('index.php?page=diagnoses');
    }
    if ($a === 'bind') {
        db()->prepare('INSERT INTO IstDiagnozy(IstoriyaID,DiagnozID,data_postanovki,is_osnovnoy,primechanie) VALUES(?,?,?,?,?)')
            ->execute([(int)$_POST['IstoriyaID'], (int)$_POST['DiagnozID'], $_POST['data_postanovki'], ($_POST['is_osnovnoy'] ?? 'No') === 'Yes' ? 'Yes' : 'No', trim($_POST['primechanie'])]);
        set_flash('success', 'Диагноз привязан к истории.'); redirect('index.php?page=diagnoses');
    }
    if ($a === 'del_bind') {
        db()->prepare('DELETE FROM IstDiagnozy WHERE IstDiagnozID=?')->execute([(int)$_POST['id']]);
        set_flash('success', 'Привязка удалена.'); redirect('index.php?page=diagnoses');
    }
}
$diag = db()->query('SELECT * FROM Diagnozy ORDER BY DiagnozID DESC')->fetchAll(PDO::FETCH_ASSOC);
$hist = db()->query('SELECT h.IstoriyaID, h.nomer_istorii, CONCAT(p.last_name, " ", p.first_name, " ", COALESCE(p.middle_name, "")) fio FROM IstoriiBolezni h JOIN Pacienty p ON p.PacientID=h.PacientID ORDER BY h.IstoriyaID DESC')->fetchAll(PDO::FETCH_ASSOC);
$bind = db()->query('SELECT b.*, h.nomer_istorii, d.mkb_code, d.imya FROM IstDiagnozy b JOIN IstoriiBolezni h ON h.IstoriyaID=b.IstoriyaID JOIN Diagnozy d ON d.DiagnozID=b.DiagnozID ORDER BY b.IstDiagnozID DESC')->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/../layout.php';
?>
<div class="card">
    <div class="panel-header">
        <div><h2 class="panel-title">Диагнозы</h2><p class="panel-subtitle">Справочник диагнозов и привязка к историям болезни.</p></div>
        <a class="btn secondary" href="index.php?page=dashboard">← Назад на главную</a>
    </div>
</div>

<div class="two-col">
    <div class="card">
        <h3>Справочник диагнозов</h3>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="add_diag">
            <label>Код МКБ<input name="mkb_code" required></label>
            <label>Наименование<input name="imya" required></label>
            <button class="btn">Добавить</button>
        </form>
    </div>

    <div class="card">
        <h3>Привязка диагноза к истории болезни</h3>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="bind">
            <label>История
                <select name="IstoriyaID">
                    <?php foreach($hist as $h):?>
                        <option value="<?= $h['IstoriyaID'] ?>">#<?= $h['IstoriyaID'] ?> — <?= h($h['nomer_istorii']) ?> (<?= h(trim($h['fio'])) ?>)</option>
                    <?php endforeach;?>
                </select>
            </label>
            <label>Диагноз
                <select name="DiagnozID">
                    <?php foreach($diag as $d):?><option value="<?= $d['DiagnozID'] ?>"><?= h($d['mkb_code'].' '.$d['imya']) ?></option><?php endforeach;?>
                </select>
            </label>
            <label>Дата постановки<input type="date" name="data_postanovki" required></label>
            <label>Основной
                <select name="is_osnovnoy"><option value="Yes">Yes</option><option value="No">No</option></select>
            </label>
            <label>Примечание<textarea name="primechanie"></textarea></label>
            <button class="btn">Привязать</button>
        </form>
    </div>
</div>

<div class="card"><h3>Список диагнозов</h3>
<div class="table-wrap"><table><tr><th>ID</th><th>Код</th><th>Наименование</th><th>Действия</th></tr>
<?php foreach($diag as $d):?><tr><td><?= $d['DiagnozID'] ?></td><td><?= h($d['mkb_code']) ?></td><td><?= h($d['imya']) ?></td><td><form method="post" class="form-grid"><input type="hidden" name="action" value="edit_diag"><input type="hidden" name="id" value="<?= $d['DiagnozID'] ?>"><label>Код<input name="mkb_code" value="<?= h($d['mkb_code']) ?>"></label><label>Наименование<input name="imya" value="<?= h($d['imya']) ?>"></label><button class="btn">Сохранить</button></form><form method="post"><input type="hidden" name="action" value="del_diag"><input type="hidden" name="id" value="<?= $d['DiagnozID'] ?>"><button class="btn danger">Удалить</button></form></td></tr><?php endforeach;?>
</table></div></div>

<div class="card"><h3>Список привязок диагнозов</h3>
<div class="table-wrap"><table><tr><th>ID</th><th>История</th><th>Диагноз</th><th>Дата</th><th>Основной</th><th>Действие</th></tr><?php foreach($bind as $b):?><tr><td><?= $b['IstDiagnozID'] ?></td><td><?= h($b['nomer_istorii']) ?></td><td><?= h($b['mkb_code'].' '.$b['imya']) ?></td><td><?= h($b['data_postanovki']) ?></td><td><?= h($b['is_osnovnoy']) ?></td><td><form method="post"><input type="hidden" name="action" value="del_bind"><input type="hidden" name="id" value="<?= $b['IstDiagnozID'] ?>"><button class="btn danger">Удалить</button></form></td></tr><?php endforeach;?></table></div></div>
<?php require_once __DIR__ . '/../footer.php'; ?>
