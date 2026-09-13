<?php
$password = trim(file_get_contents('/run/oj-secrets/db-password'));
try {
    $db = new PDO('mysql:host=db;dbname=jol', 'hustoj', $password);
    $db->query('SELECT 1 FROM users LIMIT 1');
    $response = file_get_contents('http://127.0.0.1/index.php');
    exit($response === false ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "Web/database health check failed\n");
    exit(1);
}
