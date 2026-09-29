--------------------------------------------------------------------------------
-- MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
-- Componente: bloqueo preventivo de alertas (HU-MVP-06, Sprint 4)
-- Archivo    : database/sp_bloquear_alerta.sql
--
-- Proposito: procedimiento almacenado SP_BLOQUEAR_ALERTA que ejecuta la
--   accion "Bloquear" de la app Android de forma ATOMICA en Oracle XE:
--     1. Verifica que la alerta exista y no este ya BLOQUEADA.
--     2. UPDATE de ALERTA: estado = BLOQUEADA, atendida_por (usuario del
--        oficial), respuesta y fecha_atencion (SYSTIMESTAMP).
--     3. INSERT en AUDITORIA: entidad ALERTA, accion BLOQUEO, usuario y
--        detalle trazable (tipo, respuesta y timestamp).
--
-- Requisitos: HU-MVP-06 (bloqueo desde la app), RF-08 (auditoria),
--             RNF-04 (auditoria inmutable) y RNF-05 (unidad atomica).
--
-- CONTRATO (invocado por config/db.php :: midff_db_bloquear_alerta):
--   ENTRADA : p_id_alerta (NUMBER, id de ALERTA)
--             p_usuario   (VARCHAR2 <= 60, oficial de DISPOSITIVO_MOVIL)
--             p_respuesta (VARCHAR2 <= 200, comentario opcional; NULL usa
--                          el texto por omision)
--   SALIDA  : p_estado        ('BLOQUEADA')
--             p_fecha_atencion (ISO 8601 con zona, para la respuesta HTTP)
--             p_id_auditoria  (id del registro BLOQUEO en AUDITORIA)
--
-- ERRORES DE NEGOCIO (la API los mapea a HTTP):
--   ORA-20003: no existe la alerta                -> HTTP 404
--   ORA-20004: la alerta ya esta BLOQUEADA        -> HTTP 409
--   ORA-20001: cualquier otro fallo (con ROLLBACK)-> HTTP 500
--
-- DECISION D-35 (BITACORA BIT-010): igual que SP_REGISTRAR_TRANSACCION
--   (D-13), este SP NO ejecuta COMMIT: ante cualquier error hace ROLLBACK
--   total y relanza; el llamador (API PHP) confirma la unidad atomica.
--   Vive en un script APARTE re-ejecutable (CREATE OR REPLACE) para no
--   reescribir el schema.sql congelado del Sprint 1 (mismo criterio que
--   D-24 con database/dispositivo_movil.sql).
--
-- EJECUCION (SQL*Plus, como MIDFFuser en Oracle XE; COMPLEMENTA schema.sql):
--   sqlplus MIDFFuser/midffadmin@localhost:1521/XEPDB1 @sp_bloquear_alerta.sql
--------------------------------------------------------------------------------

PROMPT ========================================================================
PROMPT Instalando SP_BLOQUEAR_ALERTA (Sprint 4 - bloqueo preventivo HU-MVP-06)
PROMPT ========================================================================

CREATE OR REPLACE PROCEDURE SP_BLOQUEAR_ALERTA (
    p_id_alerta      IN  NUMBER,
    p_usuario        IN  VARCHAR2,
    p_respuesta      IN  VARCHAR2,
    p_estado         OUT VARCHAR2,
    p_fecha_atencion OUT VARCHAR2,
    p_id_auditoria   OUT NUMBER
)
IS
    v_estado_actual  ALERTA.estado%TYPE;
    v_tipo           ALERTA.tipo%TYPE;
    v_respuesta      ALERTA.respuesta%TYPE;
BEGIN
    -- 1. La alerta debe existir (NO_DATA_FOUND se traduce a ORA-20003).
    SELECT estado, tipo
      INTO v_estado_actual, v_tipo
      FROM ALERTA
     WHERE id_alerta = p_id_alerta;

    -- 2. Idempotencia controlada: bloquear dos veces es un conflicto (HTTP 409).
    IF v_estado_actual = 'BLOQUEADA' THEN
        RAISE_APPLICATION_ERROR(-20004,
            'SP_BLOQUEAR_ALERTA: la alerta ' || p_id_alerta || ' ya esta BLOQUEADA');
    END IF;

    v_respuesta := NVL(TRIM(p_respuesta),
                       'Bloqueo preventivo desde la app Android (HU-MVP-06)');

    -- 3. Bloqueo de la alerta con responsable y timestamp (ck_alerta_bloqueo).
    UPDATE ALERTA
       SET estado         = 'BLOQUEADA',
           atendida_por   = p_usuario,
           respuesta      = SUBSTR(v_respuesta, 1, 200),
           fecha_atencion = SYSTIMESTAMP
     WHERE id_alerta = p_id_alerta;

    -- 4. Registro inmutable del bloqueo en AUDITORIA (RF-08 / RNF-04).
    INSERT INTO AUDITORIA (id_auditoria, entidad, accion, usuario_o_sistema, detalle)
    VALUES (SEQ_AUDITORIA.NEXTVAL, 'ALERTA', 'BLOQUEO', p_usuario,
            'Bloqueo preventivo HU-MVP-06 de la alerta ' || p_id_alerta
            || ' (tipo ' || v_tipo || ', estado previo ' || v_estado_actual
            || '). Respuesta del oficial: ' || v_respuesta);

    -- 5. Valores de salida para la respuesta HTTP de la API.
    p_estado := 'BLOQUEADA';

    SELECT TO_CHAR(fecha_atencion, 'YYYY-MM-DD"T"HH24:MI:SSTZH:TZM')
      INTO p_fecha_atencion
      FROM ALERTA
     WHERE id_alerta = p_id_alerta;

    SELECT SEQ_AUDITORIA.CURRVAL
      INTO p_id_auditoria
      FROM DUAL;

EXCEPTION
    WHEN NO_DATA_FOUND THEN
        ROLLBACK;
        RAISE_APPLICATION_ERROR(-20003,
            'SP_BLOQUEAR_ALERTA: no existe la alerta ' || p_id_alerta);
    WHEN OTHERS THEN
        ROLLBACK;
        -- Los errores de negocio propios (-20003/-20004) conservan su codigo
        -- para que la API los mapee a 404/409; el resto se envuelve en -20001.
        IF SQLCODE BETWEEN -20099 AND -20000 THEN
            RAISE;
        END IF;
        RAISE_APPLICATION_ERROR(-20001,
            'SP_BLOQUEAR_ALERTA: se revirtio el bloqueo completo. Causa: ' || SQLERRM);
END SP_BLOQUEAR_ALERTA;
/

SHOW ERRORS PROCEDURE SP_BLOQUEAR_ALERTA

PROMPT Procedimiento SP_BLOQUEAR_ALERTA creado (bloqueo + auditoria atomicos).
PROMPT ========================================================================
