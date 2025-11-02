<?php
// Database connection using PDO
// TODO: change credentials accordingly
$DB_HOST = 'localhost';
$DB_NAME = 'silantra_schema';
$DB_USER = 'root';
$DB_PASS = 'Kdkprncsdsindy230856!))(';
$DB_PORT = '3306';

$DB_HOST = rtrim($DB_HOST);
$DB_NAME = rtrim($DB_NAME);
$DB_USER = rtrim($DB_USER);
$DB_PASS = rtrim($DB_PASS);

$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

try {
  $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode([
    'error' => 'DB connection failed',
    'detail' => $e->getMessage(),
    'host' => $DB_HOST,
    'port' => $DB_PORT,
    'db' => $DB_NAME,
    'user' => $DB_USER,
  ]);
  exit;
}
