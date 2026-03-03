<?php
require_once __DIR__ . '/../auth.php';
require_user_role();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'add') {
        db()->prepare('INSERT INTO SprProcedur(kod_procedury,imya,tip_procedury,bazovaya_stoimost,active) VALUES(?,?,?,?,?)')
            ->execute([trim($_POST['kod_procedury']), trim($_POST['imya']), trim($_POST['tip_procedury']), $_POST['bazovaya_stoimost'] ?: null, ($_POST['active'] ?? 'Yes') === 'Yes' ? 'Yes' : 'No']);
        set_flash('success', 'Процедура добавлена.'); redirect('index.php?page=procedures');
    }
    if ($a === 'edit') {
        db()->prepare('UPDATE SprProcedur SET kod_procedury=?, imya=?, tip_procedury=?, bazovaya_stoimost=?, active=? WHERE ProceduraID=?')
            ->execute([trim($_POST['kod_procedury']), trim($_POST['imya']), trim($_POST['tip_procedury']), $_POST['bazovaya_stoimost'] ?: null, ($_POST['active'] ?? 'Yes') === 'Yes' ? 'Yes' : 'No', (int)$_POST['id']]);
        set_flash('success', 'Процедура обновлена.'); redirect('index.php?page=procedures');
    }
    if ($a === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $cnt = db()->prepare('SELECT COUNT(*) FROM Naznacheniya WHERE ProceduraID=?');
        $cnt->execute([$id]);
        if ((int)$cnt->fetchColumn() > 0) {
            set_flash('error', 'Нельзя удалить процедуру: она используется в назначениях.');
        } else {
            db()->prepare('DELETE FROM SprProcedur WHERE ProceduraID=?')->execute([$id]);
            set_flash('success', 'Процедура удалена.');
        }
        redirect('index.php?page=procedures');
    }
}
$q = trim($_GET['q'] ?? '');
$sql='SELECT * FROM SprProcedur';$params=[];
if($q){$sql.=' WHERE kod_procedury LIKE ? OR imya LIKE ?';$params=["%$q%","%$q%"];} 
$sql.=' ORDER BY imya';
$st=db()->prepare($sql);$st->execute($params);$rows=$st->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/../layout.php';
?>
<div class="card">
    <div class="panel-header">
        <div><h2 class="panel-title">Справочник процедур</h2><p class="panel-subtitle">Каталог доступных медицинских процедур.</p></div>
        <a class="btn secondary" href="index.php?page=dashboard">← Назад на главную</a>
    </div>
    <form method="get" class="inline-form"><input type="hidden" name="page" value="procedures"><input name="q" value="<?= h($q) ?>" placeholder="Поиск по коду/названию"><div class="actions"><button class="btn">Поиск</button><a class="btn secondary" href="index.php?page=procedures">Очистить</a></div></form>
</div>
<div class="card"><h3>Добавить процедуру</h3>
<form method="post" class="form-grid"><input type="hidden" name="action" value="add">
<label>Код<input name="kod_procedury" required></label>
<label>Название<input name="imya" required></label>
<label>Тип<select name="tip_procedury"><option value="Манипуляция">Манипуляция</option><option value="Процедура">Процедура</option><option value="Диагностическая">Диагностическая</option></select></label>
<label>Базовая стоимость<input type="number" step="0.01" name="bazovaya_stoimost"></label>
<label>Активность<select name="active"><option value="Yes">Yes</option><option value="No">No</option></select></label>
<button class="btn">Добавить</button></form></div>
<div class="card"><h3>Список процедур</h3><div class="table-wrap"><table><tr><th>ID</th><th>Код</th><th>Название</th><th>Тип</th><th>Цена</th><th>Active</th><th>Действия</th></tr>
<?php foreach($rows as $r):?><tr><td><?= $r['ProceduraID'] ?></td><td><?= h($r['kod_procedury']) ?></td><td><?= h($r['imya']) ?></td><td><?= h($r['tip_procedury']) ?></td><td><?= h($r['bazovaya_stoimost']) ?></td><td><?= h($r['active']) ?></td><td>
<form method="post" class="form-grid"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= $r['ProceduraID'] ?>">
<label>Код<input name="kod_procedury" value="<?= h($r['kod_procedury']) ?>"></label><label>Название<input name="imya" value="<?= h($r['imya']) ?>"></label><label>Тип<input name="tip_procedury" value="<?= h($r['tip_procedury']) ?>"></label><label>Цена<input type="number" step="0.01" name="bazovaya_stoimost" value="<?= h($r['bazovaya_stoimost']) ?>"></label><label>Active<select name="active"><option value="Yes" <?= $r['active']==='Yes'?'selected':'' ?>>Yes</option><option value="No" <?= $r['active']==='No'?'selected':'' ?>>No</option></select></label>
<button class="btn">Сохранить</button></form>
<form method="post"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['ProceduraID'] ?>"><button class="btn danger">Удалить</button></form></td></tr><?php endforeach;?></table></div></div>
<?php require_once __DIR__ . '/../footer.php'; ?>
