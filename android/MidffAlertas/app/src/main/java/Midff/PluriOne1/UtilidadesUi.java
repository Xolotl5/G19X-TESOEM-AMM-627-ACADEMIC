package Midff.PluriOne1;

import android.animation.ObjectAnimator;
import android.animation.TimeInterpolator;
import android.graphics.LinearGradient;
import android.graphics.Shader;
import android.view.View;
import android.view.animation.AccelerateDecelerateInterpolator;
import android.widget.TextView;

import java.util.Locale;

/**
 * Utilidades visuales del rediseño glass/neón: texto con degradado aurora,
 * resplandores (glow) sobre texto, pulso de la isla dinámica e iniciales
 * del avatar.
 */
public final class UtilidadesUi {

    private UtilidadesUi() {
    }

    /**
     * Aplica un degradado lineal al texto (perfil de usuario azul-rosa).
     * Se aplica tras el layout para conocer el ancho real del TextView.
     */
    public static void textoDegradado(TextView vista, int colorDesde, int colorHasta) {
        vista.post(() -> {
            if (vista.getWidth() <= 0) {
                return;
            }
            vista.getPaint().setShader(new LinearGradient(
                    0f, 0f, vista.getWidth(), vista.getHeight(),
                    colorDesde, colorHasta, Shader.TileMode.CLAMP));
            vista.invalidate();
        });
    }

    /** Resplandor neón suave y difuso alrededor del texto (glow effect). */
    public static void resplandor(TextView vista, int color, float radio) {
        vista.setShadowLayer(radio, 0f, 0f, color);
    }

    /** Versión translúcida (alfa 40%) de un color para usarla como glow. */
    public static int colorGlow(int color) {
        return (color & 0x00FFFFFF) | 0x66000000;
    }

    /** Pulso suave e infinito (punto de estado de la isla dinámica). */
    public static void animarPulso(View vista) {
        TimeInterpolator suave = new AccelerateDecelerateInterpolator();
        ObjectAnimator pulso = ObjectAnimator.ofFloat(vista, View.ALPHA, 1f, 0.35f, 1f);
        pulso.setDuration(1400L);
        pulso.setRepeatCount(ObjectAnimator.INFINITE);
        pulso.setInterpolator(suave);
        pulso.start();
    }

    /** Iniciales del usuario para el avatar circular (p. ej. OFICIAL-01 = "O0"). */
    public static String iniciales(String texto) {
        if (texto == null || texto.trim().isEmpty()) {
            return "M";
        }
        String[] partes = texto.trim().split("[\\s._-]+");
        if (partes.length == 1) {
            return partes[0].substring(0, Math.min(2, partes[0].length()))
                    .toUpperCase(Locale.US);
        }
        return (partes[0].substring(0, 1) + partes[1].substring(0, 1))
                .toUpperCase(Locale.US);
    }
}