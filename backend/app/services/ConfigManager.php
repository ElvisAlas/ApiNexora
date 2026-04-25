<?php
declare(strict_types=1);

class ConfigManager
{
    private static array $config = [];
    private static bool $initialized = false;

    const DEFAULTS = [
        'DB_NAME' => 'NexoraPlayer',
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => 1433,
        'DB_USER' => 'sa',
        'DB_PASS' => '',
        'DB_DRIVER' => 'sqlsrv',
        'LIVEACCESS_DB_NAME' => 'alastv_db',
        'SESSION_TIMEOUT_SECONDS' => 86400,
        'HEARTBEAT_TIMEOUT_SECONDS' => 120,
        'XTREAM_CACHE_TTL' => 300,
        'NEXORA_ENV' => 'development',
        'NEXORA_LOG_LEVEL' => 'info',
    ];

    public static function load(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$config = self::DEFAULTS;

        $configFile = NEXORA_ROOT . '/private/config.php';
        if (file_exists($configFile)) {
            require_once $configFile;

            foreach (self::DEFAULTS as $key => $_) {
                if (defined($key)) {
                    self::$config[$key] = constant($key);
                }
            }
        }

        self::$initialized = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        return self::$config[$key] ?? $default;
    }

    public static function getDatabase(): array
    {
        self::load();
        return [
            'name' => self::$config['DB_NAME'],
            'host' => self::$config['DB_HOST'],
            'port' => self::$config['DB_PORT'],
            'user' => self::$config['DB_USER'],
            'pass' => self::$config['DB_PASS'],
            'driver' => self::$config['DB_DRIVER'],
        ];
    }

    public static function getLiveAccessDB(): string
    {
        self::load();
        return self::$config['LIVEACCESS_DB_NAME'];
    }

    public static function isProduction(): bool
    {
        self::load();
        return self::$config['NEXORA_ENV'] === 'production';
    }

    public static function getSessionTimeout(): int
    {
        self::load();
        return self::$config['SESSION_TIMEOUT_SECONDS'];
    }

    public static function getHeartbeatTimeout(): int
    {
        self::load();
        return self::$config['HEARTBEAT_TIMEOUT_SECONDS'];
    }

    public static function getCacheTTL(): int
    {
        self::load();
        return self::$config['XTREAM_CACHE_TTL'];
    }
}
