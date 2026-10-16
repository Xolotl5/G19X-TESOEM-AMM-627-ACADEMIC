# MIDFF — Documento de Arquitectura de Software

**Proyecto:** Motor Inteligente de Detección de Fraude Financiero (MIDFF)
**Versión:** 1.0 · 15/10/2026 · Residente del proyecto · PluriOne
**Referencias:** `PRD_MIDFF.md` · `MVP_MIDFF.md` · `BITACORA.md` (decisiones D-01…D-58) · `docs/Justificacion_Tecnologias.md`

---

## 1. Visión general

MIDFF evalúa transacciones financieras en tiempo real, calcula un score de riesgo
explicable (0.0–1.0), clasifica la operación (BAJO/MEDIO/ALTO), decide (APROBADA /
EN_REVISION / BLOQUEADA), persiste toda la evidencia en Oracle de forma atómica e
inmutable, y notifica al **cliente** y al **banco** por canales distintos (push FCM y
consola web), cerrando el ciclo con la respuesta del cliente ("Sí/No fui yo") y la
decisión del oficial (Bloquear/Desbloquear) — todo auditable.

```
                ┌───────────────────────────── 8091 ─────────────────────────────┐
                │                        API REST PHP 8 (router.php)             │
 banco.html ───►│  POST /api/v1/transacciones   (X-API-Key)                      │
 (simulador)    │    ├─ validación 400/401/405                                     │
                │    ├─ invocación motor C (stdin/stdout JSON)                    │
                │    │      ┌──────────────────────────┐                          │
                │    │      │ midff-scoring.exe (C)    │ score 0..1 + variables   │
                │    │      │ 0.45·monto+0.30·frec+    │ + detalle auditable     │
                │    │      │ 0.25·ubicación/IP        │ (mediana 7 µs)          │
                │    │      └──────────────────────────┘                          │
                │    ├─ SP_REGISTRAR_TRANSACCION (ACID: TRANSACCION+EVALUACION+  │
                │    │   ALERTA+AUDITORIA, trigger de inmutabilidad)              │
                │    ├─ motivo XAI (BIT-034): "Monto 12.9× tu habitual · ..."     │
                │    └─ push FCM HTTP v1/OAuth2 (best-effort, ≤ 2 s)              │
                └───────────────┬──────────────────────────────┬─────────────────┘
                                │ PDO_OCI                       │ data-only push
                        ┌───────▼────────┐            ┌────────▼─────────┐
                        │ Oracle XE 21c  │            │ Firebase Cloud   │
                        │ XEPDB1         │            │ Messaging + Auth │
                        │ (auditoría     │            └────────┬─────────┘
                        │  inmutable)    │                     ▼
                        └───────▲────────┘            ┌──────────────────┐
                                │                     │ App Android      │
                MIDFF.html ─────┤                     │ (Java, biometría)│
                (consola 5 s)   │                     │ Sí/No fui yo     │
                Bloquear/Desbloq.└── SP_BLOQUEAR / desbloquear.php ─────►┘
                                   (push de ESTADO regresa al cliente)
```

## 2. Componentes y responsabilidades

| # | Componente | Tecnología | Responsabilidad | Requisitos |
|---|---|---|---|---|
| 1 | **Motor de scoring** | C (`src/motor`) | Score con 3 variables normalizadas, umbrales 0.4/0.7, fail-safe neutro 0.5 sin contexto, salida JSON auditable | RF-03/04/05, RNF-01 |
| 2 | **API REST** | PHP 8 (`api/v1`, `router.php`) | 9 endpoints: transacciones, dispositivos, bloquear, desbloquear, reportar, estado, admin, baja, ping. Validación, orquestación y traducción de errores del motor | RF-01/02/06 |
| 3 | **Base de datos** | Oracle XE 21c (`database/`) | Esquema MIDFFuser: TRANSACCION, EVALUACION, ALERTA, AUDITORIA (inmutable por trigger), DISPOSITIVO_MOVIL, ADMIN_USUARIO; SPs atómicos; CHECK de coherencia score↔banda↔decisión | RF-07/08, RNF-04/05 |
| 4 | **Mensajería** | Firebase FCM HTTP v1 + OAuth2 (`config/firebase.php`) | Push data-only al dispositivo del cliente (alerta, sana y cambio de ESTADO); purga de tokens; verificación de uids | RF-09, RNF-07 |
| 5 | **App del cliente** | Android Java (`android/MidffAlertas`) | Firebase Auth + biometría, bandeja con motivo XAI, "Sí/No fui yo", guía interactiva, autodescubrimiento de la API por WiFi | RF-10/11 |
| 6 | **Consola del banco** | HTML5/JS (`MIDFF.html`) | KPIs, bandeja global, Bloquear/Desbloquear con doble confirmación, refresco 5 s | RF-13/14 (post-MVP) |
| 7 | **Simulador** | HTML5/JS (`banco.html`) | Banco origen: plantillas por banda de riesgo contra el endpoint de ingesta | Demo |
| 8 | **Automatización** | PowerShell | `build.ps1` (compila + 167 verificaciones), `smoke-test.ps1` (E2E), `servidor.ps1` (levantar todo en el puerto 8091) | RF-15 |

## 3. Contrato del motor (resumen ejecutivo)

* **Entrada** (stdin, JSON ≤ 8192 bytes): id_transaccion, id_cliente, monto, moneda,
  origen, ubicacion/ip_origen/canal opcionales, fecha_hora, contexto opcional
  (monto_promedio_historial, transacciones_ultima_hora, ubicacion_habitual, ip_habitual).
* **Salida** (stdout, JSON): score (4 dec.), clasificación, decisión, variables
  (monto: entrada/referencia/ratio/puntaje · frecuencia: entrada/rango/puntaje ·
  ubicacion_ip: puntaje/flags), datos_faltantes, latencia_evaluacion_us.
* **Umbrales congelados:** BAJO < 0.4 → APROBADA · MEDIO 0.4–0.7 → EN_REVISION ·
  ALTO > 0.7 → BLOQUEADA. Pesos 45/30/25. Contrato completo: `docs/contratos/contrato_scoring.md`.
* **XAI (BIT-034):** la API traduce `variables` a lenguaje llano para el cliente;
  el scoring no cambia.

## 4. Flujo por nivel de riesgo (decisión distribuida)

| Banda | Decisión automática | El cliente puede | El banco puede |
|---|---|---|---|
| BAJO (<0.4) | APROBADA, sin alerta | Verla en "Aprobadas" | Verla en historial (badge SANA) |
| MEDIO (0.4–0.7) | EN_REVISION + alerta preventiva | "Sí fui yo" (aprueba) / "No fui yo" (⚑ al banco) | Bloquear / Desbloquear |
| ALTO (>0.7) | **BLOQUEADA** + alerta de fraude | Solo "Sí fui yo" (solicita desbloqueo) | Desbloquear (doble confirmación → push de estado) |

## 5. Modelo de datos (Oracle)

```
TRANSACCION (id, uid_transaccion UK, id_cliente, monto, moneda, origen, canal,
             ubicacion, ip_origen, fecha_hora)
EVALUACION  (id, fk_transaccion, score, clasificacion, decision, puntaje_monto,
             puntaje_frecuencia, puntaje_ubicacion_ip, variables_usadas CLOB,
             latencia_us, datos_faltantes)          -- CK_EVALUACION_UMBRAL
ALERTA      (id, fk_evaluacion, tipo_alerta, estado PENDIENTE/ATENDIDA/BLOQUEADA,
             id_cliente, fecha_creacion)
AUDITORIA   (id, tabla, id_registro, accion, detalle CLOB, usuario_sistema,
             fecha)  -- trigger AUDITORIA_INMUTABLE: sin UPDATE/DELETE (RNF-04)
DISPOSITIVO_MOVIL (id, usuario, rol, id_cliente, uid_firebase, token_fcm UK, activo)
ADMIN_USUARIO (id, usuario UK, clave_hash, rol)
```

## 6. Seguridad y privacidad

* Autenticación de ingesta por `X-API-Key` (variable `MIDFF_API_KEY`).
* Autenticación del cliente: Firebase Auth + biometría del dispositivo en cada entrada.
* Credenciales **nunca** versionadas (`.gitignore`): cuenta de servicio FCM,
  `google-services.json`, `firebase/cuenta_admin.txt` (acceso a la consola).
* Seudonimización del cliente en la UI (`CLI-XXXXXX`); auditoría inmutable; baja de
  cuenta con verificación contra Firebase (BIT-031).

## 7. Decisiones de arquitectura clave

Extracto de las 58 decisiones trazadas en `BITACORA.md`:

| ID | Decisión |
|---|---|
| D-05 | Umbrales fijos 0.4/0.7 y push solo con score ≥ 0.4 |
| D-23 | FCM migrado a HTTP v1/OAuth2 (server keys legacy descontinuadas) |
| D-27 | Bandeja local por SharedPreferences serializada a JSON (Realtime DB diferida) |
| D-43 | Autodescubrimiento de la API (faro `/ping`, escaneo de subred /24) |
| D-46 | Push **data-only** (el bloque `notification` impedía procesar en 2º plano) |
| D-47 | Firebase Auth + biometría en la app del cliente |
| D-52 | Flujo por nivel de riesgo: quién decide depende de la banda |
| BIT-034 | XAI: motivo en lenguaje llano derivado de `variables` |

## 8. Despliegue y operación

* **Puerto oficial:** 8091 (`servidor.ps1` levanta `php -S 0.0.0.0:8091 router.php`,
  abre firewall y realinea secuencias).
* Guía de levantamiento paso a paso: `README.md` §0.
* Ruta crítica de latencia: mediana 7.0 µs (motor) + 308 ms promedio E2E (API).

## 9. Evolución (post-MVP)

Calibración de pesos con historia real (regresión logística que genere los
coeficientes), perfil de gasto por cliente desde Oracle, detección de anomalías no
supervisada como segunda opinión, y contenedores/CI-CD para despliegue industrial.

