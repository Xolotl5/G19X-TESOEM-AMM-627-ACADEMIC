<?php
declare(strict_types=1);

/*
 --------------------------------------------------------------------------------
 -- MIDFF - Router del servidor embebido de PHP (desarrollo / smoke test)
 -- Archivo: router.php
 --
 -- Uso (desde la raiz del repositorio):
 --     php -S 127.0.0.1:8090 router.php
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

if ($ruta === '/' || $ruta === '/api/v1') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode([
        'servicio' => 'MIDFF API',
        'version'  => '1.0',
        'endpoint' => 'POST /api/v1/transacciones',
        'documentacion' => 'docs/contratos/contrato_scoring.md',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return true;
}

return false;

/* Fin del archivo router.php - MIDFF Sprint 2 (24/09/2026) */
