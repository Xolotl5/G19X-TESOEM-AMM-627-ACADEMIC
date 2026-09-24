--------------------------------------------------------------------------------
-- MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
-- Componente 2: modelo de datos (Sprint 1)
-- Archivo: database/schema.sql
-- Motor de base de datos: Oracle Database XE (11.2 / 18c / 21c)
-- Requisitos: RF-07 (persistencia ACID), RF-08 (auditoria de cada evaluacion),
--             RNF-04 (registros de auditoria inmutables) y RNF-05 (integridad:
--             una evaluacion sin registro de auditoria se considera fallida).
-- Historias: HU-MVP-04 (registro completo de cada evaluacion).
-- Documento base: MVP_MIDFF.md secciones 4.1, 7, 11 y PRD_MIDFF.md seccion 9.
--
-- CONTENIDO
--   1. Limpieza re-ejecutable del esquema
--   2. Secuencias de identificadores
--   3. Tablas TRANSACCION, EVALUACION, ALERTA y AUDITORIA (con restricciones)
--   4. Indices de consulta operativa
--   5. Triggers de asignacion de identificador y de inmutabilidad de auditoria
--   6. Procedimiento almacenado SP_REGISTRAR_TRANSACCION (insercion atomica ACID)
--   7. Comentarios de tabla y columna
--   8. Consultas de control (verificacion de cobertura de auditoria)
--
-- EJECUCION (SQL*Plus, como usuario MIDFF en Oracle XE):
--     sqlplus midff/clave@XEPDB1 @database/schema.sql
--     (o dentro de SQL*Plus:  SQL> @C:\todoMIDFF\database\schema.sql)
--
-- DISENO Y SUPUESTOS DEL SPRINT 1
--   a) El script es RE-EJECUTABLE: borra los objetos y los vuelve a crear, por lo
--      que se puede correr dos veces seguidas sin errores (criterio de aceptacion
--      del Sprint 1 y mitigacion del riesgo R-02 del MVP).
--   b) Los identificadores se generan con SECUENCIAS + TRIGGERS en lugar de
--      columnas IDENTITY, para que el mismo script funcione en Oracle XE 11.2,
--      18c y 21c sin cambios.
--   c) Los umbrales 0.4 / 0.7 estan fijos por decision D-05 del MVP: se replican
--      como restricciones CHECK de coherencia score-clasificacion-decision.
--   d) El procedimiento NO ejecuta COMMIT: el llamador (API PHP en el Sprint 2)
--      confirma la transaccion; ante cualquier error el procedimiento revierte
--      todo (ROLLBACK) y falla, garantizando el ACID del RNF-05.
--   e) DISPOSITIVO_MOVIL (Sprint 3) no se crea en este script: queda fuera del
--      alcance del Sprint 1.
--------------------------------------------------------------------------------

SET DEFINE OFF
SET SERVEROUTPUT ON SIZE UNLIMITED

--------------------------------------------------------------------------------
-- 1. LIMPIEZA RE-EJECUTABLE DEL ESQUEMA
--------------------------------------------------------------------------------
DECLARE
    e_tabla_inexistente    EXCEPTION;
    PRAGMA EXCEPTION_INIT(e_tabla_inexistente, -942);    -- ORA-00942
    e_secuencia_inexistente EXCEPTION;
    PRAGMA EXCEPTION_INIT(e_secuencia_inexistente, -2289); -- ORA-02289
BEGIN
    -- Tablas (dependencias en orden inverso; CASCADE elimina las llaves foraneas)
    BEGIN EXECUTE IMMEDIATE 'DROP TABLE ALERTA CASCADE CONSTRAINTS';     EXCEPTION WHEN e_tabla_inexistente THEN NULL; END;
    BEGIN EXECUTE IMMEDIATE 'DROP TABLE AUDITORIA CASCADE CONSTRAINTS';  EXCEPTION WHEN e_tabla_inexistente THEN NULL; END;
    BEGIN EXECUTE IMMEDIATE 'DROP TABLE EVALUACION CASCADE CONSTRAINTS'; EXCEPTION WHEN e_tabla_inexistente THEN NULL; END;
    BEGIN EXECUTE IMMEDIATE 'DROP TABLE TRANSACCION CASCADE CONSTRAINTS'; EXCEPTION WHEN e_tabla_inexistente THEN NULL; END;

    -- Secuencias (los triggers se eliminan junto con sus tablas)
    BEGIN EXECUTE IMMEDIATE 'DROP SEQUENCE SEQ_ALERTA';     EXCEPTION WHEN e_secuencia_inexistente THEN NULL; END;
    BEGIN EXECUTE IMMEDIATE 'DROP SEQUENCE SEQ_AUDITORIA';  EXCEPTION WHEN e_secuencia_inexistente THEN NULL; END;
    BEGIN EXECUTE IMMEDIATE 'DROP SEQUENCE SEQ_EVALUACION'; EXCEPTION WHEN e_secuencia_inexistente THEN NULL; END;
    BEGIN EXECUTE IMMEDIATE 'DROP SEQUENCE SEQ_TRANSACCION'; EXCEPTION WHEN e_secuencia_inexistente THEN NULL; END;

    DBMS_OUTPUT.PUT_LINE('[1/8] Esquema anterior eliminado (o inexistente).');
END;
/

--------------------------------------------------------------------------------
-- 2. SECUENCIAS DE IDENTIFICADORES
--------------------------------------------------------------------------------
CREATE SEQUENCE SEQ_TRANSACCION START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE SEQ_EVALUACION START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE SEQ_ALERTA     START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE SEQ_AUDITORIA  START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

PROMPT [2/8] Secuencias SEQ_TRANSACCION, SEQ_EVALUACION, SEQ_ALERTA y SEQ_AUDITORIA creadas.
--------------------------------------------------------------------------------
-- 3. TABLAS DEL MVP (MVP_MIDFF.md seccion 3.3)
--------------------------------------------------------------------------------

-- 3.1 TRANSACCION: transaccion recibida del sistema origen (RF-01)
CREATE TABLE TRANSACCION (
    id_transaccion   NUMBER(18)               NOT NULL,
    uid_transaccion  VARCHAR2(40)             NOT NULL,
    id_cliente       VARCHAR2(40)             NOT NULL,
    monto            NUMBER(18,2)             NOT NULL,
    moneda           CHAR(3)                  DEFAULT 'MXN' NOT NULL,
    origen           VARCHAR2(20)             NOT NULL,
    canal            VARCHAR2(20),
    ubicacion        VARCHAR2(80),
    ip_origen        VARCHAR2(45),
    fecha_hora       TIMESTAMP WITH TIME ZONE NOT NULL,
    fecha_registro   TIMESTAMP WITH TIME ZONE DEFAULT SYSTIMESTAMP NOT NULL,
    CONSTRAINT pk_transaccion        PRIMARY KEY (id_transaccion),
    CONSTRAINT uq_transaccion_uid    UNIQUE (uid_transaccion),
    CONSTRAINT ck_transaccion_monto  CHECK (monto >= 0),
    CONSTRAINT ck_transaccion_origen CHECK (origen IN ('ERP', 'BANCA', 'PASARELA', 'OTRO'))
);

-- 3.2 EVALUACION: resultado del motor de scoring en C (RF-04, RF-05, RF-08)
CREATE TABLE EVALUACION (
    id_evaluacion        NUMBER(18)               NOT NULL,
    id_transaccion       NUMBER(18)               NOT NULL,
    score                NUMBER(4,3)              NOT NULL,
    clasificacion        VARCHAR2(10)             NOT NULL,
    decision             VARCHAR2(12)             NOT NULL,
    puntaje_monto        NUMBER(4,3),
    puntaje_frecuencia   NUMBER(4,3),
    puntaje_ubicacion_ip NUMBER(4,3),
    variables_usadas     CLOB,
    latencia_us          NUMBER(12,3),
    datos_faltantes      VARCHAR2(400),
    fecha_evaluacion     TIMESTAMP WITH TIME ZONE DEFAULT SYSTIMESTAMP NOT NULL,
    CONSTRAINT pk_evaluacion            PRIMARY KEY (id_evaluacion),
    CONSTRAINT fk_evaluacion_transaccion FOREIGN KEY (id_transaccion)
        REFERENCES TRANSACCION (id_transaccion),
    CONSTRAINT uq_evaluacion_transaccion UNIQUE (id_transaccion),
    CONSTRAINT ck_evaluacion_score      CHECK (score >= 0 AND score <= 1),
    CONSTRAINT ck_evaluacion_clasificacion CHECK (clasificacion IN ('BAJO', 'MEDIO', 'ALTO')),
    CONSTRAINT ck_evaluacion_decision      CHECK (decision IN ('APROBADA', 'EN_REVISION', 'BLOQUEADA')),
    -- Coherencia obligatoria con los umbrales fijos del MVP (0.40 / 0.70, D-05)
    CONSTRAINT ck_evaluacion_umbral CHECK (
        (score < 0.40  AND clasificacion = 'BAJO'  AND decision = 'APROBADA')
     OR (score >= 0.40 AND score <= 0.70 AND clasificacion = 'MEDIO' AND decision = 'EN_REVISION')
     OR (score > 0.70  AND clasificacion = 'ALTO'  AND decision = 'BLOQUEADA'))
);

-- 3.3 ALERTA: alerta emitida ante riesgo medio/alto (RF-09, HU-MVP-06)
CREATE TABLE ALERTA (
    id_alerta      NUMBER(18)               NOT NULL,
    id_evaluacion  NUMBER(18)               NOT NULL,
    tipo           VARCHAR2(12)             NOT NULL,
    estado         VARCHAR2(12)             DEFAULT 'PENDIENTE' NOT NULL,
    descripcion    VARCHAR2(200),
    atendida_por   VARCHAR2(60),
    respuesta      VARCHAR2(200),
    fecha_creacion TIMESTAMP WITH TIME ZONE DEFAULT SYSTIMESTAMP NOT NULL,
    fecha_atencion TIMESTAMP WITH TIME ZONE,
    CONSTRAINT pk_alerta             PRIMARY KEY (id_alerta),
    CONSTRAINT fk_alerta_evaluacion  FOREIGN KEY (id_evaluacion)
        REFERENCES EVALUACION (id_evaluacion),
    CONSTRAINT uq_alerta_evaluacion  UNIQUE (id_evaluacion),
    CONSTRAINT ck_alerta_tipo        CHECK (tipo IN ('PREVENTIVA', 'FRAUDE')),
    CONSTRAINT ck_alerta_estado      CHECK (estado IN ('PENDIENTE', 'ATENDIDA', 'BLOQUEADA')),
    -- Trazabilidad del bloqueo preventivo (HU-MVP-06): BLOQUEADA exige responsable
    CONSTRAINT ck_alerta_bloqueo CHECK (
        estado <> 'BLOQUEADA' OR (atendida_por IS NOT NULL AND fecha_atencion IS NOT NULL))
);

-- 3.4 AUDITORIA: registro inmutable de cada evaluacion y decision (RF-08, RNF-04)
CREATE TABLE AUDITORIA (
    id_auditoria      NUMBER(18)               NOT NULL,
    entidad           VARCHAR2(30)             NOT NULL,
    accion            VARCHAR2(30)             NOT NULL,
    usuario_o_sistema VARCHAR2(60)             NOT NULL,
    detalle           CLOB,
    fecha_hora        TIMESTAMP WITH TIME ZONE DEFAULT SYSTIMESTAMP NOT NULL,
    CONSTRAINT pk_auditoria        PRIMARY KEY (id_auditoria),
    CONSTRAINT ck_auditoria_entidad CHECK (entidad IN ('TRANSACCION', 'EVALUACION', 'ALERTA')),
    CONSTRAINT ck_auditoria_accion  CHECK (accion IN ('INSERT', 'BLOQUEO', 'ATENCION', 'CONSULTA'))
);

--------------------------------------------------------------------------------
-- 4. INDICES DE CONSULTA OPERATIVA (monitoreo por SQL durante el MVP, RF-13 diferido)
--------------------------------------------------------------------------------
CREATE INDEX ix_transaccion_cliente_fecha ON TRANSACCION (id_cliente, fecha_hora);
CREATE INDEX ix_transaccion_fecha         ON TRANSACCION (fecha_hora);
CREATE INDEX ix_evaluacion_score          ON EVALUACION (score);
CREATE INDEX ix_evaluacion_fecha          ON EVALUACION (fecha_evaluacion);
CREATE INDEX ix_alerta_estado             ON ALERTA (estado, tipo);
CREATE INDEX ix_auditoria_entidad_fecha   ON AUDITORIA (entidad, fecha_hora);

PROMPT [3/8] Tablas TRANSACCION, EVALUACION, ALERTA y AUDITORIA creadas con sus restricciones.
PROMPT [4/8] Indices operativos creados.
--------------------------------------------------------------------------------
-- 5. TRIGGERS
--------------------------------------------------------------------------------
-- 5.1 Asignacion automatica del identificador cuando la insercion no lo informa
--     (permite inserts manuales en SQL*Plus; el procedimiento de la seccion 6
--     toma el valor de la secuencia de forma explicita).
CREATE OR REPLACE TRIGGER TRG_TRANSACCION_ID
BEFORE INSERT ON TRANSACCION
FOR EACH ROW
BEGIN
    IF :NEW.id_transaccion IS NULL THEN
        SELECT SEQ_TRANSACCION.NEXTVAL INTO :NEW.id_transaccion FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER TRG_EVALUACION_ID
BEFORE INSERT ON EVALUACION
FOR EACH ROW
BEGIN
    IF :NEW.id_evaluacion IS NULL THEN
        SELECT SEQ_EVALUACION.NEXTVAL INTO :NEW.id_evaluacion FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER TRG_ALERTA_ID
BEFORE INSERT ON ALERTA
FOR EACH ROW
BEGIN
    IF :NEW.id_alerta IS NULL THEN
        SELECT SEQ_ALERTA.NEXTVAL INTO :NEW.id_alerta FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER TRG_AUDITORIA_ID
BEFORE INSERT ON AUDITORIA
FOR EACH ROW
BEGIN
    IF :NEW.id_auditoria IS NULL THEN
        SELECT SEQ_AUDITORIA.NEXTVAL INTO :NEW.id_auditoria FROM DUAL;
    END IF;
END;
/

-- 5.2 Inmutabilidad de la auditoria (RNF-04): los registros de auditoria no se
--     pueden modificar ni eliminar. Para reiniciar el entorno de pruebas hay que
--     deshabilitar el trigger o ejecutar de nuevo este script (que borra la tabla).
CREATE OR REPLACE TRIGGER TRG_AUDITORIA_INMUTABLE
BEFORE UPDATE OR DELETE ON AUDITORIA
BEGIN
    RAISE_APPLICATION_ERROR(-20010,
        'AUDITORIA es inmutable (RNF-04): no se permite UPDATE ni DELETE.');
END;
/

PROMPT [5/8] Triggers de identificador y de inmutabilidad de auditoria creados.
--------------------------------------------------------------------------------
-- 6. PROCEDIMIENTO ALMACENADO: SP_REGISTRAR_TRANSACCION
--------------------------------------------------------------------------------
-- Persiste de forma ATOMICA (todo o nada) el ciclo del paso 6 del flujo end-to-end
-- de MVP_MIDFF.md:
--     TRANSACCION + EVALUACION + (ALERTA si el score >= 0.4) + AUDITORIA
--
-- Garantias de integridad:
--   * Valida coherencia del score con los umbrales 0.40 / 0.70 antes de escribir.
--   * Registra en AUDITORIA la transaccion, la evaluacion y la alerta emitida.
--   * Ante CUALQUIER error hace ROLLBACK total: nunca queda una evaluacion sin su
--     registro de auditoria ni una alerta sin evaluacion (RNF-05 / HU-MVP-04).
--   * NO ejecuta COMMIT: el llamador (API PHP en el Sprint 2) confirma la
--     transaccion, de modo que la persistencia y el resto del flujo puedan
--     manejarse como una sola unidad de trabajo.
--
-- Parametros de salida: los identificadores generados (para la respuesta
-- sincronica del RF-06 y para la alerta_id del contrato JSON).
CREATE OR REPLACE PROCEDURE SP_REGISTRAR_TRANSACCION (
    p_uid_transaccion      IN  VARCHAR2,
    p_id_cliente           IN  VARCHAR2,
    p_monto                IN  NUMBER,
    p_origen               IN  VARCHAR2,
    p_fecha_hora           IN  TIMESTAMP WITH TIME ZONE,
    p_score                IN  NUMBER,
    p_clasificacion        IN  VARCHAR2,
    p_decision             IN  VARCHAR2,
    p_moneda               IN  VARCHAR2 DEFAULT 'MXN',
    p_canal                IN  VARCHAR2 DEFAULT NULL,
    p_ubicacion            IN  VARCHAR2 DEFAULT NULL,
    p_ip_origen            IN  VARCHAR2 DEFAULT NULL,
    p_puntaje_monto        IN  NUMBER   DEFAULT NULL,
    p_puntaje_frecuencia   IN  NUMBER   DEFAULT NULL,
    p_puntaje_ubicacion_ip IN  NUMBER   DEFAULT NULL,
    p_variables_usadas     IN  CLOB     DEFAULT NULL,
    p_latencia_us          IN  NUMBER   DEFAULT NULL,
    p_datos_faltantes      IN  VARCHAR2 DEFAULT NULL,
    p_usuario_sistema      IN  VARCHAR2 DEFAULT 'API-MIDFF',
    p_id_transaccion       OUT NUMBER,
    p_id_evaluacion        OUT NUMBER,
    p_id_alerta            OUT NUMBER
) IS
    v_clasificacion_esperada VARCHAR2(10);
    v_decision_esperada      VARCHAR2(12);
    v_tipo_alerta            VARCHAR2(12);
    v_detalle_transaccion    CLOB;
    v_detalle_evaluacion     CLOB;

    -- Subprograma local: deja rastro de cada operacion en la tabla AUDITORIA.
    PROCEDURE registrar_auditoria (p_entidad IN VARCHAR2, p_accion IN VARCHAR2,
                                   p_detalle IN CLOB) IS
    BEGIN
        INSERT INTO AUDITORIA (id_auditoria, entidad, accion, usuario_o_sistema,
                               detalle, fecha_hora)
        VALUES (SEQ_AUDITORIA.NEXTVAL, p_entidad, p_accion, p_usuario_sistema,
                p_detalle, SYSTIMESTAMP);
    END registrar_auditoria;
BEGIN
    p_id_alerta := NULL;

    -- --- Validaciones previas (RF-02 / RF-05): nada se escribe si algo no cuadra
    IF p_uid_transaccion IS NULL OR p_id_cliente IS NULL THEN
        RAISE_APPLICATION_ERROR(-20023,
            'SP_REGISTRAR_TRANSACCION: uid_transaccion e id_cliente son obligatorios.');
    END IF;

    IF p_monto IS NULL OR p_monto <= 0 THEN
        RAISE_APPLICATION_ERROR(-20022,
            'SP_REGISTRAR_TRANSACCION: el monto debe ser mayor que cero.');
    END IF;

    IF p_score IS NULL OR p_score < 0 OR p_score > 1 THEN
        RAISE_APPLICATION_ERROR(-20020,
            'SP_REGISTRAR_TRANSACCION: el score debe estar entre 0.0 y 1.0.');
    END IF;

    IF p_score < 0.40 THEN
        v_clasificacion_esperada := 'BAJO';
        v_decision_esperada      := 'APROBADA';
    ELSIF p_score <= 0.70 THEN
        v_clasificacion_esperada := 'MEDIO';
        v_decision_esperada      := 'EN_REVISION';
    ELSE
        v_clasificacion_esperada := 'ALTO';
        v_decision_esperada      := 'BLOQUEADA';
    END IF;

    IF p_clasificacion IS NULL OR p_decision IS NULL
       OR p_clasificacion <> v_clasificacion_esperada OR p_decision <> v_decision_esperada THEN
        RAISE_APPLICATION_ERROR(-20021,
            'SP_REGISTRAR_TRANSACCION: clasificacion/decision incoherentes con el score '
            || TO_CHAR(p_score, 'FM0.0000') || ' (esperado ' || v_clasificacion_esperada
            || ' / ' || v_decision_esperada || ').');
    END IF;

    -- --- 1) TRANSACCION --------------------------------------------------------
    SELECT SEQ_TRANSACCION.NEXTVAL INTO p_id_transaccion FROM DUAL;

    INSERT INTO TRANSACCION (id_transaccion, uid_transaccion, id_cliente, monto, moneda,
                             origen, canal, ubicacion, ip_origen, fecha_hora)
    VALUES (p_id_transaccion, p_uid_transaccion, p_id_cliente, p_monto, p_moneda,
            p_origen, p_canal, p_ubicacion, p_ip_origen, p_fecha_hora);

    -- --- 2) EVALUACION ---------------------------------------------------------
    SELECT SEQ_EVALUACION.NEXTVAL INTO p_id_evaluacion FROM DUAL;

    INSERT INTO EVALUACION (id_evaluacion, id_transaccion, score, clasificacion, decision,
                            puntaje_monto, puntaje_frecuencia, puntaje_ubicacion_ip,
                            variables_usadas, latencia_us, datos_faltantes)
    VALUES (p_id_evaluacion, p_id_transaccion, ROUND(p_score, 3), p_clasificacion, p_decision,
            p_puntaje_monto, p_puntaje_frecuencia, p_puntaje_ubicacion_ip,
            p_variables_usadas, p_latencia_us, p_datos_faltantes);

    -- --- 3) ALERTA (solo riesgo medio/alto, RF-09) ------------------------------
    IF p_score >= 0.40 THEN
        IF p_score > 0.70 THEN
            v_tipo_alerta := 'FRAUDE';
        ELSE
            v_tipo_alerta := 'PREVENTIVA';
        END IF;

        SELECT SEQ_ALERTA.NEXTVAL INTO p_id_alerta FROM DUAL;

        INSERT INTO ALERTA (id_alerta, id_evaluacion, tipo, estado, descripcion)
        VALUES (p_id_alerta, p_id_evaluacion, v_tipo_alerta, 'PENDIENTE',
                'Score ' || TO_CHAR(p_score, 'FM0.0000') || ' (' || p_clasificacion
                || '): ' || p_decision || ' segun RF-05.');
    END IF;

    -- --- 4) AUDITORIA (RF-08 y RNF-04: trazabilidad completa) -------------------
    v_detalle_transaccion := 'uid=' || p_uid_transaccion
                          || ';id_cliente=' || p_id_cliente
                          || ';monto=' || TO_CHAR(p_monto, 'FM9999999999990.00')
                          || ';moneda=' || p_moneda
                          || ';origen=' || p_origen
                          || ';ubicacion=' || p_ubicacion
                          || ';ip_origen=' || p_ip_origen;

    v_detalle_evaluacion := 'id_transaccion=' || TO_CHAR(p_id_transaccion)
                         || ';score=' || TO_CHAR(p_score, 'FM0.0000')
                         || ';clasificacion=' || p_clasificacion
                         || ';decision=' || p_decision
                         || ';puntaje_monto=' || TO_CHAR(p_puntaje_monto, 'FM0.0000')
                         || ';puntaje_frecuencia=' || TO_CHAR(p_puntaje_frecuencia, 'FM0.0000')
                         || ';puntaje_ubicacion_ip=' || TO_CHAR(p_puntaje_ubicacion_ip, 'FM0.0000')
                         || ';latencia_us=' || TO_CHAR(p_latencia_us, 'FM9999999990.000')
                         || ';datos_faltantes=' || p_datos_faltantes
                         || ';variables=' || p_variables_usadas;

    registrar_auditoria('TRANSACCION', 'INSERT', v_detalle_transaccion);
    registrar_auditoria('EVALUACION', 'INSERT', v_detalle_evaluacion);

    IF p_id_alerta IS NOT NULL THEN
        registrar_auditoria('ALERTA', 'INSERT',
            'id_evaluacion=' || TO_CHAR(p_id_evaluacion) || ';id_alerta=' || TO_CHAR(p_id_alerta)
            || ';tipo=' || v_tipo_alerta || ';estado=PENDIENTE');
    END IF;

    -- NO se ejecuta COMMIT: lo confirma el llamador (API PHP, Sprint 2).

EXCEPTION
    WHEN OTHERS THEN
        -- Garantia ACID (RNF-05): si falla cualquier insercion, se revierte TODO
        -- y el llamador recibe el error para responder al sistema origen.
        ROLLBACK;
        RAISE_APPLICATION_ERROR(-20001,
            'SP_REGISTRAR_TRANSACCION: se revirtio la transaccion completa. Causa: ' || SQLERRM);
END SP_REGISTRAR_TRANSACCION;
/

SHOW ERRORS PROCEDURE SP_REGISTRAR_TRANSACCION
PROMPT [6/8] Procedimiento SP_REGISTRAR_TRANSACCION creado (insercion atomica ACID).
--------------------------------------------------------------------------------
-- 7. COMENTARIOS DEL MODELO (documentacion dentro del diccionario de datos)
--------------------------------------------------------------------------------
COMMENT ON TABLE  TRANSACCION                 IS 'Transaccion recibida del sistema origen (RF-01).';
COMMENT ON COLUMN TRANSACCION.uid_transaccion IS 'Identificador de negocio de la transaccion (TRX-AAAA-NNNNNN).';
COMMENT ON COLUMN TRANSACCION.monto           IS 'Monto de la operacion en la moneda indicada.';
COMMENT ON COLUMN TRANSACCION.fecha_hora      IS 'Fecha y hora de la operacion informada por el origen.';

COMMENT ON TABLE  EVALUACION                  IS 'Resultado del motor de scoring en C para cada transaccion (RF-04, RF-05).';
COMMENT ON COLUMN EVALUACION.score            IS 'Score de riesgo calculado por el motor C: 0.000 a 1.000 (RF-04).';
COMMENT ON COLUMN EVALUACION.clasificacion    IS 'BAJO (< 0.40) | MEDIO (0.40 - 0.70) | ALTO (> 0.70) (RF-05).';
COMMENT ON COLUMN EVALUACION.decision         IS 'APROBADA | EN_REVISION | BLOQUEADA (RF-05).';
COMMENT ON COLUMN EVALUACION.puntaje_monto    IS 'Puntaje normalizado de la variable 1 (desviacion del monto vs. historial).';
COMMENT ON COLUMN EVALUACION.puntaje_frecuencia IS 'Puntaje normalizado de la variable 2 (frecuencia por hora).';
COMMENT ON COLUMN EVALUACION.puntaje_ubicacion_ip IS 'Puntaje normalizado de la variable 3 (ubicacion/IP inusual).';
COMMENT ON COLUMN EVALUACION.variables_usadas IS 'JSON con las 3 variables evaluadas (salida del motor C, RF-08).';
COMMENT ON COLUMN EVALUACION.latencia_us      IS 'Latencia del motor C en microsegundos (RNF-01).';
COMMENT ON COLUMN EVALUACION.datos_faltantes  IS 'Variables que no pudieron calcularse (fail-safe del motor).';

COMMENT ON TABLE  ALERTA                      IS 'Alerta emitida ante riesgo medio/alto (RF-09) y bloqueo preventivo (RF-11).';
COMMENT ON COLUMN ALERTA.tipo                 IS 'PREVENTIVA (banda media) | FRAUDE (banda alta).';
COMMENT ON COLUMN ALERTA.estado               IS 'PENDIENTE | ATENDIDA | BLOQUEADA.';
COMMENT ON COLUMN ALERTA.atendida_por         IS 'Oficial de seguridad que ejecuto la accion (HU-MVP-06).';

COMMENT ON TABLE  AUDITORIA                   IS 'Registro inmutable de cada evaluacion y decision (RF-08, RNF-04).';
COMMENT ON COLUMN AUDITORIA.entidad           IS 'TRANSACCION | EVALUACION | ALERTA.';
COMMENT ON COLUMN AUDITORIA.usuario_o_sistema IS 'Usuario o componente que origino la operacion (API-MIDFF).';
COMMENT ON COLUMN AUDITORIA.detalle           IS 'Detalle completo de la operacion (incluye variables y score).';

--------------------------------------------------------------------------------
-- 8. VERIFICACION DEL ESQUEMA
--------------------------------------------------------------------------------
DECLARE
    v_tablas     NUMBER;
    v_secuencias NUMBER;
    v_triggers   NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_tablas FROM user_tables
     WHERE table_name IN ('TRANSACCION', 'EVALUACION', 'ALERTA', 'AUDITORIA');

    SELECT COUNT(*) INTO v_secuencias FROM user_sequences
     WHERE sequence_name IN ('SEQ_TRANSACCION', 'SEQ_EVALUACION', 'SEQ_ALERTA', 'SEQ_AUDITORIA');

    SELECT COUNT(*) INTO v_triggers FROM user_triggers
     WHERE trigger_name IN ('TRG_TRANSACCION_ID', 'TRG_EVALUACION_ID', 'TRG_ALERTA_ID',
                            'TRG_AUDITORIA_ID', 'TRG_AUDITORIA_INMUTABLE');

    DBMS_OUTPUT.PUT_LINE('[7/8] Comentarios del diccionario de datos registrados.');
    DBMS_OUTPUT.PUT_LINE('[8/8] Verificacion del esquema MIDFF');
    DBMS_OUTPUT.PUT_LINE('      Tablas      : ' || v_tablas     || ' de 4');
    DBMS_OUTPUT.PUT_LINE('      Secuencias  : ' || v_secuencias || ' de 4');
    DBMS_OUTPUT.PUT_LINE('      Triggers    : ' || v_triggers   || ' de 5');
    DBMS_OUTPUT.PUT_LINE('      Procedimiento: SP_REGISTRAR_TRANSACCION');
    IF v_tablas = 4 AND v_secuencias = 4 AND v_triggers = 5 THEN
        DBMS_OUTPUT.PUT_LINE('      ESTADO: esquema listo para el Sprint 2.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('      ESTADO: ADVERTENCIA, faltan objetos (revise los errores previos).');
    END IF;
END;
/
--------------------------------------------------------------------------------
-- ANEXO A: EJEMPLO DE INVOCACION (el Sprint 2 lo llamara desde PHP)
--------------------------------------------------------------------------------
-- Para probarlo manualmente en SQL*Plus, quite los comentarios del bloque.
--
-- DECLARE
--     v_trx    NUMBER;
--     v_eval   NUMBER;
--     v_alerta NUMBER;
-- BEGIN
--     SP_REGISTRAR_TRANSACCION(
--         p_uid_transaccion      => 'TRX-2026-000123',
--         p_id_cliente           => 'CLI-001',
--         p_monto                => 15499.00,
--         p_origen               => 'ERP',
--         p_fecha_hora           => TO_TIMESTAMP_TZ('2026-09-21T10:15:00-06:00',
--                                                   'YYYY-MM-DD"T"HH24:MI:SSTZH:TZM'),
--         p_score                => 0.9500,
--         p_clasificacion        => 'ALTO',
--         p_decision             => 'BLOQUEADA',
--         p_ubicacion            => 'CDMX',
--         p_ip_origen            => '203.0.113.45',
--         p_puntaje_monto        => 1.0000,
--         p_puntaje_frecuencia   => 0.8333,
--         p_puntaje_ubicacion_ip => 1.0000,
--         p_variables_usadas     => '{"monto":{"entrada":15499.00,"referencia":1200.00}}',
--         p_latencia_us          => 0.600,
--         p_id_transaccion       => v_trx,
--         p_id_evaluacion        => v_eval,
--         p_id_alerta            => v_alerta);
--     COMMIT;
--     DBMS_OUTPUT.PUT_LINE('TRANSACCION=' || v_trx || ' EVALUACION=' || v_eval
--                          || ' ALERTA=' || v_alerta);
-- END;
-- /

--------------------------------------------------------------------------------
-- ANEXO B: CONSULTAS DE CONTROL DEL MVP (monitoreo por SQL, RF-13 diferido)
--------------------------------------------------------------------------------
-- a) Evaluaciones sin registro de auditoria (debe devolver 0 filas, metrica
--    "Cobertura de auditoria: 100 %" de MVP_MIDFF.md seccion 10):
--      SELECT e.id_evaluacion
--        FROM EVALUACION e
--       WHERE NOT EXISTS (SELECT 1 FROM AUDITORIA a
--                          WHERE a.entidad = 'EVALUACION'
--                            AND a.detalle LIKE '%id_transaccion=' || e.id_transaccion || '%');
--
-- b) Alertas por tipo y estado (bandeja de trabajo del oficial de seguridad):
--      SELECT tipo, estado, COUNT(*) AS total
--        FROM ALERTA GROUP BY tipo, estado ORDER BY tipo, estado;
--
-- c) Latencia del motor C registrada por banda de riesgo (RNF-01):
--      SELECT clasificacion, COUNT(*) AS evaluaciones,
--             ROUND(AVG(latencia_us), 3) AS latencia_us_promedio
--        FROM EVALUACION GROUP BY clasificacion ORDER BY clasificacion;
--
-- d) Trazabilidad completa de un bloqueo preventivo (HU-MVP-06):
--      SELECT a.id_alerta, a.estado, a.atendida_por, a.fecha_atencion
--        FROM ALERTA a WHERE a.estado = 'BLOQUEADA';
--
-- e) Re-ejecucion del script sobre un entorno ya instalado (idempotencia):
--      @C:\todoMIDFF\database\schema.sql

PROMPT ========================================================================
PROMPT  Esquema MIDFF (Sprint 1) instalado:
PROMPT    TRANSACCION, EVALUACION, ALERTA, AUDITORIA (RF-07, RF-08, RNF-04)
PROMPT    Procedimiento SP_REGISTRAR_TRANSACCION (insercion atomica ACID, RNF-05)
PROMPT  Siguiente paso: Sprint 2 (API REST PHP que invoca al motor C y persiste).
PROMPT ========================================================================
SET DEFINE ON

-- Fin del archivo schema.sql - MIDFF Sprint 1 (23/09/2026)






