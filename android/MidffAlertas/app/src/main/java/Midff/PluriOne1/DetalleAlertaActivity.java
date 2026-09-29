package Midff.PluriOne1;

import android.os.Bundle;
import android.widget.Button;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

/**
 * Detalle de la alerta (RF-10): monto, score y clasificacion de la
 * transaccion que disparo el push, con su trazabilidad hacia Oracle
 * (id de transaccion, id de alerta, decision y fecha de evaluacion).
 *
 * <p>Sprint 4 (HU-MVP-06): el boton "Bloquear" envia
 * {@code POST /api/v1/bloquear} con el usuario del oficial registrado en
 * {@link AlertasRepositorio}; Oracle persiste {@code ALERTA.estado =
 * BLOQUEADA} mas el registro BLOQUEO en AUDITORIA (usuario y timestamp),
 * y la app refleja el estado en la bandeja local.</p>
 *
 * <p>Recibe la alerta serializada en el extra
 * {@link NotificacionFcmService#EXTRA_ALERTA_JSON}.</p>
 */
public class DetalleAlertaActivity extends AppCompatActivity {

    private ModeloAlerta alerta;
    private Button botonBloquear;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_detalle_alerta);

        alerta = ModeloAlerta.desdeJson(
                getIntent().getStringExtra(NotificacionFcmService.EXTRA_ALERTA_JSON));
        if (alerta == null) {
            finish();
            return;
        }

        TextView titulo = findViewById(R.id.txtDetalleTitulo);
        titulo.setText(alerta.tituloNotificacion());
        titulo.setTextColor(alerta.esRiesgoAlto()
                ? getColor(R.color.clasificacion_alto)
                : getColor(R.color.clasificacion_medio));

        ((TextView) findViewById(R.id.txtValorMonto))
                .setText(getString(R.string.formato_monto, alerta.monto, alerta.moneda));
        ((TextView) findViewById(R.id.txtValorScore)).setText(alerta.score);

        TextView clasificacion = findViewById(R.id.txtValorClasificacion);
        clasificacion.setText(alerta.clasificacion);
        clasificacion.setTextColor(alerta.esRiesgoAlto()
                ? getColor(R.color.clasificacion_alto)
                : getColor(R.color.clasificacion_medio));

        ((TextView) findViewById(R.id.txtValorDecision)).setText(alerta.decision);
        ((TextView) findViewById(R.id.txtValorCliente)).setText(alerta.idCliente);
        ((TextView) findViewById(R.id.txtValorTransaccion)).setText(alerta.idTransaccion);
        ((TextView) findViewById(R.id.txtValorAlerta)).setText(alerta.idAlerta);
        ((TextView) findViewById(R.id.txtValorFecha)).setText(alerta.fechaEvaluacion);
        ((TextView) findViewById(R.id.txtValorRecibida)).setText(alerta.fechaRecepcion());

        TextView estado = findViewById(R.id.txtValorEstado);
        estado.setText(alerta.estaBloqueada() ? "BLOQUEADA" : alerta.estado);
        estado.setTextColor(alerta.estaBloqueada()
                ? getColor(R.color.clasificacion_alto)
                : getColor(R.color.clasificacion_medio));

        botonBloquear = findViewById(R.id.btnBloquear);
        if (alerta.estaBloqueada()) {
            deshabilitarBoton(getString(R.string.bloqueo_ya));
        } else {
            botonBloquear.setOnClickListener(v -> bloquear());
        }
    }

    /**
     * Accion "Bloquear" (HU-MVP-06): POST /api/v1/bloquear contra la API MIDFF.
     * El callback llega en el hilo de red, por lo que la vista se toca via
     * runOnUiThread.
     */
    private void bloquear() {
        botonBloquear.setEnabled(false);
        botonBloquear.setText(R.string.bloqueando);

        String usuario = AlertasRepositorio.leerUsuario(this);
        ApiClient.bloquearAlerta(alerta.idAlerta, usuario, null, (ok, mensaje) ->
                runOnUiThread(() -> {
                    if (ok) {
                        alerta.estado = "BLOQUEADA";
                        TextView estado = findViewById(R.id.txtValorEstado);
                        estado.setText("BLOQUEADA");
                        estado.setTextColor(getColor(R.color.clasificacion_alto));
                        new AlertasRepositorio(this).marcarBloqueada(alerta.idAlerta);
                        deshabilitarBoton(getString(R.string.bloqueo_ya));
                        Toast.makeText(this, R.string.bloqueo_ok, Toast.LENGTH_LONG).show();
                    } else {
                        botonBloquear.setEnabled(true);
                        botonBloquear.setText(R.string.btn_bloquear);
                        Toast.makeText(this,
                                getString(R.string.bloqueo_fallo) + "\n" + mensaje,
                                Toast.LENGTH_LONG).show();
                    }
                }));
    }

    private void deshabilitarBoton(String texto) {
        botonBloquear.setEnabled(false);
        botonBloquear.setText(texto);
        botonBloquear.setOnClickListener(null);
    }
}
