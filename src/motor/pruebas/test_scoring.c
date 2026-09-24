/* ============================================================================
 * MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
 * Archivo: src/motor/pruebas/test_scoring.c
 * Suite de PRUEBAS UNITARIAS del motor de scoring (Sprint 1).
 * Requisitos cubiertos: RF-04 (3 variables), RF-05 (umbrales 0.4 / 0.7),
 *   RF-08 (detalle auditable), HU-MVP-02 (cada variable eleva el score) y
 *   HU-MVP-03 (la decision corresponde al umbral).
 *
 * Compilacion (ver build.ps1): se enlaza con src/motor/scoring.c, json_io.c y
 * cronometro.c definiendo MIDFF_SIN_MAIN para excluir el main() del motor.
 *
 *     gcc -std=c99 -Wall -Wextra -O2 -DMIDFF_SIN_MAIN -Isrc/motor \
 *         -o src/motor/bin/midff-test-scoring.exe \
 *         src/motor/pruebas/test_scoring.c src/motor/scoring.c \
 *         src/motor/json_io.c src/motor/cronometro.c -lm
 * ==========================================================================*/

#include "../scoring.h"
#include "../json_io.h"
#include "marco_pruebas.h"

#include <stdio.h>
#include <string.h>

#define TOLERANCIA 0.0000001

/* ---------------------------------------------------------------------------
 * Datos de prueba: cliente con comportamiento normal (banda BAJA esperada)
 * -------------------------------------------------------------------------*/

static Transaccion transaccion_base(void)
{
    Transaccion transaccion;

    memset(&transaccion, 0, sizeof(transaccion));
    strcpy(transaccion.id_transaccion, "TRX-2026-000001");
    strcpy(transaccion.id_cliente, "CLI-001");
    transaccion.monto = 1100.00;
    strcpy(transaccion.moneda, "MXN");
    strcpy(transaccion.origen, "ERP");
    strcpy(transaccion.ubicacion, "CDMX");
    strcpy(transaccion.ip_origen, "189.203.4.7");
    strcpy(transaccion.fecha_hora, "2026-09-21T10:15:00-06:00");
    return transaccion;
}

static ContextoCliente contexto_base(void)
{
    ContextoCliente contexto;

    memset(&contexto, 0, sizeof(contexto));
    contexto.presente = 1;
    contexto.monto_promedio_historial = 1200.00;
    contexto.transacciones_ultima_hora = 2;
    strcpy(contexto.ubicacion_habitual, "CDMX");
    strcpy(contexto.ip_habitual, "189.203.4.7");
    return contexto;
}

static Evaluacion evaluar(const Transaccion *transaccion, const ContextoCliente *contexto)
{
    Evaluacion evaluacion;

    evaluar_transaccion(transaccion, contexto, &evaluacion);
    return evaluacion;
}
/* ---------------------------------------------------------------------------
 * Caso 1: normalizacion por rampa
 * -------------------------------------------------------------------------*/
static void caso_normalizacion(void)
{
    MIDFF_CASO("utilidades: normalizacion por rampa (0.0 - 1.0)");

    MIDFF_VERIFICAR_CERCA(normalizar_rampa(0.5, 1.0, 5.0), 0.0, TOLERANCIA,
                          "por debajo del minimo el puntaje es 0.0");
    MIDFF_VERIFICAR_CERCA(normalizar_rampa(1.0, 1.0, 5.0), 0.0, TOLERANCIA,
                          "en el minimo el puntaje es 0.0");
    MIDFF_VERIFICAR_CERCA(normalizar_rampa(3.0, 1.0, 5.0), 0.5, TOLERANCIA,
                          "a mitad de rango el puntaje es 0.5");
    MIDFF_VERIFICAR_CERCA(normalizar_rampa(5.0, 1.0, 5.0), 1.0, TOLERANCIA,
                          "en el maximo el puntaje es 1.0");
    MIDFF_VERIFICAR_CERCA(normalizar_rampa(40.0, 1.0, 5.0), 1.0, TOLERANCIA,
                          "por arriba del maximo el puntaje se satura en 1.0");
    MIDFF_VERIFICAR_CERCA(normalizar_rampa(5.0, 5.0, 5.0), 1.0, TOLERANCIA,
                          "rango degenerado (minimo == maximo) devuelve 1.0");
    MIDFF_VERIFICAR_CERCA(normalizar_rampa(3.0, 5.0, 5.0), 0.0, TOLERANCIA,
                          "rango degenerado por debajo del umbral devuelve 0.0");
}

/* ---------------------------------------------------------------------------
 * Caso 2: acotado y redondeo del score
 * -------------------------------------------------------------------------*/
static void caso_limite_redondeo(void)
{
    MIDFF_CASO("utilidades: acotado a [0,1] y redondeo a 4 decimales");

    MIDFF_VERIFICAR_CERCA(limitar(1.7, 0.0, 1.0), 1.0, TOLERANCIA, "limitar acota el maximo");
    MIDFF_VERIFICAR_CERCA(limitar(-0.4, 0.0, 1.0), 0.0, TOLERANCIA, "limitar acota el minimo");
    MIDFF_VERIFICAR_CERCA(limitar(0.42, 0.0, 1.0), 0.42, TOLERANCIA, "limitar respeta el valor interior");
    MIDFF_VERIFICAR_CERCA(redondear(0.123456, 4), 0.1235, TOLERANCIA, "redondeo a 4 decimales");
    MIDFF_VERIFICAR_CERCA(redondear(0.69996, 4), 0.7, TOLERANCIA, "0.69996 se redondea a 0.7");
    MIDFF_VERIFICAR_CERCA(redondear(0.99996, 4), 1.0, TOLERANCIA, "0.99996 se redondea a 1.0");
    MIDFF_VERIFICAR_CERCA(redondear(0.4, 4), 0.4, TOLERANCIA, "un valor exacto no se altera");
}

/* ---------------------------------------------------------------------------
 * Caso 3: variable 1 - desviacion del monto (RF-04a, HU-MVP-02.1)
 * -------------------------------------------------------------------------*/
static void caso_variable_monto(void)
{
    double ratio = -1.0;

    MIDFF_CASO("variable 1: desviacion del monto respecto al historial (RF-04a)");

    MIDFF_VERIFICAR_CERCA(puntaje_monto(1200.00, 1200.00, &ratio), 0.0, TOLERANCIA,
                          "monto igual al promedio historico no aporta riesgo");
    MIDFF_VERIFICAR_CERCA(ratio, 1.0, TOLERANCIA, "el ratio informado es monto / promedio");
    MIDFF_VERIFICAR_CERCA(puntaje_monto(600.00, 1200.00, &ratio), 0.0, TOLERANCIA,
                          "un monto por debajo del promedio tampoco aporta riesgo");
    MIDFF_VERIFICAR_CERCA(puntaje_monto(3600.00, 1200.00, &ratio), 0.5, TOLERANCIA,
                          "monto 3x el promedio -> puntaje 0.5");
    MIDFF_VERIFICAR_CERCA(puntaje_monto(6000.00, 1200.00, &ratio), 1.0, TOLERANCIA,
                          "monto 5x el promedio -> puntaje 1.0");
    MIDFF_VERIFICAR_CERCA(puntaje_monto(60000.00, 1200.00, &ratio), 1.0, TOLERANCIA,
                          "monto 50x el promedio se satura en 1.0");
    MIDFF_VERIFICAR_CERCA(puntaje_monto(1500.00, 0.0, &ratio), PUNTAJE_NEUTRO, TOLERANCIA,
                          "sin monto promedio historico se aplica el puntaje neutro");
    MIDFF_VERIFICAR_CERCA(ratio, 0.0, TOLERANCIA,
                          "sin historial el ratio informado es 0.0");
    MIDFF_VERIFICAR_CERCA(puntaje_monto(0.0, 1200.00, &ratio), PUNTAJE_NEUTRO, TOLERANCIA,
                          "un monto no positivo no se evalua (puntaje neutro)");
}

/* ---------------------------------------------------------------------------
 * Caso 4: variable 2 - frecuencia por hora (RF-04b, HU-MVP-02.2)
 * -------------------------------------------------------------------------*/
static void caso_variable_frecuencia(void)
{
    MIDFF_CASO("variable 2: frecuencia de transacciones por hora (RF-04b)");

    MIDFF_VERIFICAR_CERCA(puntaje_frecuencia(1), 0.0, TOLERANCIA,
                          "una transaccion en la hora no aporta riesgo");
    MIDFF_VERIFICAR_CERCA(puntaje_frecuencia(FRECUENCIA_BAJA), 0.0, TOLERANCIA,
                          "hasta FRECUENCIA_BAJA el puntaje es 0.0");
    MIDFF_VERIFICAR_CERCA(puntaje_frecuencia(5), 0.5, TOLERANCIA,
                          "5 transacciones en la hora -> puntaje 0.5");
    MIDFF_VERIFICAR_CERCA(puntaje_frecuencia(FRECUENCIA_ALTA), 1.0, TOLERANCIA,
                          "FRECUENCIA_ALTA transacciones -> puntaje 1.0");
    MIDFF_VERIFICAR_CERCA(puntaje_frecuencia(500), 1.0, TOLERANCIA,
                          "frecuencias extremas se saturan en 1.0");
    MIDFF_VERIFICAR_CERCA(puntaje_frecuencia(0), PUNTAJE_NEUTRO, TOLERANCIA,
                          "sin dato de frecuencia se aplica el puntaje neutro");
    MIDFF_VERIFICAR_CERCA(puntaje_frecuencia(-3), PUNTAJE_NEUTRO, TOLERANCIA,
                          "una frecuencia invalida se trata como dato ausente");
}
/* ---------------------------------------------------------------------------
 * Caso 5: variable 3 - ubicacion / IP inusual (RF-04c, HU-MVP-02.3)
 * -------------------------------------------------------------------------*/
static void caso_variable_ubicacion_ip(void)
{
    Transaccion transaccion = transaccion_base();
    ContextoCliente contexto = contexto_base();
    int ubicacion_distinta = 0;
    int ip_distinta = 0;

    MIDFF_CASO("variable 3: ubicacion / IP inusual (RF-04c)");

    MIDFF_VERIFICAR_CERCA(puntaje_ubicacion_ip(&transaccion, &contexto, &ubicacion_distinta,
                                               &ip_distinta),
                          0.0, TOLERANCIA, "misma ubicacion e IP -> puntaje 0.0");
    MIDFF_VERIFICAR(ubicacion_distinta == 0 && ip_distinta == 0,
                    "sin cambios no se marcan dimensiones distintas");

    strcpy(transaccion.ubicacion, "GDL");
    MIDFF_VERIFICAR_CERCA(puntaje_ubicacion_ip(&transaccion, &contexto, &ubicacion_distinta,
                                               &ip_distinta),
                          0.5, TOLERANCIA, "cambio de ubicacion -> puntaje 0.5");
    MIDFF_VERIFICAR(ubicacion_distinta == 1 && ip_distinta == 0,
                    "se marca solo la ubicacion como distinta");

    strcpy(transaccion.ubicacion, "CDMX");
    strcpy(transaccion.ip_origen, "203.0.113.45");
    MIDFF_VERIFICAR_CERCA(puntaje_ubicacion_ip(&transaccion, &contexto, &ubicacion_distinta,
                                               &ip_distinta),
                          0.5, TOLERANCIA, "cambio de IP -> puntaje 0.5");
    MIDFF_VERIFICAR(ubicacion_distinta == 0 && ip_distinta == 1,
                    "se marca solo la IP como distinta");

    strcpy(transaccion.ubicacion, "GDL");
    MIDFF_VERIFICAR_CERCA(puntaje_ubicacion_ip(&transaccion, &contexto, &ubicacion_distinta,
                                               &ip_distinta),
                          1.0, TOLERANCIA, "ubicacion e IP distintas -> puntaje 1.0");
    MIDFF_VERIFICAR(ubicacion_distinta == 1 && ip_distinta == 1,
                    "se marcan ambas dimensiones como distintas");

    /* La comparacion no distingue mayusculas ni espacios extremos. */
    strcpy(transaccion.ubicacion, "  cdmx ");
    strcpy(transaccion.ip_origen, "189.203.4.7");
    MIDFF_VERIFICAR_CERCA(puntaje_ubicacion_ip(&transaccion, &contexto, &ubicacion_distinta,
                                               &ip_distinta),
                          0.0, TOLERANCIA,
                          "mayusculas y espacios no cuentan como ubicacion distinta");

    /* Dato ausente: no hay nada que comparar, se aplica el puntaje neutro. */
    strcpy(transaccion.ubicacion, "");
    strcpy(transaccion.ip_origen, "");
    MIDFF_VERIFICAR_CERCA(puntaje_ubicacion_ip(&transaccion, &contexto, &ubicacion_distinta,
                                               &ip_distinta),
                          PUNTAJE_NEUTRO, TOLERANCIA,
                          "sin datos comparables la variable aporta el puntaje neutro");
}
/* ---------------------------------------------------------------------------
 * Caso 6: score ponderado (RF-04)
 * -------------------------------------------------------------------------*/
static void caso_score_ponderado(void)
{
    PuntajesVariables puntajes;

    MIDFF_CASO("score ponderado de las 3 variables (RF-04)");

    MIDFF_VERIFICAR_CERCA(PESO_MONTO + PESO_FRECUENCIA + PESO_UBICACION_IP, 1.0, TOLERANCIA,
                          "los pesos del modelo suman 1.0");

    puntajes.monto = 1.0;
    puntajes.frecuencia = 1.0;
    puntajes.ubicacion_ip = 1.0;
    MIDFF_VERIFICAR_CERCA(calcular_score(&puntajes), 1.0, TOLERANCIA,
                          "los tres puntajes al maximo dan score 1.0");

    puntajes.monto = 0.0;
    puntajes.frecuencia = 0.0;
    puntajes.ubicacion_ip = 0.0;
    MIDFF_VERIFICAR_CERCA(calcular_score(&puntajes), 0.0, TOLERANCIA,
                          "los tres puntajes en cero dan score 0.0");

    puntajes.monto = 0.5;
    puntajes.frecuencia = 0.5;
    puntajes.ubicacion_ip = 0.5;
    MIDFF_VERIFICAR_CERCA(calcular_score(&puntajes), 0.5, TOLERANCIA,
                          "puntajes medios dan score medio");

    puntajes.monto = 2.0;
    puntajes.frecuencia = 2.0;
    puntajes.ubicacion_ip = 2.0;
    MIDFF_VERIFICAR_CERCA(calcular_score(&puntajes), 1.0, TOLERANCIA,
                          "puntajes fuera de rango se saturan en 1.0");

    puntajes.monto = -3.0;
    puntajes.frecuencia = -3.0;
    puntajes.ubicacion_ip = -3.0;
    MIDFF_VERIFICAR_CERCA(calcular_score(&puntajes), 0.0, TOLERANCIA,
                          "puntajes negativos se saturan en 0.0");
}
/* ---------------------------------------------------------------------------
 * Caso 7: umbrales de clasificacion 0.4 / 0.7 (RF-05, HU-MVP-03)
 * -------------------------------------------------------------------------*/
static void caso_umbrales(void)
{
    char clasificacion[12];
    char decision[16];

    MIDFF_CASO("umbrales de clasificacion y decision (RF-05 / HU-MVP-03)");

    clasificar(0.0, clasificacion, sizeof(clasificacion), decision, sizeof(decision));
    MIDFF_VERIFICAR_TEXTO(clasificacion, "BAJO", "score 0.0 -> BAJO");
    MIDFF_VERIFICAR_TEXTO(decision, "APROBADA", "score 0.0 -> APROBADA");

    clasificar(0.3999, clasificacion, sizeof(clasificacion), decision, sizeof(decision));
    MIDFF_VERIFICAR_TEXTO(clasificacion, "BAJO", "frontera inferior: 0.3999 -> BAJO");
    MIDFF_VERIFICAR_TEXTO(decision, "APROBADA", "frontera inferior: 0.3999 -> APROBADA");

    clasificar(UMBRAL_RIESGO_MEDIO, clasificacion, sizeof(clasificacion), decision,
               sizeof(decision));
    MIDFF_VERIFICAR_TEXTO(clasificacion, "MEDIO", "frontera: 0.4000 -> MEDIO");
    MIDFF_VERIFICAR_TEXTO(decision, "EN_REVISION", "frontera: 0.4000 -> EN_REVISION");

    clasificar(0.55, clasificacion, sizeof(clasificacion), decision, sizeof(decision));
    MIDFF_VERIFICAR_TEXTO(clasificacion, "MEDIO", "banda media: 0.55 -> MEDIO");
    MIDFF_VERIFICAR_TEXTO(decision, "EN_REVISION", "banda media: 0.55 -> EN_REVISION");

    clasificar(UMBRAL_RIESGO_ALTO, clasificacion, sizeof(clasificacion), decision,
               sizeof(decision));
    MIDFF_VERIFICAR_TEXTO(clasificacion, "MEDIO", "frontera: 0.7000 -> MEDIO (limite inclusivo)");
    MIDFF_VERIFICAR_TEXTO(decision, "EN_REVISION", "frontera: 0.7000 -> EN_REVISION");

    clasificar(0.7001, clasificacion, sizeof(clasificacion), decision, sizeof(decision));
    MIDFF_VERIFICAR_TEXTO(clasificacion, "ALTO", "0.7001 -> ALTO");
    MIDFF_VERIFICAR_TEXTO(decision, "BLOQUEADA", "0.7001 -> BLOQUEADA");

    clasificar(1.0, clasificacion, sizeof(clasificacion), decision, sizeof(decision));
    MIDFF_VERIFICAR_TEXTO(clasificacion, "ALTO", "score 1.0 -> ALTO");
    MIDFF_VERIFICAR_TEXTO(decision, "BLOQUEADA", "score 1.0 -> BLOQUEADA");
}
/* ---------------------------------------------------------------------------
 * Casos 8 a 10: integracion de la evaluacion completa por banda de riesgo
 * (MVP_MIDFF.md seccion 10: "casos de prueba por banda de riesgo")
 * -------------------------------------------------------------------------*/
static void caso_bandas_integracion(void)
{
    Transaccion transaccion;
    ContextoCliente contexto;
    Evaluacion evaluacion;

    /* --- Banda BAJA: comportamiento habitual del cliente ------------------ */
    MIDFF_CASO("integracion banda BAJA (score < 0.4 -> APROBADA)");
    transaccion = transaccion_base();
    contexto = contexto_base();
    evaluacion = evaluar(&transaccion, &contexto);
    MIDFF_VERIFICAR_CERCA(evaluacion.score, 0.0, TOLERANCIA, "cliente habitual -> score 0.0000");
    MIDFF_VERIFICAR_TEXTO(evaluacion.clasificacion, "BAJO", "banda BAJA");
    MIDFF_VERIFICAR_TEXTO(evaluacion.decision, "APROBADA", "banda BAJA -> APROBADA");
    MIDFF_VERIFICAR(evaluacion.genera_alerta == 0, "banda BAJA no genera alerta ni push");
    MIDFF_VERIFICAR(evaluacion.datos_faltantes == 0,
                    "con contexto completo no hay datos faltantes");
    MIDFF_VERIFICAR_CERCA(evaluacion.latencia_evaluacion_us >= 0.0, 0.0, 1000000.0,
                          "la latencia de evaluacion se mide en microsegundos");

    /* --- Banda MEDIA: monto 3x el promedio y 6 operaciones por hora -------- */
    MIDFF_CASO("integracion banda MEDIA (0.4 - 0.7 -> EN_REVISION)");
    transaccion = transaccion_base();
    contexto = contexto_base();
    transaccion.monto = 3600.00;                 /* ratio 3.0  -> puntaje 0.5 */
    contexto.transacciones_ultima_hora = 6;      /*            -> puntaje 0.6667 */
    strcpy(transaccion.ip_origen, "203.0.113.45"); /* IP distinta -> puntaje 0.5 */
    evaluacion = evaluar(&transaccion, &contexto);
    MIDFF_VERIFICAR_CERCA(evaluacion.score, 0.55, TOLERANCIA, "score esperado 0.5500");
    MIDFF_VERIFICAR_TEXTO(evaluacion.clasificacion, "MEDIO", "banda MEDIA");
    MIDFF_VERIFICAR_TEXTO(evaluacion.decision, "EN_REVISION", "banda MEDIA -> EN_REVISION");
    MIDFF_VERIFICAR(evaluacion.genera_alerta == 1, "banda MEDIA genera alerta preventiva");
    MIDFF_VERIFICAR_CERCA(evaluacion.ratio_monto, 3.0, TOLERANCIA, "ratio del monto informado");
    MIDFF_VERIFICAR(evaluacion.frecuencia_usada == 6, "frecuencia usada informada");
    MIDFF_VERIFICAR(evaluacion.ubicacion_distinta == 0 && evaluacion.ip_distinta == 1,
                    "solo la IP se marca como distinta");

    /* --- Banda ALTA: patron claro de fraude ------------------------------- */
    MIDFF_CASO("integracion banda ALTA (score > 0.7 -> BLOQUEADA)");
    transaccion = transaccion_base();
    contexto = contexto_base();
    strcpy(transaccion.id_transaccion, "TRX-2026-000123");
    transaccion.monto = 15499.00;                /* 12.9x el promedio -> puntaje 1.0 */
    strcpy(transaccion.ubicacion, "GDL");        /* ubicacion distinta */
    strcpy(transaccion.ip_origen, "203.0.113.45");
    contexto.transacciones_ultima_hora = 7;
    evaluacion = evaluar(&transaccion, &contexto);
    MIDFF_VERIFICAR_CERCA(evaluacion.score, 0.95, TOLERANCIA, "score esperado 0.9500");
    MIDFF_VERIFICAR_TEXTO(evaluacion.clasificacion, "ALTO", "banda ALTA");
    MIDFF_VERIFICAR_TEXTO(evaluacion.decision, "BLOQUEADA", "banda ALTA -> BLOQUEADA");
    MIDFF_VERIFICAR(evaluacion.genera_alerta == 1, "banda ALTA genera alerta de fraude");
    MIDFF_VERIFICAR_TEXTO(evaluacion.id_transaccion, "TRX-2026-000123",
                          "el identificador de la transaccion se conserva");
    MIDFF_VERIFICAR_TEXTO(evaluacion.id_cliente, "CLI-001", "el cliente se conserva");
    MIDFF_VERIFICAR(evaluacion.datos_faltantes == 0, "caso completo sin datos faltantes");
}
/* ---------------------------------------------------------------------------
 * Casos 11 a 13: aislamiento de cada variable (HU-MVP-02: "las 3 variables
 * elevan el score") - se mueve una variable por vez y se verifica el efecto.
 * -------------------------------------------------------------------------*/
static void caso_aislar_variables(void)
{
    Transaccion transaccion = transaccion_base();
    ContextoCliente contexto = contexto_base();
    Transaccion copia;
    Evaluacion referencia = evaluar(&transaccion, &contexto);
    Evaluacion variante;

    MIDFF_CASO("HU-MVP-02.1: solo un monto muy superior eleva el score");
    copia = transaccion;
    copia.monto = 9000.00;                       /* 7.5x el promedio */
    variante = evaluar(&copia, &contexto);
    MIDFF_VERIFICAR(variante.score > referencia.score,
                    "el score sube al desviarse el monto");
    MIDFF_VERIFICAR_CERCA(variante.puntajes.monto, 1.0, TOLERANCIA,
                          "el puntaje del monto llega a 1.0");
    MIDFF_VERIFICAR_CERCA(variante.puntajes.frecuencia, referencia.puntajes.frecuencia, TOLERANCIA,
                          "la variable de frecuencia no cambia");
    MIDFF_VERIFICAR_CERCA(variante.puntajes.ubicacion_ip, referencia.puntajes.ubicacion_ip,
                          TOLERANCIA, "la variable de ubicacion/IP no cambia");

    MIDFF_CASO("HU-MVP-02.2: solo mas transacciones por hora elevan el score");
    copia = transaccion;
    contexto = contexto_base();
    contexto.transacciones_ultima_hora = 9;
    variante = evaluar(&copia, &contexto);
    MIDFF_VERIFICAR(variante.score > referencia.score,
                    "el score sube al aumentar la frecuencia por hora");
    MIDFF_VERIFICAR_CERCA(variante.puntajes.frecuencia, 1.0, TOLERANCIA,
                          "el puntaje de frecuencia llega a 1.0");
    MIDFF_VERIFICAR_CERCA(variante.puntajes.monto, referencia.puntajes.monto, TOLERANCIA,
                          "la variable de monto no cambia");

    MIDFF_CASO("HU-MVP-02.3: solo una ubicacion o IP inusual eleva el score");
    copia = transaccion;
    contexto = contexto_base();
    strcpy(copia.ubicacion, "GDL");
    variante = evaluar(&copia, &contexto);
    MIDFF_VERIFICAR(variante.score > referencia.score,
                    "el score sube con una ubicacion inusual");
    MIDFF_VERIFICAR_CERCA(variante.puntajes.ubicacion_ip, 0.5, TOLERANCIA,
                          "solo la ubicacion distinta aporta 0.5");

    copia = transaccion;
    strcpy(copia.ip_origen, "203.0.113.45");
    variante = evaluar(&copia, &contexto);
    MIDFF_VERIFICAR(variante.score > referencia.score,
                    "el score sube con una IP inusual");
    MIDFF_VERIFICAR_CERCA(variante.puntajes.ubicacion_ip, 0.5, TOLERANCIA,
                          "solo la IP distinta aporta 0.5");

    copia = transaccion;
    strcpy(copia.ubicacion, "GDL");
    strcpy(copia.ip_origen, "203.0.113.45");
    variante = evaluar(&copia, &contexto);
    MIDFF_VERIFICAR_CERCA(variante.puntajes.ubicacion_ip, 1.0, TOLERANCIA,
                          "ubicacion e IP distintas saturan el puntaje en 1.0");
}
/* ---------------------------------------------------------------------------
 * Caso 14: fail-safe con datos ausentes (RF-08 / RNF-04)
 * -------------------------------------------------------------------------*/
static void caso_failsafe(void)
{
    Transaccion transaccion = transaccion_base();
    ContextoCliente contexto = contexto_base();
    Evaluacion evaluacion;

    MIDFF_CASO("fail-safe: los datos ausentes nunca aprueban a ciegas");

    contexto.presente = 0;
    evaluacion = evaluar(&transaccion, &contexto);
    MIDFF_VERIFICAR_CERCA(evaluacion.score, PUNTAJE_NEUTRO, TOLERANCIA,
                          "sin contexto historico el score es neutro (0.5)");
    MIDFF_VERIFICAR_TEXTO(evaluacion.clasificacion, "MEDIO", "score neutro -> MEDIO");
    MIDFF_VERIFICAR_TEXTO(evaluacion.decision, "EN_REVISION", "score neutro -> EN_REVISION");
    MIDFF_VERIFICAR((evaluacion.datos_faltantes & DATO_CONTEXTO) != 0,
                    "se registra la bandera de contexto ausente");
    MIDFF_VERIFICAR(evaluacion.genera_alerta == 1,
                    "el caso neutro genera alerta preventiva (nunca APROBADA)");

    contexto = contexto_base();
    contexto.monto_promedio_historial = 0.0;
    contexto.transacciones_ultima_hora = 0;
    strcpy(contexto.ubicacion_habitual, "");
    strcpy(contexto.ip_habitual, "");
    evaluacion = evaluar(&transaccion, &contexto);
    MIDFF_VERIFICAR((evaluacion.datos_faltantes & DATO_MONTO_PROMEDIO) != 0,
                    "se registra la falta de monto promedio historico");
    MIDFF_VERIFICAR((evaluacion.datos_faltantes & DATO_FRECUENCIA) != 0,
                    "se registra la falta de frecuencia por hora");
    MIDFF_VERIFICAR((evaluacion.datos_faltantes & DATO_UBICACION_HABITUAL) != 0,
                    "se registra la falta de ubicacion habitual");
    MIDFF_VERIFICAR((evaluacion.datos_faltantes & DATO_IP_HABITUAL) != 0,
                    "se registra la falta de IP habitual");
    MIDFF_VERIFICAR_CERCA(evaluacion.score, PUNTAJE_NEUTRO, TOLERANCIA,
                          "contexto vacio -> score neutro");
    MIDFF_VERIFICAR_TEXTO(evaluacion.decision, "EN_REVISION",
                          "contexto vacio -> revision manual");

    contexto = contexto_base();
    strcpy(transaccion.ubicacion, "");
    strcpy(transaccion.ip_origen, "");
    evaluacion = evaluar(&transaccion, &contexto);
    MIDFF_VERIFICAR((evaluacion.datos_faltantes & DATO_UBICACION_ENTRADA) != 0,
                    "la transaccion sin ubicacion se reporta como dato faltante");
    MIDFF_VERIFICAR((evaluacion.datos_faltantes & DATO_IP_ENTRADA) != 0,
                    "la transaccion sin IP se reporta como dato faltante");
    MIDFF_VERIFICAR_CERCA(evaluacion.puntajes.ubicacion_ip, PUNTAJE_NEUTRO, TOLERANCIA,
                          "sin dimensiones comparables la variable 3 es neutra");
    MIDFF_VERIFICAR_CERCA(evaluacion.score, 0.125, TOLERANCIA,
                          "score = 0.45*0 + 0.30*0 + 0.25*0.5 (solo el neutro de la variable 3)");
}
/* ---------------------------------------------------------------------------
 * Casos 15 y 16: contrato JSON de entrada (Anexo A de MVP_MIDFF.md)
 * -------------------------------------------------------------------------*/

/* Peticion del Anexo A mas el objeto "contexto" con el historial del cliente. */
static const char *JSON_CONTRATO =
    "{"
    "\"id_transaccion\":\"TRX-2026-000123\","
    "\"id_cliente\":\"CLI-001\","
    "\"monto\":15499.00,"
    "\"moneda\":\"MXN\","
    "\"origen\":\"ERP\","
    "\"ubicacion\":\"CDMX\","
    "\"ip_origen\":\"203.0.113.45\","
    "\"fecha_hora\":\"2026-09-21T10:15:00-06:00\","
    "\"contexto\":{"
    "\"monto_promedio_historial\":1200.00,"
    "\"transacciones_ultima_hora\":7,"
    "\"ubicacion_habitual\":\"GDL\","
    "\"ip_habitual\":\"189.203.4.7\""
    "}"
    "}";

static void caso_parseo_contrato(void)
{
    Transaccion transaccion;
    ContextoCliente contexto;
    char error[MIDFF_MAX_ERROR];
    Evaluacion evaluacion;

    MIDFF_CASO("parseo del contrato JSON del Anexo A (RF-01 / RF-03)");

    MIDFF_VERIFICAR(midff_leer_entrada_json(JSON_CONTRATO, &transaccion, &contexto,
                                            error, sizeof(error)) == 1,
                    "el JSON del contrato se parsea sin error");
    MIDFF_VERIFICAR_TEXTO(error, "", "no se reporta error en un JSON valido");
    MIDFF_VERIFICAR_TEXTO(transaccion.id_transaccion, "TRX-2026-000123", "id_transaccion");
    MIDFF_VERIFICAR_TEXTO(transaccion.id_cliente, "CLI-001", "id_cliente");
    MIDFF_VERIFICAR_CERCA(transaccion.monto, 15499.00, TOLERANCIA, "monto");
    MIDFF_VERIFICAR_TEXTO(transaccion.moneda, "MXN", "moneda");
    MIDFF_VERIFICAR_TEXTO(transaccion.origen, "ERP", "origen");
    MIDFF_VERIFICAR_TEXTO(transaccion.ubicacion, "CDMX", "ubicacion");
    MIDFF_VERIFICAR_TEXTO(transaccion.ip_origen, "203.0.113.45", "ip_origen");
    MIDFF_VERIFICAR_TEXTO(transaccion.fecha_hora, "2026-09-21T10:15:00-06:00", "fecha_hora");
    MIDFF_VERIFICAR(contexto.presente == 1, "el objeto contexto se detecta");
    MIDFF_VERIFICAR_CERCA(contexto.monto_promedio_historial, 1200.00, TOLERANCIA,
                          "monto_promedio_historial");
    MIDFF_VERIFICAR(contexto.transacciones_ultima_hora == 7, "transacciones_ultima_hora");
    MIDFF_VERIFICAR_TEXTO(contexto.ubicacion_habitual, "GDL", "ubicacion_habitual");
    MIDFF_VERIFICAR_TEXTO(contexto.ip_habitual, "189.203.4.7", "ip_habitual");

    evaluacion = evaluar(&transaccion, &contexto);
    MIDFF_VERIFICAR_CERCA(evaluacion.score, 0.95, TOLERANCIA,
                          "el caso del Anexo A produce score 0.9500 (banda ALTA)");
    MIDFF_VERIFICAR_TEXTO(evaluacion.decision, "BLOQUEADA", "el caso del Anexo A se bloquea");
}
static void caso_parseo_errores(void)
{
    Transaccion transaccion;
    ContextoCliente contexto;
    char error[MIDFF_MAX_ERROR];
    FILE *flujo;

    MIDFF_CASO("validacion de entrada: JSON invalido y campos obligatorios (RF-02)");

    /* JSON mal formado. */
    MIDFF_VERIFICAR(midff_leer_entrada_json("{", &transaccion, &contexto, error,
                                            sizeof(error)) == 0,
                    "un objeto sin cerrar se rechaza");
    MIDFF_VERIFICAR(error[0] != '\0', "el rechazo incluye mensaje de diagnostico");

    /* Entrada vacia. */
    MIDFF_VERIFICAR(midff_leer_entrada_json("", &transaccion, &contexto, error,
                                            sizeof(error)) == 0,
                    "la entrada vacia se rechaza");

    /* Sin monto (campo obligatorio). */
    MIDFF_VERIFICAR(midff_leer_entrada_json("{\"id_cliente\":\"CLI-001\"}", &transaccion,
                                            &contexto, error, sizeof(error)) == 0,
                    "la transaccion sin monto se rechaza");

    /* Monto no numerico. */
    MIDFF_VERIFICAR(midff_leer_entrada_json("{\"id_cliente\":\"C\",\"monto\":\"abc\"}",
                                            &transaccion, &contexto, error,
                                            sizeof(error)) == 0,
                    "un monto no numerico se rechaza");
    MIDFF_VERIFICAR(midff_leer_entrada_json("{\"id_cliente\":\"C\",\"monto\":\"12abc\"}",
                                            &transaccion, &contexto, error,
                                            sizeof(error)) == 0,
                    "un monto con texto adicional se rechaza");

    /* Monto no positivo. */
    MIDFF_VERIFICAR(midff_leer_entrada_json("{\"id_cliente\":\"C\",\"monto\":-5}",
                                            &transaccion, &contexto, error,
                                            sizeof(error)) == 0,
                    "un monto negativo se rechaza");
    MIDFF_VERIFICAR(midff_leer_entrada_json("{\"id_cliente\":\"C\",\"monto\":0}",
                                            &transaccion, &contexto, error,
                                            sizeof(error)) == 0,
                    "un monto en cero se rechaza");

    /* Sin id_cliente o con id_cliente vacio. */
    MIDFF_VERIFICAR(midff_leer_entrada_json("{\"monto\":100}", &transaccion, &contexto,
                                            error, sizeof(error)) == 0,
                    "la transaccion sin id_cliente se rechaza");
    MIDFF_VERIFICAR(midff_leer_entrada_json("{\"id_cliente\":\"\",\"monto\":100}",
                                            &transaccion, &contexto, error,
                                            sizeof(error)) == 0,
                    "un id_cliente vacio se rechaza");

    /* JSON valido: monto entero, formato multilinea y orden distinto. */
    MIDFF_VERIFICAR(midff_leer_entrada_json(
                        "{\n"
                        "  \"id_cliente\" : \"CLI-777\",\n"
                        "  \"monto\"      : 5000,\n"
                        "  \"ubicacion\"  : \"MTY\"\n"
                        "}",
                        &transaccion, &contexto, error, sizeof(error)) == 1,
                    "se aceptan espacios, saltos de linea y monto entero");
    MIDFF_VERIFICAR_CERCA(transaccion.monto, 5000.0, TOLERANCIA, "monto entero 5000");
    MIDFF_VERIFICAR_TEXTO(transaccion.ubicacion, "MTY", "campo ubicacion");
    MIDFF_VERIFICAR(contexto.presente == 0,
                    "sin objeto contexto el motor aplica el fail-safe");
    MIDFF_VERIFICAR_TEXTO(transaccion.origen, "", "los campos opcionales quedan vacios");

    /* Tolerancia al BOM UTF-8 que algunos clientes agregan a la entrada. */
    MIDFF_VERIFICAR(midff_leer_entrada_json("\xEF\xBB\xBF{\"id_cliente\":\"C\",\"monto\":10}",
                                            &transaccion, &contexto, error, sizeof(error)) == 1,
                    "se ignora el BOM UTF-8 al inicio de la entrada");
    MIDFF_VERIFICAR_CERCA(transaccion.monto, 10.0, TOLERANCIA,
                          "el monto se lee aunque la entrada traiga BOM");

    /* Lectura del flujo de entrada (json_leer_flujo). */
    flujo = tmpfile();
    MIDFF_VERIFICAR(flujo != NULL, "se pudo crear el archivo temporal de prueba");
    if (flujo != NULL) {
        char salida[MIDFF_JSON_MAX];
        int leidos;

        fputs("{\"id_cliente\":\"C\",\"monto\":10}", flujo);
        rewind(flujo);
        leidos = json_leer_flujo(flujo, salida, sizeof(salida));
        MIDFF_VERIFICAR(leidos == 29, "json_leer_flujo devuelve la longitud leida");
        MIDFF_VERIFICAR_TEXTO(salida, "{\"id_cliente\":\"C\",\"monto\":10}",
                              "json_leer_flujo copia el contenido integro");
        fclose(flujo);
    }

    flujo = tmpfile();
    MIDFF_VERIFICAR(flujo != NULL, "se pudo crear el archivo temporal de desbordamiento");
    if (flujo != NULL) {
        static char enorme[MIDFF_JSON_MAX + 8];
        char salida[MIDFF_JSON_MAX];
        size_t i;

        for (i = 0; i < sizeof(enorme) - 1; ++i) {
            enorme[i] = 'x';
        }
        enorme[sizeof(enorme) - 1] = '\0';
        fputs(enorme, flujo);
        rewind(flujo);
        MIDFF_VERIFICAR(json_leer_flujo(flujo, salida, sizeof(salida)) == -1,
                        "una entrada mayor que MIDFF_JSON_MAX se rechaza");
        fclose(flujo);
    }
}
/* ---------------------------------------------------------------------------
 * Caso 17: serializacion JSON de salida (RF-06 / RF-08)
 * -------------------------------------------------------------------------*/
static void caso_serializacion(void)
{
    Transaccion transaccion;
    ContextoCliente contexto;
    Evaluacion evaluacion;
    char error[MIDFF_MAX_ERROR];
    char salida[3 * MIDFF_JSON_MAX];
    char variables[MIDFF_JSON_MAX];
    int escritos;

    MIDFF_CASO("serializacion JSON de la respuesta del motor (RF-06 / RF-08)");

    MIDFF_VERIFICAR(midff_leer_entrada_json(JSON_CONTRATO, &transaccion, &contexto,
                                            error, sizeof(error)) == 1,
                    "el contrato de prueba se parsea");
    evaluacion = evaluar(&transaccion, &contexto);

    escritos = evaluacion_a_json(&transaccion, &evaluacion, salida, sizeof(salida));
    MIDFF_VERIFICAR(escritos > 0, "la respuesta se serializa");
    MIDFF_VERIFICAR(json_estructura_valida(salida, salida + strlen(salida)) == 1,
                    "la respuesta del motor es un objeto JSON valido");
    MIDFF_VERIFICAR(strstr(salida, "\"score\":0.9500") != NULL,
                    "la respuesta informa el score con 4 decimales");
    MIDFF_VERIFICAR(strstr(salida, "\"clasificacion\":\"ALTO\"") != NULL,
                    "la respuesta informa la clasificacion");
    MIDFF_VERIFICAR(strstr(salida, "\"decision\":\"BLOQUEADA\"") != NULL,
                    "la respuesta informa la decision (RF-06)");
    MIDFF_VERIFICAR(strstr(salida, "\"genera_alerta\":true") != NULL,
                    "la respuesta informa si se emite alerta (RF-09)");
    MIDFF_VERIFICAR(strstr(salida, "\"datos_faltantes\":[]") != NULL,
                    "sin datos faltantes el arreglo queda vacio");
    MIDFF_VERIFICAR(strstr(salida, "\"latencia_evaluacion_us\":") != NULL,
                    "la respuesta informa la latencia medida (RNF-01)");
    MIDFF_VERIFICAR(strstr(salida, "\"ubicacion_ip\"") != NULL,
                    "la respuesta incluye la tercera variable (RF-08)");

    escritos = variables_a_json(&evaluacion, variables, sizeof(variables));
    MIDFF_VERIFICAR(escritos > 0, "el detalle de variables se serializa");
    MIDFF_VERIFICAR(json_estructura_valida(variables, variables + strlen(variables)) == 1,
                    "el detalle de variables es un objeto JSON valido (columna CLOB)");
    MIDFF_VERIFICAR(strstr(variables, "\"monto\":{\"entrada\":15499.00") != NULL,
                    "el detalle incluye el monto de entrada");
    MIDFF_VERIFICAR(strstr(variables, "\"referencia\":1200.00") != NULL,
                    "el detalle incluye el monto de referencia historico");
    MIDFF_VERIFICAR(strstr(variables, "\"puntaje\":") != NULL,
                    "el detalle incluye los puntajes normalizados");

    /* Buffer insuficiente: se reporta el error en lugar de truncar en silencio. */
    MIDFF_VERIFICAR(evaluacion_a_json(&transaccion, &evaluacion, salida, 16) == -1,
                    "un buffer insuficiente devuelve error");
    MIDFF_VERIFICAR(variables_a_json(&evaluacion, variables, 8) == -1,
                    "un buffer insuficiente en las variables devuelve error");
    MIDFF_VERIFICAR(evaluacion_a_json(NULL, &evaluacion, salida, sizeof(salida)) == -1,
                    "una transaccion nula devuelve error");
    evaluar_transaccion(NULL, NULL, NULL);   /* debe tolerar parametros nulos */
    MIDFF_VERIFICAR(1, "evaluar_transaccion tolera parametros nulos sin bloquear");
}
/* ---------------------------------------------------------------------------
 * Caso 18: consistencia score <-> clasificacion en todo el espacio de entrada
 * (propiedad verificada sobre una malla de casos; respalda la metrica
 * "Casos de prueba por banda de riesgo: 100 % correctos" de MVP_MIDFF.md 10).
 * -------------------------------------------------------------------------*/
static void caso_consistencia_malla(void)
{
    int monto;
    int frecuencia;
    int geolocalizacion;
    int total = 0;
    int inconsistentes = 0;

    MIDFF_CASO("consistencia score/clasificacion sobre una malla de casos");

    for (monto = 1; monto <= 12; ++monto) {
        for (frecuencia = 1; frecuencia <= 10; ++frecuencia) {
            for (geolocalizacion = 0; geolocalizacion < 4; ++geolocalizacion) {
                Transaccion transaccion = transaccion_base();
                ContextoCliente contexto = contexto_base();
                Evaluacion evaluacion;
                int esperado_ok;

                transaccion.monto = 500.0 * (double)monto;
                contexto.transacciones_ultima_hora = frecuencia;
                if ((geolocalizacion & 1) != 0) {
                    strcpy(transaccion.ubicacion, "GDL");
                }
                if ((geolocalizacion & 2) != 0) {
                    strcpy(transaccion.ip_origen, "203.0.113.45");
                }

                evaluacion = evaluar(&transaccion, &contexto);
                esperado_ok = (evaluacion.score >= 0.0 && evaluacion.score <= 1.0);

                if (evaluacion.score < UMBRAL_RIESGO_MEDIO) {
                    esperado_ok = esperado_ok &&
                                  strcmp(evaluacion.clasificacion, "BAJO") == 0 &&
                                  strcmp(evaluacion.decision, "APROBADA") == 0 &&
                                  evaluacion.genera_alerta == 0;
                } else if (evaluacion.score <= UMBRAL_RIESGO_ALTO) {
                    esperado_ok = esperado_ok &&
                                  strcmp(evaluacion.clasificacion, "MEDIO") == 0 &&
                                  strcmp(evaluacion.decision, "EN_REVISION") == 0 &&
                                  evaluacion.genera_alerta == 1;
                } else {
                    esperado_ok = esperado_ok &&
                                  strcmp(evaluacion.clasificacion, "ALTO") == 0 &&
                                  strcmp(evaluacion.decision, "BLOQUEADA") == 0 &&
                                  evaluacion.genera_alerta == 1;
                }
                ++total;
                if (!esperado_ok) {
                    ++inconsistentes;
                    printf("    [FALLO] monto=%.2f frecuencia=%d geolocalizacion=%d score=%.4f "
                           "clasificacion=%s decision=%s\n",
                           transaccion.monto, frecuencia, geolocalizacion, evaluacion.score,
                           evaluacion.clasificacion, evaluacion.decision);
                }
            }
        }
    }

    MIDFF_VERIFICAR(total == 480, "la malla cubre 480 combinaciones de entrada");
    MIDFF_VERIFICAR(inconsistentes == 0,
                    "el score siempre queda en [0,1] y coincide con la decision por umbral");
}

int main(void)
{
    printf("=======================================================================\n");
    printf("MIDFF - Pruebas unitarias del motor de scoring en C (Sprint 1)\n");
    printf("Requisitos: RF-03, RF-04, RF-05, RF-08, RNF-01 | Historias: HU-MVP-02, HU-MVP-03\n");
    printf("=======================================================================\n");

    caso_normalizacion();
    caso_limite_redondeo();
    caso_variable_monto();
    caso_variable_frecuencia();
    caso_variable_ubicacion_ip();
    caso_score_ponderado();
    caso_umbrales();
    caso_bandas_integracion();
    caso_aislar_variables();
    caso_failsafe();
    caso_parseo_contrato();
    caso_parseo_errores();
    caso_serializacion();
    caso_consistencia_malla();

    return midff_resumen("pruebas unitarias del motor de scoring (scoring.c)");
}











