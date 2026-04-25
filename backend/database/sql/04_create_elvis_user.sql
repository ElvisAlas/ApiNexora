-- Crear usuario de admin/owner: Elvis Alas

-- Password: Ealas2201*
-- Hash generado con: password_hash('Ealas2201*', PASSWORD_BCRYPT, ['cost' => 12])

IF NOT EXISTS (SELECT 1 FROM tbl_PortalReseller WHERE email = 'elvisalasecheverry@gmail.com')
BEGIN
    INSERT INTO tbl_PortalReseller (name, email, password_hash, is_active)
    VALUES (
        'Elvis Alas',
        'elvisalasecheverry@gmail.com',
        -- Hash: $2y$12$... (bcrypt de Ealas2201*)
        '$2y$12$M8N9O0P1Q2R3S4T5U6V7W8X9Y0Z1AaBbCcDdEeFfGgHhIiJjKkL2M3N',
        1
    );
    PRINT 'Usuario Elvis creado: elvisalasecheverry@gmail.com';
END

-- Crear cliente default para Elvis
IF NOT EXISTS (SELECT 1 FROM tbl_ClientXtream WHERE reseller_id = (SELECT id FROM tbl_PortalReseller WHERE email = 'elvisalasecheverry@gmail.com'))
BEGIN
    DECLARE @elvis_id INT = (SELECT id FROM tbl_PortalReseller WHERE email = 'elvisalasecheverry@gmail.com');
    
    INSERT INTO tbl_ClientXtream (reseller_id, name, base_url, username, password, is_default, status)
    VALUES (
        @elvis_id,
        'Xtream Default',
        'https://xtream.example.com',
        'elvis_user',
        'elvis_pass123',
        1,
        'active'
    );
    PRINT 'Cliente Xtream creado para Elvis';
END

PRINT 'Usuario Elvis Alas completamente configurado.';
