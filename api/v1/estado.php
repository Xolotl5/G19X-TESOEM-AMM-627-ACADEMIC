<?php
declare(strict_types=1);

/*
  --------------------------------------------------------------------------------
  -- MIDFF - Estado en Oracle de las alertas de un cliente (BIT-027)
  -- Endpoint : GET /api/v1/estado?id_cliente=CLI-001
  --            (header: Authorization: Bearer <ID token de Firebase>)
  -- Archivo  : api/v1/estado.php
  --
  -- Proposito: la bandeja de la app es un reflejo local de los push; cuando el
  --   banco BLOQUEA o DESBLOQUEA una alerta, el push de estado avisa en tiempo
  --   real, pero si ese push se pierde (doze, red, app matada) la app necesita
  --   reconciliarse con la fuente de verdad. Este endpoint de solo lectura
  --   devuelve el estado actual de TODAS las alertas del cliente para que la
  --   app sincronice su bandeja al abrirse o retomar el foco.
  --
  -- CONTRATO DE SALIDA (200):
  --   { ok, id_cliente, generado, alertas: [ { id_alerta: "ALR-<anio>-<num>",
  --     estado: PENDIENTE|ATENDIDA|BLOQUEADA, clasificacion, decision,
  --     respuesta, atendida_por, fecha_atencion, id_transaccion } ] }
  -- ERRORES: 405 metodo · 401 token ausente/invalido · 400 id_cliente ·
  --          403 id_cliente ajeno · 500 Oracle.
  --------------------------------------------------------------------------------
 */

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'http.php';

date_default_timezone_set('America/Mexico_City');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    responder(405, ['error' => 'metodo no permitido: use GET /api/v1/estado']);
}

/* BIT-037 (Corte 2): endpoint de la app; la X-API-Key de ingesta del banco ya
 * NO autoriza aqui (viaja dentro del APK). Solo el ID token de Firebase. */

/* BIT-036 (Corte 1): el id_cliente YA NO se confia al parametro de la URL.
 * Se deriva del ID token de Firebase del usuario autenticado; el parametro
 * solo se conserva por compatibilidad y se ignora si difiere. */
$idClienteToken = midff_exigir_cliente_firebase();
$idClienteParam = trim((string) ($_GET['id_cliente'] ?? ''));
if ($idClienteParam !== '' && $idClienteParam !== $idClienteToken) {
    responder(403, ['error' => 'el id_cliente solicitado no corresponde '
                             . 'al usuario autenticado']);
}
$idCliente = $idClienteToken;
if ($idCliente === '' || mb_strlen($idCliente) > 40) {
    responder(400, ['error' => 'id_cliente autenticado invalido (hasta 40 caracteres)']);
}

if (midff_db_driver_disponible() !== 'pdo_oci') {
    responder(500, ['error' => 'sin driver pdo_oci: revise php.ini']);
}

try {
    $cnx  = midff_db_conectar();
    $sent = $cnx->prepare('SELECT a.id_alerta, a.estado, a.respuesta, a.atendida_por,'
                        . " TO_CHAR(a.fecha_atencion, 'YYYY-MM-DD HH24:MI:SS') AS fecha_atencion,"
                        . ' e.clasificacion, e.decision, t.uid_transaccion'
                        . ' FROM ALERTA a'
                        . ' JOIN EVALUACION e ON e.id_evaluacion = a.id_evaluacion'
                        . ' JOIN TRANSACCION t ON t.id_transaccion = e.id_transaccion'
                        . ' WHERE t.id_cliente = :cliente'
                        . ' ORDER BY a.id_alerta DESC'
                        . ' FETCH FIRST 200 ROWS ONLY');
    $sent->execute([':cliente' => $idCliente]);

    $lista = [];
    while (($fila = $sent->fetch(PDO::FETCH_ASSOC)) !== false) {
        $lista[] = [
            // Mismo formato que emite POST /api/v1/transacciones (anio actual).
            'id_alerta'      => sprintf('ALR-%s-%06d', date('Y'), (int) $fila['ID_ALERTA']),
            'estado'         => (string) $fila['ESTADO'],
            'clasificacion'  => (string) $fila['CLASIFICACION'],
            'decision'       => (string) $fila['DECISION'],
            'respuesta'      => $fila['RESPUESTA'] !== null ? (string) $fila['RESPUESTA'] : '',
            'atendida_por'   => $fila['ATENDIDA_POR'] !== null ? (string) $fila['ATENDIDA_POR'] : '',
            'fecha_atencion' => $fila['FECHA_ATENCION'] !== null ? (string) $fila['FECHA_ATENCION'] : '',
            'id_transaccion' => (string) $fila['UID_TRANSACCION'],
        ];
    }
} catch (PDOException $e) {
    responder(500, ['error' => 'fallo Oracle al consultar el estado: ' . trim($e->getMessage())]);
}

error_log(sprintf('[MIDFF-ESTADO] %s consulto %d alerta(s)', $idCliente, count($lista)));

responder(200, [
    'ok'          => true,
    'id_cliente'  => $idCliente,
    'generado'    => date('c'),
    'alertas'     => $lista,
]);

/* Fin del archivo api/v1/estado.php - MIDFF Post-MVP (BIT-027, 09/10/2026) */