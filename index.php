<?php
$page = $_GET['page'] ?? 'dashboard';
$map = [
    'login' => 'pages/login.php',
    'register' => 'pages/register.php',
    'logout' => 'pages/logout.php',
    'dashboard' => 'pages/dashboard.php',
    'users' => 'pages/users.php',
    'patients' => 'pages/patients.php',
    'departments' => 'pages/departments.php',
    'staff' => 'pages/staff.php',
    'histories' => 'pages/histories.php',
    'diagnoses' => 'pages/diagnoses.php',
    'procedures' => 'pages/procedures.php',
    'appointments' => 'pages/appointments.php',
    'executions' => 'pages/executions.php',
    'reports' => 'pages/reports.php',
    'generate_report' => 'pages/generate_report.php',
];

if (isset($map[$page])) {
    require __DIR__ . '/' . $map[$page];
} else {
    http_response_code(404);
    echo '404';
}
