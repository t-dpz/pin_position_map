<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=127.0.0.1;dbname=webdev;charset=utf8mb4',
            'dev', 'devpw',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
    }
    return $pdo;
}
