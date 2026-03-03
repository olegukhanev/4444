<?php
require_once __DIR__ . '/../auth.php';
logout();
set_flash('success', 'Вы вышли из системы.');
redirect('index.php?page=login');
