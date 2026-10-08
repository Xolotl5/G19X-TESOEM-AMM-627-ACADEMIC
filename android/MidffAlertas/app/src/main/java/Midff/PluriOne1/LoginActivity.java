package Midff.PluriOne1;

import android.content.Intent;
import android.os.Bundle;
import android.widget.Button;
import android.widget.EditText;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;

import com.google.firebase.auth.FirebaseAuth;
import com.google.firebase.auth.FirebaseUser;

/**
 * Acceso de clientes (BIT-015, D-47): Firebase Authentication con
 * correo y contraseña crea la identidad del cliente. Tras un acceso válido
 * la sesión queda persistida ({@link SesionCliente}) y
 * {@link MainActivity} exige el desbloqueo biométrico del dispositivo
 * (huella, rostro o PIN) en cada entrada.
 *
 * <p>Requiere habilitar el proveedor Correo/contraseña en
 * Firebase Console → Authentication → Sign-in method (ver README).</p>
 */
public class LoginActivity extends AppCompatActivity {

    private EditText edtCorreo;
    private EditText edtContrasena;
    private TextView txtEstado;
    private Button btnEntrar;
    private FirebaseAuth autenticacion;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        autenticacion = FirebaseAuth.getInstance();
        if (SesionCliente.estaActiva(this) && autenticacion.getCurrentUser() != null) {
            irAPrincipal();
            return;
        }

        setContentView(R.layout.activity_login);
        edtCorreo = findViewById(R.id.edtCorreo);
        edtContrasena = findViewById(R.id.edtContrasena);
        txtEstado = findViewById(R.id.txtEstadoLogin);
        btnEntrar = findViewById(R.id.btnEntrar);

        btnEntrar.setOnClickListener(v -> acceder(false));
        findViewById(R.id.btnCrearCuenta).setOnClickListener(v -> acceder(true));
    }

    /** entrar (crear=false) o crear cuenta (crear=true) contra Firebase Auth. */
    private void acceder(boolean crear) {
        String correo     = edtCorreo.getText().toString().trim();
        String contrasena = edtContrasena.getText().toString();

        if (correo.isEmpty() || !correo.contains("@")) {
            estado(getString(R.string.login_correo_invalido), true);
            edtCorreo.requestFocus();
            return;
        }
        if (contrasena.length() < 6) {
            estado(getString(R.string.login_contrasena_invalido), true);
            edtContrasena.requestFocus();
            return;
        }

        btnEntrar.setEnabled(false);
        estado(getString(R.string.login_en_progreso), false);

        if (crear) {
            autenticacion.createUserWithEmailAndPassword(correo, contrasena)
                    .addOnSuccessListener(resultado -> abrirSesion(resultado.getUser(), correo))
                    .addOnFailureListener(error -> fallo(error, true));
        } else {
            autenticacion.signInWithEmailAndPassword(correo, contrasena)
                    .addOnSuccessListener(resultado -> abrirSesion(resultado.getUser(), correo))
                    .addOnFailureListener(error -> fallo(error, false));
        }
    }

    private void abrirSesion(FirebaseUser usuario, String correo) {
        SesionCliente.abrir(this, correo, usuario != null ? usuario.getUid() : "");
        irAPrincipal();
    }

    private void fallo(@NonNull Exception error, boolean crear) {
        btnEntrar.setEnabled(true);
        estado(getString(crear
                ? R.string.login_error_creacion
                : R.string.login_error, error.getMessage()), false);
    }

    /** Mensaje inline: neutro (gris) mientras se procesa, magenta si es error. */
    private void estado(String mensaje, boolean neutro) {
        txtEstado.setText(mensaje);
        txtEstado.setTextColor(getColor(neutro
                ? R.color.texto_secundario
                : R.color.neon_magenta));
    }

    private void irAPrincipal() {
        startActivity(new Intent(this, MainActivity.class)
                .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK));
        finish();
    }
}