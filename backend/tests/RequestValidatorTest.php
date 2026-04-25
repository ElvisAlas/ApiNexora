<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/services/RequestValidator.php';

class RequestValidatorTest extends \PHPUnit\Framework\TestCase
{
    public function testValidateRequired(): void
    {
        $body = ['username' => 'john'];
        $rules = [
            'username' => ['required' => true, 'type' => 'string'],
            'password' => ['required' => true, 'type' => 'string'],
        ];

        $this->assertFalse(RequestValidator::validate($body, $rules));
        $errors = RequestValidator::getErrors();
        $this->assertArrayHasKey('password', $errors);
    }

    public function testValidateType(): void
    {
        $body = ['count' => 'not-a-number'];
        $rules = ['count' => ['type' => 'int']];

        $this->assertFalse(RequestValidator::validate($body, $rules));
    }

    public function testValidateLength(): void
    {
        $body = ['username' => 'ab'];
        $rules = ['username' => ['min_length' => 3, 'max_length' => 20]];

        $this->assertFalse(RequestValidator::validate($body, $rules));
    }

    public function testValidatePattern(): void
    {
        $body = ['email' => 'invalid-email'];
        $rules = ['email' => ['pattern' => '/^[a-z0-9]+@[a-z0-9]+\.[a-z]+$/']];

        $this->assertFalse(RequestValidator::validate($body, $rules));
    }

    public function testValidateEnum(): void
    {
        $body = ['platform' => 'invalid'];
        $rules = ['platform' => ['enum' => ['roku', 'tizen', 'webos']]];

        $this->assertFalse(RequestValidator::validate($body, $rules));
    }

    public function testValidateCustom(): void
    {
        $body = ['age' => 15];
        $rules = [
            'age' => [
                'custom' => function ($value) { return $value >= 18; },
                'custom_error' => 'Must be 18+',
            ],
        ];

        $this->assertFalse(RequestValidator::validate($body, $rules));
        $error = RequestValidator::getFirstError();
        $this->assertStringContainsString('18+', $error);
    }

    public function testValidDeviceUID(): void
    {
        $this->assertTrue(RequestValidator::validateDeviceUID('valid-uid-123'));
        $this->assertFalse(RequestValidator::validateDeviceUID("uid-with-quotes'\""));
        $this->assertFalse(RequestValidator::validateDeviceUID(str_repeat('x', 256))); // too long
    }

    public function testValidSessionToken(): void
    {
        $valid = hash('sha256', 'test');
        $this->assertTrue(RequestValidator::validateSessionToken($valid));

        $this->assertFalse(RequestValidator::validateSessionToken('invalid'));
        $this->assertFalse(RequestValidator::validateSessionToken(substr($valid, 0, 32))); // too short
    }

    public function testValidEmail(): void
    {
        $this->assertTrue(RequestValidator::validateEmail('user@example.com'));
        $this->assertFalse(RequestValidator::validateEmail('invalid-email'));
        $this->assertFalse(RequestValidator::validateEmail('user@'));
    }

    public function testValidPlatform(): void
    {
        $this->assertTrue(RequestValidator::validatePlatform('roku'));
        $this->assertTrue(RequestValidator::validatePlatform('tizen'));
        $this->assertTrue(RequestValidator::validatePlatform('webos'));
        $this->assertTrue(RequestValidator::validatePlatform('android_tv'));
        $this->assertFalse(RequestValidator::validatePlatform('invalid_platform'));
    }

    public function testSanitize(): void
    {
        $dirty = "  text\x00with\x08control\x1fchars  ";
        $clean = RequestValidator::sanitize($dirty);
        $this->assertStringNotContainsString("\x00", $clean);
        $this->assertStringNotContainsString("\x08", $clean);
        $this->assertEquals('textwithcontrolchars', $clean);
    }

    public function testValidRequest(): void
    {
        $body = [
            'device_uid' => 'device-123',
            'platform' => 'roku',
            'session_token' => hash('sha256', 'test'),
        ];

        $rules = [
            'device_uid' => ['required' => true, 'type' => 'string'],
            'platform' => ['required' => true, 'enum' => ['roku', 'tizen', 'webos']],
            'session_token' => ['required' => true, 'type' => 'string'],
        ];

        $this->assertTrue(RequestValidator::validate($body, $rules));
        $this->assertEmpty(RequestValidator::getErrors());
    }
}
