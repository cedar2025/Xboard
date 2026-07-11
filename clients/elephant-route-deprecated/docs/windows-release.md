# Windows x64 release

The Windows client is built and packaged only on Windows 10/11 x64 with Visual
Studio 2022 Desktop development with C++, Flutter, and Inno Setup 6.

## Production capabilities

The production package installs the desktop application, the bundled sing-box
core, and `ElephantNetworkService`. The service provides local IPC for TUN mode
so a standard user can connect without approving a second UAC prompt. The app
also supports system proxy mode, tray controls, optional launch at startup, and
in-app update checks.

The installer supports fresh installation, in-place upgrades, and uninstall.
Uninstall stops the app, service, and core processes and restores only legacy
proxy settings owned by Elephant Network. Users can choose whether application
data is retained.

## Build

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\build_windows_release.ps1 -Version 1.5.0 -BuildNumber 10500
```

The application binaries, Windows service, and installer are intentionally
distributed without an Authenticode code signature. The build does not create,
import, or require a certificate. Windows may display an unknown-publisher or
SmartScreen warning during installation.

The downloaded Microsoft WebView2 bootstrapper is still required to have a
valid Microsoft signature. This uses Microsoft's certificate and does not
require an Elephant Network certificate.

The script prints the installer SHA-256 hash. Publish the resulting artifact
with `app_key=elephant-route-desktop`, `platform=windows`, `arch=x64`, and the
exact SHA-256 value. The update client rejects a downloaded artifact when its
hash does not match the release metadata.

## Publish checklist

1. Build the installer with the production version and build number.
2. Record the SHA-256 value printed by the build script.
3. Upload the `.exe` to the application distribution service.
4. Publish matching Windows x64 release metadata, including the exact hash.
5. Download and install through the same public URL used by the update client.
6. Complete the manual verification scenarios below before announcing the release.

## Manual verification

```powershell
Get-FileHash .\windows\installer\output\ElephantNetwork-Setup-x64-v1.5.0.exe -Algorithm SHA256
sc.exe query ElephantNetworkService
```

Verify fresh install, standard-user TUN connection without a second UAC prompt,
upgrade while disconnected, upgrade while connected, data-retaining uninstall,
default data-deleting uninstall, and absence of the app/service/core processes
after uninstall.
