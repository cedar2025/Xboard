import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Windows installer has stable identity and lifecycle cleanup', () {
    final installer =
        File('windows/installer/ElephantNetwork.iss').readAsStringSync();

    expect(
      installer,
      contains('AppId={{5F1D7A6E-2B3C-4A91-9D74-E0C8F6B1A245}'),
    );
    expect(installer, contains('PrivilegesRequired=admin'));
    expect(installer, contains('ElephantNetworkService'));
    expect(installer, contains('MicrosoftEdgeWebview2Setup.exe'));
    expect(installer, contains('function PrepareToInstall'));
    expect(installer, contains('function InitializeUninstall'));
    expect(installer, contains('RemoveUserData := True'));
    expect(installer, contains('RestoreOwnedLegacyProxy'));
    expect(installer, contains('/IM sing-box-windows-amd64.exe'));
  });

  test('release script builds unsigned artifacts and preserves integrity checks',
      () {
    final script = File('scripts/build_windows_release.ps1').readAsStringSync();

    expect(script, isNot(contains('WINDOWS_CERT_THUMBPRINT')));
    expect(script, isNot(contains('New-SelfSignedCertificate')));
    expect(script, isNot(contains('signtool.exe')));
    expect(script, contains('Get-AuthenticodeSignature'));
    expect(script, contains('MicrosoftEdgeWebview2Setup.exe'));
    expect(script, contains(r'Get-FileHash $Installer -Algorithm SHA256'));
  });

  test('native service is constrained to local IPC and the bundled core', () {
    final header = File('windows/common/windows_protocol.h').readAsStringSync();
    final service = File('windows/service/service_main.cpp').readAsStringSync();

    expect(header, contains('kPipeName'));
    expect(header, contains('ElephantNetworkService.v1'));
    expect(header, contains('kMaxConfigBytes = 4 * 1024 * 1024'));
    expect(service, contains('PIPE_REJECT_REMOTE_CLIENTS'));
    expect(
      service,
      contains(r'D:P(A;;GA;;;SY)(A;;GA;;;BA)(A;;GRGW;;;IU)'),
    );
    expect(service, contains('sing-box-windows-amd64.exe'));
    expect(service, isNot(contains('powershell')));
  });
}
