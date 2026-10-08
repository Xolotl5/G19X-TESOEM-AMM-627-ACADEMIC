<?php
declare(strict_types=1);

/*
  --------------------------------------------------------------------------------
  -- MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
  -- Componente: mensajeria push Firebase Cloud Messaging - FCM (Sprint 3)
  -- Archivo: config/firebase.php
  --
  -- Requisitos: RF-09 (notificacion push solo cuando score >= 0.4), RNF-07
  --             (envio unico, sin cola de reintentos: alcance del MVP) y
  --             HU-MVP-05 (alerta visible en el dispositivo Android).
  --
  -- CONTENIDO
  --   1. Constantes de configuracion FCM (umbral, canal, OAuth2)
  --   2. midff_fcm_configurada()            -> credencial de servicio utilizable
  --   3. midff_fcm_dispositivos_activos()   -> tokens de DISPOSITIVO_MOVIL (Oracle)
  --   4. midff_fcm_desactivar_dispositivo() -> baja logica de tokens invalidos
  --   5. midff_fcm_payload_alerta()         -> payload HTTP v1 (message.*)
  --   6. midff_fcm_enviar()                 -> POST al endpoint v1 via cURL (OAuth2)
  --   7. midff_fcm_notificar_alerta()       -> orquestador best-effort (RF-09)
  --
  -- PROTOCOLO: HTTP v1 de FCM (OAuth2) con la cuenta de servicio del proyecto.
  --   Sustituye al protocolo legacy (Authorization: key=<server key>) porque
  --   Google descontinua las server keys legacy: los proyectos nuevos ya no las
  --   emiten (decision D-23 de BITACORA.md; migracion post-MVP completada).
  --   La credencial es un JSON de cuenta de servicio (type=service_account) que
  --   se firma como JWT RS256 y se canjea por un access_token OAuth2.
  --
  -- POLITICA DE FALLOS (RF-06): el envio del push es BEST-EFFORT. Ningun fallo
  --   de FCM altera la respuesta sincrona del contrato del Anexo A ni compensa
  --   la transaccion ya persistida en Oracle: todo fallo deja rastro en el log
  --   del servidor (error_log). Los tokens que FCM reporte como invalidos
  --   reciben baja logica en DISPOSITIVO_MOVIL (activo = 0, sin DELETE).
  --
  -- VARIABLES DE ENTORNO RECONOCIDAS (produccion; jamas versionar credenciales):
  --   MIDFF_FCM_CUENTA_SERVICIO  ruta del JSON de cuenta de servicio FCM
  --   MIDFF_FCM_PROYECTO         project_id de Firebase (si no, se lee del JSON)
  --------------------------------------------------------------------------------
*/

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';

/* -----------------------------------------------------------------------------
 * 1. CONSTANTES DE CONFIGURACION FCM (sobreescribibles por entorno)
 *    El protocolo HTTP v1 usa OAuth2: se lee la cuenta de servicio y se firma
 *    un JWT RS256 para canjearlo por un access_token (cacheado por peticion).
 * --------------------------------------------------------------------------
 */
define('MIDFF_FCM_TIMEOUT_S', 5);              // presupuesto corto: el envio viaja dentro de la peticion
define('MIDFF_FCM_UMBRAL_NOTIFICACION', 0.4);  // RF-09 / decision D-05: bandas MEDIA y ALTA
define('MIDFF_FCM_CANAL', 'midff_alertas');    // canal Android que crea NotificacionFcmService

// Alcance OAuth2 de mensajeria y URI de emision de tokens (fijos de Google).
define('MIDFF_FCM_SCOPE', 'https://www.googleapis.com/auth/firebase.messaging');
define('MIDFF_FCM_TOKEN_URI', 'https://oauth2.googleapis.com/token');

/* -----------------------------------------------------------------------------
 * 2. CUENTA DE SERVICIO Y CONFIGURACION DERIVADA
 *    midff_fcm_cuenta_servicio() resuelve la ruta del JSON de credenciales.
 *    midff_fcm_cargar_cuenta()  lo decodifica (cacheado en la peticion).
 *    midff_fcm_proyecto()       project_id del JSON (o variable de entorno).
 *    midff_fcm_endpoint()       URL v1 del proyecto.
 *    midff_fcm_access_token()   access_token OAuth2 (JWT RS256 + canje).
 * --------------------------------------------------------------------------
 */

/**
 * Cierto cuando hay una credencial de servicio FCM utilizable (JSON valido con
 * project_id) y la extension openssl esta disponible para firmar el JWT.
 */
function midff_fcm_configurada(): bool
{
    return extension_loaded('openssl')
        && midff_fcm_cargar_cuenta() !== null
        && midff_fcm_proyecto() !== '';
}

/**
 * Resuelve la ruta de la cuenta de servicio FCM. Prioridad:
 *   1) variable de entorno MIDFF_FCM_CUENTA_SERVICIO
 *   2) config/firebase.credentials.json
 *   3) cuenta de servicio de la raiz del repositorio
 */
function midff_fcm_cuenta_servicio(): string
{
    $explicita = getenv('MIDFF_FCM_CUENTA_SERVICIO');
    if ($explicita !== false && $explicita !== '') {
        return $explicita;
    }

    $candidatos = [
        __DIR__ . DIRECTORY_SEPARATOR . 'firebase.credentials.json',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'midff-57e6b-firebase-adminsdk-fbsvc-bf7960c9b6.json',
    ];
    foreach ($candidatos as $ruta) {
        if (is_file($ruta)) {
            return $ruta;
        }
    }
    return $candidatos[0];
}

/**
 * Carga (una sola vez por peticion) el JSON de la cuenta de servicio.
 *
 * @return array<string,mixed>|null la cuenta decodificada, o null si no es valida.
 */
function midff_fcm_cargar_cuenta(): ?array
{
    static $cuenta = null;
    static $cargada = false;
    if ($cargada) {
        return $cuenta;
    }
    $cargada = true;

    $ruta = midff_fcm_cuenta_servicio();
    if (!is_file($ruta) || !is_readable($ruta)) {
        error_log('[MIDFF-FCM] cuenta de servicio no encontrada o ilegible: ' . $ruta);
        return $cuenta;
    }
    $json = file_get_contents($ruta);
    if ($json === false) {
        error_log('[MIDFF-FCM] no se pudo leer la cuenta de servicio: ' . $ruta);
        return $cuenta;
    }
    $decodificado = json_decode($json, true);
    if (!is_array($decodificado)) {
        error_log('[MIDFF-FCM] JSON de cuenta de servicio invalido: ' . $ruta);
        return $cuenta;
    }
    $cuenta = $decodificado;
    return $cuenta;
}

/**
 * project_id de Firebase (variable de entorno MIDFF_FCM_PROYECTO o el JSON).
 */
function midff_fcm_proyecto(): string
{
    $explicito = getenv('MIDFF_FCM_PROYECTO');
    if ($explicito !== false && $explicito !== '') {
        return $explicito;
    }
    $cuenta = midff_fcm_cargar_cuenta();
    return (string) ($cuenta['project_id'] ?? '');
}

/**
 * Endpoint HTTP v1 de envio del proyecto.
 */
function midff_fcm_endpoint(): string
{
    return 'https://fcm.googleapis.com/v1/projects/' . midff_fcm_proyecto() . '/messages:send';
}

/**
 * Obtiene (y cachea) un access_token OAuth2 de la cuenta de servicio: firma un
 * JWT RS256 con la clave privada del JSON y lo canjea en token_uri.
 *
 * @return string access_token, o cadena vacia si no se pudo emitir.
 */
function midff_fcm_access_token(): string
{
    static $token = null;
    static $expira = 0;

    if ($token !== null && $expira > time() + 60) {
        return $token;
    }

    $cuenta = midff_fcm_cargar_cuenta();
    if ($cuenta === null) {
        return '';
    }

    $clavePrivada = (string) ($cuenta['private_key'] ?? '');
    $clienteEmail = (string) ($cuenta['client_email'] ?? '');
    if ($clavePrivada === '' || $clienteEmail === '') {
        error_log('[MIDFF-FCM] cuenta de servicio sin private_key/client_email');
        return '';
    }

    $ahora     = time();
    $base64url = static function (string $datos): string {
        return rtrim(strtr(base64_encode($datos), '+/', '-_'), '=');
    };

    $cabecera = $base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
    $claims   = $base64url(json_encode([
        'iss'   => $clienteEmail,
        'scope' => MIDFF_FCM_SCOPE,
        'aud'   => MIDFF_FCM_TOKEN_URI,
        'iat'   => $ahora,
        'exp'   => $ahora + 3600,
    ], JSON_UNESCAPED_SLASHES));
    $segmento = $cabecera . '.' . $claims;

    $firma = '';
    if (!openssl_sign($segmento, $firma, $clavePrivada, OPENSSL_ALGO_SHA256)) {
        error_log('[MIDFF-FCM] openssl_sign fallo al firmar el JWT');
        return '';
    }
    $jwt = $segmento . '.' . $base64url($firma);

    $canal = curl_init(MIDFF_FCM_TOKEN_URI);
    curl_setopt_array($canal, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => MIDFF_FCM_TIMEOUT_S,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
    ]);
    $crudo = curl_exec($canal);
    $err   = curl_error($canal);
    curl_close($canal);

    if ($crudo === false) {
        error_log('[MIDFF-FCM] fallo al solicitar access_token: ' . $err);
        return '';
    }

    $respuesta = json_decode((string) $crudo, true);
    if (!is_array($respuesta) || empty($respuesta['access_token'])) {
        error_log('[MIDFF-FCM] respuesta de token invalida: ' . substr(trim((string) $crudo), 0, 200));
        return '';
    }

    $token  = (string) $respuesta['access_token'];
    $expira = $ahora + (int) ($respuesta['expires_in'] ?? 3600);
    return $token;
}

/**
 * Muestra solo los ultimos 6 caracteres del token (los logs no filtran el FCM
 * token completo, que es una credencial del dispositivo).
 */
function midff_fcm_enmascarar_token(string $token): string
{
    return '...' . substr($token, -6);
}

/* -----------------------------------------------------------------------------
 * 3. DISPOSITIVOS REGISTRADOS (Oracle XE, tabla DISPOSITIVO_MOVIL del Sprint 3)
 *    Devuelve la lista de token_fcm con activo = 1. Con $idCliente no vacio
 *    (BIT-017) entrega SOLO los dispositivos de ese cliente; con '' mantiene
 *    el broadcast legado (modo oficial/demo). Ante cualquier fallo (tabla o
 *    columna inexistente, driver ausente, etc.) devuelve [] y deja rastro en
 *    el log: la notificacion se omite, pero la transaccion sigue su curso.
 * --------------------------------------------------------------------------
 */
function midff_fcm_dispositivos_activos(string $idCliente = ''): array
{
    $driver = midff_db_driver_disponible();
    if ($driver === '') {
        error_log('[MIDFF-FCM] sin driver Oracle (pdo_oci/oci8): no se consultan dispositivos');
        return [];
    }

    $filtrar = $idCliente !== '';
    $sql     = $filtrar
        ? 'SELECT token_fcm FROM DISPOSITIVO_MOVIL WHERE activo = 1 AND id_cliente = :cliente ORDER BY id_dispositivo'
        : 'SELECT token_fcm FROM DISPOSITIVO_MOVIL WHERE activo = 1 ORDER BY id_dispositivo';

    if ($driver === 'pdo_oci') {
        try {
            $cnx = midff_db_conectar();
            if ($filtrar) {
                $sent = $cnx->prepare($sql);
                $sent->execute([':cliente' => $idCliente]);
                return $sent->fetchAll(PDO::FETCH_COLUMN) ?: [];
            }
            return $cnx->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (PDOException $e) {
            $detalle = trim($e->getMessage());
            if (stripos($detalle, 'ORA-00904') !== false) {
                $detalle .= ' (falta la columna ID_CLIENTE: ejecute database/dispositivo_movil_id_cliente.sql)';
            }
            error_log('[MIDFF-FCM] error al consultar DISPOSITIVO_MOVIL: ' . $detalle);
            return [];
        }
    }

    // --------------------------- Ruta OCI8 (respaldo) ------------------------
    $cnx = oci_connect(MIDFF_DB_USUARIO, MIDFF_DB_CLAVE,
                       midff_db_cadena_conexion(), MIDFF_DB_CHARSET);
    if ($cnx === false) {
        error_log('[MIDFF-FCM] oci_connect fallo: no se consultan dispositivos');
        return [];
    }

    $tokens = [];
    $sent   = oci_parse($cnx, $sql);
    if ($sent !== false && (!$filtrar || oci_bind_by_name($sent, ':cliente', $idCliente))
            && oci_execute($sent)) {
        while (($fila = oci_fetch_array($sent, OCI_ASSOC + OCI_RETURN_NULLS)) !== false) {
            $tokens[] = (string) reset($fila);
        }
    } else {
        $e = oci_error($sent ?: $cnx);
        error_log('[MIDFF-FCM] error al consultar DISPOSITIVO_MOVIL: ' . trim($e['message'] ?? 'desconocido'));
    }
    if ($sent !== false) {
        oci_free_statement($sent);
    }
    oci_close($cnx);

    return $tokens;
}

/**
 * Baja logica (activo = 0) del dispositivo cuyo token FCM reporto invalido
 * (UNREGISTERED / registro invalido / remitente distinto). El MVP no borra
 * registros operativos: la baja queda marcada en fecha_actualizacion.
 */
function midff_fcm_desactivar_dispositivo(string $token): bool
{
    $driver = midff_db_driver_disponible();
    if ($driver === '') {
        return false;
    }

    if ($driver === 'pdo_oci') {
        try {
            $cnx  = midff_db_conectar();
            $sent = $cnx->prepare('UPDATE DISPOSITIVO_MOVIL'
                                . ' SET activo = 0, fecha_actualizacion = SYSTIMESTAMP'
                                . ' WHERE token_fcm = :token');
            $sent->execute([':token' => $token]);
            return $sent->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('[MIDFF-FCM] error al desactivar dispositivo: ' . trim($e->getMessage()));
            return false;
        }
    }

    // --------------------------- Ruta OCI8 (respaldo) ------------------------
    $cnx = oci_connect(MIDFF_DB_USUARIO, MIDFF_DB_CLAVE,
                       midff_db_cadena_conexion(), MIDFF_DB_CHARSET);
    if ($cnx === false) {
        return false;
    }
    $valor = $token;
    $sent  = oci_parse($cnx, 'UPDATE DISPOSITIVO_MOVIL'
                           . ' SET activo = 0, fecha_actualizacion = SYSTIMESTAMP'
                           . ' WHERE token_fcm = :token');
    $exito = false;
    if ($sent !== false) {
        oci_bind_by_name($sent, ':token', $valor, 255, SQLT_CHR);
        if (oci_execute($sent, OCI_NO_AUTO_COMMIT)) {
            $exito = oci_num_rows($sent) > 0;
            oci_commit($cnx);
        } else {
            oci_rollback($cnx);
        }
        oci_free_statement($sent);
    }
    oci_close($cnx);

    return $exito;
}

/* -----------------------------------------------------------------------------
 * 5. PAYLOAD DE LA ALERTA (HTTP v1: DATA-ONLY, decisión D-46)
 *    - Sin message.notification: con ese bloque, Android muestra la
 *      notificación él mismo cuando la app está en 2º plano y NO invoca
 *      NotificacionFcmService, por lo que la alerta nunca llegaba a la
 *      bandeja. Con data-only el servicio siempre corre y construye la
 *      notificación local (canal midff_alertas) que abre el detalle.
 *    - data SIEMPRE con cadenas (requisito de FCM) e incluye monto, score,
 *      clasificacion (RF-10) mas la trazabilidad a Oracle.
 * --------------------------------------------------------------------------
 */
function midff_fcm_payload_alerta(array $alerta): array
{
    $clasificacion = (string) ($alerta['clasificacion'] ?? 'MEDIO');
    $tipoAlerta    = (string) ($alerta['tipo_alerta']
                    ?? ($clasificacion === 'ALTO' ? 'FRAUDE' : 'PREVENTIVA'));   // D-05
    $titulo        = $tipoAlerta === 'FRAUDE'
        ? sprintf('MIDFF: alerta de FRAUDE (riesgo %s)', $clasificacion)
        : sprintf('MIDFF: alerta preventiva (riesgo %s)', $clasificacion);

    return [
        'message' => [
            'token'  => (string) ($alerta['token'] ?? ''),
            'android' => [
                'priority'     => 'high',
                'collapse_key' => 'midff_alerta',
            ],
            'data' => [
                'id_alerta'        => (string) ($alerta['id_alerta'] ?? ''),
                'id_transaccion'   => (string) ($alerta['id_transaccion'] ?? ''),
                'id_cliente'       => (string) ($alerta['id_cliente'] ?? ''),
                'monto'            => number_format((float) ($alerta['monto'] ?? 0), 2, '.', ''),
                'moneda'           => (string) ($alerta['moneda'] ?? 'MXN'),
                'score'            => number_format((float) ($alerta['score'] ?? 0), 4, '.', ''),
                'clasificacion'    => $clasificacion,
                'decision'         => (string) ($alerta['decision'] ?? ''),
                'tipo_alerta'      => $tipoAlerta,
                'fecha_evaluacion' => (string) ($alerta['fecha_evaluacion'] ?? ''),
                'origen'           => 'MIDFF-API',
            ],
        ],
    ];
}

/* -----------------------------------------------------------------------------
 * 6. ENVIO A UN TOKEN (cURL contra el endpoint HTTP v1 de FCM, con OAuth2)
 *
 * @return array{ok: bool, codigo: int, mensaje: string, desactivar: bool}
 * --------------------------------------------------------------------------
 */
function midff_fcm_enviar(string $token, array $payload): array
{
    if (!extension_loaded('curl')) {
        return ['ok' => false, 'codigo' => 0,
                'mensaje' => 'extension curl no disponible en PHP: no se puede enviar el push',
                'desactivar' => false];
    }

    $accessToken = midff_fcm_access_token();
    if ($accessToken === '') {
        return ['ok' => false, 'codigo' => 0,
                'mensaje' => 'sin access_token FCM (verifique la cuenta de servicio y openssl)',
                'desactivar' => false];
    }

    $cuerpo = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($cuerpo === false) {
        return ['ok' => false, 'codigo' => 0, 'mensaje' => 'payload no serializable', 'desactivar' => false];
    }

    $canal = curl_init(midff_fcm_endpoint());
    curl_setopt_array($canal, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => MIDFF_FCM_TIMEOUT_S,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_POSTFIELDS     => $cuerpo,
    ]);

    $crudo = curl_exec($canal);

    if ($crudo === false) {
        $mensaje = 'curl fallo: ' . curl_error($canal);
        curl_close($canal);
        return ['ok' => false, 'codigo' => 0, 'mensaje' => $mensaje, 'desactivar' => false];
    }

    $codigo = (int) curl_getinfo($canal, CURLINFO_RESPONSE_CODE);
    curl_close($canal);

    $respuesta = json_decode((string) $crudo, true);

    // Exito HTTP v1: 200 con el "name" del mensaje (projects/.../messages/...).
    if ($codigo === 200 && is_array($respuesta) && isset($respuesta['name'])) {
        return ['ok' => true, 'codigo' => $codigo,
                'mensaje' => 'entregado a FCM (' . (string) $respuesta['name'] . ')',
                'desactivar' => false];
    }

    // Error: extraer status/codigo de la estructura de error v1.
    $status     = (string) ($respuesta['error']['status'] ?? 'HTTP_' . $codigo);
    $mensajeErr = (string) ($respuesta['error']['message'] ?? trim((string) $crudo));
    $errorCode  = '';
    if (is_array($respuesta['error']['details'] ?? null) && isset($respuesta['error']['details'][0]['errorCode'])) {
        $errorCode = (string) $respuesta['error']['details'][0]['errorCode'];
    }

    // Equivalencias v1 de los codigos legacy que reciben baja logica:
    //   NotRegistered       -> UNREGISTERED / NOT_FOUND
    //   InvalidRegistration -> mensaje con "registration token"
    //   MismatchSenderId    -> SENDER_ID_MISMATCH
    $desactivar = in_array($errorCode, ['UNREGISTERED', 'SENDER_ID_MISMATCH'], true)
        || $status === 'NOT_FOUND'
        || stripos($mensajeErr, 'registration token') !== false;

    $detalle = trim(($errorCode !== '' ? $errorCode . ' ' : '') . substr($mensajeErr, 0, 200));

    return [
        'ok'         => false,
        'codigo'     => $codigo,
        'mensaje'    => $status . ': ' . $detalle,
        'desactivar' => $desactivar,
    ];
}

/* -----------------------------------------------------------------------------
 * 5b. PAYLOAD DE TRANSACCION SANA (score < 0.4, banda BAJO / APROBADA)
 *
 *    Mismo contrato HTTP v1 que la alerta, pero con titulo, prioridad y
 *    collapse_key propios: informa que la transaccion es SANA sin disparar la
 *    bandeja de alertas de la app (tipo_alerta = SANA, clasificacion = BAJO).
 * --------------------------------------------------------------------------
 */
function midff_fcm_payload_sana(array $datos): array
{
    return [
        'message' => [
            'token'  => (string) ($datos['token'] ?? ''),
            'android' => [
                'priority'     => 'normal',
                'collapse_key' => 'midff_sana',
            ],
            'data' => [
                'id_alerta'        => '',
                'id_transaccion'   => (string) ($datos['id_transaccion'] ?? ''),
                'id_cliente'       => (string) ($datos['id_cliente'] ?? ''),
                'monto'            => number_format((float) ($datos['monto'] ?? 0), 2, '.', ''),
                'moneda'           => (string) ($datos['moneda'] ?? 'MXN'),
                'score'            => number_format((float) ($datos['score'] ?? 0), 4, '.', ''),
                'clasificacion'    => 'BAJO',
                'decision'         => 'APROBADA',
                'tipo_alerta'      => 'SANA',
                'fecha_evaluacion' => (string) ($datos['fecha_evaluacion'] ?? ''),
                'origen'           => 'MIDFF-API',
            ],
        ],
    ];
}

/* -----------------------------------------------------------------------------
 * 7. ORQUESTADOR DEL ENVIO (RF-09 / HU-MVP-05)
 *
 *    midff_fcm_enviar_a_dispositivos() concentra la politica BEST-EFFORT:
 *    consulta los dispositivos activos de DISPOSITIVO_MOVIL (solo los del
 *    cliente indicado cuando $idCliente no es vacio, BIT-017), construye el
 *    payload de cada token con el constructor recibido y resume
 *    exitosos/fallidos. NUNCA lanza excepciones: cada fallo se registra con
 *    error_log y se resume en el arreglo de retorno.
 *
 * @return array{aplica: bool, configurada: bool, dispositivos: int,
 *               exitosos: int, fallidos: int, mensaje: string}
 * --------------------------------------------------------------------------
 */
function midff_fcm_enviar_a_dispositivos(callable $constructor, string $idCliente = ''): array
{
    if (!midff_fcm_configurada()) {
        return ['aplica' => true, 'configurada' => false, 'dispositivos' => 0,
                'exitosos' => 0, 'fallidos' => 0,
                'mensaje'  => 'FCM omitido: cuenta de servicio sin configurar '
                            . '(coloque config/firebase.credentials.json o defina MIDFF_FCM_CUENTA_SERVICIO)'];
    }

    $tokens = midff_fcm_dispositivos_activos($idCliente);
    if ($tokens === []) {
        return ['aplica' => true, 'configurada' => true, 'dispositivos' => 0,
                'exitosos' => 0, 'fallidos' => 0,
                'mensaje'  => $idCliente !== ''
                            ? 'FCM omitido: sin dispositivos activos para el cliente ' . $idCliente
                              . ' en DISPOSITIVO_MOVIL (registre la app con ese id_cliente)'
                            : 'FCM omitido: sin dispositivos activos en DISPOSITIVO_MOVIL'
                              . ' (ejecute database/dispositivo_movil.sql y registre la app Android)'];
    }

    $exitosos = 0;
    $fallidos = 0;

    foreach ($tokens as $token) {
        $token     = (string) $token;
        $resultado = midff_fcm_enviar($token, $constructor($token));

        if ($resultado['ok']) {
            $exitosos++;
            continue;
        }

        $fallidos++;
        error_log(sprintf('[MIDFF-FCM] fallo el envio al token %s (HTTP %d): %s',
                          midff_fcm_enmascarar_token($token),
                          $resultado['codigo'],
                          $resultado['mensaje']));

        if ($resultado['desactivar'] && midff_fcm_desactivar_dispositivo($token)) {
            error_log(sprintf('[MIDFF-FCM] token %s dado de baja en DISPOSITIVO_MOVIL (invalido segun FCM)',
                              midff_fcm_enmascarar_token($token)));
        }
    }

    return ['aplica' => true, 'configurada' => true, 'dispositivos' => count($tokens),
            'exitosos' => $exitosos, 'fallidos' => $fallidos,
            'mensaje'  => sprintf('push enviado a %d de %d dispositivo(s)', $exitosos, count($tokens))];
}

/**
 * Orquestador de la alerta (score >= 0.4): push preventivo o de fraude.
 * Entrega SOLO a los dispositivos registrados con el id_cliente de la
 * transaccion (BIT-017; sin id_cliente mantiene el broadcast legado).
 */
function midff_fcm_notificar_alerta(array $alerta): array
{
    $idCliente = trim((string) ($alerta['id_cliente'] ?? ''));
    return midff_fcm_enviar_a_dispositivos(static function (string $token) use ($alerta): array {
        $payload          = $alerta;
        $payload['token'] = $token;
        return midff_fcm_payload_alerta($payload);
    }, $idCliente);
}

/**
 * Orquestador de la transaccion sana (score < 0.4): push informativo.
 * Mismo filtro por cliente que la alerta (BIT-017).
 */
function midff_fcm_notificar_sana(array $datos): array
{
    $idCliente = trim((string) ($datos['id_cliente'] ?? ''));
    return midff_fcm_enviar_a_dispositivos(static function (string $token) use ($datos): array {
        $payload          = $datos;
        $payload['token'] = $token;
        return midff_fcm_payload_sana($payload);
    }, $idCliente);
}

/* Fin del archivo config/firebase.php - MIDFF Sprint 3 (24/09/2026) · HTTP v1 */
