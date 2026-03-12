<?php
require_once __DIR__ . '/db.php';
session_start();

function user() { return $_SESSION['u'] ?? null; }
function is_logged_in(): bool { return user() !== null; }
function is_admin(): bool { return is_logged_in() && (user()['role_name'] ?? '') === 'admin'; }
function is_user_role(): bool { return is_logged_in() && (user()['role_name'] ?? '') === 'user'; }

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function yes_no_label(?string $value): string {
    return $value === 'Yes' ? 'Да' : 'Нет';
}

function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function require_login(): void {
    if (!is_logged_in()) {
        set_flash('error', 'Сначала выполните вход.');
        redirect('index.php?page=login');
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        set_flash('error', 'Доступ только для администратора.');
        redirect('index.php?page=dashboard');
    }
}

function require_user_role(): void {
    require_login();
    if (!is_user_role()) {
        set_flash('error', 'Раздел доступен только для роли user.');
        redirect('index.php?page=users');
    }
}

function login(string $login, string $password): ?string {
    $st = db()->prepare('SELECT * FROM AuthUsers WHERE auth_login = ? LIMIT 1');
    $st->execute([$login]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u || ($u['active'] ?? 'No') !== 'Yes' || !password_verify($password, $u['password_hash'])) {
        return 'Неверный логин или пароль или учетная запись неактивна.';
    }
    $_SESSION['u'] = $u;
    return null;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function register(string $login, string $name, string $password, string $password2): ?string {
    if ($password !== $password2) {
        return 'Пароли не совпадают.';
    }
    if (mb_strlen($password) < 4) {
        return 'Минимальная длина пароля: 4 символа.';
    }

    $exists = db()->prepare('SELECT COUNT(*) FROM AuthUsers WHERE auth_login = ?');
    $exists->execute([$login]);
    if ((int)$exists->fetchColumn() > 0) {
        return 'Логин уже занят.';
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    db()->prepare("INSERT INTO AuthUsers(auth_login, password_hash, role_name, display_name, active, created_at) VALUES (?, ?, 'user', ?, 'Yes', CURDATE())")
        ->execute([$login, $hash, $name]);

    return null;
}
