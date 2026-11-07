<#
.SYNOPSIS
    Calibracion del motor MIDFF con datos etiquetados (BIT-039, Corte 4/5).
.DESCRIPTION
    Pasa cada caso de datos\datasets\calibracion_etiquetada.json por
    src\motor\bin\midff-scoring.exe (stdin JSON -> stdout JSON) y mide la
    matriz de confusion frente al campo is_fraude (verdad terrestre definida
    en el propio dataset, ANTES de correr el motor):

      TP fraude con alerta (score >= umbral)   FP sano con alerta (molesta al cliente)
      FN fraude sin alerta (se le escapa)      VN sano sin alerta

    Umbral de alerta actual del motor: 0.40 (score >= 0.4 crea ALERTA). Como
    el score no depende del umbral, se comparan tambien 0.40 vs 0.35 sin
    recompilar.

    Corte 5 (casos dificiles y pesos):
      - Guarda los tres puntajes por variable que devuelve el motor
        (variables.monto.puntaje, variables.frecuencia.puntaje y
        variables.ubicacion_ip.puntaje) para recalcular el score con OTRA
        combinacion de pesos SIN recompilar: 0.45/0.30/0.25 (motor) contra
        0.35/0.30/0.35, ambas con umbral 0.40.
      - Pasa por el mismo proceso los 20 casos de datos\datasets\
        casos_dificiles.json, etiquetados con criterios EXTERNOS al motor
        (cada caso trae razon_etiqueta).
.NOTES
    Uso: .\calibrar.ps1   (desde la raiz del repositorio)
#>
$ErrorActionPreference = 'Stop'
$raiz      = $PSScriptRoot
$motorExe  = Join-Path $raiz 'src\motor\bin\midff-scoring.exe'
if (-not (Test-Path $motorExe)) { throw "Falta $motorExe (ejecute .\build.ps1)" }

function Invoke-Caso {
    param($c)
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
    # Corte 5: se guardan los puntajes por variable para recalcular pesos.
    [pscustomobject]@{
        id = $c.id_transaccion; razon = [string]$c.razon_etiqueta; is_fraude = [bool]$c.is_fraude
        score = [double]$j.score; clasificacion = $j.clasificacion
        pm = [double]$j.variables.monto.puntaje
        pf = [double]$j.variables.frecuencia.puntaje
        pu = [double]$j.variables.ubicacion_ip.puntaje
    }
}

$dataSet    = Get-Content (Join-Path $raiz 'datos\datasets\calibracion_etiquetada.json') -Raw | ConvertFrom-Json
$resultados = foreach ($c in $dataSet.transacciones) { Invoke-Caso $c }

$rutaDif = Join-Path $raiz 'datos\datasets\casos_dificiles.json'
$dificiles = @()
if (Test-Path $rutaDif) {
    $dataDif   = Get-Content $rutaDif -Raw | ConvertFrom-Json
    $dificiles = foreach ($c in $dataDif.transacciones) { Invoke-Caso $c }
}

function Matriz {
    param($res, [double]$umbral)
    $tp = @($res | Where-Object { $_.is_fraude -and $_.score -ge $umbral }).Count
    $fp = @($res | Where-Object { -not $_.is_fraude -and $_.score -ge $umbral }).Count
    $fn = @($res | Where-Object { $_.is_fraude -and $_.score -lt $umbral }).Count
    $vn = @($res | Where-Object { -not $_.is_fraude -and $_.score -lt $umbral }).Count
    $prec = if (($tp + $fp) -gt 0) { [math]::Round(100 * $tp / ($tp + $fp), 1) } else { 0 }
    $rec  = if (($tp + $fn) -gt 0) { [math]::Round(100 * $tp / ($tp + $fn), 1) } else { 0 }
    $f1   = if (($prec + $rec) -gt 0) { [math]::Round(2 * $prec * $rec / ($prec + $rec), 1) } else { 0 }
    [pscustomobject]@{ Umbral = $umbral; TP = $tp; FP = $fp; FN = $fn; VN = $vn; 'Precision %' = $prec; 'Recall %' = $rec; 'F1' = $f1 }
}

# Corte 5: recalcular el score con otros pesos a partir de los puntajes por
# variable que ya devolvio el motor (sin recompilar el .exe).
function Score($r, $wm, $wf, $wu) { [math]::Round($wm * $r.pm + $wf * $r.pf + $wu * $r.pu, 4) }

function MatrizPesos {
    param($res, [double]$wm, [double]$wf, [double]$wu, [double]$umbral)
    $tp = @($res | Where-Object { $_.is_fraude -and (Score $_ $wm $wf $wu) -ge $umbral }).Count
    $fp = @($res | Where-Object { -not $_.is_fraude -and (Score $_ $wm $wf $wu) -ge $umbral }).Count
    $fn = @($res | Where-Object { $_.is_fraude -and (Score $_ $wm $wf $wu) -lt $umbral }).Count
    $vn = @($res | Where-Object { -not $_.is_fraude -and (Score $_ $wm $wf $wu) -lt $umbral }).Count
    $prec = if (($tp + $fp) -gt 0) { [math]::Round(100 * $tp / ($tp + $fp), 1) } else { 0 }
    $rec  = if (($tp + $fn) -gt 0) { [math]::Round(100 * $tp / ($tp + $fn), 1) } else { 0 }
    $f1   = if (($prec + $rec) -gt 0) { [math]::Round(2 * $prec * $rec / ($prec + $rec), 1) } else { 0 }
    [pscustomobject]@{ Pesos = ("{0:N2}/{1:N2}/{2:N2}" -f $wm, $wf, $wu); Umbral = $umbral
                       TP = $tp; FP = $fp; FN = $fn; VN = $vn
                       'Precision %' = $prec; 'Recall %' = $rec; 'F1' = $f1 }
}

Write-Host ('=' * 78)
Write-Host (' Conjunto base: {0} casos ({1} fraudes)  Pesos motor: 0.45/0.30/0.25' -f $resultados.Count, @($resultados | Where-Object is_fraude).Count)
Write-Host ('=' * 78)
@(Matriz $resultados 0.40; Matriz $resultados 0.35) | Format-Table -AutoSize | Out-Host
$fn40 = @(Matriz $resultados 0.40)
if ($fn40.FN -gt 0) {
    Write-Host 'Fraudes que se escapan con 0.40:'
    $resultados | Where-Object { $_.is_fraude -and $_.score -lt 0.40 } | Format-Table id, score, clasificacion -AutoSize | Out-Host
}

if ($dificiles.Count -gt 0) {
    Write-Host ('=' * 78)
    Write-Host (' Casos dificiles ({0} casos, {1} fraudes): etiqueta con criterios externos al motor' -f $dificiles.Count, @($dificiles | Where-Object is_fraude).Count)
    Write-Host ('=' * 78)
    $dificiles | Format-Table id, is_fraude, @{ n = 'pm'; e = { '{0:N2}' -f $_.pm } }, @{ n = 'pf'; e = { '{0:N2}' -f $_.pf } }, @{ n = 'pu'; e = { '{0:N2}' -f $_.pu } }, @{ n = 'score'; e = { '{0:N4}' -f $_.score } }, clasificacion -AutoSize | Out-Host

    Write-Host ('-' * 78)
    Write-Host ' Comparacion de pesos (umbral 0.40, recalculado sin recompilar)'
    Write-Host ('-' * 78)
    @(MatrizPesos $dificiles 0.45 0.30 0.25 0.40; MatrizPesos $dificiles 0.35 0.30 0.35 0.40) | Format-Table -AutoSize | Out-Host

    $m = MatrizPesos $dificiles 0.45 0.30 0.25 0.40
    if ($m.FN -gt 0) {
        Write-Host 'Fraudes que se escapan con 0.45/0.30/0.25 @ 0.40 (y recalculo 0.35/0.30/0.35):'
        $dificiles | Where-Object { $_.is_fraude -and $_.score -lt 0.40 } |
            Select-Object id, @{ n = 'score'; e = { '{0:N4}' -f $_.score } }, @{ n = 'alt'; e = { '{0:N4}' -f (Score $_ 0.35 0.30 0.35) } }, razon |
            Format-Table -AutoSize -Wrap | Out-Host
    }
    if ($m.FP -gt 0) {
        Write-Host 'Sanos que igual generan alerta (FP) con 0.45/0.30/0.25 @ 0.40 (y recalculo 0.35/0.30/0.35):'
        $dificiles | Where-Object { -not $_.is_fraude -and $_.score -ge 0.40 } |
            Select-Object id, @{ n = 'score'; e = { '{0:N4}' -f $_.score } }, @{ n = 'alt'; e = { '{0:N4}' -f (Score $_ 0.35 0.30 0.35) } }, razon |
            Format-Table -AutoSize -Wrap | Out-Host
    }
}
