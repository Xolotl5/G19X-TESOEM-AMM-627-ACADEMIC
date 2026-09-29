# Firebase Cloud Messaging — MIDFF (Sprint 3)

**MIDFF — Motor Inteligente de Detección de Fraude Financiero**
Componente 5 del MVP: mensajería en tiempo real (`MVP_MIDFF.md` §6 · RF-09).

| Campo | Valor |
|---|---|
| Requisitos | RF-09 (push solo con `score >= 0.4`), RNF-07 (envío único, sin reintentos) |
| Protocolo | HTTP legacy de FCM (`Authorization: key=<FCM_SERVER_KEY>`) — decisión D-23 |
| Credenciales | `android/MidffAlertas/app/google-services.json` (app) · `MIDFF_FCM_SERVER_KEY` (servidor) |
| Código servidor | `config/firebase.php` · gancho en `api/v1/transacciones.php` (paso 7b) |
| Registro de tokens | `POST /api/v1/dispositivos` → tabla `DISPOSITIVO_MOVIL` (`database/dispositivo_movil.sql`) |

## 1. Arquitectura del flujo de notificación

```
Transacción (POST /api/v1/transacciones)
        │  score >= 0.4 (umbral D-05) ──► ALERTA creada en Oracle (SP Sprint 1)
        ▼
api/v1/transacciones.php  ── midff_fcm_notificar_alerta() ──►  config/firebase.php
        │                        │                                     │
        │                        │                             DISPOSITIVO_MOVIL (Oracle)
        │                        │                             tokens con activo = 1
        │                        ▼                                     │
        │              payload legacy (notification + data)            │
        │                        └──────────► HTTPS ──► fcm.googleapis.com/fcm/send
        │                                                            │
        ▼                                                            ▼
respuesta sincrónica (Anexo A, sin cambios)              push al dispositivo Android
                                                                 │
                                                                 ▼
                                         NotificacionFcmService.onMessageReceived()
                                           ├─ guarda la alerta en la bandeja local
                                           └─ notificación local (monto, score, clasificación)
```

El envío es **best-effort** dentro de la propia petición HTTP (timeout 5 s):
un fallo de FCM **nunca** altera la respuesta del contrato ni la transacción
ya confirmada en Oracle; queda registrado en `error_log` con el prefijo
`[MIDFF-FCM]` y con el token enmascarado.

## 2. Configuración de la server key (constante FCM_SERVER_KEY)

`config/firebase.php` define la constante pedida por el alcance del Sprint 3:

```php
define('MIDFF_FCM_SERVER_KEY', getenv('MIDFF_FCM_SERVER_KEY')
    ?: 'COLOCAR-AQUI-LA-FCM_SERVER_KEY-DEL-PROYECTO');
```

Para habilitar el envío real:

1. Consola Firebase → *Configuración del proyecto* → *Cloud Messaging* →
   **Credenciales del proyecto** → copiar la *Server key*.
2. Colocarla en la constante o, preferible en producción, exportar la variable
   de entorno `MIDFF_FCM_SERVER_KEY` (RNF-03: credenciales fuera del código).

> **Importante (coherencia de proyectos):** la credencial de mensajería debe
> pertenecer al **mismo proyecto Firebase** en el que se registró la app
> `Midff.PluriOne1` (paso a paso en `android/MidffAlertas/README.md`): el
> `google-services.json` descargado y la server key deben salir del mismo
> proyecto. La cuenta de servicio
> `midff-57e6b-firebase-adminsdk-fbsvc-bf7960c9b6.json` (raíz del repositorio)
> queda registrada en `MIDFF_FCM_CUENTA_SERVICIO` como insumo para migrar al
> protocolo HTTP v1 (OAuth2), primer candidato del backlog post-MVP cuando
> Google retire las *server keys* legacy (los proyectos nuevos ya pueden no
> emitirlas: en ese caso la migración a HTTP v1 pasa a ser obligatoria).

## 3. Registro del token del dispositivo (DISPOSITIVO_MOVIL)

La app obtiene el token (`onNewToken` / `FirebaseMessaging.getToken`) y lo
registra contra la API (autoregistro del servicio o botón *Registrar token*):

```http
POST /api/v1/dispositivos
X-API-Key: MIDFF-API-KEY-2026-SPRINT2
Content-Type: application/json

{ "usuario": "OFICIAL-01", "rol": "OFICIAL",
  "token_fcm": "<token de 64-255 caracteres>", "activo": true }
```

Respuesta `200`:

```json
{ "ok": true, "accion": "INSERT", "id_dispositivo": 1,
  "token": "...Ab12Cd", "mensaje": "dispositivo registrado" }
```

El endpoint hace **UPSERT** por token: una reinstalación reutiliza la fila.
Los tokens que FCM rechace como `NotRegistered` / `InvalidRegistration` /
`MismatchSenderId` reciben baja lógica (`activo = 0`).

## 4. Plantillas de payload

`firebase/plantillas/` documenta el mensaje que construye
`midff_fcm_payload_alerta()`:

- `payload-alerta-preventiva.json` — banda MEDIA (0.4 – 0.7, `EN_REVISION`);
- `payload-alerta-fraude.json` — banda ALTO (> 0.7, `BLOQUEADA`).

El bloque `data` viaja siempre como cadenas (protocolo legacy) e incluye
monto, score, clasificación, decisión y trazabilidad (`id_transaccion`,
`id_alerta`, `fecha_evaluacion`).

## 5. Verificación (métrica "push < 2 s")

1. `sqlplus MIDFFuser/midffadmin@localhost:1521/XEPDB1 @database/dispositivo_movil.sql`
2. Registrar el dispositivo real (paso 3) con la server key configurada.
3. `.\smoke-test.ps1` — los casos MEDIO/ALTO disparan el push; el log de la API
   imprime `[MIDFF-FCM] ... exitosos=1 ... envio_ms=...` y la app recibe la
   notificación (timestamp de `recibida_en_ms` vs. envío).
