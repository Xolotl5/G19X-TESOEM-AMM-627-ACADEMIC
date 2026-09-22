# Motor Inteligente de Detección de Fraude Financiero (MIDFF)

---

### Información General
* **Nombre del Sistema:** Motor Inteligente de Detección de Fraude Financiero
* **Siglas:** MIDFF
* **Tipo de Proyecto:** Residencias Profesionales – Ingeniería en Sistemas Computacionales
* **Formato:** Markdown (`README.md`)
* **Estado:** En Desarrollo
* **Licencia:** Privada / Académica

El **Motor Inteligente de Detección de Fraude Financiero (MIDFF)** es un sistema de software diseñado para identificar patrones anómalos, clasificar riesgos en operaciones financieras y emitir alertas tempranas en tiempo real. Integra información proveniente de sistemas ERP, banca electrónica y plataformas transaccionales para fortalecer el control financiero y reducir pérdidas operativas.

---

## Índice
1. [Descripción General y Delimitación](#1-descripción-general-y-delimitación)
2. [Arquitectura Tecnológica](#2-arquitectura-tecnológica)
3. [Estructura del Repositorio](#3-estructura-del-repositorio)
4. [Clasificación de Riesgo Transaccional](#4-clasificación-de-riesgo-transaccional)
5. [Instrucciones de Instalación y Operación](#5-instrucciones-de-instalación-y-operación)
6. [Registro de Cambios y Bitácora](#6-registro-de-cambios-y-bitácora)
7. [Créditos y Información del Desarrollador](#7-créditos-y-información-del-desarrollador)

---

## 1. Descripción General y Delimitación

El motor utiliza inteligencia artificial y modelos de aprendizaje automático para analizar transacciones financieras en tiempo real. 

### Características Principales:
* **Ingesta de Datos:** Recepción de operaciones mediante solicitudes HTTP/JSON desde plataformas de banca y ERP.
* **Evaluación de Riesgo:** Procesamiento analítico mediante algoritmos de baja latencia.
* **Alertamiento Temprano:** Notificaciones push instantáneas a dispositivos móviles registrados.
* **Auditoría:** Persistencia de historiales y eventos en bases de datos relacionales empresariales.

---

## 2. Arquitectura Tecnológica

El sistema implementa una arquitectura híbrida dividida en cuatro capas funcionales:

| Capa del Sistema | Tecnología | Función Principal |
| :--- | :--- | :--- |
| **Motor Analítico** | **C** | Procesamiento transaccional de ultra baja latencia y cálculo del Score de Riesgo. |
| **Backend / API REST** | **PHP 8+** | Orquestación de servicios HTTP, recepción de peticiones JSON e integración del ejecutable C. |
| **Base de Datos** | **Oracle Database** | Almacenamiento seguro transaccional (ACID) y tablas de auditoría. |
| **Cliente Móvil** | **Android Studio (Java)** | Aplicación nativa para la recepción de alertas push y gestión de bloqueos. |
| **Mensajería Tiempo Real**| **Firebase FCM** | Canal de transmisión inmediata entre servidor y dispositivos móviles. |
| **Panel Administrativo** | **HTML5 / CSS3 / JS** | Dashboard web de monitoreo y gráficos de desempeño. |
| **Automatización** | **PowerShell (`.ps1`)** | Scripts de compilación, validación y despliegue local. |