package Midff.PluriOne1;

import android.Manifest;
import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.app.Dialog;
import android.os.Build;
import android.os.Bundle;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.ListView;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;
import androidx.biometric.BiometricManager;
import androidx.biometric.BiometricPrompt;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

import com.google.firebase.auth.FirebaseAuth;
import com.google.firebase.messaging.FirebaseMessaging;

import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

/**
 * Bandeja de entrada de alertas del MIDFF (RF-10 / HU-MVP-05), rediseñada
 * con el lenguaje visual "glassmorphism de cristal ahumado con neón de
 * contraste profundo" y con navegación por PESTAÑAS INSTANTÁNEAS.
 *
 * <p>Responsabilidades:</p>
 * <ul>
 *   <li>Tres pestañas que se intercambian por visibilidad (sin scroll
 *       largo): Panel (riesgo global, volumen y críticas), Alertas (bandeja
 *       con filtros de primer nivel) y Registro (token FCM contra
 *       {@code POST /api/v1/dispositivos}, tabla DISPOSITIVO_MOVIL).</li>
 *   <li>Cabecera compacta siempre visible: avatar degradado (salta a
 *       Registro al tocarlo) e isla dinámica táctil ("Motor C · Escaneando")
 *       que refresca la bandeja al pulsarla.</li>
 *   <li>Feedback inmediato en cada acción: ripples, fundidos cortos,
 *       estados inline del registro, diálogo de confirmación para vaciar y
 *       copia del token al portapapeles.</li>
 * </ul>
 */
public class MainActivity extends AppCompatActivity {

    private static final int CODIGO_PERMISO_NOTIFICACIONES = 1001;

    /** Pestañas de la pantalla principal (cambio instantáneo). */
    private enum Pestana {
        PANEL, ALERTAS, REGISTRO
    }

    /** Filtros de primer nivel de la bandeja. */
    private enum Filtro {
        TODAS, APROBADAS, EN_REVISION, BLOQUEADAS, CRITICAS
    }

    private TextView txtToken;
    private TextView txtIslaEstado;
    private TextView txtRiesgoGlobal;
    private TextView txtVolumen;
    private TextView badgeCriticas;
    private TextView txtCriticasDetalle;
    private TextView txtEstadoDispositivo;
    private TextView txtEstadoRegistro;
    private EditText edtUsuario;
    private EditText edtIdCliente;
    private ListView listaAlertas;
    private View pestanaPanel;
    private View pestanaAlertas;
    private View pestanaRegistro;
    private View islaDinamica;
    private Button btnRegistrar;
    private AlertasRepositorio repositorio;
    private AdaptadorAlertas adaptador;
    private Pestana pestanaActual = Pestana.ALERTAS;
    private Filtro filtroActual = Filtro.TODAS;
    private List<ModeloAlerta> alertas = new ArrayList<>();
    private boolean registroEnCurso;
    private String urlBaseActual;
    private boolean desbloqueado;
    private Dialog dialogoBloqueo;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // BIT-015: sin sesión iniciada se manda al login (Firebase Auth).
        if (!SesionCliente.estaActiva(this)) {
            startActivity(new Intent(this, LoginActivity.class));
            finish();
            return;
        }

        setContentView(R.layout.activity_main);

        repositorio = new AlertasRepositorio(this);

        listaAlertas = findViewById(R.id.listaAlertas);
        adaptador = new AdaptadorAlertas(this, new ArrayList<>());
        listaAlertas.setAdapter(adaptador);
        listaAlertas.setEmptyView(findViewById(R.id.txtBandejaVacia));
        listaAlertas.setOnItemClickListener((padre, vista, posicion, id) -> abrirDetalle(posicion));

        pestanaPanel = findViewById(R.id.pestanaPanel);
        pestanaAlertas = findViewById(R.id.pestanaAlertas);
        pestanaRegistro = findViewById(R.id.pestanaRegistro);

        vincularCabecera();
        vincularNavegacion();
        vincularFiltros();
        vincularRegistro();
        // BIT-020: la app del cliente abre directo en SU bandeja; el panel de
        // métricas del banco vive ahora en la consola MIDFF.html.
        seleccionarPestana(Pestana.ALERTAS);

        solicitarPermisoNotificaciones();
        mostrarToken();
        ejecutarDescubrimiento(true);
        registroAutomatico();
        recargarBandeja();
    }

    @Override
    protected void onResume() {
        super.onResume();
        recargarBandeja();   // el push puede llegar con la app cerrada
        if (!desbloqueado) {
            pedirBiometria();   // cada entrada exige huella/rostro/PIN
        }
    }

    @Override
    protected void onStop() {
        super.onStop();
        desbloqueado = false;   // al volver a entrar se vuelve a bloquear
        if (dialogoBloqueo != null && dialogoBloqueo.isShowing()) {
            dialogoBloqueo.dismiss();
        }
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
    /** Cabecera: avatar (salta a Registro), usuario degradado, isla táctil. */
    private void vincularCabecera() {
        String usuario = AlertasRepositorio.leerUsuario(this);
        TextView txtUsuarioHeader = findViewById(R.id.txtUsuarioHeader);
        txtUsuarioHeader.setText(usuario);
        UtilidadesUi.textoDegradado(txtUsuarioHeader,
                getColor(R.color.aurora_azul), getColor(R.color.aurora_rosa));
        TextView avatar = findViewById(R.id.txtAvatarIniciales);
        avatar.setText(UtilidadesUi.iniciales(usuario));
        findViewById(R.id.contenedorAvatar).setOnClickListener(v -> {
            seleccionarPestana(Pestana.REGISTRO);
            edtUsuario.requestFocus();
        });

        islaDinamica = findViewById(R.id.islaDinamica);
        txtIslaEstado = findViewById(R.id.txtIslaEstado);
        UtilidadesUi.animarPulso(findViewById(R.id.puntoEstado));
        islaDinamica.setOnClickListener(v -> {
            rebote(islaDinamica);
            actualizarBandeja();
            ejecutarDescubrimiento(false);
        });

        txtRiesgoGlobal = findViewById(R.id.txtRiesgoGlobal);
        txtVolumen = findViewById(R.id.txtVolumen);
        txtCriticasDetalle = findViewById(R.id.txtCriticasDetalle);
        txtEstadoDispositivo = findViewById(R.id.txtEstadoDispositivo);
        badgeCriticas = findViewById(R.id.badgeCriticas);

        findViewById(R.id.btnVerCriticas).setOnClickListener(v -> {
            seleccionarPestana(Pestana.ALERTAS);
            seleccionarFiltro(Filtro.CRITICAS);
        });
        findViewById(R.id.btnIrRegistro).setOnClickListener(v ->
                seleccionarPestana(Pestana.REGISTRO));
    }

    /** Navegación inferior: cambio de pestaña instantáneo (sin scroll).
     *  BIT-020: el tab del Panel (métricas del banco) se oculta; esa lógica
     *  vive ahora en la consola MIDFF.html y la app queda solo cliente. */
    private void vincularNavegacion() {
        findViewById(R.id.navPanel).setVisibility(View.GONE);
        findViewById(R.id.navAlertas).setOnClickListener(v -> {
            if (pestanaActual == Pestana.ALERTAS) {
                listaAlertas.setSelection(0);   // 2.º toque: al inicio, al instante
            } else {
                seleccionarPestana(Pestana.ALERTAS);
            }
        });
        findViewById(R.id.navAjustes).setOnClickListener(v ->
                seleccionarPestana(Pestana.REGISTRO));
    }

    /** Muestra la pestaña elegida al instante con un fundido muy corto. */
    private void seleccionarPestana(Pestana pestana) {
        pestanaActual = pestana;
        mostrarPestana(pestanaPanel, pestana == Pestana.PANEL);
        mostrarPestana(pestanaAlertas, pestana == Pestana.ALERTAS);
        mostrarPestana(pestanaRegistro, pestana == Pestana.REGISTRO);
        marcarNavegacion(idDeNavegacion(pestana));
        if (pestana == Pestana.PANEL) {
            actualizarEstadisticas();   // el panel siempre entra al día
        }
    }

    private void mostrarPestana(View pestana, boolean visible) {
        if (visible) {
            pestana.setVisibility(View.VISIBLE);
            pestana.setAlpha(0f);
            pestana.animate().alpha(1f).setDuration(110L).start();
        } else {
            pestana.setVisibility(View.GONE);
        }
    }

    private int idDeNavegacion(Pestana pestana) {
        switch (pestana) {
            case ALERTAS:
                return R.id.navAlertas;
            case REGISTRO:
                return R.id.navAjustes;
            case PANEL:
            default:
                return R.id.navPanel;
        }
    }

    /** Pinta el ítem activo de la navegación con cian neón y resplandor. */
    private void marcarNavegacion(int idItemActivo) {
        int[][] items = {
                {R.id.navPanel, R.id.iconoNavPanel, R.id.etiquetaNavPanel},
                {R.id.navAlertas, R.id.iconoNavAlertas, R.id.etiquetaNavAlertas},
                {R.id.navAjustes, R.id.iconoNavAjustes, R.id.etiquetaNavAjustes}};
        for (int[] item : items) {
            boolean activo = item[0] == idItemActivo;
            findViewById(item[0])
                    .setBackgroundResource(activo ? R.drawable.resplandor_cian : 0);
            ((ImageView) findViewById(item[1])).setColorFilter(activo
                    ? getColor(R.color.neon_cian)
                    : getColor(R.color.texto_secundario));
            ((TextView) findViewById(item[2])).setTextColor(activo
                    ? getColor(R.color.neon_cian)
                    : getColor(R.color.texto_secundario));
        }
    }

    /** Pequeño rebote de escala como acuse táctil (isla dinámica). */
    private void rebote(View vista) {
        vista.animate().scaleX(1.06f).scaleY(1.06f).setDuration(90L)
                .withEndAction(() -> vista.animate()
                        .scaleX(1f).scaleY(1f).setDuration(90L).start())
                .start();
    }

    /**
     * Detección automática de la API (D-43): emulador, adb reverse o red WiFi
     * local; la isla dinámica informa el enlace encontrado.
     */
    private void ejecutarDescubrimiento(boolean mostrarBusqueda) {
        if (mostrarBusqueda) {
            txtIslaEstado.setText(R.string.isla_buscando);
        }
        DescubrimientoApi.asegurar(this, (ok, url, motivo) -> {
            urlBaseActual = ok ? url : null;
            txtIslaEstado.setText(ok
                    ? getString(R.string.isla_enlace, hostPuerto(url))
                    : getString(R.string.isla_sin_enlace));
            actualizarEstadisticas();
        });
    }

    /** "http://192.168.1.10:8091" -> "192.168.1.10:8091" (texto de la isla). */
    private static String hostPuerto(String url) {
        if (url == null) {
            return "";
        }
        return url.startsWith("http://") ? url.substring("http://".length()) : url;
    }

    // -----------------------------------------------------------------------
    // BIT-015: candado biométrico en cada entrada (huella, rostro o PIN)
    // -----------------------------------------------------------------------

    /** Muestra la pantalla de bloqueo y lanza el BiometricPrompt del sistema. */
    private void pedirBiometria() {
        desbloqueado = false;
        if (dialogoBloqueo == null) {
            dialogoBloqueo = new Dialog(this,
                    android.R.style.Theme_Black_NoTitleBar_Fullscreen);
            dialogoBloqueo.setContentView(R.layout.pantalla_bloqueo);
            dialogoBloqueo.setCancelable(false);
            dialogoBloqueo.findViewById(R.id.btnReintentarBloqueo)
                    .setOnClickListener(v -> lanzarPromptBiometrico());
        }
        if (!dialogoBloqueo.isShowing()) {
            dialogoBloqueo.show();
        }
        lanzarPromptBiometrico();
    }

    /**
     * Autenticadores permitidos: huella/rostro (BIOMETRIC_WEAK) y credenciales
     * del dispositivo (PIN/patrón, DEVICE_CREDENTIAL). En Android 9 o menor la
     * combinación no está soportada y se ofrece solo el biométrico con botón
     * negativo. Sin biometría configurada (p. ej. emulador limpio) no se
     * bloquea la demo: se informa en la isla dinámica.
     */
    private void lanzarPromptBiometrico() {
        BiometricManager gestor = BiometricManager.from(this);
        int puede = gestor.canAuthenticate(BiometricManager.Authenticators.BIOMETRIC_WEAK
                | BiometricManager.Authenticators.DEVICE_CREDENTIAL);
        if (puede != BiometricManager.BIOMETRIC_SUCCESS) {
            desbloquear();
            txtIslaEstado.setText(R.string.isla_sin_biometria);
            return;
        }

        // API de androidx.biometric 1.1.0: PromptInfo.Builder aun no expone
        // setAuthenticators(); la credencial del dispositivo (PIN/patron/
        // contrasena) se habilita con el flag clasico. Nota: con la credencial
        // habilitada NO puede fijarse setNegativeButtonText (excepcion).
        BiometricPrompt.PromptInfo.Builder info = new BiometricPrompt.PromptInfo.Builder()
                .setTitle(getString(R.string.bloqueo_titulo))
                .setSubtitle(getString(R.string.bloqueo_subtitulo))
                .setConfirmationRequired(false)
                .setDeviceCredentialAllowed(true);

        BiometricPrompt prompt = new BiometricPrompt(this,
                ContextCompat.getMainExecutor(this),
                new BiometricPrompt.AuthenticationCallback() {
                    @Override
                    public void onAuthenticationSucceeded(
                            @NonNull BiometricPrompt.AuthenticationResult resultado) {
                        desbloquear();
                    }

                    @Override
                    public void onAuthenticationError(int codigo, @NonNull CharSequence mensaje) {
                        // Queda bloqueado: el botón "Reintentar" vuelve a lanzar el prompt.
                    }
                });
        prompt.authenticate(info.build());
    }

    private void desbloquear() {
        desbloqueado = true;
        if (dialogoBloqueo != null && dialogoBloqueo.isShowing()) {
            dialogoBloqueo.dismiss();
        }
        actualizarIsla();
    }

    /** Cierra la sesión de Firebase y vuelve al login. */
    private void confirmarCerrarSesion() {
        new AlertDialog.Builder(this)
                .setTitle(R.string.confirmar_salir_titulo)
                .setMessage(R.string.confirmar_salir_mensaje)
                .setPositiveButton(R.string.accion_si, (dialogo, cual) -> {
                    FirebaseAuth.getInstance().signOut();
                    SesionCliente.cerrar(this);
                    startActivity(new Intent(this, LoginActivity.class)
                            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK
                                    | Intent.FLAG_ACTIVITY_CLEAR_TASK));
                    finish();
                })
                .setNegativeButton(R.string.accion_cancelar, null)
                .show();
    }
    /** Filtros de primer nivel de la bandeja (chips de cristal). */
    private void vincularFiltros() {
        findViewById(R.id.chipTodas)
                .setOnClickListener(v -> seleccionarFiltro(Filtro.TODAS));
        findViewById(R.id.chipAprobadas)
                .setOnClickListener(v -> seleccionarFiltro(Filtro.APROBADAS));
        findViewById(R.id.chipRevision)
                .setOnClickListener(v -> seleccionarFiltro(Filtro.EN_REVISION));
        findViewById(R.id.chipBloqueadas)
                .setOnClickListener(v -> seleccionarFiltro(Filtro.BLOQUEADAS));
        findViewById(R.id.chipCriticas)
                .setOnClickListener(v -> seleccionarFiltro(Filtro.CRITICAS));
    }

    /** Cambia el filtro activo y refresca la bandeja. */
    private void seleccionarFiltro(Filtro filtro) {
        filtroActual = filtro;
        pintarFiltros();
        aplicarFiltro();
    }

    /** Subrayado magenta + texto cian neón en el chip activo. */
    private void pintarFiltros() {
        pintarChip(R.id.lineaTodas, R.id.etiquetaTodas, filtroActual == Filtro.TODAS);
        pintarChip(R.id.lineaAprobadas, R.id.etiquetaAprobadas, filtroActual == Filtro.APROBADAS);
        pintarChip(R.id.lineaRevision, R.id.etiquetaRevision, filtroActual == Filtro.EN_REVISION);
        pintarChip(R.id.lineaBloqueadas, R.id.etiquetaBloqueadas, filtroActual == Filtro.BLOQUEADAS);
        findViewById(R.id.chipCriticas).setBackgroundResource(filtroActual == Filtro.CRITICAS
                ? R.drawable.btn_neon_magenta_ripple
                : R.drawable.chip_cristal_ripple);
    }

    private void pintarChip(int idLinea, int idEtiqueta, boolean activo) {
        findViewById(idLinea).setVisibility(activo ? View.VISIBLE : View.INVISIBLE);
        TextView etiqueta = findViewById(idEtiqueta);
        etiqueta.setTextColor(activo
                ? getColor(R.color.neon_cian)
                : getColor(R.color.texto_secundario));
        UtilidadesUi.resplandor(etiqueta, activo
                ? UtilidadesUi.colorGlow(getColor(R.color.neon_cian))
                : Color.TRANSPARENT, 10f);
    }
    /** Pestaña Registro: alta del dispositivo + acciones con feedback. */
    private void vincularRegistro() {
        txtToken = findViewById(R.id.txtToken);
        txtEstadoRegistro = findViewById(R.id.txtEstadoRegistro);
        edtUsuario = findViewById(R.id.edtUsuario);
        String correoSesion = SesionCliente.leerCorreo(this);
        edtUsuario.setText(correoSesion.isEmpty()
                ? AlertasRepositorio.leerUsuario(this)
                : correoSesion);

        // BIT-017: id_cliente que vincula el dispositivo al dueño de las
        // transacciones (se recuerda entre sesiones para no reescribirlo).
        edtIdCliente = findViewById(R.id.edtIdCliente);
        edtIdCliente.setText(SesionCliente.leerIdCliente(this));

        btnRegistrar = findViewById(R.id.btnRegistrar);
        btnRegistrar.setOnClickListener(v -> registrarDispositivo());
        findViewById(R.id.btnActualizar).setOnClickListener(v -> actualizarBandeja());
        findViewById(R.id.btnLimpiar).setOnClickListener(v -> confirmarLimpiar());
        findViewById(R.id.btnCopiarToken).setOnClickListener(v -> copiarToken());
        findViewById(R.id.btnCerrarSesion).setOnClickListener(v -> confirmarCerrarSesion());
    }

    /** BIT-020: registro silencioso del token con el id_cliente de la sesión
     *  en cada apertura (mantiene DISPOSITIVO_MOVIL al día sin intervención;
     *  el botón manual de la pestaña Perfil queda como respaldo). */
    private void registroAutomatico() {
        String token = AlertasRepositorio.leerToken(this);
        String idCliente = SesionCliente.leerIdCliente(this);
        String correo = SesionCliente.leerCorreo(this);
        if (token == null || idCliente.isEmpty()) {
            return;   // sin token o sesión incompleta: seguirá el registro manual
        }
        String usuario = !correo.isEmpty() ? correo : AlertasRepositorio.leerUsuario(this);
        DescubrimientoApi.asegurar(this, (apiOk, url, motivo) -> {
            if (apiOk) {
                urlBaseActual = url;
            }
            ApiClient.registrarDispositivo(usuario, "CLIENTE", idCliente, token, (ok, mensaje) ->
                    runOnUiThread(() -> {
                        if (ok) {
                            txtToken.setText(getString(R.string.token_actual, token)
                                    + " (registrado)");
                            txtEstadoRegistro.setText(R.string.estado_registro_ok);
                            txtEstadoRegistro.setTextColor(getColor(R.color.neon_cian));
                        }
                    }));
        });
    }

    /** POST /api/v1/dispositivos: registra el token con usuario, cliente y rol. */
    private void registrarDispositivo() {
        if (registroEnCurso) {
            return;   // ya hay una llamada en vuelo: se ignora el doble toque
        }
        String usuario = edtUsuario.getText().toString().trim();
        String idCliente = edtIdCliente.getText().toString().trim();
        String token = AlertasRepositorio.leerToken(this);

        if (usuario.isEmpty()) {
            Toast.makeText(this, R.string.usuario_vacio, Toast.LENGTH_SHORT).show();
            edtUsuario.requestFocus();
            return;
        }
        if (idCliente.isEmpty()) {
            Toast.makeText(this, R.string.cliente_vacio, Toast.LENGTH_SHORT).show();
            edtIdCliente.requestFocus();
            return;
        }
        if (token == null) {
            Toast.makeText(this, R.string.token_ausente, Toast.LENGTH_SHORT).show();
            return;
        }

        registroEnCurso = true;
        AlertasRepositorio.guardarUsuario(this, usuario);
        SesionCliente.guardarIdCliente(this, idCliente);
        btnRegistrar.setEnabled(false);
        btnRegistrar.setText(R.string.registrando);
        txtEstadoRegistro.setTextColor(getColor(R.color.texto_secundario));
        txtEstadoRegistro.setText(R.string.registrando);

        // Se garantiza el enlace con la API antes del POST: si el modo de
        // ejecución cambió (emulador/USB/WiFi) el descubrimiento lo resuelve.
        DescubrimientoApi.asegurar(this, (apiOk, url, motivo) -> {
            if (!apiOk) {
                runOnUiThread(() -> {
                    registroEnCurso = false;
                    btnRegistrar.setEnabled(true);
                    btnRegistrar.setText(R.string.btn_registrar);
                    txtEstadoRegistro.setText(R.string.api_no_detectada);
                    txtEstadoRegistro.setTextColor(getColor(R.color.neon_magenta));
                });
                return;
            }
            urlBaseActual = url;
            ApiClient.registrarDispositivo(usuario, "CLIENTE", idCliente, token, (ok, mensaje) ->
                    runOnUiThread(() -> {
                        registroEnCurso = false;
                        btnRegistrar.setEnabled(true);
                        btnRegistrar.setText(R.string.btn_registrar);
                        txtToken.setText(getString(R.string.token_actual, token)
                                + (ok ? " (registrado)" : " (sin registrar)"));
                        txtEstadoRegistro.setText(ok
                                ? getString(R.string.estado_registro_ok)
                                : getString(R.string.estado_registro_fallo, mensaje));
                        txtEstadoRegistro.setTextColor(getColor(ok
                                ? R.color.neon_cian
                                : R.color.neon_magenta));
                        if (ok) {
                            txtIslaEstado.setText(getString(
                                    R.string.isla_enlace, hostPuerto(url)));
                            actualizarEstadisticas();
                        }
                    }));
        });
    }

    /** Refresca la bandeja con acuse visible (isla + parpadeo de la lista). */
    private void actualizarBandeja() {
        recargarBandeja();
        txtIslaEstado.setText(getString(R.string.bandeja_actualizada, alertas.size()));
        listaAlertas.animate().alpha(0.15f).setDuration(90L)
                .withEndAction(() -> listaAlertas.animate().alpha(1f).setDuration(120L).start())
                .start();
    }

    /** Vaciar la bandeja local pide confirmación (Oracle no se toca). */
    private void confirmarLimpiar() {
        new AlertDialog.Builder(this)
                .setTitle(R.string.confirmar_limpiar_titulo)
                .setMessage(R.string.confirmar_limpiar_mensaje)
                .setPositiveButton(R.string.accion_vaciar, (dialogo, cual) -> {
                    repositorio.limpiar();
                    recargarBandeja();
                })
                .setNegativeButton(R.string.accion_cancelar, null)
                .show();
    }

    /** Copia el token FCM al portapapeles del oficial. */
    private void copiarToken() {
        String token = AlertasRepositorio.leerToken(this);
        if (token == null) {
            Toast.makeText(this, R.string.token_ausente, Toast.LENGTH_SHORT).show();
            return;
        }
        ClipboardManager portapapeles =
                (ClipboardManager) getSystemService(Context.CLIPBOARD_SERVICE);
        if (portapapeles != null) {
            portapapeles.setPrimaryClip(
                    ClipData.newPlainText("Token FCM MIDFF", token));
            Toast.makeText(this, R.string.token_copiado, Toast.LENGTH_SHORT).show();
        }
    }
    /** Obtiene el token FCM (FirebaseMessaging) y lo guarda localmente. */
    private void mostrarToken() {
        FirebaseMessaging.getInstance().getToken()
                .addOnSuccessListener(token -> {
                    AlertasRepositorio.guardarToken(MainActivity.this, token);
                    txtToken.setText(getString(R.string.token_actual, token));
                    actualizarIsla();
                    actualizarEstadisticas();
                })
                .addOnFailureListener(error -> {
                    txtToken.setText(R.string.token_no_disponible);
                    txtIslaEstado.setText(R.string.isla_sin_enlace);
                });
    }

    /** Vuelve a leer la bandeja local (alertas recibidas por FCM). */
    private void recargarBandeja() {
        alertas = repositorio.listar();
        aplicarFiltro();
        actualizarEstadisticas();
    }

    /** Aplica el filtro activo sobre la bandeja completa. */
    private void aplicarFiltro() {
        ArrayList<ModeloAlerta> visibles = new ArrayList<>();
        for (ModeloAlerta alerta : alertas) {
            if (coincideConFiltro(alerta)) {
                visibles.add(alerta);
            }
        }
        adaptador.clear();
        adaptador.addAll(visibles);
        adaptador.notifyDataSetChanged();
    }

    private boolean coincideConFiltro(ModeloAlerta alerta) {
        switch (filtroActual) {
            case APROBADAS:
                return alerta.esAprobada();
            case EN_REVISION:
                return alerta.enRevision();
            case BLOQUEADAS:
                return alerta.estaBloqueada();
            case CRITICAS:
                return alerta.esRiesgoAlto();
            case TODAS:
            default:
                return true;
        }
    }

    /**
     * Estadísticas de la pestaña Panel: riesgo global (score máximo),
     * volumen monitoreado (suma de montos), badge y conteos por filtro
     * (visibles en los propios chips de la pestaña Alertas).
     */
    private void actualizarEstadisticas() {
        double scoreMaximo = 0d;
        double volumen = 0d;
        int criticas = 0;
        int aprobadas = 0;
        int enRevision = 0;
        int bloqueadas = 0;
        for (ModeloAlerta alerta : alertas) {
            scoreMaximo = Math.max(scoreMaximo, alerta.scoreValor());
            if (alerta.esRiesgoAlto()) {
                criticas++;
            }
            if (alerta.esAprobada()) {
                aprobadas++;
            } else if (alerta.estaBloqueada()) {
                bloqueadas++;
            } else {
                enRevision++;
            }
            try {
                volumen += Double.parseDouble(
                        alerta.monto.replace(",", "").replace("$", ""));
            } catch (Exception ignorado) {
                // monto no numérico: no suma al volumen
            }
        }
        txtRiesgoGlobal.setText(String.format(Locale.US, "%.2f", scoreMaximo));
        txtVolumen.setText(String.format(Locale.US, "%.2f", volumen));
        badgeCriticas.setText(String.valueOf(criticas));
        badgeCriticas.setVisibility(criticas > 0 ? View.VISIBLE : View.GONE);
        txtCriticasDetalle.setText(getString(R.string.panel_criticas_detalle, criticas));
        txtEstadoDispositivo.setText(urlBaseActual != null
                ? getString(R.string.api_resuelta, urlBaseActual)
                : getText(R.string.isla_buscando));

        ((TextView) findViewById(R.id.etiquetaTodas))
                .setText(getString(R.string.filtro_todas) + " · " + alertas.size());
        ((TextView) findViewById(R.id.etiquetaAprobadas))
                .setText(getString(R.string.filtro_aprobadas) + " · " + aprobadas);
        ((TextView) findViewById(R.id.etiquetaRevision))
                .setText(getString(R.string.filtro_revision) + " · " + enRevision);
        ((TextView) findViewById(R.id.etiquetaBloqueadas))
                .setText(getString(R.string.filtro_bloqueadas) + " · " + bloqueadas);
        actualizarIsla();
    }

    /** Isla dinámica: estado del sistema con el conteo de alertas. */
    private void actualizarIsla() {
        txtIslaEstado.setText(getString(R.string.isla_escaneando, alertas.size()));
    }

    private void abrirDetalle(int posicion) {
        if (posicion < 0 || posicion >= adaptador.getCount()) {
            return;
        }
        ModeloAlerta alerta = adaptador.getItem(posicion);
        if (alerta == null) {
            return;
        }
        Intent intencion = new Intent(this, DetalleAlertaActivity.class);
        intencion.putExtra(NotificacionFcmService.EXTRA_ALERTA_JSON, alerta.aJsonSeguro());
        startActivity(intencion);
    }
}