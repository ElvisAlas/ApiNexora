<?php
declare(strict_types=1);

class Logger
{
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';

    private static string $logDir = '';
    private static string $logFile = '';
    private static array $levels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4,
    ];

    public static function init(string $logDir): void
    {
        self::$logDir = $logDir;

        if (!is_dir(self::$logDir)) {
            @mkdir(self::$logDir, 0755, true);
        }

        $date = date('Y-m-d');
        self::$logFile = self::$logDir . "/nexora-{$date}.log";
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log(self::LEVEL_WARNING, $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log(self::LEVEL_ERROR, $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::log(self::LEVEL_CRITICAL, $message, $context);
    }

    private static function log(string $level, string $message, array $context = []): void
    {
        if (!self::$logFile) {
            self::init(NEXORA_ROOT . '/storage/logs');
        }

        $timestamp = date('Y-m-d H:i:s');
        $requestId = self::getRequestId();
        $context = self::sanitizeContext($context);
        $contextJson = !empty($context) ? json_encode($context) : '';

        $logLine = sprintf(
            "[%s] [%s] [%s] %s %s\n",
            $timestamp,
            $level,
            $requestId,
            $message,
            $contextJson
        );

        self::writeLog($logLine);
    }

    private static function writeLog(string $message): void
    {
        if (!file_exists(self::$logFile)) {
            touch(self::$logFile);
            chmod(self::$logFile, 0644);
        }

        error_log($message, 3, self::$logFile);
    }

    private static function sanitizeContext(array $context): array
    {
        $sensitive = ['password', 'token', 'secret', 'api_key', 'xtream_password'];

        foreach ($context as $key => $value) {
            if (in_array(strtolower($key), $sensitive, true)) {
                $context[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $context[$key] = self::sanitizeContext($value);
            }
        }

        return $context;
    }

    private static function getRequestId(): string
    {
        static $requestId = null;

        if ($requestId === null) {
            $requestId = substr(bin2hex(random_bytes(8)), 0, 16);
        }

        return $requestId;
    }

    public static function httpRequest(string $method, string $action, int $statusCode, float $duration): void
    {
        self::info("HTTP {$method} {$action}", [
            'status_code' => $statusCode,
            'duration_ms' => round($duration * 1000, 2),
        ]);
    }

    public static function databaseQuery(string $query, float $duration): void
    {
        self::debug("Database query executed", [
            'query' => substr($query, 0, 100),
            'duration_ms' => round($duration * 1000, 2),
        ]);
    }

    public static function authFailure(string $reason, array $context = []): void
    {
        self::warning("Authentication failed: {$reason}", $context);
    }

    public static function securityEvent(string $event, array $context = []): void
    {
        self::warning("Security event: {$event}", $context);
    }
}
