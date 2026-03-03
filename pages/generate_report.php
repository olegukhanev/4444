<?php
require_once __DIR__ . '/../auth.php';
require_user_role();

$type = $_GET['type'] ?? '';
$d1 = trim($_GET['d1'] ?? '');
$d2 = trim($_GET['d2'] ?? '');

if ($d1 !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d1)) {
    set_flash('error', 'Некорректная дата "с".');
    redirect('index.php?page=reports');
}
if ($d2 !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d2)) {
    set_flash('error', 'Некорректная дата "по".');
    redirect('index.php?page=reports');
}
if ($d1 !== '' && $d2 !== '' && $d1 > $d2) {
    set_flash('error', 'Дата "с" не может быть больше даты "по".');
    redirect('index.php?page=reports');
}

$now = date('Ymd_His');
$dir = __DIR__ . '/../generated_reports';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$where = [];
$params = [];
if ($d1 !== '') {
    $where[] = 'v.data_vypolneniya >= ?';
    $params[] = $d1;
}
if ($d2 !== '') {
    $where[] = 'v.data_vypolneniya <= ?';
    $params[] = $d2;
}
$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
$periodText = ($d1 ?: 'начала учета') . ' — ' . ($d2 ?: 'текущей даты');

if ($type === 'procedures') {
    $sql = "SELECT v.data_vypolneniya, p.imya procedure_name, v.rezultat, COALESCE(v.summa,0) summa
            FROM Vypolneniya v
            JOIN Naznacheniya n ON n.NaznachenieID=v.NaznachenieID
            JOIN SprProcedur p ON p.ProceduraID=n.ProceduraID
            $whereSql
            ORDER BY v.data_vypolneniya DESC
            LIMIT 1000";
    $st = db()->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $filename = "report_procedures_{$now}.doc";
    $title = 'Отчёт: Выполненные процедуры';

    $totalCount = count($rows);
    $totalSum = 0;
    foreach ($rows as $r) {
        $totalSum += (float)$r['summa'];
    }
} elseif ($type === 'payments') {
    $sql = "SELECT v.status_oplaty, COUNT(*) cnt, SUM(COALESCE(v.summa,0)) total_sum
            FROM Vypolneniya v
            $whereSql
            GROUP BY v.status_oplaty
            ORDER BY v.status_oplaty";
    $st = db()->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $filename = "report_payments_{$now}.doc";
    $title = 'Отчёт: Финансовая сводка по оплатам';

    $totalCount = 0;
    $totalSum = 0;
    foreach ($rows as $r) {
        $totalCount += (int)$r['cnt'];
        $totalSum += (float)$r['total_sum'];
    }
} else {
    set_flash('error', 'Неизвестный тип отчета.');
    redirect('index.php?page=reports');
}

$html = '<html><head><meta charset="utf-8"><style>body{font-family:Calibri;} h1{color:#0b6aa2;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #555;padding:6px;} th{background:#dfefff;} .total{font-weight:bold;background:#f1f7ff;} .meta{margin-bottom:12px;color:#333;}</style></head><body>';
$html .= '<h1>' . h($title) . '</h1>';
$html .= '<div class="meta">Период: ' . h($periodText) . '<br>Дата формирования: ' . date('d.m.Y H:i') . '</div><table>';
if ($type === 'procedures') {
    $html .= '<tr><th>Дата</th><th>Процедура</th><th>Результат</th><th>Сумма</th></tr>';
    foreach ($rows as $r) {
        $html .= '<tr><td>' . h($r['data_vypolneniya']) . '</td><td>' . h($r['procedure_name']) . '</td><td>' . h($r['rezultat']) . '</td><td>' . h(number_format((float)$r['summa'], 2, '.', '')) . '</td></tr>';
    }
    $html .= '<tr class="total"><td colspan="3">Итого процедур: ' . h((string)$totalCount) . '</td><td>' . h(number_format($totalSum, 2, '.', '')) . '</td></tr>';
} else {
    $html .= '<tr><th>Статус оплаты</th><th>Количество</th><th>Сумма</th></tr>';
    foreach ($rows as $r) {
        $html .= '<tr><td>' . h($r['status_oplaty']) . '</td><td>' . h($r['cnt']) . '</td><td>' . h(number_format((float)$r['total_sum'], 2, '.', '')) . '</td></tr>';
    }
    $html .= '<tr class="total"><td>Итог</td><td>' . h((string)$totalCount) . '</td><td>' . h(number_format($totalSum, 2, '.', '')) . '</td></tr>';
}
$html .= '</table></body></html>';

file_put_contents($dir . '/' . $filename, $html);
set_flash('success', 'Отчёт сформирован: ' . $filename);
redirect('index.php?page=reports&file=' . urlencode($filename));
