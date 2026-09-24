<#
.SYNOPSIS
    Compila el motor de scoring en C del proyecto MIDFF con gcc y ejecuta sus pruebas.

.DESCRIPTION
    Automatiza los entregables del Sprint 1 relacionados con el motor C:
      1. Compilacion del ejecutable del motor (src/motor/*.c -> src/motor/bin/midff-scoring.exe).
      2. Compilacion de la suite de pruebas unitarias y de la suite de latencia en microsegundos.
      3. Ejecucion de las pruebas unitarias (3 variables y umbrales 0.4 / 0.7).
      4. Ejecucion del benchmark de latencia (<time.h>, percentiles en microsegundos).
      5. Pruebas de integracion del ejecutable por linea de comandos (stdin/stdout, codigos de salida).

    Requisitos: RF-03, RF-04, RF-05, RF-08, RF-15, RNF-01 y RNF-09.
    Entregable del Sprint 1 segun MVP_MIDFF.md seccion 11 ("build.ps1").

.PARAMETER Configuracion
    Release (por omision) compila con -O2; Debug compila con -g -O0.

.PARAMETER Limpiar
    Borra la carpeta de salida src/motor/bin antes de compilar.

.PARAMETER SinPruebas
    Solo compila (no ejecuta pruebas ni benchmark).

.PARAMETER IteracionesLatencia
    Numero de iteraciones del benchmark de latencia (por omision 200000).

.EXAMPLE
    .\build.ps1
    .\build.ps1 -Configuracion Debug -Limpiar
    .\build.ps1 -SinPruebas -IteracionesLatencia 50000

.NOTES
    Proyecto: MIDFF - Motor Inteligente de Deteccion de Fraude Financiero (Sprint 1).
    El ejecutable y los binarios de prueba se generan en src/motor/bin, carpeta excluida en .gitignore.
#>

[CmdletBinding()]
param(
    [ValidateSet('Debug', 'Release')]
    [string] $Configuracion = 'Release',

    [switch] $Limpiar,

    [switch] $SinPruebas,

    [int] $IteracionesLatencia = 200000
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

# Salida de texto en UTF-8 para que el JSON del motor se lea correctamente.
$OutputEncoding = New-Object System.Text.UTF8Encoding($false)
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch { Write-Verbose 'Salida de consola no modificada.' }

# -----------------------------------------------------------------------------
# Rutas del repositorio y nombres de los artefactos
# -----------------------------------------------------------------------------
$script:RaizRepositorio = if ([string]::IsNullOrEmpty($PSScriptRoot)) { (Get-Location).Path } else { $PSScriptRoot }
$script:CarpetaMotor    = Join-Path $script:RaizRepositorio 'src\motor'
$script:CarpetaPruebas  = Join-Path $script:CarpetaMotor 'pruebas'
$script:CarpetaBin      = Join-Path $script:CarpetaMotor 'bin'

$script:FuentesMotor = @(
    (Join-Path $script:CarpetaMotor 'scoring.c'),
    (Join-Path $script:CarpetaMotor 'json_io.c'),
    (Join-Path $script:CarpetaMotor 'cronometro.c')
)

$script:EjecutableMotor  = Join-Path $script:CarpetaBin 'midff-scoring.exe'
$script:EjecutablePruebas = Join-Path $script:CarpetaBin 'midff-test-scoring.exe'
$script:EjecutableLatencia = Join-Path $script:CarpetaBin 'midff-test-latencia.exe'

# Resultados para el resumen final.
$script:Fallos            = New-Object System.Collections.Generic.List[string]
$script:ResultadosCli     = New-Object System.Collections.Generic.List[string]
$script:Compilaciones     = 0

# -----------------------------------------------------------------------------
# Presentacion en consola
# -----------------------------------------------------------------------------
function Escribir-Encabezado {
    param([string] $Texto)
    Write-Host ''
    Write-Host ('=' * 71) -ForegroundColor DarkCyan
    Write-Host (" MIDFF | $Texto") -ForegroundColor Cyan
    Write-Host ('=' * 71) -ForegroundColor DarkCyan
}

function Escribir-Paso {
    param([string] $Texto)
    Write-Host ''
    Write-Host (">> $Texto") -ForegroundColor White
}

function Escribir-Ok {
    param([string] $Texto)
    Write-Host ("   [OK]    $Texto") -ForegroundColor Green
}

function Escribir-Aviso {
    param([string] $Texto)
    Write-Host ("   [AVISO] $Texto") -ForegroundColor Yellow
}

function Escribir-Fallo {
    param([string] $Texto)
    Write-Host ("   [FALLO] $Texto") -ForegroundColor Red
    $script:Fallos.Add($Texto) | Out-Null
}

# -----------------------------------------------------------------------------
# Localizacion del compilador gcc (MinGW-w64 / MinGW)
# -----------------------------------------------------------------------------
function Buscar-Gcc {
    $comando = Get-Command gcc -ErrorAction SilentlyContinue
    if ($null -ne $comando) {
        return $comando.Source
    }

    $rutasConocidas = @(
        'C:\MinGW\bin\gcc.exe',
        'C:\MinGW64\bin\gcc.exe',
        'C:\msys64\mingw64\bin\gcc.exe',
        'C:\msys64\mingw32\bin\gcc.exe',
        'C:\Program Files\mingw-w64\mingw64\bin\gcc.exe'
    )
    foreach ($ruta in $rutasConocidas) {
        if (Test-Path -LiteralPath $ruta) {
            return $ruta
        }
    }

    $detectado = Get-ChildItem -Path 'C:\' -Filter 'gcc.exe' -Recurse -ErrorAction SilentlyContinue `
                               -Depth 4 | Select-Object -First 1
    if ($null -ne $detectado) {
        return $detectado.FullName
    }

    return $null
}

# -----------------------------------------------------------------------------
# Compilacion
# -----------------------------------------------------------------------------
function Invoke-Compilacion {
    param(
        [string]   $Nombre,
        [string[]] $Fuentes,
        [string]   $Destino,
        [switch]   $ComoPrueba
    )

    $argumentos = New-Object System.Collections.Generic.List[string]
    $argumentos.Add('-std=c99')
    $argumentos.Add('-Wall')
    $argumentos.Add('-Wextra')
    $argumentos.Add('-pedantic')
    if ($Configuracion -eq 'Release') {
        $argumentos.Add('-O2')
    }
    else {
        $argumentos.Add('-g')
        $argumentos.Add('-O0')
    }
    $argumentos.Add('-I' + $script:CarpetaMotor)
    if ($ComoPrueba) {
        # Excluye el main() del motor para enlazar las suites de prueba.
        $argumentos.Add('-DMIDFF_SIN_MAIN')
    }
    $argumentos.Add('-o')
    $argumentos.Add($Destino)
    foreach ($fuente in $Fuentes) {
        $argumentos.Add($fuente)
    }
    $argumentos.Add('-lm')

    Write-Host "   gcc $($argumentos -join ' ')" -ForegroundColor DarkGray
    $salida = & $script:Gcc @argumentos 2>&1
    if ($LASTEXITCODE -ne 0) {
        Escribir-Fallo "la compilacion de $Nombre termino con codigo $LASTEXITCODE"
        if ($salida) { Write-Host ($salida -join "`n") -ForegroundColor DarkRed }
        return $false
    }
    if ($salida) { Write-Host ($salida -join "`n") -ForegroundColor DarkYellow }

    $script:Compilaciones++
    $archivo = Get-Item -LiteralPath $Destino
    Escribir-Ok ("$Nombre compilado ({0:N0} bytes) -> {1}" -f $archivo.Length, $archivo.FullName)
    return $true
}

# -----------------------------------------------------------------------------
# Ejecucion de binarios de prueba
# -----------------------------------------------------------------------------
function Invoke-Binario {
    param(
        [string]   $Nombre,
        [string]   $Ruta,
        [string[]] $Argumentos = @()
    )

    Write-Host "   $Ruta $($Argumentos -join ' ')" -ForegroundColor DarkGray
    $cronometro = [System.Diagnostics.Stopwatch]::StartNew()
    $salida = & $Ruta @Argumentos 2>&1
    $codigo = $LASTEXITCODE
    $cronometro.Stop()
    $segundos = [math]::Round($cronometro.Elapsed.TotalSeconds, 2)

    foreach ($linea in $salida) {
        Write-Host ("   | $linea")
    }
    if ($codigo -ne 0) {
        Escribir-Fallo "$Nombre termino con codigo $codigo (duracion $segundos s)"
        return $false
    }
    Escribir-Ok "$Nombre correcto (duracion $segundos s)"
    return $true
}

# -----------------------------------------------------------------------------
# Pruebas de integracion del ejecutable (stdin/stdout, RF-03)
# -----------------------------------------------------------------------------
function Invoke-PruebaCli {
    param(
        [string] $Nombre,
        [string] $Json,
        [int]    $CodigoEsperado = 0,
        [string] $TextoEsperado = '',
        [double] $ScoreEsperado = -1,
        [string] $TextoErrorEsperado = ''
    )

    # El motor se invoca como proceso con entrada/salida por tuberia, igual que
    # lo hara la API REST en el Sprint 2 (RF-03): asi se capturan de forma
    # confiable stdout, stderr y el codigo de salida.
    $inicioInfo = New-Object System.Diagnostics.ProcessStartInfo
    $inicioInfo.FileName = $script:EjecutableMotor
    $inicioInfo.RedirectStandardInput = $true
    $inicioInfo.RedirectStandardOutput = $true
    $inicioInfo.RedirectStandardError = $true
    $inicioInfo.UseShellExecute = $false
    $inicioInfo.CreateNoWindow = $true
    $inicioInfo.StandardOutputEncoding = [System.Text.Encoding]::UTF8
    $inicioInfo.StandardErrorEncoding = [System.Text.Encoding]::UTF8

    $cronometro = [System.Diagnostics.Stopwatch]::StartNew()
    $proceso = [System.Diagnostics.Process]::Start($inicioInfo)
    $proceso.StandardInput.Write($Json)
    $proceso.StandardInput.Close()
    $texto = $proceso.StandardOutput.ReadToEnd()
    $textoError = $proceso.StandardError.ReadToEnd()
    $proceso.WaitForExit()
    $codigo = $proceso.ExitCode
    $cronometro.Stop()
    $proceso.Dispose()
    $milisegundos = [math]::Round($cronometro.Elapsed.TotalMilliseconds, 2)

    if ($codigo -ne $CodigoEsperado) {
        Escribir-Fallo "$Nombre esperaba salida $CodigoEsperado y obtuvo $codigo"
        return $false
    }
    if ($TextoErrorEsperado -ne '') {
        if (-not $textoError.Contains($TextoErrorEsperado)) {
            Escribir-Fallo "$Nombre no reporto el diagnostico esperado en stderr: $textoError"
            return $false
        }
    }
    elseif ($textoError.Trim() -ne '') {
        Escribir-Fallo "$Nombre escribio en stderr con una transaccion valida: $textoError"
        return $false
    }
    if ($TextoEsperado -ne '' -and -not $texto.Contains($TextoEsperado)) {
        Escribir-Fallo "$Nombre no contiene '$TextoEsperado' en la respuesta"
        return $false
    }

    $detalle = "codigo $codigo"
    if ($texto.Trim().StartsWith('{')) {
        try {
            $objeto = $texto | ConvertFrom-Json
            $detalle = "codigo $codigo, score $($objeto.score), $($objeto.clasificacion) / $($objeto.decision)"
            if ($ScoreEsperado -ge 0 -and [math]::Abs([double]$objeto.score - $ScoreEsperado) -gt 0.00005) {
                Escribir-Fallo ("{0} esperaba score {1:F4} y obtuvo {2}" -f $Nombre, $ScoreEsperado, $objeto.score)
                return $false
            }
        }
        catch {
            Escribir-Fallo "$Nombre devolvio una respuesta que no es JSON valido: $texto"
            return $false
        }
    }
    elseif ($TextoErrorEsperado -ne '') {
        $detalle = "codigo $codigo, diagnostico por stderr"
    }

    Escribir-Ok ("{0} -> {1}, {2} ms de proceso completo" -f $Nombre, $detalle, $milisegundos)
    $script:ResultadosCli.Add(("{0} | {1} | {2} ms" -f $Nombre, $detalle, $milisegundos)) | Out-Null
    return $true
}

# -----------------------------------------------------------------------------
# Ejecucion principal
# -----------------------------------------------------------------------------
Escribir-Encabezado 'build.ps1 - Motor de scoring en C (Sprint 1)'
Write-Host " Repositorio    : $script:RaizRepositorio"
Write-Host " Configuracion  : $Configuracion"
Write-Host " Fuentes        : $($script:FuentesMotor.Count) archivos en src\motor"
Write-Host " Salida         : $script:CarpetaBin (excluida en .gitignore)"

$global:LASTEXITCODE = 0

# 1. Verificacion de las fuentes del motor.
Escribir-Paso 'Verificando las fuentes del motor C'
$fuentesFaltantes = @($script:FuentesMotor | Where-Object { -not (Test-Path -LiteralPath $_) })
if ($fuentesFaltantes.Count -gt 0) {
    foreach ($faltante in $fuentesFaltantes) {
        Escribir-Fallo "no se encontro la fuente $faltante"
    }
    Write-Host ''
    Write-Host 'BUILD ABORTADO: faltan fuentes del motor.' -ForegroundColor Red
    exit 1
}
foreach ($fuente in $script:FuentesMotor) {
    Escribir-Ok "fuente encontrada: $fuente"
}

# 2. Localizacion de gcc.
Escribir-Paso 'Localizando el compilador gcc'
$script:Gcc = Buscar-Gcc
if ($null -eq $script:Gcc) {
    Escribir-Fallo 'no se encontro gcc en el PATH ni en las rutas habituales de MinGW'
    Write-Host '   Instale MinGW-w64 y agregue su carpeta bin al PATH (ver README.md).' -ForegroundColor Yellow
    exit 1
}
Escribir-Ok "gcc: $script:Gcc"
$versionGcc = (& $script:Gcc --version 2>&1 | Select-Object -First 1)
Escribir-Ok "version: $versionGcc"

# 3. Limpieza opcional.
if ($Limpiar) {
    Escribir-Paso 'Limpiando los artefactos anteriores'
    if (Test-Path -LiteralPath $script:CarpetaBin) {
        Remove-Item -LiteralPath $script:CarpetaBin -Recurse -Force
        Escribir-Ok "carpeta eliminada: $script:CarpetaBin"
    }
    else {
        Escribir-Aviso 'no habia artefactos previos por eliminar'
    }
}
if (-not (Test-Path -LiteralPath $script:CarpetaBin)) {
    New-Item -ItemType Directory -Path $script:CarpetaBin -Force | Out-Null
}

# 4. Compilacion del motor y de las suites de prueba.
Escribir-Paso 'Compilando el motor de scoring (scoring.c, json_io.c, cronometro.c)'
$compilado = Invoke-Compilacion -Nombre 'midff-scoring.exe' -Fuentes $script:FuentesMotor `
                                -Destino $script:EjecutableMotor
if (-not $compilado) {
    Write-Host ''
    Write-Host 'BUILD ABORTADO: el motor no compilo.' -ForegroundColor Red
    exit 1
}

Escribir-Paso 'Compilando las suites de prueba (bandera MIDFF_SIN_MAIN)'
$fuentesPruebasUnitarias = @((Join-Path $script:CarpetaPruebas 'test_scoring.c')) + $script:FuentesMotor
$fuentesPruebasLatencia  = @((Join-Path $script:CarpetaPruebas 'test_latencia.c')) + $script:FuentesMotor
Invoke-Compilacion -Nombre 'midff-test-scoring.exe' -Fuentes $fuentesPruebasUnitarias `
                   -Destino $script:EjecutablePruebas -ComoPrueba | Out-Null
Invoke-Compilacion -Nombre 'midff-test-latencia.exe' -Fuentes $fuentesPruebasLatencia `
                   -Destino $script:EjecutableLatencia -ComoPrueba | Out-Null

# 5. Pruebas unitarias.
if (-not $SinPruebas) {
    Escribir-Paso 'Ejecutando las pruebas unitarias del motor (3 variables y umbrales)'
    if (Test-Path -LiteralPath $script:EjecutablePruebas) {
        Invoke-Binario -Nombre 'pruebas unitarias' -Ruta $script:EjecutablePruebas | Out-Null
    }
    else {
        Escribir-Fallo 'no se genero midff-test-scoring.exe'
    }

    # 6. Benchmark de latencia en microsegundos.
    Escribir-Paso "Ejecutando el benchmark de latencia ($IteracionesLatencia iteraciones)"
    if (Test-Path -LiteralPath $script:EjecutableLatencia) {
        Invoke-Binario -Nombre 'benchmark de latencia' -Ruta $script:EjecutableLatencia `
                       -Argumentos @([string]$IteracionesLatencia) | Out-Null
    }
    else {
        Escribir-Fallo 'no se genero midff-test-latencia.exe'
    }

    # 7. Contrato JSON por linea de comandos (RF-03).
    Escribir-Paso 'Validando el contrato JSON por stdin/stdout del ejecutable'
    $jsonBandaAlta = '{"id_transaccion":"TRX-2026-000123","id_cliente":"CLI-001","monto":15499.00,' +
                     '"moneda":"MXN","origen":"ERP","ubicacion":"CDMX","ip_origen":"203.0.113.45",' +
                     '"fecha_hora":"2026-09-21T10:15:00-06:00","contexto":{"monto_promedio_historial":1200.00,' +
                     '"transacciones_ultima_hora":7,"ubicacion_habitual":"GDL","ip_habitual":"189.203.4.7"}}'
    $jsonBandaMedia = '{"id_cliente":"CLI-002","monto":3600.00,"origen":"BANCA","ubicacion":"CDMX",' +
                      '"ip_origen":"203.0.113.45","contexto":{"monto_promedio_historial":1200.00,' +
                      '"transacciones_ultima_hora":6,"ubicacion_habitual":"CDMX","ip_habitual":"189.203.4.7"}}'
    $jsonBandaBaja = '{"id_cliente":"CLI-003","monto":1100.00,"origen":"ERP","ubicacion":"CDMX",' +
                     '"ip_origen":"189.203.4.7","contexto":{"monto_promedio_historial":1200.00,' +
                     '"transacciones_ultima_hora":2,"ubicacion_habitual":"CDMX","ip_habitual":"189.203.4.7"}}'
    $jsonSinContexto = '{"id_cliente":"CLI-004","monto":5000.00,"ubicacion":"MTY","ip_origen":"10.0.0.5"}'

    Invoke-PruebaCli -Nombre 'banda ALTA  (score > 0.7)' -Json $jsonBandaAlta `
                     -TextoEsperado '"decision":"BLOQUEADA"' -ScoreEsperado 0.95 | Out-Null
    Invoke-PruebaCli -Nombre 'banda MEDIA (0.4 - 0.7)' -Json $jsonBandaMedia `
                     -TextoEsperado '"decision":"EN_REVISION"' -ScoreEsperado 0.55 | Out-Null
    Invoke-PruebaCli -Nombre 'banda BAJA  (score < 0.4)' -Json $jsonBandaBaja `
                     -TextoEsperado '"decision":"APROBADA"' -ScoreEsperado 0.0 | Out-Null
    Invoke-PruebaCli -Nombre 'sin contexto (fail-safe)' -Json $jsonSinContexto `
                     -TextoEsperado '"datos_faltantes":["contexto"]' -ScoreEsperado 0.5 | Out-Null
    Invoke-PruebaCli -Nombre 'JSON mal formado (HTTP 400 en la API)' -Json '{' `
                     -CodigoEsperado 2 -TextoErrorEsperado '"error"' | Out-Null
    Invoke-PruebaCli -Nombre 'sin campo obligatorio monto' -Json '{"id_cliente":"CLI-005"}' `
                     -CodigoEsperado 2 -TextoErrorEsperado 'monto' | Out-Null
}
else {
    Escribir-Aviso 'pruebas omitidas por el parametro -SinPruebas'
}

# 8. Resumen final.
Escribir-Encabezado 'Resumen del build (Sprint 1)'
Write-Host " Configuracion           : $Configuracion"
Write-Host " Compilador              : $script:Gcc"
Write-Host " Compilaciones correctas : $script:Compilaciones de 3"
Write-Host " Pruebas de contrato CLI : $($script:ResultadosCli.Count) casos"
foreach ($resultado in $script:ResultadosCli) {
    Write-Host "   - $resultado"
}

if ($script:Fallos.Count -gt 0) {
    Write-Host ''
    Write-Host (" FALLOS ({0}):" -f $script:Fallos.Count) -ForegroundColor Red
    foreach ($fallo in $script:Fallos) {
        Write-Host "   - $fallo" -ForegroundColor Red
    }
    Write-Host ''
    Write-Host ' RESULTADO: BUILD CON FALLOS' -ForegroundColor Red
    exit 1
}

Write-Host ''
Write-Host ' RESULTADO: BUILD CORRECTO (motor compilado y pruebas aprobadas)' -ForegroundColor Green
Write-Host " Ejecutable del motor: $script:EjecutableMotor" -ForegroundColor Green
exit 0

