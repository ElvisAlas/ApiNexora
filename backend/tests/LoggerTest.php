<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/services/Logger.php';

class LoggerTest extends \PHPUnit\Framework\TestCase
{
    private string $logDir;
    private string $logFile;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/nexora-test-logs';
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }

        $this->logFile = $this->logDir . '/nexora-' . date('Y-m-d') . '.log';
        Logger::init($this->logDir);
    }

    protected function tearDown(): void
    {
        // Clean up test log files
        if (is_file($this->logFile)) {
            unlink($this->logFile);
        }
        if (is_dir($this->logDir)) {
            rmdir($this->logDir);
        }
    }

    public function testDebugLog(): void
    {
        Logger::debug('Test debug message', ['key' => 'value']);
        $this->assertFileExists($this->logFile);

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('[DEBUG]', $content);
        $this->assertStringContainsString('Test debug message', $content);
    }

    public function testErrorLog(): void
    {
        Logger::error('Test error message', ['error' => 'details']);
        $this->assertFileExists($this->logFile);

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('[ERROR]', $content);
    }

    public function testCriticalLog(): void
    {
        Logger::critical('Critical failure', ['fatal' => true]);
        $this->assertFileExists($this->logFile);

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('[CRITICAL]', $content);
    }

    public function testSanitization(): void
    {
        Logger::info('Auth attempt', [
            'username' => 'user@example.com',
            'password' => 'secret123',
            'api_key' => 'pk_live_xyz',
        ]);

        $content = file_get_contents($this->logFile);
        $this->assertStringNotContainsString('secret123', $content);
        $this->assertStringNotContainsString('pk_live_xyz', $content);
        $this->assertStringContainsString('***REDACTED***', $content);
    }

    public function testHttpRequestLogging(): void
    {
        Logger::httpRequest('POST', '/api/device/register', 200, 0.045);
        $this->assertFileExists($this->logFile);

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('HTTP POST', $content);
        $this->assertStringContainsString('device/register', $content);
    }

    public function testAuthFailureLogging(): void
    {
        Logger::authFailure('Invalid token', ['device_uid' => 'test123']);
        $this->assertFileExists($this->logFile);

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('Authentication failed', $content);
    }

    public function testSecurityEventLogging(): void
    {
        Logger::securityEvent('Suspicious activity detected', ['ip' => '192.168.1.1']);
        $this->assertFileExists($this->logFile);

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('Security event', $content);
    }
}
