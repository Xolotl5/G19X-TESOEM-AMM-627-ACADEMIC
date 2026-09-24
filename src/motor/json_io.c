/* ============================================================================
 * MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
 * Archivo: src/motor/json_io.c - Implementacion del JSON minimo.
 * Sprint: 1 | Requisitos: RF-01, RF-03, RF-08
 * ==========================================================================*/

#include "json_io.h"

#include <ctype.h>
#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

/* ---------------------------------------------------------------------------
 * Utilidades internas
 * -------------------------------------------------------------------------*/

/* Devuelve el puntero siguiente al cierre de la cadena cuya comilla de apertura
 * esta en 'p' (o 'fin' si la cadena no cierra). */
static const char *saltar_cadena(const char *p, const char *fin)
{
    ++p;
    while (p < fin) {
        if (*p == '\\') {
            p += 2;
            continue;
        }
        if (*p == '"') {
            return p + 1;
        }
        ++p;
    }
    return fin;
}

/* Devuelve el puntero siguiente al cierre del objeto/arreglo abierto en 'p'. */
static const char *saltar_agrupador(const char *p, const char *fin, char apertura, char cierre)
{
    int nivel = 0;

    while (p < fin) {
        if (*p == '"') {
            p = saltar_cadena(p, fin);
            continue;
        }
        if (*p == apertura) {
            ++nivel;
        } else if (*p == cierre) {
            --nivel;
            if (nivel == 0) {
                return p + 1;
            }
        }
        ++p;
    }
    return fin;
}

/* Devuelve el final del valor que empieza en 'p', recortando los espacios
 * finales de los escalares. */
static const char *fin_de_valor(const char *p, const char *fin)
{
    const char *ultimo;

    if (p >= fin) {
        return p;
    }
    if (*p == '"') {
        return saltar_cadena(p, fin);
    }
    if (*p == '{') {
        return saltar_agrupador(p, fin, '{', '}');
    }
    if (*p == '[') {
        return saltar_agrupador(p, fin, '[', ']');
    }
    ultimo = p;
    while (ultimo < fin && *ultimo != ',' && *ultimo != '}' && *ultimo != ']') {
        ++ultimo;
    }
    while (ultimo > p && isspace((unsigned char)*(ultimo - 1))) {
        --ultimo;
    }
    return ultimo;
}

/* Recorta los espacios finales de un rango de valor. */
static const char *recortar_fin(const char *ini, const char *fin)
{
    while (fin > ini && isspace((unsigned char)*(fin - 1))) {
        --fin;
    }
    return fin;
}
/* ---------------------------------------------------------------------------
 * Entrada
 * -------------------------------------------------------------------------*/

int json_leer_flujo(FILE *flujo, char *destino, size_t capacidad)
{
    size_t total = 0;

    if (destino == NULL || capacidad == 0) {
        return -1;
    }
    destino[0] = '\0';
    if (flujo == NULL) {
        return -1;
    }
    while (total + 1 < capacidad) {
        size_t leidos = fread(destino + total, 1, capacidad - 1 - total, flujo);

        if (leidos == 0) {
            if (ferror(flujo)) {
                destino[total] = '\0';
                return -1;
            }
            break;
        }
        total += leidos;
    }
    destino[total] = '\0';
    if (total + 1 >= capacidad && !feof(flujo)) {
        return -1;          /* la entrada excede MIDFF_JSON_MAX */
    }
    return (int)total;
}

int json_estructura_valida(const char *inicio, const char *fin)
{
    const char *p;
    int nivel = 0;
    int visto_objeto = 0;

    if (inicio == NULL || fin == NULL || inicio >= fin) {
        return 0;
    }
    for (p = inicio; p < fin; ++p) {
        if (isspace((unsigned char)*p)) {
            continue;
        }
        if (!visto_objeto) {
            if (*p != '{') {
                return 0;
            }
            visto_objeto = 1;
        }
        if (*p == '"') {
            const char *despues = saltar_cadena(p, fin);

            if (despues >= fin && *(fin - 1) != '"') {
                return 0;   /* cadena sin cerrar */
            }
            p = despues - 1;
            continue;
        }
        if (*p == '{') {
            ++nivel;
        } else if (*p == '}') {
            --nivel;
            if (nivel < 0) {
                return 0;
            }
            if (nivel == 0) {
                const char *resto = p + 1;

                while (resto < fin && isspace((unsigned char)*resto)) {
                    ++resto;
                }
                return (resto == fin) ? 1 : 0;
            }
        }
    }
    return 0;
}

const char *json_ubicar_clave(const char *inicio, const char *fin, const char *clave,
                              const char **valor_ini, const char **valor_fin)
{
    size_t largo_clave;
    const char *p;

    if (inicio == NULL || fin == NULL || clave == NULL || inicio >= fin) {
        return NULL;
    }
    largo_clave = strlen(clave);
    p = inicio;
    while (p < fin) {
        if (*p == '"') {
            const char *despues = saltar_cadena(p, fin);

            if ((size_t)(despues - p) == largo_clave + 2 &&
                strncmp(p + 1, clave, largo_clave) == 0) {
                const char *q = despues;

                while (q < fin && isspace((unsigned char)*q)) {
                    ++q;
                }
                if (q < fin && *q == ':') {
                    ++q;
                    while (q < fin && isspace((unsigned char)*q)) {
                        ++q;
                    }
                    if (valor_ini != NULL) {
                        *valor_ini = q;
                    }
                    if (valor_fin != NULL) {
                        *valor_fin = fin_de_valor(q, fin);
                    }
                    return q;
                }
            }
            p = despues;
            continue;
        }
        ++p;
    }
    return NULL;
}
int json_extraer_numero(const char *inicio, const char *fin, const char *clave, double *salida)
{
    const char *ini = NULL;
    const char *valor_fin = NULL;
    char *final_convertido = NULL;
    double valor;

    if (salida == NULL || json_ubicar_clave(inicio, fin, clave, &ini, &valor_fin) == NULL) {
        return 0;
    }
    valor_fin = recortar_fin(ini, valor_fin);
    if (ini >= valor_fin) {
        return 0;
    }
    valor = strtod(ini, &final_convertido);
    if (final_convertido != valor_fin) {
        return 0;           /* el valor no es un numero completo */
    }
    *salida = valor;
    return 1;
}

int json_extraer_entero(const char *inicio, const char *fin, const char *clave, long *salida)
{
    const char *ini = NULL;
    const char *valor_fin = NULL;
    char *final_convertido = NULL;
    long valor;

    if (salida == NULL || json_ubicar_clave(inicio, fin, clave, &ini, &valor_fin) == NULL) {
        return 0;
    }
    valor_fin = recortar_fin(ini, valor_fin);
    if (ini >= valor_fin) {
        return 0;
    }
    valor = strtol(ini, &final_convertido, 10);
    if (final_convertido != valor_fin) {
        return 0;
    }
    *salida = valor;
    return 1;
}

int json_extraer_cadena(const char *inicio, const char *fin, const char *clave,
                        char *salida, size_t capacidad)
{
    const char *ini = NULL;
    const char *valor_fin = NULL;
    size_t indice = 0;

    if (salida == NULL || capacidad == 0) {
        return 0;
    }
    if (json_ubicar_clave(inicio, fin, clave, &ini, &valor_fin) == NULL || ini >= valor_fin) {
        return 0;
    }
    if (*ini != '"' || *(valor_fin - 1) != '"') {
        return 0;
    }
    ++ini;                       /* se omite la comilla de apertura */
    --valor_fin;                 /* se omite la comilla de cierre   */
    while (ini < valor_fin && indice + 1 < capacidad) {
        if (*ini == '\\' && (ini + 1) < valor_fin) {
            ++ini;
            switch (*ini) {
                case 'n':  salida[indice++] = '\n'; break;
                case 't':  salida[indice++] = '\t'; break;
                case 'r':  salida[indice++] = '\r'; break;
                default:   salida[indice++] = *ini; break;
            }
            ++ini;
            continue;
        }
        salida[indice++] = *ini++;
    }
    salida[indice] = '\0';
    return 1;
}

int json_extraer_objeto(const char *inicio, const char *fin, const char *clave,
                        const char **objeto_ini, const char **objeto_fin)
{
    const char *ini = NULL;
    const char *valor_fin = NULL;

    if (json_ubicar_clave(inicio, fin, clave, &ini, &valor_fin) == NULL || ini >= valor_fin) {
        return 0;
    }
    if (*ini != '{' || *(valor_fin - 1) != '}') {
        return 0;
    }
    if (objeto_ini != NULL) {
        *objeto_ini = ini + 1;              /* contenido, sin llaves */
    }
    if (objeto_fin != NULL) {
        *objeto_fin = valor_fin - 1;        /* contenido, sin llaves */
    }
    return 1;
}

/* ---------------------------------------------------------------------------
 * Salida
 * -------------------------------------------------------------------------*/

static void agregar(char *destino, size_t capacidad, size_t *pos, const char *formato, ...)
{
    va_list args;
    int escritos;

    if (destino == NULL || pos == NULL || capacidad == 0 || *pos >= capacidad - 1) {
        return;
    }
    va_start(args, formato);
    escritos = vsnprintf(destino + *pos, capacidad - *pos, formato, args);
    va_end(args);
    if (escritos < 0) {
        destino[*pos] = '\0';
        return;
    }
    if ((size_t)escritos >= capacidad - *pos) {
        *pos = capacidad - 1;                /* truncado seguro */
        destino[*pos] = '\0';
        return;
    }
    *pos += (size_t)escritos;
}

void json_escribir_texto(char *destino, size_t capacidad, size_t *pos, const char *texto)
{
    if (texto == NULL) {
        texto = "";
    }
    agregar(destino, capacidad, pos, "%s", texto);
}

void json_escribir_cadena(char *destino, size_t capacidad, size_t *pos, const char *texto)
{
    const char *p;

    if (texto == NULL) {
        texto = "";
    }
    agregar(destino, capacidad, pos, "\"");
    for (p = texto; *p != '\0'; ++p) {
        unsigned char c = (unsigned char)*p;

        if (c == '"' || c == '\\') {
            agregar(destino, capacidad, pos, "\\%c", c);
        } else if (c == '\n') {
            agregar(destino, capacidad, pos, "\\n");
        } else if (c == '\r') {
            agregar(destino, capacidad, pos, "\\r");
        } else if (c == '\t') {
            agregar(destino, capacidad, pos, "\\t");
        } else if (c < 0x20) {
            agregar(destino, capacidad, pos, "\\u%04x", (unsigned int)c);
        } else {
            agregar(destino, capacidad, pos, "%c", c);
        }
    }
    agregar(destino, capacidad, pos, "\"");
}

void json_escribir_numero(char *destino, size_t capacidad, size_t *pos, double valor, int decimales)
{
    char formato[8];

    if (decimales < 0) {
        decimales = 0;
    }
    if (decimales > 6) {
        decimales = 6;
    }
    sprintf(formato, "%%.%df", decimales);
    agregar(destino, capacidad, pos, formato, valor);
}

void json_escribir_entero(char *destino, size_t capacidad, size_t *pos, long valor)
{
    agregar(destino, capacidad, pos, "%ld", valor);
}

void json_escribir_logico(char *destino, size_t capacidad, size_t *pos, int valor)
{
    agregar(destino, capacidad, pos, "%s", valor ? "true" : "false");
}

