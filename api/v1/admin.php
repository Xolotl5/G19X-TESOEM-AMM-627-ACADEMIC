<?php
declare(strict_types=1);

/*
  --------------------------------------------------------------------------------
  -- MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
  -- Componente: API de la consola del banco (Post-MVP, BIT-019)
  -- Endpoints : /api/v1/admin/sesion    POST  login (usuario + clave)
  --             /api/v1/admin/clave     POST  cambio de clave
  --             /api/v1/admin/resumen   GET   KPIs + top clientes + serie 24 h
  --             /api/v1/admin/alertas   GET   bandeja global con filtros
  --             /api/v1/admin/clientes  GET   clientes + dispositivos
  -- Archivo  : api/v1/admin.php (el router deriva la accion por la ruta)
  --
  -- AUTENTICACION: sesion HMAC-SHA256 sin estado (Bearer token, 8 h):
  --   1. POST sesion valida contra ADMIN_USUARIO (bcrypt o clave temporal
  --      de desarrollo) y firma {usuario, exp} con MIDFF_ADMIN_SECRETO.
  --   2. El resto de acciones exigen "Authorization: Bearer <token>".
  --   El endpoint de bloqueo sigue siendo POST /api/v1/bloquear (X-API-Key).
  --
  -- ERRORES: 405 metodo - 400 cuerpo - 401 credenciales/token - 500 Oracle.
  --------------------------------------------------------------------------------
 */

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'firebase.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bajas.php';

date_default_timezone_set('America/Mexico_City');

define('MIDFF_ADMIN_SECRETO', getenv('MIDFF_ADMIN_SECRETO') ?: 'MIDFF-SECRETO-DEV-2026');
define('MIDFF_ADMIN_SESION_SEG', 8 * 3600);

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

/* --------------------------- Token de sesion (HMAC) ------------------------- */
function midff_admin_token_firmar(string $usuario): string
{
    $payload = rtrim(strtr(base64_encode(json_encode(['u' => $usuario, 'exp' => time() + MIDFF_ADMIN_SESION_SEG])), '+/', '-_'), '=');
    return $payload . '.' . hash_hmac('sha256', $payload, MIDFF_ADMIN_SECRETO);
}

function midff_admin_token_validar(): string
{
    $encabezado = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (strpos($encabezado, 'Bearer ') !== 0) {
        responder(401, ['error' => 'encabezado Authorization: Bearer ausente']);
    }
    $partes = explode('.', substr($encabezado, 7));
    if (count($partes) !== 2 || !hash_equals(hash_hmac('sha256', $partes[0], MIDFF_ADMIN_SECRETO), $partes[1])) {
        responder(401, ['error' => 'token de sesion invalido']);
    }
    $datos = json_decode((string) base64_decode(strtr($partes[0], '-_', '+/')), true);
    if (!is_array($datos) || (int) ($datos['exp'] ?? 0) < time()) {
        responder(401, ['error' => 'sesion expirada: vuelva a iniciar sesion']);
    }
    return (string) ($datos['u'] ?? '');
}

function midff_admin_conectar(): PDO
{
    if (midff_db_driver_disponible() !== 'pdo_oci') {
        responder(500, ['error' => 'sin driver pdo_oci: revise php.ini']);
    }
    return midff_db_conectar();
}

$accion = preg_replace('/\.php$/', '', basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''));
$metodo = $_SERVER['REQUEST_METHOD'] ?? '';

/* ===== POST /api/v1/admin/sesion { usuario, clave } ======================== */
if ($accion === 'sesion') {
    if ($metodo !== 'POST') {
        header('Allow: POST');
        responder(405, ['error' => 'metodo no permitido: use POST /api/v1/admin/sesion']);
    }
    $cuerpo  = json_decode((string) file_get_contents('php://input'), true);
    $usuario = is_array($cuerpo) ? trim((string) ($cuerpo['usuario'] ?? '')) : '';
    $clave   = is_array($cuerpo) ? (string) ($cuerpo['clave'] ?? '') : '';
    if ($usuario === '' || $clave === '') {
        rechazar('campos obligatorios ausentes: usuario y clave');
    }

    try {
        $cnx  = midff_admin_conectar();
        $sent = $cnx->prepare('SELECT nombre, password_hash, clave_temporal FROM ADMIN_USUARIO'
                            . ' WHERE usuario = :u AND activo = 1');
        $sent->execute([':u' => $usuario]);
        $fila = $sent->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        responder(500, ['error' => 'fallo Oracle al autenticar (ejecute database/admin_usuario.sql): ' . trim($e->getMessage())]);
    }

    $valida = $fila !== false && midff_admin_clave_valida($clave, $fila);
    if (!$valida) {
        error_log('[MIDFF-ADMIN] acceso rechazado para "' . $usuario . '"');
        responder(401, ['error' => 'usuario o clave incorrectos']);
    }

    error_log('[MIDFF-ADMIN] sesion iniciada: ' . $usuario);
    responder(200, [
        'ok'                 => true,
        'token'              => midff_admin_token_firmar($usuario),
        'usuario'            => $usuario,
        'nombre'             => (string) ($fila['NOMBRE'] ?? $usuario),
        'debe_cambiar_clave' => midff_admin_usa_temporal($fila),
        'expira_seg'         => MIDFF_ADMIN_SESION_SEG,
    ]);
}
/* -------------- Validacion de clave (bcrypt o temporal de dev) -------------- */
function midff_admin_usa_temporal(array $fila): bool
{
    $hash = $fila['PASSWORD_HASH'] ?? null;
    return $hash === null || $hash === '';
}

function midff_admin_clave_valida(string $clave, array $fila): bool
{
    $hash     = $fila['PASSWORD_HASH'] ?? null;
    $temporal = $fila['CLAVE_TEMPORAL'] ?? null;
    if ($hash !== null && $hash !== '') {
        return password_verify($clave, $hash);
    }
    return $temporal !== null && hash_equals((string) $temporal, $clave);
}

/* ===== POST /api/v1/admin/clave { clave_actual, clave_nueva } ============== */
if ($accion === 'clave') {
    if ($metodo !== 'POST') {
        header('Allow: POST');
        responder(405, ['error' => 'metodo no permitido: use POST /api/v1/admin/clave']);
    }
    $sesion = midff_admin_token_validar();
    $cuerpo = json_decode((string) file_get_contents('php://input'), true);
    $actual = is_array($cuerpo) ? (string) ($cuerpo['clave_actual'] ?? '') : '';
    $nueva  = is_array($cuerpo) ? (string) ($cuerpo['clave_nueva'] ?? '') : '';
    if ($actual === '' || strlen($nueva) < 8) {
        rechazar('clave_actual obligatoria y clave_nueva de al menos 8 caracteres');
    }

    try {
        $cnx  = midff_admin_conectar();
        $sent = $cnx->prepare('SELECT password_hash, clave_temporal FROM ADMIN_USUARIO WHERE usuario = :u AND activo = 1');
        $sent->execute([':u' => $sesion]);
        $fila = $sent->fetch(PDO::FETCH_ASSOC);
        if ($fila === false) {
            responder(401, ['error' => 'usuario inactivo o inexistente']);
        }
        if (!midff_admin_clave_valida($actual, $fila)) {
            responder(401, ['error' => 'clave actual incorrecta']);
        }
        $sent = $cnx->prepare('UPDATE ADMIN_USUARIO SET password_hash = :h, clave_temporal = NULL WHERE usuario = :u');
        $sent->execute([':h' => password_hash($nueva, PASSWORD_DEFAULT), ':u' => $sesion]);
    } catch (PDOException $e) {
        responder(500, ['error' => 'fallo Oracle al cambiar la clave: ' . trim($e->getMessage())]);
    }

    error_log('[MIDFF-ADMIN] clave actualizada para ' . $sesion);
    responder(200, ['ok' => true, 'mensaje' => 'clave actualizada']);
}

/* ===== POST /api/v1/admin/sincronizar-bajas (BIT-031) ======================
 * Verifica contra Firebase Auth (Identity Toolkit accounts:lookup) que las
 * cuentas con uid registrado en DISPOSITIVO_MOVIL sigan vivas y purga las
 * borradas (misma logica atomica de POST /api/v1/baja): cubre el caso en que
 * el usuario borro su cuenta en Firebase y la app nunca vuelve a abrirse. */
if ($accion === 'sincronizar-bajas') {
    if ($metodo !== 'POST') {
        header('Allow: POST');
        responder(405, ['error' => 'metodo no permitido: use POST /api/v1/admin/sincronizar-bajas']);
    }
    $admin = midff_admin_token_validar();

    if (!midff_fcm_configurada()) {
        responder(503, ['error' => 'FCM sin configurar: coloque config/firebase.credentials.json']);
    }

    try {
        $cnx = midff_admin_conectar();
        // (en Oracle '' es NULL: basta IS NOT NULL; un TRIM(...) <> '' nunca
        //  seria verdadero y la consulta devolveria siempre 0 filas)
        $filas = $cnx->query('SELECT id_cliente, uid_firebase FROM DISPOSITIVO_MOVIL'
                           . ' WHERE uid_firebase IS NOT NULL'
                           . ' ORDER BY id_dispositivo')->fetchAll(PDO::FETCH_ASSOC);
        $sinUid = (int) $cnx->query('SELECT COUNT(*) FROM DISPOSITIVO_MOVIL'
                                  . ' WHERE uid_firebase IS NULL')->fetchColumn();
    } catch (PDOException $e) {
        responder(500, ['error' => 'fallo Oracle al listar dispositivos: ' . trim($e->getMessage())]);
    }

    // uid -> [id_cliente, ...] (un uid puede tener varios dispositivos).
    $pares = [];
    foreach ($filas as $fila) {
        $pares[trim((string) $fila['UID_FIREBASE'])][] = (string) $fila['ID_CLIENTE'];
    }

    $existentes = midff_fcm_verificar_uids(array_keys($pares));
    if ($existentes === null) {
        responder(502, ['error' => 'Firebase no respondio (accounts:lookup): intente de nuevo']);
    }

    $dadosDeBaja = [];
    foreach ($pares as $uid => $clientes) {
        if (isset($existentes[$uid])) {
            continue;   // la cuenta sigue viva: nada que hacer
        }
        foreach ($clientes as $idCliente) {
            $purga = midff_baja_purgar_cliente($idCliente);
            if (!$purga['ok']) {
                $dadosDeBaja[] = ['id_cliente' => $idCliente, 'ok' => false,
                                  'error' => $purga['error']];
                error_log('[MIDFF-ADMIN] sincronizar-bajas: fallo purgando ' . $idCliente . ': ' . $purga['error']);
                continue;
            }
            $dadosDeBaja[] = [
                'id_cliente'    => $idCliente,
                'ok'            => true,
                'dispositivos'  => $purga['dispositivos'],
                'transacciones' => $purga['transacciones'],
                'evaluaciones'  => $purga['evaluaciones'],
                'alertas'       => $purga['alertas'],
                'id_auditoria'  => $purga['id_auditoria'],
            ];
        }
    }

    error_log(sprintf('[MIDFF-ADMIN] sincronizar-bajas por %s: verificados=%d vivos=%d sin_uid=%d purgados=%d',
        $admin, count($pares), count($existentes), $sinUid, count($dadosDeBaja)));

    responder(200, [
        'ok'           => true,
        'verificados'  => count($pares),
        'vivas'        => count(array_intersect_key($pares, $existentes)),
        'sin_uid'      => $sinUid,   // registros previos a BIT-031: no verificables
        'dados_de_baja' => $dadosDeBaja,
        'mensaje'      => count($dadosDeBaja) . ' cuenta(s) purgada(s); ' . $sinUid . ' dispositivo(s) sin uid no verificables',
    ]);
}

/* ---- A partir de aqui toda accion es GET y exige token valido ------------- */
if ($metodo !== 'GET') {
    header('Allow: GET');
    responder(405, ['error' => 'metodo no permitido: esta accion usa GET']);
}
$admin = midff_admin_token_validar();
/* ===== GET /api/v1/admin/resumen (KPIs, bandas, top clientes, serie 24 h) == */
if ($accion === 'resumen') {
    try {
        $cnx = midff_admin_conectar();

        $hoy = $cnx->query('SELECT COUNT(*) AS n, NVL(SUM(monto), 0) AS monto'
                         . ' FROM TRANSACCION WHERE fecha_registro >= TRUNC(SYSDATE)')->fetch(PDO::FETCH_ASSOC);

        $bandas = [];
        foreach ($cnx->query('SELECT e.clasificacion AS banda, COUNT(*) AS n'
                            . ' FROM EVALUACION e WHERE e.fecha_evaluacion >= SYSDATE - 1'
                            . ' GROUP BY e.clasificacion') as $fila) {
            $bandas[$fila['BANDA']] = (int) $fila['N'];
        }

        $alertas = $cnx->query('SELECT COUNT(*) AS total,'
                             . " SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END) AS pendientes"
                             . ' FROM ALERTA WHERE fecha_creacion >= TRUNC(SYSDATE)')->fetch(PDO::FETCH_ASSOC);

        $motor = $cnx->query('SELECT NVL(ROUND(AVG(score), 4), 0) AS score, NVL(ROUND(AVG(latencia_us), 0), 0) AS latencia_us'
                            . ' FROM EVALUACION WHERE fecha_evaluacion >= SYSDATE - 1')->fetch(PDO::FETCH_ASSOC);

        $top = $cnx->query('SELECT * FROM ('
                          . ' SELECT t.id_cliente, COUNT(*) AS transacciones,'
                          . ' ROUND(NVL(AVG(e.score), 0), 3) AS score_promedio,'
                          . ' ROUND(NVL(SUM(t.monto), 0), 2) AS monto_total,'
                          . ' COUNT(a.id_alerta) AS alertas'
                          . ' FROM TRANSACCION t'
                          . ' JOIN EVALUACION e ON e.id_transaccion = t.id_transaccion'
                          . ' LEFT JOIN ALERTA a ON a.id_evaluacion = e.id_evaluacion'
                          . ' GROUP BY t.id_cliente ORDER BY 3 DESC) WHERE ROWNUM <= 5'
                          )->fetchAll(PDO::FETCH_ASSOC);

        $serie = [];
        foreach ($cnx->query("SELECT TO_CHAR(e.fecha_evaluacion, 'DD/MM HH24:00') AS hora,"
                            . " e.clasificacion AS banda, COUNT(*) AS n"
                            . ' FROM EVALUACION e WHERE e.fecha_evaluacion >= SYSDATE - 1'
                            . " GROUP BY TO_CHAR(e.fecha_evaluacion, 'DD/MM HH24:00'), e.clasificacion"
                            . ' ORDER BY MIN(e.fecha_evaluacion)') as $fila) {
            $serie[] = ['hora' => $fila['HORA'], 'banda' => $fila['BANDA'], 'n' => (int) $fila['N']];
        }
    } catch (PDOException $e) {
        responder(500, ['error' => 'fallo Oracle en el resumen: ' . trim($e->getMessage())]);
    }

    error_log('[MIDFF-ADMIN] resumen consultado por ' . $admin);
    responder(200, [
        'ok'            => true,
        'transacciones' => ['hoy' => (int) $hoy['N'], 'monto_hoy' => (float) $hoy['MONTO']],
        'bandas_24h'    => ['BAJO' => $bandas['BAJO'] ?? 0, 'MEDIO' => $bandas['MEDIO'] ?? 0, 'ALTO' => $bandas['ALTO'] ?? 0],
        'alertas'       => ['hoy' => (int) $alertas['TOTAL'], 'pendientes' => (int) $alertas['PENDIENTES']],
        'motor_24h'     => ['score_promedio' => (float) $motor['SCORE'], 'latencia_promedio_us' => (int) $motor['LATENCIA_US']],
        'top_clientes'  => $top,
        'serie_24h'     => $serie,
    ]);
}
/* ===== GET /api/v1/admin/alertas?cliente=&banda=&estado=&limite= =========== */
/* BIT-022: historial COMPLETO de evaluaciones (incluye transacciones sanas):
 * parte de EVALUACION con LEFT JOIN a ALERTA; las sanas traen id_alerta NULL. */
if ($accion === 'alertas') {
    $cliente = trim((string) ($_GET['cliente'] ?? ''));
    $banda   = strtoupper((string) ($_GET['banda'] ?? ''));
    $estado  = strtoupper((string) ($_GET['estado'] ?? ''));
    $limite  = max(10, min(200, (int) ($_GET['limite'] ?? 50)));

    if ($banda !== '' && !in_array($banda, ['BAJO', 'MEDIO', 'ALTO'], true)) {
        rechazar('banda invalida: BAJO, MEDIO o ALTO');
    }
    if ($estado !== '' && !in_array($estado, ['PENDIENTE', 'ATENDIDA', 'BLOQUEADA'], true)) {
        rechazar('estado invalido: PENDIENTE, ATENDIDA o BLOQUEADA');
    }

    $sql  = 'SELECT t.uid_transaccion, t.id_cliente, t.monto, t.moneda,'
          . ' e.id_evaluacion, e.score, e.clasificacion, e.decision,'
          . " TO_CHAR(e.fecha_evaluacion, 'YYYY-MM-DD HH24:MI:SS') AS creada,"
          . ' a.id_alerta, a.tipo, a.estado, a.atendida_por, a.descripcion, a.respuesta'
          . ' FROM EVALUACION e'
          . ' JOIN TRANSACCION t ON t.id_transaccion = e.id_transaccion'
          . ' LEFT JOIN ALERTA a ON a.id_evaluacion = e.id_evaluacion'
          . ' WHERE 1 = 1';
    $params = [];
    if ($cliente !== '') {
        $sql .= ' AND UPPER(t.id_cliente) LIKE :cliente';
        $params[':cliente'] = '%' . strtoupper($cliente) . '%';
    }
    if ($banda !== '') {
        $sql .= ' AND e.clasificacion = :banda';
        $params[':banda'] = $banda;
    }
    if ($estado !== '') {
        $sql .= ' AND a.estado = :estado';
        $params[':estado'] = $estado;
    }
    $sql .= ' ORDER BY e.id_evaluacion DESC FETCH FIRST ' . $limite . ' ROWS ONLY';

    try {
        $sent = midff_admin_conectar()->prepare($sql);
        $sent->execute($params);
        $filas = $sent->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        responder(500, ['error' => 'fallo Oracle en las alertas: ' . trim($e->getMessage())]);
    }

    foreach ($filas as $i => $fila) {
        $filas[$i]['ALERTA_ID'] = ($fila['ID_ALERTA'] ?? null) !== null && $fila['ID_ALERTA'] !== ''
            ? 'ALR-' . date('Y') . '-' . str_pad((string) $fila['ID_ALERTA'], 6, '0', STR_PAD_LEFT)
            : '';
    }

    error_log('[MIDFF-ADMIN] alertas consultadas por ' . $admin . ' (' . count($filas) . ' filas)');
    responder(200, ['ok' => true, 'total' => count($filas), 'alertas' => $filas]);
}

/* ===== GET /api/v1/admin/clientes ========================================== */
if ($accion === 'clientes') {
    try {
        $cnx = midff_admin_conectar();

        $clientes = $cnx->query('SELECT t.id_cliente, COUNT(*) AS transacciones,'
                              . ' ROUND(NVL(SUM(t.monto), 0), 2) AS monto_total,'
                              . ' ROUND(NVL(AVG(e.score), 0), 3) AS score_promedio,'
                              . ' COUNT(a.id_alerta) AS alertas,'
                              . " TO_CHAR(MAX(t.fecha_registro), 'YYYY-MM-DD HH24:MI') AS ultima_actividad"
                              . ' FROM TRANSACCION t'
                              . ' LEFT JOIN EVALUACION e ON e.id_transaccion = t.id_transaccion'
                              . ' LEFT JOIN ALERTA a ON a.id_evaluacion = e.id_evaluacion'
                              . ' GROUP BY t.id_cliente ORDER BY 4 DESC')->fetchAll(PDO::FETCH_ASSOC);

        $dispositivos = [];
        foreach ($cnx->query('SELECT id_cliente, COUNT(*) AS n FROM DISPOSITIVO_MOVIL'
                            . ' WHERE activo = 1 GROUP BY id_cliente') as $fila) {
            $dispositivos[$fila['ID_CLIENTE']] = (int) $fila['N'];
        }
    } catch (PDOException $e) {
        responder(500, ['error' => 'fallo Oracle en los clientes: ' . trim($e->getMessage())]);
    }

    foreach ($clientes as $i => $fila) {
        $clientes[$i]['DISPOSITIVOS'] = $dispositivos[$fila['ID_CLIENTE']] ?? 0;
    }

    error_log('[MIDFF-ADMIN] clientes consultados por ' . $admin . ' (' . count($clientes) . ')');
    responder(200, ['ok' => true, 'total' => count($clientes), 'clientes' => $clientes]);
}

responder(404, ['error' => 'accion de administrador desconocida: ' . $accion]);

/* Fin del archivo api/v1/admin.php - MIDFF Post-MVP (06/10/2026) */
