#ifndef MIDFF_JSON_IO_H
#define MIDFF_JSON_IO_H

/* ============================================================================
 * MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
 * Archivo: src/motor/json_io.h - Lectura/escritura JSON minima (stdin/stdout).
 * Sprint: 1 | Requisitos: RF-01, RF-03, RF-08
 *
 * El motor no depende de bibliotecas externas: este modulo implementa lo
 * minimo indispensable para leer el contrato JSON del Anexo A de MVP_MIDFF.md
 * (objeto plano con escalares y un objeto anidado "contexto") y para escribir
 * la respuesta del motor. Al ser minusculo y sin asignacion dinamica mantiene
 * la latencia en el orden de microsegundos (RNF-01).
 *
 * Convencion de retorno: las funciones json_extraer_* devuelven 1 si la clave
 * existe y su valor es del tipo pedido, 0 en cualquier otro caso (el valor de
 * salida queda intacto), de modo que el llamador decide el valor por omision.
 * ==========================================================================*/

#include <stddef.h>
#include <stdio.h>

/* Tamano maximo de la entrada JSON (una transaccion del MVP ronda 300 bytes). */
#define MIDFF_JSON_MAX 8192

/* ---------------------------------------------------------------------------
 * Entrada
 * -------------------------------------------------------------------------*/

/* Lee todo el flujo hasta EOF y lo termina en '\0'.
 * Devuelve la cantidad de bytes leidos (>= 0) o -1 si el flujo excede la
 * capacidad del destino (MIDFF_JSON_MAX). */
int json_leer_flujo(FILE *flujo, char *destino, size_t capacidad);

/* Verifica que el texto sea un objeto JSON con llaves balanceadas, cadenas
 * cerradas y sin contenido despues del cierre. Devuelve 1 si la estructura es
 * valida y 0 si no lo es. */
int json_estructura_valida(const char *inicio, const char *fin);

/* Localiza la clave dentro del rango [inicio, fin) y devuelve el puntero a su
 * valor (el primer caracter no blanco despues de ':'), o NULL si no existe.
 * En valor_ini/valor_fin se devuelven los limites del valor completo. */
const char *json_ubicar_clave(const char *inicio, const char *fin, const char *clave,
                              const char **valor_ini, const char **valor_fin);

/* Búsqueda de valores tipados (sobre el rango indicado). */
int json_extraer_numero(const char *inicio, const char *fin, const char *clave, double *salida);
int json_extraer_entero(const char *inicio, const char *fin, const char *clave, long *salida);
int json_extraer_cadena(const char *inicio, const char *fin, const char *clave,
                        char *salida, size_t capacidad);
int json_extraer_objeto(const char *inicio, const char *fin, const char *clave,
                        const char **objeto_ini, const char **objeto_fin);

/* ---------------------------------------------------------------------------
 * Salida
 * -------------------------------------------------------------------------*/

/* Acumuladores de escritura sobre un buffer ya inicializado.
 * 'pos' se actualiza con los bytes escritos; el contenido siempre queda
 * terminado en '\0' y truncado de forma segura si el buffer se agota. */
void json_escribir_texto(char *destino, size_t capacidad, size_t *pos, const char *texto);
void json_escribir_cadena(char *destino, size_t capacidad, size_t *pos, const char *texto);
void json_escribir_numero(char *destino, size_t capacidad, size_t *pos, double valor, int decimales);
void json_escribir_entero(char *destino, size_t capacidad, size_t *pos, long valor);
void json_escribir_logico(char *destino, size_t capacidad, size_t *pos, int valor);

#endif /* MIDFF_JSON_IO_H */
