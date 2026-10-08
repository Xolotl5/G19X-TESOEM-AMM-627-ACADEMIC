<?php
declare(strict_types=1);

/*
  --------------------------------------------------------------------------------
  -- MIDFF - Sondeo de salud para el descubrimiento automatico de la API
  -- Archivo: api/v1/ping.php
  --
  -- GET /api/v1/ping  ->  200 { "servicio": "midff", "estado": "ok", ... }
  --
  -- NO requiere X-API-Key ni conexion a Oracle: es un faro minimo para que la
  -- app Android (DescubrimientoApi) detecte en que IP corre el servidor segun
  -- el modo de ejecucion (emulador 10.0.2.2, USB adb reverse 127.0.0.1 o la
  -- red WiFi local). No expone datos de negocio.
  --------------------------------------------------------------------------------
 */

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'servicio'  => 'midff',
    'componente' => 'api',
    'estado'    => 'ok',
    'timestamp' => date('c'),
]);
