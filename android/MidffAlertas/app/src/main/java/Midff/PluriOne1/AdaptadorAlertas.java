package Midff.PluriOne1;

import android.content.Context;
import android.graphics.drawable.GradientDrawable;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ArrayAdapter;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import java.util.List;

/**
 * Adaptador de la bandeja de alertas (RF-10): une la lista de
 * {@link ModeloAlerta} con el panel de cristal {@code item_alerta}.
 *
 * <p>BIT-028 (modismos entendibles): el protagonista de la tarjeta es el
 * MONTO de la transacción (el score baja al detalle), el chip dice "Riesgo
 * alto/medio/bajo · Bloqueada" y el punto, el chip y el resplandor usan la
 * semántica rojo/ámbar/verde en tonos oscuros que combinan con la estética
 * monocroma de la app.</p>
 */
public class AdaptadorAlertas extends ArrayAdapter<ModeloAlerta> {

    public AdaptadorAlertas(Context contexto, List<ModeloAlerta> alertas) {
        super(contexto, R.layout.item_alerta, alertas);
    }

    @NonNull
    @Override
    public View getView(int posicion, @Nullable View vista, @NonNull ViewGroup padre) {
        Vista vistaHolder;

        if (vista == null) {
            vista = LayoutInflater.from(getContext()).inflate(R.layout.item_alerta, padre, false);
            vistaHolder = new Vista();
            vistaHolder.chipClasificacion = vista.findViewById(R.id.chipClasificacion);
            vistaHolder.puntoRiesgo = vista.findViewById(R.id.puntoRiesgo);
            vistaHolder.monto = vista.findViewById(R.id.txtItemMonto);
            vistaHolder.titulo = vista.findViewById(R.id.txtItemTitulo);
            vistaHolder.motivo = vista.findViewById(R.id.txtItemMotivo);
            vistaHolder.detalle = vista.findViewById(R.id.txtItemDetalle);
            vistaHolder.fecha = vista.findViewById(R.id.txtItemFecha);
            vista.setTag(vistaHolder);
        } else {
            vistaHolder = (Vista) vista.getTag();
        }

        ModeloAlerta alerta = getItem(posicion);
        if (alerta != null) {
            int colorBanda = colorSegunClasificacion(alerta.clasificacion);

            vistaHolder.chipClasificacion.setText(alerta.estaBloqueada()
                    ? alerta.clasificacionAmigable() + " · Bloqueada"
                    : (alerta.esAprobada() && !alerta.esSana()
                            ? alerta.clasificacionAmigable() + " · Aprobada"
                            : alerta.clasificacionAmigable()));
            vistaHolder.chipClasificacion.setTextColor(colorBanda);
            UtilidadesUi.resplandor(vistaHolder.chipClasificacion,
                    UtilidadesUi.colorGlow(colorBanda), 10f);

            // BIT-028: el monto ocupa el lugar del score (dato que el usuario
            // entiende de inmediato); el score queda en el detalle de la tarjeta.
            vistaHolder.monto.setText(alerta.montoFormateado());
            vistaHolder.monto.setTextColor(colorBanda);
            UtilidadesUi.resplandor(vistaHolder.monto,
                    UtilidadesUi.colorGlow(colorBanda), 14f);

            vistaHolder.titulo.setText(alerta.tituloTarjeta());
            // BIT-034 (XAI): la razon morada bajo el titulo (solo si existe).
            vistaHolder.motivo.setText(alerta.tieneMotivo() ? alerta.motivo : "");
            vistaHolder.motivo.setVisibility(alerta.tieneMotivo() ? View.VISIBLE : View.GONE);
            vistaHolder.detalle.setText("Transacción " + alerta.idTransaccion
                    + " · Score " + alerta.score);
            vistaHolder.fecha.setText(alerta.fechaRecepcion());

            if (vistaHolder.puntoRiesgo.getBackground() instanceof GradientDrawable) {
                GradientDrawable punto =
                        (GradientDrawable) vistaHolder.puntoRiesgo.getBackground().mutate();
                punto.setColor(colorBanda);
            }
        }
        return vista;
    }

    private int colorSegunClasificacion(String clasificacion) {
        if ("ALTO".equalsIgnoreCase(clasificacion)) {
            return getContext().getColor(R.color.clasificacion_alto);
        }
        if ("MEDIO".equalsIgnoreCase(clasificacion)) {
            return getContext().getColor(R.color.clasificacion_medio);
        }
        return getContext().getColor(R.color.clasificacion_bajo);
    }

    /** ViewHolder del panel de cristal de la bandeja. */
    private static final class Vista {
        TextView chipClasificacion;
        View puntoRiesgo;
        TextView monto;
        TextView titulo;
        TextView motivo;
        TextView detalle;
        TextView fecha;
    }
}
