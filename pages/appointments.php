<?php
require_once __DIR__ . '/../auth.php';
require_user_role();

$hist = db()->query('SELECT h.IstoriyaID, h.nomer_istorii, h.ist_status, CONCAT(p.last_name, " ", p.first_name, " ", COALESCE(p.middle_name, "")) fio FROM IstoriiBolezni h JOIN Pacienty p ON p.PacientID=h.PacientID ORDER BY h.IstoriyaID DESC')->fetchAll(PDO::FETCH_ASSOC);
$proc = db()->query('SELECT ProceduraID, kod_procedury, imya FROM SprProcedur WHERE active="Yes" ORDER BY imya')->fetchAll(PDO::FETCH_ASSOC);
$sotr = db()->query('SELECT SotrudnikID, CONCAT(last_name, " ", first_name, " ", COALESCE(middle_name, "")) fio FROM Sotrudniki WHERE active="Yes" ORDER BY last_name, first_name')->fetchAll(PDO::FETCH_ASSOC);

if($_SERVER['REQUEST_METHOD']==='POST'){
    $a=$_POST['action']??'';
    if($a==='add'){
        db()->prepare('INSERT INTO Naznacheniya(IstoriyaID,ProceduraID,naznachil_SotrudnikID,data_naznacheniya,plan_data,prioritet,naz_status,primechanie) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([(int)$_POST['IstoriyaID'],(int)$_POST['ProceduraID'], $_POST['naznachil_SotrudnikID'] !== '' ? (int)$_POST['naznachil_SotrudnikID'] : null, $_POST['data_naznacheniya'], $_POST['plan_data'] ?: null, trim($_POST['prioritet']), trim($_POST['naz_status']), trim($_POST['primechanie'])]);
        set_flash('success','Назначение добавлено.');redirect('index.php?page=appointments');
    }
    if($a==='edit'){
        db()->prepare('UPDATE Naznacheniya SET IstoriyaID=?,ProceduraID=?,naznachil_SotrudnikID=?,data_naznacheniya=?,plan_data=?,prioritet=?,naz_status=?,primechanie=? WHERE NaznachenieID=?')
            ->execute([(int)$_POST['IstoriyaID'],(int)$_POST['ProceduraID'], $_POST['naznachil_SotrudnikID'] !== '' ? (int)$_POST['naznachil_SotrudnikID'] : null, $_POST['data_naznacheniya'], $_POST['plan_data'] ?: null, trim($_POST['prioritet']), trim($_POST['naz_status']), trim($_POST['primechanie']), (int)$_POST['id']]);
        set_flash('success','Назначение обновлено.');redirect('index.php?page=appointments');
    }
    if($a==='delete'){
        $id = (int)($_POST['id'] ?? 0);
        $cnt = db()->prepare('SELECT COUNT(*) FROM Vypolneniya WHERE NaznachenieID=?');
        $cnt->execute([$id]);
        if ((int)$cnt->fetchColumn() > 0) {
            set_flash('error','Нельзя удалить назначение: по нему уже есть выполнения.');
        } else {
            db()->prepare('DELETE FROM Naznacheniya WHERE NaznachenieID=?')->execute([$id]);
            set_flash('success','Назначение удалено.');
        }
        redirect('index.php?page=appointments');
    }
}
$hmap=[];$pmap=[];$smap=[];
foreach($hist as $v){$hmap[$v['IstoriyaID']]=$v['nomer_istorii'].' / '.trim($v['fio']);}
foreach($proc as $v){$pmap[$v['ProceduraID']]='['.$v['kod_procedury'].'] '.$v['imya'];}
foreach($sotr as $v){$smap[$v['SotrudnikID']]=trim($v['fio']);}
$rows=db()->query('SELECT * FROM Naznacheniya ORDER BY NaznachenieID DESC')->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/../layout.php';
?>
<div class="card">
    <div class="panel-header">
        <div><h2 class="panel-title">Назначения процедур</h2><p class="panel-subtitle">Назначайте процедуры по истории болезни и сотруднику.</p></div>
        <a class="btn secondary" href="index.php?page=dashboard">← Назад на главную</a>
    </div>
</div>
<div class="card">
    <h3>Добавить назначение</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="action" value="add">
        <label>История болезни
            <select name="IstoriyaID" required>
                <?php foreach($hist as $h):?><option value="<?= $h['IstoriyaID'] ?>">История <?= h($h['nomer_istorii']) ?> (ID <?= (int)$h['IstoriyaID'] ?>) — <?= h(trim($h['fio'])) ?>, <?= h($h['ist_status']) ?></option><?php endforeach;?>
            </select>
        </label>
        <label>Процедура
            <select name="ProceduraID" required>
                <?php foreach($proc as $p):?><option value="<?= $p['ProceduraID'] ?>">[<?= h($p['kod_procedury']) ?>] <?= h($p['imya']) ?></option><?php endforeach;?>
            </select>
        </label>
        <label>Назначил (сотрудник)
            <select name="naznachil_SotrudnikID">
                <option value="">— не указано —</option>
                <?php foreach($sotr as $s):?><option value="<?= $s['SotrudnikID'] ?>"><?= h(trim($s['fio'])) ?></option><?php endforeach;?>
            </select>
        </label>
        <label>Дата назначения<input type="date" name="data_naznacheniya" required></label>
        <label>Плановая дата<input type="date" name="plan_data"></label>
        <label>Приоритет
            <select name="prioritet"><option value="Обычный">Обычный</option><option value="Высокий">Высокий</option><option value="Срочно">Срочно</option></select>
        </label>
        <label>Статус
            <select name="naz_status"><option value="Назначено">Назначено</option><option value="В работе">В работе</option><option value="Выполнено">Выполнено</option><option value="Отменено">Отменено</option></select>
        </label>
        <label>Примечание<textarea name="primechanie"></textarea></label>
        <button class="btn">Добавить</button>
    </form>
</div>
<div class="card">
    <h3>Список назначений</h3>
    <div class="table-wrap"><table><tr><th>ID</th><th>История</th><th>Процедура</th><th>Сотрудник</th><th>Статус</th><th>Действия</th></tr>
    <?php foreach($rows as $r):?><tr><td><?= $r['NaznachenieID'] ?></td><td><?= h($hmap[$r['IstoriyaID']] ?? '') ?></td><td><?= h($pmap[$r['ProceduraID']] ?? '') ?></td><td><?= h($smap[$r['naznachil_SotrudnikID']] ?? '—') ?></td><td><?= h($r['naz_status']) ?></td><td>
    <form method="post" class="form-grid"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= $r['NaznachenieID'] ?>">
    <label>История<select name="IstoriyaID"><?php foreach($hist as $h):?><option value="<?= $h['IstoriyaID'] ?>" <?= $h['IstoriyaID']==$r['IstoriyaID']?'selected':'' ?>>История <?= h($h['nomer_istorii']) ?> (ID <?= (int)$h['IstoriyaID'] ?>) — <?= h(trim($h['fio'])) ?></option><?php endforeach;?></select></label>
    <label>Процедура<select name="ProceduraID"><?php foreach($proc as $p):?><option value="<?= $p['ProceduraID'] ?>" <?= $p['ProceduraID']==$r['ProceduraID']?'selected':'' ?>>[<?= h($p['kod_procedury']) ?>] <?= h($p['imya']) ?></option><?php endforeach;?></select></label>
    <label>Сотрудник<select name="naznachil_SotrudnikID"><option value="">— не указано —</option><?php foreach($sotr as $s):?><option value="<?= $s['SotrudnikID'] ?>" <?= (string)$s['SotrudnikID']===(string)$r['naznachil_SotrudnikID']?'selected':'' ?>><?= h(trim($s['fio'])) ?></option><?php endforeach;?></select></label>
    <label>Дата назначения<input type="date" name="data_naznacheniya" value="<?= h($r['data_naznacheniya']) ?>"></label>
    <label>Плановая дата<input type="date" name="plan_data" value="<?= h($r['plan_data']) ?>"></label>
    <label>Приоритет
        <select name="prioritet">
            <option value="Обычный" <?= $r['prioritet']==='Обычный'?'selected':'' ?>>Обычный</option>
            <option value="Высокий" <?= $r['prioritet']==='Высокий'?'selected':'' ?>>Высокий</option>
            <option value="Срочно" <?= $r['prioritet']==='Срочно'?'selected':'' ?>>Срочно</option>
        </select>
    </label>
    <label>Статус
        <select name="naz_status">
            <option value="Назначено" <?= $r['naz_status']==='Назначено'?'selected':'' ?>>Назначено</option>
            <option value="В работе" <?= $r['naz_status']==='В работе'?'selected':'' ?>>В работе</option>
            <option value="Выполнено" <?= $r['naz_status']==='Выполнено'?'selected':'' ?>>Выполнено</option>
            <option value="Отменено" <?= $r['naz_status']==='Отменено'?'selected':'' ?>>Отменено</option>
        </select>
    </label>
    <label>Примечание<textarea name="primechanie"><?= h($r['primechanie']) ?></textarea></label>
    <button class="btn">Сохранить</button></form>
    <form method="post"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['NaznachenieID'] ?>"><button class="btn danger">Удалить</button></form>
    </td></tr><?php endforeach;?></table></div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
