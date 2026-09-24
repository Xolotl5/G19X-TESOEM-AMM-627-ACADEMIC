/* ============================================================================
 * MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
 * Archivo: src/motor/cronometro.c - Reloj de alta resolucion en microsegundos.
 * Sprint: 1 | Requisitos: RNF-01
 *
 * Implementacion:
 *   - Windows: QueryPerformanceCounter / QueryPerformanceFrequency (0.1 us).
 *   - POSIX  : clock_gettime(CLOCK_MONOTONIC) de <time.h>.
 * En ambos casos <time.h> se usa para time() y clock() (tiempo de CPU).
 * ==========================================================================*/

#include "cronometro.h"

#include <time.h>

#if defined(_WIN32)
  #include <windows.h>

  static double frecuencia_contador(void)
  {
      LARGE_INTEGER frecuencia;

      if (!QueryPerformanceFrequency(&frecuencia) || frecuencia.QuadPart == 0) {
          return 0.0;
      }
      return (double)frecuencia.QuadPart;
  }

  static long long int leer_contador(void)
  {
      LARGE_INTEGER valor;

      QueryPerformanceCounter(&valor);
      return (long long int)valor.QuadPart;
  }

  const char *cronometro_fuente(void)
  {
      return "QueryPerformanceCounter (Windows) + time.h";
  }
#else
  #include <time.h>

  static double frecuencia_contador(void)
  {
      return 1000000000.0;  /* el contador de abajo esta expresado en nanosegundos */
  }

  static long long int leer_contador(void)
  {
      struct timespec instante;

      if (clock_gettime(CLOCK_MONOTONIC, &instante) != 0) {
          return 0;
      }
      return (long long int)instante.tv_sec * 1000000000LL + (long long int)instante.tv_nsec;
  }

  const char *cronometro_fuente(void)
  {
      return "clock_gettime(CLOCK_MONOTONIC) de time.h";
  }
#endif

void cronometro_iniciar(Cronometro *cronometro)
{
    if (cronometro == NULL) {
        return;
    }
    cronometro->inicio_contador = leer_contador();
    cronometro->fin_contador = cronometro->inicio_contador;
    cronometro->inicio_cpu = clock();
    cronometro->fin_cpu = cronometro->inicio_cpu;
    cronometro->inicio_segundos = time(NULL);
}

void cronometro_detener(Cronometro *cronometro)
{
    if (cronometro == NULL) {
        return;
    }
    cronometro->fin_contador = leer_contador();
    cronometro->fin_cpu = clock();
}

double cronometro_segundos(const Cronometro *cronometro)
{
    double frecuencia;

    if (cronometro == NULL) {
        return 0.0;
    }
    frecuencia = frecuencia_contador();
    if (frecuencia <= 0.0) {
        return 0.0;
    }
    return (double)(cronometro->fin_contador - cronometro->inicio_contador) / frecuencia;
}

double cronometro_us(const Cronometro *cronometro)
{
    return cronometro_segundos(cronometro) * 1000000.0;
}

double cronometro_cpu_segundos(const Cronometro *cronometro)
{
    if (cronometro == NULL) {
        return 0.0;
    }
    return (double)(cronometro->fin_cpu - cronometro->inicio_cpu) / (double)CLOCKS_PER_SEC;
}

double cronometro_cpu_us(const Cronometro *cronometro)
{
    return cronometro_cpu_segundos(cronometro) * 1000000.0;
}

double cronometro_resolucion_us(void)
{
    double frecuencia = frecuencia_contador();

    if (frecuencia <= 0.0) {
        return 0.0;
    }
    return 1000000.0 / frecuencia;
}
