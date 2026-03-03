<?php
require_once __DIR__ . '/../layout.php';
require_login();
?>
<div class="card">
<h3>Отчёт 1: Выполненные процедуры</h3>
<form method="get">
  <label>Дата с:</label><input type="date" name="d1" />
  <label>Дата по:</label><input type="date" name="d2" />
  <button class="btn">Показать</button>
</form>
</div>
<?php require_once __DIR__.'/../footer.php'; ?>
