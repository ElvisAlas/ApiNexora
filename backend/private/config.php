<?php
declare(strict_types=1);

// ============ NEXORA PLAYER CONFIGURATION ============
// Este archivo NO debe estar en Git (está en .gitignore)
// Valores para desarrollo/testing
// Para producción, actualizar credenciales en el servidor

// ──── DATABASE ────
define('DB_HOST', '127.0.0.1');         // local: 127.0.0.1, production: 198.12.254.198
define('DB_PORT', 1433);
define('DB_NAME', 'NexoraPlayer');
define('DB_USER', 'sa');
define('DB_PASS', '');                  // Cambiar a credencial real
define('DB_DRIVER', 'sqlsrv');

// ──── COMPATIBILITY ────
define('LIVEACCESS_DB_NAME', 'alastv_db');

// ──── APP SETTINGS ────
define('NEXORA_ENV', 'development');
define('NEXORA_LOG_LEVEL', 'debug');
define('SESSION_TIMEOUT_SECONDS', 86400);
define('HEARTBEAT_TIMEOUT_SECONDS', 120);
define('XTREAM_CACHE_TTL', 300);
