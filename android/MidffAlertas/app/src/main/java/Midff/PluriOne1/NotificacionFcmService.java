package Midff.PluriOne1;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.os.Build;
import android.util.Log;

import androidx.annotation.NonNull;
import androidx.core.app.NotificationCompat;
import androidx.core.app.NotificationManagerCompat;

import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;

import java.util.Map;

/**
 * Servicio de mensajeria FCM del MIDFF (RF-09 / RF-10 / HU-MVP-05, Sprint 3).
 *
 * <p>Responsabilidades:</p>
 * <ul>
 *   <li>{@link #onNewToken(String)}: captura el token FCM del dispositivo y lo
 *       auto-registra contra {@code POST /api/v1/dispositivos} (tabla
 *       DISPOSITIVO_MOVIL de Oracle XE).</li>
 *   <li>{@link #onMessageReceived(RemoteMessage)}: convierte el push en una
 *       NOTIFICACION LOCAL con monto, score y clasificacion, la guarda en la
 *       bandeja (AlertasRepositorio) y abre el detalle al tocarla.</li>
 * </ul>
 */
public class NotificacionFcmService extends FirebaseMessagingService {

    private static final String ETIQUETA = "MIDFF-FCM";

    /** Canal de las alertas (debe existir en Android 8+; lo crea crearCanal()). */
    public static final String CANAL_ALERTAS = "midff_alertas";

    /** Extra de Intent con el JSON de la alerta (ModeloAlerta.aJsonSeguro). */
    public static final String EXTRA_ALERTA_JSON = "alerta_json";

    /**
     * Token nuevo o rotado: se guarda localmente y se auto-registra en la API
     * (best-effort; el usuario puede repetir el registro desde MainActivity).
     */
    @Override
    public void onNewToken(@NonNull String token) {
        Log.i(ETIQUETA, "token FCM recibido: ..." + token.substring(Math.max(0, token.length() - 6)));
        AlertasRepositorio.guardarToken(this, token);

        // BIT-017: la sesion define usuario (correo) e id_cliente para que el
        // push llegue SOLO a este cliente; sin sesion queda el modo legado.
        String correo    = SesionCliente.leerCorreo(this);
        String usuario   = !correo.isEmpty() ? correo : AlertasRepositorio.leerUsuario(this);
        String idCliente = SesionCliente.leerIdCliente(this);
        DescubrimientoApi.asegurar(this, (apiOk, urlBase, motivo) ->
                ApiClient.registrarDispositivo(usuario, "CLIENTE", idCliente, token, (ok, mensaje) ->
                        Log.i(ETIQUETA, "autoregistro del dispositivo "
                                + (ok ? "OK" : "FALLO") + ": " + mensaje)));
    }

    /**
     * Push recibido: con la app en primer plano o en segundo plano con payload
     * "data", construye la alerta, la agrega a la bandeja y notifica.
     */
    @Override
    public void onMessageReceived(@NonNull RemoteMessage mensaje) {
        Map<String, String> datos = mensaje.getData();

        // BIT-027: actualizacion de estado decidida por el BANCO (bloqueo o
        // desbloqueo). No es una alerta nueva: actualiza la existente en la
        // bandeja y avisa con una notificacion propia.
        String tipoFcm = datos.get("tipo_alerta") == null ? "" : datos.get("tipo_alerta").trim();
        if ("ESTADO".equalsIgnoreCase(tipoFcm)) {
            procesarEstadoBanco(datos);
            return;
        }

        ModeloAlerta alerta = ModeloAlerta.desdeDatosFcm(datos);
        if (alerta == null && mensaje.getNotification() != null) {
            // Respaldo: push con solo "notification" (sin data de negocio).
            RemoteMessage.Notification n = mensaje.getNotification();
            alerta = ModeloAlerta.desdeJson("{}");
            if (alerta != null) {
                alerta.clasificacion = "-";
                alerta.tipoAlerta = "PREVENTIVA";
                alerta.decision = "EN_REVISION";
                alerta.monto = "-";
                alerta.moneda = "";
                alerta.score = "-";
                alerta.idCliente = "-";
                if (n.getBody() != null) {
                    alerta.fechaEvaluacion = n.getBody();
                }
            }
        }
        if (alerta == null) {
            Log.w(ETIQUETA, "push sin datos de negocio; se descarta");
            return;
        }

        // BIT-022: TODAS las transacciones quedan en el historial de la app:
        // las sanas aparecen bajo el filtro "Aprobadas" (ModeloAlerta.esAprobada)
        // y las de riesgo en el resto de la bandeja.
        new AlertasRepositorio(this).guardar(alerta);
        mostrarNotificacionLocal(alerta);
    }

    /**
     * Push ESTADO (BIT-027): el banco bloqueo o desbloqueo una alerta. Refleja
     * el estado en la bandeja local (si la alerta ya llego por push) y notifica
     * al cliente; si la bandeja no tiene la alerta, la notificacion igual
     * informa la decision (la sincronizacion con Oracle la reconciliara).
     */
    private void procesarEstadoBanco(Map<String, String> datos) {
        String idAlerta = datos.get("id_alerta") == null ? "" : datos.get("id_alerta").trim();
        String estado   = datos.get("estado") == null ? "" : datos.get("estado").trim();
        String evento   = datos.get("evento") == null ? "" : datos.get("evento").trim();
        String detalle  = datos.get("detalle") == null ? "" : datos.get("detalle").trim();
        if (idAlerta.isEmpty() || estado.isEmpty()) {
            Log.w(ETIQUETA, "push ESTADO sin id_alerta/estado; se descarta");
            return;
        }

        boolean cambio = new AlertasRepositorio(this).aplicarEstado(idAlerta, estado);
        Log.i(ETIQUETA, "decision del banco " + idAlerta + " -> " + estado
                + (cambio ? " (bandeja actualizada)" : " (sin cambios locales)"));

        if (Build.VERSION.SDK_INT >= 33
                && checkSelfPermission(android.Manifest.permission.POST_NOTIFICATIONS)
                        != PackageManager.PERMISSION_GRANTED) {
            return;   // sin permiso: el cambio queda solo en la bandeja
        }
        crearCanal();

        boolean desbloqueo = "DESBLOQUEO".equalsIgnoreCase(evento);
        String titulo = desbloqueo
                ? "MIDFF: transaccion desbloqueada por el banco"
                : "MIDFF: alerta bloqueada por el banco";
        String texto = detalle.isEmpty()
                ? idAlerta + " ahora esta " + estado
                : detalle + " (" + idAlerta + ")";

        Intent intencion = new Intent(this, MainActivity.class);
        intencion.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        PendingIntent pendiente = PendingIntent.getActivity(
                this,
                idAlerta.hashCode(),
                intencion,
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);

        NotificationCompat.Builder builder = new NotificationCompat.Builder(this, CANAL_ALERTAS)
                .setSmallIcon(R.drawable.ic_stat_midff)
                .setContentTitle(titulo)
                .setContentText(texto)
                .setStyle(new NotificationCompat.BigTextStyle().bigText(texto))
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setCategory(NotificationCompat.CATEGORY_EVENT)
                .setColor(desbloqueo ? 0xFF81C99B : 0xFFF28B82)
                .setAutoCancel(true)
                .setContentIntent(pendiente);

        NotificationManagerCompat.from(this)
                .notify(("ESTADO:" + idAlerta).hashCode(), builder.build());
    }

    /** Notificacion local con monto, score y clasificacion (RF-10). */
    private void mostrarNotificacionLocal(ModeloAlerta alerta) {
        if (Build.VERSION.SDK_INT >= 33
                && checkSelfPermission(android.Manifest.permission.POST_NOTIFICATIONS)
                        != PackageManager.PERMISSION_GRANTED) {
            Log.w(ETIQUETA, "permiso POST_NOTIFICATIONS no concedido; la alerta queda solo en la bandeja");
            return;
        }

        crearCanal();

        boolean sana = alerta.esSana();
        int idNotificacion = sana ? alerta.idTransaccion.hashCode() : alerta.idAlerta.hashCode();

        // La transaccion sana abre la bandeja (no tiene detalle con "Bloquear").
        Intent intencion = new Intent(this, sana ? MainActivity.class : DetalleAlertaActivity.class);
        if (!sana) {
            intencion.putExtra(EXTRA_ALERTA_JSON, alerta.aJsonSeguro());
        }
        intencion.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        PendingIntent pendiente = PendingIntent.getActivity(
                this,
                idNotificacion,
                intencion,
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);

        NotificationCompat.Builder builder = new NotificationCompat.Builder(this, CANAL_ALERTAS)
                .setSmallIcon(R.drawable.ic_stat_midff)
                .setContentTitle(alerta.tituloNotificacion())
                .setContentText(alerta.detalleCorto())
                .setStyle(new NotificationCompat.BigTextStyle().bigText(alerta.detalleLargo()))
                .setPriority(sana ? NotificationCompat.PRIORITY_DEFAULT : NotificationCompat.PRIORITY_HIGH)
                .setCategory(sana ? NotificationCompat.CATEGORY_STATUS : NotificationCompat.CATEGORY_ALARM)
                .setColor(sana ? 0xFF81C99B
                        : (alerta.esRiesgoAlto() ? 0xFFF28B82 : 0xFFFDD663))
                .setAutoCancel(true)
                .setContentIntent(pendiente);

        NotificationManagerCompat.from(this).notify(idNotificacion, builder.build());
    }

    /** Crea el canal de alertas (requerido desde Android 8.0 / API 26). */
    private void crearCanal() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel canal = new NotificationChannel(
                    CANAL_ALERTAS,
                    getString(R.string.canal_alertas_nombre),
                    NotificationManager.IMPORTANCE_HIGH);
            canal.setDescription(getString(R.string.canal_alertas_descripcion));
            getSystemService(NotificationManager.class).createNotificationChannel(canal);
        }
    }
}
