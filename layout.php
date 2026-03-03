<?php
require_once __DIR__ . '/auth.php';
$flash = get_flash();
$currentPage = $_GET['page'] ?? 'dashboard';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ГБУЗ "Городская больница" г. Бугуруслана — АИС "Процедурный кабинет"</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="top">
    <div class="brand">
        <img src="assets/logo.svg" alt="Логотип ГБУЗ" class="brand-logo">
        <div>
            <h1>ГБУЗ "Городская больница" г. Бугуруслана</h1>
            <div class="subtitle">АИС "Процедурный кабинет" — учет выполненных процедур и манипуляций</div>
        </div>
    </div>
    <?php if (user()): ?>
        <div class="user-box">
            <div><strong><?= h(user()['display_name']) ?></strong></div>
            <div>Роль: <?= h(user()['role_name']) ?></div>
            <a class="btn ghost" href="index.php?page=logout">Выход</a>
        </div>
    <?php endif; ?>
</header>

<?php if (user()): ?>
<nav class="tabs">
    <?php if (is_admin()): ?>
        <a class="tab <?= $currentPage === 'users' ? 'active' : '' ?>" href="index.php?page=users">Управление пользователями</a>
    <?php else: ?>
        <a class="tab <?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="index.php?page=dashboard">Главная</a>
        <a class="tab <?= $currentPage === 'patients' ? 'active' : '' ?>" href="index.php?page=patients">Пациенты</a>
        <a class="tab <?= $currentPage === 'departments' ? 'active' : '' ?>" href="index.php?page=departments">Отделения</a>
        <a class="tab <?= $currentPage === 'staff' ? 'active' : '' ?>" href="index.php?page=staff">Сотрудники</a>
        <a class="tab <?= $currentPage === 'histories' ? 'active' : '' ?>" href="index.php?page=histories">Истории болезни</a>
        <a class="tab <?= $currentPage === 'diagnoses' ? 'active' : '' ?>" href="index.php?page=diagnoses">Диагнозы</a>
        <a class="tab <?= $currentPage === 'procedures' ? 'active' : '' ?>" href="index.php?page=procedures">Процедуры</a>
        <a class="tab <?= $currentPage === 'appointments' ? 'active' : '' ?>" href="index.php?page=appointments">Назначения</a>
        <a class="tab <?= $currentPage === 'executions' ? 'active' : '' ?>" href="index.php?page=executions">Выполнения</a>
        <a class="tab <?= $currentPage === 'reports' ? 'active' : '' ?>" href="index.php?page=reports">Отчёты</a>
    <?php endif; ?>
</nav>
<?php endif; ?>

<main class="wrap">
    <?php if ($flash): ?>
        <div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>
