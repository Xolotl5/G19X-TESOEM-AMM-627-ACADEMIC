package Midff.PluriOne1;

import android.content.Context;
import android.content.SharedPreferences;

import org.json.JSONArray;
import org.json.JSONException;

import java.util.ArrayList;
import java.util.List;
import java.util.Map;

/**
 * Almacen local de la bandeja de alertas y del token FCM del dispositivo.
 *
 * <p>Decision D-27 (BITACORA BIT-004): al diferirse Firebase Realtime Database
 * (RF-12), la bandeja del MVP persiste las alertas recibidas en
 * SharedPreferences como un arreglo JSON (maximo {@value #MAX_ALERTAS}).
 * La fuente de verdad sigue siendo Oracle: este almacen es solo el reflejo
 * del dispositivo para la vista (RF-10).</p>
 */
public class AlertasRepositorio {

    private static final String PREFS           = "midff_alertas";
    private static final String CLAVE_ALERTAS   = "alertas";
    private static final String CLAVE_TOKEN     = "token_fcm";
    private static final String CLAVE_USUARIO   = "usuario_oficial";
    private static final int    MAX_ALERTAS     = 100;

    private final SharedPreferences preferencias;

    public AlertasRepositorio(Context contexto) {
        preferencias = contexto.getApplicationContext()
                .getSharedPreferences(PREFS, Context.MODE_PRIVATE);
    }

    /** Guarda la alerta al inicio de la bandeja (la mas reciente primero). */
    public synchronized void guardar(ModeloAlerta alerta) {
        if (alerta == null) {
            return;
        }
        try {
            JSONArray actual = leerArreglo();
            JSONArray nueva  = new JSONArray();
            nueva.put(alerta.aJson());
            for (int i = 0; i < actual.length() && i < MAX_ALERTAS - 1; i++) {
                nueva.put(actual.get(i));
            }
            preferencias.edit().putString(CLAVE_ALERTAS, nueva.toString()).apply();
        } catch (JSONException e) {
            // La recepcion del push nunca debe fallar por el almacen local.
        }
    }

    /** Devuelve las alertas de la mas reciente a la mas antigua. */
    public List<ModeloAlerta> listar() {
        List<ModeloAlerta> lista = new ArrayList<>();
        JSONArray arreglo = leerArreglo();
        for (int i = 0; i < arreglo.length(); i++) {
            ModeloAlerta alerta = ModeloAlerta.desdeJson(arreglo.optString(i, null));
            if (alerta != null) {
                lista.add(alerta);
            }
        }
        return lista;
    }

    /** Vacia la bandeja local (no toca Oracle ni el token registrado). */
    public void limpiar() {
        preferencias.edit().remove(CLAVE_ALERTAS).apply();
    }

    /**
     * Marca la alerta como BLOQUEADA en la bandeja local tras la confirmacion
     * de Oracle (HU-MVP-06, Sprint 4). Oracle sigue siendo la fuente de verdad;
     * este reflejo solo alimenta la vista (RF-10).
     */
    public synchronized void marcarBloqueada(String idAlerta) {
        aplicarEstado(idAlerta, "BLOQUEADA");
    }

    /**
     * BIT-027: aplica a la bandeja local el estado que Oracle reporta para una
     * alerta (decision del banco: BLOQUEADA al bloquear, ATENDIDA al
     * desbloquear o aprobar). Alimenta tanto el push de estado en tiempo real
     * como la sincronizacion con {@code GET /api/v1/estado}.
     *
     * @return true si la alerta existia y su estado cambio.
     */
    public synchronized boolean aplicarEstado(String idAlerta, String estado) {
        if (idAlerta == null || idAlerta.trim().isEmpty()
                || estado == null || estado.trim().isEmpty()) {
            return false;
        }
        boolean cambio = false;
        try {
            JSONArray actual = leerArreglo();
            JSONArray nueva  = new JSONArray();
            for (int i = 0; i < actual.length(); i++) {
                ModeloAlerta alerta = ModeloAlerta.desdeJson(actual.optString(i, null));
                if (alerta != null) {
                    if (idAlerta.equals(alerta.idAlerta)
                            && !estado.trim().equalsIgnoreCase(alerta.estado)) {
                        alerta.estado = estado.trim();
                        cambio = true;
                    }
                    nueva.put(alerta.aJson());
                }
            }
            if (cambio) {
                preferencias.edit().putString(CLAVE_ALERTAS, nueva.toString()).apply();
            }
        } catch (JSONException e) {
            // El reflejo local nunca debe fallar el flujo de sincronizacion.
        }
        return cambio;
    }

    /**
     * BIT-027: aplica en lote los estados que reporta Oracle (mapa
     * id_alerta -> estado). Devuelve cuantas alertas cambiaron.
     */
    public synchronized int aplicarEstados(Map<String, String> estados) {
        if (estados == null || estados.isEmpty()) {
            return 0;
        }
        int cambios = 0;
        for (Map.Entry<String, String> entrada : estados.entrySet()) {
            if (aplicarEstado(entrada.getKey(), entrada.getValue())) {
                cambios++;
            }
        }
        return cambios;
    }

    private JSONArray leerArreglo() {
        try {
            return new JSONArray(preferencias.getString(CLAVE_ALERTAS, "[]"));
        } catch (JSONException e) {
            return new JSONArray();
        }
    }

    // -----------------------------------------------------------------------
    // Token FCM y usuario del oficial (compartidos con NotificacionFcmService)
    // -----------------------------------------------------------------------

    public static void guardarToken(Context contexto, String token) {
        prefs(contexto).edit().putString(CLAVE_TOKEN, token).apply();
    }

    public static String leerToken(Context contexto) {
        return prefs(contexto).getString(CLAVE_TOKEN, null);
    }

    public static void guardarUsuario(Context contexto, String usuario) {
        prefs(contexto).edit().putString(CLAVE_USUARIO, usuario).apply();
    }

    /** Usuario del oficial usado por el autoregistro del servicio. */
    public static String leerUsuario(Context contexto) {
        return prefs(contexto).getString(CLAVE_USUARIO, "OFICIAL-MOVIL");
    }

    private static SharedPreferences prefs(Context contexto) {
        return contexto.getApplicationContext().getSharedPreferences(PREFS, Context.MODE_PRIVATE);
    }
}
