<?php
declare(strict_types=1);

// PDO singleton para SQL Server — mismo driver que AlasTV (pdo_sqlsrv, IIS/Windows)
class Database
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance === null) {
            // DSN idéntico al de AlasTV: sqlsrv con Encrypt=no, TrustServerCertificate=yes
            $dsn = sprintf(
                'sqlsrv:Server=%s,%d;Database=%s;Encrypt=no;TrustServerCertificate=yes',
                DB_HOST, (int)DB_PORT, DB_NAME
            );

            self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$instance;
    }
}
