<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ConfigManagerTest extends TestCase
{
    public function testConfigLoadWithDefaults(): void
    {
        ConfigManager::load();
        $this->assertTrue(true, "ConfigManager loads without error");
    }

    public function testGetDatabaseConfig(): void
    {
        ConfigManager::load();
        $dbConfig = ConfigManager::getDatabase();

        $this->assertIsArray($dbConfig);
        $this->assertArrayHasKey('name', $dbConfig);
        $this->assertArrayHasKey('host', $dbConfig);
        $this->assertArrayHasKey('port', $dbConfig);
        $this->assertArrayHasKey('user', $dbConfig);
    }

    public function testGetLiveAccessDB(): void
    {
        ConfigManager::load();
        $liveDb = ConfigManager::getLiveAccessDB();

        $this->assertIsString($liveDb);
        $this->assertNotEmpty($liveDb);
    }

    public function testSessionTimeout(): void
    {
        ConfigManager::load();
        $timeout = ConfigManager::getSessionTimeout();

        $this->assertIsInt($timeout);
        $this->assertGreaterThan(0, $timeout);
    }

    public function testEnvironmentDetection(): void
    {
        ConfigManager::load();
        $isProduction = ConfigManager::isProduction();

        $this->assertIsBool($isProduction);
    }

    public function testCacheTTL(): void
    {
        ConfigManager::load();
        $ttl = ConfigManager::getCacheTTL();

        $this->assertIsInt($ttl);
        $this->assertGreaterThan(0, $ttl);
    }
}
