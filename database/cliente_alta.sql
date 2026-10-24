--------------------------------------------------------------------------------
-- MIDFF - Tabla CLIENTE_ALTA: aprobacion del banco para vincular un id_cliente
-- que la app no genero por si misma (BIT-038, Corte 3)
-- Archivo: database/cliente_alta.sql
--
-- PROPOSITO: cerrar el ultimo hueco del primer registro en
--   api/v1/dispositivos.php. Antes, un id_cliente sin dueno se lo quedaba la
--   primera cuenta de Firebase que lo pidiera. Ahora el vinculo con un id
--   AJENO al auto-generado de la propia cuenta (CLI- + 6 primeros caracteres
--   alfanumericos del uid, decision D-50) solo se acepta si un administrador
--   lo aprobo antes desde MIDFF.html (pestania Clientes -> «Aprobar ID»),
--   que invoca POST /api/v1/admin/clientes/aprobar.
--
-- DISENO Y SUPUESTOS
--   a) Re-ejecutable: borra y recrea la tabla (mismo criterio que
--      admin_usuario.sql, riesgo R-02). No guarda secretos.
--   b) La fila es el registro de la aprobacion: quien la hizo y cuando.
--      AUDITORIA no se usa porque sus CHECK congelados solo aceptan
--      entidad TRANSACCION/EVALUACION/ALERTA (D-58).
--   c) Idempotente desde la API: aprobar dos veces hace MERGE (no falla).
--
-- EJECUCION (SQL*Plus, como MIDFFuser en Oracle XE; COMPLEMENTA schema.sql):
--   sqlplus MIDFFuser@localhost:1521/XEPDB1 @database/cliente_alta.sql
--------------------------------------------------------------------------------

SET SERVEROUTPUT ON SIZE UNLIMITED

--------------------------------------------------------------------------------
-- 1. LIMPIEZA RE-EJECUTABLE
--------------------------------------------------------------------------------
DECLARE
    e_tabla_inexistente EXCEPTION;
    PRAGMA EXCEPTION_INIT(e_tabla_inexistente, -942);  -- ORA-00942
BEGIN
    BEGIN EXECUTE IMMEDIATE 'DROP TABLE CLIENTE_ALTA CASCADE CONSTRAINTS'; EXCEPTION WHEN e_tabla_inexistente THEN NULL; END;
    DBMS_OUTPUT.PUT_LINE('[1/2] Esquema anterior eliminado (o inexistente).');
END;
/

--------------------------------------------------------------------------------
-- 2. TABLA CLIENTE_ALTA (altas aprobadas por el banco)
--------------------------------------------------------------------------------
CREATE TABLE CLIENTE_ALTA (
    id_cliente         VARCHAR2(40)             NOT NULL,
    aprobado_por       VARCHAR2(60)             NOT NULL,
    fecha_aprobacion   TIMESTAMP WITH TIME ZONE DEFAULT SYSTIMESTAMP NOT NULL,
    CONSTRAINT pk_cliente_alta PRIMARY KEY (id_cliente)
);

COMMENT ON TABLE  CLIENTE_ALTA IS 'id_cliente que el banco autorizo a vincular en el primer registro de la app (BIT-038)';
COMMENT ON COLUMN CLIENTE_ALTA.aprobado_por IS 'usuario administrador de la consola que aprobo el vinculo';

PROMPT [2/2] Tabla CLIENTE_ALTA creada (aprobaciones del banco, BIT-038).

-- Fin del archivo database/cliente_alta.sql - MIDFF Corte 3 (21/10/2026)
