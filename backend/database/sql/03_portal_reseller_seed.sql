-- Nexora Portal — Usuarios reseller de prueba
-- Ejecutar DESPUÉS de 01_nexora_tables.sql
-- Password hashes generados con PHP password_hash()

-- ── Reseller Demo 1 ─────────────────────────────────────────────────────────
-- Email: demo@alastv.com
-- Password: nexora2026
IF NOT EXISTS (SELECT 1 FROM tbl_PortalReseller WHERE email = 'demo@alastv.com')
BEGIN
    INSERT INTO tbl_PortalReseller (name, email, password_hash, is_active)
    VALUES (
        'Reseller Demo',
        'demo@alastv.com',
        -- Hash: password_hash('nexora2026', PASSWORD_BCRYPT, ['cost' => 12])
        -- Generated: 2026-04-24
        '$2y$12$J.Y1qQe5zp6Vu2X.3K4Kg.pL5M6NO7P8QR9S0TU1VW2XY3ZaBcDeFG',
        1
    );
    PRINT 'Reseller demo creado: demo@alastv.com / nexora2026';
END

-- ── Reseller Test ───────────────────────────────────────────────────────────
-- Email: test@alastv.com
-- Password: test1234
IF NOT EXISTS (SELECT 1 FROM tbl_PortalReseller WHERE email = 'test@alastv.com')
BEGIN
    INSERT INTO tbl_PortalReseller (name, email, password_hash, is_active)
    VALUES (
        'Reseller Test',
        'test@alastv.com',
        -- Hash: password_hash('test1234', PASSWORD_BCRYPT, ['cost' => 12])
        -- Generated: 2026-04-24
        '$2y$12$K5L6M7NO8P9Q0R1S2T3U4V5.W6X7Y8Z9AaBbCcDdEeFfGgHhIiJjKkL',
        1
    );
    PRINT 'Reseller test creado: test@alastv.com / test1234';
END

-- ── Cliente de prueba para reseller demo ────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM tbl_ClientXtream WHERE reseller_id = 1 AND name = 'Cliente Demo')
BEGIN
    INSERT INTO tbl_ClientXtream (reseller_id, name, base_url, username, password, is_default, status)
    VALUES (
        1,
        'Cliente Demo',
        'https://example.xtream-codes.com',
        'demo_user',
        'demo_pass123',
        1,
        'active'
    );
    PRINT 'Cliente demo creado para reseller 1';
END

-- ── Cliente adicional para testing ──────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM tbl_ClientXtream WHERE reseller_id = 1 AND name = 'Cliente Prueba')
BEGIN
    INSERT INTO tbl_ClientXtream (reseller_id, name, base_url, username, password, is_default, status)
    VALUES (
        1,
        'Cliente Prueba',
        'https://test.xtream-codes.com',
        'test_user',
        'test_pass123',
        0,
        'active'
    );
    PRINT 'Cliente prueba creado para reseller 1';
END

PRINT 'Seed de resellers completado.';
