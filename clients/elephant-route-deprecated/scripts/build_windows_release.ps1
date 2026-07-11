param(
  [Parameter(Mandatory = $true)][ValidatePattern('^\d+\.\d+\.\d+$')][string]$Version,
  [Parameter(Mandatory = $true)][ValidateRange(1, 65535)][int]$BuildNumber,
  [ValidateSet('internal', 'production')][string]$SigningMode = 'internal'
)

$ErrorActionPreference = 'Stop'
$ClientRoot = Split-Path -Parent $PSScriptRoot
$InstallerDir = Join-Path $ClientRoot 'windows\installer'
$ReleaseDir = Join-Path $ClientRoot 'build\windows\x64\runner\Release'
$OutputDir = Join-Path $InstallerDir 'output'
$TimestampUrl = 'http://timestamp.digicert.com'

function Resolve-Tool([string]$Name, [string[]]$Candidates) {
  $command = Get-Command $Name -ErrorAction SilentlyContinue
  if ($command) { return $command.Source }
  foreach ($candidate in $Candidates) {
    $expanded = [Environment]::ExpandEnvironmentVariables($candidate)
    if (Test-Path $expanded) { return $expanded }
  }
  throw "Required tool not found: $Name"
}

function Sign-File([string]$Path, [string]$Thumbprint) {
  & $script:SignTool sign /sha1 $Thumbprint /fd SHA256 /tr $TimestampUrl /td SHA256 $Path
  if ($LASTEXITCODE -ne 0) { throw "Signing failed: $Path" }
  & $script:SignTool verify /pa /all /v $Path
  if ($LASTEXITCODE -ne 0) { throw "Signature verification failed: $Path" }
}

$script:SignTool = Resolve-Tool 'signtool.exe' @(
  '%ProgramFiles(x86)%\Windows Kits\10\bin\10.0.26100.0\x64\signtool.exe',
  '%ProgramFiles(x86)%\Windows Kits\10\bin\10.0.22621.0\x64\signtool.exe'
)
$Iscc = Resolve-Tool 'ISCC.exe' @(
  '%ProgramFiles(x86)%\Inno Setup 6\ISCC.exe',
  '%ProgramFiles%\Inno Setup 6\ISCC.exe'
)

Push-Location $ClientRoot
try {
  flutter pub get
  flutter analyze
  flutter test --no-pub
  flutter build windows --release --build-name $Version --build-number $BuildNumber
} finally {
  Pop-Location
}

if (!(Test-Path $ReleaseDir)) { throw "Windows release bundle is missing: $ReleaseDir" }

$Thumbprint = $env:WINDOWS_CERT_THUMBPRINT
$GeneratedInternalCertificate = $false
if ($SigningMode -eq 'production' -and [string]::IsNullOrWhiteSpace($Thumbprint)) {
  throw 'Production signing is disabled until WINDOWS_CERT_THUMBPRINT is configured for a publicly trusted certificate.'
}

if ([string]::IsNullOrWhiteSpace($Thumbprint)) {
  $certificate = New-SelfSignedCertificate `
    -Type CodeSigningCert `
    -Subject 'CN=Elephant Network Internal Testing Only' `
    -CertStoreLocation 'Cert:\CurrentUser\My' `
    -HashAlgorithm SHA256 `
    -NotAfter (Get-Date).AddMonths(6)
  $Thumbprint = $certificate.Thumbprint
  $GeneratedInternalCertificate = $true
  New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null
  $CertificatePath = Join-Path $OutputDir 'ElephantNetwork-Internal-Testing.cer'
  Export-Certificate -Cert $certificate -FilePath $CertificatePath | Out-Null
  # Trust the ephemeral certificate only on the build account while signtool
  # verifies the generated artifacts. It is removed in the finally block.
  Import-Certificate -FilePath $CertificatePath `
    -CertStoreLocation 'Cert:\CurrentUser\Root' | Out-Null
}

try {
  Get-ChildItem $ReleaseDir -Recurse -File |
    Where-Object { $_.Extension -in '.exe', '.dll' } |
    ForEach-Object { Sign-File $_.FullName $Thumbprint }

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
  Sign-File $Installer $Thumbprint
  $Hash = Get-FileHash $Installer -Algorithm SHA256
  $Hash | Format-List
  Write-Host "Artifact: $Installer"
  Write-Host "Signing mode: $SigningMode"
} finally {
  if ($GeneratedInternalCertificate) {
    Remove-Item "Cert:\CurrentUser\Root\$Thumbprint" -ErrorAction SilentlyContinue
    Remove-Item "Cert:\CurrentUser\My\$Thumbprint" -ErrorAction SilentlyContinue
  }
}
