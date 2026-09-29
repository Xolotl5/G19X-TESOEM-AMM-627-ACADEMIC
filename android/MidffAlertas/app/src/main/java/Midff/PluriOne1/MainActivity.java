package Midff.PluriOne1;

import android.Manifest;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.os.Build;
import android.os.Bundle;
import android.widget.EditText;
import android.widget.ListView;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

import com.google.firebase.messaging.FirebaseMessaging;

import java.util.ArrayList;

/**
 * Bandeja de entrada de alertas del MIDFF (RF-10 / HU-MVP-05, Sprint 3).
 *
 * <p>Responsabilidades:</p>
 * <ul>
 *   <li>Obtener y mostrar el token FCM del dispositivo.</li>
 *   <li>Registrar el token contra {@code POST /api/v1/dispositivos}
 *       (usuario del oficial + rol; tabla DISPOSITIVO_MOVIL en Oracle XE).</li>
 *   <li>Listar las alertas push recibidas (guardadas por
 *       {@link NotificacionFcmService}) y abrir su detalle al tocarlas.</li>
 * </ul>
 */
public class MainActivity extends AppCompatActivity {

    private static final int CODIGO_PERMISO_NOTIFICACIONES = 1001;

    private TextView txtToken;
    private EditText edtUsuario;
    private AlertasRepositorio repositorio;
    private AdaptadorAlertas adaptador;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        repositorio = new AlertasRepositorio(this);
        txtToken = findViewById(R.id.txtToken);
        edtUsuario = findViewById(R.id.edtUsuario);
        edtUsuario.setText(AlertasRepositorio.leerUsuario(this));

        ListView listaAlertas = findViewById(R.id.listaAlertas);
        adaptador = new AdaptadorAlertas(this, new ArrayList<>());
        listaAlertas.setAdapter(adaptador);
        listaAlertas.setEmptyView(findViewById(R.id.txtBandejaVacia));
        listaAlertas.setOnItemClickListener((padre, vista, posicion, id) -> abrirDetalle(posicion));

        findViewById(R.id.btnRegistrar).setOnClickListener(v -> registrarDispositivo());
        findViewById(R.id.btnActualizar).setOnClickListener(v -> recargarBandeja());
        findViewById(R.id.btnLimpiar).setOnClickListener(v -> {
            repositorio.limpiar();
            recargarBandeja();
        });

        solicitarPermisoNotificaciones();
        mostrarToken();
    }

    @Override
    protected void onResume() {
        super.onResume();
        recargarBandeja();   // el push puede llegar con la app cerrada
    }

    /** Solicita el permiso de notificaciones requerido desde Android 13. */
    private void solicitarPermisoNotificaciones() {
        if (Build.VERSION.SDK_INT >= 33
                && ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
                        != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(
                    this,
                    new String[]{Manifest.permission.POST_NOTIFICATIONS},
                    CODIGO_PERMISO_NOTIFICACIONES);
        }
    }

    /** Obtiene el token FCM (FirebaseMessaging) y lo guarda localmente. */
    private void mostrarToken() {
        FirebaseMessaging.getInstance().getToken()
                .addOnSuccessListener(token -> {
                    AlertasRepositorio.guardarToken(MainActivity.this, token);
                    txtToken.setText(getString(R.string.token_actual, token));
                })
                .addOnFailureListener(error ->
                        txtToken.setText(R.string.token_no_disponible));
    }

    /** POST /api/v1/dispositivos: registra el token con usuario y rol. */
    private void registrarDispositivo() {
        String usuario = edtUsuario.getText().toString().trim();
        String token = AlertasRepositorio.leerToken(this);

        if (usuario.isEmpty()) {
            Toast.makeText(this, R.string.usuario_vacio, Toast.LENGTH_SHORT).show();
            return;
        }
        if (token == null) {
            Toast.makeText(this, R.string.token_ausente, Toast.LENGTH_SHORT).show();
            return;
        }

        AlertasRepositorio.guardarUsuario(this, usuario);
        Toast.makeText(this, R.string.registrando, Toast.LENGTH_SHORT).show();

        ApiClient.registrarDispositivo(usuario, "OFICIAL", token, (ok, mensaje) ->
                runOnUiThread(() -> {
                    Toast.makeText(
                            MainActivity.this,
                            ok ? R.string.registro_ok : R.string.registro_fallo,
                            Toast.LENGTH_LONG).show();
                    txtToken.setText(getString(R.string.token_actual, token)
                            + (ok ? " (registrado)" : " (sin registrar)"));
                }));
    }

    /** Vuelve a leer la bandeja local (alertas recibidas por FCM). */
    private void recargarBandeja() {
        adaptador.clear();
        adaptador.addAll(repositorio.listar());
        adaptador.notifyDataSetChanged();
    }

    private void abrirDetalle(int posicion) {
        ModeloAlerta alerta = adaptador.getItem(posicion);
        if (alerta == null) {
            return;
        }
        Intent intencion = new Intent(this, DetalleAlertaActivity.class);
        intencion.putExtra(NotificacionFcmService.EXTRA_ALERTA_JSON, alerta.aJsonSeguro());
        startActivity(intencion);
    }
}
