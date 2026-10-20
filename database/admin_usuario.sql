--------------------------------------------------------------------------------
-- MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
-- Componente: tabla ADMIN_USUARIO para la consola del banco (Post-MVP, BIT-019)
-- Archivo: database/admin_usuario.sql
-- Motor de base de datos: Oracle Database XE (11.2 / 18c / 21c)
--
-- CONTENIDO
--   1. Limpieza re-ejecutable (tabla y secuencia propias)
--   2. Secuencia SEQ_ADMIN_USUARIO
--   3. Tabla ADMIN_USUARIO (usuario, nombre, password_hash, clave_temporal...)
--   4. Triggers de identificador y de fecha de actualizacion
--   5. Comentarios y usuario semilla de desarrollo
--
-- EJECUCION (SQL*Plus, como MIDFFuser en Oracle XE; COMPLEMENTA schema.sql):
--   sqlplus MIDFFuser@localhost:1521/XEPDB1 @database/admin_usuario.sql
--   (la contrasena de MIDFFuser la pide SQL*Plus al conectar; BIT-037: ninguna
--    credencial viaja escrita en este repositorio)
--
-- DISENO Y SUPUESTOS
--   a) Re-ejecutable: borra y recrea tabla/secuencia (mismo criterio que
--      dispositivo_movil.sql, riesgo R-02).
--   b) password_hash guarda bcrypt de PHP (password_hash/password_verify);
--      clave_temporal es la clave de arranque en claro SOLO para desarrollo:
--      al primer inicio de sesion la consola exige cambiarla y queda en NULL.
--   c) El token de sesion es HMAC-SHA256 sin estado (lo firma api/v1/admin.php
--      con MIDFF_ADMIN_SECRETO); esta tabla solo autentica identidad.
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
    BEGIN EXECUTE IMMEDIATE 'DROP TABLE ADMIN_USUARIO CASCADE CONSTRAINTS'; EXCEPTION WHEN e_tabla_inexistente THEN NULL; END;
    BEGIN EXECUTE IMMEDIATE 'DROP SEQUENCE SEQ_ADMIN_USUARIO';               EXCEPTION WHEN e_secuencia_inexistente THEN NULL; END;
    DBMS_OUTPUT.PUT_LINE('[1/5] Esquema anterior eliminado (o inexistente).');
END;
/

--------------------------------------------------------------------------------
-- 2. SECUENCIA DE IDENTIFICADORES
--------------------------------------------------------------------------------
CREATE SEQUENCE SEQ_ADMIN_USUARIO START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

PROMPT [2/5] Secuencia SEQ_ADMIN_USUARIO creada.

--------------------------------------------------------------------------------
-- 3. TABLA ADMIN_USUARIO (acceso a la consola MIDFF.html)
--------------------------------------------------------------------------------
CREATE TABLE ADMIN_USUARIO (
    id_admin            NUMBER(18)               NOT NULL,
    usuario             VARCHAR2(60)             NOT NULL,
    nombre              VARCHAR2(120),
    password_hash       VARCHAR2(255),
    clave_temporal      VARCHAR2(60),
    rol                 VARCHAR2(30)             DEFAULT 'ADMIN' NOT NULL,
    activo              NUMBER(1)                DEFAULT 1 NOT NULL,
    fecha_registro      TIMESTAMP WITH TIME ZONE DEFAULT SYSTIMESTAMP NOT NULL,
    fecha_actualizacion TIMESTAMP WITH TIME ZONE DEFAULT SYSTIMESTAMP NOT NULL,
    CONSTRAINT pk_admin_usuario       PRIMARY KEY (id_admin),
    CONSTRAINT uq_admin_usuario_login UNIQUE (usuario),
    CONSTRAINT ck_admin_usuario_act   CHECK (activo IN (0, 1)),
    CONSTRAINT ck_admin_usuario_rol   CHECK (rol IN ('ADMIN', 'OFICIAL', 'SUPERVISOR'))
);

PROMPT [3/5] Tabla ADMIN_USUARIO creada con sus restricciones.

--------------------------------------------------------------------------------
-- 4. TRIGGERS: identificador automatico y fecha de actualizacion
--------------------------------------------------------------------------------
CREATE OR REPLACE TRIGGER TRG_ADMIN_USUARIO_ID
BEFORE INSERT ON ADMIN_USUARIO
FOR EACH ROW
BEGIN
    IF :NEW.id_admin IS NULL THEN
        SELECT SEQ_ADMIN_USUARIO.NEXTVAL INTO :NEW.id_admin FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER TRG_ADMIN_USUARIO_UPD
BEFORE UPDATE ON ADMIN_USUARIO
FOR EACH ROW
BEGIN
    :NEW.fecha_actualizacion := SYSTIMESTAMP;
END;
/

PROMPT [4/5] Triggers TRG_ADMIN_USUARIO_ID y TRG_ADMIN_USUARIO_UPD creados.

--------------------------------------------------------------------------------
-- 5. COMENTARIOS + USUARIO SEMILLA DE DESARROLLO (BIT-037: la clave temporal
--    LA DEFINE QUIEN EJECUTA ESTE SCRIPT; no hay contrasena en el repositorio
--    y la consola exigira cambiarla al primer inicio de sesion)
--------------------------------------------------------------------------------
COMMENT ON TABLE  ADMIN_USUARIO                IS 'Usuarios del banco con acceso a la consola MIDFF.html (BIT-019)';
COMMENT ON COLUMN ADMIN_USUARIO.password_hash  IS 'bcrypt (password_hash de PHP); NULL mientras la clave temporal este vigente';
COMMENT ON COLUMN ADMIN_USUARIO.clave_temporal IS 'Clave de arranque en claro SOLO para desarrollo; se limpia al cambiarla';

SET DEFINE ON
SET VERIFY OFF
ACCEPT CLAVE_SEMILLA CHAR PROMPT 'Clave temporal para el usuario admin de la consola (minimo 8 caracteres; se pedira cambiarla al primer inicio): '

INSERT INTO ADMIN_USUARIO (usuario, nombre, clave_temporal, rol, activo)
VALUES ('admin', 'Administrador MIDFF', '&CLAVE_SEMILLA', 'ADMIN', 1);

PROMPT [5/5] Usuario semilla 'admin' creado con la clave temporal indicada (la consola pedira cambiarla).

SELECT id_admin, usuario, nombre, rol, activo,
       CASE WHEN password_hash IS NULL THEN 'TEMPORAL' ELSE 'HASH' END AS tipo_clave
  FROM ADMIN_USUARIO;

SET DEFINE ON
-- Fin del archivo database/admin_usuario.sql - MIDFF Post-MVP (06/10/2026)
