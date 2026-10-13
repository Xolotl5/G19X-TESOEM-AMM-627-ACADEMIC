package Midff.PluriOne1;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import java.util.Locale;

/**
 * Detalle de la alerta (RF-10), rediseñado glass/neón: score y montos en
 * cian neón con resplandor según la clasificación (cian APROBADA,
 * púrpura/magenta EN_REVISION o BLOQUEADA), analítica ponderada
 * (monto 45%, frecuencia 30%, ubicación IP 25%) con degradados aurora y
 * carrusel de dispositivos vinculados (DISPOSITIVO_MOVIL).
 *
 * <p>Sprint 4 (HU-MVP-06): el botón "Bloquear" (cápsula de cristal con
 * borde magenta neón) envía {@code POST /api/v1/bloquear} con el usuario
 * del oficial; Oracle persiste {@code ALERTA.estado = BLOQUEADA} más el
 * registro BLOQUEO en AUDITORIA, y la app refleja el estado en la bandeja.</p>
 *
 * <p>Recibe la alerta serializada en el extra
 * {@link NotificacionFcmService#EXTRA_ALERTA_JSON}.</p>
 */
public class DetalleAlertaActivity extends AppCompatActivity {

    private ModeloAlerta alerta;
    private Button botonNoFui;
    private Button botonSiFui;

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

        findViewById(R.id.btnRegresar).setOnClickListener(v -> finish());

        int colorNeon = colorNeonPorAlerta();
        int cian = getColor(R.color.neon_cian);

        TextView titulo = findViewById(R.id.txtDetalleTitulo);
        titulo.setText(alerta.tituloNotificacion());
        UtilidadesUi.resplandor(titulo, UtilidadesUi.colorGlow(colorNeon), 16f);

        TextView score = findViewById(R.id.txtValorScore);
        score.setText(alerta.score);
        score.setTextColor(colorNeon);
        UtilidadesUi.resplandor(score, UtilidadesUi.colorGlow(colorNeon), 22f);

        TextView clasificacion = findViewById(R.id.txtValorClasificacion);
        clasificacion.setText(alerta.clasificacionAmigable());
        clasificacion.setTextColor(colorNeon);
        UtilidadesUi.resplandor(clasificacion, UtilidadesUi.colorGlow(colorNeon), 12f);

        // El monto siempre se destaca en cian neón (dato clave del panel).
        TextView monto = findViewById(R.id.txtValorMonto);
        monto.setText(getString(R.string.formato_monto, alerta.monto, alerta.moneda));
        monto.setTextColor(cian);
        UtilidadesUi.resplandor(monto, UtilidadesUi.colorGlow(cian), 16f);

        ((TextView) findViewById(R.id.txtValorDecision)).setText(alerta.decisionAmigable());
        ((TextView) findViewById(R.id.txtValorCliente)).setText(alerta.idCliente);
        ((TextView) findViewById(R.id.txtValorTransaccion)).setText(alerta.idTransaccion);
        ((TextView) findViewById(R.id.txtValorAlerta)).setText(alerta.idAlerta);
        ((TextView) findViewById(R.id.txtValorFecha)).setText(alerta.fechaEvaluacion);
        ((TextView) findViewById(R.id.txtValorRecibida)).setText(alerta.fechaRecepcion());

        pintarEstado(findViewById(R.id.txtValorEstado));
        pintarAnalitica();
        poblarCarrusel();

        // BIT-020/024: el cliente responde a SU alerta. En nivel ALTO (o ya
        // bloqueada) solo se ofrece "SI FUI YO": al confirmarlo, la consola
        // habilita el DESBLOQUEO para el administrador.
        botonNoFui = findViewById(R.id.btnNoFuiYo);
        botonSiFui = findViewById(R.id.btnSiFuiYo);
        if (alerta.estaBloqueada()) {
            deshabilitarBotones(getString(R.string.bloqueo_ya));
        } else {
            botonNoFui.setOnClickListener(v -> reportar("NO_FUI_YO"));
            botonSiFui.setOnClickListener(v -> reportar("SI_FUI_YO"));
            if (alerta.esRiesgoAlto()) {
                botonNoFui.setVisibility(View.GONE);
            }
        }
    }

    /** Neón de la alerta: magenta BLOQUEADA/ALTO, cian APROBADA, púrpura resto. */
    private int colorNeonPorAlerta() {
        if (alerta.estaBloqueada() || alerta.esRiesgoAlto()) {
            return getColor(R.color.clasificacion_alto);
        }
        if (alerta.esAprobada()) {
            return getColor(R.color.clasificacion_bajo);
        }
        return getColor(R.color.clasificacion_medio);
    }

    /** Chip de estado con resplandor según clasificación. */
    private void pintarEstado(TextView estado) {
        int color = alerta.estaBloqueada()
                ? getColor(R.color.clasificacion_alto)
                : (alerta.esAprobada()
                        ? getColor(R.color.clasificacion_bajo)
                        : getColor(R.color.clasificacion_medio));
        estado.setText(alerta.estaBloqueada() ? "Bloqueada" : alerta.estadoAmigable());
        estado.setTextColor(color);
        UtilidadesUi.resplandor(estado, UtilidadesUi.colorGlow(color), 10f);
    }

    /** Analítica ponderada: monto 45%, frecuencia 30%, ubicación IP 25%. */
    private void pintarAnalitica() {
        double s = alerta.scoreValor();

        ProgressBar barraMonto = findViewById(R.id.barraMonto);
        barraMonto.setProgress((int) Math.round(s * 45d));
        ((TextView) findViewById(R.id.txtPesoMonto))
                .setText(String.format(Locale.US, "%.2f", s * 0.45d));

        ProgressBar barraFrecuencia = findViewById(R.id.barraFrecuencia);
        barraFrecuencia.setProgress((int) Math.round(s * 30d));
        ((TextView) findViewById(R.id.txtPesoFrecuencia))
                .setText(String.format(Locale.US, "%.2f", s * 0.30d));

        ProgressBar barraIp = findViewById(R.id.barraIp);
        barraIp.setProgress((int) Math.round(s * 25d));
        ((TextView) findViewById(R.id.txtPesoIp))
                .setText(String.format(Locale.US, "%.2f", s * 0.25d));
    }

    /** Carrusel de tarjetas de cristal: dispositivo actual y backend MIDFF. */
    private void poblarCarrusel() {
        LinearLayout carrusel = findViewById(R.id.carruselDispositivos);
        carrusel.removeAllViews();
        LayoutInflater inflador = LayoutInflater.from(this);

        String token = AlertasRepositorio.leerToken(this);
        String colaToken = token == null
                ? "pendiente"
                : "…" + token.substring(Math.max(0, token.length() - 8));

        View dispositivo = inflador.inflate(R.layout.item_dispositivo, carrusel, false);
        ((TextView) dispositivo.findViewById(R.id.txtDispTitulo))
                .setText(R.string.dispositivo_actual_titulo);
        ((TextView) dispositivo.findViewById(R.id.txtDispDetalle))
                .setText(getString(R.string.dispositivo_actual_detalle, colaToken));
        ((TextView) dispositivo.findViewById(R.id.txtDispEstado))
                .setText(R.string.estado_activo);
        carrusel.addView(dispositivo);

        View backend = inflador.inflate(R.layout.item_dispositivo, carrusel, false);
        ((TextView) backend.findViewById(R.id.txtDispTitulo))
                .setText(R.string.backend_titulo);
        ((TextView) backend.findViewById(R.id.txtDispDetalle))
                .setText(getString(R.string.backend_detalle, ApiClient.baseUrl));
        TextView estadoBackend = backend.findViewById(R.id.txtDispEstado);
        estadoBackend.setText(R.string.estado_enlazado);
        estadoBackend.setTextColor(getColor(R.color.neon_purpura));
        carrusel.addView(backend);
    }

    /**
     * Respuesta del cliente (BIT-020/024): POST /api/v1/reportar con su
     * id_cliente de sesion; el banco ve la bandera en la consola.
     *
     * <p>Fix ConnectException (prompt.txt): antes del POST se garantiza el
     * enlace con la API ({@link DescubrimientoApi#asegurar}); si la app se
     * abrio desde un push y la URL base no estaba resuelta (adb reverse
     * caido, cambio de red), el descubrimiento la corrige y solo entonces
     * se envia el reporte.</p>
     */
    private void reportar(String respuesta) {
        botonNoFui.setEnabled(false);
        botonSiFui.setEnabled(false);
        botonNoFui.setText(R.string.reportando);

        String idCliente = SesionCliente.leerIdCliente(this);
        if (idCliente.isEmpty()) {
            idCliente = alerta.idCliente;
        }
        final String cliente = idCliente;

        DescubrimientoApi.asegurar(this, (apiOk, url, motivo) -> {
            if (!apiOk) {
                runOnUiThread(() -> {
                    restaurarBotones();
                    Toast.makeText(this,
                            getString(R.string.api_no_detectada),
                            Toast.LENGTH_LONG).show();
                });
                return;
            }
            ApiClient.reportarAlerta(alerta.idAlerta, cliente, respuesta, null, (ok, mensaje) ->
                    runOnUiThread(() -> {
                        if (ok) {
                            deshabilitarBotones(getString(R.string.reporte_ya));
                            Toast.makeText(this, R.string.reporte_ok, Toast.LENGTH_LONG).show();
                        } else {
                            restaurarBotones();
                            Toast.makeText(this,
                                    getString(R.string.reporte_fallo, mensaje),
                                    Toast.LENGTH_LONG).show();
                        }
                    }));
        });
    }

    /** Restaura los botones a su estado original tras un fallo de envio. */
    private void restaurarBotones() {
        botonNoFui.setEnabled(true);
        botonSiFui.setEnabled(true);
        botonNoFui.setText(R.string.btn_no_fui_yo);
        botonSiFui.setText(R.string.btn_si_fui_yo);
        botonNoFui.setVisibility(alerta.esRiesgoAlto() ? View.GONE : View.VISIBLE);
    }

    private void deshabilitarBotones(String texto) {
        botonNoFui.setEnabled(false);
        botonSiFui.setEnabled(false);
        botonNoFui.setText(texto);
        botonSiFui.setOnClickListener(null);
        botonNoFui.setOnClickListener(null);
    }

    @Override
    protected void onResume() {
        super.onResume();
        refrescarEstadoDesdeBandeja();
    }

    /**
     * BIT-027: si el banco bloqueo o desbloqueo la alerta mientras este detalle
     * estaba abierto, el estado mostrado se refresca desde la bandeja local
     * (que el push ESTADO y la sincronizacion mantienen al dia con Oracle).
     */
    private void refrescarEstadoDesdeBandeja() {
        if (alerta == null || alerta.idAlerta == null) {
            return;
        }
        for (ModeloAlerta candidata : new AlertasRepositorio(this).listar()) {
            if (alerta.idAlerta.equals(candidata.idAlerta)) {
                if (!candidata.estado.equalsIgnoreCase(alerta.estado)) {
                    alerta.estado = candidata.estado;
                    pintarEstado(findViewById(R.id.txtValorEstado));
                }
                return;
            }
        }
    }
}
