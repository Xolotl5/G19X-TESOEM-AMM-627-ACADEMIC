#ifndef MIDFF_CRONOMETRO_H
#define MIDFF_CRONOMETRO_H

/* ============================================================================
 * MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
 * Componente 1: motor de scoring en C (ultra baja latencia)
 * Archivo: src/motor/cronometro.h - Contrato del cronometro en microsegundos.
 * Sprint: 1
 * Requisitos: RNF-01 (evaluacion en microsegundos) y MVP_MIDFF.md seccion 10
 *             ("Latencia de evaluacion del motor C: orden de microsegundos").
 *
 * La medicion se realiza con <time.h> como lo pide la suite del Sprint 1:
 *   - Windows  : QueryPerformanceCounter (resolucion 0.1 us) y <time.h> aporta
 *                clock() (reloj de CPU) y time() (reloj de calendario).
 *   - POSIX    : clock_gettime(CLOCK_MONOTONIC) declarado en <time.h>.
 * ==========================================================================*/

#include <time.h>

/* Cronometro monotono de alta resolucion. El campo ultimo_contador guarda la
 * ultima lectura, de modo que cronometro_us() puede llamarse varias veces. */
typedef struct {
    long long int inicio_contador;  /* lectura de alta resolucion al iniciar   */
    long long int fin_contador;     /* lectura de alta resolucion al detener   */
    clock_t       inicio_cpu;       /* clock() al iniciar (<time.h>)           */
    clock_t       fin_cpu;          /* clock() al detener (<time.h>)           */
    time_t        inicio_segundos;  /* time(NULL) al iniciar (<time.h>)        */
} Cronometro;

/* Arranca el cronometro y reinicia todas sus lecturas. */
void cronometro_iniciar(Cronometro *cronometro);

/* Congela la medicion (elapsed y tiempo de CPU quedan disponibles). */
void cronometro_detener(Cronometro *cronometro);

/* Tiempo transcurrido entre iniciar y detener. */
double cronometro_segundos(const Cronometro *cronometro);
double cronometro_us(const Cronometro *cronometro);

/* Tiempo de CPU del proceso (clock(), resolucion de CLOCKS_PER_SEC = 1 ms en
 * Windows): se reporta como dato complementario del benchmark. */
double cronometro_cpu_segundos(const Cronometro *cronometro);
double cronometro_cpu_us(const Cronometro *cronometro);

/* Resolucion del reloj usado durante la medicion, en microsegundos. */
double cronometro_resolucion_us(void);

/* Nombre de la fuente de tiempo activa (evidencia para la bitacora). */
const char *cronometro_fuente(void);

#endif /* MIDFF_CRONOMETRO_H */
