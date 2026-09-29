package Midff.PluriOne1;

import android.content.Context;
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
 * {@link ModeloAlerta} con el layout {@code item_alerta} y pinta cada titulo
 * con el color de su banda de riesgo (verde BAJO / ambar MEDIO / rojo ALTO).
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
            vistaHolder.titulo = vista.findViewById(R.id.txtItemTitulo);
            vistaHolder.detalle = vista.findViewById(R.id.txtItemDetalle);
            vistaHolder.fecha = vista.findViewById(R.id.txtItemFecha);
            vista.setTag(vistaHolder);
        } else {
            vistaHolder = (Vista) vista.getTag();
        }

        ModeloAlerta alerta = getItem(posicion);
        if (alerta != null) {
            vistaHolder.titulo.setText(alerta.tituloCorto());
            vistaHolder.titulo.setTextColor(colorSegunClasificacion(alerta.clasificacion));
            vistaHolder.detalle.setText(alerta.detalleCorto());
            vistaHolder.fecha.setText(alerta.fechaRecepcion());
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

    /** ViewHolder del renglon de la bandeja. */
    private static final class Vista {
        TextView titulo;
        TextView detalle;
        TextView fecha;
    }
}
