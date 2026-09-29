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
  --   el score sea >= 0.4 (RF-09 / HU-MVP-05). El usuario + rol identifican
  --   al oficial de seguridad (mitigacion del riesgo R-05 del MVP).
  --
  -- Requisitos: RF-09 (notificacion push), RF-10 (app Android de alertas),
  --             RF-02 (validacion de entrada y API Key, misma que
  --             transacciones.php por decision D-25).
  --
  -- CONTRATO DE ENTRADA (JSON):
  --   Obligatorios : token_fcm (string 64-255, el que entrega Firebase)
  --                  usuario   (string <= 60, oficial de seguridad)
  --   Opcionales   : rol     (OFICIAL | SUPERVISOR | ADMIN; default OFICIAL)
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
 * @return array{ok: bool, mensaje: string, id: int|null, accion: string}
 * -------------------------------------------------------------------------- */
function midff_registrar_dispositivo(string $usuario, string $rol, string $token, bool $activo): array
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

            $buscar = $cnx->prepare('SELECT id_dispositivo FROM DISPOSITIVO_MOVIL WHERE token_fcm = :token');
            $buscar->execute([':token' => $token]);
            $id = $buscar->fetchColumn();
            $buscar = null;

            if ($id !== false) {
                $sent = $cnx->prepare('UPDATE DISPOSITIVO_MOVIL'
                                    . ' SET usuario = :usuario, rol = :rol, activo = :activo,'
                                    . ' fecha_actualizacion = SYSTIMESTAMP'
                                    . ' WHERE token_fcm = :token');
                $sent->execute([':usuario' => $usuario, ':rol' => $rol,
                                ':activo' => $activo ? '1' : '0', ':token' => $token]);
                $cnx->commit();
                return ['ok' => true, 'mensaje' => 'dispositivo actualizado',
                        'id' => (int) $id, 'accion' => 'UPDATE'];
            }

            // INSERT + relectura del id (PDO_OCI no soporta RETURNING INTO).
            $sent = $cnx->prepare('INSERT INTO DISPOSITIVO_MOVIL (usuario, rol, token_fcm, activo)'
                                . ' VALUES (:usuario, :rol, :token, :activo)');
            $sent->execute([':usuario' => $usuario, ':rol' => $rol,
                            ':activo' => $activo ? '1' : '0', ':token' => $token]);
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
 * 1. METODO: solo POST (405 + encabezado Allow, RFC 7231)
 * -------------------------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responder(405, ['error' => 'metodo no permitido: use POST /api/v1/dispositivos']);
}

/* -----------------------------------------------------------------------------
 * 2. API KEY (RF-02 / RNF-03): la misma de POST /api/v1/transacciones (D-25)
 * -------------------------------------------------------------------------- */
$claveRecibida = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($claveRecibida === '' || !hash_equals(MIDFF_API_KEY, (string) $claveRecibida)) {
    responder(401, ['error' => 'encabezado X-API-Key ausente o invalido']);
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

// rol: OFICIAL | SUPERVISOR | ADMIN (CHECK en DISPOSITIVO_MOVIL.rol); default OFICIAL.
$rol = strtoupper((string) ($cuerpo['rol'] ?? 'OFICIAL'));
if (!in_array($rol, ['OFICIAL', 'SUPERVISOR', 'ADMIN'], true)) {
    rechazar('rol invalido: se espera OFICIAL, SUPERVISOR o ADMIN');
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
$resultado = midff_registrar_dispositivo($usuario, $rol, $token, $activo);

if (!$resultado['ok']) {
    fallaInterna('fallo el registro del dispositivo en Oracle: ' . $resultado['mensaje']);
}

error_log(sprintf(
    '[MIDFF-FCM] registro de dispositivo accion=%s id=%d usuario=%s rol=%s token=...%s',
    $resultado['accion'],
    (int) $resultado['id'],
    $usuario,
    $rol,
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
