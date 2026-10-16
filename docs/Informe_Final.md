# MIDFF — Informe Final de Residencia Profesional

**Proyecto:** Motor Inteligente de Detección de Fraude Financiero (MIDFF)
**Empresa:** PluriOne · **Residente:** (nombre del residente) · **Fecha:** 15/10/2026

---

## 1. Resumen ejecutivo

Se desarrolló y entregó un **sistema funcional de detección temprana de fraude
financiero** end-to-end: ingesta HTTP/JSON → motor de scoring en C (explicable, mediana
7.0 µs) → decisión automática por umbrales → persistencia atómica e inmutable en
Oracle XE → notificación push en tiempo real (Firebase FCM HTTP v1) → **respuesta del
cliente** en app Android ("Sí/No fui yo" con biometría) → **decisión del banco** en
consola web en vivo (Bloquear/Desbloquear) → auditoría completa. El MVP se cerró en 4
sprints (DoD 10/10) y el post-MVP (BIT-011…BIT-034) añadió la experiencia completa del
cliente, la consola del banco y la **IA explicable** (el cliente ve *por qué* saltó la
alerta: "Monto 12.9× tu habitual · Desde una ubicación distinta").

## 2. Objetivos vs resultado

| Objetivo específico (propuesta) | Resultado |
|---|---|
| Analizar transacciones financieras | ✅ 3 variables normalizadas (monto/frecuencia/ubicación-IP) con fail-safe |
| Diseñar la arquitectura | ✅ 8 componentes, 58 decisiones documentadas (`docs/Arquitectura.md`) |
| Implementar modelos predictivos | ✅ scoring ponderado explicable 45/30/25 + XAI al cliente; ML declarado como evolución |
| Validar precisión, desempeño y seguridad | ✅ 167 verificaciones / 0 fallos; mediana 7.0 µs, E2E 308 ms; Reporte QA aprobado |
| Documentación técnica y plan de soporte | ✅ README + 6 documentos en `docs/` + bitácora de 34 entradas |

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
| Entradas de bitácora | — | 34 (BIT-001…BIT-034), 58 decisiones |

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

## 7. Trabajo futuro

Calibración de pesos con `EVALUACION` histórica (regresión logística que genere los
coeficientes), perfil de gasto real por cliente desde Oracle, segunda opinión con
detección de anomalías, particionamiento mensual de auditoría para escala bancaria y
pipeline CI/CD con contenedores.

## 8. Conclusión

El proyecto cumple el objetivo general —detección temprana de fraude con evidencia
auditable— y lo excede con un ciclo de decisión **colaborativo cliente↔banco** en
tiempo real e IA explicable de punta a punta. El sistema se levanta con un comando,
se regressiona con dos y está documentado para ser operado por un tercero.
