<?php
declare(strict_types=1);

// Controlador principal para dispositivos TV (todas las plataformas)
class TvApiController
{
    private DeviceRegistryService $registry;
    private DeviceSessionService  $sessions;
    private PDO $db;

    public function __construct()
    {
        $this->registry = new DeviceRegistryService();
        $this->sessions = new DeviceSessionService();
        $this->db       = Database::get();
    }

    public function handle(string $action, array $body): void
    {
        // Acciones públicas (sin session_token)
        $publicActions = ['device/register', 'device/status'];
        if (!in_array($action, $publicActions, true)) {
            $this->requireSession($body);
        }

        switch ($action) {
            // ── Device lifecycle ─────────────────────────────────────────
            case 'device/register':   $this->deviceRegister($body);   break;
            case 'device/status':     $this->deviceStatus($body);     break;
            case 'device/heartbeat':  $this->deviceHeartbeat($body);  break;
            case 'device/unlink':     $this->deviceUnlink($body);     break;
            case 'session/end':       $this->sessionEnd($body);       break;

            // ── Catalog ──────────────────────────────────────────────────
            case 'catalog/live/categories':   $this->catalogCategories('live', $body);   break;
            case 'catalog/live/streams':      $this->catalogStreams('live', $body);      break;
            case 'catalog/vod/categories':    $this->catalogCategories('vod', $body);    break;
            case 'catalog/vod/streams':       $this->catalogStreams('vod', $body);       break;
            case 'catalog/series/categories': $this->catalogCategories('series', $body); break;
            case 'catalog/series':            $this->catalogStreams('series', $body);    break;

            // ── Playback ─────────────────────────────────────────────────
            case 'playback/resolve': $this->playbackResolve($body); break;
            case 'playback/start':   $this->playbackEvent('start', $body); break;
            case 'playback/progress': $this->playbackEvent('progress', $body); break;
            case 'playback/end':     $this->playbackEvent('end', $body); break;

            default:
                nexoraError('UNKNOWN_ACTION', "Accion no reconocida: $action");
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function requireSession(array $body): array
    {
        $token = $body['session_token'] ?? '';
        if (!$token) {
            nexoraError('SESSION_REQUIRED', 'session_token es requerido.', 401);
        }

        $session = $this->sessions->validateSession($token);
        if (!$session) {
            nexoraError('INVALID_SESSION', 'Sesion invalida, expirada o dispositivo bloqueado.', 401);
        }

        return $session;
    }

    private function getXtreamCredentials(int $clientId): ?array
    {
        // Usa la misma vista que AlasTV — vw_ClientLiveAccessDefault en alastv_db
        // Si NexoraPlayer usa BD separada, reemplazar con tbl_ClientXtream
        $sourceDb = preg_replace('/[^A-Za-z0-9_]/', '', (string)LIVEACCESS_DB_NAME);
        if (!$sourceDb) {
            $sourceDb = preg_replace('/[^A-Za-z0-9_]/', '', (string)DB_NAME);
        }

        $stmt = $this->db->prepare("
            SELECT base_url, xtream_username AS username, xtream_password AS password
            FROM [{$sourceDb}].dbo.vw_ClientLiveAccessDefault
            WHERE client_id = ? AND habilitado = 1
        ");
        $stmt->execute([$clientId]);
        return $stmt->fetch() ?: null;
    }

    private function xtreamRequest(string $baseUrl, string $username, string $password, array $params = []): ?array
    {
        $url = rtrim($baseUrl, '/') . '/player_api.php?username=' . urlencode($username) . '&password=' . urlencode($password);
        foreach ($params as $k => $v) {
            $url .= '&' . $k . '=' . urlencode($v);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) return null;
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    // ── Device ───────────────────────────────────────────────────────────────

    private function deviceRegister(array $body): void
    {
        $deviceUid = trim($body['device_uid'] ?? '');
        $platform  = trim($body['platform'] ?? 'web');

        if (!$deviceUid) {
            nexoraError('MISSING_DEVICE_UID', 'device_uid es requerido.');
        }

        try {
            $device = $this->registry->registerDevice($deviceUid, $platform);
        } catch (InvalidArgumentException $e) {
            nexoraError('INVALID_PLATFORM', $e->getMessage());
        }

        nexoraSuccess([
            'device_uid'      => $device['device_uid'],
            'activation_code' => $device['activation_code'],
            'status'          => $device['status'],
            'platform'        => $device['platform'],
        ], $device['status']);
    }

    private function deviceStatus(array $body): void
    {
        $deviceUid = trim($body['device_uid'] ?? '');
        if (!$deviceUid) {
            nexoraError('MISSING_DEVICE_UID', 'device_uid es requerido.');
        }

        $device = $this->registry->getStatus($deviceUid);
        if (!$device) {
            nexoraError('DEVICE_NOT_FOUND', 'Dispositivo no registrado.', 404);
        }

        $data = [
            'device_uid'      => $device['device_uid'],
            'activation_code' => $device['activation_code'],
            'status'          => $device['status'],
            'platform'        => $device['platform'],
        ];

        // Si activo/trial y no tiene sesión, crearla
        if (in_array($device['status'], ['active', 'trial'], true)) {
            $token = $device['session_token'] ?? null;
            if (!$token) {
                $token = $this->sessions->createOrRenewSession($deviceUid);
            }
            $data['session_token'] = $token;
            $data['client_id']     = $device['client_id'];
        }

        nexoraSuccess($data, $device['status']);
    }

    private function deviceHeartbeat(array $body): void
    {
        $token = $body['session_token'] ?? '';
        $this->sessions->heartbeat($token);
        nexoraSuccess([], 'ok');
    }

    private function deviceUnlink(array $body): void
    {
        $session = $this->requireSession($body);
        $code = $body['activation_code'] ?? '';
        if ($code) {
            $this->registry->unlinkDevice($code);
        }
        $this->sessions->endSession($body['session_token']);
        nexoraSuccess([], 'unlinked');
    }

    private function sessionEnd(array $body): void
    {
        $this->sessions->endSession($body['session_token'] ?? '');
        nexoraSuccess([], 'ended');
    }

    // ── Catalog ──────────────────────────────────────────────────────────────

    private function catalogCategories(string $type, array $body): void
    {
        $session = $this->requireSession($body);
        $creds   = $this->getXtreamCredentials((int)$session['client_id']);

        if (!$creds) {
            nexoraError('NO_XTREAM_ACCOUNT', 'Sin cuenta Xtream asignada.');
        }

        $action = ['live' => 'get_live_categories', 'vod' => 'get_vod_categories', 'series' => 'get_series_categories'][$type] ?? 'get_live_categories';
        $data = $this->xtreamRequest($creds['base_url'], $creds['username'], $creds['password'], ['action' => $action]);

        nexoraSuccess(['categories' => $data ?? []]);
    }

    private function catalogStreams(string $type, array $body): void
    {
        $session    = $this->requireSession($body);
        $creds      = $this->getXtreamCredentials((int)$session['client_id']);
        $categoryId = $body['category_id'] ?? '';

        if (!$creds) {
            nexoraError('NO_XTREAM_ACCOUNT', 'Sin cuenta Xtream asignada.');
        }

        $action = ['live' => 'get_live_streams', 'vod' => 'get_vod_streams', 'series' => 'get_series'][$type] ?? 'get_live_streams';
        $params = ['action' => $action];
        if ($categoryId) { $params['category_id'] = $categoryId; }

        $data = $this->xtreamRequest($creds['base_url'], $creds['username'], $creds['password'], $params);

        nexoraSuccess(['streams' => $data ?? []]);
    }

    // ── Playback ─────────────────────────────────────────────────────────────

    private function playbackResolve(array $body): void
    {
        $session   = $this->requireSession($body);
        $creds     = $this->getXtreamCredentials((int)$session['client_id']);
        $contentId = $body['content_id'] ?? '';
        $type      = $body['content_type'] ?? 'live';
        $ext       = $body['container_extension'] ?? 'm3u8';

        if (!$creds || !$contentId) {
            nexoraError('MISSING_PARAMS', 'content_id y cuenta Xtream son requeridos.');
        }

        $base = rtrim($creds['base_url'], '/');
        $u    = urlencode($creds['username']);
        $p    = urlencode($creds['password']);

        if ($type === 'live') {
            $url = "$base/live/$u/$p/$contentId.m3u8";
        } elseif ($type === 'vod') {
            $url = "$base/movie/$u/$p/$contentId.$ext";
        } else {
            $url = "$base/series/$u/$p/$contentId.$ext";
        }

        nexoraSuccess(['direct_stream_url' => $url], 'resolved');
    }

    private function playbackEvent(string $event, array $body): void
    {
        // Registrar en tbl_DevicePlayback
        $session   = $this->requireSession($body);
        $contentId = $body['content_id'] ?? '';
        $type      = $body['content_type'] ?? 'live';

        if ($contentId) {
            try {
                $this->db->prepare("
                    INSERT INTO tbl_DevicePlayback
                        (device_uid, content_id, content_type, event, created_at)
                    VALUES (?, ?, ?, ?, GETDATE())
                ")->execute([$session['device_uid'], $contentId, $type, $event]);
            } catch (Exception $e) {
                // No bloquear reproducción por error de tracking
            }
        }

        nexoraSuccess([], $event);
    }
}
