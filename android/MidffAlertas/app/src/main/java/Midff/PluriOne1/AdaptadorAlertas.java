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
 * Adaptador de la bandeja de alertas (RF-10, rediseño glass/neón): une la
 * lista de {@link ModeloAlerta} con el panel de cristal {@code item_alerta}
 * y pinta chip de clasificación, punto de riesgo y score con el neón de su
 * banda (cian BAJO/APROBADA, púrpura MEDIO/EN_REVISION, magenta ALTO).
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
            vistaHolder.score = vista.findViewById(R.id.txtItemScore);
            vistaHolder.titulo = vista.findViewById(R.id.txtItemTitulo);
            vistaHolder.detalle = vista.findViewById(R.id.txtItemDetalle);
            vistaHolder.fecha = vista.findViewById(R.id.txtItemFecha);
            vista.setTag(vistaHolder);
        } else {
            vistaHolder = (Vista) vista.getTag();
        }

        ModeloAlerta alerta = getItem(posicion);
        if (alerta != null) {
            int colorNeon = colorSegunClasificacion(alerta.clasificacion);

            vistaHolder.chipClasificacion.setText(alerta.estaBloqueada()
                    ? alerta.clasificacion + " · BLOQUEADA"
                    : alerta.clasificacion);
            vistaHolder.chipClasificacion.setTextColor(colorNeon);
            UtilidadesUi.resplandor(vistaHolder.chipClasificacion,
                    UtilidadesUi.colorGlow(colorNeon), 10f);

            vistaHolder.score.setText(alerta.score);
            vistaHolder.score.setTextColor(colorNeon);
            UtilidadesUi.resplandor(vistaHolder.score,
                    UtilidadesUi.colorGlow(colorNeon), 14f);

            vistaHolder.titulo.setText(alerta.tituloCorto());
            vistaHolder.detalle.setText(alerta.detalleCorto());
            vistaHolder.fecha.setText(alerta.fechaRecepcion());

            if (vistaHolder.puntoRiesgo.getBackground() instanceof GradientDrawable) {
                GradientDrawable punto =
                        (GradientDrawable) vistaHolder.puntoRiesgo.getBackground().mutate();
                punto.setColor(colorNeon);
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
        TextView score;
        TextView titulo;
        TextView detalle;
        TextView fecha;
    }
}
