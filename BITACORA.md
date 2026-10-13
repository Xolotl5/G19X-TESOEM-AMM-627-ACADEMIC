# BITÁCORA DEL PROYECTO — MIDFF

**Motor Inteligente de Detección de Fraude Financiero**

| Campo | Valor |
|---|---|
| **Proyecto** | MIDFF — Motor Inteligente de Detección de Fraude Financiero |
| **Título del proyecto** | Desarrollo de un Motor Inteligente de Detección de Fraude Financiero con Arquitectura Híbrida (Android, C, PHP, Oracle DB y Firebase) |
| **Tipo de proyecto** | Residencias Profesionales — Ingeniería en Sistemas Computacionales |
| **Documentos base** | `MVP_MIDFF.md` v1.0 (alcance del MVP) y `PRD_MIDFF.md` v1.0 |
| **Versión de la bitácora** | 1.29 |
| **Fecha de apertura** | 23/09/2026 |
| **Última actualización** | 12/10/2026 |
| **Metodología** | Scrum — 4 sprints de 2 semanas (8 semanas) |
| **Control de versiones** | Git (rama principal `master`) |
| **Stack** | C · PHP 8+ · Oracle Database XE · Android (Java) · Firebase FCM · PowerShell |
| **Responsable** | Residente del proyecto |
| **Estado** | Post-MVP (BIT-029): proyecto listo para una ronda nueva de pruebas — negocio en 0/0/0 (`database/limpiar_pruebas.sql` reutilizable), `AUDITORIA`/`ADMIN_USUARIO`/`DISPOSITIVO_MOVIL` conservados y secuencias realineadas; app con UX de lenguaje llano (BIT-028), push rápido y decisiones del banco reflejadas (BIT-027) |

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

- [x] **1. Flujo completo demostrable:** una transacción recorre ingesta → score → decisión → auditoría → push → bloqueo sin intervención manual. → *Verificado (BIT-010): E2E CLI-003 score 0.95 → `ALR-2026-000010` → bloqueo + auditoría 39; envío del push best-effort autenticado (BIT-009).*
- [x] **2. Motor C:** compila con `build.ps1`, pasa sus pruebas unitarias (3 variables + umbrales) y se reporta su latencia en microsegundos. → *Cumplido (BIT-002): 18 casos / 167 verificaciones / 0 fallos; mediana 7.0 µs en la ruta completa.*
- [x] **3. API REST:** `POST /api/v1/transacciones` responde score + clasificación + decisión en ≤ 1 s; rechaza JSON inválido con `HTTP 400`; valida API Key. → *Cumplido (BIT-003): 308.2 ms de promedio; 400/401 verificados en el smoke test.*
- [x] **4. Persistencia:** transacción, evaluación, alerta y auditoría se registran de forma atómica en Oracle XE; no existe evaluación sin auditoría. → *Cumplido (BIT-003/BIT-010): 0 evaluaciones sin registro; bloqueo + auditoría atómicos vía `SP_BLOQUEAR_ALERTA`.*
- [ ] **5. Notificaciones:** score ≥ 0.4 genera push recibido en la app en < 2 s; score < 0.4 no genera notificación. → *Parcial: envío HTTP v1 autenticado con token real (BIT-009) y score < 0.4 sin push verificado; falta medir la recepción en dispositivo físico (R-03).*
- [x] **6. Bloqueo preventivo:** "Bloquear" desde la app persiste el estado en Oracle y queda en auditoría con usuario y timestamp. → *Cumplido (BIT-010): `POST /api/v1/bloquear` → `BLOQUEADA` + registro `BLOQUEO`; E2E del 28/09 con 409/404 controlados.*
- [x] **7. Clasificación correcta:** 100 % de los casos por banda resueltos con la decisión esperada. → *Cumplido (BIT-003): 3/3 bandas (0.0000 BAJO / 0.5000 MEDIO / 0.9500 ALTO).*
- [x] **8. Automatización mínima:** `build.ps1` y `smoke-test.ps1` corren sin pasos manuales. → *Cumplido: ambos terminan con código de salida 0.*
- [x] **9. Documentación:** README con instalación/ejecución y manual breve de la demo. → *Cumplido (BIT-010): `README.md` reescrito (instalación, credenciales Oracle/Firebase, demo §7 y consultas de control §8).*
- [ ] **10. Demo final:** presentación del flujo end-to-end ante el asesor con dataset sintético. → *Pendiente de la sesión con el asesor; guion listo en `README.md` §7 (incluye medición del push).*

## 7. Métricas de seguimiento

| Métrica | Objetivo del MVP | Cómo se mide | Estado |
|---|---|---|---|
| Latencia de evaluación del motor C | Orden de microsegundos | Benchmark en las pruebas unitarias del motor. | Cumplido (BIT-002): mediana 7.0 µs y p95 10.8 µs en la ruta completa. |
| Latencia extremo a extremo (API) | ≤ 1 s | Logs/timestamps de la API en el smoke test. | Cumplido (BIT-003): 308.2 ms de promedio por transacción (924.7 ms las 3 pruebas). |
| Entrega de push (riesgo ≥ 0.4) | < 2 s | Timestamp de envío FCM vs. recepción en la app. | En progreso (BIT-009): envío migrado a HTTP v1 (OAuth2) con la cuenta de servicio de `midff-57e6b`; access token real emitido por Google en la validación del 28/09; falta medir la recepción en el dispositivo (R-03). |
| Cobertura de auditoría | 100 % de evaluaciones registradas | Consulta de control: evaluaciones vs. registros de auditoría. | Cumplido (BIT-003): 0 evaluaciones sin registro tras el smoke test. |
| Casos de prueba por banda de riesgo | 100 % correctos | Suite de casos bajo/medio/alto en `smoke-test.ps1`. | Cumplido (BIT-003): 3/3 bandas correctas. |
| Bloqueos preventivos trazables | 100 % con usuario y timestamp | Consulta a `ALERTA` + `AUDITORIA`. | Cumplido (BIT-010): E2E verificado — `ALR-2026-000010` con `atendida_por`, `fecha_atencion` y registro `BLOQUEO` (auditoría 39). |

> Las columnas **Estado** y **Evidencia** se actualizan al cierre de cada sprint con la salida
> real de los scripts y las consultas SQL de control.

## 8. Riesgos y mitigación

| # | Riesgo | Sprint de impacto | Mitigación | Estado |
|---|---|---|---|---|
| R-01 | Latencia de crear un proceso C por cada transacción. | Sprint 2 | Medir en el smoke test desde el primer día; si supera el objetivo, aplicar el plan B documentado (proceso persistente/pipes) como primer ítem post-MVP. | Medido (BIT-003): el objetivo ≤ 1 s se cumple con holgura; el plan B queda en el backlog post-MVP. |
| R-02 | Instalación y conexión de Oracle XE en el entorno de residencia. | Sprint 1–2 | Instalar Oracle XE en el Sprint 1 con DDL mínimo; mantener scripts re-ejecutables. | Cerrado (BIT-003): XE 21c en servicio, esquema instalado y conexión verificada desde PHP. |
| R-03 | Recepción de push en emulador Android (FCM requiere Google Play services). | Sprint 3 | Contar con al menos un dispositivo físico de prueba desde el Sprint 3. | En progreso (BIT-009): envío por HTTP v1 validado en vivo (access token OAuth2 real emitido con la cuenta de servicio de `midff-57e6b`); falta dar de alta la app en el mismo proyecto, compilar el APK y validar la recepción en dispositivo físico. |
| R-04 | Datos de prueba poco representativos (falsos resultados del scoring). | Sprint 1 | Dataset sintético con casos por banda (bajo, medio, alto), revisado por el asesor. | En observación |
| R-05 | El bloqueo desde la app requiere autenticación mínima no definida. | Sprint 4 | Definir en el Sprint 3 un identificador de oficial + API Key para la acción de bloqueo. | Cerrado (BIT-010): `POST /api/v1/bloquear` con la misma `X-API-Key` y el `usuario` registrado en `DISPOSITIVO_MOVIL` (D-25/D-36); bloqueo trazable en `ALERTA` + `AUDITORIA`. |
| R-06 | Scope creep por funcionalidades atractivas del PRD. | Todos | `MVP_MIDFF.md` es el contrato de alcance: lo de su §8 se rechaza por defecto y pasa al backlog post-MVP. | Controlado |


## 9. Registro de entradas de la bitácora

### 9.1 Índice de entradas

| ID | Fecha | Sprint | Tipo | Título | Estado |
|---|---|---|---|---|---|
| **BIT-001** | 23/09/2026 | Sprint 1 | Apertura de proyecto | Apertura de la bitácora: objetivo general, alcance, estructura y plan de 4 sprints | Cerrada |
| **BIT-002** | 23/09/2026 | Sprint 1 | Sesión de trabajo | Motor de scoring en C con pruebas unitarias y de latencia, esquema Oracle XE y `build.ps1` | Cerrada |
| **BIT-003** | 24/09/2026 | Sprint 2 | Sesión de trabajo | API REST PHP 8 integrada con el motor C y Oracle XE: endpoint, persistencia atómica y `smoke-test.ps1` | Cerrada |
| **BIT-004** | 24/09/2026 | Sprint 3 | Sesión de trabajo | Notificaciones push FCM (`config/firebase.php`) y app Android de alertas (`android/MidffAlertas`) | Cerrada |
| **BIT-005** | 24/09/2026 | Sprint 3 | Corrección | Renombrado del `applicationId` de la app a `midff_plurione` y alta en Firebase preparada (`google-services.json.example` + guía) | Cerrada |
| **BIT-006** | 24/09/2026 | Sprint 3 | Corrección | `applicationId` definitivo `Midff.PluriOne1` (el guion `-` no es válido en paquetes Java/Android/Firebase) y guía de alta actualizada | Cerrada |
| **BIT-007** | 24/09/2026 | Sprint 3 | Corrección | Bloqueo de sync en Android Studio resuelto: Gradle 8.0 → 8.9 y AGP 8.1.2 → 8.5.2 (JDK 21 / "class file major version 65") | Cerrada |
| **BIT-008** | 24/09/2026 | Sprint 3 | Corrección | Parse del `AndroidManifest.xml` resuelto: la secuencia `--` no está permitida en comentarios XML (cabecera reescrita con `=`) | Cerrada |
| **BIT-009** | 28/09/2026 | Sprint 3 | Sesión de trabajo | Migración del envío FCM a HTTP v1 (OAuth2), credencial de servicio en `config/firebase.credentials.json` (no versionada) y validación en vivo con access token real; regularización documental | Cerrada |
| **BIT-010** | 28/09/2026 | Sprint 4 | Cierre de sprint | Bloqueo preventivo HU-MVP-06 (`POST /api/v1/bloquear` + `SP_BLOQUEAR_ALERTA` + botón en la app), `README.md` completo y cierre del MVP con DoD y métricas finales | Cerrada |
| **BIT-011** | 01/10/2026 | Post-MVP | Sesión de trabajo | Rediseño glass/neón de la app Android según `prompt.txt` (paneles de cristal ahumado, neones cian/magenta, auroras, analítica ponderada 45/30/25 y carrusel de dispositivos) + usabilidad: pestañas instantáneas Panel/Alertas/Registro, ripples y feedback en toda la interfaz, acciones nuevas (copiar token, isla táctil, ver críticas) | Cerrada |
| **BIT-012** | 01/10/2026 | Post-MVP | Sesión de trabajo | Detección automática de la URL de la API: `GET /api/v1/ping` sin API-Key (`api/v1/ping.php` + ruta en `router.php`) y clase `DescubrimientoApi` (guardada → emulador 10.0.2.2 → adb reverse 127.0.0.1 → gateway WiFi → escaneo de subred /24 con 32 hilos), con persistencia y fijado de `ApiClient.baseUrl` | Cerrada |
| **BIT-013** | 01/10/2026 | Post-MVP | Corrección | Primer build real (errores.txt): ternario `String`/`int` inválido en `setText` de la isla dinámica corregido con `getString()`; `sourceCompatibility`/`targetCompatibility` elevados de Java 8 a **17** (D-44) y `@SuppressWarnings` sobre `WifiManager.getDhcpInfo()` | Cerrada |
| **BIT-014** | 01/10/2026 | Post-MVP | Corrección | Push FCM migrado a **data-only** (D-46): con bloque `notification`, Android en 2º plano mostraba el aviso sin ejecutar `NotificacionFcmService` y la alerta nunca llegaba a la bandeja. Puerto del MVP mudado de 8090 a **8091** (D-45: Wondershare NativePush ocupa el 8090); `banco.html` con URL configurable persistente y docs/`smoke-test.ps1` actualizados | Cerrada |
| **BIT-015** | 01/10/2026 | Post-MVP | Sesión de trabajo | Pivote a **enfoque cliente** decidido (D-47): `LoginActivity` con Firebase Auth correo/contraseña, sesión persistente (`SesionCliente`), candado biométrico en cada entrada (`BiometricPrompt`: huella/rostro/PIN, permiso `USE_BIOMETRIC`), botón Cerrar sesión y README con el paso de Firebase Console | Cerrada |
| **BIT-016** | 06/10/2026 | Post-MVP | Corrección | `PromptInfo.Builder.setAuthenticators()` no existe en `androidx.biometric:1.1.0` (2 errores de compilación en `MainActivity.lanzarPromptBiometrico()`); sustituido por `setDeviceCredentialAllowed(true)` (API clásica de 1.1.0, acepta huella/rostro/PIN/patrón del dispositivo). `assembleDebug` verificado: BUILD SUCCESSFUL y APK generado | Cerrada |
| **BIT-017** | 06/10/2026 | Post-MVP | Sesión de trabajo | Push **filtrado por cliente** (2ª parte del pivote, D-47): columna `ID_CLIENTE` en `DISPOSITIVO_MOVIL` (migración `dispositivo_movil_id_cliente.sql` ejecutada en XEPDB1) + rol `CLIENTE` + `midff_fcm_enviar_a_dispositivos()` filtra por `id_cliente` en `firebase.php` + la app registra su token con el campo "ID de cliente" (`SesionCliente`); sin `id_cliente` se conserva el broadcast legado | Cerrada |
| **BIT-018** | 06/10/2026 | Post-MVP | Sesión de trabajo | ID de cliente **auto-generado** al crear la cuenta (D-50: `CLI-` + 6 caracteres del uid de Firebase, determinista ante reinstalación; visible en pestaña Registro) y **vista del banco**: `GET /api/v1/dispositivos` (con API-Key) + panel "Clientes registrados" en `banco.html` con botón "Usar" que copia el ID al formulario de transacciones | Cerrada |
| **BIT-019** | 06/10/2026 | Post-MVP | Sesión de trabajo | **Consola del banco `MIDFF.html`** (D-52/D-53): tabla `ADMIN_USUARIO` (bcrypt + clave temporal, script `admin_usuario.sql` ejecutado; admin/MIDFFadmin2026), API `/api/v1/admin/{sesion,clave,resumen,alertas,clientes}` con token Bearer HMAC-SHA256 de 8 h (rutas en `router.php`) y UI enterprise dark: KPIs, serie 24 h por banda, top clientes, bandeja global filtrable con **Bloquear** (reusa `POST /api/v1/bloquear` + auditoría) y clientes con dispositivos | Cerrada |
| **BIT-020** | 06/10/2026 | Post-MVP | Sesión de trabajo | **Flujo completo de ambos lados**: app del cliente limpia (tab Panel oculto, abre en su bandeja, "Registro"→"Perfil", registro automático silencioso del token en cada apertura) y **"No fui yo / Sí fui yo"** en el detalle (`POST /api/v1/reportar`: verifica dueño con FOR UPDATE, guarda en ALERTA.respuesta/atendida_por/fecha_atencion + AUDITORIA ATENCION `CLIENTE:<id>`, 404 ajeno, 409 doble); la consola `MIDFF.html` muestra la columna **"Reporte cliente"** con bandera ⚑ NO FUI YO / ✓ SÍ FUI YO | Cerrada |
| **BIT-021** | 07/10/2026 | Post-MVP | Corrección | Limpieza de pruebas: tablas de negocio vaciadas y secuencias reiniciadas, pero `SEQ_AUDITORIA` quedó en 1 con 103 filas inmutables aún en la tabla → `ORA-00001 PK_AUDITORIA` al insertar (`errores.txt`). Secuencia realineada a `MAX(id)+1` y verificada con transacción sana real (`TRX-2026-60F56107`). Además: tokens de `DISPOSITIVO_MOVIL` borrados a petición (re-registro) y string `api_no_detectada` corregida al 8091 | Cerrada |
| **BIT-022** | 07/10/2026 | Post-MVP | Sesión de trabajo | **Transacciones sanas visibles en ambos historiales**: la app guarda TODAS las transacciones en la bandeja (sanAs bajo el filtro "Aprobadas", `NotificacionFcmService` ya no las descarta) y `GET /api/v1/admin/alertas` pasa a historial completo (`EVALUACION LEFT JOIN ALERTA`: las sanas sin id de alerta se muestran con badge SANA y sin botón Bloquear; filtro por banda BAJO las aísla). Además: limpieza de pruebas con `SEQ_AUDITORIA` realineada a `MAX(id)+1` (lección de BIT-021), `banco.html` precarga el cliente registrado y plantillas/aleatoria no pisan un ID real. Verificado: sana `TRX-2026-C8808AD2` visible para ambos lados | Cerrada |
| **BIT-023** | 09/10/2026 | Post-MVP | Sesión de trabajo | **Cambio de paleta monocroma** (negro · gris · plateado · humo, con blanco y variantes para los resaltes) manteniendo el diseño intacto: `colors.xml` remapeado por luminosidad (BAJO plateado, MEDIO gris, ALTO blanco puro), 25 drawables y 6 layouts con sustitución tonal, colores de notificación incluidos; `MIDFF.html` y `banco.html` con variables CSS y botones/tags monocromos (acción crítica en blanco invertido). APK recompilado y JS verificado | Cerrada |
| **BIT-024** | 09/10/2026 | Post-MVP | Sesión de trabajo | **Flujo sincronizado por nivel de riesgo (prompt.txt)**: BAJO auto-aprobado; MEDIO con "SÍ FUI YO" aprueba la alerta en el acto (`ATENDIDA`) y "NO FUI YO" genera el aviso ⚑ para el banco; ALTO llega bloqueada con **solo "SÍ FUI YO"** en la app (botón "No fui yo" oculto) y habilita **Desbloquear** en `MIDFF.html` (`POST /api/v1/desbloquear`: respeta `CK_EVALUACION_UMBRAL` del schema congelado —la evaluación histórica no se toca— y deja `SI_FUI_YO :: DESBLOQUEADA` + auditoría). Consola en vivo cada **5 s** y **fix ConnectException**: el reporte se envía tras `DescubrimientoApi.asegurar`. E2E verificado (409 sin confirmar/doble, ALTO desbloqueada aud 138, MEDIO auto-atendida) | Cerrada |
| **BIT-025** | 09/10/2026 | Post-MVP | Sesión de trabajo | **`servidor.ps1`**: demo de un solo comando — agrega las DLLs de Oracle al PATH, autodetecta php.exe (PATH/Laragon), abre el firewall 8091 (regla persistente `MIDFF-API-8091`, una sola vez) y levanta la API en `0.0.0.0:8091` imprimiendo las URLs (consola/simulador/IP WiFi real, excluyendo adaptadores virtuales). Con esto la app se enlaza **sola por WiFi** (DescubrimientoApi ya escaneaba gateway + /24) y el `adb reverse` deja de ser obligatorio en la demo. Probado: bind `0.0.0.0:8091`, regla creada y ping OK | Cerrada |
| **BIT-026** | 09/10/2026 | Post-MVP | Corrección + blindaje | **Secuencias Oracle auto-reparadas**: 3ª recurrencia del `ORA-00001 PK_AUDITORIA` (`errores.txt` 18:57) tras limpieza manual durante las pruebas de BIT-024 — tablas de negocio vaciadas con `AUDITORIA` conservada (inmutable) y `SEQ_AUDITORIA` atrás del `MAX(id)`. Corrección: realineación a `MAX(id)+1` (diagnóstico en vivo: las 4 secuencias ya alineadas tras el arreglo manual de la sesión, sin bitácora). Blindaje: **`database/realinear_secuencias.sql`** (idempotente, `ALTER SEQUENCE … RESTART` Oracle 18c+, omite schema ausente sin fallar) para las 4 secuencias + **paso [4/5] en `servidor.ps1`** que lo ejecuta al arrancar la demo (avisa, no detiene, si Oracle no responde). Verificado: reparación controlada de `SEQ_ALERTA` (1→2 con el script), re-ejecución no-op y transacción BAJO real persistida (`SP_REGISTRAR_TRANSACCION` OK) | Cerrada |
| **BIT-027** | 12/10/2026 | Post-MVP | Sesión de trabajo | **Latencia de alertas ALTO + decisiones del banco en la app**: (1) **caché de archivo del access_token FCM** — el canje OAuth2 por petición (firma JWT + round-trip a Google) sumaba 1-3 s a cada envío; ahora solo el primer envío lo paga (medido: POST 1980→197 ms, `envio_ms` 245.5→59.5); (2) **push de estado `tipo_alerta=ESTADO`** cuando el banco Bloquea (`bloquear.php`) o Desbloquea (`desbloquear.php`) desde `MIDFF.html` — `midff_fcm_payload_estado/notificar_estado` con filtro por dueño (BIT-017) — y la app lo refleja (`NotificacionFcmService.procesarEstadoBanco`: actualiza la bandeja + notifica "bloqueada/desbloqueada por el banco"); (3) **`GET /api/v1/estado?id_cliente`** + `MainActivity.sincronizarEstados()` en `onResume` y `AlertasRepositorio.aplicarEstado(s)` para reconciliar con Oracle si el push se pierde (el detalle también se refresca). E2E: ALTO→`SI_FUI_YO`→desbloqueada (push 73 ms) y bloqueo (push 127 ms); `GET estado` devolvió `000004=BLOQUEADA, 000003=ATENDIDA` | Cerrada |
| **BIT-028** | 12/10/2026 | Post-MVP | Sesión de trabajo | **UX: modismos entendibles en la app** (menos peso visual, menos jerga): (1) tarjetas de la bandeja con el **MONTO como protagonista** (`txtItemMonto`, `$ 15,499.00 MXN` con separador de miles vía `ModeloAlerta.montoFormateado()`); el score baja al pie de la tarjeta ("Transacción … · Score …"); (2) **colores por clasificación**: `clasificacion_alto/medio/bajo` pasa de monocromo a **rojo `#F28B82` / ámbar `#FDD663` / verde `#81C99B`** (tonos "dark material" desaturados que armonizan con el carbón/cristal); colorean punto, chip y resplandor de las tarjetas, el detalle y las notificaciones (`setColor` del servicio FCM); (3) **lenguaje llano**: `clasificacionAmigable()` ("Riesgo alto/medio · Bloqueada", "Aprobada"), `decisionAmigable()`, `estadoAmigable()` y `tituloTarjeta()` ("Posible fraude en tu cuenta" / "Movimiento inusual" / "Movimiento aprobado") en bandeja y detalle; (4) **nombre de usuario propio**: `SesionCliente.guardarNombre/leerNombre` + diálogo al tocar el nombre de la cabecera (`MainActivity.pedirNombreUsuario`, máx 24 chars) que reemplaza el correo en la esquina superior izquierda y alimenta las iniciales del avatar — solo presentación: Firebase Auth y `DISPOSITIVO_MOVIL.usuario` siguen con el correo real | Cerrada |
| **BIT-029** | 12/10/2026 | Post-MVP | Limpieza | **Limpieza del proyecto para una ronda nueva de pruebas**: nuevo script reutilizable **`database/limpiar_pruebas.sql`** — vacía las tablas de negocio (TRANSACCION/EVALUACION/ALERTA), **conserva** `AUDITORIA` (inmutable, 177 filas de evidencia), `ADMIN_USUARIO` (login de la consola) y `DISPOSITIVO_MOVIL` (token del teléfono: sigue recibiendo push sin re-registrarse), y al final **realinea las secuencias** invocando `@@realinear_secuencias.sql` (la lección BIT-021/022/026 convertida en un solo comando). Ejecutado: limpieza 1 → negocio 0/0/0 con secuencias 10→1; **verificación de inserción** (POST BAJO `TRX-2026-B63E91CE`, score 0.125/APROBADA, sin `ORA-00001`); limpieza final → 0/0/0 definitivo con SEQ_* en 1/1/1 y `SEQ_AUDITORIA`→181. Además: basura del repositorio retirada (`replay_pid*.log` 618 KB, `.srv.log`/`.srv-err.log`, `datos/tmp/transaccion_prueba.json`). Pendiente en el dispositivo: botón "Limpiar" de la pestaña Perfil para vaciar la bandeja local (SharedPreferences, no depende de Oracle) | Cerrada |

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

### 9.3 BIT-002 — 23/09/2026 — Sprint 1: motor de scoring en C, modelo de datos Oracle XE y `build.ps1`

| Campo | Valor |
|---|---|
| **ID** | BIT-002 |
| **Fecha** | 23/09/2026 |
| **Sprint** | Sprint 1 (semana 1 de 8) |
| **Tipo** | Sesión de trabajo (implementación) |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada |
| **Documentos de referencia** | `MVP_MIDFF.md` §4.1, §5, §7, §9, §10, §11 y Anexo A · `PRD_MIDFF.md` RF-03, RF-04, RF-05, RF-07, RF-08, RF-15 y RNF-01, RNF-04, RNF-05 |

#### Contexto

Arranque de la implementación del Sprint 1 con el contrato de alcance ya definido en `MVP_MIDFF.md`
y la estructura objetivo registrada en BIT-001. El entorno de trabajo (Windows) cuenta con
**MinGW GCC 6.3.0** y PowerShell 5.1, pero **todavía no tiene Oracle XE instalado** (riesgo R-02),
por lo que el DDL del sprint se entrega y valida por revisión estructural, quedando pendiente su
ejecución en el motor de base de datos.

Los entregables comprometidos en la §5.3 de esta bitácora para el Sprint 1 son: contrato JSON,
motor C con las 3 variables y umbrales, pruebas unitarias (una por variable + fronteras +
benchmark de latencia), DDL de Oracle XE y `build.ps1`.

#### Actividades realizadas

1. **Motor de scoring en C (RF-03, RF-04, RF-05, RNF-01):** se implementó `src/motor/scoring.c`
   como ejecutable de línea de comandos que lee un objeto JSON por `stdin` y devuelve por `stdout`
   el `score` (0.0 – 1.0), la `clasificacion`, la `decision`, el detalle de las **3 variables** y la
   latencia medida en microsegundos. El cálculo es un modelo de reglas ponderado sobre las
   variables del PRD: desviación del monto vs. el historial del cliente (RF-04a), frecuencia de
   transacciones por hora (RF-04b) y ubicación/IP inusual (RF-04c).
2. **Capa de soporte del motor:** `src/motor/scoring.h` (contrato del cálculo, umbrales y pesos),
   `src/motor/json_io.c|h` (lectura/escritura JSON mínima, sin dependencias externas, para no
   afectar la latencia) y `src/motor/cronometro.c|h` (reloj de alta resolución con `<time.h>` y
   `QueryPerformanceCounter`, resolución 0.100 µs).
3. **Suite de pruebas unitarias (HU-MVP-02, HU-MVP-03):** `src/motor/pruebas/test_scoring.c` con
   18 casos y 167 verificaciones: normalización por rampa, acotado y redondeo, un caso por
   variable, umbrales y sus fronteras (0.3999 / 0.4000 / 0.5500 / 0.7000 / 0.7001), integración de
   las tres bandas de riesgo, aislamiento de cada variable, fail-safe con datos ausentes, parseo y
   validación del contrato JSON, serialización de salida y una malla de 480 combinaciones que
   verifica que el score siempre quede en [0,1] y coincida con la decisión por umbral.
4. **Suite de medición de latencia (RNF-01):** `src/motor/pruebas/test_latencia.c`, con tres
   escenarios medidos uno a uno con `<time.h>` (costo del cronómetro, evaluación pura del motor y
   ruta completa parseo + evaluación), estadísticas de mínimo/media/mediana/p95/p99/máximo,
   operaciones por segundo y tiempo de CPU (`clock()`); devuelve error si no se cumplen los
   objetivos de latencia.
5. **Modelo de datos Oracle XE (RF-07, RF-08, RNF-04, RNF-05):** `database/schema.sql` crea las
   tablas `TRANSACCION`, `EVALUACION`, `ALERTA` y `AUDITORIA` con restricciones de coherencia
   (score ↔ clasificación ↔ decisión según los umbrales 0.40 / 0.70, tipos y estados válidos,
   trazabilidad del bloqueo), 4 secuencias, 5 triggers (asignación de identificadores e
   inmutabilidad de `AUDITORIA`) y el procedimiento almacenado **`SP_REGISTRAR_TRANSACCION`**, que
   inserta de forma **atómica** transacción + evaluación + alerta (si el score ≥ 0.4) + auditoría,
   validando la coherencia del score antes de escribir y revirtiendo todo ante cualquier error.
   El script es **re-ejecutable** (elimina y recrea los objetos) y termina verificando que los 13
   objetos quedaron creados.
6. **Automatización del build (RF-15, RNF-09):** `build.ps1` localiza `gcc`, compila el motor y las
   dos suites de prueba, ejecuta las pruebas unitarias, corre el benchmark de latencia, valida el
   contrato JSON por `stdin`/`stdout` con las tres bandas de riesgo y los errores de entrada, y
   termina con un resumen y código de salida 0/1 (apto para usarse en el Sprint 2 y 4 sin pasos
   manuales).
7. **Registro y verificación:** se documentó el layout entregado, las decisiones de diseño y los
   resultados medidos en esta entrada, y se excluyeron los artefactos generados con las reglas ya
   existentes de `.gitignore` (`bin/`, `*.exe`, `*.o`).

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-07 | El layout entregado en el Sprint 1 es `src/motor/` (motor y pruebas), `database/schema.sql` y `build.ps1` en la raíz, **en lugar** del árbol `motor-c/`, `db/ddl/` y `scripts/` de la sección 4. | Indicación expresa del residente/asesor para esta entrega. La sección 4 se conserva como estructura objetivo del MVP y su contenido sigue siendo obligatorio: `src/motor/` cubre el componente 1, `database/` el componente 2 y `build.ps1` el entregable de RF-15. Mapeo: `motor-c/src/*.c` → `src/motor/*.c`; `motor-c/include/*.h` → `src/motor/*.h`; `motor-c/pruebas/*.c` → `src/motor/pruebas/*.c`; `db/ddl/*.sql` → `database/schema.sql`; `scripts/build.ps1` → `build.ps1`. |
| D-08 | Modelo de reglas **ponderado** sobre rampas lineales: `score = 0.45·p_monto + 0.30·p_frecuencia + 0.25·p_ubicacion_ip`, cada `p_i` normalizado en [0,1]. | RF-04 exige las 3 variables pero no define pesos; ponderar por impacto (el monto desviado es la señal más fuerte de fraude) mantiene el modelo explicable y auditable, y permite verificar cada variable con pruebas unitarias (HU-MVP-02). |
| D-09 | El historial del cliente viaja en el propio JSON de entrada, dentro del objeto `contexto` (`monto_promedio_historial`, `transacciones_ultima_hora`, `ubicacion_habitual`, `ip_habitual`). | El motor es un **proceso sin estado** invocado por línea de comandos (RF-03); la API obtendrá estos datos de Oracle DB en el Sprint 2. Así el motor no requiere conexión a la base y mantiene su latencia en microsegundos. |
| D-10 | **Fail-safe:** si una variable no puede calcularse (sin `contexto`, sin monto promedio, sin frecuencia o sin ubicación/IP comparables) se usa un puntaje neutro `0.50`, el caso cae en banda `MEDIO / EN_REVISION` y el motivo se registra en `datos_faltantes`. | Cumple HU-MVP-04 y RNF-04: una transacción sin historial **nunca se aprueba a ciegas** y queda auditado el motivo. Se documenta como comportamiento obligatorio del motor. |
| D-11 | El motor devuelve, además del score, la **clasificación y la decisión** por umbral. | HU-MVP-02.4 y el criterio §9.7 del MVP piden verificar los umbrales en las pruebas del motor; la API (Sprint 2) conserva la autoridad final de decisión del paso 5 del flujo. |
| D-12 | Los identificadores de Oracle se generan con **secuencias + triggers** `BEFORE INSERT`, en lugar de columnas `IDENTITY`. | Compatibilidad con Oracle XE 11.2, 18c y 21c sin cambios en el script (riesgo R-02), y permite inserts manuales en SQL*Plus durante las pruebas. |
| D-13 | `SP_REGISTRAR_TRANSACCION` **no ejecuta `COMMIT`** y ante cualquier error hace `ROLLBACK` total y relanza el error. | RNF-05: la persistencia de transacción, evaluación, alerta y auditoría es una sola unidad atómica; el llamador (API PHP del Sprint 2) confirma. Evita dejar una evaluación sin registro de auditoría. |
| D-14 | `main()` del motor se excluye con la bandera de compilación `-DMIDFF_SIN_MAIN` para enlazar el dominio dentro de las suites de prueba. | Permite probar unitariamente las funciones reales del motor (sin duplicar código ni ejecutar procesos), que es lo que exige HU-MVP-02.4. La bandera la aplica `build.ps1`. |
| D-15 | El parser del motor tolera un **BOM UTF-8** al inicio de la entrada. | Se detectó al integrar `build.ps1` con `System.Diagnostics.Process` (.NET escribe el BOM en la tubería); evita rechazar entradas válidas cuando el cliente sea PHP, PowerShell u otro. |
| D-16 | Las suites de `src/motor/pruebas/` se compilan como ejecutables independientes que devuelven código de salida 0/1 y un resumen con casos, verificaciones y fallos. | RNF-09 y criterio §9.8: `build.ps1` automatiza la verificación sin intervención manual ni herramientas externas. |



#### Evidencia

- **Archivos creados (Sprint 1)**
  - `C:\todoMIDFF\src\motor\scoring.h` (198 líneas) — contrato del cálculo, umbrales, pesos y banderas de datos faltantes.
  - `C:\todoMIDFF\src\motor\scoring.c` (630 líneas) — motor: 3 variables, score ponderado, clasificación, contrato JSON y `main` (RF-03, RF-04, RF-05, RF-08).
  - `C:\todoMIDFF\src\motor\json_io.h` (67 líneas) y `json_io.c` (408 líneas) — parser y escritor JSON mínimo por `stdin`/`stdout`, sin dependencias externas.
  - `C:\todoMIDFF\src\motor\cronometro.h` (51 líneas) y `cronometro.c` (126 líneas) — reloj de microsegundos con `<time.h>` (RNF-01).
  - `C:\todoMIDFF\src\motor\pruebas\marco_pruebas.h` (83 líneas) — marco de aserciones sin dependencias.
  - `C:\todoMIDFF\src\motor\pruebas\test_scoring.c` (798 líneas) — 18 casos / 167 verificaciones (HU-MVP-02, HU-MVP-03).
  - `C:\todoMIDFF\src\motor\pruebas\test_latencia.c` (299 líneas) — benchmark en microsegundos (RNF-01).
  - `C:\todoMIDFF\database\schema.sql` (544 líneas) — DDL + procedimiento PL/SQL de Oracle XE (RF-07, RF-08, RNF-04, RNF-05).
  - `C:\todoMIDFF\build.ps1` (450 líneas) — compilación y pruebas automatizadas (RF-15, RNF-09).
- **Resultado de las pruebas unitarias del motor** (`src\motor\bin\midff-test-scoring.exe`):
  `Casos: 18 | Verificaciones: 167 | Fallos: 0` → `RESULTADO: OK - todas las verificaciones pasaron`.
- **Benchmark de latencia** (`src\motor\bin\midff-test-latencia.exe 200000`, gcc `-O2`, `QueryPerformanceCounter`, resolución 0.100 µs):

| Escenario | Mínimo | Mediana | p95 | p99 | Máximo | Rendimiento |
|---|---|---|---|---|---|---|
| 0. Costo del cronómetro (línea base) | 0.000 µs | 0.100 µs | 0.100 µs | 0.200 µs | 18.200 µs | 12.3 M ops/s |
| 1. Evaluación pura del motor (3 variables + umbrales) | 0.500 µs | **0.600 µs** | **0.800 µs** | 1.200 µs | 109.100 µs | 1 575 407 ops/s |
| 2. Ruta de la API (parseo JSON + evaluación) | 6.600 µs | 7.000 µs | 10.800 µs | 14.000 µs | 6 737.400 µs | 125 200 ops/s |

  Tiempo de CPU del escenario 1: **0.760 µs por iteración** (`clock()` de `<time.h>`). Objetivos del RNF-01 (mediana ≤ 100 µs y p95 ≤ 1 000 µs): **CUMPLE** en ambos escenarios.
- **Pruebas del contrato JSON por línea de comandos** (ejecutadas por `build.ps1` con `System.Diagnostics.Process` y tuberías reales):

| Caso | Código de salida | Resultado |
|---|---|---|
| Banda ALTA (Anexo A: monto 15 499.00 vs. promedio 1 200.00, 7 operaciones/hora, ubicación e IP distintas) | 0 | `score 0.9500` → `ALTO / BLOQUEADA` |
| Banda MEDIA (monto 3 600.00, 6 operaciones/hora, IP distinta) | 0 | `score 0.5500` → `MEDIO / EN_REVISION` |
| Banda BAJA (cliente con comportamiento habitual) | 0 | `score 0.0000` → `BAJO / APROBADA` |
| Sin `contexto` histórico (fail-safe D-10) | 0 | `score 0.5000` → `MEDIO / EN_REVISION`, `datos_faltantes:["contexto"]` |
| JSON mal formado (`{`) | 2 | diagnóstico JSON por `stderr` (la API responderá HTTP 400, RF-02) |
| Sin campo obligatorio `monto` | 2 | diagnóstico JSON por `stderr` |

- **Resultado del build automatizado:** `build.ps1` → `Compilaciones correctas: 3 de 3`, pruebas unitarias y benchmark OK, 6 casos de contrato OK, `RESULTADO: BUILD CORRECTO`, código de salida **0**.
- **Verificación estructural del DDL** (Oracle XE aún no instalado, riesgo R-02): 4 tablas, 4 secuencias, 5 triggers, 1 procedimiento, 8 bloques PL/SQL terminados correctamente y paréntesis balanceados (129 de 129).


#### Bloqueos y riesgos

- **R-02 (Oracle XE en el entorno de residencia) — EN OBSERVACIÓN:** el motor de base de datos aún
  no está instalado, por lo que `database/schema.sql` no se ha podido ejecutar. Se mitigó entregando
  el script **re-ejecutable** (se puede correr dos veces seguidas), **portable** entre XE 11.2/18c/21c
  (secuencias + triggers) y **autoverificable** (bloque final que reporta 13 de 13 objetos creados).
  Pendiente: ejecutarlo en XE y registrar la salida en la siguiente entrada.
- **R-01 (latencia de crear un proceso C por transacción) — CON DATO CUANTITATIVO:** la invocación
  completa del ejecutable medida desde `build.ps1` está entre **38 y 44 ms** por transacción (más
  133 ms el primer arranque en frío), muy por encima de la latencia interna del motor (0.600 µs).
  Confirma el riesgo de la §12 del MVP: el presupuesto de ≤ 1 s extremo a extremo sigue siendo
  holgado, pero la optimización (proceso persistente/pipes) queda como primer ítem del post-MVP v1.2
  si el smoke test del Sprint 2 lo exige.
- **R-04 (datos de prueba poco representativos):** el dataset sintético de `datos/datasets/` aún no
  se ha creado; por ahora los casos por banda viven en la suite de pruebas y en `build.ps1`.
- **Dependencia de entorno:** el compilador es MinGW.org GCC 6.3.0 (no MinGW-w64) y no expone
  `clock_gettime(CLOCK_MONOTONIC)`; el cronómetro resuelve esto usando `QueryPerformanceCounter` con
  respaldo POSIX documentado, sin afectar el requisito de medición con `<time.h>`.

#### Métricas del sprint (MVP_MIDFF.md §10)

| Métrica | Objetivo | Resultado medido | Cumple |
|---|---|---|---|
| Latencia de evaluación del motor C | Orden de microsegundos | Mediana **0.600 µs**, p95 **0.800 µs**, p99 1.200 µs (200 000 iteraciones) | Sí |
| Latencia de la ruta completa (parseo + evaluación) | Mantenerse en el orden de microsegundos | Mediana **7.000 µs**, p95 10.800 µs | Sí |
| Casos de prueba por banda de riesgo | 100 % con la decisión esperada | 3 de 3 bandas correctas (unitarias + CLI) y 480 combinaciones consistentes | Sí |
| Cobertura de auditoría | 100 % de evaluaciones registradas | Diseñada en `SP_REGISTRAR_TRANSACCION` (transacción + evaluación + alerta + auditoría en una sola unidad atómica) | Por comprobar en Oracle XE |
| Automatización mínima | Compilación y pruebas sin pasos manuales | `build.ps1` compila, prueba y valida el contrato JSON; código de salida 0 | Sí |
| Criterio de aceptación del Sprint 1 (§5.3) | `build.ps1` sin errores, pruebas en verde y DDL ejecutable dos veces | `build.ps1` OK y 167 verificaciones sin fallos; DDL pendiente de ejecución real en XE | Parcial |

#### Siguiente paso

1. Instalar Oracle XE, ejecutar `database/schema.sql` **dos veces seguidas** y registrar la salida
   (criterio de aceptación del Sprint 1 y cierre del riesgo R-02).
2. Generar el dataset sintético del Sprint 1 (`datos/datasets/transacciones_bajo|medio|alto.json`)
   con casos revisados por el asesor para cerrar R-04.
3. Documentar el contrato JSON congelado (Anexo A + objeto `contexto` de la decisión D-09) en
   `docs/contratos/api-transacciones-v1.md`.
4. Iniciar el Sprint 2 (semana 3): endpoint `POST /api/v1/transacciones` con API Key, invocación del
   ejecutable `src\motor\bin\midff-scoring.exe` y persistencia mediante `SP_REGISTRAR_TRANSACCION`
   (HU-MVP-01, HU-MVP-04).


### 9.4 BIT-003 — 24/09/2026 — Sprint 2: API REST PHP integrada con el motor C y Oracle XE

| Campo | Valor |
|---|---|
| **ID** | BIT-003 |
| **Fecha** | 24/09/2026 |
| **Sprint** | Sprint 2 (semanas 3–4 del plan; iniciado de forma anticipada) |
| **Tipo** | Sesión de trabajo (integración backend) |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada |
| **Documentos de referencia** | `MVP_MIDFF.md` §4.1, §5.4, §7 y Anexo A · `PRD_MIDFF.md` RF-01/02/03/06/07/08 · `docs/contratos/contrato_scoring.md` v1.0 |

#### Contexto

El Sprint 1 dejó el motor C compilado y probado, el DDL re-ejecutable y `build.ps1`, con un
pendiente explícito: ejecutar `database/schema.sql` sobre un Oracle XE real (criterio de
aceptación parcial y riesgo R-02). El entorno local dispone de Oracle Database XE 21c
(servicio `XEPDB1`, listener activo) y de PHP 8.1.10 (Laragon) **sin** las extensiones de
Oracle habilitadas y **sin** usuario de aplicación. El Sprint 2 (HU-MVP-01, HU-MVP-03,
HU-MVP-04) exige exponer `POST /api/v1/transacciones` con `X-API-Key`, invocar al
ejecutable C y persistir el ciclo completo de forma atómica.

#### Actividades realizadas

1. **Preparación del entorno Oracle** → RF-07 (cierra el pendiente del Sprint 1):
   habilitadas `pdo_oci` y `oci8_19` en `php.ini`; creado el usuario `MIDFFuser` en `XEPDB1`
   (grants mínimos: sesión, tablas, secuencias, triggers, procedimientos) y ejecutado
   `database/schema.sql` bajo ese usuario: verificación del propio script **4/4 tablas,
   4/4 secuencias, 5/5 triggers, SP creado — "esquema listo para el Sprint 2"**.
2. **`config/db.php`** (252 líneas) → RF-07, RNF-03, RNF-05: placeholders de usuario,
   contraseña, host, puerto y SID sobreescribibles por variables de entorno
   (`MIDFF_DB_*`); `midff_db_conectar()` vía **PDO_OCI** con respaldo **OCI8**; y
   `midff_db_registrar_transaccion()` que invoca `SP_REGISTRAR_TRANSACCION` con los 19
   parámetros de entrada (fecha con `TO_TIMESTAMP_TZ` y `variables_usadas` como CLOB) +
   3 `OUT`, confirmando con `COMMIT` o revirtiendo con `ROLLBACK` (D-13).
3. **`api/v1/transacciones.php`** (356 líneas) → RF-01, RF-02, RF-03, RF-06, HU-MVP-01:
   validación de método (405), `X-API-Key` con comparación en tiempo constante (401),
   cuerpo JSON y campos obligatorios/tipos/longitudes según las columnas de `TRANSACCION`
   (400), generación de `id_transaccion`, invocación de `midff-scoring.exe` por
   `proc_open` (stdin→stdout, `bypass_shell`), traducción de códigos de salida del
   contrato (0→200, 2→400, 3→500), persistencia atómica y respuesta sincrónica del
   Anexo A (`id_transaccion`, `score`, `clasificacion`, `decision`, `fecha_evaluacion`,
   `alerta_id`).
4. **`router.php`** (50 líneas): router mínimo del servidor embebido (`php -S`) para
   exponer la ruta oficial `/api/v1/transacciones` sin extensión durante el MVP.
5. **`smoke-test.ps1`** (406 líneas) → RF-15, criterio §9.8: detecta `php.exe`/`sqlplus`
   y las DLLs del cliente Oracle, levanta el servidor embebido, ejecuta las 3 pruebas por
   banda con el dataset congelado, mide latencias en ms, agrega pruebas negativas
   (401/400) y verifica en Oracle la cobertura de auditoría.
6. **Ejecución del smoke test** con resultado **OK / código de salida 0** (evidencia abajo):
   las 3 bandas resueltas con el `score_esperado` exacto, las negativas 401/400 en verde
   y cobertura de auditoría del 100 % en Oracle.

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-16 | El acceso a Oracle se centraliza en `config/db.php` con **PDO_OCI como driver principal y OCI8 de respaldo**, y una única función `midff_db_registrar_transaccion()` encapsula el SP. | El prompt del Sprint 2 exige soportar `pdo_oci`/`oci8`; encapsular el driver deja el endpoint agnóstico y auditable en un solo lugar (RF-07, RNF-05). |
| D-17 | La API **asigna** `id_transaccion` con formato `TRX-<año>-<8 hex>` (aleatorio criptográfico) e ignora el que pudiera enviar el cliente. | El contrato del motor define el campo como "identificador asignado por la API"; el formato cabe en `VARCHAR2(40)` y evita colisiones sin consultar secuencias antes de evaluar (RF-02). |
| D-18 | El objeto `contexto` se acepta **opcionalmente en el cuerpo** y se reenvía al motor tal cual; si no llega, el motor aplica su fail-safe (0.5 / EN_REVISION). | `contrato_scoring.md` §2.1 prevé que el contexto lo aporte la API desde Oracle, pero el esquema del MVP aún no tiene tabla de historial; el passthrough permite probar las bandas del dataset hoy y construir el historial real en sprints posteriores (D-09). |
| D-19 | `alerta_id` de la respuesta se formatea `ALR-<año>-<id de SEQ_ALERTA>` a partir del parámetro `OUT` del SP; es `null` en banda baja. | Respeta el Anexo A del MVP (alerta solo con `score ≥ 0.4`) y usa el identificador real persistido, garantizando trazabilidad alerta↔Oracle (RF-09 base, HU-MVP-03). |
| D-20 | El servidor del MVP es el **embebido de PHP (`php -S` + `router.php`)**; no se instala Apache/Nginx. | El MVP valida el flujo, no la infraestructura de producción; `router.php` permite usar la ruta oficial `/api/v1/transacciones` sin extensión (§7.2 del MVP, alcance mínimo viable). |
| D-21 | La latencia del smoke test se mide **externa y total** (Stopwatch alrededor del POST HTTP), no solo la del motor. | Es la métrica del MVP §10 ("extremo a extremo ≤ 1 s") y cuantifica el riesgo R-01 tal como lo pide su mitigación. |
| D-22 | API Key y credenciales Oracle viven como **placeholders con override por variables de entorno** (`MIDFF_API_KEY`, `MIDFF_DB_*`). | Convención de secretos de la bitácora §4.2: nada real versionado; en el entorno de residencia se inyectan sin editar código (RNF-03). |

#### Evidencia

- **Archivos creados (Sprint 2):**
  - `C:\todoMIDFF\config\db.php` (252 líneas) — conexión y persistencia atómica (RF-07, RNF-05).
  - `C:\todoMIDFF\api\v1\transacciones.php` (356 líneas) — endpoint `POST /api/v1/transacciones` (RF-01/02/03/06).
  - `C:\todoMIDFF\router.php` (50 líneas) — ruta del contrato para `php -S`.
  - `C:\todoMIDFF\smoke-test.ps1` (406 líneas) — smoke test del flujo (RF-15).
- `php -l` sin errores en los tres archivos PHP.
- **Salida de `smoke-test.ps1` (24/09/2026, exit 0):**
  - BAJO: `CLI-001` score 0.0000 → `APROBADA`, sin alerta — 509.0 ms.
  - MEDIO: `CLI-002` score 0.5000 → `EN_REVISION`, alerta `ALR-2026-000002` — 223.6 ms.
  - ALTO: `CLI-003` score 0.9500 → `BLOQUEADA`, alerta `ALR-2026-000003` — 192.1 ms.
  - Negativas: sin `X-API-Key` → HTTP 401; JSON mal formado → HTTP 400 con error descriptivo.
  - Oracle: 4 evaluaciones registradas, **0 sin auditoría** (cobertura 100 %); alertas
    PREVENTIVA/FRAUDE = 1/2 (la primera FRAUDE provino de la prueba manual previa).
  - Latencia total de las 3 pruebas: **924.7 ms** (promedio **308.2 ms**, objetivo ≤ 1 s).

#### Bloqueos y riesgos

- **R-01 (invocación de proceso C por transacción):** medido — el objetivo ≤ 1 s se cumple
  con holgura (~0.2–0.5 s por request incluidas red, PHP, proceso C y Oracle); el plan B
  (proceso persistente/pipes) permanece como primer ítem del backlog post-MVP.
- **R-02 (instalación/conexión de Oracle XE): cerrado** — XE 21c operativo, esquema
  instalado y conexión verificada desde PHP con ambos drivers disponibles.
- Sin bloqueos abiertos para el Sprint 3.

#### Métricas del sprint

| Métrica | Objetivo | Resultado medido | Cumple |
|---|---|---|---|
| Latencia extremo a extremo (API) | ≤ 1 s | 924.7 ms las 3 pruebas; 308.2 ms promedio (`smoke-test.ps1`) | Sí |
| Cobertura de auditoría | 100 % de evaluaciones registradas | 0 evaluaciones sin registro en `AUDITORIA` (consulta de control del Anexo B) | Sí |
| Casos por banda de riesgo | 100 % correctos | 3/3 (BAJO 0.0000 / MEDIO 0.5000 / ALTO 0.9500 con decisión esperada) | Sí |
| API Key / JSON inválido | 401 / 400 respectivamente | Verificado en el smoke test | Sí |

#### Siguiente paso

1. Iniciar el Sprint 3: proyecto Firebase (FCM), `FcmService` en la API para push con
   `score ≥ 0.4` y app Android básica (registro de token y bandeja de alertas).
2. Congelar el contrato de la API en `docs/contratos/api-transacciones-v1.md` (pendiente
   arrastrado de BIT-002, ahora con la implementación real como referencia).
3. Ampliar el dataset sintético con casos adicionales validados por el asesor (riesgo R-04).


### 9.5 BIT-004 — 24/09/2026 — Sprint 3: notificaciones push FCM y app Android

| Campo | Valor |
|---|---|
| **ID** | BIT-004 |
| **Fecha** | 24/09/2026 |
| **Sprint** | Sprint 3 (semanas 5–6 del plan; iniciado de forma anticipada tras el cierre del Sprint 2) |
| **Tipo** | Sesión de trabajo (notificaciones y cliente móvil) |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada (componentes entregados; medición del push < 2 s pendiente de dispositivo físico) |
| **Documentos de referencia** | `MVP_MIDFF.md` §4.1 (RF-09/RF-10), §5.5, §7 (paso 7), §9 criterio 5 · `PRD_MIDFF.md` RF-09, RF-10, RNF-07 · `firebase/README-fcm.md` |

#### Contexto

El Sprint 2 (BIT-003) dejó en verde el flujo transacción → motor C → Oracle → respuesta
sincrónica. El Sprint 3 compromete **HU-MVP-05**: convertir una transacción de riesgo
medio/alto (`score >= 0.4`) en una alerta **visible y accionable** en un dispositivo
Android en menos de 2 segundos (§5.5). Se dispone de las credenciales reales del
proyecto: `google-services.json` (lado app) y la cuenta de servicio
`midff-57e6b-firebase-adminsdk-fbsvc-bf7960c9b6.json` (lado servidor).

#### Actividades realizadas

1. **`config/firebase.php`** (~353 líneas) → RF-09, RNF-03, RNF-07, HU-MVP-05: constante
   **`MIDFF_FCM_SERVER_KEY`** (placeholder `COLOCAR-AQUI-...`, sobreescribible por la
   variable de entorno `MIDFF_FCM_SERVER_KEY`) y umbral de notificación 0.4; consulta de
   dispositivos activos en `DISPOSITIVO_MOVIL` (PDO_OCI con respaldo OCI8, mismo patrón
   que `config/db.php`); payload legacy `notification` + `data` (monto, score y
   clasificación como cadenas, con trazabilidad `id_transaccion`/`id_alerta`); envío por
   cURL (timeout 5 s) y orquestador `midff_fcm_notificar_alerta()` **best-effort** que
   nunca lanza excepciones. Los tokens invalidados por FCM (`NotRegistered`,
   `InvalidRegistration`, `MismatchSenderId`) reciben baja lógica (`activo = 0`) y todos
   los logs enmascaran el token.
2. **`database/dispositivo_movil.sql`** → RF-09/RF-10, tabla `DISPOSITIVO_MOVIL` de la
   §3.3: DDL **re-ejecutable** (script aparte para no reescribir el artefacto congelado
   del Sprint 1) con `usuario`, `rol` (OFICIAL/SUPERVISOR/ADMIN), `token_fcm` UNIQUE
   (64–255), `activo`, fechas, `SEQ_DISPOSITIVO_MOVIL` y triggers de id y de fecha de
   actualización. **Ejecutado dos veces seguidas** sobre `XEPDB1` sin errores.
3. **Gancho push en `api/v1/transacciones.php`** (paso 7b del flujo) → RF-06 + RF-09:
   tras el COMMIT atómico, si se creó alerta (`score >= 0.4`) se invoca
   `midff_fcm_notificar_alerta()` y se registra `[MIDFF-FCM] ... exitosos/fallidos/
   envio_ms` en el log; la respuesta del Anexo A **no cambia** (contrato congelado).
4. **`api/v1/dispositivos.php` + `router.php`** → RF-02, RF-10: endpoint
   `POST /api/v1/dispositivos` con la misma `X-API-Key` (D-25) que hace **UPSERT** del
   token (INSERT si es nuevo, UPDATE si la app se reinstaló); validaciones 405/401/400 y
   respuesta con token enmascarado. Router ampliado con la nueva ruta y raíz informativa
   actualizada a versión 1.1 (ambos endpoints).
5. **App Android `android/MidffAlertas/`** → RF-10: proyecto Android Studio en Java
   (compileSdk 34, minSdk 24) con el plugin **`com.google.gms.google-services`** 4.4.2 y
   `firebase-messaging` 23.4.1. Componentes:
   `NotificacionFcmService` (extiende `FirebaseMessagingService`: captura del token en
   `onNewToken` con autoregistro contra la API, y `onMessageReceived` que guarda la
   alerta en la bandeja y muestra la **notificación local con monto, score y
   clasificación** por el canal `midff_alertas`), `MainActivity` (**bandeja de entrada**
   de alertas + registro del token con usuario/rol del oficial), `DetalleAlertaActivity`
   (detalle: monto, score, clasificación, decisión y trazabilidad), `AdaptadorAlertas`
   (color por banda de riesgo), `ModeloAlerta` (espejo del bloque `data`),
   `AlertasRepositorio` (bandeja persistente en SharedPreferences) y `ApiClient`
   (HTTP mínimo con `X-API-Key`, sin dependencias externas). `google-services.json`
   copiado al módulo `app/`.
6. **`firebase/README-fcm.md` + `firebase/plantillas/`** → entregable 1 del §5.5:
   arquitectura del flujo de notificación, configuración de la server key, contrato del
   registro de token y plantillas de payload por banda (preventiva/fraude).
7. **Validación en vivo** (detalle en Evidencia): `php -l` 4/4, DDL re-ejecutable,
   `smoke-test.ps1` en verde con el gancho FCM activo y pruebas del endpoint de
   dispositivos contra Oracle XE real (INSERT/UPDATE/401/400) más un intento real de
   envío FCM.

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-23 | Envío por protocolo **HTTP legacy de FCM** (`Authorization: key=<server key>`) con la constante `MIDFF_FCM_SERVER_KEY` | Es la constante única de configuración que fija el alcance del Sprint 3 (una sola clave, sin OAuth2). Google descontinúa las server keys: la migración a HTTP v1 con la cuenta de servicio `midff-57e6b` queda como primer candidato del post-MVP y el archivo ya está referido en `MIDFF_FCM_CUENTA_SERVICIO` (`config/firebase.php`). |
| D-24 | `DISPOSITIVO_MOVIL` se crea en `database/dispositivo_movil.sql` y **no** en `schema.sql` | Las entradas no se reescriben: el DDL del Sprint 1 queda congelado; el script nuevo es re-ejecutable y complementario (mismo criterio de aceptación). |
| D-25 | Registro del token por `POST /api/v1/dispositivos` con la **misma `X-API-Key`** de transacciones | Seguridad mínima viable (RF-02) sin esquema de autenticación adicional fuera de alcance; además identifica `usuario` + `rol` del oficial (mitigación del riesgo R-05 exigida para el Sprint 4). |
| D-26 | `applicationId` de la app = `com.example.damad_8s21_amm_examen_final` | Forzado por el `google-services.json` provisto (proyecto Firebase `examen-1479b`): el plugin valida el `package_name`. La server key debe pertenecer al **mismo** proyecto para que los tokens coincidan (documentado en `firebase/README-fcm.md`). Desviación del namespace planeado `mx.tecnm.midff.alertas` (§4), aceptada por la credencial real disponible. |
| D-27 | Bandeja de la app persistida en `SharedPreferences` (arreglo JSON, máx. 100) | RF-12 (Realtime Database) está diferido: la fuente de verdad es Oracle y el reflejo local basta para la bandeja del MVP. |
| D-28 | El push se envía **dentro de la misma petición HTTP** (síncrono, timeout 5 s) y es best-effort | Cumple RF-06 sin cola de trabajadores (RNF-07: envío único). Un fallo de FCM no puede alterar la respuesta del contrato ni compensar la transacción ya confirmada; queda en `error_log`. El intento medido (225.6 ms) mantiene holgado el presupuesto e2e ≤ 1 s. |

#### Evidencia

- Archivos: `config\firebase.php` · `api\v1\dispositivos.php` · `api\v1\transacciones.php` (paso 7b) · `router.php` · `database\dispositivo_movil.sql` · `android\MidffAlertas\` (Gradle + google-services + 7 clases Java + recursos) · `firebase\README-fcm.md` · `firebase\plantillas\payload-alerta-preventiva.json` y `payload-alerta-fraude.json`.
- `php -l` sin errores en los 4 archivos PHP nuevos/modificados.
- DDL ejecutado **dos veces** sobre `XEPDB1` con `MIDFFuser`: "Tabla DISPOSITIVO_MOVIL (Sprint 3) instalada" en ambas corridas.
- `smoke-test.ps1` → **RESULTADO: SMOKE TEST OK** con el gancho FCM integrado: 3/3 bandas (0.0000 BAJO / 0.5000 MEDIO / 0.9500 ALTO), negativas 401/400 en verde, cobertura de auditoría 100 % (10 evaluaciones, 0 sin registro) y **763.0 ms las 3 pruebas (254.3 ms promedio)** ≤ 1 s.
- `POST /api/v1/dispositivos` contra Oracle real: `INSERT` id=2 → `UPDATE` id=2 con el mismo token (UPSERT verificado) → 401 sin API Key → 400 con rol inválido; token sintético eliminado tras la prueba (`DISPOSITIVOS_RESTANTES=0`).
- Intento **real** de envío FCM con clave sintética (log local `datos\resultados\php-fcm-intento-php.log`, no versionado):
  `[MIDFF-FCM] TRX-2026-1DC102BC alerta=ALR-2026-000009 dispositivos=1 exitosos=0 fallidos=1 envio_ms=225.6` — Google respondió HTTP 404 (clave inválida), el fallo quedó solo en el log y la respuesta 200 del contrato se entregó intacta (best-effort, D-28).

#### Bloqueos y riesgos

- **R-03 (push en emulador/dispositivo):** sigue en progreso: la app y el envío están listos; falta validar la recepción en un dispositivo físico (o emulador con Google Play services) con la server key real del proyecto `examen-1479b`.
- **R-05 (autenticación del bloqueo):** mitigación definida y materializada (D-25: `usuario` + `rol` en `DISPOSITIVO_MOVIL` y la misma `X-API-Key`); la acción "Bloquear" se implementa en el Sprint 4.
- Riesgo operativo nuevo (documentado en `firebase/README-fcm.md`): coherencia de proyectos Firebase — el `google-services.json` corresponde a `examen-1479b` y la cuenta de servicio a `midff-57e6b`; la server key debe corresponder al proyecto de la app para que la entrega funcione.

#### Métricas del sprint (MVP_MIDFF.md §10)

| Métrica | Objetivo | Resultado medido | Cumple |
|---|---|---|---|
| Entrega de push (riesgo ≥ 0.4) | < 2 s | Envío implementado; intento de referencia 225.6 ms (rechazado por clave sintética). Recepción en dispositivo pendiente (R-03). | Parcial |
| Latencia extremo a extremo (API) | ≤ 1 s | Smoke test con el gancho FCM activo: 763.0 ms las 3 pruebas (254.3 ms promedio). | Sí |
| Cobertura de auditoría | 100 % | 0 evaluaciones sin registro tras las pruebas del sprint. | Sí |

#### Siguiente paso

1. Colocar la **server key real** del proyecto Firebase de la app en `MIDFF_FCM_SERVER_KEY`
   (o en la variable de entorno), compilar el APK en Android Studio y medir la recepción
   del push (< 2 s) en un dispositivo físico — cierre del criterio del §5.5 y del riesgo R-03.
2. Iniciar el Sprint 4 (HU-MVP-06): acción "Bloquear" en `DetalleAlertaActivity` → endpoint
   de alertas → `ALERTA.estado = BLOQUEADA` + registro en `AUDITORIA` con usuario y timestamp.
3. Congelar el contrato de la API en `docs/contratos/api-transacciones-v1.md` (pendiente
   arrastrado de BIT-002/BIT-003).


### 9.6 BIT-005 — 24/09/2026 — Corrección: `applicationId` de la app a `midff_plurione` y alta en Firebase preparada

| Campo | Valor |
|---|---|
| **ID** | BIT-005 |
| **Fecha** | 24/09/2026 |
| **Sprint** | Sprint 3 (semanas 5–6 del plan) |
| **Tipo** | Corrección / cambio de identificador |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada |
| **Documentos de referencia** | Regla 3 de la §1 de esta bitácora · `MVP_MIDFF.md` RF-09/RF-10 · `android/MidffAlertas/README.md` · `firebase/README-fcm.md` |

#### Contexto

Se decide dar de alta la app en Firebase con un identificador propio
(`midff_plurione`) en lugar del paquete heredado `com.example.damad_8s21_amm_examen_final`
(fijado en D-26 por el `google-services.json` prestado del proyecto `examen-1479b`).
Por la regla 3 (las entradas no se reescriben), el cambio se registra como corrección
sin tocar BIT-004.

#### Actividades realizadas

1. **Renombrado del identificador** → RF-10: `namespace` y `applicationId` de
   `android/MidffAlertas/app/build.gradle` cambiados a `midff_plurione`; las 7 clases
   Java movidas a `app/src/main/java/midff_plurione/` con `package midff_plurione;`
   (sin BOM). El `AndroidManifest.xml` no requiere cambios (usa nombres relativos).
2. **Retiro del `google-services.json` prestado** → el archivo de `examen-1479b` quedó
   eliminado del módulo `app/` porque el plugin `com.google.gms.google-services`
   fallaría al no coincidir `package_name` con el nuevo `applicationId`. Se agregó la
   plantilla **`app/google-services.json.example`** con `package_name = midff_plurione`.
3. **Guía de alta en Firebase** → `android/MidffAlertas/README.md` (nueva sección
   "Agregar la aplicación a Firebase": registrar el paquete `midff_plurione`, descargar
   el `google-services.json` real a `app/`, sincronizar y colocar la credencial de
   mensajería del mismo proyecto en `MIDFF_FCM_SERVER_KEY`). `firebase/README-fcm.md`
   actualizado en la misma línea.

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-29 | `applicationId`/paquete de un solo segmento `midff_plurione`; sustituye a D-26 | Identificador solicitado para el alta propia en Firebase; válido para compilar, para FCM y para la demo. Advertencias documentadas: Google Play exige paquete con punto (≥ 2 segmentos) para publicar en tienda, y los proyectos Firebase nuevos pueden no emitir *server key* legacy (en ese caso la migración a HTTP v1 con la cuenta de servicio ya referenciada en `MIDFF_FCM_CUENTA_SERVICIO` pasa a ser obligatoria). |

#### Evidencia

- `android\MidffAlertas\app\build.gradle` (namespace/applicationId `midff_plurione`).
- `android\MidffAlertas\app\src\main\java\midff_plurione\` con las 7 clases (`package midff_plurione;` verificado en todas).
- `android\MidffAlertas\app\google-services.json.example` (JSON válido, `package_name = midff_plurione`).
- Búsqueda en el repositorio: fuera de las menciones históricas intencionales (BIT-004 en esta bitácora, la nota de procedencia en el README de la app y el `google-services.json` original de la raíz, que el proyecto ya no consume), no quedan referencias activas a `com.example.damad_8s21_amm_examen_final`.

#### Bloqueos y riesgos

- R-03 sigue en progreso: después de registrar la app y colocar el `google-services.json`
  real + la credencial de mensajería, falta medir la recepción del push (< 2 s) en
  dispositivo físico.

#### Siguiente paso

1. Registrar `midff_plurione` en Firebase Console, descargar el `google-services.json`
   real a `android/MidffAlertas/app/`, compilar el APK y completar la medición del
   criterio §5.5.


### 9.7 BIT-006 — 24/09/2026 — Corrección: `applicationId` definitivo `Midff.PluriOne1`

| Campo | Valor |
|---|---|
| **ID** | BIT-006 |
| **Fecha** | 24/09/2026 |
| **Sprint** | Sprint 3 (semanas 5–6 del plan) |
| **Tipo** | Corrección / cambio de identificador |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada |
| **Documentos de referencia** | Regla 3 de la §1 de esta bitácora · `android/MidffAlertas/README.md` · `firebase/README-fcm.md` |

#### Contexto

Se solicita el identificador `Midff.Pluri-One1`, pero el guion (`-`) **no es un
carácter válido** para un paquete Java: Gradle fallaría con "not a valid Java
package name", y Firebase Console rechaza el registro ("Package name contains
invalid characters"). De común acuerdo con el residente se adopta la variante
**`Midff.PluriOne1`** (mismo nombre sin el guion). Este cambio sustituye a
`midff_plurione` (D-29) sin reescribir BIT-005 (regla 3).

#### Actividades realizadas

1. **Renombrado del identificador** → RF-10: `namespace` y `applicationId` de
   `android/MidffAlertas/app/build.gradle` fijados en `Midff.PluriOne1` (iguales
   entre sí para que el plugin de Google Services valide sin ambigüedad); las 7
   clases Java movidas a `app/src/main/java/Midff/PluriOne1/` con
   `package Midff.PluriOne1;` (sin BOM).
2. **Plantilla actualizada**: `app/google-services.json.example` ahora con
   `package_name = Midff.PluriOne1` y la advertencia del guion en su comentario.
3. **Guía de alta actualizada**: `android/MidffAlertas/README.md` y
   `firebase/README-fcm.md` referencian el paquete definitivo.

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-30 | Paquete definitivo **`Midff.PluriOne1`** (sin guion); sustituye a D-29 | El `-` no es válido en paquetes Java/Android/Firebase. Con 2 segmentos con punto ya cumple el requisito de Google Play para tienda (advertencia de D-29 resuelta). `namespace` = `applicationId` para que el plugin `google-services` y el manifest (nombres relativos `.MainActivity` etc.) resuelvan siempre contra el mismo paquete. |

#### Evidencia

- `android\MidffAlertas\app\build.gradle`: `namespace 'Midff.PluriOne1'` y `applicationId "Midff.PluriOne1"`.
- `android\MidffAlertas\app\src\main\java\Midff\PluriOne1\` con las 7 clases (`package Midff.PluriOne1;` verificado en todas; carpeta `midff_plurione` eliminada).
- `android\MidffAlertas\app\google-services.json.example` (JSON válido, `package_name = Midff.PluriOne1`).
- Fuera de las menciones históricas de esta bitácora, no quedan referencias activas a `midff_plurione`.

#### Bloqueos y riesgos

- R-03 sigue en progreso: falta registrar la app en Firebase, colocar el
  `google-services.json` real + la credencial de mensajería y medir la recepción
  del push (< 2 s) en dispositivo físico.

#### Siguiente paso

1. Registrar `Midff.PluriOne1` en Firebase Console, descargar el `google-services.json`
   real a `android/MidffAlertas/app/`, compilar el APK y completar la medición del
   criterio §5.5.


### 9.8 BIT-007 — 24/09/2026 — Corrección: bloqueo de sync en Android Studio (JDK 21 vs Gradle 8.0)

| Campo | Valor |
|---|---|
| **ID** | BIT-007 |
| **Fecha** | 24/09/2026 |
| **Sprint** | Sprint 3 (semanas 5–6 del plan) |
| **Tipo** | Corrección (bloqueo de build resuelto) |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada |
| **Documentos de referencia** | `android/MidffAlertas/README.md` (solución de problemas) · `MVP_MIDFF.md` RF-10 |

#### Contexto

Al abrir `android/MidffAlertas` en Android Studio, la sincronización de Gradle falla
con el reporte registrado en `prompt.txt`:

- *"Your build is currently configured to use incompatible **Java 21.0.7 and Gradle
  8.0**. Cannot sync the project. The minimum compatible Gradle version is **8.5**"*;
- *CacheOpenException ... **"Unsupported class file major version 65"*** (Java 21 =
  class file 65).

Causa raíz: el wrapper fijado en el Sprint 3 usaba Gradle 8.0, que **no soporta
ejecutarse sobre Java 21** (el JBR incluido en el Android Studio del entorno); Gradle
soporta Java 21 a partir de la **8.5**. El IDE además sugería "Gradle 9.0-milestone-1",
versión preliminar que no debe usarse.

#### Actividades realizadas

1. **Wrapper actualizado**: `gradle/wrapper/gradle-wrapper.properties` pasa de
   `gradle-8.0-bin.zip` a **`gradle-8.9-bin.zip`** (estable, soporta JDK 21).
2. **AGP actualizado**: `build.gradle` raíz pasa de `com.android.application` 8.1.2 a
   **8.5.2** (exige Gradle ≥ 8.7, soporta `compileSdk 34`, corre con JDK 17/21);
   `google-services` 4.4.2 permanece (compatible).
3. **README de la app**: fila "Build" en la tabla y nueva sección **"Solución de
   problemas"** (ignorar el 9.0-milestone, re-sincronizar, borrar la caché
   `%USERPROFILE%\.gradle\caches\8.0` si persiste, Gradle JVM = jbr-21, generación de
   `gradlew` por el IDE).

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-31 | Toolchain fijada en **AGP 8.5.2 + Gradle 8.9 + JDK 17/21** | Pareja estable y probada para el JBR 21 de Android Studio actual: Gradle ≥ 8.5 (soporte Java 21), AGP 8.5 (Gradle ≥ 8.7 y `compileSdk 34`). Se descarta el "Gradle 9.0-milestone-1" sugerido por el IDE por ser preliminar. Con esto se cierra el bloqueo sin tocar código Java ni el contrato FCM. |

#### Evidencia

- `android\MidffAlertas\gradle\wrapper\gradle-wrapper.properties`: `distributionUrl=...gradle-8.9-bin.zip`.
- `android\MidffAlertas\build.gradle`: `id 'com.android.application' version '8.5.2' apply false`.
- `android\MidffAlertas\README.md`: sección "Solución de problemas" con el procedimiento de re-sincronización.
- Reporte del error (log completo del IDE) registrado por el residente en `prompt.txt`.

#### Bloqueos y riesgos

- R-03 continúa en progreso (recepción del push en dispositivo físico); este bloqueo
  era de toolchain y queda resuelto a nivel de configuración: falta la re-sincronización
  en el entorno del residente para confirmar.

#### Siguiente paso

1. En Android Studio: *File → Sync Project with Gradle Files* (descarga Gradle 8.9) y
   compilar; luego continuar con el alta en Firebase de `Midff.PluriOne1` (BIT-006).

### 9.9 BIT-008 — 24/09/2026 — Corrección: parse del AndroidManifest.xml por "--" en comentario XML

| Campo | Valor |
|---|---|
| **ID** | BIT-008 |
| **Fecha** | 24/09/2026 |
| **Sprint** | Sprint 3 (semanas 5–6 del plan) |
| **Tipo** | Corrección (bloqueo de build resuelto) |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada |
| **Documentos de referencia** | `android/MidffAlertas/app/src/main/AndroidManifest.xml` · `MVP_MIDFF.md` RF-10 |

#### Contexto

Al compilar, la tarea `:app:processDebugMainManifest` falla con el reporte registrado
en `prompt.txt`:

- `MergeFailureException: Error parsing ...\AndroidManifest.xml`;
- `SAXParseException; lineNumber: 2; columnNumber: 8; La cadena "--" no está permitida en los comentarios.`

Causa raíz: la cabecera decorativa del manifiesto usaba líneas de guiones (`---`).
En XML un comentario **no puede contener la secuencia `--`** (solo está permitida en
los delimitadores `<!--` y `-->`).

#### Actividades realizadas

1. **Cabecera corregida** en `AndroidManifest.xml`: la decoración pasa de `---` a `=`
   (con nota que aclara el motivo) → requisito RF-10 (app compilable).
2. **Verificación**: parseo XML de todos los archivos `.xml` del módulo con PowerShell
   (`[xml](Get-Content -Raw)`) → resultado `OK: todos los XML parsean sin errores`.
3. **README de la app**: bullet en "Solución de problemas" para no usar `--` en
   comentarios XML.

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-32 | La decoración de comentarios XML usa **`=`** (nunca `--`) | La secuencia `--` está prohibida por la especificación XML 1.0 dentro de los comentarios; usar `=` evita la recurrencia del bloqueo de merge sin afectar legibilidad. |

#### Evidencia

- `android\MidffAlertas\app\src\main\AndroidManifest.xml`: cabecera reescrita con `=`.
- Salida de verificación: `OK: todos los XML parsean sin errores`.
- Reporte del error (log completo del IDE) registrado por el residente en `prompt.txt`.

#### Bloqueos y riesgos

- R-03 continúa en progreso (recepción del push en dispositivo físico); este bloqueo
  era de parse del manifiesto y queda resuelto a nivel de archivo: falta recompilar
  en el entorno del residente para confirmar que el build avanza.

#### Siguiente paso

1. En Android Studio: *Build → Make Project* para confirmar el merge del manifiesto y
   continuar con el alta en Firebase de `Midff.PluriOne1` (BIT-006).

### 9.10 BIT-009 — 28/09/2026 — Sprint 3: migración del envío FCM a HTTP v1 (OAuth2) y validación de la credencial

| Campo | Valor |
|---|---|
| **ID** | BIT-009 |
| **Fecha** | 28/09/2026 |
| **Sprint** | Sprint 3 (semanas 5–6 del plan) |
| **Tipo** | Sesión de trabajo (regularización documental y validación) |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada |
| **Documentos de referencia** | `config/firebase.php` · `config/firebase.credentials.json` · `firebase/README-fcm.md` · `MVP_MIDFF.md` §4.1 (RF-09), RNF-03, RNF-07 · decisión previa D-23 |

#### Contexto

La decisión D-23 (BIT-004) fijó el envío de push por el protocolo legacy de FCM
(`Authorization: key=<server key>`) con la constante `MIDFF_FCM_SERVER_KEY`. Ese mismo
24/09, tras las correcciones BIT-005 a BIT-008, `config/firebase.php` se reescribió al
**protocolo HTTP v1 (OAuth2)** y la cuenta de servicio se reubicó de la raíz del
repositorio a `config/firebase.credentials.json`; el cambio quedó operativo pero **sin
entrada en esta bitácora** (el índice, la métrica §7 y el riesgo R-03 seguían describiendo
la server key legacy como vigente). Esta sesión regulariza el estado documental y valida
la credencial en vivo.

#### Actividades realizadas

1. **Migración a HTTP v1 materializada en `config/firebase.php`** (553 líneas; la versión
   legacy se conserva como `config/firebase.php.bak`, excluida de Git por `*.bak`) →
   RF-09, RNF-07: firma de un **JWT RS256** con la clave privada de la cuenta de servicio
   (extensión `openssl`), canje por `access_token` en `oauth2.googleapis.com` (cacheado
   por petición con margen de 60 s) y envío por
   `POST https://fcm.googleapis.com/v1/projects/{proyecto}/messages:send` con payloads
   `message.*` (plantillas de `firebase/plantillas/` actualizadas). Se conservan el umbral
   0.4, el orquestador `midff_fcm_notificar_alerta()` best-effort y la baja lógica de
   tokens invalidados, ahora con las equivalencias v1 de los códigos legacy
   (`UNREGISTERED`, `SENDER_ID_MISMATCH`, `NOT_FOUND`).
2. **Credencial de servicio instalada en `config/firebase.credentials.json`** → RNF-03:
   cuenta de servicio real del proyecto `midff-57e6b`
   (`firebase-adminsdk-fbsvc@midff-57e6b.iam.gserviceaccount.com`). Resolución de ruta:
   1) variable de entorno `MIDFF_FCM_CUENTA_SERVICIO`, 2)
   `config/firebase.credentials.json`, 3) JSON de la raíz (respaldo histórico).
3. **Validación en vivo** (28/09/2026, PHP 8.1.10 de Laragon; script temporal en
   `datos\tmp\` —carpeta excluida de Git—, eliminado tras la corrida): `php -l` sin
   errores; `midff_fcm_configurada()` = `SI`; `midff_fcm_proyecto()` = `midff-57e6b`;
   `midff_fcm_access_token()` emitió un **access token OAuth2 real** (prefijo `ya29.`,
   1024 caracteres) contra el endpoint de Google → la autenticación del lado servidor
   queda verificada de extremo a extremo.
4. Actualización documental: esta entrada, la métrica §7 (entrega de push), el riesgo
   R-03 y la portada (v1.8).

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-33 | El envío de push usa el **protocolo HTTP v1 de FCM (OAuth2)** con la cuenta de servicio; **sustituye a D-23** (legacy server key) | Google descontinúa las server keys legacy y el proyecto `midff-57e6b` (2026) no la emite: la migración prevista como post-MVP pasa a ser obligatoria ya en el Sprint 3. El contrato del Anexo A y la política best-effort no cambian. |
| D-34 | La cuenta de servicio vive en **`config/firebase.credentials.json`**, nunca versionada y sobreescribible por la variable de entorno `MIDFF_FCM_CUENTA_SERVICIO` | Convención del repositorio (secretos fuera de Git, RNF-03) y centralización junto a `db.php`; el `.gitignore` ya contiene `**/firebase.credentials.json` (verificado con `git check-ignore`). |

#### Evidencia

- `php -l config\firebase.php` → "No syntax errors detected".
- Salida de la validación en vivo: `configurada=SI` · `proyecto=midff-57e6b` · `endpoint=https://fcm.googleapis.com/v1/projects/midff-57e6b/messages:send` · `cuenta=C:\todoMIDFF\config\firebase.credentials.json` · `access_token=EMITIDO_OK longitud=1024 prefijo=ya29.c.c0A...`.
- `git check-ignore -v config/firebase.credentials.json` → `.gitignore:318:**/firebase.credentials.json`; `git status config/` solo reporta `?? config/firebase.php` (la credencial no aparece).
- El paso 7b de `api/v1/transacciones.php` invoca `midff_fcm_notificar_alerta()` sin cambios de firma: la migración no rompe el gancho del endpoint.

#### Bloqueos y riesgos

- **R-03 (recepción del push):** avanza — el servidor ya autentica contra Google con credencial real; falta dar de alta la app `Midff.PluriOne1` **en el mismo proyecto `midff-57e6b`**, instalar su `google-services.json` real, compilar el APK y medir la recepción (< 2 s) en dispositivo físico. El `google-services.json` de la raíz (proyecto `examen-1479b`) ya no lo consume nadie y la server key legacy deja de ser necesaria.
- **R-05:** sin cambios (mitigado con D-25; la acción "Bloquear" se implementa en el Sprint 4).

#### Métricas del sprint (MVP_MIDFF.md §10)

| Métrica | Objetivo | Resultado medido | Cumple |
|---|---|---|---|
| Entrega de push (riesgo ≥ 0.4) | < 2 s | Lado servidor verificado: OAuth2 con access token real emitido por Google; falta medir la recepción en el dispositivo (R-03). | Parcial |
| Latencia extremo a extremo (API) | ≤ 1 s | Sin cambios desde BIT-004: 254.3 ms de promedio con el gancho FCM activo. | Sí |

#### Siguiente paso

1. Dar de alta `Midff.PluriOne1` en el proyecto Firebase `midff-57e6b`, colocar el `google-services.json` real en `android/MidffAlertas/app/`, compilar el APK y medir el push (< 2 s) en dispositivo físico — cierre del criterio §5.5 y del riesgo R-03.
2. Commitear el Sprint 3 en Git y congelar el contrato de la API en `docs/contratos/api-transacciones-v1.md` (pendiente arrastrado de BIT-002/BIT-003).
3. Iniciar el Sprint 4 (HU-MVP-06): acción "Bloquear" en `DetalleAlertaActivity` → endpoint de alertas → `ALERTA.estado = BLOQUEADA` + registro en `AUDITORIA` con usuario y timestamp.

### 9.11 BIT-010 — 28/09/2026 — Sprint 4: bloqueo preventivo, cierre del MVP y demo

| Campo | Valor |
|---|---|
| **ID** | BIT-010 |
| **Fecha** | 28/09/2026 |
| **Sprint** | Sprint 4 (semanas 7–8 del plan; iniciado el 28/09 tras la regularización BIT-009) |
| **Tipo** | Cierre de sprint / sesión de trabajo |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada (MVP cerrado a nivel de componentes; pendientes de entorno: recepción física del push y demo ante el asesor) |
| **Documentos de referencia** | `MVP_MIDFF.md` §4.1 (HU-MVP-06), §9 (DoD), §10 (métricas) · `prompt.txt` (alcance fijado por el residente) · `database/sp_bloquear_alerta.sql` · `api/v1/bloquear.php` · `README.md` |

#### Contexto

BIT-009 dejó el servidor FCM autenticado y la documentación al día. El residente fijó el
alcance del Sprint 4 en `prompt.txt`: (1) botón "Bloquear" en la app, (2) endpoint que
persista `ALERTA.estado = BLOQUEADA` + `AUDITORIA`, (3) `README.md` con compilación,
credenciales y demo, y (4) cierre del MVP en esta bitácora con métricas y DoD.

#### Actividades realizadas

1. **`database/sp_bloquear_alerta.sql`** → HU-MVP-06, RF-08, RNF-04, RNF-05:
   procedimiento `SP_BLOQUEAR_ALERTA(p_id_alerta, p_usuario, p_respuesta, OUT estado,
   fecha_atencion, id_auditoria)` que valida existencia (ORA-20003) y no duplicidad
   (ORA-20004), ejecuta el UPDATE de `ALERTA` (estado/atendida_por/respuesta/
   fecha_atencion) y el INSERT `BLOQUEO` en `AUDITORIA` en una sola unidad atómica,
   **sin COMMIT** y con ROLLBACK + relanzamiento ante error (D-35). Instalado **dos
   veces** seguidas sobre `XEPDB1` con "No hay errores" en ambas corridas.
2. **`config/db.php`** → `midff_db_bloquear_alerta()` con la ruta dual PDO_OCI/OCI8
   (binds de entrada y de salida: estado, fecha ISO 8601 con zona e id de auditoría) y
   COMMIT/ROLLBACK, replicando el patrón de `midff_db_registrar_transaccion()` (D-13).
3. **`api/v1/bloquear.php` + `router.php` v1.2** → RF-02, HU-MVP-06: 405/401/400;
   `alerta_id` con regex `^ALR-\d{4}-\d{1,18}$` (el mismo formato que emite
   `transacciones.php`, cuyo número viaja al SP); `usuario` ≤ 60 obligatorio y
   `respuesta` ≤ 200 opcional; mapa ORA-20003 → **404**, ORA-20004 → **409**; respuesta
   200 con `estado`, `atendida_por`, `fecha_atencion` e `id_auditoria` (D-36).
4. **App Android** → HU-MVP-06: `ApiClient.bloquearAlerta()` (POST), botón "Bloquear"
   en `DetalleAlertaActivity` (deshabilitado durante el vuelo, callback por
   `runOnUiThread`, Toast de confirmación/error y estado "ya bloqueada"), fila "Estado"
   en `activity_detalle_alerta.xml`, `ModeloAlerta.estado` + `estaBloqueada()` y
   `AlertasRepositorio.marcarBloqueada()` para reflejar BLOQUEADA en la bandeja (D-37).
   Strings nuevas y README de la app actualizados; los 12 XML del módulo parsean sin
   errores.
5. **`README.md`** raíz reescrito (criterio 9 del DoD): requisitos del entorno,
   instalación paso a paso (Oracle: `schema.sql` + `dispositivo_movil.sql` +
   `sp_bloquear_alerta.sql`; credenciales por variables de entorno; Firebase
   `midff-57e6b`; app con `adb reverse`), `build.ps1`/`smoke-test.ps1`, **guion de demo
   end-to-end (§7)** y consultas de control en Oracle (§8) + solución de problemas.
6. **Validación E2E en vivo** (evidencia más abajo) y cierre documental: DoD §6,
   métricas §7, riesgo R-05 cerrado, esta entrada y portada v1.9.

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-35 | `SP_BLOQUEAR_ALERTA` vive en `database/sp_bloquear_alerta.sql` (script aparte, `CREATE OR REPLACE`), **no ejecuta COMMIT** y ante error hace ROLLBACK + relanzamiento | Mismo criterio que D-13 (atomicidad RNF-05: ni el bloqueo ni la auditoría quedan a medias) y D-24 (no reescribir el `schema.sql` congelado del Sprint 1). Los errores de negocio propios (−20003/−20004) conservan su código para que la API los mapee a 404/409. |
| D-36 | Contrato del bloqueo: `alerta_id` en formato `ALR-<anio>-<numero>` (el que ya emite `/transacciones`), `usuario` obligatorio ≤ 60 (el registrado en `DISPOSITIVO_MOVIL`), `respuesta` opcional ≤ 200; misma `X-API-Key` (D-25) | Reutiliza el identificador que la app ya recibe en el push sin exponer ids numéricos internos; el usuario viaja explícito para la trazabilidad de `AUDITORIA` (HU-MVP-06) y se cierra el riesgo R-05 con la autenticación mínima viable ya definida. |
| D-37 | La app marca la alerta como `BLOQUEADA` en la bandeja local (SharedPreferences) **solo tras el HTTP 200** de Oracle | Coherente con D-27: Oracle es la fuente de verdad; el reflejo local alimenta la vista (RF-10) y evita estados fantasma si el bloqueo falla (el botón se rehabilita y se muestra el error). |

#### Evidencia

- `php -l` → "No syntax errors detected" en `config\db.php`, `api\v1\bloquear.php` y `router.php`.
- XML del módulo Android: "OK: todos los XML de la app parsean sin errores" (12 archivos).
- Instalación del SP **dos veces** sobre `XEPDB1` (`MIDFFuser`): "No hay errores." y "Procedimiento SP_BLOQUEAR_ALERTA creado (bloqueo + auditoria atomicos)." en ambas corridas.
- E2E del 28/09 (servidor `php -S 127.0.0.1:8091 router.php`, script temporal en `datos\tmp\`, eliminado tras la corrida):
  - `TRANSACCION: score=0.95 clasificacion=ALTO decision=BLOQUEADA alerta_id=ALR-2026-000010`
  - `BLOQUEO: ok=True estado=BLOQUEADA atendida_por=OFICIAL-DEMO-4 fecha_atencion=2026-09-28T19:20:34-06:00 id_auditoria=39`
  - `REBLOQUEO: HTTP 409 (esperado 409)` · `ALERTA-INEXISTENTE: HTTP 404 (esperado 404)`
- Consultas de control (sqlplus): `ALERTA` id 10 `BLOQUEADA` por `OFICIAL-DEMO-4` el 2026-09-28 19:20:34 y `AUDITORIA` id 39 (`ALERTA` / `BLOQUEO` / `OFICIAL-DEMO-4`, mismo timestamp) → **trazabilidad 100 %** (métrica 6 del §10 del MVP).

#### Bloqueos y riesgos

- **R-05: CERRADO** — el bloqueo usa la misma `X-API-Key` y el `usuario` de `DISPOSITIVO_MOVIL` (D-25/D-36), con auditoría inmutable del responsable y timestamp.
- **R-03: sin cambios** — el envío sigue autenticado (BIT-009); falta la recepción en dispositivo físico, que se mide en la sesión de demo (guion §7 del README).
- R-04 queda en observación final (el dataset de 3 bandas cumplió su cometido; la ampliación pasa al post-MVP).

#### Métricas finales del MVP (MVP_MIDFF.md §10)

| Métrica | Objetivo | Resultado medido | Cumple |
|---|---|---|---|
| Latencia de evaluación del motor C | Orden de microsegundos | Mediana 7.0 µs / p95 10.8 µs en la ruta completa (BIT-002). | Sí |
| Latencia extremo a extremo (API) | ≤ 1 s | 308.2 ms de promedio (BIT-003); 254.3 ms con el gancho FCM activo (BIT-004). | Sí |
| Entrega de push (riesgo ≥ 0.4) | < 2 s | Servidor autenticado OAuth2 con token real (BIT-009); recepción en dispositivo físico pendiente (R-03). | Parcial |
| Cobertura de auditoría | 100 % | 0 evaluaciones sin registro (BIT-003/BIT-004). | Sí |
| Casos de prueba por banda de riesgo | 100 % correctos | 3/3 bandas: 0.0000 BAJO / 0.5000 MEDIO / 0.9500 ALTO (BIT-003, re-verificado en BIT-010). | Sí |
| Bloqueos preventivos trazables | 100 % con usuario y timestamp | `ALR-2026-000010` + auditoría 39 con `atendida_por` y `fecha_atencion` (BIT-010). | Sí |

#### Siguiente paso

1. Sesión de **demo ante el asesor** con el guion del `README.md` §7 (criterio 10 del DoD), midiendo ahí el push < 2 s en dispositivo físico con la app dada de alta en `midff-57e6b` (cierre de R-03 y del criterio 5).
2. Commits de cierre en Git (sprint-3 y sprint-4) y, de requerirse, congelar `docs/contratos/api-transacciones-v1.md` (arrastre de BIT-002/003).
3. Post-MVP v1.1 (`MVP_MIDFF.md` §13): dashboard web (RF-13), consulta de historial (RF-14) y sincronización con Firebase Realtime Database (RF-12).

### 9.12 BIT-011 — 01/10/2026 — Post-MVP: rediseño glass/neón de la app Android y usabilidad (pestañas instantáneas)

| Campo | Valor |
|---|---|
| **ID** | BIT-011 |
| **Fecha** | 01/10/2026 |
| **Sprint** | Post-MVP (mejora continua; los 4 sprints cerraron en BIT-010) |
| **Tipo** | Sesión de trabajo |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada |
| **Documentos de referencia** | `prompt.txt` (especificación visual y de flujo) · `MVP_MIDFF.md` §6 (RF-09 / RF-10 / HU-MVP-05) · `android/MidffAlertas/README.md` |

#### Contexto

Con el MVP cerrado (BIT-010), el residente fijó en `prompt.txt` un rediseño de la interfaz de la app bajo el estilo **"Glassmorphism de Cristal Ahumado con Neón de Contraste Profundo"** (carbón `#121214`, cristal `#1E1E24` translúcido, cian `#00F0FF` y magenta `#E000FF`, auroras azul-púrpura-rosa). En una segunda pasada se corrigió la usabilidad: la navegación inferior hacía `smoothScrollToPosition` sobre una cabecera de ~700 dp (segundos de scroll y "Panel"/"Alertas" ejecutaban la misma acción) y varios botones no daban feedback aparente.

#### Actividades realizadas

Rediseño visual (RF-10):

1. **Paleta y drawables (20 archivos nuevos)**: fondo carbón con auroras radiales difusas (`bg_pantalla.xml`), paneles de cristal ~70 % con borde 1 px y degradado interno que sugiere volumen (`panel_cristal`, `chip_cristal`), isla dinámica (`isla_dinamica`), subrayado magenta de filtros (`indicador_filtro`), cápsulas neón con halo (`btn_neon_cian`, `btn_neon_magenta`), barra de analítica con gradiente cian-púrpura-magenta (`progreso_analitica`), avatar con degradado, resplandores radiales y 10 iconos vectoriales con relleno degradado vía `aapt:attr` (`ic_rayo_aurora`, `ic_nav_*`, `ic_dispositivo` y de filtros).
2. **Tema**: `Theme.MaterialComponents.NoActionBar` con `windowBackground` auroral y barras de sistema carbón; `colorPrimary` cian y `colorSecondary` magenta (D-40).
3. **Bandas de riesgo en neón** (D-41): `clasificacion_bajo` #00F0FF (APROBADA), `clasificacion_medio` #B44CFF (EN_REVISION), `clasificacion_alto` #E000FF (ALTO/BLOQUEADA); `NotificacionFcmService.setColor` alineado (sana → cian, ALTO → magenta, resto → púrpura).
4. **`UtilidadesUi` (clase nueva)**: texto degradado azul-rosa del perfil (`LinearGradient`), glow neón sobre texto (`setShadowLayer`), pulso de la isla (`ObjectAnimator`) e iniciales del avatar.
5. **Modelo**: `ModeloAlerta.scoreValor()` (score 0-1 tolerante a comas) y `esAprobada()`/`enRevision()` para filtros y analítica.
6. **Detalle** (`activity_detalle_alerta.xml`): hero con score 46 sp neón, **analítica ponderada monto 45 % / frecuencia 30 % / ubicación IP 25 %** con barras aurora (D-42), trazabilidad compacta, **carrusel de dispositivos** (`item_dispositivo.xml`: dispositivo del oficial con token real + backend MIDFF) y "Bloquear" en cápsula magenta; botón "Volver" propio.
7. **Bandeja** (`item_alerta.xml` + `AdaptadorAlertas`): paneles de cristal separados con chip de clasificación, punto de riesgo teñido y score neón con resplandor por banda.

Usabilidad (segunda pasada):

8. **Pestañas instantáneas Panel | Alertas | Registro** (enum `Pestana` en `MainActivity`): intercambio por visibilidad con fundido de 110 ms; se elimina `cabecera_bandeja.xml` (header del ListView) y todo scroll de navegación (D-39). Segundo toque en "Alertas" → inicio de la lista sin animación; cabecera compacta (avatar + isla dinámica) siempre visible.
9. **Feedback en cada acción**: ripples en navegación/botones/chips/ítems (`ripple_tactil` como `foreground`, `btn_neon_cian_ripple`, `btn_neon_magenta_ripple`, `btn_cristal_ripple`, `chip_cristal_ripple` y `listSelector` `selector_item_alerta`), estado inline del registro (`txtEstadoRegistro`: cian éxito / magenta con motivo), "Registrar" deshabilitado con "Registrando…" y blindaje de doble toque (`registroEnCurso`), acuse de "Actualizar" (isla "Bandeja al día · N alertas" + parpadeo de la lista) y confirmación `AlertDialog` para "Limpiar".
10. **Acciones nuevas**: copiar token al portapapeles (`ClipboardManager`), isla dinámica táctil (refresca con rebote de escala), avatar → pestaña Registro, tarjeta de críticas del Panel con "Ver alertas críticas" (salta a Alertas con filtro CRITICAS) y conteos en vivo en los chips de filtro ("Todas · 12", etc.).

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-38 | El desenfoque del glassmorphism se **simula** con rellenos translúcidos sobre el fondo de auroras (sin `RenderEffect` ni bibliotecas externas) | Android no ofrece *backdrop blur* portable para Views con minSdk 24; la superposición translúcida sobre gradientes difusos produce el "vidrio ahumado" pedido con costo nulo y sin dependencias nuevas. |
| D-39 | La navegación pasa de scroll de cabecera a **pestañas por visibilidad** (Panel/Alertas/Registro) | `smoothScrollToPosition` sobre una cabecera de ~700 dp tardaba segundos y "Panel"/"Alertas" ejecutaban la misma acción (sensación de botón muerto); el intercambio de visibilidad responde en un frame y da contenido propio a cada pestaña. |
| D-40 | Tema `NoActionBar` + botón "Volver" propio en el detalle | El `ActionBar` rompía la estética glass/neón; la isla dinámica hace de cabecera compacta siempre visible en las tres pestañas. |
| D-41 | Neón por banda: cian APROBADA/BAJO, púrpura EN_REVISION/MEDIO, magenta ALTO/BLOQUEADA | `prompt.txt` pide resaltar decisiones y estados con neón de contraste; una sola tabla de color por estado simplifica el reconocimiento (chip, score, glow y notificación usan el mismo mapeo). |
| D-42 | La analítica ponderada del detalle (45/30/25) se **deriva del score recibido en el push**, sin recalcular nada en el cliente | Son los mismos pesos públicos del motor (D-08); solo se visualiza la composición del riesgo sin exponer datos adicionales ni duplicar lógica de negocio en Android. |

#### Evidencia

- Verificación automatizada (PowerShell) sobre `android/MidffAlertas`: **42 XML parsean sin errores**; sin marcadores residuales de edición; todas las referencias `@string/@color/@drawable` de los XML existen; todos los `R.string/R.drawable/R.color/R.layout` usados en Java existen; **76 `R.id`** usados en Java definidos en los layouts; llaves balanceadas en los 8 `.java` (única excepción aparente: `ApiClient.java`, falso positivo por `{@code …}` en Javadoc y el literal `"{"` de `startsWith`).
- `android/MidffAlertas/README.md` actualizado con las secciones "Rediseño UI: glassmorphism de cristal ahumado con neón" y "Usabilidad: pestañas instantáneas y feedback en cada acción" (incluye la limitación del blur simulado y el requisito AAPT2 para los degradados en vectores).
- **No se compiló el APK** en esta sesión: el repositorio no incluye el wrapper de Gradle (lo genera Android Studio al sincronizar, BIT-007); la validación fue estática completa.

#### Bloqueos y riesgos

- **R-03: sin cambios** — la recepción física del push sigue pendiente de la sesión de demo.
- Riesgo menor (abierto): ajustes visuales al ejecutarse en pantalla real (glows/sombras varían por OEM); se cierra al compilar y validar el APK.

#### Siguiente paso

1. Compilar el APK en Android Studio y validar visualmente en emulador/dispositivo (cierra el riesgo menor de esta entrada).
2. Continuar con el plan acordado: **ajustes de flujo** de la app a partir del resumen funcional y sesiones de **prueba de funcionamiento** end-to-end (transacción → push → bandeja → bloqueo).

### 9.13 BIT-012 — 01/10/2026 — Post-MVP: detección automática de la URL de la API (sin cambiar IP por modo de ejecución)

| Campo | Valor |
|---|---|
| **ID** | BIT-012 |
| **Fecha** | 01/10/2026 |
| **Sprint** | Post-MVP (mejora continua) |
| **Tipo** | Sesión de trabajo |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada |
| **Documentos de referencia** | `README.md` (app) §Notas del entorno · `MVP_MIDFF.md` §6 (RF-09) · `api/v1/ping.php` · `router.php` |

#### Contexto

Cada vez que cambiaba la forma de iniciar la app (emulador, celular por USB con
`adb reverse`, o APK instalado por WiFi) había que editar a mano
`ApiClient.baseUrl` (`127.0.0.1` ↔ `10.0.2.2` ↔ IP LAN de la PC). El residente
pidió que la app resolviera la URL sola para eliminar ese paso.

#### Actividades realizadas

1. **Faro de salud**: `api/v1/ping.php` (nuevo) responde `200` con
   `{"servicio":"midff","estado":"ok","timestamp":...}` **sin X-API-Key ni
   conexión a Oracle**; ruta agregada en `router.php` para `/api/v1/ping` y
   `/api/v1/ping.php` (D-43).
2. **`DescubrimientoApi` (clase nueva en la app)**: descubre la URL base en
   segundo plano probando en orden: última URL que funcionó
   (SharedPreferences) → `10.0.2.2:8090` si es emulador → `127.0.0.1:8090`
   (adb reverse) → gateway DHCP de la WiFi → **escaneo de la subred
   `x.x.x.1-254`** con 32 hilos y timeout de 350 ms. La URL ganadora se
   persiste y se fija en `ApiClient.baseUrl`; el callback llega al hilo
   principal.
3. **Permisos**: `ACCESS_NETWORK_STATE` y `ACCESS_WIFI_STATE` (normales,
   otorgadas en instalación) en el `AndroidManifest.xml` para leer la IP
   local (wlan0) y el gateway DHCP.
4. **Integración en la app**: `MainActivity` ejecuta el descubrimiento al
   arrancar y la isla dinámica informa `Motor C · Buscando API…` /
   `Motor C · API <host:puerto>` (tocarla re-intenta); el botón "Registrar
   token" garantiza el enlace antes del POST (si no hay API muestra
   `api_no_detectada` en magenta); la tarjeta de dispositivo del Panel
   muestra `API detectada: <url>`; `NotificacionFcmService.onNewToken`
   también asegura el enlace antes del autoregistro.
5. **README de la app** (§Notas del entorno): modo WiFi documentado
   (`php -S 0.0.0.0:8090 router.php` + regla de firewall
   `netsh advfirewall ... localport=8090`), tiempos esperados del escaneo
   (~2-4 s solo la primera vez) y re-escaneo automático ante cambio DHCP.

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-43 | Detección automática de la URL por **sondeo de `GET /api/v1/ping`** (endpoint nuevo sin API-Key ni Oracle) con orden fijo de candidatos (guardada → emulador → adb reverse → gateway) y **escaneo de subred /24** (32 hilos, timeout 350 ms, tope 4 s) solo como último recurso; la URL se persiste y fija `ApiClient.baseUrl` en runtime | Elimina el paso manual por modo de ejecución sin tocar el contrato de negocio: el ping no expone datos y su costo es despreciable; el escaneo se limita a la subred local del teléfono, se ejecuta únicamente cuando ningún candidato directo responde y su resultado se recuerda (reconexión instantánea, re-escaneo solo si la PC cambió de IP por DHCP). |

#### Evidencia

- `php -l` → "No syntax errors detected" en `api/v1/ping.php` y `router.php`
  (PHP 8.1.10 de Laragon).
- **Prueba en vivo del faro**: `php -S 127.0.0.1:8099 router.php` +
  `GET /api/v1/ping` → `HTTP 200` con
  `{"servicio":"midff","componente":"api","estado":"ok","timestamp":"2026-10-02T00:33:55+00:00"}`;
  servidor detenido tras la prueba.
- Validación estática de la app tras los cambios: 42 XML del módulo parsean
  sin errores; llaves balanceadas en los 9 `.java` (falso positivo conocido
  en `ApiClient.java` por `{@code}` y el literal `"{"`).
- README de la app: sección "Conexión automática (D-43)" con los comandos de
  WiFi (`php -S 0.0.0.0:8090` + regla `netsh`).

#### Bloqueos y riesgos

- **R-03: sin cambios** (recepción física del push pendiente de la demo).
- Riesgo menor (abierto): el escaneo de subred depende de que la PC acepte
  conexiones entrantes en 8090 (bind `0.0.0.0` + firewall); documentado en el
  README. En redes con aislamiento de clientes (AP isolation) no aplicará y la
  isla quedará en "Sin enlace FCM" hasta usar USB/emulador.

#### Siguiente paso

1. Compilar el APK y **probar los tres modos** (emulador, USB con
   `adb reverse`, WiFi instalado) verificando que la isla resuelve la URL
   correcta en cada uno.
2. Continuar con los ajustes de flujo y las sesiones de prueba de
   funcionamiento end-to-end acordadas.

### 9.14 BIT-013 — 01/10/2026 — Corrección: error de compilación en la isla dinámica y compatibilidad Java 17

| Campo | Valor |
|---|---|
| **ID** | BIT-013 |
| **Fecha** | 01/10/2026 |
| **Sprint** | Post-MVP (mejora continua) |
| **Tipo** | Corrección |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada |
| **Documentos de referencia** | `errores.txt` (salida del primer `compileDebugJavaWithJavac`) · `app/build.gradle` · `MainActivity.java` |

#### Contexto

La primera compilación real del proyecto rediseñado (Android Studio, JDK 21)
arrojó **1 error y 3 warnings**, volcados por el residente en `errores.txt`:
(1) `MainActivity.java:256` — `setText(ok ? getString(...) : R.string....)`
mezcla `String` con `int` (id de recurso) y Java no encuentra sobrecarga
aplicable; (2) source/target Java 8 deprecados por el JDK 21; (3) nota de API
deprecada en `DescubrimientoApi` (`WifiManager.getDhcpInfo()`).

#### Actividades realizadas

1. **`MainActivity.java` (error bloqueante)**: el ramo falso del ternario pasa
   a `getString(R.string.isla_sin_enlace)` → ambas ramas son `String` y
   `setText(CharSequence)` aplica.
2. **`app/build.gradle` (D-44)**: `sourceCompatibility`/`targetCompatibility`
   elevados de `VERSION_1_8` a **`VERSION_17`**; README de la app actualizado
   (fila "Lenguaje"). AGP 8.5 desugarea el bytecode a DEX para minSdk 24, por
   lo que el cambio no afecta dispositivos soportados.
3. **`DescubrimientoApi.gatewayWifi()`**: `@SuppressWarnings("deprecation")`
   documentando que `getDhcpInfo()` está deprecado desde API 31 pero sigue
   funcional y solo se usa como candidato barato de descubrimiento.

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-44 | Elevar la compatibilidad de compilación de Java 8 a **Java 17** | El JDK 21 (JBR de Android Studio) deprecó compilar con source/target 8; 17 es el nivel recomendado por el propio diagnóstico, elimina los warnings de arranque y AGP 8.5 + Gradle 8.9 lo soportan de forma nativa. |

#### Evidencia

- Corrección aplicada en `MainActivity.java` (línea 256 → `getString(...)`).
- Revisión de patrones similares: los únicos ternarios con `R.*` restantes
  (`setBackgroundResource` y `setTextColor` en `MainActivity`) son `int:int`,
  válidos.
- `errores.txt` corresponde a la compilación previa a estas correcciones.

#### Bloqueos y riesgos

- Sin cambios en R-03; sin nuevos riesgos.

#### Siguiente paso

1. Recompilar (*Build → Make Project*) y ejecutar la app en los tres modos
   (emulador, USB con `adb reverse`, WiFi instalado).

### 9.15 BIT-014 — 01/10/2026 — Corrección: push que no llenaba la bandeja y mudanza al puerto 8091

| Campo | Valor |
|---|---|
| **ID** | BIT-014 |
| **Fecha** | 01/10/2026 |
| **Sprint** | Post-MVP (mejora continua) |
| **Tipo** | Corrección |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada |
| **Documentos de referencia** | `config/firebase.php` §5 · `banco.html` · `DescubrimientoApi.java` · README raíz/app |

#### Contexto

Prueba real de la sesión: una transacción ALTO generó el push y la notificación
llegó al teléfono, **pero la alerta no aparecía en la bandeja de la app**, y el
residente no podía usar `banco.html` para enviar transacciones.

#### Actividades realizadas

1. **Diagnóstico del push**: el payload HTTP v1 incluía `message.notification`
   (título/cuerpo). En 2º plano Android muestra esa notificación él mismo y **no
   invoca `NotificacionFcmService`**, por lo que la alerta jamás se guardaba en
   la bandeja (RF-10) ni el toque abría el detalle.
2. **`config/firebase.php` (D-46)**: payloads de alerta y de transacción sana
   convertidos a **data-only** (`message.data` + `android.priority/collapse_key`,
   sin `notification` ni `android.notification`); el servicio siempre ejecuta,
   persiste en la bandeja y construye la notificación local del canal
   `midff_alertas` con apertura del detalle. `php -l` sin errores.
3. **Conflicto de puerto (D-45)**: `WsToastNotification.exe` (Wondershare)
   ocupa el 8090 de la máquina de demo → puerto del MVP mudado a **8091**:
   `DescubrimientoApi.PUERTO`, `ApiClient.baseUrl`, README raíz (9 menciones),
   README de la app (12) y `smoke-test.ps1` (default `-UrlApi`).
4. **`banco.html`**: nota de apertura actualizada a
   `http://127.0.0.1:8091/banco.html`, URL del endpoint con valor por defecto
   inteligente (relativa si se sirve por HTTP; absoluta a 8091 si se abre como
   archivo) y **persistencia en `localStorage`** de la última URL usada.

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-45 | Puerto oficial del MVP: **8091** en lugar de 8090 | Wondershare NativePush escucha el 8090 y se re-lanza con el sistema: liberarlo hoy no garantiza nada mañana. La mudanza es un reemplazo de literal en 5 archivos y queda documentada; `adb reverse`/firewall/smoke-test pasan a 8091. |
| D-46 | Payload FCM **data-only** (sin `message.notification`) | Es el único modo en que `onMessageReceived` corre con la app en 2º plano/killed: garantiza bandeja (RF-10), notificación local con monto/score/clasificación y apertura del detalle (HU-MVP-05). El título/cuerpo ya los construye la app con los datos. |

#### Evidencia

- `php -l` → "No syntax errors detected" en `config/firebase.php`.
- Transacción post-fix por HTTP contra `127.0.0.1:8091`:
  `TRX-2026-08FDA5D2 / score 0.95 / ALTO / BLOQUEADA / ALR-2026-000019`
  (push data-only enviado a los dispositivos registrados).
- Verificación de reemplazos: 0 menciones operativas de 8090 fuera de los
  comentarios explicativos de `banco.html`/`DescubrimientoApi` y BITACORA.

#### Bloqueos y riesgos

- **R-03 (parcial)**: el push se entrega; falta la verificación física del
  reflejo en bandeja (esta transacción sirve de prueba).
- La app instalada aún busca el 8090: reinstalar el APK compilado con D-45
  para registro/bloqueo por WiFi o `adb reverse tcp:8091 tcp:8091`.

#### Siguiente paso

1. Confirmar en el teléfono que `ALR-2026-000019` aparece en la bandeja al
   abrir la app (cierra el circuito RF-09/RF-10).
2. Recompilar/reinstalar el APK (puerto 8091) y usar `banco.html` desde
   `http://127.0.0.1:8091/banco.html` para las pruebas de flujo.

### 9.16 BIT-015 — 01/10/2026 — Pivote a enfoque cliente: login Firebase Auth y candado biométrico

| Campo | Valor |
|---|---|
| **ID** | BIT-015 |
| **Fecha** | 01/10/2026 |
| **Sprint** | Post-MVP (mejora continua) |
| **Tipo** | Sesión de trabajo |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada |
| **Documentos de referencia** | `prompt.txt` (propuesta: alertas tempranas, sin destinatario fijo) · `android/MidffAlertas/README.md` §Acceso de clientes |

#### Contexto

El residente aclaró que siempre imaginó la app como **herrencia del cliente
del banco** (cada usuario ve las notificaciones de SUS transacciones con
riesgo nulo/medio/alto), no como bandeja de oficiales. El análisis de la
sesión demostró que el motor (C + Oracle + API) es agnóstico del
destinatario y que el pivote toca solo la "última milla". Primera parte:
identidad y acceso.

#### Actividades realizadas

1. **`LoginActivity` + `activity_login.xml` (nuevos)**: pantalla glass/neón
   con correo y contraseña; botones **Entrar** y **Crear cuenta** contra
   **Firebase Authentication** (`firebase-auth:22.3.1`), validación local
   (correo con `@`, contraseña ≥ 6) y estado inline magenta ante fallos.
2. **`SesionCliente` (nueva)**: sesión persistente en SharedPreferences
   (activa/correo/uid); `MainActivity` manda al login si no hay sesión.
3. **Candado biométrico en cada entrada**: `pantalla_bloqueo.xml` (Dialog
   fullscreen con candado y "Reintentar") + `BiometricPrompt`
   (`androidx.biometric:1.1.0`, permiso `USE_BIOMETRIC`) aceptando
   **huella/rostro (BIOMETRIC_WEAK) o PIN/patrón del dispositivo
   (DEVICE_CREDENTIAL)** en Android 10+; en 9- solo biometría con botón
   negativo. `onStop` vuelve a bloquear; sin biometría configurada
   (emulador) no se bloquea y la isla informa "Biometría no configurada".
4. **Registro**: pestaña Registro precarga el correo de la sesión como
   usuario del dispositivo y gana botón **Cerrar sesión** (confirmación →
   `signOut()` + limpieza → login).
5. **README de la app**: sección "Acceso de clientes" con el paso obligatorio
   en **Firebase Console → Authentication → Sign-in method →
   Correo/contraseña** (sin él el login responde CONFIGURATION_ERROR).

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-47 | El producto se re-enfoca al **cliente** del banco; identidad con Firebase Auth (correo/contraseña) y desbloqueo con las credenciales **del dispositivo** (BiometricPrompt) | Es la visión del residente (dueño del producto), escala mejor (1 push al dueño vs broadcast) y Firebase Auth evita construir backend de identidad en el MVP; la biometría no se implementa propietariamente: se usan las credenciales ya registradas en Android (huella/rostro/PIN), que es lo que el usuario pidió y lo que exige la banca real. El modo "oficial/broadcast" queda como caso demo hasta completar el filtrado por cliente. |

#### Evidencia

- Validación estática automatizada: XML de la app parsean sin errores;
  referencias `@string/@drawable/@color` y `R.*` completas; llaves
  balanceadas en los nuevos `.java`.
- `app/build.gradle`: `firebase-auth:22.3.1` + `androidx.biometric:1.1.0`;
  `AndroidManifest.xml`: permiso `USE_BIOMETRIC` y `LoginActivity`.

#### Bloqueos y riesgos

- **Requiere acción en Firebase Console** (habilitar Correo/contraseña);
  documentado en el README.
- Pendiente de lógica (siguiente sesión): **filtrar el push por cliente**
  (`firebase.php` + `id_cliente` en `DISPOSITIVO_MOVIL`) y renombrar la
  acción "Bloquear" a "No fui yo / Reportar".
- R-03 sin cambios.

#### Siguiente paso

1. Habilitar Email/Password en Firebase Console, compilar y probar
   login → biometría → bandeja.
2. Ejecutar el filtrado de push por cliente (2ª parte del pivote).

---

### 9.17 BIT-016 — 06/10/2026 — Corrección: el candado biométrico no compilaba (API inexistente en biometric 1.1.0)

| Campo | Valor |
|---|---|
| **ID** | BIT-016 |
| **Fecha** | 06/10/2026 |
| **Sprint** | Post-MVP (mejora continua) |
| **Tipo** | Corrección |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada |
| **Documentos de referencia** | `errores.txt` (build de BIT-013) · `android/MidffAlertas/app/build.gradle` |

#### Contexto

La verificación del flujo cliente (login → biometría → bandeja) intentó
compilar la app con Gradle 8.9 y el build falló en
`:app:compileDebugJavaWithJavac` con 2 errores `cannot find symbol`:
`PromptInfo.Builder.setAuthenticators(int)` no existe en
`androidx.biometric:biometric:1.1.0` (esa API se agregó en versiones
posteriores de la biblioteca). El resto del código de BIT-015 compilaba.

#### Actividades realizadas

1. `MainActivity.lanzarPromptBiometrico()`: se elimina la bifurcación
   `setAuthenticators(BIOMETRIC_WEAK | DEVICE_CREDENTIAL)` (API 30+) /
   `setAuthenticators(BIOMETRIC_WEAK) + setNegativeButtonText(...)` y se
   sustituye por `.setDeviceCredentialAllowed(true)`, que es la forma
   soportada en 1.1.0 para aceptar **huella/rostro o el PIN/patrón/contraseña
   del dispositivo** en todas las versiones (minSdk 24). Nota: con la
   credencial habilitada no puede fijarse `setNegativeButtonText()`
   (lanzaría `IllegalArgumentException`).
2. Verificación real: `gradle assembleDebug` → **BUILD SUCCESSFUL in 12s**,
   `app-debug.apk` generado (queda el aviso known de API deprecada por
   `WifiManager.getDhcpInfo()` en `DescubrimientoApi`, solo warning).
3. `canAuthenticate(BIOMETRIC_WEAK | DEVICE_CREDENTIAL)` se mantiene: sí
   existe en 1.1.0 y era aceptado por el compilador.

#### Bloqueos y riesgos

- Ninguno nuevo; los pendientes de BIT-015 siguen: filtrar el push por
  cliente y reinstalar el APK en el dispositivo.

#### Siguiente paso

1. Reinstalar el APK y probar login → biometría → bandeja en dispositivo.
2. Filtrado del push por cliente (2ª parte del pivote).

---

### 9.18 BIT-017 — 06/10/2026 — Push filtrado por cliente: cada usuario recibe solo SUS transacciones

| Campo | Valor |
|---|---|
| **ID** | BIT-017 |
| **Fecha** | 06/10/2026 |
| **Sprint** | Post-MVP (mejora continua) |
| **Tipo** | Sesión de trabajo |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada |
| **Documentos de referencia** | BIT-015/BIT-016 (pivote a cliente) · `database/dispositivo_movil_id_cliente.sql` · `config/firebase.php` §3 y §7 |

#### Contexto

Con el login y el candado biométrico listos (BIT-015/016) el push seguía en
modo **broadcast**: `midff_fcm_enviar_a_todos()` entregaba cada alerta a TODOS
los dispositivos activos, así que cualquier cliente veía las transacciones de
todos. Falta la "última milla" del pivote: vincular cada dispositivo a su
`id_cliente` y filtrar el envío.

#### Actividades realizadas

1. **Oracle**: columna `ID_CLIENTE VARCHAR2(40) NULL` en `DISPOSITIVO_MOVIL`
   (en `dispositivo_movil.sql` para instalaciones nuevas y en la migración
   re-ejecutable `database/dispositivo_movil_id_cliente.sql` para la BD
   existente, **sin perder tokens**), CHECK de rol ampliado con `CLIENTE` e
   índice `IX_DISPOSITIVO_MOVIL_CLI (id_cliente, activo)`. Migración ejecutada
   en XEPDB1: los 2 dispositivos previos quedan con `id_cliente` NULL
   (broadcast legado) hasta que se re-registren desde la app.
2. **API** (`api/v1/dispositivos.php`): el contrato acepta `id_cliente`
   opcional (≤ 40, mismo tamaño que `transacciones.php`) y el rol `CLIENTE`;
   el UPSERT persiste el vínculo y el log registra
   `cliente=CLI-XXX | (broadcast)`.
3. **Filtro del push** (`config/firebase.php`):
   `midff_fcm_dispositivos_activos($idCliente)` consulta solo los dispositivos
   activos de ese cliente (PDO_OCI y OCI8) y
   `midff_fcm_enviar_a_dispositivos($constructor, $idCliente)` sustituye a
   `enviar_a_todos`; `midff_fcm_notificar_alerta()/notificar_sana()` extraen el
   `id_cliente` de la transacción. **Sin id_cliente → broadcast legado** (modo
   oficial/demo intacto). Si falta la columna en Oracle, el log sugiere
   ejecutar la migración (ORA-00904).
4. **App**: pestaña Registro gana el campo **"ID de cliente"** (persistido en
   `SesionCliente`, BIT-017); el registro envía `usuario` (correo de la
   sesión), `rol=CLIENTE` e `id_cliente`, y `NotificacionFcmService.onNewToken`
   auto-registra con los mismos datos. El contrato del Anexo A de
   `POST /api/v1/transacciones` NO cambia (el filtro es interno).

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-48 | El vínculo dispositivo↔cliente se hace por `id_cliente` explícito (campo en la pestaña Registro), no derivando el correo | Las transacciones llegan con `id_cliente` (CLI-XXX) desde el orquestador; derivarlo del correo exigiría un convencio frágil de creación de cuentas. El campo se escribe una vez y queda persistido |
| D-49 | `id_cliente` vacío/NULL conserva el broadcast legado | El modo oficial/demo y las pruebas con `banco.html`/curl siguen funcionando sin re-registro; la migración no rompe nada |

#### Evidencia

- `sqlplus ... @database/dispositivo_movil_id_cliente.sql` → `[1/3] Columna
  ID_CLIENTE agregada`, `[2/3] CHECK de rol ampliado`, `[3/3] Índice creado`
  y consulta de control con los 2 dispositivos previos (id_cliente vacío).
- `git grep midff_fcm_enviar_a_todos` → sin resultados (migración completa).
- `gradle assembleDebug` → BUILD SUCCESSFUL (APK con el campo "ID de cliente").

#### Bloqueos y riesgos

- Ninguno bloqueante. R-03 sin cambios. Pendiente de demo en dispositivo
  físico (registrar CLI-XXX en la app y disparar transacciones de ese y de
  otro cliente para verificar el aislamiento).

#### Siguiente paso

1. Reinstalar el APK, registrar un `id_cliente` real y probar el aislamiento
   del push (transacción del propio cliente llega; de otro cliente no).
2. Lógica para el banco (administración): mover/rediseñar el módulo oficial.

---

### 9.19 BIT-018 — 06/10/2026 — ID de cliente auto-generado y vista del banco

| Campo | Valor |
|---|---|
| **ID** | BIT-018 |
| **Fecha** | 06/10/2026 |
| **Sprint** | Post-MVP (mejora continua) |
| **Tipo** | Sesión de trabajo |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada |
| **Documentos de referencia** | BIT-017 (filtro por cliente) · `banco.html` · `api/v1/dispositivos.php` |

#### Contexto

En BIT-017 el usuario debía escribir su `id_cliente` a mano, pero ese ID lo
asigna el producto, no el cliente: al crear la cuenta debe quedar registrado
con un ID predeterminado que el usuario pueda ver en la app y que el
banco/administrador solo le requiera para vincular sus transacciones.

#### Actividades realizadas

1. **`SesionCliente`**: `abrir()` asigna el ID la primera vez
   (`asegurarIdCliente`): `CLI-` + 6 caracteres alfanuméricos del uid de
   Firebase en mayúsculas. Determinista (la misma cuenta recupera el mismo ID
   tras reinstalar) y no sobreescribe uno ya guardado (la captura manual queda
   como válvula de escape para la demo). Visible en la pestaña Registro
   (campo precargado) y usado por el registro del token y `onNewToken`.
2. **`GET /api/v1/dispositivos`** (misma X-API-Key): lista los dispositivos
   con `id_dispositivo, usuario, rol, id_cliente, activo, registrado,
   token_final` (token enmascarado); `midff_listar_dispositivos()` en
   `api/v1/dispositivos.php`. El 405 ahora anuncia `Allow: GET, POST`.
3. **`banco.html`**: tarjeta "Clientes registrados en la app (vista del
   banco)" con botón **Actualizar lista** (fetch al GET) y **Usar** por fila,
   que copia el `id_cliente` al formulario de transacciones.

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-50 | El ID se deriva del uid de Firebase (`CLI-XXXXXX`), sin registro en servidor ni contador | Determinista ante reinstalación, sin nueva tabla ni carrera de IDs; el vínculo real dispositivo↔cliente ya vive en `DISPOSITIVO_MOVIL.id_cliente` |
| D-51 | La vista del banco consume el mismo endpoint con API-Key, sin UI nueva de servidor | `banco.html` ya es la consola del banco en el MVP; suficiente para que el administrador "solo requiera el ID" |

#### Evidencia

- `gradle assembleDebug` → BUILD SUCCESSFUL (APK con ID auto-generado).
- `GET /api/v1/dispositivos` devuelve los 2 dispositivos existentes con
  `id_cliente` vacío (broadcast legado) hasta re-registrarse.

#### Bloqueos y riesgos

- Ninguno bloqueante. La Colisión teórica de prefijos de uid (6 caracteres)
  es despreciable para el alcance del proyecto.

#### Siguiente paso

1. Demo completa: crear cuenta → ver ID → registrar token → el banco usa el
   ID desde `banco.html` → el push llega solo a ese cliente.
2. Siguiente lógica del banco: administración de alertas (bandeja del
   oficial, bloqueos, reportes).

---

### 9.20 BIT-019 — 06/10/2026 — Consola del banco MIDFF.html (administración)

| Campo | Valor |
|---|---|
| **ID** | BIT-019 |
| **Fecha** | 06/10/2026 |
| **Sprint** | Post-MVP (mejora continua) |
| **Tipo** | Sesión de trabajo |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada |
| **Documentos de referencia** | BIT-015..018 (pivote a cliente) · `api/v1/admin.php` · `database/admin_usuario.sql` |

#### Contexto

El usuario pidió separar la lógica del banco de la app del cliente y llevarla
a un programa independiente "casi a nivel empresarial". Se decidió web (D-52)
con nombre de archivo **`MIDFF.html`**: mismo servidor PHP, cero instalación.

#### Actividades realizadas

1. **Identidad de administradores** (`database/admin_usuario.sql`, ejecutado):
   tabla `ADMIN_USUARIO` con `password_hash` (bcrypt de PHP) y
   `clave_temporal` de arranque; semilla `admin / MIDFFadmin2026` (TEMPORAL:
   la consola exige cambiarla al primer inicio y queda como hash).
2. **API protegida** (`api/v1/admin.php` + rutas en `router.php`):
   `POST /api/v1/admin/sesion` (login → token Bearer HMAC-SHA256 de 8 h firmado
   con `MIDFF_ADMIN_SECRETO`), `POST /api/v1/admin/clave` (cambio con
   verificación de la actual), y con token obligatorio:
   `GET /api/v1/admin/resumen` (transacciones/monto de hoy, bandas 24 h,
   alertas y pendientes, score/latencia promedio del motor, top 5 clientes por
   riesgo y serie por hora), `GET /api/v1/admin/alertas`
   (JOIN ALERTA→EVALUACION→TRANSACCION con filtros cliente/banda/estado y
   `ALERTA_ID` en formato ALR-AAAA-NNNNNN) y `GET /api/v1/admin/clientes`
   (agregado por cliente + dispositivos activos). 401 expira la sesión en la
   UI; todo queda en `error_log` con etiqueta `[MIDFF-ADMIN]`.
3. **`MIDFF.html`** (UI enterprise dark, sin dependencias): login con Enter,
   chip "En vivo · 20 s" (auto-refresco del tab activo), pestañas
   **Resumen** (KPIs + gráfica de barras apiladas 24 h + top clientes),
   **Alertas** (filtros + tabla + **Bloquear** con comentario → reusa
   `POST /api/v1/bloquear`/`SP_BLOQUEAR_ALERTA` + auditoría; estados
   PENDIENTE/BLOQUEADA/ATENDIDA descubiertos contra datos reales) y
   **Clientes** (búsqueda, score promedio, dispositivos, "Ver alertas"
   que precarga el filtro). Modal obligatorio de cambio de contraseña.

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-52 | Consola web `MIDFF.html` en el mismo servidor PHP, con login propio | Cero instalación/distribución, reusa API y Oracle; "programa de computadora" queda como port posterior consumiendo la misma API |
| D-53 | Sesión estatal-less HMAC (Bearer 8 h) + bcrypt en `ADMIN_USUARIO` | Sin tabla de sesiones ni dependencias; separa identidad del banco (Oracle) de la del cliente (Firebase Auth) |
| D-54 | El bloqueo sigue por `POST /api/v1/bloquear` con X-API-Key | Reusa el SP y la auditoría del Sprint 4 sin duplicar lógica ni romper el contrato |

#### Evidencia

- `sqlplus @database/admin_usuario.sql` → tabla/seq/triggers OK y semilla
  `admin` con clave TEMPORAL (el INSERT se repitió manual con COMMIT tras un
  corte de tubería del primer run).
- Las 5 consultas del resumen/alertas/clientes ejecutadas contra XEPDB1 real
  (top: CLI-003 · 10 tx · score .95; alertas FRAUDE/ALTO/BLOQUEADA;
  6 clientes) — de ahí salió el estado BLOQUEADA agregado al filtro.
- PHP sin lint local (sin php.exe en la terminal): código revisado contra los
  patrones de los endpoints existentes.

#### Bloqueos y riesgos

- Sin bloqueo. En producción: HTTPS obligatorio (el Bearer viaja en
  encabezado) y `MIDFF_ADMIN_SECRETO` definido por entorno.

#### Siguiente paso

1. Limpiar la app del cliente: quitar pestaña Panel y botón "Bloquear",
   registro invisible/automático.
2. Agregar "No fui yo / Sí fui yo" en la app (visible en la consola).

---

### 9.21 BIT-020 — 06/10/2026 — Flujo completo: app del cliente limpia + "No fui yo" visible en el banco

| Campo | Valor |
|---|---|
| **ID** | BIT-020 |
| **Fecha** | 06/10/2026 |
| **Sprint** | Post-MVP (mejora continua) |
| **Tipo** | Sesión de trabajo |
| **Responsable** | Residente del proyecto |
| **Estado** | Cerrada |
| **Documentos de referencia** | BIT-017..019 · `api/v1/reportar.php` · `DetalleAlertaActivity` |

#### Contexto

Cierre del pivote: dejar funcionando el flujo de ambos lados "tal cual se
use". Faltaba limpiar la app (lógica de banco heredada del MVP de oficiales)
y el canal de respuesta del cliente hacia el banco.

#### Actividades realizadas

1. **`POST /api/v1/reportar`** (`api/v1/reportar.php` + ruta): el cliente
   responde `NO_FUI_YO`/`SI_FUI_YO` (+ comentario ≤ 180) a una alerta
   `ALR-…`. Transacción atómica: verifica con `FOR UPDATE` que la alerta sea
   **de ese cliente** (JOIN hasta TRANSACCION), 404 si ajena, 409 si ya
   reportada; guarda en `ALERTA.respuesta/atendida_por/fecha_atencion` (el
   `estado` lo decide el banco; el CHECK del schema no se toca) y en
   `AUDITORIA` (`ATENCION`, `CLIENTE:<id>`). Log `[MIDFF-REPORTE]`.
2. **App del cliente**: `DetalleAlertaActivity` sustituye "Bloquear" por
   **"⛔ No fui yo" / "✓ Sí fui yo"** (`ApiClient.reportarAlerta`); tras
   responder ambos botones se bloquean con "Ya respondiste".
3. **Limpieza de MainActivity**: tab **Panel oculto** (la app abre directo en
   la bandeja), "Registro" renombrado a **"Perfil"**, y
   **registro automático silencioso** del token con el `id_cliente` de la
   sesión en cada apertura (el botón manual queda de respaldo).
4. **Consola `MIDFF.html`**: `GET /api/v1/admin/alertas` ahora incluye
   `a.respuesta` y la tabla muestra la columna **"Reporte cliente"** con
   bandera ⚑ NO FUI YO (roja, con comentario si lo hay) o ✓ SÍ FUI YO.

#### Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| D-55 | El reporte del cliente NO cambia `ALERTA.estado` (solo respuesta/atendida_por/fecha_atencion) | El CHECK del schema solo permite PENDIENTE/ATENDIDA/BLOQUEADA y la decisión de bloquear es del banco; el reporte es insumo, no veredicto |
| D-56 | El Panel de métricas se oculta en vez de borrarse | La estadística interna alimenta la isla dinámica; quitarla toda arriesgaba romper referencias por un cambio cosmético reversible |

#### Evidencia

- `php -l` sin errores (`reportar.php`, `admin.php`, `router.php`) y
  `node --check` OK para `MIDFF.html`.
- E2E con servidor real: reporte de `ALR-2026-000024` por `CLI-003` con
  comentario → `{ok, id_auditoria: 100}`; la consola mostró
  `respuesta=[NO_FUI_YO :: No reconozco este cargo], atendida_por=CLI-003,
  estado=PENDIENTE`; cliente ajeno → **404**; doble reporte → **409**.
  Datos de prueba restaurados (la AUDITORIA queda como evidencia inmutable).
- `gradle assembleDebug` → **BUILD SUCCESSFUL** (APK con el flujo nuevo).

#### Bloqueos y riesgos

- Ninguno. La AUDITORIA de la prueba quedó a propósito (registro inmutable).

#### Siguiente paso

1. Demo completa en dispositivo: push → "No fui yo" → bandera en MIDFF.html
   → el oficial Bloquea desde la consola.
2. Extras opcionales: notificar al cliente cuando el banco bloquea, filtro
   "solo reportadas" en la consola, exportar CSV.

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
| 1.1 | 23/09/2026 | Residente del proyecto | Se agrega la entrada **BIT-002** (Sprint 1): motor de scoring en C (`src/motor/`) con 18 casos de prueba unitaria y benchmark de latencia en microsegundos, modelo de datos Oracle XE (`database/schema.sql`) con el procedimiento atómico `SP_REGISTRAR_TRANSACCION`, script `build.ps1` y decisiones D-07 a D-15. |
| 1.2 | 24/09/2026 | Residente del proyecto | Se agrega la entrada **BIT-003** (Sprint 2): endpoint `POST /api/v1/transacciones` (API Key, validaciones, invocación del motor C y contrato de respuesta), conexión y persistencia atómica en Oracle XE desde `config/db.php`, `router.php` para el servidor embebido de PHP y `smoke-test.ps1` (3 bandas de riesgo, pruebas negativas 401/400, latencia y cobertura de auditoría). Métricas §7 y riesgos R-01/R-02 actualizados; decisiones D-16 a D-22. |
| 1.3 | 24/09/2026 | Residente del proyecto | Se agrega la entrada **BIT-004** (Sprint 3): módulo FCM `config/firebase.php` (constante `MIDFF_FCM_SERVER_KEY`, envío best-effort por protocolo legacy), tabla `DISPOSITIVO_MOVIL` (`database/dispositivo_movil.sql`, re-ejecutable), gancho de push en `api/v1/transacciones.php` cuando `score >= 0.4`, endpoint `POST /api/v1/dispositivos` (UPSERT del token) con su ruta en `router.php`, app Android `android/MidffAlertas/` en Java (plugin google-services, `NotificacionFcmService` con notificación local de monto/score/clasificación, `MainActivity` con bandeja) y `firebase/README-fcm.md` con plantillas. Métricas §7 y riesgos R-03/R-05 actualizados; decisiones D-23 a D-28. |
| 1.4 | 24/09/2026 | Residente del proyecto | Se agrega la entrada **BIT-005** (corrección): `applicationId`/`namespace` de la app renombrados a `midff_plurione` (clases movidas a `java/midff_plurione/`), retiro del `google-services.json` prestado y plantilla `google-services.json.example` + guía de alta en Firebase en los README; decisión D-29. |
| 1.5 | 24/09/2026 | Residente del proyecto | Se agrega la entrada **BIT-006** (corrección): `applicationId`/`namespace` definitivos en `Midff.PluriOne1` — el guion `-` de `Midff.Pluri-One1` no es válido en paquetes Java/Android/Firebase y se omitió de común acuerdo —; clases movidas a `java/Midff/PluriOne1/`, plantilla y guías de alta actualizadas; decisión D-30. |
| 1.6 | 24/09/2026 | Residente del proyecto | Se agrega la entrada **BIT-007** (corrección): bloqueo de sync de Android Studio resuelto actualizando el wrapper a Gradle 8.9 y el AGP a 8.5.2 (el JBR 21 del IDE no es soportado por Gradle 8.0: "Unsupported class file major version 65"); sección de solución de problemas agregada al README de la app; decisión D-31. |
| 1.7 | 24/09/2026 | Residente del proyecto | Se agrega la entrada **BIT-008** (corrección): parse del `AndroidManifest.xml` resuelto — la secuencia `--` no está permitida en comentarios XML; cabecera reescrita con `=` y bullet agregado al README de la app; decisión D-32. |
| 1.8 | 28/09/2026 | Residente del proyecto | Se agrega la entrada **BIT-009** (Sprint 3, regularización): el envío FCM quedó migrado al protocolo HTTP v1 con OAuth2 (JWT RS256 → access token; decisión D-33, sustituye D-23) y la cuenta de servicio real instalada en `config/firebase.credentials.json` (excluida de Git; decisión D-34); credencial validada en vivo el 28/09 (access token `ya29.` emitido por Google). Métrica §7 (entrega de push) y riesgo R-03 actualizados. |
| 1.9 | 28/09/2026 | Residente del proyecto | Se agrega la entrada **BIT-010** (Sprint 4, cierre del MVP): `SP_BLOQUEAR_ALERTA` (`database/sp_bloquear_alerta.sql`, D-35), `midff_db_bloquear_alerta()` en `config/db.php`, endpoint `POST /api/v1/bloquear` con su ruta en `router.php` v1.2 (D-36), botón "Bloquear" en la app Android con estado local `BLOQUEADA` (D-37) y `README.md` reescrito (instalación, credenciales Oracle/Firebase, guion de demo y consultas de control). E2E verificado en vivo (`ALR-2026-000010`, auditoría 39, 409/404 controlados). DoD §6 y métricas §7 finales; riesgo R-05 cerrado. |
| 1.10 | 01/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-011** (post-MVP): rediseño UI "glassmorphism de cristal ahumado con neón de contraste profundo" de la app Android según `prompt.txt` (20 drawables nuevos: paneles de cristal, auroras, cápsulas neón, iconos con degradado `aapt:attr`; tema `NoActionBar`; bandas de riesgo cian/púrpura/magenta; clase `UtilidadesUi`; analítica ponderada 45/30/25 y carrusel de dispositivos en el detalle) y segunda pasada de usabilidad (pestañas instantáneas Panel/Alertas/Registro que sustituyen el scroll de cabecera, ripples y feedback en toda la interfaz, estado inline del registro, confirmación de vaciado, copia de token al portapapeles, isla táctil y conteos en vivo en los filtros). Decisiones D-38 a D-42; validación estática automatizada (42 XML, 76 `R.id`, referencias completas). |
| 1.11 | 01/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-012** (post-MVP): detección automática de la URL de la API para eliminar el cambio manual de IP por modo de ejecución. Faro `GET /api/v1/ping` sin API-Key (`api/v1/ping.php` + rutas en `router.php`), clase `DescubrimientoApi` (candidatos: guardada → `10.0.2.2` emulador → `127.0.0.1` adb reverse → gateway DHCP → escaneo de subred /24 con 32 hilos y timeout 350 ms), persistencia y fijado de `ApiClient.baseUrl`; isla dinámica informa el enlace y el registro lo garantiza antes del POST; permisos `ACCESS_NETWORK_STATE`/`ACCESS_WIFI_STATE`; README con modo WiFi (`php -S 0.0.0.0:8090` + regla de firewall). Decisión D-43; faro verificado en vivo (HTTP 200 con `servicio: midff`). |
| 1.12 | 01/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-013** (corrección): primer build real corregido a partir de `errores.txt` — ternario `String`/`int` inválido en el `setText` de la isla dinámica (`MainActivity.java:256`, resuelto con `getString()`), compatibilidad de compilación elevada de Java 8 a **17** (D-44) y `@SuppressWarnings("deprecation")` sobre `WifiManager.getDhcpInfo()` en `DescubrimientoApi`. README de la app actualizado (fila "Lenguaje"). |
| 1.13 | 01/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-014** (corrección): push FCM migrado a **data-only** (D-46: el bloque `notification` hacía que Android en 2º plano mostrara el aviso sin ejecutar `NotificacionFcmService`, por lo que la alerta nunca llegaba a la bandeja) y puerto del MVP mudado de 8090 a **8091** (D-45: Wondershare NativePush ocupa el 8090) en `DescubrimientoApi`/`ApiClient`/READMEs/`smoke-test.ps1`; `banco.html` con URL por defecto inteligente y persistente. Transacción post-fix verificada (`ALR-2026-000019`). |
| 1.14 | 01/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-015** (pivote a enfoque cliente, D-47): `LoginActivity` con **Firebase Authentication** (correo/contraseña, `firebase-auth:22.3.1`), sesión persistente (`SesionCliente`), **candado biométrico en cada entrada** (`BiometricPrompt` huella/rostro/PIN del dispositivo, `androidx.biometric:1.1.0`, permiso `USE_BIOMETRIC`, pantalla de bloqueo con Reintentar), botón Cerrar sesión con confirmación y README con el paso de Firebase Console (habilitar Correo/contraseña). Pendiente: filtrado del push por cliente. |
| 1.15 | 06/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-016** (corrección): build de verificación del flujo cliente fallaba porque `PromptInfo.Builder.setAuthenticators()` no existe en `androidx.biometric:1.1.0`; sustituido por `setDeviceCredentialAllowed(true)` en `MainActivity.lanzarPromptBiometrico()`. `assembleDebug` verificado: **BUILD SUCCESSFUL**, APK generado. |
| 1.16 | 06/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-017** (2ª parte del pivote a cliente): push filtrado por `id_cliente` de punta a punta — columna `ID_CLIENTE` + rol `CLIENTE` + índice en `DISPOSITIVO_MOVIL` (migración ejecutada en XEPDB1 sin perder tokens), `midff_fcm_enviar_a_dispositivos()` con filtro en `config/firebase.php`, `POST /api/v1/dispositivos` acepta `id_cliente`, y la app registra su token con el campo "ID de cliente" persistido en `SesionCliente`. Broadcast legado conservado cuando falta el dato. |
| 1.17 | 06/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-018**: ID de cliente auto-generado al crear la cuenta (determinista desde el uid de Firebase, D-50; visible en la pestaña Registro), `GET /api/v1/dispositivos` para el banco/administrador y panel "Clientes registrados" con botón "Usar" en `banco.html`. |
| 1.18 | 06/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-019**: consola del banco **`MIDFF.html`** — tabla `ADMIN_USUARIO` (bcrypt + clave temporal, D-52/D-53/D-54), API `/api/v1/admin/{sesion,clave,resumen,alertas,clientes}` con Bearer HMAC 8 h, y UI dark con KPIs, serie 24 h, bandeja global con Bloquear y clientes con dispositivos. Consultas validadas contra XEPDB1 real. |
| 1.19 | 06/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-020**: flujo completo de ambos lados — app limpia (sin Panel/Bloquear, registro automático, "Perfil") + **"No fui yo / Sí fui yo"** vía `POST /api/v1/reportar` (dueño verificado, 404/409, ALERTA+AUDITORIA) y columna "Reporte cliente" con bandera en `MIDFF.html`. E2E verificado y APK compilado. |
| 1.20 | 07/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-021** (corrección): limpieza de pruebas dejó `SEQ_AUDITORIA` en 1 con 103 filas inmutables → `ORA-00001 PK_AUDITORIA`; secuencia realineada a `MAX(id)+1` y verificada con transacción real. |
| 1.21 | 07/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-022**: transacciones sanas visibles en la bandeja de la app (filtro "Aprobadas") y en `MIDFF.html` (query `EVALUACION LEFT JOIN ALERTA`, badge SANA sin botón Bloquear); limpieza de pruebas con `SEQ_AUDITORIA` realineada; `banco.html` siempre apunta a un cliente registrado. |
| 1.22 | 09/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-023**: paleta monocroma negro/gris/plateado/humo con resaltes en blanco en app (colors/drawables/layouts/notificaciones), `MIDFF.html` y `banco.html`, sin cambiar estructura del diseño. |
| 1.23 | 09/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-024**: flujo por nivel de riesgo sincronizado app↔consola (BAJO auto, MEDIO confirmación del cliente con aprobación/aviso, ALTO bloqueada con "SÍ FUI YO" que habilita Desbloquear en `MIDFF.html` vía `POST /api/v1/desbloquear`), consola en vivo a 5 s y fix del `ConnectException` con descubrimiento previo al envío. |
| 1.24 | 09/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-025**: `servidor.ps1` de un solo comando (Oracle PATH + firewall persistente + `0.0.0.0:8091` + URLs de demo); la app se enlaza sola por WiFi y `adb reverse` deja de ser obligatorio. |
| 1.25 | 09/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-026** (corrección + blindaje): 3ª recurrencia del `ORA-00001 PK_AUDITORIA` por `SEQ_AUDITORIA` desalineada tras limpiezas de pruebas; script idempotente `database/realinear_secuencias.sql` (las 4 secuencias) + verificación automática al arrancar en `servidor.ps1` (paso 4/5); E2E verificado con transacción BAJO real. |
| 1.26 | 12/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-027**: caché de archivo del access_token FCM (alertas ~10× más rápidas de salir del servidor), push `ESTADO` al bloquear/desbloquear desde `MIDFF.html` y `GET /api/v1/estado` + `sincronizarEstados()` en la app para reflejar las decisiones del banco. E2E verificado y APK compilado. |
| 1.27 | 12/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-028** (UX): monto en lugar de score en las tarjetas, colores rojo/ámbar/verde por clasificación (tonos oscuros que combinan con la estética), textos amigables ("Riesgo alto", "En revisión"…) y nombre de usuario editable que reemplaza el correo en la cabecera. APK recompilado. |
| 1.28 | 12/10/2026 | Residente del proyecto | Se agrega la entrada **BIT-029** (limpieza para nueva ronda de pruebas): `database/limpiar_pruebas.sql` reutilizable (vacía TRANSACCION/EVALUACION/ALERTA, conserva AUDITORIA/ADMIN_USUARIO/DISPOSITIVO_MOVIL y realinea secuencias vía `@@realinear_secuencias.sql`); ejecutado 2 veces con verificación de inserción intermedia (BAJO `TRX-2026-B63E91CE` sin ORA-00001); basura del repo retirada (`replay_pid*.log`, `.srv*.log`, `datos/tmp`). |

---

**Fin del documento — Bitácora MIDFF v1.0**
