<?php
declare(strict_types=1);

// Gestión de sesiones TV y heartbeat
class DeviceSessionService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    public function createOrRenewSession(string $deviceUid): string
    {
        // Cerrar sesiones previas
        $this->db->prepare("
            UPDATE tbl_DeviceSession SET is_active = 0 WHERE device_uid = ?
        ")->execute([$deviceUid]);

        $token = bin2hex(random_bytes(32));

        $this->db->prepare("
            INSERT INTO tbl_DeviceSession
                (device_uid, session_token, is_active, created_at, last_heartbeat_at)
            VALUES (?, ?, 1, GETDATE(), GETDATE())
        ")->execute([$deviceUid, $token]);

        return $token;
    }

    public function validateSession(string $sessionToken): ?array
    {
        $stmt = $this->db->prepare("
            SELECT ds.device_uid, ds.session_token,
                   dr.status, dr.client_id, dr.platform,
                   dr.trial_expires_at, dr.subscription_expires_at
            FROM tbl_DeviceSession ds
            JOIN tbl_DeviceRegistry dr ON dr.device_uid = ds.device_uid
            WHERE ds.session_token = ? AND ds.is_active = 1
        ");
        $stmt->execute([$sessionToken]);
        $row = $stmt->fetch();
        if (!$row) return null;

        // Verificar que no esté bloqueado ni vencido
        if (in_array($row['status'], ['blocked', 'unlinked'], true)) {
            return null;
        }

        return $row;
    }

    public function heartbeat(string $sessionToken): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tbl_DeviceSession
            SET last_heartbeat_at = GETDATE()
            WHERE session_token = ? AND is_active = 1
        ");
        $stmt->execute([$sessionToken]);
        return $stmt->rowCount() > 0;
    }

    public function endSession(string $sessionToken): void
    {
        $this->db->prepare("
            UPDATE tbl_DeviceSession SET is_active = 0 WHERE session_token = ?
        ")->execute([$sessionToken]);
    }
}
