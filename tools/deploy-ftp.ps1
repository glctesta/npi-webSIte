<#
.SYNOPSIS
  Carica il sito gtmc-npi.eu via FTP/FTPS applicando le esclusioni di DEPLOY_FTP.md.
.EXAMPLE
  # Dry-run: mostra cosa verrebbe caricato, senza connettersi
  powershell -NoProfile -ExecutionPolicy Bypass -File tools\deploy-ftp.ps1 -FtpHost ftp.gtmc-npi.eu -FtpUser r145004 -DryRun
.EXAMPLE
  # Caricamento reale (FTPS esplicito, password in modo sicuro)
  powershell -NoProfile -ExecutionPolicy Bypass -File tools\deploy-ftp.ps1 -FtpHost ftp.gtmc-npi.eu -FtpUser r145004 -PromptPass -UseSsl
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]  [string]$FtpHost,
    [Parameter(Mandatory = $true)]  [string]$FtpUser,
    [string]$FtpPass,
    [switch]$PromptPass,
    [string]$RemoteDir = 'public_html',
    [string]$LocalRoot = '.',
    [string]$Filter = '',
    [switch]$UseSsl,
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'

# Esclusioni: identiche a DEPLOY_FTP.md par. 0 (regex relative alla root del repo)
$excludes = @(
    '^\.git(/|$)',
    '^(tests|tools|sql|keys)(/|$)',
    '\.md$',
    '^\.gitignore$',
    '\.gitkeep$',
    '^\.env(\.|$)',
    '\.pem$',
    '\.key$',
    '\.bak$',
    '\.log$'
)

if ($PromptPass -and -not $FtpPass) {
    $sec = Read-Host -Prompt 'FTP password' -AsSecureString
    $b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec)
    $FtpPass = [Runtime.InteropServices.Marshal]::PtrToStringAuto($b)
}
if (-not $FtpPass) { $FtpPass = $env:FTP_PASS }
if (-not $FtpPass -and -not $DryRun) { throw 'Password FTP mancante: usa -FtpPass, -PromptPass oppure la variabile d ambiente FTP_PASS.' }

if ($UseSsl) {
    Add-Type -TypeDefinition @"
namespace Gtmc { public class TrustAll {
    public static void Enable() {
        System.Net.ServicePointManager.ServerCertificateValidationCallback =
            delegate { return true; };
    }
} }
"@ -ErrorAction SilentlyContinue
    [Gtmc.TrustAll]::Enable()
}

$localRoot = (Resolve-Path $LocalRoot).Path.TrimEnd('\')
$base = 'ftp://' + $FtpHost + '/' + $RemoteDir.Trim('/') + '/'

function New-FtpRequest([string]$uri, [string]$method) {
    $req = [Net.FtpWebRequest]::Create($uri)
    $req.Credentials = New-Object Net.NetworkCredential($FtpUser, $FtpPass)
    $req.Method = $method
    $req.UseBinary = $true
    $req.UsePassive = $true
    $req.KeepAlive = $false
    if ($UseSsl) { $req.EnableSsl = $true }
    return $req
}

function Ensure-RemoteDir([string]$rel) {
    $parts = ($rel.Replace('\', '/')).Split('/') | Where-Object { $_ }
    $cur = $base
    foreach ($p in $parts) {
        $cur += $p + '/'
        try {
            $req = New-FtpRequest $cur ([Net.WebRequestMethods+Ftp]::MakeDirectory)
            [void]$req.GetResponse()
        } catch [Net.WebException] {
            # 550 = gia esistente: va bene
            if ($_.Exception.Response -and [int]$_.Exception.Response.StatusCode -ne 550) { throw }
        }
    }
}

# Elenco file locali da caricare
$all = Get-ChildItem -LiteralPath $localRoot -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Substring($localRoot.Length + 1).Replace('\', '/')
    [pscustomobject]@{ Rel = $rel; Full = $_.FullName; Size = $_.Length }
}
$targets = $all | Where-Object {
    $r = $_.Rel
    @($excludes | Where-Object { $r -match $_ }).Count -eq 0
}
if ($Filter) {
    $f = $Filter.Replace('\', '/')
    $targets = $targets | Where-Object { $_.Rel -eq $f }
}

$dirs = $targets | ForEach-Object { $_.Rel -replace '/[^/]*$', '' } | Sort-Object -Unique
Write-Host ("File da caricare: {0}  (esclusi: {1})" -f @($targets).Count, (@($all).Count - @($targets).Count)) -ForegroundColor Cyan
foreach ($t in $targets) { Write-Host ("  {0}  [{1} KB]" -f $t.Rel, [math]::Round($t.Size/1KB,1)) }

if ($DryRun) { Write-Host 'DryRun: nessun caricamento eseguito.' -ForegroundColor Yellow; exit 0 }

$uploaded = 0
$failed = 0
foreach ($d in ($dirs | Where-Object { $_ })) { Ensure-RemoteDir $d }
foreach ($t in $targets) {
    $uri = $base + $t.Rel
    try {
        $req = New-FtpRequest $uri ([Net.WebRequestMethods+Ftp]::UploadFile)
        $req.ContentLength = $t.Size
        $bytes = [IO.File]::ReadAllBytes($t.Full)
        $stream = $req.GetRequestStream()
        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Close()
        [void]$req.GetResponse()
        $uploaded++
        Write-Host ("  OK  {0}" -f $t.Rel) -ForegroundColor Green
    } catch {
        $failed++
        Write-Host ("  FAIL {0}: {1}" -f $t.Rel, $_.Exception.Message) -ForegroundColor Red
    }
}
Write-Host ("Completato: {0}/{1} file caricati su {2}" -f $uploaded, @($targets).Count, $base) -ForegroundColor Cyan
if ($failed) { exit 1 }
