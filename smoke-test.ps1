<#
.SYNOPSIS
    Smoke test del Sprint 2: valida el flujo API REST -> motor C -> Oracle XE.

.DESCRIPTION
    Recorre el flujo completo del MVP por HTTP (RF-01, RF-06):
      1. Levanta el servidor embebido de PHP (php -S) con router.php.
      2. Ejecuta 3 pruebas con datos sinteticos del dataset congelado
         (datos/datasets/transacciones_prueba.json), una por banda de riesgo:
           BAJO  (< 0.4)  -> APROBADA   (sin alerta)
           MEDIO (0.4-0.7)-> EN_REVISION (alerta PREVENTIVA)
           ALTO  (> 0.7)  -> BLOQUEADA  (alerta FRAUDE)
      3. Verifica el contrato JSON de la respuesta (score, clasificacion,
         decision, alerta_id) contra el score_esperado del dataset.
      4. Mide la latencia total de cada peticion y de las 3 juntas (objetivo
         <= 1000 ms por transaccion, RNF-01 / HU-MVP-01.2).
      5. Pruebas negativas: X-API-Key ausente -> 401 y JSON invalido -> 400
          (criterio de aceptacion del Sprint 2).
     6. Pruebas de autorizacion (BIT-037, Corte 2): la X-API-Key de ingesta
          ya no autoriza /estado, /bloquear ni GET /dispositivos (401), y sin
          variables de entorno la API responde 500 (midff_env_obligatoria).
     7. Verifica en Oracle XE la cobertura de auditoria: ninguna evaluacion
          queda sin registro en AUDITORIA (HU-MVP-04 / RNF-05).

    Requisitos cubiertos: RF-01, RF-02, RF-03, RF-06, RF-07, RF-08, RNF-01,
    RNF-05 y el criterio 8 del MVP (automatizacion minima).

.PARAMETER UrlApi
    URL base de la API (por omision http://127.0.0.1:8091).

.PARAMETER ApiKey
    Valor del encabezado X-API-Key (por omision la variable de entorno
    MIDFF_API_KEY del .env; ya NO existe valor por defecto en el script).

.PARAMETER RutaPhp
    Ruta de php.exe; se autodetecta (PATH o Laragon) si se omite.

.PARAMETER SinServidor
    No levanta el servidor (usa una API ya corriendo en -UrlApi).

.PARAMETER SinOracle
    Omite la verificacion de auditoria en Oracle XE.

.EXAMPLE
    .\smoke-test.ps1
    .\smoke-test.ps1 -SinServidor -UrlApi http://127.0.0.1:9000

.NOTES
    Proyecto: MIDFF (Sprint 2). El motor C debe estar compilado (build.ps1).
    Requiere PHP 8+ con pdo_oci u oci8, y sqlplus solo para la verificacion
    final de auditoria (se omite con -SinOracle si no esta disponible).
#>

[CmdletBinding()]
param(
    [string] $UrlApi = 'http://127.0.0.1:8091',

    [string] $ApiKey = '',

    [string] $RutaPhp = '',

    [switch] $SinServidor,

    [switch] $SinOracle,

    [string] $DbUsuario = '',

    [string] $DbClave = '',

    [string] $DbCadena = 'localhost:1521/XEPDB1'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$OutputEncoding = New-Object System.Text.UTF8Encoding($false)
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch { Write-Verbose 'Salida de consola no modificada.' }

# -----------------------------------------------------------------------------
# Rutas del repositorio y artefactos
# -----------------------------------------------------------------------------
$script:RaizRepositorio = if ([string]::IsNullOrEmpty($PSScriptRoot)) { (Get-Location).Path } else { $PSScriptRoot }
$script:RutaDataset     = Join-Path $script:RaizRepositorio 'datos\datasets\transacciones_prueba.json'
$script:RutaMotorExe    = Join-Path $script:RaizRepositorio 'src\motor\bin\midff-scoring.exe'
$script:RutaRouter      = Join-Path $script:RaizRepositorio 'router.php'

# Carga del .env local (no versionado) cuando la variable aun no esta definida
# en el entorno (mismo criterio que servidor.ps1; BIT-036/037: los secretos
# viven en .env, jamas en el codigo ni con valores por defecto).
$envLocal = Join-Path $script:RaizRepositorio '.env'
if (Test-Path $envLocal) {
    Get-Content $envLocal | ForEach-Object {
        $linea = $_.Trim()
        if ($linea -and -not $linea.StartsWith('#')) {
            $pos = $linea.IndexOf('=')
            if ($pos -gt 0) {
                $k = $linea.Substring(0, $pos).Trim()
                $v = $linea.Substring($pos + 1).Trim()
                if ($k -and $v -and -not (Get-Item "Env:$k" -ErrorAction SilentlyContinue)) {
                    Set-Item "Env:$k" $v
                }
            }
        }
    }
    Write-Verbose '.env cargado (secretos locales inyectados al proceso).'
}

if ([string]::IsNullOrEmpty($ApiKey))  { $ApiKey  = $env:MIDFF_API_KEY }
if ([string]::IsNullOrEmpty($ApiKey))  { throw 'Falta MIDFF_API_KEY: copie .env.example a .env, exporte la variable o use -ApiKey (el valor ya NO vive en el codigo)' }
if ([string]::IsNullOrEmpty($DbUsuario)) { $DbUsuario = if ($env:MIDFF_DB_USUARIO) { $env:MIDFF_DB_USUARIO } else { 'MIDFFuser' } }
if ([string]::IsNullOrEmpty($DbClave))   { $DbClave   = $env:MIDFF_DB_CLAVE }
if ([string]::IsNullOrEmpty($DbClave))   {
    Write-Warning 'Falta MIDFF_DB_CLAVE (definida en .env; BIT-037: sin contrasena por defecto en el script): se omite la verificacion de auditoria en Oracle.'
    $SinOracle = $true
}

$script:Fallos         = New-Object System.Collections.Generic.List[string]
$script:Resultados     = New-Object System.Collections.Generic.List[object]
$script:ServidorPhp    = $null

# -----------------------------------------------------------------------------
# Presentacion en consola
# -----------------------------------------------------------------------------
function Escribir-Encabezado {
    param([string] $Texto)
    Write-Host ''
    Write-Host ('=' * 78) -ForegroundColor DarkCyan
    Write-Host " $Texto" -ForegroundColor Cyan
    Write-Host ('=' * 78) -ForegroundColor DarkCyan
}

function Escribir-Paso {
    param([string] $Texto)
    Write-Host ''
    Write-Host ("==> $Texto") -ForegroundColor White
}

function Escribir-Ok {
    param([string] $Texto)
    Write-Host ("  [OK]    $Texto") -ForegroundColor Green
}

function Escribir-Fallo {
    param([string] $Texto)
    Write-Host ("  [FALLO] $Texto") -ForegroundColor Red
    $script:Fallos.Add($Texto)
}

function Escribir-Aviso {
    param([string] $Texto)
    Write-Host ("  [AVISO] $Texto") -ForegroundColor Yellow
}
# -----------------------------------------------------------------------------
# 1. Deteccion de dependencias (php.exe, sqlplus.exe y DLLs del cliente Oracle)
# -----------------------------------------------------------------------------
Escribir-Paso 'Detectando dependencias del entorno'

if ([string]::IsNullOrEmpty($RutaPhp)) {
    $cmdPhp = Get-Command php -ErrorAction SilentlyContinue
    if ($cmdPhp) {
        $RutaPhp = $cmdPhp.Source
    } elseif (Test-Path 'C:\laragon\bin\php') {
        $candidatos = Get-ChildItem 'C:\laragon\bin\php' -Recurse -Depth 2 -Filter 'php.exe' -ErrorAction SilentlyContinue
        if ($candidatos) { $RutaPhp = ($candidatos | Sort-Object FullName -Descending | Select-Object -First 1).FullName }
    }
}
if ([string]::IsNullOrEmpty($RutaPhp) -or -not (Test-Path $RutaPhp)) {
    Write-Host '  No se encontro php.exe (indiquelo con -RutaPhp). El smoke test no puede correr.' -ForegroundColor Red
    exit 1
}
Escribir-Ok "php.exe: $RutaPhp"

# El driver pdo_oci/oci8 necesita las DLLs del cliente Oracle en el PATH.
$cmdSqlplus = Get-Command sqlplus -ErrorAction SilentlyContinue
$rutaSqlplus = if ($cmdSqlplus) { $cmdSqlplus.Source } else { $null }
if (-not $rutaSqlplus) {
    $candidatosSp = Get-ChildItem 'C:\app' -Recurse -Depth 6 -Filter 'sqlplus.exe' -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($candidatosSp) { $rutaSqlplus = $candidatosSp.FullName }
}
if ($rutaSqlplus) {
    $binOracle = Split-Path $rutaSqlplus -Parent
    if ($env:PATH -notlike "*$binOracle*") { $env:PATH = "$binOracle;$env:PATH" }
    Escribir-Ok "sqlplus.exe: $rutaSqlplus"
} else {
    Escribir-Aviso 'sqlplus.exe no encontrado: se omite la verificacion de auditoria en Oracle.'
    $SinOracle = $true
}

if (-not (Test-Path $script:RutaMotorExe)) {
    Escribir-Fallo "no existe $script:RutaMotorExe (ejecute .\build.ps1 antes del smoke test)"
}
else { Escribir-Ok "motor C: $script:RutaMotorExe" }

if (-not (Test-Path $script:RutaDataset)) {
    Escribir-Fallo "no existe el dataset $script:RutaDataset"
} else { Escribir-Ok "dataset: $script:RutaDataset" }

if (-not (Test-Path $script:RutaRouter)) {
    if (-not $SinServidor) { Escribir-Fallo "no existe $script:RutaRouter (necesario para -S)" }
}

$pruebaDriver = & $RutaPhp -r "echo extension_loaded('pdo_oci') ? 'pdo_oci' : (extension_loaded('oci8') ? 'oci8' : 'ninguno');" 2>$null
if ($pruebaDriver -eq 'ninguno') {
    Escribir-Fallo 'PHP no tiene cargado pdo_oci ni oci8 (habilite la extension en php.ini).'
} else { Escribir-Ok "driver Oracle de PHP: $pruebaDriver" }

if ($script:Fallos.Count -gt 0) {
    Escribir-Encabezado 'Resultado del smoke test (Sprint 2)'
    $script:Fallos | ForEach-Object { Write-Host "  - $_" -ForegroundColor Red }
    Write-Host ' RESULTADO: SMOKE TEST CON FALLOS DE ENTORNO' -ForegroundColor Red
    exit 1
}

# -----------------------------------------------------------------------------
# 2. Carga del dataset congelado (3 casos: uno por banda de riesgo)
# -----------------------------------------------------------------------------
# Nota: en PowerShell 5.1 ConvertFrom-Json no enumera los arrays JSON por la
# tuberia, por eso se normaliza el resultado a un array de transacciones.
$parseadoJson = ConvertFrom-Json (Get-Content $script:RutaDataset -Raw -Encoding UTF8)
$casos = if ($parseadoJson -is [array]) { $parseadoJson } else { @($parseadoJson) }
Escribir-Ok ("dataset cargado: {0} transacciones sinteticas" -f @($casos).Count)

# -----------------------------------------------------------------------------
# 3. Servidor embebido de PHP (php -S ... router.php)
# -----------------------------------------------------------------------------
$UrlEndpoint = $UrlApi.TrimEnd('/') + '/api/v1/transacciones'
if (-not $SinServidor) {
    Escribir-Paso "Iniciando servidor embebido de PHP en $UrlApi"
    $script:ServidorPhp = Start-Process -FilePath $RutaPhp `
        -ArgumentList @('-S', ([uri] $UrlApi).Authority, 'router.php') `
        -WorkingDirectory $script:RaizRepositorio -PassThru -WindowStyle Hidden
    $listo = $false
    for ($i = 0; $i -lt 30 -and -not $listo; $i++) {
        Start-Sleep -Milliseconds 400
        try {
            # GET al endpoint debe responder 405 (solo POST): servidor listo.
            Invoke-WebRequest -UseBasicParsing -Uri $UrlEndpoint -Method Get -TimeoutSec 2 | Out-Null
            $listo = $true
        } catch {
            if ($_.Exception.Response) { $listo = $true } else { Start-Sleep -Milliseconds 200 }
        }
    }
    if ($listo) { Escribir-Ok 'servidor PHP listo (GET /api/v1/transacciones -> 405 esperado)' }
    else {
        Escribir-Fallo 'el servidor embebido de PHP no respondio en 12 s'
        Write-Host ' RESULTADO: SMOKE TEST CON FALLOS' -ForegroundColor Red
        exit 1
    }
}

# -----------------------------------------------------------------------------
# 4. Cliente HTTP de prueba (POST JSON; captura cuerpo aunque sea 4xx/5xx)
# -----------------------------------------------------------------------------
function Invoke-PostJson {
    param([string] $Uri, [hashtable] $Encabezados, [string] $Cuerpo)
    $crono = [System.Diagnostics.Stopwatch]::StartNew()
    try {
        $r = Invoke-WebRequest -UseBasicParsing -Uri $Uri -Method Post `
             -Headers $Encabezados -ContentType 'application/json; charset=utf-8' `
             -Body ([System.Text.Encoding]::UTF8.GetBytes($Cuerpo)) -TimeoutSec 15
        $crono.Stop()
        return @{ Codigo = [int] $r.StatusCode; Cuerpo = [string] $r.Content; Ms = $crono.Elapsed.TotalMilliseconds }
    } catch {
        $crono.Stop()
        $codigo = 0; $cuerpo = ''
        if ($_.Exception.Response) {
            $codigo = [int] $_.Exception.Response.StatusCode
            try {
                $lector = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $cuerpo = $lector.ReadToEnd()
            } catch { $cuerpo = '' }
        }
        return @{ Codigo = $codigo; Cuerpo = $cuerpo; Ms = $crono.Elapsed.TotalMilliseconds }
    }
}

function Get-Banda {
    param([double] $Score)
    if ($Score -lt 0.4)  { 'BAJO' }  elseif ($Score -le 0.7) { 'MEDIO' }  else { 'ALTO' }
}
# -----------------------------------------------------------------------------
# 5. PRUEBAS POR BANDA DE RIESGO (3 casos sinteticos del dataset congelado)
# -----------------------------------------------------------------------------
Escribir-Encabezado 'Pruebas por banda de riesgo (POST /api/v1/transacciones)'
$latenciaTotalMs = 0.0

foreach ($caso in $casos) {
    $scoreEsperado  = [double] $caso.score_esperado
    $banda          = Get-Banda $scoreEsperado
    $decisionEsp    = @{ BAJO = 'APROBADA'; MEDIO = 'EN_REVISION'; ALTO = 'BLOQUEADA' }[$banda]

    # Cuerpo de la peticion: contrato del MVP sin claves de apoyo del dataset.
    $peticion = [ordered] @{}
    foreach ($prop in $caso.PSObject.Properties) {
        if ($prop.Name -notin @('id_transaccion', 'timestamp', 'score_esperado')) {
            $peticion[$prop.Name] = $prop.Value
        }
    }
    $jsonPeticion = ConvertTo-Json -InputObject $peticion -Depth 8

    $r = Invoke-PostJson -Uri $UrlEndpoint -Encabezados @{ 'X-API-Key' = $ApiKey } -Cuerpo $jsonPeticion
    $latenciaTotalMs += $r.Ms

    $detalle = "[{0}] cliente={1} score_esperado={2:N4}" -f $banda, $caso.id_cliente, $scoreEsperado
    Write-Host ''
    Write-Host ("  --- $detalle ---") -ForegroundColor White

    if ($r.Codigo -ne 200) {
        Escribir-Fallo ("{0}: HTTP {1} (se esperaba 200). Cuerpo: {2}" -f $banda, $r.Codigo, $r.Cuerpo)
        continue
    }
    $resp = $r.Cuerpo | ConvertFrom-Json

    # a) Score dentro de la banda y consistente con el dataset de referencia.
    $diferencia = [math]::Abs([double] $resp.score - $scoreEsperado)
    if ($diferencia -le 0.0001) { Escribir-Ok ("score={0:N4} coincide con score_esperado={1:N4}" -f [double] $resp.score, $scoreEsperado) }
    else { Escribir-Fallo ("score={0:N4} difiere del esperado {1:N4} (delta {2:N4})" -f [double] $resp.score, $scoreEsperado, $diferencia) }

    if ((Get-Banda ([double] $resp.score)) -eq $banda) { Escribir-Ok ("banda={0} dentro del rango esperado" -f $banda) }
    else { Escribir-Fallo ("score={0:N4} cayo fuera de la banda {1}" -f [double] $resp.score, $banda) }

    # b) Clasificacion y decision por umbral (HU-MVP-03).
    if ($resp.clasificacion -eq $banda) { Escribir-Ok ("clasificacion={0}" -f $resp.clasificacion) }
    else { Escribir-Fallo ("clasificacion={0} (se esperaba {1})" -f $resp.clasificacion, $banda) }

    if ($resp.decision -eq $decisionEsp) { Escribir-Ok ("decision={0}" -f $resp.decision) }
    else { Escribir-Fallo ("decision={0} (se esperaba {1})" -f $resp.decision, $decisionEsp) }

    # c) alerta_id: presente solo en banda MEDIO/ALTO (RF-09 / HU-MVP-03).
    if ($banda -eq 'BAJO') {
        if ($null -eq $resp.alerta_id -or '' -eq "$($resp.alerta_id)") { Escribir-Ok 'alerta_id ausente (banda BAJA: sin alerta ni push)' }
        else { Escribir-Fallo ("banda BAJA no debe generar alerta; respuesta trae alerta_id={0}" -f $resp.alerta_id) }
    } else {
        if ($resp.alerta_id -and "$($resp.alerta_id)".StartsWith('ALR-')) { Escribir-Ok ("alerta_id={0} (alerta creada en Oracle)" -f $resp.alerta_id) }
        else { Escribir-Fallo "banda $banda debio generar alerta y alerta_id esta vacio" }
    }

    # d) Contrato: id_transaccion asignado por la API.
    if ("$($resp.id_transaccion)" -match '^TRX-\d{4}-[0-9A-F]{8}$') { Escribir-Ok ("id_transaccion={0}" -f $resp.id_transaccion) }
    else { Escribir-Fallo ("id_transaccion={0} no sigue el formato TRX-<anio>-<hex>" -f $resp.id_transaccion) }

    # e) Latencia de la peticion (objetivo <= 1000 ms, HU-MVP-01.2).
    if ($r.Ms -le 1000.0) { Escribir-Ok ("latencia HTTP: {0:N1} ms (objetivo <= 1000 ms)" -f $r.Ms) }
    else { Escribir-Fallo ("latencia HTTP: {0:N1} ms excede el objetivo de 1000 ms" -f $r.Ms) }

    $script:Resultados.Add([pscustomobject] @{
        Banda = $banda; Cliente = $caso.id_cliente; ScoreEsperado = $scoreEsperado
        Score = [double] $resp.score; Decision = $resp.decision
        Alerta = $resp.alerta_id; LatenciaMs = [math]::Round($r.Ms, 1) })
}

# -----------------------------------------------------------------------------
# 6. PRUEBAS NEGATIVAS (criterio de aceptacion del Sprint 2)
# -----------------------------------------------------------------------------
Escribir-Encabezado 'Pruebas negativas del endpoint'

$r401 = Invoke-PostJson -Uri $UrlEndpoint -Encabezados @{} -Cuerpo '{"id_cliente":"CLI-NEG","monto":100.00}'
if ($r401.Codigo -eq 401) { Escribir-Ok 'sin X-API-Key -> HTTP 401 (API Key invalida se rechaza)' }
else { Escribir-Fallo ("sin X-API-Key -> HTTP {0} (se esperaba 401). Cuerpo: {1}" -f $r401.Codigo, $r401.Cuerpo) }

$r400 = Invoke-PostJson -Uri $UrlEndpoint -Encabezados @{ 'X-API-Key' = $ApiKey } -Cuerpo '{'
if ($r400.Codigo -eq 400) { Escribir-Ok 'JSON mal formado -> HTTP 400 (error descriptivo en el cuerpo)' }
else { Escribir-Fallo ("JSON mal formado -> HTTP {0} (se esperaba 400). Cuerpo: {1}" -f $r400.Codigo, $r400.Cuerpo) }

# -----------------------------------------------------------------------------
# 6b. PRUEBAS DE AUTORIZACION (BIT-037, Corte 2)
#     - La X-API-Key (llave de INGESTA del banco) ya no autoriza los endpoints
#       de la app (estado) ni las acciones del banco (bloquear, GET dispositivos).
#     - Si falta una variable de entorno obligatoria, la API se niega a
#       atender con 500 (midff_env_obligatoria de config/http.php).
#     El caso 403 de "otro usuario pide alertas ajenas" requiere dos ID token
#     reales de Firebase y esta documentado paso a paso en docs/Reporte_QA.md
#     (seccion 5.1).
# -----------------------------------------------------------------------------
Escribir-Encabezado 'Pruebas de autorizacion (BIT-037, Corte 2)'

function Invoke-GetJson {
    param([string] $Uri, [hashtable] $Encabezados)
    try {
        $r = Invoke-WebRequest -UseBasicParsing -Uri $Uri -Method Get -Headers $Encabezados -TimeoutSec 15
        return @{ Codigo = [int] $r.StatusCode; Cuerpo = [string] $r.Content }
    } catch {
        $codigo = 0; $cuerpo = ''
        if ($_.Exception.Response) {
            $codigo = [int] $_.Exception.Response.StatusCode
            try {
                $lector = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $cuerpo = $lector.ReadToEnd()
            } catch { $cuerpo = '' }
        }
        return @{ Codigo = $codigo; Cuerpo = $cuerpo }
    }
}

# a) GET /estado con solo la X-API-Key de ingesta -> 401 (exige ID token de
#    Firebase del dueno; con el APK ya no se pueden leer alertas ajenas).
$rEstado = Invoke-GetJson -Uri ($UrlApi.TrimEnd('/') + '/api/v1/estado?id_cliente=CLI-001') -Encabezados @{ 'X-API-Key' = $ApiKey }
if ($rEstado.Codigo -eq 401) { Escribir-Ok 'GET /estado con solo X-API-Key -> HTTP 401 (la llave de ingesta ya no autoriza a la app)' }
else { Escribir-Fallo ("GET /estado con solo X-API-Key -> HTTP {0} (se esperaba 401). Cuerpo: {1}" -f $rEstado.Codigo, $rEstado.Cuerpo) }

# b) POST /bloquear con solo la X-API-Key -> 401 (exige sesion de administrador
#    de MIDFF.html; el usuario sale del token, no del cuerpo).
$rBloq = Invoke-PostJson -Uri ($UrlApi.TrimEnd('/') + '/api/v1/bloquear') -Encabezados @{ 'X-API-Key' = $ApiKey } -Cuerpo '{"alerta_id":"ALR-2026-000001"}'
if ($rBloq.Codigo -eq 401) { Escribir-Ok 'POST /bloquear con solo X-API-Key -> HTTP 401 (exige sesion de administrador)' }
else { Escribir-Fallo ("POST /bloquear con solo X-API-Key -> HTTP {0} (se esperaba 401). Cuerpo: {1}" -f $rBloq.Codigo, $rBloq.Cuerpo) }

# c) GET /dispositivos con solo la X-API-Key -> 401 (vista del banco, sesion
#    de administrador).
$rDisp = Invoke-GetJson -Uri ($UrlApi.TrimEnd('/') + '/api/v1/dispositivos') -Encabezados @{ 'X-API-Key' = $ApiKey }
if ($rDisp.Codigo -eq 401) { Escribir-Ok 'GET /dispositivos con solo X-API-Key -> HTTP 401 (exige sesion de administrador)' }
else { Escribir-Fallo ("GET /dispositivos con solo X-API-Key -> HTTP {0} (se esperaba 401). Cuerpo: {1}" -f $rDisp.Codigo, $rDisp.Cuerpo) }

# d) Sin variables de entorno -> 500 explicativo: segunda instancia del
#    servidor con las variables MIDFF_* retiradas del entorno heredado (la
#    primera instancia conserva las que recibio al arrancar).
$puerto500  = ([uri] $UrlApi).Port + 10
$url500     = "http://127.0.0.1:$puerto500"
$srv500     = $null
$varsGuardadas = @{}
foreach ($nombreVar in @('MIDFF_API_KEY','MIDFF_DB_USUARIO','MIDFF_DB_CLAVE','MIDFF_ADMIN_SECRETO','MIDFF_FIREBASE_WEB_KEY')) {
    $itemVar = Get-Item "Env:$nombreVar" -ErrorAction SilentlyContinue
    if ($itemVar) {
        $varsGuardadas[$nombreVar] = $itemVar.Value
        Remove-Item "Env:$nombreVar" -ErrorAction SilentlyContinue
    }
}
try {
    $srv500 = Start-Process -FilePath $RutaPhp -ArgumentList @('-S', "127.0.0.1:$puerto500", 'router.php') `
        -WorkingDirectory $script:RaizRepositorio -PassThru -WindowStyle Hidden
    Start-Sleep -Milliseconds 1500
    $r500 = Invoke-PostJson -Uri ($url500 + '/api/v1/transacciones') -Encabezados @{ 'X-API-Key' = 'valor-cualquiera' } -Cuerpo '{"id_cliente":"CLI-ENV","monto":100.00}'
    if ($r500.Codigo -eq 500 -and $r500.Cuerpo -match 'falta la variable') {
        Escribir-Ok ('sin variables de entorno -> HTTP 500 y la API se niega a atender ("configuracion incompleta... falta la variable ...") :: ' +
                     $r500.Cuerpo)
    } else {
        Escribir-Fallo ("sin variables de entorno -> HTTP {0} (se esperaba 500). Cuerpo: {1}" -f $r500.Codigo, $r500.Cuerpo)
    }
} finally {
    if ($srv500 -and -not $srv500.HasExited) { Stop-Process -Id $srv500.Id -Force -ErrorAction SilentlyContinue }
    foreach ($nombreVar in $varsGuardadas.Keys) { Set-Item "Env:$nombreVar" $varsGuardadas[$nombreVar] }
}
# -----------------------------------------------------------------------------
# 7. VERIFICACION DE AUDITORIA EN ORACLE XE (HU-MVP-04 / RNF-05)
# -----------------------------------------------------------------------------
if (-not $SinOracle -and $rutaSqlplus) {
    Escribir-Encabezado 'Verificacion de persistencia y auditoria en Oracle XE'
    $archivoSql = Join-Path ([System.IO.Path]::GetTempPath()) 'midff_smoke_auditoria.sql'
    $lineasSql = @(
        'SET HEADING OFF FEEDBACK OFF PAGESIZE 0 VERIFY OFF'
        'SELECT ''EVALUACIONES='' || COUNT(*) FROM EVALUACION;'
        'SELECT ''SIN_AUDITORIA='' || COUNT(*) FROM EVALUACION e WHERE NOT EXISTS'
        '  (SELECT 1 FROM AUDITORIA a WHERE a.entidad = ''EVALUACION'''
        '    AND a.detalle LIKE ''%id_transaccion='' || e.id_transaccion || ''%'');'
        'SELECT ''ALERTAS_PREVENTIVA='' || COUNT(*) FROM ALERTA WHERE tipo = ''PREVENTIVA'';'
        'SELECT ''ALERTAS_FRAUDE='' || COUNT(*) FROM ALERTA WHERE tipo = ''FRAUDE'';'
        'EXIT;'
    )
    [System.IO.File]::WriteAllLines($archivoSql, $lineasSql, (New-Object System.Text.ASCIIEncoding))

    $salidaSql = & $rutaSqlplus -S -L "$DbUsuario/$DbClave@$DbCadena" "@$archivoSql" 2>&1 | Out-String
    Remove-Item $archivoSql -ErrorAction SilentlyContinue

    $metricas = @{}
    foreach ($linea in ($salidaSql -split "`r?`n")) {
        if ($linea -match '^(EVALUACIONES|SIN_AUDITORIA|ALERTAS_PREVENTIVA|ALERTAS_FRAUDE)=(\d+)\s*$') {
            $metricas[$Matches[1]] = [int] $Matches[2]
        }
    }

    if ($metricas.Count -eq 4) {
        Escribir-Ok ("EVALUACION registradas en Oracle: {0}" -f $metricas['EVALUACIONES'])
        Escribir-Ok ("ALERTAS PREVENTIVA / FRAUDE: {0} / {1}" -f $metricas['ALERTAS_PREVENTIVA'], $metricas['ALERTAS_FRAUDE'])
        if ($metricas['SIN_AUDITORIA'] -eq 0) {
            Escribir-Ok 'cobertura de auditoria 100%: 0 evaluaciones sin registro en AUDITORIA'
        } else {
            Escribir-Fallo ("{0} evaluaciones sin registro de auditoria (RNF-05 violado)" -f $metricas['SIN_AUDITORIA'])
        }
    } else {
        Escribir-Aviso 'no fue posible leer las metricas de auditoria desde sqlplus:'
        Write-Host $salidaSql -ForegroundColor DarkGray
    }
}

# -----------------------------------------------------------------------------
# 8. Resumen final del smoke test
# -----------------------------------------------------------------------------
Escribir-Encabezado 'Resumen del smoke test (Sprint 2)'
Write-Host " Endpoint probado   : POST $UrlEndpoint"
Write-Host " API / PHP          : $RutaPhp"
if (-not $SinServidor) { Write-Host ' Servidor embebido  : php -S (detenido al terminar)' }
Write-Host " Dataset            : $script:RutaDataset"
Write-Host ''
$script:Resultados | Format-Table Banda, Cliente, ScoreEsperado, Score, Decision, Alerta, LatenciaMs -AutoSize | Out-Host
Write-Host (" Latencia total de las 3 transacciones : {0:N1} ms (promedio {1:N1} ms)" -f $latenciaTotalMs, ($latenciaTotalMs / [math]::Max(1, $script:Resultados.Count)))

if ($script:Fallos.Count -gt 0) {
    Write-Host ''
    Write-Host (" FALLOS ({0}):" -f $script:Fallos.Count) -ForegroundColor Red
    foreach ($fallo in $script:Fallos) { Write-Host "   - $fallo" -ForegroundColor Red }
}

# Detener el servidor embebido si este script lo inicio.
if ($script:ServidorPhp -and -not $script:ServidorPhp.HasExited) {
    Stop-Process -Id $script:ServidorPhp.Id -Force -ErrorAction SilentlyContinue
}

Write-Host ''
if ($script:Fallos.Count -gt 0) {
    Write-Host ' RESULTADO: SMOKE TEST CON FALLOS' -ForegroundColor Red
    exit 1
}
Write-Host ' RESULTADO: SMOKE TEST OK (bandas, contrato, latencia, autorizacion BIT-037 y auditoria verificados)' -ForegroundColor Green
exit 0
