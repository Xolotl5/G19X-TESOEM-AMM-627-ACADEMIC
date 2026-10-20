# MIDFF — Reporte de Pruebas de Calidad (QA)

**Proyecto:** Motor Inteligente de Detección de Fraude Financiero (MIDFF)
**Versión:** 1.0 · 15/10/2026 · Residente del proyecto
**Alcance:** validación funcional, de contrato, de rendimiento y de seguridad del MVP + post-MVP

---

## 1. Estrategia de pruebas

| Nivel | Herramienta | Qué valida | Evidencia |
|---|---|---|---|
| **Unitarias (motor C)** | `pruebas/test_scoring.c` + `build.ps1` | 3 variables, fronteras de umbral, JSON de contrato | 18 casos / **167 verificaciones / 0 fallos** |
| **Benchmark (motor C)** | `pruebas/test_latencia.c` | RNF-01: orden de microsegundos | mediana **7.0 µs**, p95 **10.8 µs** (ruta completa) |
| **Integración/E2E** | `smoke-test.ps1` | API → motor → Oracle → FCM | 3 transacciones por banda, 400/401 controlados |
| **E2E manual (demo)** | `banco.html` + app + consola | Flujo completo con push real | BIT-024/027/032: doble confirmación, desbloqueo, 409/404 |
| **Estático Android** | Gradle `assembleDebug` | Compilación Java + recursos | **BUILD SUCCESSFUL** (34 tareas, BIT-033/034) |
| **Lint PHP** | `php -l` | Sintaxis de endpoints tocados | 0 errores (transacciones, firebase) |

## 2. Resultados contra el Definition of Done (MVP_MIDFF.md §9)

| # | Criterio DoD | Resultado | Evidencia |
|---|---|---|---|
| 1 | Flujo completo sin intervención manual | ✅ | Demo E2E BIT-024 (bloqueo→desbloqueo aud. 138) |
| 2 | Motor C compila, pasa pruebas y reporta µs | ✅ | BIT-002: 18 casos, 167 verif., mediana 7.0 µs |
| 3 | API ≤ 1 s; 400 con JSON inválido; API Key | ✅ | BIT-003: 308.2 ms promedio; 400/401 en smoke |
| 4 | Persistencia atómica; auditoría al 100 % | ✅ | SP_REGISTRAR_TRANSACCION + trigger inmutable |
| 5 | Push < 2 s con score ≥ 0.4; sin push < 0.4 | ✅ | BIT-009/014 (HTTP v1, data-only) |
| 6 | Bloqueo persistente y auditable | ✅ | BIT-010 (SP_BLOQUEAR_ALERTA) |
| 7 | Casos por banda 100 % correctos | ✅ | Suite bajo/medio/alto del smoke test |
| 8 | Automatización sin pasos manuales | ✅ | `build.ps1` + `smoke-test.ps1` + `servidor.ps1` |
| 9 | README + manual de demo | ✅ | README §0–§10, `docs/Manual_Usuario.md` |
| 10 | Demo final ante revisor | ✅ | Guión de 2 min (README §0 paso 7) |

## 3. Métricas de éxito medidas (MVP_MIDFF.md §10)

| Métrica | Objetivo | Medido | Estado |
|---|---|---|---|
| Latencia motor C | µs | mediana 7.0 µs / p95 10.8 µs | ✅ |
| Latencia E2E API | ≤ 1 s | 308.2 ms promedio | ✅ |
| Entrega de push | < 2 s | envío HTTP v1 validado (BIT-009) | ✅ |
| Cobertura de auditoría | 100 % | 100 % (SP atómico) | ✅ |
| Casos por banda | 100 % | 100 % | ✅ |
| Bloqueos trazables | 100 % | 100 % (usuario + timestamp) | ✅ |

## 4. Pruebas funcionales por banda de riesgo

| Escenario | Entrada | Esperado | Observado |
|---|---|---|---|
| Sana | monto ≈ promedio, sin cambios | BAJO/APROBADA, sin alerta, push informativo | ✅ `TRX-2026-C8808AD2` |
| Preventiva | ratio ≥ 1.5 o ubicación distinta | MEDIO/EN_REVISION + alerta | ✅ ALR visibles en ambas UI |
| Fraude | monto 12.9× + IP/ubicación distinta | ALTO/BLOQUEADA + push FRAUDE | ✅ aud. 138 (BIT-024) |
| Sin contexto (fail-safe) | transacción sin `contexto` | 0.5000 MEDIO/EN_REVISION + "Sin historial…" | ✅ (contrato motor) |
| Doble reporte | 2× "No fui yo" | 409 "ya fue reportada" sin error en UI | ✅ BIT-032 |
| Reporte sin alerta | sana → reportar | botones ocultos (sin 404) | ✅ BIT-032 |
| Desbloqueo banco | ALTO bloqueada → "Sí fui yo" + consola | DESBLOQUEADA + push ESTADO + ATENDIDA | ✅ BIT-024 |
| XAI (BIT-034) | riesgo alto | motivo "Monto 12.9× tu habitual · Desde una ubicación distinta" en push/tarjeta/detalle | ✅ build + lint OK |

## 5. Pruebas de seguridad

| Prueba | Resultado |
|---|---|
| `X-API-Key` ausente/incorrecta en `/transacciones` | HTTP 401 (smoke) |
| JSON inválido / campos fuera de dominio | HTTP 400 con diagnóstico |
| Método distinto de POST | HTTP 405 |
| Payload > 8192 bytes | Rechazado por el motor (código 2 → 400) |
| Auditoría mutable (UPDATE/DELETE) | Bloqueada por trigger (ORA) |
| Tokens FCM inválidos | Baja lógica automática (activo=0) |
| Credenciales en Git | Ninguna versionada (.gitignore auditado) |

### 5.1 Autorización por identidad (BIT-037, Corte 2)

Casos exigidos por el diagnóstico del Corte 2. Los automatizados corren en
`smoke-test.ps1` (sección «Pruebas de autorización»); el 403 cruzado requiere
dos cuentas reales de Firebase y se ejecuta manualmente con la app o `curl`.

**CP-A1 — Otro usuario pide alertas ajenas → 403 (manual, 2 cuentas).**

1. En la app (o con `curl`), inicie sesión como el cliente **B** y obtenga su
   ID token (la app lo envía sola; con Firebase REST sería
   `identitytoolkit.googleapis.com/v1/accounts:signInWithPassword`).
2. Pida las alertas del cliente **A** con el token de B:

   ```powershell
   curl.exe "http://127.0.0.1:8091/api/v1/estado?id_cliente=<ID_DE_A>" `
        -H "Authorization: Bearer <ID_TOKEN_DE_B>"
   ```

3. **Esperado:** `HTTP 403` — `{"error":"el id_cliente solicitado no corresponde al usuario autenticado"}`.
   El `id_cliente` se deriva del token (BIT-036); el parámetro solo se tolera si coincide.

**CP-A2 — Primer registro con `id_cliente` ajeno → 403 (manual).** Una cuenta
nueva de Firebase que intente `POST /api/v1/dispositivos` con el `id_cliente`
de otro cliente recibe `403 el id_cliente ya esta vinculado a otra cuenta de
Firebase` (la validación consulta `DISPOSITIVO_MOVIL` por uid distinto).

**CP-A3 — Registro con rol elevado → 403 (manual).** `POST /api/v1/dispositivos`
con `"rol":"ADMIN"` responde `403 la app solo puede registrar dispositivos con
rol CLIENTE` (el rol del cuerpo ya no se acepta).

**CP-A4 — Acciones del banco sin sesión de administrador → 401 (automatizado).**
`POST /bloquear`, `POST /desbloquear` y `GET /dispositivos` con solo la
`X-API-Key` responden **401**: exigen el Bearer HMAC de `/api/v1/admin/sesion`
y el responsable sale del token, no del cuerpo.

**CP-A5 — Falta una variable de entorno → 500 (automatizado).** El smoke test
levanta una segunda instancia de la API con `MIDFF_*` retiradas del entorno:
cualquier petición a `/transacciones` responde **500** con
`"configuracion incompleta del servidor: falta la variable ..."` y la API se
niega a atender (antes `GET /estado` con solo la llave de ingesta también
responde **401**, CP del punto a del smoke).

| Caso | Entrada | Esperado | Dónde |
|---|---|---|---|
| CP-A1 | token de B + `id_cliente` de A | 403 | manual (arriba) |
| CP-A2 | registro 1ª vez con id ajeno | 403 | manual (arriba) |
| CP-A3 | `"rol":"ADMIN"` en el registro | 403 | manual (arriba) |
| CP-A4 | `X-API-Key` sola en bloquear/desbloquear/GET dispositivos | 401 | `smoke-test.ps1` §6b |
| CP-A5 | servidor sin `MIDFF_*` en el entorno | 500 «falta la variable» | `smoke-test.ps1` §6b |

## 6. Defectos encontrados y resueltos (extracto)

| BIT | Defecto | Solución |
|---|---|---|
| BIT-013 | Ternario String/int no compila (JDK 21) | `getString()` en ambas ramas |
| BIT-014 | Push no procesado en 2º plano | Migración a payload data-only |
| BIT-021 | `ORA-00001` en auditoría tras limpieza | `realinear_secuencias.sql` (ahora automático) |
| BIT-026 | `ConnectException` al reportar | Envío tras `DescubrimientoApi.asegurar` |

## 7. Conclusión

El **100 % de los criterios de aceptación del MVP** se cumple con evidencia trazable
(commits + bitácora), las métricas de rendimiento superan los objetivos por amplio
margen (motor ~142 000× por debajo del segundo) y la suite automatizada permite
regresionar en un solo comando. **Calidad: APROBADO.**
