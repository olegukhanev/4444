<?php
require_once __DIR__ . '/../auth.php';
require_user_role();
require_once __DIR__ . '/../layout.php';
$d1 = trim($_GET['d1'] ?? '');
$d2 = trim($_GET['d2'] ?? '');
?>
<div class="card">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">Отчёты</h2>
            <p class="panel-subtitle">Формирование документов по выбранному периоду с итогами.</p>
        </div>
        <a class="btn secondary" href="index.php?page=dashboard">← Назад на главную</a>
    </div>

    <?php if (!empty($_GET['file'])): ?>
        <div class="flash success">Отчёт создан: <a href="generated_reports/<?= h($_GET['file']) ?>" target="_blank">открыть/скачать файл</a></div>
    <?php endif; ?>

    <div class="actions">
        <div class="report-card">
            <h3>Отчёт 1: Выполненные процедуры</h3>
            <p class="small">Список выполнений по дате, процедуре, результату и сумме + итоги по количеству и сумме.</p>
            <form method="get" class="form-grid">
                <input type="hidden" name="page" value="generate_report">
                <input type="hidden" name="type" value="procedures">
                <label>Дата с <input type="date" name="d1" value="<?= h($d1) ?>"></label>
                <label>Дата по <input type="date" name="d2" value="<?= h($d2) ?>"></label>
                <button class="btn">Сформировать отчёт</button>
            </form>
        </div>
        <div class="report-card">
            <h3>Отчёт 2: Финансовая сводка оплат</h3>
            <p class="small">Сводка по статусам оплаты за период + общий итог.</p>
            <form method="get" class="form-grid">
                <input type="hidden" name="page" value="generate_report">
                <input type="hidden" name="type" value="payments">
                <label>Дата с <input type="date" name="d1" value="<?= h($d1) ?>"></label>
                <label>Дата по <input type="date" name="d2" value="<?= h($d2) ?>"></label>
                <button class="btn">Сформировать отчёт</button>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
