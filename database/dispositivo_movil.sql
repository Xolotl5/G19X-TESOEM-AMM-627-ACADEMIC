--------------------------------------------------------------------------------
-- MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
-- Componente: tabla DISPOSITIVO_MOVIL para notificaciones push (Sprint 3)
-- Archivo: database/dispositivo_movil.sql
-- Motor de base de datos: Oracle Database XE (11.2 / 18c / 21c)
-- Requisitos: RF-09 (notificacion push FCM) y RF-10 (app Android de alertas).
-- Documento base: MVP_MIDFF.md secciones 3.3 y 7 (paso 7) · BITACORA.md 5.5.
--
-- CONTENIDO
--   1. Limpieza re-ejecutable (tabla y secuencia propias)
--   2. Secuencia SEQ_DISPOSITIVO_MOVIL
--   3. Tabla DISPOSITIVO_MOVIL (id, usuario, rol, id_cliente, token, activo, fechas)
--   4. Triggers de identificador y de fecha de actualizacion
--   5. Comentarios de tabla y columna
--   6. Consulta de control (dispositivos que recibiran el push)
--
-- EJECUCION (SQL*Plus, como MIDFFuser en Oracle XE; COMPLEMENTA schema.sql):
--     sqlplus MIDFFuser@localhost:1521/XEPDB1 @database/dispositivo_movil.sql
--
-- DISENO Y SUPUESTOS DEL SPRINT 3
--   a) El script es RE-EJECUTABLE: borra la tabla y la secuencia y las vuelve
--      a crear (mismo criterio de aceptacion que schema.sql, riesgo R-02).
--   b) Se entrega en un script APARTE para no reescribir database/schema.sql,
--      artefacto congelado del Sprint 1 (decision D-24 de BITACORA.md).
--   c) token_fcm es UNIQUE: el registro desde la app hace UPSERT sobre el
--      token, de modo que una reinstalacion reutiliza su fila.
--   d) Los tokens invalidos reportados por FCM (NotRegistered y similares)
--      reciben BAJA LOGICA (activo = 0): el MVP no elimina registros.
--   e) usuario + rol identifican al usuario (cliente u oficial) y id_cliente
--      vincula el dispositivo al cliente cuyas transacciones debe recibir
--      (BIT-017: filtro del push por cliente; instalaciones ya creadas usan
--      database/dispositivo_movil_id_cliente.sql para agregar la columna).
--------------------------------------------------------------------------------

SET DEFINE OFF
SET SERVEROUTPUT ON SIZE UNLIMITED

--------------------------------------------------------------------------------
-- 1. LIMPIEZA RE-EJECUTABLE
--------------------------------------------------------------------------------
DECLARE
    e_tabla_inexistente     EXCEPTION;
    PRAGMA EXCEPTION_INIT(e_tabla_inexistente, -942);      -- ORA-00942
    e_secuencia_inexistente EXCEPTION;
    PRAGMA EXCEPTION_INIT(e_secuencia_inexistente, -2289); -- ORA-02289
BEGIN
    BEGIN EXECUTE IMMEDIATE 'DROP TABLE DISPOSITIVO_MOVIL CASCADE CONSTRAINTS'; EXCEPTION WHEN e_tabla_inexistente THEN NULL; END;
    BEGIN EXECUTE IMMEDIATE 'DROP SEQUENCE SEQ_DISPOSITIVO_MOVIL';             EXCEPTION WHEN e_secuencia_inexistente THEN NULL; END;

    DBMS_OUTPUT.PUT_LINE('[1/6] Esquema anterior eliminado (o inexistente).');
END;
/

--------------------------------------------------------------------------------
-- 2. SECUENCIA DE IDENTIFICADORES
--------------------------------------------------------------------------------
CREATE SEQUENCE SEQ_DISPOSITIVO_MOVIL START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

PROMPT [2/6] Secuencia SEQ_DISPOSITIVO_MOVIL creada.

--------------------------------------------------------------------------------
-- 3. TABLA DISPOSITIVO_MOVIL (MVP_MIDFF.md seccion 3.3: "Sprint 3")
--------------------------------------------------------------------------------
CREATE TABLE DISPOSITIVO_MOVIL (
    id_dispositivo      NUMBER(18)               NOT NULL,
    usuario             VARCHAR2(60)             NOT NULL,
    rol                 VARCHAR2(30)             DEFAULT 'OFICIAL' NOT NULL,
    id_cliente          VARCHAR2(40)             DEFAULT NULL NULL,
    token_fcm           VARCHAR2(255)            NOT NULL,
    activo              NUMBER(1)                DEFAULT 1 NOT NULL,
    fecha_registro      TIMESTAMP WITH TIME ZONE DEFAULT SYSTIMESTAMP NOT NULL,
    fecha_actualizacion TIMESTAMP WITH TIME ZONE DEFAULT SYSTIMESTAMP NOT NULL,
    CONSTRAINT pk_dispositivo_movil      PRIMARY KEY (id_dispositivo),
    CONSTRAINT uq_dispositivo_movil_tok  UNIQUE (token_fcm),
    CONSTRAINT ck_dispositivo_movil_act  CHECK (activo IN (0, 1)),
    CONSTRAINT ck_dispositivo_movil_rol  CHECK (rol IN ('OFICIAL', 'SUPERVISOR', 'ADMIN', 'CLIENTE')),
    CONSTRAINT ck_dispositivo_movil_long CHECK (LENGTH(token_fcm) >= 64)
);

PROMPT [3/6] Tabla DISPOSITIVO_MOVIL creada con sus restricciones.
PROMPT [4/6] Indice unico sobre token_fcm creado (heredado de la restriccion).

--------------------------------------------------------------------------------
-- 4. TRIGGERS: identificador automatico y fecha de actualizacion
--------------------------------------------------------------------------------
CREATE OR REPLACE TRIGGER TRG_DISPOSITIVO_MOVIL_ID
BEFORE INSERT ON DISPOSITIVO_MOVIL
FOR EACH ROW
BEGIN
    IF :NEW.id_dispositivo IS NULL THEN
        SELECT SEQ_DISPOSITIVO_MOVIL.NEXTVAL INTO :NEW.id_dispositivo FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER TRG_DISPOSITIVO_MOVIL_UPD
BEFORE UPDATE ON DISPOSITIVO_MOVIL
FOR EACH ROW
BEGIN
    :NEW.fecha_actualizacion := SYSTIMESTAMP;
END;
/

PROMPT [5/6] Triggers TRG_DISPOSITIVO_MOVIL_ID y TRG_DISPOSITIVO_MOVIL_UPD creados.

--------------------------------------------------------------------------------
-- 5. COMENTARIOS DE TABLA Y COLUMNA
--------------------------------------------------------------------------------
COMMENT ON TABLE  DISPOSITIVO_MOVIL                 IS 'Dispositivos Android registrados para recibir alertas push via FCM cuando el score >= 0.4 (Sprint 3, RF-09/RF-10)';
COMMENT ON COLUMN DISPOSITIVO_MOVIL.id_dispositivo  IS 'Identificador consecutivo (SEQ_DISPOSITIVO_MOVIL)';
COMMENT ON COLUMN DISPOSITIVO_MOVIL.usuario         IS 'Identificador del usuario (cliente u oficial) registrado desde la app';
COMMENT ON COLUMN DISPOSITIVO_MOVIL.rol             IS 'Rol del usuario: CLIENTE, OFICIAL, SUPERVISOR o ADMIN';
COMMENT ON COLUMN DISPOSITIVO_MOVIL.id_cliente      IS 'Cliente dueno del dispositivo: el push de una transaccion llega SOLO a los dispositivos con su id_cliente (BIT-017)';
COMMENT ON COLUMN DISPOSITIVO_MOVIL.token_fcm       IS 'Token FCM del dispositivo; unico, se reutiliza al reinstalar la app';
COMMENT ON COLUMN DISPOSITIVO_MOVIL.activo          IS '1 = recibe notificaciones; 0 = baja logica (token invalido segun FCM)';
COMMENT ON COLUMN DISPOSITIVO_MOVIL.fecha_registro  IS 'Alta del dispositivo en la bandeja de notificacion';

--------------------------------------------------------------------------------
-- 6. CONSULTA DE CONTROL: dispositivos que recibiran el push (RF-09)
--------------------------------------------------------------------------------
 SELECT id_dispositivo, usuario, rol, id_cliente, activo,
        TO_CHAR(fecha_registro, 'YYYY-MM-DD HH24:MI:SS') AS registrado,
        SUBSTR(token_fcm, -6)                            AS token_final
   FROM DISPOSITIVO_MOVIL ORDER BY id_dispositivo;

PROMPT ========================================================================
PROMPT  Tabla DISPOSITIVO_MOVIL (Sprint 3) instalada: el endpoint
PROMPT  POST /api/v1/dispositivos registra tokens con su id_cliente y la API
PROMPT  envia el push FCM SOLO a los dispositivos activos cuyo id_cliente
PROMPT  coincida con el de la transaccion (score >= 0.4, BIT-017).
PROMPT ========================================================================
SET DEFINE ON

SELECT id_dispositivo, usuario, rol, id_cliente, activo, fecha_registro
  FROM DISPOSITIVO_MOVIL;
-- Fin del archivo dispositivo_movil.sql - MIDFF Sprint 3 (24/09/2026)

