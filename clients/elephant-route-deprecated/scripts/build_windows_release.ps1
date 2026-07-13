param(
  [Parameter(Mandatory = $true)][ValidatePattern('^\d+\.\d+\.\d+$')][string]$Version,
  [Parameter(Mandatory = $true)][ValidateRange(1, 65535)][int]$BuildNumber
)

$ErrorActionPreference = 'Stop'
$ClientRoot = Split-Path -Parent $PSScriptRoot
$InstallerDir = Join-Path $ClientRoot 'windows\installer'
$ReleaseDir = Join-Path $ClientRoot 'build\windows\x64\runner\Release'
$OutputDir = Join-Path $InstallerDir 'output'

function Resolve-Tool([string]$Name, [string[]]$Candidates) {
  $command = Get-Command $Name -ErrorAction SilentlyContinue
  if ($command) { return $command.Source }
  foreach ($candidate in $Candidates) {
    $expanded = [Environment]::ExpandEnvironmentVariables($candidate)
    if (Test-Path $expanded) { return $expanded }
  }
  throw "Required tool not found: $Name"
}

function Assert-NativeSuccess([string]$Step) {
  if ($LASTEXITCODE -ne 0) {
    throw "$Step failed with exit code $LASTEXITCODE"
  }
}

$Iscc = Resolve-Tool 'ISCC.exe' @(
  '%LOCALAPPDATA%\Programs\Inno Setup 6\ISCC.exe',
  '%ProgramFiles(x86)%\Inno Setup 6\ISCC.exe',
  '%ProgramFiles%\Inno Setup 6\ISCC.exe'
)

Push-Location $ClientRoot
try {
  flutter pub get
  Assert-NativeSuccess 'flutter pub get'
  flutter analyze
  Assert-NativeSuccess 'flutter analyze'
  flutter test --no-pub
  Assert-NativeSuccess 'flutter test'
  flutter build windows --release --build-name $Version --build-number $BuildNumber
  Assert-NativeSuccess 'flutter build windows'
} finally {
  Pop-Location
}

if (!(Test-Path $ReleaseDir)) { throw "Windows release bundle is missing: $ReleaseDir" }

$WebViewBootstrapper = Join-Path $InstallerDir 'MicrosoftEdgeWebview2Setup.exe'
if (!(Test-Path $WebViewBootstrapper)) {
  Invoke-WebRequest `
    -Uri 'https://go.microsoft.com/fwlink/p/?LinkId=2124703' `
    -OutFile $WebViewBootstrapper
}
$WebViewSignature = Get-AuthenticodeSignature $WebViewBootstrapper
if ($WebViewSignature.Status -ne 'Valid' -or
    $WebViewSignature.SignerCertificate.Subject -notmatch 'Microsoft') {
  throw 'The downloaded WebView2 bootstrapper does not have a valid Microsoft signature.'
}

& $Iscc "/DAppVersion=$Version" "/DAppBuild=$BuildNumber" `
  "/DSourceDir=$ReleaseDir" (Join-Path $InstallerDir 'ElephantNetwork.iss')
if ($LASTEXITCODE -ne 0) { throw 'Inno Setup compilation failed.' }

$Installer = Join-Path $OutputDir "ElephantNetwork-Setup-x64-v$Version.exe"
$Hash = Get-FileHash $Installer -Algorithm SHA256
$Hash | Format-List
Write-Host "Artifact: $Installer"
Write-Host 'Code signing: disabled'
