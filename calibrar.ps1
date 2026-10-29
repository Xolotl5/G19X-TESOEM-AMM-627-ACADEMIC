<#
.SYNOPSIS
    Calibracion del motor MIDFF con datos etiquetados (BIT-039, Corte 4).
.DESCRIPTION
    Pasa cada caso de datos\datasets\calibracion_etiquetada.json por
    src\motor\bin\midff-scoring.exe (stdin JSON -> stdout JSON) y mide la
    matriz de confusion frente al campo is_fraude (verdad terrestre definida
    en el propio dataset, ANTES de correr el motor):

      TP fraude con alerta (score >= umbral)   FP sano con alerta (molesta al cliente)
      FN fraude sin alerta (se le escapa)      VN sano sin alerta

    Umbral de alerta actual del motor: 0.40 (score >= 0.4 crea ALERTA). Como
    el score no depende del umbral, se comparan tambien 0.40 vs 0.35 sin
    recompilar. Los pesos actuales (PESO_MONTO 0.45 / PESO_FRECUENCIA 0.30 /
    PESO_UBICACION_IP 0.25) no se tocan en esta corrida.
.NOTES
    Uso: .\calibrar.ps1   (desde la raiz del repositorio)
#>
$ErrorActionPreference = 'Stop'
$raiz      = $PSScriptRoot
$dataSet   = Get-Content (Join-Path $raiz 'datos\datasets\calibracion_etiquetada.json') -Raw | ConvertFrom-Json
$motorExe  = Join-Path $raiz 'src\motor\bin\midff-scoring.exe'
if (-not (Test-Path $motorExe)) { throw "Falta $motorExe (ejecute .\build.ps1)" }

$resultados = foreach ($c in $dataSet.transacciones) {
    $entrada = @{
        id_cliente = $c.id_cliente
        monto      = $c.monto
        ubicacion  = $c.ubicacion
        ip_origen  = $c.ip_origen
        contexto   = @{
            monto_promedio_historial = $c.contexto.monto_promedio_historial
            transacciones_ultima_hora = $c.contexto.transacciones_ultima_hora
            ubicacion_habitual = $c.contexto.ubicacion_habitual
            ip_habitual        = $c.contexto.ip_habitual
        }
    } | ConvertTo-Json -Compress -Depth 4
    $salida = $entrada | & $motorExe 2>$null | Out-String
    $j = $salida | ConvertFrom-Json
    [pscustomobject]@{ is_fraude = [bool]$c.is_fraude; score = [double]$j.score; clasificacion = $j.clasificacion }
}

function Matriz {
    param([double]$umbral)
    $tp = ($resultados | Where-Object { $_.is_fraude -and $_.score -ge $umbral }).Count
    $fp = ($resultados | Where-Object { -not $_.is_fraude -and $_.score -ge $umbral }).Count
    $fn = ($resultados | Where-Object { $_.is_fraude -and $_.score -lt $umbral }).Count
    $vn = ($resultados | Where-Object { -not $_.is_fraude -and $_.score -lt $umbral }).Count
    $prec = if (($tp + $fp) -gt 0) { [math]::Round(100 * $tp / ($tp + $fp), 1) } else { 0 }
    $rec  = if (($tp + $fn) -gt 0) { [math]::Round(100 * $tp / ($tp + $fn), 1) } else { 0 }
    $f1   = if (($prec + $rec) -gt 0) { [math]::Round(2 * $prec * $rec / ($prec + $rec), 1) } else { 0 }
    [pscustomobject]@{ Umbral = $umbral; TP = $tp; FP = $fp; FN = $fn; VN = $vn; 'Precision %' = $prec; 'Recall %' = $rec; 'F1' = $f1 }
}

Write-Host ("Casos: {0} (fraudes: {1})  Pesos: 0.45/0.30/0.25 (sin cambios)" -f $resultados.Count, ($resultados | Where-Object is_fraude).Count)
@(Matriz 0.40; Matriz 0.35) | Format-Table -AutoSize | Out-Host
$fn40 = @(Matriz 0.40)
if ($fn40.FN -gt 0) {
    Write-Host 'Fraudes que se escapan con 0.40:'
    $resultados | Where-Object { $_.is_fraude -and $_.score -lt 0.40 } | Format-Table score, clasificacion -AutoSize | Out-Host
}
