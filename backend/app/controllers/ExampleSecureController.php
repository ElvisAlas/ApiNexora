<?php
declare(strict_types=1);

class ExampleSecureController
{
    public static function registerDevice(): void
    {
        try {
            // Get request data
            $body = nexoraReadBody();
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

            // Validate request structure
            $validation = ErrorHandler::validateRequest($body, [
                'device_uid' => ['required' => true, 'type' => 'string'],
                'platform' => ['required' => true, 'enum' => ['roku', 'tizen', 'webos', 'android_tv', 'fire_tv', 'appletv', 'web']],
                'device_name' => ['type' => 'string', 'max_length' => 255],
            ]);

            if (!$validation['valid']) {
                Logger::warning('Device registration validation failed', $validation['errors']);
                ErrorHandler::respondWithError('VALIDATION_ERROR', 'Invalid device data', 400);
            }

            // Check rate limits
            $deviceUid = $body['device_uid'];
            if (!ErrorHandler::checkRateLimit($deviceUid, $clientIp)) {
                Logger::securityEvent('Rate limit exceeded', ['device_uid' => $deviceUid, 'ip' => $clientIp]);
                ErrorHandler::respondWithError('RATE_LIMIT', 'Too many requests. Try again later.', 429);
            }

            // Validate device UID format
            if (!RequestValidator::validateDeviceUID($deviceUid)) {
                Logger::warning('Invalid device UID format', ['device_uid' => $deviceUid]);
                ErrorHandler::respondWithError('INVALID_DEVICE_UID', 'Device UID format invalid', 400);
            }

            // Sanitize inputs
            $deviceName = isset($body['device_name']) ? RequestValidator::sanitize($body['device_name']) : '';
            $platform = $body['platform'];

            // Process registration (mock)
            $activationCode = self::generateActivationCode();
            Logger::info('Device registration successful', [
                'device_uid' => $deviceUid,
                'platform' => $platform,
                'activation_code' => substr($activationCode, 0, 4) . '****',
            ]);

            ErrorHandler::respondWithSuccess([
                'activation_code' => $activationCode,
                'device_uid' => $deviceUid,
                'expires_in' => 900, // 15 minutes
            ], 'registered', 'Device registered successfully');

        } catch (\Throwable $e) {
            ErrorHandler::handleException($e);
        }
    }

    public static function authenticateDevice(): void
    {
        try {
            $body = nexoraReadBody();
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

            // Validate request
            $validation = ErrorHandler::validateRequest($body, [
                'device_uid' => ['required' => true],
                'activation_code' => ['required' => true, 'min_length' => 4, 'max_length' => 4],
            ]);

            if (!$validation['valid']) {
                ErrorHandler::respondWithError('VALIDATION_ERROR', 'Invalid authentication data', 400);
            }

            // Rate limit check
            $deviceUid = $body['device_uid'];
            if (!ErrorHandler::checkRateLimit($deviceUid, $clientIp)) {
                Logger::authFailure('Rate limit on auth', ['device_uid' => $deviceUid]);
                ErrorHandler::respondWithError('RATE_LIMIT', 'Too many attempts', 429);
            }

            // Simulate auth check
            $code = $body['activation_code'];
            if (!self::verifyActivationCode($deviceUid, $code)) {
                Logger::authFailure('Invalid activation code', ['device_uid' => $deviceUid]);
                ErrorHandler::respondWithError('AUTH_ERROR', 'Invalid activation code', 401);
            }

            // Generate session token
            $sessionToken = bin2hex(random_bytes(32));
            if (!RequestValidator::validateSessionToken($sessionToken)) {
                throw new \Exception('Invalid token generated');
            }

            Logger::info('Device authenticated', [
                'device_uid' => $deviceUid,
                'session_token' => substr($sessionToken, 0, 8) . '****',
            ]);

            ErrorHandler::respondWithSuccess([
                'session_token' => $sessionToken,
                'expires_in' => 86400, // 24 hours
            ], 'authenticated', 'Device authenticated');

        } catch (\Throwable $e) {
            ErrorHandler::handleException($e);
        }
    }

    private static function generateActivationCode(): string
    {
        return str_pad((string)mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    }

    private static function verifyActivationCode(string $deviceUid, string $code): bool
    {
        // Mock verification — in real code, check against database
        return strlen($code) === 4 && ctype_digit($code);
    }
}
