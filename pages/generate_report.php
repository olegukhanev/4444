<?php
require_once __DIR__ . '/../auth.php';
require_user_role();

$type = $_GET['type'] ?? '';
$d1 = trim($_GET['d1'] ?? '');
$d2 = trim($_GET['d2'] ?? '');
$historyId = (int)($_GET['IstoriyaID'] ?? 0);

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

    $html = '<html><head><meta charset="utf-8"><style>body{font-family:Calibri;} h1{color:#0b6aa2;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #555;padding:6px;} th{background:#dfefff;} .total{font-weight:bold;background:#f1f7ff;} .meta{margin-bottom:12px;color:#333;}</style></head><body>';
    $html .= '<h1>' . h($title) . '</h1>';
    $html .= '<div class="meta">Период: ' . h($periodText) . '<br>Дата формирования: ' . date('d.m.Y H:i') . '</div><table>';
    $html .= '<tr><th>Дата</th><th>Процедура</th><th>Результат</th><th>Сумма</th></tr>';
    foreach ($rows as $r) {
        $html .= '<tr><td>' . h($r['data_vypolneniya']) . '</td><td>' . h($r['procedure_name']) . '</td><td>' . h($r['rezultat']) . '</td><td>' . h(number_format((float)$r['summa'], 2, '.', '')) . '</td></tr>';
    }
    $html .= '<tr class="total"><td colspan="3">Итого процедур: ' . h((string)$totalCount) . '</td><td>' . h(number_format($totalSum, 2, '.', '')) . '</td></tr>';
    $html .= '</table></body></html>';

    file_put_contents($dir . '/' . $filename, $html);
    set_flash('success', 'Отчёт сформирован: ' . $filename);
    redirect('index.php?page=reports&file=' . urlencode($filename));
}

if ($type === 'patient_certificate') {
    if ($historyId <= 0) {
        set_flash('error', 'Выберите историю болезни для формирования справки.');
        redirect('index.php?page=reports');
    }

    $stHistory = db()->prepare(
        'SELECT h.IstoriyaID, h.nomer_istorii, h.data_otkrytiya, h.data_zakrytiya, h.ist_status,
                CONCAT(p.last_name, " ", p.first_name, " ", COALESCE(p.middle_name, "")) fio,
                p.birth_date
         FROM IstoriiBolezni h
         JOIN Pacienty p ON p.PacientID=h.PacientID
         WHERE h.IstoriyaID=? LIMIT 1'
    );
    $stHistory->execute([$historyId]);
    $history = $stHistory->fetch(PDO::FETCH_ASSOC);

    if (!$history) {
        set_flash('error', 'История болезни не найдена.');
        redirect('index.php?page=reports');
    }

    $stDiag = db()->prepare(
        'SELECT d.mkb_code, d.imya, b.data_postanovki, b.is_osnovnoy
         FROM IstDiagnozy b
         JOIN Diagnozy d ON d.DiagnozID=b.DiagnozID
         WHERE b.IstoriyaID=?
         ORDER BY b.data_postanovki DESC, b.IstDiagnozID DESC'
    );
    $stDiag->execute([$historyId]);
    $diagRows = $stDiag->fetchAll(PDO::FETCH_ASSOC);

    $stProc = db()->prepare(
        'SELECT v.data_vypolneniya, p.kod_procedury, p.imya procedure_name, v.rezultat,
                COALESCE(v.summa,0) summa
         FROM Vypolneniya v
         JOIN Naznacheniya n ON n.NaznachenieID=v.NaznachenieID
         JOIN SprProcedur p ON p.ProceduraID=n.ProceduraID
         WHERE n.IstoriyaID=?
         ORDER BY v.data_vypolneniya DESC, v.VypolnenieID DESC'
    );
    $stProc->execute([$historyId]);
    $procRows = $stProc->fetchAll(PDO::FETCH_ASSOC);

    $diagMain = array_filter($diagRows, static fn($r) => ($r['is_osnovnoy'] ?? 'No') === 'Yes');
    $mainText = 'не указан';
    if (!empty($diagMain)) {
        $first = array_values($diagMain)[0];
        $mainText = $first['mkb_code'] . ' ' . $first['imya'];
    } elseif (!empty($diagRows)) {
        $first = $diagRows[0];
        $mainText = $first['mkb_code'] . ' ' . $first['imya'];
    }

    $filename = "patient_certificate_{$historyId}_{$now}.doc";

    $html = '<html><head><meta charset="utf-8"><style>'
        . 'body{font-family:"Times New Roman",serif;color:#111;font-size:14px;line-height:1.4;padding:20px;}'
        . 'h1{font-size:20px;text-align:center;margin:0 0 6px 0;}'
        . '.doc-no{text-align:center;margin-bottom:16px;}'
        . '.section{margin-bottom:12px;}'
        . 'table{border-collapse:collapse;width:100%;margin-top:8px;}'
        . 'th,td{border:1px solid #444;padding:6px;vertical-align:top;}'
        . 'th{background:#f5f5f5;}'
        . '.sign{margin-top:26px;display:flex;justify-content:space-between;align-items:flex-end;gap:24px;}'
        . '.line{border-bottom:1px solid #000;display:inline-block;min-width:220px;height:18px;}'
        . '.stamp{margin-top:20px;border:1px dashed #666;height:100px;display:flex;align-items:center;justify-content:center;color:#666;}'
        . '</style></head><body>';

    $html .= '<h1>СПРАВКА О ПРОХОЖДЕНИИ ЛЕЧЕНИЯ</h1>';
    $html .= '<div class="doc-no">№ ' . h('СП-' . date('Y') . '-' . str_pad((string)$historyId, 4, '0', STR_PAD_LEFT))
        . ' от ' . date('d.m.Y') . '</div>';

    $html .= '<div class="section">'
        . 'Выдана пациенту: <strong>' . h($history['fio']) . '</strong><br>'
        . 'Дата рождения: ' . h($history['birth_date'] ?: '—') . '<br>'
        . 'История болезни: <strong>' . h($history['nomer_istorii']) . '</strong> (ID ' . h((string)$history['IstoriyaID']) . ')<br>'
        . 'Период лечения: ' . h($history['data_otkrytiya']) . ' — ' . h($history['data_zakrytiya'] ?: 'по настоящее время') . '<br>'
        . 'Статус истории: ' . h($history['ist_status'])
        . '</div>';

    $html .= '<div class="section">Основной диагноз: <strong>' . h($mainText) . '</strong></div>';

    $html .= '<div class="section"><strong>Диагнозы по истории болезни:</strong><table>'
        . '<tr><th>Дата</th><th>Код МКБ</th><th>Наименование</th><th>Основной диагноз</th></tr>';
    if (empty($diagRows)) {
        $html .= '<tr><td colspan="4">Нет данных</td></tr>';
    } else {
        foreach ($diagRows as $r) {
            $html .= '<tr><td>' . h($r['data_postanovki']) . '</td><td>' . h($r['mkb_code']) . '</td><td>' . h($r['imya']) . '</td><td>' . h(($r['is_osnovnoy'] ?? 'No') === 'Yes' ? 'Да' : 'Нет') . '</td></tr>';
        }
    }
    $html .= '</table></div>';

    $html .= '<div class="section"><strong>Выполненные процедуры:</strong><table>'
        . '<tr><th>Дата</th><th>Код</th><th>Процедура</th><th>Результат</th><th>Сумма</th></tr>';
    if (empty($procRows)) {
        $html .= '<tr><td colspan="5">Нет данных</td></tr>';
    } else {
        foreach ($procRows as $r) {
            $html .= '<tr><td>' . h($r['data_vypolneniya']) . '</td><td>' . h($r['kod_procedury']) . '</td><td>' . h($r['procedure_name']) . '</td><td>' . h($r['rezultat']) . '</td><td>' . h(number_format((float)$r['summa'], 2, '.', '')) . '</td></tr>';
        }
    }
    $html .= '</table></div>';

    $html .= '<div class="section">Справка выдана по месту требования.</div>';
    $html .= '<div class="sign"><div>Лечащий врач: <span class="line"></span></div><div>Подпись: <span class="line"></span></div></div>';
    $html .= '<div class="stamp">Место для печати медицинской организации</div>';
    $html .= '</body></html>';

    file_put_contents($dir . '/' . $filename, $html);
    set_flash('success', 'Справка сформирована: ' . $filename);
    redirect('index.php?page=reports&file=' . urlencode($filename));
}

set_flash('error', 'Неизвестный тип отчета.');
redirect('index.php?page=reports');
