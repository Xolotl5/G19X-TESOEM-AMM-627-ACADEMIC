# MIDFF — Informe Final de Residencia Profesional

**Proyecto:** Motor Inteligente de Detección de Fraude Financiero (MIDFF)
**Empresa:** PluriOne · **Residente:** Alejandro Miranda Martínez · **Fecha:** 07/11/2026 (actualizado a BIT-040)

---

## 1. Resumen ejecutivo

Se desarrolló y entregó un **sistema funcional de detección temprana de fraude
financiero** end-to-end: ingesta HTTP/JSON → motor de scoring en C (explicable, mediana
7.0 µs) → decisión automática por umbrales → persistencia atómica e inmutable en
Oracle XE → notificación push en tiempo real (Firebase FCM HTTP v1) → **respuesta del
cliente** en app Android ("Sí/No fui yo" con biometría) → **decisión del banco** en
consola web en vivo (Bloquear/Desbloquear) → auditoría completa. El MVP se cerró en 4
sprints (DoD 10/10) y el post-MVP (BIT-011…BIT-040) añadió la experiencia completa del
cliente, la consola del banco y la **IA explicable** (el cliente ve *por qué* saltó la
alerta: "Monto 12.9× tu habitual · Desde una ubicación distinta").

## 2. Objetivos vs resultado

| Objetivo específico (propuesta) | Resultado |
|---|---|
| Analizar transacciones financieras | ✅ 3 variables normalizadas (monto/frecuencia/ubicación-IP) con fail-safe |
| Diseñar la arquitectura | ✅ 8 componentes, 58 decisiones documentadas (`docs/Arquitectura.md`) |
| Implementar modelos predictivos | ✅ scoring ponderado explicable 45/30/25 + XAI al cliente; ML declarado como evolución |
| Validar precisión, desempeño y seguridad | ✅ 167 verificaciones / 0 fallos; mediana 7.0 µs, E2E 308 ms; Reporte QA aprobado; calibración §7 — 100 % en la base sintética y casos difíciles donde falla declarados (no es precisión de producción) |
| Documentación técnica y plan de soporte | ✅ README + 6 documentos en `docs/` + bitácora de 40 entradas |

## 3. Producto entregado

| Componente | Detalle |
|---|---|
| Motor C | `midff-scoring.exe`: score 0–1, umbrales 0.4/0.7, JSON auditable, fail-safe |
| API REST PHP | 9 endpoints en el **puerto 8091** (`transacciones`, `bloquear`, `desbloquear`, `reportar`, `estado`, `admin`, `dispositivos`, `baja`, `ping`) |
| Oracle XE | 6 tablas + SPs atómicos + auditoría inmutable por trigger |
| App Android | Java: Firebase Auth, biometría, bandeja con XAI, guía interactiva, autodescubrimiento de red |
| Consola `MIDFF.html` | KPIs, bandeja global, Bloquear/Desbloquear, refresco 5 s |
| Simulador `banco.html` | Plantillas por banda de riesgo para demos |
| Automatización | `build.ps1`, `smoke-test.ps1`, `servidor.ps1` (un comando levanta todo) |
| Documentación | README (guía de levantamiento), Arquitectura, Reporte QA, Manuales técnico/usuario, Justificación de tecnologías, esta bitácora |

## 4. Métricas finales

| Métrica | Objetivo | Resultado |
|---|---|---|
| Latencia del motor | µs | mediana **7.0 µs**, p95 10.8 µs |
| Ciclo E2E | ≤ 1 s | **308 ms** promedio |
| Verificaciones automatizadas | — | 167 (0 fallos) |
| Casos por banda de riesgo | 100 % | 100 % |
| Cobertura de auditoría | 100 % | 100 % |
| Entradas de bitácora | — | 40 (BIT-001…BIT-040), 58 decisiones |
| Calibración del motor (BIT-039) | medir con datos | base 80 casos: **P/R/F1 100 %** + 20 casos difíciles con FP/FN declarados (§7) |

**Seguridad (BIT-036…BIT-039):** autorización por actor — app con ID token de Firebase
(verificado contra Identity Toolkit), ingesta del banco con `X-API-Key` exclusiva de
`/transacciones` (sin llaves dentro del APK) y consola con sesión HMAC de administrador;
el vínculo de un `id_cliente` ajeno lo confirma el banco (aprobación ligada a una cuenta
concreta de Firebase) y los errores de Oracle solo se registran en el log del servidor.

## 5. Desviaciones declaradas

* **Stack:** la propuesta inicial listaba Python/Azure/React; por decisión del jefe de
  proyecto se desarrolló con las tecnologías dominadas por el residente (C/PHP/Oracle/
  Android/Firebase). Detalle y equivalencias: `docs/Justificacion_Tecnologias.md`.
* **IA:** el motor es un sistema experto ponderado (explicable y auditable — requisito
  bancario), no ML entrenado; la calibración con historia real queda como línea de
  continuación junto con el perfil de gasto por cliente y anomalías no supervisadas.

## 6. Lecciones aprendidas

1. **Contratos congelados primero**: fijar el JSON del motor C y los umbrales antes de
   escribir código evitó retrabajos entre C, PHP y Android.
2. **Push data-only**: el bloque `notification` de FCM impide procesar en segundo plano
   (BIT-014) — en mensajería financiera, los datos viajan en `data`.
3. **Secuencias tras limpiezas**: reiniciar tablas sin realinear `SEQ_AUDITORIA` produjo
   `ORA-00001` (BIT-021); la realineación ahora es automática en cada arranque.
4. **Explicabilidad gana adopción**: mostrar el motivo ("12.9× tu habitual") transforma
   una alarma críptica en una decisión informada del cliente (BIT-034).
5. **Un comando para la demo**: `servidor.ps1` (PATH de DLLs, firewall, puerto 8091 y
   secuencias) eliminó el riesgo operativo de levantar el sistema ante terceros.

## 7. Calibración del motor (BIT-039)

**Conjunto base (80 casos sintéticos, 16 fraudes ≈ 1/5).** Se construyó
`datos/datasets/calibracion_etiquetada.json` (criterio de etiquetado definido
**antes** de correr el motor y documentado en el archivo) y `calibrar.ps1`, que
pasa cada caso por `midff-scoring.exe` y mide la matriz de confusión:

| Umbral de alerta | TP | FP | FN | VN | Precisión | Recall | F1 |
|---|---|---|---|---|---|---|---|
| **0.40 (actual)** | 16 | 0 | 0 | 64 | **100 %** | **100 %** | **100** |
| 0.35 (alternativo) | 16 | 0 | 0 | 64 | 100 % | 100 % | 100 |

**Casos difíciles (Corte 5: 20 casos cuya etiqueta NO sale del motor).**
`datos/datasets/casos_dificiles.json` etiqueta cada caso con criterios externos
—horario (03:41), rubro del comercio (gift cards), canal ATM, estacionalidad
(diciembre/Buen Fin), reporte del cliente, comercio nuevo en 12 meses—
declarados en `razon_etiqueta`. Es un conjunto diseñado para encontrar dónde
FALLA el motor, no para confirmar aciertos. `calibrar.ps1` guarda los tres
puntajes por variable (`variables.{monto,frecuencia,ubicacion_ip}.puntaje`) y
recalcula el score con otra combinación de pesos **sin recompilar**:

| Pesos (monto/frecuencia/ubicación) | Umbral | TP | FP | FN | VN | Precisión | Recall |
|---|---|---|---|---|---|---|---|
| **0.45/0.30/0.25 (motor)** | 0.40 | 2 | 5 | 8 | 5 | 28.6 % | 20.0 % |
| 0.35/0.30/0.35 (alternativo) | 0.40 | 1 | 3 | 9 | 7 | 25.0 % | 10.0 % |

* **Fraudes que se escapan (FN = 8, todos con score ≤ 0.25):** phishing con
  monto habitual (TRX-DIF-01, 0.14), compra a las 03:41 (02), ráfaga con el
  monto exacto al promedio (03), malware desde el propio teléfono (04), cajero
  clonado en otra plaza (05), cuenta tomada imitando un viaje plausible (06),
  fencing de gift cards (08) y card testing (09). Imitan el patrón legítimo del
  cliente o explotan variables que el motor no modela (horario, rubro,
  comercio nuevo).
* **Sanos que molestan (FP = 5, scores 0.42–0.66):** gasto estacional grande
  (vuelos anuales 0.45, Buen Fin 0.66, navidad 0.43, fin de mes 0.44) y un
  mueble comprado en otra colonia (0.42) — concentraciones legítimas que
  superan el umbral por monto y frecuencia.
* **Comparación de pesos:** con 0.35/0.30/0.35 los FP bajan de 5 a 3, pero se
  pierde el único vaciamiento detectable (TRX-DIF-10 cae de 0.45 a 0.35, justo
  bajo el umbral). Como el costo de un FN (fraude que pasa) es mucho mayor que
  el de un FP (una alerta molesta que el cliente confirma en la app), **se
  mantienen los pesos 0.45/0.30/0.25 y el umbral 0.40** — igual que decidió la
  base: bajar a 0.35 no aporta detección.
* **Qué haríamos con los fallos:** (1) incorporar como señales nuevas el
  horario, el rubro del comercio y el comercio/canal nuevo, con pesos propios;
  (2) regla de ráfaga independiente para card testing (cargos pequeños
  repetidos); (3) perfil de gasto estacional por cliente para perdonar los FP
  de diciembre/Buen Fin; (4) mientras tanto, el hueco lo cubre la decisión
  humana del ciclo cliente↔banco (las MEDIO van a EN_REVISION y el cliente
  responde «Sí/No fui yo»).

*Límite honesto:* la base es sintética y construida sobre las mismas variables
del motor (la separación perfecta valida la coherencia del motor con la
política de riesgo, no una precisión de producción); el conjunto difícil es
pequeño y diseñado a propósito para romper la regla. Calibrar con historia real
(`EVALUACION` + casos etiquetados del banco) sigue siendo la continuación
natural.

## 8. Trabajo futuro

Nuevas variables de contexto (horario, rubro del comercio, comercio nuevo y
canal) detectadas en los casos difíciles, calibración de pesos con
`EVALUACION` histórica (regresión logística que genere los coeficientes),
perfil de gasto estacional real por cliente desde Oracle, segunda opinión con
detección de anomalías, particionamiento mensual de auditoría para escala
bancaria y pipeline CI/CD con contenedores.

## 9. Conclusión

El proyecto cumple el objetivo general —detección temprana de fraude con evidencia
auditable— y lo excede con un ciclo de decisión **colaborativo cliente↔banco** en
tiempo real e IA explicable de punta a punta. El sistema se levanta con un comando,
se regressiona con dos y está documentado para ser operado por un tercero.
