# MIDFF Alertas — App Android (Sprint 3)

**MIDFF — Motor Inteligente de Detección de Fraude Financiero**
Componente 4 del MVP: app Android en **Java** que recibe las alertas push de
FCM y las presenta en una bandeja de entrada (`MVP_MIDFF.md` §6 · RF-10).

| Campo | Valor |
|---|---|
| Requisitos | RF-09 (recepción push), RF-10 (bandeja + detalle), HU-MVP-05 |
| Lenguaje | Java 8 (source/target compatibility) |
| SDK | compileSdk 34 · minSdk 24 (Android 7.0) · targetSdk 34 |
| Firebase | `firebase-messaging` 23.4.1 + plugin `com.google.gms.google-services` 4.4.2 |
| Build | AGP 8.5.2 · Gradle 8.9 (wrapper) · JDK 17+ (probado con JBR 21 de Android Studio) |
| Package | `Midff.PluriOne1` (decisión D-30; el guion `-` de "Pluri-One" no es válido en paquetes Java/Android/Firebase, se omitió de común acuerdo) |

## Componentes (java/Midff/PluriOne1/)

| Clase | Responsabilidad |
|---|---|
| `MainActivity` | Bandeja de alertas, obtención/registro del token FCM (`POST /api/v1/dispositivos`) y apertura del detalle. |
| `NotificacionFcmService` | Extiende `FirebaseMessagingService`: captura el token (`onNewToken`, autoregistro) y procesa el push (`onMessageReceived`) mostrando la **notificación local** con monto, score y clasificación. |
| `DetalleAlertaActivity` | Detalle de la alerta: monto, score, clasificación, decisión y trazabilidad (transacción/alerta/fechas). |
| `AdaptadorAlertas` | Adaptador de la lista con color por banda de riesgo. |
| `ModeloAlerta` | Espejo del bloque `data` del push (serialización JSON). |
| `AlertasRepositorio` | Bandeja persistente en `SharedPreferences` + token/usuario del dispositivo (D-27). |
| `ApiClient` | Cliente HTTP mínimo (`HttpURLConnection`) contra la API REST con `X-API-Key`. |

## Solución de problemas

- **"Your build is currently configured to use incompatible Java 21.x and Gradle 8.0"** /
  **"Unsupported class file major version 65"**: Gradle menor a 8.5 no soporta el JBR 21
  con el que corre Android Studio. El proyecto ya viene corregido con **Gradle 8.9**
  (`gradle/wrapper/gradle-wrapper.properties`) y **AGP 8.5.2** (`build.gradle` raíz).
  Si el IDE sugiere *"Gradle 9.0-milestone-1"*, **ignóralo**: es una versión preliminar,
  no estable. Fuerza *File → Sync Project with Gradle Files*; si persistiera el error de
  caché, borra la carpeta `%USERPROFILE%\.gradle\caches\8.0` y vuelve a sincronizar.
- **Gradle JVM del IDE**: *Settings → Build, Execution, Deployment → Build Tools →
  Gradle → Gradle JVM* debe quedar en *jbr-21* (o cualquier JDK 17+); el wrapper se
  encarga de descargar Gradle 8.9 la primera vez.
- **Wrapper por CLI**: los scripts `gradlew.bat`/`gradle-wrapper.jar` los genera Android
  Studio al abrir el proyecto; si necesitas compilar por consola, créalos con
  *File → Sync* o `gradle wrapper` desde una instalación local de Gradle 8.9.
- **`processDebugMainManifest` falla con "Error parsing AndroidManifest.xml" /
  "La cadena '--' no está permitida en los comentarios"**: un comentario XML no puede
  contener dos guiones seguidos (`--`), solo en los delimitadores `<!--` y `-->`.
  Decora los encabezados con `=` (u otro carácter) en lugar de `---`.

## Cómo abrir y compilar

1. **Android Studio** → *Open* → carpeta `android/MidffAlertas`.
   (Android Studio genera `local.properties` con la ruta del SDK y completa el
   wrapper de Gradle al primer *sync*.)
2. Colocar el `google-services.json` **real** descargado de Firebase en
   `app/google-services.json` (ver sección siguiente; hay una plantilla
   `app/google-services.json.example` de referencia).
3. *Build → Make Project* o `gradlew.bat assembleDebug`.

## Agregar la aplicación a Firebase (paquete `Midff.PluriOne1`)

1. Entra a [Firebase Console](https://console.firebase.google.com/) y abre (o
   crea) el proyecto de MIDFF.
2. *Icono Android → Agregar app* (o *Configuración del proyecto → Tus apps →
   Android*).
3. **Nombre del paquete de Android:** `Midff.PluriOne1` (debe coincidir
   exactamente con el `applicationId` de `app/build.gradle`; el guion `-` no
   es un carácter válido y Firebase lo rechaza). Alias opcional:
   `MIDFF Alertas`; el certificado SHA-1 no es obligatorio para FCM.
4. **Descargar el archivo `google-services.json`** y guardarlo en
   `android/MidffAlertas/app/google-services.json` (reemplaza a
   `google-services.json.example`).
5. Sincronizar Gradle y compilar: el plugin `com.google.gms.google-services`
   valida que `package_name` coincida con `Midff.PluriOne1`.
6. En el servidor, colocar la credencial de mensajería **del mismo proyecto**
   en `MIDFF_FCM_SERVER_KEY` (`config/firebase.php`); ver
   `firebase/README-fcm.md` §2.

> Notas: los proyectos Firebase nuevos pueden no ofrecer *server key* legacy
> (solo HTTP v1); en ese caso aplica la migración prevista en D-23/D-30 con la
> cuenta de servicio. `Midff.PluriOne1` ya cumple los 2 segmentos con punto
> que exige Google Play para publicar en tienda.

## Puesta en marcha con la API local

1. Levantar la API y la tabla de dispositivos:
   ```powershell
   sqlplus MIDFFuser/midffadmin@localhost:1521/XEPDB1 @database/dispositivo_movil.sql
   php -S 127.0.0.1:8090 router.php
   ```
2. En la app (emulador con Google Play services o dispositivo físico):
   - aceptar el permiso de notificaciones (Android 13+);
   - **dispositivo físico (USB)**: antes, activar el puente
     `adb reverse tcp:8090 tcp:8090` (la app usa `127.0.0.1:8090`);
   - escribir el usuario del oficial y pulsar **Registrar token**
     (`POST /api/v1/dispositivos` → `DISPOSITIVO_MOVIL`);
   - enviar una transacción con score ≥ 0.4:
     ```powershell
     .\smoke-test.ps1
     ```
3. El push llega como notificación local y la alerta queda en la bandeja;
   tocarla abre el detalle (monto, score, clasificación).

## Notas del entorno

- `ApiClient.baseUrl` apunta a `http://127.0.0.1:8090` (localhost del
  teléfono). En un **dispositivo físico conectado por USB** se usa el puente
  `adb reverse tcp:8090 tcp:8090`, que redirige ese `127.0.0.1:8090` del
  teléfono al `127.0.0.1:8090` del HOST (donde corre la API). No requiere
  abrir el firewall ni conocer la IP LAN; hay que volver a ejecutarlo tras
  reconectar el cable.
- En el **emulador**, cambiar `ApiClient.baseUrl` a `http://10.0.2.2:8090`
  (loopback del host visto desde el emulador).
- `usesCleartextTraffic="true"` es **solo para desarrollo** (HTTP local);
  producción exige HTTPS.
- El botón **"Bloquear"** (HU-MVP-06, Sprint 4) está implementado:
  `DetalleAlertaActivity` lo envía a `POST /api/v1/bloquear` (requiere
  `database/sp_bloquear_alerta.sql` instalado en Oracle XE).
