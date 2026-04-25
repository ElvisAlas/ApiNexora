<?php
declare(strict_types=1);

class RequestValidator
{
    private static array $errors = [];

    public static function validate(array $body, array $rules): bool
    {
        self::$errors = [];

        foreach ($rules as $field => $rule) {
            $value = $body[$field] ?? null;
            self::validateField($field, $value, $rule);
        }

        return empty(self::$errors);
    }

    private static function validateField(string $field, mixed $value, array $rule): void
    {
        // Required check
        if (($rule['required'] ?? false) && (is_null($value) || $value === '')) {
            self::$errors[$field] = "Field '{$field}' is required";
            return;
        }

        if (is_null($value)) {
            return;
        }

        // Type check
        $type = $rule['type'] ?? 'string';
        if (!self::checkType($value, $type)) {
            self::$errors[$field] = "Field '{$field}' must be of type {$type}";
            return;
        }

        // Length check
        if (isset($rule['max_length']) && strlen((string)$value) > $rule['max_length']) {
            self::$errors[$field] = "Field '{$field}' exceeds maximum length of {$rule['max_length']}";
        }

        if (isset($rule['min_length']) && strlen((string)$value) < $rule['min_length']) {
            self::$errors[$field] = "Field '{$field}' must be at least {$rule['min_length']} characters";
        }

        // Pattern check
        if (isset($rule['pattern']) && !preg_match($rule['pattern'], (string)$value)) {
            self::$errors[$field] = "Field '{$field}' format is invalid";
        }

        // Enum check
        if (isset($rule['enum']) && !in_array($value, $rule['enum'], true)) {
            self::$errors[$field] = "Field '{$field}' value not allowed";
        }

        // Custom validator
        if (isset($rule['custom']) && is_callable($rule['custom'])) {
            if (!$rule['custom']($value)) {
                self::$errors[$field] = $rule['custom_error'] ?? "Field '{$field}' validation failed";
            }
        }
    }

    private static function checkType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'int', 'integer' => is_int($value) || (is_string($value) && is_numeric($value)),
            'bool', 'boolean' => is_bool($value) || in_array($value, ['0', '1', 0, 1], true),
            'array' => is_array($value),
            default => true,
        };
    }

    public static function getErrors(): array
    {
        return self::$errors;
    }

    public static function getFirstError(): ?string
    {
        return reset(self::$errors) ?: null;
    }

    public static function sanitize(string $value): string
    {
        // Remove null bytes
        $value = str_replace("\0", '', $value);

        // Remove control characters
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        // Trim whitespace
        $value = trim($value);

        return $value;
    }

    public static function validateDeviceUID(string $uid): bool
    {
        return strlen($uid) > 0 && strlen($uid) <= 255 && !preg_match('/[\'";]/', $uid);
    }

    public static function validateSessionToken(string $token): bool
    {
        return strlen($token) === 64 && ctype_xdigit($token);
    }

    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validatePlatform(string $platform): bool
    {
        $allowed = ['roku', 'tizen', 'webos', 'android_tv', 'fire_tv', 'appletv', 'web'];
        return in_array($platform, $allowed, true);
    }
}
