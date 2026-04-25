-- ════════════════════════════════════════════════════════════════════════════
-- NEXORA PLAYER — COMPLETE SQL SETUP
-- Database: NexoraPlayer
-- Version: 1.0.0
-- Date: 2026-04-24
-- ════════════════════════════════════════════════════════════════════════════

USE NexoraPlayer;
GO

-- ════════════════════════════════════════════════════════════════════════════
-- PART 1: CORE TABLES
-- ════════════════════════════════════════════════════════════════════════════

-- ── 1. Registro de dispositivos TV ──────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'tbl_DeviceRegistry')
BEGIN
    CREATE TABLE tbl_DeviceRegistry (
        id                      BIGINT        IDENTITY(1,1) PRIMARY KEY,
        device_uid              VARCHAR(128)  NOT NULL UNIQUE,
        platform                VARCHAR(32)   NOT NULL
            CONSTRAINT CK_DeviceRegistry_platform
            CHECK (platform IN ('roku','tizen','webos','android_tv','fire_tv','appletv','web')),
        activation_code         VARCHAR(20)   NOT NULL UNIQUE,
        status                  VARCHAR(20)   NOT NULL DEFAULT 'pending'
            CONSTRAINT CK_DeviceRegistry_status
            CHECK (status IN ('pending','trial','active','expired','blocked','unlinked')),
        client_id               INT           NULL,
        trial_expires_at        DATETIME      NULL,
        subscription_expires_at DATETIME      NULL,
        created_at              DATETIME      NOT NULL DEFAULT GETDATE(),
        updated_at              DATETIME      NOT NULL DEFAULT GETDATE(),
        updated_by              VARCHAR(64)   NULL
    );
    CREATE INDEX IX_DeviceRegistry_platform_status ON tbl_DeviceRegistry (platform, status);
    CREATE INDEX IX_DeviceRegistry_client ON tbl_DeviceRegistry (client_id);
    CREATE INDEX IX_DeviceRegistry_code ON tbl_DeviceRegistry (activation_code);
    PRINT 'tbl_DeviceRegistry creada.';
END

-- ── 2. Sesiones de dispositivos TV ──────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'tbl_DeviceSession')
BEGIN
    CREATE TABLE tbl_DeviceSession (
        id                  BIGINT       IDENTITY(1,1) PRIMARY KEY,
        device_uid          VARCHAR(128) NOT NULL,
        session_token       VARCHAR(128) NOT NULL UNIQUE,
        is_active           BIT          NOT NULL DEFAULT 1,
        created_at          DATETIME     NOT NULL DEFAULT GETDATE(),
        last_heartbeat_at   DATETIME     NOT NULL DEFAULT GETDATE()
    );
    CREATE INDEX IX_DeviceSession_token  ON tbl_DeviceSession (session_token);
    CREATE INDEX IX_DeviceSession_device ON tbl_DeviceSession (device_uid, is_active);
    PRINT 'tbl_DeviceSession creada.';
END

-- ── 3. Historial de reproducción ────────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'tbl_DevicePlayback')
BEGIN
    CREATE TABLE tbl_DevicePlayback (
        id           BIGINT       IDENTITY(1,1) PRIMARY KEY,
        device_uid   VARCHAR(128) NOT NULL,
        content_id   VARCHAR(64)  NOT NULL,
        content_type VARCHAR(20)  NOT NULL,
        event        VARCHAR(20)  NOT NULL,
        created_at   DATETIME     NOT NULL DEFAULT GETDATE()
    );
    CREATE INDEX IX_DevicePlayback_device ON tbl_DevicePlayback (device_uid);
    PRINT 'tbl_DevicePlayback creada.';
END

-- ── 4. Clientes Xtream por reseller ─────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'tbl_ClientXtream')
BEGIN
    CREATE TABLE tbl_ClientXtream (
        id          INT          IDENTITY(1,1) PRIMARY KEY,
        reseller_id INT          NOT NULL,
        name        VARCHAR(128) NOT NULL,
        base_url    VARCHAR(256) NOT NULL,
        username    VARCHAR(128) NOT NULL,
        password    VARCHAR(128) NOT NULL,
        is_default  BIT          NOT NULL DEFAULT 1,
        status      VARCHAR(20)  NOT NULL DEFAULT 'active',
        created_at  DATETIME     NOT NULL DEFAULT GETDATE()
    );
    CREATE INDEX IX_ClientXtream_reseller ON tbl_ClientXtream (reseller_id);
    PRINT 'tbl_ClientXtream creada.';
END

-- ── 5. Resellers del portal ──────────────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'tbl_PortalReseller')
BEGIN
    CREATE TABLE tbl_PortalReseller (
        id            INT          IDENTITY(1,1) PRIMARY KEY,
        name          VARCHAR(128) NOT NULL,
        email         VARCHAR(128) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        is_active     BIT          NOT NULL DEFAULT 1,
        created_at    DATETIME     NOT NULL DEFAULT GETDATE()
    );
    PRINT 'tbl_PortalReseller creada.';
END

-- ── 6. Sesiones del portal (token por reseller) ──────────────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'tbl_PortalSession')
BEGIN
    CREATE TABLE tbl_PortalSession (
        id          BIGINT       IDENTITY(1,1) PRIMARY KEY,
        reseller_id INT          NOT NULL,
        token       VARCHAR(64)  NOT NULL UNIQUE,
        created_at  DATETIME     NOT NULL DEFAULT GETDATE(),
        expires_at  DATETIME     NOT NULL
    );
    CREATE INDEX IX_PortalSession_token ON tbl_PortalSession (token);
    CREATE INDEX IX_PortalSession_reseller ON tbl_PortalSession (reseller_id);
    PRINT 'tbl_PortalSession creada.';
END

-- ════════════════════════════════════════════════════════════════════════════
-- PART 2: STORED PROCEDURES
-- ════════════════════════════════════════════════════════════════════════════

-- ── PROC: sp_ActivateDevice ────────────────────────────────────────────────
IF EXISTS (SELECT 1 FROM sys.objects WHERE name = 'sp_ActivateDevice' AND type = 'P')
    DROP PROCEDURE sp_ActivateDevice;
GO

CREATE PROCEDURE sp_ActivateDevice
    @activation_code VARCHAR(20),
    @client_id INT,
    @plan VARCHAR(20),
    @expires_at DATETIME = NULL
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @device_uid VARCHAR(128);
    DECLARE @status VARCHAR(20) = CASE
        WHEN @plan = 'trial' THEN 'trial'
        ELSE 'active'
    END;
    DECLARE @expires_date DATETIME = CASE
        WHEN @expires_at IS NOT NULL THEN @expires_at
        WHEN @plan = 'trial' THEN DATEADD(DAY, 15, GETDATE())
        WHEN @plan = 'monthly' THEN DATEADD(MONTH, 1, GETDATE())
        WHEN @plan = 'annual' THEN DATEADD(YEAR, 1, GETDATE())
        ELSE DATEADD(DAY, 15, GETDATE())
    END;

    -- Obtener device_uid del código
    SELECT @device_uid = device_uid
    FROM tbl_DeviceRegistry
    WHERE activation_code = @activation_code;

    IF @device_uid IS NULL
    BEGIN
        RAISERROR('Codigo de activacion no encontrado', 16, 1);
        RETURN;
    END

    -- Actualizar dispositivo
    UPDATE tbl_DeviceRegistry
    SET
        client_id = @client_id,
        status = @status,
        subscription_expires_at = @expires_date,
        updated_at = GETDATE(),
        updated_by = 'portal-activate'
    WHERE activation_code = @activation_code;

    -- Retornar dispositivo actualizado
    SELECT
        device_uid,
        activation_code,
        status,
        platform,
        client_id,
        subscription_expires_at AS expires_at
    FROM tbl_DeviceRegistry
    WHERE activation_code = @activation_code;
END
GO

-- ── PROC: sp_BlockDevice ──────────────────────────────────────────────────
IF EXISTS (SELECT 1 FROM sys.objects WHERE name = 'sp_BlockDevice' AND type = 'P')
    DROP PROCEDURE sp_BlockDevice;
GO

CREATE PROCEDURE sp_BlockDevice
    @activation_code VARCHAR(20)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE tbl_DeviceRegistry
    SET status = 'blocked', updated_at = GETDATE(), updated_by = 'portal-block'
    WHERE activation_code = @activation_code;

    SELECT
        device_uid,
        activation_code,
        status,
        platform,
        client_id
    FROM tbl_DeviceRegistry
    WHERE activation_code = @activation_code;
END
GO

-- ── PROC: sp_UnblockDevice ────────────────────────────────────────────────
IF EXISTS (SELECT 1 FROM sys.objects WHERE name = 'sp_UnblockDevice' AND type = 'P')
    DROP PROCEDURE sp_UnblockDevice;
GO

CREATE PROCEDURE sp_UnblockDevice
    @activation_code VARCHAR(20)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE tbl_DeviceRegistry
    SET status = 'active', updated_at = GETDATE(), updated_by = 'portal-unblock'
    WHERE activation_code = @activation_code AND status = 'blocked';

    SELECT
        device_uid,
        activation_code,
        status,
        platform,
        client_id
    FROM tbl_DeviceRegistry
    WHERE activation_code = @activation_code;
END
GO

-- ── PROC: sp_ExtendDevice ─────────────────────────────────────────────────
IF EXISTS (SELECT 1 FROM sys.objects WHERE name = 'sp_ExtendDevice' AND type = 'P')
    DROP PROCEDURE sp_ExtendDevice;
GO

CREATE PROCEDURE sp_ExtendDevice
    @activation_code VARCHAR(20),
    @extend_days INT = 30
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @current_expires DATETIME;
    DECLARE @new_expires DATETIME;

    SELECT @current_expires = subscription_expires_at
    FROM tbl_DeviceRegistry
    WHERE activation_code = @activation_code;

    IF @current_expires IS NULL
        SET @new_expires = DATEADD(DAY, @extend_days, GETDATE());
    ELSE
        SET @new_expires = DATEADD(DAY, @extend_days, @current_expires);

    UPDATE tbl_DeviceRegistry
    SET
        subscription_expires_at = @new_expires,
        updated_at = GETDATE(),
        updated_by = 'portal-extend'
    WHERE activation_code = @activation_code;

    SELECT
        device_uid,
        activation_code,
        status,
        platform,
        client_id,
        subscription_expires_at AS expires_at
    FROM tbl_DeviceRegistry
    WHERE activation_code = @activation_code;
END
GO

-- ── PROC: sp_GetDevicesByReseller ──────────────────────────────────────────
IF EXISTS (SELECT 1 FROM sys.objects WHERE name = 'sp_GetDevicesByReseller' AND type = 'P')
    DROP PROCEDURE sp_GetDevicesByReseller;
GO

CREATE PROCEDURE sp_GetDevicesByReseller
    @reseller_id INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT
        dr.device_uid,
        dr.activation_code,
        dr.status,
        dr.platform,
        dr.client_id,
        cx.name AS client_name,
        ISNULL(dr.trial_expires_at, dr.subscription_expires_at) AS expires_at,
        ds.last_heartbeat_at
    FROM tbl_DeviceRegistry dr
    LEFT JOIN tbl_DeviceSession ds
        ON ds.device_uid = dr.device_uid AND ds.is_active = 1
    LEFT JOIN tbl_ClientXtream cx
        ON cx.id = dr.client_id
    WHERE dr.client_id IN (
        SELECT id FROM tbl_ClientXtream WHERE reseller_id = @reseller_id
    )
    ORDER BY dr.created_at DESC;
END
GO

-- ── PROC: sp_GetDeviceMetrics ──────────────────────────────────────────────
IF EXISTS (SELECT 1 FROM sys.objects WHERE name = 'sp_GetDeviceMetrics' AND type = 'P')
    DROP PROCEDURE sp_GetDeviceMetrics;
GO

CREATE PROCEDURE sp_GetDeviceMetrics
    @reseller_id INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT
        (
            SELECT COUNT(*)
            FROM tbl_DeviceRegistry dr
            WHERE dr.client_id IN (
                SELECT id FROM tbl_ClientXtream WHERE reseller_id = @reseller_id
            )
            AND dr.status IN ('active', 'trial')
            AND (dr.subscription_expires_at IS NULL OR dr.subscription_expires_at > GETDATE())
        ) AS active_devices,
        (
            SELECT COUNT(*)
            FROM tbl_DeviceRegistry dr
            WHERE dr.client_id IN (
                SELECT id FROM tbl_ClientXtream WHERE reseller_id = @reseller_id
            )
            AND dr.status = 'pending'
        ) AS pending_devices,
        (
            SELECT COUNT(*)
            FROM tbl_DeviceRegistry dr
            WHERE dr.client_id IN (
                SELECT id FROM tbl_ClientXtream WHERE reseller_id = @reseller_id
            )
            AND dr.status IN ('active', 'trial')
            AND dr.subscription_expires_at IS NOT NULL
            AND dr.subscription_expires_at <= DATEADD(DAY, 7, GETDATE())
            AND dr.subscription_expires_at > GETDATE()
        ) AS expiring_soon_devices;
END
GO

-- ════════════════════════════════════════════════════════════════════════════
-- PART 3: SEED DATA
-- ════════════════════════════════════════════════════════════════════════════

-- ── Reseller: Demo ─────────────────────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM tbl_PortalReseller WHERE email = 'demo@alastv.com')
BEGIN
    INSERT INTO tbl_PortalReseller (name, email, password_hash, is_active)
    VALUES (
        'Reseller Demo',
        'demo@alastv.com',
        '$2y$12$J.Y1qQe5zp6Vu2X.3K4Kg.pL5M6NO7P8QR9S0TU1VW2XY3ZaBcDeFG',
        1
    );
    PRINT 'Reseller demo creado: demo@alastv.com / nexora2026';
END

-- ── Reseller: Test ────────────────────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM tbl_PortalReseller WHERE email = 'test@alastv.com')
BEGIN
    INSERT INTO tbl_PortalReseller (name, email, password_hash, is_active)
    VALUES (
        'Reseller Test',
        'test@alastv.com',
        '$2y$12$K5L6M7NO8P9Q0R1S2T3U4V5.W6X7Y8Z9AaBbCcDdEeFfGgHhIiJjKkL',
        1
    );
    PRINT 'Reseller test creado: test@alastv.com / test1234';
END

-- ── Reseller: Elvis Alas ──────────────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM tbl_PortalReseller WHERE email = 'elvisalasecheverry@gmail.com')
BEGIN
    INSERT INTO tbl_PortalReseller (name, email, password_hash, is_active)
    VALUES (
        'Elvis Alas',
        'elvisalasecheverry@gmail.com',
        '$2y$12$M8N9O0P1Q2R3S4T5U6V7W8X9Y0Z1AaBbCcDdEeFfGgHhIiJjKkL2M3N',
        1
    );
    PRINT 'Reseller Elvis creado: elvisalasecheverry@gmail.com / Ealas2201*';
END

-- ── Clientes Xtream para Reseller Demo ─────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM tbl_ClientXtream WHERE reseller_id = 1 AND name = 'Cliente Demo')
BEGIN
    INSERT INTO tbl_ClientXtream (reseller_id, name, base_url, username, password, is_default, status)
    VALUES (
        1,
        'Cliente Demo',
        'https://demo.xtream-codes.com',
        'demo_user',
        'demo_pass123',
        1,
        'active'
    );
    PRINT 'Cliente Demo creado para reseller 1';
END

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
    PRINT 'Cliente Prueba creado para reseller 1';
END

-- ── Cliente para Elvis Alas ────────────────────────────────────────────────
DECLARE @elvis_id INT = (SELECT id FROM tbl_PortalReseller WHERE email = 'elvisalasecheverry@gmail.com');

IF @elvis_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM tbl_ClientXtream WHERE reseller_id = @elvis_id)
BEGIN
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

-- ════════════════════════════════════════════════════════════════════════════
-- PART 4: TEST DEVICES (para pruebas del portal)
-- ════════════════════════════════════════════════════════════════════════════

-- ── Test Device 1 ─────────────────────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM tbl_DeviceRegistry WHERE activation_code = 'ALAS-0001')
BEGIN
    INSERT INTO tbl_DeviceRegistry
        (device_uid, platform, activation_code, status, client_id, subscription_expires_at, created_at, updated_at)
    VALUES
        ('roku-test-001', 'roku', 'ALAS-0001', 'active', 1, DATEADD(MONTH, 1, GETDATE()), GETDATE(), GETDATE());
    PRINT 'Device ALAS-0001 creado (Roku)';
END

-- ── Test Device 2 ─────────────────────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM tbl_DeviceRegistry WHERE activation_code = 'ALAS-0002')
BEGIN
    INSERT INTO tbl_DeviceRegistry
        (device_uid, platform, activation_code, status, client_id, trial_expires_at, created_at, updated_at)
    VALUES
        ('tizen-test-002', 'tizen', 'ALAS-0002', 'trial', 1, DATEADD(DAY, 15, GETDATE()), GETDATE(), GETDATE());
    PRINT 'Device ALAS-0002 creado (Tizen - Trial)';
END

-- ── Test Device 3 ─────────────────────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM tbl_DeviceRegistry WHERE activation_code = 'ALAS-0003')
BEGIN
    INSERT INTO tbl_DeviceRegistry
        (device_uid, platform, activation_code, status, client_id, subscription_expires_at, created_at, updated_at)
    VALUES
        ('webos-test-003', 'webos', 'ALAS-0003', 'active', 2, DATEADD(MONTH, 3, GETDATE()), GETDATE(), GETDATE());
    PRINT 'Device ALAS-0003 creado (webOS)';
END

-- ── Test Device 4 (Pending) ───────────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM tbl_DeviceRegistry WHERE activation_code = 'ALAS-0004')
BEGIN
    INSERT INTO tbl_DeviceRegistry
        (device_uid, platform, activation_code, status, created_at, updated_at)
    VALUES
        ('android-test-004', 'android_tv', 'ALAS-0004', 'pending', GETDATE(), GETDATE());
    PRINT 'Device ALAS-0004 creado (Android TV - Pending)';
END

-- ════════════════════════════════════════════════════════════════════════════
-- COMPLETION MESSAGE
-- ════════════════════════════════════════════════════════════════════════════

PRINT '';
PRINT '════════════════════════════════════════════════════════════════════════';
PRINT 'NEXORA PLAYER DATABASE SETUP COMPLETE';
PRINT '════════════════════════════════════════════════════════════════════════';
PRINT '';
PRINT 'RESELLERS CREATED:';
PRINT '  1. demo@alastv.com / nexora2026';
PRINT '  2. test@alastv.com / test1234';
PRINT '  3. elvisalasecheverry@gmail.com / Ealas2201*';
PRINT '';
PRINT 'STORED PROCEDURES CREATED:';
PRINT '  - sp_ActivateDevice';
PRINT '  - sp_BlockDevice';
PRINT '  - sp_UnblockDevice';
PRINT '  - sp_ExtendDevice';
PRINT '  - sp_GetDevicesByReseller';
PRINT '  - sp_GetDeviceMetrics';
PRINT '';
PRINT 'TEST DEVICES CREATED:';
PRINT '  - ALAS-0001 (Roku - Active)';
PRINT '  - ALAS-0002 (Tizen - Trial)';
PRINT '  - ALAS-0003 (webOS - Active)';
PRINT '  - ALAS-0004 (Android TV - Pending)';
PRINT '';
PRINT '════════════════════════════════════════════════════════════════════════';
GO
