#ifndef MIDFF_SCORING_H
#define MIDFF_SCORING_H

/* ============================================================================
 * MIDFF - Motor Inteligente de Deteccion de Fraude Financiero
 * Componente 1: motor de scoring en C (ultra baja latencia)
 * Archivo: src/motor/scoring.h - Contrato del calculo del score (0.0 - 1.0).
 * Sprint: 1
 * Requisitos:  RF-03 (invocacion por linea de comandos), RF-04 (score con las 3
 *              variables), RF-05 (clasificacion por umbral), RF-08 (detalle
 *              auditable de variables), RNF-01 (microsegundos).
 * Historias:   HU-MVP-02 (las 3 variables afectan el score) y HU-MVP-03 (la
 *              decision automatica corresponde al umbral).
 * Documento base: MVP_MIDFF.md secciones 4.1, 7, 10 y Anexo A.
 *
 * MODELO DE REGLAS v1.0 (RF-04)
 *   score = PESO_MONTO * p_monto
 *         + PESO_FRECUENCIA * p_frecuencia
 *         + PESO_UBICACION_IP * p_ubicacion_ip
 *   con cada p_i normalizado en [0,1] mediante rampas lineales y el score final
 *   acotado a [0,1] y redondeado a DECIMALES_SCORE.
 *
 * FAIL-SAFE (dato ausente): si una variable no puede calcularse (sin contexto
 * historico del cliente, sin monto promedio, sin frecuencia o sin ubicacion/IP
 * de referencia) se usa PUNTAJE_NEUTRO en esa variable y se anota la bandera
 * correspondiente en Evaluacion.datos_faltantes. Asi una transaccion sin
 * historial se clasifica como MEDIO/EN_REVISION (nunca se aprueba a ciegas) y
 * queda registrado el motivo (RF-08 / RNF-04).
 * ==========================================================================*/

#include <stddef.h>

/* --- Umbrales de clasificacion (RF-05, MVP_MIDFF.md 4.1 y 7) ---------------
 *   Bajo : score < 0.40                  -> APROBADA     (sin notificacion)
 *   Medio: 0.40 <= score <= 0.70         -> EN_REVISION   (alerta preventiva)
 *   Alto : score > 0.70                  -> BLOQUEADA     (alerta de fraude)
 * El limite superior es inclusivo en MEDIO segun el texto del MVP ("0.4 - 0.7").
 * -------------------------------------------------------------------------*/
#define UMBRAL_RIESGO_MEDIO 0.40
#define UMBRAL_RIESGO_ALTO  0.70

/* --- Pesos del modelo (deben sumar 1.0; orden de importancia de RF-04) --- */
#define PESO_MONTO        0.45
#define PESO_FRECUENCIA   0.30
#define PESO_UBICACION_IP 0.25

/* --- Parametros de normalizacion (rampas lineales) -----------------------
 * p_monto       : 0.0 cuando el monto iguala el promedio historico del cliente
 *                 (ratio <= 1.0) y 1.0 cuando lo supera 5 veces (ratio >= 5.0).
 * p_frecuencia  : 0.0 hasta FRECUENCIA_BAJA transacciones en la ultima hora y
 *                 1.0 desde FRECUENCIA_ALTA.
 * p_ubicacion_ip: 0.5 si cambia la ubicacion, 0.5 si cambia la IP (maximo 1.0).
 * -------------------------------------------------------------------------*/
#define MONTO_RATIO_BAJO  1.0
#define MONTO_RATIO_ALTO  5.0
#define FRECUENCIA_BAJA   2
#define FRECUENCIA_ALTA   8
#define PESO_DIMENSION_GEOLOCALIZACION 0.50

/* --- Valores de control ------------------------------------------------- */
#define PUNTAJE_NEUTRO  0.50   /* datos ausentes: no aprueba ni bloquea */
#define DECIMALES_SCORE 4      /* precision informada al origen (RF-06) */

/* --- Banderas de datos faltantes (mascara Evaluacion.datos_faltantes) ---- */
#define DATO_CONTEXTO           0x0001  /* el JSON no traia "contexto"           */
#define DATO_MONTO_PROMEDIO     0x0002  /* sin monto promedio historico valido   */
#define DATO_FRECUENCIA         0x0004  /* sin frecuencia por hora valida        */
#define DATO_UBICACION_HABITUAL 0x0008  /* sin ubicacion habitual del cliente    */
#define DATO_IP_HABITUAL        0x0010  /* sin IP habitual del cliente           */
#define DATO_UBICACION_ENTRADA  0x0020  /* la transaccion no informa ubicacion   */
#define DATO_IP_ENTRADA         0x0040  /* la transaccion no informa IP origen   */

/* --- Longitudes maximas de los campos del contrato JSON ------------------ */
#define MIDFF_MAX_CADENA 64
#define MIDFF_MAX_TEXTO  64
#define MIDFF_MAX_ERROR  256

/* --- Codigos de salida del ejecutable -----------------------------------
 *   0 = evaluacion emitida en stdout
 *   2 = entrada invalida (JSON mal formado o sin campos obligatorios) -> la API
 *       responde HTTP 400 (RF-02 simplificado)
 *   3 = error interno (lectura de stdin o buffer de salida insuficiente)
 * -----------------------------------------------------------------------*/
#define MIDFF_CODIGO_OK                0
#define MIDFF_CODIGO_ENTRADA_INVALIDA  2
#define MIDFF_CODIGO_ERROR_INTERNO     3

/* --- Variables transaccionales del MVP (Anexo A de MVP_MIDFF.md) --------- */
typedef struct {
    char   id_transaccion[MIDFF_MAX_CADENA]; /* opcional: lo asigna la API       */
    char   id_cliente[MIDFF_MAX_CADENA];     /* obligatorio                      */
    double monto;                            /* obligatorio, mayor que cero      */
    char   moneda[MIDFF_MAX_CADENA];
    char   origen[MIDFF_MAX_CADENA];
    char   ubicacion[MIDFF_MAX_CADENA];
    char   ip_origen[MIDFF_MAX_CADENA];
    char   fecha_hora[MIDFF_MAX_TEXTO];
    char   canal[MIDFF_MAX_CADENA];
} Transaccion;

/* --- Contexto historico del cliente --------------------------------------
 * La API obtiene estos datos de Oracle DB (TRANSACCION/EVALUACION) y los envia
 * dentro del objeto "contexto" del JSON, porque el motor es un proceso sin
 * estado (RF-03). Si el objeto no llega, "presente" queda en 0 y se aplica el
 * fail-safe descrito en la cabecera. */
typedef struct {
    int    presente;                        /* 1 si el JSON traia "contexto"     */
    double monto_promedio_historial;        /* <= 0 => sin historial utilizable  */
    int    transacciones_ultima_hora;       /* <= 0 => sin dato de frecuencia    */
    char   ubicacion_habitual[MIDFF_MAX_CADENA];
    char   ip_habitual[MIDFF_MAX_CADENA];
} ContextoCliente;

/* --- Puntajes normalizados de las 3 variables ---------------------------- */
typedef struct {
    double monto;
    double frecuencia;
    double ubicacion_ip;
} PuntajesVariables;

/* --- Resultado de la evaluacion (RF-04, RF-05, RF-08) -------------------- */
typedef struct {
    char   id_transaccion[MIDFF_MAX_CADENA];
    char   id_cliente[MIDFF_MAX_CADENA];
    double score;                     /* 0.0 - 1.0, redondeado a 4 decimales   */
    char   clasificacion[12];         /* BAJO | MEDIO | ALTO                   */
    char   decision[16];              /* APROBADA | EN_REVISION | BLOQUEADA    */
    int    genera_alerta;             /* 1 si el score >= UMBRAL_RIESGO_MEDIO  */
    PuntajesVariables puntajes;
    double monto_entrada;             /* monto recibido en la transaccion      */
    double monto_referencia;          /* promedio historico usado (0 si no hay) */
    double ratio_monto;               /* monto / monto_promedio_historial      */
    int    frecuencia_usada;          /* transacciones en la ultima hora       */
    int    ubicacion_distinta;        /* 1 si difiere de la ubicacion habitual */
    int    ip_distinta;               /* 1 si difiere de la IP habitual        */
    int    datos_faltantes;           /* mascara DATO_*                        */
    double latencia_evaluacion_us;    /* duracion de evaluar_transaccion       */
    double latencia_parseo_us;        /* duracion del parseo del JSON          */
} Evaluacion;

/* --- Calculo ------------------------------------------------------------ */

/* Normalizacion lineal por rampa: 0.0 si valor <= minimo, 1.0 si valor >= maximo. */
double normalizar_rampa(double valor, double minimo, double maximo);

/* Acota un valor al rango [minimo, maximo]. */
double limitar(double valor, double minimo, double maximo);

/* Redondeo a la cantidad de decimales indicada. */
double redondear(double valor, int decimales);

/* Puntaje de la variable 1 (desviacion del monto vs. historial del cliente). */
double puntaje_monto(double monto, double monto_promedio_historial, double *ratio);

/* Puntaje de la variable 2 (frecuencia de transacciones por hora). */
double puntaje_frecuencia(int transacciones_ultima_hora);

/* Puntaje de la variable 3 (ubicacion/IP inusual).
 * ubicacion_distinta e ip_distinta devuelven 1 cuando el dato difiere del
 * habitual (comparacion sin distinguir mayusculas ni espacios extremos).
 * Si el dato falta en la transaccion o en el contexto, esa dimension no se
 * compara; cuando no queda ninguna dimension comparable se devuelve
 * PUNTAJE_NEUTRO (fail-safe) y la ausencia se reporta en datos_faltantes. */
double puntaje_ubicacion_ip(const Transaccion *transaccion, const ContextoCliente *contexto,
                            int *ubicacion_distinta, int *ip_distinta);

/* Score ponderado de las 3 variables, acotado a [0,1]. */
double calcular_score(const PuntajesVariables *puntajes);

/* Clasificacion y decision por umbral (RF-05 / HU-MVP-03). */
void clasificar(double score, char *clasificacion, size_t capacidad_clasificacion,
                char *decision, size_t capacidad_decision);

/* Evaluacion completa de una transaccion (RF-04 + RF-05 + RF-08). */
void evaluar_transaccion(const Transaccion *transaccion, const ContextoCliente *contexto,
                         Evaluacion *evaluacion);

/* --- Contrato JSON ----------------------------------------------------- */

/* Convierte el texto JSON de entrada en Transaccion + ContextoCliente.
 * Devuelve 1 en exito; 0 si el JSON es invalido o faltan campos obligatorios
 * (monto > 0 e id_cliente), dejando el diagnostico en 'error'. */
int midff_leer_entrada_json(const char *json, Transaccion *transaccion,
                            ContextoCliente *contexto, char *error, size_t capacidad_error);

/* Serializa SOLO el objeto "variables" (las 3 variables con sus puntajes): es
 * el valor que la API persiste en EVALUACION.variables_usadas (RF-08).
 * Devuelve los bytes escritos o -1 si el buffer fue insuficiente. */
int variables_a_json(const Evaluacion *evaluacion, char *destino, size_t capacidad);

/* Serializa la respuesta completa del motor para stdout (score, clasificacion,
 * decision, variables, pesos, umbrales, latencias y datos faltantes).
 * Devuelve los bytes escritos o -1 si el buffer fue insuficiente. */
int evaluacion_a_json(const Transaccion *transaccion, const Evaluacion *evaluacion,
                      char *destino, size_t capacidad);


#endif /* MIDFF_SCORING_H */
