-- 1. Verificar la cantidad de registros en cada tabla
SELECT 'TRANSACCION' AS tabla, COUNT(*) AS total FROM TRANSACCION
UNION ALL
SELECT 'EVALUACION' AS tabla, COUNT(*) AS total FROM EVALUACION
UNION ALL
SELECT 'ALERTA' AS tabla, COUNT(*) AS total FROM ALERTA
UNION ALL
SELECT 'AUDITORIA' AS tabla, COUNT(*) AS total FROM AUDITORIA;


SELECT id_dispositivo, usuario, rol, activo, fecha_registro
  FROM DISPOSITIVO_MOVIL;   