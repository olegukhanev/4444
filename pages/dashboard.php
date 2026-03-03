<?php
require_once __DIR__ . '/../auth.php';
require_user_role();
require_once __DIR__ . '/../layout.php';
?>
<div class="card">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">Главная панель</h2>
            <p class="panel-subtitle">Выберите раздел для работы с данными.</p>
        </div>
    </div>
    <div class="actions">
        <a class="btn" href="index.php?page=patients">Пациенты</a>
        <a class="btn" href="index.php?page=departments">Отделения</a>
        <a class="btn" href="index.php?page=staff">Сотрудники</a>
        <a class="btn" href="index.php?page=histories">Истории болезни</a>
        <a class="btn" href="index.php?page=diagnoses">Диагнозы</a>
        <a class="btn" href="index.php?page=procedures">Процедуры</a>
        <a class="btn" href="index.php?page=appointments">Назначения</a>
        <a class="btn" href="index.php?page=executions">Выполнения</a>
        <a class="btn" href="index.php?page=reports">Отчёты</a>
    </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
