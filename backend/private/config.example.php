<?php
// NexoraPlayer — config.example.php
// Copia como private/config.php en el servidor. NUNCA subir config.php a Git.
// Mismo SQL Server que alastv_db (misma instancia, BD separada o misma).

// ── SQL Server ───────────────────────────────────────────────────────────────
define('DB_HOST', '198.12.254.198');   // Misma IP que AlasTV
define('DB_PORT', 1433);               // Puerto SQL Server default
define('DB_NAME', 'NexoraPlayer');     // BD dedicada — o 'alastv_db' si usas la misma
define('DB_USER', 'AppUser');          // Mismo usuario que AlasTV o uno dedicado
define('DB_PASS', 'TU_PASSWORD_REAL'); // Cambiar antes de usar

// Si la vista heredada vw_ClientLiveAccessDefault vive en otra BD (ej. alastv_db),
// puedes indicarla aquí sin mover las tablas propias de Nexora:
// define('LIVEACCESS_DB_NAME', 'alastv_db');

// ── Plataformas TV permitidas (opcional — omitir usa el default) ─────────────
// define('TV_PLATFORMS_ALLOWED', 'roku,tizen,webos,android_tv,fire_tv,appletv,web');
