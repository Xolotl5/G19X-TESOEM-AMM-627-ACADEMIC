# Evidencia — Corte 5: limpieza de errores Oracle + calibración con casos difíciles

**Fecha:** 07/11/2026 · **Base:** commit `214c426` + cambios sin commitear (este corte)

## 1. Contextos de error sin pistas para el cliente

| Archivo | Línea (antes) | Cambio |
|---|---|---|
| `api/v1/admin.php` | 73 | «(ejecute database/admin_usuario.sql)» movido a `error_log` aparte; contexto sin dos puntos finales |
| `api/v1/admin.php` | 418 | «(ejecute database/cliente_alta.sql): » movido a `error_log` aparte; contexto limpio |
| `api/v1/dispositivos.php` | 194 | quitado el `: ` final del contexto |
| `api/v1/dispositivos.php` | 326 | quitado el `: ` final del contexto |
| `config/http.php` | 181 | quitado el `: ` final del contexto |
| `config/firebase.php` | 672 | quitado «(ejecute database/dispositivo_movil.sql …)» del `mensaje` devuelto |

El detalle del error sigue llegando al log (`[MIDFF-DB] contexto: ORA-…` vía
`midff_error_bd`); solo cambió lo que ve quien llama. `php -l` sin errores en los
cuatro archivos.

## 2. smoke-test.ps1 posterior a los cambios (corrida registrada)

```
.\smoke-test.ps1 -SinServidor -UrlApi http://127.0.0.1:8092   (servidor ya caliente)
RESULTADO: SMOKE TEST OK (bandas, contrato, latencia, autorizacion BIT-037 y auditoria verificados)
Latencias: BAJO 755.3 ms · MEDIO 219.2 ms · ALTO 217.2 ms (objetivo <= 1000 ms)
```

Nota: en arranque en frío la primera petición puede rozar ~1.1 s (PHP + conexión
Oracle se inicializan); con el servidor caliente todo queda por debajo del
objetivo, como en las corridas anteriores (p. ej. la de `7742a9d`).

## 3. Calibración Corte 5 (`.\calibrar.ps1`)

Conjunto base (80 casos, BIT-039) **sin cambios**: P/R/F1 100 % en 0.40 y 0.35.
Nuevo `datos/datasets/casos_dificiles.json` (20 casos etiquetados con criterios
externos al motor, cada uno con `razon_etiqueta`):

| Pesos | Umbral | TP | FP | FN | VN |
|---|---|---|---|---|---|
| 0.45/0.30/0.25 (motor) | 0.40 | 2 | 5 | 8 | 5 |
| 0.35/0.30/0.35 (alt.) | 0.40 | 1 | 3 | 9 | 7 |

Decisión: se mantienen 0.45/0.30/0.25 (los pesos alternativos dejan escapar el
vaciamiento TRX-DIF-10). Tabla y análisis completos en `docs/Informe_Final.md` §7.

## 4. Pendiente (ensayo en carpeta limpia con teléfono)

Falta repetir el ensayo de instalación limpia con el APK del Release v1.3 y
CP-A6/CP-A7 con dos cuentas (requisita teléfono físico y esquema Oracle nuevo).
