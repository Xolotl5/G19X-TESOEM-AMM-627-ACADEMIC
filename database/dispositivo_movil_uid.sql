--------------------------------------------------------------------------------
-- MIDFF - Migration: uid de Firebase en DISPOSITIVO_MOVIL (BIT-031)
-- Archivo : database/dispositivo_movil_uid.sql
--
-- PROPOSITO: la purga de cuentas borradas (BIT-030) dependia de que la app
--   se abriera (reload() -> FirebaseAuthInvalidUserException). Con el uid
--   almacenado, el banco puede verificar contra Firebase Auth
--   (Identity Toolkit accounts:lookup, POST /api/v1/admin/sincronizar-bajas)
--   que cuentas siguen vivas y purgar las borradas SIN abrir la app.
--
-- Nota: el id_cliente (CLI-XXXXXX, D-50) deriva del uid pero el mapeo no es
--   reversible (mayusculas + solo alfanumericos), por eso se guarda el uid
--   completo. Es dato no identificable por si solo (igual que el uid).
--
-- Idempotente: si la columna ya existe, no hace nada.
--------------------------------------------------------------------------------
SET SERVEROUTPUT ON
SET FEEDBACK OFF

DECLARE
    v_existe PLS_INTEGER;
BEGIN
    SELECT COUNT(*) INTO v_existe FROM user_tab_columns
     WHERE table_name = 'DISPOSITIVO_MOVIL' AND column_name = 'UID_FIREBASE';

    IF v_existe = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE DISPOSITIVO_MOVIL ADD (uid_firebase VARCHAR2(128))';
        EXECUTE IMMEDIATE 'COMMENT ON COLUMN DISPOSITIVO_MOVIL.uid_firebase IS '
            || '''uid de la cuenta Firebase Auth (BIT-031): permite detectar cuentas borradas sin abrir la app''';
        DBMS_OUTPUT.PUT_LINE('DISPOSITIVO_MOVIL.UID_FIREBASE agregada (VARCHAR2(128))');
    ELSE
        DBMS_OUTPUT.PUT_LINE('UID_FIREBASE ya existia: sin cambios');
    END IF;
END;
/
EXIT
