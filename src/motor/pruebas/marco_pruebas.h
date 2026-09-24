#ifndef MIDFF_MARCO_PRUEBAS_H
#define MIDFF_MARCO_PRUEBAS_H

/* ============================================================================
 * MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
 * Archivo: src/motor/pruebas/marco_pruebas.h
 * Marco minimo de aserciones para las suites de pruebas del motor C (Sprint 1).
 * Sin dependencias externas: solo <stdio.h>, <string.h> y <math.h>.
 *
 * Uso:
 *     MIDFF_CASO("variable 1: desviacion del monto");
 *     MIDFF_VERIFICAR(condicion, "mensaje del porque");
 *     ...
 *     return midff_resumen("pruebas unitarias del motor de scoring");
 * ==========================================================================*/

#include <math.h>
#include <stdio.h>
#include <string.h>

static int midff_casos       = 0;
static int midff_verificacion = 0;
static int midff_fallos      = 0;

/* Declara un caso de prueba (agrupa verificaciones). */
#define MIDFF_CASO(nombre)                                                     \
    do {                                                                       \
        ++midff_casos;                                                          \
        printf("\n[CASO %02d] %s\n", midff_casos, (nombre));                    \
    } while (0)

/* Verificacion booleana. */
#define MIDFF_VERIFICAR(condicion, mensaje)                                    \
    do {                                                                       \
        ++midff_verificacion;                                                   \
        if (!(condicion)) {                                                     \
            ++midff_fallos;                                                     \
            printf("    [FALLO] %s | %s:%d\n", (mensaje), __FILE__, __LINE__);  \
            printf("            condicion no cumplida: %s\n", #condicion);      \
        }                                                                       \
    } while (0)

/* Verificacion de igualdad numerica con tolerancia (comparaciones de double). */
#define MIDFF_VERIFICAR_CERCA(obtenido, esperado, tolerancia, mensaje)         \
    do {                                                                       \
        double midff_obtenido = (double)(obtenido);                            \
        double midff_esperado = (double)(esperado);                            \
        ++midff_verificacion;                                                   \
        if (fabs(midff_obtenido - midff_esperado) > (double)(tolerancia)) {     \
            ++midff_fallos;                                                     \
            printf("    [FALLO] %s | %s:%d\n", (mensaje), __FILE__, __LINE__);  \
            printf("            esperado %.6f, obtenido %.6f\n",                 \
                   midff_esperado, midff_obtenido);                             \
        }                                                                       \
    } while (0)

/* Verificacion de igualdad de cadenas exacta. */
#define MIDFF_VERIFICAR_TEXTO(obtenido, esperado, mensaje)                     \
    do {                                                                       \
        const char *midff_texto = (obtenido);                                  \
        ++midff_verificacion;                                                   \
        if (midff_texto == NULL || strcmp(midff_texto, (esperado)) != 0) {       \
            ++midff_fallos;                                                     \
            printf("    [FALLO] %s | %s:%d\n", (mensaje), __FILE__, __LINE__);  \
            printf("            esperado \"%s\", obtenido \"%s\"\n",             \
                   (esperado), (midff_texto == NULL ? "(NULL)" : midff_texto)); \
        }                                                                       \
    } while (0)

/* Resumen final; devuelve el codigo de salida del programa de pruebas. */
static int midff_resumen(const char *suite)
{
    printf("\n=======================================================================\n");
    printf("SUITE: %s\n", suite);
    printf("Casos: %d | Verificaciones: %d | Fallos: %d\n",
           midff_casos, midff_verificacion, midff_fallos);
    printf("RESULTADO: %s\n",
           (midff_fallos == 0) ? "OK - todas las verificaciones pasaron" : "FALLIDO");
    printf("=======================================================================\n");
    return (midff_fallos == 0) ? 0 : 1;
}

#endif /* MIDFF_MARCO_PRUEBAS_H */
