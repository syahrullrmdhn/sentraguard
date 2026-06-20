; ============================================================================
;  SentraGuard Agent — Inno Setup Installer
;  Produces: SentraGuardAgentSetup.exe
;
;  Interactive install:
;     SentraGuardAgentSetup.exe
;       -> wizard prompts for Dashboard URL + Enrollment Token
;
;  Silent / unattended install (for GPO, RMM, scripted rollout):
;     SentraGuardAgentSetup.exe /SILENT /server="https://sentraguard.example.com" /token="AGT_xxxxxxxx"
;     SentraGuardAgentSetup.exe /VERYSILENT /SUPPRESSMSGBOXES /server="..." /token="..."
;
;  Requires the compiled agent at: ..\..\agent\build\sentraguard-agent.exe
;  Build the agent first:  cd agent && VERSION=1.0.0 ./build.sh
; ============================================================================

#define AppName        "SentraGuard Agent"
#define AppVersion      "1.0.0"
#define AppPublisher    "SentraGuard"
#define AppURL          "https://github.com/syahrullrmdhn/sentraguard"
#define ServiceName     "SentraGuard Agent Service"
#define ExeName         "sentraguard-agent.exe"
#define AgentExeSource  "..\..\agent\build\sentraguard-agent.exe"

[Setup]
AppId={{8F3A2C14-7B6D-4E59-9A21-SENTRAGUARD01}
AppName={#AppName}
AppVersion={#AppVersion}
AppPublisher={#AppPublisher}
AppPublisherURL={#AppURL}
AppSupportURL={#AppURL}
DefaultDirName={autopf}\SentraGuard\Agent
DefaultGroupName=SentraGuard
DisableProgramGroupPage=yes
OutputDir=..\..\dist
OutputBaseFilename=SentraGuardAgentSetup
Compression=lzma2/max
SolidCompression=yes
; Agent talks to a 64-bit world and the service runs as LocalSystem.
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
; Service install/uninstall requires elevation.
PrivilegesRequired=admin
WizardStyle=modern
UninstallDisplayName={#AppName}
UninstallDisplayIcon={app}\{#ExeName}

[Languages]
Name: "en"; MessagesFile: "compiler:Default.isl"

[Files]
Source: "{#AgentExeSource}"; DestDir: "{app}"; Flags: ignoreversion

[Icons]
Name: "{group}\SentraGuard Agent (status)"; Filename: "{app}\{#ExeName}"; Parameters: "status"; Comment: "Show agent service status"
Name: "{group}\Uninstall SentraGuard Agent"; Filename: "{uninstallexe}"

[Run]
; Post-install: register with dashboard + install the Windows Service.
; Uses the values gathered by the wizard (or /server= /token= in silent mode).
Filename: "{app}\{#ExeName}"; \
  Parameters: "install --server ""{code:GetServerURL}"" --token ""{code:GetToken}"""; \
  StatusMsg: "Registering agent and installing the Windows Service..."; \
  Flags: runhidden waituntilterminated

[UninstallRun]
; Stop + remove the Windows Service before files are deleted.
Filename: "{app}\{#ExeName}"; Parameters: "uninstall"; Flags: runhidden waituntilterminated; RunOnceId: "RemoveSentraGuardService"

[Code]
var
  ConfigPage: TInputQueryWizardPage;
  CmdServerURL: String;
  CmdToken: String;

{ --- read /server= and /token= from the command line (silent installs) --- }
function GetCmdParam(const Name: String): String;
var
  I: Integer;
  Param, Prefix: String;
begin
  Result := '';
  Prefix := '/' + Name + '=';
  for I := 1 to ParamCount do
  begin
    Param := ParamStr(I);
    if Pos(Uppercase(Prefix), Uppercase(Param)) = 1 then
    begin
      Result := Copy(Param, Length(Prefix) + 1, MaxInt);
      Exit;
    end;
  end;
end;

function InitializeSetup(): Boolean;
begin
  CmdServerURL := GetCmdParam('server');
  CmdToken := GetCmdParam('token');
  Result := True;
end;

procedure InitializeWizard();
begin
  ConfigPage := CreateInputQueryPage(wpSelectDir,
    'SentraGuard Dashboard',
    'Connect this agent to your dashboard',
    'Enter the dashboard URL and the one-time enrollment token shown when you ' +
    'added this server in SentraGuard. The token is single-use.');
  ConfigPage.Add('Dashboard URL (https://...):', False);
  ConfigPage.Add('Enrollment Token (AGT_...):', False);

  { Prefill from command line if provided. }
  if CmdServerURL <> '' then
    ConfigPage.Values[0] := CmdServerURL;
  if CmdToken <> '' then
    ConfigPage.Values[1] := CmdToken;
end;

{ Skip the prompt page entirely when both values came in via command line. }
function ShouldSkipPage(PageID: Integer): Boolean;
begin
  Result := False;
  if (PageID = ConfigPage.ID) and (CmdServerURL <> '') and (CmdToken <> '') then
    Result := True;
end;

{ Validate the page before letting the wizard advance. }
function NextButtonClick(CurPageID: Integer): Boolean;
begin
  Result := True;
  if CurPageID = ConfigPage.ID then
  begin
    if Trim(ConfigPage.Values[0]) = '' then
    begin
      MsgBox('Dashboard URL is required.', mbError, MB_OK);
      Result := False;
      Exit;
    end;
    if Pos('http', Lowercase(Trim(ConfigPage.Values[0]))) <> 1 then
    begin
      MsgBox('Dashboard URL must start with http:// or https://', mbError, MB_OK);
      Result := False;
      Exit;
    end;
    if Trim(ConfigPage.Values[1]) = '' then
    begin
      MsgBox('Enrollment token is required.', mbError, MB_OK);
      Result := False;
      Exit;
    end;
  end;
end;

{ Resolvers used by the [Run] section. Command line wins; otherwise the wizard. }
function GetServerURL(Param: String): String;
begin
  if CmdServerURL <> '' then
    Result := CmdServerURL
  else
    Result := Trim(ConfigPage.Values[0]);
end;

function GetToken(Param: String): String;
begin
  if CmdToken <> '' then
    Result := CmdToken
  else
    Result := Trim(ConfigPage.Values[1]);
end;

{ Guard: in silent mode, fail fast if values are missing. }
function PrepareToInstall(var NeedsRestart: Boolean): String;
begin
  Result := '';
  if (GetServerURL('') = '') or (GetToken('') = '') then
    Result := 'Missing --server / --token. In silent mode pass ' +
              '/server="https://..." /token="AGT_...".';
end;
