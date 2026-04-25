<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/services/RateLimiter.php';

class RateLimiterTest extends \PHPUnit\Framework\TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/nexora-test-cache';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        RateLimiter::init($this->cacheDir);
    }

    protected function tearDown(): void
    {
        // Clean up cache files
        $files = glob($this->cacheDir . '/*.json');
        foreach ($files as $file) {
            unlink($file);
        }
        if (is_dir($this->cacheDir)) {
            rmdir($this->cacheDir);
        }
    }

    public function testDeviceLimitAllowed(): void
    {
        $deviceUid = 'test-device-123';

        // First 10 requests should be allowed
        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue(RateLimiter::checkDeviceLimit($deviceUid));
        }

        // 11th request should be denied
        $this->assertFalse(RateLimiter::checkDeviceLimit($deviceUid));
    }

    public function testIPLimitAllowed(): void
    {
        $ip = '192.168.1.100';

        // First 100 requests should be allowed
        for ($i = 0; $i < 100; $i++) {
            $this->assertTrue(RateLimiter::checkIPLimit($ip));
        }

        // 101st request should be denied
        $this->assertFalse(RateLimiter::checkIPLimit($ip));
    }

    public function testGetRemainingRequests(): void
    {
        $deviceUid = 'test-device-456';

        $this->assertEquals(10, RateLimiter::getRemainingRequests($deviceUid));

        RateLimiter::checkDeviceLimit($deviceUid);
        $this->assertEquals(9, RateLimiter::getRemainingRequests($deviceUid));

        RateLimiter::checkDeviceLimit($deviceUid);
        $this->assertEquals(8, RateLimiter::getRemainingRequests($deviceUid));
    }

    public function testMultipleDevices(): void
    {
        $device1 = 'device-1';
        $device2 = 'device-2';

        // Each device has independent limits
        RateLimiter::checkDeviceLimit($device1);
        RateLimiter::checkDeviceLimit($device2);

        $this->assertEquals(9, RateLimiter::getRemainingRequests($device1));
        $this->assertEquals(9, RateLimiter::getRemainingRequests($device2));
    }

    public function testCleanup(): void
    {
        $deviceUid = 'cleanup-test';
        RateLimiter::checkDeviceLimit($deviceUid);

        // Verify cache file exists
        $files = glob($this->cacheDir . '/*.json');
        $this->assertCount(1, $files);

        // Run cleanup
        RateLimiter::cleanup();

        // Files should still exist (not expired yet)
        $files = glob($this->cacheDir . '/*.json');
        $this->assertCount(1, $files);
    }
}
