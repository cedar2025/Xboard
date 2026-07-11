# Windows x64 release

The Windows client is built and packaged only on Windows 10/11 x64 with Visual
Studio 2022 Desktop development with C++, Flutter, Inno Setup 6, and the Windows
SDK signing tools installed.

## Internal build

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\build_windows_release.ps1 -Version 1.5.0 -BuildNumber 10500 -SigningMode internal
```

The script creates a six-month self-signed code-signing certificate and exports
its public certificate next to the installer. Testers must import that `.cer`
manually before installing. The installer never adds a root certificate and an
internal artifact must not be uploaded to the public download page.

## Production build

Configure `WINDOWS_CERT_THUMBPRINT` with a publicly trusted code-signing
certificate available to the release account, then run:

```powershell
$env:WINDOWS_CERT_THUMBPRINT = 'CERTIFICATE_THUMBPRINT'
.\scripts\build_windows_release.ps1 -Version 1.5.0 -BuildNumber 10500 -SigningMode production
```

Production mode fails closed when the thumbprint is absent. The script signs
and verifies every distributed EXE/DLL before packaging, signs the installer,
and prints the final SHA-256 hash. Publish the resulting artifact with
`app_key=elephant-route-desktop`, `platform=windows`, `arch=x64`.

## Manual verification

```powershell
Get-AuthenticodeSignature .\windows\installer\output\ElephantNetwork-Setup-x64-v1.5.0.exe
signtool verify /pa /all /v .\windows\installer\output\ElephantNetwork-Setup-x64-v1.5.0.exe
sc.exe query ElephantNetworkService
```

Verify fresh install, standard-user TUN connection without a second UAC prompt,
upgrade while disconnected, upgrade while connected, data-retaining uninstall,
default data-deleting uninstall, and absence of the app/service/core processes
after uninstall.
