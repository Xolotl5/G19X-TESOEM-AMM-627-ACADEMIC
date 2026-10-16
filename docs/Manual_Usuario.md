# MIDFF — Manual de Usuario

**Versión:** 1.0 · 15/10/2026 · App del cliente · Consola del banco · Simulador

---

## A. App Android del cliente ("Midff Alertas")

### A.1 Primer uso
1. Instala el APK que te entregó el banco y ábrelo.
2. **Inicia sesión** con tu correo y contraseña (o crea tu cuenta la primera vez).
3. Autoriza la **biometría** (huella/rostro/PIN del teléfono): se pedirá en cada
   entrada para proteger tu bandeja.
4. Aparece la **guía interactiva** (5 pasos con ejemplos visuales). Puedes saltarla
   con "¡Empezar!" y reabrirla cuando quieras en **Perfil → Guía de inicio**.

### A.2 Entender una alerta
Cada tarjeta de la bandeja te muestra:
* **El monto** protagonizando la tarjeta, con el color de su riesgo
  (🟢 bajo · 🟡 medio · 🔴 alto).
* El **título** claro: "Movimiento aprobado", "Movimiento inusual" o "Posible fraude".
* **Por qué te avisamos** (letra morada): la razón en lenguaje sencillo, p. ej.
  *"Monto 12.9× tu habitual · Desde una ubicación distinta"*. Es el motor explicándote
  qué llamó su atención — ningún dato tuyo se comparte en esa explicación.
* Transacción, score y fecha/hora de recepción.

### A.3 Responder una alerta
| Situación | Botones disponibles | Qué pasa |
|---|---|---|
| Riesgo medio | **"✓ Sí fui yo"** y **"⛔ No fui yo"** | "Sí" la aprueba al instante; "No" levanta la bandera ⚑ al banco |
| Riesgo alto (llega bloqueada) | Solo **"✓ Sí fui yo"** | Tu confirmación habilita al banco a desbloquear |
| Movimiento aprobado (sana) | Ninguno | Solo informativa; la ves en el filtro "Aprobadas" |

Si ya reportaste una alerta, la app te avisa en lugar de mostrar un error.

### A.4 Navegación
* **Panel**: resumen y estado del enlace con el banco.
* **Alertas**: bandeja con filtros (Todas · Riesgo · Estado) y botón **↻ Actualizar**
  en la propia fila de filtros.
* **Perfil**: tu avatar, registro del dispositivo, guía de inicio y cerrar sesión.

---

## B. Consola del banco (`MIDFF.html`)

1. Abre `MIDFF.html` en el navegador e inicia sesión con el usuario administrador.
2. Verás: **KPIs** (transacciones, alertas, bloqueadas, monto vigilado), la **serie de
   las últimas 24 h**, el **top de clientes** con más alertas y la **bandeja global**.
3. La consola **se actualiza sola cada 5 segundos** — no necesitas recargar.
4. Sobre una alerta:
   * **Bloquear**: confirmación directa → la transacción queda bloqueada y se audita.
   * **Desbloquear**: aparece cuando el cliente confirmó "Sí fui yo" en una alerta de
     riesgo alto; pide **doble confirmación** y notifica al cliente por push al instante.
5. Las transacciones sanas aparecen con badge **SANA** y sin botones.

---

## C. Simulador del banco (`banco.html`)

Para demos: elige **cliente registrado**, una **plantilla** (Transacción normal /
Montos inusuales / Posible fraude) o datos personalizados, y pulsa **Enviar**. El
resultado (score, clasificación y decisión) aparece en pantalla y el push llega al
teléfono en segundos con su motivo.

---

## D. Preguntas frecuentes

* **¿Por qué llegó bloqueada una compra mía?** El motor detectó riesgo alto
  (monto varias veces tu habitual, lugar o dispositivo distinto). Confirma "Sí fui yo"
  y el banco podrá desbloquearla en segundos.
* **¿Qué pasa si no respondo?** La alerta sigue pendiente; el oficial puede bloquear
  preventivamente desde la consola.
* **¿Mi información está segura?** Solo viaja dentro del sistema del banco: sesión con
  biometría, canal propio del banco y auditoría inmutable de cada decisión.
* **¿Puedo ver mi historial?** Sí: la bandeja conserva tus movimientos y el filtro
  "Aprobadas" aísla los confirmados.
