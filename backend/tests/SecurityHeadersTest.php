<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/services/SecurityHeaders.php';

class SecurityHeadersTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        // Clear any previously sent headers
        if (!function_exists('xdebug_get_headers')) {
            $this->markTestSkipped('xdebug extension required for header testing');
        }
    }

    public function testSetStandardHeaders(): void
    {
        ob_start();
        SecurityHeaders::setStandardHeaders();
        ob_end_clean();

        $headers = xdebug_get_headers();
        $this->assertContains('X-Content-Type-Options: nosniff', $headers);
        $this->assertContains('X-Frame-Options: DENY', $headers);
    }

    public function testCORSHeaders(): void
    {
        ob_start();
        SecurityHeaders::setCORSHeaders('https://example.com');
        ob_end_clean();

        $headers = xdebug_get_headers();
        $this->assertContains('Access-Control-Allow-Origin: https://example.com', $headers);
        $this->assertContains('Access-Control-Allow-Methods: POST, OPTIONS', $headers);
    }

    public function testOriginValidation(): void
    {
        $allowed = ['https://example.com', 'https://app.example.com'];

        $this->assertTrue(SecurityHeaders::validateOrigin('https://example.com', $allowed));
        $this->assertFalse(SecurityHeaders::validateOrigin('https://malicious.com', $allowed));
    }

    public function testJSONHeaders(): void
    {
        ob_start();
        SecurityHeaders::setJSONHeaders();
        ob_end_clean();

        $headers = xdebug_get_headers();
        $this->assertContains('Content-Type: application/json; charset=utf-8', $headers);
    }
}
