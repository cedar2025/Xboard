#ifndef AppVersion
  #define AppVersion "1.5.0"
#endif
#ifndef AppBuild
  #define AppBuild "10500"
#endif
#ifndef SourceDir
  #define SourceDir "..\..\build\windows\x64\runner\Release"
#endif

[Setup]
AppId={{5F1D7A6E-2B3C-4A91-9D74-E0C8F6B1A245}
AppName=大象网络
AppVersion={#AppVersion}
AppVerName=大象网络 {#AppVersion}
VersionInfoVersion={#AppVersion}.{#AppBuild}
VersionInfoProductVersion={#AppVersion}.{#AppBuild}
AppPublisher=Elephant Network
AppPublisherURL=https://elephantroute.com
AppSupportURL=https://elephantroute.com
AppUpdatesURL=https://elephantroute.com
DefaultDirName={autopf}\ElephantNetwork
DefaultGroupName=大象网络
DisableProgramGroupPage=yes
OutputDir=output
OutputBaseFilename=ElephantNetwork-Setup-x64-v{#AppVersion}
SetupIconFile=..\runner\resources\app_icon.ico
UninstallDisplayIcon={app}\ElephantNetwork.exe
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
CloseApplications=yes
RestartApplications=yes
AppMutex=ElephantNetwork_SingleInstance_Mutex
ChangesAssociations=no
MinVersion=10.0.17763

[Files]
Source: "{#SourceDir}\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "MicrosoftEdgeWebview2Setup.exe"; DestDir: "{tmp}"; Flags: deleteafterinstall skipifsourcedoesntexist

[Icons]
Name: "{group}\大象网络"; Filename: "{app}\ElephantNetwork.exe"
Name: "{autodesktop}\大象网络"; Filename: "{app}\ElephantNetwork.exe"; Tasks: desktopicon

[Tasks]
Name: "desktopicon"; Description: "创建桌面快捷方式"; GroupDescription: "快捷方式："; Flags: unchecked

[Run]
Filename: "{sys}\sc.exe"; Parameters: "stop ElephantNetworkService"; Flags: runhidden waituntilterminated; Check: ServiceExists
Filename: "{sys}\sc.exe"; Parameters: "config ElephantNetworkService binPath= ""{app}\ElephantNetworkService.exe"" start= auto DisplayName= ""Elephant Network TUN Service"""; Flags: runhidden waituntilterminated; Check: ServiceExists
Filename: "{sys}\sc.exe"; Parameters: "create ElephantNetworkService binPath= ""{app}\ElephantNetworkService.exe"" start= auto DisplayName= ""Elephant Network TUN Service"""; Flags: runhidden waituntilterminated; Check: ServiceMissing
Filename: "{sys}\sc.exe"; Parameters: "description ElephantNetworkService ""Manages the Elephant Network sing-box TUN runtime."""; Flags: runhidden waituntilterminated
Filename: "{sys}\sc.exe"; Parameters: "config ElephantNetworkService start= delayed-auto"; Flags: runhidden waituntilterminated
Filename: "{sys}\sc.exe"; Parameters: "start ElephantNetworkService"; Flags: runhidden waituntilterminated
Filename: "{tmp}\MicrosoftEdgeWebview2Setup.exe"; Parameters: "/silent /install"; StatusMsg: "正在安装 Microsoft Edge WebView2 Runtime..."; Flags: waituntilterminated runhidden; Check: NeedsWebView2
Filename: "{app}\ElephantNetwork.exe"; Description: "启动大象网络"; Flags: nowait postinstall skipifsilent

[UninstallRun]
Filename: "{sys}\taskkill.exe"; Parameters: "/F /IM ElephantNetwork.exe"; Flags: runhidden waituntilterminated
Filename: "{sys}\sc.exe"; Parameters: "stop ElephantNetworkService"; Flags: runhidden waituntilterminated
Filename: "{sys}\taskkill.exe"; Parameters: "/F /IM sing-box-windows-amd64.exe"; Flags: runhidden waituntilterminated
Filename: "{sys}\taskkill.exe"; Parameters: "/F /IM ElephantNetworkService.exe"; Flags: runhidden waituntilterminated
Filename: "{sys}\sc.exe"; Parameters: "delete ElephantNetworkService"; Flags: runhidden waituntilterminated

[UninstallDelete]
Type: filesandordirs; Name: "{commonappdata}\ElephantNetwork"

[Code]
var
  RemoveUserData: Boolean;

function ServiceExists: Boolean;
begin
  Result := RegKeyExists(HKLM64,
    'SYSTEM\CurrentControlSet\Services\ElephantNetworkService');
end;

function ServiceMissing: Boolean;
begin
  Result := not ServiceExists;
end;

function PrepareToInstall(var NeedsRestart: Boolean): String;
var
  ResultCode: Integer;
  Attempt: Integer;
begin
  Result := '';
  if not ServiceExists then
    exit;

  Exec(ExpandConstant('{sys}\sc.exe'), 'stop ElephantNetworkService', '',
    SW_HIDE, ewWaitUntilTerminated, ResultCode);
  for Attempt := 1 to 20 do
  begin
    if not ServiceExists then
      break;
    Exec(ExpandConstant('{sys}\sc.exe'), 'query ElephantNetworkService', '',
      SW_HIDE, ewWaitUntilTerminated, ResultCode);
    if ResultCode <> 0 then
      break;
    Sleep(250);
  end;
  Exec(ExpandConstant('{sys}\taskkill.exe'),
    '/F /IM sing-box-windows-amd64.exe', '', SW_HIDE,
    ewWaitUntilTerminated, ResultCode);
end;

function NeedsWebView2: Boolean;
var
  Version: String;
begin
  Result := not (
    RegQueryStringValue(HKLM64,
      'SOFTWARE\Microsoft\EdgeUpdate\Clients\{F1E7E1C8-18D3-49BB-8DA4-AE1D5FCA7AB7}',
      'pv', Version) or
    RegQueryStringValue(HKLM32,
      'SOFTWARE\Microsoft\EdgeUpdate\Clients\{F1E7E1C8-18D3-49BB-8DA4-AE1D5FCA7AB7}',
      'pv', Version) or
    RegQueryStringValue(HKCU,
      'SOFTWARE\Microsoft\EdgeUpdate\Clients\{F1E7E1C8-18D3-49BB-8DA4-AE1D5FCA7AB7}',
      'pv', Version)
  );
end;

procedure RestoreOwnedLegacyProxy;
var
  ProxyServer: String;
  ProxyEnabled: Cardinal;
begin
  if RegQueryDWordValue(HKCU,
      'Software\Microsoft\Windows\CurrentVersion\Internet Settings',
      'ProxyEnable', ProxyEnabled) and (ProxyEnabled = 1) and
     RegQueryStringValue(HKCU,
      'Software\Microsoft\Windows\CurrentVersion\Internet Settings',
      'ProxyServer', ProxyServer) and
     ((CompareText(ProxyServer, '127.0.0.1:2334') = 0) or
      (CompareText(ProxyServer, 'localhost:2334') = 0)) then
  begin
    RegWriteDWordValue(HKCU,
      'Software\Microsoft\Windows\CurrentVersion\Internet Settings',
      'ProxyEnable', 0);
    RegDeleteValue(HKCU,
      'Software\Microsoft\Windows\CurrentVersion\Internet Settings',
      'ProxyServer');
  end;
end;

function InitializeUninstall: Boolean;
begin
  if UninstallSilent then
    RemoveUserData := True
  else
    RemoveUserData := MsgBox(
      '是否同时删除本机保存的账号信息、配置和日志？' + #13#10 +
      '选择“否”可在重新安装后继续使用这些数据。',
      mbConfirmation, MB_YESNO) = IDYES;
  Result := True;
end;

procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
begin
  if CurUninstallStep = usUninstall then
  begin
    RestoreOwnedLegacyProxy;
    RegDeleteValue(HKCU,
      'Software\Microsoft\Windows\CurrentVersion\Run', '大象网络');
    RegDeleteValue(HKCU,
      'Software\Microsoft\Windows\CurrentVersion\Run', 'ElephantNetwork');
    RegDeleteValue(HKCU,
      'Software\Microsoft\Windows\CurrentVersion\Run', 'elephant_network');
  end;
  if (CurUninstallStep = usPostUninstall) and RemoveUserData then
  begin
    DelTree(ExpandConstant('{userappdata}\com.elephantroute\elephant_network'),
      True, True, True);
    DelTree(ExpandConstant('{userappdata}\Elephant Network\大象网络'),
      True, True, True);
    DelTree(ExpandConstant('{localappdata}\flutter_webview_windows\ElephantNetwork'),
      True, True, True);
    DelTree(ExpandConstant('{localappdata}\ElephantNetwork'), True, True, True);
    DelTree(ExpandConstant('{userappdata}\ElephantNetwork'), True, True, True);
  end;
end;
