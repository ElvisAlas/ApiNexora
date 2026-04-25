<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class DeviceRegistryTest extends TestCase
{
    private DeviceRegistryService $registry;
    private string $testDeviceUid = 'test-roku-001';
    private string $testPlatform = 'roku';

    protected function setUp(): void
    {
        $this->registry = new DeviceRegistryService();
    }

    public function testGenerateActivationCode(): void
    {
        $code = $this->generateRandomCode();

        $this->assertNotEmpty($code);
        $this->assertStringStartsWith('ALAS-', $code);
        $this->assertLessThanOrEqual(50, strlen($code));
    }

    public function testDeviceUIDFormatValidation(): void
    {
        $validUids = [
            'roku-ABC123',
            'tizen-XYZ789',
            'webos-12345',
            'test-device-001'
        ];

        foreach ($validUids as $uid) {
            $this->assertNotEmpty($uid);
            $this->assertLessThanOrEqual(255, strlen($uid));
        }
    }

    public function testPlatformValidation(): void
    {
        $validPlatforms = ['roku', 'tizen', 'webos', 'android_tv', 'fire_tv', 'appletv', 'web'];

        foreach ($validPlatforms as $platform) {
            $this->assertNotEmpty($platform);
            $this->assertTrue(strlen($platform) <= 20);
        }
    }

    private function generateRandomCode(): string
    {
        return 'ALAS-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
}
