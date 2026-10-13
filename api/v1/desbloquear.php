<?php
declare(strict_types=1);

/*
  --------------------------------------------------------------------------------
  -- MIDFF - Desbloqueo de alerta tras confirmacion del cliente (BIT-024)
  -- Endpoint : POST /api/v1/desbloquear   (header obligatorio: X-API-Key)
  -- Archivo  : api/v1/desbloquear.php
  --
  -- Flujo ALTO (prompt.txt): la transaccion llega BLOQUEADA; si el cliente
  --   responde "SI FUI YO" en la app, la consola MIDFF.html habilita este
  --   DESBLOQUEO para el administrador: EVALUACION.decision pasa a APROBADA,
  --   ALERTA queda ATENDIDA por el admin y todo queda en AUDITORIA.
  --
  -- CONTRATO DE ENTRADA (JSON):
  --   Obligatorios : alerta_id (string ALR-<anio>-<numero>), usuario (admin)
  --   Opcionales   : respuesta (string <= 180, comentario del admin)
  --
  -- CONTRATO DE SALIDA (200): { ok, mensaje, id_auditoria }
  -- ERRORES: 405 · 401 · 400 · 404 alerta inexistente · 409 sin confirmacion
  --          del cliente (SI_FUI_YO) o ya desbloqueada · 500 Oracle.
  --------------------------------------------------------------------------------
 */

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'firebase.php';

date_default_timezone_set('America/Mexico_City');

define('MIDFF_API_KEY', getenv('MIDFF_API_KEY') ?: 'MIDFF-API-KEY-2026-SPRINT2');

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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responder(405, ['error' => 'metodo no permitido: use POST /api/v1/desbloquear']);
}

$claveRecibida = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($claveRecibida === '' || !hash_equals(MIDFF_API_KEY, (string) $claveRecibida)) {
    responder(401, ['error' => 'encabezado X-API-Key ausente o invalido']);
}

$cuerpo    = json_decode((string) file_get_contents('php://input'), true);
$alertaId  = trim((string) (is_array($cuerpo) ? ($cuerpo['alerta_id'] ?? '') : ''));
$usuario   = trim((string) (is_array($cuerpo) ? ($cuerpo['usuario'] ?? '') : ''));
$respuesta = trim((string) (is_array($cuerpo) ? ($cuerpo['respuesta'] ?? '') : ''));

if ($alertaId === '' || !preg_match('/^ALR-\d{4}-\d{1,18}$/', $alertaId)) {
    rechazar('alerta_id invalido: se espera el formato ALR-<anio>-<numero>');
}
if ($usuario === '' || mb_strlen($usuario) > 60) {
    rechazar('usuario obligatorio (administrador que desbloquea, hasta 60 caracteres)');
}
if (mb_strlen($respuesta) > 180) {
    rechazar('respuesta excede 180 caracteres');
}

$idNumerico = (int) substr($alertaId, strrpos($alertaId, '-') + 1);

if (midff_db_driver_disponible() !== 'pdo_oci') {
    responder(500, ['error' => 'sin driver pdo_oci: revise php.ini']);
}

// Dueno de la alerta para el aviso push de BIT-027 (se captura en la misma
// consulta bloqueante para no abrir otra ventana de consistencia).
$clienteDueno = null;
$trxUid       = '';

try {
    $cnx = midff_db_conectar();
    $cnx->beginTransaction();

    $ver = $cnx->prepare('SELECT e.decision, a.respuesta, a.estado, t.id_cliente, t.uid_transaccion'
                       . ' FROM ALERTA a'
                       . ' JOIN EVALUACION e ON e.id_evaluacion = a.id_evaluacion'
                       . ' JOIN TRANSACCION t ON t.id_transaccion = e.id_transaccion'
                       . ' WHERE a.id_alerta = :id'
                       . ' FOR UPDATE');
    $ver->execute([':id' => $idNumerico]);
    $fila = $ver->fetch(PDO::FETCH_ASSOC);
    $clienteDueno = $fila === false ? null : (string) $fila['ID_CLIENTE'];
    $trxUid       = $fila === false ? ''   : (string) $fila['UID_TRANSACCION'];
    if ($fila === false) {
        $cnx->rollBack();
        responder(404, ['error' => 'alerta inexistente: ' . $alertaId]);
    }
    if (strtoupper((string) $fila['DECISION']) !== 'BLOQUEADA') {
        $cnx->rollBack();
        responder(409, ['error' => 'la alerta no corresponde a riesgo ALTO (decision actual: ' . $fila['DECISION'] . ')']);
    }
    if (stripos((string) $fila['RESPUESTA'], 'SI_FUI_YO') !== 0) {
        $cnx->rollBack();
        responder(409, ['error' => 'el cliente no ha confirmado la transaccion (falta SI_FUI_YO en la app)']);
    }
    if (stripos((string) $fila['RESPUESTA'], 'DESBLOQUEADA') !== false) {
        $cnx->rollBack();
        responder(409, ['error' => 'la alerta ya fue desbloqueada por el banco']);
    }

    // NOTA (BIT-024): EVALUACION.decision NO se toca: el CHECK del schema
    // congelado (CK_EVALUACION_UMBRAL) exige coherencia score<->banda<->decision
    // y la evaluacion historica del motor es inmutable. El desbloqueo queda
    // en el ciclo de vida de la ALERTA y en AUDITORIA.
    $respuestaFinal = ((string) $fila['RESPUESTA']) . ' :: DESBLOQUEADA';

    $cnx->prepare('UPDATE ALERTA SET estado = \'ATENDIDA\', atendida_por = :usuario,'
                . ' fecha_atencion = SYSTIMESTAMP, respuesta = :respuesta'
                . ' WHERE id_alerta = :id')
        ->execute([':usuario' => $usuario, ':id' => $idNumerico, ':respuesta' => $respuestaFinal]);

    $detalle = 'Desbloqueo de ' . $alertaId . ' tras confirmacion SI_FUI_YO del cliente'
             . ($respuesta !== '' ? ' :: ' . $respuesta : '');
    $cnx->prepare('INSERT INTO AUDITORIA (id_auditoria, entidad, accion, usuario_o_sistema, detalle)'
                . ' VALUES (SEQ_AUDITORIA.NEXTVAL, \'ALERTA\', \'ATENCION\', :usuario, :detalle)')
        ->execute([':usuario' => $usuario, ':detalle' => $detalle]);

    $cnx->commit();
    $idAud = (int) $cnx->query('SELECT SEQ_AUDITORIA.CURRVAL FROM DUAL')->fetchColumn();
} catch (PDOException $e) {
    responder(500, ['error' => 'fallo Oracle al desbloquear: ' . trim($e->getMessage())]);
}

error_log(sprintf('[MIDFF-DESBLOQUEO] %s desbloqueo %s (auditoria %d)', $usuario, $alertaId, $idAud));

/* BIT-027: aviso push al cliente — su confirmacion SI_FUI_YO habilito el
   desbloqueo y el banco lo ejecuto; la app actualiza la bandeja en tiempo real. */
if ($clienteDueno !== null && $clienteDueno !== '') {
    $instanteFcm = hrtime(true);
    $resumenFcm  = midff_fcm_notificar_estado([
        'evento'         => 'DESBLOQUEO',
        'id_alerta'      => $alertaId,
        'id_transaccion' => $trxUid,
        'id_cliente'     => $clienteDueno,
        'estado'         => 'ATENDIDA',
        'decision'       => 'APROBADA',
        'atendida_por'   => $usuario,
        'detalle'        => 'El banco desbloqueo la transaccion ' . $trxUid
                          . ' tras tu confirmacion',
    ]);
    error_log(sprintf(
        '[MIDFF-FCM-ESTADO] desbloqueo %s cliente=%s dispositivos=%d exitosos=%d fallidos=%d envio_ms=%.1f :: %s',
        $alertaId,
        $clienteDueno,
        (int) $resumenFcm['dispositivos'],
        (int) $resumenFcm['exitosos'],
        (int) $resumenFcm['fallidos'],
        (hrtime(true) - $instanteFcm) / 1e6,
        $resumenFcm['mensaje']
    ));
}

responder(200, [
    'ok'           => true,
    'mensaje'      => 'alerta desbloqueada: transaccion aprobada y atendida',
    'id_auditoria' => $idAud,
]);

/* Fin del archivo api/v1/desbloquear.php - MIDFF Post-MVP (09/10/2026) */
