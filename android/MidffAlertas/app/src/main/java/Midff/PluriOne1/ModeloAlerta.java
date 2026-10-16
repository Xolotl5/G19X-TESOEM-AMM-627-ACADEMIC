package Midff.PluriOne1;

import org.json.JSONException;
import org.json.JSONObject;

import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;
import java.util.Map;

/**
 * Modelo de la alerta MIDFF (RF-10): espejo del bloque "data" que envia
 * {@code config/firebase.php} en el push FCM (monto, score, clasificacion,
 * decision y trazabilidad hacia Oracle).
 *
 * <p>El MVP difirio Firebase Realtime Database (RF-12), por lo que el modelo
 * se serializa a JSON para la bandeja local (SharedPreferences) y para los
 * extras de los Intent (decision D-27 de BITACORA.md).</p>
 */
public class ModeloAlerta {

    public String idAlerta;
    public String idTransaccion;
    public String idCliente;
    public String monto;
    public String moneda;
    public String score;
    public String clasificacion;
    public String decision;
    public String tipoAlerta;

    /**
     * BIT-034 (XAI): razon en lenguaje llano de por que la transaccion llamo
     * la atencion ("Monto 12.9x tu habitual · Desde una ubicacion distinta");
     * viene del backend derivada de las variables auditables del motor.
     */
    public String motivo;

    /** Estado de la alerta en Oracle: PENDIENTE | ATENDIDA | BLOQUEADA (Sprint 4). */
    public String estado;
    public String fechaEvaluacion;

    /** Marca de tiempo (epoch ms) en la que el dispositivo recibio el push. */
    public long recibidaEnMs;

    /**
     * Construye el modelo desde el bloque {@code data} del mensaje FCM.
     *
     * @return la alerta, o {@code null} si el mapa no trae datos.
     */
    public static ModeloAlerta desdeDatosFcm(Map<String, String> datos) {
        if (datos == null || datos.isEmpty()) {
            return null;
        }
        ModeloAlerta alerta = new ModeloAlerta();
        alerta.idAlerta        = texto(datos.get("id_alerta"));
        alerta.idTransaccion   = texto(datos.get("id_transaccion"));
        alerta.idCliente       = texto(datos.get("id_cliente"));
        alerta.monto           = texto(datos.get("monto"));
        alerta.moneda          = texto(datos.get("moneda"));
        alerta.score           = texto(datos.get("score"));
        alerta.clasificacion   = texto(datos.get("clasificacion"));
        alerta.decision        = texto(datos.get("decision"));
        alerta.tipoAlerta      = texto(datos.get("tipo_alerta"));
        String motivoFcm = datos.get("motivo");
        alerta.motivo = motivoFcm == null ? "" : motivoFcm.trim();
        alerta.fechaEvaluacion = texto(datos.get("fecha_evaluacion"));
        // El push no viaja con el estado: nace PENDIENTE hasta que el oficial
        // la bloquea desde la app (HU-MVP-06, Sprint 4).
        String estadoFcm = datos.get("estado");
        alerta.estado    = (estadoFcm == null || estadoFcm.trim().isEmpty())
                ? "PENDIENTE" : estadoFcm.trim();
        alerta.recibidaEnMs    = System.currentTimeMillis();
        return alerta;
    }

    private static String texto(String valor) {
        return (valor == null || valor.trim().isEmpty()) ? "-" : valor.trim();
    }

    public JSONObject aJson() throws JSONException {
        JSONObject objeto = new JSONObject();
        objeto.put("id_alerta", idAlerta);
        objeto.put("id_transaccion", idTransaccion);
        objeto.put("id_cliente", idCliente);
        objeto.put("monto", monto);
        objeto.put("moneda", moneda);
        objeto.put("score", score);
        objeto.put("clasificacion", clasificacion);
        objeto.put("decision", decision);
        objeto.put("tipo_alerta", tipoAlerta);
        objeto.put("motivo", motivo == null ? "" : motivo);
        objeto.put("estado", estado);
        objeto.put("fecha_evaluacion", fechaEvaluacion);
        objeto.put("recibida_en_ms", recibidaEnMs);
        return objeto;
    }

    /** Serializacion que nunca lanza: para extras de Intent y la bandeja. */
    public String aJsonSeguro() {
        try {
            return aJson().toString();
        } catch (JSONException e) {
            return "{}";
        }
    }

    /** Deserializa la alerta; devuelve {@code null} si el JSON es invalido. */
    public static ModeloAlerta desdeJson(String texto) {
        if (texto == null || texto.trim().isEmpty()) {
            return null;
        }
        try {
            JSONObject objeto = new JSONObject(texto);
            ModeloAlerta alerta = new ModeloAlerta();
            alerta.idAlerta        = objeto.optString("id_alerta", "-");
            alerta.idTransaccion   = objeto.optString("id_transaccion", "-");
            alerta.idCliente       = objeto.optString("id_cliente", "-");
            alerta.monto           = objeto.optString("monto", "-");
            alerta.moneda          = objeto.optString("moneda", "-");
            alerta.score           = objeto.optString("score", "-");
            alerta.clasificacion   = objeto.optString("clasificacion", "-");
            alerta.decision        = objeto.optString("decision", "-");
            alerta.tipoAlerta      = objeto.optString("tipo_alerta", "-");
            alerta.motivo          = objeto.optString("motivo", "");
            alerta.estado          = objeto.optString("estado", "PENDIENTE");
            alerta.fechaEvaluacion = objeto.optString("fecha_evaluacion", "-");
            alerta.recibidaEnMs    = objeto.optLong("recibida_en_ms", System.currentTimeMillis());
            return alerta;
        } catch (JSONException e) {
            return null;
        }
    }

    /** Cierto para la banda ALTA / alerta de FRAUDE (umbral 0.7, decision D-05). */
    public boolean esRiesgoAlto() {
        return "ALTO".equalsIgnoreCase(clasificacion) || "FRAUDE".equalsIgnoreCase(tipoAlerta);
    }

    /** Cierto para una transaccion sana (banda BAJO / tipo SANA, sin alerta). */
    public boolean esSana() {
        return "SANA".equalsIgnoreCase(tipoAlerta) || "BAJO".equalsIgnoreCase(clasificacion);
    }

    /** Cierto cuando la alerta ya fue bloqueada desde la app (HU-MVP-06, Sprint 4). */
    public boolean estaBloqueada() {
        return "BLOQUEADA".equalsIgnoreCase(estado);
    }

    /**
     * Score como número entre 0 y 1 para la analítica visual del detalle
     * (rediseño glass/neón); 0 si el texto no es legible.
     */
    public double scoreValor() {
        try {
            return Math.max(0d, Math.min(1d,
                    Double.parseDouble(score.replace(',', '.'))));
        } catch (Exception e) {
            return 0d;
        }
    }

    /** Cierto para transacciones aprobadas (banda BAJO o decisión APROBADA).
     *  BIT-032: también las resueltas a favor (ATENDIDA: confirmadas por el
     *  cliente o desbloqueadas por el banco) cuentan como aprobadas en la app. */
    public boolean esAprobada() {
        return esSana()
                || "ATENDIDA".equalsIgnoreCase(estado)
                || (decision != null && decision.toUpperCase(Locale.US).contains("APROB"));
    }

    // -----------------------------------------------------------------------
    // BIT-028: modismos entendibles para el usuario final (menos jerga técnica)
    // -----------------------------------------------------------------------

    /** Clasificación en lenguaje llano: "Riesgo alto/medio/bajo" o "Aprobada". */
    public String clasificacionAmigable() {
        if ("ALTO".equalsIgnoreCase(clasificacion)) {
            return "Riesgo alto";
        }
        if ("MEDIO".equalsIgnoreCase(clasificacion)) {
            return "Riesgo medio";
        }
        if (esSana() || esAprobada()) {
            return "Aprobada";
        }
        return "Riesgo bajo";
    }

    /** Decisión en lenguaje llano ("Aprobada", "En revisión", "Bloqueada"). */
    public String decisionAmigable() {
        String d = decision == null ? "" : decision.toUpperCase(Locale.US);
        if (d.contains("APROB")) {
            return "Aprobada";
        }
        if (d.contains("REVIS")) {
            return "En revisión";
        }
        if (d.contains("BLOQU")) {
            return "Bloqueada";
        }
        return decision == null ? "-" : decision;
    }

    /** Estado de la alerta en lenguaje llano. BIT-032: una alerta ATENDIDA
     *  (confirmada por el cliente o desbloqueada por el banco) se muestra como
     *  "Aprobada"; si el banco la considera fraude queda BLOQUEADA y así se ve. */
    public String estadoAmigable() {
        if (estaBloqueada()) {
            return "Bloqueada";
        }
        if ("ATENDIDA".equalsIgnoreCase(estado)) {
            return "Aprobada";
        }
        return "Pendiente";
    }

    /** Título de la tarjeta: qué le pasa a su dinero, sin jerga de motor. */
    public String tituloTarjeta() {
        if (esSana() || esAprobada()) {
            return "Movimiento aprobado";
        }
        return esRiesgoAlto() ? "Posible fraude en tu cuenta" : "Movimiento inusual";
    }

    /**
     * Monto protagonista de la tarjeta (BIT-028): "$ 15,499.00 MXN" con
     * separador de miles; si el texto no es numérico se muestra tal cual.
     */
    public String montoFormateado() {
        String monedaLegible = (moneda == null || moneda.trim().isEmpty() || "-".equals(moneda))
                ? "" : " " + moneda.trim();
        try {
            double valor = Double.parseDouble(monto.replace(",", "").replace("$", "").trim());
            return String.format(Locale.US, "$ %,.2f%s", valor, monedaLegible);
        } catch (Exception e) {
            return monto + monedaLegible;
        }
    }

    /** Cierto mientras la alerta espera al oficial (ni aprobada ni bloqueada). */
    public boolean enRevision() {
        return !estaBloqueada() && !esAprobada();
    }

    public String tituloNotificacion() {
        if (esSana()) {
            return "MIDFF: transaccion aprobada (riesgo " + clasificacion + ")";
        }
        return esRiesgoAlto()
                ? "MIDFF: alerta de FRAUDE (riesgo " + clasificacion + ")"
                : "MIDFF: alerta preventiva (riesgo " + clasificacion + ")";
    }

    public String tituloCorto() {
        return tipoAlerta + " | " + clasificacion + " | " + decision
                + (estaBloqueada() ? " | BLOQUEADA" : "");
    }

    public String detalleCorto() {
        return "Monto " + monto + " " + moneda + " | Score " + score + " | Cliente " + idCliente;
    }

    public String detalleLargo() {
        return detalleCorto()
                + (tieneMotivo() ? "\nPor que te avisamos: " + motivo : "")
                + "\nTransaccion: " + idTransaccion
                + "\nAlerta: " + idAlerta
                + "\nDecision: " + decision
                + "\nFecha: " + fechaEvaluacion;
    }

    /** BIT-034 (XAI): cierto si la alerta trae razon explicativa del motor. */
    public boolean tieneMotivo() {
        return motivo != null && !motivo.trim().isEmpty() && !"-".equals(motivo.trim());
    }

    public String fechaRecepcion() {
        return new SimpleDateFormat("dd/MM/yyyy HH:mm:ss", Locale.US).format(new Date(recibidaEnMs));
    }
}
