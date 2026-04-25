<?php
declare(strict_types=1);

// Test bootstrap para PHPUnit
define('NEXORA_ROOT', dirname(__DIR__));
define('NEXORA_TEST_MODE', true);

// Cargar autoload o clases manualmente
spl_autoload_register(function ($class) {
    $file = NEXORA_ROOT . '/app/' . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

require_once NEXORA_ROOT . '/app/config/bootstrap.php';
