# BITÁCORA DEL PROYECTO — MIDFF

**Motor Inteligente de Detección de Fraude Financiero**

| Campo | Valor |
|---|---|
| **Proyecto** | MIDFF — Motor Inteligente de Detección de Fraude Financiero |
| **Título del proyecto** | Desarrollo de un Motor Inteligente de Detección de Fraude Financiero con Arquitectura Híbrida (Android, C, PHP, Oracle DB y Firebase) |
| **Tipo de proyecto** | Residencias Profesionales — Ingeniería en Sistemas Computacionales |
| **Documentos base** | `MVP_MIDFF.md` v1.0 (alcance del MVP) y `PRD_MIDFF.md` v1.0 |
| **Versión de la bitácora** | 1.0 |
| **Fecha de apertura** | 23/09/2026 |
| **Última actualización** | 23/09/2026 |
| **Metodología** | Scrum — 4 sprints de 2 semanas (8 semanas) |
| **Control de versiones** | Git (rama principal `master`) |
| **Stack** | C · PHP 8+ · Oracle Database XE · Android (Java) · Firebase FCM · PowerShell |
| **Responsable** | Residente del proyecto |
| **Estado** | Sprint 1 — en progreso |

---

## Índice

1. [Propósito y reglas de uso de la bitácora](#1-propósito-y-reglas-de-uso-de-la-bitácora)
2. [Objetivo general del proyecto](#2-objetivo-general-del-proyecto)
3. [Alcance del MVP](#3-alcance-del-mvp)
4. [Estructura del proyecto](#4-estructura-del-proyecto)
5. [Plan de 4 sprints](#5-plan-de-4-sprints)
6. [Definition of Done del MVP](#6-definition-of-done-del-mvp)
7. [Métricas de seguimiento](#7-métricas-de-seguimiento)
8. [Riesgos y mitigación](#8-riesgos-y-mitigación)
9. [Registro de entradas de la bitácora](#9-registro-de-entradas-de-la-bitácora)
10. [Plantilla para nuevas entradas](#10-plantilla-para-nuevas-entradas)
11. [Control de cambios de este documento](#11-control-de-cambios-de-este-documento)

---

## 1. Propósito y reglas de uso de la bitácora

Este documento es la **memoria de trabajo del proyecto MIDFF**: registra de forma cronológica
y auditable qué se hizo, qué se decidió, qué se entregó y qué quedó pendiente en cada sesión
de trabajo o cierre de sprint.

**Reglas de uso:**

1. Cada entrada lleva un **identificador consecutivo** (`BIT-001`, `BIT-002`, …), la **fecha**
   (formato `dd/mm/yyyy`), el **sprint** al que pertenece y el **estado**
   (`Abierta` · `En progreso` · `Cerrada` · `Bloqueada`).
2. Se registra **una entrada por sesión de trabajo relevante** (no se registran tareas triviales)
   y **una entrada de cierre por sprint** con la revisión de entregables.
3. Las entradas **no se borran ni se reescriben**: si algo cambia, se agrega una **nota de
   corrección** dentro de la entrada siguiente, manteniendo la trazabilidad.
4. Toda decisión técnica debe quedar con su **justificación** y vincularse al requisito del
   MVP/PRD que la origina (p. ej. `RF-04`, `HU-MVP-02`, `RNF-04`).
5. Las **evidencias** (commits, rutas de archivo, capturas, resultados de pruebas o benchmarks)
   se anotan en la entrada; los artefactos generados (ejecutables, `*.apk`, dumps, logs) **no se
   versionan** porque están excluidos en `.gitignore`.
6. Este documento **no sustituye** al `README.md` (instalación/operación) ni al
   `MVP_MIDFF.md` (contrato de alcance). Cualquier funcionalidad nueva fuera del MVP se anota
   como candidata al **backlog post-MVP** (sección 8 de `MVP_MIDFF.md`) y no se implementa
   durante el MVP salvo aprobación expresa del asesor.

---

## 2. Objetivo general del proyecto

### 2.1 Objetivo general (PRD_MIDFF.md §3.1)

> Desarrollar un motor inteligente de detección de fraude financiero con arquitectura híbrida
> que evalúe transacciones en tiempo real, calcule un score de riesgo de 0.0 a 1.0, clasifique
> la operación y notifique/bloquee preventivamente según el nivel de riesgo, garantizando la
> auditoría completa de cada decisión.

### 2.2 Objetivo específico del MVP (MVP_MIDFF.md §1–§2)

Entregar una **rebanada vertical completa y funcional** que demuestre el ciclo extremo a extremo
con datos de prueba:

> **Transacción → Score (motor C) → Clasificación y decisión → Auditoría (Oracle DB) →
> Notificación push (FCM) → Bloqueo preventivo (App Android)**

**Hipótesis a validar:**

> "Un motor de scoring en C de ultra baja latencia, orquestado por una API REST en PHP y
> notificado vía Firebase FCM, permite **detectar y detener transacciones fraudulentas en
> tiempo real**, con trazabilidad completa de cada decisión."

### 2.3 Objetivos medibles del MVP (MVP_MIDFF.md §2)

1. Demostrar el flujo completo de detección en tiempo real con transacciones simuladas.
2. Validar la integración del stack híbrido (C + PHP 8+ + Oracle DB + Firebase + Android/Java).
3. Medir la latencia real: motor C en microsegundos y ciclo extremo a extremo ≤ 1 s.
4. Validar la utilidad operativa de la alerta push y del bloqueo preventivo inmediato.
5. Dejar una base de código extensible en Git sobre la cual construir el resto del PRD.

**Regla de clasificación vigente (umbrales fijos del MVP):**

| Banda | Rango de score | Decisión | Acción |
|---|---|---|---|
| **Bajo** | `< 0.4` | `APROBADA` | Sin notificación |
| **Medio** | `0.4 – 0.7` | `EN_REVISION` | Alerta preventiva + push FCM |
| **Alto** | `> 0.7` | `BLOQUEADA` | Alerta de fraude + push FCM |


## 3. Alcance del MVP

### 3.1 Flujo end-to-end que se debe demostrar (MVP_MIDFF.md §7)

1. El cliente de pruebas envía `POST /api/v1/transacciones` (JSON con monto, cliente, ubicación, IP, hora).
2. La API PHP valida **API Key**, estructura y campos obligatorios.
3. PHP invoca al ejecutable del **motor C** con las variables de la transacción.
4. El motor C calcula el **score (0.0 – 1.0)**: desviación de monto vs. historial, frecuencia por hora y ubicación/IP inusual.
5. PHP clasifica y decide: `< 0.4` Aprobada · `0.4 – 0.7` En Revisión + alerta preventiva · `> 0.7` Bloqueo inmediato + alerta de fraude.
6. Persistencia **atómica** en Oracle DB: `TRANSACCION` + `EVALUACION` + `ALERTA` (si aplica) + `AUDITORIA`.
7. Si el score es `≥ 0.4`, se envía **push vía FCM** a los dispositivos registrados.
8. La app Android muestra la alerta (monto, score, clasificación) y permite ejecutar **"Bloquear"**.
9. El bloqueo viaja a la API REST, actualiza `ALERTA` y se registra en `AUDITORIA` (usuario + timestamp).
10. El sistema origen recibe la **respuesta sincrónica** con score, clasificación y decisión (paso 5).

### 3.2 Requisitos del PRD dentro del MVP (MVP_MIDFF.md §4.1)

| ID PRD | Requisito | Estado en el MVP |
|---|---|---|
| **RF-01** | Ingesta HTTP/JSON | ✅ Incluido — un solo endpoint `POST /api/v1/transacciones`. |
| **RF-02** | Validación de entrada | 🟡 Simplificado — campos obligatorios, tipos y API Key. |
| **RF-03** | Invocación del motor C | ✅ Incluido — ejecutable por línea de comandos (stdin/stdout). |
| **RF-04** | Cálculo del score (0.0–1.0) | ✅ Incluido — 3 variables (monto, frecuencia, ubicación/IP). |
| **RF-05** | Clasificación del riesgo | ✅ Incluido — umbrales fijos 0.4 / 0.7. |
| **RF-06** | Respuesta sincrónica | ✅ Incluido — JSON con score, clasificación y decisión. |
| **RF-07** | Persistencia ACID en Oracle | ✅ Incluido — Oracle XE de desarrollo. |
| **RF-08** | Auditoría de cada evaluación | 🟡 Simplificado — variables, score, clasificación, decisión y timestamp. |
| **RF-09** | Notificación push FCM | ✅ Incluido — solo score `≥ 0.4`. |
| **RF-10** | App Android de alertas | ✅ Incluido — token FCM + bandeja de alertas + detalle. |
| **RF-11** | Bloqueo preventivo | ✅ Incluido — acción "Bloquear" desde la app hacia la API. |
| **RF-12** | Firebase Realtime DB | ⏸️ Diferido — el estado se consulta por API REST. |
| **RF-13** | Dashboard web | ⏸️ Diferido — monitoreo con SQL y logs. |
| **RF-14** | Consulta de historial (UI) | ⏸️ Diferido — solo consultas SQL directas. |
| **RF-15** | Scripts PowerShell | 🟡 Simplificado — `build.ps1` y `smoke-test.ps1`. |

### 3.3 Modelo de datos mínimo del MVP (PRD_MIDFF.md §9)

| Tabla | Contenido | Sprint que la crea |
|---|---|---|
| `TRANSACCION` | Transacción recibida (`id`, `origen`, `id_cliente`, `monto`, `moneda`, `fecha_hora`, `ubicacion`, `ip_origen`, `canal`). | Sprint 1 |
| `EVALUACION` | Resultado del motor C (`id`, `id_transaccion`, `score`, `variables_usadas`, `clasificacion`, `decision`, `fecha_evaluacion`). | Sprint 1 |
| `ALERTA` | Alerta emitida (`id`, `id_evaluacion`, `tipo` PREVENTIVA/FRAUDE, `estado` PENDIENTE/ATENDIDA/BLOQUEADA, `atendida_por`, `respuesta`). | Sprint 1 (DDL) / Sprint 3 (push) |
| `DISPOSITIVO_MOVIL` | Dispositivos registrados para notificación (`id`, `usuario`, `rol`, `token_fcm`, `activo`). | Sprint 3 |
| `AUDITORIA` | Registro inmutable de cada evaluación y decisión (`id`, `entidad`, `accion`, `usuario_o_sistema`, `detalle`, `fecha_hora`). | Sprint 1 (DDL) / Sprint 2 (escritura) |


## 4. Estructura del proyecto

Estructura derivada de los 5 componentes núcleo del MVP (`MVP_MIDFF.md` §6) y de los
entregables del PRD (§12). Cada carpeta corresponde a una tecnología del stack híbrido y
respeta las reglas de `.gitignore` (lo generado no se versiona; la plantilla `*.example` sí).

```text
C:\todoMIDFF\
├── .gitignore                     # Exclusiones del stack híbrido (C/PHP/Oracle/Android/PS)
├── README.md                      # Descripción, instalación y operación
├── PRD_MIDFF.md                   # Documento de requisitos del producto (v1.0)
├── MVP_MIDFF.md                   # Contrato de alcance del MVP (documento base)
├── BITACORA.md                    # Este documento: memoria de trabajo y decisiones
├── ideas.txt                      # Idea original del proyecto (fuente del PRD)
│
├── docs/                          # Documentación de apoyo (entregable 9 del PRD)
│   ├── contratos/
│   │   └── api-transacciones-v1.md        # Contrato JSON congelado (Anexo A del MVP)
│   ├── manuales/
│   │   ├── instalacion.md                 # Instalación del entorno completo
│   │   ├── operacion.md                   # Operación y monitoreo por SQL/logs
│   │   └── demo.md                        # Guion de la demostración final
│   └── diagramas/
│       ├── arquitectura-mvp.png           # Diagrama de la arquitectura simplificada
│       └── flujo-end-to-end.png           # Flujo de los 10 pasos del MVP
│
├── motor-c/                       # COMPONENTE 1: motor de scoring (C)
│   ├── include/
│   │   ├── score.h                        # Contrato del cálculo del score (0.0–1.0)
│   │   ├── variables.h                    # Modelo de las 3 variables del RF-04
│   │   ├── reglas.h                       # Umbrales 0.4 / 0.7 y clasificación
│   │   └── json_io.h                      # Entrada/salida JSON por stdin/stdout
│   ├── src/
│   │   ├── main.c                         # Punto de entrada del ejecutable (RF-03)
│   │   ├── score.c                        # Cálculo ponderado del score (RF-04)
│   │   ├── variables.c                    # Desviación de monto, frecuencia, ubicación/IP
│   │   ├── reglas.c                       # Clasificación BAJO/MEDIO/ALTO (RF-05)
│   │   └── json_io.c                      # Parseo mínimo del JSON de entrada
│   ├── pruebas/                           # Pruebas unitarias del motor (HU-MVP-02)
│   │   ├── test_variables.c               # Un caso por variable
│   │   ├── test_umbrales.c                # Fronteras 0.4 / 0.7
│   │   └── test_latencia.c                # Benchmark en microsegundos (RNF-01)
│   ├── Makefile                           # Compilación con gcc/MinGW-w64
│   ├── build/                             # (ignorado) objetos y binarios intermedios
│   └── bin/midff-score.exe                # (ignorado) ejecutable invocado por PHP
│
├── api/                           # COMPONENTE 2: API REST PHP 8+ (RF-01, RF-06)
│   ├── public/
│   │   ├── index.php                      # Front controller: POST /api/v1/transacciones
│   │   └── .htaccess                      # Reescritura de rutas
│   ├── src/
│   │   ├── Controlador/
│   │   │   ├── TransaccionController.php  # Ingesta y respuesta sincrónica (HU-MVP-01)
│   │   │   └── AlertaController.php       # Bloqueo preventivo desde la app (HU-MVP-06)
│   │   ├── Servicio/
│   │   │   ├── MotorCService.php          # Invocación del ejecutable C (RF-03)
│   │   │   ├── ClasificadorService.php    # Umbrales y decisión (RF-05)
│   │   │   ├── OracleService.php          # Conexión y transacción ACID (RF-07)
│   │   │   ├── AuditoriaService.php       # Registro inmutable (RF-08, RNF-04)
│   │   │   └── FcmService.php             # Envío de push para score >= 0.4 (RF-09)
│   │   ├── Middleware/
│   │   │   ├── ApiKeyMiddleware.php       # Autenticación mínima viable (RF-02)
│   │   │   └── ValidadorTransaccion.php   # Validación de campos, tipos y fechas
│   │   └── Config/
│   │       ├── config.example.php         # Plantilla versionable (sin credenciales)
│   │       └── config.local.php           # (ignorado) credenciales del entorno
│   ├── pruebas/
│   │   ├── ClasificadorTest.php           # Umbrales y decisiones (PHPUnit)
│   │   └── ValidadorTest.php              # Casos de JSON inválido (HTTP 400)
│   ├── composer.json
│   └── vendor/                            # (ignorado) dependencias de Composer
├── db/                            # COMPONENTE 3: persistencia Oracle (RF-07, RF-08)
│   ├── ddl/
│   │   ├── 01_esquema.sql                 # Usuario/esquema MIDFF y privilegios
│   │   ├── 02_tablas.sql                  # TRANSACCION, EVALUACION, ALERTA,
│   │   │                                  # DISPOSITIVO_MOVIL, AUDITORIA
│   │   ├── 03_restricciones_indices.sql   # PK, FK, CHECK de score/estado e índices
│   │   ├── 04_secuencias.sql              # Secuencias de folios (TRX-2026-000123)
│   │   └── 05_trigger_auditoria.sql       # Trigger de auditoría inmutable
│   ├── dml/
│   │   ├── seed_datos_prueba.sql          # Datos por banda de riesgo
│   │   └── consultas_control.sql          # Cobertura de auditoría y consultas de control
│   ├── scripts/
│   │   ├── desplegar_esquema.sql          # Ejecución ordenada con SQL*Plus
│   │   └── reiniciar_esquema.sql          # Script re-ejecutable del entorno de pruebas
│   └── tnsnames.ora.example               # (versionable) plantilla de conexión Oracle XE
│
├── android/                       # COMPONENTE 4: app de alertas (Java) — RF-10, RF-11
│   └── MidffAlertas/
│       ├── app/
│       │   ├── src/main/AndroidManifest.xml
│       │   ├── src/main/java/mx/tecnm/midff/alertas/
│       │   │   ├── MainActivity.java          # Registro de token FCM
│       │   │   ├── AlertasActivity.java       # Bandeja de alertas
│       │   │   ├── DetalleAlertaActivity.java # Detalle: monto, score, clasificación
│       │   │   ├── AdaptadorAlertas.java      # Lista de alertas
│       │   │   ├── NotificacionFcmService.java# Recepción del push (RF-09)
│       │   │   ├── ApiClient.java             # Consumo de la API REST (bloqueo)
│       │   │   └── ModeloAlerta.java          # Modelo de la alerta
│       │   ├── src/main/res/                  # Layouts, strings, colores e íconos
│       │   ├── src/test/java/.../ClasificacionTest.java
│       │   └── build.gradle
│       ├── build.gradle
│       ├── settings.gradle
│       ├── gradle.properties
│       ├── gradle/wrapper/                    # (versionable) wrapper de Gradle
│       ├── google-services.json.example       # (versionable) plantilla de Firebase
│       └── local.properties                   # (ignorado) ruta del SDK local
│
├── firebase/                      # COMPONENTE 5: mensajería en tiempo real (RF-09)
│   ├── README-fcm.md                      # Proyecto, claves y registro de token
│   └── plantillas/
│       ├── payload-alerta-preventiva.json # Notificación para banda media
│       └── payload-alerta-fraude.json     # Notificación para banda alta
│
├── scripts/                       # Automatización PowerShell (RF-15)
│   ├── build.ps1                          # Compila el motor C y ejecuta sus pruebas
│   ├── smoke-test.ps1                     # Flujo end-to-end por banda de riesgo
│   ├── desplegar.ps1                      # Despliegue local (PHP + DDL + binario C)
│   ├── config.psd1                        # Parámetros del entorno (sin credenciales)
│   └── lib/MidffComun.psm1                # Funciones comunes (logs, HTTP, validaciones)
│
├── pruebas/                       # Casos y evidencia de calidad (métricas §10 del MVP)
│   ├── casos/
│   │   ├── banda-bajo.json                # Entradas esperadas < 0.4
│   │   ├── banda-medio.json               # Entradas esperadas 0.4 – 0.7
│   │   ├── banda-alto.json                # Entradas esperadas > 0.7
│   │   └── esperados.json                 # Resultado esperado por caso
│   └── e2e/flujo-completo.ps1             # Orquesta el ciclo de los 10 pasos
│
└── datos/                         # Insumos de prueba (dataset sintético)
    ├── datasets/
    │   ├── transacciones_bajo.json        # Cliente con comportamiento normal
    │   ├── transacciones_medio.json       # Comportamiento atípico moderado
    │   └── transacciones_alto.json        # Patrón claro de fraude
    └── resultados/                        # (ignorado) salidas de benchmarks
        └── .gitkeep                       # Mantiene la carpeta en el repositorio
```

### 4.1 Trazabilidad componente → carpeta → sprint

| Componente (MVP §6) | Carpeta | Tecnología | Requisitos | Sprint |
|---|---|---|---|---|
| Motor de scoring | `motor-c/` | C (gcc/MinGW-w64) | RF-03, RF-04, RF-05, RNF-01 | 1 |
| Base de datos | `db/` | Oracle Database XE + SQL*Plus | RF-07, RF-08, RNF-04, RNF-05 | 1–2 |
| API REST | `api/` | PHP 8+ / Composer | RF-01, RF-02, RF-06, RF-09 | 2 |
| Mensajería | `firebase/` | Firebase FCM | RF-09, RNF-07 | 3 |
| App móvil | `android/` | Android Studio (Java) + Gradle | RF-10, RF-11 | 3–4 |
| Automatización | `scripts/` | PowerShell + Pester | RF-15 | 1–2 |
| Calidad y datos | `pruebas/`, `datos/` | JSON + PowerShell | Métricas §10 del MVP | 2–4 |
| Documentación | `docs/`, `*.md` | Markdown | RNF-09 | Todos |

### 4.2 Convenciones de trabajo

| Aspecto | Convención |
|---|---|
| **Ramas** | `master` (estable y demostrable) · `sprint-N/<tema>` para trabajo en curso (p. ej. `sprint-1/motor-c-score`) · `hotfix/<tema>`. |
| **Commits** | `<tipo>(<alcance>): <descripción en imperativo>`, con tipos `feat`, `fix`, `docs`, `test`, `build`, `refactor`, `chore`; alcance = componente (`motor-c`, `api`, `db`, `android`, `scripts`, `docs`). |
| **Idioma** | Carpetas, archivos y documentación en **español**; identificadores de código en español cuando representan el dominio del negocio (`calcularScore`, `ClasificadorService`). |
| **Fin de línea** | `core.autocrlf=true` en Windows; `.ps1` y `*.sql` se guardan con CRLF. |
| **Secretos** | Nunca se versionan API Keys, `google-services.json`, contraseñas de Oracle ni `local.properties`; se versiona la plantilla `*.example`. Reglas en `.gitignore`. |
| **Terminado por tarea** | Código funcionando + prueba que lo respalde + referencia al requisito (`RF-xx` / `HU-MVP-xx`) + entrada en esta bitácora. |
| **Control de alcance** | Todo lo listado en `MVP_MIDFF.md` §8 (dashboard, Realtime DB, ML, reportes) queda fuera del MVP y solo se anota como candidato post-MVP. |


## 5. Plan de 4 sprints

Cada sprint dura **2 semanas** (10 días hábiles). El plan es **tentativo** y se confirma en cada
Sprint Planning; los entregables, en cambio, provienen de `MVP_MIDFF.md` §11 y son el
compromiso de alcance del MVP.

### 5.1 Resumen del plan

| Sprint | Semanas | Objetivo del sprint | Entregables clave |
|---|---|---|---|
| **Sprint 1** | 1–2 | Contrato JSON congelado, modelo de datos y motor C funcionando. | Contrato JSON aprobado; scripts DDL de Oracle XE (`TRANSACCION`, `EVALUACION`, `ALERTA`, `AUDITORIA`); motor C con las 3 variables y umbrales + pruebas unitarias; `build.ps1`; dataset sintético. |
| **Sprint 2** | 3–4 | API REST integrada con el motor C y Oracle DB. | `POST /api/v1/transacciones` con validación y API Key; invocación del ejecutable C; persistencia atómica + auditoría; respuesta sincrónica; primer `smoke-test.ps1`. |
| **Sprint 3** | 5–6 | Notificación en tiempo real y app Android básica. | Proyecto Firebase (FCM); envío de push para score ≥ 0.4; app Android con registro de token, bandeja de alertas y detalle (monto, score, clasificación). |
| **Sprint 4** | 7–8 | Bloqueo preventivo, pruebas E2E y demo. | Acción "Bloquear" en la app → API → Oracle + auditoría; suite de casos por banda de riesgo; benchmarks de latencia; `README.md` y demo final. |

### 5.2 Calendario tentativo (a partir de la apertura de la bitácora)

| Sprint | Inicio | Fin | Duración |
|---|---|---|---|
| **Sprint 1** | 23/09/2026 | 06/10/2026 | 2 semanas |
| **Sprint 2** | 07/10/2026 | 20/10/2026 | 2 semanas |
| **Sprint 3** | 21/10/2026 | 03/11/2026 | 2 semanas |
| **Sprint 4** | 04/11/2026 | 17/11/2026 | 2 semanas |
| **Fin del MVP** | — | 17/11/2026 | 8 semanas en total |

### 5.3 Sprint 1 — Fundaciones y motor C (semanas 1–2)

- **Sprint Goal:** dejar el contrato de datos congelado, el esquema Oracle creado y el motor C
  calculando el score con las tres variables y clasificando por umbral.
- **Historias comprometidas:** HU-MVP-02 (score con las 3 variables) y la base de HU-MVP-03 (umbrales).
- **Entregables:**
  1. Contrato JSON de entrada/salida aprobado y documentado en `docs/contratos/api-transacciones-v1.md` (Anexo A del MVP).
  2. Scripts DDL re-ejecutables en `db/ddl/` con las tablas `TRANSACCION`, `EVALUACION`, `ALERTA`, `DISPOSITIVO_MOVIL`, `AUDITORIA` y sus restricciones.
  3. Motor C en `motor-c/` con las 3 variables (desviación de monto, frecuencia por hora, ubicación/IP inusual) y umbrales `0.4` / `0.7`.
  4. Pruebas unitarias del motor: una por variable, fronteras de umbral y benchmark de latencia.
  5. `scripts/build.ps1` (compila y ejecuta pruebas) y `datos/datasets/*.json` (casos bajo/medio/alto).
- **Criterio de aceptación del sprint:** `build.ps1` compila sin errores, todas las pruebas unitarias pasan y el DDL se ejecuta limpio dos veces seguidas sobre Oracle XE.

### 5.4 Sprint 2 — API REST y persistencia (semanas 3–4)

- **Sprint Goal:** exponer la API REST que orquesta al motor C y persiste de forma atómica en Oracle.
- **Historias comprometidas:** HU-MVP-01 (ingesta y respuesta sincrónica), HU-MVP-03 (decisión por umbral) y HU-MVP-04 (auditoría completa).
- **Entregables:**
  1. Endpoint `POST /api/v1/transacciones` con validación de campos, tipos y `X-API-Key` (`api/public/index.php`).
  2. `MotorCService` que invoca el ejecutable C (stdin/stdout) y mide su latencia.
  3. Persistencia ACID: `TRANSACCION` + `EVALUACION` + `ALERTA` + `AUDITORIA` en una sola transacción.
  4. Respuesta JSON con `score`, `clasificacion`, `decision`, `fecha_evaluacion` y `alerta_id` en ≤ 1 s.
  5. Primera versión de `scripts/smoke-test.ps1` recorriendo el flujo ingesta → score → decisión → auditoría.
- **Criterio de aceptación del sprint:** JSON inválido responde `HTTP 400`, API Key inválida se rechaza, y no existe evaluación sin registro de auditoría.


### 5.5 Sprint 3 — Notificación push y app Android (semanas 5–6)

- **Sprint Goal:** que una transacción de riesgo medio/alto se convierta en una alerta visible y
  accionable en el dispositivo Android en menos de 2 segundos.
- **Historias comprometidas:** HU-MVP-05 (push inmediato ante riesgo medio/alto) y preparación de HU-MVP-06 (bloqueo).
- **Entregables:**
  1. Proyecto Firebase creado y `firebase/README-fcm.md` con el registro del token (`DISPOSITIVO_MOVIL`).
  2. `FcmService` en la API: envío de push solo cuando el score es `≥ 0.4` (con la plantilla de `firebase/plantillas/`).
  3. App Android en `android/MidffAlertas/`: registro del token FCM, bandeja de alertas y pantalla de detalle con monto, score y clasificación.
  4. Identificador de oficial + API Key definidos para la acción de bloqueo (seguridad mínima viable).
- **Criterio de aceptación del sprint:** un score `≥ 0.4` produce notificación recibida en el dispositivo en < 2 s; un score `< 0.4` no genera notificación.

### 5.6 Sprint 4 — Bloqueo preventivo, E2E y demo (semanas 7–8)

- **Sprint Goal:** cerrar el ciclo con el bloqueo preventivo trazable y demostrar el flujo completo
  ante el asesor con evidencia de métricas.
- **Historias comprometidas:** HU-MVP-06 (bloqueo desde la app) y validación final de HU-MVP-01 a HU-MVP-05.
- **Entregables:**
  1. Acción "Bloquear" en la app → `AlertaController` → `ALERTA.estado = BLOQUEADA` + registro en `AUDITORIA` (usuario y timestamp).
  2. Suite de casos por banda de riesgo en `pruebas/casos/` ejecutada por `smoke-test.ps1` (100 % de aciertos).
  3. Benchmarks de latencia: motor C en microsegundos y ciclo extremo a extremo ≤ 1 s.
  4. `README.md` actualizado con instalación y ejecución, `docs/manuales/demo.md` y demo final end-to-end.
- **Criterio de aceptación del sprint:** se cumple el 100 % del Definition of Done (sección 6) y las métricas de la sección 7.


## 6. Definition of Done del MVP

El MVP se considera **terminado** cuando se cumple el 100 % de estos puntos
(`MVP_MIDFF.md` §9). Esta lista es la que se revisa al cierre del Sprint 4:

- [ ] **1. Flujo completo demostrable:** una transacción recorre ingesta → score → decisión → auditoría → push → bloqueo sin intervención manual.
- [ ] **2. Motor C:** compila con `build.ps1`, pasa sus pruebas unitarias (3 variables + umbrales) y se reporta su latencia en microsegundos.
- [ ] **3. API REST:** `POST /api/v1/transacciones` responde score + clasificación + decisión en ≤ 1 s; rechaza JSON inválido con `HTTP 400`; valida API Key.
- [ ] **4. Persistencia:** transacción, evaluación, alerta y auditoría se registran de forma atómica en Oracle XE; no existe evaluación sin auditoría.
- [ ] **5. Notificaciones:** score ≥ 0.4 genera push recibido en la app en < 2 s; score < 0.4 no genera notificación.
- [ ] **6. Bloqueo preventivo:** "Bloquear" desde la app persiste el estado en Oracle y queda en auditoría con usuario y timestamp.
- [ ] **7. Clasificación correcta:** 100 % de los casos por banda resueltos con la decisión esperada.
- [ ] **8. Automatización mínima:** `build.ps1` y `smoke-test.ps1` corren sin pasos manuales.
- [ ] **9. Documentación:** README con instalación/ejecución y manual breve de la demo.
- [ ] **10. Demo final:** presentación del flujo end-to-end ante el asesor con dataset sintético.

## 7. Métricas de seguimiento

| Métrica | Objetivo del MVP | Cómo se mide | Estado |
|---|---|---|---|
| Latencia de evaluación del motor C | Orden de microsegundos | Benchmark en las pruebas unitarias del motor. | Pendiente (Sprint 1) |
| Latencia extremo a extremo (API) | ≤ 1 s | Logs/timestamps de la API en el smoke test. | Pendiente (Sprint 2) |
| Entrega de push (riesgo ≥ 0.4) | < 2 s | Timestamp de envío FCM vs. recepción en la app. | Pendiente (Sprint 3) |
| Cobertura de auditoría | 100 % de evaluaciones registradas | Consulta de control: evaluaciones vs. registros de auditoría. | Pendiente (Sprint 2) |
| Casos de prueba por banda de riesgo | 100 % correctos | Suite de casos bajo/medio/alto en `smoke-test.ps1`. | Pendiente (Sprint 2–4) |
| Bloqueos preventivos trazables | 100 % con usuario y timestamp | Consulta a `ALERTA` + `AUDITORIA`. | Pendiente (Sprint 4) |

> Las columnas **Estado** y **Evidencia** se actualizan al cierre de cada sprint con la salida
> real de los scripts y las consultas SQL de control.

## 8. Riesgos y mitigación

| # | Riesgo | Sprint de impacto | Mitigación | Estado |
|---|---|---|---|---|
| R-01 | Latencia de crear un proceso C por cada transacción. | Sprint 2 | Medir en el smoke test desde el primer día; si supera el objetivo, aplicar el plan B documentado (proceso persistente/pipes) como primer ítem post-MVP. | Abierto |
| R-02 | Instalación y conexión de Oracle XE en el entorno de residencia. | Sprint 1–2 | Instalar Oracle XE en el Sprint 1 con DDL mínimo; mantener scripts re-ejecutables. | En observación |
| R-03 | Recepción de push en emulador Android (FCM requiere Google Play services). | Sprint 3 | Contar con al menos un dispositivo físico de prueba desde el Sprint 3. | Abierto |
| R-04 | Datos de prueba poco representativos (falsos resultados del scoring). | Sprint 1 | Dataset sintético con casos por banda (bajo, medio, alto), revisado por el asesor. | En observación |
| R-05 | El bloqueo desde la app requiere autenticación mínima no definida. | Sprint 4 | Definir en el Sprint 3 un identificador de oficial + API Key para la acción de bloqueo. | Abierto |
| R-06 | Scope creep por funcionalidades atractivas del PRD. | Todos | `MVP_MIDFF.md` es el contrato de alcance: lo de su §8 se rechaza por defecto y pasa al backlog post-MVP. | Controlado |


## 9. Registro de entradas de la bitácora

### 9.1 Índice de entradas

| ID | Fecha | Sprint | Tipo | Título | Estado |
|---|---|---|---|---|---|
| **BIT-001** | 23/09/2026 | Sprint 1 | Apertura de proyecto | Apertura de la bitácora: objetivo general, alcance, estructura y plan de 4 sprints | Cerrada |

---

### 9.2 BIT-001 — 23/09/2026 — Apertura de la bitácora y arranque del Sprint 1

| Campo | Valor |
|---|---|
| **ID** | BIT-001 |
| **Fecha** | 23/09/2026 |
| **Sprint** | Sprint 1 (semana 1 de 8) |
| **Tipo** | Apertura de proyecto / gestión documental |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada |
| **Documentos de referencia** | `PRD_MIDFF.md` v1.0 · `MVP_MIDFF.md` v1.0 · `README.md` · `ideas.txt` |

#### Contexto

Se cuenta con el repositorio Git inicializado en `C:\todoMIDFF` y con los documentos de
requisitos (`PRD_MIDFF.md` v1.0, elaborado el 21/09/2026) y de alcance del MVP
(`MVP_MIDFF.md` v1.0) ya redactados y versionados en el primer commit
(`5da8682 — Se agregan primeras versiones del README.md, PRD y MVP de mi proyecto`).
Falta la base de trabajo del proyecto: las reglas de exclusión del control de versiones para el
stack híbrido y el documento de seguimiento del proyecto.

#### Actividades realizadas en la sesión

1. **Objetivo general y alcance registrados:** se transcribió el objetivo general del PRD (§3.1),
   la hipótesis de validación y los cinco objetivos medibles del MVP (`MVP_MIDFF.md` §2), junto con
   los umbrales de clasificación (`< 0.4` Aprobada · `0.4 – 0.7` En Revisión · `> 0.7` Bloqueada)
   y la línea base del modelo de datos (`TRANSACCION`, `EVALUACION`, `ALERTA`,
   `DISPOSITIVO_MOVIL`, `AUDITORIA`).
2. **Plan de 4 sprints registrado:** se consolidó el plan de `MVP_MIDFF.md` §11 con calendario
   tentativo, sprint goals, historias de usuario comprometidas (HU-MVP-01 a HU-MVP-06), entregables
   y criterio de aceptación por sprint (sección 5 de esta bitácora).
3. **Estructura del proyecto definida:** se documentó el árbol completo del repositorio alineado a
   los 5 componentes núcleo del MVP (`motor-c/`, `api/`, `db/`, `android/`, `firebase/`) más
   `scripts/`, `pruebas/`, `datos/` y `docs/` (sección 4), con la trazabilidad
   componente → carpeta → requisito → sprint.
4. **Reglas de control de versiones creadas:** se redactó el archivo `.gitignore` para el repositorio
   híbrido (C · PHP · Oracle SQL · Android/Java · PowerShell), excluyendo artefactos de compilación
   (`*.o`, `*.exe`, `api/vendor/`, `build/`, `.gradle/`, `*.apk`), recursos y credenciales sensibles
   (`google-services.json`, `local.properties`, `*.jks`, `tnsnames.ora`, `.env`, `config.local.*`) y
   salidas de pruebas (spool, `*.dmp`, `TestResults/`, transcripts), dejando versionables las
   plantillas `*.example`, los scripts `.ps1`/`.sql` y los casos de prueba en JSON.

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-01 | Estructura de carpetas por **componente tecnológico** (una carpeta por capa del MVP). | Cumple RNF-09 (separación clara de responsabilidades) y facilita el trabajo por sprints. |
| D-02 | Excluir del repositorio **todo artefacto generado** (binarios C, `vendor/`, builds de Gradle, APK, dumps de Oracle). | Evita ruido y conflictos en Git; los artefactos se reproducen con `scripts/build.ps1`. |
| D-03 | Versionar **solo plantillas** de configuración (`config.example.php`, `google-services.json.example`, `tnsnames.ora.example`, `.env.example`). | Cumple RNF-03 (seguridad mínima viable) sin filtrar API Keys ni credenciales. |
| D-04 | Mantener una rama `master` estable y trabajar con ramas `sprint-N/<tema>`. | Trazabilidad de avances por sprint, exigida por la metodología Scrum del proyecto. |
| D-05 | Los umbrales `0.4` / `0.7` quedan **fijos en código** durante el MVP. | El MVP usa reglas fijas; los umbrales configurables están diferidos (post-MVP v1.2). |
| D-06 | Usar `BITACORA.md` como registro cronológico de avances y decisiones. | Evidencia de trabajo para la residencia y entrada al capítulo de desarrollo del reporte técnico. |

#### Evidencia

- `C:\todoMIDFF\.gitignore` — archivo de exclusiones del stack híbrido (nuevo en la sesión).
- `C:\todoMIDFF\BITACORA.md` — este documento (nuevo en la sesión).
- Commit inicial existente: `5da8682` con `README.md`, `PRD_MIDFF.md`, `MVP_MIDFF.md` e `ideas.txt`.

#### Bloqueos y riesgos abiertos

- **R-02 (Oracle XE):** aún no se instala el motor de base de datos en el equipo de trabajo; es
  prerrequisito del DDL del Sprint 1.
- **R-03 (dispositivo Android):** falta confirmar un dispositivo físico para probar FCM en el Sprint 3.
- Sin bloqueos que impidan continuar con el Sprint 1.

#### Siguiente paso (compromiso inmediato)

1. Congelar el contrato JSON de `POST /api/v1/transacciones` y documentarlo en `docs/contratos/`.
2. Instalar Oracle XE y ejecutar los scripts DDL de `db/ddl/`.
3. Implementar el motor C (`motor-c/src/`) con las 3 variables y los umbrales, con `scripts/build.ps1` y pruebas unitarias.


---

## 10. Plantilla para nuevas entradas

Copiar la siguiente estructura al final de la sección 9, incrementar el ID (`BIT-00N`), agregar la
fila correspondiente en el índice 9.1 y actualizar la tabla de metadatos de la portada
(*Última actualización* y *Estado*).

````markdown
### 9.N BIT-00N — dd/mm/yyyy — <Título de la sesión o cierre de sprint>

| Campo | Valor |
|---|---|
| **ID** | BIT-00N |
| **Fecha** | dd/mm/yyyy |
| **Sprint** | Sprint N (semana X de 8) |
| **Tipo** | Sesión de trabajo · Cierre de sprint · Corrección · Bloqueo |
| **Responsable** | Residente del proyecto |
| **Estado** | Abierta / En progreso / Cerrada / Bloqueada |
| **Documentos de referencia** | `MVP_MIDFF.md` §X · `PRD_MIDFF.md` RF-XX / RNF-XX |

#### Contexto
<situación de partida de la sesión>

#### Actividades realizadas
1. <actividad> → requisito impactado (`RF-xx` / `HU-MVP-xx`)

#### Decisiones tomadas
| # | Decisión | Justificación |
|---|---|---|
| D-0N | <decisión> | <motivo y requisito asociado> |

#### Evidencia
- <ruta de archivo, hash de commit, salida de script o resultado de consulta SQL>

#### Bloqueos y riesgos
- <riesgo (R-0N) abierto/cerrado y su efecto en el sprint>

#### Métricas del sprint (si aplica)
| Métrica | Objetivo | Resultado medido | Cumple |
|---|---|---|---|
| <métrica de la sección 7> | <objetivo> | <valor real> | Sí / No |

#### Siguiente paso
1. <compromiso inmediato verificable>
````

---

## 11. Control de cambios de este documento

| Versión | Fecha | Autor | Descripción del cambio |
|---|---|---|---|
| 1.0 | 23/09/2026 | Residente del proyecto | Apertura de la bitácora: objetivo general, alcance del MVP, estructura del proyecto derivada de `MVP_MIDFF.md`, plan de 4 sprints, Definition of Done, métricas, riesgos y entrada inicial BIT-001. |

---

**Fin del documento — Bitácora MIDFF v1.0**
