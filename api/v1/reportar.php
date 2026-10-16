<?php
declare(strict_types=1);

/*
  --------------------------------------------------------------------------------
  -- MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
  -- Componente: reporte del cliente desde la app (Post-MVP, BIT-020)
  -- Endpoint : POST /api/v1/reportar   (header obligatorio: X-API-Key)
  -- Archivo  : api/v1/reportar.php
  --
  -- Proposito: el cliente responde a SUS alertas con "No fui yo" (sospecha
  --   de fraude) o "Si fui yo" (legitima). El reporte queda en ALERTA
  --   (respuesta/atendida_por/fecha_atencion; el estado lo decide el banco)
  --   y en AUDITORIA (accion ATENCION, usuario_o_sistema = CLIENTE:<id>),
  --   y se muestra en la consola MIDFF.html como bandera del cliente.
  --
  -- CONTRATO DE ENTRADA (JSON):
  --   Obligatorios : alerta_id  (string ALR-<anio>-<numero>)
  --                  id_cliente (string <= 40, dueno de la alerta)
  --                  respuesta  ("NO_FUI_YO" | "SI_FUI_YO")
  --   Opcionales   : comentario (string <= 180)
  --
  -- CONTRATO DE SALIDA (200): { ok, mensaje, id_auditoria }
  -- ERRORES: 405 metodo · 401 API Key · 400 campos · 404 alerta ajena ·
  --          409 ya reportada · 500 fallo de Oracle.
  --------------------------------------------------------------------------------
 */

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'http.php';

date_default_timezone_set('America/Mexico_City');


if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responder(405, ['error' => 'metodo no permitido: use POST /api/v1/reportar']);
}

midff_exigir_api_key();



$cuerpo = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($cuerpo)) {
    rechazar('cuerpo JSON invalido o mal formado');
}

$alertaId   = trim((string) ($cuerpo['alerta_id'] ?? ''));
$idCliente  = trim((string) ($cuerpo['id_cliente'] ?? ''));
$respuesta  = strtoupper(trim((string) ($cuerpo['respuesta'] ?? '')));

/* BIT-036 (Corte 1): el reporte solo lo puede hacer el dueno autenticado;
 * el id_cliente del cuerpo debe coincidir con el del ID token de Firebase. */
$idClienteAutenticado = midff_exigir_cliente_firebase();
if ($idCliente !== $idClienteAutenticado) {
    responder(403, ['error' => 'el id_cliente no corresponde al usuario autenticado']);
}
$comentario = trim((string) ($cuerpo['comentario'] ?? ''));

if ($alertaId === '' || !preg_match('/^ALR-\d{4}-\d{1,18}$/', $alertaId)) {
    rechazar('alerta_id invalido: se espera el formato ALR-<anio>-<numero>');
}
if ($idCliente === '' || mb_strlen($idCliente) > 40) {
    rechazar('id_cliente obligatorio (cadena de hasta 40 caracteres)');
}
if (!in_array($respuesta, ['NO_FUI_YO', 'SI_FUI_YO'], true)) {
    rechazar('respuesta invalida: se espera NO_FUI_YO o SI_FUI_YO');
}
if (mb_strlen($comentario) > 180) {
    rechazar('comentario excede 180 caracteres');
}

$idNumerico        = (int) substr($alertaId, strrpos($alertaId, '-') + 1);
$respuestaCompleta = $respuesta . ($comentario !== '' ? ' :: ' . $comentario : '');
/* 4. TRANSACCION ATOMICA: verificacion de dueno + ALERTA + AUDITORIA */
if (midff_db_driver_disponible() !== 'pdo_oci') {
    responder(500, ['error' => 'sin driver pdo_oci: revise php.ini']);
}

try {
    $cnx = midff_db_conectar();
    $cnx->beginTransaction();

    // Solo el dueno de la transaccion puede reportar su alerta (RF-02/R-05).
    // BIT-024: se trae tambien e.decision porque la aprobacion automatica con
    // "SI FUI YO" solo aplica a nivel MEDIO (EN_REVISION); en ALTO la alerta
    // queda PENDIENTE hasta que el banco la desbloquee o bloquee.
    $ver = $cnx->prepare('SELECT a.respuesta, e.decision'
                       . ' FROM ALERTA a'
                       . ' JOIN EVALUACION e ON e.id_evaluacion = a.id_evaluacion'
                       . ' JOIN TRANSACCION t ON t.id_transaccion = e.id_transaccion'
                       . ' WHERE a.id_alerta = :id AND t.id_cliente = :cliente'
                       . ' FOR UPDATE');
    $ver->execute([':id' => $idNumerico, ':cliente' => $idCliente]);
    $fila = $ver->fetch(PDO::FETCH_ASSOC);
    $previa = $fila === false ? false : $fila['RESPUESTA'];
    if ($fila === false) {
        $cnx->rollBack();
        responder(404, ['error' => 'alerta inexistente o no pertenece al cliente ' . $idCliente]);
    }
    if ($previa !== null && trim((string) $previa) !== '') {
        $cnx->rollBack();
        responder(409, ['error' => 'la alerta ya fue reportada por el cliente']);
    }

    // Nivel MEDIO (EN_REVISION): "SI FUI YO" aprueba la alerta en el acto.
    // Nivel ALTO (BLOQUEADA): queda PENDIENTE para que el banco decida
    // (desbloqueo habilitado en MIDFF.html tras esta confirmacion).
    $aprueba = ($respuesta === 'SI_FUI_YO' && strtoupper((string) $fila['DECISION']) === 'EN_REVISION') ? 1 : 0;

    $upd = $cnx->prepare('UPDATE ALERTA'
                       . ' SET respuesta = :respuesta, atendida_por = :cliente,'
                       . ' fecha_atencion = SYSTIMESTAMP,'
                       . " estado = CASE WHEN :aprobada = 1 THEN 'ATENDIDA' ELSE estado END"
                       . ' WHERE id_alerta = :id');
    $upd->execute([':respuesta' => $respuestaCompleta, ':cliente' => $idCliente,
                   ':aprobada' => $aprueba, ':id' => $idNumerico]);

    $detalleAud = $respuesta === 'SI_FUI_YO'
        ? 'Confirmacion del cliente sobre ' . $alertaId . ': SI_FUI_YO (alerta aprobada'
          . ($respuestaCompleta !== $respuesta ? ', con comentario' : '') . ')'
        : 'Reporte del cliente sobre ' . $alertaId . ': ' . $respuestaCompleta;

    $aud = $cnx->prepare('INSERT INTO AUDITORIA (id_auditoria, entidad, accion, usuario_o_sistema, detalle)'
                       . ' VALUES (SEQ_AUDITORIA.NEXTVAL, \'ALERTA\', \'ATENCION\', :usuario, :detalle)');
    $aud->execute([':usuario' => 'CLIENTE:' . $idCliente, ':detalle' => $detalleAud]);

    $cnx->commit();

    $idAud = (int) $cnx->query('SELECT SEQ_AUDITORIA.CURRVAL FROM DUAL')->fetchColumn();
} catch (PDOException $e) {
    responder(500, ['error' => 'fallo Oracle al registrar el reporte: ' . trim($e->getMessage())]);
}

error_log(sprintf('[MIDFF-REPORTE] %s reporto %s sobre %s (auditoria %d)',
                  $idCliente, $respuesta, $alertaId, $idAud));

responder(200, [
    'ok'           => true,
    'mensaje'      => $respuesta === 'NO_FUI_YO'
                    ? 'Reporte recibido: el banco revisara esta transaccion'
                    : 'Gracias: transaccion confirmada por el cliente',
    'id_auditoria' => $idAud,
]);

/* Fin del archivo api/v1/reportar.php - MIDFF Post-MVP (06/10/2026) */
