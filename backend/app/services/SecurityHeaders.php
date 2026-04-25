<?php
declare(strict_types=1);

class SecurityHeaders
{
    public static function setStandardHeaders(): void
    {
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');

        // Prevent clickjacking
        header('X-Frame-Options: DENY');

        // Enable XSS protection (legacy, modern browsers use CSP)
        header('X-XSS-Protection: 1; mode=block');

        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Feature policy / Permissions policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }

    public static function setSecurityHeaders(bool $isProduction = false): void
    {
        self::setStandardHeaders();

        if ($isProduction) {
            // HSTS (only in production with HTTPS)
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // CSP (Content Security Policy)
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
        ]);
        header("Content-Security-Policy: {$csp}");
    }

    public static function setCORSHeaders(string $origin = '*'): void
    {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 3600');
    }

    public static function setJSONHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
    }

    public static function handleOPTIONSRequest(): void
    {
        http_response_code(204);
        self::setStandardHeaders();
        self::setCORSHeaders();
        exit;
    }

    public static function validateOrigin(string $requestOrigin, array $allowedOrigins): bool
    {
        // In development, allow any origin
        if (defined('NEXORA_ENV') && NEXORA_ENV === 'development') {
            return true;
        }

        return in_array($requestOrigin, $allowedOrigins, true);
    }

    public static function enforceHTTPS(): void
    {
        if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
            http_response_code(301);
            $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header("Location: {$url}");
            exit;
        }
    }
}
