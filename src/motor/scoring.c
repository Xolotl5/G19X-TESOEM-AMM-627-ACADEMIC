/* ============================================================================
 * MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
 * Componente 1: motor de scoring en C (ultra baja latencia) - Sprint 1
 * Archivo: src/motor/scoring.c
 * Requisitos: RF-03, RF-04, RF-05, RF-08, RNF-01 | Historias: HU-MVP-02, HU-MVP-03
 *
 * INVOCACION (RF-03, tal como la hara la API REST en el Sprint 2):
 *     type peticion.json | src\motor\bin\midff-scoring.exe
 *
 * ENTRADA (stdin): objeto JSON del Anexo A de MVP_MIDFF.md (peticion de
 * POST /api/v1/transacciones) mas el objeto opcional "contexto" con el
 * historial del cliente. Ejemplo:
 *   {
 *     "id_transaccion": "TRX-2026-000123", "id_cliente": "CLI-001",
 *     "monto": 15499.00, "moneda": "MXN", "origen": "ERP",
 *     "ubicacion": "CDMX", "ip_origen": "203.0.113.45",
 *     "fecha_hora": "2026-09-21T10:15:00-06:00",
 *     "contexto": {
 *       "monto_promedio_historial": 1200.00,
 *       "transacciones_ultima_hora": 7,
 *       "ubicacion_habitual": "GDL",
 *       "ip_habitual": "189.203.4.7"
 *     }
 *   }
 *
 * SALIDA (stdout): una linea JSON con el score (0.0 - 1.0), la clasificacion,
 * la decision, el detalle de las 3 variables y las latencias medidas.
 *
 * ERRORES: JSON invalido o sin campos obligatorios -> JSON de error por stderr
 * y codigo de salida 2 (la API responde HTTP 400, RF-02 simplificado).
 *
 * NOTA DE COMPILACION: main() se excluye definiendo MIDFF_SIN_MAIN, lo que
 * permite enlazar este archivo dentro de las suites de pruebas unitarias y de
 * latencia (src/motor/pruebas/). build.ps1 aplica esa bandera a las pruebas.
 * ==========================================================================*/

#include "scoring.h"
#include "json_io.h"
#include "cronometro.h"

#include <math.h>
#include <stdio.h>
#include <string.h>

/* ---------------------------------------------------------------------------
 * Utilidades internas
 * -------------------------------------------------------------------------*/

/* Copia segura de cadenas (siempre termina en '\0'). */
static void copiar_cadena(char *destino, size_t capacidad, const char *origen)
{
    size_t longitud;

    if (destino == NULL || capacidad == 0) {
        return;
    }
    if (origen == NULL) {
        destino[0] = '\0';
        return;
    }
    longitud = strlen(origen);
    if (longitud >= capacidad) {
        longitud = capacidad - 1;
    }
    memcpy(destino, origen, longitud);
    destino[longitud] = '\0';
}

/* Compara dos cadenas ignorando mayusculas y espacios extremos.
 * Se usa para decidir si la ubicacion o la IP de la transaccion difieren de las
 * habituales del cliente (variable 3 del RF-04). */
static int cadenas_iguales(const char *a, const char *b)
{
    size_t largo_a;
    size_t largo_b;
    size_t i;

    if (a == NULL || b == NULL) {
        return 0;
    }
    while (*a == ' ' || *a == '\t') {
        ++a;
    }
    while (*b == ' ' || *b == '\t') {
        ++b;
    }
    largo_a = strlen(a);
    largo_b = strlen(b);
    while (largo_a > 0 && (a[largo_a - 1] == ' ' || a[largo_a - 1] == '\t')) {
        --largo_a;
    }
    while (largo_b > 0 && (b[largo_b - 1] == ' ' || b[largo_b - 1] == '\t')) {
        --largo_b;
    }
    if (largo_a != largo_b || largo_a == 0) {
        return 0;
    }
    for (i = 0; i < largo_a; ++i) {
        char ca = a[i];
        char cb = b[i];

        if (ca >= 'a' && ca <= 'z') {
            ca = (char)(ca - ('a' - 'A'));
        }
        if (cb >= 'a' && cb <= 'z') {
            cb = (char)(cb - ('a' - 'A'));
        }
        if (ca != cb) {
            return 0;
        }
    }
    return 1;
}

/* ---------------------------------------------------------------------------
 * Calculo del score (RF-04)
 * -------------------------------------------------------------------------*/

double limitar(double valor, double minimo, double maximo)
{
    if (valor < minimo) {
        return minimo;
    }
    if (valor > maximo) {
        return maximo;
    }
    return valor;
}

double redondear(double valor, int decimales)
{
    double factor = 1.0;
    int i;

    if (decimales < 0) {
        decimales = 0;
    }
    for (i = 0; i < decimales; ++i) {
        factor *= 10.0;
    }
    return floor(valor * factor + 0.5) / factor;
}

double normalizar_rampa(double valor, double minimo, double maximo)
{
    if (maximo <= minimo) {
        return (valor >= maximo) ? 1.0 : 0.0;
    }
    if (valor <= minimo) {
        return 0.0;
    }
    if (valor >= maximo) {
        return 1.0;
    }
    return (valor - minimo) / (maximo - minimo);
}

/* Variable 1 del RF-04: desviacion del monto respecto al historial del cliente.
 * ratio = monto / monto_promedio_historial; sin historial utilizable devuelve
 * PUNTAJE_NEUTRO (fail-safe) y deja ratio en 0.0. */
double puntaje_monto(double monto, double monto_promedio_historial, double *ratio)
{
    if (ratio != NULL) {
        *ratio = 0.0;
    }
    if (monto <= 0.0 || monto_promedio_historial <= 0.0) {
        return PUNTAJE_NEUTRO;
    }
    if (ratio != NULL) {
        *ratio = monto / monto_promedio_historial;
    }
    return normalizar_rampa(monto / monto_promedio_historial, MONTO_RATIO_BAJO, MONTO_RATIO_ALTO);
}

/* Variable 2 del RF-04: frecuencia de transacciones del cliente en la ultima
 * hora (incluida la actual). Sin dato valido devuelve PUNTAJE_NEUTRO. */
double puntaje_frecuencia(int transacciones_ultima_hora)
{
    if (transacciones_ultima_hora <= 0) {
        return PUNTAJE_NEUTRO;
    }
    return normalizar_rampa((double)transacciones_ultima_hora,
                            (double)FRECUENCIA_BAJA, (double)FRECUENCIA_ALTA);
}

/* Variable 3 del RF-04: ubicacion/IP inusual.
 * Solo se compara una dimension cuando ambos datos existen; si falta alguno de
 * los dos no se penaliza (la ausencia se reporta en datos_faltantes). */
double puntaje_ubicacion_ip(const Transaccion *transaccion, const ContextoCliente *contexto,
                            int *ubicacion_distinta, int *ip_distinta)
{
    double puntaje = 0.0;
    int dimensiones_comparables = 0;

    if (ubicacion_distinta != NULL) {
        *ubicacion_distinta = 0;
    }
    if (ip_distinta != NULL) {
        *ip_distinta = 0;
    }
    if (transaccion == NULL || contexto == NULL) {
        return PUNTAJE_NEUTRO;
    }
    if (transaccion->ubicacion[0] != '\0' && contexto->ubicacion_habitual[0] != '\0') {
        ++dimensiones_comparables;
        if (!cadenas_iguales(transaccion->ubicacion, contexto->ubicacion_habitual)) {
            puntaje += PESO_DIMENSION_GEOLOCALIZACION;
            if (ubicacion_distinta != NULL) {
                *ubicacion_distinta = 1;
            }
        }
    }
    if (transaccion->ip_origen[0] != '\0' && contexto->ip_habitual[0] != '\0') {
        ++dimensiones_comparables;
        if (!cadenas_iguales(transaccion->ip_origen, contexto->ip_habitual)) {
            puntaje += PESO_DIMENSION_GEOLOCALIZACION;
            if (ip_distinta != NULL) {
                *ip_distinta = 1;
            }
        }
    }
    if (dimensiones_comparables == 0) {
        /* Sin ubicacion/IP de referencia no hay nada que comparar: se aplica el
         * fail-safe y la ausencia se reporta en datos_faltantes (RF-08). */
        return PUNTAJE_NEUTRO;
    }
    return limitar(puntaje, 0.0, 1.0);
}

double calcular_score(const PuntajesVariables *puntajes)
{
    double score;

    if (puntajes == NULL) {
        return 0.0;
    }
    score = PESO_MONTO * puntajes->monto +
            PESO_FRECUENCIA * puntajes->frecuencia +
            PESO_UBICACION_IP * puntajes->ubicacion_ip;
    return limitar(score, 0.0, 1.0);
}

/* Clasificacion y decision por umbral (RF-05):
 *   score < 0.40  -> BAJO  / APROBADA     (sin notificacion, HU-MVP-03.1)
 *   0.40 - 0.70   -> MEDIO / EN_REVISION  (alerta preventiva, HU-MVP-03.2)
 *   score > 0.70  -> ALTO  / BLOQUEADA    (alerta de fraude,   HU-MVP-03.3) */
void clasificar(double score, char *clasificacion, size_t capacidad_clasificacion,
                char *decision, size_t capacidad_decision)
{
    if (score < UMBRAL_RIESGO_MEDIO) {
        copiar_cadena(clasificacion, capacidad_clasificacion, "BAJO");
        copiar_cadena(decision, capacidad_decision, "APROBADA");
    } else if (score <= UMBRAL_RIESGO_ALTO) {
        copiar_cadena(clasificacion, capacidad_clasificacion, "MEDIO");
        copiar_cadena(decision, capacidad_decision, "EN_REVISION");
    } else {
        copiar_cadena(clasificacion, capacidad_clasificacion, "ALTO");
        copiar_cadena(decision, capacidad_decision, "BLOQUEADA");
    }
}

/* Evaluacion completa: calcula las 3 variables (RF-04), el score ponderado, la
 * clasificacion/decision (RF-05) e informa los datos faltantes (RF-08). */
void evaluar_transaccion(const Transaccion *transaccion, const ContextoCliente *contexto,
                         Evaluacion *evaluacion)
{
    Cronometro cronometro;

    if (transaccion == NULL || contexto == NULL || evaluacion == NULL) {
        return;
    }
    memset(evaluacion, 0, sizeof(*evaluacion));
    copiar_cadena(evaluacion->id_transaccion, sizeof(evaluacion->id_transaccion),
                  transaccion->id_transaccion);
    copiar_cadena(evaluacion->id_cliente, sizeof(evaluacion->id_cliente), transaccion->id_cliente);
    evaluacion->monto_entrada = transaccion->monto;
    evaluacion->monto_referencia = (contexto->presente ? contexto->monto_promedio_historial : 0.0);

    cronometro_iniciar(&cronometro);

    if (!contexto->presente) {
        /* Sin contexto historico no se puede calcular ninguna variable: se
         * aplica el fail-safe (0.50 -> MEDIO / EN_REVISION) y se registra el
         * motivo para la auditoria (RF-08). */
        evaluacion->datos_faltantes |= DATO_CONTEXTO;
        evaluacion->puntajes.monto = PUNTAJE_NEUTRO;
        evaluacion->puntajes.frecuencia = PUNTAJE_NEUTRO;
        evaluacion->puntajes.ubicacion_ip = PUNTAJE_NEUTRO;
    } else {
        if (contexto->monto_promedio_historial > 0.0 && transaccion->monto > 0.0) {
            evaluacion->puntajes.monto = puntaje_monto(transaccion->monto,
                                                      contexto->monto_promedio_historial,
                                                      &evaluacion->ratio_monto);
        } else {
            evaluacion->puntajes.monto = PUNTAJE_NEUTRO;
            evaluacion->datos_faltantes |= DATO_MONTO_PROMEDIO;
        }

        if (contexto->transacciones_ultima_hora > 0) {
            evaluacion->frecuencia_usada = contexto->transacciones_ultima_hora;
            evaluacion->puntajes.frecuencia =
                puntaje_frecuencia(contexto->transacciones_ultima_hora);
        } else {
            evaluacion->puntajes.frecuencia = PUNTAJE_NEUTRO;
            evaluacion->datos_faltantes |= DATO_FRECUENCIA;
        }

        if (transaccion->ubicacion[0] == '\0') {
            evaluacion->datos_faltantes |= DATO_UBICACION_ENTRADA;
        }
        if (contexto->ubicacion_habitual[0] == '\0') {
            evaluacion->datos_faltantes |= DATO_UBICACION_HABITUAL;
        }
        if (transaccion->ip_origen[0] == '\0') {
            evaluacion->datos_faltantes |= DATO_IP_ENTRADA;
        }
        if (contexto->ip_habitual[0] == '\0') {
            evaluacion->datos_faltantes |= DATO_IP_HABITUAL;
        }
        evaluacion->puntajes.ubicacion_ip =
            puntaje_ubicacion_ip(transaccion, contexto,
                                 &evaluacion->ubicacion_distinta, &evaluacion->ip_distinta);
    }

    evaluacion->score = redondear(limitar(calcular_score(&evaluacion->puntajes), 0.0, 1.0),
                                  DECIMALES_SCORE);
    clasificar(evaluacion->score, evaluacion->clasificacion, sizeof(evaluacion->clasificacion),
               evaluacion->decision, sizeof(evaluacion->decision));
    evaluacion->genera_alerta = (evaluacion->score >= UMBRAL_RIESGO_MEDIO) ? 1 : 0;

    cronometro_detener(&cronometro);
    evaluacion->latencia_evaluacion_us = cronometro_us(&cronometro);
}

/* ---------------------------------------------------------------------------
 * Contrato JSON: entrada (Anexo A de MVP_MIDFF.md + objeto "contexto")
 * -------------------------------------------------------------------------*/

static void anotar_error(char *error, size_t capacidad_error, const char *mensaje)
{
    copiar_cadena(error, capacidad_error, mensaje);
}

int midff_leer_entrada_json(const char *json, Transaccion *transaccion,
                            ContextoCliente *contexto, char *error, size_t capacidad_error)
{
    const char *fin;
    const char *contexto_ini = NULL;
    const char *contexto_fin = NULL;
    double monto = 0.0;
    long frecuencia = 0;

    if (error != NULL && capacidad_error > 0) {
        error[0] = '\0';
    }
    if (transaccion == NULL || contexto == NULL || json == NULL || error == NULL) {
        anotar_error(error, capacidad_error, "parametros internos invalidos");
        return 0;
    }
    memset(transaccion, 0, sizeof(*transaccion));
    memset(contexto, 0, sizeof(*contexto));

    /* Tolerancia al BOM UTF-8: algunos clientes (HTTP, PowerShell/.NET) lo
     * agregan al preparar la entrada; no es un error del contrato. */
    if (json[0] == (char)0xEF && json[1] == (char)0xBB && json[2] == (char)0xBF) {
        json += 3;
    }
    fin = json + strlen(json);
    if (json == fin) {
        anotar_error(error, capacidad_error,
                     "entrada vacia: se esperaba un objeto JSON por stdin");
        return 0;
    }
    if (!json_estructura_valida(json, fin)) {
        anotar_error(error, capacidad_error,
                     "JSON mal formado: se esperaba un objeto con llaves balanceadas");
        return 0;
    }

    /* Validacion minima del contrato (RF-02 simplificado). */
    if (!json_extraer_cadena(json, fin, "id_cliente",
                             transaccion->id_cliente, sizeof(transaccion->id_cliente)) ||
        transaccion->id_cliente[0] == '\0') {
        anotar_error(error, capacidad_error,
                     "campo obligatorio ausente o invalido: id_cliente");
        return 0;
    }
    if (!json_extraer_numero(json, fin, "monto", &monto) || monto <= 0.0) {
        anotar_error(error, capacidad_error,
                     "campo obligatorio ausente o invalido: monto (numerico mayor que cero)");
        return 0;
    }
    transaccion->monto = monto;

    /* Campos opcionales del Anexo A. */
    json_extraer_cadena(json, fin, "id_transaccion",
                        transaccion->id_transaccion, sizeof(transaccion->id_transaccion));
    json_extraer_cadena(json, fin, "moneda", transaccion->moneda, sizeof(transaccion->moneda));
    json_extraer_cadena(json, fin, "origen", transaccion->origen, sizeof(transaccion->origen));
    json_extraer_cadena(json, fin, "ubicacion",
                        transaccion->ubicacion, sizeof(transaccion->ubicacion));
    json_extraer_cadena(json, fin, "ip_origen",
                        transaccion->ip_origen, sizeof(transaccion->ip_origen));
    json_extraer_cadena(json, fin, "fecha_hora",
                        transaccion->fecha_hora, sizeof(transaccion->fecha_hora));
    json_extraer_cadena(json, fin, "canal", transaccion->canal, sizeof(transaccion->canal));

    /* Contexto historico del cliente (lo aporta la API desde Oracle DB). */
    if (json_extraer_objeto(json, fin, "contexto", &contexto_ini, &contexto_fin)) {
        contexto->presente = 1;
        json_extraer_numero(contexto_ini, contexto_fin, "monto_promedio_historial",
                            &contexto->monto_promedio_historial);
        if (json_extraer_entero(contexto_ini, contexto_fin, "transacciones_ultima_hora",
                                &frecuencia)) {
            contexto->transacciones_ultima_hora = (int)frecuencia;
        }
        json_extraer_cadena(contexto_ini, contexto_fin, "ubicacion_habitual",
                            contexto->ubicacion_habitual, sizeof(contexto->ubicacion_habitual));
        json_extraer_cadena(contexto_ini, contexto_fin, "ip_habitual",
                            contexto->ip_habitual, sizeof(contexto->ip_habitual));
    }
    return 1;
}

/* ---------------------------------------------------------------------------
 * Contrato JSON: salida del motor
 * -------------------------------------------------------------------------*/

/* Catalogo de banderas de datos faltantes -> arreglo JSON de nombres. */
static void banderas_a_json(int banderas, char *destino, size_t capacidad, size_t *pos)
{
    static const struct {
        int bandera;
        const char *nombre;
    } catalogo[] = {
        { DATO_CONTEXTO,           "contexto"                  },
        { DATO_MONTO_PROMEDIO,     "monto_promedio_historial"  },
        { DATO_FRECUENCIA,         "transacciones_ultima_hora" },
        { DATO_UBICACION_HABITUAL, "ubicacion_habitual"        },
        { DATO_IP_HABITUAL,        "ip_habitual"               },
        { DATO_UBICACION_ENTRADA,  "ubicacion"                 },
        { DATO_IP_ENTRADA,         "ip_origen"                 }
    };
    size_t total = sizeof(catalogo) / sizeof(catalogo[0]);
    size_t i;
    int primero = 1;

    json_escribir_texto(destino, capacidad, pos, "[");
    for (i = 0; i < total; ++i) {
        if ((banderas & catalogo[i].bandera) == 0) {
            continue;
        }
        if (!primero) {
            json_escribir_texto(destino, capacidad, pos, ",");
        }
        json_escribir_cadena(destino, capacidad, pos, catalogo[i].nombre);
        primero = 0;
    }
    json_escribir_texto(destino, capacidad, pos, "]");
}

/* Detalle de las 3 variables evaluadas (RF-04) con sus valores de entrada y
 * sus puntajes normalizados. Es lo que se persiste en
 * EVALUACION.variables_usadas y en AUDITORIA (RF-08). */
int variables_a_json(const Evaluacion *evaluacion, char *destino, size_t capacidad)
{
    size_t pos = 0;

    if (evaluacion == NULL || destino == NULL || capacidad == 0) {
        return -1;
    }
    destino[0] = '\0';

    json_escribir_texto(destino, capacidad, &pos, "{\"monto\":{\"entrada\":");
    json_escribir_numero(destino, capacidad, &pos, evaluacion->monto_entrada, 2);
    json_escribir_texto(destino, capacidad, &pos, ",\"referencia\":");
    json_escribir_numero(destino, capacidad, &pos, evaluacion->monto_referencia, 2);
    json_escribir_texto(destino, capacidad, &pos, ",\"ratio\":");
    json_escribir_numero(destino, capacidad, &pos, evaluacion->ratio_monto, DECIMALES_SCORE + 1);
    json_escribir_texto(destino, capacidad, &pos, ",\"puntaje\":");
    json_escribir_numero(destino, capacidad, &pos, evaluacion->puntajes.monto, DECIMALES_SCORE);
    json_escribir_texto(destino, capacidad, &pos, "},\"frecuencia\":{\"entrada\":");
    json_escribir_entero(destino, capacidad, &pos, (long)evaluacion->frecuencia_usada);
    json_escribir_texto(destino, capacidad, &pos, ",\"rango\":[");
    json_escribir_entero(destino, capacidad, &pos, (long)FRECUENCIA_BAJA);
    json_escribir_texto(destino, capacidad, &pos, ",");
    json_escribir_entero(destino, capacidad, &pos, (long)FRECUENCIA_ALTA);
    json_escribir_texto(destino, capacidad, &pos, "],\"puntaje\":");
    json_escribir_numero(destino, capacidad, &pos, evaluacion->puntajes.frecuencia, DECIMALES_SCORE);
    json_escribir_texto(destino, capacidad, &pos, "},\"ubicacion_ip\":{\"puntaje\":");
    json_escribir_numero(destino, capacidad, &pos, evaluacion->puntajes.ubicacion_ip, DECIMALES_SCORE);
    json_escribir_texto(destino, capacidad, &pos, ",\"ubicacion_distinta\":");
    json_escribir_logico(destino, capacidad, &pos, evaluacion->ubicacion_distinta);
    json_escribir_texto(destino, capacidad, &pos, ",\"ip_distinta\":");
    json_escribir_logico(destino, capacidad, &pos, evaluacion->ip_distinta);
    json_escribir_texto(destino, capacidad, &pos, "}}");

    if (pos + 1 >= capacidad) {
        return -1;              /* el buffer se agoto: salida truncada */
    }
    return (int)pos;
}

/* Respuesta completa del motor (RF-06: score, clasificacion y decision; mas el
 * detalle auditable del RF-08 y las latencias del RNF-01). */
int evaluacion_a_json(const Transaccion *transaccion, const Evaluacion *evaluacion,
                      char *destino, size_t capacidad)
{
    char variables[MIDFF_JSON_MAX];
    size_t pos = 0;

    if (transaccion == NULL || evaluacion == NULL || destino == NULL || capacidad == 0) {
        return -1;
    }
    if (variables_a_json(evaluacion, variables, sizeof(variables)) < 0) {
        return -1;
    }
    destino[0] = '\0';

    json_escribir_texto(destino, capacidad, &pos, "{\"id_transaccion\":");
    json_escribir_cadena(destino, capacidad, &pos, evaluacion->id_transaccion);
    json_escribir_texto(destino, capacidad, &pos, ",\"id_cliente\":");
    json_escribir_cadena(destino, capacidad, &pos, evaluacion->id_cliente);
    json_escribir_texto(destino, capacidad, &pos, ",\"monto\":");
    json_escribir_numero(destino, capacidad, &pos, evaluacion->monto_entrada, 2);
    json_escribir_texto(destino, capacidad, &pos, ",\"moneda\":");
    json_escribir_cadena(destino, capacidad, &pos, transaccion->moneda);
    json_escribir_texto(destino, capacidad, &pos, ",\"origen\":");
    json_escribir_cadena(destino, capacidad, &pos, transaccion->origen);
    json_escribir_texto(destino, capacidad, &pos, ",\"score\":");
    json_escribir_numero(destino, capacidad, &pos, evaluacion->score, DECIMALES_SCORE);
    json_escribir_texto(destino, capacidad, &pos, ",\"clasificacion\":");
    json_escribir_cadena(destino, capacidad, &pos, evaluacion->clasificacion);
    json_escribir_texto(destino, capacidad, &pos, ",\"decision\":");
    json_escribir_cadena(destino, capacidad, &pos, evaluacion->decision);
    json_escribir_texto(destino, capacidad, &pos, ",\"genera_alerta\":");
    json_escribir_logico(destino, capacidad, &pos, evaluacion->genera_alerta);
    json_escribir_texto(destino, capacidad, &pos, ",\"variables\":");
    json_escribir_texto(destino, capacidad, &pos, variables);
    json_escribir_texto(destino, capacidad, &pos, ",\"pesos\":{\"monto\":");
    json_escribir_numero(destino, capacidad, &pos, PESO_MONTO, DECIMALES_SCORE);
    json_escribir_texto(destino, capacidad, &pos, ",\"frecuencia\":");
    json_escribir_numero(destino, capacidad, &pos, PESO_FRECUENCIA, DECIMALES_SCORE);
    json_escribir_texto(destino, capacidad, &pos, ",\"ubicacion_ip\":");
    json_escribir_numero(destino, capacidad, &pos, PESO_UBICACION_IP, DECIMALES_SCORE);
    json_escribir_texto(destino, capacidad, &pos, "},\"umbrales\":{\"medio\":");
    json_escribir_numero(destino, capacidad, &pos, UMBRAL_RIESGO_MEDIO, DECIMALES_SCORE);
    json_escribir_texto(destino, capacidad, &pos, ",\"alto\":");
    json_escribir_numero(destino, capacidad, &pos, UMBRAL_RIESGO_ALTO, DECIMALES_SCORE);
    json_escribir_texto(destino, capacidad, &pos, "},\"latencia_evaluacion_us\":");
    json_escribir_numero(destino, capacidad, &pos, evaluacion->latencia_evaluacion_us, 3);
    json_escribir_texto(destino, capacidad, &pos, ",\"latencia_parseo_us\":");
    json_escribir_numero(destino, capacidad, &pos, evaluacion->latencia_parseo_us, 3);
    json_escribir_texto(destino, capacidad, &pos, ",\"datos_faltantes\":");
    banderas_a_json(evaluacion->datos_faltantes, destino, capacidad, &pos);
    json_escribir_texto(destino, capacidad, &pos, "}");

    if (pos + 1 >= capacidad) {
        return -1;
    }
    return (int)pos;
}

/* ---------------------------------------------------------------------------
 * Punto de entrada (RF-03): lectura por stdin, evaluacion y salida por stdout.
 * Se excluye cuando MIDFF_SIN_MAIN esta definido (suites de pruebas).
 * -------------------------------------------------------------------------*/
#ifndef MIDFF_SIN_MAIN

/* Emite el diagnostico en formato JSON por stderr (stdout queda reservado para
 * la evaluacion; la API lo traduce a HTTP 400, RF-02). */
static void emitir_error(const char *mensaje)
{
    char salida[MIDFF_MAX_ERROR + 96];
    size_t pos = 0;

    salida[0] = '\0';
    json_escribir_texto(salida, sizeof(salida), &pos, "{\"componente\":\"midff-scoring\",\"error\":");
    json_escribir_cadena(salida, sizeof(salida), &pos, mensaje);
    json_escribir_texto(salida, sizeof(salida), &pos, "}");
    fprintf(stderr, "%s\n", salida);
}

int main(void)
{
    static char entrada[MIDFF_JSON_MAX];
    static char salida[3 * MIDFF_JSON_MAX];
    Transaccion transaccion;
    ContextoCliente contexto;
    Evaluacion evaluacion;
    Cronometro cronometro;
    char error[MIDFF_MAX_ERROR];
    int leidos;
    int escritos;

    leidos = json_leer_flujo(stdin, entrada, sizeof(entrada));
    if (leidos < 0) {
        emitir_error("entrada ilegible o mayor que el limite permitido (8192 bytes)");
        return MIDFF_CODIGO_ERROR_INTERNO;
    }

    cronometro_iniciar(&cronometro);
    if (!midff_leer_entrada_json(entrada, &transaccion, &contexto, error, sizeof(error))) {
        cronometro_detener(&cronometro);
        emitir_error(error);
        return MIDFF_CODIGO_ENTRADA_INVALIDA;
    }
    cronometro_detener(&cronometro);

    evaluar_transaccion(&transaccion, &contexto, &evaluacion);
    evaluacion.latencia_parseo_us = cronometro_us(&cronometro);

    escritos = evaluacion_a_json(&transaccion, &evaluacion, salida, sizeof(salida));
    if (escritos < 0) {
        emitir_error("buffer de salida insuficiente para serializar la evaluacion");
        return MIDFF_CODIGO_ERROR_INTERNO;
    }
    fputs(salida, stdout);
    fputc('\n', stdout);
    fflush(stdout);
    return MIDFF_CODIGO_OK;
}

#endif /* MIDFF_SIN_MAIN */






