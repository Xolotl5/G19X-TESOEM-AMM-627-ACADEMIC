<?php
declare(strict_types=1);

/*
 --------------------------------------------------------------------------------
 -- MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
 -- Componente: configuracion y acceso a Oracle Database XE (Sprint 2)
 -- Archivo: config/db.php
 --
 -- Requisitos: RF-07 (persistencia ACID), RF-08 (auditoria de cada evaluacion),
 --             RNF-05 (integridad: transaccion + evaluacion + alerta + auditoria
 --             en una sola unidad atomica) y RNF-03 (credenciales fuera del codigo).
 --
 -- CONTENIDO
 --   1. Placeholders de conexion (sobreescribibles por variables de entorno)
 --   2. Seleccion de driver: PDO_OCI (preferido) con respaldo OCI8
 --   3. midff_db_conectar()              -> conexion PDO via pdo_oci
 --   4. midff_db_registrar_transaccion() -> invoca SP_REGISTRAR_TRANSACCION
 --      (database/schema.sql) y confirma la transaccion (COMMIT). Ante cualquier
 --      error hace ROLLBACK y devuelve el mensaje ORA-xxxxx al llamador.
 --
 -- VARIABLES DE ENTORNO RECONOCIDAS (produccion; jamas versionar credenciales):
 --   MIDFF_DB_USUARIO   (default: MIDFFuser)
 --   MIDFF_DB_CLAVE     (default: midffadmin)
 --   MIDFF_DB_HOST      (default: localhost)
 --   MIDFF_DB_PUERTO    (default: 1521)
 --   MIDFF_DB_SERVICIO  (default: XEPDB1)
 --
 -- NOTA: el usuario, la contrasena y el SID son PLACEHOLDERS del entorno local
 --       de desarrollo (Oracle XE 21c). En otro entorno se definen con las
 --       variables de entorno anteriores sin editar este archivo.
 --------------------------------------------------------------------------------
 */

/*
 * 1. PLACEHOLDERS DE CONEXION (se resuelven una sola vez al incluir el archivo).
 *    getenv() permite inyectar las credenciales reales sin tocar el codigo,
 *    cumpliendo la convencion de secretos de BITACORA.md seccion 4.2.
 */
define('MIDFF_DB_USUARIO',  getenv('MIDFF_DB_USUARIO')  ?: 'MIDFFuser');
define('MIDFF_DB_CLAVE',    getenv('MIDFF_DB_CLAVE')    ?: 'midffadmin');
define('MIDFF_DB_HOST',     getenv('MIDFF_DB_HOST')     ?: 'localhost');
define('MIDFF_DB_PUERTO',   getenv('MIDFF_DB_PUERTO')   ?: '1521');
define('MIDFF_DB_SERVICIO', getenv('MIDFF_DB_SERVICIO') ?: 'XEPDB1');
define('MIDFF_DB_CHARSET',  'AL32UTF8');

/**
 * Devuelve el driver Oracle disponible en esta instalacion de PHP:
 * 'pdo_oci' (preferido), 'oci8' (respaldo) o '' (ninguno).
 */
function midff_db_driver_disponible(): string
{
    if (extension_loaded('pdo_oci')) {
        return 'pdo_oci';
    }
    if (extension_loaded('oci8')) {
        return 'oci8';
    }
    return '';
}

/**
 * Cadena de conexion estilo EZConnect: //host:puerto/servicio
 */
function midff_db_cadena_conexion(): string
{
    return '//' . MIDFF_DB_HOST . ':' . MIDFF_DB_PUERTO . '/' . MIDFF_DB_SERVICIO;
}

/*
 * 2. CONEXION VIA PDO_OCI (driver preferido).
 *    Errores por excepcion para que el endpoint pueda traducirlas a HTTP 500.
 */
function midff_db_conectar(): PDO
{
    $dsn = 'oci:dbname=' . midff_db_cadena_conexion() . ';charset=' . MIDFF_DB_CHARSET;

    return new PDO($dsn, MIDFF_DB_USUARIO, MIDFF_DB_CLAVE, [
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_CASE              => PDO::CASE_UPPER,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_PERSISTENT        => false,
    ]);
}


/*
 * 3. PERSISTENCIA ATOMICA DEL CICLO COMPLETO (RF-07 / RNF-05 / HU-MVP-04).
 *
 *    Invoca al procedimiento almacenado SP_REGISTRAR_TRANSACCION creado por
 *    database/schema.sql, que inserta en una sola unidad de trabajo:
 *      TRANSACCION + EVALUACION + (ALERTA si score >= 0.4) + AUDITORIA
 *    y NO ejecuta COMMIT (decision D-13): esta funcion confirma o revierte.
 *
 *    Claves esperadas de $datos (ver contrato_scoring.md):
 *      uid_transaccion, id_cliente, monto, origen, fecha_hora (ISO 8601 con
 *      offset +/-HH:MM), score, clasificacion, decision, moneda, canal,
 *      ubicacion, ip_origen, puntaje_monto, puntaje_frecuencia,
 *      puntaje_ubicacion_ip, variables_usadas (JSON), latencia_us,
 *      datos_faltantes, usuario_sistema.
 *
 *    Devuelve: ['ok' => bool, 'mensaje' => string,
 *               'id_transaccion' => ?int, 'id_evaluacion' => ?int,
 *               'id_alerta' => ?int]
 */
function midff_db_registrar_transaccion(array $datos): array
{
    $sql = "BEGIN\n"
         . "  SP_REGISTRAR_TRANSACCION(\n"
         . "    p_uid_transaccion      => :uid_transaccion,\n"
         . "    p_id_cliente           => :id_cliente,\n"
         . "    p_monto                => :monto,\n"
         . "    p_origen               => :origen,\n"
         . "    p_fecha_hora           => TO_TIMESTAMP_TZ(:fecha_hora,\n"
         . "                             'YYYY-MM-DD\"T\"HH24:MI:SSTZH:TZM'),\n"
         . "    p_score                => :score,\n"
         . "    p_clasificacion        => :clasificacion,\n"
         . "    p_decision             => :decision,\n"
         . "    p_moneda               => :moneda,\n"
         . "    p_canal                => :canal,\n"
         . "    p_ubicacion            => :ubicacion,\n"
         . "    p_ip_origen            => :ip_origen,\n"
         . "    p_puntaje_monto        => :puntaje_monto,\n"
         . "    p_puntaje_frecuencia   => :puntaje_frecuencia,\n"
         . "    p_puntaje_ubicacion_ip => :puntaje_ubicacion_ip,\n"
         . "    p_variables_usadas     => :variables_usadas,\n"
         . "    p_latencia_us          => :latencia_us,\n"
         . "    p_datos_faltantes      => :datos_faltantes,\n"
         . "    p_usuario_sistema      => :usuario_sistema,\n"
         . "    p_id_transaccion       => :id_transaccion,\n"
         . "    p_id_evaluacion        => :id_evaluacion,\n"
         . "    p_id_alerta            => :id_alerta\n"
         . "  );\n"
         . "END;";

    // Valores de entrada como cadenas: Oracle convierte implicitamente a NUMBER.
    $entradas = [
        'uid_transaccion'      => [$datos['uid_transaccion']      ?? null, 40],
        'id_cliente'           => [$datos['id_cliente']           ?? null, 40],
        'monto'                => [$datos['monto']                ?? null, 32],
        'origen'               => [$datos['origen']               ?? null, 20],
        'fecha_hora'           => [$datos['fecha_hora']           ?? null, 64],
        'score'                => [$datos['score']                ?? null, 16],
        'clasificacion'        => [$datos['clasificacion']        ?? null, 10],
        'decision'             => [$datos['decision']             ?? null, 12],
        'moneda'               => [$datos['moneda']               ?? 'MXN', 3],
        'canal'                => [$datos['canal']                ?? null, 20],
        'ubicacion'            => [$datos['ubicacion']            ?? null, 80],
        'ip_origen'            => [$datos['ip_origen']            ?? null, 45],
        'puntaje_monto'        => [$datos['puntaje_monto']        ?? null, 16],
        'puntaje_frecuencia'   => [$datos['puntaje_frecuencia']   ?? null, 16],
        'puntaje_ubicacion_ip' => [$datos['puntaje_ubicacion_ip'] ?? null, 16],
        'variables_usadas'     => [$datos['variables_usadas']     ?? null, 32767],
        'latencia_us'          => [$datos['latencia_us']          ?? null, 24],
        'datos_faltantes'      => [$datos['datos_faltantes']      ?? null, 400],
        'usuario_sistema'      => $datos['usuario_sistema'] ?? 'API-MIDFF',
    ];

    $idTransaccion = 0;
    $idEvaluacion  = 0;
    $idAlerta      = 0;
    $vacio = ['id_transaccion' => null, 'id_evaluacion' => null, 'id_alerta' => null];


    $driver = midff_db_driver_disponible();
    if ($driver === '') {
        return ['ok' => false,
                'mensaje' => 'PHP no tiene cargado el driver de Oracle (pdo_oci ni oci8). Revise php.ini.'
                ] + $vacio;
    }

    if ($driver === 'pdo_oci') {
        // ------------------------- Ruta PDO_OCI (preferida) -----------------
        $cnx = null;
        try {
            $cnx  = midff_db_conectar();
            $sent = $cnx->prepare($sql);

            foreach ($entradas as $nombre => $def) {
                $sent->bindValue(':' . $nombre,
                                 $def[0] === null ? null : (string) $def[0], PDO::PARAM_STR);
            }
            // Parametros de salida (ids generados por las secuencias del SP).
            $sent->bindParam(':id_transaccion', $idTransaccion,
                             PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 38);
            $sent->bindParam(':id_evaluacion',  $idEvaluacion,
                             PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 38);
            $sent->bindParam(':id_alerta',      $idAlerta,
                             PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 38);

            $cnx->beginTransaction();
            $sent->execute();
            $cnx->commit();   // D-13: el SP no confirma; la API cierra la unidad atomica.
        } catch (PDOException $e) {
            if ($cnx !== null && $cnx->inTransaction()) {
                $cnx->rollBack();
            }
            return ['ok' => false, 'mensaje' => trim($e->getMessage())] + $vacio;
        }

        return ['ok' => true, 'mensaje' => '',
                'id_transaccion' => (int) $idTransaccion,
                'id_evaluacion'  => (int) $idEvaluacion,
                'id_alerta'      => $idAlerta > 0 ? (int) $idAlerta : null];
    }

    // --------------------------- Ruta OCI8 (respaldo) -----------------------
    $cnx = oci_connect(MIDFF_DB_USUARIO, MIDFF_DB_CLAVE,
                       midff_db_cadena_conexion(), MIDFF_DB_CHARSET);
    if ($cnx === false) {
        $e = oci_error();
        return ['ok' => false, 'mensaje' => $e['message'] ?? 'oci_connect fallo'] + $vacio;
    }

    $sent = oci_parse($cnx, $sql);
    if ($sent === false) {
        $e   = oci_error($cnx);
        $msj = trim($e['message'] ?? 'oci_parse fallo');
        oci_rollback($cnx);
        oci_close($cnx);
        return ['ok' => false, 'mensaje' => $msj] + $vacio;
    }

    foreach ($entradas as $nombre => $def) {
        $valor = $def[0] === null ? '' : (string) $def[0];
        oci_bind_by_name($sent, ':' . $nombre, $valor, max(1, (int) $def[1]), SQLT_CHR);
    }
    oci_bind_by_name($sent, ':id_transaccion', $idTransaccion, 38, SQLT_INT);
    oci_bind_by_name($sent, ':id_evaluacion',  $idEvaluacion,  38, SQLT_INT);
    oci_bind_by_name($sent, ':id_alerta',      $idAlerta,      38, SQLT_INT);

    if (!oci_execute($sent, OCI_NO_AUTO_COMMIT)) {
        $e   = oci_error($sent);
        $msj = trim($e['message'] ?? 'oci_execute fallo');
        oci_rollback($cnx);   // RNF-05: nada queda a medias.
        oci_free_statement($sent);
        oci_close($cnx);
        return ['ok' => false, 'mensaje' => $msj] + $vacio;
    }

    oci_commit($cnx);         // D-13: confirma la unidad atomica completa.
    oci_free_statement($sent);
    oci_close($cnx);

    return ['ok' => true, 'mensaje' => '',
            'id_transaccion' => (int) $idTransaccion,
            'id_evaluacion'  => (int) $idEvaluacion,
            'id_alerta'      => $idAlerta > 0 ? (int) $idAlerta : null];
}

/* Fin del archivo config/db.php - MIDFF Sprint 2 (24/09/2026) */

