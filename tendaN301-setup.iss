[Setup]
AppName=tendaN301-billing
AppVersion=1.0
DefaultDirName={pf}\tendaN301-billing
DefaultGroupName=tendaN301-billing
OutputBaseFilename=tendaN301-setup
Compression=lzma
SolidCompression=yes
PrivilegesRequired=admin
WizardStyle=modern
Uninstallable=yes

[Files]
; Copy the full PHP project folder
Source: "tendaN301-billing\*"; DestDir: "{app}"; Flags: recursesubdirs
; Copy portable PHP folder
Source: "php\*"; DestDir: "{app}\php"; Flags: recursesubdirs

[Tasks]
Name: "desktopicon"; Description: "Create a &desktop shortcut"; GroupDescription: "Additional icons:"; Flags: unchecked
Name: "installservice"; Description: "Install NSSM service to run boot.bat"; GroupDescription: "Optional actions:"; Flags: unchecked
Name: "runstack"; Description: "Run PHP stack now"; GroupDescription: "Optional actions:"; Flags: unchecked

[Run]
; Run stack install/build silently (console hidden)
Filename: "{app}\php\php.exe"; Parameters: "stack install"; WorkingDir: "{app}"; Flags: waituntilterminated runhidden
Filename: "{app}\php\php.exe"; Parameters: "stack build"; WorkingDir: "{app}"; Flags: waituntilterminated runhidden

; Conditional: Install NSSM service if user checked box (hidden)
Filename: "{app}\nssm.exe"; Parameters: "install tendaN301-billing ""{app}\boot.bat"""; WorkingDir: "{app}"; Tasks: installservice; Flags: waituntilterminated runhidden

; Conditional: Start NSSM service if user checked box (hidden)
Filename: "{app}\nssm.exe"; Parameters: "start tendaN301-billing"; WorkingDir: "{app}"; Tasks: installservice; Flags: waituntilterminated runhidden

; Conditional: Run PHP stack immediately if user checked box (console hidden)
Filename: "{app}\php\php.exe"; Parameters: "stack start"; WorkingDir: "{app}"; Tasks: runstack; Flags: nowait postinstall runhidden

[Icons]
; Conditional desktop shortcut
Name: "{commondesktop}\tendaN301-billing"; Filename: "explorer.exe"; Parameters: "http://127.0.0.1:8000"; IconFilename: "{app}\wifi.png"; Tasks: desktopicon
