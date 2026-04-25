<?php
declare(strict_types=1);

class RateLimiter
{
    private static string $cacheDir = '';

    const LIMIT_DEVICE = 10;           // 10 requests
    const LIMIT_DEVICE_WINDOW = 60;    // per 60 seconds
    const LIMIT_IP = 100;              // 100 requests
    const LIMIT_IP_WINDOW = 60;        // per 60 seconds

    public static function init(string $cacheDir): void
    {
        self::$cacheDir = $cacheDir;

        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0755, true);
        }
    }

    public static function checkDeviceLimit(string $deviceUid): bool
    {
        return self::checkLimit(
            "device:{$deviceUid}",
            self::LIMIT_DEVICE,
            self::LIMIT_DEVICE_WINDOW
        );
    }

    public static function checkIPLimit(string $ipAddress): bool
    {
        return self::checkLimit(
            "ip:{$ipAddress}",
            self::LIMIT_IP,
            self::LIMIT_IP_WINDOW
        );
    }

    private static function checkLimit(string $key, int $maxRequests, int $windowSeconds): bool
    {
        if (!self::$cacheDir) {
            self::init(NEXORA_ROOT . '/storage/cache');
        }

        $file = self::$cacheDir . '/' . hash('sha256', $key) . '.json';
        $now = time();

        $data = self::readCache($file);

        if ($data === null) {
            self::writeCache($file, ['count' => 1, 'reset_at' => $now + $windowSeconds]);
            return true;
        }

        // Window expired
        if ($now >= $data['reset_at']) {
            self::writeCache($file, ['count' => 1, 'reset_at' => $now + $windowSeconds]);
            return true;
        }

        // Within limit
        if ($data['count'] < $maxRequests) {
            $data['count']++;
            self::writeCache($file, $data);
            return true;
        }

        // Limit exceeded
        return false;
    }

    private static function readCache(string $file): ?array
    {
        if (!file_exists($file)) {
            return null;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    private static function writeCache(string $file, array $data): void
    {
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    public static function getRemainingRequests(string $deviceUid): int
    {
        return self::getRemainingLimit(
            "device:{$deviceUid}",
            self::LIMIT_DEVICE
        );
    }

    private static function getRemainingLimit(string $key, int $maxRequests): int
    {
        $file = self::$cacheDir . '/' . hash('sha256', $key) . '.json';
        $data = self::readCache($file);

        if ($data === null) {
            return $maxRequests;
        }

        return max(0, $maxRequests - $data['count']);
    }

    public static function cleanup(): void
    {
        if (!is_dir(self::$cacheDir)) {
            return;
        }

        $now = time();
        $files = glob(self::$cacheDir . '/*.json');

        foreach ($files as $file) {
            $data = self::readCache($file);
            if ($data !== null && $now >= $data['reset_at']) {
                @unlink($file);
            }
        }
    }
}
