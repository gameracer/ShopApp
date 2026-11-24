// Direct (ESC/POS) receipt printing.
//
// Windows thermal printer drivers often print blank paper when Chromium prints to them silently.
// Instead, the receipt page is drawn as a 1-bit picture at the printer's own resolution
// (576 dots = 72 mm on 80 mm rolls, 384 dots = 48 mm on 58 mm rolls, at 203 dpi), turned into
// ESC/POS raster commands, and sent to the printer as a RAW spooler job, which skips the
// driver's drawing step. Almost every 58/80 mm thermal printer understands these commands.

const { BrowserWindow } = require('electron');
const { execFile } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const DOTS = { 58: 384, 80: 576 };
const BAND_ROWS = 255; // rows per raster command; small enough for any printer's buffer
const INK_THRESHOLD = 170; // 0-255: lighter than this is paper. High, so thin text stays solid.

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * Draw a receipt page as a 1-bit image exactly `dots` wide.
 * Resolves to { width, height, rows: Buffer } with 1 bit per dot, 1 = black, rows padded to bytes.
 */
async function renderReceipt(url) {
    const win = new BrowserWindow({
        show: false, width: DOTS[80], height: 400, useContentSize: true,
        webPreferences: { offscreen: true, contextIsolation: true, sandbox: true, nodeIntegration: false, backgroundThrottling: false },
    });
    // Offscreen windows hand over each finished frame through 'paint'.
    let frame = null;
    win.webContents.on('paint', (_event, _dirty, image) => { frame = image; });
    try {
        await win.loadURL(url);
        const page = await win.webContents.executeJavaScript(`
            (document.fonts ? document.fonts.ready : Promise.resolve())
                .then(() => ({ paper: document.body?.dataset.paper, width: document.body?.getBoundingClientRect().width }))`);
        if (!page.paper || !page.width) throw new Error('The receipt could not be opened.');
        const dots = DOTS[page.paper] ?? DOTS[80];
        const cssWidth = page.width;
        win.setContentSize(dots, 400);

        // Scale so the receipt's printable width fills the printer's dots exactly. CSS zoom on this
        // page only: Electron's setZoomFactor applies to every window of the same site, so it
        // would zoom the till screen too.
        // A page zoom the user set for the app (Ctrl +/-) also applies here: compensate, don't change it.
        const pageZoom = win.webContents.getZoomFactor() || 1;
        const zoom = dots / (cssWidth * pageZoom);
        const cssHeight = await win.webContents.executeJavaScript('Math.ceil(document.body.getBoundingClientRect().bottom)');
        await win.webContents.executeJavaScript(`document.documentElement.style.zoom = '${zoom}'`);
        const height = Math.ceil(cssHeight * zoom * pageZoom);
        win.setContentSize(dots, height);

        // Wait for a frame drawn at the new size.
        let image = null;
        for (let tries = 0; tries < 40 && !image; tries++) {
            frame = null;
            win.webContents.invalidate();
            await wait(100);
            if (frame && !frame.isEmpty() && Math.abs(frame.getSize().height / frame.getSize().width - height / dots) < 0.02) image = frame;
        }
        if (!image) throw new Error('The receipt could not be drawn.');
        if (image.getSize().width !== dots) image = image.resize({ width: dots, quality: 'best' });
        return toMonochrome(image.toBitmap(), image.getSize(), dots);
    } finally {
        win.destroy();
    }
}

/** BGRA bitmap -> packed 1-bit rows, blank rows trimmed from the end. */
function toMonochrome(bgra, size, dots) {
    const bytesPerRow = Math.ceil(dots / 8);
    let height = size.height;
    const rows = Buffer.alloc(bytesPerRow * height);
    let lastInked = -1;
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < Math.min(dots, size.width); x++) {
            const i = (y * size.width + x) * 4;
            const luminance = 0.114 * bgra[i] + 0.587 * bgra[i + 1] + 0.299 * bgra[i + 2];
            if (bgra[i + 3] > 0 && luminance < INK_THRESHOLD) {
                rows[y * bytesPerRow + (x >> 3)] |= 0x80 >> (x & 7);
                lastInked = y;
            }
        }
    }
    height = Math.max(1, lastInked + 1);
    return { width: dots, height, rows: rows.subarray(0, bytesPerRow * height) };
}

/** ESC/POS job: initialise, optional cash drawer pulse, raster image in bands, feed, cut. */
function escposJob(bitmap, { cashDrawer = false } = {}) {
    const bytesPerRow = Math.ceil(bitmap.width / 8);
    const parts = [Buffer.from([0x1b, 0x40])]; // ESC @ : reset
    if (cashDrawer) parts.push(Buffer.from([0x1b, 0x70, 0x00, 0x19, 0xfa])); // ESC p : open drawer (pin 2)
    for (let y = 0; y < bitmap.height; y += BAND_ROWS) {
        const rows = Math.min(BAND_ROWS, bitmap.height - y);
        // GS v 0 : print raster bit image, normal size
        parts.push(Buffer.from([0x1d, 0x76, 0x30, 0x00, bytesPerRow & 0xff, bytesPerRow >> 8, rows & 0xff, rows >> 8]));
        parts.push(bitmap.rows.subarray(y * bytesPerRow, (y + rows) * bytesPerRow));
    }
    parts.push(Buffer.from([0x1b, 0x64, 0x04])); // ESC d 4 : feed 4 lines past the cutter
    parts.push(Buffer.from([0x1d, 0x56, 0x42, 0x00])); // GS V B 0 : feed and partial cut
    return Buffer.concat(parts);
}

// Sends a file to a printer as a RAW job through the Windows spooler (winspool WritePrinter).
const RAW_PRINT_PS1 = `
$ErrorActionPreference = 'Stop'
Add-Type -TypeDefinition @"
using System;
using System.ComponentModel;
using System.Runtime.InteropServices;
public static class ShopAppRawPrint {
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    public class DOC_INFO_1 { public string pDocName; public string pOutputFile; public string pDatatype; }
    [DllImport("winspool.drv", CharSet = CharSet.Unicode, SetLastError = true)] static extern bool OpenPrinter(string name, out IntPtr handle, IntPtr defaults);
    [DllImport("winspool.drv", SetLastError = true)] static extern bool ClosePrinter(IntPtr handle);
    [DllImport("winspool.drv", CharSet = CharSet.Unicode, SetLastError = true)] static extern int StartDocPrinter(IntPtr handle, int level, [In] DOC_INFO_1 info);
    [DllImport("winspool.drv", SetLastError = true)] static extern bool EndDocPrinter(IntPtr handle);
    [DllImport("winspool.drv", SetLastError = true)] static extern bool StartPagePrinter(IntPtr handle);
    [DllImport("winspool.drv", SetLastError = true)] static extern bool EndPagePrinter(IntPtr handle);
    [DllImport("winspool.drv", SetLastError = true)] static extern bool WritePrinter(IntPtr handle, byte[] data, int length, out int written);
    public static void Send(string printer, string document, byte[] data) {
        IntPtr handle;
        if (!OpenPrinter(printer, out handle, IntPtr.Zero)) throw new Win32Exception();
        try {
            if (StartDocPrinter(handle, 1, new DOC_INFO_1 { pDocName = document, pDatatype = "RAW" }) == 0) throw new Win32Exception();
            try {
                StartPagePrinter(handle);
                int written;
                if (!WritePrinter(handle, data, data.Length, out written) || written != data.Length) throw new Win32Exception();
                EndPagePrinter(handle);
            } finally { EndDocPrinter(handle); }
        } finally { ClosePrinter(handle); }
    }
}
"@
[ShopAppRawPrint]::Send($env:SHOPAPP_PRINTER, $env:SHOPAPP_DOCUMENT, [IO.File]::ReadAllBytes($env:SHOPAPP_FILE))
`;

/** Send bytes to a Windows printer as a RAW job. */
async function sendRaw(printerName, data, documentName = 'ShopApp receipt') {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'shopapp-print-'));
    const script = path.join(dir, 'raw-print.ps1');
    const file = path.join(dir, 'receipt.bin');
    fs.writeFileSync(script, RAW_PRINT_PS1);
    fs.writeFileSync(file, data);
    try {
        await new Promise((resolve, reject) => {
            execFile('powershell.exe', ['-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', script], {
                windowsHide: true,
                timeout: 30000,
                // Names travel in environment variables, never inside the script text.
                env: { ...process.env, SHOPAPP_PRINTER: printerName, SHOPAPP_DOCUMENT: documentName, SHOPAPP_FILE: file },
            }, (error, stdout, stderr) => {
                if (!error) return resolve();
                const reason = (stderr || error.message).split(/\r?\n/).find((l) => l.trim() && !l.startsWith('At ')) ?? error.message;
                reject(new Error(reason.replace(/^.*?:\s*"?/, '').replace(/"\s*$/, '').trim() || 'Printing failed.'));
            });
        });
    } finally {
        fs.rmSync(dir, { recursive: true, force: true });
    }
}

/** Draw a receipt URL and print it on a thermal printer with ESC/POS. */
async function printEscpos(url, printerName, options = {}) {
    const bitmap = await renderReceipt(url);
    const job = escposJob(bitmap, options);
    await sendRaw(printerName, job, options.documentName);
    return { width: bitmap.width, height: bitmap.height, bytes: job.length };
}

module.exports = { printEscpos, renderReceipt, escposJob, sendRaw };
