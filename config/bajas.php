<?php
declare(strict_types=1);

/*
  --------------------------------------------------------------------------------
  -- MIDFF - Purga de cuenta de cliente (BIT-030/031)
  -- Archivo : config/bajas.php
  --
  -- Transaccion atomica (RNF-05) que elimina a un cliente DE TODOS LADOS:
  --   DISPOSITIVO_MOVIL (tokens FCM) -> ALERTA -> EVALUACION -> TRANSACCION
  --   (historial completo), dejando UN evento en AUDITORIA. La usan:
  --     * api/v1/baja.php               (la app, al detectar la cuenta borrada)
  --     * api/v1/admin.php sincronizar-  (el banco, verificando uids contra
  --       bajas                           Firebase sin abrir la app, BIT-031)
  --
  -- AUDITORIA no se toca (inmutable, RF-08 + TRG_AUDITORIA_INMUTABLE, D-57)
  -- y el evento se registra como ALERTA/ATENCION 'BANCO:BAJA' por los
  -- CK_AUDITORIA_* congelados (D-58). Funcion pura de datos: NO responde HTTP.
  --------------------------------------------------------------------------------
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';

/**
 * Purga al cliente indicado. Devuelve ['ok', 'dispositivos', 'alertas',
 * 'evaluaciones', 'transacciones', 'id_auditoria', 'error'].
 */
function midff_baja_purgar_cliente(string $idCliente): array
{
    $r = ['ok' => false, 'dispositivos' => 0, 'alertas' => 0, 'evaluaciones' => 0,
          'transacciones' => 0, 'id_auditoria' => null, 'error' => ''];

    $cnx = null;
    try {
        $cnx = midff_db_conectar();
        $cnx->beginTransaction();

        // 1. Tokens FCM: el dispositivo deja de recibir push de inmediato.
        $del = $cnx->prepare('DELETE FROM DISPOSITIVO_MOVIL WHERE id_cliente = :c');
        $del->execute([':c' => $idCliente]);
        $r['dispositivos'] = $del->rowCount();

        // 2. Alertas de las transacciones del cliente (incluye sus reportes).
        $del = $cnx->prepare('DELETE FROM ALERTA WHERE id_evaluacion IN ('
                           . ' SELECT e.id_evaluacion FROM EVALUACION e'
                           . ' JOIN TRANSACCION t ON t.id_transaccion = e.id_transaccion'
                           . ' WHERE t.id_cliente = :c)');
        $del->execute([':c' => $idCliente]);
        $r['alertas'] = $del->rowCount();

        // 3. Evaluaciones del motor.
        $del = $cnx->prepare('DELETE FROM EVALUACION WHERE id_transaccion IN ('
                           . ' SELECT id_transaccion FROM TRANSACCION WHERE id_cliente = :c)');
        $del->execute([':c' => $idCliente]);
        $r['evaluaciones'] = $del->rowCount();

        // 4. Historial completo de transacciones.
        $del = $cnx->prepare('DELETE FROM TRANSACCION WHERE id_cliente = :c');
        $del->execute([':c' => $idCliente]);
        $r['transacciones'] = $del->rowCount();

        // 5. AUDITORIA (inmutable, RF-08): NO se toca; el seudonimo
        //    CLIENTE:<id> deja de resolver a nadie al borrarse la cuenta de
        //    Firebase (D-57, verificado: el trigger rechaza UPDATE con ORA-20010).

        // 6. Registro del evento como ATENCION del banco (CHECKs congelados,
        //    misma convencion que reportar/desbloquear: D-55/D-58).
        $huboBaja = ($r['dispositivos'] + $r['alertas'] + $r['evaluaciones']
                     + $r['transacciones']) > 0;
        if ($huboBaja) {
            $cnx->prepare('INSERT INTO AUDITORIA (id_auditoria, entidad, accion, usuario_o_sistema, detalle)'
                        . " VALUES (SEQ_AUDITORIA.NEXTVAL, 'ALERTA', 'ATENCION', 'BANCO:BAJA', :detalle)")
                ->execute([':detalle' => sprintf(
                    'Baja de cuenta (eliminada de Firebase): %d dispositivo(s), %d transaccion(es),'
                    . ' %d evaluacion(es) y %d alerta(s) purgadas; auditoria historica conservada (inmutable)',
                    $r['dispositivos'], $r['transacciones'], $r['evaluaciones'], $r['alertas']
                )]);
        }

        $cnx->commit();

        if ($huboBaja) {
            $r['id_auditoria'] = (int) $cnx->query('SELECT SEQ_AUDITORIA.CURRVAL FROM DUAL')->fetchColumn();
        }
        $r['ok'] = true;
    } catch (PDOException $e) {
        if ($cnx !== null && $cnx->inTransaction()) {
            $cnx->rollBack();
        }
        $r['error'] = trim($e->getMessage());
    }
    return $r;
}

/* Fin del archivo config/bajas.php - MIDFF Post-MVP (BIT-030/031, 12/10/2026) */