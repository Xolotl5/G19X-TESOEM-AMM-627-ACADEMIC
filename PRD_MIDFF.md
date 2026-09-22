# PRD — Motor Inteligente de Detección de Fraude Financiero (MIDFF)

**Documento de Requisitos del Producto (Product Requirements Document)**

| Campo | Valor |
|---|---|
| **Nombre del producto** | MIDFF — Motor Inteligente de Detección de Fraude Financiero |
| **Título del proyecto** | Desarrollo de un Motor Inteligente de Detección de Fraude Financiero con Arquitectura Híbrida (Android, C, PHP, Oracle DB y Firebase) |
| **Tipo de proyecto** | Residencias Profesionales |
| **Versión del documento** | 1.0 |
| **Fecha de elaboración** | 21/09/2026 |
| **Estado** | Borrador en revisión |
| **Documento fuente** | `ideas.txt` |
| **Metodología** | Scrum |
| **Control de versiones** | Git |

### Control de cambios

| Versión | Fecha | Autor | Descripción del cambio |
|---|---|---|---|
| 1.0 | 21/09/2026 | Residente del proyecto | Versión inicial generada a partir de `ideas.txt` |

---

## 1. Resumen ejecutivo

MIDFF es una plataforma integral de seguridad financiera capaz de **analizar transacciones en tiempo real** provenientes de sistemas ERP, banca electrónica y pasarelas de pago. Su propósito es detectar patrones sospechosos, clasificar el nivel de riesgo de cada operación y disparar acciones preventivas (alertas o bloqueos) de forma inmediata.

El sistema se apoya en tres pilares principales:

1. **Analítica de alta velocidad:** un motor escrito en **C** que procesa variables transaccionales (montos, frecuencia, localización, patrón de conducta) mediante un modelo de scoring/reglas avanzadas para clasificar el nivel de riesgo en **microsegundos**.
2. **Orquestación y persistencia empresarial:** una **API REST en PHP 8+** que recibe las operaciones, interactúa con el motor en C y las persiste en **Oracle Database** bajo estándares de auditoría bancaria.
3. **Monitoreo móvil en tiempo real:** un ecosistema en **Android Studio (Java)** sincronizado mediante **Firebase Cloud Messaging (FCM)** que notifica instantáneamente al personal de seguridad o a los usuarios ante cualquier anomalía, permitiendo el **bloqueo preventivo inmediato**.

## 2. Visión del producto

> "Permitir que instituciones financieras y empresas con alto volumen transaccional identifiquen y detengan el fraude **antes** de que se materialice, mediante un motor de evaluación de riesgo de ultra baja latencia, con trazabilidad total de cada decisión y capacidad de respuesta móvil en tiempo real."

**Principios rectores:**

- **Velocidad:** cada evaluación debe ejecutarse en microsegundos; el fraude se combate en tiempo real.
- **Trazabilidad:** toda evaluación y decisión queda registrada para auditoría bancaria.
- **Acción inmediata:** una alerta no es útil si no se puede actuar; el bloqueo preventivo es parte del flujo.
- **Arquitectura híbrida:** cada tecnología resuelve lo que mejor sabe hacer (C para el cómputo, PHP para la orquestación, Oracle para la persistencia, Firebase/Android para la notificación).

## 3. Objetivos

### 3.1 Objetivo general

Desarrollar un motor inteligente de detección de fraude financiero con arquitectura híbrida que evalúe transacciones en tiempo real, calcule un score de riesgo de 0.0 a 1.0, clasifique la operación y notifique/bloquee preventivamente según el nivel de riesgo, garantizando la auditoría completa de cada decisión.

### 3.2 Objetivos específicos

1. Construir un motor de análisis en **C** que calcule el score de riesgo considerando la desviación del monto respecto al historial, la frecuencia de transacciones por hora y los cambios de ubicación o IPs inusuales.
2. Desarrollar una **API REST en PHP 8+** que reciba transacciones HTTP/JSON desde sistemas ERP, banca electrónica y pasarelas de pago, invoque al ejecutable C y persista los resultados en **Oracle DB**.
3. Implementar el **registro de auditoría** de cada evaluación y decisión bajo estándares de auditoría bancaria.
4. Desarrollar una **app Android nativa (Java)** que reciba notificaciones push vía **Firebase FCM** y permita el bloqueo preventivo inmediato.
5. Construir un **dashboard web administrativo** (HTML5/CSS/JavaScript) con monitoreo y gráficos de desempeño.
6. Automatizar compilación, pruebas y despliegue mediante **scripts PowerShell (.ps1)**, con gestión del código en **Git** bajo metodología **Scrum**.

## 4. Alcance

### 4.1 Dentro del alcance (In-Scope)

- Ingesta de transacciones mediante peticiones **HTTP/JSON** desde sistemas ERP y banca.
- Cálculo del **score de riesgo (0.0 a 1.0)** en el motor C con tres variables: desviación de monto respecto al historial, frecuencia de transacciones por hora y cambios de ubicación o IPs inusuales.
- **Clasificación de riesgo** en tres niveles: Bajo (< 0.4), Medio (0.4 – 0.7) y Alto (> 0.7).
- Decisión automática: aprobación, revisión con alerta preventiva o **bloqueo inmediato con alerta de fraude**.
- Envío de **notificaciones push** a la app Android vía Firebase para riesgo medio/alto.
- **Persistencia transaccional ACID** y tablas de auditoría en Oracle Database.
- Sincronización servidor–móvil mediante Firebase FCM / Realtime Database.
- Dashboard web de monitoreo y gráficos de desempeño.
- Scripts de compilación, pruebas y despliegue en PowerShell (.ps1).

### 4.2 Fuera del alcance (Out-of-Scope)

- Integración directa (batch) con core bancario heredado por protocolos propietarios; la entrada es únicamente vía API HTTP/JSON.
- Modelos de machine learning entrenados con datasets externos de fraude; la versión 1.0 se basa en scoring por reglas sobre variables transaccionales.
- Apps móviles para iOS (el cliente móvil es Android nativo).
- Procesamiento de disputas, contracargos (chargebacks) o casos legales posteriores al fraude.
- Migración de datos históricos desde sistemas legacy.

## 5. Usuarios y roles

| Rol | Descripción | Interacción principal |
|---|---|---|
| **Sistema origen (ERP / Banca / Pasarela de pago)** | Actor de sistema que envía transacciones para evaluación. | Envía HTTP/JSON a la API REST y recibe score + decisión. |
| **Oficial de seguridad financiera** | Personal de seguridad que supervisa las alertas. | Recibe notificaciones push, revisa alertas preventivas y ejecuta bloqueos. |
| **Usuario final / Titular** | Cliente cuya actividad transaccional es monitoreada. | Recibe notificaciones de anomalías y puede solicitar el bloqueo preventivo de su cuenta. |
| **Auditor** | Personal que revisa el cumplimiento bancario. | Consulta los registros de auditoría en Oracle DB (cada evaluación y decisión). |
| **Administrador del sistema** | Responsable de la operación de la plataforma. | Monitorea el dashboard web, gráficos de desempeño y parámetros del sistema. |

## 6. Requisitos funcionales (RF)

| ID | Requisito | Prioridad |
|---|---|---|
| **RF-01** | **Ingesta de transacciones:** la API REST en PHP 8+ debe exponer un endpoint que reciba transacciones vía HTTP/JSON desde sistemas ERP, banca electrónica y pasarelas de pago. | Alta |
| **RF-02** | **Validación de entrada:** cada petición debe validarse (estructura JSON, campos obligatorios, autenticación del canal), rechazándose con códigos de error estándar si es inválida. | Alta |
| **RF-03** | **Invocación del motor C:** el backend PHP debe invocar al ejecutable del motor en C y recibir como respuesta el score calculado. | Alta |
| **RF-04** | **Cálculo del score de riesgo:** el motor en C debe calcular un score de **0.0 a 1.0** en microsegundos, considerando: (a) desviación del monto respecto al historial del cliente, (b) frecuencia de transacciones por hora y (c) cambios de ubicación o IPs inusuales. | Alta |
| **RF-05** | **Clasificación del riesgo:** el sistema debe clasificar cada transacción: **Bajo (< 0.4)** → Aprobada; **Medio (0.4 – 0.7)** → bajo Revisión / Alerta Preventiva; **Alto (> 0.7)** → Bloqueo Inmediato y emisión de Alerta de Fraude. | Alta |
| **RF-06** | **Respuesta sincrónica al origen:** la API debe responder al sistema origen con el score, la clasificación y la decisión adoptada. | Alta |
| **RF-07** | **Persistencia transaccional:** cada transacción y su evaluación deben persistirse en Oracle Database con propiedades **ACID**. | Alta |
| **RF-08** | **Auditoría:** el sistema debe registrar en Oracle DB el detalle completo de **cada evaluación y decisión** (variables usadas, score, clasificación, decisión, fecha/hora) bajo estándares de auditoría bancaria. | Alta |
| **RF-09** | **Notificación push:** ante riesgo **medio o alto**, el backend debe enviar notificaciones push a la app Android vía **Firebase Cloud Messaging (FCM)**. | Alta |
| **RF-10** | **App Android de alertas:** la app nativa (Java) debe recibir y gestionar las notificaciones de anomalías para el personal de seguridad o los usuarios. | Alta |
| **RF-11** | **Bloqueo preventivo inmediato:** la app Android debe permitir ejecutar un bloqueo preventivo desde el dispositivo, el cual se refleja en el backend y queda registrado en auditoría. | Alta |
| **RF-12** | **Sincronización de estado:** el estado de alertas y bloqueos debe sincronizarse servidor–móvil mediante Firebase Realtime Database. | Media |
| **RF-13** | **Dashboard administrativo:** el panel web (HTML5/CSS/JavaScript) debe mostrar monitoreo en tiempo real y gráficos de desempeño (volumen transaccional, distribución de riesgo, alertas activas). | Media |
| **RF-14** | **Consulta de historial:** el dashboard y los roles autorizados deben poder consultar el historial de evaluaciones, alertas y decisiones. | Media |
| **RF-15** | **Automatización:** deben existir scripts PowerShell (.ps1) para compilación del motor C, ejecución de pruebas y despliegue. | Media |

## 7. Requisitos no funcionales (RNF)

| ID | Categoría | Requisito |
|---|---|---|
| **RNF-01** | Rendimiento | La evaluación del motor en C debe completarse en **microsegundos** (procesamiento de ultra baja latencia); la respuesta extremo a extremo de la API debe mantenerse en el orden de milisegundos. |
| **RNF-02** | Disponibilidad | La API REST debe alcanzar una disponibilidad objetivo ≥ 99.5 % en horario operativo, al ser un canal crítico de prevención de fraude. |
| **RNF-03** | Seguridad | Autenticación de los sistemas origen, validación estricta de entradas y cifrado del canal (TLS) para toda petición y notificación. |
| **RNF-04** | Auditoría y cumplimiento | Los registros de auditoría deben ser completos, inmutables y consultables, conforme a estándares de auditoría bancaria. |
| **RNF-05** | Integridad de datos | Toda persistencia en Oracle DB debe ser transaccional (**ACID**); una evaluación sin registro de auditoría debe considerarse fallida. |
| **RNF-06** | Escalabilidad | La arquitectura debe soportar picos de volumen transaccional sin degradar el tiempo de evaluación (inclinándose por invocaciones eficientes al motor C). |
| **RNF-07** | Confiabilidad de mensajería | Las notificaciones push (FCM) deben entregarse de forma inmediata ante riesgo medio/alto, con reintentos ante fallos de entrega. |
| **RNF-08** | Usabilidad móvil | Las notificaciones deben ser claras y accionables (permitir revisar la alerta o bloquear en el menor número de toques posible). |
| **RNF-09** | Mantenibilidad | Código versionado en Git, scripts de build/test/deploy en PowerShell y separación clara de responsabilidades por componente. |
| **RNF-10** | Portabilidad | El motor C debe poder compilarse en los entornos de desarrollo definidos (VSCode/Android Studio + toolchain C), documentando el proceso de compilación. |

## 8. Arquitectura del sistema

### 8.1 Diagrama general

```
+--------------------+   HTTP/JSON    +--------------------+   invocación    +---------------------+
| ERP / Banca        | -------------> |   API REST PHP 8+  | ------------->  |   MOTOR DE ANÁLISIS  |
| electrónica /      | <------------- |   (Orquestador)    | <-------------  |   EN C (scoring)    |
| Pasarelas de pago  |  score/decisión|                    |   score 0.0-1.0 |  ultra baja latencia|
+--------------------+                +---------+----------+                 +---------------------+
                                                |  SQL (ACID)
                                                v
                                      +---------------------+
                                      |   ORACLE DATABASE   |
                                      |  Transacciones,     |
                                      |  evaluaciones y     |
                                      |  auditoría bancaria |
                                      +----------+----------+
                                                 |  alerta (riesgo >= 0.4)
                                                 v
                                      +---------------------+    push     +--------------------------+
                                      | FIREBASE FCM /      | ---------> |  APP ANDROID (Java)      |
                                      | Realtime Database   | <--------- |  + DASHBOARD WEB          |
                                      +---------------------+  bloqueo/  |  (HTML5/CSS/JavaScript)  |
                                                               acciones   +--------------------------+
```

### 8.2 Componentes y responsabilidades

| Componente | Tecnología | Responsabilidad |
|---|---|---|
| **Motor de análisis (IA/ML)** | C | Procesamiento de ultra baja latencia y evaluación del score de riesgo a partir de variables transaccionales. |
| **Backend / API REST** | PHP 8+ | Recepción de JSONs, orquestación de servicios, invocación del ejecutable C y persistencia en Oracle. |
| **Base de datos empresarial** | Oracle Database | Almacenamiento transaccional ACID, tablas de auditoría y registros. |
| **Cliente móvil** | Android Studio / Java | App nativa para alertas tempranas, gestión de notificaciones y bloqueos. |
| **Mensajería en tiempo real** | Firebase FCM / Realtime Database | Canal de transmisión inmediata servidor–móvil. |
| **Dashboard administrativo** | HTML5 / CSS / JavaScript | Panel web de monitoreo y gráficos de desempeño. |
| **Automatización** | PowerShell (.ps1) | Scripts de compilación, pruebas y despliegue. |
| **Control y metodología** | Git y Scrum | Versionado del código y gestión ágil del proyecto. |

### 8.3 Entornos y herramientas

- **Entornos de desarrollo:** VSCode (motor C, PHP, dashboard) y Android Studio (app móvil).
- **Control de versiones:** Git (repositorio único del proyecto).

### 8.4 Flujo de transacción (camino feliz)

1. **Ingesta:** el sistema origen (ERP, banca o pasarela) envía una transacción por HTTP/JSON al endpoint de la API REST.
2. **Validación:** PHP valida autenticidad y estructura de la petición.
3. **Evaluación:** PHP invoca al ejecutable C, que calcula el **score de riesgo (0.0 – 1.0)** considerando desviación del monto respecto al historial, frecuencia de transacciones por hora y cambios de ubicación o IPs inusuales.
4. **Clasificación y decisión:** Bajo (< 0.4) → aprobada; Medio (0.4 – 0.7) → bajo revisión con alerta preventiva; Alto (> 0.7) → **bloqueo inmediato** y emisión de alerta de fraude.
5. **Persistencia y auditoría:** transacción, evaluación y decisión se registran en Oracle DB (ACID + auditoría bancaria).
6. **Notificación:** si el riesgo es **medio o alto**, se envía un push vía Firebase FCM a la app Android y se actualiza el estado en Realtime Database.
7. **Acción móvil:** el oficial de seguridad o el usuario revisa la alerta y, de ser necesario, ejecuta el **bloqueo preventivo inmediato** desde la app.
8. **Cierre de auditoría:** la acción tomada desde el móvil se registra de vuelta en Oracle DB, cerrando el ciclo trazable de la operación.

## 9. Modelo de datos (propuesta inicial en Oracle DB)

> Nota: esquema propuesto como línea base para el diseño físico; los nombres definitivos se definirán en la fase de diseño detallado.

| Tabla | Descripción | Atributos clave (propuestos) |
|---|---|---|
| `TRANSACCION` | Transacción recibida para evaluación. | `id`, `origen` (ERP/banca/pasarela), `id_cliente`, `monto`, `moneda`, `fecha_hora`, `ubicacion`, `ip_origen`, `canal` |
| `EVALUACION` | Resultado del motor C para cada transacción. | `id`, `id_transaccion`, `score` (0.0–1.0), `variables_usadas`, `clasificacion` (BAJO/MEDIO/ALTO), `decision`, `fecha_evaluacion` |
| `ALERTA` | Alerta emitida ante riesgo medio/alto. | `id`, `id_evaluacion`, `tipo` (PREVENTIVA/FRAUDE), `estado` (PENDIENTE/ATENDIDA/BLOQUEADA), `atendida_por`, `respuesta` |
| `DISPOSITIVO_MOVIL` | Dispositivos registrados para notificación. | `id`, `usuario`, `rol`, `token_fcm`, `activo` |
| `AUDITORIA` | Registro inmutable de cada evaluación y decisión. | `id`, `entidad`, `accion`, `usuario_o_sistema`, `detalle`, `fecha_hora` |

## 10. Historias de usuario

| ID | Como... | Quiero... | Para... |
|---|---|---|---|
| **HU-01** | sistema ERP / banca / pasarela | enviar transacciones vía HTTP/JSON y recibir el score con la decisión | automatizar la operación sin intervención humana. |
| **HU-02** | oficial de seguridad financiera | recibir una notificación push inmediata cuando una transacción sea de riesgo medio o alto | revisar la operación y actuar antes de que el fraude se materialice. |
| **HU-03** | oficial de seguridad financiera | ejecutar el bloqueo preventivo directamente desde la app Android | detener la operación sospechosa de forma inmediata. |
| **HU-04** | usuario final (titular) | ser notificado en mi teléfono ante movimientos inusuales en mi cuenta | solicitar el bloqueo preventivo si no reconozco la operación. |
| **HU-05** | auditor | que cada evaluación y decisión quede registrada en Oracle DB | reconstruir el historial completo de decisiones con fines de cumplimiento bancario. |
| **HU-06** | administrador del sistema | contar con un dashboard web con gráficos de desempeño | monitorear el volumen transaccional y el comportamiento del motor de riesgo. |

## 11. Métricas de éxito (KPIs)

| Métrica | Objetivo |
|---|---|
| Latencia de evaluación del motor C | En el orden de microsegundos (ultra baja latencia). |
| Latencia extremo a extremo de la API | ≤ 1 segundo (meta de diseño). |
| Tiempo de entrega de notificación push (riesgo medio/alto) | < 2 segundos desde la decisión. |
| Cobertura de auditoría | 100 % de las evaluaciones registradas en Oracle DB. |
| Trazabilidad del ciclo | 100 % de bloqueos preventivos con registro de origen, decisión y acción. |
| Calidad del modelo de scoring | Reducción de falsos negativos (fraudes no detectados) manteniendo falsos positivos controlados; línea base por definir con datos de prueba. |
| Disponibilidad de la API | ≥ 99.5 % en horario operativo. |

## 12. Entregables

1. **Código fuente del motor de análisis en C** (cálculo de score y clasificación).
2. **API REST en PHP 8+** con endpoints de ingesta y consulta.
3. **Scripts DDL de Oracle Database** (esquema transaccional y tablas de auditoría).
4. **App Android nativa (Java)** con gestión de notificaciones y bloqueo preventivo.
5. **Configuración de Firebase** (FCM + Realtime Database).
6. **Dashboard web administrativo** (HTML5/CSS/JavaScript).
7. **Scripts PowerShell (.ps1)** de compilación, pruebas y despliegue.
8. **Repositorio Git** con el historial completo del proyecto.
9. **Documentación técnica** (manual de instalación, operación y prueba del sistema).

## 13. Plan de trabajo tentativo (Sprints Scrum)

> Sprints de 2 semanas; el plan exacto se ajustará en el Sprint Planning.

| Sprint | Objetivo del sprint |
|---|---|
| **Sprint 1** | Configuración del entorno (Git, VSCode, Android Studio), definición del contrato JSON de la API y diseño del modelo de datos Oracle. |
| **Sprint 2** | Desarrollo del motor C: cálculo del score (0.0–1.0) con las tres variables y pruebas unitarias de latencia. |
| **Sprint 3** | Desarrollo de la API REST en PHP 8+, integración con el ejecutable C y persistencia/auditoría en Oracle DB. |
| **Sprint 4** | Integración Firebase FCM/Realtime Database y desarrollo de la app Android (alertas y bloqueo preventivo). |
| **Sprint 5** | Dashboard web, scripts PowerShell de build/test/deploy, pruebas de integración extremo a extremo y documentación final. |

## 14. Riesgos y mitigación

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Latencia adicional al invocar el ejecutable C desde PHP (creación de proceso por transacción). | Alto | Optimizar la invocación (proceso persistente / pipes) y medir con benchmarks en cada sprint. |
| Falsos positivos del modelo de scoring que bloqueen operaciones legítimas. | Alto | Calibrar reglas/umbrales con datos históricos de prueba y usar el estado "Medio (revisión)" como banda de amortiguamiento. |
| Dependencia de conectividad para la entrega de notificaciones push (FCM). | Medio | Implementar reintentos y respaldar el estado en Firebase Realtime Database para sincronizar al reconectar. |
| Complejidad/licenciamiento de Oracle Database en entorno de residencia. | Medio | Utilizar una edición gratuita para desarrollo (p. ej. Oracle XE) y mantener el DDL portable. |
| Coordinación de un stack multi-tecnología (C, PHP, Android, Firebase) en el equipo. | Medio | Contratos de interfaz claros (JSON de entrada/salida), Git con ramas por componente y ceremonias Scrum frecuentes. |
| Datos sensibles en las transacciones. | Alto | Cifrado en tránsito (TLS), acceso restringido por rol y auditoría de accesos. |

## 15. Supuestos y restricciones

- Los umbrales de clasificación (0.4 y 0.7) son los definidos en el documento fuente `ideas.txt`; se asume que podrán ajustarse como parámetros de configuración.
- La versión 1.0 del motor se basa en **scoring por reglas** sobre las variables descritas; los modelos ML entrenados quedan como evolución futura.
- Los sistemas origen consumen la API; MIDFF no se integra directamente con el core bancario.
- La infraestructura de Firebase (FCM / Realtime Database) requiere cuenta y proyecto Firebase configurados.

## 16. Glosario

| Término | Definición |
|---|---|
| **ACID** | Propiedades de las transacciones de base de datos: Atomicidad, Consistencia, Aislamiento y Durabilidad. |
| **ERP** | Enterprise Resource Planning; sistema de gestión empresarial que puede ser origen de transacciones. |
| **FCM** | Firebase Cloud Messaging; servicio de envío de notificaciones push de Google. |
| **Score de riesgo** | Valor numérico entre 0.0 y 1.0 que representa la probabilidad/severidad de riesgo de una transacción. |
| **Bloqueo preventivo** | Acción que detiene una operación o cuenta de forma inmediata ante una alerta de fraude. |
| **Push (notificación)** | Mensaje enviado del servidor al dispositivo móvil sin que la app lo solicite activamente. |
| **Auditoría bancaria** | Estándares y registros que permiten verificar y reconstruir las decisiones sobre operaciones financieras. |
| **Pasarela de pago** | Servicio que procesa pagos electrónicos y actúa como origen de transacciones. |
| **Scrum** | Marco ágil de gestión de proyectos basado en sprints y entregas incrementales. |

---

**Fin del documento — PRD MIDFF v1.0**




