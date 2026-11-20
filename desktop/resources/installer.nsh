; Custom steps for the ShopApp installer (included by electron-builder, see electron-builder.yml).
;
; Delete the installer after a successful install, so ShopApp-Setup-x.y.z.exe does not pile up in
; Downloads (or in the updater's cache after an automatic update). An installer cannot delete
; itself while it runs, so a hidden helper waits until it has closed (finish page included,
; up to 30 minutes) and then deletes it.
;
; The installer is kept when:
;   - it runs from a USB stick, CD or network drive (one copy can set up several shop computers);
;   - it is started with /KEEP, e.g. ShopApp-Setup-1.2.0.exe /KEEP

!ifndef BUILD_UNINSTALLER
  !include "FileFunc.nsh"

  !macro customInstall
    Push $0
    Push $1

    ${GetParameters} $0
    ClearErrors
    ${GetOptions} $0 "/KEEP" $1
    ${If} ${Errors}
      ; 3 = DRIVE_FIXED (a local hard disk / SSD).
      ${GetRoot} "$EXEDIR" $1
      System::Call 'kernel32::GetDriveType(t "$1\") i .r1'
      ${If} $1 == 3
        ExecShell "" "$SYSDIR\cmd.exe" '/d /c for /l %i in (1,1,1800) do @(ping -n 2 127.0.0.1 >nul & del /f /q "$EXEPATH" >nul 2>&1 & if not exist "$EXEPATH" exit)' SW_HIDE
      ${EndIf}
    ${EndIf}

    Pop $1
    Pop $0
  !macroend
!endif
