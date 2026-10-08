package Midff.PluriOne1;

import android.content.Context;
import android.content.SharedPreferences;
import android.net.wifi.WifiManager;
import android.os.Build;
import android.os.Handler;
import android.os.Looper;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.InetAddress;
import java.net.NetworkInterface;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicBoolean;

/**
 * Descubrimiento automatico de la URL base de la API MIDFF (decisión D-43):
 * elimina el cambio manual de {@link ApiClient#baseUrl} segun el modo de
 * ejecucion de la app.
 *
 * <p>Orden de candidatos contra {@code GET /api/v1/ping} (sin API-Key ni
 * Oracle): última URL que funcionó, {@code 10.0.2.2} en emulador,
 * {@code 127.0.0.1} con {@code adb reverse}, gateway DHCP de la WiFi y por
 * último escaneo de la subred ({@code x.x.x.1-254}) con 32 hilos.</p>
 *
 * <p>La URL ganadora se persiste y se fija en {@link ApiClient#baseUrl}.
 * Para el modo WiFi la API debe escuchar en {@code 0.0.0.0:8091} y el
 * firewall debe permitir el puerto (ver README de la app).</p>
 */
public final class DescubrimientoApi {

    /** Puerto fijo del MVP (php -S). D-45: 8091, porque el 8090 suele estar
     *  tomado por Wondershare NativePush en las máquinas de demo. */
    public static final int PUERTO = 8091;

    private static final String PREFS      = "midff_red";
    private static final String CLAVE_URL  = "url_base";
    private static final int    TIMEOUT_MS = 350;

    /** Resultado del descubrimiento (se entrega en el hilo principal). */
    public interface Callback {
        void terminado(boolean ok, String urlBase, String motivo);
    }

    private DescubrimientoApi() {
    }

    // -----------------------------------------------------------------------
    // Persistencia del último enlace válido
    // -----------------------------------------------------------------------

    public static String leerUrlGuardada(Context contexto) {
        return prefs(contexto).getString(CLAVE_URL, null);
    }

    private static void guardarUrl(Context contexto, String urlBase) {
        prefs(contexto).edit().putString(CLAVE_URL, urlBase).apply();
    }

    private static SharedPreferences prefs(Context contexto) {
        return contexto.getApplicationContext()
                .getSharedPreferences(PREFS, Context.MODE_PRIVATE);
    }

    // -----------------------------------------------------------------------
    // Sensores del dispositivo
    // -----------------------------------------------------------------------

    /** Cierto si la app corre dentro del emulador de Android. */
    public static boolean esEmulador() {
        String huella   = Build.FINGERPRINT == null ? "" : Build.FINGERPRINT;
        String modelo   = Build.MODEL == null ? "" : Build.MODEL;
        String producto = Build.PRODUCT == null ? "" : Build.PRODUCT;
        String hardware = Build.HARDWARE == null ? "" : Build.HARDWARE;
        huella   = huella.toLowerCase(Locale.US);
        modelo   = modelo.toLowerCase(Locale.US);
        producto = producto.toLowerCase(Locale.US);
        hardware = hardware.toLowerCase(Locale.US);
        return huella.startsWith("generic")
                || huella.contains("emulator")
                || modelo.contains("emulator")
                || modelo.contains("android sdk built")
                || producto.contains("sdk")
                || hardware.contains("ranchu")
                || hardware.contains("goldfish");
    }

    /** IP local IPv4 del teléfono por wlan0/swlan0 (o null si no hay WiFi). */
    public static String ipLocal() {
        try {
            List<NetworkInterface> interfaces =
                    Collections.list(NetworkInterface.getNetworkInterfaces());
            for (NetworkInterface interfaz : interfaces) {
                if (!interfaz.isUp() || interfaz.isLoopback()) {
                    continue;
                }
                String nombre = interfaz.getName().toLowerCase(Locale.US);
                if (!nombre.startsWith("wlan") && !nombre.startsWith("swlan")) {
                    continue;
                }
                for (InetAddress direccion : Collections.list(interfaz.getInetAddresses())) {
                    if (!direccion.isLoopbackAddress()
                            && direccion.getAddress() != null
                            && direccion.getAddress().length == 4) {
                        return direccion.getHostAddress();
                    }
                }
            }
        } catch (Exception ignorado) {
            // sin permiso o sin interfaces: el escaneo simplemente se omite
        }
        return null;
    }

    /** Gateway DHCP de la WiFi (router) como candidato barato (o null). */
    @SuppressWarnings("deprecation")   // WifiManager.getDhcpInfo(): vigente y funcional
    private static String gatewayWifi(Context contexto) {
        try {
            WifiManager wifi = (WifiManager) contexto.getApplicationContext()
                    .getSystemService(Context.WIFI_SERVICE);
            if (wifi == null) {
                return null;
            }
            int gateway = wifi.getDhcpInfo().gateway;
            if (gateway == 0) {
                return null;
            }
            return String.format(Locale.US, "%d.%d.%d.%d",
                    gateway & 0xff, (gateway >> 8) & 0xff,
                    (gateway >> 16) & 0xff, (gateway >> 24) & 0xff);
        } catch (Exception e) {
            return null;
        }
    }
    // -----------------------------------------------------------------------
    // Orquestación
    // -----------------------------------------------------------------------

    /**
     * Ejecuta el descubrimiento en segundo plano y entrega el resultado en el
     * hilo principal; si hay éxito fija {@link ApiClient#baseUrl} y persiste
     * la URL para la próxima sesión.
     */
    public static void asegurar(Context contexto, Callback callback) {
        Handler principal = new Handler(Looper.getMainLooper());
        ExecutorService hilo = Executors.newSingleThreadExecutor();
        hilo.execute(() -> {
            String url = descubrirSincrono(contexto);
            if (url != null) {
                ApiClient.baseUrl = url;
                guardarUrl(contexto, url);
            }
            principal.post(() -> {
                if (callback != null) {
                    callback.terminado(url != null, url,
                            url == null ? "ningún candidato respondió" : "");
                }
            });
            hilo.shutdown();
        });
    }

    private static String descubrirSincrono(Context contexto) {
        List<String> candidatos = new ArrayList<>();

        String guardada = leerUrlGuardada(contexto);
        if (guardada != null) {
            candidatos.add(guardada);
        }
        boolean emulador = esEmulador();
        if (emulador) {
            candidatos.add("http://10.0.2.2:" + PUERTO);
        }
        candidatos.add("http://127.0.0.1:" + PUERTO);
        if (!emulador) {
            String gateway = gatewayWifi(contexto);
            if (gateway != null) {
                candidatos.add("http://" + gateway + ":" + PUERTO);
            }
        }

        for (String candidato : candidatos) {
            if (probar(candidato)) {
                return candidato;
            }
        }

        // Último recurso: barrer la /24 de la WiFi (con 32 hilos tarda
        // ~2-4 s en el peor caso) para capturar la IP LAN de la PC aunque
        // haya cambiado por DHCP.
        String ip = ipLocal();
        if (ip != null && !emulador) {
            String prefijo = ip.substring(0, ip.lastIndexOf('.') + 1);
            return escanearSubred(prefijo);
        }
        return null;
    }

    /** Barrido x.x.x.1-254 en paralelo; devuelve la primera URL que responda. */
    private static String escanearSubred(String prefijo) {
        ExecutorService pool = Executors.newFixedThreadPool(32);
        AtomicBoolean ganador = new AtomicBoolean(false);
        final String[] resultado = {null};
        try {
            for (int i = 1; i <= 254; i++) {
                final String url = "http://" + prefijo + i + ":" + PUERTO;
                pool.execute(() -> {
                    if (ganador.get()) {
                        return;
                    }
                    if (probar(url) && ganador.compareAndSet(false, true)) {
                        synchronized (resultado) {
                            resultado[0] = url;
                        }
                    }
                });
            }
        } finally {
            pool.shutdown();
        }
        try {
            pool.awaitTermination(4, TimeUnit.SECONDS);
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }
        synchronized (resultado) {
            return resultado[0];
        }
    }

    /**
     * Sondeo {@code GET <urlBase>/api/v1/ping}: cierto solo si responde 200
     * con un cuerpo que identifica al servicio ("midff").
     */
    static boolean probar(String urlBase) {
        HttpURLConnection conexion = null;
        try {
            conexion = (HttpURLConnection) new URL(urlBase + "/api/v1/ping")
                    .openConnection();
            conexion.setConnectTimeout(TIMEOUT_MS);
            conexion.setReadTimeout(TIMEOUT_MS);
            conexion.setRequestMethod("GET");
            if (conexion.getResponseCode() != 200) {
                return false;
            }
            StringBuilder cuerpo = new StringBuilder();
            try (BufferedReader lector = new BufferedReader(
                    new InputStreamReader(conexion.getInputStream(), StandardCharsets.UTF_8))) {
                String linea;
                while ((linea = lector.readLine()) != null) {
                    cuerpo.append(linea);
                }
            }
            return cuerpo.toString().contains("midff");
        } catch (Exception e) {
            return false;
        } finally {
            if (conexion != null) {
                conexion.disconnect();
            }
        }
    }
}