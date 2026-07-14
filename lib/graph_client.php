<?php
/**
 * Cliente mínimo de Microsoft Graph API (flujo "client credentials" / app-only).
 * No usa librerías externas, solo curl.
 *
 * Requiere una app registrada en Azure AD (portal.azure.com) con permisos de
 * tipo Application (no delegados): User.Read.All, Organization.Read.All,
 * Directory.Read.All y, si se quiere restablecer contraseñas, User-PasswordProfile.ReadWrite.All
 * (rol de Azure AD "Administrador de contraseñas" asignado a la app/cuenta que autoriza el consentimiento).
 */

class GraphClientException extends Exception {}

class GraphClient {
    private $tenantId, $clientId, $clientSecret;
    private $token = null;
    private $tokenExpira = 0;

    public function __construct($tenantId, $clientId, $clientSecret) {
        $this->tenantId = $tenantId;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    private function obtenerToken() {
        if ($this->token && time() < $this->tokenExpira - 30) {
            return $this->token;
        }
        $url = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";
        $body = http_build_query([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials',
        ]);
        $resp = $this->curl($url, 'POST', $body, ['Content-Type: application/x-www-form-urlencoded']);
        $data = json_decode($resp['body'], true);
        if ($resp['code'] !== 200 || empty($data['access_token'])) {
            $err = $data['error_description'] ?? $resp['body'];
            throw new GraphClientException("No se pudo autenticar con Microsoft: {$err}");
        }
        $this->token = $data['access_token'];
        $this->tokenExpira = time() + (int) ($data['expires_in'] ?? 3600);
        return $this->token;
    }

    private function curl($url, $method = 'GET', $body = null, $headersExtra = []) {
        $ch = curl_init($url);
        $headers = array_merge(['Accept: application/json'], $headersExtra);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $respBody = curl_exec($ch);
        if ($respBody === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new GraphClientException("Error de red hacia Microsoft Graph: {$err}");
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => $respBody];
    }

    private function get($path) {
        $token = $this->obtenerToken();
        $resp = $this->curl("https://graph.microsoft.com/v1.0{$path}", 'GET', null, ["Authorization: Bearer {$token}"]);
        $data = json_decode($resp['body'], true);
        if ($resp['code'] >= 300) {
            $err = $data['error']['message'] ?? $resp['body'];
            throw new GraphClientException("Graph API [{$resp['code']}]: {$err}");
        }
        return $data;
    }

    private function patch($path, array $body) {
        $token = $this->obtenerToken();
        $resp = $this->curl("https://graph.microsoft.com/v1.0{$path}", 'PATCH', json_encode($body),
            ["Authorization: Bearer {$token}", 'Content-Type: application/json']);
        if ($resp['code'] >= 300) {
            $data = json_decode($resp['body'], true);
            $err = $data['error']['message'] ?? $resp['body'];
            throw new GraphClientException("Graph API [{$resp['code']}]: {$err}");
        }
        return true;
    }

    private function postJson($path, array $body = []) {
        $token = $this->obtenerToken();
        $resp = $this->curl("https://graph.microsoft.com/v1.0{$path}", 'POST', json_encode($body),
            ["Authorization: Bearer {$token}", 'Content-Type: application/json']);
        if ($resp['code'] >= 300) {
            $data = json_decode($resp['body'], true);
            $err = $data['error']['message'] ?? $resp['body'];
            throw new GraphClientException("Graph API [{$resp['code']}]: {$err}");
        }
        return json_decode($resp['body'], true) ?: true;
    }

    /** Prueba de conexión simple. */
    public function probarConexion() {
        $this->obtenerToken();
        return true;
    }

    /** Lista todos los usuarios (paginado), con campos esenciales. */
    public function listarUsuarios() {
        $usuarios = [];
        $path = "/users?\$select=id,displayName,mail,userPrincipalName,accountEnabled,department,jobTitle&\$top=999";
        while ($path) {
            $data = $this->get($path);
            foreach (($data['value'] ?? []) as $u) $usuarios[] = $u;
            $path = isset($data['@odata.nextLink']) ? str_replace('https://graph.microsoft.com/v1.0', '', $data['@odata.nextLink']) : null;
        }
        return $usuarios;
    }

    /** Licencias disponibles en el tenant (SKUs) con cantidades compradas/consumidas. */
    public function listarSkus() {
        $data = $this->get('/subscribedSkus');
        return $data['value'] ?? [];
    }

    /** Licencias asignadas a un usuario específico. */
    public function licenciasDeUsuario($userId) {
        $data = $this->get("/users/{$userId}/licenseDetails");
        return $data['value'] ?? [];
    }

    /**
     * Restablece la contraseña de un usuario (NO puede leer la actual - eso no
     * existe en ninguna API). Genera una nueva y obliga a cambiarla en el
     * próximo inicio de sesión. Requiere rol de Administrador de contraseñas
     * o Administrador de usuarios en el tenant.
     */
    public function restablecerContrasena($userId, $nuevaContrasena, $forzarCambio = true) {
        return $this->patch("/users/{$userId}", [
            'passwordProfile' => [
                'password' => $nuevaContrasena,
                'forceChangePasswordNextSignIn' => $forzarCambio,
            ],
        ]);
    }

    /** Habilita o bloquea una cuenta. Solo debe invocarse desde un ciclo aprobado. */
    public function cambiarEstadoCuenta($userId, bool $activa) {
        $id = rawurlencode((string)$userId);
        return $this->patch("/users/{$id}", ['accountEnabled' => $activa]);
    }

    /** Revoca sesiones y tokens de actualización de una cuenta. */
    public function revocarSesiones($userId) {
        $id = rawurlencode((string)$userId);
        return $this->postJson("/users/{$id}/revokeSignInSessions");
    }

    // ---------------- SharePoint / OneDrive ----------------
    // Requiere el permiso de aplicación adicional: Sites.Read.All (o Sites.ReadWrite.All
    // para subir/crear). Se agrega igual que los demás: Azure AD -> esta misma app ->
    // Permisos de API -> Agregar permiso -> Microsoft Graph -> Aplicación.

    /** Lista los sitios de SharePoint del tenant. */
    public function listarSitiosSharePoint() {
        $data = $this->get('/sites?search=*');
        return $data['value'] ?? [];
    }

    /** Lista archivos/carpetas en la raíz de la biblioteca de documentos de un sitio. */
    public function listarArchivosSitio($siteId, $rutaCarpeta = '') {
        $path = $rutaCarpeta
            ? "/sites/{$siteId}/drive/root:/{$rutaCarpeta}:/children"
            : "/sites/{$siteId}/drive/root/children";
        $data = $this->get($path);
        return $data['value'] ?? [];
    }

    /** Lista el contenido raíz del OneDrive de un usuario específico (por su id o userPrincipalName). */
    public function listarOneDriveUsuario($userId) {
        $data = $this->get("/users/{$userId}/drive/root/children");
        return $data['value'] ?? [];
    }

    // ---------------- Microsoft Teams ----------------
    // Requiere el permiso de aplicación adicional: Team.ReadBasic.All (listar) y
    // Channel.ReadBasic.All (canales). Igual que arriba: se agrega en Permisos de API.

    /** Lista los equipos (Teams) del tenant en los que el usuario dado es miembro. */
    public function listarEquiposDeUsuario($userId) {
        $data = $this->get("/users/{$userId}/joinedTeams");
        return $data['value'] ?? [];
    }

    /** Lista todos los grupos de Microsoft 365 que tienen un Team asociado. */
    public function listarTodosLosEquipos() {
        $filtro = rawurlencode("resourceProvisioningOptions/Any(x:x eq 'Team')");
        $data = $this->get("/groups?\$filter={$filtro}&\$select=id,displayName,description");
        return $data['value'] ?? [];
    }

    /** Lista los canales de un Team específico. */
    public function listarCanalesEquipo($teamId) {
        $data = $this->get("/teams/{$teamId}/channels");
        return $data['value'] ?? [];
    }

    // ---------------- Correo (para convertir en tickets) ----------------
    // Requiere el permiso de aplicación Mail.Read sobre el buzón indicado.

    /** Lee los mensajes no leídos de la bandeja de entrada de un buzón (correo o id de usuario). */
    public function leerCorreosNoLeidos($buzon, $top = 25) {
        $filtro = rawurlencode('isRead eq false');
        $data = $this->get("/users/{$buzon}/mailFolders/inbox/messages?\$filter={$filtro}&\$top={$top}&\$select=id,subject,from,receivedDateTime,bodyPreview");
        return $data['value'] ?? [];
    }

    /** Marca un correo como leído, para no volver a procesarlo. */
    public function marcarCorreoLeido($buzon, $mensajeId) {
        return $this->patch("/users/{$buzon}/messages/{$mensajeId}", ['isRead' => true]);
    }

    /** Últimos correos de la bandeja (leídos y no leídos), para auditoría/verificación. */
    public function leerMensajesRecientes($buzon, $top = 10) {
        $orderby = rawurlencode('receivedDateTime desc');
        $data = $this->get("/users/{$buzon}/mailFolders/inbox/messages?\$top={$top}&\$select=subject,receivedDateTime,isRead&\$orderby={$orderby}");
        return $data['value'] ?? [];
    }

    /** Sube un archivo pequeño (<4MB) al OneDrive de un usuario, dentro de una carpeta dada. */
    public function subirArchivoOneDrive($userId, $carpeta, $nombreArchivo, $contenidoBinario) {
        $token = $this->obtenerToken();
        $carpeta = trim($carpeta, '/');
        $ruta = $carpeta !== '' ? "{$carpeta}/{$nombreArchivo}" : $nombreArchivo;
        $rutaCodificada = implode('/', array_map('rawurlencode', explode('/', $ruta)));
        $resp = $this->curl("https://graph.microsoft.com/v1.0/users/{$userId}/drive/root:/{$rutaCodificada}:/content",
            'PUT', $contenidoBinario, ["Authorization: Bearer {$token}", 'Content-Type: application/octet-stream']);
        $data = json_decode($resp['body'], true);
        if ($resp['code'] >= 300) {
            $err = $data['error']['message'] ?? $resp['body'];
            throw new GraphClientException("Graph API [{$resp['code']}]: {$err}");
        }
        return $data;
    }
}

function generar_contrasena_temporal($longitud = 12) {
    $mayus = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $minus = 'abcdefghijkmnopqrstuvwxyz';
    $num = '23456789';
    $simb = '!@#$%*';
    $todas = $mayus . $minus . $num . $simb;
    $pass = $mayus[random_int(0, strlen($mayus) - 1)]
        . $minus[random_int(0, strlen($minus) - 1)]
        . $num[random_int(0, strlen($num) - 1)]
        . $simb[random_int(0, strlen($simb) - 1)];
    for ($i = strlen($pass); $i < $longitud; $i++) {
        $pass .= $todas[random_int(0, strlen($todas) - 1)];
    }
    return str_shuffle($pass);
}
