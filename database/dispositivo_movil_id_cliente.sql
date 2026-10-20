--------------------------------------------------------------------------------
-- MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
-- Componente: migracion de DISPOSITIVO_MOVIL al enfoque CLIENTE (Post-MVP)
-- Archivo: database/dispositivo_movil_id_cliente.sql
-- Motor de base de datos: Oracle Database XE (11.2 / 18c / 21c)
-- Requisitos: BIT-017 (push filtrado por cliente) sobre una tabla ya creada
--             por database/dispositivo_movil.sql (Sprint 3).
--
-- CONTENIDO
--   1. Columna ID_CLIENTE (VARCHAR2(40), NULL): cliente dueno del dispositivo
--   2. CHECK de rol ampliado con 'CLIENTE' (la app se registra con ese rol)
--   3. Indice IX_DISPOSITIVO_MOVIL_CLI (id_cliente, activo) para el filtro
--
-- EJECUCION (SQL*Plus, como MIDFFuser en Oracle XE; NO borra datos y es
-- RE-EJECUTABLE, se puede lanzar todas las veces que se necesite):
--     sqlplus MIDFFuser@localhost:1521/XEPDB1 @database/dispositivo_movil_id_cliente.sql
--
-- DISENO Y SUPUESTOS
--   a) NO recrea la tabla: los tokens ya registrados se conservan y quedan
--      con id_cliente NULL (reciben pushes solo en modo broadcast legado).
--   b) El filtro del push vive en config/firebase.php
--      (midff_fcm_dispositivos_activos): con id_cliente vacio se mantiene el
--      broadcast historico (modo oficial/demo), con id_cliente se entrega
--      SOLO al dueno de la transaccion.
--------------------------------------------------------------------------------

SET DEFINE OFF
SET SERVEROUTPUT ON SIZE UNLIMITED

--------------------------------------------------------------------------------
-- 1. COLUMNA ID_CLIENTE (solo si no existe)
--------------------------------------------------------------------------------
DECLARE
    n NUMBER;
BEGIN
    SELECT COUNT(*) INTO n FROM user_tab_columns
     WHERE table_name = 'DISPOSITIVO_MOVIL' AND column_name = 'ID_CLIENTE';

    IF n = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE DISPOSITIVO_MOVIL ADD (id_cliente VARCHAR2(40) DEFAULT NULL NULL)';
        DBMS_OUTPUT.PUT_LINE('[1/3] Columna ID_CLIENTE agregada a DISPOSITIVO_MOVIL.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('[1/3] La columna ID_CLIENTE ya existe (nada que hacer).');
    END IF;
END;
/

--------------------------------------------------------------------------------
-- 2. CHECK DE ROL AMPLIADO CON 'CLIENTE' (se reemplaza siempre: idempotente)
--------------------------------------------------------------------------------
DECLARE
    e_constraint_inexistente EXCEPTION;
    PRAGMA EXCEPTION_INIT(e_constraint_inexistente, -2443);   -- ORA-02443
BEGIN
    BEGIN
        EXECUTE IMMEDIATE 'ALTER TABLE DISPOSITIVO_MOVIL DROP CONSTRAINT CK_DISPOSITIVO_MOVIL_ROL';
    EXCEPTION
        WHEN e_constraint_inexistente THEN NULL;   -- no existia: instalacion parcial
    END;

    EXECUTE IMMEDIATE 'ALTER TABLE DISPOSITIVO_MOVIL ADD CONSTRAINT CK_DISPOSITIVO_MOVIL_ROL '
                    || 'CHECK (rol IN (''OFICIAL'', ''SUPERVISOR'', ''ADMIN'', ''CLIENTE''))';
    DBMS_OUTPUT.PUT_LINE('[2/3] CHECK de rol ampliado: OFICIAL | SUPERVISOR | ADMIN | CLIENTE.');
END;
/

--------------------------------------------------------------------------------
-- 3. INDICE PARA EL FILTRO DEL PUSH POR CLIENTE (solo si no existe)
--------------------------------------------------------------------------------
DECLARE
    n NUMBER;
BEGIN
    SELECT COUNT(*) INTO n FROM user_indexes
     WHERE index_name = 'IX_DISPOSITIVO_MOVIL_CLI';

    IF n = 0 THEN
        EXECUTE IMMEDIATE 'CREATE INDEX IX_DISPOSITIVO_MOVIL_CLI ON DISPOSITIVO_MOVIL (id_cliente, activo)';
        DBMS_OUTPUT.PUT_LINE('[3/3] Indice IX_DISPOSITIVO_MOVIL_CLI creado.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('[3/3] El indice IX_DISPOSITIVO_MOVIL_CLI ya existe (nada que hacer).');
    END IF;
END;
/

COMMENT ON COLUMN DISPOSITIVO_MOVIL.id_cliente IS 'Cliente dueno del dispositivo: el push de una transaccion llega SOLO a los dispositivos con su id_cliente (BIT-017)';

PROMPT Consulta de control: dispositivos con su cliente vinculado.
 SELECT id_dispositivo, usuario, rol, id_cliente, activo,
        SUBSTR(token_fcm, -6) AS token_final
   FROM DISPOSITIVO_MOVIL ORDER BY id_dispositivo;

SET DEFINE ON
-- Fin del archivo database/dispositivo_movil_id_cliente.sql - MIDFF Post-MVP (06/10/2026)
