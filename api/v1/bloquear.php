<?php
declare(strict_types=1);

/*
  --------------------------------------------------------------------------------
  -- MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
  -- Componente: API REST (Sprint 4)
  -- Endpoint : POST /api/v1/bloquear      (header obligatorio: X-API-Key)
  -- Archivo  : api/v1/bloquear.php
  --
  -- Proposito: ejecutar la accion "Bloquear" de una alerta desde la app
  --   Android (HU-MVP-06): pasa ALERTA.estado a BLOQUEADA con el usuario del
  --   oficial y su timestamp, e inserta el registro BLOQUEO en AUDITORIA
  --   (RF-08) dentro de la misma unidad atomica (RNF-05). El procedimiento
  --   SP_BLOQUEAR_ALERTA (database/sp_bloquear_alerta.sql, decision D-35)
  --   hace el trabajo en Oracle; este endpoint valida y traduce a HTTP.
  --
  -- Requisitos: HU-MVP-06 (bloqueo preventivo), RF-02 (validacion de entrada
  --             y API Key, misma que transacciones.php por D-25) y RF-08.
  --
  -- CONTRATO DE ENTRADA (JSON):
  --   Obligatorios : alerta_id (string ALR-<anio>-<numero>, el mismo formato
  --                              que devuelve POST /api/v1/transacciones)
  --                  usuario   (string <= 60, oficial de DISPOSITIVO_MOVIL)
  --   Opcionales   : respuesta (string <= 200, comentario del oficial)
  --
  -- CONTRATO DE SALIDA (200):
  --   { "ok": true, "alerta_id": "ALR-...", "estado": "BLOQUEADA",
  --     "atendida_por": "...", "fecha_atencion": "2026-09-28T...",
  --     "id_auditoria": n, "mensaje": "..." }
  --
  -- ERRORES: 405 metodo distinto de POST · 401 API Key invalida ·
  --          400 cuerpo/campos invalidos · 404 alerta inexistente (ORA-20003) ·
  --          409 alerta ya bloqueada (ORA-20004) · 500 fallo de Oracle.
  --------------------------------------------------------------------------------
*/

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'firebase.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'http.php';

date_default_timezone_set('America/Mexico_City');

/* Misma clave del endpoint de transacciones (D-25): una sola API Key para el MVP. */

/* -----------------------------------------------------------------------------
 * Utilidades de respuesta HTTP (siempre JSON)
 * -------------------------------------------------------------------------- */
function noEncontrado(string $mensaje): void
{
    responder(404, ['error' => $mensaje]);
}

function conflicto(string $mensaje): void
{
    responder(409, ['error' => $mensaje]);
}

/* -----------------------------------------------------------------------------
 * 1. METODO: solo POST (405 + encabezado Allow, RFC 7231)
 * -------------------------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responder(405, ['error' => 'metodo no permitido: use POST /api/v1/bloquear']);
}

/* -----------------------------------------------------------------------------
 * 2. API KEY (RF-02 / RNF-03): la misma de POST /api/v1/transacciones (D-25)
 * -------------------------------------------------------------------------- */
midff_exigir_api_key();



/* -----------------------------------------------------------------------------
 * 3. CUERPO JSON Y VALIDACION DE CAMPOS (RF-02)
 * -------------------------------------------------------------------------- */
$crudo = file_get_contents('php://input');
if ($crudo === false || trim($crudo) === '') {
    rechazar('cuerpo de la peticion vacio: se espera un objeto JSON de bloqueo');
}

$cuerpo = json_decode($crudo, true);
if (!is_array($cuerpo)) {
    rechazar('cuerpo JSON invalido o mal formado');
}

// alerta_id: obligatorio, formato ALR-<anio>-<numero> (el mismo que emite la API
// de transacciones: sprintf('ALR-%s-%06d', anio, id)). El numero viaja al SP.
$alertaId = $cuerpo['alerta_id'] ?? null;
if (!is_string($alertaId) || !preg_match('/^ALR-(\d{4})-(\d{1,18})$/', trim($alertaId), $partes)) {
    rechazar('campo obligatorio ausente o invalido: alerta_id (formato ALR-<anio>-<numero>)');
}
$alertaId = trim($alertaId);
$idAlerta = (int) $partes[2];

// usuario: obligatorio, string no vacio, <= 60 (VARCHAR2(60) de ALERTA.atendida_por).
$usuario = $cuerpo['usuario'] ?? null;
if (!is_string($usuario) || trim($usuario) === '') {
    rechazar('campo obligatorio ausente o invalido: usuario (cadena no vacia)');
}
$usuario = trim($usuario);
if (mb_strlen($usuario) > 60) {
    rechazar('usuario excede 60 caracteres');
}

// respuesta: opcional, <= 200 (VARCHAR2(200) de ALERTA.respuesta).
$respuesta = array_key_exists('respuesta', $cuerpo) ? (string) $cuerpo['respuesta'] : '';
$respuesta = trim($respuesta);
if (mb_strlen($respuesta) > 200) {
    rechazar('respuesta excede 200 caracteres');
}

/* -----------------------------------------------------------------------------
 * 4. BLOQUEO ATOMICO EN ORACLE XE (SP_BLOQUEAR_ALERTA, Sprint 4)
 * -------------------------------------------------------------------------- */
$resultado = midff_db_bloquear_alerta($idAlerta, $usuario, $respuesta);

if (!$resultado['ok']) {
    $msj = $resultado['mensaje'];
    if (strpos($msj, 'ORA-20003') !== false) {
        noEncontrado('alerta inexistente: ' . $alertaId);
    }
    if (strpos($msj, 'ORA-20004') !== false) {
        conflicto('la alerta ' . $alertaId . ' ya esta BLOQUEADA');
    }
    fallaInterna('fallo el bloqueo de la alerta en Oracle: ' . $msj);
}

error_log(sprintf(
    '[MIDFF-BLOQUEO] alerta=%s usuario=%s auditoria=%d fecha=%s',
    $alertaId,
    $usuario,
    (int) $resultado['id_auditoria'],
    (string) $resultado['fecha_atencion']
));

/* -----------------------------------------------------------------------------
 * 5. AVISO PUSH AL CLIENTE (BIT-027): la decision del banco (bloqueo) se
 *    refleja en la app en tiempo real. Best-effort como RF-09: ningun fallo
 *    de FCM altera la respuesta del bloqueo ya persistido en Oracle.
 * -------------------------------------------------------------------------- */
$instanteFcm = hrtime(true);
try {
    $cnxDueno = midff_db_conectar();
    $selDueno = $cnxDueno->prepare('SELECT t.id_cliente, t.uid_transaccion'
                                  . ' FROM ALERTA a'
                                  . ' JOIN EVALUACION e ON e.id_evaluacion = a.id_evaluacion'
                                  . ' JOIN TRANSACCION t ON t.id_transaccion = e.id_transaccion'
                                  . ' WHERE a.id_alerta = :id');
    $selDueno->execute([':id' => $idAlerta]);
    $dueno = $selDueno->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $dueno = false;
    error_log('[MIDFF-FCM-ESTADO] no se resolvio el dueno de ' . $alertaId . ': ' . trim($e->getMessage()));
}
if (is_array($dueno) && (string) $dueno['ID_CLIENTE'] !== '') {
    $resumenFcm = midff_fcm_notificar_estado([
        'evento'         => 'BLOQUEO',
        'id_alerta'      => $alertaId,
        'id_transaccion' => (string) $dueno['UID_TRANSACCION'],
        'id_cliente'     => (string) $dueno['ID_CLIENTE'],
        'estado'         => 'BLOQUEADA',
        'decision'       => 'BLOQUEADA',
        'atendida_por'   => $usuario,
        'detalle'        => 'El banco bloqueo preventivamente la transaccion '
                           . (string) $dueno['UID_TRANSACCION'],
    ]);
    error_log(sprintf(
        '[MIDFF-FCM-ESTADO] bloqueo %s cliente=%s dispositivos=%d exitosos=%d fallidos=%d envio_ms=%.1f :: %s',
        $alertaId,
        (string) $dueno['ID_CLIENTE'],
        (int) $resumenFcm['dispositivos'],
        (int) $resumenFcm['exitosos'],
        (int) $resumenFcm['fallidos'],
        (hrtime(true) - $instanteFcm) / 1e6,
        $resumenFcm['mensaje']
    ));
}

responder(200, [
    'ok'             => true,
    'alerta_id'      => $alertaId,
    'estado'         => $resultado['estado'],
    'atendida_por'   => $usuario,
    'fecha_atencion' => $resultado['fecha_atencion'],
    'id_auditoria'   => (int) $resultado['id_auditoria'],
    'mensaje'        => 'alerta bloqueada y registrada en AUDITORIA (HU-MVP-06)',
]);

/* Fin del archivo api/v1/bloquear.php - MIDFF Sprint 4 (28/09/2026) */
