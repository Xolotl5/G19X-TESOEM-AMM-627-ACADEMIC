<?php
declare(strict_types=1);

/*
  --------------------------------------------------------------------------------
  -- MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
  -- Componente: API REST (Sprint 3)
  -- Endpoint : POST /api/v1/dispositivos   (header obligatorio: X-API-Key)
  -- Archivo  : api/v1/dispositivos.php
  --
  -- Proposito: registrar (UPSERT) el token FCM que entrega la app Android
  --   (NotificacionFcmService.onNewToken / boton "Registrar token") en la
  --   tabla DISPOSITIVO_MOVIL, para que la API pueda enviar el push cuando
  --   el score sea >= 0.4 (RF-09 / HU-MVP-05). Con el pivote a CLIENTE
  --   (BIT-017) el cuerpo incluye id_cliente: el push de una transaccion
  --   se entrega SOLO a los dispositivos de su dueno.
  --
  -- Requisitos: RF-09 (notificacion push), RF-10 (app Android de alertas),
  --             RF-02 (validacion de entrada y API Key, misma que
  --             transacciones.php por decision D-25).
  --
  -- CONTRATO DE ENTRADA (JSON):
  --   Obligatorios : token_fcm (string 64-255, el que entrega Firebase)
  --                  usuario   (string <= 60, correo del cliente o usuario)
  --   Opcionales   : id_cliente (string <= 40, cliente dueno del dispositivo;
  --                              vacio = broadcast legado, modo oficial/demo)
  --                  rol     (CLIENTE | OFICIAL | SUPERVISOR | ADMIN;
  --                           default OFICIAL)
  --                  activo  (boolean; default true)
  --
  -- CONTRATO DE SALIDA (200):
  --   { "ok": true, "accion": "INSERT"|"UPDATE", "id_dispositivo": n,
  --     "token": "...<ultimos 6>", "mensaje": "..." }
  --
  -- ERRORES: 405 metodo distinto de POST · 401 API Key invalida ·
  --          400 cuerpo/campos invalidos · 500 fallo de Oracle.
  --------------------------------------------------------------------------------
*/

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';

date_default_timezone_set('America/Mexico_City');

/* Misma clave del endpoint de transacciones (D-25): una sola API Key para el MVP. */
define('MIDFF_API_KEY', getenv('MIDFF_API_KEY') ?: 'MIDFF-API-KEY-2026-SPRINT2');

/* -----------------------------------------------------------------------------
 * Utilidades de respuesta HTTP (siempre JSON)
 * -------------------------------------------------------------------------- */
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

/* -----------------------------------------------------------------------------
 * UPSERT del dispositivo en DISPOSITIVO_MOVIL (database/dispositivo_movil.sql)
 *
 * @param string $idCliente cliente dueno del dispositivo ('' = broadcast legado)
 *
 * @return array{ok: bool, mensaje: string, id: int|null, accion: string}
 * -------------------------------------------------------------------------- */
function midff_registrar_dispositivo(string $usuario, string $rol, string $token, bool $activo, string $idCliente = '', string $uidFirebase = ''): array
{
    $driver = midff_db_driver_disponible();
    if ($driver === '') {
        return ['ok' => false, 'mensaje' => 'sin driver Oracle (pdo_oci/oci8): revise php.ini',
                'id' => null, 'accion' => 'NINGUNA'];
    }

    if ($driver === 'pdo_oci') {
        try {
            $cnx = midff_db_conectar();
            $cnx->beginTransaction();
            $cliente = $idCliente === '' ? null : $idCliente;
            // BIT-031: uid de Firebase (nullable; en UPDATE, NVL conserva el
            // existente cuando la llamada no lo trae - app con version vieja).
            $uid = $uidFirebase === '' ? null : $uidFirebase;

            $buscar = $cnx->prepare('SELECT id_dispositivo FROM DISPOSITIVO_MOVIL WHERE token_fcm = :token');
            $buscar->execute([':token' => $token]);
            $id = $buscar->fetchColumn();
            $buscar = null;

            if ($id !== false) {
                // (el bind se llama uidfb: ":uid" es palabra reservada de Oracle, ORA-01745)
                $sent = $cnx->prepare('UPDATE DISPOSITIVO_MOVIL'
                                    . ' SET usuario = :usuario, rol = :rol, id_cliente = :cliente, activo = :activo,'
                                    . ' uid_firebase = NVL(:uidfb, uid_firebase),'
                                    . ' fecha_actualizacion = SYSTIMESTAMP'
                                    . ' WHERE token_fcm = :token');
                $sent->execute([':usuario' => $usuario, ':rol' => $rol, ':cliente' => $cliente,
                                ':activo' => $activo ? '1' : '0', ':uidfb' => $uid, ':token' => $token]);
                $cnx->commit();
                return ['ok' => true, 'mensaje' => 'dispositivo actualizado',
                        'id' => (int) $id, 'accion' => 'UPDATE'];
            }

            // INSERT + relectura del id (PDO_OCI no soporta RETURNING INTO).
            $sent = $cnx->prepare('INSERT INTO DISPOSITIVO_MOVIL (usuario, rol, id_cliente, token_fcm, activo, uid_firebase)'
                                . ' VALUES (:usuario, :rol, :cliente, :token, :activo, :uidfb)');
            $sent->execute([':usuario' => $usuario, ':rol' => $rol, ':cliente' => $cliente,
                            ':activo' => $activo ? '1' : '0', ':token' => $token, ':uidfb' => $uid]);
            $sent = null;

            $buscar = $cnx->prepare('SELECT id_dispositivo FROM DISPOSITIVO_MOVIL WHERE token_fcm = :token');
            $buscar->execute([':token' => $token]);
            $idNuevo = (int) $buscar->fetchColumn();
            $buscar = null;

            $cnx->commit();

            return ['ok' => true, 'mensaje' => 'dispositivo registrado',
                    'id' => $idNuevo, 'accion' => 'INSERT'];
        } catch (PDOException $e) {
            return ['ok' => false, 'mensaje' => trim($e->getMessage()),
                    'id' => null, 'accion' => 'NINGUNA'];
        }
    }

    return ['ok' => false, 'mensaje' => 'ruta OCI8 no implementada para este endpoint: habilite pdo_oci',
            'id' => null, 'accion' => 'NINGUNA'];
}

/* -----------------------------------------------------------------------------
 * LISTA de dispositivos registrados (BIT-018: el banco/administrador ve los
 * ID de cliente dados de alta en la app para pedirlos al usuario).
 *
 * @return array{ok: bool, mensaje: string, dispositivos: array<int, array>}
 * -------------------------------------------------------------------------- */
function midff_listar_dispositivos(): array
{
    if (midff_db_driver_disponible() !== 'pdo_oci') {
        return ['ok' => false, 'mensaje' => 'sin driver pdo_oci: revise php.ini', 'dispositivos' => []];
    }

    try {
        $cnx  = midff_db_conectar();
        $sent = $cnx->query('SELECT id_dispositivo, usuario, rol, id_cliente, activo,'
                          . " TO_CHAR(fecha_registro, 'YYYY-MM-DD HH24:MI') AS registrado,"
                          . ' SUBSTR(token_fcm, -6) AS token_final'
                          . ' FROM DISPOSITIVO_MOVIL ORDER BY id_dispositivo');
        return ['ok' => true, 'mensaje' => '', 'dispositivos' => $sent->fetchAll(PDO::FETCH_ASSOC) ?: []];
    } catch (PDOException $e) {
        return ['ok' => false, 'mensaje' => trim($e->getMessage()), 'dispositivos' => []];
    }
}

/* -----------------------------------------------------------------------------
 * 1. METODO: POST registra el token; GET lista los dispositivos (BIT-018,
 *    vista del banco/administrador). Otros metodos: 405 + Allow (RFC 7231).
 * -------------------------------------------------------------------------- */
$metodo = $_SERVER['REQUEST_METHOD'] ?? '';
if ($metodo !== 'POST' && $metodo !== 'GET') {
    header('Allow: GET, POST');
    responder(405, ['error' => 'metodo no permitido: use POST (registrar) o GET (listar) /api/v1/dispositivos']);
}

/* -----------------------------------------------------------------------------
 * 2. API KEY (RF-02 / RNF-03): la misma de POST /api/v1/transacciones (D-25)
 * -------------------------------------------------------------------------- */
$claveRecibida = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($claveRecibida === '' || !hash_equals(MIDFF_API_KEY, (string) $claveRecibida)) {
    responder(401, ['error' => 'encabezado X-API-Key ausente o invalido']);
}

/* -----------------------------------------------------------------------------
 * 2b. GET: lista de dispositivos con su id_cliente (BIT-018, banco/admin).
 *      Salida: { ok, total, dispositivos: [{ id_dispositivo, usuario, rol,
 *               id_cliente, activo, registrado, token_final }] }
 * -------------------------------------------------------------------------- */
if ($metodo === 'GET') {
    $listado = midff_listar_dispositivos();
    if (!$listado['ok']) {
        fallaInterna('fallo el listado de dispositivos en Oracle: ' . $listado['mensaje']);
    }
    error_log(sprintf('[MIDFF-FCM] listado de dispositivos (GET): %d registro(s)', count($listado['dispositivos'])));
    responder(200, [
        'ok'           => true,
        'total'        => count($listado['dispositivos']),
        'dispositivos' => $listado['dispositivos'],
    ]);
}

/* -----------------------------------------------------------------------------
 * 3. CUERPO JSON Y VALIDACION DE CAMPOS (RF-02)
 * -------------------------------------------------------------------------- */
$crudo = file_get_contents('php://input');
if ($crudo === false || trim($crudo) === '') {
    rechazar('cuerpo de la peticion vacio: se espera un objeto JSON de dispositivo');
}

$cuerpo = json_decode($crudo, true);
if (!is_array($cuerpo)) {
    rechazar('cuerpo JSON invalido o mal formado');
}

// usuario: obligatorio, string no vacio, <= 60 (VARCHAR2(60)).
$usuario = $cuerpo['usuario'] ?? null;
if (!is_string($usuario) || trim($usuario) === '') {
    rechazar('campo obligatorio ausente o invalido: usuario (cadena no vacia)');
}
if (mb_strlen($usuario) > 60) {
    rechazar('usuario excede 60 caracteres');
}

// rol: CLIENTE | OFICIAL | SUPERVISOR | ADMIN (CHECK en DISPOSITIVO_MOVIL.rol);
// default OFICIAL para no romper llamadas existentes (banco.html/curl).
$rol = strtoupper((string) ($cuerpo['rol'] ?? 'OFICIAL'));
if (!in_array($rol, ['CLIENTE', 'OFICIAL', 'SUPERVISOR', 'ADMIN'], true)) {
    rechazar('rol invalido: se espera CLIENTE, OFICIAL, SUPERVISOR o ADMIN');
}

// id_cliente (BIT-017): opcional, string <= 40 (mismo tamanio que en
// transacciones.php); vincula el dispositivo al dueno de las transacciones.
// Vacio = broadcast legado (modo oficial/demo).
$idClienteCrudo = $cuerpo['id_cliente'] ?? null;
if ($idClienteCrudo !== null && !is_string($idClienteCrudo)) {
    rechazar('id_cliente invalido: se espera una cadena de hasta 40 caracteres');
}
$idCliente = $idClienteCrudo === null ? '' : trim($idClienteCrudo);
if (mb_strlen($idCliente) > 40) {
    rechazar('id_cliente excede 40 caracteres');
}

// uid_firebase (BIT-031): opcional, string <= 128; permite al banco verificar
// contra Firebase si la cuenta sigue viva y purgar las borradas sin la app.
$uidCrudo = $cuerpo['uid_firebase'] ?? null;
if ($uidCrudo !== null && !is_string($uidCrudo)) {
    rechazar('uid_firebase invalido: se espera una cadena de hasta 128 caracteres');
}
$uidFirebase = $uidCrudo === null ? '' : trim($uidCrudo);
if (mb_strlen($uidFirebase) > 128) {
    rechazar('uid_firebase excede 128 caracteres');
}

// token_fcm: obligatorio, 64-255 caracteres (CHECK + VARCHAR2(255) en Oracle).
$token = $cuerpo['token_fcm'] ?? null;
if (!is_string($token) || trim($token) === '') {
    rechazar('campo obligatorio ausente o invalido: token_fcm (token entregado por Firebase)');
}
if (strlen($token) < 64 || strlen($token) > 255) {
    rechazar('token_fcm invalido: se espera una cadena de 64 a 255 caracteres');
}

// activo: booleano opcional; default true.
$activo = array_key_exists('activo', $cuerpo) ? (bool) $cuerpo['activo'] : true;

/* -----------------------------------------------------------------------------
 * 4. UPSERT EN ORACLE XE (DISPOSITIVO_MOVIL, Sprint 3)
 * -------------------------------------------------------------------------- */
$resultado = midff_registrar_dispositivo($usuario, $rol, $token, $activo, $idCliente, $uidFirebase);

if (!$resultado['ok']) {
    fallaInterna('fallo el registro del dispositivo en Oracle: ' . $resultado['mensaje']);
}

error_log(sprintf(
    '[MIDFF-FCM] registro de dispositivo accion=%s id=%d usuario=%s rol=%s cliente=%s token=...%s',
    $resultado['accion'],
    (int) $resultado['id'],
    $usuario,
    $rol,
    $idCliente === '' ? '(broadcast)' : $idCliente,
    substr($token, -6)
));

responder(200, [
    'ok'             => true,
    'accion'         => $resultado['accion'],
    'id_dispositivo' => (int) $resultado['id'],
    'token'          => '...' . substr($token, -6),   // enmascarado: credencial del dispositivo
    'mensaje'        => $resultado['mensaje'],
]);

/* Fin del archivo api/v1/dispositivos.php - MIDFF Sprint 3 (24/09/2026) */
