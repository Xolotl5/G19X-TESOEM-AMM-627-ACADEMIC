# MVP — Motor Inteligente de Detección de Fraude Financiero (MIDFF)

**Definición del Producto Mínimo Viable (Minimum Viable Product)**

| Campo | Valor |
|---|---|
| **Nombre del producto** | MIDFF — Motor Inteligente de Detección de Fraude Financiero |
| **Documento base** | `PRD_MIDFF.md` v1.0 |
| **Versión del documento** | 1.0 |
| **Fecha de elaboración** | 21/09/2026 |
| **Estado** | Borrador en revisión |
| **Metodología** | Scrum (sprints de 2 semanas) |
| **Duración estimada del MVP** | 8 semanas (4 sprints) |
| **Control de versiones** | Git |

### Control de cambios

| Versión | Fecha | Autor | Descripción del cambio |
|---|---|---|---|
| 1.0 | 21/09/2026 | Residente del proyecto | Versión inicial derivada del PRD MIDFF v1.0 |

---

## 1. Qué es el MVP de MIDFF

El MVP es una **rebanada vertical (thin slice) completa y funcional** de la plataforma descrita en el PRD: desde la ingesta de una transacción HTTP/JSON hasta la alerta push en el dispositivo Android y el bloqueo preventivo, pasando por la evaluación del score en el motor C, la decisión automática y el registro auditable en Oracle DB.

En una sola frase, el MVP debe demostrar el flujo completo:

> **Transacción → Score (motor C) → Clasificación y decisión → Auditoría (Oracle DB) → Notificación push (FCM) → Bloqueo preventivo (App Android)**

Todo lo que no sea imprescindible para demostrar ese ciclo de extremo a extremo queda **diferido al backlog post-MVP** (dashboard web, historiales, sincronización por Realtime Database, modelos ML, etc.).

### Hipótesis a validar

> "Un motor de scoring en C de ultra baja latencia, orquestado por una API REST en PHP y notificado vía Firebase FCM, permite **detectar y detener transacciones fraudulentas en tiempo real**, con trazabilidad completa de cada decisión."

## 2. Objetivos del MVP

1. **Demostrar el flujo completo** de detección en tiempo real con datos de prueba (transacciones simuladas de ERP/banca/pasarela).
2. **Validar la integración del stack híbrido** (C + PHP 8+ + Oracle DB + Firebase + Android/Java).
3. **Medir la latencia real** del motor C (microsegundos) y del ciclo extremo a extremo (≤ 1 s).
4. **Validar la utilidad operativa** de la alerta push y del bloqueo preventivo inmediato.
5. Dejar una **base de código extensible** en Git sobre la cual construir el resto del PRD.

## 3. Criterios de selección del alcance

Para decidir qué entra al MVP se aplicaron los siguientes criterios sobre los requisitos del PRD:

| Criterio | Pregunta clave | Resultado |
|---|---|---|
| **Núcleo de valor** | ¿Sin esto no se puede detectar ni detener un fraude end-to-end? → **Must** | Ingesta, scoring, clasificación, decisión, persistencia, auditoría, push, app, bloqueo. |
| **Simplificable** | ¿Se puede entregar una versión mínima sin perder la demostración? → **Should** | Validación básica, auditoría esencial, scripts de build. |
| **Diferible** | ¿Aporta monitoreo/análisis pero no el ciclo de detección? → **Could/Won't (this time)** | Dashboard web, historiales en UI, Realtime DB, reportes, ML. |

## 4. Alcance del MVP — mapeo de requisitos del PRD

### 4.1 Requisitos funcionales

| ID PRD | Requisito | Estado MVP | Nota de alcance |
|---|---|---|---|
| **RF-01** | Ingesta de transacciones HTTP/JSON | ✅ Incluido | Un solo endpoint: `POST /api/v1/transacciones`. |
| **RF-02** | Validación de entrada | 🟡 Simplificado | Validación de campos obligatorios y tipos; autenticación básica por API Key. |
| **RF-03** | Invocación del motor C | ✅ Incluido | Invocación del ejecutable por línea de comandos (stdin/stdout); optimización diferida. |
| **RF-04** | Cálculo del score (0.0–1.0) | ✅ Incluido | Con las 3 variables del PRD: desviación de monto, frecuencia por hora, ubicación/IP inusual. |
| **RF-05** | Clasificación del riesgo | ✅ Incluido | Umbrales fijos del PRD: Bajo < 0.4, Medio 0.4–0.7, Alto > 0.7. |
| **RF-06** | Respuesta sincrónica al origen | ✅ Incluido | Respuesta JSON con score, clasificación y decisión. |
| **RF-07** | Persistencia ACID en Oracle | ✅ Incluido | Oracle XE en entorno de desarrollo del MVP. |
| **RF-08** | Auditoría de cada evaluación | 🟡 Simplificado | Registro esencial: variables, score, clasificación, decisión y timestamp. |
| **RF-09** | Notificación push vía FCM | ✅ Incluido | Solo para riesgo medio/alto (score ≥ 0.4) a dispositivos registrados. |
| **RF-10** | App Android de alertas | ✅ Incluido | App básica en Java: registro de token FCM y bandeja de alertas. |
| **RF-11** | Bloqueo preventivo inmediato | ✅ Incluido | Acción "Bloquear" sobre una alerta; se registra en Oracle vía API. |
| **RF-12** | Sincronización Realtime DB | ⏸️ Diferido | En el MVP el estado se consulta/confirma por API REST; sin Realtime DB. |
| **RF-13** | Dashboard administrativo web | ⏸️ Diferido | Durante el MVP el monitoreo se hace con consultas SQL y logs. |
| **RF-14** | Consulta de historial | ⏸️ Diferido | Sin UI; solo consultas SQL directas sobre las tablas. |
| **RF-15** | Scripts PowerShell | 🟡 Simplificado | Solo `build.ps1` (motor C) y `smoke-test.ps1` (prueba del flujo). |

### 4.2 Requisitos no funcionales aplicables al MVP

| ID PRD | Categoría | Aplicación en el MVP |
|---|---|---|
| **RNF-01** | Rendimiento | Sí, medible: motor C en microsegundos; extremo a extremo ≤ 1 s (objetivo). |
| **RNF-03** | Seguridad | Mínima viable: API Key + TLS en el canal. |
| **RNF-04** | Auditoría | Sí: toda evaluación deja registro en Oracle DB. |
| **RNF-05** | Integridad ACID | Sí: transacción + evaluación + auditoría se persisten de forma atómica. |
| **RNF-07** | Confiabilidad de mensajería | Simplificada: envío único por alerta (sin reintentos). |
| **RNF-09** | Mantenibilidad | Sí: repositorio Git con README y scripts básicos. |
| Otros RNF (02, 06, 08, 10) | Disponibilidad, escalabilidad, usabilidad, portabilidad | Diferidos a fase de producción; se documentan como objetivo post-MVP. |

## 5. Historias de usuario del MVP y criterios de aceptación

| ID | Historia | Criterios de aceptación |
|---|---|---|
| **HU-MVP-01** | Como **sistema ERP/banca**, quiero enviar una transacción JSON y recibir el score con la decisión. | (1) `POST /api/v1/transacciones` con JSON válido responde HTTP 200 con `score` (0.0–1.0), `clasificacion` y `decision`; (2) la respuesta llega en ≤ 1 s; (3) un JSON inválido responde HTTP 400 con error descriptivo. |
| **HU-MVP-02** | Como **analista de riesgo**, quiero que el score considere las 3 variables del modelo. | (1) Monto muy superior al historial eleva el score; (2) ≥ N transacciones en una hora elevan el score; (3) ubicación/IP inusual eleva el score; (4) pruebas unitarias del motor C verifican los 3 casos y los umbrales de clasificación. |
| **HU-MVP-03** | Como **oficial de seguridad**, quiero la decisión automática correcta por umbral. | (1) Score < 0.4 → decisión `APROBADA`, sin push; (2) 0.4–0.7 → `EN_REVISION` + alerta preventiva; (3) > 0.7 → `BLOQUEADA` + alerta de fraude; (4) verificado con casos de prueba por defecto de cada banda. |
| **HU-MVP-04** | Como **auditor**, quiero el registro completo de cada evaluación. | (1) Cada transacción evaluada genera registro en `EVALUACION` y `AUDITORIA`; (2) ninguna evaluación queda sin registro (atomicidad); (3) el registro incluye variables, score, clasificación, decisión y timestamp. |
| **HU-MVP-05** | Como **oficial de seguridad**, quiero recibir push inmediato ante riesgo medio/alto. | (1) Score ≥ 0.4 genera notificación FCM al dispositivo registrado en < 2 s; (2) la notificación muestra monto, score y clasificación; (3) score < 0.4 no genera notificación. |
| **HU-MVP-06** | Como **oficial de seguridad**, quiero bloquear preventivamente desde la app. | (1) Desde la alerta se puede ejecutar "Bloquear"; (2) el bloqueo se persiste en Oracle (`ALERTA.estado = BLOQUEADA` + registro de auditoría con usuario y timestamp); (3) la app muestra confirmación del bloqueo. |

## 6. Arquitectura del MVP (simplificada)

Se conservan los 5 componentes núcleo del PRD; se excluyen dashboard web y Realtime Database.

```
+--------------------+   HTTP/JSON    +--------------------+   invocación    +---------------------+
| ERP / Banca /      | -------------> |   API REST PHP 8+  | ------------->  |   MOTOR C (score)   |
| Pasarela (simulado | <------------- |  - valida (API Key)| <-------------  |   3 variables,      |
|  con datos de      |  score/decisión|  - decide          |   score 0.0-1.0 |   microsegundos     |
|  prueba)           |                |  - persiste (ACID) |                 +---------------------+
+--------------------+                +---------+----------+
                                                |  SQL (ACID + auditoría)
                                                v
                                      +---------------------+
                                      |  ORACLE DB (XE dev) |
                                      | TRANSACCION/EVALUA- |
                                      | CION/ALERTA/AUDITOR.|
                                      +----------+----------+
                                                 |  push (score >= 0.4)
                                                 v
                                      +---------------------+             +--------------------------+
                                      | FIREBASE FCM        | --------->  | APP ANDROID (Java, MVP)  |
                                      | (solo mensajería)   |             | - registra token         |
                                      +---------------------+             | - bandeja de alertas     |
                                                                          | - botón "Bloquear" (API) |
                                                                          +--------------------------+
```

**Diferencias clave respecto a la arquitectura del PRD:**

- El sistema origen se simula con un cliente de pruebas (script PowerShell o Postman/curl) y un dataset sintético.
- No hay dashboard web ni Firebase Realtime Database; la app Android se comunica con el backend por la API REST (bloqueo) y FCM (recepción de alertas).
- Oracle corre en edición gratuita para desarrollo (Oracle XE) con el esquema reducido del MVP.

## 7. Flujo end-to-end del MVP

1. El cliente de pruebas envía `POST /api/v1/transacciones` (JSON con monto, cliente, ubicación, IP, hora).
2. La API PHP valida API Key, estructura y campos obligatorios.
3. PHP invoca al ejecutable C con las variables de la transacción.
4. El motor C calcula el **score (0.0–1.0)** con: desviación de monto vs. historial, frecuencia por hora y ubicación/IP inusual.
5. PHP clasifica y decide: **< 0.4 Aprobada** · **0.4–0.7 En Revisión + alerta preventiva** · **> 0.7 Bloqueo inmediato + alerta de fraude**.
6. Se persiste de forma atómica en Oracle DB: `TRANSACCION` + `EVALUACION` + `ALERTA` (si aplica) + `AUDITORIA`.
7. Si el score es ≥ 0.4, se envía push vía FCM a los dispositivos registrados.
8. La app Android muestra la alerta (monto, score, clasificación) y permite ejecutar **"Bloquear"**.
9. El bloqueo se envía a la API REST, se actualiza la `ALERTA` y se registra en `AUDITORIA` (usuario + timestamp).
10. El sistema origen ya recibió en el paso 5 la respuesta sincrónica con score, clasificación y decisión.

## Anexo A — Contrato JSON de ejemplo del MVP

**Petición** — `POST /api/v1/transacciones` (header: `X-API-Key`)

```json
{
  "id_cliente": "CLI-001",
  "monto": 15499.00,
  "moneda": "MXN",
  "origen": "ERP",
  "ubicacion": "CDMX",
  "ip_origen": "203.0.113.45",
  "fecha_hora": "2026-09-21T10:15:00-06:00"
}
```

**Respuesta** — `HTTP 200`

```json
{
  "id_transaccion": "TRX-2026-000123",
  "score": 0.82,
  "clasificacion": "ALTO",
  "decision": "BLOQUEADA",
  "fecha_evaluacion": "2026-09-21T10:15:00-06:00",
  "alerta_id": "ALR-2026-000045"
}
```

> Nota: el contrato es una línea base para el Sprint 1; los nombres definitivos se congelarán al aprobarse el diseño de la API.

## 8. Fuera del alcance del MVP (backlog post-MVP)

Los siguientes elementos del PRD **no** se construyen en el MVP y pasan al backlog de evolución:

| Elemento diferido | Referencia PRD | Justificación |
|---|---|---|
| Dashboard web administrativo | RF-13 | Aporta monitoreo, no el ciclo de detección; el MVP lo cubre con SQL y logs. |
| Consulta de historial en UI | RF-14 | No bloquea la detección end-to-end. |
| Sincronización por Firebase Realtime Database | RF-12 | FCM basta para notificar; el bloqueo se hace por API REST. |
| Umbrales configurables en caliente | Supuesto PRD §15 | En el MVP los umbrales 0.4/0.7 son fijos y definidos en código. |
| Reintentos/cola de reenvío de notificaciones | RNF-07 completo | Se entrega envío único; el refuerzo es de producción. |
| Optimización de la invocación C (proceso persistente/pipes) | Riesgo PRD §14 | El MVP usa invocación simple y mide; la optimización se decide con datos. |
| Reportes y exportaciones | Fuera de alcance PRD | Evolución futura. |
| Disponibilidad 99.5 %, escalabilidad horizontal | RNF-02/RNF-06 | Objetivos de producción, no de validación. |
| Modelo ML entrenado | Evolución PRD §15 | El MVP usa scoring por reglas con las 3 variables. |
| App iOS | Fuera de alcance PRD | No aplica. |

## 9. Criterios de aceptación del MVP (Definition of Done)

El MVP se considera **terminado** cuando se cumple el 100 % de los siguientes puntos:

1. **Flujo completo demostrable:** una transacción de prueba recorre todo el ciclo (ingesta → score → decisión → auditoría → push → bloqueo) sin intervención manual intermedia.
2. **Motor C:** compila con `build.ps1`, pasa sus pruebas unitarias (3 variables + umbrales) y se reporta su latencia medida en microsegundos.
3. **API REST:** `POST /api/v1/transacciones` responde score + clasificación + decisión en ≤ 1 s; rechaza JSON inválido con HTTP 400; valida API Key.
4. **Persistencia:** transacción, evaluación, alerta y auditoría se registran de forma atómica en Oracle XE; no existe evaluación sin registro de auditoría.
5. **Notificaciones:** score ≥ 0.4 genera push FCM recibido en la app Android en < 2 s; score < 0.4 no genera notificación.
6. **Bloqueo preventivo:** la acción "Bloquear" desde la app persiste el cambio de estado en Oracle y queda reflejada en auditoría con usuario y timestamp.
7. **Clasificación correcta:** los casos de prueba definidos por banda de riesgo (bajo/medio/alto) se resuelven con la decisión esperada al 100 %.
8. **Automatización mínima:** `build.ps1` y `smoke-test.ps1` ejecutan compilación y prueba del flujo sin pasos manuales.
9. **Documentación:** repositorio Git con README (instalación y ejecución) y manual breve de la demo.
10. **Demo final:** presentación del flujo end-to-end ante el asesor/revisor con dataset sintético.

## 10. Métricas de éxito del MVP

| Métrica | Objetivo del MVP | Cómo se mide |
|---|---|---|
| Latencia de evaluación del motor C | Orden de microsegundos | Benchmark dentro de las pruebas unitarias del motor. |
| Latencia extremo a extremo (API) | ≤ 1 s | Logs/timestamps de la API en el smoke test. |
| Entrega de push (riesgo ≥ 0.4) | < 2 s | Timestamp del envío FCM vs. recepción en la app. |
| Cobertura de auditoría | 100 % de evaluaciones registradas | Consulta de control: evaluaciones vs. registros de auditoría. |
| Casos de prueba por banda de riesgo | 100 % correctos | Suite de casos bajo/medio/alto en `smoke-test.ps1`. |
| Bloqueos preventivos trazables | 100 % con registro de usuario y timestamp | Consulta a `ALERTA` + `AUDITORIA`. |

## 11. Plan de sprints del MVP (4 sprints / 8 semanas)

| Sprint | Semanas | Objetivo | Entregables clave |
|---|---|---|---|
| **Sprint 1** | 1–2 | Contrato JSON congelado, modelo de datos y motor C funcionando. | Contrato JSON aprobado; scripts DDL de Oracle XE (`TRANSACCION`, `EVALUACION`, `ALERTA`, `AUDITORIA`); motor C con las 3 variables y umbrales + pruebas unitarias; `build.ps1`; dataset sintético de pruebas. |
| **Sprint 2** | 3–4 | API REST integrada con el motor C y Oracle DB. | Endpoint `POST /api/v1/transacciones` con validación y API Key; invocación del ejecutable C; persistencia atómica + auditoría; respuesta sincrónica; primer `smoke-test.ps1`. |
| **Sprint 3** | 5–6 | Notificación en tiempo real y app Android básica. | Proyecto Firebase (FCM); envío de push para score ≥ 0.4; app Android con registro de token, bandeja de alertas y detalle (monto, score, clasificación). |
| **Sprint 4** | 7–8 | Bloqueo preventivo, pruebas E2E y demo. | Acción "Bloquear" en la app → API → Oracle + auditoría; suite de casos por banda de riesgo; benchmarks de latencia; README y demo final. |

## 12. Riesgos técnicos del MVP y mitigación

| Riesgo | Sprint de impacto | Mitigación |
|---|---|---|
| Latencia de crear un proceso C por cada transacción (invocación desde PHP). | Sprint 2 | Medir en el smoke test desde el primer día; si supera el objetivo, aplicar plan B documentado (proceso persistente/pipes) como primer ítem del post-MVP. |
| Instalación y conexión de Oracle XE en el entorno de residencia. | Sprint 1–2 | Instalar Oracle XE en el Sprint 1 con DDL mínimo; mantener scripts re-ejecutables. |
| Recepción de push en emulador Android (FCM en emulador requiere Google Play services). | Sprint 3 | Contar con al menos un dispositivo físico de prueba desde el Sprint 3. |
| Datos de prueba poco representativos (falsos resultados del scoring). | Sprint 1 | Generar dataset sintético con casos por banda: bajo, medio y alto riesgo, revisado por el asesor. |
| Bloqueo desde la app requiere autenticación mínima no definida. | Sprint 4 | Definir en Sprint 3 un identificador de oficial + API Key para la acción de bloqueo (seguridad mínima viable). |
| Alcance creciente (scope creep) por funcionalidades del PRD atractivas. | Todos | Este documento es el contrato de alcance: todo lo de la sección 8 se rechaza por defecto en el Product Backlog del MVP. |

## 13. Post-MVP — hoja de ruta de evolución

| Versión | Alcance propuesto |
|---|---|
| **v1.1** | Dashboard web (RF-13), consulta de historial (RF-14) y sincronización con Firebase Realtime Database (RF-12). |
| **v1.2** | Umbrales configurables, reintentos y cola de reenvío de notificaciones, optimización de la invocación del motor C (proceso persistente/pipes), endurecimiento de seguridad (roles, TLS estricto). |
| **v2.0** | Modelo de ML entrenado sobre histórico de evaluaciones, escalabilidad horizontal, alta disponibilidad (objetivos RNF-02/RNF-06) y reportes/exportaciones. |

**Decisión de paso a v1.1:** el MVP debe cumplir el 100 % de la sección 9 (Definition of Done) y sus métricas de la sección 10.

---

**Fin del documento — MVP MIDFF v1.0**




