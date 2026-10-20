--------------------------------------------------------------------------------
-- MIDFF - Realineacion de secuencias contra MAX(id) de cada tabla (BIT-026)
-- Archivo : database/realinear_secuencias.sql
--
-- PROPOSITO: tras una limpieza de pruebas que vacia las tablas de negocio pero
--   conserva AUDITORIA (registro inmutable, RF-08), las secuencias pueden
--   quedar por debajo del MAX(id) existente y la siguiente insercion falla
--   con ORA-00001 (PK_AUDITORIA / PK_ALERTA / PK_TRANSACCION / PK_EVALUACION).
--   Este script es IDEMPOTENTE: deja cada SEQ_* apuntando a MAX(id)+1 y solo
--   reajusta las que esten desalineadas (BIT-021 y BIT-022 documentan las dos
--   recurrencias anteriores; BIT-026 lo blinda de forma permanente).
--
-- USO (desde sqlplus con el usuario del esquema):
--   sqlplus MIDFFuser@localhost:1521/XEPDB1 @database\realinear_secuencias.sql
--
-- Tambien lo ejecuta servidor.ps1 automaticamente al arrancar la demo (paso
-- 4/5). Requiere Oracle 18c+ (ALTER SEQUENCE ... RESTART; XE 21c local OK).
--------------------------------------------------------------------------------
SET SERVEROUTPUT ON SIZE UNLIMITED
SET FEEDBACK OFF

DECLARE
    v_ajustes PLS_INTEGER := 0;

    /* Reajusta p_secuencia para que su proximo NEXTVAL sea MAX(p_columna)+1
       de p_tabla. No hace nada si ya esta alineada; omite (sin fallar) si la
       secuencia o la tabla no existen porque el schema no esta instalado. */
    PROCEDURE realinear(p_secuencia VARCHAR2, p_tabla VARCHAR2, p_columna VARCHAR2) IS
        v_max       PLS_INTEGER;
        v_siguiente PLS_INTEGER;
    BEGIN
        EXECUTE IMMEDIATE 'SELECT NVL(MAX(' || p_columna || '), 0) + 1 FROM ' || p_tabla
            INTO v_max;

        -- Las secuencias MIDFF son NOCACHE: LAST_NUMBER = proximo NEXTVAL.
        SELECT LAST_NUMBER
          INTO v_siguiente
          FROM USER_SEQUENCES
         WHERE SEQUENCE_NAME = UPPER(p_secuencia);

        IF v_siguiente <> v_max THEN
            EXECUTE IMMEDIATE 'ALTER SEQUENCE ' || p_secuencia
                           || ' RESTART START WITH ' || v_max;
            v_ajustes := v_ajustes + 1;
            DBMS_OUTPUT.PUT_LINE(RPAD(p_secuencia, 16) || ' REALINEADA  ' || v_siguiente
                                 || ' -> ' || v_max || '  (MAX(' || p_columna
                                 || ')+1 de ' || p_tabla || ')');
        ELSE
            DBMS_OUTPUT.PUT_LINE(RPAD(p_secuencia, 16) || ' OK          siguiente=' || v_max);
        END IF;
    EXCEPTION
        WHEN NO_DATA_FOUND THEN
            DBMS_OUTPUT.PUT_LINE(RPAD(p_secuencia, 16)
                                 || ' OMITIDA     (secuencia/tabla inexistente: schema sin instalar)');
        WHEN OTHERS THEN
            DBMS_OUTPUT.PUT_LINE(RPAD(p_secuencia, 16) || ' ERROR       ' || SQLERRM);
    END;
BEGIN
    DBMS_OUTPUT.PUT_LINE('=== MIDFF realinear_secuencias.sql (BIT-026) ===');
    realinear('SEQ_TRANSACCION', 'TRANSACCION', 'id_transaccion');
    realinear('SEQ_EVALUACION',  'EVALUACION',  'id_evaluacion');
    realinear('SEQ_ALERTA',      'ALERTA',      'id_alerta');
    realinear('SEQ_AUDITORIA',   'AUDITORIA',   'id_auditoria');
    DBMS_OUTPUT.PUT_LINE('=== Ajustes aplicados: ' || v_ajustes
                         || ' (0 = todo ya estaba alineado) ===');
END;
/
EXIT
