<?php
declare(strict_types=1);

// NexoraPlayer — api.php
// Entry point unificado: TV devices + Portal resellers
// Backend runtime cargado por portal.alastv.com/api.php -> /api/api.php

@ini_set('max_execution_time', 60);
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

require_once __DIR__ . '/app/config/bootstrap.php';
require_once __DIR__ . '/app/services/ConfigManager.php';
require_once __DIR__ . '/app/services/Database.php';
require_once __DIR__ . '/app/services/DeviceRegistryService.php';
require_once __DIR__ . '/app/services/DeviceSessionService.php';
require_once __DIR__ . '/app/controllers/TvApiController.php';
require_once __DIR__ . '/app/controllers/PortalController.php';

ConfigManager::load();

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'POST'));
if ($method !== 'POST') {
    nexoraError('METHOD_NOT_ALLOWED', 'Solo se permite metodo POST.', 405);
}

$body = nexoraReadBody();
$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if (!is_array($body) || empty($body)) {
    nexoraError('INVALID_BODY', 'Body JSON requerido.', 400);
}
if ($contentType !== '' && strpos($contentType, 'application/json') === false) {
    nexoraError('INVALID_CONTENT_TYPE', 'Content-Type debe ser application/json.', 415);
}

$action = trim($body['action'] ?? '');

if (!$action) {
    nexoraError('MISSING_ACTION', 'El campo action es requerido.');
}

// Rate limit by IP (all requests).
$clientIp = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown');
$clientIp = trim(explode(',', $clientIp)[0]);
if ($clientIp === '') {
    $clientIp = 'unknown';
}

if (!RateLimiter::checkIPLimit($clientIp)) {
    Logger::securityEvent('IP rate limit exceeded', ['ip' => $clientIp, 'action' => $action]);
    nexoraError('RATE_LIMIT', 'Demasiadas solicitudes. Intenta nuevamente.', 429);
}

// Device-aware rate limit when device_uid is available.
$deviceUid = isset($body['device_uid']) ? trim((string)$body['device_uid']) : '';
if ($deviceUid !== '') {
    if (!RequestValidator::validateDeviceUID($deviceUid)) {
        nexoraError('INVALID_DEVICE_UID', 'device_uid invalido.', 400);
    }
    if (!RateLimiter::checkDeviceLimit($deviceUid)) {
        Logger::securityEvent('Device rate limit exceeded', ['device_uid' => $deviceUid, 'action' => $action]);
        nexoraError('RATE_LIMIT', 'Demasiadas solicitudes del dispositivo. Intenta nuevamente.', 429);
    }
}

// Action-specific validation gates.
if ($action === 'portal/login') {
    $email = (string)($body['email'] ?? '');
    if (!RequestValidator::validateEmail($email)) {
        nexoraError('INVALID_EMAIL', 'Email invalido.', 400);
    }
}

$requiresSessionToken = [
    'device/heartbeat', 'device/unlink', 'session/end',
    'catalog/live/categories', 'catalog/live/streams',
    'catalog/vod/categories', 'catalog/vod/streams',
    'catalog/series/categories', 'catalog/series',
    'playback/resolve', 'playback/start', 'playback/progress', 'playback/end',
];
if (in_array($action, $requiresSessionToken, true)) {
    $sessionToken = (string)($body['session_token'] ?? '');
    if (!RequestValidator::validateSessionToken($sessionToken)) {
        nexoraError('INVALID_SESSION_TOKEN', 'session_token invalido.', 401);
    }
}

// Acciones del portal (resellers)
$portalActions = [
    'portal/login', 'portal/clients', 'portal/devices', 'portal/device/detail',
    'device/activate', 'device/block', 'device/unblock', 'device/extend', 'portal/device/unlink',
];

try {
    if (in_array($action, $portalActions, true)) {
        (new PortalController())->handle($action, $body);
    } else {
        (new TvApiController())->handle($action, $body);
    }
} catch (PDOException $e) {
    Logger::error('NexoraPlayer DB Error', ['message' => $e->getMessage(), 'action' => $action]);
    nexoraError('DB_ERROR', 'Error de base de datos. Contacta al administrador.', 500);
} catch (Exception $e) {
    Logger::error('NexoraPlayer Error', ['message' => $e->getMessage(), 'action' => $action]);
    nexoraError('SERVER_ERROR', 'Error interno del servidor.', 500);
}
