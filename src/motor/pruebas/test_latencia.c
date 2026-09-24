/* ============================================================================
 * MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
 * Archivo: src/motor/pruebas/test_latencia.c
 * Suite de MEDICION DE LATENCIA del motor C en MICROSEGUNDOS (Sprint 1).
 * Requisitos: RNF-01 (evaluacion en microsegundos) y metrica "Latencia de
 *   evaluacion del motor C: orden de microsegundos" de MVP_MIDFF.md seccion 10.
 *
 * Medicion con <time.h> (via src/motor/cronometro.c):
 *   - Windows: QueryPerformanceCounter (resolucion de 0.1 us).
 *   - POSIX  : clock_gettime(CLOCK_MONOTONIC) de <time.h>.
 * Se reporta ademas el costo del propio cronometro para que la medicion sea
 * auditable, y el tiempo de CPU con clock() como dato complementario.
 *
 * Escenarios medidos (una muestra por iteracion):
 *   0. Costo del cronometro (linea base de la instrumentacion).
 *   1. Evaluacion pura: evaluar_transaccion() sobre datos ya en memoria.
 *   2. Ruta completa de la API: parseo del JSON + evaluacion (RF-03).
 *
 * Uso: midff-test-latencia.exe [iteraciones]   (por omision 200000)
 * ==========================================================================*/

#include "../cronometro.h"
#include "../scoring.h"
#include "marco_pruebas.h"

#include <math.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#define ITERACIONES_POR_OMISION 200000
#define CALENTAMIENTO           2000

/* Objetivos del RNF-01 (el motor debe trabajar en el orden de microsegundos). */
#define OBJETIVO_MEDIANA_US 100.0    /* percentil 50 */
#define OBJETIVO_P95_US    1000.0    /* percentil 95 */

#define JSON_MEDICION                                                         \
    "{\"id_transaccion\":\"TRX-2026-000123\",\"id_cliente\":\"CLI-001\","     \
    "\"monto\":15499.00,\"moneda\":\"MXN\",\"origen\":\"ERP\","               \
    "\"ubicacion\":\"CDMX\",\"ip_origen\":\"203.0.113.45\","                  \
    "\"fecha_hora\":\"2026-09-21T10:15:00-06:00\","                           \
    "\"contexto\":{\"monto_promedio_historial\":1200.00,"                     \
    "\"transacciones_ultima_hora\":7,\"ubicacion_habitual\":\"GDL\","         \
    "\"ip_habitual\":\"189.203.4.7\"}}"

typedef struct {
    double minimo;
    double media;
    double mediana;
    double p95;
    double p99;
    double maximo;
    double desviacion;
    double suma_total_us;
} Estadisticas;

static int comparar_muestras(const void *a, const void *b)
{
    double valor_a = *(const double *)a;
    double valor_b = *(const double *)b;

    if (valor_a < valor_b) {
        return -1;
    }
    if (valor_a > valor_b) {
        return 1;
    }
    return 0;
}

static Estadisticas calcular_estadisticas(double *muestras, int total)
{
    Estadisticas estadisticas;
    double suma = 0.0;
    double suma_cuadrados = 0.0;
    int i;

    memset(&estadisticas, 0, sizeof(estadisticas));
    if (muestras == NULL || total <= 0) {
        return estadisticas;
    }
    qsort(muestras, (size_t)total, sizeof(double), comparar_muestras);
    for (i = 0; i < total; ++i) {
        suma += muestras[i];
        suma_cuadrados += muestras[i] * muestras[i];
    }
    estadisticas.minimo = muestras[0];
    estadisticas.maximo = muestras[total - 1];
    estadisticas.media = suma / (double)total;
    estadisticas.mediana = muestras[total / 2];
    estadisticas.p95 = muestras[(int)((double)total * 0.95) < total
                                ? (int)((double)total * 0.95) : total - 1];
    estadisticas.p99 = muestras[(int)((double)total * 0.99) < total
                                ? (int)((double)total * 0.99) : total - 1];
    estadisticas.suma_total_us = suma;
    estadisticas.desviacion =
        sqrt((suma_cuadrados / (double)total) -
             (estadisticas.media * estadisticas.media));
    return estadisticas;
}

static void imprimir_estadisticas(const char *escenario, const Estadisticas *estadisticas,
                                  int total)
{
    double por_segundo = (estadisticas->suma_total_us > 0.0)
                             ? ((double)total * 1000000.0) / estadisticas->suma_total_us
                             : 0.0;

    printf("\n  %s (%d iteraciones)\n", escenario, total);
    printf("  --------------------------------------------------------------------\n");
    printf("  minimo   : %10.3f us      mediana : %10.3f us\n",
           estadisticas->minimo, estadisticas->mediana);
    printf("  media    : %10.3f us      desv.   : %10.3f us\n",
           estadisticas->media, estadisticas->desviacion);
    printf("  p95      : %10.3f us      p99     : %10.3f us\n",
           estadisticas->p95, estadisticas->p99);
    printf("  maximo   : %10.3f us      ops/seg : %10.0f\n",
           estadisticas->maximo, por_segundo);
}

static double *reservar_muestras(int total)
{
    return (double *)malloc(sizeof(double) * (size_t)total);
}

/* ---------------------------------------------------------------------------
 * Escenarios de medicion
 * -------------------------------------------------------------------------*/

/* Escenario 0: lineas base de la instrumentacion (sin trabajo a medir). */
static void medir_cronometro(double *muestras, int total)
{
    Cronometro cronometro;
    int i;

    for (i = 0; i < CALENTAMIENTO; ++i) {
        cronometro_iniciar(&cronometro);
        cronometro_detener(&cronometro);
    }
    for (i = 0; i < total; ++i) {
        cronometro_iniciar(&cronometro);
        cronometro_detener(&cronometro);
        muestras[i] = cronometro_us(&cronometro);
    }
}

/* Escenario 1: evaluacion pura del motor (las 3 variables y la clasificacion). */
static void medir_evaluacion(double *muestras, int total, double *suma_control)
{
    Transaccion transaccion;
    ContextoCliente contexto;
    Evaluacion evaluacion;
    Cronometro cronometro;
    int i;

    memset(&transaccion, 0, sizeof(transaccion));
    strcpy(transaccion.id_cliente, "CLI-001");
    transaccion.monto = 15499.00;
    strcpy(transaccion.origen, "ERP");
    strcpy(transaccion.ubicacion, "CDMX");
    strcpy(transaccion.ip_origen, "203.0.113.45");

    memset(&contexto, 0, sizeof(contexto));
    contexto.presente = 1;
    contexto.monto_promedio_historial = 1200.00;
    contexto.transacciones_ultima_hora = 7;
    strcpy(contexto.ubicacion_habitual, "GDL");
    strcpy(contexto.ip_habitual, "189.203.4.7");

    for (i = 0; i < CALENTAMIENTO; ++i) {
        evaluar_transaccion(&transaccion, &contexto, &evaluacion);
    }
    for (i = 0; i < total; ++i) {
        cronometro_iniciar(&cronometro);
        evaluar_transaccion(&transaccion, &contexto, &evaluacion);
        cronometro_detener(&cronometro);
        muestras[i] = cronometro_us(&cronometro);
        *suma_control += evaluacion.score;
    }
}

/* Escenario 2: ruta completa que usara la API en el Sprint 2 (RF-03). */
static void medir_ruta_completa(double *muestras, int total, double *suma_control)
{
    Transaccion transaccion;
    ContextoCliente contexto;
    Evaluacion evaluacion;
    char error[MIDFF_MAX_ERROR];
    Cronometro cronometro;
    int fallos = 0;
    int i;

    for (i = 0; i < CALENTAMIENTO; ++i) {
        if (midff_leer_entrada_json(JSON_MEDICION, &transaccion, &contexto, error,
                                    sizeof(error))) {
            evaluar_transaccion(&transaccion, &contexto, &evaluacion);
        }
    }
    for (i = 0; i < total; ++i) {
        cronometro_iniciar(&cronometro);
        if (!midff_leer_entrada_json(JSON_MEDICION, &transaccion, &contexto, error,
                                     sizeof(error))) {
            ++fallos;
        }
        evaluar_transaccion(&transaccion, &contexto, &evaluacion);
        cronometro_detener(&cronometro);
        muestras[i] = cronometro_us(&cronometro);
        *suma_control += evaluacion.score;
    }
    if (fallos > 0) {
        printf("\n  [AVISO] %d de %d iteraciones no pudieron parsear el JSON de medicion\n",
               fallos, total);
    }
}
int main(int argc, char **argv)
{
    int total = ITERACIONES_POR_OMISION;
    double *muestras;
    double suma_control = 0.0;
    double cpu_escenario_us;
    Estadisticas est_cronometro;
    Estadisticas est_evaluacion;
    Estadisticas est_ruta;
    Cronometro cronometro;

    if (argc > 1) {
        total = atoi(argv[1]);
    }
    if (total < 1000) {
        total = 1000;
    }
    if (total > 20000000) {
        total = 20000000;
    }

    printf("=======================================================================\n");
    printf("MIDFF - Medicion de latencia del motor C en microsegundos (Sprint 1)\n");
    printf("Requisitos: RNF-01 | Metrica: MVP_MIDFF.md seccion 10\n");
    printf("Fuente de tiempo : %s\n", cronometro_fuente());
    printf("Resolucion       : %.3f us\n", cronometro_resolucion_us());
    printf("Iteraciones      : %d (calentamiento previo: %d)\n", total, CALENTAMIENTO);
    printf("=======================================================================\n");

    muestras = reservar_muestras(total);
    if (muestras == NULL) {
        fprintf(stderr, "ERROR: sin memoria para %d muestras\n", total);
        return MIDFF_CODIGO_ERROR_INTERNO;
    }

    medir_cronometro(muestras, total);
    est_cronometro = calcular_estadisticas(muestras, total);
    imprimir_estadisticas("Escenario 0: costo de la instrumentacion (cronometro)",
                          &est_cronometro, total);

    cronometro_iniciar(&cronometro);
    medir_evaluacion(muestras, total, &suma_control);
    cronometro_detener(&cronometro);
    cpu_escenario_us = cronometro_cpu_us(&cronometro);
    est_evaluacion = calcular_estadisticas(muestras, total);
    imprimir_estadisticas("Escenario 1: evaluacion pura del motor (3 variables + umbrales)",
                          &est_evaluacion, total);
    printf("  tiempo de CPU del escenario 1: %.3f us por iteracion (clock() de time.h)\n",
           cpu_escenario_us / (double)total);

    medir_ruta_completa(muestras, total, &suma_control);
    est_ruta = calcular_estadisticas(muestras, total);
    imprimir_estadisticas("Escenario 2: ruta de la API (parseo JSON + evaluacion)",
                          &est_ruta, total);

    printf("\nObjetivos del RNF-01\n");
    printf("  evaluacion pura : mediana %8.3f us (objetivo <= %.0f us) -> %s\n",
           est_evaluacion.mediana, OBJETIVO_MEDIANA_US,
           (est_evaluacion.mediana <= OBJETIVO_MEDIANA_US) ? "CUMPLE" : "NO CUMPLE");
    printf("  evaluacion pura : p95     %8.3f us (objetivo <= %.0f us) -> %s\n",
           est_evaluacion.p95, OBJETIVO_P95_US,
           (est_evaluacion.p95 <= OBJETIVO_P95_US) ? "CUMPLE" : "NO CUMPLE");
    printf("  ruta de la API  : mediana %8.3f us (objetivo <= %.0f us) -> %s\n",
           est_ruta.mediana, OBJETIVO_P95_US,
           (est_ruta.mediana <= OBJETIVO_P95_US) ? "CUMPLE" : "NO CUMPLE");
    printf("  costo del cronometro (mediana): %.3f us\n", est_cronometro.mediana);

    MIDFF_CASO("objetivos de latencia del RNF-01");
    MIDFF_VERIFICAR(est_evaluacion.mediana <= OBJETIVO_MEDIANA_US,
                    "la evaluacion pura cumple la mediana objetivo (orden de microsegundos)");
    MIDFF_VERIFICAR(est_evaluacion.p95 <= OBJETIVO_P95_US,
                    "la evaluacion pura cumple el percentil 95 objetivo");
    MIDFF_VERIFICAR(est_ruta.mediana <= OBJETIVO_P95_US,
                    "la ruta de la API (parseo + evaluacion) se mantiene bajo 1 ms");
    MIDFF_VERIFICAR(est_ruta.p95 <= 5000.0,
                    "la ruta de la API cumple su percentil 95 (<= 5 ms)");
    MIDFF_VERIFICAR(suma_control > 0.0,
                    "suma de control de scores (impide que el compilador elimine el trabajo)");

    free(muestras);
    return midff_resumen("medicion de latencia del motor C en microsegundos (<time.h>)");
}


