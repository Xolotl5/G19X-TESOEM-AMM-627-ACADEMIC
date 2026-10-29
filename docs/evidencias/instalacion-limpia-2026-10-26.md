# Evidencia de instalación limpia — 26/10/2026 (BIT-039, Corte 4)

**Objetivo:** levantar el proyecto como lo haría el revisor: clonar el repo en una
carpeta nueva y seguir el README §0 al pie de la letra, sin usar el `.env`, los
binarios compilados ni el `google-services.json` de la carpeta de trabajo.

**Commit probado:** `7742a9d` («README actualizado», HEAD en GitHub al momento del ensayo).
**Máquina:** Windows 11 · Oracle XE 21c local · PHP 8.1.10 (Laragon) · gcc (MinGW).

| # | Paso (README §0) | Resultado | Tiempo |
|---|---|---|---|
| 1 | `git clone` a `C:\prueba-midff` | ✔ limpio: sin `.env`, sin `midff-scoring.exe`, sin credenciales Firebase | 8.9 s |
| 2 | `.env` a partir de `.env.example` + cuenta de servicio en `config\` (credenciales reales locales) | ✔ | <1 s |
| 3 | `.\build.ps1` (compila motor C + 167 verificaciones) | ✔ `RESULTADO: BUILD CORRECTO`; `midff-scoring.exe` generado | ~50 s |
| 4 | `.\smoke-test.ps1` (3 bandas + negativas + §6b autorización + auditoría) | ✔ `RESULTADO: SMOKE TEST OK` (promedio 539 ms) | ~40 s |
| 5 | `.\servidor.ps1` (PATH de Oracle autodetectado sin `MIDFF_ORACLE_BIN`, firewall, 0.0.0.0:8091, secuencias realineadas) | ✔ levanta y expone consola/simulador | ~15 s |

**Notas honestas:**
- El **primer POST** de cada ronda paga el arranque en frío (conexión inicial a Oracle
  inactiva): 1.8–3.8 s la primera vez; con la BD caliente, promedio 539 ms. Ya está
  documentado en el README §7 y no es regresión del código.
- Los scripts de `database/` **no se re-ejecutaron** (el esquema `MIDFFuser` ya existía en
  el XE local, incluida `CLIENTE_ALTA`); en una máquina nueva el README §0 paso 1 los
  instala en orden.
- **No se probó en teléfono** en esta ronda: el APK del *Release* v1.3 y el flujo CP-A6/A7
  con una cuenta nueva quedan como verificación manual del revisor (pasos en §0 y §5.1 del
  `Reporte_QA.md`).
- Durante el ensayo se usaron las credenciales reales locales (mismo XE y mismo proyecto
  Firebase `midff-57e6b`), pues son insumos que el revisor sustituirá por los suyos.

**Conclusión:** la sección 0 del README reproduce el levantamiento completo sin pasos
faltantes; el único costo inicial documentado es el arranque en frío de Oracle.
