# Contrato de Interfaz — Motor de Scoring MIDFF (`scoring.c`)

| Campo | Valor |
|---|---|
| **Componente** | Motor analítico en C — `src/motor/scoring.c` |
| **Ejecutable** | `src/motor/bin/midff-scoring.exe` (compilado por `build.ps1`) |
| **Versión del contrato** | 1.0 (congelado; basado en el Anexo A de `MVP_MIDFF.md`) |
| **Sprint** | 1 |
| **Requisitos cubiertos** | RF-03 (invocación por línea de comandos), RF-04 (score con 3 variables), RF-05 (clasificación por umbral), RF-06 (respuesta JSON), RF-08 (detalle auditable) |

---

## 1. Mecánica de invocación (RF-03)

El ejecutable se invoca **sin argumentos**: lee **un objeto JSON** por `stdin` y emite la evaluación por `stdout` (con un salto de línea final). El motor es un proceso sin estado: todo el contexto necesario viaja en la propia entrada.

| Flujo | Contenido |
|---|---|
| `stdin` | Objeto JSON de la transacción (máximo **8192 bytes**; se tolera BOM UTF-8 inicial). |
| `stdout` | Objeto JSON con la evaluación completa (sección 3). Solo se escribe si el código de salida es `0`. |
| `stderr` | Objeto JSON de diagnóstico (sección 5). |

**Códigos de salida del proceso:**

| Código | Significado | Traducción en la API (Sprint 2) |
|---|---|---|
| `0` | Evaluación emitida en `stdout` | HTTP 200 |
| `2` | Entrada inválida: JSON mal formado, mayor a 8192 bytes o sin campos obligatorios | HTTP 400 |
| `3` | Error interno: `stdin` ilegible o buffer de salida insuficiente | HTTP 500 |

---

## 2. Entrada JSON esperada (`stdin`)

### 2.1 Objeto transacción

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `id_transaccion` | string (≤ 64) | No | Identificador asignado por la API; se refleja en la salida. |
| `id_cliente` | string (≤ 64) | **Sí** (no vacío) | Identificador del cliente; alimenta la auditoría. |
| `monto` | number | **Sí** (> 0) | Monto de la operación. |
| `moneda` | string (≤ 64) | No | Código de moneda (p. ej. `MXN`). Se refleja en la salida. |
| `origen` | string (≤ 64) | No | Sistema origen: `ERP`, `BANCA`, `PASARELA`, `OTRO`. |
| `ubicacion` | string (≤ 64) | No | Ubicación declarada de la operación; se compara (sin distinguir mayúsculas) contra `contexto.ubicacion_habitual`. |
| `ip_origen` | string (≤ 64) | No | IP de origen; se compara contra `contexto.ip_habitual`. |
| `fecha_hora` | string (≤ 64) | No | Marca de tiempo ISO 8601 de la transacción. |
| `canal` | string (≤ 64) | No | Canal de la operación (`WEB`, `MOVIL`, `POS`, ...). |
| `contexto` | objeto | No* | Historial del cliente (2.2); lo aporta la API desde Oracle DB. |

\* Si `contexto` no llega, el motor aplica **fail-safe**: las 3 variables toman el puntaje neutro `0.50`, el score resulta `0.5000` (MEDIO / EN_REVISION) y la ausencia queda registrada en `datos_faltantes` — nunca se aprueba a ciegas (RNF-04).

### 2.2 Objeto `contexto` (todos opcionales; cada ausencia se reporta en `datos_faltantes`)

| Campo | Tipo | Descripción |
|---|---|---|
| `monto_promedio_historial` | number (> 0) | Promedio histórico del cliente; base de la variable 1. |
| `transacciones_ultima_hora` | integer (> 0) | Frecuencia de la última hora, incluida la actual; variable 2. |
| `ubicacion_habitual` | string (≤ 64) | Ubicación de referencia del cliente; variable 3. |
| `ip_habitual` | string (≤ 64) | IP de referencia del cliente; variable 3. |

### 2.3 Ejemplo de entrada

```json
{
  "id_transaccion": "TRX-2026-000103",
  "id_cliente": "CLI-003",
  "monto": 15499.00,
  "moneda": "MXN",
  "origen": "PASARELA",
  "ubicacion": "CDMX",
  "ip_origen": "203.0.113.45",
  "fecha_hora": "2026-09-23T23:55:00-06:00",
  "canal": "POS",
  "contexto": {
    "monto_promedio_historial": 1200.00,
    "transacciones_ultima_hora": 7,
    "ubicacion_habitual": "GDL",
    "ip_habitual": "189.203.4.7"
  }
}
```

---

## 3. Salida JSON producida (`stdout`)

Objeto único, una sola línea, con las claves en este orden:

| Clave | Tipo | Descripción |
|---|---|---|
| `id_transaccion` | string | Eco del identificador recibido (vacío si no llegó). |
| `id_cliente` | string | Eco del cliente evaluado. |
| `monto` | number (2 dec.) | Monto recibido. |
| `moneda` | string | Moneda recibida. |
| `origen` | string | Origen recibido. |
| `score` | number (4 dec.) | Score de riesgo en `[0.0000, 1.0000]` (RF-04). |
| `clasificacion` | string | `BAJO` \| `MEDIO` \| `ALTO` (RF-05). |
| `decision` | string | `APROBADA` \| `EN_REVISION` \| `BLOQUEADA` (RF-05). |
| `genera_alerta` | boolean | `true` si `score >= 0.4000` (dispara el push FCM, RF-09). |
| `variables` | objeto | Detalle auditable de las 3 variables (RF-08); es el valor persistido en `EVALUACION.variables_usadas`. |
| `variables.monto` | objeto | `entrada`, `referencia` (promedio usado), `ratio` (5 dec.) y `puntaje` normalizado. |
| `variables.frecuencia` | objeto | `entrada` (tx/hora), `rango` `[2, 8]` de la rampa y `puntaje`. |
| `variables.ubicacion_ip` | objeto | `puntaje`, `ubicacion_distinta` y `ip_distinta` (booleanos). |
| `pesos` | objeto | Pesos vigentes del modelo: `monto`, `frecuencia`, `ubicacion_ip`. |
| `umbrales` | objeto | Umbrales vigentes: `medio` (0.4000) y `alto` (0.7000). |
| `latencia_evaluacion_us` | number (3 dec.) | Duración del cálculo en microsegundos (RNF-01). |
| `latencia_parseo_us` | number (3 dec.) | Duración del parseo del JSON en microsegundos. |
| `datos_faltantes` | array de strings | Nombres de los datos ausentes; `[]` cuando la entrada llegó completa. Valores posibles: `contexto`, `monto_promedio_historial`, `transacciones_ultima_hora`, `ubicacion_habitual`, `ip_habitual`, `ubicacion`, `ip_origen`. |

### 3.1 Ejemplo de salida (respuesta real del ejecutable a la entrada 2.3)

```json
{
  "id_transaccion": "TRX-2026-000103",
  "id_cliente": "CLI-003",
  "monto": 15499.00,
  "moneda": "MXN",
  "origen": "PASARELA",
  "score": 0.9500,
  "clasificacion": "ALTO",
  "decision": "BLOQUEADA",
  "genera_alerta": true,
  "variables": {
    "monto": { "entrada": 15499.00, "referencia": 1200.00, "ratio": 12.91583, "puntaje": 1.0000 },
    "frecuencia": { "entrada": 7, "rango": [2, 8], "puntaje": 0.8333 },
    "ubicacion_ip": { "puntaje": 1.0000, "ubicacion_distinta": true, "ip_distinta": true }
  },
  "pesos": { "monto": 0.4500, "frecuencia": 0.3000, "ubicacion_ip": 0.2500 },
  "umbrales": { "medio": 0.4000, "alto": 0.7000 },
  "latencia_evaluacion_us": 4.400,
  "latencia_parseo_us": 27.100,
  "datos_faltantes": []
}
```

> Las claves `latencia_evaluacion_us` y `latencia_parseo_us` son informativas y varían en cada ejecución.

---

## 4. Modelo y bandas de clasificación (referencia)

`score = 0.45·p_monto + 0.30·p_frecuencia + 0.25·p_ubicacion_ip`, con cada `p_i` normalizado a `[0, 1]` por rampas lineales y el resultado acotado y redondeado a 4 decimales.

| Banda | Rango de score | Decisión | Acción |
|---|---|---|---|
| **BAJO** | `< 0.40` | `APROBADA` | Sin notificación |
| **MEDIO** | `0.40 – 0.70` (ambos incluidos) | `EN_REVISION` | Alerta preventiva |
| **ALTO** | `> 0.70` | `BLOQUEADA` | Alerta de fraude + bloqueo |

---

## 5. Salida de error (`stderr`)

Ante entrada inválida (código de salida `2`) o error interno (`3`), `stdout` queda vacío y `stderr` recibe:

```json
{"componente":"midff-scoring","error":"campo obligatorio ausente o invalido: monto (numerico mayor que cero)"}
```

---

## 6. Dataset de pruebas de referencia

`datos/datasets/transacciones_prueba.json` contiene 3 transacciones representativas (una por banda de riesgo: `0.0000` BAJO, `0.5000` MEDIO, `0.9500` ALTO) con el campo `score_esperado` para verificación de regresión. Cada objeto incluye `timestamp` como espejo de `fecha_hora` para las herramientas de prueba; el motor consume `fecha_hora` e ignora claves desconocidas.

