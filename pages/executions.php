<?php
require_once __DIR__ . '/../auth.php';
require_user_role();

$naz = db()->query('SELECT n.NaznachenieID, h.nomer_istorii, CONCAT(pa.last_name, " ", pa.first_name, " ", COALESCE(pa.middle_name,"")) fio, p.kod_procedury, p.imya proc_name, p.bazovaya_stoimost FROM Naznacheniya n JOIN IstoriiBolezni h ON h.IstoriyaID=n.IstoriyaID JOIN Pacienty pa ON pa.PacientID=h.PacientID JOIN SprProcedur p ON p.ProceduraID=n.ProceduraID ORDER BY n.NaznachenieID DESC')->fetchAll(PDO::FETCH_ASSOC);
$sotr = db()->query('SELECT SotrudnikID, CONCAT(last_name, " ", first_name, " ", COALESCE(middle_name, "")) fio FROM Sotrudniki WHERE active="Yes" ORDER BY last_name, first_name')->fetchAll(PDO::FETCH_ASSOC);

$costMap = [];
foreach($naz as $n){ $costMap[$n['NaznachenieID']] = $n['bazovaya_stoimost']; }

function execution_sum(int $nazId, array $costMap): ?string {
    return isset($costMap[$nazId]) ? (string)$costMap[$nazId] : null;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $a=$_POST['action']??'';
    if($a==='add'){
        $nazId = (int)$_POST['NaznachenieID'];
        $sum = execution_sum($nazId, $costMap);
        db()->prepare('INSERT INTO Vypolneniya(NaznachenieID,vypolnil_SotrudnikID,data_vypolneniya,rezultat,oslozhneniya,summa,status_oplaty) VALUES(?,?,?,?,?,?,?)')
            ->execute([$nazId,(int)$_POST['vypolnil_SotrudnikID'],$_POST['data_vypolneniya'],trim($_POST['rezultat']),trim($_POST['oslozhneniya']),$sum,trim($_POST['status_oplaty'])]);
        set_flash('success','Выполнение добавлено. Сумма подставлена из базовой стоимости процедуры.');redirect('index.php?page=executions');
    }
    if($a==='edit'){
        $nazId = (int)$_POST['NaznachenieID'];
        $sum = execution_sum($nazId, $costMap);
        db()->prepare('UPDATE Vypolneniya SET NaznachenieID=?,vypolnil_SotrudnikID=?,data_vypolneniya=?,rezultat=?,oslozhneniya=?,summa=?,status_oplaty=? WHERE VypolnenieID=?')
            ->execute([$nazId,(int)$_POST['vypolnil_SotrudnikID'],$_POST['data_vypolneniya'],trim($_POST['rezultat']),trim($_POST['oslozhneniya']),$sum,trim($_POST['status_oplaty']),(int)$_POST['id']]);
        set_flash('success','Выполнение обновлено. Сумма синхронизирована с базовой стоимостью процедуры.');redirect('index.php?page=executions');
    }
    if($a==='delete'){
        db()->prepare('DELETE FROM Vypolneniya WHERE VypolnenieID=?')->execute([(int)$_POST['id']]);
        set_flash('success','Выполнение удалено.');redirect('index.php?page=executions');
    }
}
$nmap=[];$smap=[];
foreach($naz as $n){$nmap[$n['NaznachenieID']]=$n['nomer_istorii'].' / '.trim($n['fio']).' / ['.$n['kod_procedury'].'] '.$n['proc_name'];}
foreach($sotr as $s){$smap[$s['SotrudnikID']]=trim($s['fio']);}
$rows=db()->query('SELECT * FROM Vypolneniya ORDER BY VypolnenieID DESC')->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/../layout.php';
?>
<div class="card">
    <div class="panel-header">
        <div><h2 class="panel-title">Выполнения процедур</h2><p class="panel-subtitle">Фиксация факта выполнения и оплаты. Сумма берется автоматически из «Базовой стоимости» процедуры.</p></div>
        <a class="btn secondary" href="index.php?page=dashboard">← Назад на главную</a>
    </div>
</div>
<div class="card">
    <h3>Добавить выполнение</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="action" value="add">
        <label>Назначение
            <select name="NaznachenieID" id="addNaznachenie" required>
                <?php foreach($naz as $n):?><option value="<?= $n['NaznachenieID'] ?>" data-cost="<?= h($n['bazovaya_stoimost']) ?>">Назначение ID <?= $n['NaznachenieID'] ?> — История <?= h($n['nomer_istorii']) ?> / <?= h(trim($n['fio'])) ?> / [<?= h($n['kod_procedury']) ?>] <?= h($n['proc_name']) ?></option><?php endforeach;?>
            </select>
        </label>
        <label>Исполнитель
            <select name="vypolnil_SotrudnikID" required>
                <?php foreach($sotr as $s):?><option value="<?= $s['SotrudnikID'] ?>"><?= h(trim($s['fio'])) ?></option><?php endforeach;?>
            </select>
        </label>
        <label>Дата выполнения<input type="date" name="data_vypolneniya" required></label>
        <label>Результат<textarea name="rezultat"></textarea></label>
        <label>Осложнения<textarea name="oslozhneniya"></textarea></label>
        <label>Сумма (авто из процедуры)<input type="number" step="0.01" id="addSummaPreview" readonly></label>
        <label>Статус оплаты
            <select name="status_oplaty"><option value="Не оплачено">Не оплачено</option><option value="Частично">Частично</option><option value="Оплачено">Оплачено</option></select>
        </label>
        <button class="btn">Добавить</button>
    </form>
</div>
<div class="card">
    <h3>Список выполнений</h3>
    <div class="table-wrap"><table><tr><th>ID</th><th>Назначение</th><th>Исполнитель</th><th>Дата</th><th>Сумма</th><th>Оплата</th><th>Действия</th></tr>
    <?php foreach($rows as $r):?><tr><td><?= $r['VypolnenieID'] ?></td><td><?= h($nmap[$r['NaznachenieID']] ?? ('#'.$r['NaznachenieID'])) ?></td><td><?= h($smap[$r['vypolnil_SotrudnikID']] ?? $r['vypolnil_SotrudnikID']) ?></td><td><?= h($r['data_vypolneniya']) ?></td><td><?= h($r['summa']) ?></td><td><?= h($r['status_oplaty']) ?></td><td>
    <form method="post" class="form-grid"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= $r['VypolnenieID'] ?>">
    <label>Назначение<select name="NaznachenieID" class="editNaznachenie"><?php foreach($naz as $n):?><option value="<?= $n['NaznachenieID'] ?>" data-cost="<?= h($n['bazovaya_stoimost']) ?>" <?= $n['NaznachenieID']==$r['NaznachenieID']?'selected':'' ?>>Назначение ID <?= $n['NaznachenieID'] ?> — История <?= h($n['nomer_istorii']) ?> / <?= h(trim($n['fio'])) ?> / [<?= h($n['kod_procedury']) ?>] <?= h($n['proc_name']) ?></option><?php endforeach;?></select></label>
    <label>Исполнитель<select name="vypolnil_SotrudnikID"><?php foreach($sotr as $s):?><option value="<?= $s['SotrudnikID'] ?>" <?= (string)$s['SotrudnikID']===(string)$r['vypolnil_SotrudnikID']?'selected':'' ?>><?= h(trim($s['fio'])) ?></option><?php endforeach;?></select></label>
    <label>Дата<input type="date" name="data_vypolneniya" value="<?= h($r['data_vypolneniya']) ?>"></label>
    <label>Результат<textarea name="rezultat"><?= h($r['rezultat']) ?></textarea></label>
    <label>Осложнения<textarea name="oslozhneniya"><?= h($r['oslozhneniya']) ?></textarea></label>
    <label>Сумма (авто из процедуры)<input type="number" step="0.01" value="<?= h($r['summa']) ?>" class="editSummaPreview" readonly></label>
    <label>Статус оплаты<input name="status_oplaty" value="<?= h($r['status_oplaty']) ?>"></label>
    <button class="btn">Сохранить</button></form>
    <form method="post"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['VypolnenieID'] ?>"><button class="btn danger">Удалить</button></form>
    </td></tr><?php endforeach;?></table></div>
</div>

<script>
(function () {
    const addSelect = document.getElementById('addNaznachenie');
    const addSum = document.getElementById('addSummaPreview');
    function syncAdd() {
        const option = addSelect?.options[addSelect.selectedIndex];
        if (addSum && option) addSum.value = option.dataset.cost || '';
    }
    if (addSelect && addSum) {
        addSelect.addEventListener('change', syncAdd);
        syncAdd();
    }

    document.querySelectorAll('.editNaznachenie').forEach((select) => {
        const form = select.closest('form');
        const input = form?.querySelector('.editSummaPreview');
        const sync = () => {
            const option = select.options[select.selectedIndex];
            if (input && option) input.value = option.dataset.cost || '';
        };
        select.addEventListener('change', sync);
        sync();
    });
})();
</script>
<?php require_once __DIR__ . '/../footer.php'; ?>
