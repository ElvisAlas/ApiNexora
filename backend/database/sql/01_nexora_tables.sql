-- NexoraPlayer — Tablas SQL Server
-- Ejecutar en orden. Idempotente (usa IF NOT EXISTS).

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
    PRINT 'tbl_PortalSession creada.';
END

-- ── Datos de prueba: reseller demo ───────────────────────────────────────────
-- Password: nexora2026 (bcrypt hash)
IF NOT EXISTS (SELECT 1 FROM tbl_PortalReseller WHERE email = 'demo@alastv.com')
BEGIN
    INSERT INTO tbl_PortalReseller (name, email, password_hash, is_active)
    VALUES (
        'Reseller Demo',
        'demo@alastv.com',
        '$2y$12$examplehashchangebeforeproduction.......................u',
        1
    );
    PRINT 'Reseller demo insertado (actualiza password_hash antes de produccion).';
END
