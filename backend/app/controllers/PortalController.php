<?php
declare(strict_types=1);

// Endpoints del portal web para resellers
class PortalController
{
    private DeviceRegistryService $registry;
    private PDO $db;

    public function __construct()
    {
        $this->registry = new DeviceRegistryService();
        $this->db       = Database::get();
    }

    public function handle(string $action, array $body): void
    {
        switch ($action) {
            case 'portal/login':        $this->login($body);        break;
            case 'portal/clients':      $this->clients($body);      break;
            case 'portal/devices':      $this->devices($body);      break;
            case 'portal/device/detail': $this->deviceDetail($body); break;
            case 'device/activate':     $this->activate($body);     break;
            case 'device/block':        $this->block($body);        break;
            case 'device/unblock':      $this->unblock($body);      break;
            case 'device/extend':       $this->extend($body);       break;
            case 'portal/device/unlink': $this->unlink($body);      break;
            default:
                nexoraError('UNKNOWN_ACTION', "Accion de portal no reconocida: $action");
        }
    }

    // ── Auth ────────────────────────────────────────────────────────────────

    private function login(array $body): void
    {
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!$email || !$password) {
            nexoraError('MISSING_CREDENTIALS', 'Email y contrasena son requeridos.');
        }

        // Buscar reseller en tbl_PortalReseller
        $stmt = $this->db->prepare("
            SELECT id, name, email, password_hash, is_active
            FROM tbl_PortalReseller
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $reseller = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reseller || !password_verify($password, $reseller['password_hash'])) {
            nexoraError('INVALID_CREDENTIALS', 'Credenciales invalidas.', 401);
        }

        if (!$reseller['is_active']) {
            nexoraError('ACCOUNT_INACTIVE', 'La cuenta del reseller esta inactiva.', 403);
        }

        $token = $this->issuePortalToken((int)$reseller['id']);

        nexoraSuccess([
            'token'       => $token,
            'reseller_id' => $reseller['id'],
            'name'        => $reseller['name'],
        ], 'authenticated');
    }

    private function issuePortalToken(int $resellerId): string
    {
        $token = bin2hex(random_bytes(24));
        $this->db->prepare("
            INSERT INTO tbl_PortalSession (reseller_id, token, created_at, expires_at)
            VALUES (?, ?, GETDATE(), DATEADD(hour, 8, GETDATE()))
        ")->execute([$resellerId, $token]);
        return $token;
    }

    // ── Helpers de auth portal ──────────────────────────────────────────────

    private function requirePortalAuth(array $body): int
    {
        $token = $body['portal_token'] ?? '';
        if (!$token) {
            nexoraError('UNAUTHORIZED', 'Token de portal requerido.', 401);
        }

        $stmt = $this->db->prepare("
            SELECT reseller_id FROM tbl_PortalSession
            WHERE token = ? AND expires_at > GETDATE()
        ");
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            nexoraError('SESSION_EXPIRED', 'Sesion de portal expirada o invalida.', 401);
        }

        return (int)$row['reseller_id'];
    }

    // ── Clientes del reseller ───────────────────────────────────────────────

    private function clients(array $body): void
    {
        $resellerId = $this->requirePortalAuth($body);

        try {
            $stmt = $this->db->prepare("
                SELECT c.id AS client_id, c.name, c.status AS plan
                FROM tbl_ClientXtream c
                WHERE c.reseller_id = ?
                ORDER BY c.name
            ");
            $stmt->execute([$resellerId]);
            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            nexoraSuccess(['clients' => $clients ?: []]);
        } catch (Exception $e) {
            Logger::error('clients() failed', ['error' => $e->getMessage()]);
            nexoraSuccess(['clients' => []]);
        }
    }

    // ── Dispositivos del reseller ───────────────────────────────────────────

    private function devices(array $body): void
    {
        $resellerId = $this->requirePortalAuth($body);

        try {
            $devices = $this->registry->getDevicesByReseller($resellerId);
            nexoraSuccess(['devices' => $devices ?: []]);
        } catch (Exception $e) {
            Logger::error('devices() failed', ['error' => $e->getMessage()]);
            nexoraSuccess(['devices' => []]);
        }
    }

    // ── Detalle por código ──────────────────────────────────────────────────

    private function deviceDetail(array $body): void
    {
        $this->requirePortalAuth($body);
        $code = strtoupper(trim($body['activation_code'] ?? ''));

        if (!$code) {
            nexoraError('MISSING_CODE', 'activation_code es requerido.');
        }

        $device = $this->registry->getByCode($code);
        if (!$device) {
            nexoraError('NOT_FOUND', "Codigo no encontrado: $code", 404);
        }

        nexoraSuccess(['device' => $device]);
    }

    // ── Activar ─────────────────────────────────────────────────────────────

    private function activate(array $body): void
    {
        $this->requirePortalAuth($body);

        $code     = strtoupper(trim($body['activation_code'] ?? ''));
        $clientId = (int)($body['client_id'] ?? 0);
        $plan     = $body['plan'] ?? 'trial';
        $expires  = $body['expires_at'] ?? null;

        if (!$code || !$clientId) {
            nexoraError('MISSING_FIELDS', 'activation_code y client_id son requeridos.');
        }

        try {
            $device = $this->registry->activateDevice($code, $clientId, $plan, $expires);
        } catch (Exception $e) {
            nexoraError('ACTIVATION_FAILED', $e->getMessage());
        }

        nexoraSuccess(['device' => $device], 'activated');
    }

    // ── Bloquear ────────────────────────────────────────────────────────────

    private function block(array $body): void
    {
        $this->requirePortalAuth($body);
        $code = strtoupper(trim($body['activation_code'] ?? ''));
        if (!$code) { nexoraError('MISSING_CODE', 'activation_code es requerido.'); }
        nexoraSuccess(['device' => $this->registry->blockDevice($code)], 'blocked');
    }

    // ── Desbloquear ─────────────────────────────────────────────────────────

    private function unblock(array $body): void
    {
        $this->requirePortalAuth($body);
        $code = strtoupper(trim($body['activation_code'] ?? ''));
        if (!$code) { nexoraError('MISSING_CODE', 'activation_code es requerido.'); }
        nexoraSuccess(['device' => $this->registry->unblockDevice($code)], 'unblocked');
    }

    // ── Extender ────────────────────────────────────────────────────────────

    private function extend(array $body): void
    {
        $this->requirePortalAuth($body);
        $code = strtoupper(trim($body['activation_code'] ?? ''));
        $days = max(1, (int)($body['extend_days'] ?? 30));
        if (!$code) { nexoraError('MISSING_CODE', 'activation_code es requerido.'); }
        nexoraSuccess(['device' => $this->registry->extendDevice($code, $days)], 'extended');
    }

    // ── Desvincular ─────────────────────────────────────────────────────────

    private function unlink(array $body): void
    {
        $this->requirePortalAuth($body);
        $code = strtoupper(trim($body['activation_code'] ?? ''));
        if (!$code) { nexoraError('MISSING_CODE', 'activation_code es requerido.'); }
        nexoraSuccess(['device' => $this->registry->unlinkDevice($code)], 'unlinked');
    }
}
