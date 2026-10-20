<?php
declare(strict_types=1);

/*
 --------------------------------------------------------------------------------
 -- MIDFF - Router del servidor embebido de PHP (desarrollo / smoke test)
 -- Archivo: router.php
 --
 -- Uso (desde la raiz del repositorio):
 --     php -S 0.0.0.0:8091 router.php
 --
 -- Expone el endpoint SIN extension, tal como lo define el contrato del MVP:
 --     POST /api/v1/transacciones  ->  api/v1/transacciones.php
 --
 -- Cualquier otra ruta se devuelve al servidor embebido (archivos estaticos).
 -- Este archivo NO forma parte del despliegue de produccion (Apache/Nginx
 -- aplicarian sus propias reglas de reescritura); existe para que el
 -- smoke-test.ps1 y las pruebas manuales usen la ruta oficial del contrato.
 --------------------------------------------------------------------------------
 */

$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($ruta === '/api/v1/transacciones') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1'
          . DIRECTORY_SEPARATOR . 'transacciones.php';
    return true;
}

if ($ruta === '/api/v1/transacciones.php') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1'
          . DIRECTORY_SEPARATOR . 'transacciones.php';
    return true;
}

if ($ruta === '/api/v1/dispositivos') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1'
          . DIRECTORY_SEPARATOR . 'dispositivos.php';
    return true;
}

if ($ruta === '/api/v1/dispositivos.php') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1'
          . DIRECTORY_SEPARATOR . 'dispositivos.php';
    return true;
}

if ($ruta === '/api/v1/bloquear') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1'
          . DIRECTORY_SEPARATOR . 'bloquear.php';
    return true;
}

if ($ruta === '/api/v1/bloquear.php') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1'
          . DIRECTORY_SEPARATOR . 'bloquear.php';
    return true;
}

if ($ruta === '/api/v1/desbloquear' || $ruta === '/api/v1/desbloquear.php') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1'
          . DIRECTORY_SEPARATOR . 'desbloquear.php';
    return true;
}

if ($ruta === '/api/v1/reportar' || $ruta === '/api/v1/reportar.php') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1'
          . DIRECTORY_SEPARATOR . 'reportar.php';
    return true;
}

if ($ruta === '/api/v1/admin/sesion' || $ruta === '/api/v1/admin/sesion.php') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1'
          . DIRECTORY_SEPARATOR . 'admin.php';
    return true;
}

if ($ruta === '/api/v1/admin/clave' || $ruta === '/api/v1/admin/clave.php') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1'
          . DIRECTORY_SEPARATOR . 'admin.php';
    return true;
}

if ($ruta === '/api/v1/admin/resumen' || $ruta === '/api/v1/admin/resumen.php') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1'
          . DIRECTORY_SEPARATOR . 'admin.php';
    return true;
}

if ($ruta === '/api/v1/admin/alertas' || $ruta === '/api/v1/admin/alertas.php') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1'
          . DIRECTORY_SEPARATOR . 'admin.php';
    return true;
}

if ($ruta === '/api/v1/admin/clientes' || $ruta === '/api/v1/admin/clientes.php') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1'
          . DIRECTORY_SEPARATOR . 'admin.php';
    return true;
}

// Sincronizacion de bajas con Firebase Auth (BIT-031): purga las cuentas
// eliminadas en Firebase aunque la app nunca vuelva a abrirse.
if ($ruta === '/api/v1/admin/sincronizar-bajas' || $ruta === '/api/v1/admin/sincronizar-bajas.php') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1'
          . DIRECTORY_SEPARATOR . 'admin.php';
    return true;
}

// Baja de cuenta de cliente: purga dispositivos + historial completo y
// anonimiza la auditoria (lo llama la app cuando Firebase ya no tiene al
// usuario, BIT-030).
if ($ruta === '/api/v1/baja' || $ruta === '/api/v1/baja.php') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1'
          . DIRECTORY_SEPARATOR . 'baja.php';
    return true;
}

// Estado en Oracle de las alertas de un cliente: sincronizacion de la bandeja
// de la app con las decisiones del banco (BIT-027).
if ($ruta === '/api/v1/estado' || $ruta === '/api/v1/estado.php') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1'
          . DIRECTORY_SEPARATOR . 'estado.php';
    return true;
}

// Sondeo de salud (sin API-Key) para el descubrimiento automatico de la IP
// por parte de la app Android: emulador, adb reverse o red WiFi local.
if ($ruta === '/api/v1/ping' || $ruta === '/api/v1/ping.php') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1'
          . DIRECTORY_SEPARATOR . 'ping.php';
    return true;
}

if ($ruta === '/' || $ruta === '/api/v1') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode([
        'servicio' => 'MIDFF API',
        'version'  => '1.3',
        'endpoints' => [
            'POST /api/v1/transacciones' => 'evalua una transaccion (score, clasificacion, decision, alerta y push FCM si score >= 0.4); X-API-Key de ingesta del banco',
            'POST /api/v1/dispositivos'  => 'registra/actualiza el token FCM de la app Android (Bearer: ID token de Firebase; rol fijo CLIENTE, BIT-037)',
            'GET  /api/v1/dispositivos'  => 'lista los dispositivos dados de alta (Bearer: sesion de administrador, BIT-037)',
            'POST /api/v1/bloquear'      => 'bloqueo preventivo de una alerta: ALERTA.estado = BLOQUEADA + AUDITORIA (Bearer: sesion de administrador, BIT-037)',
            'POST /api/v1/reportar'      => 'respuesta del cliente a una alerta: NO_FUI_YO / SI_FUI_YO (Bearer: ID token de Firebase)',
            'POST /api/v1/desbloquear'   => 'desbloqueo por el banco tras SI_FUI_YO del cliente en riesgo ALTO (Bearer: sesion de administrador)',
            'GET  /api/v1/estado'        => 'estado en Oracle de las alertas del cliente autenticado (Bearer: ID token de Firebase)',
            'POST /api/v1/baja'          => 'baja de cuenta: purga dispositivos e historial completo y anonimiza auditoria (Bearer: ID token de Firebase)',
        ],
        'documentacion' => 'docs/contratos/contrato_scoring.md',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return true;
}

return false;

/* Fin del archivo router.php - MIDFF Sprint 2 + Sprint 3 + Sprint 4 (28/09/2026) */
