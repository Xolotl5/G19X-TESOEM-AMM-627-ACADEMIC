package Midff.PluriOne1;

import android.util.Log;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

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
     * puente {@code adb reverse tcp:8090 tcp:8090}, que redirige el
     * 127.0.0.1:8090 del telefono al 127.0.0.1:8090 del HOST (donde corre la
     * API MIDFF). No requiere abrir el firewall ni conocer la IP LAN.</p>
     *
     * <p>Emulador Android: cambiar a {@code http://10.0.2.2:8090} (loopback del
     * host visto desde el emulador).</p>
     */
    public static String baseUrl = "http://127.0.0.1:8090";

    /** Clave de desarrollo; en produccion se inyecta la real (RNF-03). */
    public static String apiKey = "MIDFF-API-KEY-2026-SPRINT2";

    /** Callback de las llamadas asincronas (se invoca en el hilo de red). */
    public interface Callback {
        void terminado(boolean ok, String mensaje);
    }

    private ApiClient() {
    }

    /** POST /api/v1/dispositivos: registra/actualiza el token FCM (UPSERT). */
    public static void registrarDispositivo(final String usuario, final String rol,
                                            final String token, final Callback callback) {
        try {
            JSONObject cuerpo = new JSONObject();
            cuerpo.put("usuario", usuario);
            cuerpo.put("rol", rol);
            cuerpo.put("token_fcm", token);
            cuerpo.put("activo", true);
            post("/api/v1/dispositivos", cuerpo.toString().getBytes(StandardCharsets.UTF_8), callback);
        } catch (Exception e) {
            Log.e(ETIQUETA, "no fue posible armar el cuerpo del registro", e);
            if (callback != null) {
                callback.terminado(false, String.valueOf(e.getMessage()));
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
                callback.terminado(false, String.valueOf(e.getMessage()));
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
                    callback.terminado(codigo == 200, "HTTP " + codigo + ": " + texto);
                }
            } catch (Exception e) {
                Log.e(ETIQUETA, "fallo la llamada a " + ruta, e);
                if (callback != null) {
                    callback.terminado(false, String.valueOf(e.getMessage()));
                }
            } finally {
                if (conexion != null) {
                    conexion.disconnect();
                }
            }
        }).start();
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
}
