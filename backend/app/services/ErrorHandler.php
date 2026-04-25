<?php
declare(strict_types=1);

class ErrorHandler
{
    private static array $errorMap = [
        'VALIDATION_ERROR' => ['http' => 400, 'message' => 'Request validation failed'],
        'AUTH_ERROR' => ['http' => 401, 'message' => 'Authentication failed'],
        'FORBIDDEN' => ['http' => 403, 'message' => 'Access forbidden'],
        'NOT_FOUND' => ['http' => 404, 'message' => 'Resource not found'],
        'RATE_LIMIT' => ['http' => 429, 'message' => 'Too many requests'],
        'SERVER_ERROR' => ['http' => 500, 'message' => 'Internal server error'],
        'DATABASE_ERROR' => ['http' => 500, 'message' => 'Database operation failed'],
    ];

    public static function handleException(\Throwable $exception): void
    {
        $code = $exception->getCode();
        $message = $exception->getMessage();

        // Log the exception
        Logger::error('Exception caught', [
            'exception' => get_class($exception),
            'code' => $code,
            'message' => $message,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        // Determine HTTP response
        $errorCode = $code > 0 && $code < 600 ? $code : 500;
        $responseMessage = $message ?: 'An unexpected error occurred';

        nexoraError(
            'EXCEPTION_ERROR',
            $responseMessage,
            $errorCode
        );
    }

    public static function handleError(int $level, string $message, string $file, int $line): bool
    {
        // Log the error
        Logger::error('PHP Error', [
            'level' => $level,
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ]);

        // Return true to prevent PHP's standard error handling
        return true;
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
            Logger::critical('Fatal error', [
                'type' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
            ]);

            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'FATAL_ERROR', 'message' => 'Fatal error occurred'],
            ]);
        }
    }

    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function validateRequest(array $body, array $rules): array
    {
        if (!RequestValidator::validate($body, $rules)) {
            $errors = RequestValidator::getErrors();
            Logger::warning('Request validation failed', ['errors' => $errors]);
            return ['valid' => false, 'errors' => $errors];
        }
        return ['valid' => true, 'errors' => []];
    }

    public static function checkRateLimit(string $deviceUid, string $ipAddress): bool
    {
        if (!RateLimiter::checkDeviceLimit($deviceUid)) {
            Logger::securityEvent('Device rate limit exceeded', ['device_uid' => $deviceUid]);
            return false;
        }

        if (!RateLimiter::checkIPLimit($ipAddress)) {
            Logger::securityEvent('IP rate limit exceeded', ['ip' => $ipAddress]);
            return false;
        }

        return true;
    }

    public static function respondWithError(string $code, string $message, int $http = 400): void
    {
        Logger::warning('Error response', ['code' => $code, 'message' => $message]);
        nexoraError($code, $message, $http);
    }

    public static function respondWithSuccess($data = [], string $status = 'ok', string $message = ''): void
    {
        nexoraSuccess($data, $status, $message);
    }
}
