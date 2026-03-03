<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
    global $pdo;  // Use the globally defined PDO connection
    return $pdo;
}
