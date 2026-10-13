package Midff.PluriOne1;

import android.util.Log;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.ConnectException;
import java.net.HttpURLConnection;
import java.net.SocketTimeoutException;
import java.net.URL;
import java.net.URLEncoder;
import java.net.UnknownHostException;
import java.nio.charset.StandardCharsets;
import java.util.HashMap;
import java.util.Map;

/**
 * Cliente minimo de la API REST MIDFF (sin dependencias externas).
 *
 * <p>Sprint 3: registro del token FCM contra {@code POST /api/v1/dispositivos}
 * (DISPOSITIVO_MOVIL en Oracle XE). Sprint 4: accion "Bloquear" contra
 * {@code POST /api/v1/bloquear} (SP_BLOQUEAR_ALERTA en Oracle XE).</p>
 *
 * <p>La misma X-API-Key del endpoint de transacciones (decision D-25 de
 * BITACORA.md: una sola clave para el MVP).</p>
 */
public final class ApiClient {

    private static final String ETIQUETA = "MIDFF-ApiClient";

    /**
     * URL base de la API.
     *
     * <p>Dispositivo fisico (USB/adb): se usa el localhost del telefono con el
     * puente {@code adb reverse tcp:8091 tcp:8091}, que redirige el
     * 127.0.0.1:8091 del telefono al 127.0.0.1:8091 del HOST (donde corre la
     * API MIDFF). No requiere abrir el firewall ni conocer la IP LAN.</p>
     *
     * <p>Emulador Android: cambiar a {@code http://10.0.2.2:8091} (loopback del
     * host visto desde el emulador).</p>
     */
    public static String baseUrl = "http://127.0.0.1:8091";

    /** Clave de desarrollo; en produccion se inyecta la real (RNF-03). */
    public static String apiKey = "MIDFF-API-KEY-2026-SPRINT2";

    /** Callback de las llamadas asincronas (se invoca en el hilo de red). */
    public interface Callback {
        void terminado(boolean ok, String mensaje);
    }

    /**
     * Callback de {@link #obtenerEstados(String, CallbackEstados)} (BIT-027):
     * mapa id_alerta -> estado actual en Oracle (BLOQUEADA / ATENDIDA / ...).
     */
    public interface CallbackEstados {
        void terminado(boolean ok, Map<String, String> estados, String mensaje);
    }

    private ApiClient() {
    }

    /**
     * POST /api/v1/dispositivos: registra/actualiza el token FCM de la app
     * Android (tabla DISPOSITIVO_MOVIL de Oracle XE).
     *
     * <p>BIT-031: el cuerpo incluye {@code uid_firebase} cuando la sesión lo
     * tiene, para que el banco pueda verificar contra Firebase Auth si la
     * cuenta sigue viva y purgar las eliminadas sin abrir la app.</p>
     */
    public static void registrarDispositivo(final String usuario, final String rol,
                                            final String idCliente, final String token,
                                            final String uidFirebase, final Callback callback) {
        try {
            JSONObject cuerpo = new JSONObject();
            cuerpo.put("usuario", usuario);
            cuerpo.put("rol", rol);
            if (idCliente != null && !idCliente.trim().isEmpty()) {
                cuerpo.put("id_cliente", idCliente.trim());
            }
            if (uidFirebase != null && !uidFirebase.trim().isEmpty()) {
                cuerpo.put("uid_firebase", uidFirebase.trim());
            }
            cuerpo.put("token_fcm", token);
            cuerpo.put("activo", true);
            post("/api/v1/dispositivos", cuerpo.toString().getBytes(StandardCharsets.UTF_8), callback);
        } catch (Exception e) {
            Log.e(ETIQUETA, "no fue posible armar el cuerpo del registro", e);
            if (callback != null) {
                callback.terminado(false, mensajeDeExcepcion(e));
            }
        }
    }

    /** POST /api/v1/reportar: el cliente responde "No fui yo" o "Sí fui yo"
     *  a una de SUS alertas (BIT-020); el banco lo ve en MIDFF.html. */
    public static void reportarAlerta(final String alertaId, final String idCliente,
                                      final String respuesta, final String comentario,
                                      final Callback callback) {
        try {
            JSONObject cuerpo = new JSONObject();
            cuerpo.put("alerta_id", alertaId);
            cuerpo.put("id_cliente", idCliente);
            cuerpo.put("respuesta", respuesta);
            if (comentario != null && !comentario.trim().isEmpty()) {
                cuerpo.put("comentario", comentario.trim());
            }
            post("/api/v1/reportar", cuerpo.toString().getBytes(StandardCharsets.UTF_8), callback);
        } catch (Exception e) {
            Log.e(ETIQUETA, "no fue posible armar el cuerpo del reporte", e);
            if (callback != null) {
                callback.terminado(false, mensajeDeExcepcion(e));
            }
        }
    }

    /** POST /api/v1/bloquear: bloqueo preventivo de la alerta (HU-MVP-06, Sprint 4). */
    public static void bloquearAlerta(final String alertaId, final String usuario,
                                      final String respuesta, final Callback callback) {
        try {
            JSONObject cuerpo = new JSONObject();
            cuerpo.put("alerta_id", alertaId);
            cuerpo.put("usuario", usuario);
            if (respuesta != null && !respuesta.trim().isEmpty()) {
                cuerpo.put("respuesta", respuesta.trim());
            }
            post("/api/v1/bloquear", cuerpo.toString().getBytes(StandardCharsets.UTF_8), callback);
        } catch (Exception e) {
            Log.e(ETIQUETA, "no fue posible armar el cuerpo del bloqueo", e);
            if (callback != null) {
                callback.terminado(false, mensajeDeExcepcion(e));
            }
        }
    }

    private static void post(final String ruta, final byte[] cuerpo, final Callback callback) {
        new Thread(() -> {
            HttpURLConnection conexion = null;
            try {
                URL url = new URL(baseUrl + ruta);
                conexion = (HttpURLConnection) url.openConnection();
                conexion.setRequestMethod("POST");
                conexion.setConnectTimeout(5000);
                conexion.setReadTimeout(8000);
                conexion.setDoOutput(true);
                conexion.setRequestProperty("Content-Type", "application/json; charset=utf-8");
                conexion.setRequestProperty("X-API-Key", apiKey);

                try (OutputStream salida = conexion.getOutputStream()) {
                    salida.write(cuerpo);
                }

                int codigo = conexion.getResponseCode();
                String texto = leer(codigo >= 400 ? conexion.getErrorStream() : conexion.getInputStream());
                if (callback != null) {
                    callback.terminado(codigo == 200, mensajeHttp(codigo, texto));
                }
            } catch (Exception e) {
                Log.e(ETIQUETA, "fallo la llamada a " + ruta, e);
                if (callback != null) {
                    callback.terminado(false, mensajeDeExcepcion(e));
                }
            } finally {
                if (conexion != null) {
                    conexion.disconnect();
                }
            }
        }).start();
    }

    /**
     * GET /api/v1/estado?id_cliente=... (BIT-027): estado actual en Oracle de
     * las alertas del cliente. La app lo usa para reflejar las decisiones del
     * banco (bloqueo/desbloqueo) aunque el push de estado se haya perdido.
     * Se ejecuta en su propio hilo; el callback llega en el hilo de red.
     */
    public static void obtenerEstados(final String idCliente, final CallbackEstados callback) {
        new Thread(() -> {
            HttpURLConnection conexion = null;
            try {
                URL url = new URL(baseUrl + "/api/v1/estado?id_cliente="
                        + URLEncoder.encode(idCliente, "UTF-8"));
                conexion = (HttpURLConnection) url.openConnection();
                conexion.setRequestMethod("GET");
                conexion.setConnectTimeout(6000);
                conexion.setReadTimeout(6000);
                conexion.setRequestProperty("X-API-Key", apiKey);

                int codigo = conexion.getResponseCode();
                String texto = leer(codigo >= 400
                        ? conexion.getErrorStream() : conexion.getInputStream());
                if (codigo == 200) {
                    JSONArray arreglo = new JSONObject(texto).optJSONArray("alertas");
                    Map<String, String> estados = new HashMap<>();
                    if (arreglo != null) {
                        for (int i = 0; i < arreglo.length(); i++) {
                            JSONObject fila = arreglo.optJSONObject(i);
                            if (fila != null) {
                                estados.put(fila.optString("id_alerta"),
                                        fila.optString("estado"));
                            }
                        }
                    }
                    if (callback != null) {
                        callback.terminado(true, estados, "");
                    }
                } else if (callback != null) {
                    callback.terminado(false, null, mensajeHttp(codigo, texto));
                }
            } catch (Exception e) {
                Log.e(ETIQUETA, "fallo GET /api/v1/estado", e);
                if (callback != null) {
                    callback.terminado(false, null, mensajeDeExcepcion(e));
                }
            } finally {
                if (conexion != null) {
                    conexion.disconnect();
                }
            }
        }).start();
    }

    /**
     * POST /api/v1/baja (BIT-030): purga del servidor TODOS los datos del
     * cliente (dispositivos, transacciones, evaluaciones y alertas) y
     * anonimiza su seudonimo en AUDITORIA. La app lo llama cuando detecta
     * que la cuenta ya no existe en Firebase Auth.
     */
    public static void darDeBaja(final String idCliente, final Callback callback) {
        try {
            JSONObject cuerpo = new JSONObject();
            cuerpo.put("id_cliente", idCliente);
            post("/api/v1/baja", cuerpo.toString().getBytes(StandardCharsets.UTF_8), callback);
        } catch (Exception e) {
            Log.e(ETIQUETA, "no fue posible armar el cuerpo de la baja", e);
            if (callback != null) {
                callback.terminado(false, mensajeDeExcepcion(e));
            }
        }
    }

    private static String leer(InputStream entrada) throws Exception {
        if (entrada == null) {
            return "";
        }
        StringBuilder constructor = new StringBuilder();
        try (BufferedReader lector = new BufferedReader(
                new InputStreamReader(entrada, StandardCharsets.UTF_8))) {
            String linea;
            while ((linea = lector.readLine()) != null) {
                constructor.append(linea);
            }
        }
        return constructor.toString();
    }

    // ------------------------------------------------------------------------
    // Mensajes de error accionables (los muestra el Toast de la app)
    // ------------------------------------------------------------------------

    /**
     * Mensaje para una respuesta HTTP distinta de 200. En lugar del JSON crudo,
     * extrae el campo {@code error} del contrato de errores de la API
     * (p. ej. {@code HTTP 400 - token_fcm invalido: se espera...}).
     */
    private static String mensajeHttp(int codigo, String texto) {
        String detalle = extraerError(texto);
        return detalle.isEmpty() ? "HTTP " + codigo : "HTTP " + codigo + " - " + detalle;
    }

    /** Toma el campo "error" del cuerpo JSON; si no aplica, devuelve el texto tal cual. */
    private static String extraerError(String texto) {
        if (texto == null) {
            return "";
        }
        String limpio = texto.trim();
        if (!limpio.startsWith("{")) {
            return limpio;
        }
        try {
            return new JSONObject(limpio).optString("error", limpio);
        } catch (JSONException e) {
            return limpio;
        }
    }

    /**
     * Traduce una excepcion de red en un mensaje diagnosticable sin Logcat:
     * incluye el tipo de fallo (ConnectException, SocketTimeoutException, ...) y,
     * cuando es un problema de conexion, la URL base y la ayuda del puente adb.
     */
    private static String mensajeDeExcepcion(Exception e) {
        String detalle = e.getMessage();
        String base = e.getClass().getSimpleName()
                + (detalle == null || detalle.trim().isEmpty() ? "" : ": " + detalle);
        if (e instanceof ConnectException
                || e instanceof UnknownHostException
                || e instanceof SocketTimeoutException) {
            // En una sola linea: en Android 12+ los Toast del sistema recortan
            // el texto a ~2 lineas, asi el motivo nunca queda oculto.
            base += " | verifique la API MIDFF (en USB: adb reverse tcp:8091 tcp:8091)";
        }
        return base;
    }
}
