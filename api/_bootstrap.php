<?php
declare(strict_types=1);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function respond($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(int $status, string $error, string $message = ''): void
{
    respond(['error' => $error, 'message' => $message], $status);
}

set_exception_handler(function (Throwable $e): void {
    error_log('[mom-writer] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    fail(500, 'server_error', 'เกิดข้อผิดพลาดที่เซิร์ฟเวอร์');
});

function config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $file = __DIR__ . '/config.php';
    if (is_file($file)) {
        $cfg = require $file;
    } elseif (getenv('DB_HOST')) {
        $cfg = ['db' => [
            'host' => getenv('DB_HOST'),
            'port' => (int)(getenv('DB_PORT') ?: 3306),
            'name' => getenv('DB_NAME') ?: 'mom_writer',
            'user' => getenv('DB_USER') ?: 'mom',
            'pass' => getenv('DB_PASS') ?: '',
        ]];
    } else {
        fail(500, 'not_configured', 'ยังไม่ได้ตั้งค่าฐานข้อมูล (api/config.php)');
    }
    $cfg['max_body_bytes'] = $cfg['max_body_bytes'] ?? 5000000;
    return $cfg;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $c = config()['db'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'] ?? 3306, $c['name']);
    try {
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log('[mom-writer] DB connect failed: ' . $e->getMessage());
        fail(503, 'db_unavailable', 'เชื่อมต่อฐานข้อมูลไม่ได้');
    }
    return $pdo;
}

function method(): string
{
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

function body(): array
{
    $max = config()['max_body_bytes'];
    $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($len > $max) {
        fail(413, 'too_large', 'ข้อมูลใหญ่เกินกำหนด');
    }
    $raw = file_get_contents('php://input', false, null, 0, $max + 1);
    if ($raw === false || strlen($raw) > $max) {
        fail(413, 'too_large', 'ข้อมูลใหญ่เกินกำหนด');
    }
    $json = json_decode($raw === '' ? '{}' : $raw, true);
    if (!is_array($json)) {
        fail(400, 'bad_json', 'รูปแบบข้อมูลไม่ถูกต้อง');
    }
    return $json;
}

function query_id(): ?int
{
    if (!isset($_GET['id'])) {
        return null;
    }
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        fail(400, 'bad_id', 'รหัสไม่ถูกต้อง');
    }
    return $id;
}

function str_limit($v, int $max): string
{
    $s = is_string($v) ? trim($v) : '';
    return mb_substr($s, 0, $max);
}

function like_escape(string $s): string
{
    return '%' . addcslashes($s, '%_\\') . '%';
}
