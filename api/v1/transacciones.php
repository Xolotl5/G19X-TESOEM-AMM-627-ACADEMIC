<?php
declare(strict_types=1);

/*
 --------------------------------------------------------------------------------
 -- MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
 -- Componente: API REST (Sprint 2)
 -- Endpoint : POST /api/v1/transacciones   (header obligatorio: X-API-Key)
 -- Archivo  : api/v1/transacciones.php
 --
 -- Requisitos: RF-01 (ingesta HTTP/JSON), RF-02 (validacion de entrada y API
 --             Key), RF-03 (invocacion del motor C por stdin/stdout), RF-06
 --             (respuesta sincrona), RF-07 (persistencia ACID), RF-08 (auditoria).
 -- Historias: HU-MVP-01 (ingesta y respuesta <= 1 s), HU-MVP-03 (decision por
 --             umbral), HU-MVP-04 (registro completo y atomico).
 --
 -- FLUJO DEL ENDPOINT (MVP_MIDFF.md seccion 7):
 --   1. Valida metodo (solo POST) y el encabezado X-API-Key  -> 405 / 401
 --   2. Valida el cuerpo JSON y los campos obligatorios      -> 400
 --   3. Genera id_transaccion (la API lo asigna, RF-02)
 --   4. Invoca src/motor/bin/midff-scoring.exe por stdin/stdout (RF-03)
 --   5. Traduce codigos de salida del motor: 0 -> 200, 2 -> 400, 3 -> 500
 --   6. Persiste con SP_REGISTRAR_TRANSACCION (config/db.php) y hace COMMIT
 --   7. Responde el contrato JSON del MVP (Anexo A):
 --        { id_transaccion, score, clasificacion, decision,
 --          fecha_evaluacion, alerta_id }
 --
 -- CONTRATO DE ENTRADA (contrato_scoring.md seccion 2 + Anexo A del MVP):
 --   Obligatorios : id_cliente (string <= 40), monto (numero > 0)
 --   Opcionales   : moneda (3 letras), origen (ERP|BANCA|PASARELA|OTRO),
 --                  ubicacion, ip_origen, canal, fecha_hora (ISO 8601) y
 --                  contexto (passthrough al motor mientras no exista la
 --                  tabla de historial; si falta, el motor aplica fail-safe).
 --
 -- VARIABLES DE ENTORNO OPCIONALES:
 --   MIDFF_API_KEY   clave esperada en X-API-Key (default de desarrollo)
 --   MIDFF_MOTOR_EXE ruta del ejecutable del motor C
 --------------------------------------------------------------------------------
 */

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';

date_default_timezone_set('America/Mexico_City');

/* -----------------------------------------------------------------------------
 * Parametros locales del endpoint (placeholders sobreescribibles por entorno)
 * -------------------------------------------------------------------------- */
define('MIDFF_API_KEY', getenv('MIDFF_API_KEY') ?: 'MIDFF-API-KEY-2026-SPRINT2');
define('MIDFF_MOTOR_EXE', getenv('MIDFF_MOTOR_EXE')
    ?: dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'motor'
       . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'midff-scoring.exe');

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
 * 1. METODO: solo POST (405 + encabezado Allow, RFC 7231)
 * -------------------------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responder(405, ['error' => 'metodo no permitido: use POST /api/v1/transacciones']);
}

/* -----------------------------------------------------------------------------
 * 2. API KEY (RF-02 / RNF-03): comparacion en tiempo constante (hash_equals)
 * -------------------------------------------------------------------------- */
$claveRecibida = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($claveRecibida === '' || !hash_equals(MIDFF_API_KEY, (string) $claveRecibida)) {
    responder(401, ['error' => 'encabezado X-API-Key ausente o invalido']);
}

/* -----------------------------------------------------------------------------
 * 3. CUERPO JSON Y VALIDACION DE CAMPOS (RF-02 / HU-MVP-01.3)
 * -------------------------------------------------------------------------- */
$crudo = file_get_contents('php://input');
if ($crudo === false || trim($crudo) === '') {
    rechazar('cuerpo de la peticion vacio: se espera un objeto JSON de transaccion');
}

$cuerpo = json_decode($crudo, true);
if (!is_array($cuerpo)) {
    rechazar('cuerpo JSON invalido o mal formado');
}
// id_cliente: obligatorio, string no vacio, <= 40 (VARCHAR2(40) en TRANSACCION).
$idCliente = $cuerpo['id_cliente'] ?? null;
if (!is_string($idCliente) || trim($idCliente) === '') {
    rechazar('campo obligatorio ausente o invalido: id_cliente (cadena no vacia)');
}
if (mb_strlen($idCliente) > 40) {
    rechazar('id_cliente excede 40 caracteres');
}

// monto: obligatorio, numerico > 0 (NUMBER(18,2) en TRANSACCION).
$monto = $cuerpo['monto'] ?? null;
if (!is_numeric($monto) || (float) $monto <= 0.0) {
    rechazar('campo obligatorio ausente o invalido: monto (numerico mayor que cero)');
}
$monto = (float) $monto;

// moneda: 3 letras (CHAR(3)); default 'MXN'.
$moneda = $cuerpo['moneda'] ?? 'MXN';
if (!is_string($moneda) || !preg_match('/^[A-Za-z]{3}$/', $moneda)) {
    rechazar('moneda invalida: se esperan 3 letras (p. ej. MXN)');
}
$moneda = strtoupper($moneda);

// origen: ERP | BANCA | PASARELA | OTRO (CHECK en TRANSACCION.origen); default OTRO.
$origen = $cuerpo['origen'] ?? 'OTRO';
if (!is_string($origen) || !in_array(strtoupper($origen), ['ERP', 'BANCA', 'PASARELA', 'OTRO'], true)) {
    rechazar('origen invalido: se espera ERP, BANCA, PASARELA u OTRO');
}
$origen = strtoupper($origen);

// Opcionales de texto, acotados por las columnas de TRANSACCION.
$campoTexto = static function (mixed $valor, string $nombre, int $max): ?string {
    if ($valor === null) {
        return null;
    }
    if (!is_string($valor) || trim($valor) === '') {
        rechazar("campo opcional invalido: $nombre (cadena no vacia o ausente)");
    }
    if (mb_strlen($valor) > $max) {
        rechazar("$nombre excede $max caracteres");
    }
    return $valor;
};
$ubicacion = $campoTexto($cuerpo['ubicacion'] ?? null, 'ubicacion', 80);
$ipOrigen  = $campoTexto($cuerpo['ip_origen'] ?? null, 'ip_origen', 45);
$canal     = $campoTexto($cuerpo['canal'] ?? null, 'canal', 20);

// fecha_hora: ISO 8601; si falta se usa la hora actual del servidor de la API.
try {
    $fechaHora = isset($cuerpo['fecha_hora']) && is_string($cuerpo['fecha_hora']) && $cuerpo['fecha_hora'] !== ''
        ? new DateTimeImmutable($cuerpo['fecha_hora'])
        : new DateTimeImmutable('now');
} catch (Exception) {
    rechazar('fecha_hora invalida: se espera una marca de tiempo ISO 8601 (p. ej. 2026-09-21T10:15:00-06:00)');
}
$fechaHoraIso = $fechaHora->format('Y-m-d\\TH:i:sP');   // formato que TO_TIMESTAMP_TZ espera

// contexto: passthrough opcional hacia el motor (contrato_scoring.md seccion 2.2).
$contexto = null;
if (array_key_exists('contexto', $cuerpo) && $cuerpo['contexto'] !== null) {
    if (!is_array($cuerpo['contexto'])) {
        rechazar('contexto invalido: se espera un objeto con el historial del cliente');
    }
    $contexto = $cuerpo['contexto'];
    if (isset($contexto['monto_promedio_historial'])
        && (!is_numeric($contexto['monto_promedio_historial'])
            || (float) $contexto['monto_promedio_historial'] <= 0.0)) {
        rechazar('contexto.monto_promedio_historial invalido: numerico mayor que cero');
    }
    if (isset($contexto['transacciones_ultima_hora'])
        && (!is_int($contexto['transacciones_ultima_hora']) || $contexto['transacciones_ultima_hora'] <= 0)) {
        rechazar('contexto.transacciones_ultima_hora invalido: entero mayor que cero');
    }
    foreach (['ubicacion_habitual', 'ip_habitual'] as $campoContexto) {
        if (isset($contexto[$campoContexto]) && !is_string($contexto[$campoContexto])) {
            rechazar("contexto.$campoContexto invalido: se espera una cadena");
        }
    }
}

/* -----------------------------------------------------------------------------
 * 4. IDENTIFICADOR DE LA TRANSACCION (asignado por la API, RF-02)
 *    Formato TRX-<anio>-<8 hex>: <= VARCHAR2(40) y unico por construccion.
 * -------------------------------------------------------------------------- */
try {
    $idTransaccion = sprintf('TRX-%s-%s', date('Y'), strtoupper(bin2hex(random_bytes(4))));
} catch (Exception) {
    fallaInterna('no fue posible generar el identificador de la transaccion');
}
/* -----------------------------------------------------------------------------
 * 5. INVOCACION DEL MOTOR C (RF-03): stdin -> stdout, codigos 0/2/3
 * -------------------------------------------------------------------------- */
$entradaMotor = [
    'id_transaccion' => $idTransaccion,
    'id_cliente'     => $idCliente,
    'monto'          => $monto,
    'moneda'         => $moneda,
    'origen'         => $origen,
];
foreach (['ubicacion' => $ubicacion, 'ip_origen' => $ipOrigen, 'canal' => $canal] as $k => $v) {
    if ($v !== null) {
        $entradaMotor[$k] = $v;
    }
}
$entradaMotor['fecha_hora'] = $fechaHoraIso;
if ($contexto !== null) {
    $entradaMotor['contexto'] = $contexto;
}

$jsonEntrada = json_encode($entradaMotor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($jsonEntrada === false || strlen($jsonEntrada) > 8192) {
    rechazar('la transaccion codificada excede el limite de 8192 bytes del motor');
}

/**
 * Ejecuta midff-scoring.exe entregando el JSON por stdin y capturando
 * stdout/stderr (misma mecanica que build.ps1 usa para validar el contrato).
 *
 * @return array{codigo: int, stdout: string, stderr: string}
 */
function invocarMotor(string $jsonEntrada): array
{
    if (!is_file(MIDFF_MOTOR_EXE)) {
        return ['codigo' => -1, 'stdout' => '', 'stderr' => json_encode(
            ['componente' => 'api-midff',
             'error'      => 'ejecutable del motor no encontrado: ' . MIDFF_MOTOR_EXE
                             . ' (compile con build.ps1)'])];
    }

    $descriptores = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proceso = proc_open(MIDFF_MOTOR_EXE, $descriptores, $tuberias,
                         dirname(MIDFF_MOTOR_EXE), null, ['bypass_shell' => true]);
    if (!is_resource($proceso)) {
        return ['codigo' => -1, 'stdout' => '', 'stderr' => json_encode(
            ['componente' => 'api-midff', 'error' => 'no fue posible lanzar el proceso del motor C'])];
    }

    fwrite($tuberias[0], $jsonEntrada);
    fclose($tuberias[0]);

    $stdout = stream_get_contents($tuberias[1]) ?: '';
    $stderr = stream_get_contents($tuberias[2]) ?: '';
    fclose($tuberias[1]);
    fclose($tuberias[2]);

    return ['codigo' => proc_close($proceso), 'stdout' => $stdout, 'stderr' => $stderr];
}

$instanteMotor        = hrtime(true);
$resultadoMotor       = invocarMotor($jsonEntrada);
$latenciaInvocacionMs = (hrtime(true) - $instanteMotor) / 1e6;   // informativo (riesgo R-01)

// Traduccion de los codigos de salida del contrato (contrato_scoring.md seccion 1).
$diagnostico = static function (string $stderr): string {
    $d = json_decode($stderr, true);
    return is_array($d) && isset($d['error'])
        ? (string) $d['error']
        : ($stderr !== '' ? $stderr : 'sin detalle');
};

if ($resultadoMotor['codigo'] === 2) {
    rechazar('entrada rechazada por el motor de scoring: ' . $diagnostico($resultadoMotor['stderr']));
}
if ($resultadoMotor['codigo'] !== 0) {
    fallaInterna('fallo del motor de scoring (codigo ' . $resultadoMotor['codigo'] . '): '
                 . $diagnostico($resultadoMotor['stderr']));
}

$salida = json_decode($resultadoMotor['stdout'], true);
if (!is_array($salida)) {
    fallaInterna('la salida del motor de scoring no es JSON valido');
}

$score          = $salida['score']          ?? null;
$clasificacion  = $salida['clasificacion']  ?? null;
$decision       = $salida['decision']       ?? null;
$variables      = is_array($salida['variables'] ?? null) ? $salida['variables'] : [];
$datosFaltantes = is_array($salida['datos_faltantes'] ?? null) ? $salida['datos_faltantes'] : [];

// Defensa en profundidad: el SP revalida la coherencia score/umbral (D-05).
if (!is_numeric($score) || (float) $score < 0.0 || (float) $score > 1.0
    || !in_array($clasificacion, ['BAJO', 'MEDIO', 'ALTO'], true)
    || !in_array($decision, ['APROBADA', 'EN_REVISION', 'BLOQUEADA'], true)) {
    fallaInterna('la salida del motor no cumple el contrato (score/clasificacion/decision)');
}
$score = (float) $score;
/* -----------------------------------------------------------------------------
 * 6. PERSISTENCIA ATOMICA EN ORACLE XE (RF-07 / RF-08 / RNF-05 / HU-MVP-04)
 * -------------------------------------------------------------------------- */
$persistencia = midff_db_registrar_transaccion([
    'uid_transaccion'      => $idTransaccion,
    'id_cliente'           => $idCliente,
    'monto'                => $monto,
    'origen'               => $origen,
    'fecha_hora'           => $fechaHoraIso,
    'score'                => $score,
    'clasificacion'        => $clasificacion,
    'decision'             => $decision,
    'moneda'               => $moneda,
    'canal'                => $canal,
    'ubicacion'            => $ubicacion,
    'ip_origen'            => $ipOrigen,
    'puntaje_monto'        => $variables['monto']['puntaje']        ?? null,
    'puntaje_frecuencia'   => $variables['frecuencia']['puntaje']   ?? null,
    'puntaje_ubicacion_ip' => $variables['ubicacion_ip']['puntaje'] ?? null,
    'variables_usadas'     => $variables === [] ? null : json_encode($variables, JSON_UNESCAPED_UNICODE),
    'latencia_us'          => isset($salida['latencia_evaluacion_us'])
                              && is_numeric($salida['latencia_evaluacion_us'])
                                ? (float) $salida['latencia_evaluacion_us'] : null,
    'datos_faltantes'      => $datosFaltantes === [] ? null : implode(', ', $datosFaltantes),
    'usuario_sistema'      => 'API-MIDFF',
]);

if (!$persistencia['ok']) {
    fallaInterna('fallo la persistencia en Oracle (RNF-05: se revirtio la unidad atomica): '
                 . $persistencia['mensaje']);
}

/* -----------------------------------------------------------------------------
 * 7. RESPUESTA SINCRONA (RF-06 / HU-MVP-01): contrato del Anexo A del MVP.
 *    alerta_id solo existe cuando el score >= 0.4 creo la ALERTA en Oracle.
 * -------------------------------------------------------------------------- */
$alertaId = $persistencia['id_alerta'] !== null
    ? sprintf('ALR-%s-%06d', date('Y'), (int) $persistencia['id_alerta'])
    : null;

error_log(sprintf(
    '[MIDFF] %s cliente=%s score=%.4f %s/%s motor_us=%.3f invocacion_ms=%.3f ids=%d/%d alerta=%s',
    $idTransaccion,
    $idCliente,
    $score,
    $clasificacion,
    $decision,
    (float) ($salida['latencia_evaluacion_us'] ?? 0),
    $latenciaInvocacionMs,
    (int) $persistencia['id_transaccion'],
    (int) $persistencia['id_evaluacion'],
    $alertaId ?? '-'
));

responder(200, [
    'id_transaccion'   => $idTransaccion,
    'score'            => $score,
    'clasificacion'    => $clasificacion,
    'decision'         => $decision,
    'fecha_evaluacion' => $fechaHoraIso,
    'alerta_id'        => $alertaId,
]);

/* Fin del archivo api/v1/transacciones.php - MIDFF Sprint 2 (24/09/2026) */