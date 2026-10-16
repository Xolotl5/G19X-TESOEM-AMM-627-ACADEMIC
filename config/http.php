<?php
declare(strict_types=1);

/*
  --------------------------------------------------------------------------------
  -- MIDFF - Utilidades HTTP comunes de la API (BIT-036, refactor del Corte 1)
  -- Archivo: config/http.php
  --
  -- Contenido (antes duplicado en los 9 endpoints):
  --   responder()/rechazar()/fallaInterna()  -> respuestas JSON uniformes
  --   midff_env_obligatoria()                -> variable de entorno SIN valor
  --                                             por defecto: si falta, la API
  --                                             se niega a servir (500 claro)
  --   midff_exigir_api_key()                 -> valida X-API-Key (hash_equals)
  --   midff_exigir_cliente_firebase()        -> valida el ID token de Firebase
  --                                             del header Authorization y
  --                                             resuelve SU id_cliente real
  --
  -- SEGURIDAD (diagnostico Corte 1): ningun secreto vive en el codigo. Todos
  --   los valores se inyectan por entorno (ver .env.example y servidor.ps1,
  --   que carga el archivo .env local no versionado).
  --------------------------------------------------------------------------------
 */

date_default_timezone_set('America/Mexico_City');

/** Respuesta JSON uniforme y fin de la peticion. */
function responder(int $codigo, array $cuerpo): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function rechazar(string $mensaje): void
{
    responder(400, ['error' => $mensaje]);
}

function fallaInterna(string $mensaje): void
{
    responder(500, ['error' => $mensaje]);
}

/**
 * Variable de entorno OBLIGATORIA (sin valor por defecto publicable).
 * Si falta, la API se niega a atender la peticion con un 500 explicativo.
 */
function midff_env_obligatoria(string $nombre): string
{
    $valor = getenv($nombre);
    if ($valor === false || trim((string) $valor) === '') {
        error_log('[MIDFF-CONFIG] variable de entorno faltante: ' . $nombre
                . ' (definila en .env o en el entorno; ver .env.example)');
        responder(500, [
            'error' => 'configuracion incompleta del servidor: falta la variable '
                     . $nombre . ' (revise .env / .env.example)',
        ]);
    }
    return (string) $valor;
}

/**
 * Valida el encabezado X-API-Key contra MIDFF_API_KEY (comparacion constante).
 * Cualquier endpoint protegido debe llamarla primero.
 */
function midff_exigir_api_key(): void
{
    $esperada = midff_env_obligatoria('MIDFF_API_KEY');
    $recibida = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($recibida === '' || !hash_equals($esperada, (string) $recibida)) {
        responder(401, ['error' => 'encabezado X-API-Key ausente o invalido']);
    }
}

/**
 * Verifica el ID token de Firebase del header "Authorization: Bearer <token>"
 * contra Identity Toolkit (accounts:lookup con la Web API key del proyecto).
 *
 * @return array{uid: string, email: string}
 */
function midff_verificar_id_token(): array
{
    $encabezado = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if ($encabezado === '' || stripos($encabezado, 'bearer ') !== 0) {
        responder(401, ['error' => 'se requiere el ID token de Firebase '
                                 . '(Authorization: Bearer <idToken>)']);
    }
    $idToken = trim(substr($encabezado, 7));
    if ($idToken === '' || strlen($idToken) > 4096) {
        responder(401, ['error' => 'ID token de Firebase ausente o malformed']);
    }
    $webKey = getenv('MIDFF_FIREBASE_WEB_KEY');
    if ($webKey === false || trim($webKey) === '') {
        error_log('[MIDFF-CONFIG] variable MIDFF_FIREBASE_WEB_KEY faltante '
                . '(Web API key del proyecto Firebase, ver .env.example)');
        responder(500, ['error' => 'configuracion incompleta: falta MIDFF_FIREBASE_WEB_KEY']);
    }
    if (!function_exists('curl_init')) {
        responder(503, ['error' => 'extension curl no disponible para validar el token']);
    }

    // El token es valido si Identity Toolkit lo acepta y devuelve el usuario.
    $canal = curl_init('https://identitytoolkit.googleapis.com/v1/accounts:lookup?key='
                     . urlencode(trim($webKey)));
    curl_setopt_array($canal, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode(['idToken' => $idToken]),
    ]);
    $crudo  = curl_exec($canal);
    $err    = curl_error($canal);
    $codigo = (int) curl_getinfo($canal, CURLINFO_RESPONSE_CODE);
    curl_close($canal);

    if ($crudo === false) {
        error_log('[MIDFF-AUTH] accounts:lookup fallo de red: ' . $err);
        responder(503, ['error' => 'no fue posible validar el token contra Firebase']);
    }
    $respuesta = json_decode((string) $crudo, true);
    if ($codigo !== 200 || !is_array($respuesta) || empty($respuesta['users'][0]['localId'])) {
        responder(401, ['error' => 'ID token de Firebase invalido o expirado']);
    }
    return [
        'uid'   => (string) $respuesta['users'][0]['localId'],
        'email' => (string) ($respuesta['users'][0]['email'] ?? ''),
    ];
}

/**
 * Verifica el ID token de Firebase y resuelve el id_cliente REAL del usuario
 * autenticado desde DISPOSITIVO_MOVIL (uid_firebase -> id_cliente, activo=1).
 *
 * Sustituye la autorizacion por id_cliente confiable (Corte 1): antes, con la
 * llave compartida cualquiera podia pedir las alertas de cualquier cliente;
 * ahora el id_cliente se deriva del token, nunca del parametro de la URL.
 *
 * Requiere config/db.php (midff_db_conectar) previamente cargado.
 *
 * @return string id_cliente autenticado (asociado al uid en DISPOSITIVO_MOVIL)
 */
function midff_exigir_cliente_firebase(): string
{
    $usuario = midff_verificar_id_token();
    try {
        $cnx  = midff_db_conectar();
        $sent = $cnx->prepare('SELECT id_cliente FROM DISPOSITIVO_MOVIL'
                            . ' WHERE uid_firebase = :uid AND activo = 1'
                            . ' FETCH FIRST 1 ROWS ONLY');
        $sent->execute([':uid' => $usuario['uid']]);
        $fila = $sent->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        responder(500, ['error' => 'fallo Oracle al resolver el cliente autenticado: '
                                 . trim($e->getMessage())]);
    }
    if ($fila === false || trim((string) ($fila['ID_CLIENTE'] ?? '')) === '') {
        responder(403, ['error' => 'el usuario autenticado no tiene un id_cliente '
                                 . 'registrado (abra la app para registrar el dispositivo)']);
    }
    return trim((string) $fila['ID_CLIENTE']);
}

/* Fin del archivo config/http.php - MIDFF BIT-036 (15/10/2026) */

