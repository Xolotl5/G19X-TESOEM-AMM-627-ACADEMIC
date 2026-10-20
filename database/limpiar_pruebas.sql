--------------------------------------------------------------------------------
-- MIDFF - Limpieza de datos de prueba para una ronda nueva (BIT-029)
-- Archivo : database/limpiar_pruebas.sql
--
-- PROPOSITO: dejar las interfaces (app, consola MIDFF.html, simulador
--   banco.html) listas para nuevas pruebas:
--     * Vacia las tablas de NEGOCIO: ALERTA, EVALUACION y TRANSACCION.
--     * CONSERVA AUDITORIA (registro inmutable, RF-08: la evidencia historica
--       de las sesiones previas no se toca).
--     * CONSERVA ADMIN_USUARIO (el acceso admin de la consola sigue igual).
--     * CONSERVA DISPOSITIVO_MOVIL (los token FCM siguen activos: el telefono
--       recibe los push de la nueva ronda sin re-registrarse).
--     * Al final REALINEA las secuencias a MAX(id)+1 (lecciones BIT-021/022/
--       026: borrar filas sin ajustar SEQ_* provoca ORA-00001 en la proxima
--       insercion; @@ invoca al script idempotente de BIT-026).
--
-- USO (desde la raiz del repositorio):
--   sqlplus MIDFFuser@localhost:1521/XEPDB1 @database\limpiar_pruebas.sql
--
-- NOTA: la bandeja LOCAL de la app (SharedPreferences del telefono) no depende
--   de esta limpieza: use el boton "Limpiar" de la pestana Perfil de la app.
--------------------------------------------------------------------------------
SET SERVEROUTPUT ON SIZE UNLIMITED
SET FEEDBACK OFF

DECLARE
    v_alertas      PLS_INTEGER;
    v_evaluaciones PLS_INTEGER;
    v_transacciones PLS_INTEGER;
BEGIN
    DELETE FROM ALERTA;
    DELETE FROM EVALUACION;
    DELETE FROM TRANSACCION;
    COMMIT;

    SELECT (SELECT COUNT(*) FROM ALERTA), (SELECT COUNT(*) FROM EVALUACION),
           (SELECT COUNT(*) FROM TRANSACCION)
      INTO v_alertas, v_evaluaciones, v_transacciones
      FROM DUAL;

    DBMS_OUTPUT.PUT_LINE('=== Limpieza de pruebas (BIT-029) ===');
    DBMS_OUTPUT.PUT_LINE('TRANSACCION=' || v_transacciones
                         || '  EVALUACION=' || v_evaluaciones
                         || '  ALERTA=' || v_alertas
                         || '  (0/0/0 = negocio vacio)');
    DBMS_OUTPUT.PUT_LINE('AUDITORIA, ADMIN_USUARIO y DISPOSITIVO_MOVIL conservados');
END;
/

-- Realineacion de secuencias tras el borrado (idempotente, BIT-026).
@@realinear_secuencias.sql
