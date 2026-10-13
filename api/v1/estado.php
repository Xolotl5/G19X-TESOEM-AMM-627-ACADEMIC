<?php
declare(strict_types=1);

/*
  --------------------------------------------------------------------------------
  -- MIDFF - Estado en Oracle de las alertas de un cliente (BIT-027)
  -- Endpoint : GET /api/v1/estado?id_cliente=CLI-001   (header: X-API-Key)
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
  -- ERRORES: 405 metodo · 401 API Key · 400 id_cliente · 500 Oracle.
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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    responder(405, ['error' => 'metodo no permitido: use GET /api/v1/estado?id_cliente=...']);
}

$claveRecibida = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($claveRecibida === '' || !hash_equals(MIDFF_API_KEY, (string) $claveRecibida)) {
    responder(401, ['error' => 'encabezado X-API-Key ausente o invalido']);
}

$idCliente = trim((string) ($_GET['id_cliente'] ?? ''));
if ($idCliente === '' || mb_strlen($idCliente) > 40) {
    responder(400, ['error' => 'parametro id_cliente obligatorio (hasta 40 caracteres)']);
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