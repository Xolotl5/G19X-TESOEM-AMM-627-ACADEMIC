# Motor Inteligente de Detección de Fraude Financiero (MIDFF)

---

### Información General
* **Nombre del Sistema:** Motor Inteligente de Detección de Fraude Financiero
* **Siglas:** MIDFF
* **Tipo de Proyecto:** Residencias Profesionales – Ingeniería en Sistemas Computacionales
* **Formato:** Markdown (`README.md`)
* **Estado:** MVP terminado (Sprint 4 · 28/09/2026)
* **Licencia:** Privada / Académica

El **MIDFF** evalúa transacciones financieras en tiempo real, calcula un score de riesgo (0.0–1.0), clasifica la operación (BAJO/MEDIO/ALTO), persiste toda la evidencia en Oracle XE, notifica al oficial mediante push (Firebase FCM) y permite **bloquear preventivamente** la alerta desde una app Android, con auditoría completa de cada decisión.

**Flujo end-to-end del MVP:**

```
Transaccion (HTTP/JSON) -> API PHP -> motor C (score) -> Oracle (atomico + auditoria)
      -> push FCM (< 2 s) -> app Android (bandeja) -> "Bloquear" -> Oracle + auditoria
```

---

## Índice
1. [Descripción General y Delimitación](#1-descripción-general-y-delimitación)
2. [Arquitectura Tecnológica](#2-arquitectura-tecnológica)
3. [Estructura del Repositorio](#3-estructura-del-repositorio)
4. [Requisitos del Entorno](#4-requisitos-del-entorno)
5. [Instalación Paso a Paso y Credenciales](#5-instalación-paso-a-paso-y-credenciales)
6. [Compilación y Pruebas Automatizadas](#6-compilación-y-pruebas-automatizadas)
7. [Ejecución de la Demo End-to-End](#7-ejecución-de-la-demo-end-to-end)
8. [Verificación en Oracle (consultas de control)](#8-verificación-en-oracle-consultas-de-control)
9. [Solución de Problemas](#9-solución-de-problemas)
10. [Registro de Cambios y Bitácora](#10-registro-de-cambios-y-bitácora)
11. [Créditos y Información del Desarrollador](#11-créditos-y-información-del-desarrollador)

---

## 1. Descripción General y Delimitación

El motor analiza cada transacción con un modelo de reglas ponderado y explicable (`score = 0.45·monto + 0.30·frecuencia + 0.25·ubicación/IP`), con fail-safe neutro cuando falta el historial del cliente.

### Características Principales del MVP
* **Ingesta de Datos:** `POST /api/v1/transacciones` (HTTP/JSON + `X-API-Key`).
* **Evaluación de Riesgo:** motor en C con latencia de microsegundos e invocación por línea de comandos.
* **Alertamiento Temprano:** push FCM (protocolo HTTP v1/OAuth2) cuando `score >= 0.4`.
* **Bloqueo Preventivo:** botón "Bloquear" en la app → `POST /api/v1/bloquear` → `ALERTA.estado = BLOQUEADA` + `AUDITORIA`.
* **Auditoría:** toda evaluación y decisión queda registrada de forma atómica e inmutable en Oracle XE.

> Delimitación: el dashboard web (RF-13), el historial consultable (RF-14) y Firebase Realtime Database (RF-12) quedan para la versión 1.1 (post-MVP). El alcance completo está en `MVP_MIDFF.md`.

---

## 2. Arquitectura Tecnológica

| Capa del Sistema | Tecnología | Función Principal |
| :--- | :--- | :--- |
| **Motor Analítico** | **C** (gcc/MinGW) | Scoring de las 3 variables, umbrales y detalle auditable; latencia en microsegundos. |
| **Backend / API REST** | **PHP 8.1+** | `POST /api/v1/transacciones`, `/dispositivos` y `/bloquear`; orquesta motor C, Oracle y FCM. |
| **Base de Datos** | **Oracle Database XE 21c** | Persistencia ACID (`SP_REGISTRAR_TRANSACCION`, `SP_BLOQUEAR_ALERTA`) y auditoría inmutable. |
| **Cliente Móvil** | **Android (Java)** | Bandeja de alertas, detalle y acción "Bloquear" (HU-MVP-06). |
| **Mensajería Tiempo Real** | **Firebase FCM (HTTP v1/OAuth2)** | Push con monto, score y clasificación cuando `score >= 0.4`. |
| **Automatización** | **PowerShell (`.ps1`)** | `build.ps1` (compilación y pruebas del motor) y `smoke-test.ps1` (flujo API→C→Oracle). |
| **Panel Administrativo** | HTML5/CSS3/JS | *Post-MVP v1.1* (RF-13); fuera del alcance de este repositorio. |

---

## 3. Estructura del Repositorio

```
C:\todoMIDFF
├── src\motor\                 # Motor C: scoring.c, json_io.c, cronometro.c + pruebas
├── api\v1\
│   ├── transacciones.php      # POST /api/v1/transacciones (Sprint 2 + push 7b Sprint 3)
│   ├── dispositivos.php       # POST /api/v1/dispositivos (UPSERT token FCM, Sprint 3)
│   └── bloquear.php           # POST /api/v1/bloquear (HU-MVP-06, Sprint 4)
├── config\
│   ├── db.php                 # Conexión Oracle (PDO_OCI/OCI8) + SPs
│   ├── firebase.php           # Envío FCM HTTP v1 (OAuth2, best-effort)
│   └── firebase.credentials.json  # CUENTA DE SERVICIO (no se versiona)
├── database\
│   ├── schema.sql             # DDL Sprint 1 (TRANSACCION, EVALUACION, ALERTA, AUDITORIA)
│   ├── dispositivo_movil.sql  # DDL Sprint 3 (DISPOSITIVO_MOVIL, re-ejecutable)
│   └── sp_bloquear_alerta.sql # SP Sprint 4 (bloqueo + auditoría atómicos)
├── android\MidffAlertas\      # App Android (Java, applicationId Midff.PluriOne1)
├── firebase\                  # README-fcm.md + plantillas de payload
├── datos\datasets\            # Dataset sintético (3 bandas de riesgo)
├── docs\contratos\            # contrato_scoring.md
├── build.ps1                  # Compila el motor C y corre sus 167 verificaciones
├── smoke-test.ps1             # Flujo API -> motor C -> Oracle + métricas
├── MIDFF.html                 # Consola del banco (login admin, KPIs, alertas, clientes)
├── banco.html                 # Simulador de transacciones del banco
├── BITACORA.md                # Memoria auditable del proyecto (BIT-001..BIT-019)
├── PRD_MIDFF.md / MVP_MIDFF.md# Requisitos y alcance del MVP
└── router.php                 # Router del servidor embebido php -S
```

---

## 4. Requisitos del Entorno

| Componente | Versión probada | Notas |
| :--- | :--- | :--- |
| Windows | 10/11 | PowerShell 5.1 incluido. |
| gcc (MinGW) | 6.3.0 | Compilación del motor C (`build.ps1` lo detecta). |
| PHP | 8.1.10 (Laragon, TS) | Extensiones: `pdo_oci` **o** `oci8`, `openssl`, `curl`, `mbstring`. |
| Oracle Database XE | 21c (PDB `XEPDB1`) | Con SQL*Plus disponible en el `PATH`. |
| Android Studio | JBR 21 · Gradle 8.9 · AGP 8.5.2 | `compileSdk 34`, `minSdk 24`, Java. |
| Firebase | proyecto `midff-57e6b` | App Android `Midff.PluriOne1` + cuenta de servicio. |

---

## 5. Instalación Paso a Paso y Credenciales

### 5.1 Oracle Database XE (esquema MIDFF)

1. Instale **Oracle XE 21c** y verifique que el servicio esté activo y la PDB `XEPDB1` accesible.
2. Cree el usuario de aplicación (solo la primera vez), por ejemplo desde SQL*Plus como SYS:

   ```
   sqlplus sys/ClaveSys@localhost:1521/XEPDB1 AS SYSDBA
   CREATE USER MIDFFuser IDENTIFIED BY midffadmin QUOTA UNLIMITED ON USERS;
   GRANT CREATE SESSION, CREATE TABLE, CREATE SEQUENCE, CREATE TRIGGER,
         CREATE PROCEDURE, CREATE TYPE TO MIDFFuser;
   ```

3. Instale los tres scripts DDL (todos **re-ejecutables**), desde la raíz del repo:

   ```
   sqlplus MIDFFuser/midffadmin@localhost:1521/XEPDB1 @database\schema.sql
   sqlplus MIDFFuser/midffadmin@localhost:1521/XEPDB1 @database\dispositivo_movil.sql
   sqlplus MIDFFuser/midffadmin@localhost:1521/XEPDB1 @database\sp_bloquear_alerta.sql
   ```

   Debe ver los PROMPT de éxito: *13 objetos del esquema*, *Tabla DISPOSITIVO_MOVIL instalada* y *SP_BLOQUEAR_ALERTA creado*.

### 5.2 API REST en PHP (credenciales de Oracle)

Las credenciales viven en **variables de entorno** (RNF-03: nunca en el código ni en Git); si no se definen, `config/db.php` usa los placeholders de desarrollo:

| Variable de entorno | Default (desarrollo) | Uso |
| :--- | :--- | :--- |
| `MIDFF_DB_USUARIO` | `MIDFFuser` | Usuario de Oracle. |
| `MIDFF_DB_CLAVE` | `midffadmin` | Contraseña de Oracle. |
| `MIDFF_DB_HOST` / `MIDFF_DB_PUERTO` / `MIDFF_DB_SERVICIO` | `localhost` / `1521` / `XEPDB1` | Conexión EZConnect. |
| `MIDFF_API_KEY` | `MIDFF-API-KEY-2026-SPRINT2` | `X-API-Key` de los 3 endpoints. |
| `MIDFF_FCM_CUENTA_SERVICIO` | `config\firebase.credentials.json` | Ruta del JSON de cuenta de servicio. |

PHP necesita las DLLs del cliente Oracle en el `PATH` (`smoke-test.ps1` lo hace solo) y las extensiones `pdo_oci` (o `oci8`), `openssl`, `curl` y `mbstring` habilitadas en `php.ini`.

### 5.3 Firebase (FCM HTTP v1 / OAuth2)

1. En la consola de Firebase use el proyecto **`midff-57e6b`** (el mismo del `google-services.json` y de la cuenta de servicio: la coherencia de proyectos es obligatoria).
2. Registre la app Android con paquete **`Midff.PluriOne1`** y descargue `google-services.json` a `android\MidffAlertas\app\` (existe la plantilla `google-services.json.example`).
3. Descargue la **cuenta de servicio** (Configuración del proyecto → Cuentas de servicio → Generar clave) y guárdela como:

   ```
   C:\todoMIDFF\config\firebase.credentials.json
   ```

   Ese archivo **nunca se sube a Git** (`.gitignore` lo excluye con `**/firebase.credentials.json`). Con él presente, la API firma un JWT RS256, obtiene un access token OAuth2 de Google y envía el push por `https://fcm.googleapis.com/v1/projects/midff-57e6b/messages:send`.

### 5.4 App Android (MidffAlertas)

1. Abra `android\MidffAlertas` en Android Studio (Gradle 8.9 + AGP 8.5.2; use *File → Sync Project with Gradle Files*).
2. Compile el APK (*Build → Build Bundle(s)/APK(s) → Build APK(s)*) e instálelo en un dispositivo con Google Play services.
3. Conecte el teléfono por USB y redirija el puerto de la API:

   ```
   adb reverse tcp:8091 tcp:8091
   ```

   (En emulador cambie `ApiClient.baseUrl` a `http://10.0.2.2:8091`.)
4. En la app escriba el usuario del oficial (p. ej. `OFICIAL-01`) y pulse **Registrar token**: el token FCM queda en `DISPOSITIVO_MOVIL` (Oracle) con `UPSERT`.

### 5.5 Levantar la API

Desde la raíz del repositorio:

```
php -S 127.0.0.1:8091 router.php
```

La raíz `http://127.0.0.1:8091/` responde el JSON informativo v1.2 con los tres endpoints.

---

## 6. Compilación y Pruebas Automatizadas

Desde `C:\todoMIDFF` en PowerShell:

```
.\build.ps1        # Compila el motor C (-O2) y ejecuta:
                   #   - 18 casos / 167 verificaciones unitarias (0 fallos)
                   #   - benchmark de latencia (mediana ~7 us en la ruta completa)
                   #   - validacion del contrato JSON por linea de comandos (6 casos)

.\smoke-test.ps1   # Flujo API -> motor C -> Oracle XE:
                   #   - autodetecta php.exe (PATH o Laragon) y sqlplus
                   #   - levanta php -S con router.php
                   #   - evalua las 3 bandas del dataset (BAJO/MEDIO/ALTO)
                   #   - pruebas negativas 401/400, latencia <= 1 s y
                   #     cobertura de auditoria 100 %
```

### 3.1 Consola del banco (MIDFF.html, BIT-019)

Panel web del administrador (abrir `http://127.0.0.1:8091/MIDFF.html` con el
servidor PHP arriba). Requisito una sola vez:

```powershell
sqlplus MIDFFuser/midffadmin@localhost:1521/XEPDB1 @database/admin_usuario.sql
```

- **Acceso semilla**: `admin` / `MIDFFadmin2026` (temporal: la propia consola
  exige cambiarla al primer inicio y queda guardada como bcrypt en Oracle).
- **Funciones**: KPIs del día + gráfica por hora (24 h) + top clientes por
  riesgo, bandeja global de alertas con filtros (cliente/banda/estado) y
  **Bloquear** (reusa `POST /api/v1/bloquear` + `SP_BLOQUEAR_ALERTA` +
  auditoría), y clientes con sus dispositivos registrados.
- **API propia**: `/api/v1/admin/{sesion,clave,resumen,alertas,clientes}`
  con token Bearer HMAC-SHA256 de 8 h (`MIDFF_ADMIN_SECRETO` en entorno para
  producción; HTTPS obligatorio fuera de desarrollo).
- `banco.html` sigue siendo el **simulador** de transacciones; `MIDFF.html`
  es la consola de administración.

Parámetros útiles del smoke test: `-RutaPhp <ruta a php.exe>` si PHP no está en el `PATH`, `-SinOracle` para omitir la verificación en base de datos. Resultado esperado: **`RESULTADO: SMOKE TEST OK`** con código de salida 0.

---

## 7. Ejecución de la Demo End-to-End

Guion de ~5 minutos (cumple el criterio 10 del DoD). Requiere: Oracle XE activo con los 3 scripts instalados, la API corriendo (`php -S 127.0.0.1:8091 router.php`), `config\firebase.credentials.json` presente y la app instalada con su token registrado.

1. **(Opcional) Limpieza:** re-ejecute `database\schema.sql` + `database\dispositivo_movil.sql` + `database\sp_bloquear_alerta.sql` y vuelva a registrar el token en la app.

2. **Transacción BAJO (sin alerta):**

   ```
   curl -X POST http://127.0.0.1:8091/api/v1/transacciones -H "X-API-Key: MIDFF-API-KEY-2026-SPRINT2" -H "Content-Type: application/json" -d "{\"id_cliente\":\"CLI-001\",\"monto\":850.00,\"contexto\":{\"monto_promedio_historial\":1200.00,\"transacciones_ultima_hora\":1,\"ubicacion_habitual\":\"GDL\",\"ip_habitual\":\"189.203.4.7\"}}"
   ```

   → `score 0.0 · BAJO · APROBADA`, **sin** `alerta_id` y **sin** push.

3. **Transacción ALTO (alerta de fraude):** reuse el ejemplo de `explicacion.txt` §11 (cliente `CLI-003`, monto 15499, 7 operaciones/hora, ubicación e IP distintas) → `score 0.95 · ALTO · BLOQUEADA` con `alerta_id ALR-2026-XXXXXX`.

4. **Push en el teléfono:** la notificación llega en < 2 s con monto, score y clasificación (servidor → Google autenticado por OAuth2). Tóquela para abrir el detalle.

5. **Acción "Bloquear" (HU-MVP-06):** pulse el botón de la pantalla de detalle. La app envía:

   ```
   POST /api/v1/bloquear  { "alerta_id": "ALR-2026-XXXXXX", "usuario": "OFICIAL-01" }
   ```

   Respuesta esperada:

   ```json
   { "ok": true, "estado": "BLOQUEADA", "atendida_por": "OFICIAL-01",
     "fecha_atencion": "2026-09-28T...", "id_auditoria": 11,
     "mensaje": "alerta bloqueada y registrada en AUDITORIA (HU-MVP-06)" }
   ```

6. **Confirmación en la app:** el botón queda "Alerta ya bloqueada" y la bandeja marca la alerta como BLOQUEADA.

7. **Errores controlados (opcional):** repetir el bloqueo → `HTTP 409`; usar un `alerta_id` inexistente → `HTTP 404`; omitir `X-API-Key` → `HTTP 401`.

8. **Verificación en Oracle:** corra las consultas de la sección 8 y muestre la trazabilidad completa (alerta bloqueada + registro BLOQUEO con usuario y timestamp).

---

## 8. Verificación en Oracle (consultas de control)

Conéctese con `sqlplus MIDFFuser/midffadmin@localhost:1521/XEPDB1`:

```sql
-- a) Bloqueos preventivos trazables (metrica 6 del MVP: 100 % con usuario y timestamp)
SELECT a.id_alerta, a.estado, a.atendida_por, a.respuesta, a.fecha_atencion
  FROM ALERTA a WHERE a.estado = 'BLOQUEADA';

-- b) Registro de auditoria del bloqueo (entidad ALERTA, accion BLOQUEO)
SELECT id_auditoria, usuario_o_sistema, fecha_hora, SUBSTR(detalle, 1, 80) AS detalle
  FROM AUDITORIA WHERE accion = 'BLOQUEO';

-- c) Cobertura de auditoria (debe devolver 0: ninguna evaluacion sin registro)
SELECT COUNT(*) AS evaluaciones_sin_auditoria
  FROM EVALUACION e
 WHERE NOT EXISTS (SELECT 1 FROM AUDITORIA u
                    WHERE u.entidad = 'EVALUACION'
                      AND u.detalle LIKE '%id_evaluacion=' || e.id_evaluacion || '%');

-- d) Clasificacion por banda (las 3 bandas del dataset con su decision esperada)
SELECT clasificacion, decision, COUNT(*) AS total
  FROM EVALUACION GROUP BY clasificacion, decision ORDER BY clasificacion;

-- e) Dispositivos registrados para el push
SELECT id_dispositivo, usuario, rol, activo FROM DISPOSITIVO_MOVIL;
```

---

## 9. Solución de Problemas

| Síntoma | Causa probable | Solución |
| :--- | :--- | :--- |
| `php` no se reconoce en PowerShell. | PHP fuera del `PATH`. | Use Laragon o pase `-RutaPhp C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe` al smoke test. |
| `ORA-12154` / no conecta a Oracle. | DLLs del cliente Oracle fuera del `PATH` o servicio caído. | Inicie el servicio OracleXTSID y deje que `smoke-test.ps1` agregue el `bin` de Oracle al `PATH`. |
| `HTTP 500 ... SP_BLOQUEAR_ALERTA: identificador debe declararse`. | Falta instalar el SP del Sprint 4. | `sqlplus ... @database\sp_bloquear_alerta.sql`. |
| El push no llega al teléfono. | `google-services.json` de otro proyecto o falta `config\firebase.credentials.json`. | Ambos archivos deben salir del **mismo** proyecto `midff-57e6b` (ver `firebase\README-fcm.md`). |
| La app no reacha la API. | Puerto sin redirigir. | `adb reverse tcp:8091 tcp:8091` (o `10.0.2.2` en emulador). |
| Gradle no sincroniza en Android Studio. | JDK 21 con Gradle viejo. | Wrapper 8.9 + AGP 8.5.2 ya configurados; haga *Sync* y borre `%USERPROFILE%\.gradle\caches\8.0`. |
| Error de parse de XML al compilar la app. | Comentario XML con `--`. | Nunca use `--` dentro de comentarios XML (D-32); use `=`. |

---

## 10. Registro de Cambios y Bitácora

| Sprint | Commit | Entrega |
| :--- | :--- | :--- |
| Sprint 1 (23/09/2026) | `18430ae` | Motor C + esquema Oracle + `build.ps1` + dataset (BIT-001/BIT-002). |
| Sprint 2 (24/09/2026) | `cbac5a3` | API `POST /api/v1/transacciones` + persistencia atómica + `smoke-test.ps1` (BIT-003). |
| Sprint 3 (24–28/09/2026) | pendiente | FCM HTTP v1 + `DISPOSITIVO_MOVIL` + `POST /api/v1/dispositivos` + app Android (BIT-004 a BIT-009). |
| Sprint 4 (28/09/2026) | pendiente | `POST /api/v1/bloquear` + `SP_BLOQUEAR_ALERTA` + botón "Bloquear" + README y cierre del MVP (BIT-010). |

La memoria completa y auditable del proyecto (decisiones D-01 a D-37, métricas, riesgos y evidencias) está en **`BITACORA.md`**.

---

## 11. Créditos y Información del Desarrollador

* **Desarrollador / Responsable:** Residente del proyecto (Residencias Profesionales, Ingeniería en Sistemas Computacionales).
* **Documentos base:** `PRD_MIDFF.md` (requisitos) y `MVP_MIDFF.md` (alcance, DoD y métricas).
* **Metodología:** Scrum — 4 sprints de 2 semanas; bitácora con entradas `BIT-001` a `BIT-010`.



