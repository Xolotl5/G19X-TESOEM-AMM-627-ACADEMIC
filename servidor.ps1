# -----------------------------------------------------------------------------
# MIDFF - Levanta la API lista para la demo (un solo comando, sin pasos manuales)
# Archivo : servidor.ps1      Uso:  .\servidor.ps1      (desde C:\todoMIDFF)
#
# Que hace:
#   1. Agrega las DLLs del cliente Oracle al PATH (pdo_oci las necesita).
#   2. Autodetecta php.exe (PATH o Laragon).
#   3. Abre el puerto 8091 en el firewall de Windows (UNA sola vez; si no hay
#      permisos de administrador, imprime el comando para hacerlo a mano).
#   4. Verifica/realinea las secuencias de Oracle (anti ORA-00001 PK_AUDITORIA:
#      leccion BIT-021/022/026, ejecuta database\realinear_secuencias.sql).
#   5. Levanta la API en 0.0.0.0:8091 (accesible desde el telefono por WiFi,
#      por USB con adb reverse y desde el emulador via 10.0.2.2).
#
# Con esto la app Android se enlaza SOLA por WiFi (misma red que la PC;
# DescubrimientoApi escanea el gateway y la subred /24). Para la demo en la
# PC de otra persona: copiar el proyecto, abrir PowerShell aqui y ejecutar
# este script; el telefono solo necesita estar en el mismo WiFi.
# -----------------------------------------------------------------------------

param(
    [int] $Puerto = 8091
)

$ErrorActionPreference = 'Stop'

# 0) Cargar el .env local (no versionado) como variables de entorno del proceso
#    (BIT-036, Corte 1: ningun secreto vive en el codigo; ver .env.example)
$envFile = Join-Path $PSScriptRoot '.env'
if (Test-Path $envFile) {
    Get-Content $envFile | ForEach-Object {
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
    Write-Host '[0/5] .env cargado (secretos locales inyectados al proceso)'
} elseif ($env:MIDFF_API_KEY -or $env:MIDFF_DB_CLAVE) {
    Write-Host '[0/5] secretos tomados de las variables de entorno existentes'
} else {
    Write-Warning 'No existe .env ni variables de entorno: la API rechazara peticiones protegidas. Copie .env.example a .env y llene los valores.'
}

# 1) DLLs del cliente Oracle XE al PATH (mismo criterio que smoke-test.ps1)
$oracleBin = 'C:\app\100157216\product\21c\dbhomeXE\bin'
if (-not (Test-Path $oracleBin)) {
    $sqlplus = (Get-Command sqlplus.exe -ErrorAction SilentlyContinue).Source
    if ($sqlplus) { $oracleBin = Split-Path -Parent $sqlplus }
}
if ($oracleBin -and (Test-Path $oracleBin)) {
    $env:PATH = "$oracleBin;$env:PATH"
    Write-Host "[1/5] Cliente Oracle en PATH: $oracleBin"
} else {
    Write-Warning '[1/5] No se encontro el cliente Oracle (sqlplus); php_oci podria fallar.'
}

# 2) php.exe (PATH o instalacion tipica de Laragon)
$php = (Get-Command php.exe -ErrorAction SilentlyContinue).Source
if (-not $php) {
    $php = (Get-ChildItem 'C:\laragon\bin\php' -Recurse -Filter php.exe `
            -ErrorAction SilentlyContinue | Select-Object -First 1).FullName
}
if (-not $php) {
    Write-Error 'No se encontro php.exe: instale PHP 8+ o ajuste el PATH.'
    exit 1
}
Write-Host "[2/5] PHP: $php"

# 3) Regla de firewall (persistente: solo se necesita la primera vez)
$nombreRegla = "MIDFF-API-$Puerto"
if (-not (Get-NetFirewallRule -Name $nombreRegla -ErrorAction SilentlyContinue)) {
    try {
        New-NetFirewallRule -Name $nombreRegla -DisplayName "MIDFF API ($Puerto)" `
            -Direction Inbound -Protocol TCP -LocalPort $Puerto -Action Allow | Out-Null
        Write-Host "[3/5] Firewall: puerto $Puerto abierto (regla $nombreRegla)"
    } catch {
        Write-Warning "[3/5] Sin permisos de administrador para el firewall. Ejecute UNA vez en PowerShell como administrador:`n" `
            + "        New-NetFirewallRule -Name $nombreRegla -DisplayName 'MIDFF API' -Direction Inbound -Protocol TCP -LocalPort $Puerto -Action Allow`n" `
            + "       (o ejecute este script como administrador la primera vez)"
    }
} else {
    Write-Host "[3/5] Firewall: regla $nombreRegla ya existe"
}

# 4) Verificacion/realineacion de secuencias Oracle (lecciones BIT-021/022/026:
#    una limpieza de pruebas que conserva AUDITORIA -registro inmutable- deja
#    SEQ_* por debajo del MAX(id) y la proxima insercion falla con ORA-00001).
#    El script es idempotente: solo reajusta las secuencias desalineadas.
if ($oracleBin -and (Test-Path (Join-Path $oracleBin 'sqlplus.exe'))) {
    $dbUsuario  = if ($env:MIDFF_DB_USUARIO)  { $env:MIDFF_DB_USUARIO }  else { 'MIDFFuser' }
    $dbClave    = if ($env:MIDFF_DB_CLAVE)    { $env:MIDFF_DB_CLAVE }    else { 'midffadmin' }
    $dbHost     = if ($env:MIDFF_DB_HOST)     { $env:MIDFF_DB_HOST }     else { 'localhost' }
    $dbPuerto   = if ($env:MIDFF_DB_PUERTO)   { $env:MIDFF_DB_PUERTO }   else { '1521' }
    $dbServicio = if ($env:MIDFF_DB_SERVICIO) { $env:MIDFF_DB_SERVICIO } else { 'XEPDB1' }
    $scriptSecuencias = Join-Path $PSScriptRoot 'database\realinear_secuencias.sql'
    if (Test-Path $scriptSecuencias) {
        # PS 5.1: con $ErrorActionPreference='Stop', la redireccion 2>&1 de un
        # comando nativo que escriba a stderr lanza NativeCommandError; se baja
        # a 'Continue' solo durante la llamada a sqlplus.
        $eapPrevio = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        $salida = & (Join-Path $oracleBin 'sqlplus.exe') -S "$dbUsuario/$dbClave@${dbHost}:$dbPuerto/$dbServicio" "@$scriptSecuencias" 2>&1
        $ErrorActionPreference = $eapPrevio
        $salida | Where-Object { "$_" -match 'SEQ_|Ajustes|ORA-' } | ForEach-Object {
            Write-Host "       $($_.ToString().Trim())" -ForegroundColor DarkGray
        }
        if ($LASTEXITCODE -eq 0 -and -not @($salida | Where-Object { "$_" -match 'ORA-' })) {
            Write-Host "[4/5] Secuencias Oracle verificadas (database\realinear_secuencias.sql)"
        } else {
            Write-Warning '[4/5] Oracle no respondio (¿XE detenido?); la API arranca igual pero fallara al persistir.'
        }
    } else {
        Write-Warning "[4/5] Falta $scriptSecuencias; se omitio la verificacion de secuencias."
    }
} else {
    Write-Warning '[4/5] sqlplus no disponible; se omitio la verificacion (ejecute database\realinear_secuencias.sql a mano tras limpiezas).'
}

# 5) IP local de referencia + servidor en 0.0.0.0 (todas las interfaces)
#    (se excluyen adaptadores virtuales: VirtualBox, Hyper-V, WSL, VMware...)
$ipLocal = (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object {
        $_.IPAddress -match '^(192\.168|10\.|172\.(1[6-9]|2[0-9]|3[01])\.)' -and
        $_.InterfaceAlias -notmatch 'VirtualBox|vEthernet|Loopback|WSL|VMware|Bluetooth'
    } |
    Select-Object -First 1).IPAddress

Write-Host "[5/5] Levantando la API MIDFF..."
Write-Host ''
Write-Host "  Consola del banco : http://127.0.0.1:$Puerto/MIDFF.html" -ForegroundColor Cyan
Write-Host "  Simulador         : http://127.0.0.1:$Puerto/banco.html" -ForegroundColor Cyan
if ($ipLocal) {
    Write-Host "  Desde el telefono : http://${ipLocal}:$Puerto  (misma red WiFi; la app se autodetecta)" -ForegroundColor Cyan
}
Write-Host ''
Write-Host '  Deje esta ventana ABIERTA durante la demo. Ctrl+C para detener.' -ForegroundColor Yellow
Write-Host ''

Set-Location $PSScriptRoot
& $php -S "0.0.0.0:$Puerto" router.php
