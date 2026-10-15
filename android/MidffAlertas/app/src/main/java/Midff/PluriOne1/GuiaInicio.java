package Midff.PluriOne1;

import android.app.Activity;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.appcompat.app.AlertDialog;

/**
 * Guía de bienvenida interactiva (BIT-033): asistente de 5 pasos navegable,
 * con indicador de progreso (puntos) y una mini-demostración visual en cada
 * paso (puntos de riesgo, monto protagonista, botones de respuesta, ciclo
 * bloqueada→aprobada y nombre propio). Construida 100% por código —sin
 * layouts XML nuevos ni dependencias extra— con el lenguaje visual de la app:
 * cristal oscuro, tipografía blanca y los acentos por clasificación
 * (verde/ámbar/rojo en tonos oscuros, BIT-028).
 */
public final class GuiaInicio {

    private static final int TOTAL_PASOS = 5;
    private static final int MATCH = ViewGroup.LayoutParams.MATCH_PARENT;
    private static final int WRAP = ViewGroup.LayoutParams.WRAP_CONTENT;

    private final Activity actividad;
    private final TextView titulo;
    private final TextView descripcion;
    private final LinearLayout ilustracion;
    private final LinearLayout puntos;
    private final TextView botonAnterior;
    private final TextView botonSiguiente;
    private AlertDialog dialogo;
    private int paso = 0;

    private GuiaInicio(Activity actividad) {
        this.actividad = actividad;

        LinearLayout contenido = new LinearLayout(actividad);
        contenido.setOrientation(LinearLayout.VERTICAL);
        contenido.setPadding(dp(24), dp(22), dp(24), dp(10));

        ilustracion = new LinearLayout(actividad);
        ilustracion.setOrientation(LinearLayout.VERTICAL);
        ilustracion.setGravity(Gravity.CENTER_HORIZONTAL);
        LinearLayout.LayoutParams pIl = new LinearLayout.LayoutParams(MATCH, WRAP);
        pIl.bottomMargin = dp(16);
        contenido.addView(ilustracion, pIl);

        titulo = new TextView(actividad);
        titulo.setTextSize(20f);
        titulo.setTypeface(Typeface.DEFAULT_BOLD);
        titulo.setTextColor(0xFFFFFFFF);
        titulo.setGravity(Gravity.CENTER);
        contenido.addView(titulo, new LinearLayout.LayoutParams(MATCH, WRAP));

        descripcion = new TextView(actividad);
        descripcion.setTextSize(14f);
        descripcion.setTextColor(0xFFA0A0AB);
        descripcion.setGravity(Gravity.CENTER);
        descripcion.setLineSpacing(dp(3), 1f);
        LinearLayout.LayoutParams pDesc = new LinearLayout.LayoutParams(MATCH, WRAP);
        pDesc.topMargin = dp(10);
        contenido.addView(descripcion, pDesc);

        puntos = new LinearLayout(actividad);
        puntos.setGravity(Gravity.CENTER_HORIZONTAL);
        LinearLayout.LayoutParams pPuntos = new LinearLayout.LayoutParams(MATCH, WRAP);
        pPuntos.topMargin = dp(18);
        contenido.addView(puntos, pPuntos);

        LinearLayout botonera = new LinearLayout(actividad);
        botonera.setOrientation(LinearLayout.HORIZONTAL);
        LinearLayout.LayoutParams pBotonera = new LinearLayout.LayoutParams(MATCH, WRAP);
        pBotonera.topMargin = dp(14);
        contenido.addView(botonera, pBotonera);

        botonAnterior = textoBoton(R.string.guia_anterior, false);
        botonAnterior.setOnClickListener(v -> cambiarPaso(-1));
        botonSiguiente = textoBoton(R.string.guia_siguiente, true);
        botonSiguiente.setOnClickListener(v -> {
            if (paso >= TOTAL_PASOS - 1) {
                dialogo.dismiss();
            } else {
                cambiarPaso(+1);
            }
        });
        LinearLayout.LayoutParams pAnterior = new LinearLayout.LayoutParams(0, WRAP, 1f);
        pAnterior.setMarginEnd(dp(10));
        botonera.addView(botonAnterior, pAnterior);
        botonera.addView(botonSiguiente, new LinearLayout.LayoutParams(0, WRAP, 1.3f));

        dialogo = new AlertDialog.Builder(actividad)
                .setView(contenido)
                .setCancelable(true)
                .create();
    }

    /** Abre el asistente desde el principio (posición 0). */
    public static void mostrar(Activity actividad) {
        GuiaInicio guia = new GuiaInicio(actividad);
        guia.mostrarPaso();
        guia.dialogo.show();
    }

    /* --------------------------- pasos del asistente ------------------------ */

    private void cambiarPaso(int delta) {
        paso = Math.max(0, Math.min(TOTAL_PASOS - 1, paso + delta));
        mostrarPaso();
    }

    private void mostrarPaso() {
        int[] titulos = {R.string.guia_p1_titulo, R.string.guia_p2_titulo,
                R.string.guia_p3_titulo, R.string.guia_p4_titulo, R.string.guia_p5_titulo};
        int[] descripciones = {R.string.guia_p1_desc, R.string.guia_p2_desc,
                R.string.guia_p3_desc, R.string.guia_p4_desc, R.string.guia_p5_desc};

        titulo.setText(titulos[paso]);
        descripcion.setText(descripciones[paso]);
        botonAnterior.setVisibility(paso == 0 ? View.INVISIBLE : View.VISIBLE);
        botonSiguiente.setText(paso == TOTAL_PASOS - 1
                ? R.string.guia_empezar : R.string.guia_siguiente);

        ilustracion.removeAllViews();
        ilustracion.addView(construirIlustracion());
        pintarPuntos();

        // Transición suave entre pasos (fundido de la lámina).
        ilustracion.setAlpha(0f);
        ilustracion.animate().alpha(1f).setDuration(240L).start();
    }

    private void pintarPuntos() {
        puntos.removeAllViews();
        for (int i = 0; i < TOTAL_PASOS; i++) {
            View punto = new View(actividad);
            GradientDrawable circulo = new GradientDrawable();
            circulo.setShape(GradientDrawable.OVAL);
            boolean activo = i == paso;
            circulo.setColor(activo ? 0xFFFFFFFF : 0x55FFFFFF);
            punto.setBackground(circulo);
            LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(
                    activo ? dp(9) : dp(7), activo ? dp(9) : dp(7));
            lp.setMarginEnd(dp(5));
            puntos.addView(punto, lp);
        }
    }

    /** Lámina visual de cada paso (demostración mínima del concepto). */
    private View construirIlustracion() {
        LinearLayout lamina = new LinearLayout(actividad);
        lamina.setOrientation(LinearLayout.VERTICAL);
        lamina.setGravity(Gravity.CENTER_HORIZONTAL);

        switch (paso) {
            case 0:   // Bandeja: puntos de riesgo por color.
                filaRiesgo(lamina, R.color.clasificacion_bajo, "Aprobada · $ 850.00");
                filaRiesgo(lamina, R.color.clasificacion_medio, "Por revisar · $ 3,600.00");
                filaRiesgo(lamina, R.color.clasificacion_alto, "Posible fraude · $ 15,499.00");
                break;
            case 1: { // Monto protagonista.
                lamina.addView(textoGrande("$ 15,499.00 MXN", R.color.clasificacion_alto));
                TextView pie = textoChico("El score (0.95) queda en el detalle de la tarjeta");
                LinearLayout.LayoutParams pPie = new LinearLayout.LayoutParams(WRAP, WRAP);
                pPie.topMargin = dp(6);
                lamina.addView(pie, pPie);
                break;
            }
            case 2:   // Respuestas del cliente.
                lamina.addView(chip("⛔ No fui yo — avisa al banco", R.color.clasificacion_alto));
                lamina.addView(chip("✓ Sí fui yo — la apruebo", R.color.clasificacion_bajo));
                break;
            case 3: { // Riesgo alto: bloqueada → aprobada.
                lamina.addView(chip("Riesgo alto · Bloqueada", R.color.clasificacion_alto));
                TextView flecha = textoGrande("↓", R.color.neon_cian);
                LinearLayout.LayoutParams pFlecha = new LinearLayout.LayoutParams(WRAP, WRAP);
                pFlecha.topMargin = dp(2);
                lamina.addView(flecha, pFlecha);
                lamina.addView(chip("Riesgo alto · Aprobada", R.color.clasificacion_bajo));
                TextView pie = textoChico("Confirma «Sí fui yo» y el banco la desbloquea");
                LinearLayout.LayoutParams pPie = new LinearLayout.LayoutParams(WRAP, WRAP);
                pPie.topMargin = dp(8);
                lamina.addView(pie, pPie);
                break;
            }
            default:   // Nombre propio: avatar de ejemplo.
                TextView avatar = new TextView(actividad);
                avatar.setText("TÚ");
                avatar.setGravity(Gravity.CENTER);
                avatar.setTypeface(Typeface.DEFAULT_BOLD);
                avatar.setTextColor(0xFF0B0C0E);
                avatar.setTextSize(16f);
                GradientDrawable circulo = new GradientDrawable();
                circulo.setShape(GradientDrawable.OVAL);
                circulo.setColor(0xFFC9CED5);
                avatar.setBackground(circulo);
                LinearLayout.LayoutParams pAvatar = new LinearLayout.LayoutParams(dp(58), dp(58));
                pAvatar.bottomMargin = dp(8);
                lamina.addView(avatar, pAvatar);
                lamina.addView(textoChico("Toca tu nombre (arriba a la izquierda) y elige cómo llamarte"));
                break;
        }
        return lamina;
    }

    /* ------------------------------ helpers UI ------------------------------ */

    private void filaRiesgo(LinearLayout padre, int colorRes, String texto) {
        LinearLayout fila = new LinearLayout(actividad);
        fila.setOrientation(LinearLayout.HORIZONTAL);
        fila.setGravity(Gravity.CENTER_VERTICAL);

        View punto = new View(actividad);
        GradientDrawable circulo = new GradientDrawable();
        circulo.setShape(GradientDrawable.OVAL);
        circulo.setColor(actividad.getColor(colorRes));
        punto.setBackground(circulo);
        LinearLayout.LayoutParams pPunto = new LinearLayout.LayoutParams(dp(11), dp(11));
        pPunto.setMarginEnd(dp(10));
        fila.addView(punto, pPunto);

        TextView etiqueta = new TextView(actividad);
        etiqueta.setText(texto);
        etiqueta.setTextSize(14f);
        etiqueta.setTextColor(0xFFE9EBEE);
        fila.addView(etiqueta, new LinearLayout.LayoutParams(WRAP, WRAP));

        LinearLayout.LayoutParams pFila = new LinearLayout.LayoutParams(WRAP, WRAP);
        pFila.bottomMargin = dp(8);
        padre.addView(fila, pFila);
    }

    private TextView textoGrande(String texto, int colorRes) {
        TextView v = new TextView(actividad);
        v.setText(texto);
        v.setTextSize(24f);
        v.setTypeface(Typeface.DEFAULT_BOLD);
        v.setTextColor(actividad.getColor(colorRes));
        v.setGravity(Gravity.CENTER);
        return v;
    }

    private TextView textoChico(String texto) {
        TextView v = new TextView(actividad);
        v.setText(texto);
        v.setTextSize(12f);
        v.setTextColor(0xFF8A8A95);
        v.setGravity(Gravity.CENTER);
        return v;
    }

    /** Chip de cristal con borde del color indicado (como los de la bandeja). */
    private TextView chip(String texto, int colorRes) {
        TextView v = new TextView(actividad);
        v.setText(texto);
        v.setTextSize(13f);
        v.setTypeface(Typeface.DEFAULT_BOLD);
        v.setTextColor(actividad.getColor(colorRes));
        v.setGravity(Gravity.CENTER);
        v.setPadding(dp(16), dp(9), dp(16), dp(9));
        GradientDrawable fondo = new GradientDrawable();
        fondo.setShape(GradientDrawable.RECTANGLE);
        fondo.setCornerRadius(dp(18));
        fondo.setColor(0x2617181C);
        fondo.setStroke(dp(1), actividad.getColor(colorRes));
        v.setBackground(fondo);
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(WRAP, WRAP);
        p.bottomMargin = dp(8);
        v.setLayoutParams(p);
        return v;
    }

    private TextView textoBoton(int textoRes, boolean primario) {
        TextView v = new TextView(actividad);
        v.setText(textoRes);
        v.setGravity(Gravity.CENTER);
        v.setAllCaps(false);
        v.setPadding(dp(16), dp(11), dp(16), dp(11));
        v.setTypeface(Typeface.DEFAULT_BOLD);
        v.setTextSize(14f);
        GradientDrawable fondo = new GradientDrawable();
        fondo.setShape(GradientDrawable.RECTANGLE);
        fondo.setCornerRadius(dp(22));
        if (primario) {
            v.setTextColor(0xFF0B0C0E);
            fondo.setColor(0xFFFFFFFF);
        } else {
            v.setTextColor(0xFF9BA1AA);
            fondo.setColor(0x00000000);
            fondo.setStroke(dp(1), 0x33FFFFFF);
        }
        v.setBackground(fondo);
        return v;
    }

    private int dp(int valor) {
        return (int) (valor * actividad.getResources().getDisplayMetrics().density);
    }
}
