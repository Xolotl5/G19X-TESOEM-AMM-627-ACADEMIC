package Midff.PluriOne1;

import android.content.Context;
import android.content.SharedPreferences;

/**
 * Sesión del cliente (BIT-015): persiste el acceso Firebase entre entradas
 * para que la app solo pida correo/contraseña una vez y en cada apertura
 * baste con el desbloqueo biométrico del dispositivo.
 *
 * <p>BIT-017: además persiste el {@code id_cliente} (p. ej. CLI-3F9K2A) con el
 * que se registra el token FCM, para que el push llegue SOLO a su dueño.</p>
 *
 * <p>BIT-018: al abrir sesión se asigna un ID de cliente determinista derivado
 * del uid de Firebase; el usuario lo ve en la pestaña Registro y el banco lo
 * requiere para vincular sus transacciones (sin captura manual).</p>
 */
public final class SesionCliente {

    private static final String PREFS         = "midff_sesion";
    private static final String CLAVE_ACTIVA  = "activa";
    private static final String CLAVE_CORREO  = "correo";
    private static final String CLAVE_UID     = "uid";
    private static final String CLAVE_CLIENTE = "id_cliente";
    private static final String CLAVE_NOMBRE  = "nombre_mostrar";

    private SesionCliente() {
    }

    /** Abre la sesión tras un acceso válido en Firebase Auth. */
    public static void abrir(Context contexto, String correo, String uid) {
        prefs(contexto).edit()
                .putBoolean(CLAVE_ACTIVA, true)
                .putString(CLAVE_CORREO, correo)
                .putString(CLAVE_UID, uid)
                .apply();
        asegurarIdCliente(contexto, uid);
    }

    /** Cierra la sesión local (el signOut de Firebase lo llama quien cierra). */
    public static void cerrar(Context contexto) {
        prefs(contexto).edit().clear().apply();
    }

    public static boolean estaActiva(Context contexto) {
        return prefs(contexto).getBoolean(CLAVE_ACTIVA, false);
    }

    public static String leerCorreo(Context contexto) {
        return prefs(contexto).getString(CLAVE_CORREO, "");
    }

    public static String leerUid(Context contexto) {
        return prefs(contexto).getString(CLAVE_UID, "");
    }

    /** id_cliente con el que se vinculó el dispositivo (BIT-017; '' = aún sin vincular). */
    public static String leerIdCliente(Context contexto) {
        return prefs(contexto).getString(CLAVE_CLIENTE, "");
    }

    /** Guarda el id_cliente elegido al registrar el dispositivo (BIT-017). */
    public static void guardarIdCliente(Context contexto, String idCliente) {
        prefs(contexto).edit().putString(CLAVE_CLIENTE, idCliente == null ? "" : idCliente.trim()).apply();
    }

    /**
     * BIT-028: nombre para mostrar en la cabecera de la app (en lugar del
     * correo). Es SOLO presentación: la sesión Firebase y el registro del
     * dispositivo siguen usando el correo real. Cadena vacía = usar el correo.
     */
    public static void guardarNombre(Context contexto, String nombre) {
        prefs(contexto).edit().putString(CLAVE_NOMBRE, nombre == null ? "" : nombre.trim()).apply();
    }

    /** Nombre para mostrar guardado por el usuario ('' = aún sin crear). */
    public static String leerNombre(Context contexto) {
        return prefs(contexto).getString(CLAVE_NOMBRE, "");
    }

    /**
     * Asigna el ID de cliente la primera vez que abre sesión (BIT-018): no
     * sobreescribe uno ya guardado (ni el capturado a mano en la pestaña
     * Registro, que queda como válvula de escape para la demo).
     */
    public static void asegurarIdCliente(Context contexto, String uid) {
        if (prefs(contexto).contains(CLAVE_CLIENTE)) {
            return;
        }
        prefs(contexto).edit().putString(CLAVE_CLIENTE, generarIdCliente(uid)).apply();
    }

    /**
     * ID determinista "CLI-" + 6 caracteres alfanuméricos del uid de Firebase
     * (en mayúsculas): la misma cuenta siempre recupera el mismo ID incluso
     * tras reinstalar. Respaldo numérico aleatorio si el uid viniera vacío.
     */
    private static String generarIdCliente(String uid) {
        String base = uid == null ? "" : uid.replaceAll("[^A-Za-z0-9]", "");
        if (base.length() >= 6) {
            return "CLI-" + base.substring(0, 6).toUpperCase();
        }
        return "CLI-" + String.format("%06d", (int) (Math.random() * 1_000_000));
    }

    private static SharedPreferences prefs(Context contexto) {
        return contexto.getApplicationContext()
                .getSharedPreferences(PREFS, Context.MODE_PRIVATE);
    }
}