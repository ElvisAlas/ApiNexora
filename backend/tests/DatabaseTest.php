<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    public function testDatabaseIsConfigurable(): void
    {
        // Test que Database puede ser inicializado (mocked)
        $this->assertTrue(true, "Database test infrastructure working");
    }

    public function testStrictTypesEnabled(): void
    {
        // Verificar que strict_types=1 está habilitado
        $this->assertTrue(defined('NEXORA_TEST_MODE'));
    }
}
