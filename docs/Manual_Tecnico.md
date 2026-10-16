# MIDFF — Manual Técnico (instalación, operación y mantenimiento)

**Versión:** 1.0 · 15/10/2026 · Residente del proyecto

> Este manual profundiza la **Guía rápida** del `README.md` §0. Para levantar el
> sistema en 10 minutos, sigue aquella sección; aquí está el detalle de cada pieza.

## 1. Requisitos e ingredientes

| Pieza | Versión probada | Notas |
|---|---|---|
| Windows | 10/11 + PowerShell 5.1 | `servidor.ps1` y `build.ps1` |
| Oracle XE | 21c (`XEPDB1`, puerto 1521) | Esquema propietario `MIDFFuser` |
| PHP | 8.1+ (probado 8.1.10 Laragon) | `pdo_oci` u `oci8`, `curl`, `openssl`, `mbstring` |
| Compilador C | gcc / MinGW-w64 | Solo para recompilar el motor |
| Android Studio | + JDK 17 (JBR) | Solo para recompilar la app (`gradle assembleDebug --offline`) |

## 2. Instalación completa (resumen)

1. **Oracle:** ejecutar `database/MIDFFuser.sql` y luego el resto de scripts
   (`schema.sql`, `dispositivo_movil*.sql`, `admin_usuario.sql`,
   `sp_bloquear_alerta.sql`) como SYSTEM. Conexión por defecto de la API:
   `MIDFFuser/midffadmin@localhost:1521/XEPDB1` (variables `MIDFF_DB_*` para cambiar).
2. **Motor C:** `.\build.ps1` → compila `src/motor/bin/midff-scoring.exe` y ejecuta
   167 verificaciones. El ejecutable puede fijarse con `MIDFF_MOTOR_EXE`.
3. **Firebase:** cuenta de servicio en `config/firebase.credentials.json` (o variable
   `MIDFF_FCM_CUENTA_SERVICIO`); `google-services.json` en la app; para la consola de
   Firebase, credenciales en `firebase/cuenta_admin.txt`.
4. **API:** `.\servidor.ps1` → **puerto 8091** (`php -S 0.0.0.0:8091 router.php`),
   firewall automático (regla `MIDFF-API-8091`) y realineación de secuencias.
   Verificar: `GET http://localhost:8091/api/v1/ping`.
5. **App:** instalar `android/MidffAlertas/app/build/outputs/apk/debug/app-debug.apk`;
   se enlaza sola por WiFi (`DescubrimientoApi`); por USB `adb reverse tcp:8091 tcp:8091`.

## 3. Endpoints de la API (puerto 8091)

| Método y ruta | Auth | Función |
|---|---|---|
| `POST /api/v1/transacciones` | `X-API-Key` | Ingesta + score + decisión + persistencia + push (contrato Anexo A del MVP) |
| `POST /api/v1/dispositivos` | `X-API-Key` | Registro/UPSERT de token FCM por cliente |
| `POST /api/v1/bloquear` | `X-API-Key` | Bloqueo preventivo del oficial (SP_BLOQUEAR_ALERTA) |
| `POST /api/v1/desbloquear` | `X-API-Key` | Desbloqueo tras confirmación del cliente (push de ESTADO) |
| `POST /api/v1/reportar` | `X-API-Key` | "No fui yo" del cliente (409 si ya fue reportada) |
| `GET /api/v1/estado?id=` | `X-API-Key` | Estado de una alerta |
| `GET /api/v1/admin/alertas` | admin consola | Historial completo para `MIDFF.html` |
| `POST /api/v1/baja` | `X-API-Key` | Baja lógica de cuenta + purga verificada contra Firebase |
| `GET /api/v1/ping` | pública | Faro de descubrimiento (`{"servicio":"midff"}`) |

## 4. Operación diaria

* **Levantar:** `.\servidor.ps1` (deja consola con URLs; logs en `.srv.log`).
* **Probar:** `.\smoke-test.ps1` (flujo E2E por banda) o `.\build.ps1` (regresión motor).
* **Limpiar datos de demo:** `database/limpiar_pruebas.sql` + reiniciar secuencias
  (`realinear_secuencias.sql` lo hace `servidor.ps1` en cada arranque — lección BIT-021).
* **Consultas de control:** README §8 (conteos EVALUACION vs AUDITORIA, últimas alertas).

## 5. Diagnóstico rápido

| Síntoma | Causa típica | Acción |
|---|---|---|
| `ping` no responde | API caída / puerto ocupado | `netstat -ano | findstr 8091`; relanzar `servidor.ps1` |
| `ORA-12541/oci8` ausente | DLLs Oracle fuera de PATH | `servidor.ps1` las agrega; verificar `php -m` |
| `ORA-00001` en auditoría | Secuencias desalineadas | Ejecutar `realinear_secuencias.sql` |
| Push no llega | Sin cuenta de servicio / token inactivo | Ver `config/firebase.credentials.json` y `DISPOSITIVO_MOVIL.activo` |
| App no enlaza | Otro WiFi / firewall | Mismo WiFi, o `adb reverse tcp:8091 tcp:8091` |
| 401 en transacciones | API Key distinta | Variable `MIDFF_API_KEY` en servidor y cliente |

## 6. Mantenimiento

* **Backups:** export del esquema `MIDFFuser` (Data Pump) diario; la tabla
  `AUDITORIA` es inmutable y respalda la evidencia regulatoria.
* **Rotación de credenciales:** regenerar cuenta de servicio FCM y `google-services.json`
  por proyecto; `MIDFF_API_KEY` por entorno.
* **Actualización del motor:** cualquier cambio de pesos/umbrales exige reprocesar
  la suite (`build.ps1`) — el contrato `contrato_scoring.md` está congelado.
* **Métricas a vigilar en producción:** latencia E2E (objetivo ≤ 1 s), tasa de push
  fallidos (baja lógica de tokens), crecimiento de `AUDITORIA` (particionar por mes).
