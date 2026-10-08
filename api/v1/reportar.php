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
    responder(405, ['error' => 'metodo no permitido: use POST /api/v1/reportar']);
}

$claveRecibida = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($claveRecibida === '' || !hash_equals(MIDFF_API_KEY, (string) $claveRecibida)) {
    responder(401, ['error' => 'encabezado X-API-Key ausente o invalido']);
}

$cuerpo = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($cuerpo)) {
    rechazar('cuerpo JSON invalido o mal formado');
}

$alertaId   = trim((string) ($cuerpo['alerta_id'] ?? ''));
$idCliente  = trim((string) ($cuerpo['id_cliente'] ?? ''));
$respuesta  = strtoupper(trim((string) ($cuerpo['respuesta'] ?? '')));
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
    $ver = $cnx->prepare('SELECT a.respuesta'
                       . ' FROM ALERTA a'
                       . ' JOIN EVALUACION e ON e.id_evaluacion = a.id_evaluacion'
                       . ' JOIN TRANSACCION t ON t.id_transaccion = e.id_transaccion'
                       . ' WHERE a.id_alerta = :id AND t.id_cliente = :cliente'
                       . ' FOR UPDATE');
    $ver->execute([':id' => $idNumerico, ':cliente' => $idCliente]);
    $previa = $ver->fetchColumn();
    if ($previa === false) {
        $cnx->rollBack();
        responder(404, ['error' => 'alerta inexistente o no pertenece al cliente ' . $idCliente]);
    }
    if ($previa !== null && trim((string) $previa) !== '') {
        $cnx->rollBack();
        responder(409, ['error' => 'la alerta ya fue reportada por el cliente']);
    }

    $upd = $cnx->prepare('UPDATE ALERTA'
                       . ' SET respuesta = :respuesta, atendida_por = :cliente,'
                       . ' fecha_atencion = SYSTIMESTAMP'
                       . ' WHERE id_alerta = :id');
    $upd->execute([':respuesta' => $respuestaCompleta, ':cliente' => $idCliente, ':id' => $idNumerico]);

    $aud = $cnx->prepare('INSERT INTO AUDITORIA (id_auditoria, entidad, accion, usuario_o_sistema, detalle)'
                       . ' VALUES (SEQ_AUDITORIA.NEXTVAL, \'ALERTA\', \'ATENCION\', :usuario, :detalle)');
    $aud->execute([':usuario' => 'CLIENTE:' . $idCliente,
                   ':detalle' => 'Reporte del cliente sobre ' . $alertaId . ': ' . $respuestaCompleta]);

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
