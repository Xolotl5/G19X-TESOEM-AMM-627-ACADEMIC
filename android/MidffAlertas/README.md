# MIDFF Alertas — App Android (Sprint 3)

**MIDFF — Motor Inteligente de Detección de Fraude Financiero**
Componente 4 del MVP: app Android en **Java** que recibe las alertas push de
FCM y las presenta en una bandeja de entrada (`MVP_MIDFF.md` §6 · RF-10).

| Campo | Valor |
|---|---|
| Requisitos | RF-09 (recepción push), RF-10 (bandeja + detalle), HU-MVP-05 |
| Lenguaje | Java 17 (source/target compatibility, D-44) |
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
   php -S 127.0.0.1:8091 router.php
   ```
2. En la app (emulador con Google Play services o dispositivo físico):
   - aceptar el permiso de notificaciones (Android 13+);
   - **dispositivo físico (USB)**: antes, activar el puente
     `adb reverse tcp:8091 tcp:8091` (la app usa `127.0.0.1:8091`);
   - escribir el **correo de la cuenta** y el **ID de cliente** (p. ej.
     `CLI-003`) y pulsar **Registrar token** (`POST /api/v1/dispositivos` →
     `DISPOSITIVO_MOVIL` con `rol=CLIENTE` e `id_cliente`);
   - enviar una transacción con score ≥ 0.4:
     ```powershell
     .\smoke-test.ps1
     ```
3. El push llega como notificación local y la alerta queda en la bandeja;
   tocarla abre el detalle (monto, score, clasificación). **Solo llega al
   dispositivo cuyo `id_cliente` coincide con el de la transacción**
   (BIT-017); si la BD existente no tiene la columna, ejecutar primero:
   ```powershell
   sqlplus MIDFFuser/midffadmin@localhost:1521/XEPDB1 @database/dispositivo_movil_id_cliente.sql
   ```

## Notas del entorno

- **Conexión automática (D-43)**: la app detecta sola la URL de la API al
  arrancar (`DescubrimientoApi`), probando en orden: última URL que funcionó →
  `http://10.0.2.2:8091` (emulador) → `http://127.0.0.1:8091` (USB con
  `adb reverse`) → gateway de la WiFi → **escaneo de la subred WiFi**
  (`x.x.x.1-254`, puerto 8091, 32 hilos). Ya **no hay que cambiar la IP a
  mano**: la isla dinámica muestra `Motor C · API <host:puerto>` y tocándola
  se vuelve a buscar. El faro es `GET /api/v1/ping` (sin API-Key ni Oracle).
- **Modo USB (adb reverse)**: seguir usando `adb reverse tcp:8091 tcp:8091`
  tras reconectar el cable; la app lo resuelve sola vía `127.0.0.1`.
- **Modo WiFi (app instalada)**: la API debe escuchar en todas las
  interfaces y el firewall debe permitir el puerto:
  ```powershell
  php -S 0.0.0.0:8091 router.php
  netsh advfirewall firewall add rule name="MIDFF API 8091" dir=in action=allow protocol=TCP localport=8091
  ```
  La primera vez el escaneo tarda ~2-4 s; la IP ganadora se guarda y las
  siguientes conexiones son inmediatas (si la PC cambia de IP por DHCP, se
  re-escanea automáticamente).
- `usesCleartextTraffic="true"` es **solo para desarrollo** (HTTP local);
  producción exige HTTPS.
- El botón **"Bloquear"** (HU-MVP-06, Sprint 4) está implementado:
  `DetalleAlertaActivity` lo envía a `POST /api/v1/bloquear` (requiere
  `database/sp_bloquear_alerta.sql` instalado en Oracle XE).

## Rediseño UI: glassmorphism de cristal ahumado con neón (prompt.txt)

La interfaz se rediseñó como panel de control híbrido con el estilo
**"Glassmorphism de Cristal Ahumado con Neón de Contraste Profundo"**:

| Elemento | Implementación |
|---|---|
| Fondo | Carbón profundo `#121214` con auroras radiales difusas azul-púrpura-rosa (`bg_pantalla.xml`). |
| Paneles | Cristal ahumado translúcido (~60-70%) con degradado vertical interno y borde 1px semitransparente (`panel_cristal.xml`, `chip_cristal.xml`). |
| Neones | Cian `#00F0FF` y magenta `#E000FF`: texto de filtros activo (cian), subrayado magenta, scores/montos grandes con glow (`shadowLayer`), botones cápsula con halo (`btn_neon_cian/btn_neon_magenta.xml`). |
| Bandas de riesgo | Cian APROBADA/BAJO, púrpura `#B44CFF` EN_REVISION/MEDIO, magenta ALTO/BLOQUEADA. |
| Cabecera (`cabecera_bandeja.xml`) | Avatar circular con degradado aurora + usuario en texto degradado azul-rosa (`UtilidadesUi.textoDegradado`), isla dinámica "Motor C · Escaneando" con punto pulsante, panel de riesgo global (score máximo) y volumen monitoreado en cian neón. |
| Filtros | Chips de cristal (Todas/Aprobadas/En Revisión/Bloqueadas) + rayo aurora de Críticas con badge; filtran la bandeja en `MainActivity` (enum `Filtro`). |
| Detalle | Hero con score gigante, analítica ponderada (monto 45% / frecuencia 30% / ubicación IP 25%) con barras de gradiente cian-púrpura-magenta, trazabilidad, carrusel de dispositivos (`item_dispositivo.xml`) y "Bloquear" en cápsula magenta. |
| Navegación | Banda inferior de cristal helado con iconos aurora; el activo se tiñe de cian con resplandor (`marcarNavegacion`). |

**Nota técnica**: el desenfoque de fondo (`backdrop-filter: blur`) no existe
como tal en Views de Android; se simula con rellenos translúcidos sobre las
auroras del fondo, como es práctica estándar. El tema pasó de
`DarkActionBar` a `NoActionBar` (la isla dinámica hace de cabecera; el
detalle tiene su botón "Volver"). Los iconos con relleno degradado usan
`aapt:attr` en los vectores (requiere AAPT2, incluido en AGP 8.x).

## Usabilidad: pestañas instantáneas y feedback en cada acción

La navegación inferior ya **no hace scroll** de una lista larga: la pantalla
se divide en tres **pestañas que se intercambian por visibilidad** con un
fundido de ~110 ms (`Pestana.PANEL | ALERTAS | REGISTRO` en `MainActivity`):

| Acción | Comportamiento |
|---|---|
| Panel / Alertas / Registro (nav inferior) | Cambio **instantáneo** de pestaña + resaltado cian. Segundo toque en "Alertas" salta al inicio de la lista (`setSelection(0)`, sin animación). |
| Isla dinámica | Táctil: al pulsarla refresca la bandeja con rebote de escala y actualiza su texto. |
| Avatar | Salta a la pestaña Registro y enfoca el campo de usuario. |
| Registrar token | Se deshabilita y muestra "Registrando…" mientras la API responde (ignora dobles toques); el resultado (éxito cian / fallo magenta con motivo) queda **inline** en `txtEstadoRegistro`. |
| Actualizar | Refresca y da acuse: texto en la isla ("Bandeja al día · N alertas") + parpadeo corto de la lista. |
| Limpiar | Pide confirmación con `AlertDialog` antes de vaciar la bandeja local. |
| Copiar token | Copia el token FCM al portapapeles (`ClipboardManager`). |
| Ver alertas críticas (Panel) | Salta a Alertas y aplica el filtro CRITICAS. |
| Filtros | Chips con **conteo en vivo** ("Todas · 12", "Bloqueadas · 3") y respuesta inmediata. |

Además, **todo lo tocable tiene ripple**: nav inferior (`foreground`),
botones (`btn_neon_*_ripple`, `btn_cristal_ripple`), chips
(`chip_cristal_ripple`) y los ítems de la bandeja (`listSelector`
`selector_item_alerta`). La cabecera compacta (avatar + isla) está siempre
visible en las tres pestañas.

## Acceso de clientes: Firebase Auth + biometría (BIT-015)

La app dejó de ser abierta: ahora pertenece a un **cliente identificado**.

| Paso | Qué pasa |
|---|---|
| 1er arranque | `LoginActivity` (glass/neón): **Entrar** o **Crear cuenta** con correo y contraseña (Firebase Authentication). |
| Siguientes entradas | La sesión queda guardada (`SesionCliente`) y MainActivity muestra la **pantalla de bloqueo**: hay que autenticarse con la **huella, el rostro o el PIN del dispositivo** (`BiometricPrompt`, permiso `USE_BIOMETRIC`). Sin biometría configurada (emulador limpio) no se bloquea y la isla lo indica. |
| Cerrar sesión | Botón en la pestaña Registro (con confirmación): `signOut` de Firebase + borrado de sesión → vuelve al login. |

**Paso requerido en Firebase Console (una sola vez, sin esto el login
falla con "CONFIGURATION_ERROR" / "EMAIL/password disabled")**:

1. [Firebase Console](https://console.firebase.google.com/) → proyecto
   `midff-57e6b` → **Authentication → Sign-in method**.
2. Habilitar el proveedor **Correo/contraseña** (Email/Password).
3. Los usuarios se crean desde la propia app (**Crear cuenta**) o desde
   Console → Authentication → Users → *Add user*.

Notas técnicas: dependencias `firebase-auth:22.3.1` y
`androidx.biometric:1.1.0`; con `setDeviceCredentialAllowed(true)` el prompt
acepta biometría **o** el PIN/patrón/contraseña del dispositivo en todas las
versiones soportadas (minSdk 24).

## Alertas filtradas por cliente (BIT-017)

El push ya no es broadcast: cada usuario recibe **solo SUS transacciones**.

| Pieza | Cambio |
|---|---|
| Oracle | `DISPOSITIVO_MOVIL` gana `ID_CLIENTE VARCHAR2(40)`, rol `CLIENTE` e índice `(id_cliente, activo)`. Instalaciones existentes: `database/dispositivo_movil_id_cliente.sql` (re-ejecutable, no pierde tokens). |
| API | `POST /api/v1/dispositivos` acepta `id_cliente` (opcional, ≤ 40) y `rol=CLIENTE`. |
| Filtro | `config/firebase.php`: `midff_fcm_enviar_a_dispositivos()` consulta solo los dispositivos activos con el `id_cliente` de la transacción. Sin `id_cliente` se conserva el **broadcast legado** (modo oficial/demo). |
| App | Pestaña Registro: campo **"ID de cliente"** — se **genera solo al crear la cuenta** (determinista desde el uid de Firebase, BIT-018) y queda precargado; `onNewToken` auto-registra con correo + id_cliente. |

**Vista del banco (BIT-018):** `GET /api/v1/dispositivos` (misma X-API-Key)
lista los clientes dados de alta con su `id_cliente`; `banco.html` lo muestra
en "Clientes registrados" y el botón **Usar** copia el ID al formulario.

Para probar el aislamiento: registrar `CLI-003` en la app y enviar
transacciones de `CLI-003` (llega push) y de `CLI-004` (no llega).

## "No fui yo / Sí fui yo" + app limpia (BIT-020)

La app quedó **solo para el cliente** (la lógica del banco vive en
`MIDFF.html`):

| Cambio | Detalle |
|---|---|
| Sin Panel | La app abre directo en la bandeja de SUS alertas. |
| Sin "Bloquear" | En el detalle ahora hay **"⛔ No fui yo"** y **"✓ Sí fui yo"** (`POST /api/v1/reportar`): queda en `ALERTA` + `AUDITORIA` y el banco lo ve como bandera roja en la consola. Solo el dueño puede reportar (404 si ajena, 409 si repetido). |
| "Perfil" (antes Registro) | Muestra el correo, el **ID de cliente** y el token; botón manual de respaldo. |
| Registro automático | En cada apertura la app re-registra el token con su `id_cliente` en silencio. |

Flujo completo de demo:
cliente recibe push → responde "No fui yo" → bandera ⚑ en `MIDFF.html`
(pestaña Alertas) → el oficial **Bloquea** desde la consola → auditoría en
Oracle.
