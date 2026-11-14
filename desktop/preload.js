// Bridge between the ShopApp pages and the desktop shell, exposed as window.shopDevice.
// Used by resources/js/devices.js (silent receipt printing) and Settings (printer, customer display).

const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('shopDevice', {
    /** { printers: [{ name, displayName, isDefault }], selected: name|null, detected: displayName|null } */
    printers: () => ipcRenderer.invoke('devices:printers'),
    /** Save the receipt printer for this computer (null = automatic). */
    setPrinter: (name) => ipcRenderer.invoke('devices:set-printer', name),
    /** Print a receipt page of this app without a dialog. Resolves to { ok, printer?, error? }. */
    printReceipt: (url) => ipcRenderer.invoke('devices:print-receipt', url),
    /** { displays: [{ id, label, primary, till }], selected: 'auto'|'off'|id, active } */
    displays: () => ipcRenderer.invoke('devices:displays'),
    /** Customer display for this computer: 'auto', 'off' or a screen id. */
    setDisplay: (value) => ipcRenderer.invoke('devices:set-display', value),
});
