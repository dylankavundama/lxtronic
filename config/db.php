<?php
// ============================================================
// Chargement sécurisé de la configuration depuis .env
// ============================================================
function load_env(string $path): void {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

load_env(dirname(__DIR__) . '/.env');

$host    = $_ENV['DB_HOST']    ?? 'localhost';
$db      = $_ENV['DB_NAME']    ?? '';
$user    = $_ENV['DB_USER']    ?? '';
$pass    = $_ENV['DB_PASS']    ?? '';
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Ne pas divulguer les détails en production
    error_log('DB Connection Error: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed.']));
}

function fx_log_error($level, $message, $file, $line) {
    global $pdo;
    if (!$pdo) return;
    try {
        $pdo->prepare("INSERT INTO system_logs (level, message, file, line) VALUES (?, ?, ?, ?)")
            ->execute([$level, $message, $file, $line]);
    } catch(\Exception $e) {}
}

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return; 
    if (in_array($errno, [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED])) return;
    fx_log_error("WARNING_V ($errno)", $errstr, $errfile, $errline);
});

set_exception_handler(function($ex) {
    fx_log_error("EXCEPTION", $ex->getMessage(), $ex->getFile(), $ex->getLine());
});
?>
