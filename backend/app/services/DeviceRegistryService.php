<?php
declare(strict_types=1);

// Gestión del ciclo de vida de dispositivos TV
class DeviceRegistryService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    // Registra un dispositivo nuevo o devuelve el existente
    public function registerDevice(string $deviceUid, string $platform): array
    {
        $platforms = explode(',', TV_PLATFORMS_ALLOWED);
        if (!in_array($platform, $platforms, true)) {
            throw new InvalidArgumentException("Plataforma no valida: $platform");
        }

        // Buscar existente
        $stmt = $this->db->prepare("
            SELECT device_uid, activation_code, status, client_id, platform,
                   trial_expires_at, subscription_expires_at, created_at
            FROM tbl_DeviceRegistry
            WHERE device_uid = ?
        ");
        $stmt->execute([$deviceUid]);
        $existing = $stmt->fetch();

        if ($existing) {
            return $existing;
        }

        // Generar activation_code único ALAS-XXXXXX
        $code = $this->generateActivationCode();

        $stmt = $this->db->prepare("
            INSERT INTO tbl_DeviceRegistry
                (device_uid, platform, activation_code, status, created_at, updated_at, updated_by)
            VALUES (?, ?, ?, 'pending', GETDATE(), GETDATE(), ?)
        ");
        $stmt->execute([$deviceUid, $platform, $code, $platform . '-register']);

        return [
            'device_uid'      => $deviceUid,
            'activation_code' => $code,
            'status'          => 'pending',
            'platform'        => $platform,
            'client_id'       => null,
        ];
    }

    public function getStatus(string $deviceUid): ?array
    {
        $stmt = $this->db->prepare("
            SELECT dr.device_uid, dr.activation_code, dr.status, dr.platform,
                   dr.client_id, dr.trial_expires_at, dr.subscription_expires_at,
                   ds.session_token
            FROM tbl_DeviceRegistry dr
            LEFT JOIN tbl_DeviceSession ds
                ON ds.device_uid = dr.device_uid AND ds.is_active = 1
            WHERE dr.device_uid = ?
        ");
        $stmt->execute([$deviceUid]);
        return $stmt->fetch() ?: null;
    }

    public function activateDevice(string $activationCode, int $clientId, string $plan, ?string $expiresAt = null): array
    {
        // Verificar código existe y está pending
        $stmt = $this->db->prepare("
            SELECT device_uid, status FROM tbl_DeviceRegistry WHERE activation_code = ?
        ");
        $stmt->execute([$activationCode]);
        $device = $stmt->fetch();

        if (!$device) {
            throw new RuntimeException("Codigo de activacion no encontrado: $activationCode");
        }

        $newStatus = ($plan === 'trial') ? 'trial' : 'active';
        $trialExpires = null;
        $subExpires = null;

        if ($plan === 'trial') {
            $trialExpires = date('Y-m-d H:i:s', strtotime('+15 days'));
        } elseif ($expiresAt) {
            $subExpires = $expiresAt;
        } else {
            $subExpires = date('Y-m-d H:i:s', strtotime('+30 days'));
        }

        $stmt = $this->db->prepare("
            UPDATE tbl_DeviceRegistry
            SET status = ?,
                client_id = ?,
                trial_expires_at = ?,
                subscription_expires_at = ?,
                updated_at = GETDATE(),
                updated_by = 'portal-activate'
            WHERE activation_code = ?
        ");
        $stmt->execute([$newStatus, $clientId, $trialExpires, $subExpires, $activationCode]);

        return $this->getByCode($activationCode);
    }

    public function blockDevice(string $activationCode): array
    {
        return $this->updateStatus($activationCode, 'blocked', 'portal-block');
    }

    public function unblockDevice(string $activationCode): array
    {
        return $this->updateStatus($activationCode, 'active', 'portal-unblock');
    }

    public function unlinkDevice(string $activationCode): array
    {
        $stmt = $this->db->prepare("
            UPDATE tbl_DeviceRegistry
            SET status = 'unlinked', client_id = NULL,
                updated_at = GETDATE(), updated_by = 'portal-unlink'
            WHERE activation_code = ?
        ");
        $stmt->execute([$activationCode]);
        return $this->getByCode($activationCode);
    }

    public function extendDevice(string $activationCode, int $days = 30): array
    {
        $stmt = $this->db->prepare("
            UPDATE tbl_DeviceRegistry
            SET subscription_expires_at = DATEADD(day, ?, ISNULL(subscription_expires_at, GETDATE())),
                status = CASE WHEN status IN ('expired') THEN 'active' ELSE status END,
                updated_at = GETDATE(), updated_by = 'portal-extend'
            WHERE activation_code = ?
        ");
        $stmt->execute([$days, $activationCode]);
        return $this->getByCode($activationCode);
    }

    public function getByCode(string $activationCode): ?array
    {
        $stmt = $this->db->prepare("
            SELECT dr.device_uid, dr.activation_code, dr.status, dr.platform,
                   dr.client_id, dr.trial_expires_at, dr.subscription_expires_at,
                   dr.created_at
            FROM tbl_DeviceRegistry dr
            WHERE dr.activation_code = ?
        ");
        $stmt->execute([$activationCode]);
        return $stmt->fetch() ?: null;
    }

    public function getDevicesByReseller(int $resellerId): array
    {
        $stmt = $this->db->prepare("
            SELECT dr.device_uid, dr.activation_code, dr.status, dr.platform,
                   dr.client_id,
                   ISNULL(dr.trial_expires_at, dr.subscription_expires_at) AS expires_at,
                   ds.last_heartbeat_at
            FROM tbl_DeviceRegistry dr
            LEFT JOIN tbl_DeviceSession ds
                ON ds.device_uid = dr.device_uid AND ds.is_active = 1
            WHERE dr.client_id IN (
                SELECT id FROM tbl_ClientXtream WHERE reseller_id = ?
            )
            ORDER BY dr.created_at DESC
        ");
        $stmt->execute([$resellerId]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($devices) ? $devices : [];
    }

    private function updateStatus(string $activationCode, string $status, string $updatedBy): array
    {
        $stmt = $this->db->prepare("
            UPDATE tbl_DeviceRegistry
            SET status = ?, updated_at = GETDATE(), updated_by = ?
            WHERE activation_code = ?
        ");
        $stmt->execute([$status, $updatedBy, $activationCode]);
        return $this->getByCode($activationCode);
    }

    private function generateActivationCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $candidate = 'ALAS-' . $code;

        // Verificar unicidad
        $stmt = $this->db->prepare("SELECT 1 FROM tbl_DeviceRegistry WHERE activation_code = ?");
        $stmt->execute([$candidate]);
        if ($stmt->fetch()) {
            return $this->generateActivationCode();
        }
        return $candidate;
    }
}
