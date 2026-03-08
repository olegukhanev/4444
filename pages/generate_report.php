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
        'SELECT v.data_vypolneniya, p.kod_procedury, p.imya procedure_name, v.rezultat
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

    $diagText = '';
    if (!empty($diagRows)) {
        $parts = [];
        foreach ($diagRows as $r) {
            $parts[] = ($r['mkb_code'] . ' ' . $r['imya'] . ' (от ' . $r['data_postanovki'] . ')');
        }
        $diagText = implode('; ', $parts) . '.';
    } else {
        $diagText = 'данные о диагнозах в истории отсутствуют.';
    }

    $procText = '';
    if (!empty($procRows)) {
        $parts = [];
        foreach ($procRows as $r) {
            $item = $r['data_vypolneniya'] . ' — [' . $r['kod_procedury'] . '] ' . $r['procedure_name'];
            if (!empty($r['rezultat'])) {
                $item .= ' (результат: ' . $r['rezultat'] . ')';
            }
            $parts[] = $item;
        }
        $procText = implode('; ', $parts) . '.';
    } else {
        $procText = 'выполненные процедуры по данной истории не зарегистрированы.';
    }

    $filename = "patient_certificate_{$historyId}_{$now}.doc";

    $html = '<html><head><meta charset="utf-8"><style>'
        . 'body{font-family:"Times New Roman",serif;color:#111;font-size:14px;line-height:1.5;padding:30px;}'
        . 'h1{font-size:20px;text-align:center;margin:0 0 8px 0;}'
        . '.doc-no{text-align:center;margin-bottom:20px;}'
        . '.paragraph{text-indent:36px;margin:0 0 10px 0;text-align:justify;}'
        . '.sign{margin-top:36px;display:flex;justify-content:space-between;align-items:flex-end;gap:24px;}'
        . '.line{border-bottom:1px solid #000;display:inline-block;min-width:230px;height:18px;}'
        . '.stamp{margin-top:24px;border:1px dashed #666;height:110px;display:flex;align-items:center;justify-content:center;color:#666;}'
        . '</style></head><body>';

    $docNumber = 'СП-' . date('Y') . '-' . str_pad((string)$historyId, 4, '0', STR_PAD_LEFT);

    $html .= '<h1>СПРАВКА О ПРОХОЖДЕНИИ ЛЕЧЕНИЯ</h1>';
    $html .= '<div class="doc-no">№ ' . h($docNumber) . ' от ' . date('d.m.Y') . '</div>';

    $html .= '<p class="paragraph">Настоящая справка выдана пациенту <strong>' . h($history['fio']) . '</strong>, '
        . 'дата рождения: ' . h($history['birth_date'] ?: 'не указана')
        . ', о том, что он(она) проходил(а) лечение в медицинской организации по истории болезни '
        . '<strong>' . h($history['nomer_istorii']) . '</strong> (ID ' . h((string)$history['IstoriyaID']) . ') '
        . 'в период с ' . h($history['data_otkrytiya']) . ' по ' . h($history['data_zakrytiya'] ?: 'настоящее время') . '.</p>';

    $html .= '<p class="paragraph">Текущий статус истории болезни: <strong>' . h($history['ist_status']) . '</strong>. '
        . 'Основной диагноз: <strong>' . h($mainText) . '</strong>.</p>';

    $html .= '<p class="paragraph">Диагнозы, указанные в истории болезни: ' . h($diagText) . '</p>';

    $html .= '<p class="paragraph">Сведения о выполненных процедурах: ' . h($procText) . '</p>';

    $html .= '<p class="paragraph">Справка выдана для предоставления по месту требования.</p>';

    $html .= '<div class="sign"><div>Лечащий врач: <span class="line"></span></div><div>Подпись: <span class="line"></span></div></div>';
    $html .= '<div class="stamp">Место для печати медицинской организации</div>';
    $html .= '</body></html>';

    file_put_contents($dir . '/' . $filename, $html);
    set_flash('success', 'Справка сформирована: ' . $filename);
    redirect('index.php?page=reports&file=' . urlencode($filename));
}

set_flash('error', 'Неизвестный тип отчета.');
redirect('index.php?page=reports');
