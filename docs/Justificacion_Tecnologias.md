# MIDFF — Justificación de Tecnologías y Alcance de IA

**Proyecto:** Motor Inteligente de Detección de Fraude Financiero (MIDFF)
**Residencia profesional · PluriOne** · Documento de cierre — v1.0

---

## 1. Contexto de la decisión tecnológica

La propuesta inicial del proyecto enumeraba un
stack hipotético: Python/FastAPI, React, Azure OpenAI/Azure ML/AI Search,
PostgreSQL, Power BI, Docker y GitHub Actions.

**Decisión de proyecto (jefe de proyecto / PluriOne):** el desarrollo se
realizaría **con las tecnologías ya dominadas por el residente**, priorizando
entregar un sistema funcional, auditable y operable en equipo, sobre adoptar
plataformas nuevas con curva de aprendizaje dentro de un calendario de
residencia fijo. Esta decisión quedó vigente desde el Sprint 1 y todo el
trabajo (BIT-001 a BIT-033 en `BITACORA.md`) se ejecutó bajo ella.

## 2. Equivalencia propuesta → implementado

| Propuesta inicial | Implementado en MIDFF | Por qué es equivalente (o mejor) para el caso |
|---|---|---|
| Python (motor analítico) | **C** (`src/motor`) | Latencia de microsegundos por evaluación (mediana ~7 µs ruta completa); binario auditable sin runtime |
| FastAPI (API REST) | **PHP 8** (`api/v1`, `router.php`) | Mismo contrato REST/JSON; deploy estándar en infraestructura bancaria |
| PostgreSQL | **Oracle XE 21c** (`database/`) | Estándar bancario; ACID + auditoría inmutable (RNF-04/RF-08) con triggers y SP |
| React.js (frontend) | **Consola `MIDFF.html` + simulador `banco.html`** | KPIs, serie 24 h, bandeja global con Bloquear/Desbloquear y sincronización en vivo (5 s) |
| — (no propuesto) | **App Android nativa (Java)** | Notificación push en tiempo real (FCM), biometría, respuestas "Sí/No fui yo" del cliente |
| Azure OpenAI / AI Search | **Fuera de alcance** (ver §3) | No requerido para el núcleo de detección |
| Azure ML | **Motor de scoring ponderado en C** (ver §3) | Explicable y auditable — requisito bancario |
| Power BI | **Panel `MIDFF.html`** (KPIs + top clientes + serie) | Consultas agregadas directas a Oracle |
| Docker / GitHub Actions | **`build.ps1` + `smoke-test.ps1`** (PowerShell) | Compilación, 167 verificaciones unitarias y E2E automatizados en un comando |
| Microsoft Entra ID | **Firebase Auth** (correo/contraseña + biometría) | Autenticación del cliente + control de sesión con desbloqueo biométrico del dispositivo |

## 3. Alcance de la "inteligencia" (declaración y roadmap)

**Lo entregado** es un motor de **scoring heurístico ponderado** — en la
taxonomía clásica, un *sistema experto*: tres variables normalizadas (monto
vs. historial, frecuencia por hora, cambio de ubicación/IP) ponderadas
(45/30/25), con umbrales 0.4/0.7 que definen BAJO/MEDIO/ALTO y la decisión
automática (APROBADA / EN_REVISION / BLOQUEADA).

**Por qué es la elección correcta para banca (y no una limitación menor):**
reguladores y equipos antifraude exigen **explicabilidad**: cada alerta puede
trazarse a sus variables, pesos y umbrales (el detalle lo muestra la propia
app y la consola). Un modelo de caja negra sería rechazado en un entorno
real sin un proceso de validación de modelos que excede una residencia.

**Limitación declarada:** el motor **no aprende** de los datos; sus pesos y
umbrales son fijos y fueron definidos por diseño (documentados en
`docs/contratos/contrato_scoring.md`).

**Trabajo futuro (líneas de continuación):**
1. Calibración de pesos con historia real: entrenamiento supervisado sobre
   `TRANSACCION`/`EVALUACION` (p. ej. regresión logística) que genere los
   coeficientes y el motor C solo los consuma en runtime.
2. Perfil de gasto por cliente: alimentar `contexto.monto_promedio_historial`
   desde el promedio móvil real del cliente en Oracle (hoy viene en la
   petición), eliminando falsos positivos para patrones de gasto altos.
3. Detección de anomalías no supervisada (autoencoder / isolación) como
   segunda opinión sobre el score heurístico.

## 4. Referencias

- Bitácora completa de decisiones (D-01…D-58): `BITACORA.md`
- Contrato del motor: `docs/contratos/contrato_scoring.md`
- Alcance original del producto: `PRD_MIDFF.md` y `MVP_MIDFF.md`
