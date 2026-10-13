<?php
declare(strict_types=1);

/*
  --------------------------------------------------------------------------------
  -- MIDFF - Baja de cuenta de cliente (BIT-030)
  -- Endpoint : POST /api/v1/baja      (header obligatorio: X-API-Key)
  -- Archivo  : api/v1/baja.php
  --
  -- Proposito: cuando una cuenta se elimina desde Firebase Auth (consola o
  --   por soporte), el usuario debe desaparecer DE TODOS LADOS del MIDFF.
  --   La app lo invoca automaticamente al detectar que su cuenta ya no existe
  --   (FirebaseAuthInvalidUserException en MainActivity.verificarCuentaFirebase);
  --   tambien puede llamarlo el banco a mano para cuentas que no abren su app.
  --
  -- Que purga (una sola transaccion atomica, RNF-05):
  --   1. DISPOSITIVO_MOVIL : tokens FCM del cliente (dejan de recibir push).
  --   2. ALERTA            : alertas de sus transacciones (y sus reportes).
  --   3. EVALUACION        : evaluaciones del motor de sus transacciones.
  --   4. TRANSACCION       : TODO su historial de transacciones.
  --   5. AUDITORIA         : NO se toca. Es inmutable por diseno (RF-08 y el
  --      trigger TRG_AUDITORIA_INMUTABLE, RNF-04, bloquea UPDATE/DELETE: un
  --      intento de anonimizacion activa falla con ORA-20010). La desvin-
  --      culacion es EFECTIVA de todos modos: el seudonimo 'CLIENTE:<id>'
  --      (CLI-XXXXXX derivado del uid de Firebase) solo era resoluble a una
  --      persona a traves de su cuenta de Firebase, que es lo que se borro:
  --      los registros historicos quedan huerfanos de identidad (D-57).
  --   6. Se inserta UN registro del evento en AUDITORIA como ATENCION del
  --      banco ('BANCO:BAJA'), respetando los CHECK del schema congelado.
  --
  -- CONTRATO DE ENTRADA (JSON): { id_cliente (string <= 40, obligatorio) }
  -- CONTRATO DE SALIDA (200): { ok, mensaje, id_cliente, dispositivos,
  --                             transacciones, evaluaciones, alertas,
  --                             id_auditoria }
  -- ERRORES: 405 metodo · 401 API Key · 400 campos · 500 Oracle.
  -- Es IDEMPOTENTE: repetirlo sobre un cliente ya purgado devuelve ceros (200),
  --   para que la app pueda reintentar la baja sin condiciones de carrera.
  --
  -- NOTA DE SEGURIDAD (produccion): la identidad del llamador debe validarse
  --   con el token de Firebase del propio usuario o de un administrador; la
  --   X-API-Key compartida es aceptable solo en el alcance demo de residencias.
  --------------------------------------------------------------------------------
 */

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bajas.php';

date_default_timezone_set('America/Mexico_City');

define('MIDFF_API_KEY', getenv('MIDFF_API_KEY') ?: 'MIDFF-API-KEY-2026-SPRINT2');

function responder(int $codigo, array $cuerpo): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responder(405, ['error' => 'metodo no permitido: use POST /api/v1/baja']);
}

$claveRecibida = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($claveRecibida === '' || !hash_equals(MIDFF_API_KEY, (string) $claveRecibida)) {
    responder(401, ['error' => 'encabezado X-API-Key ausente o invalido']);
}

$cuerpo    = json_decode((string) file_get_contents('php://input'), true);
$idCliente = trim((string) (is_array($cuerpo) ? ($cuerpo['id_cliente'] ?? '') : ''));

if ($idCliente === '' || mb_strlen($idCliente) > 40) {
    responder(400, ['error' => 'id_cliente obligatorio (cadena de hasta 40 caracteres)']);
}

if (midff_db_driver_disponible() !== 'pdo_oci') {
    responder(500, ['error' => 'sin driver pdo_oci: revise php.ini']);
}

/* ----------------------- TRANSACCION ATOMICA (RNF-05) --------------------- */
$purga = midff_baja_purgar_cliente($idCliente);
if (!$purga['ok']) {
    error_log('[MIDFF-BAJA] fallo Oracle: ' . $purga['error']);
    responder(500, ['error' => 'fallo Oracle al dar de baja la cuenta: ' . $purga['error']]);
}

// La logica vive en config/bajas.php (compartida con la sincronizacion del
// banco, BIT-031): purga dispositivos + historial y deja el evento BANCO:BAJA.
$dispositivos  = $purga['dispositivos'];
$alertas       = $purga['alertas'];
$evaluaciones  = $purga['evaluaciones'];
$transacciones = $purga['transacciones'];
$idAuditoria   = $purga['id_auditoria'];

error_log(sprintf(
    '[MIDFF-BAJA] %s purgada: dispositivos=%d transacciones=%d evaluaciones=%d alertas=%d (auditoria conservada)',
    $idCliente,
    $dispositivos,
    $transacciones,
    $evaluaciones,
    $alertas
));

responder(200, [
    'ok'            => true,
    'mensaje'       => 'cuenta eliminada del MIDFF: dispositivos, transacciones, evaluaciones y alertas purgados;'
                     . ' auditoria conservada (inmutable RNF-04) y desvinculada al borrar la cuenta de Firebase',
    'id_cliente'    => $idCliente,
    'dispositivos'  => $dispositivos,
    'transacciones' => $transacciones,
    'evaluaciones'  => $evaluaciones,
    'alertas'       => $alertas,
    'id_auditoria'  => $idAuditoria,
]);

/* Fin del archivo api/v1/baja.php - MIDFF Post-MVP (BIT-030, 12/10/2026) */