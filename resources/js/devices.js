/**
 * Plug-and-play hardware: USB barcode scanners and thermal receipt printers.
 *
 * Barcode scanner
 *   USB / Bluetooth scanners act as a keyboard: they "type" the code very fast and
 *   press Enter. We tell a scan apart from a person typing by the speed of the keys,
 *   undo the text the scanner typed into whatever field had focus, and fire a
 *   cancelable `barcode` event on window ({ detail: { code } }).
 *   Screens handle it (POS adds to cart, stock-in adds a line, the product form fills
 *   the SKU) by calling preventDefault(). If nothing handles it, the product with that
 *   code is opened.
 *
 * Thermal printer
 *   printReceipt(url) prints a receipt page. In the ShopApp desktop app it prints
 *   silently to the receipt printer (picked in Settings, or auto-detected). In a normal
 *   browser it opens the print dialog for the receipt only.
 */

const MAX_KEY_GAP_MS = 50; // scanners send keys every 1–20 ms; people rarely type 4+ keys this fast
const MAX_ENTER_GAP_MS = 120;
const MIN_LENGTH = 4;

function config() {
    const meta = document.querySelector('meta[name="shop-devices"]');
    if (!meta) return null;
    try {
        return JSON.parse(meta.content);
    } catch {
        return null;
    }
}

// ---------------------------------------------------------------- sound

let audio = null;

export function beep(ok = true) {
    if (!config()?.beep) return;
    try {
        audio ??= new (window.AudioContext || window.webkitAudioContext)();
        const tones = ok ? [[1400, 0, 0.08]] : [[300, 0, 0.15], [220, 0.18, 0.2]];
        for (const [freq, start, length] of tones) {
            const osc = audio.createOscillator();
            const gain = audio.createGain();
            osc.type = ok ? 'sine' : 'square';
            osc.frequency.value = freq;
            gain.gain.value = 0.15;
            osc.connect(gain).connect(audio.destination);
            osc.start(audio.currentTime + start);
            osc.stop(audio.currentTime + start + length);
        }
    } catch {
        /* no audio device: ignore */
    }
}

// ---------------------------------------------------------------- scanner

function isEditable(el) {
    return el && (el.tagName === 'TEXTAREA' || (el.tagName === 'INPUT' && !['checkbox', 'radio', 'button', 'submit'].includes(el.type)) || el.isContentEditable);
}

export function startScanner() {
    const cfg = config();
    if (!cfg) return; // not signed in (login / setup pages)

    let buffer = '';
    let last = 0;
    let field = null;
    let fieldValue = '';

    document.addEventListener('keydown', (e) => {
        const now = performance.now();

        if (e.key === 'Enter' || e.key === 'Tab') {
            const isScan = buffer.length >= MIN_LENGTH && now - last <= MAX_ENTER_GAP_MS;
            const code = buffer;
            buffer = '';
            if (!isScan) return;

            // Stop the Enter from submitting forms / picking search results.
            e.preventDefault();
            e.stopImmediatePropagation();

            // Undo what the scanner typed into the focused field.
            if (field && isEditable(field) && field === document.activeElement && field.value !== undefined) {
                field.value = fieldValue;
                field.dispatchEvent(new Event('input', { bubbles: true }));
            }
            dispatch(code.trim(), cfg);
            return;
        }

        if (e.key === 'Shift') return; // scanners press Shift for capitals
        if (e.key.length !== 1 || e.ctrlKey || e.altKey || e.metaKey) {
            buffer = '';
            return;
        }

        if (now - last > MAX_KEY_GAP_MS) {
            // Possible start of a scan: remember the focused field before this key lands in it.
            buffer = '';
            field = document.activeElement;
            fieldValue = isEditable(field) ? field.value : '';
        }
        buffer += e.key;
        last = now;
    }, true);
}

function dispatch(code, cfg) {
    if (!code) return;
    const event = new CustomEvent('barcode', { detail: { code }, cancelable: true });
    window.dispatchEvent(event);
    if (event.defaultPrevented) return;

    // Nobody on this screen wants it: open the product with this barcode.
    beep(true);
    window.location = `${cfg.lookupUrl}?code=${encodeURIComponent(code)}`;
}

/** JSON lookup of a product by exact barcode / SKU. Resolves to the product or null. */
export async function lookupBarcode(code) {
    const cfg = config();
    const res = await fetch(`${cfg.lookupUrl}?code=${encodeURIComponent(code)}`, {
        headers: { Accept: 'application/json' },
    });
    return res.ok ? res.json() : null;
}

// ---------------------------------------------------------------- printer

/** The ShopApp desktop bridge (desktop/preload.js), or null in a normal browser. */
export const desktop = window.shopDevice ?? null;

/**
 * Print a receipt page. Resolves to { ok, printer?, error? }.
 */
export async function printReceipt(url) {
    if (desktop) {
        try {
            return await desktop.printReceipt(new URL(url, window.location.href).href);
        } catch (e) {
            return { ok: false, error: e?.message ?? String(e) };
        }
    }

    // Browser: print the receipt page alone through a hidden iframe.
    return new Promise((resolve) => {
        const frame = document.createElement('iframe');
        frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
        frame.src = url;
        frame.onload = () => {
            const win = frame.contentWindow;
            const cleanup = () => setTimeout(() => frame.remove(), 500);
            win.addEventListener('afterprint', cleanup, { once: true });
            win.focus();
            win.print();
            setTimeout(cleanup, 60000);
            resolve({ ok: true });
        };
        frame.onerror = () => resolve({ ok: false });
        document.body.appendChild(frame);
    });
}

window.ShopDevices = { beep, printReceipt, lookupBarcode, desktop };
