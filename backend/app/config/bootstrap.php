<?php
declare(strict_types=1);

// NexoraPlayer Backend — bootstrap.php
// Mismo patrón que AlasTV. Deploy en portal.alastv.com (IIS + Plesk + Windows)

define('NEXORA_VERSION', '1.0.0');
define('NEXORA_ROOT', dirname(__DIR__, 2));

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors',     '1');
date_default_timezone_set('America/Guatemala');

// ── Config privada (nunca en Git) ────────────────────────────────────────────
$_privateConfig = NEXORA_ROOT . '/private/config.php';
if (!file_exists($_privateConfig)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => ['code' => 'NO_CONFIG', 'message' => 'private/config.php no encontrado en el servidor.']]);
    exit;
}
require_once $_privateConfig;

// ── Constantes requeridas ────────────────────────────────────────────────────
foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $_required) {
    if (!defined($_required)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => ['code' => 'MISSING_CONST', 'message' => "Constante requerida no definida: $_required"]]);
        exit;
    }
}

// ── Defaults opcionales ──────────────────────────────────────────────────────
if (!defined('TV_PLATFORMS_ALLOWED')) {
    define('TV_PLATFORMS_ALLOWED', 'roku,tizen,webos,android_tv,fire_tv,appletv,web');
}

if (!defined('LIVEACCESS_DB_NAME')) {
    define('LIVEACCESS_DB_NAME', DB_NAME);
}

// ── Auto-detección de entorno (mismo patrón que AlasTV) ─────────────────────
$_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
if ($_host === 'portal.alastv.com') {
    define('APP_ENV',   'prod');
    define('APP_DEBUG', false);
} elseif (strpos($_host, 'staging') !== false) {
    define('APP_ENV',   'staging');
    define('APP_DEBUG', true);
} else {
    define('APP_ENV',   'dev');
    define('APP_DEBUG', true);
}

// ── Security modules ─────────────────────────────────────────────────────────
require_once NEXORA_ROOT . '/app/services/Logger.php';
require_once NEXORA_ROOT . '/app/services/RateLimiter.php';
require_once NEXORA_ROOT . '/app/services/RequestValidator.php';
require_once NEXORA_ROOT . '/app/services/SecurityHeaders.php';
require_once NEXORA_ROOT . '/app/services/ErrorHandler.php';

// Initialize services
Logger::init(NEXORA_ROOT . '/storage/logs');
RateLimiter::init(NEXORA_ROOT . '/storage/cache');
ErrorHandler::register();

// Apply security headers based on environment
$_isProduction = defined('APP_ENV') && APP_ENV === 'prod';
SecurityHeaders::setSecurityHeaders($_isProduction);

// ── CORS (whitelist) ───────────────────────────────────────────────────────
if (!defined('NEXORA_ALLOWED_ORIGINS')) {
    define('NEXORA_ALLOWED_ORIGINS', implode(',', [
        'https://portal.alastv.com',
        'https://www.portal.alastv.com',
        'http://localhost',
        'http://127.0.0.1',
    ]));
}

function nexoraApplyCorsHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    $origin = isset($_SERVER['HTTP_ORIGIN']) ? trim((string)$_SERVER['HTTP_ORIGIN']) : '';
    $allowedOrigins = array_values(array_filter(array_map('trim', explode(',', (string)NEXORA_ALLOWED_ORIGINS))));
    $allowAny = defined('APP_ENV') && APP_ENV !== 'prod';

    if ($origin !== '' && ($allowAny || in_array($origin, $allowedOrigins, true))) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Vary: Origin');
    } elseif ($allowAny) {
        header('Access-Control-Allow-Origin: *');
    }

    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

nexoraApplyCorsHeaders();

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

// Log request start
$_startTime = microtime(true);
$_method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$_action = $_SERVER['REQUEST_URI'] ?? '/';
register_shutdown_function(function () use ($_startTime, $_method, $_action) {
    $_duration = microtime(true) - $_startTime;
    $_code = http_response_code();
    Logger::httpRequest($_method, $_action, $_code, $_duration);
});

// ── Helpers globales ─────────────────────────────────────────────────────────
function nexoraResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function nexoraSuccess($data = [], string $status = 'ok', string $message = ''): void
{
    nexoraResponse([
        'success'     => true,
        'status'      => $status,
        'message'     => $message ?: null,
        'server_time' => date('c'),
        'data'        => $data,
    ]);
}

function nexoraError(string $code, string $message, int $http = 400): void
{
    nexoraResponse([
        'success'     => false,
        'status'      => 'error',
        'message'     => $message,
        'server_time' => date('c'),
        'data'        => [],
        'error'       => ['code' => $code, 'message' => $message],
    ], $http);
}

function nexoraReadBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
