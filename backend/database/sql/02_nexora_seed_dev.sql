-- Seed data para desarrollo/testing
-- NO usar en producción

USE NexoraPlayer;
GO

-- Insertar reseller de prueba si no existe
IF NOT EXISTS (SELECT 1 FROM tbl_PortalReseller WHERE email = 'elvisalasecheverry@gmail.com')
BEGIN
    INSERT INTO tbl_PortalReseller (email, password_hash, name, company, status, created_at)
    VALUES (
        'elvisalasecheverry@gmail.com',
        '$2y$10$6.ynf4LqDX9QP5c1K5q2luNQSLNqvBx9gHj0r8gOWfxLJlN.vGJ2a',  -- password: Ealas2201*
        'Elvis Alas',
        'Alas TV',
        'active',
        GETUTCDATE()
    );
END
GO

-- Insertar dispositivo de prueba si no existe
IF NOT EXISTS (SELECT 1 FROM tbl_DeviceRegistry WHERE device_uid = 'test-device-001')
BEGIN
    INSERT INTO tbl_DeviceRegistry (
        device_uid, platform, activation_code, status, client_id,
        created_at, updated_at, updated_by
    )
    VALUES (
        'test-device-001',
        'web',
        'ALAS-TEST001',
        'active',
        1,
        GETUTCDATE(),
        GETUTCDATE(),
        'web-register'
    );
END
GO

-- Insertar sesión de prueba si no existe
IF NOT EXISTS (SELECT 1 FROM tbl_DeviceSession WHERE session_token LIKE 'token_test_%')
BEGIN
    DECLARE @deviceId INT;
    SELECT @deviceId = device_id FROM tbl_DeviceRegistry WHERE device_uid = 'test-device-001';

    IF @deviceId IS NOT NULL
    BEGIN
        INSERT INTO tbl_DeviceSession (
            device_id, session_token, status, expires_at, last_heartbeat_at
        )
        VALUES (
            @deviceId,
            'token_test_' + REPLACE(CAST(NEWID() AS VARCHAR(36)), '-', ''),
            'active',
            DATEADD(hour, 24, GETUTCDATE()),
            GETUTCDATE()
        );
    END
END
GO

PRINT 'Nexora Player seed data applied successfully.';
